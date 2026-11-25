using Alhayah.LocalData.Data;
using Alhayah.SyncService.Cursors;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class SourceChangeTests
{
    private const string Channel = "changes";

    [Fact]
    public async Task EndpointCertificateAndCredentialRotation_PreserveCursorAndOutbox()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        const string sourceId = "laravel:synthetic-a";
        Guid operationId = Guid.NewGuid();
        await harness.SeedCreateAsync(sourceId, operationId, Guid.NewGuid());
        CursorStore cursors = new(harness.Store, new SyntheticPageApplier(), TimeProvider.System);
        await cursors.RegisterSourceAsync(Registration(sourceId, "https://one.synthetic.invalid", "AA", "credential-a"));
        await cursors.CommitPageAsync(
            sourceId,
            Channel,
            CursorTests.Page(70, "snapshot-a", "cursor-70", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        CursorState before = await cursors.GetAsync(sourceId, Channel);

        await cursors.RegisterSourceAsync(Registration(sourceId, "https://two.synthetic.invalid", "BB", "credential-b"));
        await cursors.RegisterSourceAsync(Registration(sourceId, "https://three.synthetic.invalid", "CC", "credential-c"));

        Assert.Equal(before, await cursors.GetAsync(sourceId, Channel));
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        OutboxOperation operation = await context.OutboxOperations.SingleAsync();
        Assert.Equal(operationId, operation.OperationId);
        Assert.Equal("Pending", operation.Status);
    }

    [Fact]
    public async Task ChangedSourceId_StartsOnlyThatSourceAtFullReconciliationWithoutDeletingOutbox()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        const string previousSourceId = "laravel:synthetic-a";
        const string replacementSourceId = "laravel:synthetic-new";
        const string localSourceId = "local:synthetic-b";
        await harness.SeedCreateAsync(previousSourceId, Guid.NewGuid(), Guid.NewGuid());
        CursorStore cursors = new(harness.Store, new SyntheticPageApplier(), TimeProvider.System);
        await cursors.CommitPageAsync(
            previousSourceId,
            Channel,
            CursorTests.Page(80, "snapshot-old", "cursor-old", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        await cursors.CommitPageAsync(
            localSourceId,
            Channel,
            CursorTests.Page(81, "snapshot-local", "cursor-local", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        CursorState previous = await cursors.GetAsync(previousSourceId, Channel);
        CursorState local = await cursors.GetAsync(localSourceId, Channel);

        await cursors.RegisterSourceAsync(
            Registration(replacementSourceId, "https://replacement.synthetic.invalid", "DD", "credential-new"),
            cancellationToken: CancellationToken.None);

        CursorState replacement = await cursors.GetAsync(replacementSourceId, Channel);
        Assert.True(replacement.ReconciliationRequired);
        Assert.Equal(ReconciliationPhase.FullReconciliation, replacement.Phase);
        Assert.Null(replacement.Cursor);
        Assert.Null(replacement.SnapshotId);
        Assert.Null(replacement.LastChangeLogId);
        Assert.Equal(previous, await cursors.GetAsync(previousSourceId, Channel));
        Assert.Equal(local, await cursors.GetAsync(localSourceId, Channel));
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(1, await context.OutboxOperations.CountAsync(item => item.Status == "Pending"));
    }

    [Fact]
    public async Task ChangingLaravelSource_DoesNotResetLocalServerCursorOrRemoveItsOutbox()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        const string localSourceId = "local:synthetic-b";
        Guid localOperationId = Guid.NewGuid();
        await harness.SeedCreateAsync(localSourceId, localOperationId, Guid.NewGuid());
        CursorStore cursors = new(harness.Store, new SyntheticPageApplier(), TimeProvider.System);
        await cursors.CommitPageAsync(
            localSourceId,
            Channel,
            CursorTests.Page(82, "snapshot-local", "cursor-local", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);
        CursorState local = await cursors.GetAsync(localSourceId, Channel);

        await cursors.RegisterSourceAsync(
            Registration("laravel:synthetic-new", "https://new.synthetic.invalid", "EE", "credential-new"));

        Assert.Equal(local, await cursors.GetAsync(localSourceId, Channel));
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        OutboxOperation localOutbox = await context.OutboxOperations.SingleAsync();
        Assert.Equal(localOperationId, localOutbox.OperationId);
        Assert.Equal(localSourceId, localOutbox.SourceId);
        Assert.Equal("Pending", localOutbox.Status);
    }

    private static SourceConnectionRegistration Registration(
        string sourceId,
        string endpoint,
        string certificatePin,
        string credentialReference) => new()
    {
        SourceId = sourceId,
        Endpoint = endpoint,
        CertificatePin = certificatePin,
        CredentialReference = credentialReference
    };
}
