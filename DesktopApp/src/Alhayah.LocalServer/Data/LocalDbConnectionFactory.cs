using System.Data.Common;
using System.Globalization;
using System.Security.Cryptography;
using Alhayah.LocalServer.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace Alhayah.LocalServer.Data;

public sealed class LocalDbConnectionFactory(
    LocalStorageOptions storage,
    IDatabaseKeyProvider keyProvider)
{
    private readonly LocalStorageOptions _storage = storage ?? throw new ArgumentNullException(nameof(storage));
    private readonly IDatabaseKeyProvider _keyProvider = keyProvider ?? throw new ArgumentNullException(nameof(keyProvider));

    public DbContextOptions<LocalDbContext> CreateOptions(string? databasePath = null)
    {
        DbContextOptionsBuilder<LocalDbContext> builder = new();
        Configure(builder, databasePath);
        return builder.Options;
    }

    public void Configure(DbContextOptionsBuilder<LocalDbContext> builder, string? databasePath = null)
    {
        ArgumentNullException.ThrowIfNull(builder);
        string path = PreparePath(databasePath ?? _storage.DatabasePath);

        builder
            .UseSqlite(BuildConnectionString(path))
            .AddInterceptors(new SqlitePragmaInterceptor(_keyProvider, _storage.BusyTimeoutMilliseconds));
    }

    public async Task<SqliteConnection> OpenConnectionAsync(
        string? databasePath = null,
        CancellationToken cancellationToken = default,
        bool enableWal = true)
    {
        string path = PreparePath(databasePath ?? _storage.DatabasePath);
        SqliteConnection connection = new(BuildConnectionString(path));

        try
        {
            await connection.OpenAsync(cancellationToken).ConfigureAwait(false);
            await SqlitePragmaInterceptor.ConfigureAsync(
                connection,
                _keyProvider,
                _storage.BusyTimeoutMilliseconds,
                cancellationToken,
                enableWal).ConfigureAwait(false);
            return connection;
        }
        catch
        {
            await connection.DisposeAsync().ConfigureAwait(false);
            throw;
        }
    }

    private static string PreparePath(string path)
    {
        string localPath = LocalPathGuard.EnsureLocalPath(path);
        string directory = Path.GetDirectoryName(localPath)
            ?? throw new InvalidOperationException("The database path must include a local directory.");
        Directory.CreateDirectory(directory);
        return localPath;
    }

    private static string BuildConnectionString(string path) =>
        new SqliteConnectionStringBuilder
        {
            DataSource = path,
            Mode = SqliteOpenMode.ReadWriteCreate,
            Cache = SqliteCacheMode.Private,
            Pooling = false,
            ForeignKeys = true
        }.ToString();
}

internal sealed class SqlitePragmaInterceptor(
    IDatabaseKeyProvider keyProvider,
    int busyTimeoutMilliseconds) : DbConnectionInterceptor
{
    private readonly IDatabaseKeyProvider _keyProvider = keyProvider;
    private readonly int _busyTimeoutMilliseconds = busyTimeoutMilliseconds;

    public override void ConnectionOpened(DbConnection connection, ConnectionEndEventData eventData) =>
        throw new NotSupportedException("Open encrypted LocalServer connections asynchronously.");

    public override async Task ConnectionOpenedAsync(
        DbConnection connection,
        ConnectionEndEventData eventData,
        CancellationToken cancellationToken = default)
    {
        if (connection is not SqliteConnection sqliteConnection)
        {
            throw new InvalidOperationException("LocalServer supports SQLite connections only.");
        }

        await ConfigureAsync(
            sqliteConnection,
            _keyProvider,
            _busyTimeoutMilliseconds,
            cancellationToken).ConfigureAwait(false);
    }

    internal static async Task ConfigureAsync(
        SqliteConnection connection,
        IDatabaseKeyProvider keyProvider,
        int busyTimeoutMilliseconds,
        CancellationToken cancellationToken,
        bool enableWal = true)
    {
        if (busyTimeoutMilliseconds <= 0)
        {
            throw new InvalidOperationException("SQLite busy_timeout must be greater than zero.");
        }

        byte[] key = keyProvider.GetKey();
        if (key.Length != 32)
        {
            CryptographicOperations.ZeroMemory(key);
            throw new CryptographicException("SQLCipher requires a 32-byte encryption key.");
        }

        try
        {
            await using (SqliteCommand keyCommand = connection.CreateCommand())
            {
                try
                {
                    keyCommand.CommandText = $"PRAGMA key = \"x'{Convert.ToHexString(key)}'\";";
                    await keyCommand.ExecuteNonQueryAsync(cancellationToken).ConfigureAwait(false);
                }
                finally
                {
                    keyCommand.CommandText = string.Empty;
                }
            }
        }
        finally
        {
            CryptographicOperations.ZeroMemory(key);
        }

        await using SqliteCommand pragmaCommand = connection.CreateCommand();
        if (enableWal)
        {
            pragmaCommand.CommandText = "PRAGMA journal_mode = WAL;";
            string? journalMode = Convert.ToString(
                await pragmaCommand.ExecuteScalarAsync(cancellationToken).ConfigureAwait(false),
                CultureInfo.InvariantCulture);
            if (!string.Equals(journalMode, "wal", StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidOperationException("The local operational database could not enable WAL mode.");
            }
        }

        pragmaCommand.CommandText = string.Create(
            CultureInfo.InvariantCulture,
            $"PRAGMA busy_timeout = {busyTimeoutMilliseconds};");
        await pragmaCommand.ExecuteNonQueryAsync(cancellationToken).ConfigureAwait(false);

        pragmaCommand.CommandText = "PRAGMA foreign_keys = ON;";
        await pragmaCommand.ExecuteNonQueryAsync(cancellationToken).ConfigureAwait(false);

        pragmaCommand.CommandText = "PRAGMA synchronous = NORMAL;";
        await pragmaCommand.ExecuteNonQueryAsync(cancellationToken).ConfigureAwait(false);
    }
}
