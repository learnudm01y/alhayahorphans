namespace Alhayah.LocalServer.Data;

public sealed class Disbursement
{
    public Guid Id { get; set; }

    public string TransactionNumber { get; set; } = string.Empty;

    public long RowVersion { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset UpdatedAtUtc { get; set; }

    public DateTimeOffset? DeletedAtUtc { get; set; }

    public Guid EntitlementId { get; set; }

    public Guid? BatchId { get; set; }

    public Guid? SupplierId { get; set; }

    public decimal? Amount { get; set; }

    public decimal? Quantity { get; set; }

    public string? CurrencyCode { get; set; }

    public string Status { get; set; } = string.Empty;

    public DateTimeOffset RequestedAtUtc { get; set; }

    public DateTimeOffset? DeliveredAtUtc { get; set; }

    public string RecipientType { get; set; } = string.Empty;

    public string RecipientNameSnapshot { get; set; } = string.Empty;

    public string? RecipientIdentityNumberSnapshot { get; set; }

    public Guid? DeliveredByRepresentativeId { get; set; }

    public string? VerificationNotes { get; set; }

    public List<DeliveryEvidence> DeliveryEvidences { get; set; } = [];
}
