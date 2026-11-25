namespace Alhayah.LocalServer.Domain.Entities;

public abstract class LocalEntity
{
    public Guid Id { get; set; }

    public Guid ConcurrencyToken { get; set; }

    public long RowVersion { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset UpdatedAtUtc { get; set; }

    public DateTimeOffset? DeletedAtUtc { get; set; }

    public string LastModifiedByDeviceId { get; set; } = string.Empty;

    public string? DeletedByDeviceId { get; set; }
}
