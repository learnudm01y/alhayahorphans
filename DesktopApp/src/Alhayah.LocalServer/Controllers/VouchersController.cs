using System.Security.Claims;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Authentication;
using Alhayah.LocalServer.Domain.Entities;
using Alhayah.LocalServer.Services;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;

namespace Alhayah.LocalServer.Controllers;

[ApiController]
[Authorize(Policy = DevicePolicies.Operations)]
[Route("local/vouchers")]
public sealed class VouchersController(VoucherNumberAllocator voucherOperations) : ControllerBase
{
    private readonly VoucherNumberAllocator _voucherOperations = voucherOperations
        ?? throw new ArgumentNullException(nameof(voucherOperations));

    [HttpPost("issue")]
    public async Task<ActionResult<LocalOperationResult>> Issue(
        IssueVoucherHttpRequest request,
        CancellationToken cancellationToken)
    {
        IssueVoucherCommand command = new()
        {
            OperationId = request.OperationId,
            IdempotencyKey = request.IdempotencyKey,
            DeviceId = GetDeviceId(),
            SequenceId = request.SequenceId,
            Envelope = request.Envelope,
            SupplierId = request.SupplierId,
            Amount = request.Amount,
            CurrencyCode = request.CurrencyCode,
            BeneficiaryNameSnapshot = request.BeneficiaryNameSnapshot,
            BeneficiaryIdentityNumberSnapshot = request.BeneficiaryIdentityNumberSnapshot,
            BeneficiaryPhoneSnapshot = request.BeneficiaryPhoneSnapshot,
            DisbursementId = request.DisbursementId,
            IssuedAtUtc = request.IssuedAtUtc,
            ExpiresAtUtc = request.ExpiresAtUtc
        };
        LocalOperationResult result = await _voucherOperations.IssueAsync(command, cancellationToken)
            .ConfigureAwait(false);
        return ToActionResult(result);
    }

    [HttpPost("redeem")]
    public async Task<ActionResult<LocalOperationResult>> Redeem(
        RedeemVoucherHttpRequest request,
        CancellationToken cancellationToken)
    {
        RedeemVoucherCommand command = new()
        {
            OperationId = request.OperationId,
            IdempotencyKey = request.IdempotencyKey,
            DeviceId = GetDeviceId(),
            VoucherId = request.VoucherId,
            ExpectedRowVersion = request.ExpectedRowVersion,
            ExpectedConcurrencyToken = request.ExpectedConcurrencyToken,
            Envelope = request.Envelope,
            Amount = request.Amount,
            CurrencyCode = request.CurrencyCode,
            RedeemedAtUtc = request.RedeemedAtUtc,
            RedemptionReference = request.RedemptionReference
        };
        LocalOperationResult result = await _voucherOperations.RedeemAsync(command, cancellationToken)
            .ConfigureAwait(false);
        return ToActionResult(result);
    }

    private ActionResult<LocalOperationResult> ToActionResult(LocalOperationResult result) => result.Status switch
    {
        LocalOperationStatus.Pending => Accepted(result),
        LocalOperationStatus.Conflict => Conflict(result),
        _ => Ok(result)
    };

    private string GetDeviceId() => User.FindFirstValue(DeviceClaimTypes.DeviceId)
        ?? throw new InvalidOperationException("The authenticated device claim is missing.");
}

public sealed class IssueVoucherHttpRequest
{
    public required Guid OperationId { get; init; }

    public string? IdempotencyKey { get; init; }

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

public sealed class RedeemVoucherHttpRequest
{
    public required Guid OperationId { get; init; }

    public string? IdempotencyKey { get; init; }

    public required Guid VoucherId { get; init; }

    public required long ExpectedRowVersion { get; init; }

    public required Guid ExpectedConcurrencyToken { get; init; }

    public required SyncEnvelope Envelope { get; init; }

    public required decimal Amount { get; init; }

    public required string CurrencyCode { get; init; }

    public required DateTimeOffset RedeemedAtUtc { get; init; }

    public string? RedemptionReference { get; init; }
}
