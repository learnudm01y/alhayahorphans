using Alhayah.Contracts.Domain;

namespace Alhayah.LocalServer.Domain.Entities;

public sealed class LocalChangeLog
{
    public long ChangeLogId { get; set; }

    public string EntityType { get; set; } = string.Empty;

    public Guid EntityId { get; set; }

    public SyncOperationType OperationType { get; set; }

    public long RowVersion { get; set; }

    public Guid ConcurrencyToken { get; set; }

    public DateTimeOffset ChangedAtUtc { get; set; }

    public string DeviceId { get; set; } = string.Empty;

    public string? PayloadJson { get; set; }

    public DateTimeOffset? DeletedAtUtc { get; set; }

    public string? DeletedByDeviceId { get; set; }
}
