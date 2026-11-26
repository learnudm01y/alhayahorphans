using System.Collections.ObjectModel;
using System.Text.Json;
using System.Windows.Input;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;

namespace Alhayah.Desktop.ViewModels;

public sealed class ConflictFieldViewModel : ViewModelBase
{
    private bool _isSelected;

    public required ConflictFieldReviewData Field { get; init; }

    public string FieldName => Field.FieldName;

    public string BaseValue => Display(Field.BaseValue);

    public string LocalValue => Display(Field.LocalValue);

    public string RemoteValue => Display(Field.RemoteValue);

    public bool IsCritical => Field.Criticality != FieldCriticality.Mergeable;

    public bool IsSelected
    {
        get => _isSelected;
        set => SetProperty(ref _isSelected, value);
    }

    private static string Display(JsonElement? value) => value is null
        ? "غير موجود"
        : value.Value.ValueKind == JsonValueKind.String ? value.Value.GetString() ?? "غير موجود" : value.Value.GetRawText();
}

public sealed class ConflictReviewViewModel : ViewModelBase
{
    private readonly IConflictReviewClient _client;

    private ConflictReviewData? _selectedConflict;
    private string _statusMessage = "لم تُحمّل التعارضات.";
    private bool _isBusy;

    public ConflictReviewViewModel(IConflictReviewClient client)
    {
        _client = client ?? throw new ArgumentNullException(nameof(client));
        LoadCommand = new AsyncRelayCommand(LoadAsync, () => !IsBusy && CanResolveConflicts);
        ResolveServerCommand = new AsyncRelayCommand(() => ResolveAsync("server"), () => CanDecide);
        ResolveLocalCommand = new AsyncRelayCommand(() => ResolveAsync("local"), () => CanDecide);
        ResolveSelectedFieldsCommand = new AsyncRelayCommand(() => ResolveAsync("selected-fields"), () => CanDecide && HasSelectedFields);
        RejectCommand = new AsyncRelayCommand(() => ResolveAsync("rejected"), () => CanDecide);
        _client.NotificationReceived += OnNotificationReceived;
    }

    public event EventHandler? ConflictDetected;

    public ObservableCollection<ConflictReviewData> Conflicts { get; } = [];

    public ObservableCollection<ConflictFieldViewModel> ComparisonFields { get; } = [];

    public AsyncRelayCommand LoadCommand { get; }

    public AsyncRelayCommand ResolveServerCommand { get; }

    public AsyncRelayCommand ResolveLocalCommand { get; }

    public AsyncRelayCommand ResolveSelectedFieldsCommand { get; }

    public AsyncRelayCommand RejectCommand { get; }

    public bool CanResolveConflicts => _client.CanResolveConflicts;

    public bool IsBusy
    {
        get => _isBusy;
        private set
        {
            if (SetProperty(ref _isBusy, value))
            {
                OnPropertyChanged(nameof(CanDecide));
                OnPropertyChanged(nameof(HasSelectedFields));
            }
        }
    }

    public bool CanDecide => CanResolveConflicts && !IsBusy && SelectedConflict is not null &&
        SelectedConflict.Status == "PendingReview";

    public bool HasSelectedFields => ComparisonFields.Any(field => field.IsSelected);

    public int ConflictCount => Conflicts.Count;

    public string StatusMessage
    {
        get => _statusMessage;
        private set => SetProperty(ref _statusMessage, value);
    }

    public ConflictReviewData? SelectedConflict
    {
        get => _selectedConflict;
        set
        {
            if (!SetProperty(ref _selectedConflict, value))
            {
                return;
            }

            ComparisonFields.Clear();
            if (value is not null)
            {
                foreach (ConflictFieldReviewData field in value.Fields)
                {
                    ConflictFieldViewModel model = new() { Field = field };
                    model.PropertyChanged += (_, args) =>
                    {
                        if (args.PropertyName == nameof(ConflictFieldViewModel.IsSelected))
                        {
                            OnPropertyChanged(nameof(HasSelectedFields));
                            CommandManager.InvalidateRequerySuggested();
                        }
                    };
                    ComparisonFields.Add(model);
                }
            }

            OnPropertyChanged(nameof(CanDecide));
            OnPropertyChanged(nameof(HasSelectedFields));
        }
    }

    public async Task LoadAsync()
    {
        IsBusy = true;
        try
        {
            if (!CanResolveConflicts)
            {
                Conflicts.Clear();
                SelectedConflict = null;
                StatusMessage = "مراجعة التعارضات متاحة للمدير فقط.";
                return;
            }

            ConflictListResponse response = await _client.GetConflictsAsync();
            Conflicts.Clear();
            foreach (ConflictReviewData conflict in response.Items)
            {
                Conflicts.Add(conflict);
            }

            SelectedConflict = Conflicts.FirstOrDefault(item => item.Status == "PendingReview")
                ?? Conflicts.FirstOrDefault();
            StatusMessage = Conflicts.Count == 0
                ? "لا توجد تعارضات محفوظة."
                : "القرارات تُحفظ محلياً؛ يبقى القرار منتظراً حتى تأكيد الخادم.";
        }
        catch (UnauthorizedAccessException)
        {
            Conflicts.Clear();
            SelectedConflict = null;
            StatusMessage = "مراجعة التعارضات متاحة للمدير فقط.";
        }
        catch (Exception exception) when (exception is SyncServiceException or InvalidOperationException or JsonException)
        {
            StatusMessage = "تعذر تحميل التعارضات المحلية. أعد المحاولة بعد التحقق من جلسة المدير.";
        }
        finally
        {
            IsBusy = false;
        }
    }

    public async Task CheckForNotificationsAsync()
    {
        if (!CanResolveConflicts || IsBusy)
        {
            return;
        }

        try
        {
            ConflictListResponse response = await _client.GetConflictsAsync();
            bool hasNew = response.Items.Any(item =>
                item.Status is "PendingReview" or "PendingResolution" &&
                Conflicts.All(existing => existing.ConflictId != item.ConflictId));
            if (hasNew)
            {
                StatusMessage = "يوجد تعارض محفوظ يحتاج إلى مراجعة المدير.";
                ConflictDetected?.Invoke(this, EventArgs.Empty);
            }
        }
        catch (Exception exception) when (exception is SyncServiceException or InvalidOperationException or JsonException or UnauthorizedAccessException)
        {
        }
    }

    private async Task ResolveAsync(string decision)
    {
        ConflictReviewData? conflict = SelectedConflict;
        if (!CanDecide || conflict is null || conflict.ExpectedRowVersion is null)
        {
            StatusMessage = "اختر تعارضاً صالحاً قبل حفظ القرار.";
            return;
        }

        string[] selectedFields = decision == "selected-fields"
            ? ComparisonFields.Where(field => field.IsSelected).Select(field => field.FieldName).ToArray()
            : [];
        if (decision == "selected-fields" && selectedFields.Length == 0)
        {
            StatusMessage = "اختر حقلاً واحداً على الأقل من المقارنة.";
            return;
        }

        IsBusy = true;
        try
        {
            ConflictResolutionRequest request = new()
            {
                ConflictId = conflict.ConflictId,
                Decision = decision,
                ExpectedRowVersion = conflict.ExpectedRowVersion,
                ExpectedConflictVersion = conflict.ConflictVersion,
                SelectedFields = selectedFields,
                ResolutionOperationId = Guid.NewGuid()
            };
            ConflictResolutionResponse response = await _client.ResolveConflictAsync(request);
            ConflictReviewData updated = conflict with
            {
                Status = response.Status,
                ConflictVersion = response.ConflictVersion,
                ResolutionOperationId = response.ResolutionOperationId
            };
            ReplaceConflict(updated);
            SelectedConflict = updated;
            StatusMessage = response.Status == "Resolved"
                ? "تم تأكيد القرار من الخادم."
                : response.Status == "Rejected"
                    ? "رفض الخادم القرار؛ راجع النسخة الحالية."
                    : "حُفظ القرار محلياً وهو بانتظار تأكيد الخادم؛ لم يُعد الاتصال مطلوباً لإتمام العمل المحلي.";
        }
        catch (SyncServiceException exception) when (exception.Code is "version_mismatch" or "conflict_version_mismatch" or "stale_decision")
        {
            StatusMessage = "تغير التعارض منذ فتحه؛ أعد تحميله قبل اتخاذ قرار جديد.";
        }
        catch (SyncServiceException)
        {
            StatusMessage = "تعذر تأكيد نتيجة حفظ القرار؛ أعد تحميل التعارض قبل إعادة المحاولة.";
        }
        catch (InvalidOperationException)
        {
            StatusMessage = "تغير التعارض منذ فتحه؛ أعد تحميله قبل اتخاذ قرار جديد.";
        }
        finally
        {
            IsBusy = false;
        }
    }

    private void ReplaceConflict(ConflictReviewData updated)
    {
        int index = Conflicts.ToList().FindIndex(item => item.ConflictId == updated.ConflictId);
        if (index >= 0)
        {
            Conflicts[index] = updated;
        }
    }

    private void OnNotificationReceived(object? sender, ConflictReviewNotification notification)
    {
        if (notification.Status is not ("PendingReview" or "PendingResolution"))
        {
            return;
        }

        StatusMessage = "يوجد تعارض محفوظ يحتاج إلى مراجعة المدير.";
        ConflictDetected?.Invoke(this, EventArgs.Empty);
    }
}
