using Alhayah.LocalData.Data;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalData.Repositories;

public sealed class LocalCacheRepository(LocalStoreDbContext context)
{
    private readonly LocalStoreDbContext _context = context ?? throw new ArgumentNullException(nameof(context));

    public Task<CachedEntity?> GetAsync(
        string sourceId,
        string entityType,
        Guid clientUuid,
        CancellationToken cancellationToken = default)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(sourceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(entityType);
        return _context.CachedEntities.SingleOrDefaultAsync(
            item => item.SourceId == sourceId && item.EntityType == entityType && item.ClientUuid == clientUuid,
            cancellationToken);
    }

    public async Task<IReadOnlyList<CachedEntity>> SearchAsync(
        string sourceId,
        string entityType,
        string query,
        CancellationToken cancellationToken = default)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(sourceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(entityType);
        ArgumentException.ThrowIfNullOrWhiteSpace(query);
        string normalizedQuery = NormalizeSearchText(query);
        CachedEntity[] results = await _context.CachedEntities
            .Where(item => item.SourceId == sourceId &&
                           item.EntityType == entityType &&
                           item.SearchTextNormalized.Contains(normalizedQuery))
            .OrderBy(item => item.CanonicalId)
            .ThenBy(item => item.ClientUuid)
            .Take(200)
            .ToArrayAsync(cancellationToken)
            .ConfigureAwait(false);
        return results;
    }

    internal Task<CachedEntity?> GetIncludingDeletedAsync(
        string sourceId,
        string entityType,
        Guid clientUuid,
        CancellationToken cancellationToken) =>
        _context.CachedEntities.IgnoreQueryFilters().SingleOrDefaultAsync(
            item => item.SourceId == sourceId && item.EntityType == entityType && item.ClientUuid == clientUuid,
            cancellationToken);

    internal static string NormalizeSearchText(string value) => value.Trim().ToUpperInvariant();
}
