using Alhayah.LocalData.Data;
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Merge;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.SyncService.Connectors;

public sealed class LocalChangePageApplier(SafeMergeService? mergeService = null) : IChangePageApplier
{
    private readonly SafeMergeService _mergeService = mergeService ?? new SafeMergeService();

    public async Task ApplyAsync(
        LocalStoreDbContext context,
        string sourceId,
        string channel,
        RemoteChangePage page,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(context);
        ArgumentNullException.ThrowIfNull(page);
        foreach (RemoteChange change in page.Changes)
        {
            CachedEntity? cached = await context.CachedEntities
                .IgnoreQueryFilters()
                .SingleOrDefaultAsync(
                    entity => entity.SourceId == sourceId &&
                        entity.EntityType == change.EntityType &&
                        entity.ClientUuid == change.ClientUuid,
                    cancellationToken)
                .ConfigureAwait(false);
            bool deleted = string.Equals(change.Operation, "delete", StringComparison.OrdinalIgnoreCase);
            if (cached is null)
            {
                cached = new CachedEntity
                {
                    ClientUuid = change.ClientUuid,
                    SourceId = sourceId,
                    EntityType = change.EntityType,
                    CanonicalId = change.CanonicalId,
                    PayloadJson = deleted ? "{}" : change.PayloadJson,
                    SearchTextNormalized = change.SearchText.Trim().ToUpperInvariant(),
                    BaseVersion = change.ConcurrencyToken,
                    RowVersion = change.RowVersion,
                    CreatedAtUtc = change.OccurredAtUtc ?? DateTimeOffset.UnixEpoch,
                    UpdatedAtUtc = change.OccurredAtUtc ?? DateTimeOffset.UnixEpoch
                };
                context.CachedEntities.Add(cached);
            }
            else
            {
                if (await _mergeService.ApplyRemoteAsync(context, sourceId, change, cached, cancellationToken).ConfigureAwait(false))
                {
                    continue;
                }

                EnsureCanonicalIdentity(cached.CanonicalId, change.CanonicalId);
                cached.BaseVersion = change.ConcurrencyToken ?? cached.BaseVersion;
            }

            cached.DeletedAtUtc = deleted ? change.DeletedAtUtc : null;
            cached.DeletedByDeviceId = deleted ? change.DeletedByDeviceId : null;
            await ApplyIdentityAsync(context, change, cancellationToken).ConfigureAwait(false);
            await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        }
    }

    private static async Task ApplyIdentityAsync(
        LocalStoreDbContext context,
        RemoteChange change,
        CancellationToken cancellationToken)
    {
        ClientIdentityMappingEntity? mapping = await context.ClientIdentityMap
            .SingleOrDefaultAsync(item => item.ClientUuid == change.ClientUuid, cancellationToken)
            .ConfigureAwait(false);
        if (mapping is null)
        {
            mapping = new ClientIdentityMappingEntity
            {
                ClientUuid = change.ClientUuid,
                EntityType = change.EntityType,
                CreatedAtUtc = change.OccurredAtUtc ?? DateTimeOffset.UnixEpoch
            };
            context.ClientIdentityMap.Add(mapping);
        }
        else if (!string.Equals(mapping.EntityType, change.EntityType, StringComparison.Ordinal))
        {
            throw new InvalidOperationException("A local client UUID is mapped to a different entity type.");
        }

        EnsureCanonicalIdentity(mapping.CanonicalId, change.CanonicalId);
        if (change.CanonicalId is not null)
        {
            bool reused = await context.ClientIdentityMap.AnyAsync(
                item => item.ClientUuid != change.ClientUuid &&
                    item.EntityType == change.EntityType &&
                    item.CanonicalId == change.CanonicalId,
                cancellationToken).ConfigureAwait(false);
            if (reused)
            {
                throw new InvalidOperationException("A local canonical identifier is already mapped to another client UUID.");
            }
        }

        mapping.CanonicalId = change.CanonicalId ?? mapping.CanonicalId;
        mapping.ResolvedAtUtc ??= mapping.CanonicalId is null ? null : change.OccurredAtUtc ?? DateTimeOffset.UnixEpoch;
    }

    private static void EnsureCanonicalIdentity(string? current, string? incoming)
    {
        if (current is not null && incoming is not null &&
            !string.Equals(current, incoming, StringComparison.Ordinal))
        {
            throw new InvalidOperationException("A local canonical identifier conflicts with the existing identity map.");
        }
    }
}
