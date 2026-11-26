using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Conflicts;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Outbox;
using Alhayah.SyncService.Services;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class ConflictQueueTests
{
    private const string SourceId = "laravel:synthetic-a";
    private static readonly DateTimeOffset Now = DateTimeOffset.Parse("2026-11-26T10:00:00Z");

    [Fact]
    public async Task HoldAsync_WithCriticalConflict_PreservesCacheAndExposesValueComparisons()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        string localJson = "{\"displayName\":\"Synthetic record\",\"status\":\"local-status\"}";
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            CachedEntity entity = await context.CachedEntities.SingleAsync();
            rowVersion = entity.RowVersion!;
            entity.PayloadJson = localJson;
            await context.SaveChangesAsync();
            ConflictQueue queue = Queue();
            await queue.HoldAsync(context, Hold(clientUuid, "remote-status", "remote-row-version"), CancellationToken.None);
        }

        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        ConflictReviewData conflict = Assert.Single(await Queue().ListAsync(verification, SourceId, CancellationToken.None));
        Assert.Equal("CriticalField", conflict.ConflictClass);
        Assert.Equal(1, conflict.ConflictVersion);
        Assert.Equal(rowVersion, conflict.RowVersion);
        Assert.Equal("local-status", Read(Assert.Single(conflict.Fields, field => field.FieldName == "status").LocalValue));
        Assert.Equal("remote-status", Read(Assert.Single(conflict.Fields, field => field.FieldName == "status").RemoteValue));
        Assert.Equal(localJson, (await verification.CachedEntities.SingleAsync()).PayloadJson);
    }

    [Fact]
    public async Task HoldAsync_WhenConflictAlreadyExists_HoldsNewLocalOperationsBeforeReplay()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
            await Queue().HoldAsync(context, Hold(clientUuid, "remote-status"), CancellationToken.None);
        }

        await harness.SeedPatchAsync(Guid.NewGuid(), clientUuid, rowVersion, Now);
        await using (LocalStoreDbContext verification = harness.Store.CreateContext())
        {
            Assert.Equal(2, await verification.OutboxOperations.CountAsync(item => item.Status == "Held"));
        }
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            await Queue().HoldAsync(context, Hold(clientUuid, "remote-status"), CancellationToken.None);
        }

        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Single(await verification.SyncConflicts.ToListAsync());
        Assert.Equal(2, await verification.OutboxOperations.CountAsync(item => item.Status == "Held"));
    }

    [Fact]
    public async Task ResolveAsync_WhileOffline_AtomicallyPersistsPendingDecisionOutboxLogAndAudit()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        Guid conflictId;
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
            conflictId = (await Queue().HoldAsync(context, Hold(clientUuid, "remote-status", "remote-row-version"), CancellationToken.None)).Id;
        }

        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            ConflictResolutionResponse response = await Queue().ResolveAsync(context, Decision(conflictId, rowVersion), User(), CancellationToken.None);
            Assert.Equal("PendingResolution", response.Status);
        }

        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        SyncConflict conflict = await verification.SyncConflicts.SingleAsync();
        OutboxOperation outbox = await verification.OutboxOperations.SingleAsync(item => item.OperationType == "ConflictResolution");
        LocalChangeLogEntry change = await verification.LocalChangeLog.SingleAsync(item => item.OperationId == outbox.OperationId);
        AuditEntry audit = await verification.AuditEntries.SingleAsync(item => item.OperationId == outbox.OperationId);
        Assert.Equal("PendingResolution", conflict.Status);
        Assert.Null(conflict.ResolvedAtUtc);
        Assert.Equal("Pending", outbox.Status);
        Assert.Equal(outbox.OperationId, change.OperationId);
        Assert.Equal(LocalPermissions.ResolveConflicts, audit.PermissionUsed);
        Assert.DoesNotContain("local-status", audit.PayloadSha256, StringComparison.Ordinal);
    }

    [Fact]
    public async Task ResolveAsync_WithStaleRowOrConflictVersion_IsRejectedWithoutOutbox()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        Guid conflictId;
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
            conflictId = (await Queue().HoldAsync(context, Hold(clientUuid, "remote-status"), CancellationToken.None)).Id;
        }

        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            await Assert.ThrowsAsync<DbUpdateConcurrencyException>(() => Queue().ResolveAsync(
                context, Decision(conflictId, "stale-row-version"), User(), CancellationToken.None));
            await Assert.ThrowsAsync<DbUpdateConcurrencyException>(() => Queue().ResolveAsync(
                context, Decision(conflictId, rowVersion, expectedConflictVersion: 2), User(), CancellationToken.None));
        }

        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal(1, await verification.OutboxOperations.CountAsync());
        Assert.Equal("PendingReview", (await verification.SyncConflicts.SingleAsync()).Status);
    }

    [Fact]
    public async Task ResolveAsync_WithRepeatedOperationId_IsIdempotentAndConfirmationIsRequiredBeforeResolved()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        Guid conflictId;
        string rowVersion;
        Guid resolutionOperationId = Guid.NewGuid();
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
            conflictId = (await Queue().HoldAsync(context, Hold(clientUuid, "remote-status"), CancellationToken.None)).Id;
        }

        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            ConflictResolutionRequest request = Decision(conflictId, rowVersion) with { ResolutionOperationId = resolutionOperationId };
            await Queue().ResolveAsync(context, request, User(), CancellationToken.None);
            ConflictResolutionResponse repeated = await Queue().ResolveAsync(context, request, User(), CancellationToken.None);
            Assert.Equal("PendingResolution", repeated.Status);
        }

        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            Assert.False(await Queue().ConfirmAsync(context, resolutionOperationId, Now, CancellationToken.None));
        }
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            Assert.Equal("PendingResolution", (await context.SyncConflicts.SingleAsync()).Status);
            OutboxOperation outbox = await context.OutboxOperations.SingleAsync(item => item.OperationId == resolutionOperationId);
            outbox.Status = "Sent";
            await context.SaveChangesAsync();
        }
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            Assert.True(await Queue().ConfirmAsync(context, resolutionOperationId, Now, CancellationToken.None));
            Assert.False(await Queue().ConfirmAsync(context, resolutionOperationId, Now, CancellationToken.None));
        }

        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal("Resolved", (await verification.SyncConflicts.SingleAsync()).Status);
        Assert.Equal(2, await verification.OutboxOperations.CountAsync());
        Assert.Equal(2, await verification.LocalChangeLog.CountAsync());
        Assert.Equal(2, await verification.AuditEntries.CountAsync());
    }

    [Fact]
    public async Task ConfirmAsync_WithLocalEditAfterDecision_ReleasesOnlyTheNewerOperationForReplay()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        SyncEnvelope original = await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        Guid conflictId;
        string rowVersion;
        Guid resolutionOperationId = Guid.NewGuid();
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
            conflictId = (await Queue().HoldAsync(context,
                Hold(clientUuid, "remote-status") with { OperationId = original.OperationId }, CancellationToken.None)).Id;
            await Queue().ResolveAsync(context,
                Decision(conflictId, rowVersion) with { ResolutionOperationId = resolutionOperationId }, User(), CancellationToken.None);
        }

        SyncEnvelope newer = await harness.SeedPatchAsync(Guid.NewGuid(), clientUuid, rowVersion, Now.AddMinutes(1));
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            Assert.Equal("Held", (await context.OutboxOperations.SingleAsync(item => item.OperationId == newer.OperationId)).Status);
            OutboxOperation resolution = await context.OutboxOperations.SingleAsync(item => item.OperationId == resolutionOperationId);
            resolution.Status = "Sent";
            await context.SaveChangesAsync();
            Assert.True(await Queue().ConfirmAsync(context, resolutionOperationId, Now.AddMinutes(2), CancellationToken.None));
        }

        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal("Sent", (await verification.OutboxOperations.SingleAsync(item => item.OperationId == original.OperationId)).Status);
        Assert.Equal("Pending", (await verification.OutboxOperations.SingleAsync(item => item.OperationId == newer.OperationId)).Status);
        Assert.Equal("Resolved", (await verification.SyncConflicts.SingleAsync()).Status);
    }

    [Fact]
    public async Task ResolveAsync_WithSelectedFields_RejectsIdentityAndFieldsOutsideConflict()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        Guid conflictId;
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
            conflictId = (await Queue().HoldAsync(context, Hold(clientUuid, "remote-status"), CancellationToken.None)).Id;
        }

        await using LocalStoreDbContext context = harness.Store.CreateContext();
        ConflictResolutionRequest selected = Decision(conflictId, rowVersion) with
        {
            Decision = "selected-fields",
            SelectedFields = ["status", "canonicalId", "unrelated"]
        };
        SyncConflict conflict = await context.SyncConflicts.SingleAsync();
        ConflictSnapshot snapshot = ConflictQueue.ReadSnapshot(conflict);
        SyncEnvelope envelope = JsonSerializer.Deserialize<SyncEnvelope>(conflict.EnvelopeJson)!;
        FieldPatch identityPatch = new()
        {
            FieldName = "canonicalId",
            BaseValue = JsonSerializer.SerializeToElement("synthetic-base-id"),
            LocalValue = JsonSerializer.SerializeToElement("synthetic-local-id"),
            RemoteValue = JsonSerializer.SerializeToElement("synthetic-remote-id"),
            Criticality = FieldCriticality.Critical
        };
        conflict.EnvelopeJson = JsonSerializer.Serialize(envelope with
        {
            Patches = [.. snapshot.Patches, identityPatch],
            ExtensionData = new Dictionary<string, JsonElement>
            {
                ["desktopConflictSnapshot"] = JsonSerializer.SerializeToElement(snapshot with
                {
                    Patches = [.. snapshot.Patches, identityPatch]
                })
            }
        });
        await context.SaveChangesAsync();

        await Assert.ThrowsAsync<ArgumentException>(() => Queue().ResolveAsync(context, selected, User(), CancellationToken.None));
        Assert.Equal(1, await context.OutboxOperations.CountAsync());
        Assert.Equal("PendingReview", (await context.SyncConflicts.SingleAsync()).Status);
    }

    [Fact]
    public async Task ApplyRemoteAsync_WhenServerConflictIsOnEarlierOperation_PreservesServerConflictIdentity()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        SyncEnvelope create = await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
        }

        await harness.SeedPatchAsync(Guid.NewGuid(), clientUuid, rowVersion, Now);
        string serverConflictUuid = Guid.NewGuid().ToString();
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            OutboxOperation operation = await context.OutboxOperations.SingleAsync(item => item.OperationId == create.OperationId);
            SyncEnvelope envelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)!;
            Dictionary<string, JsonElement> extensions = envelope.ExtensionData is null
                ? []
                : new Dictionary<string, JsonElement>(envelope.ExtensionData);
            extensions[ServerConflictMetadata.ExtensionName] = JsonSerializer.SerializeToElement(new ServerConflictMetadata
            {
                ConflictUuid = serverConflictUuid
            });
            operation.EnvelopeJson = JsonSerializer.Serialize(envelope with { ExtensionData = extensions });
            await context.SaveChangesAsync();

            CachedEntity cached = await context.CachedEntities.SingleAsync();
            SafeMergeService merge = new(Queue());
            bool held = await merge.ApplyRemoteAsync(context, SourceId, new RemoteChange
            {
                ChangeId = 43,
                ClientUuid = clientUuid,
                EntityType = "synthetic.records",
                PayloadJson = "{\"displayName\":\"Synthetic remote\"}",
                SearchText = "Synthetic remote",
                RowVersion = "2",
                OccurredAtUtc = Now
            }, cached, CancellationToken.None);

            Assert.True(held);
            SyncConflict conflict = await context.SyncConflicts.SingleAsync();
            Assert.Equal(serverConflictUuid, ConflictQueue.ReadSnapshot(conflict).ServerConflictUuid);
        }
    }

    [Fact]
    public async Task ApplyRemoteAsync_WhenConflictAlreadyHeld_PreservesLocalPayloadForNewerRemoteVersion()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        string rowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
        }
        await harness.SeedPatchAsync(Guid.NewGuid(), clientUuid, rowVersion, Now);

        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            CachedEntity cached = await context.CachedEntities.SingleAsync();
            string localPayload = cached.PayloadJson;
            SafeMergeService merge = new(Queue());
            Assert.True(await merge.ApplyRemoteAsync(context, SourceId, new RemoteChange
            {
                ChangeId = 43,
                ClientUuid = clientUuid,
                EntityType = "synthetic.records",
                PayloadJson = "{\"displayName\":\"Synthetic remote one\"}",
                SearchText = "Synthetic remote one",
                RowVersion = "2",
                OccurredAtUtc = Now
            }, cached, CancellationToken.None));
            Assert.True(await merge.ApplyRemoteAsync(context, SourceId, new RemoteChange
            {
                ChangeId = 44,
                ClientUuid = clientUuid,
                EntityType = "synthetic.records",
                PayloadJson = "{\"displayName\":\"Synthetic remote two\"}",
                SearchText = "Synthetic remote two",
                RowVersion = "3",
                OccurredAtUtc = Now.AddMinutes(1)
            }, cached, CancellationToken.None));

            Assert.Equal(localPayload, cached.PayloadJson);
            Assert.Equal(rowVersion, cached.RowVersion);
            Assert.Equal(2, await context.SyncConflicts.CountAsync());
            Assert.Equal(2, await context.OutboxOperations.CountAsync(item => item.Status == "Held"));
        }
    }

    [Fact]
    public async Task OutboxReplay_AfterReconnect_UsesSameResolutionOperationWithoutDuplication()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        SyncEnvelope original = await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        Guid conflictId;
        string rowVersion;
        Guid resolutionOperationId = Guid.NewGuid();
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            rowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
            conflictId = (await Queue().HoldAsync(context,
                Hold(clientUuid, "remote-status") with { OperationId = original.OperationId }, CancellationToken.None)).Id;
            await Queue().ResolveAsync(
                context, Decision(conflictId, rowVersion) with { ResolutionOperationId = resolutionOperationId }, User(), CancellationToken.None);
        }

        ConfirmingOutboxTransport transport = new(SourceId, resolutionOperationId);
        ManualTimeProvider time = new(Now);
        OutboxProcessor processor = new(harness.Store, transport, new RetryPolicy(), time, Microsoft.Extensions.Logging.Abstractions.NullLogger<OutboxProcessor>.Instance);
        await processor.ProcessPendingAsync(CancellationToken.None);
        transport.Connected = false;
        await processor.ProcessPendingAsync(CancellationToken.None);
        transport.Connected = true;
        await processor.ProcessPendingAsync(CancellationToken.None);
        await processor.ProcessPendingAsync(CancellationToken.None);

        Assert.Equal([resolutionOperationId], transport.OperationIds.Distinct().ToArray());
        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal(2, await verification.OutboxOperations.CountAsync());
        Assert.Equal(2, await verification.LocalChangeLog.CountAsync());
        Assert.Equal("Resolved", (await verification.SyncConflicts.SingleAsync()).Status);
        Assert.Equal("Sent", (await verification.OutboxOperations.SingleAsync(item => item.OperationId == original.OperationId)).Status);
    }

    [Fact]
    public async Task RemotePageCommit_WithConcurrentEdit_AtomicallyHoldsChangeAndPublishesPiiFreeNotification()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        Guid clientUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(SourceId, Guid.NewGuid(), clientUuid);
        string baseRowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            baseRowVersion = (await context.CachedEntities.SingleAsync()).RowVersion!;
        }

        await harness.SeedPatchAsync(Guid.NewGuid(), clientUuid, baseRowVersion, Now);
        ManualTimeProvider time = new(Now);
        ConflictQueue queue = new(time);
        ConflictNotifier notifier = new(harness.Store,
            Microsoft.Extensions.Logging.Abstractions.NullLogger<ConflictNotifier>.Instance);
        List<ConflictNotification> notifications = [];
        notifier.Changed += (_, notification) => notifications.Add(notification);
        CursorStore cursors = new(harness.Store, new LaravelChangePageApplier(new SafeMergeService(queue)), time, notifier);
        RemoteChangePage page = new()
        {
            NextCursor = "synthetic-next-cursor",
            LastChangeLogId = 42,
            CompletesReconciliation = true,
            Changes =
            [
                new RemoteChange
                {
                    ChangeId = 42,
                    ClientUuid = clientUuid,
                    EntityType = "synthetic.records",
                    CanonicalId = "synthetic-canonical-id",
                    Operation = "update",
                    PayloadJson = "{\"displayName\":\"Synthetic remote\"}",
                    SearchText = "Synthetic remote",
                    RowVersion = "2",
                    OccurredAtUtc = Now
                }
            ]
        };

        CursorCommitResult result = await cursors.CommitPageAsync(SourceId, "changes", page, CancellationToken.None);
        CursorCommitResult repeated = await cursors.CommitPageAsync(SourceId, "changes", page, CancellationToken.None);

        Assert.Equal(CursorCommitResult.Applied, result);
        Assert.Equal(CursorCommitResult.AlreadyCommitted, repeated);
        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Contains("Synthetic patched", (await verification.CachedEntities.SingleAsync()).PayloadJson, StringComparison.Ordinal);
        Assert.Equal(2, await verification.OutboxOperations.CountAsync(item => item.Status == "Held"));
        SyncConflict conflict = await verification.SyncConflicts.SingleAsync();
        Assert.Equal("PendingReview", conflict.Status);
        Assert.Equal(42, (await verification.SyncCheckpoints.SingleAsync()).LastCommittedChangeId);
        ConflictNotification notification = Assert.Single(notifications);
        Assert.Equal(conflict.Id, notification.ConflictId);
        Assert.Equal("synthetic.records", notification.EntityType);
        Assert.Equal("PendingReview", notification.Status);
    }

    private static ConflictQueue Queue() => new(new ManualTimeProvider(Now));

    private static HoldConflictRequest Hold(Guid clientUuid, string remoteStatus, string? remoteRowVersion = null) => new()
    {
        EntityType = "synthetic.records",
        ClientUuid = clientUuid,
        ConflictClass = ConflictClass.CriticalField,
        RemotePayloadJson = JsonSerializer.Serialize(new { displayName = "Synthetic record", status = remoteStatus }),
        RemoteRowVersion = remoteRowVersion,
        Patches = [new FieldPatch
        {
            FieldName = "status",
            BaseValue = JsonSerializer.SerializeToElement("base-status"),
            LocalValue = JsonSerializer.SerializeToElement("local-status"),
            RemoteValue = JsonSerializer.SerializeToElement(remoteStatus),
            Criticality = FieldCriticality.Critical
        }]
    };

    private static ConflictResolutionRequest Decision(Guid conflictId, string rowVersion, int expectedConflictVersion = 1) => new()
    {
        ConflictId = conflictId,
        ExpectedRowVersion = rowVersion,
        ExpectedConflictVersion = expectedConflictVersion,
        Decision = "local"
    };

    private static (Guid UserId, string DeviceId, long ManifestVersion) User() => (Guid.NewGuid(), "synthetic-device", 1);

    private static string? Read(JsonElement? value) => value?.ValueKind == JsonValueKind.String ? value.Value.GetString() : null;

    private sealed class ConfirmingOutboxTransport(string sourceId, Guid confirmOperationId) : IOutboxTransport, IConfirmableOutboxTransport
    {
        public string SourceId { get; } = sourceId;
        public bool Connected { get; set; } = true;
        public List<Guid> OperationIds { get; } = [];

        public Task SendAsync(OutboxDelivery delivery, CancellationToken cancellationToken) => throw new NotSupportedException();

        public Task<bool> TrySendAsync(OutboxDelivery delivery, CancellationToken cancellationToken)
        {
            if (delivery.OperationId == confirmOperationId)
            {
                OperationIds.Add(delivery.OperationId);
            }

            return Task.FromResult(Connected && delivery.OperationId == confirmOperationId);
        }
    }
}
