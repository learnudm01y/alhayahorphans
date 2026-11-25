using System.Net.Http.Headers;
using System.Net.Security;
using System.Security.Cryptography.X509Certificates;
using Alhayah.Contracts.Ipc;
using Alhayah.SyncService.Configuration;

namespace Alhayah.SyncService.Services;

public interface ILaravelHttpClientFactory
{
    Task<HttpClient> CreateAsync(CancellationToken cancellationToken);
}

public sealed class LaravelHttpClientFactory(SyncSettingsStore settingsStore) : ILaravelHttpClientFactory
{
    private readonly SyncSettingsStore _settingsStore = settingsStore ?? throw new ArgumentNullException(nameof(settingsStore));

    public async Task<HttpClient> CreateAsync(CancellationToken cancellationToken)
    {
        ConnectionSettingsData settings = await _settingsStore.LoadAsync(cancellationToken).ConfigureAwait(false);
        CertificatePinValidator pins = new(
            settings.LaravelSpkiSha256Pin,
            settings.PreviousLaravelSpkiSha256Pin);
        SocketsHttpHandler handler = new()
        {
            AllowAutoRedirect = false
        };
        handler.SslOptions.RemoteCertificateValidationCallback = (_, certificate, _, errors) =>
        {
            if (certificate is null)
            {
                return false;
            }

            using X509Certificate2 serverCertificate = new(certificate);
            if (errors == SslPolicyErrors.None && !pins.Validate(serverCertificate, errors))
            {
                throw new LaravelCertificatePinException();
            }

            return pins.Validate(serverCertificate, errors);
        };

        return new HttpClient(handler, disposeHandler: true)
        {
            BaseAddress = new Uri(settings.LaravelEndpoint.TrimEnd('/') + "/api/desktop/v1/", UriKind.Absolute),
            Timeout = TimeSpan.FromSeconds(settings.RequestTimeoutSeconds),
            DefaultRequestHeaders =
            {
                Accept = { new MediaTypeWithQualityHeaderValue("application/json") }
            }
        };
    }
}
