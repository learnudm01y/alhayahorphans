using System.Security.Cryptography;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;

namespace Alhayah.LocalData.Tests;

internal sealed class LocalStoreTestHarness : IDisposable
{
    static LocalStoreTestHarness()
    {
        SQLitePCL.Batteries_V2.Init();
    }

    private readonly string _rootPath = Path.Combine(
        Path.GetTempPath(),
        $"alhayah-local-data-tests-{Guid.NewGuid():N}");

    public LocalStoreTestHarness()
    {
        byte[] key = RandomNumberGenerator.GetBytes(32);
        SyntheticKeyHex = Convert.ToHexString(key);
        KeyProvider = new FixedEncryptionKeyProvider(key);
        Storage = new LocalStoreOptions
        {
            DatabasePath = Path.Combine(_rootPath, "data", "alhayah-device.db"),
            BackupDirectory = Path.Combine(_rootPath, "backups"),
            AttachmentDirectory = Path.Combine(_rootPath, "attachments")
        };
        Connections = LocalStoreConnectionFactory.CreateForTesting(Storage, KeyProvider, "Alhayah.SyncService");
    }

    public LocalStoreOptions Storage { get; }

    public FixedEncryptionKeyProvider KeyProvider { get; }

    public LocalStoreConnectionFactory Connections { get; }

    public string SyntheticKeyHex { get; }

    public string RootPath => _rootPath;

    public static LocalAuditContext Audit { get; } = new(
        Guid.Parse("6e3481eb-94b1-4f5b-a069-f3a0b5922a0d"),
        "sync:write",
        1);

    public LocalStoreDbContext CreateContext(string? databasePath = null) =>
        new(Connections.CreateOptions(databasePath));

    public EncryptedAttachmentStore CreateAttachmentStore(LocalStoreOptions? storage = null) =>
        EncryptedAttachmentStore.CreateForTesting(
            storage ?? Storage,
            KeyProvider,
            "Alhayah.SyncService");

    public static SyncEnvelope CreateEnvelope(
        Guid operationId,
        Guid clientUuid,
        SyncOperationType operationType,
        string? rowVersion = null,
        IReadOnlyList<FieldPatch>? patches = null)
    {
        return new SyncEnvelope
        {
            OperationId = operationId,
            SourceId = new SyncSourceId("laravel:synthetic-installation"),
            DeviceId = "synthetic-device",
            Entity = new EntityDescriptor
            {
                EntityType = "synthetic_records",
                ClientUuid = clientUuid,
                CanonicalId = null,
                References = []
            },
            OperationType = operationType,
            BaseVersion = operationType == SyncOperationType.Create ? null : 1,
            RowVersion = rowVersion,
            OccurredAtUtc = new DateTimeOffset(2026, 2, 3, 4, 5, 6, TimeSpan.Zero),
            Tombstone = operationType == SyncOperationType.Delete
                ? new Tombstone
                {
                    DeletedAtUtc = new DateTimeOffset(2026, 2, 4, 4, 5, 6, TimeSpan.Zero),
                    DeletedByDeviceId = "synthetic-device",
                    Reason = "Synthetic test deletion"
                }
                : null,
            Patches = patches ?? []
        };
    }

    public static FieldPatch CreateDisplayNamePatch(string baseValue, string localValue) => new()
    {
        FieldName = "display_name",
        BaseValue = JsonSerializer.SerializeToElement(baseValue),
        LocalValue = JsonSerializer.SerializeToElement(localValue),
        RemoteValue = JsonSerializer.SerializeToElement(baseValue),
        Criticality = FieldCriticality.Mergeable
    };

    public void Dispose()
    {
        if (Directory.Exists(_rootPath))
        {
            Directory.Delete(_rootPath, recursive: true);
        }
    }
}

internal sealed class FixedEncryptionKeyProvider(byte[] key) : ILocalStoreKeyProvider
{
    private readonly byte[] _key = key.ToArray();

    public byte[] GetKey() => _key.ToArray();
}
