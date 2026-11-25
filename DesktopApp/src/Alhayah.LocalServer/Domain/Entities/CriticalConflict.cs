namespace Alhayah.LocalServer.Domain.Entities;

public sealed class CriticalConflict
{
    public Guid Id { get; set; }

    public Guid OperationId { get; set; }

    public string DeviceId { get; set; } = string.Empty;

    public string EntityType { get; set; } = string.Empty;

    public Guid EntityId { get; set; }

    public string ConflictType { get; set; } = string.Empty;

    public string DetailsJson { get; set; } = string.Empty;

    public string Decision { get; set; } = "PendingReview";

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset? DecisionAtUtc { get; set; }

    public string? DecisionByDeviceId { get; set; }
}
