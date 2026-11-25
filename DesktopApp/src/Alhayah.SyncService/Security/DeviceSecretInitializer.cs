using System.Security.Cryptography;
using Alhayah.LocalData.Data;

namespace Alhayah.SyncService.Security;

public static class DeviceSecretInitializer
{
    public static void EnsureEncryptionKeys(ISecretStore secrets, LocalStoreOptions options)
    {
        ArgumentNullException.ThrowIfNull(secrets);
        ArgumentNullException.ThrowIfNull(options);
        EnsureKey(secrets, options.DatabaseCredentialTarget);
        EnsureKey(secrets, options.AttachmentCredentialTarget);
    }

    private static void EnsureKey(ISecretStore secrets, string target)
    {
        byte[]? existing = secrets.Read(target);
        if (existing is not null)
        {
            try
            {
                if (existing.Length != 32)
                {
                    throw new CryptographicException("The device encryption key must contain exactly 32 bytes.");
                }

                return;
            }
            finally
            {
                CryptographicOperations.ZeroMemory(existing);
            }
        }

        byte[] generated = RandomNumberGenerator.GetBytes(32);
        try
        {
            secrets.SetBytes(target, generated);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(generated);
        }
    }
}
