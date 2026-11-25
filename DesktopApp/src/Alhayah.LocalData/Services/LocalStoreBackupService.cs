using System.Globalization;
using System.Security.Cryptography;
using System.Text.Json;
using Alhayah.LocalData.Data;
using Microsoft.Data.Sqlite;

namespace Alhayah.LocalData.Services;

public sealed record LocalStoreBackup(string DirectoryPath, string DatabasePath, string ManifestPath);

public sealed class LocalStoreBackupService(
    LocalStoreConnectionFactory connectionFactory,
    LocalStoreOptions storage)
{
    private const int FormatVersion = 1;
    private const string DatabaseFileName = "device.db";
    private readonly LocalStoreConnectionFactory _connectionFactory = connectionFactory ?? throw new ArgumentNullException(nameof(connectionFactory));
    private readonly LocalStoreOptions _storage = storage ?? throw new ArgumentNullException(nameof(storage));

    public async Task<LocalStoreBackup> CreateBackupAsync(CancellationToken cancellationToken = default)
    {
        string backupRoot = LocalStorePathGuard.EnsureLocalPath(_storage.BackupDirectory);
        Directory.CreateDirectory(backupRoot);
        string backupDirectory = Path.Combine(
            backupRoot,
            string.Create(
                CultureInfo.InvariantCulture,
                $"alhayah-{DateTimeOffset.UtcNow:yyyyMMddHHmmssfff}-{Guid.NewGuid():N}"));
        string databasePath = Path.Combine(backupDirectory, DatabaseFileName);
        string manifestPath = Path.Combine(backupDirectory, "manifest.json");
        Directory.CreateDirectory(backupDirectory);

        try
        {
            await CopyOnlineAsync(_storage.DatabasePath, databasePath, cancellationToken).ConfigureAwait(false);
            BackupFile databaseFile = await HashFileAsync(DatabaseFileName, databasePath, cancellationToken)
                .ConfigureAwait(false);
            List<BackupFile> attachments = await CopyAttachmentsAsync(backupDirectory, cancellationToken)
                .ConfigureAwait(false);
            BackupManifest manifest = new()
            {
                FormatVersion = FormatVersion,
                Database = databaseFile,
                Attachments = attachments
            };
            await File.WriteAllTextAsync(
                manifestPath,
                JsonSerializer.Serialize(manifest),
                cancellationToken).ConfigureAwait(false);
            return new LocalStoreBackup(backupDirectory, databasePath, manifestPath);
        }
        catch
        {
            Directory.Delete(backupDirectory, recursive: true);
            throw;
        }
    }

    public async Task RestoreBackupAsync(
        string backupDirectory,
        string destinationDatabasePath,
        string destinationAttachmentDirectory,
        CancellationToken cancellationToken = default)
    {
        string sourceDirectory = LocalStorePathGuard.EnsureLocalPath(backupDirectory);
        string liveDatabasePath = LocalStorePathGuard.EnsureLocalPath(_storage.DatabasePath);
        string liveAttachmentDirectory = LocalStorePathGuard.EnsureLocalPath(_storage.AttachmentDirectory);
        string restoredDatabasePath = LocalStorePathGuard.EnsureLocalPath(destinationDatabasePath);
        string restoredAttachmentDirectory = LocalStorePathGuard.EnsureLocalPath(destinationAttachmentDirectory);
        if (string.Equals(liveDatabasePath, restoredDatabasePath, StringComparison.OrdinalIgnoreCase) ||
            string.Equals(liveAttachmentDirectory, restoredAttachmentDirectory, StringComparison.OrdinalIgnoreCase))
        {
            throw new InvalidOperationException("A backup cannot be restored over the live device store.");
        }

        if (File.Exists(restoredDatabasePath) || Directory.Exists(restoredAttachmentDirectory))
        {
            throw new IOException("The restore destination already exists; refusing to overwrite it.");
        }

        BackupManifest manifest = await ReadAndVerifyManifestAsync(sourceDirectory, cancellationToken)
            .ConfigureAwait(false);
        string restoredDatabaseDirectory = Path.GetDirectoryName(restoredDatabasePath)
            ?? throw new InvalidOperationException("The restore database path must include a local directory.");
        Directory.CreateDirectory(restoredDatabaseDirectory);
        bool databaseCreated = false;
        bool attachmentDirectoryCreated = false;
        try
        {
            await CopyOnlineAsync(
                ResolveBackupFile(sourceDirectory, manifest.Database.RelativePath),
                restoredDatabasePath,
                cancellationToken,
                enableSourceWal: false).ConfigureAwait(false);
            databaseCreated = true;
            Directory.CreateDirectory(restoredAttachmentDirectory);
            attachmentDirectoryCreated = true;
            foreach (BackupFile attachment in manifest.Attachments)
            {
                string fileName = Path.GetFileName(attachment.RelativePath);
                string destination = Path.Combine(restoredAttachmentDirectory, fileName);
                BackupFile copied = await CopyFileWithHashAsync(
                    ResolveBackupFile(sourceDirectory, attachment.RelativePath),
                    destination,
                    attachment.RelativePath,
                    cancellationToken).ConfigureAwait(false);
                EnsureMatchingFile(attachment, copied);
            }
        }
        catch
        {
            if (attachmentDirectoryCreated && Directory.Exists(restoredAttachmentDirectory))
            {
                Directory.Delete(restoredAttachmentDirectory, recursive: true);
            }

            if (databaseCreated)
            {
                File.Delete(restoredDatabasePath);
            }

            throw;
        }
    }

    private async Task CopyOnlineAsync(
        string sourcePath,
        string destinationPath,
        CancellationToken cancellationToken,
        bool enableSourceWal = true)
    {
        string source = LocalStorePathGuard.EnsureLocalPath(sourcePath);
        string destination = LocalStorePathGuard.EnsureLocalPath(destinationPath);
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
                .OpenConnectionAsync(source, cancellationToken, enableSourceWal)
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

    private async Task<List<BackupFile>> CopyAttachmentsAsync(
        string backupDirectory,
        CancellationToken cancellationToken)
    {
        string sourceDirectory = LocalStorePathGuard.EnsureLocalPath(_storage.AttachmentDirectory);
        string destinationDirectory = Path.Combine(backupDirectory, "attachments");
        Directory.CreateDirectory(destinationDirectory);
        List<BackupFile> files = [];
        if (!Directory.Exists(sourceDirectory))
        {
            return files;
        }

        foreach (string source in Directory.EnumerateFiles(sourceDirectory, "*", SearchOption.TopDirectoryOnly)
                     .OrderBy(path => path, StringComparer.Ordinal))
        {
            string relativePath = Path.Combine("attachments", Path.GetFileName(source));
            files.Add(await CopyFileWithHashAsync(
                source,
                Path.Combine(backupDirectory, relativePath),
                relativePath,
                cancellationToken).ConfigureAwait(false));
        }

        return files;
    }

    private static async Task<BackupManifest> ReadAndVerifyManifestAsync(
        string backupDirectory,
        CancellationToken cancellationToken)
    {
        string manifestPath = Path.Combine(backupDirectory, "manifest.json");
        BackupManifest manifest = JsonSerializer.Deserialize<BackupManifest>(
            await File.ReadAllTextAsync(manifestPath, cancellationToken).ConfigureAwait(false))
            ?? throw new InvalidDataException("The local store backup manifest is empty.");
        if (manifest.FormatVersion != FormatVersion ||
            manifest.Database is null ||
            !string.Equals(manifest.Database.RelativePath, DatabaseFileName, StringComparison.Ordinal) ||
            manifest.Attachments is null)
        {
            throw new InvalidDataException("The local store backup manifest is invalid or unsupported.");
        }

        foreach (BackupFile file in new[] { manifest.Database }.Concat(manifest.Attachments))
        {
            string path = ResolveBackupFile(backupDirectory, file.RelativePath);
            BackupFile actual = await HashFileAsync(file.RelativePath, path, cancellationToken).ConfigureAwait(false);
            EnsureMatchingFile(file, actual);
        }

        return manifest;
    }

    private static string ResolveBackupFile(string backupDirectory, string relativePath)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(relativePath);
        if (Path.IsPathRooted(relativePath))
        {
            throw new InvalidDataException("Backup manifest paths must be relative.");
        }

        string fullPath = Path.GetFullPath(Path.Combine(backupDirectory, relativePath));
        string root = Path.GetFullPath(backupDirectory);
        string prefix = root.EndsWith(Path.DirectorySeparatorChar) ? root : root + Path.DirectorySeparatorChar;
        if (!fullPath.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
        {
            throw new InvalidDataException("A backup manifest path escapes the backup directory.");
        }

        return fullPath;
    }

    private static async Task<BackupFile> HashFileAsync(
        string relativePath,
        string path,
        CancellationToken cancellationToken)
    {
        await using FileStream input = new(path, FileMode.Open, FileAccess.Read, FileShare.Read, 81920, useAsync: true);
        byte[] hash = await SHA256.HashDataAsync(input, cancellationToken).ConfigureAwait(false);
        return new BackupFile
        {
            RelativePath = relativePath,
            Length = input.Length,
            Sha256 = Convert.ToHexString(hash)
        };
    }

    private static async Task<BackupFile> CopyFileWithHashAsync(
        string sourcePath,
        string destinationPath,
        string relativePath,
        CancellationToken cancellationToken)
    {
        await using FileStream source = new(sourcePath, FileMode.Open, FileAccess.Read, FileShare.Read, 81920, useAsync: true);
        await using FileStream destination = new(destinationPath, FileMode.CreateNew, FileAccess.Write, FileShare.None, 81920, useAsync: true);
        using IncrementalHash hasher = IncrementalHash.CreateHash(HashAlgorithmName.SHA256);
        byte[] buffer = new byte[81920];
        long length = 0;
        int read;
        while ((read = await source.ReadAsync(buffer, cancellationToken).ConfigureAwait(false)) > 0)
        {
            hasher.AppendData(buffer, 0, read);
            await destination.WriteAsync(buffer.AsMemory(0, read), cancellationToken).ConfigureAwait(false);
            length += read;
        }

        await destination.FlushAsync(cancellationToken).ConfigureAwait(false);
        return new BackupFile
        {
            RelativePath = relativePath,
            Length = length,
            Sha256 = Convert.ToHexString(hasher.GetHashAndReset())
        };
    }

    private static void EnsureMatchingFile(BackupFile expected, BackupFile actual)
    {
        if (expected.Length != actual.Length ||
            !string.Equals(expected.Sha256, actual.Sha256, StringComparison.Ordinal))
        {
            throw new InvalidDataException("A local store backup file failed SHA-256 verification.");
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

    private sealed class BackupManifest
    {
        public int FormatVersion { get; set; }

        public BackupFile Database { get; set; } = null!;

        public List<BackupFile> Attachments { get; set; } = [];
    }

    private sealed class BackupFile
    {
        public string RelativePath { get; set; } = string.Empty;

        public long Length { get; set; }

        public string Sha256 { get; set; } = string.Empty;
    }
}
