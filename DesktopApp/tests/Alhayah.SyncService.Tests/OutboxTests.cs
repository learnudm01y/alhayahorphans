using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Repositories;
using Alhayah.LocalData.Services;
using Alhayah.SyncService.Outbox;
using Alhayah.SyncService.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class OutboxTests
{
    private static readonly DateTimeOffset Start = DateTimeOffset.Parse("2026-11-25T02:00:00Z");

    [Fact]
    public async Task UnitOfWorkCreate_CommitsEntityChangeLogAndOutboxTogether()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();

        await harness.SeedCreateAsync("laravel:synthetic-a", operationId, clientUuid);

        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Single(await context.CachedEntities.Where(item => item.ClientUuid == clientUuid).ToArrayAsync());
        Assert.Single(await context.LocalChangeLog.Where(item => item.OperationId == operationId).ToArrayAsync());
        OutboxOperation pending = Assert.Single(await new OutboxRepository(context).GetPendingAsync());
        Assert.Equal(operationId, pending.OperationId);
        Assert.Single(await context.AuditEntries.Where(item => item.OperationId == operationId).ToArrayAsync());
    }

    [Fact]
    public async Task TransientFailure_KeepsPendingAndBackoffSurvivesProcessorRestart()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid operationId = Guid.NewGuid();
        await harness.SeedCreateAsync("laravel:synthetic-a", operationId, Guid.NewGuid());
        ManualTimeProvider time = new(Start);
        RetryPolicy retry = CreateRetryPolicy();
        FakeOutboxTransport transport = new();
        transport.Enqueue((_, _) => Task.FromException(new IOException("Synthetic network outage.")));
        OutboxProcessor firstProcessor = CreateProcessor(harness, transport, retry, time);

        OutboxPassResult failed = await firstProcessor.ProcessPendingAsync(CancellationToken.None);

        Assert.Equal(0, failed.SentCount);
        Assert.Equal(1, failed.PendingCount);
        Assert.Equal(Start.AddSeconds(1), failed.NextRetryAtUtc);
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            OutboxOperation operation = await context.OutboxOperations.SingleAsync();
            Assert.Equal("Pending", operation.Status);
            Assert.Equal(1, operation.AttemptCount);
            Assert.Equal(Start, operation.LastAttemptAtUtc);
        }

        OutboxProcessor restarted = CreateProcessor(harness, transport, retry, time);
        OutboxPassResult deferred = await restarted.ProcessPendingAsync(CancellationToken.None);
        Assert.Equal(1, deferred.DeferredCount);
        Assert.Single(transport.SendOperationIds);

        time.Advance(TimeSpan.FromSeconds(1));
        OutboxPassResult sent = await restarted.ProcessPendingAsync(CancellationToken.None);
        Assert.Equal(1, sent.SentCount);
        Assert.Equal([operationId, operationId], transport.SendOperationIds);
        Assert.Single(transport.AppliedOperationIds);
    }

    [Fact]
    public async Task CrashBeforeAcknowledgementCommit_ReplaysOperationIdWithoutDuplicateRemoteEffect()
    {
        CommitCrashInterceptor crash = new();
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync(crash);
        Guid operationId = Guid.NewGuid();
        await harness.SeedCreateAsync("laravel:synthetic-a", operationId, Guid.NewGuid());
        ManualTimeProvider time = new(Start);
        FakeOutboxTransport transport = new();
        OutboxProcessor processor = CreateProcessor(harness, transport, CreateRetryPolicy(), time);
        crash.ArmBeforeCommit();

        OutboxPassResult interrupted = await processor.ProcessPendingAsync(CancellationToken.None);

        Assert.Equal(1, interrupted.PendingCount);
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            Assert.Equal("Pending", (await context.OutboxOperations.SingleAsync()).Status);
        }

        time.Advance(TimeSpan.FromSeconds(1));
        await processor.ProcessPendingAsync(CancellationToken.None);
        Assert.Equal([operationId, operationId], transport.SendOperationIds);
        Assert.Single(transport.AppliedOperationIds);
    }

    [Fact]
    public async Task CrashAfterAcknowledgementCommit_DoesNotReplaySentOperation()
    {
        CommitCrashInterceptor crash = new();
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync(crash);
        Guid operationId = Guid.NewGuid();
        await harness.SeedCreateAsync("laravel:synthetic-a", operationId, Guid.NewGuid());
        ManualTimeProvider time = new(Start);
        FakeOutboxTransport transport = new();
        OutboxProcessor processor = CreateProcessor(harness, transport, CreateRetryPolicy(), time);
        crash.ArmAfterCommit();

        await processor.ProcessPendingAsync(CancellationToken.None);
        await processor.ProcessPendingAsync(CancellationToken.None);

        Assert.Equal([operationId], transport.SendOperationIds);
        Assert.Single(transport.AppliedOperationIds);
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        OutboxOperation operation = await context.OutboxOperations.SingleAsync();
        Assert.Equal("Sent", operation.Status);
        Assert.Equal(1, operation.AttemptCount);
    }

    [Fact]
    public async Task CancellationDuringSend_PersistsPendingMetadataAndAllowsLaterRetry()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid operationId = Guid.NewGuid();
        await harness.SeedCreateAsync("laravel:synthetic-a", operationId, Guid.NewGuid());
        ManualTimeProvider time = new(Start);
        FakeOutboxTransport transport = new();
        using CancellationTokenSource cancellation = new();
        transport.Enqueue(async (_, _) =>
        {
            cancellation.Cancel();
            await Task.Yield();
            throw new OperationCanceledException(cancellation.Token);
        });
        OutboxProcessor processor = CreateProcessor(harness, transport, CreateRetryPolicy(), time);

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() => processor.ProcessPendingAsync(cancellation.Token));

        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            OutboxOperation operation = await context.OutboxOperations.SingleAsync();
            Assert.Equal("Pending", operation.Status);
            Assert.Equal(1, operation.AttemptCount);
            Assert.Equal(Start, operation.LastAttemptAtUtc);
        }

        time.Advance(TimeSpan.FromSeconds(1));
        OutboxPassResult result = await processor.ProcessPendingAsync(CancellationToken.None);
        Assert.Equal(1, result.SentCount);
        Assert.Equal([operationId, operationId], transport.SendOperationIds);
    }

    [Fact]
    public async Task FailedEarlierOperation_BlocksLaterDependentEntityOperation()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        Guid createId = Guid.NewGuid();
        await harness.SeedCreateAsync("laravel:synthetic-a", createId, clientUuid, Start.AddMinutes(-2));
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
        }

        Guid patchId = Guid.NewGuid();
        await harness.SeedPatchAsync(patchId, clientUuid, rowVersion, Start.AddMinutes(-1));
        FakeOutboxTransport transport = new();
        transport.Enqueue((_, _) => Task.FromException(new TimeoutException("Synthetic timeout.")));
        OutboxProcessor processor = CreateProcessor(harness, transport, CreateRetryPolicy(), new ManualTimeProvider(Start));

        OutboxPassResult result = await processor.ProcessPendingAsync(CancellationToken.None);

        Assert.Equal(1, result.PendingCount);
        Assert.Equal([createId], transport.SendOperationIds);
        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal(2, await verification.OutboxOperations.CountAsync(item => item.Status == "Pending"));
        Assert.Equal(0, await verification.OutboxOperations.CountAsync(item => item.OperationId == patchId && item.AttemptCount > 0));
    }

    [Fact]
    public async Task TemporaryFailure_DoesNotLogExceptionDetailsOrEnvelopeValues()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        await harness.SeedCreateAsync("laravel:synthetic-a", Guid.NewGuid(), Guid.NewGuid());
        FakeOutboxTransport transport = new();
        transport.Enqueue((_, _) => Task.FromException(new IOException("Synthetic private endpoint and credential.")));
        TestLogSink logs = new();
        OutboxProcessor processor = new(
            harness.Store,
            transport,
            CreateRetryPolicy(),
            new ManualTimeProvider(Start),
            logs.CreateLogger<OutboxProcessor>());

        await processor.ProcessPendingAsync(CancellationToken.None);

        string messages = string.Join('\n', logs.Messages);
        Assert.DoesNotContain("Synthetic private endpoint", messages, StringComparison.Ordinal);
        Assert.DoesNotContain("Synthetic record", messages, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-device", messages, StringComparison.Ordinal);
    }

    [Theory]
    [InlineData(0.0, 1, 500)]
    [InlineData(0.5, 1, 1000)]
    [InlineData(0.5, 2, 2000)]
    [InlineData(0.5, 8, 8000)]
    [InlineData(0.999999, 3, 5999.996)]
    public void RetryPolicy_UsesBoundedDeterministicExponentialBackoffWithJitter(
        double sample,
        int attempt,
        double expectedMilliseconds)
    {
        RetryPolicy retry = CreateRetryPolicy(() => sample);

        TimeSpan delay = retry.GetDelay(attempt);

        Assert.Equal(expectedMilliseconds, delay.TotalMilliseconds, precision: 3);
        Assert.InRange(delay, TimeSpan.FromMilliseconds(500), TimeSpan.FromMilliseconds(12000));
    }

    private static RetryPolicy CreateRetryPolicy(Func<double>? jitter = null) => new(
        new RetryOptions
        {
            InitialDelay = TimeSpan.FromSeconds(1),
            MaximumDelay = TimeSpan.FromSeconds(8)
        },
        jitter);

    private static OutboxProcessor CreateProcessor(
        SynchronizationTestHarness harness,
        IOutboxTransport transport,
        RetryPolicy retry,
        TimeProvider time) =>
        new(harness.Store, transport, retry, time, NullLogger<OutboxProcessor>.Instance);
}
