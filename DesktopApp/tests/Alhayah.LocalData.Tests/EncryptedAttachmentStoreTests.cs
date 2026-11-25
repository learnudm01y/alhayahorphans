using System.Security.Cryptography;
using Alhayah.LocalData.Services;
using Xunit;

namespace Alhayah.LocalData.Tests;

public sealed class EncryptedAttachmentStoreTests
{
    [Fact]
    public async Task SaveAndOpenAsync_WithSyntheticContent_StoresAesGcmCiphertextAndSha256()
    {
        using LocalStoreTestHarness harness = new();
        EncryptedAttachmentStore store = harness.CreateAttachmentStore();
        byte[] plaintext = "Synthetic encrypted attachment payload."u8.ToArray();
        await using MemoryStream input = new(plaintext);

        EncryptedAttachmentMetadata metadata = await store.SaveAsync(input);

        string storedPath = Path.Combine(harness.Storage.AttachmentDirectory, metadata.StorageName);
        byte[] storedPayload = await File.ReadAllBytesAsync(storedPath);
        Assert.Equal(1, storedPayload[0]);
        Assert.False(ContainsSequence(storedPayload, plaintext));
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
        using LocalStoreTestHarness harness = new();
        EncryptedAttachmentStore store = harness.CreateAttachmentStore();
        await using MemoryStream input = new("Synthetic tamper-check payload"u8.ToArray());
        EncryptedAttachmentMetadata metadata = await store.SaveAsync(input);
        string storedPath = Path.Combine(harness.Storage.AttachmentDirectory, metadata.StorageName);
        byte[] storedPayload = await File.ReadAllBytesAsync(storedPath);
        storedPayload[^1] ^= 0xFF;
        await File.WriteAllBytesAsync(storedPath, storedPayload);

        await Assert.ThrowsAsync<CryptographicException>(() => store.OpenReadAsync(metadata.StorageName));
    }

    [Fact]
    public async Task OpenReadAsync_WithUnsupportedVersion_ThrowsInvalidDataException()
    {
        using LocalStoreTestHarness harness = new();
        EncryptedAttachmentStore store = harness.CreateAttachmentStore();
        await using MemoryStream input = new("Synthetic version-check payload"u8.ToArray());
        EncryptedAttachmentMetadata metadata = await store.SaveAsync(input);
        string storedPath = Path.Combine(harness.Storage.AttachmentDirectory, metadata.StorageName);
        byte[] storedPayload = await File.ReadAllBytesAsync(storedPath);
        storedPayload[0] = 2;
        await File.WriteAllBytesAsync(storedPath, storedPayload);

        await Assert.ThrowsAsync<InvalidDataException>(() => store.OpenReadAsync(metadata.StorageName));
    }

    [Fact]
    public void CreateForTesting_WithUncAttachmentDirectory_RejectsNetworkStorage()
    {
        using LocalStoreTestHarness harness = new();
        Alhayah.LocalData.Data.LocalStoreOptions storage = new()
        {
            DatabasePath = harness.Storage.DatabasePath,
            BackupDirectory = harness.Storage.BackupDirectory,
            AttachmentDirectory = @"\\server\share\attachments"
        };

        Assert.Throws<InvalidOperationException>(() => EncryptedAttachmentStore.CreateForTesting(
            storage,
            harness.KeyProvider,
            "Alhayah.SyncService"));
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
