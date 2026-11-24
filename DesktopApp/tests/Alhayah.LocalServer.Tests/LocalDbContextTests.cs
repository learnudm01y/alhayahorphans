using System.Globalization;
using Alhayah.LocalServer.Data;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class LocalDbContextTests
{
    [Fact]
    public async Task MigrateAsync_WithNewDatabase_CreatesOperationalSchema()
    {
        using LocalServerTestHarness harness = new();
        await using LocalDbContext context = harness.CreateContext();

        await context.Database.MigrateAsync();
        IEnumerable<string> migrations = await context.Database.GetAppliedMigrationsAsync();

        Assert.Contains("20261124000100_InitialLocalSchema", migrations);
        Assert.Equal(0, await context.Disbursements.CountAsync());
        Assert.Equal(0, await context.DeliveryEvidences.CountAsync());
    }

    [Fact]
    public async Task Disbursements_CreateReadUpdateAndSoftDelete_PreserveDomainState()
    {
        using LocalServerTestHarness harness = new();
        Guid disbursementId;

        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
            Disbursement disbursement = LocalServerTestHarness.CreateDisbursement(25.50m);
            disbursementId = disbursement.Id;
            context.Disbursements.Add(disbursement);
            await context.SaveChangesAsync();
        }

        await using (LocalDbContext context = harness.CreateContext())
        {
            Disbursement stored = await context.Disbursements.SingleAsync(item => item.Id == disbursementId);
            Assert.Equal(25.50m, stored.Amount);
            Assert.Equal("Requested", stored.Status);

            stored.Status = "Delivered";
            stored.DeliveredAtUtc = new DateTimeOffset(2026, 1, 3, 4, 5, 6, TimeSpan.Zero);
            stored.UpdatedAtUtc = stored.DeliveredAtUtc.Value;
            stored.RowVersion = 2;
            await context.SaveChangesAsync();

            stored.DeletedAtUtc = new DateTimeOffset(2026, 1, 4, 4, 5, 6, TimeSpan.Zero);
            stored.RowVersion = 3;
            await context.SaveChangesAsync();
        }

        await using (LocalDbContext context = harness.CreateContext())
        {
            Assert.False(await context.Disbursements.AnyAsync(item => item.Id == disbursementId));
            Disbursement deleted = await context.Disbursements
                .IgnoreQueryFilters()
                .SingleAsync(item => item.Id == disbursementId);
            Assert.Equal("Delivered", deleted.Status);
            Assert.Equal(3L, deleted.RowVersion);
            Assert.NotNull(deleted.DeletedAtUtc);
        }
    }

    [Fact]
    public async Task OpenConnectionAsync_WithOperationalDatabase_EnablesWal()
    {
        using LocalServerTestHarness harness = new();
        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        await using SqliteConnection connection = await harness.Connections.OpenConnectionAsync();
        await using SqliteCommand command = connection.CreateCommand();
        command.CommandText = "PRAGMA journal_mode;";

        string? journalMode = Convert.ToString(await command.ExecuteScalarAsync(), CultureInfo.InvariantCulture);

        Assert.Equal("wal", journalMode, ignoreCase: true);
    }

    [Fact]
    public async Task OpenConnectionAsync_WithOperationalDatabase_SetsBusyTimeout()
    {
        using LocalServerTestHarness harness = new();
        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        await using SqliteConnection connection = await harness.Connections.OpenConnectionAsync();
        await using SqliteCommand command = connection.CreateCommand();
        command.CommandText = "PRAGMA busy_timeout;";

        int busyTimeout = Convert.ToInt32(await command.ExecuteScalarAsync(), CultureInfo.InvariantCulture);

        Assert.Equal(5_000, busyTimeout);
    }

    [Fact]
    public async Task DatabaseFile_WithSqlCipher_DoesNotUsePlaintextSqliteHeader()
    {
        using LocalServerTestHarness harness = new();
        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

        byte[] header = (await File.ReadAllBytesAsync(harness.Storage.DatabasePath)).Take(16).ToArray();

        Assert.NotEqual("SQLite format 3\0"u8.ToArray(), header);
    }

    [Fact]
    public async Task OpenConnectionAsync_WithoutEncryptionKey_CannotReadEncryptedDatabase()
    {
        using LocalServerTestHarness harness = new();
        await using (LocalDbContext context = harness.CreateContext())
        {
            await context.Database.MigrateAsync();
        }

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
    public async Task OpenConnectionAsync_WithUncPath_RejectsDirectNetworkDatabaseAccess()
    {
        using LocalServerTestHarness harness = new();

        await Assert.ThrowsAsync<InvalidOperationException>(() =>
            harness.Connections.OpenConnectionAsync(@"\\server\share\alhayah-local.db"));
    }

    [Fact]
    public void TextArtifacts_WithSyntheticKey_DoNotContainEncryptionKey()
    {
        using LocalServerTestHarness harness = new();
        string projectDirectory = FindProjectDirectory();
        string desktopAppDirectory = Directory.GetParent(Directory.GetParent(projectDirectory)!.FullName)!.FullName;
        string localServerDirectory = Path.Combine(desktopAppDirectory, "src", "Alhayah.LocalServer");
        HashSet<string> extensions = new(StringComparer.OrdinalIgnoreCase)
        {
            ".cs", ".csproj", ".json", ".config", ".xml", ".md", ".sln", ".ps1", ".cmd", ".sh"
        };

        string[] textFiles = new[] { localServerDirectory, projectDirectory }
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

    private static string FindProjectDirectory()
    {
        DirectoryInfo? directory = new(AppContext.BaseDirectory);

        while (directory is not null)
        {
            if (File.Exists(Path.Combine(directory.FullName, "Alhayah.LocalServer.Tests.csproj")))
            {
                return directory.FullName;
            }

            directory = directory.Parent;
        }

        throw new DirectoryNotFoundException("The LocalServer test project directory was not found.");
    }
}
