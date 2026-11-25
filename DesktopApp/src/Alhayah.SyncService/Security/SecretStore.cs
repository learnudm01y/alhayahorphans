using System.ComponentModel;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using Alhayah.Contracts.Domain;

namespace Alhayah.SyncService.Security;

public static class SecretTargets
{
    public static string LaravelDeviceToken(string sourceId) =>
        $"Alhayah/Sync/{new SyncSourceId(sourceId).Value}/LaravelDeviceToken";

    public static string LocalServerDeviceCredential(string sourceId) =>
        $"Alhayah/Sync/{new SyncSourceId(sourceId).Value}/LocalDeviceCredential";
}

public interface ISecretStore
{
    void SetUtf8(string target, string value);

    void SetBytes(string target, byte[] value);

    byte[]? Read(string target);

    void Remove(string target);
}

public sealed class SecretStore : ISecretStore
{
    private const int CredentialTypeGeneric = 1;
    private const int CredentialPersistLocalMachine = 2;
    private const int ErrorNotFound = 1168;
    private const int MaximumCredentialBlobBytes = 2560;

    public void SetUtf8(string target, string value)
    {
        ArgumentNullException.ThrowIfNull(value);
        byte[] bytes = Encoding.UTF8.GetBytes(value);
        try
        {
            SetBytes(target, bytes);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(bytes);
        }
    }

    public void SetBytes(string target, byte[] value)
    {
        ValidateTarget(target);
        ArgumentNullException.ThrowIfNull(value);
        if (value.Length > MaximumCredentialBlobBytes)
        {
            throw new ArgumentException("The credential exceeds the Windows Credential Manager size boundary.", nameof(value));
        }

        IntPtr credentialBlob = IntPtr.Zero;
        try
        {
            credentialBlob = Marshal.AllocHGlobal(value.Length);
            Marshal.Copy(value, 0, credentialBlob, value.Length);
            NativeCredential credential = new()
            {
                Type = CredentialTypeGeneric,
                TargetName = target,
                CredentialBlobSize = value.Length,
                CredentialBlob = credentialBlob,
                Persist = CredentialPersistLocalMachine,
                UserName = "Alhayah.SyncService"
            };
            if (!CredWrite(ref credential, 0))
            {
                throw new Win32Exception(Marshal.GetLastWin32Error(), "The Windows credential could not be written.");
            }
        }
        finally
        {
            if (credentialBlob != IntPtr.Zero)
            {
                Marshal.Copy(new byte[value.Length], 0, credentialBlob, value.Length);
                Marshal.FreeHGlobal(credentialBlob);
            }
        }
    }

    public byte[]? Read(string target)
    {
        ValidateTarget(target);
        EnsureWindows();
        if (!CredRead(target, CredentialTypeGeneric, 0, out IntPtr credentialPointer))
        {
            int error = Marshal.GetLastWin32Error();
            if (error == ErrorNotFound)
            {
                return null;
            }

            throw new Win32Exception(error, "The Windows credential could not be read.");
        }

        try
        {
            NativeCredential credential = Marshal.PtrToStructure<NativeCredential>(credentialPointer);
            if (credential.CredentialBlobSize < 0 || credential.CredentialBlobSize > MaximumCredentialBlobBytes)
            {
                throw new InvalidDataException("The Windows credential has an invalid size.");
            }

            if (credential.CredentialBlobSize > 0 && credential.CredentialBlob == IntPtr.Zero)
            {
                throw new InvalidDataException("The Windows credential has no credential blob.");
            }

            byte[] value = new byte[credential.CredentialBlobSize];
            if (value.Length > 0)
            {
                Marshal.Copy(credential.CredentialBlob, value, 0, value.Length);
                Marshal.Copy(new byte[value.Length], 0, credential.CredentialBlob, value.Length);
            }

            return value;
        }
        finally
        {
            CredFree(credentialPointer);
        }
    }

    public void Remove(string target)
    {
        ValidateTarget(target);
        EnsureWindows();
        if (CredDelete(target, CredentialTypeGeneric, 0))
        {
            return;
        }

        int error = Marshal.GetLastWin32Error();
        if (error != ErrorNotFound)
        {
            throw new Win32Exception(error, "The Windows credential could not be removed.");
        }
    }

    private static void ValidateTarget(string target)
    {
        EnsureWindows();
        ArgumentException.ThrowIfNullOrWhiteSpace(target);
        if (target.Length > 32767)
        {
            throw new ArgumentException("The Windows credential target is too long.", nameof(target));
        }
    }

    private static void EnsureWindows()
    {
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("Windows Credential Manager is available only on Windows.");
        }
    }

    [DllImport("advapi32.dll", EntryPoint = "CredWriteW", CharSet = CharSet.Unicode, SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool CredWrite(ref NativeCredential credential, int flags);

    [DllImport("advapi32.dll", EntryPoint = "CredReadW", CharSet = CharSet.Unicode, SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool CredRead(string targetName, int type, int flags, out IntPtr credential);

    [DllImport("advapi32.dll", EntryPoint = "CredDeleteW", CharSet = CharSet.Unicode, SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool CredDelete(string targetName, int type, int flags);

    [DllImport("advapi32.dll", SetLastError = true)]
    private static extern void CredFree(IntPtr credential);

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private sealed class NativeCredential
    {
        public int Flags;
        public int Type;

        [MarshalAs(UnmanagedType.LPWStr)]
        public string TargetName = string.Empty;

        [MarshalAs(UnmanagedType.LPWStr)]
        public string? Comment;

        public System.Runtime.InteropServices.ComTypes.FILETIME LastWritten;
        public int CredentialBlobSize;
        public IntPtr CredentialBlob;
        public int Persist;
        public int AttributeCount;
        public IntPtr Attributes;

        [MarshalAs(UnmanagedType.LPWStr)]
        public string? TargetAlias;

        [MarshalAs(UnmanagedType.LPWStr)]
        public string? UserName;
    }
}
