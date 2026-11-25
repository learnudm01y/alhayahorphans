using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class ExternalReferenceTests
{
    private const string DeviceId = "synthetic-device-020";

    [Fact]
    public async Task ExecuteAsync_WithUnresolvedExternalReference_StaysPendingWithoutFinalBusinessEffect()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        Guid targetUuid = Guid.NewGuid();
        SyncEnvelope envelope = CreateEnvelopeWithReference(operationId, targetUuid);
        await OperationsTests.RegisterResolvedIdentityAsync(service, envelope.Entity);
        await service.RegisterClientUuidAsync("SyntheticBeneficiary", targetUuid);
        bool handlerCalled = false;

        LocalOperationResult result = await service.ExecuteAsync(
            operationId,
            "pending-reference-key",
            DeviceId,
            "aid.create",
            envelope,
            (db, resolvedEnvelope, cancellationToken) =>
            {
                handlerCalled = true;
                return OperationsTests.CommitAidAsync(db, resolvedEnvelope, cancellationToken);
            });

        Assert.False(handlerCalled);
        Assert.Equal(LocalOperationStatus.Pending, result.Status);
        Assert.Null(result.EntityId);
        Assert.Equal(0, await context.AidOperations.CountAsync());
        Assert.Equal(0, await context.LocalChangeLogs.CountAsync());
        IdempotencyRecord record = await context.IdempotencyRecords.SingleAsync();
        Assert.Equal(LocalOperationStatus.Pending, record.Status);
    }

    [Fact]
    public async Task ExecuteAsync_AfterCanonicalMapArrives_UsesTransformedReferenceWithOriginalOperationIdOnlyOnce()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        Guid targetUuid = Guid.NewGuid();
        SyncEnvelope original = CreateEnvelopeWithReference(operationId, targetUuid);
        await OperationsTests.RegisterResolvedIdentityAsync(service, original.Entity);
        await service.RegisterClientUuidAsync("SyntheticBeneficiary", targetUuid);
        List<SyncEnvelope> handled = new();

        LocalOperationResult pending = await service.ExecuteAsync(
            operationId,
            "resolved-reference-key",
            DeviceId,
            "aid.create",
            original,
            (db, resolvedEnvelope, cancellationToken) =>
            {
                handled.Add(resolvedEnvelope);
                return OperationsTests.CommitAidAsync(db, resolvedEnvelope, cancellationToken);
            });
        await service.ResolveCanonicalIdAsync("SyntheticBeneficiary", targetUuid, "canonical-beneficiary-001");

        await using (LocalDbContext transformedContext = harness.CreateContext())
        {
            IdempotencyRecord transformed = await transformedContext.IdempotencyRecords.SingleAsync();
            Assert.Contains("canonical-beneficiary-001", transformed.RequestJson, StringComparison.Ordinal);
            Assert.Equal(operationId, transformed.OperationId);
            Assert.Equal(LocalOperationStatus.Pending, transformed.Status);
        }

        LocalOperationResult completed = await service.ExecuteAsync(
            operationId,
            "resolved-reference-key",
            DeviceId,
            "aid.create",
            original,
            (db, resolvedEnvelope, cancellationToken) =>
            {
                handled.Add(resolvedEnvelope);
                return OperationsTests.CommitAidAsync(db, resolvedEnvelope, cancellationToken);
            });
        LocalOperationResult replay = await service.ExecuteAsync(
            operationId,
            "resolved-reference-key",
            DeviceId,
            "aid.create",
            original,
            (db, resolvedEnvelope, cancellationToken) =>
            {
                handled.Add(resolvedEnvelope);
                return OperationsTests.CommitAidAsync(db, resolvedEnvelope, cancellationToken);
            });

        Assert.Equal(LocalOperationStatus.Pending, pending.Status);
        Assert.Equal(LocalOperationStatus.Completed, completed.Status);
        Assert.Equal(completed, replay);
        SyncEnvelope resolved = Assert.Single(handled);
        Assert.Equal(operationId, resolved.OperationId);
        Assert.Equal("canonical-beneficiary-001", resolved.Entity.References[0].TargetCanonicalId);
        Assert.Equal(targetUuid, resolved.Entity.References[0].TargetClientUuid);
        Assert.Equal(1, await context.AidOperations.CountAsync());
        Assert.Equal(1, await context.LocalChangeLogs.CountAsync());
    }

    [Fact]
    public async Task CleanupExpiredAsync_WithUnresolvedReference_RetainsPendingOperationBeyondNinetyDays()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        SyncEnvelope envelope = CreateEnvelopeWithReference(operationId, Guid.NewGuid());
        await OperationsTests.RegisterResolvedIdentityAsync(service, envelope.Entity);
        await service.RegisterClientUuidAsync("SyntheticBeneficiary", envelope.Entity.References[0].TargetClientUuid!.Value);
        await service.ExecuteAsync(
            operationId,
            "pending-retention-key",
            DeviceId,
            "aid.create",
            envelope,
            OperationsTests.CommitAidAsync);
        time.Advance(TimeSpan.FromDays(120));

        Assert.Equal(0, await service.CleanupExpiredAsync());
        LocalOperationResult? retained = await service.GetResultAsync(operationId);
        Assert.NotNull(retained);
        Assert.Equal(LocalOperationStatus.Pending, retained.Status);
    }

    [Fact]
    public async Task ExecuteAsync_WithDeletedExternalReference_RecordsCriticalConflictWithoutBusinessEffect()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (ConflictService conflicts, IdempotencyService service) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        Guid operationId = Guid.NewGuid();
        Guid targetUuid = Guid.NewGuid();
        SyncEnvelope envelope = CreateEnvelopeWithReference(operationId, targetUuid);
        await OperationsTests.RegisterResolvedIdentityAsync(service, envelope.Entity);
        await service.RegisterClientUuidAsync("SyntheticBeneficiary", targetUuid, "canonical-beneficiary-deleted");
        await service.MarkClientUuidDeletedAsync("SyntheticBeneficiary", targetUuid);

        LocalOperationResult result = await service.ExecuteAsync(
            operationId,
            "deleted-reference-key",
            DeviceId,
            "aid.create",
            envelope,
            OperationsTests.CommitAidAsync);

        Assert.Equal(LocalOperationStatus.Conflict, result.Status);
        Assert.Equal(ConflictClass.DeletedReference.ToString(), result.ConflictType);
        Assert.Equal(0, await context.AidOperations.CountAsync());
        Assert.Equal(0, await context.LocalChangeLogs.CountAsync());
        CriticalConflict conflict = await context.CriticalConflicts.SingleAsync();
        Assert.Equal(ConflictClass.DeletedReference.ToString(), conflict.ConflictType);
        Assert.Equal("PendingReview", conflict.Decision);
        Assert.Equal(operationId, conflict.OperationId);
        _ = conflicts;
    }

    [Fact]
    public async Task ResolveCanonicalIdAsync_WithReassignmentOrReuse_RejectsIdentityMismatchWithoutChangingMap()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService service) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        Guid firstUuid = Guid.NewGuid();
        Guid secondUuid = Guid.NewGuid();
        await service.RegisterClientUuidAsync("SyntheticBeneficiary", firstUuid, "canonical-shared");
        await service.RegisterClientUuidAsync("SyntheticBeneficiary", secondUuid);

        await Assert.ThrowsAsync<InvalidOperationException>(() =>
            service.ResolveCanonicalIdAsync("SyntheticBeneficiary", firstUuid, "canonical-reassigned"));
        await Assert.ThrowsAsync<InvalidOperationException>(() =>
            service.ResolveCanonicalIdAsync("SyntheticBeneficiary", secondUuid, "canonical-shared"));

        ClientIdentityMappingView? first = await service.GetClientIdentityAsync("SyntheticBeneficiary", firstUuid);
        ClientIdentityMappingView? second = await service.GetClientIdentityAsync("SyntheticBeneficiary", secondUuid);
        Assert.Equal("canonical-shared", first?.CanonicalId);
        Assert.Null(second?.CanonicalId);
    }

    private static SyncEnvelope CreateEnvelopeWithReference(Guid operationId, Guid targetUuid) =>
        LocalOperationTestFactory.CreateEnvelope(
            operationId,
            Guid.NewGuid(),
            "AidOperation",
            new List<EntityReference>
            {
                new()
                {
                    FieldName = "beneficiary_id",
                    TargetEntityType = "SyntheticBeneficiary",
                    TargetClientUuid = targetUuid,
                    Kind = EntityReferenceKind.Field
                }
            });
}
