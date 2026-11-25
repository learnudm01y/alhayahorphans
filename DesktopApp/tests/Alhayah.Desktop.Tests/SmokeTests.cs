using System.Windows;
using Alhayah.Desktop.Models;
using Alhayah.Desktop.Services;
using Alhayah.Desktop.Services.Interfaces;
using Alhayah.Desktop.ViewModels;
using Alhayah.Desktop.Views;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class SmokeTests
{
    [StaFact]
    public void MainWindow_OpenWithFakeIpc_ShowsRtlDashboard()
    {
        EnsureApplicationResources();
        FakeDesktopIpcClient ipcClient = new();
        MainViewModel viewModel = CreateMainViewModel(ipcClient);
        MainWindow window = new(viewModel);

        try
        {
            window.Show();

            Assert.True(window.IsVisible);
            Assert.Equal(FlowDirection.RightToLeft, window.FlowDirection);
            Assert.Equal(AppSection.Dashboard, viewModel.SelectedSection);
            FrameworkElement dashboardPanel = Assert.IsAssignableFrom<FrameworkElement>(window.FindName("DashboardPanel"));
            Assert.Equal(Visibility.Visible, dashboardPanel.Visibility);
        }
        finally
        {
            window.Close();
        }
    }

    [StaFact]
    public void NavigateToSettingsCommand_FromDashboard_DisplaysSettingsPanel()
    {
        EnsureApplicationResources();
        FakeDesktopIpcClient ipcClient = new();
        MainViewModel viewModel = CreateMainViewModel(ipcClient);
        MainWindow window = new(viewModel);

        try
        {
            window.Show();

            viewModel.NavigateToSettingsCommand.Execute(null);

            Assert.Equal(AppSection.ConnectionSettings, viewModel.SelectedSection);
            FrameworkElement settingsPanel = Assert.IsAssignableFrom<FrameworkElement>(window.FindName("SettingsPanel"));
            FrameworkElement dashboardPanel = Assert.IsAssignableFrom<FrameworkElement>(window.FindName("DashboardPanel"));
            Assert.Equal(Visibility.Visible, settingsPanel.Visibility);
            Assert.Equal(Visibility.Collapsed, dashboardPanel.Visibility);
        }
        finally
        {
            window.Close();
        }
    }

    [StaFact]
    public async Task SaveSettingsCommand_WithSyntheticValue_RoundTripsThroughFakeIpc()
    {
        FakeDesktopIpcClient ipcClient = new();
        MainViewModel viewModel = CreateMainViewModel(ipcClient);
        viewModel.NavigateToSettingsCommand.Execute(null);
        viewModel.ConnectionName = "اتصال تجريبي اصطناعي";

        await viewModel.SaveSettingsCommand.ExecuteAsync(null);

        DesktopSettings savedSettings = await ipcClient.GetSettingsAsync();
        Assert.Equal("اتصال تجريبي اصطناعي", savedSettings.ConnectionName);
        Assert.Equal("تم حفظ الإعداد التجريبي دون اتصال", viewModel.StatusMessage);
    }

    private static MainViewModel CreateMainViewModel(IDesktopIpcClient ipcClient)
    {
        SyncServiceClient client = new();
        LocalAuthService authentication = new(client);
        ConnectionSettingsService service = new(client, authentication);
        return new MainViewModel(
            ipcClient,
            new ConnectionSettingsViewModel(
                service,
                authentication,
                new BackupClient(client, NullAppLogger.Instance),
                new RestoreClient(client, NullAppLogger.Instance)));
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
}
