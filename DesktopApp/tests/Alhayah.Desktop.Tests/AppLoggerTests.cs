using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Alhayah.Desktop.Utilities;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class AppLoggerTests
{
    [Fact]
    public async Task BackupAndRestoreLogs_WithSyntheticSecretsPiiAndFieldValuesInPaths_ContainOnlyFixedEvents()
    {
        using SyntheticTempDirectory temp = new();
        const string syntheticSecret = "synthetic-password-7f3c9a";
        const string syntheticPii = "synthetic-person-name-123456789";
        const string syntheticFieldValue = "synthetic-field-value-confidential";
        string sensitivePath = temp.GetPath(syntheticSecret, syntheticPii, syntheticFieldValue);
        string logPath = temp.GetPath("desktop.log");
        AppLogger logger = new(logPath);
        FakeSyncServiceHost host = new();
        host.Handler = (request, _) => Task.FromResult(request.Command switch
        {
            RepositoryCommands.Backup => host.Ok(request, new BackupResponse
            {
                DirectoryPath = sensitivePath,
                DatabasePath = Path.Combine(sensitivePath, "device.db"),
                ManifestPath = Path.Combine(sensitivePath, "manifest.json")
            }),
            RepositoryCommands.Restore => host.Ok(request, new RestoreResponse
            {
                DestinationDatabasePath = Path.Combine(sensitivePath, "restored", "device.db"),
                DestinationAttachmentDirectory = Path.Combine(sensitivePath, "restored", "attachments")
            }),
            _ => host.Error(request, "unexpected_command")
        });
        SyncServiceClient ipcClient = IpcTestData.CreateClient(host);

        await new BackupClient(ipcClient, logger).CreateBackupAsync(sensitivePath);
        await new RestoreClient(ipcClient, logger).RestoreBackupAsync(
            sensitivePath,
            Path.Combine(sensitivePath, "restored", "device.db"),
            Path.Combine(sensitivePath, "restored", "attachments"));

        string log = await File.ReadAllTextAsync(logPath);
        Assert.DoesNotContain(syntheticSecret, log, StringComparison.Ordinal);
        Assert.DoesNotContain(syntheticPii, log, StringComparison.Ordinal);
        Assert.DoesNotContain(syntheticFieldValue, log, StringComparison.Ordinal);
        Assert.DoesNotContain(sensitivePath, log, StringComparison.Ordinal);
        string[] lines = await File.ReadAllLinesAsync(logPath);
        Assert.Equal(4, lines.Length);
        Assert.All(lines, line =>
        {
            JsonObjectAssert.HasOnlyProperties(line, "timestampUtc", "eventName");
        });
    }

    [Fact]
    public void TryLog_WhenLogCannotBeWritten_ReturnsFalseInsteadOfClaimingSuccess()
    {
        using SyntheticTempDirectory temp = new();
        string directoryUsedAsFile = temp.GetPath("not-a-log-file");
        Directory.CreateDirectory(directoryUsedAsFile);
        AppLogger logger = new(directoryUsedAsFile);

        bool written = logger.TryLog(AppLogEvent.BackupSucceeded);

        Assert.False(written);
    }
}

internal static class JsonObjectAssert
{
    public static void HasOnlyProperties(string json, params string[] expectedNames)
    {
        JsonElement value = JsonSerializer.Deserialize<JsonElement>(json);
        Assert.Equal(JsonValueKind.Object, value.ValueKind);
        string[] actualNames = value.EnumerateObject().Select(property => property.Name).Order().ToArray();
        Assert.Equal(expectedNames.Order(), actualNames);
    }
}
