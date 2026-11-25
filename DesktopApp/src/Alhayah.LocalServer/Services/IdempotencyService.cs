using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Hubs;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;

namespace Alhayah.LocalServer.Services;

public sealed record LocalOperationResult(
    [property: JsonPropertyName("operation_id")] Guid OperationId,
    [property: JsonPropertyName("operation_type")] string OperationType,
    [property: JsonPropertyName("status")] LocalOperationStatus Status,
    [property: JsonPropertyName("entity_id")] Guid? EntityId,
    [property: JsonPropertyName("row_version")] long? RowVersion,
    [property: JsonPropertyName("conflict_type")] string? ConflictType,
    [property: JsonPropertyName("expires_at")] DateTimeOffset ExpiresAtUtc);

public sealed record LocalBusinessResult(Guid? EntityId, long? RowVersion, string? ConflictType)
{
    public static LocalBusinessResult Completed(Guid entityId, long rowVersion) => new(entityId, rowVersion, null);

    public static LocalBusinessResult Conflict(Guid? entityId, long? rowVersion, string conflictType) =>
        new(entityId, rowVersion, conflictType);
}

public sealed record IdempotencyRetentionWarning(
    Guid OperationId,
    DateTimeOffset ExpiresAtUtc,
    int RemainingDays);

public sealed record ClientIdentityMappingView(
    string EntityType,
    Guid ClientUuid,
    string? CanonicalId,
    bool IsDeleted);

public sealed class IdempotencyMismatchException() : Exception("The operation id or idempotency key was already used for a different request.");

public sealed class IdempotencyService(
    LocalDbContext dbContext,
    TimeProvider timeProvider,
    ILocalSyncNotifier notifier,
    ConflictService conflictService)
{
    public const int RetentionDays = 90;
    public const int RetentionWarningDays = 7;

    private static readonly JsonSerializerOptions RequestSerializerOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase
    };

    private readonly LocalDbContext _dbContext = dbContext ?? throw new ArgumentNullException(nameof(dbContext));
    private readonly TimeProvider _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
    private readonly ILocalSyncNotifier _notifier = notifier ?? throw new ArgumentNullException(nameof(notifier));
    private readonly ConflictService _conflictService = conflictService ?? throw new ArgumentNullException(nameof(conflictService));

    public async Task<LocalOperationResult> ExecuteAsync(
        Guid operationId,
        string? idempotencyKey,
        string deviceId,
        string operationType,
        SyncEnvelope envelope,
        Func<LocalDbContext, SyncEnvelope, CancellationToken, Task<LocalBusinessResult>> businessAction,
        CancellationToken cancellationToken = default)
    {
        ValidateRequest(operationId, idempotencyKey, deviceId, operationType, envelope, businessAction);
        string requestJson = JsonSerializer.Serialize(envelope, RequestSerializerOptions);
        string requestHash = ComputeRequestHash(operationId, idempotencyKey, deviceId, operationType, requestJson);

        try
        {
            return await ExecuteCoreAsync(
                operationId,
                idempotencyKey,
                deviceId,
                operationType,
                requestJson,
                requestHash,
                businessAction,
                cancellationToken).ConfigureAwait(false);
        }
        catch (IdempotencyClaimCollisionException)
        {
            _dbContext.ChangeTracker.Clear();
            IdempotencyRecord? winner = await _dbContext.IdempotencyRecords
                .AsNoTracking()
                .SingleOrDefaultAsync(item => item.OperationId == operationId, cancellationToken)
                .ConfigureAwait(false);
            if (winner is null && idempotencyKey is not null)
            {
                winner = await _dbContext.IdempotencyRecords
                    .AsNoTracking()
                    .SingleOrDefaultAsync(
                        item => item.DeviceId == deviceId && item.IdempotencyKey == idempotencyKey,
                        cancellationToken)
                    .ConfigureAwait(false);
            }

            if (winner is null)
            {
                throw;
            }

            EnsureMatchingRequest(winner, operationId, requestHash);
            return DeserializeStoredResult(winner);
        }
    }

    public async Task<LocalOperationResult?> GetResultAsync(
        Guid operationId,
        CancellationToken cancellationToken = default)
    {
        IdempotencyRecord? record = await _dbContext.IdempotencyRecords
            .AsNoTracking()
            .SingleOrDefaultAsync(item => item.OperationId == operationId, cancellationToken)
            .ConfigureAwait(false);
        return record?.ResultJson is null ? null : DeserializeStoredResult(record);
    }

    public async Task RegisterClientUuidAsync(
        string entityType,
        Guid clientUuid,
        string? canonicalId = null,
        CancellationToken cancellationToken = default)
    {
        ValidateIdentity(entityType, clientUuid);
        ClientIdentityMappingEntity? mapping = await _dbContext.ClientIdentities
            .SingleOrDefaultAsync(item => item.ClientUuid == clientUuid, cancellationToken)
            .ConfigureAwait(false);
        if (mapping is null)
        {
            DateTimeOffset now = _timeProvider.GetUtcNow();
            _dbContext.ClientIdentities.Add(new ClientIdentityMappingEntity
            {
                Id = Guid.NewGuid(),
                EntityType = entityType,
                ClientUuid = clientUuid,
                CreatedAtUtc = now,
                UpdatedAtUtc = now
            });
            await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        }
        else if (!string.Equals(mapping.EntityType, entityType, StringComparison.Ordinal))
        {
            throw new InvalidOperationException("The client UUID is already assigned to another entity type.");
        }

        if (canonicalId is not null)
        {
            await ResolveCanonicalIdAsync(entityType, clientUuid, canonicalId, cancellationToken).ConfigureAwait(false);
        }
    }

    public async Task ResolveCanonicalIdAsync(
        string entityType,
        Guid clientUuid,
        string canonicalId,
        CancellationToken cancellationToken = default)
    {
        ValidateIdentity(entityType, clientUuid);
        ArgumentException.ThrowIfNullOrWhiteSpace(canonicalId);
        await using IDbContextTransaction transaction = await _dbContext.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        ClientIdentityMappingEntity mapping = await _dbContext.ClientIdentities
            .SingleOrDefaultAsync(item => item.ClientUuid == clientUuid, cancellationToken)
            .ConfigureAwait(false)
            ?? throw new KeyNotFoundException("The client UUID must be registered before canonical resolution.");
        EnsureIdentityType(mapping, entityType);

        if (mapping.CanonicalId is not null &&
            !string.Equals(mapping.CanonicalId, canonicalId, StringComparison.Ordinal))
        {
            throw new InvalidOperationException("The client UUID already has a different canonical identifier.");
        }

        bool canonicalInUse = await _dbContext.ClientIdentities.AnyAsync(
            item => item.ClientUuid != clientUuid &&
                item.EntityType == entityType &&
                item.CanonicalId == canonicalId,
            cancellationToken).ConfigureAwait(false);
        if (canonicalInUse)
        {
            throw new InvalidOperationException("The canonical identifier is already mapped to another client UUID.");
        }

        mapping.CanonicalId = canonicalId;
        mapping.UpdatedAtUtc = _timeProvider.GetUtcNow();
        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);

        ClientIdentityMap identityMap = await BuildIdentityMapAsync(cancellationToken).ConfigureAwait(false);
        List<IdempotencyRecord> pending = await _dbContext.IdempotencyRecords
            .Where(item => item.Status == LocalOperationStatus.Pending)
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        foreach (IdempotencyRecord record in pending)
        {
            SyncEnvelope envelope = DeserializeEnvelope(record.RequestJson);
            if (!ContainsClientUuid(envelope, clientUuid))
            {
                continue;
            }

            SyncEnvelope resolved = identityMap.ResolvePendingReferences(envelope);
            record.RequestJson = JsonSerializer.Serialize(resolved, RequestSerializerOptions);
        }

        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
    }

    public async Task MarkClientUuidDeletedAsync(
        string entityType,
        Guid clientUuid,
        CancellationToken cancellationToken = default)
    {
        ValidateIdentity(entityType, clientUuid);
        ClientIdentityMappingEntity mapping = await _dbContext.ClientIdentities
            .SingleOrDefaultAsync(item => item.ClientUuid == clientUuid, cancellationToken)
            .ConfigureAwait(false)
            ?? throw new KeyNotFoundException("The client UUID is not registered.");
        EnsureIdentityType(mapping, entityType);
        mapping.IsDeleted = true;
        mapping.UpdatedAtUtc = _timeProvider.GetUtcNow();
        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
    }

    public async Task<ClientIdentityMappingView?> GetClientIdentityAsync(
        string entityType,
        Guid clientUuid,
        CancellationToken cancellationToken = default)
    {
        ClientIdentityMappingEntity? mapping = await _dbContext.ClientIdentities
            .AsNoTracking()
            .SingleOrDefaultAsync(
                item => item.EntityType == entityType && item.ClientUuid == clientUuid,
                cancellationToken)
            .ConfigureAwait(false);
        return mapping is null
            ? null
            : new ClientIdentityMappingView(mapping.EntityType, mapping.ClientUuid, mapping.CanonicalId, mapping.IsDeleted);
    }

    public async Task<IReadOnlyList<IdempotencyRetentionWarning>> GetRetentionWarningsAsync(
        CancellationToken cancellationToken = default)
    {
        DateTimeOffset now = _timeProvider.GetUtcNow();
        DateTimeOffset warningCutoff = now.AddDays(RetentionWarningDays);
        List<IdempotencyRecord> records = await _dbContext.IdempotencyRecords
            .AsNoTracking()
            .Where(item => item.Status != LocalOperationStatus.Pending &&
                item.ExpiresAtUtc > now &&
                item.ExpiresAtUtc <= warningCutoff)
            .OrderBy(item => item.ExpiresAtUtc)
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        return records.Select(item => new IdempotencyRetentionWarning(
            item.OperationId,
            item.ExpiresAtUtc,
            Math.Max(0, (int)Math.Ceiling((item.ExpiresAtUtc - now).TotalDays)))).ToArray();
    }

    public async Task<int> CleanupExpiredAsync(CancellationToken cancellationToken = default)
    {
        DateTimeOffset now = _timeProvider.GetUtcNow();
        return await _dbContext.IdempotencyRecords
            .Where(item => item.Status != LocalOperationStatus.Pending && item.ExpiresAtUtc <= now)
            .ExecuteDeleteAsync(cancellationToken)
            .ConfigureAwait(false);
    }

    private async Task<LocalOperationResult> ExecuteCoreAsync(
        Guid operationId,
        string? idempotencyKey,
        string deviceId,
        string operationType,
        string requestJson,
        string requestHash,
        Func<LocalDbContext, SyncEnvelope, CancellationToken, Task<LocalBusinessResult>> businessAction,
        CancellationToken cancellationToken)
    {
        IdempotencyRecord? existing = await _dbContext.IdempotencyRecords
            .SingleOrDefaultAsync(item => item.OperationId == operationId, cancellationToken)
            .ConfigureAwait(false);
        if (existing is not null)
        {
            EnsureMatchingRequest(existing, operationId, requestHash);
            if (existing.Status != LocalOperationStatus.Pending)
            {
                return DeserializeStoredResult(existing);
            }
        }

        if (idempotencyKey is not null)
        {
            bool keyUsed = await _dbContext.IdempotencyRecords.AnyAsync(
                item => item.OperationId != operationId &&
                    item.DeviceId == deviceId &&
                    item.IdempotencyKey == idempotencyKey,
                cancellationToken).ConfigureAwait(false);
            if (keyUsed)
            {
                throw new IdempotencyMismatchException();
            }
        }

        await using IDbContextTransaction transaction = await _dbContext.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        try
        {
            DateTimeOffset now = _timeProvider.GetUtcNow();
            IdempotencyRecord record = existing ?? new IdempotencyRecord
            {
                Id = Guid.NewGuid(),
                OperationId = operationId,
                IdempotencyKey = idempotencyKey,
                DeviceId = deviceId,
                OperationType = operationType,
                RequestHash = requestHash,
                RequestJson = requestJson,
                Status = LocalOperationStatus.Pending,
                CreatedAtUtc = now,
                ExpiresAtUtc = now.AddDays(RetentionDays)
            };
            if (existing is null)
            {
                _dbContext.IdempotencyRecords.Add(record);
                try
                {
                    await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
                }
                catch (DbUpdateException exception)
                {
                    throw new IdempotencyClaimCollisionException(exception);
                }
            }

            await EnsureIdentityMappingsAsync(DeserializeEnvelope(record.RequestJson), cancellationToken).ConfigureAwait(false);
            IdentityResolution resolution = await ResolveEnvelopeAsync(record.RequestJson, cancellationToken).ConfigureAwait(false);
            LocalOperationResult result;
            if (resolution.ConflictType is not null)
            {
                _conflictService.AddCriticalConflict(
                    operationId,
                    deviceId,
                    resolution.Envelope.Entity.EntityType,
                    resolution.Envelope.Entity.ClientUuid,
                    resolution.ConflictType,
                    JsonSerializer.Serialize(new { clientUuid = resolution.Envelope.Entity.ClientUuid }));
                result = new LocalOperationResult(
                    operationId,
                    operationType,
                    LocalOperationStatus.Conflict,
                    resolution.Envelope.Entity.ClientUuid,
                    null,
                    resolution.ConflictType,
                    record.ExpiresAtUtc);
            }
            else if (resolution.IsPending)
            {
                result = new LocalOperationResult(
                    operationId,
                    operationType,
                    LocalOperationStatus.Pending,
                    null,
                    null,
                    null,
                    record.ExpiresAtUtc);
            }
            else
            {
                record.RequestJson = JsonSerializer.Serialize(resolution.Envelope, RequestSerializerOptions);
                LocalBusinessResult businessResult = await businessAction(
                    _dbContext,
                    resolution.Envelope,
                    cancellationToken).ConfigureAwait(false);
                bool isConflict = businessResult.ConflictType is not null;
                result = new LocalOperationResult(
                    operationId,
                    operationType,
                    isConflict ? LocalOperationStatus.Conflict : LocalOperationStatus.Completed,
                    businessResult.EntityId,
                    businessResult.RowVersion,
                    businessResult.ConflictType,
                    record.ExpiresAtUtc);
            }

            record.Status = result.Status;
            record.ResultJson = JsonSerializer.Serialize(result, RequestSerializerOptions);
            await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
            await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
            await NotifyWithoutInvalidatingCommitAsync(result, cancellationToken).ConfigureAwait(false);
            return result;
        }
        catch
        {
            await transaction.RollbackAsync(CancellationToken.None).ConfigureAwait(false);
            throw;
        }
    }

    private async Task EnsureIdentityMappingsAsync(
        SyncEnvelope envelope,
        CancellationToken cancellationToken)
    {
        List<(string EntityType, Guid ClientUuid)> required = new()
        {
            (envelope.Entity.EntityType, envelope.Entity.ClientUuid)
        };
        required.AddRange(envelope.Entity.References
            .Where(reference => reference.TargetClientUuid.HasValue)
            .Select(reference => (reference.TargetEntityType, reference.TargetClientUuid!.Value)));

        bool added = false;
        foreach ((string entityType, Guid clientUuid) in required.Distinct())
        {
            bool exists = await _dbContext.ClientIdentities.AnyAsync(
                item => item.ClientUuid == clientUuid,
                cancellationToken).ConfigureAwait(false);
            if (exists)
            {
                continue;
            }

            DateTimeOffset now = _timeProvider.GetUtcNow();
            _dbContext.ClientIdentities.Add(new ClientIdentityMappingEntity
            {
                Id = Guid.NewGuid(),
                EntityType = entityType,
                ClientUuid = clientUuid,
                CreatedAtUtc = now,
                UpdatedAtUtc = now
            });
            added = true;
        }

        if (added)
        {
            await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        }
    }

    private async Task<IdentityResolution> ResolveEnvelopeAsync(
        string requestJson,
        CancellationToken cancellationToken)
    {
        SyncEnvelope envelope = DeserializeEnvelope(requestJson);
        List<ClientIdentityMappingEntity> mappings = await _dbContext.ClientIdentities
            .AsNoTracking()
            .OrderBy(item => item.Id)
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        ClientIdentityMap identityMap = BuildIdentityMap(mappings);
        HashSet<Guid> requiredIds = envelope.Entity.References
            .Select(reference => reference.TargetClientUuid)
            .Where(clientUuid => clientUuid.HasValue)
            .Select(clientUuid => clientUuid!.Value)
            .ToHashSet();
        requiredIds.Add(envelope.Entity.ClientUuid);
        if (mappings.Any(item => requiredIds.Contains(item.ClientUuid) && item.IsDeleted))
        {
            return new IdentityResolution(envelope, false, ConflictClass.DeletedReference.ToString());
        }

        try
        {
            SyncEnvelope resolved = identityMap.ResolvePendingReferences(envelope);
            ClientIdentityResolution state = identityMap.Evaluate(resolved.Entity);
            return state switch
            {
                ClientIdentityResolution.Ready => new IdentityResolution(resolved, false, null),
                ClientIdentityResolution.BlockedDeletedReference => new IdentityResolution(
                    resolved,
                    false,
                    ConflictClass.DeletedReference.ToString()),
                _ => new IdentityResolution(resolved, true, null)
            };
        }
        catch (InvalidOperationException)
        {
            return new IdentityResolution(envelope, false, ConflictClass.SourceIdentityMismatch.ToString());
        }
    }

    private async Task<ClientIdentityMap> BuildIdentityMapAsync(CancellationToken cancellationToken)
    {
        List<ClientIdentityMappingEntity> mappings = await _dbContext.ClientIdentities
            .AsNoTracking()
            .OrderBy(item => item.Id)
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        return BuildIdentityMap(mappings);
    }

    private static ClientIdentityMap BuildIdentityMap(IEnumerable<ClientIdentityMappingEntity> mappings)
    {
        ClientIdentityMap map = ClientIdentityMap.Empty;
        foreach (ClientIdentityMappingEntity mapping in mappings)
        {
            map = map.AddPending(mapping.EntityType, mapping.ClientUuid);
            if (mapping.CanonicalId is not null)
            {
                map = map.Resolve(mapping.ClientUuid, mapping.CanonicalId);
            }
        }

        return map;
    }

    private async Task NotifyWithoutInvalidatingCommitAsync(
        LocalOperationResult result,
        CancellationToken cancellationToken)
    {
        try
        {
            await _notifier.NotifyOperationAsync(result, cancellationToken).ConfigureAwait(false);
        }
        catch (Exception)
        {
        }
    }

    private static LocalOperationResult DeserializeStoredResult(IdempotencyRecord record) =>
        JsonSerializer.Deserialize<LocalOperationResult>(record.ResultJson ?? throw new InvalidOperationException(
            "The idempotency record has no stored result."), RequestSerializerOptions)
        ?? throw new InvalidOperationException("The stored idempotency result is empty.");

    private static SyncEnvelope DeserializeEnvelope(string requestJson) =>
        JsonSerializer.Deserialize<SyncEnvelope>(requestJson, RequestSerializerOptions)
        ?? throw new InvalidOperationException("The stored operation request is empty.");

    private static void EnsureMatchingRequest(
        IdempotencyRecord record,
        Guid operationId,
        string requestHash)
    {
        if (record.OperationId != operationId ||
            !CryptographicOperations.FixedTimeEquals(
                Encoding.ASCII.GetBytes(record.RequestHash),
                Encoding.ASCII.GetBytes(requestHash)))
        {
            throw new IdempotencyMismatchException();
        }
    }

    private static string ComputeRequestHash(
        Guid operationId,
        string? idempotencyKey,
        string deviceId,
        string operationType,
        string requestJson)
    {
        byte[] bytes = Encoding.UTF8.GetBytes(string.Join(
            '\n',
            operationId.ToString("D"),
            idempotencyKey ?? string.Empty,
            deviceId,
            operationType,
            requestJson));
        return Convert.ToHexString(SHA256.HashData(bytes));
    }

    private static bool ContainsClientUuid(SyncEnvelope envelope, Guid clientUuid) =>
        envelope.Entity.ClientUuid == clientUuid ||
        envelope.Entity.References.Any(reference => reference.TargetClientUuid == clientUuid);

    private static void EnsureIdentityType(ClientIdentityMappingEntity mapping, string entityType)
    {
        if (!string.Equals(mapping.EntityType, entityType, StringComparison.Ordinal))
        {
            throw new InvalidOperationException("The client UUID is mapped to a different entity type.");
        }
    }

    private static void ValidateIdentity(string entityType, Guid clientUuid)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(entityType);
        if (clientUuid == Guid.Empty)
        {
            throw new ArgumentException("A non-empty client UUID is required.", nameof(clientUuid));
        }
    }

    private static void ValidateRequest(
        Guid operationId,
        string? idempotencyKey,
        string deviceId,
        string operationType,
        SyncEnvelope envelope,
        Func<LocalDbContext, SyncEnvelope, CancellationToken, Task<LocalBusinessResult>> businessAction)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        ArgumentNullException.ThrowIfNull(businessAction);
        if (operationId == Guid.Empty || envelope.OperationId != operationId)
        {
            throw new ArgumentException("The operation id must be non-empty and match the envelope.", nameof(operationId));
        }

        ArgumentException.ThrowIfNullOrWhiteSpace(deviceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(operationType);
        if (idempotencyKey is not null && string.IsNullOrWhiteSpace(idempotencyKey))
        {
            throw new ArgumentException("The idempotency key cannot be blank.", nameof(idempotencyKey));
        }
    }

    private sealed record IdentityResolution(
        SyncEnvelope Envelope,
        bool IsPending,
        string? ConflictType);

    private sealed class IdempotencyClaimCollisionException(DbUpdateException innerException)
        : Exception("The idempotency claim was created concurrently.", innerException);
}
