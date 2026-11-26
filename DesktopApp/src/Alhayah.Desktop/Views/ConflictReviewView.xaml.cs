using System.Windows;
using System.Windows.Controls;
using System.Windows.Threading;
using Alhayah.Desktop.ViewModels;

namespace Alhayah.Desktop.Views;

public partial class ConflictReviewView : UserControl
{
    private readonly DispatcherTimer _notificationTimer;

    public ConflictReviewView()
    {
        InitializeComponent();
        _notificationTimer = new DispatcherTimer
        {
            Interval = TimeSpan.FromSeconds(15)
        };
        _notificationTimer.Tick += OnNotificationTimerTick;
        Loaded += (_, _) => _notificationTimer.Start();
        Unloaded += (_, _) => _notificationTimer.Stop();
    }

    private async void OnNotificationTimerTick(object? sender, EventArgs e)
    {
        if (DataContext is ConflictReviewViewModel viewModel)
        {
            await viewModel.CheckForNotificationsAsync();
        }
    }
}
