using System.Net.Http.Headers;
using System.Net.Security;
using System.Security.Authentication;
using System.Security.Cryptography.X509Certificates;
using Alhayah.Contracts.Ipc;
using Alhayah.SyncService.Configuration;
using Alhayah.SyncService.Connectors;

namespace Alhayah.SyncService.Services;

public interface ILocalHttpClientFactory
{
    Task<HttpClient> CreateAsync(CancellationToken cancellationToken);

    Task<HttpMessageHandler> CreateHandlerAsync(CancellationToken cancellationToken);
}

public interface ILocalClientCertificateProvider
{
    X509Certificate2 LoadCertificate(string thumbprint);
}

public sealed class LocalHttpClientFactory(
    SyncSettingsStore settingsStore,
    LocalConnectorOptions options,
    ILocalClientCertificateProvider certificateProvider) : ILocalHttpClientFactory
{
    private readonly SyncSettingsStore _settingsStore = settingsStore ?? throw new ArgumentNullException(nameof(settingsStore));
    private readonly LocalConnectorOptions _options = options ?? throw new ArgumentNullException(nameof(options));
    private readonly ILocalClientCertificateProvider _certificateProvider = certificateProvider
        ?? throw new ArgumentNullException(nameof(certificateProvider));

    public async Task<HttpClient> CreateAsync(CancellationToken cancellationToken)
    {
        (SocketsHttpHandler handler, ConnectionSettingsData settings) = await CreateSecuredHandlerAsync(cancellationToken)
            .ConfigureAwait(false);
        return new HttpClient(handler, disposeHandler: true)
        {
            BaseAddress = new Uri(settings.LocalServerEndpoint.TrimEnd('/') + "/", UriKind.Absolute),
            Timeout = TimeSpan.FromSeconds(settings.RequestTimeoutSeconds),
            DefaultRequestHeaders =
            {
                Accept = { new MediaTypeWithQualityHeaderValue("application/json") }
            }
        };
    }

    public async Task<HttpMessageHandler> CreateHandlerAsync(CancellationToken cancellationToken)
    {
        (SocketsHttpHandler handler, _) = await CreateSecuredHandlerAsync(cancellationToken).ConfigureAwait(false);
        return handler;
    }

    private async Task<(SocketsHttpHandler Handler, ConnectionSettingsData Settings)> CreateSecuredHandlerAsync(
        CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("The Local Server connector requires Windows certificate stores.");
        }

        ConnectionSettingsData settings = await _settingsStore.LoadAsync(cancellationToken).ConfigureAwait(false);
        CertificatePinValidator pins = new(
            settings.LocalServerSpkiSha256Pin,
            settings.PreviousLocalServerSpkiSha256Pin);
        X509Certificate2 clientCertificate;
        try
        {
            clientCertificate = _certificateProvider.LoadCertificate(_options.ClientCertificateThumbprint)
                ?? throw new LocalSyncException(
                    "client_certificate_missing",
                    "The configured Local Server client certificate is unavailable.");
        }
        catch (ArgumentException exception)
        {
            throw new LocalSyncException(
                "client_certificate_invalid",
                "The configured Local Server client certificate thumbprint is invalid.",
                innerException: exception);
        }

        try
        {
            if (!clientCertificate.HasPrivateKey)
            {
                throw new LocalSyncException(
                    "client_certificate_missing",
                    "The Local Server client certificate has no private key.");
            }

            LocalSslHttpHandler handler = new(clientCertificate)
            {
                AllowAutoRedirect = false
            };
            handler.SslOptions.ClientCertificates.Add(clientCertificate);
            handler.SslOptions.RemoteCertificateValidationCallback = (_, certificate, _, errors) =>
            {
                if (certificate is null || errors != SslPolicyErrors.None)
                {
                    return false;
                }

                using X509Certificate2 serverCertificate = new(certificate);
                if (!pins.Validate(serverCertificate, errors))
                {
                    throw new LocalCertificatePinException();
                }

                return true;
            };
            return (handler, settings);
        }
        catch
        {
            clientCertificate.Dispose();
            throw;
        }
    }
}

public sealed class WindowsLocalClientCertificateProvider : ILocalClientCertificateProvider
{
    public X509Certificate2 LoadCertificate(string thumbprint)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(thumbprint);
        string normalized = thumbprint.Replace(" ", string.Empty, StringComparison.Ordinal);
        if (normalized.Length != 40 || !normalized.All(Uri.IsHexDigit))
        {
            throw new ArgumentException("A client certificate thumbprint must contain 40 hexadecimal characters.", nameof(thumbprint));
        }

        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("The Local Server client certificate is stored in the Windows certificate store.");
        }

        using X509Store store = new(StoreName.My, StoreLocation.LocalMachine);
        store.Open(OpenFlags.ReadOnly | OpenFlags.OpenExistingOnly);
        X509Certificate2Collection matches = store.Certificates.Find(
            X509FindType.FindByThumbprint,
            normalized,
            validOnly: false);
        if (matches.Count != 1)
        {
            throw new LocalSyncException(
                "client_certificate_missing",
                "The configured Local Server client certificate is unavailable or ambiguous.");
        }

        X509Certificate2 certificate = matches[0];
        try
        {
            if (!certificate.HasPrivateKey)
            {
                throw new LocalSyncException(
                    "client_certificate_missing",
                    "The configured Local Server client certificate has no private key.");
            }

            return new X509Certificate2(certificate);
        }
        finally
        {
            certificate.Dispose();
        }
    }
}

internal sealed class LocalSslHttpHandler(X509Certificate2 clientCertificate) : SocketsHttpHandler
{
    protected override void Dispose(bool disposing)
    {
        try
        {
            base.Dispose(disposing);
        }
        finally
        {
            if (disposing)
            {
                clientCertificate.Dispose();
            }
        }
    }
}

internal sealed class LocalCertificatePinException()
    : AuthenticationException("The Local Server certificate SPKI pin does not match.");
