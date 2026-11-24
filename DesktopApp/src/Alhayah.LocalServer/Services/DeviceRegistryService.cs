using System.Security.Cryptography;
using System.Text;
using Alhayah.LocalServer.Data;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalServer.Services;

public sealed record DeviceEnrollmentCommand(
    string UserId,
    LocalAccess DevicePermissions,
    LocalAccess UserPermissions);

public sealed record DeviceEnrollmentIssue(
    string DeviceId,
    string EnrollmentCode,
    DateTimeOffset ExpiresAtUtc);

public sealed record DeviceEnrollmentRedemption(string DeviceId, string EnrollmentCode);

public sealed record DeviceCredentialIssue(string DeviceId, string Credential, int Version);

public sealed record DeviceAuthenticationRequest(string DeviceId, string UserId, string Credential);

public sealed record DeviceAuthenticationResult(string DeviceId, string UserId, LocalAccess Permissions);

public sealed class DeviceRegistryService(
    LocalDbContext dbContext,
    TimeProvider timeProvider,
    ILogger<DeviceRegistryService> logger)
{
    private static readonly TimeSpan EnrollmentLifetime = TimeSpan.FromMinutes(10);
    private const LocalAccess DefinedPermissions = LocalAccess.Read | LocalAccess.Operations | LocalAccess.Admin;

    private readonly LocalDbContext _dbContext = dbContext ?? throw new ArgumentNullException(nameof(dbContext));
    private readonly TimeProvider _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
    private readonly ILogger<DeviceRegistryService> _logger = logger ?? throw new ArgumentNullException(nameof(logger));

    public async Task<DeviceEnrollmentIssue> CreateEnrollmentAsync(
        DeviceEnrollmentCommand command,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(command);
        string userId = NormalizeUserId(command.UserId);
        ValidatePermissions(command.DevicePermissions);
        ValidatePermissions(command.UserPermissions);
        DateTimeOffset now = _timeProvider.GetUtcNow();
        string enrollmentCode = CreateSecret();
        DeviceRegistration device = new()
        {
            Id = Guid.NewGuid(),
            DeviceId = Guid.NewGuid().ToString("N"),
            EnrollmentCodeHash = Hash(enrollmentCode),
            EnrollmentCodeExpiresAtUtc = now.Add(EnrollmentLifetime),
            CredentialVersion = 0,
            Permissions = command.DevicePermissions,
            CreatedAtUtc = now,
            UpdatedAtUtc = now
        };
        device.Users.Add(new DeviceUserRegistration
        {
            Id = Guid.NewGuid(),
            DeviceRegistrationId = device.Id,
            UserId = userId,
            Permissions = command.UserPermissions
        });
        _dbContext.DeviceRegistrations.Add(device);
        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        _logger.LogInformation("Device enrollment issued for device {DeviceId}.", device.DeviceId);
        return new DeviceEnrollmentIssue(device.DeviceId, enrollmentCode, device.EnrollmentCodeExpiresAtUtc.Value);
    }

    public async Task<DeviceCredentialIssue?> CompleteEnrollmentAsync(
        DeviceEnrollmentRedemption redemption,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(redemption);
        if (string.IsNullOrWhiteSpace(redemption.DeviceId) || string.IsNullOrWhiteSpace(redemption.EnrollmentCode))
        {
            return null;
        }

        DeviceRegistration? device = await _dbContext.DeviceRegistrations
            .Include(item => item.Users)
            .SingleOrDefaultAsync(item => item.DeviceId == redemption.DeviceId, cancellationToken)
            .ConfigureAwait(false);
        DateTimeOffset now = _timeProvider.GetUtcNow();
        if (device?.EnrollmentCodeHash is null ||
            device.EnrollmentCodeExpiresAtUtc is null ||
            device.EnrollmentCodeExpiresAtUtc <= now ||
            !CryptographicOperations.FixedTimeEquals(device.EnrollmentCodeHash, Hash(redemption.EnrollmentCode)))
        {
            return null;
        }

        string credential = CreateSecret();
        device.CredentialHash = Hash(credential);
        device.EnrollmentCodeHash = null;
        device.EnrollmentCodeExpiresAtUtc = null;
        device.CredentialVersion += 1;
        device.RevokedAtUtc = null;
        device.UpdatedAtUtc = now;
        foreach (DeviceUserRegistration user in device.Users)
        {
            user.RevokedAtUtc = null;
        }

        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        _logger.LogInformation("Device credential issued for device {DeviceId}.", device.DeviceId);
        return new DeviceCredentialIssue(device.DeviceId, credential, device.CredentialVersion);
    }

    public async Task<DeviceAuthenticationResult?> AuthenticateAsync(
        DeviceAuthenticationRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);
        if (string.IsNullOrWhiteSpace(request.DeviceId) ||
            string.IsNullOrWhiteSpace(request.UserId) ||
            string.IsNullOrWhiteSpace(request.Credential))
        {
            return null;
        }

        DeviceRegistration? device = await _dbContext.DeviceRegistrations
            .Include(item => item.Users)
            .SingleOrDefaultAsync(item => item.DeviceId == request.DeviceId, cancellationToken)
            .ConfigureAwait(false);
        if (device?.CredentialHash is null || device.RevokedAtUtc is not null)
        {
            return null;
        }

        if (!CryptographicOperations.FixedTimeEquals(device.CredentialHash, Hash(request.Credential)))
        {
            _logger.LogWarning("Device credential rejected for device {DeviceId}.", device.DeviceId);
            return null;
        }

        DeviceUserRegistration? user = device.Users.SingleOrDefault(item =>
            item.UserId == request.UserId && item.RevokedAtUtc is null);
        if (user is null)
        {
            return null;
        }

        DateTimeOffset now = _timeProvider.GetUtcNow();
        device.LastSeenAtUtc = now;
        device.UpdatedAtUtc = now;
        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        LocalAccess effectivePermissions = device.Permissions & user.Permissions;
        _logger.LogInformation("Device authenticated with last-seen update for device {DeviceId}.", device.DeviceId);
        return new DeviceAuthenticationResult(device.DeviceId, user.UserId, effectivePermissions);
    }

    public async Task<DeviceCredentialIssue> RotateCredentialAsync(
        string deviceId,
        CancellationToken cancellationToken = default)
    {
        DeviceRegistration device = await GetDeviceAsync(deviceId, cancellationToken).ConfigureAwait(false);
        if (device.RevokedAtUtc is not null || device.CredentialHash is null)
        {
            throw new InvalidOperationException("Only an active device credential can be rotated.");
        }

        string credential = CreateSecret();
        device.CredentialHash = Hash(credential);
        device.CredentialVersion += 1;
        device.UpdatedAtUtc = _timeProvider.GetUtcNow();
        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        _logger.LogInformation("Device credential rotated for device {DeviceId}.", device.DeviceId);
        return new DeviceCredentialIssue(device.DeviceId, credential, device.CredentialVersion);
    }

    public async Task RevokeAsync(string deviceId, CancellationToken cancellationToken = default)
    {
        DeviceRegistration device = await GetDeviceAsync(deviceId, cancellationToken).ConfigureAwait(false);
        DateTimeOffset now = _timeProvider.GetUtcNow();
        device.RevokedAtUtc = now;
        device.CredentialHash = null;
        device.EnrollmentCodeHash = null;
        device.EnrollmentCodeExpiresAtUtc = null;
        device.UpdatedAtUtc = now;
        foreach (DeviceUserRegistration user in device.Users)
        {
            user.RevokedAtUtc = now;
        }

        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        _logger.LogInformation("Device revoked for device {DeviceId}.", device.DeviceId);
    }

    public async Task<DeviceEnrollmentIssue> BeginReRegistrationAsync(
        string deviceId,
        CancellationToken cancellationToken = default)
    {
        DeviceRegistration device = await GetDeviceAsync(deviceId, cancellationToken).ConfigureAwait(false);
        DateTimeOffset now = _timeProvider.GetUtcNow();
        string enrollmentCode = CreateSecret();
        device.CredentialHash = null;
        device.EnrollmentCodeHash = Hash(enrollmentCode);
        device.EnrollmentCodeExpiresAtUtc = now.Add(EnrollmentLifetime);
        device.RevokedAtUtc = null;
        device.UpdatedAtUtc = now;
        await _dbContext.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        _logger.LogInformation("Device re-registration issued for device {DeviceId}.", device.DeviceId);
        return new DeviceEnrollmentIssue(device.DeviceId, enrollmentCode, device.EnrollmentCodeExpiresAtUtc.Value);
    }

    private async Task<DeviceRegistration> GetDeviceAsync(
        string deviceId,
        CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(deviceId))
        {
            throw new ArgumentException("A registered device id is required.", nameof(deviceId));
        }

        return await _dbContext.DeviceRegistrations
            .Include(item => item.Users)
            .SingleOrDefaultAsync(item => item.DeviceId == deviceId, cancellationToken)
            .ConfigureAwait(false)
            ?? throw new KeyNotFoundException("The device is not registered.");
    }

    private static string NormalizeUserId(string userId)
    {
        if (string.IsNullOrWhiteSpace(userId) || userId.Length > 200)
        {
            throw new ArgumentException("A user id of at most 200 characters is required.", nameof(userId));
        }

        return userId.Trim();
    }

    private static void ValidatePermissions(LocalAccess permissions)
    {
        if ((permissions & ~DefinedPermissions) != 0)
        {
            throw new ArgumentOutOfRangeException(nameof(permissions), "Only read, operations, and admin permissions are supported.");
        }
    }

    private static string CreateSecret() =>
        Convert.ToBase64String(RandomNumberGenerator.GetBytes(32))
            .TrimEnd('=')
            .Replace('+', '-')
            .Replace('/', '_');

    private static byte[] Hash(string value) => SHA256.HashData(Encoding.UTF8.GetBytes(value));
}
