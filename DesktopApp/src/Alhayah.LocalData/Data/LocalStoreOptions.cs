namespace Alhayah.LocalData.Data;

public sealed class LocalStoreOptions
{
    public required string DatabasePath { get; init; }

    public required string BackupDirectory { get; init; }

    public required string AttachmentDirectory { get; init; }

    public string DatabaseCredentialTarget { get; init; } = "Alhayah/SyncService/Device/DatabaseEncryptionKey";

    public string AttachmentCredentialTarget { get; init; } = "Alhayah/SyncService/Device/AttachmentEncryptionKey";

    public int BusyTimeoutMilliseconds => 5_000;
}
