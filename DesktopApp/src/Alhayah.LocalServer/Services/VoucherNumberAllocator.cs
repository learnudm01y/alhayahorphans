using System.Globalization;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalServer.Services;

public sealed record VoucherNumberAllocation(string? SerialNumber, bool RangeExhausted);

public sealed class IssueVoucherCommand
{
    public required Guid OperationId { get; init; }

    public string? IdempotencyKey { get; init; }

    public required string DeviceId { get; init; }

    public required Guid SequenceId { get; init; }

    public required SyncEnvelope Envelope { get; init; }

    public required Guid SupplierId { get; init; }

    public required decimal Amount { get; init; }

    public required string CurrencyCode { get; init; }

    public required string BeneficiaryNameSnapshot { get; init; }

    public required string BeneficiaryIdentityNumberSnapshot { get; init; }

    public string? BeneficiaryPhoneSnapshot { get; init; }

    public Guid? DisbursementId { get; init; }

    public required DateTimeOffset IssuedAtUtc { get; init; }

    public DateTimeOffset? ExpiresAtUtc { get; init; }
}

public sealed class RedeemVoucherCommand
{
    public required Guid OperationId { get; init; }

    public string? IdempotencyKey { get; init; }

    public required string DeviceId { get; init; }

    public required Guid VoucherId { get; init; }

    public required long ExpectedRowVersion { get; init; }

    public required Guid ExpectedConcurrencyToken { get; init; }

    public required SyncEnvelope Envelope { get; init; }

    public required decimal Amount { get; init; }

    public required string CurrencyCode { get; init; }

    public required DateTimeOffset RedeemedAtUtc { get; init; }

    public string? RedemptionReference { get; init; }
}

public sealed class VoucherNumberAllocator(
    LocalDbContext dbContext,
    IdempotencyService idempotencyService,
    ConflictService conflictService,
    TimeProvider timeProvider)
{
    private readonly LocalDbContext _dbContext = dbContext ?? throw new ArgumentNullException(nameof(dbContext));
    private readonly IdempotencyService _idempotencyService = idempotencyService
        ?? throw new ArgumentNullException(nameof(idempotencyService));
    private readonly ConflictService _conflictService = conflictService ?? throw new ArgumentNullException(nameof(conflictService));
    private readonly TimeProvider _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));

    public Task<LocalOperationResult> IssueAsync(
        IssueVoucherCommand command,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(command);
        return _idempotencyService.ExecuteAsync(
            command.OperationId,
            command.IdempotencyKey,
            command.DeviceId,
            "voucher.issue",
            command.Envelope,
            async (db, envelope, ct) =>
            {
                VoucherNumberAllocation allocation = await AllocateDisplayNumberAsync(
                    command.SequenceId,
                    command.DeviceId,
                    ct).ConfigureAwait(false);
                Voucher voucher = new()
                {
                    Id = envelope.Entity.ClientUuid,
                    SequenceId = command.SequenceId,
                    SerialNumber = allocation.SerialNumber,
                    DisbursementId = command.DisbursementId,
                    BeneficiaryIdentityNumberSnapshot = command.BeneficiaryIdentityNumberSnapshot,
                    BeneficiaryNameSnapshot = command.BeneficiaryNameSnapshot,
                    BeneficiaryPhoneSnapshot = command.BeneficiaryPhoneSnapshot,
                    Amount = command.Amount,
                    CurrencyCode = command.CurrencyCode,
                    SupplierId = command.SupplierId,
                    Status = "Issued",
                    IssuedAtUtc = command.IssuedAtUtc,
                    ExpiresAtUtc = command.ExpiresAtUtc,
                    LastModifiedByDeviceId = command.DeviceId
                };
                db.Vouchers.Add(voucher);
                return LocalBusinessResult.Completed(voucher.Id, 1);
            },
            cancellationToken);
    }

    public Task<LocalOperationResult> RedeemAsync(
        RedeemVoucherCommand command,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(command);
        return _idempotencyService.ExecuteAsync(
            command.OperationId,
            command.IdempotencyKey,
            command.DeviceId,
            "voucher.redeem",
            command.Envelope,
            async (db, envelope, ct) =>
            {
                Voucher voucher = await db.Vouchers
                    .IgnoreQueryFilters()
                    .SingleOrDefaultAsync(item => item.Id == command.VoucherId, ct)
                    .ConfigureAwait(false)
                    ?? throw new KeyNotFoundException("The voucher is not registered on the local server.");
                if (voucher.DeletedAtUtc is not null)
                {
                    CriticalConflict deleted = _conflictService.AddCriticalConflict(
                        command.OperationId,
                        command.DeviceId,
                        nameof(Voucher),
                        voucher.Id,
                        ConflictClass.DeletedReference.ToString(),
                        JsonSerializer.Serialize(new { voucherId = voucher.Id, deletedAtUtc = voucher.DeletedAtUtc }));
                    return LocalBusinessResult.Conflict(voucher.Id, voucher.RowVersion, deleted.ConflictType);
                }

                CriticalConflict? versionConflict = _conflictService.EvaluateUpdate(
                    voucher,
                    command.OperationId,
                    command.DeviceId,
                    command.ExpectedRowVersion,
                    command.ExpectedConcurrencyToken);
                if (versionConflict is not null)
                {
                    return LocalBusinessResult.Conflict(voucher.Id, voucher.RowVersion, versionConflict.ConflictType);
                }

                bool alreadyRedeemed = await db.VoucherRedemptions.AnyAsync(
                    item => item.VoucherId == voucher.Id,
                    ct).ConfigureAwait(false);
                if (alreadyRedeemed || voucher.RedeemedAtUtc is not null)
                {
                    CriticalConflict duplicate = _conflictService.AddCriticalConflict(
                        command.OperationId,
                        command.DeviceId,
                        nameof(Voucher),
                        voucher.Id,
                        ConflictService.DuplicateRedemption,
                        JsonSerializer.Serialize(new { voucherId = voucher.Id }));
                    return LocalBusinessResult.Conflict(voucher.Id, voucher.RowVersion, duplicate.ConflictType);
                }

                VoucherRedemption redemption = new()
                {
                    Id = envelope.Entity.ClientUuid,
                    VoucherId = voucher.Id,
                    OperationId = command.OperationId,
                    RedeemedAtUtc = command.RedeemedAtUtc,
                    Amount = command.Amount,
                    CurrencyCode = command.CurrencyCode,
                    RedemptionReference = command.RedemptionReference,
                    LastModifiedByDeviceId = command.DeviceId
                };
                voucher.Status = "Redeemed";
                voucher.RedeemedAtUtc = command.RedeemedAtUtc;
                voucher.LastModifiedByDeviceId = command.DeviceId;
                db.VoucherRedemptions.Add(redemption);
                return LocalBusinessResult.Completed(redemption.Id, 1);
            },
            cancellationToken);
    }

    public async Task<VoucherNumberAllocation> AllocateDisplayNumberAsync(
        Guid sequenceId,
        string deviceId,
        CancellationToken cancellationToken = default)
    {
        VoucherNumberBlock? block = await _dbContext.VoucherNumberBlocks
            .SingleOrDefaultAsync(
                item => item.SequenceId == sequenceId && item.DeviceId == deviceId,
                cancellationToken)
            .ConfigureAwait(false);
        if (block is null || block.NextNumber < block.FirstNumber || block.NextNumber > block.LastNumber)
        {
            return new VoucherNumberAllocation(null, true);
        }

        long displayNumber = block.NextNumber;
        block.NextNumber++;
        return new VoucherNumberAllocation(displayNumber.ToString(CultureInfo.InvariantCulture), false);
    }
}
