using System.Security.Cryptography.X509Certificates;
using System.Text.Json.Serialization;
using Alhayah.Contracts.Domain;

namespace Alhayah.LocalServer.Authentication;

public sealed class LocalServerSecurityOptions
{
    public required SyncSourceId SourceId { get; init; }

    public required string ApiVersion { get; init; }

    public required int HttpsPort { get; init; }

    public required string ServerCertificateThumbprint { get; init; }

    public required string TrustedRootCertificateThumbprint { get; init; }

    public required IReadOnlyList<string> AllowedClientSpkiPins { get; init; }

    public void Validate()
    {
        if (!SourceId.Value.StartsWith("local:", StringComparison.Ordinal))
        {
            throw new InvalidOperationException("The LocalServer SourceId must use the local: installation identity.");
        }

        if (string.IsNullOrWhiteSpace(ApiVersion))
        {
            throw new InvalidOperationException("An API version is required.");
        }

        if (HttpsPort is < 1 or > 65535)
        {
            throw new InvalidOperationException("The HTTPS port must be between 1 and 65535.");
        }

        if (string.IsNullOrWhiteSpace(ServerCertificateThumbprint) ||
            string.IsNullOrWhiteSpace(TrustedRootCertificateThumbprint))
        {
            throw new InvalidOperationException("Server and trusted-root certificate thumbprints are required.");
        }

        if (AllowedClientSpkiPins.Count == 0 || AllowedClientSpkiPins.Any(pin => !IsValidPin(pin)))
        {
            throw new InvalidOperationException("At least one valid SHA-256 client SPKI pin is required.");
        }
    }

    private static bool IsValidPin(string pin)
    {
        if (string.IsNullOrWhiteSpace(pin))
        {
            return false;
        }

        try
        {
            return Convert.FromBase64String(pin).Length == 32;
        }
        catch (FormatException)
        {
            return false;
        }
    }
}

public sealed class LocalServerCertificateMaterials : IDisposable
{
    private readonly X509Certificate2 _serverCertificate;
    private readonly X509Certificate2 _trustedRootCertificate;

    public LocalServerCertificateMaterials(
        X509Certificate2 serverCertificate,
        X509Certificate2 trustedRootCertificate)
    {
        _serverCertificate = new X509Certificate2(serverCertificate ?? throw new ArgumentNullException(nameof(serverCertificate)));
        _trustedRootCertificate = new X509Certificate2(
            trustedRootCertificate ?? throw new ArgumentNullException(nameof(trustedRootCertificate)));
    }

    public static LocalServerCertificateMaterials Load(LocalServerSecurityOptions options)
    {
        ArgumentNullException.ThrowIfNull(options);
        options.Validate();

        using X509Certificate2 serverCertificate = LoadCertificate(
            StoreName.My,
            options.ServerCertificateThumbprint,
            requirePrivateKey: true);
        using X509Certificate2 trustedRootCertificate = LoadCertificate(
            StoreName.Root,
            options.TrustedRootCertificateThumbprint,
            requirePrivateKey: false);
        return new LocalServerCertificateMaterials(serverCertificate, trustedRootCertificate);
    }

    public X509Certificate2 CreateServerCertificate() => new(_serverCertificate);

    public X509Certificate2 CreateTrustedRootCertificate() => new(_trustedRootCertificate);

    public ServerCertificatePublicInfo GetPublicInfo() => new(
        DeviceCertificateValidator.ComputeSpkiPin(_serverCertificate),
        new DateTimeOffset(_serverCertificate.NotBefore.ToUniversalTime()),
        new DateTimeOffset(_serverCertificate.NotAfter.ToUniversalTime()));

    public void Dispose()
    {
        _serverCertificate.Dispose();
        _trustedRootCertificate.Dispose();
    }

    private static X509Certificate2 LoadCertificate(
        StoreName storeName,
        string thumbprint,
        bool requirePrivateKey)
    {
        using X509Store store = new(storeName, StoreLocation.LocalMachine);
        store.Open(OpenFlags.ReadOnly | OpenFlags.OpenExistingOnly);
        X509Certificate2Collection matches = store.Certificates.Find(
            X509FindType.FindByThumbprint,
            thumbprint,
            validOnly: false);
        if (matches.Count != 1)
        {
            throw new InvalidOperationException($"Exactly one certificate must match the configured {storeName} thumbprint.");
        }

        X509Certificate2 certificate = matches[0];
        if (requirePrivateKey && !certificate.HasPrivateKey)
        {
            throw new InvalidOperationException("The LocalServer certificate private key is unavailable to the service account.");
        }

        return new X509Certificate2(certificate);
    }
}

public sealed record ServerCertificatePublicInfo(
    [property: JsonPropertyName("sha256_spki_pin")] string Sha256SpkiPin,
    [property: JsonPropertyName("not_before")] DateTimeOffset NotBefore,
    [property: JsonPropertyName("not_after")] DateTimeOffset NotAfter);
