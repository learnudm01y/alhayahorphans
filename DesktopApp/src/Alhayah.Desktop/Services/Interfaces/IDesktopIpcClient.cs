using Alhayah.Desktop.Models;

namespace Alhayah.Desktop.Services.Interfaces;

public interface IDesktopIpcClient
{
    Task<DesktopSettings> GetSettingsAsync(CancellationToken cancellationToken = default);

    Task SaveSettingsAsync(DesktopSettings settings, CancellationToken cancellationToken = default);
}
