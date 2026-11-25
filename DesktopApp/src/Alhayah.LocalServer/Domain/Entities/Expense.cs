namespace Alhayah.LocalServer.Domain.Entities;

public sealed class Expense : LocalEntity
{
    public Guid OperationId { get; set; }

    public string Category { get; set; } = string.Empty;

    public Guid? SupplierId { get; set; }

    public decimal Amount { get; set; }

    public string CurrencyCode { get; set; } = string.Empty;

    public DateTimeOffset IncurredAtUtc { get; set; }

    public string? Description { get; set; }
}
