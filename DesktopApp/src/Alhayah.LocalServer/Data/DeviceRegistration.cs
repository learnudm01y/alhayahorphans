namespace Alhayah.LocalServer.Data;

[Flags]
public enum LocalAccess
{
    None = 0,
    Read = 1,
    Operations = 2,
    Admin = 4
}

public sealed class DeviceRegistration
{
    public Guid Id { get; set; }

    public string DeviceId { get; set; } = string.Empty;

    public byte[]? CredentialHash { get; set; }

    public byte[]? EnrollmentCodeHash { get; set; }

    public DateTimeOffset? EnrollmentCodeExpiresAtUtc { get; set; }

    public int CredentialVersion { get; set; }

    public LocalAccess Permissions { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset UpdatedAtUtc { get; set; }

    public DateTimeOffset? LastSeenAtUtc { get; set; }

    public DateTimeOffset? RevokedAtUtc { get; set; }

    public List<DeviceUserRegistration> Users { get; set; } = new();
}

public sealed class DeviceUserRegistration
{
    public Guid Id { get; set; }

    public Guid DeviceRegistrationId { get; set; }

    public string UserId { get; set; } = string.Empty;

    public LocalAccess Permissions { get; set; }

    public DateTimeOffset? RevokedAtUtc { get; set; }

    public DeviceRegistration Device { get; set; } = null!;
}
