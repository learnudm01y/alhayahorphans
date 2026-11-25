using System.Collections.Concurrent;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;
using Alhayah.SyncService;
using Alhayah.SyncService.Configuration;
using Alhayah.SyncService.Ipc;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Storage;
using Alhayah.SyncService.Synchronization;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;
using Xunit;

namespace Alhayah.SyncService.Tests;

internal static class TestPermissions
{
    public static readonly string[] All =
    [
        LocalPermissions.Read,
        LocalPermissions.Search,
        LocalPermissions.Save,
        LocalPermissions.Query,
        LocalPermissions.Backup,
        LocalPermissions.Restore,
        LocalPermissions.ManageSettings,
        LocalPermissions.ResolveConflicts,
        LocalPermissions.Synchronize,
        LocalPermissions.WriteAudit
    ];
}

internal sealed class SyncServiceIpcHarness : IAsyncDisposable
{
    private readonly string _root;
    private readonly ECDsa _signingKey;
    private readonly SignedPermissionManifestValidator _manifestValidator;
    private readonly TestLogSink _logs;

    private SyncServiceIpcHarness(string root)
    {
        _root = root;
        _signingKey = ECDsa.Create(ECCurve.NamedCurves.nistP256);
        _logs = new TestLogSink();
        Store = new SyntheticSyncStore(root);
        Secrets = new InMemorySecretStore();
        SettingsFilePath = Path.Combine(root, "configuration", "connection-settings.json");
        SyncSettingsStore settings = new(SettingsFilePath);
        Argon2PasswordHasher passwordHasher = new();
        _manifestValidator = new SignedPermissionManifestValidator(_signingKey.ExportSubjectPublicKeyInfo());
        LocalAuthenticationService authentication = new(
            Store,
            passwordHasher,
            _manifestValidator,
            TimeProvider.System);
        SyncServiceHostOptions hostOptions = new()
        {
            LaravelSourceId = "laravel:synthetic",
            LocalServerSourceId = "local:synthetic"
        };
        Handlers = new LocalStoreCommandHandlers(
            Store,
            settings,
            Secrets,
            authentication,
            new UnavailableAdministratorBootstrapService(),
            new SyncChannelRegistry([]),
            hostOptions,
            TimeProvider.System,
            _logs.CreateLogger<LocalStoreCommandHandlers>());
        string currentSid = WindowsIdentity.GetCurrent().User!.Value;
        NamedPipeHostOptions pipeOptions = new()
        {
            PipeName = $"Alhayah.Tests.Integration.{Guid.NewGuid():N}",
            AllowedSids = [currentSid],
            RequestTimeout = TimeSpan.FromSeconds(5)
        };
        Host = new NamedPipeHost(
            pipeOptions,
            Handlers,
            new WindowsNamedPipeAccepter(pipeOptions),
            _logs.CreateLogger<NamedPipeHost>());
        Client = new TestIpcClient(pipeOptions.PipeName);
    }

    public static JsonSerializerOptions JsonOptions => SyncServiceJson.Options;

    public SyntheticSyncStore Store { get; }

    public InMemorySecretStore Secrets { get; }

    public LocalStoreCommandHandlers Handlers { get; }

    public NamedPipeHost Host { get; }

    public TestIpcClient Client { get; }

    public string SettingsFilePath { get; }

    public IReadOnlyCollection<string> LogMessages => _logs.Messages;

    public TestLogSink Logs => _logs;

    public static async Task<SyncServiceIpcHarness> CreateAsync()
    {
        string root = Path.Combine(Path.GetTempPath(), $"Alhayah.SyncService.Tests.{Guid.NewGuid():N}");
        SyncServiceIpcHarness harness = new(root);
        try
        {
            await harness.Store.InitializeAsync(CancellationToken.None);
            await harness.Host.StartAsync(CancellationToken.None);
            return harness;
        }
        catch
        {
            await harness.DisposeAsync();
            throw;
        }
    }

    public string GetPath(params string[] segments) => Path.Combine([_root, .. segments]);

    public async Task SeedUserAsync(string userName, string password, params string[] permissions)
    {
        Guid userId = Guid.NewGuid();
        PasswordHashResult passwordHash = new Argon2PasswordHasher().Hash(password);
        LocalPermissionManifest manifest = new()
        {
            UserId = userId,
            DeviceId = "synthetic-device",
            SourceId = "laravel:synthetic",
            Permissions = permissions,
            ManifestVersion = 1,
            IssuedAtUtc = DateTimeOffset.Parse("2026-11-25T00:00:00Z")
        };
        string manifestJson = PermissionManifestJson.Serialize(manifest);
        byte[] signature = _signingKey.SignData(Encoding.UTF8.GetBytes(manifestJson), HashAlgorithmName.SHA256);
        await using LocalStoreDbContext context = Store.CreateContext();
        context.LocalUsers.Add(new LocalUser
        {
            Id = userId,
            UserNameNormalized = userName.Trim().ToUpperInvariant(),
            DisplayName = "Synthetic User",
            PasswordHash = passwordHash.Hash,
            PasswordSalt = passwordHash.Salt,
            Argon2MemoryKib = passwordHash.MemoryKib,
            Argon2Iterations = passwordHash.Iterations,
            Argon2Parallelism = passwordHash.Parallelism,
            PermissionManifestJson = manifestJson,
            ManifestSignature = signature,
            ManifestVersion = manifest.ManifestVersion,
            ManifestIssuedAtUtc = manifest.IssuedAtUtc,
            CreatedAtUtc = manifest.IssuedAtUtc
        });
        await context.SaveChangesAsync();
    }

    public async Task<LocalSession> LoginAsync(string userName, string password)
    {
        SyncServiceResponse response = await Client.SendAsync(
            SyncServiceCommands.Login,
            new LoginRequest
            {
                UserName = userName,
                Password = password,
                DeviceId = "synthetic-device",
                SourceId = "laravel:synthetic"
            },
            session: null);
        Assert.True(response.Success, response.Error?.Code);
        return response.Payload!.Value.Deserialize<LocalSession>(JsonOptions)
            ?? throw new InvalidDataException("The synthetic login response is empty.");
    }

    public async ValueTask DisposeAsync()
    {
        await Host.DisposeAsync();
        _manifestValidator.Dispose();
        _signingKey.Dispose();
        if (Directory.Exists(_root))
        {
            Directory.Delete(_root, recursive: true);
        }
    }
}

internal sealed class SyntheticSyncStore(string root) : ISyncServiceStore
{
    private readonly string _root = root;
    private readonly string _databasePath = Path.Combine(root, "live", "device.db");
    private readonly string _attachmentDirectory = Path.Combine(root, "live", "attachments");

    public async Task InitializeAsync(CancellationToken cancellationToken)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(_databasePath)!);
        Directory.CreateDirectory(_attachmentDirectory);
        await using LocalStoreDbContext context = CreateContext();
        await context.Database.MigrateAsync(cancellationToken);
    }

    public LocalStoreDbContext CreateContext(string? databasePath = null)
    {
        string path = databasePath ?? _databasePath;
        Directory.CreateDirectory(Path.GetDirectoryName(path)!);
        SqliteConnectionStringBuilder connection = new()
        {
            DataSource = path,
            Mode = SqliteOpenMode.ReadWriteCreate,
            Cache = SqliteCacheMode.Private,
            Pooling = false,
            ForeignKeys = true
        };
        DbContextOptions<LocalStoreDbContext> options = new DbContextOptionsBuilder<LocalStoreDbContext>()
            .UseSqlite(connection.ToString())
            .Options;
        return new LocalStoreDbContext(options);
    }

    public async Task<LocalStoreBackup> CreateBackupAsync(
        string? destinationDirectory,
        CancellationToken cancellationToken)
    {
        string backupRoot = destinationDirectory ?? Path.Combine(_root, "backups");
        string backupDirectory = Path.Combine(backupRoot, $"synthetic-{Guid.NewGuid():N}");
        string databasePath = Path.Combine(backupDirectory, "device.db");
        string manifestPath = Path.Combine(backupDirectory, "manifest.json");
        Directory.CreateDirectory(Path.Combine(backupDirectory, "attachments"));
        await using (SqliteConnection source = new($"Data Source={_databasePath};Mode=ReadOnly;Pooling=False"))
        await using (SqliteConnection destination = new($"Data Source={databasePath};Mode=ReadWriteCreate;Pooling=False"))
        {
            await source.OpenAsync(cancellationToken);
            await destination.OpenAsync(cancellationToken);
            source.BackupDatabase(destination);
        }

        if (Directory.Exists(_attachmentDirectory))
        {
            foreach (string source in Directory.EnumerateFiles(_attachmentDirectory, "*", SearchOption.TopDirectoryOnly))
            {
                string destination = Path.Combine(backupDirectory, "attachments", Path.GetFileName(source));
                await using FileStream input = new(source, FileMode.Open, FileAccess.Read, FileShare.Read, 81920, true);
                await using FileStream output = new(destination, FileMode.CreateNew, FileAccess.Write, FileShare.None, 81920, true);
                await input.CopyToAsync(output, cancellationToken);
            }
        }

        await File.WriteAllTextAsync(manifestPath, "{}", cancellationToken);
        return new LocalStoreBackup(backupDirectory, databasePath, manifestPath);
    }

    public async Task RestoreBackupAsync(
        string backupDirectory,
        string destinationDatabasePath,
        string destinationAttachmentDirectory,
        CancellationToken cancellationToken)
    {
        if (File.Exists(destinationDatabasePath) || Directory.Exists(destinationAttachmentDirectory))
        {
            throw new IOException("The synthetic restore destination already exists.");
        }

        Directory.CreateDirectory(Path.GetDirectoryName(destinationDatabasePath)!);
        Directory.CreateDirectory(destinationAttachmentDirectory);
        await using (FileStream input = new(
            Path.Combine(backupDirectory, "device.db"),
            FileMode.Open,
            FileAccess.Read,
            FileShare.Read,
            81920,
            true))
        await using (FileStream output = new(
            destinationDatabasePath,
            FileMode.CreateNew,
            FileAccess.Write,
            FileShare.None,
            81920,
            true))
        {
            await input.CopyToAsync(output, cancellationToken);
        }

        string attachments = Path.Combine(backupDirectory, "attachments");
        if (!Directory.Exists(attachments))
        {
            return;
        }

        foreach (string source in Directory.EnumerateFiles(attachments, "*", SearchOption.TopDirectoryOnly))
        {
            string destination = Path.Combine(destinationAttachmentDirectory, Path.GetFileName(source));
            await using FileStream input = new(source, FileMode.Open, FileAccess.Read, FileShare.Read, 81920, true);
            await using FileStream output = new(destination, FileMode.CreateNew, FileAccess.Write, FileShare.None, 81920, true);
            await input.CopyToAsync(output, cancellationToken);
        }
    }
}

internal sealed class InMemorySecretStore : ISecretStore
{
    private readonly ConcurrentDictionary<string, byte[]> _values = new(StringComparer.Ordinal);

    public void SetUtf8(string target, string value) => _values[target] = Encoding.UTF8.GetBytes(value);

    public void SetBytes(string target, byte[] value) => _values[target] = [.. value];

    public byte[]? Read(string target) => _values.TryGetValue(target, out byte[]? value) ? [.. value] : null;

    public void Remove(string target) => _values.TryRemove(target, out _);

    public string? GetUtf8(string target)
    {
        byte[]? value = Read(target);
        return value is null ? null : Encoding.UTF8.GetString(value);
    }
}

internal sealed class TestLogSink
{
    private readonly ConcurrentQueue<string> _messages = new();

    public IReadOnlyCollection<string> Messages => _messages.ToArray();

    public ILogger<T> CreateLogger<T>() => new TestLogger<T>(this);

    private sealed class TestLogger<T>(TestLogSink sink) : ILogger<T>
    {
        public IDisposable? BeginScope<TState>(TState state) where TState : notnull => null;

        public bool IsEnabled(LogLevel logLevel) => true;

        public void Log<TState>(
            LogLevel logLevel,
            EventId eventId,
            TState state,
            Exception? exception,
            Func<TState, Exception?, string> formatter) =>
            sink._messages.Enqueue($"{logLevel}:{eventId.Id}:{formatter(state, exception)}");
    }
}
