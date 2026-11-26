using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using Alhayah.SyncService.Configuration;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Security;
using Microsoft.AspNetCore.SignalR.Client;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService.Realtime;

public sealed class LocalSyncHubClient : ILocalSyncHubClient
{
    private readonly Func<CancellationToken, Task<HttpMessageHandler>> _handlerFactory;
    private readonly SyncSettingsStore _settingsStore;
    private readonly LocalConnectorOptions _options;
    private readonly ISecretStore _secrets;
    private readonly ILogger<LocalSyncHubClient> _logger;
    private HttpMessageHandler? _handler;
    private HubConnection? _connection;
    private Func<CancellationToken, Task>? _onChanged;

    public LocalSyncHubClient(
        Func<CancellationToken, Task<HttpMessageHandler>> handlerFactory,
        SyncSettingsStore settingsStore,
        LocalConnectorOptions options,
        ISecretStore secrets,
        ILogger<LocalSyncHubClient> logger)
    {
        _handlerFactory = handlerFactory ?? throw new ArgumentNullException(nameof(handlerFactory));
        _settingsStore = settingsStore ?? throw new ArgumentNullException(nameof(settingsStore));
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _secrets = secrets ?? throw new ArgumentNullException(nameof(secrets));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    }

    public bool IsConnected => _connection?.State == HubConnectionState.Connected;

    public async Task StartAsync(
        Func<CancellationToken, Task> onChanged,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(onChanged);
        cancellationToken.ThrowIfCancellationRequested();
        if (_connection is not null)
        {
            return;
        }

        Alhayah.Contracts.Ipc.ConnectionSettingsData settings = await _settingsStore
            .LoadAsync(cancellationToken)
            .ConfigureAwait(false);
        string credential = ReadCredential();
        HttpMessageHandler handler = await _handlerFactory(cancellationToken).ConfigureAwait(false);
        _handler = handler;
        HubConnection? connection = null;
        try
        {
            Uri hubUri = new(settings.LocalServerEndpoint.TrimEnd('/') + "/local/hubs/sync", UriKind.Absolute);
            connection = new HubConnectionBuilder()
                .WithUrl(hubUri, options =>
                {
                    options.Headers["Authorization"] = new AuthenticationHeaderValue("Device", credential).ToString();
                    options.Headers["X-Alhayah-Device-Id"] = _options.DeviceId;
                    options.Headers["X-Alhayah-User-Id"] = _options.UserId;
                    options.HttpMessageHandlerFactory = _ => Task.FromResult(handler);
                })
                .WithAutomaticReconnect()
                .Build();
            _connection = connection;
            _onChanged = onChanged;
            connection.On<LocalOperationResult>("localOperationChanged", _ => InvokeHintAsync());
            await connection.StartAsync(cancellationToken).ConfigureAwait(false);
        }
        catch
        {
            _connection = null;
            _onChanged = null;
            if (connection is not null)
            {
                await connection.DisposeAsync().ConfigureAwait(false);
            }

            handler.Dispose();
            _handler = null;
            throw;
        }
    }

    public async Task StopAsync(CancellationToken cancellationToken)
    {
        HubConnection? connection = _connection;
        if (connection is null)
        {
            return;
        }

        await connection.StopAsync(cancellationToken).ConfigureAwait(false);
        _onChanged = null;
    }

    public async ValueTask DisposeAsync()
    {
        HubConnection? connection = _connection;
        _connection = null;
        _onChanged = null;
        if (connection is not null)
        {
            await connection.DisposeAsync().ConfigureAwait(false);
        }

        _handler?.Dispose();
        _handler = null;
    }

    private string ReadCredential()
    {
        byte[]? bytes = _secrets.Read(SecretTargets.LocalServerDeviceCredential(_options.SourceId));
        if (bytes is null || bytes.Length == 0)
        {
            throw new LocalSyncException("credential_missing", "The Local Server device credential is unavailable.");
        }

        try
        {
            string credential = new UTF8Encoding(encoderShouldEmitUTF8Identifier: false, throwOnInvalidBytes: true).GetString(bytes);
            if (string.IsNullOrWhiteSpace(credential) || credential.Any(char.IsControl))
            {
                throw new LocalSyncException("credential_invalid", "The Local Server device credential is invalid.");
            }

            return credential;
        }
        catch (DecoderFallbackException exception)
        {
            throw new LocalSyncException("credential_invalid", "The Local Server device credential is invalid.", innerException: exception);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(bytes);
        }
    }

    private async Task InvokeHintAsync()
    {
        Func<CancellationToken, Task>? onChanged = _onChanged;
        if (onChanged is null)
        {
            return;
        }

        try
        {
            await onChanged(CancellationToken.None).ConfigureAwait(false);
        }
        catch (OperationCanceledException)
        {
        }
        catch (Exception)
        {
            _logger.LogInformation(
                new EventId(29402),
                "A Local Server SignalR hint could not trigger polling; periodic synchronization remains available.");
        }
    }
}
