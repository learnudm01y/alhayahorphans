using System.Security.Cryptography;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Services;

namespace Alhayah.LocalServer.Tests;

internal sealed class LocalServerTestHarness : IDisposable
{
    static LocalServerTestHarness()
    {
        SQLitePCL.Batteries_V2.Init();
    }

    private readonly string _rootPath = Path.Combine(
        Path.GetTempPath(),
        $"alhayah-local-server-tests-{Guid.NewGuid():N}");

    public LocalServerTestHarness()
    {
        byte[] key = RandomNumberGenerator.GetBytes(32);
        SyntheticKeyHex = Convert.ToHexString(key);
        KeyProvider = new FixedEncryptionKeyProvider(key);
        Storage = new LocalStorageOptions
        {
            DatabasePath = Path.Combine(_rootPath, "data", "alhayah-local.db"),
            BackupDirectory = Path.Combine(_rootPath, "backups"),
            AttachmentDirectory = Path.Combine(_rootPath, "attachments")
        };
        Connections = new LocalDbConnectionFactory(Storage, KeyProvider);
    }

    public LocalStorageOptions Storage { get; }

    public FixedEncryptionKeyProvider KeyProvider { get; }

    public LocalDbConnectionFactory Connections { get; }

    public string SyntheticKeyHex { get; }

    public string RootPath => _rootPath;

    public LocalDbContext CreateContext(string? databasePath = null) =>
        new(Connections.CreateOptions(databasePath));

    public static Disbursement CreateDisbursement(decimal amount)
    {
        DateTimeOffset timestamp = new(2026, 1, 2, 3, 4, 5, TimeSpan.Zero);

        return new Disbursement
        {
            Id = Guid.NewGuid(),
            TransactionNumber = $"SYNTHETIC-{Guid.NewGuid():N}",
            RowVersion = 1,
            CreatedAtUtc = timestamp,
            UpdatedAtUtc = timestamp,
            EntitlementId = Guid.NewGuid(),
            Amount = amount,
            Quantity = 1,
            CurrencyCode = "USD",
            Status = "Requested",
            RequestedAtUtc = timestamp,
            RecipientType = "Synthetic",
            RecipientNameSnapshot = "Synthetic recipient"
        };
    }

    public void Dispose()
    {
        if (Directory.Exists(_rootPath))
        {
            Directory.Delete(_rootPath, recursive: true);
        }
    }
}

internal sealed class FixedEncryptionKeyProvider(byte[] key) : IDatabaseKeyProvider, IAttachmentKeyProvider
{
    private readonly byte[] _key = key.ToArray();

    public byte[] GetKey() => _key.ToArray();
}
