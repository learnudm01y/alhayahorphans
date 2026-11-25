using System.Data.Common;
using System.Globalization;
using System.Security.Cryptography;
using Alhayah.LocalData.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace Alhayah.LocalData.Data;

public sealed class LocalStoreConnectionFactory
{
    private readonly LocalStoreOptions _storage;
    private readonly ILocalStoreKeyProvider _keyProvider;
    private readonly string _processName;

    private LocalStoreConnectionFactory(
        LocalStoreOptions storage,
        ILocalStoreKeyProvider keyProvider,
        string processName)
    {
        _storage = storage ?? throw new ArgumentNullException(nameof(storage));
        _keyProvider = keyProvider ?? throw new ArgumentNullException(nameof(keyProvider));
        _processName = processName;
        EnsureSyncServiceProcess(processName);
    }

    public static LocalStoreConnectionFactory CreateForSyncService(LocalStoreOptions storage)
    {
        ArgumentNullException.ThrowIfNull(storage);
        return new LocalStoreConnectionFactory(
            storage,
            new WindowsCredentialStoreKeyProvider(storage.DatabaseCredentialTarget),
            Environment.ProcessPath ?? string.Empty);
    }

    internal static LocalStoreConnectionFactory CreateForTesting(
        LocalStoreOptions storage,
        ILocalStoreKeyProvider keyProvider,
        string processName) => new(storage, keyProvider, processName);

    public DbContextOptions<LocalStoreDbContext> CreateOptions(string? databasePath = null)
    {
        EnsureSyncServiceProcess(_processName);
        string path = PreparePath(databasePath ?? _storage.DatabasePath);
        return new DbContextOptionsBuilder<LocalStoreDbContext>()
            .UseSqlite(BuildConnectionString(path))
            .AddInterceptors(new LocalStorePragmaInterceptor(_keyProvider, _storage.BusyTimeoutMilliseconds))
            .Options;
    }

    public async Task<SqliteConnection> OpenConnectionAsync(
        string? databasePath = null,
        CancellationToken cancellationToken = default,
        bool enableWal = true)
    {
        EnsureSyncServiceProcess(_processName);
        string path = PreparePath(databasePath ?? _storage.DatabasePath);
        SqliteConnection connection = new(BuildConnectionString(path));
        try
        {
            await connection.OpenAsync(cancellationToken).ConfigureAwait(false);
            await LocalStorePragmaInterceptor.ConfigureAsync(
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

    private static void EnsureSyncServiceProcess(string processName)
    {
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("The device-local store is supported only by the Windows sync service.");
        }

        string fileName = Path.GetFileName(processName);
        string executableName = fileName.EndsWith(".exe", StringComparison.OrdinalIgnoreCase)
            ? fileName[..^4]
            : fileName;
        if (!string.Equals(executableName, "Alhayah.SyncService", StringComparison.OrdinalIgnoreCase))
        {
            throw new UnauthorizedAccessException("Only Alhayah.SyncService may open the device-local store.");
        }
    }

    private static string PreparePath(string path)
    {
        string localPath = LocalStorePathGuard.EnsureLocalPath(path);
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

internal sealed class LocalStorePragmaInterceptor(
    ILocalStoreKeyProvider keyProvider,
    int busyTimeoutMilliseconds) : DbConnectionInterceptor
{
    private readonly ILocalStoreKeyProvider _keyProvider = keyProvider;
    private readonly int _busyTimeoutMilliseconds = busyTimeoutMilliseconds;

    public override void ConnectionOpened(DbConnection connection, ConnectionEndEventData eventData) =>
        throw new NotSupportedException("Open encrypted LocalData connections asynchronously.");

    public override async Task ConnectionOpenedAsync(
        DbConnection connection,
        ConnectionEndEventData eventData,
        CancellationToken cancellationToken = default)
    {
        if (connection is not SqliteConnection sqliteConnection)
        {
            throw new InvalidOperationException("LocalData supports SQLite connections only.");
        }

        await ConfigureAsync(
            sqliteConnection,
            _keyProvider,
            _busyTimeoutMilliseconds,
            cancellationToken).ConfigureAwait(false);
    }

    internal static async Task ConfigureAsync(
        SqliteConnection connection,
        ILocalStoreKeyProvider keyProvider,
        int busyTimeoutMilliseconds,
        CancellationToken cancellationToken,
        bool enableWal = true)
    {
        if (busyTimeoutMilliseconds != 5_000)
        {
            throw new InvalidOperationException("SQLite busy_timeout must be 5000 milliseconds.");
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
                throw new InvalidOperationException("The device-local database could not enable WAL mode.");
            }
        }

        pragmaCommand.CommandText = "PRAGMA busy_timeout = 5000;";
        await pragmaCommand.ExecuteNonQueryAsync(cancellationToken).ConfigureAwait(false);
        pragmaCommand.CommandText = "PRAGMA foreign_keys = ON;";
        await pragmaCommand.ExecuteNonQueryAsync(cancellationToken).ConfigureAwait(false);
        pragmaCommand.CommandText = "PRAGMA synchronous = NORMAL;";
        await pragmaCommand.ExecuteNonQueryAsync(cancellationToken).ConfigureAwait(false);
    }
}
