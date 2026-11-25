using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Alhayah.Desktop.Utilities;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class BackupRestoreClientTests
{
    [Fact]
    public async Task BackupClient_WithLocalDestination_ForwardsContractAndLogsOnlyCompletionAfterResponse()
    {
        using SyntheticTempDirectory temp = new();
        string destination = temp.GetPath("backup-output");
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new();
        BackupRequest? sent = null;
        host.Handler = (request, _) =>
        {
            sent = request.Payload.Deserialize<BackupRequest>();
            string directory = sent!.DestinationDirectory!;
            return Task.FromResult(host.Ok(request, new BackupResponse
            {
                DirectoryPath = directory,
                DatabasePath = Path.Combine(directory, "device.db"),
                ManifestPath = Path.Combine(directory, "manifest.json")
            }));
        };
        BackupClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));

        BackupResponse response = await client.CreateBackupAsync(destination);

        Assert.NotNull(sent);
        Assert.Equal(Path.GetFullPath(destination), sent.DestinationDirectory);
        Assert.Equal(Path.Combine(destination, "manifest.json"), response.ManifestPath);
        Assert.Equal(
            [nameof(AppLogEvent.BackupStarted), nameof(AppLogEvent.BackupSucceeded)],
            ReadLoggedEvents(logPath));
    }

    [Fact]
    public async Task BackupClient_WithoutDestination_UsesSharedServiceDefault()
    {
        using SyntheticTempDirectory temp = new();
        FakeSyncServiceHost host = new();
        BackupRequest? sent = null;
        host.Handler = (request, _) =>
        {
            sent = request.Payload.Deserialize<BackupRequest>();
            return Task.FromResult(host.Ok(request, new BackupResponse
            {
                DirectoryPath = temp.GetPath("service-default-backup"),
                DatabasePath = temp.GetPath("service-default-backup", "device.db"),
                ManifestPath = temp.GetPath("service-default-backup", "manifest.json")
            }));
        };
        BackupClient client = new(IpcTestData.CreateClient(host), new AppLogger(temp.GetPath("desktop.log")));

        await client.CreateBackupAsync("  ");

        Assert.NotNull(sent);
        Assert.Null(sent.DestinationDirectory);
    }

    [Theory]
    [InlineData("relative-backup")]
    [InlineData(@"\\server\share\backup")]
    public async Task BackupClient_WithUnsafeDestination_RejectsBeforeIpc(string destination)
    {
        using SyntheticTempDirectory temp = new();
        FakeSyncServiceHost host = new();
        string logPath = temp.GetPath("desktop.log");
        BackupClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));

        await Assert.ThrowsAsync<ArgumentException>(() => client.CreateBackupAsync(destination));

        Assert.Empty(host.Commands);
        Assert.False(File.Exists(logPath));
    }

    [Fact]
    public async Task BackupClient_WhenCancelled_PropagatesCancellationWithoutSuccessLog()
    {
        using SyntheticTempDirectory temp = new();
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new();
        BackupClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));
        using CancellationTokenSource cancellation = new();
        await cancellation.CancelAsync();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() =>
            client.CreateBackupAsync(temp.GetPath("backup"), cancellation.Token));

        Assert.Empty(host.Commands);
        Assert.Equal(
            [nameof(AppLogEvent.BackupStarted), nameof(AppLogEvent.BackupCancelled)],
            ReadLoggedEvents(logPath));
    }

    [Fact]
    public async Task BackupClient_WhenIpcTimesOut_PropagatesTimeoutWithoutSuccessLog()
    {
        using SyntheticTempDirectory temp = new();
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new() { SuppressResponses = true };
        BackupClient client = new(
            IpcTestData.CreateClient(host, requestTimeout: TimeSpan.FromMilliseconds(20)),
            new AppLogger(logPath));

        await Assert.ThrowsAsync<TimeoutException>(() => client.CreateBackupAsync(null));

        Assert.Equal(
            [nameof(AppLogEvent.BackupStarted), nameof(AppLogEvent.BackupFailed)],
            ReadLoggedEvents(logPath));
    }

    [Fact]
    public async Task BackupClient_WhenServiceRejectsRequest_DoesNotReportSuccess()
    {
        using SyntheticTempDirectory temp = new();
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new();
        host.Handler = (request, _) => Task.FromResult(host.Error(request, "backup_failed"));
        BackupClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));

        SyncServiceException exception = await Assert.ThrowsAsync<SyncServiceException>(() =>
            client.CreateBackupAsync(temp.GetPath("backup")));

        Assert.Equal("backup_failed", exception.ErrorCode);
        Assert.Equal(
            [nameof(AppLogEvent.BackupStarted), nameof(AppLogEvent.BackupFailed)],
            ReadLoggedEvents(logPath));
    }

    [Fact]
    public async Task RestoreClient_WithNewLocalDestinations_ForwardsEveryContractPath()
    {
        using SyntheticTempDirectory temp = new();
        string backupDirectory = temp.GetPath("backup-source");
        string databasePath = temp.GetPath("restore-output", "device.db");
        string attachmentDirectory = temp.GetPath("restore-output", "attachments");
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new();
        RestoreRequest? sent = null;
        host.Handler = (request, _) =>
        {
            sent = request.Payload.Deserialize<RestoreRequest>();
            return Task.FromResult(host.Ok(request, new RestoreResponse
            {
                DestinationDatabasePath = sent!.DestinationDatabasePath,
                DestinationAttachmentDirectory = sent.DestinationAttachmentDirectory
            }));
        };
        RestoreClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));

        RestoreResponse response = await client.RestoreBackupAsync(
            backupDirectory,
            databasePath,
            attachmentDirectory);

        Assert.NotNull(sent);
        Assert.Equal(Path.GetFullPath(backupDirectory), sent.BackupDirectory);
        Assert.Equal(Path.GetFullPath(databasePath), sent.DestinationDatabasePath);
        Assert.Equal(Path.GetFullPath(attachmentDirectory), sent.DestinationAttachmentDirectory);
        Assert.Equal(Path.GetFullPath(databasePath), response.DestinationDatabasePath);
        Assert.Equal(Path.GetFullPath(attachmentDirectory), response.DestinationAttachmentDirectory);
        Assert.Equal(
            [nameof(AppLogEvent.RestoreStarted), nameof(AppLogEvent.RestoreSucceeded)],
            ReadLoggedEvents(logPath));
    }

    [Theory]
    [InlineData("relative-backup")]
    [InlineData(@"\\server\share\backup")]
    public async Task RestoreClient_WithUnsafeSource_RejectsBeforeIpc(string backupDirectory)
    {
        using SyntheticTempDirectory temp = new();
        FakeSyncServiceHost host = new();
        string logPath = temp.GetPath("desktop.log");
        RestoreClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));

        await Assert.ThrowsAsync<ArgumentException>(() => client.RestoreBackupAsync(
            backupDirectory,
            temp.GetPath("restore", "device.db"),
            temp.GetPath("restore", "attachments")));

        Assert.Empty(host.Commands);
        Assert.False(File.Exists(logPath));
    }

    [Theory]
    [InlineData("relative-restore-device.db")]
    [InlineData(@"\\server\share\restore\device.db")]
    public async Task RestoreClient_WithUnsafeDestination_RejectsBeforeIpc(string destinationDatabasePath)
    {
        using SyntheticTempDirectory temp = new();
        FakeSyncServiceHost host = new();
        string logPath = temp.GetPath("desktop.log");
        RestoreClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));

        await Assert.ThrowsAsync<ArgumentException>(() => client.RestoreBackupAsync(
            temp.GetPath("backup"),
            destinationDatabasePath,
            temp.GetPath("restore", "attachments")));

        Assert.Empty(host.Commands);
        Assert.False(File.Exists(logPath));
    }

    [Fact]
    public async Task RestoreClient_WhenCancelled_PropagatesCancellationWithoutSuccessLog()
    {
        using SyntheticTempDirectory temp = new();
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new();
        RestoreClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));
        using CancellationTokenSource cancellation = new();
        await cancellation.CancelAsync();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() => client.RestoreBackupAsync(
            temp.GetPath("backup"),
            temp.GetPath("restore", "device.db"),
            temp.GetPath("restore", "attachments"),
            cancellation.Token));

        Assert.Empty(host.Commands);
        Assert.Equal(
            [nameof(AppLogEvent.RestoreStarted), nameof(AppLogEvent.RestoreCancelled)],
            ReadLoggedEvents(logPath));
    }

    [Fact]
    public async Task RestoreClient_WhenIpcTimesOut_PropagatesTimeoutWithoutSuccessLog()
    {
        using SyntheticTempDirectory temp = new();
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new() { SuppressResponses = true };
        RestoreClient client = new(
            IpcTestData.CreateClient(host, requestTimeout: TimeSpan.FromMilliseconds(20)),
            new AppLogger(logPath));

        await Assert.ThrowsAsync<TimeoutException>(() => client.RestoreBackupAsync(
            temp.GetPath("backup"),
            temp.GetPath("restore", "device.db"),
            temp.GetPath("restore", "attachments")));

        Assert.Equal(
            [nameof(AppLogEvent.RestoreStarted), nameof(AppLogEvent.RestoreFailed)],
            ReadLoggedEvents(logPath));
    }

    [Theory]
    [InlineData("live_store_overwrite")]
    [InlineData("destination_exists")]
    public async Task RestoreClient_WhenSharedServiceRejectsOverwrite_DoesNotReportSuccess(string errorCode)
    {
        using SyntheticTempDirectory temp = new();
        string logPath = temp.GetPath("desktop.log");
        FakeSyncServiceHost host = new();
        host.Handler = (request, _) => Task.FromResult(host.Error(request, errorCode));
        RestoreClient client = new(IpcTestData.CreateClient(host), new AppLogger(logPath));

        SyncServiceException exception = await Assert.ThrowsAsync<SyncServiceException>(() =>
            client.RestoreBackupAsync(
                temp.GetPath("backup"),
                temp.GetPath("restore", "device.db"),
                temp.GetPath("restore", "attachments")));

        Assert.Equal(errorCode, exception.ErrorCode);
        Assert.Equal(
            [nameof(AppLogEvent.RestoreStarted), nameof(AppLogEvent.RestoreFailed)],
            ReadLoggedEvents(logPath));
        Assert.DoesNotContain(nameof(AppLogEvent.RestoreSucceeded), File.ReadAllText(logPath), StringComparison.Ordinal);
    }

    private static string[] ReadLoggedEvents(string logPath) => File.ReadAllLines(logPath)
        .Select(line => JsonSerializer.Deserialize<JsonElement>(line).GetProperty("eventName").GetString()!)
        .ToArray();
}

internal sealed class SyntheticTempDirectory : IDisposable
{
    public SyntheticTempDirectory()
    {
        RootPath = Path.Combine(Path.GetTempPath(), $"alhayah-desktop-synthetic-{Guid.NewGuid():N}");
        Directory.CreateDirectory(RootPath);
    }

    public string RootPath { get; }

    public string GetPath(params string[] segments) =>
        Path.Combine(new[] { RootPath }.Concat(segments).ToArray());

    public void Dispose() => Directory.Delete(RootPath, recursive: true);
}
