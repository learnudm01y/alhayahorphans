using Alhayah.SyncService.Connectors;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService.Realtime;

public interface ILocalSyncHubClient : IAsyncDisposable
{
    bool IsConnected { get; }

    Task StartAsync(Func<CancellationToken, Task> onChanged, CancellationToken cancellationToken);

    Task StopAsync(CancellationToken cancellationToken);
}

public sealed class LocalSyncRealtimeCoordinator : IAsyncDisposable
{
    private readonly SemaphoreSlim _pollGate = new(1, 1);
    private bool _usingPollingFallback = true;
    private readonly ILocalSyncHubClient _hubClient;
    private readonly LocalServerSyncConnector _connector;
    private readonly ILogger<LocalSyncRealtimeCoordinator> _logger;

    public LocalSyncRealtimeCoordinator(
        ILocalSyncHubClient hubClient,
        LocalServerSyncConnector connector,
        ILogger<LocalSyncRealtimeCoordinator> logger)
    {
        ArgumentNullException.ThrowIfNull(hubClient);
        ArgumentNullException.ThrowIfNull(connector);
        ArgumentNullException.ThrowIfNull(logger);
        _hubClient = hubClient;
        _connector = connector;
        _logger = logger;
    }

    public bool UsingPollingFallback => _usingPollingFallback || !_hubClient.IsConnected;

    public async Task StartAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        _usingPollingFallback = true;
        try
        {
            await _hubClient.StartAsync(PollAfterHintAsync, cancellationToken).ConfigureAwait(false);
            _usingPollingFallback = !_hubClient.IsConnected;
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception)
        {
            _logger.LogInformation(
                new EventId(29401),
                "The Local Server SignalR hint channel is unavailable; polling remains enabled.");
        }
    }

    public Task PollOnceAsync(CancellationToken cancellationToken) => PollCoreAsync(cancellationToken);

    public async Task StopAsync(CancellationToken cancellationToken)
    {
        try
        {
            await _hubClient.StopAsync(cancellationToken).ConfigureAwait(false);
        }
        finally
        {
            _usingPollingFallback = true;
        }
    }

    public async ValueTask DisposeAsync()
    {
        await StopAsync(CancellationToken.None).ConfigureAwait(false);
        await _hubClient.DisposeAsync().ConfigureAwait(false);
        _pollGate.Dispose();
    }

    private Task PollAfterHintAsync(CancellationToken cancellationToken) => PollCoreAsync(cancellationToken);

    private async Task PollCoreAsync(CancellationToken cancellationToken)
    {
        await _pollGate.WaitAsync(cancellationToken).ConfigureAwait(false);
        try
        {
            if (!_hubClient.IsConnected)
            {
                _usingPollingFallback = true;
            }

            await _connector.SynchronizeNowAsync(cancellationToken).ConfigureAwait(false);
        }
        finally
        {
            _pollGate.Release();
        }
    }
}
