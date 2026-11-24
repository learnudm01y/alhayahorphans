using System.Text.Json.Serialization;
using Alhayah.LocalServer.Authentication;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;

namespace Alhayah.LocalServer.Controllers;

[ApiController]
[AllowAnonymous]
public sealed class HealthController(
    LocalServerSecurityOptions securityOptions,
    LocalServerCertificateMaterials certificateMaterials,
    TimeProvider timeProvider) : ControllerBase
{
    private readonly LocalServerSecurityOptions _securityOptions = securityOptions ?? throw new ArgumentNullException(nameof(securityOptions));
    private readonly LocalServerCertificateMaterials _certificateMaterials = certificateMaterials
        ?? throw new ArgumentNullException(nameof(certificateMaterials));
    private readonly TimeProvider _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));

    [HttpGet("/health")]
    public LocalServerHealthResponse Health() => new(
        "ok",
        _securityOptions.ApiVersion,
        _securityOptions.SourceId.Value,
        _timeProvider.GetUtcNow(),
        _certificateMaterials.GetPublicInfo());

    [HttpGet("/capabilities")]
    public LocalServerCapabilitiesResponse Capabilities() => new(
        _securityOptions.ApiVersion,
        _securityOptions.SourceId.Value,
        _timeProvider.GetUtcNow(),
        _certificateMaterials.GetPublicInfo(),
        "independent-device-credential",
        "https-required",
        "trusted-chain-and-sha256-spki-pin-required",
        new[] { LocalPermissionNames.Read, LocalPermissionNames.Operations, LocalPermissionNames.Admin });
}

public sealed record LocalServerHealthResponse(
    [property: JsonPropertyName("status")] string Status,
    [property: JsonPropertyName("api_version")] string ApiVersion,
    [property: JsonPropertyName("source_id")] string SourceId,
    [property: JsonPropertyName("server_time")] DateTimeOffset ServerTime,
    [property: JsonPropertyName("certificate")] ServerCertificatePublicInfo Certificate);

public sealed record LocalServerCapabilitiesResponse(
    [property: JsonPropertyName("api_version")] string ApiVersion,
    [property: JsonPropertyName("source_id")] string SourceId,
    [property: JsonPropertyName("server_time")] DateTimeOffset ServerTime,
    [property: JsonPropertyName("certificate")] ServerCertificatePublicInfo Certificate,
    [property: JsonPropertyName("credential_type")] string CredentialType,
    [property: JsonPropertyName("transport")] string Transport,
    [property: JsonPropertyName("client_certificate")] string ClientCertificate,
    [property: JsonPropertyName("permissions")] IReadOnlyList<string> Permissions);
