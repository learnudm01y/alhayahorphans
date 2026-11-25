using System.Text;
using System.Text.Json;

namespace Alhayah.Desktop.Utilities;

public enum AppLogEvent
{
    BackupStarted,
    BackupSucceeded,
    BackupFailed,
    BackupCancelled,
    RestoreStarted,
    RestoreSucceeded,
    RestoreFailed,
    RestoreCancelled
}

public interface IAppLogger
{
    bool TryLog(AppLogEvent logEvent);
}

public sealed class AppLogger : IAppLogger
{
    private readonly object _sync = new();
    private readonly string _logFilePath;

    public AppLogger(string? logFilePath = null)
    {
        _logFilePath = logFilePath is null
            ? Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Alhayah",
                "desktop.log")
            : Path.GetFullPath(logFilePath);
    }

    public bool TryLog(AppLogEvent logEvent)
    {
        if (!Enum.IsDefined(logEvent))
        {
            return false;
        }

        string entry = JsonSerializer.Serialize(new
        {
            timestampUtc = DateTimeOffset.UtcNow,
            eventName = logEvent.ToString()
        });
        try
        {
            lock (_sync)
            {
                string directory = Path.GetDirectoryName(_logFilePath)
                    ?? throw new InvalidOperationException("The application log path must include a directory.");
                Directory.CreateDirectory(directory);
                File.AppendAllText(_logFilePath, entry + Environment.NewLine, Encoding.UTF8);
            }

            return true;
        }
        catch (Exception exception) when (exception is IOException or UnauthorizedAccessException)
        {
            return false;
        }
    }
}
