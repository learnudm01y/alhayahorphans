using System.Windows;
using Alhayah.Desktop.Services;
using Alhayah.Desktop.Services.Interfaces;
using Alhayah.Desktop.ViewModels;
using Alhayah.Desktop.Views;
using Microsoft.Extensions.DependencyInjection;

namespace Alhayah.Desktop;

public partial class App : Application
{
    private ServiceProvider? _serviceProvider;

    protected override void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);

        ServiceCollection services = new();
        ConfigureServices(services);
        _serviceProvider = services.BuildServiceProvider();

        Alhayah.Desktop.Views.MainWindow mainWindow = _serviceProvider.GetRequiredService<MainWindow>();
        MainWindow = mainWindow;
        mainWindow.Show();
    }

    protected override void OnExit(ExitEventArgs e)
    {
        _serviceProvider?.Dispose();
        base.OnExit(e);
    }

    private static void ConfigureServices(IServiceCollection services)
    {
        services.AddSingleton<IDesktopIpcClient, FakeDesktopIpcClient>();
        services.AddTransient<MainViewModel>();
        services.AddTransient<MainWindow>();
    }
}
