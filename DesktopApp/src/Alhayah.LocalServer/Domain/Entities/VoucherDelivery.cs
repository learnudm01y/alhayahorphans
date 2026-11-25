namespace Alhayah.LocalServer.Domain.Entities;

public sealed class VoucherDelivery : LocalEntity
{
    public Guid VoucherId { get; set; }

    public Guid OperationId { get; set; }

    public DateTimeOffset DeliveredAtUtc { get; set; }

    public string DeliveryStatus { get; set; } = string.Empty;

    public string? Notes { get; set; }
}
