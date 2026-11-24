using System.Security.Cryptography;
using Alhayah.LocalServer.Data;

namespace Alhayah.LocalServer.Services;

public sealed record EncryptedAttachmentMetadata(string StorageName, long PlaintextLength, string Sha256);

public sealed class EncryptedAttachmentStore
{
    private const byte FormatVersion = 1;
    private const int NonceSize = 12;
    private const int TagSize = 16;
    private readonly string _attachmentDirectory;
    private readonly IAttachmentKeyProvider _keyProvider;

    public EncryptedAttachmentStore(LocalStorageOptions storage, IAttachmentKeyProvider keyProvider)
    {
        ArgumentNullException.ThrowIfNull(storage);
        _keyProvider = keyProvider ?? throw new ArgumentNullException(nameof(keyProvider));
        _attachmentDirectory = LocalPathGuard.EnsureLocalPath(storage.AttachmentDirectory);
        Directory.CreateDirectory(_attachmentDirectory);
    }

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
        byte[] associatedData = [FormatVersion];

        try
        {
            using (AesGcm aes = new(key, TagSize))
            {
                aes.Encrypt(nonce, plaintext, ciphertext, tag, associatedData);
            }

            await using FileStream output = new(
                storagePath,
                FileMode.CreateNew,
                FileAccess.Write,
                FileShare.None,
                4096,
                useAsync: true);
            await output.WriteAsync(associatedData, cancellationToken).ConfigureAwait(false);
            await output.WriteAsync(nonce, cancellationToken).ConfigureAwait(false);
            await output.WriteAsync(tag, cancellationToken).ConfigureAwait(false);
            await output.WriteAsync(ciphertext, cancellationToken).ConfigureAwait(false);
            await output.FlushAsync(cancellationToken).ConfigureAwait(false);

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
            throw new InvalidDataException("The encrypted attachment format is invalid or unsupported.");
        }

        byte[] key = _keyProvider.GetKey();
        ValidateKey(key);
        byte[] plaintext = new byte[payload.Length - headerLength];
        byte[] associatedData = [payload[0]];

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

    private static void ValidateKey(byte[] key)
    {
        if (key.Length != 32)
        {
            CryptographicOperations.ZeroMemory(key);
            throw new CryptographicException("Attachment encryption requires a 32-byte key.");
        }
    }
}
