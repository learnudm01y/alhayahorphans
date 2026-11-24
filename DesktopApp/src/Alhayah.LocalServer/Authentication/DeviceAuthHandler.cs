using System.Security.Claims;
using System.Security.Cryptography.X509Certificates;
using System.Text.Encodings.Web;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Services;
using Microsoft.AspNetCore.Authentication;
using Microsoft.Extensions.Options;

namespace Alhayah.LocalServer.Authentication;

public sealed class DeviceAuthenticationOptions : AuthenticationSchemeOptions
{
}

public static class DevicePolicies
{
    public const string Read = "local:read";
    public const string Operations = "local:operations";
    public const string Admin = "local:admin";
}

public static class LocalPermissionNames
{
    public const string Read = "read";
    public const string Operations = "operations";
    public const string Admin = "admin";
}

public static class DeviceClaimTypes
{
    public const string DeviceId = "alhayah:device_id";
    public const string UserId = "alhayah:user_id";
    public const string Permission = "alhayah:permission";
}

public sealed class DeviceAuthHandler : AuthenticationHandler<DeviceAuthenticationOptions>
{
    public const string SchemeName = "Device";
    public const string DeviceIdHeader = "X-Alhayah-Device-Id";
    public const string UserIdHeader = "X-Alhayah-User-Id";

    private readonly DeviceRegistryService _deviceRegistry;
    private readonly DeviceCertificateValidator _certificateValidator;

    public DeviceAuthHandler(
        IOptionsMonitor<DeviceAuthenticationOptions> options,
        ILoggerFactory logger,
        UrlEncoder encoder,
        DeviceRegistryService deviceRegistry,
        DeviceCertificateValidator certificateValidator)
        : base(options, logger, encoder)
    {
        _deviceRegistry = deviceRegistry ?? throw new ArgumentNullException(nameof(deviceRegistry));
        _certificateValidator = certificateValidator ?? throw new ArgumentNullException(nameof(certificateValidator));
    }

    protected override async Task<AuthenticateResult> HandleAuthenticateAsync()
    {
        if (!Request.IsHttps)
        {
            return AuthenticateResult.Fail("HTTPS is required.");
        }

        X509Certificate2? certificate = Context.Connection.ClientCertificate
            ?? await Context.Connection.GetClientCertificateAsync().ConfigureAwait(false);
        if (!_certificateValidator.Validate(certificate))
        {
            return AuthenticateResult.Fail("The client certificate is not trusted and pinned.");
        }

        string authorization = Request.Headers.Authorization.ToString();
        string prefix = $"{SchemeName} ";
        if (!authorization.StartsWith(prefix, StringComparison.Ordinal) || authorization.Length == prefix.Length)
        {
            return AuthenticateResult.Fail("A device credential is required.");
        }

        string deviceId = Request.Headers[DeviceIdHeader].ToString();
        string userId = Request.Headers[UserIdHeader].ToString();
        string credential = authorization[prefix.Length..];
        DeviceAuthenticationResult? authentication = await _deviceRegistry.AuthenticateAsync(
            new DeviceAuthenticationRequest(deviceId, userId, credential),
            Context.RequestAborted).ConfigureAwait(false);
        if (authentication is null)
        {
            return AuthenticateResult.Fail("Device authentication failed.");
        }

        ClaimsIdentity identity = new(SchemeName);
        identity.AddClaim(new Claim(DeviceClaimTypes.DeviceId, authentication.DeviceId));
        identity.AddClaim(new Claim(DeviceClaimTypes.UserId, authentication.UserId));
        AddPermissionClaim(identity, authentication.Permissions, LocalAccess.Read, LocalPermissionNames.Read);
        AddPermissionClaim(identity, authentication.Permissions, LocalAccess.Operations, LocalPermissionNames.Operations);
        AddPermissionClaim(identity, authentication.Permissions, LocalAccess.Admin, LocalPermissionNames.Admin);
        ClaimsPrincipal principal = new(identity);
        AuthenticationTicket ticket = new(principal, SchemeName);
        return AuthenticateResult.Success(ticket);
    }

    protected override Task HandleChallengeAsync(AuthenticationProperties properties)
    {
        Response.StatusCode = StatusCodes.Status401Unauthorized;
        Response.Headers.WWWAuthenticate = SchemeName;
        return Task.CompletedTask;
    }

    protected override Task HandleForbiddenAsync(AuthenticationProperties properties)
    {
        Response.StatusCode = StatusCodes.Status403Forbidden;
        return Task.CompletedTask;
    }

    private static void AddPermissionClaim(
        ClaimsIdentity identity,
        LocalAccess effectivePermissions,
        LocalAccess permission,
        string claimValue)
    {
        if ((effectivePermissions & permission) == permission)
        {
            identity.AddClaim(new Claim(DeviceClaimTypes.Permission, claimValue));
        }
    }
}
