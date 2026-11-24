using System.Security.Cryptography;
using Alhayah.LocalServer.Services;
using Xunit;

namespace Alhayah.LocalServer.Tests;

public sealed class EncryptedAttachmentStoreTests
{
    [Fact]
    public async Task SaveAndOpenAsync_WithSyntheticContent_StoresCiphertextAndRoundTripsPlaintext()
    {
        using LocalServerTestHarness harness = new();
        EncryptedAttachmentStore store = new(harness.Storage, harness.KeyProvider);
        byte[] plaintext = "Synthetic attachment payload; not a real person or document."u8.ToArray();
        await using MemoryStream input = new(plaintext);

        EncryptedAttachmentMetadata metadata = await store.SaveAsync(input);

        string storedPath = Path.Combine(harness.Storage.AttachmentDirectory, metadata.StorageName);
        byte[] ciphertext = await File.ReadAllBytesAsync(storedPath);
        Assert.NotEqual(plaintext, ciphertext);
        Assert.False(ContainsSequence(ciphertext, plaintext));
        Assert.Equal(plaintext.Length, metadata.PlaintextLength);
        Assert.Equal(Convert.ToHexString(SHA256.HashData(plaintext)), metadata.Sha256);

        await using Stream decrypted = await store.OpenReadAsync(metadata.StorageName);
        using MemoryStream output = new();
        await decrypted.CopyToAsync(output);
        Assert.Equal(plaintext, output.ToArray());
    }

    [Fact]
    public async Task OpenReadAsync_WithTamperedCiphertext_ThrowsCryptographicException()
    {
        using LocalServerTestHarness harness = new();
        EncryptedAttachmentStore store = new(harness.Storage, harness.KeyProvider);
        await using MemoryStream input = new("Synthetic tamper-check payload"u8.ToArray());
        EncryptedAttachmentMetadata metadata = await store.SaveAsync(input);
        string storedPath = Path.Combine(harness.Storage.AttachmentDirectory, metadata.StorageName);
        byte[] ciphertext = await File.ReadAllBytesAsync(storedPath);
        ciphertext[^1] ^= 0xFF;
        await File.WriteAllBytesAsync(storedPath, ciphertext);

        await Assert.ThrowsAsync<CryptographicException>(() => store.OpenReadAsync(metadata.StorageName));
    }

    private static bool ContainsSequence(byte[] source, byte[] sought)
    {
        for (int offset = 0; offset <= source.Length - sought.Length; offset++)
        {
            if (source.AsSpan(offset, sought.Length).SequenceEqual(sought))
            {
                return true;
            }
        }

        return false;
    }
}
