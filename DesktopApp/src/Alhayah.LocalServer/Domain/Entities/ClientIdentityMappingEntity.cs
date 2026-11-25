namespace Alhayah.LocalServer.Domain.Entities;

public sealed class ClientIdentityMappingEntity
{
    public Guid Id { get; set; }

    public string EntityType { get; set; } = string.Empty;

    public Guid ClientUuid { get; set; }

    public string? CanonicalId { get; set; }

    public bool IsDeleted { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset UpdatedAtUtc { get; set; }
}
