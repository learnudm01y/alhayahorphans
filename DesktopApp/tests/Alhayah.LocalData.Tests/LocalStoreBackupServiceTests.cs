using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalData.Tests;

public sealed class LocalStoreBackupServiceTests
{
    [Fact]
    public async Task BackupAndRestore_WithOpenStoreAndEncryptedAttachment_RestoresRowsOutboxAndContent()
    {
        using LocalStoreTestHarness harness = new();
        Guid clientUuid = Guid.NewGuid();
        byte[] plaintext = "Synthetic backup attachment; no real document."u8.ToArray();
        EncryptedAttachmentMetadata attachment;

        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            await new UnitOfWork(context).CreateAsync(
                LocalStoreTestHarness.CreateEnvelope(Guid.NewGuid(), clientUuid, SyncOperationType.Create),
                "{\"display_name\":\"Synthetic Backup\"}",
                "Synthetic Backup",
                LocalStoreTestHarness.Audit);
        }

        await using (MemoryStream input = new(plaintext))
        {
            attachment = await harness.CreateAttachmentStore().SaveAsync(input);
        }

        LocalStoreBackupService backupService = new(harness.Connections, harness.Storage);
        LocalStoreBackup backup = await backupService.CreateBackupAsync();
        string restoredDatabasePath = Path.Combine(harness.RootPath, "restored", "alhayah-device.db");
        string restoredAttachmentDirectory = Path.Combine(harness.RootPath, "restored", "attachments");
        await backupService.RestoreBackupAsync(
            backup.DirectoryPath,
            restoredDatabasePath,
            restoredAttachmentDirectory);

        await using (LocalStoreDbContext restored = harness.CreateContext(restoredDatabasePath))
        {
            Assert.Equal(1, await restored.CachedEntities.CountAsync());
            Assert.Equal(1, await restored.OutboxOperations.CountAsync());
            CachedEntity row = await restored.CachedEntities.SingleAsync();
            Assert.Equal(clientUuid, row.ClientUuid);
            Assert.Contains("Synthetic Backup", row.PayloadJson, StringComparison.Ordinal);
        }

        LocalStoreOptions restoredStorage = new()
        {
            DatabasePath = restoredDatabasePath,
            BackupDirectory = Path.Combine(harness.RootPath, "restored-backups"),
            AttachmentDirectory = restoredAttachmentDirectory
        };
        await using Stream decrypted = await harness.CreateAttachmentStore(restoredStorage)
            .OpenReadAsync(attachment.StorageName);
        using MemoryStream output = new();
        await decrypted.CopyToAsync(output);
        Assert.Equal(plaintext, output.ToArray());
    }

    [Fact]
    public async Task RestoreBackupAsync_OverLiveDatabaseOrAttachments_IsRejected()
    {
        using LocalStoreTestHarness harness = new();
        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        LocalStoreBackupService backupService = new(harness.Connections, harness.Storage);
        LocalStoreBackup backup = await backupService.CreateBackupAsync();

        await Assert.ThrowsAsync<InvalidOperationException>(() => backupService.RestoreBackupAsync(
            backup.DirectoryPath,
            harness.Storage.DatabasePath,
            Path.Combine(harness.RootPath, "other-attachments")));
        await Assert.ThrowsAsync<InvalidOperationException>(() => backupService.RestoreBackupAsync(
            backup.DirectoryPath,
            Path.Combine(harness.RootPath, "other.db"),
            harness.Storage.AttachmentDirectory));
    }

    [Fact]
    public async Task RestoreBackupAsync_WithTamperedAttachmentHash_DoesNotProduceDestination()
    {
        using LocalStoreTestHarness harness = new();
        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        await using (MemoryStream input = new("Synthetic tamper detection"u8.ToArray()))
        {
            await harness.CreateAttachmentStore().SaveAsync(input);
        }

        LocalStoreBackupService backupService = new(harness.Connections, harness.Storage);
        LocalStoreBackup backup = await backupService.CreateBackupAsync();
        string attachmentPath = Assert.Single(Directory.GetFiles(Path.Combine(backup.DirectoryPath, "attachments")));
        byte[] encryptedPayload = await File.ReadAllBytesAsync(attachmentPath);
        encryptedPayload[^1] ^= 0xFF;
        await File.WriteAllBytesAsync(attachmentPath, encryptedPayload);
        string restoredDatabasePath = Path.Combine(harness.RootPath, "tampered-restore", "alhayah-device.db");
        string restoredAttachmentDirectory = Path.Combine(harness.RootPath, "tampered-restore", "attachments");

        await Assert.ThrowsAsync<InvalidDataException>(() => backupService.RestoreBackupAsync(
            backup.DirectoryPath,
            restoredDatabasePath,
            restoredAttachmentDirectory));

        Assert.False(File.Exists(restoredDatabasePath));
        Assert.False(Directory.Exists(restoredAttachmentDirectory));
    }
}
