using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class LocalBackupServiceTests
{
    [Fact]
    public async Task CreateBackupAsync_WithOpenOperationalData_RestoresMatchingRowsAndTotals()
    {
        using LocalServerTestHarness harness = new();
        Disbursement first = LocalServerTestHarness.CreateDisbursement(10.25m);
        Disbursement second = LocalServerTestHarness.CreateDisbursement(4.75m);

        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            context.Disbursements.AddRange(first, second);
            await context.SaveChangesAsync();
        }

        LocalBackupService backupService = new(harness.Connections, harness.Storage);
        string backupPath = await backupService.CreateBackupAsync();
        string restoredPath = Path.Combine(harness.RootPath, "restored", "alhayah-local.db");
        await backupService.RestoreBackupAsync(backupPath, restoredPath);

        await using LocalDbContext restored = harness.CreateContext(restoredPath);
        Disbursement[] rows = await restored.Disbursements
            .OrderBy(item => item.TransactionNumber)
            .ToArrayAsync();

        Assert.Equal(2, rows.Length);
        Assert.Equal(15.00m, rows.Sum(item => item.Amount ?? 0m));
        Assert.Equal(
            new[] { first.Id, second.Id }.OrderBy(id => id),
            rows.Select(item => item.Id).OrderBy(id => id));
    }

    [Fact]
    public async Task MigrateAsync_WhenMigrationFailsAfterBackup_PreservesRestorablePreMigrationData()
    {
        using LocalServerTestHarness harness = new();
        Disbursement existing = LocalServerTestHarness.CreateDisbursement(12.00m);
        LocalDbContext context = harness.CreateContext();
        await using (context.ConfigureAwait(false))
        {
            await context.Database.EnsureCreatedAsync();
            context.Disbursements.Add(existing);
            await context.SaveChangesAsync();

            LocalBackupService backupService = new(harness.Connections, harness.Storage);
            LocalMigrationService migrationService = new(context, backupService, harness.Storage);

            await Assert.ThrowsAsync<SqliteException>(() => migrationService.MigrateAsync());

            string backupPath = Assert.Single(Directory.GetFiles(harness.Storage.BackupDirectory, "*.db"));
            string restoredPath = Path.Combine(harness.RootPath, "restored-before-migration", "alhayah-local.db");
            await backupService.RestoreBackupAsync(backupPath, restoredPath);
        }

        await using LocalDbContext restored = harness.CreateContext(restoredPath);
        Disbursement restoredRow = await restored.Disbursements.SingleAsync();
        Assert.Equal(12.00m, restoredRow.Amount);
        Assert.Equal(existing.Id, restoredRow.Id);
    }
}
