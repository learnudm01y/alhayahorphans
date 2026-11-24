using System.Globalization;
using Alhayah.LocalServer.Data;
using Microsoft.Data.Sqlite;

namespace Alhayah.LocalServer.Services;

public sealed class LocalBackupService(
    LocalDbConnectionFactory connectionFactory,
    LocalStorageOptions storage)
{
    private readonly LocalDbConnectionFactory _connectionFactory = connectionFactory ?? throw new ArgumentNullException(nameof(connectionFactory));
    private readonly LocalStorageOptions _storage = storage ?? throw new ArgumentNullException(nameof(storage));

    public async Task<string> CreateBackupAsync(CancellationToken cancellationToken = default)
    {
        string backupDirectory = LocalPathGuard.EnsureLocalPath(_storage.BackupDirectory);
        Directory.CreateDirectory(backupDirectory);
        string backupName = string.Create(
            CultureInfo.InvariantCulture,
            $"alhayah-{DateTimeOffset.UtcNow:yyyyMMddHHmmssfff}-{Guid.NewGuid():N}.db");
        string backupPath = Path.Combine(backupDirectory, backupName);

        await CopyOnlineAsync(_storage.DatabasePath, backupPath, cancellationToken).ConfigureAwait(false);
        return backupPath;
    }

    public async Task RestoreBackupAsync(
        string backupPath,
        string destinationPath,
        CancellationToken cancellationToken = default)
    {
        string liveDatabasePath = LocalPathGuard.EnsureLocalPath(_storage.DatabasePath);
        string restoredPath = LocalPathGuard.EnsureLocalPath(destinationPath);
        if (string.Equals(liveDatabasePath, restoredPath, StringComparison.OrdinalIgnoreCase))
        {
            throw new InvalidOperationException("A backup cannot be restored over the live database while the service owns it.");
        }

        await CopyOnlineAsync(backupPath, restoredPath, cancellationToken).ConfigureAwait(false);
    }

    private async Task CopyOnlineAsync(
        string sourcePath,
        string destinationPath,
        CancellationToken cancellationToken)
    {
        string source = LocalPathGuard.EnsureLocalPath(sourcePath);
        string destination = LocalPathGuard.EnsureLocalPath(destinationPath);
        if (!File.Exists(source))
        {
            throw new FileNotFoundException("The source SQLite database was not found.", source);
        }

        if (string.Equals(source, destination, StringComparison.OrdinalIgnoreCase))
        {
            throw new InvalidOperationException("SQLite online backup requires distinct source and destination paths.");
        }

        if (File.Exists(destination))
        {
            throw new IOException("The backup destination already exists; refusing to overwrite it.");
        }

        string destinationDirectory = Path.GetDirectoryName(destination)
            ?? throw new InvalidOperationException("The backup destination must include a local directory.");
        Directory.CreateDirectory(destinationDirectory);

        try
        {
            await using SqliteConnection sourceConnection = await _connectionFactory
                .OpenConnectionAsync(source, cancellationToken)
                .ConfigureAwait(false);
            await using SqliteConnection destinationConnection = await _connectionFactory
                .OpenConnectionAsync(destination, cancellationToken, enableWal: false)
                .ConfigureAwait(false);

            sourceConnection.BackupDatabase(destinationConnection);
            await VerifyIntegrityAsync(destinationConnection, cancellationToken).ConfigureAwait(false);
        }
        catch
        {
            if (File.Exists(destination))
            {
                File.Delete(destination);
            }

            throw;
        }
    }

    private static async Task VerifyIntegrityAsync(
        SqliteConnection connection,
        CancellationToken cancellationToken)
    {
        await using SqliteCommand command = connection.CreateCommand();
        command.CommandText = "PRAGMA integrity_check;";
        string? result = Convert.ToString(
            await command.ExecuteScalarAsync(cancellationToken).ConfigureAwait(false),
            CultureInfo.InvariantCulture);
        if (!string.Equals(result, "ok", StringComparison.OrdinalIgnoreCase))
        {
            throw new IOException("The SQLite backup did not pass integrity_check.");
        }
    }
}
