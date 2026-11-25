using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Utilities;

namespace Alhayah.Desktop.Services;

public interface IBackupClient
{
    Task<BackupResponse> CreateBackupAsync(
        string? destinationDirectory,
        CancellationToken cancellationToken = default);
}

public sealed class BackupClient : IBackupClient
{
    private readonly ISyncServiceClient _ipcClient;
    private readonly IAppLogger _logger;

    public BackupClient(ISyncServiceClient ipcClient, IAppLogger logger)
    {
        _ipcClient = ipcClient ?? throw new ArgumentNullException(nameof(ipcClient));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    }

    public async Task<BackupResponse> CreateBackupAsync(
        string? destinationDirectory,
        CancellationToken cancellationToken = default)
    {
        string? normalizedDestination = string.IsNullOrWhiteSpace(destinationDirectory)
            ? null
            : EnsureLocalPath(destinationDirectory, nameof(destinationDirectory));
        _logger.TryLog(AppLogEvent.BackupStarted);
        try
        {
            BackupResponse response = await _ipcClient.CreateBackupAsync(
                new BackupRequest { DestinationDirectory = normalizedDestination },
                cancellationToken).ConfigureAwait(false);
            _logger.TryLog(AppLogEvent.BackupSucceeded);
            return response;
        }
        catch (OperationCanceledException)
        {
            _logger.TryLog(AppLogEvent.BackupCancelled);
            throw;
        }
        catch (Exception)
        {
            _logger.TryLog(AppLogEvent.BackupFailed);
            throw;
        }
    }

    internal static string EnsureLocalPath(string path, string parameterName)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(path, parameterName);
        string candidate = path.Trim();
        if (IsUncPath(candidate) || !Path.IsPathFullyQualified(candidate))
        {
            throw new ArgumentException("A fully qualified device-local path is required.", parameterName);
        }

        string fullPath = Path.GetFullPath(candidate);
        if (IsUncPath(fullPath))
        {
            throw new ArgumentException("Network paths are not allowed for device-local storage.", parameterName);
        }

        return fullPath;
    }

    private static bool IsUncPath(string path) =>
        path.StartsWith(@"\\", StringComparison.Ordinal) ||
        path.StartsWith("//", StringComparison.Ordinal);
}
