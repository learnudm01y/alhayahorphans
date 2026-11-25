using Xunit;

namespace Alhayah.LocalData.Tests;

public sealed class DesktopArchitectureTests
{
    [Fact]
    public void DesktopProject_StaticSourceInspection_UsesIpcWithoutSqliteOrLocalDataReference()
    {
        string projectDirectory = FindProjectDirectory();
        string desktopAppDirectory = Directory.GetParent(Directory.GetParent(projectDirectory)!.FullName)!.FullName;
        string desktopDirectory = Path.Combine(desktopAppDirectory, "src", "Alhayah.Desktop");
        string desktopProject = File.ReadAllText(Path.Combine(desktopDirectory, "Alhayah.Desktop.csproj"));
        string[] sourceFiles = Directory.EnumerateFiles(desktopDirectory, "*.cs", SearchOption.AllDirectories)
            .Where(path => !path.Contains($"{Path.DirectorySeparatorChar}bin{Path.DirectorySeparatorChar}", StringComparison.OrdinalIgnoreCase))
            .Where(path => !path.Contains($"{Path.DirectorySeparatorChar}obj{Path.DirectorySeparatorChar}", StringComparison.OrdinalIgnoreCase))
            .ToArray();
        string combinedSource = string.Join(Environment.NewLine, sourceFiles.Select(File.ReadAllText));

        Assert.DoesNotContain("Alhayah.LocalData", desktopProject, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("Microsoft.Data.Sqlite", desktopProject, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("Microsoft.EntityFrameworkCore", desktopProject, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("SQLitePCLRaw", desktopProject, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("SqliteConnection", combinedSource, StringComparison.Ordinal);
        Assert.DoesNotContain("LocalStoreDbContext", combinedSource, StringComparison.Ordinal);
        Assert.DoesNotContain("Microsoft.Data.Sqlite", combinedSource, StringComparison.Ordinal);
        Assert.Contains("IDesktopIpcClient", combinedSource, StringComparison.Ordinal);
        Assert.Contains(
            "IDesktopIpcClient ipcClient",
            File.ReadAllText(Path.Combine(desktopDirectory, "ViewModels", "MainViewModel.cs")),
            StringComparison.Ordinal);
    }

    private static string FindProjectDirectory()
    {
        DirectoryInfo? directory = new(AppContext.BaseDirectory);
        while (directory is not null)
        {
            if (File.Exists(Path.Combine(directory.FullName, "Alhayah.LocalData.Tests.csproj")))
            {
                return directory.FullName;
            }

            directory = directory.Parent;
        }

        throw new DirectoryNotFoundException("The LocalData test project directory was not found.");
    }
}
