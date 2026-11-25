using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.SyncService.Storage;

public interface ILocalStoreInitializer
{
    Task InitializeAsync(CancellationToken cancellationToken);
}

public interface ISyncServiceStore : ILocalStoreInitializer
{
    LocalStoreDbContext CreateContext(string? databasePath = null);

    Task<LocalStoreBackup> CreateBackupAsync(
        string? destinationDirectory,
        CancellationToken cancellationToken);

    Task RestoreBackupAsync(
        string backupDirectory,
        string destinationDatabasePath,
        string destinationAttachmentDirectory,
        CancellationToken cancellationToken);
}

public sealed class SyncServiceStore : ISyncServiceStore
{
    private readonly LocalStoreOptions _options;
    private readonly LocalStoreConnectionFactory _connectionFactory;

    public SyncServiceStore(LocalStoreOptions options)
    {
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _connectionFactory = LocalStoreConnectionFactory.CreateForSyncService(options);
    }

    public static LocalStoreOptions DefaultOptions()
    {
        string root = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
            "Alhayah",
            "SyncService");
        return new LocalStoreOptions
        {
            DatabasePath = Path.Combine(root, "Store", "device.db"),
            BackupDirectory = Path.Combine(root, "Backups"),
            AttachmentDirectory = Path.Combine(root, "Attachments")
        };
    }

    public async Task InitializeAsync(CancellationToken cancellationToken)
    {
        Directory.CreateDirectory(LocalStorePathGuard.EnsureLocalPath(Path.GetDirectoryName(_options.DatabasePath)
            ?? throw new InvalidOperationException("A database directory is required.")));
        Directory.CreateDirectory(LocalStorePathGuard.EnsureLocalPath(_options.BackupDirectory));
        Directory.CreateDirectory(LocalStorePathGuard.EnsureLocalPath(_options.AttachmentDirectory));
        await using LocalStoreDbContext context = CreateContext();
        await context.Database.MigrateAsync(cancellationToken).ConfigureAwait(false);
    }

    public LocalStoreDbContext CreateContext(string? databasePath = null) =>
        new(_connectionFactory.CreateOptions(databasePath));

    public Task<LocalStoreBackup> CreateBackupAsync(
        string? destinationDirectory,
        CancellationToken cancellationToken)
    {
        LocalStoreOptions backupOptions = new()
        {
            DatabasePath = _options.DatabasePath,
            BackupDirectory = destinationDirectory ?? _options.BackupDirectory,
            AttachmentDirectory = _options.AttachmentDirectory,
            DatabaseCredentialTarget = _options.DatabaseCredentialTarget,
            AttachmentCredentialTarget = _options.AttachmentCredentialTarget
        };
        return new LocalStoreBackupService(_connectionFactory, backupOptions).CreateBackupAsync(cancellationToken);
    }

    public Task RestoreBackupAsync(
        string backupDirectory,
        string destinationDatabasePath,
        string destinationAttachmentDirectory,
        CancellationToken cancellationToken) =>
        new LocalStoreBackupService(_connectionFactory, _options).RestoreBackupAsync(
            backupDirectory,
            destinationDatabasePath,
            destinationAttachmentDirectory,
            cancellationToken);
}
