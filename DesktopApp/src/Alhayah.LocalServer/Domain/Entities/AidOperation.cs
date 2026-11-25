namespace Alhayah.LocalServer.Domain.Entities;

public sealed class AidOperation : LocalEntity
{
    public Guid OperationId { get; set; }

    public string AidType { get; set; } = string.Empty;

    public Guid? EntitlementId { get; set; }

    public decimal Amount { get; set; }

    public string CurrencyCode { get; set; } = string.Empty;

    public string Status { get; set; } = string.Empty;

    public DateTimeOffset OccurredAtUtc { get; set; }

    public string? Notes { get; set; }
}
