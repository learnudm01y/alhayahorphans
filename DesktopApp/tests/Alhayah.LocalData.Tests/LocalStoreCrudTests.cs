using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Repositories;
using Alhayah.LocalData.Services;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalData.Tests;

public sealed class LocalStoreCrudTests
{
    [Fact]
    public async Task OfflineCrud_AfterReopeningStore_PersistsSearchPatchAndSoftDelete()
    {
        using LocalStoreTestHarness harness = new();
        Guid clientUuid = Guid.NewGuid();
        Guid createOperationId = Guid.NewGuid();
        CachedEntity created;

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            UnitOfWork unitOfWork = new(context);
            created = await unitOfWork.CreateAsync(
                LocalStoreTestHarness.CreateEnvelope(createOperationId, clientUuid, SyncOperationType.Create),
                "{\"display_name\":\"Synthetic Alpha\"}",
                "Synthetic Alpha",
                LocalStoreTestHarness.Audit);
        }

        CachedEntity patched;
        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            LocalCacheRepository cache = new(context);
            CachedEntity reopened = Assert.Single(await cache.SearchAsync(
                "laravel:synthetic-installation",
                "synthetic_records",
                "alpha"));
            Assert.Equal(created.ClientUuid, reopened.ClientUuid);

            SyncEnvelope patchEnvelope = LocalStoreTestHarness.CreateEnvelope(
                Guid.NewGuid(),
                clientUuid,
                SyncOperationType.Update,
                reopened.RowVersion,
                [LocalStoreTestHarness.CreateDisplayNamePatch("Synthetic Alpha", "Synthetic Beta")]);
            UnitOfWork unitOfWork = new(context);
            patched = await unitOfWork.PatchAsync(
                patchEnvelope,
                "Synthetic Beta",
                LocalStoreTestHarness.Audit);
        }

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            LocalCacheRepository cache = new(context);
            CachedEntity result = Assert.Single(await cache.SearchAsync(
                "laravel:synthetic-installation",
                "synthetic_records",
                "beta"));
            Assert.Contains("Synthetic Beta", result.PayloadJson, StringComparison.Ordinal);

            SyncEnvelope deleteEnvelope = LocalStoreTestHarness.CreateEnvelope(
                Guid.NewGuid(),
                clientUuid,
                SyncOperationType.Delete,
                patched.RowVersion);
            await new UnitOfWork(context).SoftDeleteAsync(deleteEnvelope, LocalStoreTestHarness.Audit);
        }

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            LocalCacheRepository cache = new(context);
            Assert.Null(await cache.GetAsync("laravel:synthetic-installation", "synthetic_records", clientUuid));
            Assert.Empty(await cache.SearchAsync("laravel:synthetic-installation", "synthetic_records", "beta"));

            CachedEntity tombstone = await context.CachedEntities
                .IgnoreQueryFilters()
                .SingleAsync(item => item.ClientUuid == clientUuid);
            Assert.NotNull(tombstone.DeletedAtUtc);
            Assert.Equal("synthetic-device", tombstone.DeletedByDeviceId);
            Assert.Equal(3, await context.OutboxOperations.CountAsync());
            Assert.Equal(3, (await new OutboxRepository(context).GetPendingAsync()).Count);
            Assert.Equal(3, await context.LocalChangeLog.CountAsync());
            Assert.Equal(3, await context.AuditEntries.CountAsync());
            ClientIdentityMappingEntity identity = await context.ClientIdentityMap
                .SingleAsync(item => item.ClientUuid == clientUuid);
            Assert.Null(identity.CanonicalId);
        }
    }

    [Fact]
    public async Task PatchAsync_WhenOutboxInsertFails_RollsBackEntityChangeLogAndAudit()
    {
        using LocalStoreTestHarness harness = new();
        Guid clientUuid = Guid.NewGuid();
        Guid duplicateOperationId = Guid.NewGuid();
        string originalRowVersion;

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            CachedEntity created = await new UnitOfWork(context).CreateAsync(
                LocalStoreTestHarness.CreateEnvelope(duplicateOperationId, clientUuid, SyncOperationType.Create),
                "{\"display_name\":\"Synthetic Original\"}",
                "Synthetic Original",
                LocalStoreTestHarness.Audit);
            originalRowVersion = created.RowVersion!;
        }

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            SyncEnvelope duplicateOperation = LocalStoreTestHarness.CreateEnvelope(
                duplicateOperationId,
                clientUuid,
                SyncOperationType.Update,
                originalRowVersion,
                [LocalStoreTestHarness.CreateDisplayNamePatch("Synthetic Original", "Synthetic Rolled Back")]);

            await Assert.ThrowsAsync<DbUpdateException>(() => new UnitOfWork(context).PatchAsync(
                duplicateOperation,
                "Synthetic Rolled Back",
                LocalStoreTestHarness.Audit));
        }

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            CachedEntity stored = await context.CachedEntities.SingleAsync(item => item.ClientUuid == clientUuid);
            Assert.Equal(originalRowVersion, stored.RowVersion);
            Assert.Equal("{\"display_name\":\"Synthetic Original\"}", stored.PayloadJson);
            Assert.Single(await context.OutboxOperations.ToArrayAsync());
            Assert.Single(await context.LocalChangeLog.ToArrayAsync());
            Assert.Single(await context.AuditEntries.ToArrayAsync());
        }
    }

    [Fact]
    public async Task PatchAsync_WithStaleRowVersion_DoesNotCreateOutboxOperation()
    {
        using LocalStoreTestHarness harness = new();
        Guid clientUuid = Guid.NewGuid();

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            await new UnitOfWork(context).CreateAsync(
                LocalStoreTestHarness.CreateEnvelope(Guid.NewGuid(), clientUuid, SyncOperationType.Create),
                "{\"display_name\":\"Synthetic Original\"}",
                "Synthetic Original",
                LocalStoreTestHarness.Audit);
        }

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            SyncEnvelope stalePatch = LocalStoreTestHarness.CreateEnvelope(
                Guid.NewGuid(),
                clientUuid,
                SyncOperationType.Update,
                "stale-row-version",
                [LocalStoreTestHarness.CreateDisplayNamePatch("Synthetic Original", "Synthetic Changed")]);

            await Assert.ThrowsAsync<DbUpdateConcurrencyException>(() => new UnitOfWork(context).PatchAsync(
                stalePatch,
                "Synthetic Changed",
                LocalStoreTestHarness.Audit));
        }

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            Assert.Single(await context.OutboxOperations.ToArrayAsync());
            CachedEntity stored = await context.CachedEntities.SingleAsync(item => item.ClientUuid == clientUuid);
            Assert.Contains("Synthetic Original", stored.PayloadJson, StringComparison.Ordinal);
        }
    }
}
