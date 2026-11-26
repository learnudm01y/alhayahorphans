using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Identity;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class IdentityMapConnectorTests
{
    private const string LaravelSourceId = "laravel:synthetic-a";

    [Fact]
    public async Task ApplyPushResultsAsync_AfterCanonicalIdArrives_RewritesLocalAndAttachmentReferencesOnce()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        ClientIdentityMapStore store = new(harness.Store, TimeProvider.System);
        Guid targetOperationId = Guid.NewGuid();
        Guid targetUuid = Guid.NewGuid();
        SyncEnvelope target = await harness.SeedCreateAsync(LaravelSourceId, targetOperationId, targetUuid);
        Guid fieldOperationId = Guid.NewGuid();
        Guid attachmentOperationId = Guid.NewGuid();
        await SeedDependentAsync(harness, fieldOperationId, targetUuid, EntityReferenceKind.Field);
        await SeedDependentAsync(harness, attachmentOperationId, targetUuid, EntityReferenceKind.Attachment);
        await MarkAttemptedAsync(harness, fieldOperationId, attachmentOperationId);
        string originalTargetJson;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            originalTargetJson = (await context.OutboxOperations.SingleAsync(operation => operation.OperationId == targetOperationId)).EnvelopeJson;
        }

        int changedBeforeResolution = await store.ResolvePendingReferencesAsync(CancellationToken.None);

        Assert.Equal(0, changedBeforeResolution);
        await AssertReferenceAsync(harness, fieldOperationId, null, "Pending");
        await AssertReferenceAsync(harness, attachmentOperationId, null, "Pending");

        LaravelPushOperationResult applied = AppliedResult(targetOperationId, targetUuid, "canonical-target-7", "server-row-7", 7);
        await store.ApplyPushResultsAsync([applied], CancellationToken.None);

        SyncEnvelope resolvedField = await AssertReferenceAsync(harness, fieldOperationId, "canonical-target-7", "Pending");
        SyncEnvelope resolvedAttachment = await AssertReferenceAsync(harness, attachmentOperationId, "canonical-target-7", "Pending");
        Assert.Equal(fieldOperationId, resolvedField.OperationId);
        Assert.Equal(attachmentOperationId, resolvedAttachment.OperationId);
        Assert.Equal(targetUuid, resolvedField.Entity.References[0].TargetClientUuid);
        Assert.Equal(EntityReferenceKind.Attachment, resolvedAttachment.Entity.References[0].Kind);
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            CachedEntity cached = await context.CachedEntities.SingleAsync(entity => entity.ClientUuid == targetUuid);
            Assert.Equal("canonical-target-7", cached.CanonicalId);
            Assert.Equal("server-row-7", cached.RowVersion);
            Assert.Equal("7", cached.BaseVersion);
            ClientIdentityMappingEntity mapping = await context.ClientIdentityMap.SingleAsync(entity => entity.ClientUuid == targetUuid);
            Assert.Equal("canonical-target-7", mapping.CanonicalId);
            Assert.NotNull(mapping.ResolvedAtUtc);
            OutboxOperation targetOutbox = await context.OutboxOperations.SingleAsync(operation => operation.OperationId == targetOperationId);
            Assert.Equal(originalTargetJson, targetOutbox.EnvelopeJson);
            Assert.All(
                await context.OutboxOperations.Where(operation => operation.OperationId == fieldOperationId || operation.OperationId == attachmentOperationId).ToListAsync(),
                operation => Assert.Null(operation.LastAttemptAtUtc));
        }

        string fieldJsonAfterResolution = (await ReadOutboxAsync(harness, fieldOperationId)).EnvelopeJson;
        string attachmentJsonAfterResolution = (await ReadOutboxAsync(harness, attachmentOperationId)).EnvelopeJson;
        await store.ApplyPushResultsAsync([applied], CancellationToken.None);
        int changedByReplay = await store.ResolvePendingReferencesAsync(CancellationToken.None);

        Assert.Equal(0, changedByReplay);
        Assert.Equal(fieldJsonAfterResolution, (await ReadOutboxAsync(harness, fieldOperationId)).EnvelopeJson);
        Assert.Equal(attachmentJsonAfterResolution, (await ReadOutboxAsync(harness, attachmentOperationId)).EnvelopeJson);
        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal(3, await verification.OutboxOperations.CountAsync());
        Assert.Equal(target.OperationId, (await ReadOutboxAsync(harness, targetOperationId)).OperationId);
    }

    [Fact]
    public async Task ApplyPushResultsAsync_WithReassignmentOrCanonicalReuse_RejectsWithoutChangingIdentityMap()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        ClientIdentityMapStore store = new(harness.Store, TimeProvider.System);
        Guid firstOperationId = Guid.NewGuid();
        Guid secondOperationId = Guid.NewGuid();
        Guid firstUuid = Guid.NewGuid();
        Guid secondUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(LaravelSourceId, firstOperationId, firstUuid);
        await harness.SeedCreateAsync(LaravelSourceId, secondOperationId, secondUuid);
        await store.ApplyPushResultsAsync(
            [AppliedResult(firstOperationId, firstUuid, "canonical-shared", "row-1", 1)],
            CancellationToken.None);

        await Assert.ThrowsAsync<InvalidOperationException>(() => store.ApplyPushResultsAsync(
            [AppliedResult(firstOperationId, firstUuid, "canonical-reassigned", "row-2", 2)],
            CancellationToken.None));
        await Assert.ThrowsAsync<InvalidOperationException>(() => store.ApplyPushResultsAsync(
            [AppliedResult(secondOperationId, secondUuid, "canonical-shared", "row-2", 2)],
            CancellationToken.None));

        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal("canonical-shared", (await context.ClientIdentityMap.SingleAsync(mapping => mapping.ClientUuid == firstUuid)).CanonicalId);
        Assert.Null((await context.ClientIdentityMap.SingleAsync(mapping => mapping.ClientUuid == secondUuid)).CanonicalId);
        Assert.Equal("canonical-shared", (await context.CachedEntities.SingleAsync(entity => entity.ClientUuid == firstUuid)).CanonicalId);
        Assert.Null((await context.CachedEntities.SingleAsync(entity => entity.ClientUuid == secondUuid)).CanonicalId);
    }

    [Fact]
    public async Task ApplyPushResultsAsync_WithLaterConflict_RollsBackEarlierMappingAndRowVersion()
    {
        await using SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
        ClientIdentityMapStore store = new(harness.Store, TimeProvider.System);
        Guid anchorOperationId = Guid.NewGuid();
        Guid firstOperationId = Guid.NewGuid();
        Guid secondOperationId = Guid.NewGuid();
        Guid anchorUuid = Guid.NewGuid();
        Guid firstUuid = Guid.NewGuid();
        Guid secondUuid = Guid.NewGuid();
        await harness.SeedCreateAsync(LaravelSourceId, anchorOperationId, anchorUuid);
        await harness.SeedCreateAsync(LaravelSourceId, firstOperationId, firstUuid);
        await harness.SeedCreateAsync(LaravelSourceId, secondOperationId, secondUuid);
        await store.ApplyPushResultsAsync(
            [AppliedResult(anchorOperationId, anchorUuid, "canonical-anchor", "row-anchor", 1)],
            CancellationToken.None);
        string originalFirstRowVersion;
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            originalFirstRowVersion = (await context.CachedEntities.SingleAsync(entity => entity.ClientUuid == firstUuid)).RowVersion!;
        }

        await Assert.ThrowsAsync<InvalidOperationException>(() => store.ApplyPushResultsAsync(
            [
                AppliedResult(firstOperationId, firstUuid, "canonical-first", "row-first", 2),
                AppliedResult(secondOperationId, secondUuid, "canonical-anchor", "row-second", 2)
            ],
            CancellationToken.None));

        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        CachedEntity first = await verification.CachedEntities.SingleAsync(entity => entity.ClientUuid == firstUuid);
        Assert.Null(first.CanonicalId);
        Assert.Equal(originalFirstRowVersion, first.RowVersion);
        Assert.Null((await verification.ClientIdentityMap.SingleAsync(mapping => mapping.ClientUuid == firstUuid)).CanonicalId);
        Assert.Equal("canonical-anchor", (await verification.ClientIdentityMap.SingleAsync(mapping => mapping.ClientUuid == anchorUuid)).CanonicalId);
    }

    private static async Task SeedDependentAsync(
        SynchronizationTestHarness harness,
        Guid operationId,
        Guid targetUuid,
        EntityReferenceKind kind)
    {
        Guid clientUuid = Guid.NewGuid();
        SyncEnvelope envelope = SynchronizationTestHarness.CreateEnvelope(LaravelSourceId, operationId, clientUuid) with
        {
            Entity = new EntityDescriptor
            {
                EntityType = kind == EntityReferenceKind.Field ? "synthetic.aid" : "synthetic.attachments",
                ClientUuid = clientUuid,
                CanonicalId = null,
                References =
                [
                    new EntityReference
                    {
                        FieldName = kind == EntityReferenceKind.Field ? "beneficiary_id" : "beneficiary_attachment",
                        TargetEntityType = "synthetic.records",
                        TargetClientUuid = targetUuid,
                        TargetCanonicalId = null,
                        Kind = kind
                    }
                ]
            }
        };
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        await new UnitOfWork(context).CreateAsync(
            envelope,
            "{\"synthetic\":true}",
            "Synthetic dependent",
            new LocalAuditContext(null, "synthetic.write", 1));
    }

    private static async Task MarkAttemptedAsync(SynchronizationTestHarness harness, params Guid[] operationIds)
    {
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        foreach (Guid operationId in operationIds)
        {
            OutboxOperation operation = await context.OutboxOperations.SingleAsync(item => item.OperationId == operationId);
            operation.AttemptCount = 2;
            operation.LastAttemptAtUtc = DateTimeOffset.Parse("2026-11-25T09:00:00Z");
        }

        await context.SaveChangesAsync();
    }

    private static async Task<SyncEnvelope> AssertReferenceAsync(
        SynchronizationTestHarness harness,
        Guid operationId,
        string? expectedCanonicalId,
        string expectedStatus)
    {
        OutboxOperation operation = await ReadOutboxAsync(harness, operationId);
        Assert.Equal(expectedStatus, operation.Status);
        SyncEnvelope envelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)!;
        Assert.Equal(expectedCanonicalId, envelope.Entity.References[0].TargetCanonicalId);
        return envelope;
    }

    private static async Task<OutboxOperation> ReadOutboxAsync(SynchronizationTestHarness harness, Guid operationId)
    {
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        return await context.OutboxOperations.AsNoTracking().SingleAsync(operation => operation.OperationId == operationId);
    }

    private static LaravelPushOperationResult AppliedResult(
        Guid operationId,
        Guid clientUuid,
        string canonicalId,
        string rowVersion,
        ulong baseVersion) => new()
    {
        OperationId = operationId,
        Status = "applied",
        ClientUuid = clientUuid,
        CanonicalId = canonicalId,
        RowVersion = rowVersion,
        BaseVersion = baseVersion
    };
}
