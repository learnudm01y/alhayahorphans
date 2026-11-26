using System.Collections.Concurrent;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Storage;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService.Services;

public sealed record ConflictNotification(Guid ConflictId, string EntityType, string ConflictClass, string Status);

public sealed class ConflictNotifier(ISyncServiceStore store, ILogger<ConflictNotifier> logger)
{
    private readonly ConcurrentDictionary<Guid, byte> _published = new();

    public event EventHandler<ConflictNotification>? Changed;

    public async Task PublishPendingAsync(string sourceId, CancellationToken cancellationToken = default)
    {
        await using LocalStoreDbContext context = store.CreateContext();
        ConflictNotification[] notifications = await context.SyncConflicts
            .AsNoTracking()
            .Where(item => item.SourceId == sourceId && item.Status == "PendingReview")
            .OrderByDescending(item => item.CreatedAtUtc)
            .Take(200)
            .Select(item => new ConflictNotification(item.Id, item.EntityType, item.ConflictClass, item.Status))
            .ToArrayAsync(cancellationToken).ConfigureAwait(false);
        foreach (ConflictNotification notification in notifications)
        {
            if (!_published.TryAdd(notification.ConflictId, 0))
            {
                continue;
            }

            logger.LogInformation(new EventId(30101), "A critical conflict is awaiting manager review.");
            Changed?.Invoke(this, notification);
        }
    }
}
