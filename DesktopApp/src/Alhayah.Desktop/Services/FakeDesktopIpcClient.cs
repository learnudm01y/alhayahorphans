using Alhayah.Desktop.Models;
using Alhayah.Desktop.Services.Interfaces;

namespace Alhayah.Desktop.Services;

public sealed class FakeDesktopIpcClient : IDesktopIpcClient
{
    private DesktopSettings _settings = new();

    public Task<DesktopSettings> GetSettingsAsync(CancellationToken cancellationToken = default)
    {
        cancellationToken.ThrowIfCancellationRequested();
        return Task.FromResult(_settings with { });
    }

    public Task SaveSettingsAsync(DesktopSettings settings, CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(settings);
        cancellationToken.ThrowIfCancellationRequested();
        _settings = settings with { };
        return Task.CompletedTask;
    }
}
