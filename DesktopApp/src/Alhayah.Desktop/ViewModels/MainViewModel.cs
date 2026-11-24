using Alhayah.Desktop.Models;
using Alhayah.Desktop.Services.Interfaces;

namespace Alhayah.Desktop.ViewModels;

public enum AppSection
{
    Dashboard,
    ConnectionSettings
}

public sealed class MainViewModel : ViewModelBase
{
    private readonly IDesktopIpcClient _ipcClient;
    private AppSection _selectedSection = AppSection.Dashboard;
    private string _connectionName = string.Empty;
    private string _statusMessage = "جاهز للعمل دون اتصال";

    public MainViewModel(IDesktopIpcClient ipcClient)
    {
        _ipcClient = ipcClient ?? throw new ArgumentNullException(nameof(ipcClient));
        NavigateToDashboardCommand = new RelayCommand(() => SelectedSection = AppSection.Dashboard);
        NavigateToSettingsCommand = new RelayCommand(() => SelectedSection = AppSection.ConnectionSettings);
        SaveSettingsCommand = new AsyncRelayCommand(SaveSettingsAsync);
    }

    public AppSection SelectedSection
    {
        get => _selectedSection;
        private set
        {
            if (!SetProperty(ref _selectedSection, value))
            {
                return;
            }

            OnPropertyChanged(nameof(IsDashboardSelected));
            OnPropertyChanged(nameof(IsSettingsSelected));
        }
    }

    public bool IsDashboardSelected => SelectedSection == AppSection.Dashboard;

    public bool IsSettingsSelected => SelectedSection == AppSection.ConnectionSettings;

    public string ConnectionName
    {
        get => _connectionName;
        set => SetProperty(ref _connectionName, value);
    }

    public string StatusMessage
    {
        get => _statusMessage;
        private set => SetProperty(ref _statusMessage, value);
    }

    public RelayCommand NavigateToDashboardCommand { get; }

    public RelayCommand NavigateToSettingsCommand { get; }

    public AsyncRelayCommand SaveSettingsCommand { get; }

    private async Task SaveSettingsAsync()
    {
        DesktopSettings settings = new() { ConnectionName = ConnectionName };
        await _ipcClient.SaveSettingsAsync(settings);
        StatusMessage = "تم حفظ الإعداد التجريبي دون اتصال";
    }
}
