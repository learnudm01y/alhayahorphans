using System.Globalization;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Storage;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;

namespace Alhayah.SyncService.Identity;

public sealed class ClientIdentityMapStore
{
    private readonly ISyncServiceStore _store;
    private readonly TimeProvider _timeProvider;

    public ClientIdentityMapStore(ISyncServiceStore store, TimeProvider timeProvider)
    {
        _store = store ?? throw new ArgumentNullException(nameof(store));
        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
    }

    public async Task<int> ResolvePendingReferencesAsync(CancellationToken cancellationToken)
    {
        await using LocalStoreDbContext context = _store.CreateContext();
        await using IDbContextTransaction transaction = await context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        ClientIdentityMap identities = await LoadIdentityMapAsync(context, cancellationToken).ConfigureAwait(false);
        int changed = await RewritePendingReferencesAsync(
            context,
            identities,
            excludedOperationIds: [],
            cancellationToken).ConfigureAwait(false);
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
        return changed;
    }

    public async Task ApplyPushResultsAsync(
        IReadOnlyList<LaravelPushOperationResult> results,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(results);
        LaravelPushOperationResult[] applied = results
            .Where(result => string.Equals(result.Status, "applied", StringComparison.OrdinalIgnoreCase))
            .ToArray();
        if (applied.Length == 0)
        {
            return;
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        await using IDbContextTransaction transaction = await context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        ClientIdentityMap identities = await LoadIdentityMapAsync(context, cancellationToken).ConfigureAwait(false);
        foreach (LaravelPushOperationResult result in applied)
        {
            ValidateAppliedResult(result);
        }

        Guid[] clientUuids = applied.Select(result => result.ClientUuid!.Value).ToArray();
        Dictionary<Guid, ClientIdentityMappingEntity> mappings = await context.ClientIdentityMap
            .ToDictionaryAsync(mapping => mapping.ClientUuid, cancellationToken)
            .ConfigureAwait(false);
        Dictionary<Guid, CachedEntity> cachedEntities = await context.CachedEntities
            .IgnoreQueryFilters()
            .Where(entity => clientUuids.Contains(entity.ClientUuid))
            .ToDictionaryAsync(entity => entity.ClientUuid, cancellationToken)
            .ConfigureAwait(false);

        foreach (LaravelPushOperationResult result in applied)
        {
            Guid clientUuid = result.ClientUuid!.Value;
            OutboxOperation operation = await context.OutboxOperations
                .SingleOrDefaultAsync(item => item.OperationId == result.OperationId, cancellationToken)
                .ConfigureAwait(false)
                ?? throw new InvalidOperationException("An applied result does not match a known outbox operation.");
            if (operation.ClientUuid != clientUuid)
            {
                throw new InvalidOperationException("An applied result does not match the outbox client UUID.");
            }

            identities = identities.Resolve(clientUuid, result.CanonicalId!);
        }

        DateTimeOffset resolvedAt = _timeProvider.GetUtcNow();
        foreach (LaravelPushOperationResult result in applied)
        {
            Guid clientUuid = result.ClientUuid!.Value;
            ClientIdentityMappingEntity mapping = mappings[clientUuid];
            mapping.CanonicalId = result.CanonicalId;
            mapping.ResolvedAtUtc ??= resolvedAt;
            if (!cachedEntities.TryGetValue(clientUuid, out CachedEntity? cached))
            {
                throw new InvalidOperationException("An applied result does not match a cached entity.");
            }

            cached.CanonicalId = result.CanonicalId;
            cached.RowVersion = result.RowVersion;
            cached.BaseVersion = result.BaseVersion?.ToString(CultureInfo.InvariantCulture);
        }

        await RewritePendingReferencesAsync(
            context,
            identities,
            applied.Select(result => result.OperationId).ToHashSet(),
            cancellationToken).ConfigureAwait(false);
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
    }

    private static async Task<ClientIdentityMap> LoadIdentityMapAsync(
        LocalStoreDbContext context,
        CancellationToken cancellationToken)
    {
        List<ClientIdentityMappingEntity> entities = await context.ClientIdentityMap
            .AsNoTracking()
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        ClientIdentityMap identities = ClientIdentityMap.Empty;
        foreach (ClientIdentityMappingEntity entity in entities)
        {
            identities = identities.AddPending(entity.EntityType, entity.ClientUuid);
            if (entity.CanonicalId is not null)
            {
                identities = identities.Resolve(entity.ClientUuid, entity.CanonicalId);
            }
        }

        return identities;
    }

    private static async Task<int> RewritePendingReferencesAsync(
        LocalStoreDbContext context,
        ClientIdentityMap identities,
        IReadOnlySet<Guid> excludedOperationIds,
        CancellationToken cancellationToken)
    {
        List<OutboxOperation> operations = await context.OutboxOperations
            .Where(operation => operation.Status == "Pending")
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        int changed = 0;
        foreach (OutboxOperation operation in operations)
        {
            if (excludedOperationIds.Contains(operation.OperationId))
            {
                continue;
            }

            SyncEnvelope original = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
                ?? throw new JsonException("A pending outbox envelope is empty.");
            SyncEnvelope resolved = identities.ResolvePendingReferences(original);
            if (original.OperationType == SyncOperationType.Create)
            {
                resolved = resolved with
                {
                    Entity = resolved.Entity with { CanonicalId = original.Entity.CanonicalId }
                };
            }

            string envelopeJson = JsonSerializer.Serialize(resolved);
            if (string.Equals(operation.EnvelopeJson, envelopeJson, StringComparison.Ordinal))
            {
                continue;
            }

            operation.EnvelopeJson = envelopeJson;
            operation.LastAttemptAtUtc = null;
            changed++;
        }

        return changed;
    }

    private static void ValidateAppliedResult(LaravelPushOperationResult result)
    {
        if (result.ClientUuid is null || result.ClientUuid == Guid.Empty ||
            string.IsNullOrWhiteSpace(result.CanonicalId) ||
            string.IsNullOrWhiteSpace(result.RowVersion))
        {
            throw new InvalidOperationException("An applied result is missing canonical identity or row version metadata.");
        }
    }
}
