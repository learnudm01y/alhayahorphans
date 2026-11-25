namespace Alhayah.LocalServer.Domain.Entities;

public sealed class VoucherRedemption : LocalEntity
{
    public Guid VoucherId { get; set; }

    public Guid OperationId { get; set; }

    public DateTimeOffset RedeemedAtUtc { get; set; }

    public decimal Amount { get; set; }

    public string CurrencyCode { get; set; } = string.Empty;

    public string? RedemptionReference { get; set; }
}
