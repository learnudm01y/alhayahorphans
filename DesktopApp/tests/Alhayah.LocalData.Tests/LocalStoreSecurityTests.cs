using System.Globalization;
using Alhayah.LocalData.Data;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalData.Tests;

public sealed class LocalStoreSecurityTests
{
    [Fact]
    public async Task MigrateAsync_WithNewDatabase_CreatesEveryDeviceLocalTable()
    {
        using LocalStoreTestHarness harness = new();
        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        await using SqliteConnection connection = await harness.Connections.OpenConnectionAsync();
        await using SqliteCommand command = connection.CreateCommand();
        command.CommandText = "SELECT name FROM sqlite_master WHERE type = 'table';";
        HashSet<string> tables = new(StringComparer.Ordinal);
        await using var reader = await command.ExecuteReaderAsync();
        while (await reader.ReadAsync())
        {
            tables.Add(reader.GetString(0));
        }

        Assert.Subset(new HashSet<string>
        {
            "cached_entities",
            "outbox_operations",
            "local_change_log",
            "sync_conflicts",
            "sync_checkpoints",
            "client_identity_map",
            "local_users",
            "local_sessions",
            "audit_entries"
        }, tables);
    }

    [Fact]
    public async Task OpenConnectionAsync_WithDeviceStore_EnablesRequiredSqlitePragmas()
    {
        using LocalStoreTestHarness harness = new();
        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        await using SqliteConnection connection = await harness.Connections.OpenConnectionAsync();
        Assert.Equal("wal", await ExecuteScalarAsync(connection, "PRAGMA journal_mode;"), ignoreCase: true);
        Assert.Equal("5000", await ExecuteScalarAsync(connection, "PRAGMA busy_timeout;"));
        Assert.Equal("1", await ExecuteScalarAsync(connection, "PRAGMA foreign_keys;"));
    }

    [Fact]
    public async Task DatabaseFile_WithSqlCipher_IsNotPlaintextAndNeedsKey()
    {
        using LocalStoreTestHarness harness = new();
        await using (LocalStoreDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        byte[] header = (await File.ReadAllBytesAsync(harness.Storage.DatabasePath)).Take(16).ToArray();
        Assert.NotEqual("SQLite format 3\0"u8.ToArray(), header);

        string connectionString = new SqliteConnectionStringBuilder
        {
            DataSource = harness.Storage.DatabasePath,
            Mode = SqliteOpenMode.ReadOnly,
            Pooling = false
        }.ToString();
        await using SqliteConnection connection = new(connectionString);
        await connection.OpenAsync();
        await using SqliteCommand command = connection.CreateCommand();
        command.CommandText = "SELECT COUNT(*) FROM sqlite_master;";

        await Assert.ThrowsAsync<SqliteException>(async () => await command.ExecuteScalarAsync());
    }

    [Fact]
    public async Task OpenConnectionAsync_WithUncPath_RejectsNetworkStorage()
    {
        using LocalStoreTestHarness harness = new();

        await Assert.ThrowsAsync<InvalidOperationException>(() =>
            harness.Connections.OpenConnectionAsync(@"\\server\share\alhayah-device.db"));
    }

    [Fact]
    public void CreateForTesting_WithDesktopProcess_RejectsNonSyncServiceWriter()
    {
        using LocalStoreTestHarness harness = new();

        Assert.Throws<UnauthorizedAccessException>(() => LocalStoreConnectionFactory.CreateForTesting(
            harness.Storage,
            harness.KeyProvider,
            "Alhayah.Desktop"));
    }

    [Fact]
    public void TextArtifacts_WithSyntheticKey_DoNotContainEncryptionKey()
    {
        using LocalStoreTestHarness harness = new();
        string projectDirectory = FindProjectDirectory();
        string desktopAppDirectory = Directory.GetParent(Directory.GetParent(projectDirectory)!.FullName)!.FullName;
        string localDataDirectory = Path.Combine(desktopAppDirectory, "src", "Alhayah.LocalData");
        HashSet<string> extensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".cs", ".csproj", ".json", ".config", ".xml", ".md", ".sln", ".ps1", ".cmd", ".sh"
        };

        string[] textFiles = new[] { localDataDirectory, projectDirectory }
            .Where(Directory.Exists)
            .SelectMany(path => Directory.EnumerateFiles(path, "*", SearchOption.AllDirectories))
            .Where(path => !path.Contains($"{Path.DirectorySeparatorChar}bin{Path.DirectorySeparatorChar}", StringComparison.OrdinalIgnoreCase))
            .Where(path => !path.Contains($"{Path.DirectorySeparatorChar}obj{Path.DirectorySeparatorChar}", StringComparison.OrdinalIgnoreCase))
            .Where(path => extensions.Contains(Path.GetExtension(path)))
            .Append(Path.Combine(desktopAppDirectory, "DesktopApp.sln"))
            .ToArray();

        string[] filesContainingKey = textFiles
            .Where(path => File.ReadAllText(path).Contains(harness.SyntheticKeyHex, StringComparison.Ordinal))
            .ToArray();

        Assert.Empty(filesContainingKey);
    }

    private static async Task<string> ExecuteScalarAsync(SqliteConnection connection, string pragma)
    {
        await using SqliteCommand command = connection.CreateCommand();
        command.CommandText = pragma;
        return Convert.ToString(await command.ExecuteScalarAsync(), CultureInfo.InvariantCulture) ?? string.Empty;
    }

    private static string FindProjectDirectory()
    {
        DirectoryInfo? directory = new(AppContext.BaseDirectory);
        while (directory is not null)
        {
            if (File.Exists(Path.Combine(directory.FullName, "Alhayah.LocalData.Tests.csproj")))
            {
                return directory.FullName;
            }

            directory = directory.Parent;
        }

        throw new DirectoryNotFoundException("The LocalData test project directory was not found.");
    }
}
