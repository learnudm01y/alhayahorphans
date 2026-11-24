using System.ComponentModel;
using System.Runtime.InteropServices;
using System.Security.Cryptography;

namespace Alhayah.LocalServer.Services;

public interface IDatabaseKeyProvider
{
    byte[] GetKey();
}

public interface IAttachmentKeyProvider
{
    byte[] GetKey();
}

public sealed class WindowsCredentialKeyProvider(string credentialTarget) : IDatabaseKeyProvider, IAttachmentKeyProvider
{
    private const int CredentialTypeGeneric = 1;

    private readonly string _credentialTarget = string.IsNullOrWhiteSpace(credentialTarget)
        ? throw new ArgumentException("A Windows credential target is required.", nameof(credentialTarget))
        : credentialTarget;

    public byte[] GetKey()
    {
        if (!OperatingSystem.IsWindows())
        {
            throw new PlatformNotSupportedException("Windows Credential Manager is available only on Windows.");
        }

        if (!CredRead(_credentialTarget, CredentialTypeGeneric, 0, out IntPtr credentialPointer))
        {
            throw new Win32Exception(
                Marshal.GetLastWin32Error(),
                "The LocalServer encryption key is unavailable to the Windows service account.");
        }

        try
        {
            NativeCredential credential = Marshal.PtrToStructure<NativeCredential>(credentialPointer);
            if (credential.CredentialBlobSize != 32 || credential.CredentialBlob == IntPtr.Zero)
            {
                throw new CryptographicException("The stored LocalServer encryption key must contain exactly 32 bytes.");
            }

            byte[] key = new byte[32];
            Marshal.Copy(credential.CredentialBlob, key, 0, key.Length);
            return key;
        }
        finally
        {
            CredFree(credentialPointer);
        }
    }

    [DllImport("advapi32.dll", EntryPoint = "CredReadW", CharSet = CharSet.Unicode, SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool CredRead(string targetName, int type, int flags, out IntPtr credential);

    [DllImport("advapi32.dll", SetLastError = true)]
    private static extern void CredFree(IntPtr credential);

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct NativeCredential
    {
        public int Flags;
        public int Type;
        public IntPtr TargetName;
        public IntPtr Comment;
        public System.Runtime.InteropServices.ComTypes.FILETIME LastWritten;
        public int CredentialBlobSize;
        public IntPtr CredentialBlob;
        public int Persist;
        public int AttributeCount;
        public IntPtr Attributes;
        public IntPtr TargetAlias;
        public IntPtr UserName;
    }
}
