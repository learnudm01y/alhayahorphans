using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Utilities;

namespace Alhayah.Desktop.Services;

public interface IRestoreClient
{
    Task<RestoreResponse> RestoreBackupAsync(
        string backupDirectory,
        string destinationDatabasePath,
        string destinationAttachmentDirectory,
        CancellationToken cancellationToken = default);
}

public sealed class RestoreClient : IRestoreClient
{
    private readonly ISyncServiceClient _ipcClient;
    private readonly IAppLogger _logger;

    public RestoreClient(ISyncServiceClient ipcClient, IAppLogger logger)
    {
        _ipcClient = ipcClient ?? throw new ArgumentNullException(nameof(ipcClient));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    }

    public async Task<RestoreResponse> RestoreBackupAsync(
        string backupDirectory,
        string destinationDatabasePath,
        string destinationAttachmentDirectory,
        CancellationToken cancellationToken = default)
    {
        RestoreRequest request = new()
        {
            BackupDirectory = BackupClient.EnsureLocalPath(backupDirectory, nameof(backupDirectory)),
            DestinationDatabasePath = BackupClient.EnsureLocalPath(
                destinationDatabasePath,
                nameof(destinationDatabasePath)),
            DestinationAttachmentDirectory = BackupClient.EnsureLocalPath(
                destinationAttachmentDirectory,
                nameof(destinationAttachmentDirectory))
        };
        _logger.TryLog(AppLogEvent.RestoreStarted);
        try
        {
            RestoreResponse response = await _ipcClient.RestoreBackupAsync(request, cancellationToken)
                .ConfigureAwait(false);
            _logger.TryLog(AppLogEvent.RestoreSucceeded);
            return response;
        }
        catch (OperationCanceledException)
        {
            _logger.TryLog(AppLogEvent.RestoreCancelled);
            throw;
        }
        catch (Exception)
        {
            _logger.TryLog(AppLogEvent.RestoreFailed);
            throw;
        }
    }
}
