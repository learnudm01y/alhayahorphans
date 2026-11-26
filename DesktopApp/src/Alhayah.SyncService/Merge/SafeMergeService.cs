using System.Text.Json;
using System.Text.Json.Nodes;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Conflicts;
using Alhayah.SyncService.Cursors;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.SyncService.Merge;

public sealed record SafeMergeResult(
    string PayloadJson,
    ConflictClass Classification,
    IReadOnlyList<FieldPatch> Patches,
    bool ShouldHold);

public sealed class SafeMergeService
{
    private readonly ConflictQueue _conflictQueue;

    public SafeMergeService() : this(new ConflictQueue(TimeProvider.System))
    {
    }

    public SafeMergeService(ConflictQueue conflictQueue)
    {
        _conflictQueue = conflictQueue ?? throw new ArgumentNullException(nameof(conflictQueue));
    }

    private static readonly HashSet<string> CriticalFields = new(StringComparer.OrdinalIgnoreCase)
    {
        "id", "uuid", "clientUuid", "canonicalId", "sourceId", "deviceId", "tenantId",
        "nationalId", "passport", "identity", "amount", "balance", "price", "cost", "currency",
        "financial", "payment", "status", "state", "deleted", "deletedAt", "reference", "parentId"
    };

    public SafeMergeResult Merge(
        string basePayloadJson,
        string localPayloadJson,
        string remotePayloadJson,
        IReadOnlyList<FieldPatch> localPatches,
        bool remoteDeleted = false,
        string? localCanonicalId = null,
        string? remoteCanonicalId = null)
    {
        JsonObject basePayload = ParseObject(basePayloadJson);
        JsonObject localPayload = ParseObject(localPayloadJson);
        JsonObject remotePayload = ParseObject(remotePayloadJson);
        ArgumentNullException.ThrowIfNull(localPatches);

        bool hasLocalChanges = localPatches.Count > 0 || !JsonNode.DeepEquals(basePayload, localPayload);
        Dictionary<string, FieldPatch> patches = new(StringComparer.Ordinal);
        foreach (string fieldName in basePayload.Select(item => item.Key)
                     .Concat(localPayload.Select(item => item.Key))
                     .Concat(remotePayload.Select(item => item.Key))
                     .Concat(localPatches.Select(item => item.FieldName))
                     .Distinct(StringComparer.Ordinal))
        {
            FieldPatch? localPatch = localPatches.LastOrDefault(item => item.FieldName == fieldName);
            JsonNode? baseValue = basePayload[fieldName]?.DeepClone();
            JsonNode? localValue = localPayload[fieldName]?.DeepClone();
            JsonNode? remoteValue = remotePayload[fieldName]?.DeepClone();
            FieldCriticality criticality = localPatch?.Criticality ?? FieldCriticality.Mergeable;
            if (criticality == FieldCriticality.Mergeable && IsCriticalField(fieldName))
            {
                criticality = FieldCriticality.Critical;
            }

            patches[fieldName] = new FieldPatch
            {
                FieldName = fieldName,
                BaseValue = Element(baseValue),
                LocalValue = Element(localValue),
                RemoteValue = Element(remoteValue),
                Criticality = criticality
            };
        }

        if (localCanonicalId is not null && remoteCanonicalId is not null &&
            !string.Equals(localCanonicalId, remoteCanonicalId, StringComparison.Ordinal))
        {
            return Hold(localPayloadJson, ConflictClass.SourceIdentityMismatch, [.. patches.Values]);
        }

        if (remoteDeleted)
        {
            return hasLocalChanges
                ? Hold(localPayloadJson, ConflictClass.DeleteAgainstUpdate, [.. patches.Values])
                : new SafeMergeResult("{}", ConflictClass.RemoteOnly, [], false);
        }

        List<FieldPatch> changed = [];
        ConflictClass classification = ConflictClass.None;
        foreach (FieldPatch patch in patches.Values)
        {
            ConflictClass fieldClass = ConflictClassifier.Classify(patch);
            if (fieldClass == ConflictClass.None)
            {
                continue;
            }

            changed.Add(patch);
            classification = Combine(classification, fieldClass);
            if (fieldClass is ConflictClass.CriticalField or ConflictClass.ConcurrentField)
            {
                return Hold(localPayloadJson, classification, changed);
            }

            JsonObject target = fieldClass == ConflictClass.LocalOnly ? localPayload : remotePayload;
            localPayload[patch.FieldName] = target[patch.FieldName]?.DeepClone();
        }

        return new SafeMergeResult(localPayload.ToJsonString(), classification, changed, false);
    }

    public async Task<bool> ApplyRemoteAsync(
        LocalStoreDbContext context,
        string sourceId,
        RemoteChange change,
        CachedEntity cached,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(context);
        ArgumentNullException.ThrowIfNull(change);
        ArgumentNullException.ThrowIfNull(cached);
        List<OutboxOperation> localOperations = await context.OutboxOperations
            .Where(operation => operation.SourceId == sourceId &&
                operation.EntityType == change.EntityType &&
                operation.ClientUuid == change.ClientUuid &&
                (operation.Status == "Pending" || operation.Status == "Held") &&
                operation.OperationType != "ConflictResolution")
            .OrderBy(operation => operation.CreatedAtUtc)
            .ThenBy(operation => operation.OperationId)
            .ToListAsync(cancellationToken).ConfigureAwait(false);
        List<SyncEnvelope> envelopes = localOperations.Select(operation =>
            JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
            ?? throw new JsonException("A local outbox envelope is empty.")).ToList();
        bool hasHeldOperations = localOperations.Any(operation => operation.Status == "Held");
        JsonObject localPayload = ParseObject(cached.PayloadJson);
        JsonObject basePayload = (JsonObject)localPayload.DeepClone();
        Dictionary<string, FieldPatch> accumulated = new(StringComparer.Ordinal);
        bool localCreated = envelopes.Any(envelope => envelope.OperationType == SyncOperationType.Create);
        bool locallyDeleted = cached.DeletedAtUtc.HasValue ||
            envelopes.Any(envelope => envelope.OperationType == SyncOperationType.Delete);

        if (localCreated)
        {
            basePayload = new JsonObject();
            foreach (JsonProperty property in localPayload)
            {
                accumulated[property.Key] = new FieldPatch
                {
                    FieldName = property.Key,
                    BaseValue = null,
                    LocalValue = JsonSerializer.SerializeToElement(property.Value),
                    Criticality = FieldCriticality.Mergeable
                };
            }
        }

        foreach (SyncEnvelope envelope in envelopes)
        {
            foreach (FieldPatch patch in envelope.Patches)
            {
                if (!accumulated.TryGetValue(patch.FieldName, out FieldPatch? existing))
                {
                    accumulated[patch.FieldName] = patch with { LocalValue = JsonSerializer.SerializeToElement(localPayload[patch.FieldName]) };
                }
                else
                {
                    accumulated[patch.FieldName] = existing with { LocalValue = JsonSerializer.SerializeToElement(localPayload[patch.FieldName]) };
                }
            }
        }

        foreach (FieldPatch patch in accumulated.Values)
        {
            basePayload[patch.FieldName] = patch.BaseValue.HasValue
                ? JsonNode.Parse(patch.BaseValue.Value.GetRawText())
                : null;
        }

        bool remoteDeleted = string.Equals(change.Operation, "delete", StringComparison.OrdinalIgnoreCase);
        if (locallyDeleted && !remoteDeleted)
        {
            return await HoldRemoteAsync(context, sourceId, change, cached, localOperations, envelopes,
                ConflictClass.DeleteAgainstUpdate, [.. accumulated.Values], cancellationToken).ConfigureAwait(false);
        }

        SafeMergeResult result = Merge(
            basePayload.ToJsonString(),
            cached.PayloadJson,
            change.PayloadJson,
            [.. accumulated.Values],
            remoteDeleted,
            cached.CanonicalId,
            change.CanonicalId);
        if (result.ShouldHold || hasHeldOperations)
        {
            return await HoldRemoteAsync(context, sourceId, change, cached, localOperations, envelopes,
                result.ShouldHold ? result.Classification : ConflictClass.ConcurrentField,
                result.Patches, cancellationToken).ConfigureAwait(false);
        }

        cached.PayloadJson = result.PayloadJson;
        cached.CanonicalId = change.CanonicalId ?? cached.CanonicalId;
        cached.RowVersion = change.RowVersion ?? cached.RowVersion;
        cached.UpdatedAtUtc = change.OccurredAtUtc ?? cached.UpdatedAtUtc;
        cached.DeletedAtUtc = remoteDeleted ? change.DeletedAtUtc : null;
        cached.DeletedByDeviceId = remoteDeleted ? change.DeletedByDeviceId : null;
        if (!remoteDeleted)
        {
            JsonObject merged = ParseObject(result.PayloadJson);
            string mergedSearchText = localOperations.Count == 0
                ? change.SearchText
                : string.Join(" ", merged.Select(property => property.Value)
                    .OfType<JsonValue>()
                    .Where(value => value.TryGetValue<string>(out _))
                    .Select(value => value.GetValue<string>()));
            cached.SearchTextNormalized = (string.IsNullOrWhiteSpace(mergedSearchText)
                ? change.SearchText
                : mergedSearchText).Trim().ToUpperInvariant();
        }

        ulong? remoteVersion = ParseVersion(change.ConcurrencyToken ?? change.RowVersion);
        for (int index = 0; index < localOperations.Count; index++)
        {
            SyncEnvelope envelope = envelopes[index];
            if (remoteDeleted || envelope.OperationType == SyncOperationType.Create && result.Classification == ConflictClass.Equivalent)
            {
                localOperations[index].Status = "Sent";
                continue;
            }

            if (remoteVersion.HasValue && envelope.OperationType != SyncOperationType.Create)
            {
                localOperations[index].EnvelopeJson = JsonSerializer.Serialize(envelope with { BaseVersion = remoteVersion });
            }
        }

        return false;
    }

    private async Task<bool> HoldRemoteAsync(
        LocalStoreDbContext context,
        string sourceId,
        RemoteChange change,
        CachedEntity cached,
        IReadOnlyList<OutboxOperation> localOperations,
        IReadOnlyList<SyncEnvelope> envelopes,
        ConflictClass classification,
        IReadOnlyList<FieldPatch> patches,
        CancellationToken cancellationToken)
    {
        await _conflictQueue.HoldAsync(context, sourceId, new HoldConflictRequest
        {
            EntityType = change.EntityType,
            ClientUuid = change.ClientUuid,
            ConflictClass = classification,
            RemotePayloadJson = change.PayloadJson,
            RemoteRowVersion = change.RowVersion,
            RemoteCanonicalId = change.CanonicalId,
            RemoteOccurredAtUtc = change.OccurredAtUtc,
            RemoteDeletedAtUtc = change.DeletedAtUtc,
            RemoteDeletedByDeviceId = change.DeletedByDeviceId,
            ServerConflictUuid = envelopes
                .Select(ReadServerConflictUuid)
                .LastOrDefault(conflictUuid => !string.IsNullOrWhiteSpace(conflictUuid)),
            OperationId = localOperations.Count == 0 ? null : envelopes[0].OperationId,
            Patches = patches
        }, cancellationToken).ConfigureAwait(false);
        return true;
    }

    private static ulong? ParseVersion(string? value) =>
        ulong.TryParse(value, out ulong parsed) ? parsed : null;

    private static string? ReadServerConflictUuid(SyncEnvelope? envelope) =>
        envelope?.ExtensionData?.TryGetValue(ServerConflictMetadata.ExtensionName, out JsonElement value) == true
            ? value.Deserialize<ServerConflictMetadata>()?.ConflictUuid
            : null;

    private static SafeMergeResult Hold(string localPayloadJson, ConflictClass classification, IReadOnlyList<FieldPatch> patches) =>
        new(localPayloadJson, classification, patches, true);

    private static JsonObject ParseObject(string json)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(json);
        return JsonNode.Parse(json) as JsonObject
            ?? throw new JsonException("A merge payload must be a JSON object.");
    }

    private static JsonElement? Element(JsonNode? node) =>
        node is null ? null : JsonSerializer.SerializeToElement(node);

    private static bool IsCriticalField(string fieldName) =>
        CriticalFields.Contains(fieldName) ||
        fieldName.EndsWith("Id", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Contains("uuid", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Contains("amount", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Contains("balance", StringComparison.OrdinalIgnoreCase) ||
        fieldName.Contains("status", StringComparison.OrdinalIgnoreCase);

    private static ConflictClass Combine(ConflictClass current, ConflictClass next)
    {
        if (current == ConflictClass.CriticalField || next == ConflictClass.CriticalField)
        {
            return ConflictClass.CriticalField;
        }

        if (current == ConflictClass.ConcurrentField || next == ConflictClass.ConcurrentField)
        {
            return ConflictClass.ConcurrentField;
        }

        if (current == ConflictClass.None)
        {
            return next;
        }

        if (current == ConflictClass.Equivalent && next is ConflictClass.LocalOnly or ConflictClass.RemoteOnly)
        {
            return next;
        }

        if (next == ConflictClass.Equivalent)
        {
            return current;
        }

        bool hasLocal = current == ConflictClass.LocalOnly || next == ConflictClass.LocalOnly;
        bool hasRemote = current == ConflictClass.RemoteOnly || next == ConflictClass.RemoteOnly;
        return hasLocal && hasRemote ? ConflictClass.NonOverlapping : current;
    }
}
