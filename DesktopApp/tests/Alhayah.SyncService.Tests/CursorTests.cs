using Alhayah.LocalData.Data;
using Alhayah.SyncService.Cursors;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class CursorTests
{
    private const string SourceId = "laravel:synthetic-a";
    private const string Channel = "changes";

    [Fact]
    public async Task CommittedPage_AdvancesCursorAndReplayDoesNotApplyChangesTwice()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        SyntheticPageApplier applier = new();
        ManualTimeProvider time = new(DateTimeOffset.Parse("2026-11-25T03:00:00Z"));
        CursorStore cursors = new(harness.Store, applier, time);
        RemoteChangePage page = Page(41, "snapshot-a", "cursor-41", Guid.NewGuid(), completesReconciliation: true);

        CursorCommitResult first = await cursors.CommitPageAsync(SourceId, Channel, page, CancellationToken.None);
        CursorCommitResult replay = await cursors.CommitPageAsync(SourceId, Channel, page, CancellationToken.None);

        Assert.Equal(CursorCommitResult.Applied, first);
        Assert.Equal(CursorCommitResult.AlreadyCommitted, replay);
        Assert.Equal(1, applier.ApplyCallCount);
        CursorState state = await cursors.GetAsync(SourceId, Channel);
        Assert.Equal("cursor-41", state.Cursor);
        Assert.Equal("snapshot-a", state.SnapshotId);
        Assert.Equal(41, state.LastChangeLogId);
        Assert.Equal(ReconciliationPhase.Reconciled, state.Phase);
        Assert.False(state.ReconciliationRequired);
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(1, await context.CachedEntities.CountAsync());
    }

    [Fact]
    public async Task FailedPageApplication_RollsBackChangesAndLeavesPreviousCursorForRetry()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        SyntheticPageApplier applier = new();
        CursorStore cursors = new(harness.Store, applier, TimeProvider.System);
        await cursors.CommitPageAsync(
            SourceId,
            Channel,
            Page(10, "snapshot-a", "cursor-10", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        CursorState committed = await cursors.GetAsync(SourceId, Channel);
        RemoteChangePage failedPage = Page(11, "snapshot-a", "cursor-11", Guid.NewGuid(), completesReconciliation: true);
        applier.FailNext = true;

        await Assert.ThrowsAsync<IOException>(() => cursors.CommitPageAsync(
            SourceId,
            Channel,
            failedPage,
            CancellationToken.None));

        Assert.Equal(committed, await cursors.GetAsync(SourceId, Channel));
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            Assert.Equal(1, await context.CachedEntities.CountAsync());
        }

        await cursors.CommitPageAsync(SourceId, Channel, failedPage, CancellationToken.None);
        Assert.Equal("cursor-11", (await cursors.GetAsync(SourceId, Channel)).Cursor);
        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal(2, await verification.CachedEntities.CountAsync());
    }

    [Fact]
    public async Task CancelledPageApplication_RollsBackAndCanReplayWithoutLossOrDuplicateEffect()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        SyntheticPageApplier applier = new();
        CursorStore cursors = new(harness.Store, applier, TimeProvider.System);
        await cursors.CommitPageAsync(
            SourceId,
            Channel,
            Page(20, "snapshot-a", "cursor-20", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        CursorState committed = await cursors.GetAsync(SourceId, Channel);
        RemoteChangePage page = Page(21, "snapshot-a", "cursor-21", Guid.NewGuid(), completesReconciliation: true);
        using CancellationTokenSource cancellation = new();
        applier.BeforeComplete = _ =>
        {
            cancellation.Cancel();
            throw new OperationCanceledException(cancellation.Token);
        };

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() => cursors.CommitPageAsync(
            SourceId,
            Channel,
            page,
            cancellation.Token));

        Assert.Equal(committed, await cursors.GetAsync(SourceId, Channel));
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            Assert.Equal(1, await context.CachedEntities.CountAsync());
        }

        applier.BeforeComplete = null;
        await cursors.CommitPageAsync(SourceId, Channel, page, CancellationToken.None);
        Assert.Equal("cursor-21", (await cursors.GetAsync(SourceId, Channel)).Cursor);
        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal(2, await verification.CachedEntities.CountAsync());
    }

    [Theory]
    [InlineData(false, CursorCommitResult.Applied, 2)]
    [InlineData(true, CursorCommitResult.AlreadyCommitted, 1)]
    public async Task CrashAroundPageCommit_ReplaysSafelyWithoutDuplicateEffect(
        bool afterCommit,
        CursorCommitResult expectedRetryResult,
        int expectedApplyCalls)
    {
        CommitCrashInterceptor crash = new();
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync(crash);
        SyntheticPageApplier applier = new();
        CursorStore cursors = new(harness.Store, applier, TimeProvider.System);
        RemoteChangePage page = Page(30, "snapshot-a", "cursor-30", Guid.NewGuid(), completesReconciliation: true);
        if (afterCommit)
        {
            crash.ArmAfterCommit();
        }
        else
        {
            crash.ArmBeforeCommit();
        }

        await Assert.ThrowsAsync<IOException>(() => cursors.CommitPageAsync(
            SourceId,
            Channel,
            page,
            CancellationToken.None));
        CursorCommitResult retry = await cursors.CommitPageAsync(SourceId, Channel, page, CancellationToken.None);

        Assert.Equal(expectedRetryResult, retry);
        Assert.Equal(expectedApplyCalls, applier.ApplyCallCount);
        Assert.Equal("cursor-30", (await cursors.GetAsync(SourceId, Channel)).Cursor);
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(1, await context.CachedEntities.CountAsync());
    }

    [Fact]
    public async Task LaravelAndLocalServerCursors_AdvanceIndependently()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        SyntheticPageApplier applier = new();
        CursorStore cursors = new(harness.Store, applier, TimeProvider.System);
        await cursors.CommitPageAsync(
            "laravel:synthetic-a",
            Channel,
            Page(50, "laravel-snapshot", "laravel-cursor", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        await cursors.CommitPageAsync(
            "local:synthetic-b",
            Channel,
            Page(90, "local-snapshot", "local-cursor", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);

        CursorState laravel = await cursors.GetAsync("laravel:synthetic-a", Channel);
        CursorState local = await cursors.GetAsync("local:synthetic-b", Channel);
        Assert.Equal(50, laravel.LastChangeLogId);
        Assert.Equal("laravel-snapshot", laravel.SnapshotId);
        Assert.Equal("laravel-cursor", laravel.Cursor);
        Assert.Equal(90, local.LastChangeLogId);
        Assert.Equal("local-snapshot", local.SnapshotId);
        Assert.Equal("local-cursor", local.Cursor);
    }

    [Fact]
    public async Task NonFinalReconciliationPage_KeepsFullReconciliationRequiredUntilFinalPage()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        CursorStore cursors = new(harness.Store, new SyntheticPageApplier(), TimeProvider.System);

        await cursors.CommitPageAsync(
            SourceId,
            Channel,
            Page(60, "snapshot-new", "cursor-60", Guid.NewGuid(), completesReconciliation: false),
            CancellationToken.None);
        CursorState during = await cursors.GetAsync(SourceId, Channel);
        await cursors.CommitPageAsync(
            SourceId,
            Channel,
            Page(61, "snapshot-new", "cursor-61", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        CursorState complete = await cursors.GetAsync(SourceId, Channel);

        Assert.True(during.ReconciliationRequired);
        Assert.Equal(ReconciliationPhase.Reconciling, during.Phase);
        Assert.Equal("snapshot-new", during.SnapshotId);
        Assert.False(complete.ReconciliationRequired);
        Assert.Equal(ReconciliationPhase.Reconciled, complete.Phase);
    }

    internal static RemoteChangePage Page(
        long lastChangeId,
        string snapshotId,
        string nextCursor,
        Guid clientUuid,
        bool completesReconciliation) => new()
    {
        SnapshotId = snapshotId,
        NextCursor = nextCursor,
        LastChangeLogId = lastChangeId,
        CompletesReconciliation = completesReconciliation,
        Changes =
        [
            new RemoteChange
            {
                ChangeId = lastChangeId,
                ClientUuid = clientUuid,
                EntityType = "synthetic.records",
                PayloadJson = "{\"displayName\":\"Synthetic remote\"}",
                SearchText = "Synthetic remote"
            }
        ]
    };
}

internal sealed class SyntheticPageApplier : IChangePageApplier
{
    public int ApplyCallCount { get; private set; }

    public bool FailNext { get; set; }

    public Func<CancellationToken, Task>? BeforeComplete { get; set; }

    public async Task ApplyAsync(
        LocalStoreDbContext context,
        string sourceId,
        string channel,
        RemoteChangePage page,
        CancellationToken cancellationToken)
    {
        ApplyCallCount++;
        foreach (RemoteChange change in page.Changes)
        {
            bool exists = await context.CachedEntities.IgnoreQueryFilters()
                .AnyAsync(item => item.ClientUuid == change.ClientUuid, cancellationToken);
            if (!exists)
            {
                context.CachedEntities.Add(new CachedEntity
                {
                    ClientUuid = change.ClientUuid,
                    SourceId = sourceId,
                    EntityType = change.EntityType,
                    CanonicalId = null,
                    PayloadJson = change.PayloadJson,
                    SearchTextNormalized = change.SearchText.ToUpperInvariant(),
                    RowVersion = $"remote-{change.ChangeId}",
                    CreatedAtUtc = DateTimeOffset.Parse("2026-11-25T03:00:00Z"),
                    UpdatedAtUtc = DateTimeOffset.Parse("2026-11-25T03:00:00Z")
                });
            }
        }

        await context.SaveChangesAsync(cancellationToken);
        if (BeforeComplete is not null)
        {
            await BeforeComplete(cancellationToken);
        }

        if (FailNext)
        {
            FailNext = false;
            throw new IOException("Synthetic page application failure.");
        }
    }
}
