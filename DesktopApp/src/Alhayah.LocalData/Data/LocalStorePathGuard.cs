namespace Alhayah.LocalData.Data;

public static class LocalStorePathGuard
{
    public static string EnsureLocalPath(string path)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(path);
        if (IsUncPath(path))
        {
            throw new InvalidOperationException("Network paths are not allowed for device-local storage.");
        }

        string fullPath = Path.GetFullPath(path);
        if (!Path.IsPathFullyQualified(fullPath) || IsUncPath(fullPath))
        {
            throw new InvalidOperationException("Device-local storage requires a fully qualified local path.");
        }

        if (OperatingSystem.IsWindows())
        {
            string? root = Path.GetPathRoot(fullPath);
            if (root is not null && new DriveInfo(root).DriveType == DriveType.Network)
            {
                throw new InvalidOperationException("Mapped network drives are not allowed for device-local storage.");
            }
        }

        return fullPath;
    }

    private static bool IsUncPath(string path) =>
        path.StartsWith(@"\\", StringComparison.Ordinal) ||
        path.StartsWith("//", StringComparison.Ordinal);
}
