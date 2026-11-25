namespace Alhayah.LocalServer.Domain.Entities;

public sealed class Voucher : LocalEntity
{
    public Guid SequenceId { get; set; }

    public string? SerialNumber { get; set; }

    public Guid? DisbursementId { get; set; }

    public string BeneficiaryIdentityNumberSnapshot { get; set; } = string.Empty;

    public string BeneficiaryNameSnapshot { get; set; } = string.Empty;

    public string? BeneficiaryPhoneSnapshot { get; set; }

    public decimal Amount { get; set; }

    public string CurrencyCode { get; set; } = string.Empty;

    public Guid SupplierId { get; set; }

    public string Status { get; set; } = string.Empty;

    public DateTimeOffset IssuedAtUtc { get; set; }

    public DateTimeOffset? PrintedAtUtc { get; set; }

    public DateTimeOffset? RedeemedAtUtc { get; set; }

    public DateTimeOffset? ExpiresAtUtc { get; set; }

    public string? VoidReason { get; set; }
}
