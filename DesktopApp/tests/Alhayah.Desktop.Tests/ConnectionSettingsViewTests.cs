using System.Windows;
using System.Windows.Controls;
using Alhayah.Desktop.ViewModels;
using Alhayah.Desktop.Views;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class ConnectionSettingsViewTests
{
    [StaFact]
    public async Task ConnectionSettingsView_WithAdministrator_ShowsArabicRtlFieldsAndRotationInputs()
    {
        EnsureApplicationResources();
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        ConnectionSettingsView view = new(viewModel);
        Window window = new() { Content = view, Width = 1100, Height = 800 };

        try
        {
            window.Show();

            Assert.Equal(FlowDirection.RightToLeft, view.FlowDirection);
            Assert.NotNull(view.FindName("LaravelEndpointTextBox"));
            Assert.NotNull(view.FindName("LocalServerHostTextBox"));
            Assert.NotNull(view.FindName("LocalServerPortTextBox"));
            Assert.NotNull(view.FindName("LaravelSpkiPinTextBox"));
            Assert.NotNull(view.FindName("PreviousLaravelSpkiPinTextBox"));
            Assert.NotNull(view.FindName("LocalServerSpkiPinTextBox"));
            Assert.NotNull(view.FindName("PreviousLocalServerSpkiPinTextBox"));
            Assert.True(Assert.IsType<Button>(view.FindName("SaveSettingsButton")).IsEnabled);
            Assert.True(Assert.IsType<Button>(view.FindName("RestoreDefaultsButton")).IsEnabled);
        }
        finally
        {
            window.Close();
        }
    }

    [StaFact]
    public async Task ConnectionSettingsView_WithoutAdministrator_DisablesSettingsAndConflictControls()
    {
        EnsureApplicationResources();
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync(isAdministrator: false);
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        ConnectionSettingsView view = new(viewModel);
        Window window = new() { Content = view, Width = 1100, Height = 800 };

        try
        {
            window.Show();

            Assert.False(Assert.IsType<Button>(view.FindName("SaveSettingsButton")).IsEnabled);
            Assert.False(Assert.IsType<Button>(view.FindName("RestoreDefaultsButton")).IsEnabled);
            Assert.False(Assert.IsType<Button>(view.FindName("CreateBackupButton")).IsEnabled);
            Assert.False(Assert.IsType<Button>(view.FindName("RestoreBackupButton")).IsEnabled);
            Assert.False(Assert.IsType<TextBox>(view.FindName("LaravelSpkiPinTextBox")).IsEnabled);
            Assert.False(Assert.IsType<FrameworkElement>(view.FindName("ConflictReviewPanel")).IsEnabled);
        }
        finally
        {
            window.Close();
        }
    }

    [StaFact]
    public async Task PasswordBoxes_AfterSave_ClearTransientSecretsFromViewAndViewModel()
    {
        EnsureApplicationResources();
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        ConnectionSettingsView view = new(viewModel);
        Window window = new() { Content = view, Width = 1100, Height = 800 };

        try
        {
            window.Show();
            PasswordBox laravelSecret = Assert.IsType<PasswordBox>(view.FindName("LaravelTokenPasswordBox"));
            PasswordBox localSecret = Assert.IsType<PasswordBox>(view.FindName("LocalCredentialPasswordBox"));
            laravelSecret.Password = "synthetic-view-laravel-secret";
            localSecret.Password = "synthetic-view-local-secret";

            await viewModel.SaveCommand.ExecuteAsync(null);

            Assert.Equal(string.Empty, laravelSecret.Password);
            Assert.Equal(string.Empty, localSecret.Password);
            Assert.Equal(string.Empty, viewModel.LaravelDeviceToken);
            Assert.Equal(string.Empty, viewModel.LocalServerDeviceCredential);
        }
        finally
        {
            window.Close();
        }
    }

    [StaFact]
    public async Task BackupRestoreControls_WithFakeIpc_DisplayServiceResultsWithoutNetworkChecks()
    {
        EnsureApplicationResources();
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        ConnectionSettingsView view = new(viewModel);
        Window window = new() { Content = view, Width = 1100, Height = 800 };

        try
        {
            window.Show();
            viewModel.BackupDestinationDirectory = Path.Combine(fixture.SyntheticRootPath, "backup-target");
            viewModel.RestoreBackupDirectory = Path.Combine(fixture.SyntheticRootPath, "backup-source");
            viewModel.RestoreDestinationDatabasePath = Path.Combine(fixture.SyntheticRootPath, "restore-target", "device.db");
            viewModel.RestoreDestinationAttachmentDirectory = Path.Combine(fixture.SyntheticRootPath, "restore-target", "attachments");
            fixture.Host.Commands.Clear();

            await viewModel.CreateBackupCommand.ExecuteAsync(null);
            await viewModel.RestoreBackupCommand.ExecuteAsync(null);
            await view.Dispatcher.InvokeAsync(() => { });

            Assert.True(Assert.IsType<Button>(view.FindName("CreateBackupButton")).IsEnabled);
            Assert.True(Assert.IsType<Button>(view.FindName("RestoreBackupButton")).IsEnabled);
            Assert.Equal(
                fixture.SyntheticBackupDirectory,
                Assert.IsType<TextBlock>(view.FindName("BackupResultPathText")).Text);
            Assert.Equal(
                viewModel.RestoreDestinationDatabasePath,
                Assert.IsType<TextBlock>(view.FindName("RestoreResultDatabasePathText")).Text);
            Assert.Equal(
                viewModel.RestoreDestinationAttachmentDirectory,
                Assert.IsType<TextBlock>(view.FindName("RestoreResultAttachmentPathText")).Text);
            Assert.Equal(
                [Alhayah.Contracts.Ipc.RepositoryCommands.Backup, Alhayah.Contracts.Ipc.RepositoryCommands.Restore],
                fixture.Host.Commands);
        }
        finally
        {
            window.Close();
        }
    }

    [Fact]
    public void ConnectionSettingsXaml_StaticInspection_HasNoTlsTrustOrPinningOptOut()
    {
        string projectDirectory = FindProjectDirectory();
        string viewPath = Path.Combine(
            projectDirectory,
            "..",
            "..",
            "src",
            "Alhayah.Desktop",
            "Views",
            "ConnectionSettingsView.xaml");
        string xaml = File.ReadAllText(Path.GetFullPath(viewPath));

        Assert.DoesNotContain("AllowInsecure", xaml, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("DisableTls", xaml, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("DisablePinning", xaml, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("تعطيل TLS", xaml, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("تجاوز الشهادة", xaml, StringComparison.OrdinalIgnoreCase);
        Assert.DoesNotContain("https://cdn", xaml, StringComparison.OrdinalIgnoreCase);
    }

    private static void EnsureApplicationResources()
    {
        Application application = Application.Current ?? new Application
        {
            ShutdownMode = ShutdownMode.OnExplicitShutdown
        };
        AddResourceDictionary(application, "Themes/DesignTokens.xaml");
        AddResourceDictionary(application, "Assets/Icons/NavigationIcons.xaml");
    }

    private static void AddResourceDictionary(Application application, string relativePath)
    {
        Uri source = new($"pack://application:,,,/Alhayah.Desktop;component/{relativePath}", UriKind.Absolute);
        if (application.Resources.MergedDictionaries.Any(dictionary => dictionary.Source == source))
        {
            return;
        }

        application.Resources.MergedDictionaries.Add(new ResourceDictionary { Source = source });
    }

    private static string FindProjectDirectory()
    {
        DirectoryInfo? directory = new(AppContext.BaseDirectory);
        while (directory is not null)
        {
            if (File.Exists(Path.Combine(directory.FullName, "Alhayah.Desktop.Tests.csproj")))
            {
                return directory.FullName;
            }

            directory = directory.Parent;
        }

        throw new DirectoryNotFoundException("The Desktop test project directory was not found.");
    }
}
