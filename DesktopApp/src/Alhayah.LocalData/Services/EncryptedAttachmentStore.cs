using System.Security.Cryptography;
using System.Text;
using Alhayah.LocalData.Data;

namespace Alhayah.LocalData.Services;

public sealed record EncryptedAttachmentMetadata(string StorageName, long PlaintextLength, string Sha256);

public sealed class EncryptedAttachmentStore
{
    private const byte FormatVersion = 1;
    private const int NonceSize = 12;
    private const int TagSize = 16;
    private readonly string _attachmentDirectory;
    private readonly ILocalStoreKeyProvider _keyProvider;

    private EncryptedAttachmentStore(
        LocalStoreOptions storage,
        ILocalStoreKeyProvider keyProvider,
        string processName)
    {
        ArgumentNullException.ThrowIfNull(storage);
        _keyProvider = keyProvider ?? throw new ArgumentNullException(nameof(keyProvider));
        EnsureSyncServiceProcess(processName);
        _attachmentDirectory = LocalStorePathGuard.EnsureLocalPath(storage.AttachmentDirectory);
        Directory.CreateDirectory(_attachmentDirectory);
    }

    public static EncryptedAttachmentStore CreateForSyncService(LocalStoreOptions storage)
    {
        ArgumentNullException.ThrowIfNull(storage);
        return new EncryptedAttachmentStore(
            storage,
            new WindowsCredentialStoreKeyProvider(storage.AttachmentCredentialTarget),
            Environment.ProcessPath ?? string.Empty);
    }

    internal static EncryptedAttachmentStore CreateForTesting(
        LocalStoreOptions storage,
        ILocalStoreKeyProvider keyProvider,
        string processName) => new(storage, keyProvider, processName);

    public async Task<EncryptedAttachmentMetadata> SaveAsync(
        Stream content,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(content);
        if (!content.CanRead)
        {
            throw new ArgumentException("The attachment stream must be readable.", nameof(content));
        }

        using MemoryStream plaintextBuffer = new();
        await content.CopyToAsync(plaintextBuffer, cancellationToken).ConfigureAwait(false);
        byte[] plaintext = plaintextBuffer.ToArray();
        byte[] key = _keyProvider.GetKey();
        ValidateKey(key);
        byte[] nonce = RandomNumberGenerator.GetBytes(NonceSize);
        byte[] tag = new byte[TagSize];
        byte[] ciphertext = new byte[plaintext.Length];
        string storageName = $"{Guid.NewGuid():N}.bin";
        string storagePath = Path.Combine(_attachmentDirectory, storageName);
        byte[] associatedData = CreateAssociatedData(storageName);

        try
        {
            using (AesGcm aes = new(key, TagSize))
            {
                aes.Encrypt(nonce, plaintext, ciphertext, tag, associatedData);
            }

            try
            {
                await using FileStream output = new(
                    storagePath,
                    FileMode.CreateNew,
                    FileAccess.Write,
                    FileShare.None,
                    4096,
                    useAsync: true);
                await output.WriteAsync(new[] { FormatVersion }, cancellationToken).ConfigureAwait(false);
                await output.WriteAsync(nonce, cancellationToken).ConfigureAwait(false);
                await output.WriteAsync(tag, cancellationToken).ConfigureAwait(false);
                await output.WriteAsync(ciphertext, cancellationToken).ConfigureAwait(false);
                await output.FlushAsync(cancellationToken).ConfigureAwait(false);
            }
            catch
            {
                if (File.Exists(storagePath))
                {
                    File.Delete(storagePath);
                }

                throw;
            }

            return new EncryptedAttachmentMetadata(
                storageName,
                plaintext.LongLength,
                Convert.ToHexString(SHA256.HashData(plaintext)));
        }
        finally
        {
            CryptographicOperations.ZeroMemory(key);
            CryptographicOperations.ZeroMemory(plaintext);
            CryptographicOperations.ZeroMemory(ciphertext);
        }
    }

    public async Task<Stream> OpenReadAsync(
        string storageName,
        CancellationToken cancellationToken = default)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(storageName);
        if (!string.Equals(Path.GetFileName(storageName), storageName, StringComparison.Ordinal))
        {
            throw new ArgumentException("Only an attachment storage name is allowed.", nameof(storageName));
        }

        string storagePath = Path.Combine(_attachmentDirectory, storageName);
        byte[] payload = await File.ReadAllBytesAsync(storagePath, cancellationToken).ConfigureAwait(false);
        int headerLength = 1 + NonceSize + TagSize;
        if (payload.Length < headerLength || payload[0] != FormatVersion)
        {
            CryptographicOperations.ZeroMemory(payload);
            throw new InvalidDataException("The encrypted attachment format is invalid or unsupported.");
        }

        byte[] key = _keyProvider.GetKey();
        ValidateKey(key);
        byte[] plaintext = new byte[payload.Length - headerLength];
        byte[] associatedData = CreateAssociatedData(storageName);
        try
        {
            using AesGcm aes = new(key, TagSize);
            aes.Decrypt(
                payload.AsSpan(1, NonceSize),
                payload.AsSpan(headerLength),
                payload.AsSpan(1 + NonceSize, TagSize),
                plaintext,
                associatedData);
            return new MemoryStream(plaintext, writable: false);
        }
        catch
        {
            CryptographicOperations.ZeroMemory(plaintext);
            throw;
        }
        finally
        {
            CryptographicOperations.ZeroMemory(key);
            CryptographicOperations.ZeroMemory(payload);
        }
    }

    private static byte[] CreateAssociatedData(string storageName)
    {
        byte[] nameBytes = Encoding.UTF8.GetBytes(storageName);
        byte[] associatedData = new byte[1 + nameBytes.Length];
        associatedData[0] = FormatVersion;
        nameBytes.CopyTo(associatedData, 1);
        return associatedData;
    }

    private static void EnsureSyncServiceProcess(string processName)
    {
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("Encrypted attachment storage is supported only on Windows.");
        }

        string fileName = Path.GetFileName(processName);
        string executableName = fileName.EndsWith(".exe", StringComparison.OrdinalIgnoreCase)
            ? fileName[..^4]
            : fileName;
        if (!string.Equals(executableName, "Alhayah.SyncService", StringComparison.OrdinalIgnoreCase))
        {
            throw new UnauthorizedAccessException("Only Alhayah.SyncService may store device-local attachments.");
        }
    }

    private static void ValidateKey(byte[] key)
    {
        if (key.Length != 32)
        {
            CryptographicOperations.ZeroMemory(key);
            throw new CryptographicException("Attachment encryption requires a 32-byte key.");
        }
    }
}
