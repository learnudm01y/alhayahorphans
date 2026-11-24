namespace Alhayah.LocalServer.Data;

public sealed class LocalStorageOptions
{
    public required string DatabasePath { get; init; }

    public required string BackupDirectory { get; init; }

    public required string AttachmentDirectory { get; init; }

    public string DatabaseCredentialTarget { get; init; } = "Alhayah/LocalServer/Service/DatabaseEncryptionKey";

    public string AttachmentCredentialTarget { get; init; } = "Alhayah/LocalServer/Service/AttachmentEncryptionKey";

    public int BusyTimeoutMilliseconds { get; init; } = 5_000;
}
