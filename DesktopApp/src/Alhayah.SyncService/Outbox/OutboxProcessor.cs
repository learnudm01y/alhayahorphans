using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Repositories;
using Alhayah.SyncService.Services;
using Alhayah.SyncService.Storage;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService.Outbox;

public sealed record OutboxDelivery(Guid OperationId, string SourceId, SyncEnvelope Envelope);

public interface IOutboxTransport
{
    string SourceId { get; }

    Task SendAsync(OutboxDelivery delivery, CancellationToken cancellationToken);
}

public sealed record OutboxPassResult
{
    public int SentCount { get; init; }

    public int PendingCount { get; init; }

    public int DeferredCount { get; init; }

    public DateTimeOffset? NextRetryAtUtc { get; init; }
}

public sealed class OutboxProcessor
{
    private readonly ISyncServiceStore _store;
    private readonly IOutboxTransport _transport;
    private readonly string _sourceId;
    private readonly RetryPolicy _retryPolicy;
    private readonly TimeProvider _timeProvider;
    private readonly ILogger<OutboxProcessor> _logger;
    private readonly SemaphoreSlim _passGate = new(1, 1);

    public OutboxProcessor(
        ISyncServiceStore store,
        IOutboxTransport transport,
        RetryPolicy retryPolicy,
        TimeProvider timeProvider,
        ILogger<OutboxProcessor> logger)
    {
        _store = store ?? throw new ArgumentNullException(nameof(store));
        _transport = transport ?? throw new ArgumentNullException(nameof(transport));
        _sourceId = new SyncSourceId(transport.SourceId).Value;
        _retryPolicy = retryPolicy ?? throw new ArgumentNullException(nameof(retryPolicy));
        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    }

    public async Task<OutboxPassResult> ProcessPendingAsync(CancellationToken cancellationToken = default)
    {
        await _passGate.WaitAsync(cancellationToken).ConfigureAwait(false);
        try
        {
            IReadOnlyList<OutboxOperation> operations;
            await using (LocalStoreDbContext context = _store.CreateContext())
            {
                operations = (await new OutboxRepository(context).GetPendingAsync(cancellationToken).ConfigureAwait(false))
                    .Where(operation => string.Equals(operation.SourceId, _sourceId, StringComparison.Ordinal))
                    .ToArray();
            }

            int sentCount = 0;
            for (int index = 0; index < operations.Count; index++)
            {
                OutboxOperation operation = operations[index];
                DateTimeOffset now = _timeProvider.GetUtcNow();
                if (TryGetNextRetry(operation, out DateTimeOffset nextRetry) && now < nextRetry)
                {
                    return new OutboxPassResult
                    {
                        SentCount = sentCount,
                        DeferredCount = operations.Count - index,
                        NextRetryAtUtc = nextRetry
                    };
                }

                int attemptCount = operation.AttemptCount + 1;
                bool deliveryStarted = false;
                try
                {
                    cancellationToken.ThrowIfCancellationRequested();
                    SyncEnvelope envelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
                        ?? throw new JsonException("The outbox envelope is empty.");
                    if (envelope.OperationId != operation.OperationId ||
                        !string.Equals(envelope.SourceId.Value, operation.SourceId, StringComparison.Ordinal))
                    {
                        throw new JsonException("The outbox envelope identity does not match its operation.");
                    }

                    deliveryStarted = true;
                    await _transport.SendAsync(
                        new OutboxDelivery(operation.OperationId, operation.SourceId, envelope),
                        cancellationToken).ConfigureAwait(false);
                    if (await AcknowledgeAsync(operation.OperationId, attemptCount, cancellationToken).ConfigureAwait(false))
                    {
                        sentCount++;
                    }
                }
                catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
                {
                    if (deliveryStarted)
                    {
                        await RecordFailureAsync(
                            operation.OperationId,
                            attemptCount,
                            _timeProvider.GetUtcNow(),
                            CancellationToken.None).ConfigureAwait(false);
                    }

                    throw;
                }
                catch (Exception exception) when (IsRetryableOrPersistenceFailure(exception))
                {
                    DateTimeOffset failedAtUtc = _timeProvider.GetUtcNow();
                    bool remainsPending = await RecordFailureAsync(
                        operation.OperationId,
                        attemptCount,
                        failedAtUtc,
                        CancellationToken.None).ConfigureAwait(false);
                    if (!remainsPending)
                    {
                        sentCount++;
                        continue;
                    }

                    DateTimeOffset retryAtUtc = failedAtUtc + _retryPolicy.GetDelay(attemptCount);
                    _logger.LogWarning(new EventId(28101), "An outbox operation remains pending after a temporary failure.");
                    return new OutboxPassResult
                    {
                        SentCount = sentCount,
                        PendingCount = 1,
                        NextRetryAtUtc = retryAtUtc
                    };
                }
            }

            return new OutboxPassResult { SentCount = sentCount };
        }
        finally
        {
            _passGate.Release();
        }
    }

    private bool TryGetNextRetry(OutboxOperation operation, out DateTimeOffset nextRetry)
    {
        if (operation.AttemptCount > 0 && operation.LastAttemptAtUtc.HasValue)
        {
            nextRetry = operation.LastAttemptAtUtc.Value + _retryPolicy.GetDelay(operation.AttemptCount);
            return true;
        }

        nextRetry = default;
        return false;
    }

    private async Task<bool> AcknowledgeAsync(
        Guid operationId,
        int attemptCount,
        CancellationToken cancellationToken)
    {
        await using LocalStoreDbContext context = _store.CreateContext();
        await using IDbContextTransaction transaction = await context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        OutboxOperation? operation = await context.OutboxOperations
            .SingleOrDefaultAsync(item => item.OperationId == operationId, cancellationToken)
            .ConfigureAwait(false);
        if (operation is null || operation.Status == "Sent")
        {
            return false;
        }

        operation.Status = "Sent";
        operation.AttemptCount = attemptCount;
        operation.LastAttemptAtUtc = _timeProvider.GetUtcNow();
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
        return true;
    }

    private async Task<bool> RecordFailureAsync(
        Guid operationId,
        int attemptCount,
        DateTimeOffset attemptedAtUtc,
        CancellationToken cancellationToken)
    {
        await using LocalStoreDbContext context = _store.CreateContext();
        OutboxOperation? operation = await context.OutboxOperations
            .SingleOrDefaultAsync(item => item.OperationId == operationId, cancellationToken)
            .ConfigureAwait(false);
        if (operation is null || operation.Status == "Sent")
        {
            return false;
        }

        operation.Status = "Pending";
        operation.AttemptCount = Math.Max(operation.AttemptCount, attemptCount);
        operation.LastAttemptAtUtc = attemptedAtUtc;
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        return true;
    }

    private static bool IsRetryableOrPersistenceFailure(Exception exception) =>
        exception is IOException or
            TimeoutException or
            OperationCanceledException or
            JsonException or
            DbUpdateException or
            InvalidOperationException;
}
