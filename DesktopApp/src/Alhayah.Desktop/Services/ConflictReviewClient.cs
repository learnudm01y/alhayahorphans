using System.Collections.Concurrent;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Utilities;

namespace Alhayah.Desktop.Services;

public sealed record ConflictReviewNotification(Guid ConflictId, string EntityType, string ConflictClass, string Status);

public interface IConflictReviewClient
{
    bool CanResolveConflicts { get; }

    event EventHandler<ConflictReviewNotification>? NotificationReceived;

    Task<ConflictListResponse> GetConflictsAsync(CancellationToken cancellationToken = default);

    Task<ConflictResolutionResponse> ResolveConflictAsync(
        ConflictResolutionRequest request,
        CancellationToken cancellationToken = default);
}

public sealed class ConflictReviewClient(
    ISyncServiceClient client,
    ILocalAuthService authentication,
    IAppLogger logger) : IConflictReviewClient
{
    private readonly ConcurrentDictionary<Guid, string> _notificationStates = new();

    public bool CanResolveConflicts => authentication.CanResolveConflicts;

    public event EventHandler<ConflictReviewNotification>? NotificationReceived;

    public async Task<ConflictListResponse> GetConflictsAsync(CancellationToken cancellationToken = default)
    {
        EnsurePermission();
        ConflictListResponse response = await client.GetConflictsAsync(cancellationToken);
        foreach (ConflictReviewData conflict in response.Items)
        {
            if (conflict.Status is not ("PendingReview" or "PendingResolution"))
            {
                _notificationStates.TryRemove(conflict.ConflictId, out _);
                continue;
            }

            if (_notificationStates.TryGetValue(conflict.ConflictId, out string? publishedStatus) &&
                string.Equals(publishedStatus, conflict.Status, StringComparison.Ordinal))
            {
                continue;
            }

            _notificationStates[conflict.ConflictId] = conflict.Status;
            NotificationReceived?.Invoke(this, new ConflictReviewNotification(
                conflict.ConflictId, conflict.EntityType, conflict.ConflictClass, conflict.Status));
            logger.TryLog(AppLogEvent.ConflictNotificationReceived);
        }

        return response;
    }

    public async Task<ConflictResolutionResponse> ResolveConflictAsync(
        ConflictResolutionRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        EnsurePermission();
        ConflictResolutionResponse response = await client.ResolveConflictAsync(request, cancellationToken).ConfigureAwait(false);
        logger.TryLog(response.Status == "Resolved"
            ? AppLogEvent.ConflictDecisionConfirmed
            : AppLogEvent.ConflictDecisionPending);
        return response;
    }

    private void EnsurePermission()
    {
        if (!CanResolveConflicts)
        {
            throw new UnauthorizedAccessException("The active offline session does not grant conflict resolution.");
        }
    }
}
