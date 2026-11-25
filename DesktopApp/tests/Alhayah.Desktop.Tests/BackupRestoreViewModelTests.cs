using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.ViewModels;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class BackupRestoreViewModelTests
{
    [Fact]
    public async Task BackupAndRestoreCommands_WithFakeIpc_DisplayOnlyReturnedPathsWithoutNetworkChecks()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        string backupDestination = Path.Combine(fixture.SyntheticRootPath, "backup-target");
        string restoreSource = Path.Combine(fixture.SyntheticRootPath, "backup-source");
        string restoreDatabase = Path.Combine(fixture.SyntheticRootPath, "restore-target", "device.db");
        string restoreAttachments = Path.Combine(fixture.SyntheticRootPath, "restore-target", "attachments");
        viewModel.BackupDestinationDirectory = backupDestination;
        viewModel.RestoreBackupDirectory = restoreSource;
        viewModel.RestoreDestinationDatabasePath = restoreDatabase;
        viewModel.RestoreDestinationAttachmentDirectory = restoreAttachments;
        fixture.Host.Commands.Clear();

        await viewModel.CreateBackupCommand.ExecuteAsync(null);
        await viewModel.RestoreBackupCommand.ExecuteAsync(null);

        Assert.Equal([RepositoryCommands.Backup, RepositoryCommands.Restore], fixture.Host.Commands);
        Assert.Equal(backupDestination, Assert.Single(fixture.BackupRequests).DestinationDirectory);
        RestoreRequest restore = Assert.Single(fixture.RestoreRequests);
        Assert.Equal(restoreSource, restore.BackupDirectory);
        Assert.Equal(restoreDatabase, restore.DestinationDatabasePath);
        Assert.Equal(restoreAttachments, restore.DestinationAttachmentDirectory);
        Assert.Equal(fixture.SyntheticBackupDirectory, viewModel.BackupResultPath);
        Assert.Equal(restoreDatabase, viewModel.RestoreResultDatabasePath);
        Assert.Equal(restoreAttachments, viewModel.RestoreResultAttachmentDirectory);
        Assert.Contains("تم إنشاء", viewModel.BackupStatusMessage, StringComparison.Ordinal);
        Assert.Contains("اكتملت", viewModel.RestoreStatusMessage, StringComparison.Ordinal);
        Assert.DoesNotContain(SyncServiceCommands.CheckConnection, fixture.Host.Commands);
    }

    [Fact]
    public async Task BackupCommand_WhenServiceFails_ClearsResultAndDoesNotShowSuccess()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        fixture.BackupErrorCode = "backup_failed";

        await viewModel.CreateBackupCommand.ExecuteAsync(null);

        Assert.Equal(string.Empty, viewModel.BackupResultPath);
        Assert.Contains("تعذر", viewModel.BackupStatusMessage, StringComparison.Ordinal);
        Assert.DoesNotContain("تم إنشاء", viewModel.BackupStatusMessage, StringComparison.Ordinal);
    }

    [Fact]
    public async Task RestoreCommand_WhenServiceRejectsDestination_ClearsResultAndDoesNotShowSuccess()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        fixture.RestoreErrorCode = "destination_exists";
        viewModel.RestoreBackupDirectory = Path.Combine(fixture.SyntheticRootPath, "backup-source");
        viewModel.RestoreDestinationDatabasePath = Path.Combine(fixture.SyntheticRootPath, "restore-target", "device.db");
        viewModel.RestoreDestinationAttachmentDirectory = Path.Combine(fixture.SyntheticRootPath, "restore-target", "attachments");

        await viewModel.RestoreBackupCommand.ExecuteAsync(null);

        Assert.Equal(string.Empty, viewModel.RestoreResultDatabasePath);
        Assert.Equal(string.Empty, viewModel.RestoreResultAttachmentDirectory);
        Assert.Contains("تعذرت", viewModel.RestoreStatusMessage, StringComparison.Ordinal);
        Assert.DoesNotContain("اكتملت", viewModel.RestoreStatusMessage, StringComparison.Ordinal);
    }

    [Fact]
    public async Task BackupAndRestoreCommands_WithoutPermissions_DoNotCallIpc()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync(isAdministrator: false);
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        fixture.Host.Commands.Clear();

        await viewModel.CreateBackupCommand.ExecuteAsync(null);
        await viewModel.RestoreBackupCommand.ExecuteAsync(null);

        Assert.False(viewModel.CanBackup);
        Assert.False(viewModel.CanRestore);
        Assert.Empty(fixture.BackupRequests);
        Assert.Empty(fixture.RestoreRequests);
        Assert.Empty(fixture.Host.Commands);
        Assert.Contains("غير مصرح", viewModel.BackupStatusMessage, StringComparison.Ordinal);
        Assert.Contains("غير مصرح", viewModel.RestoreStatusMessage, StringComparison.Ordinal);
    }
}
