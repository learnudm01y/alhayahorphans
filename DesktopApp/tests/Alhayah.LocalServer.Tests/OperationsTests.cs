using System.Data.Common;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class OperationsTests
{
    private const string DeviceId = "synthetic-device-020";

    [Fact]
    public async Task ExecuteAsync_WithRepeatedOperation_ReturnsStoredResultWithoutRepeatingBusinessEffect()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (ConflictService conflicts, IdempotencyService service) = CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        SyncEnvelope envelope = LocalOperationTestFactory.CreateEnvelope(operationId, clientUuid);
        await RegisterResolvedIdentityAsync(service, envelope.Entity);

        LocalOperationResult first = await service.ExecuteAsync(
            operationId,
            "synthetic-idempotency-key",
            DeviceId,
            "aid.create",
            envelope,
            CommitAidAsync);
        LocalOperationResult replay = await service.ExecuteAsync(
            operationId,
            "synthetic-idempotency-key",
            DeviceId,
            "aid.create",
            envelope,
            CommitAidAsync);

        await using LocalDbContext verification = harness.CreateContext();
        Assert.Equal(first, replay);
        Assert.Equal(LocalOperationStatus.Completed, replay.Status);
        Assert.Equal(1, await verification.AidOperations.CountAsync());
        Assert.Equal(1, await verification.IdempotencyRecords.CountAsync());
        Assert.Equal(1, await verification.LocalChangeLogs.CountAsync());
        _ = conflicts;
    }

    [Fact]
    public async Task ExecuteAsync_WithSameOperationIdAndDifferentPayload_RejectsMismatchWithoutAnotherEffect()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        SyncEnvelope original = LocalOperationTestFactory.CreateEnvelope(operationId, Guid.NewGuid());
        await RegisterResolvedIdentityAsync(service, original.Entity);
        await service.ExecuteAsync(operationId, "key-original", DeviceId, "aid.create", original, CommitAidAsync);
        SyncEnvelope changed = LocalOperationTestFactory.CreateEnvelope(operationId, Guid.NewGuid());
        await RegisterResolvedIdentityAsync(service, changed.Entity);

        await Assert.ThrowsAsync<IdempotencyMismatchException>(() => service.ExecuteAsync(
            operationId,
            "key-original",
            DeviceId,
            "aid.create",
            changed,
            CommitAidAsync));

        await using LocalDbContext verification = harness.CreateContext();
        Assert.Equal(1, await verification.AidOperations.CountAsync());
        Assert.Equal(1, await verification.LocalChangeLogs.CountAsync());
    }

    [Fact]
    public async Task ExecuteAsync_WithSameIdempotencyKeyAndDifferentOperation_RejectsMismatch()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = CreateServices(context, time, new RecordingSyncNotifier());
        Guid firstOperationId = Guid.NewGuid();
        SyncEnvelope firstEnvelope = LocalOperationTestFactory.CreateEnvelope(firstOperationId, Guid.NewGuid());
        await RegisterResolvedIdentityAsync(service, firstEnvelope.Entity);
        await service.ExecuteAsync(firstOperationId, "shared-key", DeviceId, "aid.create", firstEnvelope, CommitAidAsync);
        Guid secondOperationId = Guid.NewGuid();
        SyncEnvelope secondEnvelope = LocalOperationTestFactory.CreateEnvelope(secondOperationId, Guid.NewGuid());
        await RegisterResolvedIdentityAsync(service, secondEnvelope.Entity);

        await Assert.ThrowsAsync<IdempotencyMismatchException>(() => service.ExecuteAsync(
            secondOperationId,
            "shared-key",
            DeviceId,
            "aid.create",
            secondEnvelope,
            CommitAidAsync));

        await using LocalDbContext verification = harness.CreateContext();
        Assert.Equal(1, await verification.AidOperations.CountAsync());
    }

    [Fact]
    public async Task ExecuteAsync_WithConcurrentDuplicates_CommitsOneBusinessEffectAndOneStoredResult()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext setup = harness.CreateContext();
        await setup.Database.MigrateAsync();
        Guid operationId = Guid.NewGuid();
        SyncEnvelope envelope = LocalOperationTestFactory.CreateEnvelope(operationId, Guid.NewGuid());
        (_, IdempotencyService setupService) = CreateServices(setup, time, new RecordingSyncNotifier());
        await RegisterResolvedIdentityAsync(setupService, envelope.Entity);

        await using LocalDbContext firstContext = harness.CreateContext();
        await using LocalDbContext secondContext = harness.CreateContext();
        (_, IdempotencyService firstService) = CreateServices(firstContext, time, new RecordingSyncNotifier());
        (_, IdempotencyService secondService) = CreateServices(secondContext, time, new RecordingSyncNotifier());

        LocalOperationResult[] results = await Task.WhenAll(
            firstService.ExecuteAsync(operationId, "concurrent-key", DeviceId, "aid.create", envelope, CommitAidAsync),
            secondService.ExecuteAsync(operationId, "concurrent-key", DeviceId, "aid.create", envelope, CommitAidAsync));

        await using LocalDbContext verification = harness.CreateContext();
        Assert.Equal(results[0], results[1]);
        Assert.Equal(1, await verification.AidOperations.CountAsync());
        Assert.Equal(1, await verification.IdempotencyRecords.CountAsync());
        Assert.Equal(1, await verification.LocalChangeLogs.CountAsync());
    }

    [Fact]
    public async Task ExecuteAsync_WhenBusinessInsertFails_RollsBackResultAndBusinessEffectTogether()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using (LocalDbContext setup = harness.CreateContext())
        {
            await setup.Database.MigrateAsync();
        }

        DbContextOptionsBuilder<LocalDbContext> options = new();
        harness.Connections.Configure(options);
        options.AddInterceptors(new FailAidInsertInterceptor());
        await using LocalDbContext failingContext = new(options.Options, time);
        (_, IdempotencyService service) = CreateServices(failingContext, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        SyncEnvelope envelope = LocalOperationTestFactory.CreateEnvelope(operationId, Guid.NewGuid());
        await RegisterResolvedIdentityAsync(service, envelope.Entity);

        await Assert.ThrowsAsync<InvalidOperationException>(() => service.ExecuteAsync(
            operationId,
            "atomic-key",
            DeviceId,
            "aid.create",
            envelope,
            CommitAidAsync));

        await using LocalDbContext verification = harness.CreateContext();
        Assert.Equal(0, await verification.AidOperations.CountAsync());
        Assert.Equal(0, await verification.IdempotencyRecords.CountAsync());
        Assert.Equal(0, await verification.LocalChangeLogs.CountAsync());
    }

    [Fact]
    public async Task Retention_WithNinetyDayOfflineWindow_WarnsBeforeExpiryAndDoesNotCleanEarly()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        SyncEnvelope envelope = LocalOperationTestFactory.CreateEnvelope(operationId, Guid.NewGuid());
        await RegisterResolvedIdentityAsync(service, envelope.Entity);
        LocalOperationResult stored = await service.ExecuteAsync(
            operationId,
            "retention-key",
            DeviceId,
            "aid.create",
            envelope,
            CommitAidAsync);

        Assert.Equal(time.GetUtcNow().AddDays(90), stored.ExpiresAtUtc);
        time.Advance(TimeSpan.FromDays(82));
        Assert.Empty(await service.GetRetentionWarningsAsync());
        time.Advance(TimeSpan.FromDays(1));
        IdempotencyRetentionWarning warning = Assert.Single(await service.GetRetentionWarningsAsync());
        Assert.Equal(operationId, warning.OperationId);
        Assert.Equal(stored.ExpiresAtUtc, warning.ExpiresAtUtc);
        Assert.Equal(0, await service.CleanupExpiredAsync());
        time.Advance(TimeSpan.FromDays(8));
        Assert.Equal(1, await service.CleanupExpiredAsync());
        Assert.Null(await service.GetResultAsync(operationId));
    }

    [Fact]
    public async Task ExecuteAsync_WhenSignalRFails_PreservesCommittedOperationAndStoredResult()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = CreateServices(context, time, new ThrowingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        SyncEnvelope envelope = LocalOperationTestFactory.CreateEnvelope(operationId, Guid.NewGuid());
        await RegisterResolvedIdentityAsync(service, envelope.Entity);

        LocalOperationResult result = await service.ExecuteAsync(
            operationId,
            "notification-key",
            DeviceId,
            "aid.create",
            envelope,
            CommitAidAsync);

        await using LocalDbContext verification = harness.CreateContext();
        Assert.Equal(LocalOperationStatus.Completed, result.Status);
        Assert.Equal(1, await verification.AidOperations.CountAsync());
        Assert.NotNull(await verification.IdempotencyRecords.SingleOrDefaultAsync(item => item.OperationId == operationId));
    }

    [Fact]
    public async Task EvaluateUpdate_WhenEntityWasDeletedAfterExpectedVersion_HoldsCriticalConflictAndAuditsManualDecision()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        Guid entityId = Guid.NewGuid();
        long staleVersion;
        Guid staleToken;

        await using (LocalDbContext setup = harness.CreateContext())
        {
            await setup.Database.MigrateAsync();
            AidOperation aid = CreateAid(entityId, Guid.NewGuid());
            setup.AidOperations.Add(aid);
            await setup.SaveChangesAsync();
            staleVersion = aid.RowVersion;
            staleToken = aid.ConcurrencyToken;
            setup.AidOperations.Remove(aid);
            await setup.SaveChangesAsync();
        }

        await using LocalDbContext context = harness.CreateContext();
        (ConflictService conflicts, IdempotencyService service) = CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        SyncEnvelope envelope = LocalOperationTestFactory.CreateEnvelope(operationId, entityId);
        await RegisterResolvedIdentityAsync(service, envelope.Entity);

        LocalOperationResult result = await service.ExecuteAsync(
            operationId,
            "delete-update-key",
            DeviceId,
            "aid.update",
            envelope,
            async (db, currentEnvelope, cancellationToken) =>
            {
                AidOperation current = await db.AidOperations
                    .IgnoreQueryFilters()
                    .SingleAsync(item => item.Id == currentEnvelope.Entity.ClientUuid, cancellationToken);
                CriticalConflict? conflict = conflicts.EvaluateUpdate(
                    current,
                    operationId,
                    DeviceId,
                    staleVersion,
                    staleToken);
                return conflict is null
                    ? LocalBusinessResult.Completed(current.Id, current.RowVersion)
                    : LocalBusinessResult.Conflict(current.Id, current.RowVersion, conflict.ConflictType);
            });

        Assert.Equal(LocalOperationStatus.Conflict, result.Status);
        Assert.Equal(ConflictClass.DeleteAgainstUpdate.ToString(), result.ConflictType);
        CriticalConflict storedConflict = await context.CriticalConflicts.SingleAsync();
        Assert.Equal("PendingReview", storedConflict.Decision);
        Assert.True(await conflicts.RecordDecisionAsync(storedConflict.Id, "Confirmed", "synthetic-admin-020"));
        Assert.Equal("Confirmed", storedConflict.Decision);
        Assert.Equal("synthetic-admin-020", storedConflict.DecisionByDeviceId);
        Assert.NotNull(storedConflict.DecisionAtUtc);
        AidOperation storedAid = await context.AidOperations.IgnoreQueryFilters().SingleAsync();
        Assert.NotNull(storedAid.DeletedAtUtc);
        Assert.Equal(2, storedAid.RowVersion);
    }

    internal static (ConflictService Conflicts, IdempotencyService Service) CreateServices(
        LocalDbContext context,
        TimeProvider time,
        ILocalSyncNotifier notifier)
    {
        ConflictService conflicts = new(context, time);
        IdempotencyService service = new(context, time, notifier, conflicts);
        return (conflicts, service);
    }

    internal static Task RegisterResolvedIdentityAsync(IdempotencyService service, EntityDescriptor entity) =>
        service.RegisterClientUuidAsync(entity.EntityType, entity.ClientUuid, $"canonical-{entity.ClientUuid:N}");

    internal static Task<LocalBusinessResult> CommitAidAsync(
        LocalDbContext db,
        SyncEnvelope envelope,
        CancellationToken cancellationToken)
    {
        AidOperation aid = CreateAid(envelope.Entity.ClientUuid, envelope.OperationId);
        db.AidOperations.Add(aid);
        return Task.FromResult(LocalBusinessResult.Completed(aid.Id, 1));
    }

    private static AidOperation CreateAid(Guid id, Guid operationId) => new()
    {
        Id = id,
        OperationId = operationId,
        AidType = "SyntheticAid",
        Amount = 12.50m,
        CurrencyCode = "USD",
        Status = "Requested",
        OccurredAtUtc = new DateTimeOffset(2026, 11, 25, 8, 0, 0, TimeSpan.Zero),
        LastModifiedByDeviceId = DeviceId
    };

    private sealed class FailAidInsertInterceptor : DbCommandInterceptor
    {
        public override InterceptionResult<int> CommandExecuting(
            DbCommand command,
            CommandEventData eventData,
            InterceptionResult<int> result)
        {
            ThrowForAidInsert(command);
            return base.CommandExecuting(command, eventData, result);
        }

        public override ValueTask<InterceptionResult<int>> CommandExecutingAsync(
            DbCommand command,
            CommandEventData eventData,
            InterceptionResult<int> result,
            CancellationToken cancellationToken = default)
        {
            ThrowForAidInsert(command);
            return base.CommandExecutingAsync(command, eventData, result, cancellationToken);
        }

        private static void ThrowForAidInsert(DbCommand command)
        {
            if (command.CommandText.Contains("INSERT INTO \"aid_operations\"", StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidOperationException("Synthetic aid insertion failure.");
            }
        }
    }
}

internal static class LocalOperationTestFactory
{
    internal static SyncEnvelope CreateEnvelope(
        Guid operationId,
        Guid clientUuid,
        string entityType = "AidOperation",
        List<EntityReference>? references = null) => new()
    {
        OperationId = operationId,
        SourceId = new SyncSourceId("local:synthetic-operations"),
        DeviceId = "synthetic-device-020",
        Entity = new EntityDescriptor
        {
            EntityType = entityType,
            ClientUuid = clientUuid,
            References = references ?? new List<EntityReference>()
        },
        OperationType = SyncOperationType.Create,
        OccurredAtUtc = new DateTimeOffset(2026, 11, 25, 8, 0, 0, TimeSpan.Zero),
        Patches = Array.Empty<FieldPatch>()
    };
}

internal sealed class RecordingSyncNotifier : ILocalSyncNotifier
{
    public List<LocalOperationResult> Notifications { get; } = new();

    public Task NotifyOperationAsync(LocalOperationResult result, CancellationToken cancellationToken = default)
    {
        Notifications.Add(result);
        return Task.CompletedTask;
    }
}

internal sealed class ThrowingSyncNotifier : ILocalSyncNotifier
{
    public Task NotifyOperationAsync(LocalOperationResult result, CancellationToken cancellationToken = default) =>
        throw new InvalidOperationException("Synthetic SignalR failure.");
}
