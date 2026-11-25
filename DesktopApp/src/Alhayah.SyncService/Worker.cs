using Alhayah.SyncService.Ipc;
using Alhayah.SyncService.Storage;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService;

public sealed class Worker(
    ILocalStoreInitializer storeInitializer,
    NamedPipeHost pipeHost,
    ILogger<Worker> logger) : BackgroundService
{
    private readonly ILocalStoreInitializer _storeInitializer =
        storeInitializer ?? throw new ArgumentNullException(nameof(storeInitializer));
    private readonly NamedPipeHost _pipeHost = pipeHost ?? throw new ArgumentNullException(nameof(pipeHost));
    private readonly ILogger<Worker> _logger = logger ?? throw new ArgumentNullException(nameof(logger));

    public override async Task StartAsync(CancellationToken cancellationToken)
    {
        await _storeInitializer.InitializeAsync(cancellationToken).ConfigureAwait(false);
        await _pipeHost.StartAsync(cancellationToken).ConfigureAwait(false);
        try
        {
            await base.StartAsync(cancellationToken).ConfigureAwait(false);
        }
        catch
        {
            await _pipeHost.StopAsync(CancellationToken.None).ConfigureAwait(false);
            throw;
        }

        _logger.LogInformation(new EventId(27201), "The UI-independent sync-service worker started.");
    }

    public override async Task StopAsync(CancellationToken cancellationToken)
    {
        await _pipeHost.StopAsync(cancellationToken).ConfigureAwait(false);
        await base.StopAsync(cancellationToken).ConfigureAwait(false);
        _logger.LogInformation(new EventId(27202), "The UI-independent sync-service worker stopped.");
    }

    protected override Task ExecuteAsync(CancellationToken stoppingToken) => Task.CompletedTask;
}
