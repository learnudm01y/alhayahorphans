using Alhayah.LocalServer.Data;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalServer.Services;

public sealed class LocalMigrationService(
    LocalDbContext dbContext,
    LocalBackupService backupService,
    LocalStorageOptions storage)
{
    private readonly LocalDbContext _dbContext = dbContext ?? throw new ArgumentNullException(nameof(dbContext));
    private readonly LocalBackupService _backupService = backupService ?? throw new ArgumentNullException(nameof(backupService));
    private readonly LocalStorageOptions _storage = storage ?? throw new ArgumentNullException(nameof(storage));

    public async Task MigrateAsync(CancellationToken cancellationToken = default)
    {
        if (File.Exists(_storage.DatabasePath))
        {
            await _backupService.CreateBackupAsync(cancellationToken).ConfigureAwait(false);
        }

        await _dbContext.Database.MigrateAsync(cancellationToken).ConfigureAwait(false);
    }
}
