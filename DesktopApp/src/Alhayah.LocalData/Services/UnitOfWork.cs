using System.Globalization;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Repositories;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;

namespace Alhayah.LocalData.Services;

public sealed record LocalAuditContext(Guid? OfflineUserId, string PermissionUsed, long? ManifestVersion);

public sealed class UnitOfWork
{
    private readonly LocalStoreDbContext _context;
    private readonly LocalCacheRepository _cache;
    private readonly OutboxRepository _outbox;

    public UnitOfWork(LocalStoreDbContext context)
    {
        _context = context ?? throw new ArgumentNullException(nameof(context));
        _cache = new LocalCacheRepository(context);
        _outbox = new OutboxRepository(context);
    }

    public async Task<CachedEntity> CreateAsync(
        SyncEnvelope envelope,
        string payloadJson,
        string searchText,
        LocalAuditContext auditContext,
        CancellationToken cancellationToken = default)
    {
        ValidateEnvelope(envelope, SyncOperationType.Create);
        ArgumentException.ThrowIfNullOrWhiteSpace(payloadJson);
        ArgumentException.ThrowIfNullOrWhiteSpace(searchText);
        ArgumentNullException.ThrowIfNull(auditContext);
        ValidatePayload(payloadJson);

        await using IDbContextTransaction transaction = await _context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        try
        {
            CachedEntity? existing = await _cache.GetIncludingDeletedAsync(
                envelope.SourceId.Value,
                envelope.Entity.EntityType,
                envelope.Entity.ClientUuid,
                cancellationToken).ConfigureAwait(false);
            if (existing is not null)
            {
                throw new InvalidOperationException("The client UUID is already present in the device cache.");
            }

            CachedEntity entity = new()
            {
                ClientUuid = envelope.Entity.ClientUuid,
                SourceId = envelope.SourceId.Value,
                EntityType = envelope.Entity.EntityType,
                CanonicalId = envelope.Entity.CanonicalId,
                PayloadJson = payloadJson,
                SearchTextNormalized = LocalCacheRepository.NormalizeSearchText(searchText),
                BaseVersion = FormatBaseVersion(envelope.BaseVersion),
                RowVersion = NewRowVersion(),
                CreatedAtUtc = envelope.OccurredAtUtc,
                UpdatedAtUtc = envelope.OccurredAtUtc
            };
            _context.CachedEntities.Add(entity);
            _context.ClientIdentityMap.Add(new ClientIdentityMappingEntity
            {
                ClientUuid = entity.ClientUuid,
                EntityType = entity.EntityType,
                CanonicalId = entity.CanonicalId,
                CreatedAtUtc = entity.CreatedAtUtc,
                ResolvedAtUtc = entity.CanonicalId is null ? null : entity.CreatedAtUtc
            });
            await AddSynchronizationArtifactsAsync(envelope, entity, auditContext, cancellationToken).ConfigureAwait(false);
            await _context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
            await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
            return entity;
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken).ConfigureAwait(false);
            throw;
        }
    }

    public async Task<CachedEntity> PatchAsync(
        SyncEnvelope envelope,
        string searchText,
        LocalAuditContext auditContext,
        CancellationToken cancellationToken = default)
    {
        ValidateEnvelope(envelope, SyncOperationType.Update);
        ArgumentException.ThrowIfNullOrWhiteSpace(searchText);
        ArgumentNullException.ThrowIfNull(auditContext);
        if (envelope.Patches.Count == 0)
        {
            throw new ArgumentException("A field patch operation requires at least one patch.", nameof(envelope));
        }

        await using IDbContextTransaction transaction = await _context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        try
        {
            CachedEntity entity = await LoadActiveAsync(envelope, cancellationToken).ConfigureAwait(false);
            EnsureCurrentRowVersion(entity, envelope.RowVersion);
            entity.PayloadJson = ApplyPatches(entity.PayloadJson, envelope.Patches);
            entity.SearchTextNormalized = LocalCacheRepository.NormalizeSearchText(searchText);
            entity.RowVersion = NewRowVersion();
            entity.UpdatedAtUtc = envelope.OccurredAtUtc;
            await AddSynchronizationArtifactsAsync(envelope, entity, auditContext, cancellationToken).ConfigureAwait(false);
            await _context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
            await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
            return entity;
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken).ConfigureAwait(false);
            throw;
        }
    }

    public async Task<CachedEntity> SoftDeleteAsync(
        SyncEnvelope envelope,
        LocalAuditContext auditContext,
        CancellationToken cancellationToken = default)
    {
        ValidateEnvelope(envelope, SyncOperationType.Delete);
        ArgumentNullException.ThrowIfNull(auditContext);
        if (envelope.Tombstone is null)
        {
            throw new ArgumentException("A soft delete requires a sync tombstone.", nameof(envelope));
        }

        if (!string.Equals(envelope.Tombstone.DeletedByDeviceId, envelope.DeviceId, StringComparison.Ordinal))
        {
            throw new ArgumentException("The tombstone device must match the envelope device.", nameof(envelope));
        }

        await using IDbContextTransaction transaction = await _context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        try
        {
            CachedEntity entity = await LoadActiveAsync(envelope, cancellationToken).ConfigureAwait(false);
            EnsureCurrentRowVersion(entity, envelope.RowVersion);
            entity.DeletedAtUtc = envelope.Tombstone.DeletedAtUtc;
            entity.DeletedByDeviceId = envelope.Tombstone.DeletedByDeviceId;
            entity.RowVersion = NewRowVersion();
            entity.UpdatedAtUtc = envelope.OccurredAtUtc;
            await AddSynchronizationArtifactsAsync(envelope, entity, auditContext, cancellationToken).ConfigureAwait(false);
            await _context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
            await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
            return entity;
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken).ConfigureAwait(false);
            throw;
        }
    }

    private async Task<CachedEntity> LoadActiveAsync(SyncEnvelope envelope, CancellationToken cancellationToken)
    {
        CachedEntity? entity = await _cache.GetAsync(
            envelope.SourceId.Value,
            envelope.Entity.EntityType,
            envelope.Entity.ClientUuid,
            cancellationToken).ConfigureAwait(false);
        return entity ?? throw new KeyNotFoundException("The active cached entity was not found.");
    }

    private async Task AddSynchronizationArtifactsAsync(
        SyncEnvelope envelope,
        CachedEntity entity,
        LocalAuditContext auditContext,
        CancellationToken cancellationToken)
    {
        string envelopeJson = JsonSerializer.Serialize(envelope);
        bool hasPendingConflict = await _context.SyncConflicts.AnyAsync(conflict =>
            conflict.SourceId == entity.SourceId &&
            conflict.EntityType == entity.EntityType &&
            conflict.ClientUuid == entity.ClientUuid &&
            (conflict.Status == "PendingReview" || conflict.Status == "PendingResolution"),
            cancellationToken).ConfigureAwait(false);
        _outbox.Add(new OutboxOperation
        {
            OperationId = envelope.OperationId,
            SourceId = envelope.SourceId.Value,
            DeviceId = envelope.DeviceId,
            EntityType = entity.EntityType,
            ClientUuid = entity.ClientUuid,
            OperationType = envelope.OperationType.ToString(),
            EnvelopeJson = envelopeJson,
            Status = hasPendingConflict ? "Held" : "Pending",
            AttemptCount = 0,
            CreatedAtUtc = envelope.OccurredAtUtc
        });
        _context.LocalChangeLog.Add(new LocalChangeLogEntry
        {
            OperationId = envelope.OperationId,
            SourceId = entity.SourceId,
            EntityType = entity.EntityType,
            ClientUuid = entity.ClientUuid,
            OperationType = envelope.OperationType.ToString(),
            RowVersion = entity.RowVersion,
            PayloadJson = envelopeJson,
            DeviceId = envelope.DeviceId,
            OccurredAtUtc = envelope.OccurredAtUtc
        });
        _context.AuditEntries.Add(new AuditEntry
        {
            OperationId = envelope.OperationId,
            OfflineUserId = auditContext.OfflineUserId,
            DeviceId = envelope.DeviceId,
            SourceId = envelope.SourceId.Value,
            PermissionUsed = auditContext.PermissionUsed,
            ManifestVersion = auditContext.ManifestVersion,
            OccurredAtUtc = envelope.OccurredAtUtc,
            PayloadSha256 = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(envelopeJson))),
            Result = "Committed"
        });
    }

    private static string ApplyPatches(string payloadJson, IReadOnlyList<FieldPatch> patches)
    {
        JsonObject root = JsonNode.Parse(payloadJson) as JsonObject
            ?? throw new JsonException("The cached entity payload must be a JSON object.");
        foreach (FieldPatch patch in patches)
        {
            ArgumentException.ThrowIfNullOrWhiteSpace(patch.FieldName);
            if (patch.Criticality == FieldCriticality.ServerOwned)
            {
                throw new InvalidOperationException("Server-owned fields cannot be changed by a local field patch.");
            }

            root[patch.FieldName] = patch.LocalValue.HasValue
                ? JsonNode.Parse(patch.LocalValue.Value.GetRawText())
                : null;
        }

        return root.ToJsonString();
    }

    private static void ValidatePayload(string payloadJson)
    {
        if (JsonNode.Parse(payloadJson) is not JsonObject)
        {
            throw new JsonException("The cached entity payload must be a JSON object.");
        }
    }

    private static void ValidateEnvelope(SyncEnvelope envelope, SyncOperationType expectedOperation)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        if (envelope.OperationType != expectedOperation)
        {
            throw new ArgumentException("The sync envelope operation type does not match the requested mutation.", nameof(envelope));
        }

        if (envelope.OperationId == Guid.Empty || envelope.Entity.ClientUuid == Guid.Empty)
        {
            throw new ArgumentException("OperationId and ClientUuid must not be empty.", nameof(envelope));
        }

        ArgumentException.ThrowIfNullOrWhiteSpace(envelope.DeviceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(envelope.Entity.EntityType);
    }

    private static void EnsureCurrentRowVersion(CachedEntity entity, string? expectedRowVersion)
    {
        if (!string.Equals(entity.RowVersion, expectedRowVersion, StringComparison.Ordinal))
        {
            throw new DbUpdateConcurrencyException("The cached entity RowVersion is stale; no outbox operation was committed.");
        }
    }

    private static string? FormatBaseVersion(ulong? baseVersion) =>
        baseVersion?.ToString(CultureInfo.InvariantCulture);

    private static string NewRowVersion() => Guid.NewGuid().ToString("N");
}
