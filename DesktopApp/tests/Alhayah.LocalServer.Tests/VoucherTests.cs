using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class VoucherTests
{
    private const string DeviceId = "synthetic-device-020";

    [Fact]
    public async Task IssueAsync_WhenAssignedNumberRangeIsExhausted_IssuesGloballyUniqueVoucherIdsWithoutDisplayNumbers()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService idempotency) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        ConflictService conflicts = new(context, time);
        VoucherNumberAllocator vouchers = new(context, idempotency, conflicts, time);
        Guid sequenceId = Guid.NewGuid();
        context.VoucherNumberBlocks.Add(new VoucherNumberBlock
        {
            Id = Guid.NewGuid(),
            SequenceId = sequenceId,
            DeviceId = DeviceId,
            FirstNumber = 1,
            LastNumber = 1,
            NextNumber = 2
        });
        await context.SaveChangesAsync();
        IssueVoucherCommand firstCommand = CreateIssueCommand(sequenceId);
        IssueVoucherCommand secondCommand = CreateIssueCommand(sequenceId);
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, firstCommand.Envelope.Entity);
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, secondCommand.Envelope.Entity);

        LocalOperationResult first = await vouchers.IssueAsync(firstCommand);
        LocalOperationResult second = await vouchers.IssueAsync(secondCommand);

        await using LocalDbContext verification = harness.CreateContext();
        List<Voucher> stored = await verification.Vouchers.ToListAsync();
        Assert.Equal(2, stored.Count);
        Assert.All(stored, item => Assert.NotEqual(Guid.Empty, item.Id));
        Assert.Equal(2, stored.Select(item => item.Id).Distinct().Count());
        Assert.All(stored, item => Assert.Null(item.SerialNumber));
        Assert.Contains(stored, item => item.Id == first.EntityId);
        Assert.Contains(stored, item => item.Id == second.EntityId);
        Assert.All(new[] { first, second }, item => Assert.Equal(LocalOperationStatus.Completed, item.Status));
    }

    [Fact]
    public async Task IssueAsync_WithAvailableDisplayNumber_UsesNumberOnlyForDisplayAndKeepsClientUuidAsVoucherId()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService idempotency) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        VoucherNumberAllocator vouchers = new(context, idempotency, new ConflictService(context, time), time);
        Guid sequenceId = Guid.NewGuid();
        context.VoucherNumberBlocks.Add(new VoucherNumberBlock
        {
            Id = Guid.NewGuid(),
            SequenceId = sequenceId,
            DeviceId = DeviceId,
            FirstNumber = 41,
            LastNumber = 42,
            NextNumber = 41
        });
        await context.SaveChangesAsync();
        IssueVoucherCommand command = CreateIssueCommand(sequenceId);
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, command.Envelope.Entity);

        LocalOperationResult result = await vouchers.IssueAsync(command);

        Voucher stored = await context.Vouchers.SingleAsync();
        Assert.Equal(command.Envelope.Entity.ClientUuid, stored.Id);
        Assert.Equal(command.Envelope.Entity.ClientUuid, result.EntityId);
        Assert.Equal("41", stored.SerialNumber);
        Assert.Equal(42, await context.VoucherNumberBlocks.Select(item => item.NextNumber).SingleAsync());
    }

    [Fact]
    public async Task RedeemAsync_WithSameOperationReplay_ReturnsOriginalResultWithoutAnotherRedemption()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService idempotency) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        VoucherNumberAllocator vouchers = new(context, idempotency, new ConflictService(context, time), time);
        IssueVoucherCommand issue = CreateIssueCommand(Guid.NewGuid());
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, issue.Envelope.Entity);
        await vouchers.IssueAsync(issue);
        Voucher voucher = await context.Vouchers.SingleAsync();
        RedeemVoucherCommand redeem = CreateRedeemCommand(voucher);
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, redeem.Envelope.Entity);
        await RegisterVoucherReferenceAsync(idempotency, voucher, redeem.Envelope);

        LocalOperationResult first = await vouchers.RedeemAsync(redeem);
        LocalOperationResult replay = await vouchers.RedeemAsync(redeem);

        Assert.Equal(first, replay);
        Assert.Equal(LocalOperationStatus.Completed, first.Status);
        Assert.Equal(1, await context.VoucherRedemptions.CountAsync());
        Assert.Empty(await context.CriticalConflicts.Where(item => item.ConflictType == "DuplicateRedemption").ToListAsync());
    }

    [Fact]
    public async Task RedeemAsync_WithDifferentOperationForAlreadyRedeemedVoucher_RecordsCriticalConflictWithoutFalseConfirmation()
    {
        using LocalServerTestHarness harness = new();
        TestTimeProvider time = new();
        await using LocalDbContext context = harness.CreateContext();
        await context.Database.MigrateAsync();
        (_, IdempotencyService idempotency) = OperationsTests.CreateServices(context, time, new RecordingSyncNotifier());
        VoucherNumberAllocator vouchers = new(context, idempotency, new ConflictService(context, time), time);
        IssueVoucherCommand issue = CreateIssueCommand(Guid.NewGuid());
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, issue.Envelope.Entity);
        await vouchers.IssueAsync(issue);
        Voucher voucher = await context.Vouchers.SingleAsync();
        RedeemVoucherCommand firstCommand = CreateRedeemCommand(voucher);
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, firstCommand.Envelope.Entity);
        await RegisterVoucherReferenceAsync(idempotency, voucher, firstCommand.Envelope);
        await vouchers.RedeemAsync(firstCommand);
        voucher = await context.Vouchers.SingleAsync();
        long versionAfterFirstRedemption = voucher.RowVersion;
        RedeemVoucherCommand duplicateCommand = CreateRedeemCommand(voucher);
        await OperationsTests.RegisterResolvedIdentityAsync(idempotency, duplicateCommand.Envelope.Entity);
        await RegisterVoucherReferenceAsync(idempotency, voucher, duplicateCommand.Envelope);

        LocalOperationResult duplicate = await vouchers.RedeemAsync(duplicateCommand);

        Assert.Equal(LocalOperationStatus.Conflict, duplicate.Status);
        Assert.Equal("DuplicateRedemption", duplicate.ConflictType);
        Assert.Equal(1, await context.VoucherRedemptions.CountAsync());
        CriticalConflict conflict = await context.CriticalConflicts.SingleAsync();
        Assert.Equal("DuplicateRedemption", conflict.ConflictType);
        Assert.Equal("PendingReview", conflict.Decision);
        Assert.Equal(versionAfterFirstRedemption, await context.Vouchers.Select(item => item.RowVersion).SingleAsync());
        Assert.Equal(2, await context.LocalChangeLogs.CountAsync(item => item.EntityId == voucher.Id));
    }

    private static IssueVoucherCommand CreateIssueCommand(Guid sequenceId)
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        return new IssueVoucherCommand
        {
            OperationId = operationId,
            IdempotencyKey = $"synthetic-issue-{operationId:N}",
            DeviceId = DeviceId,
            SequenceId = sequenceId,
            Envelope = LocalOperationTestFactory.CreateEnvelope(operationId, clientUuid, "Voucher"),
            SupplierId = Guid.NewGuid(),
            Amount = 25m,
            CurrencyCode = "USD",
            BeneficiaryNameSnapshot = "Synthetic beneficiary",
            BeneficiaryIdentityNumberSnapshot = "SYNTHETIC-IDENTITY",
            IssuedAtUtc = new DateTimeOffset(2026, 11, 25, 9, 0, 0, TimeSpan.Zero)
        };
    }

    private static RedeemVoucherCommand CreateRedeemCommand(Voucher voucher)
    {
        Guid operationId = Guid.NewGuid();
        Guid redemptionUuid = Guid.NewGuid();
        SyncEnvelope envelope = LocalOperationTestFactory.CreateEnvelope(
            operationId,
            redemptionUuid,
            "VoucherRedemption",
            new List<EntityReference>
            {
                new()
                {
                    FieldName = "voucher_id",
                    TargetEntityType = "Voucher",
                    TargetClientUuid = voucher.Id,
                    Kind = EntityReferenceKind.Field
                }
            });
        return new RedeemVoucherCommand
        {
            OperationId = operationId,
            IdempotencyKey = $"synthetic-redeem-{operationId:N}",
            DeviceId = DeviceId,
            VoucherId = voucher.Id,
            ExpectedRowVersion = voucher.RowVersion,
            ExpectedConcurrencyToken = voucher.ConcurrencyToken,
            Envelope = envelope,
            Amount = voucher.Amount,
            CurrencyCode = voucher.CurrencyCode,
            RedeemedAtUtc = new DateTimeOffset(2026, 11, 25, 10, 0, 0, TimeSpan.Zero),
            RedemptionReference = $"SYNTHETIC-{operationId:N}"
        };
    }

    private static async Task RegisterVoucherReferenceAsync(
        IdempotencyService idempotency,
        Voucher voucher,
        SyncEnvelope envelope)
    {
        if (await idempotency.GetClientIdentityAsync("Voucher", voucher.Id) is null)
        {
            await OperationsTests.RegisterResolvedIdentityAsync(idempotency, new EntityDescriptor
            {
                EntityType = "Voucher",
                ClientUuid = voucher.Id,
                References = new List<EntityReference>()
            });
        }
    }
}
