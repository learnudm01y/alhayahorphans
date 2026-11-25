using System.Windows;
using System.Windows.Controls;
using Alhayah.Desktop.ViewModels;

namespace Alhayah.Desktop.Views;

public partial class ConnectionSettingsView : UserControl
{
    private ConnectionSettingsViewModel? _subscribedViewModel;

    public ConnectionSettingsView(ConnectionSettingsViewModel viewModel)
    {
        ArgumentNullException.ThrowIfNull(viewModel);
        InitializeComponent();
        DataContext = viewModel;
        Subscribe(viewModel);
        DataContextChanged += OnDataContextChanged;
    }

    private void OnDataContextChanged(object sender, DependencyPropertyChangedEventArgs e)
    {
        if (_subscribedViewModel is not null)
        {
            _subscribedViewModel.SecretsCleared -= OnSecretsCleared;
            _subscribedViewModel = null;
        }

        if (e.NewValue is ConnectionSettingsViewModel viewModel)
        {
            Subscribe(viewModel);
        }
    }

    private void Subscribe(ConnectionSettingsViewModel viewModel)
    {
        _subscribedViewModel = viewModel;
        _subscribedViewModel.SecretsCleared += OnSecretsCleared;
    }

    private void OnLaravelTokenChanged(object sender, RoutedEventArgs e)
    {
        if (DataContext is ConnectionSettingsViewModel viewModel && sender is PasswordBox passwordBox)
        {
            viewModel.LaravelDeviceToken = passwordBox.Password;
        }
    }

    private void OnLocalCredentialChanged(object sender, RoutedEventArgs e)
    {
        if (DataContext is ConnectionSettingsViewModel viewModel && sender is PasswordBox passwordBox)
        {
            viewModel.LocalServerDeviceCredential = passwordBox.Password;
        }
    }

    private void OnSecretsCleared(object? sender, EventArgs e)
    {
        LaravelTokenPasswordBox.Password = string.Empty;
        LocalCredentialPasswordBox.Password = string.Empty;
    }
}
