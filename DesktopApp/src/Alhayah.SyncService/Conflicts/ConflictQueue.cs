using System.Globalization;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.Json.Serialization;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;

namespace Alhayah.SyncService.Conflicts;

public sealed record HoldConflictRequest
{
    public required string EntityType { get; init; }
    public required Guid ClientUuid { get; init; }
    public required ConflictClass ConflictClass { get; init; }
    public Guid? OperationId { get; init; }
    public required string RemotePayloadJson { get; init; }
    public string? RemoteRowVersion { get; init; }
    public string? RemoteCanonicalId { get; init; }
    public DateTimeOffset? RemoteOccurredAtUtc { get; init; }
    public DateTimeOffset? RemoteDeletedAtUtc { get; init; }
    public string? RemoteDeletedByDeviceId { get; init; }
    public string? ServerConflictUuid { get; init; }
    public required IReadOnlyList<FieldPatch> Patches { get; init; }
}

public sealed record ConflictSnapshot
{
    [JsonPropertyName("conflictVersion")]
    public int ConflictVersion { get; init; } = 1;

    [JsonPropertyName("remotePayloadJson")]
    public required string RemotePayloadJson { get; init; }

    [JsonPropertyName("remoteRowVersion")]
    public string? RemoteRowVersion { get; init; }

    [JsonPropertyName("remoteCanonicalId")]
    public string? RemoteCanonicalId { get; init; }

    [JsonPropertyName("remoteOccurredAtUtc")]
    public DateTimeOffset? RemoteOccurredAtUtc { get; init; }

    [JsonPropertyName("remoteDeletedAtUtc")]
    public DateTimeOffset? RemoteDeletedAtUtc { get; init; }

    [JsonPropertyName("remoteDeletedByDeviceId")]
    public string? RemoteDeletedByDeviceId { get; init; }

    [JsonPropertyName("serverConflictUuid")]
    public string? ServerConflictUuid { get; init; }

    [JsonPropertyName("patches")]
    public required IReadOnlyList<FieldPatch> Patches { get; init; }
}

public sealed record ServerConflictMetadata
{
    public const string ExtensionName = "desktopServerConflict";

    [JsonPropertyName("conflictUuid")]
    public required string ConflictUuid { get; init; }
}

public sealed record ConflictResolutionDispatch
{
    public const string ExtensionName = "desktopConflictResolution";

    [JsonPropertyName("conflictId")]
    public required Guid ConflictId { get; init; }

    [JsonPropertyName("expectedConflictVersion")]
    public required int ExpectedConflictVersion { get; init; }

    [JsonPropertyName("resolution")]
    public required string Resolution { get; init; }

    [JsonPropertyName("selectedFields")]
    public required IReadOnlyList<string> SelectedFields { get; init; }

    [JsonPropertyName("serverConflictUuid")]
    public string? ServerConflictUuid { get; init; }
}

public sealed class ConflictResolutionRejectedException(Guid operationId, string reason) : Exception("The server rejected the pending conflict decision.")
{
    public Guid OperationId { get; } = operationId;
    public string Reason { get; } = reason;
}

public sealed class ConflictQueue(TimeProvider timeProvider)
{
    private readonly TimeProvider _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));

    public async Task<SyncConflict> HoldAsync(
        LocalStoreDbContext context,
        string sourceId,
        HoldConflictRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(context);
        ArgumentNullException.ThrowIfNull(request);
        sourceId = new SyncSourceId(sourceId).Value;
        ArgumentException.ThrowIfNullOrWhiteSpace(request.EntityType);
        _ = JsonNode.Parse(request.RemotePayloadJson) as JsonObject
            ?? throw new JsonException("A conflict remote payload must be a JSON object.");

        SyncConflict[] existing = await context.SyncConflicts
            .Where(item => item.SourceId == sourceId &&
                item.EntityType == request.EntityType &&
                item.ClientUuid == request.ClientUuid &&
                (item.Status == "PendingReview" || item.Status == "PendingResolution"))
            .ToArrayAsync(cancellationToken);
        foreach (SyncConflict conflict in existing)
        {
            if (!TryReadSnapshot(conflict, out ConflictSnapshot? snapshot))
            {
                continue;
            }

            if (string.Equals(snapshot.RemoteRowVersion, request.RemoteRowVersion, StringComparison.Ordinal) &&
                string.Equals(snapshot.RemotePayloadJson, request.RemotePayloadJson, StringComparison.Ordinal))
            {
                await HoldPendingOperationsAsync(context, sourceId, request, cancellationToken);
                await context.SaveChangesAsync(cancellationToken);
                return conflict;
            }
        }

        Guid operationId = request.OperationId ?? Guid.NewGuid();
        ConflictSnapshot snapshot = new()
        {
            RemotePayloadJson = request.RemotePayloadJson,
            RemoteRowVersion = request.RemoteRowVersion,
            RemoteCanonicalId = request.RemoteCanonicalId,
            RemoteOccurredAtUtc = request.RemoteOccurredAtUtc,
            RemoteDeletedAtUtc = request.RemoteDeletedAtUtc,
            RemoteDeletedByDeviceId = request.RemoteDeletedByDeviceId,
            ServerConflictUuid = request.ServerConflictUuid,
            Patches = request.Patches
        };
        SyncEnvelope envelope = new()
        {
            OperationId = operationId,
            SourceId = new SyncSourceId(sourceId),
            DeviceId = "remote",
            Entity = new EntityDescriptor
            {
                EntityType = request.EntityType,
                ClientUuid = request.ClientUuid,
                CanonicalId = request.RemoteCanonicalId,
                References = []
            },
            OperationType = request.RemoteDeletedAtUtc.HasValue ? SyncOperationType.Delete : SyncOperationType.Update,
            RowVersion = request.RemoteRowVersion,
            OccurredAtUtc = request.RemoteOccurredAtUtc ?? _timeProvider.GetUtcNow(),
            Tombstone = request.RemoteDeletedAtUtc.HasValue ? new Tombstone
            {
                DeletedAtUtc = request.RemoteDeletedAtUtc.Value,
                DeletedByDeviceId = request.RemoteDeletedByDeviceId ?? "remote"
            } : null,
            Patches = request.Patches,
            ExtensionData = new Dictionary<string, JsonElement>
            {
                ["desktopConflictSnapshot"] = JsonSerializer.SerializeToElement(snapshot)
            }
        };
        SyncConflict created = new()
        {
            Id = Guid.NewGuid(),
            OperationId = operationId,
            SourceId = sourceId,
            EntityType = request.EntityType,
            ClientUuid = request.ClientUuid,
            ConflictClass = request.ConflictClass.ToString(),
            EnvelopeJson = JsonSerializer.Serialize(envelope),
            Status = "PendingReview",
            CreatedAtUtc = _timeProvider.GetUtcNow()
        };
        context.SyncConflicts.Add(created);
        await HoldPendingOperationsAsync(context, sourceId, request, cancellationToken);
        await context.SaveChangesAsync(cancellationToken);
        return created;
    }

    private static async Task HoldPendingOperationsAsync(
        LocalStoreDbContext context,
        string sourceId,
        HoldConflictRequest request,
        CancellationToken cancellationToken)
    {
        List<OutboxOperation> heldOperations = await context.OutboxOperations
            .Where(item => item.SourceId == sourceId && item.EntityType == request.EntityType &&
                item.ClientUuid == request.ClientUuid && item.Status == "Pending" && item.OperationType != "ConflictResolution")
            .ToListAsync(cancellationToken);
        foreach (OutboxOperation operation in heldOperations)
        {
            operation.Status = "Held";
        }
    }

    public async Task<IReadOnlyList<ConflictReviewData>> ListAsync(
        LocalStoreDbContext context,
        string sourceId,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(context);
        SyncConflict[] conflicts = await context.SyncConflicts
            .AsNoTracking()
            .Where(item => item.SourceId == sourceId)
            .OrderByDescending(item => item.CreatedAtUtc)
            .Take(200)
            .ToArrayAsync(cancellationToken);
        Guid[] clientIds = conflicts.Select(item => item.ClientUuid).Distinct().ToArray();
        Dictionary<Guid, string?> rowVersions = await context.CachedEntities
            .IgnoreQueryFilters()
            .AsNoTracking()
            .Where(item => item.SourceId == sourceId && clientIds.Contains(item.ClientUuid))
            .ToDictionaryAsync(item => item.ClientUuid, item => item.RowVersion, cancellationToken);
        return conflicts.Select(item =>
        {
            ConflictSnapshot snapshot = TryReadSnapshot(item, out ConflictSnapshot? readableSnapshot)
                ? readableSnapshot!
                : new ConflictSnapshot { RemotePayloadJson = "{}", Patches = [] };
            return new ConflictReviewData
            {
                ConflictId = item.Id,
                OperationId = item.OperationId,
                SourceId = item.SourceId,
                EntityType = item.EntityType,
                ClientUuid = item.ClientUuid,
                ConflictClass = item.ConflictClass,
                Status = item.Status,
                RowVersion = rowVersions.GetValueOrDefault(item.ClientUuid),
                ConflictVersion = snapshot.ConflictVersion,
                Fields = snapshot.Patches.Select(patch => new ConflictFieldReviewData
                {
                    FieldName = patch.FieldName,
                    BaseValue = patch.BaseValue,
                    LocalValue = patch.LocalValue,
                    RemoteValue = patch.RemoteValue,
                    Criticality = patch.Criticality
                }).ToArray()
            };
        }).ToArray();
    }

    public async Task<ConflictResolutionResponse> ResolveAsync(
        LocalStoreDbContext context,
        ConflictResolutionRequest request,
        Guid userId,
        string deviceId,
        long manifestVersion,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(context);
        ArgumentNullException.ThrowIfNull(request);
        ArgumentException.ThrowIfNullOrWhiteSpace(request.ExpectedRowVersion);
        ArgumentException.ThrowIfNullOrWhiteSpace(request.Decision);
        ArgumentNullException.ThrowIfNull(request.SelectedFields);
        if (request.ExpectedConflictVersion < 1 || request.SelectedFields.Any(string.IsNullOrWhiteSpace) ||
            request.ResolutionOperationId == Guid.Empty)
        {
            throw new ArgumentException("A conflict decision requires valid versions, fields, and operation identity.", nameof(request));
        }

        if (request.ResolutionOperationId.HasValue)
        {
            OutboxOperation? repeated = await context.OutboxOperations.AsNoTracking()
                .SingleOrDefaultAsync(item => item.OperationId == request.ResolutionOperationId.Value, cancellationToken);
            if (repeated is not null)
            {
                ConflictResolutionDispatch dispatch = ReadDispatch(repeated);
                string repeatedDecision = request.Decision.Trim().ToLowerInvariant();
                string[] repeatedFields = request.SelectedFields.Distinct(StringComparer.Ordinal).Order(StringComparer.Ordinal).ToArray();
                string[] storedFields = dispatch.SelectedFields.Distinct(StringComparer.Ordinal).Order(StringComparer.Ordinal).ToArray();
                if (dispatch.ConflictId != request.ConflictId ||
                    dispatch.ExpectedConflictVersion != request.ExpectedConflictVersion ||
                    !string.Equals(dispatch.Resolution, repeatedDecision, StringComparison.Ordinal) ||
                    !repeatedFields.SequenceEqual(storedFields))
                {
                    throw new InvalidOperationException("The resolution operation does not match the original decision.");
                }

                SyncConflict repeatedConflict = await context.SyncConflicts.SingleAsync(item => item.Id == request.ConflictId, cancellationToken);
                return new ConflictResolutionResponse
                {
                    ConflictId = request.ConflictId,
                    Status = repeatedConflict.Status,
                    ResolutionOperationId = repeated.OperationId,
                    ConflictVersion = ReadSnapshot(repeatedConflict).ConflictVersion
                };
            }
        }

        await using IDbContextTransaction transaction = await context.Database
            .BeginTransactionAsync(cancellationToken);
        try
        {
            SyncConflict conflict = await context.SyncConflicts.SingleOrDefaultAsync(
                item => item.Id == request.ConflictId, cancellationToken).ConfigureAwait(false)
                ?? throw new KeyNotFoundException();
            if (conflict.Status == "PendingResolution")
            {
                throw new InvalidOperationException("The conflict already has a pending decision.");
            }

            if (conflict.Status != "PendingReview")
            {
                throw new InvalidOperationException("The conflict is not awaiting a decision.");
            }

            ConflictSnapshot snapshot = ReadSnapshot(conflict);
            if (snapshot.ConflictVersion != request.ExpectedConflictVersion)
            {
                throw new DbUpdateConcurrencyException("The conflict version is stale.");
            }

            CachedEntity entity = await context.CachedEntities.IgnoreQueryFilters().SingleOrDefaultAsync(
                item => item.SourceId == conflict.SourceId && item.EntityType == conflict.EntityType && item.ClientUuid == conflict.ClientUuid,
                cancellationToken).ConfigureAwait(false)
                ?? throw new DbUpdateConcurrencyException("The conflicted cached entity is unavailable.");
            if (!string.Equals(entity.RowVersion, request.ExpectedRowVersion, StringComparison.Ordinal))
            {
                throw new DbUpdateConcurrencyException("The conflicted row version is stale.");
            }

            string decision = request.Decision.Trim().ToLowerInvariant();
            string[] selected = request.SelectedFields.Distinct(StringComparer.Ordinal).ToArray();
            Guid operationId = request.ResolutionOperationId ?? Guid.NewGuid();
            DateTimeOffset now = _timeProvider.GetUtcNow();
            if (decision != "rejected" && await context.SyncConflicts.AnyAsync(item =>
                    item.SourceId == conflict.SourceId && item.EntityType == conflict.EntityType &&
                    item.ClientUuid == conflict.ClientUuid && item.Id != conflict.Id &&
                    item.Status == "PendingResolution", cancellationToken).ConfigureAwait(false))
            {
                throw new InvalidOperationException("Another conflict for this entity already has a pending decision.");
            }

            if (decision == "rejected")
            {
                conflict.Status = "Rejected";
                conflict.ResolvedAtUtc = now;
                conflict.ResolvedByUserId = userId;
                AddAudit(context, conflict, operationId, deviceId, userId, manifestVersion, "Rejected", now);
                await context.SaveChangesAsync(cancellationToken);
                await transaction.CommitAsync(cancellationToken);
                return new ConflictResolutionResponse { ConflictId = conflict.Id, Status = conflict.Status, ConflictVersion = snapshot.ConflictVersion };
            }

            if (decision is not ("server" or "local" or "selected-fields"))
            {
                throw new ArgumentException("The conflict decision is invalid.", nameof(request));
            }

            if (decision == "selected-fields")
            {
                if (selected.Length == 0 || selected.Any(field => !snapshot.Patches.Any(patch =>
                        string.Equals(patch.FieldName, field, StringComparison.Ordinal) &&
                        patch.Criticality != FieldCriticality.ServerOwned && !IsProtectedIdentityField(patch.FieldName))))
                {
                    throw new ArgumentException("Selected fields must be changeable fields from this conflict.", nameof(request));
                }

                JsonObject local = JsonNode.Parse(entity.PayloadJson) as JsonObject
                    ?? throw new JsonException("The local payload must be a JSON object.");
                foreach (FieldPatch patch in snapshot.Patches.Where(patch => selected.Contains(patch.FieldName, StringComparer.Ordinal)))
                {
                    local[patch.FieldName] = patch.LocalValue.HasValue
                        ? JsonNode.Parse(patch.LocalValue.Value.GetRawText())
                        : null;
                }

                entity.PayloadJson = local.ToJsonString();
            }
            else if (decision == "server")
            {
                entity.PayloadJson = snapshot.RemotePayloadJson;
                entity.DeletedAtUtc = snapshot.RemoteDeletedAtUtc;
                entity.DeletedByDeviceId = snapshot.RemoteDeletedByDeviceId;
                entity.RowVersion = snapshot.RemoteRowVersion ?? Guid.NewGuid().ToString("N");
                entity.UpdatedAtUtc = snapshot.RemoteOccurredAtUtc ?? now;
                if (snapshot.RemoteCanonicalId is not null)
                {
                    EnsureCanonicalIdentity(entity.CanonicalId, snapshot.RemoteCanonicalId);
                    entity.CanonicalId = snapshot.RemoteCanonicalId;
                }
            }

            FieldPatch[] decisionPatches = decision switch
            {
                "server" => [],
                "local" => [.. snapshot.Patches],
                _ => [.. snapshot.Patches.Where(patch => selected.Contains(patch.FieldName, StringComparer.Ordinal))]
            };
            ConflictResolutionDispatch resolutionDispatch = new()
            {
                ConflictId = conflict.Id,
                ExpectedConflictVersion = snapshot.ConflictVersion,
                Resolution = decision,
                SelectedFields = selected,
                ServerConflictUuid = snapshot.ServerConflictUuid
            };
            SyncEnvelope envelope = new()
            {
                OperationId = operationId,
                SourceId = new SyncSourceId(conflict.SourceId),
                DeviceId = deviceId,
                Entity = new EntityDescriptor
                {
                    EntityType = entity.EntityType,
                    ClientUuid = entity.ClientUuid,
                    CanonicalId = entity.CanonicalId,
                    References = []
                },
                OperationType = entity.DeletedAtUtc.HasValue && decision == "server" ? SyncOperationType.Delete : SyncOperationType.Update,
                BaseVersion = ParseVersion(snapshot.RemoteRowVersion),
                RowVersion = entity.RowVersion,
                OccurredAtUtc = now,
                Tombstone = entity.DeletedAtUtc.HasValue && decision == "server" ? new Tombstone
                {
                    DeletedAtUtc = entity.DeletedAtUtc.Value,
                    DeletedByDeviceId = entity.DeletedByDeviceId ?? deviceId
                } : null,
                Patches = decisionPatches,
                ExtensionData = new Dictionary<string, JsonElement>
                {
                    [ConflictResolutionDispatch.ExtensionName] = JsonSerializer.SerializeToElement(resolutionDispatch)
                }
            };
            string envelopeJson = JsonSerializer.Serialize(envelope);
            context.OutboxOperations.Add(new OutboxOperation
            {
                OperationId = operationId,
                SourceId = conflict.SourceId,
                DeviceId = deviceId,
                EntityType = entity.EntityType,
                ClientUuid = entity.ClientUuid,
                OperationType = "ConflictResolution",
                EnvelopeJson = envelopeJson,
                Status = "Pending",
                CreatedAtUtc = now
            });
            context.LocalChangeLog.Add(new LocalChangeLogEntry
            {
                OperationId = operationId,
                SourceId = conflict.SourceId,
                EntityType = entity.EntityType,
                ClientUuid = entity.ClientUuid,
                OperationType = "ConflictResolution",
                RowVersion = entity.RowVersion,
                PayloadJson = envelopeJson,
                DeviceId = deviceId,
                OccurredAtUtc = now
            });
            AddAudit(context, conflict, operationId, deviceId, userId, manifestVersion, "Committed", now);
            conflict.Status = "PendingResolution";
            conflict.ResolvedByUserId = userId;
            conflict.ResolvedAtUtc = null;
            await context.SaveChangesAsync(cancellationToken);
            await transaction.CommitAsync(cancellationToken);
            return new ConflictResolutionResponse
            {
                ConflictId = conflict.Id,
                Status = conflict.Status,
                ResolutionOperationId = operationId,
                ConflictVersion = snapshot.ConflictVersion
            };
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken);
            throw;
        }
    }

    public async Task<bool> ConfirmAsync(
        LocalStoreDbContext context,
        Guid operationId,
        DateTimeOffset confirmedAtUtc,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(context);
        OutboxOperation? operation = await context.OutboxOperations
            .SingleOrDefaultAsync(item => item.OperationId == operationId, cancellationToken);
        if (operation is null || operation.Status != "Sent" || operation.OperationType != "ConflictResolution")
        {
            return false;
        }

        ConflictResolutionDispatch dispatch = ReadDispatch(operation);
        SyncConflict? conflict = await context.SyncConflicts
            .SingleOrDefaultAsync(item => item.Id == dispatch.ConflictId, cancellationToken);
        if (conflict is null || conflict.Status != "PendingResolution")
        {
            return false;
        }

        ConflictSnapshot snapshot = ReadSnapshot(conflict);
        SyncEnvelope resolutionEnvelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
            ?? throw new JsonException("The conflict resolution envelope is empty.");
        List<OutboxOperation> heldOperations = await context.OutboxOperations
            .Where(item => item.SourceId == conflict.SourceId && item.EntityType == conflict.EntityType &&
                item.ClientUuid == conflict.ClientUuid && item.Status == "Held")
            .ToListAsync(cancellationToken);
        foreach (OutboxOperation heldOperation in heldOperations)
        {
            if (heldOperation.CreatedAtUtc <= resolutionEnvelope.OccurredAtUtc)
            {
                heldOperation.Status = "Sent";
                heldOperation.LastAttemptAtUtc = confirmedAtUtc;
            }
            else
            {
                heldOperation.Status = "Pending";
            }
        }

        conflict.Status = "Resolved";
        conflict.ResolvedAtUtc = confirmedAtUtc;
        conflict.EnvelopeJson = JsonSerializer.Serialize(new SyncEnvelope
        {
            OperationId = conflict.OperationId,
            SourceId = new SyncSourceId(conflict.SourceId),
            DeviceId = "remote",
            Entity = new EntityDescriptor { EntityType = conflict.EntityType, ClientUuid = conflict.ClientUuid, References = [] },
            OperationType = SyncOperationType.Update,
            OccurredAtUtc = conflict.CreatedAtUtc,
            Patches = snapshot.Patches,
            ExtensionData = new Dictionary<string, JsonElement>
            {
                ["desktopConflictSnapshot"] = JsonSerializer.SerializeToElement(snapshot with { ConflictVersion = snapshot.ConflictVersion + 1 })
            }
        });
        await context.SaveChangesAsync(cancellationToken);
        return true;
    }

    public async Task RejectAsync(
        LocalStoreDbContext context,
        Guid operationId,
        DateTimeOffset rejectedAtUtc,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(context);
        OutboxOperation? operation = await context.OutboxOperations
            .SingleOrDefaultAsync(item => item.OperationId == operationId, cancellationToken);
        if (operation is null || operation.Status != "Pending")
        {
            return;
        }

        ConflictResolutionDispatch dispatch = ReadDispatch(operation);
        SyncConflict? conflict = await context.SyncConflicts
            .SingleOrDefaultAsync(item => item.Id == dispatch.ConflictId, cancellationToken);
        SyncEnvelope resolutionEnvelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
            ?? throw new JsonException("The conflict resolution envelope is empty.");
        operation.Status = "Rejected";
        if (conflict is not null)
        {
            List<OutboxOperation> heldOperations = await context.OutboxOperations
                .Where(item => item.SourceId == conflict.SourceId && item.EntityType == conflict.EntityType &&
                    item.ClientUuid == conflict.ClientUuid && item.Status == "Held")
                .ToListAsync(cancellationToken);
            foreach (OutboxOperation heldOperation in heldOperations)
            {
                heldOperation.Status = heldOperation.CreatedAtUtc <= resolutionEnvelope.OccurredAtUtc
                    ? "Rejected"
                    : "Pending";
            }
        }

        if (conflict is not null && conflict.Status == "PendingResolution")
        {
            conflict.Status = "Rejected";
            conflict.ResolvedAtUtc = rejectedAtUtc;
        }

        await context.SaveChangesAsync(cancellationToken);
    }

    public static ConflictSnapshot ReadSnapshot(SyncConflict conflict)
    {
        if (!TryReadSnapshot(conflict, out ConflictSnapshot? snapshot))
        {
            throw new JsonException("The conflict snapshot is missing or invalid.");
        }

        return snapshot!;
    }

    public static bool TryReadSnapshot(SyncConflict conflict, out ConflictSnapshot? snapshot)
    {
        ArgumentNullException.ThrowIfNull(conflict);
        snapshot = null;
        try
        {
            SyncEnvelope? envelope = JsonSerializer.Deserialize<SyncEnvelope>(conflict.EnvelopeJson);
            if (envelope?.ExtensionData?.TryGetValue("desktopConflictSnapshot", out JsonElement value) != true)
            {
                return false;
            }

            snapshot = value.Deserialize<ConflictSnapshot>();
            return snapshot is not null;
        }
        catch (JsonException)
        {
            return false;
        }
    }

    public static ConflictResolutionDispatch? TryReadDispatch(SyncEnvelope envelope)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        return envelope.ExtensionData?.TryGetValue(ConflictResolutionDispatch.ExtensionName, out JsonElement value) == true
            ? value.Deserialize<ConflictResolutionDispatch>()
            : null;
    }

    public static ConflictResolutionDispatch ReadDispatch(OutboxOperation operation)
    {
        ArgumentNullException.ThrowIfNull(operation);
        SyncEnvelope envelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
            ?? throw new JsonException("The outbox envelope is empty.");
        return TryReadDispatch(envelope)
            ?? throw new JsonException("The outbox operation is not a conflict resolution.");
    }

    private static void AddAudit(
        LocalStoreDbContext context,
        SyncConflict conflict,
        Guid operationId,
        string deviceId,
        Guid userId,
        long manifestVersion,
        string result,
        DateTimeOffset occurredAtUtc)
    {
        string safePayload = JsonSerializer.Serialize(new { conflict.Id, conflict.ConflictClass, result });
        context.AuditEntries.Add(new AuditEntry
        {
            OperationId = operationId,
            OfflineUserId = userId,
            DeviceId = deviceId,
            SourceId = conflict.SourceId,
            PermissionUsed = LocalPermissions.ResolveConflicts,
            ManifestVersion = manifestVersion,
            OccurredAtUtc = occurredAtUtc,
            PayloadSha256 = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(safePayload))),
            Result = result
        });
    }

    private static ulong? ParseVersion(string? value) =>
        ulong.TryParse(value, NumberStyles.None, CultureInfo.InvariantCulture, out ulong parsed) ? parsed : null;

    private static bool IsProtectedIdentityField(string fieldName) =>
        fieldName.Equals("id", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("uuid", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("clientUuid", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("canonicalId", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("sourceId", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("deviceId", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("tenantId", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("nationalId", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Equals("passport", StringComparison.OrdinalIgnoreCase);

    private static void EnsureCanonicalIdentity(string? current, string? incoming)
    {
        if (current is not null && !string.Equals(current, incoming, StringComparison.Ordinal))
        {
            throw new DbUpdateConcurrencyException("The canonical identity is stale.");
        }
    }
}
