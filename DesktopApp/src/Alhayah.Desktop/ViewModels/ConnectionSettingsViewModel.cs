using System.Collections.ObjectModel;
using System.Globalization;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;

namespace Alhayah.Desktop.ViewModels;

public sealed record ConnectionCheckStageViewModel
{
    public required string Stage { get; init; }

    public required string StageName { get; init; }

    public required bool Succeeded { get; init; }

    public required string StatusText { get; init; }

    public string? ErrorCode { get; init; }
}

public sealed class ConnectionSettingsViewModel : ViewModelBase
{
    private readonly IConnectionSettingsService _service;
    private readonly ILocalAuthService _authentication;
    private readonly IBackupClient _backupClient;
    private readonly IRestoreClient _restoreClient;
    private ConnectionSettingsData? _originalSettings;
    private string? _diagnosticStatusMessage;
    private string _connectionName = "الاتصال الافتراضي";
    private string _laravelEndpoint = ConnectionSettingsService.DefaultLaravelEndpoint;
    private string _localServerHost = ConnectionSettingsService.DefaultLocalServerHost;
    private string _localServerPort = ConnectionSettingsService.DefaultLocalServerPort.ToString(CultureInfo.InvariantCulture);
    private string _laravelSpkiSha256Pin = string.Empty;
    private string _previousLaravelSpkiSha256Pin = string.Empty;
    private string _localServerSpkiSha256Pin = string.Empty;
    private string _previousLocalServerSpkiSha256Pin = string.Empty;
    private string _requestTimeoutSeconds = ConnectionSettingsService.DefaultRequestTimeoutSeconds.ToString(CultureInfo.InvariantCulture);
    private bool _synchronizationEnabled = true;
    private string _laravelDeviceToken = string.Empty;
    private string _localServerDeviceCredential = string.Empty;
    private string _statusMessage = "الإعدادات المحلية جاهزة؛ فحص الشبكة اختياري.";
    private string _conflictStatusText = "لم تُحمّل التعارضات.";
    private string _backupDestinationDirectory = string.Empty;
    private string _restoreBackupDirectory = string.Empty;
    private string _restoreDestinationDatabasePath = string.Empty;
    private string _restoreDestinationAttachmentDirectory = string.Empty;
    private string _backupResultPath = string.Empty;
    private string _restoreResultDatabasePath = string.Empty;
    private string _restoreResultAttachmentDirectory = string.Empty;
    private string _backupStatusMessage = "لم تُنشأ نسخة احتياطية في هذه الجلسة.";
    private string _restoreStatusMessage = "لم تُطلب استعادة في هذه الجلسة.";
    private DateTimeOffset? _lastLaravelSynchronizedAtUtc;
    private DateTimeOffset? _lastLocalServerSynchronizedAtUtc;

    public ConnectionSettingsViewModel(
        IConnectionSettingsService service,
        ILocalAuthService authentication,
        IBackupClient backupClient,
        IRestoreClient restoreClient)
    {
        _service = service ?? throw new ArgumentNullException(nameof(service));
        _authentication = authentication ?? throw new ArgumentNullException(nameof(authentication));
        _backupClient = backupClient ?? throw new ArgumentNullException(nameof(backupClient));
        _restoreClient = restoreClient ?? throw new ArgumentNullException(nameof(restoreClient));
        SaveCommand = new AsyncRelayCommand(SaveAsync);
        RestoreDefaultsCommand = new AsyncRelayCommand(RestoreDefaultsAsync);
        CheckLaravelConnectionCommand = new AsyncRelayCommand(() => CheckConnectionAsync("laravel"));
        CheckLocalServerConnectionCommand = new AsyncRelayCommand(() => CheckConnectionAsync("local"));
        SynchronizeLaravelNowCommand = new AsyncRelayCommand(() => SynchronizeNowAsync("laravel"));
        SynchronizeLocalServerNowCommand = new AsyncRelayCommand(() => SynchronizeNowAsync("local"));
        CreateBackupCommand = new AsyncRelayCommand(CreateBackupAsync);
        RestoreBackupCommand = new AsyncRelayCommand(RestoreBackupAsync);
    }

    public event EventHandler? SecretsCleared;

    public ObservableCollection<ConnectionCheckStageViewModel> ConnectionCheckStages { get; } = [];

    public ObservableCollection<ConflictReviewData> Conflicts { get; } = [];

    public AsyncRelayCommand SaveCommand { get; }

    public AsyncRelayCommand RestoreDefaultsCommand { get; }

    public AsyncRelayCommand CheckLaravelConnectionCommand { get; }

    public AsyncRelayCommand CheckLocalServerConnectionCommand { get; }

    public AsyncRelayCommand SynchronizeLaravelNowCommand { get; }

    public AsyncRelayCommand SynchronizeLocalServerNowCommand { get; }

    public AsyncRelayCommand CreateBackupCommand { get; }

    public AsyncRelayCommand RestoreBackupCommand { get; }

    public bool IsAdministrator => _authentication.CanManageSettings;

    public bool CanResolveConflicts => _authentication.CanResolveConflicts;

    public bool CanSynchronize => _authentication.HasPermission(LocalPermissions.Synchronize);

    public bool CanBackup => _authentication.HasPermission(LocalPermissions.Backup);

    public bool CanRestore => _authentication.HasPermission(LocalPermissions.Restore);

    public int ConflictCount => Conflicts.Count;

    public string OutboxStatusText => "العمليات المعلقة محفوظة؛ تغيير الاتصال واستعادة الافتراضيات لا يحذفان outbox أو ذاكرة التخزين المؤقت.";

    public string ConnectionName
    {
        get => _connectionName;
        set => SetProperty(ref _connectionName, value);
    }

    public string LaravelEndpoint
    {
        get => _laravelEndpoint;
        set => SetProperty(ref _laravelEndpoint, value);
    }

    public string LocalServerHost
    {
        get => _localServerHost;
        set => SetProperty(ref _localServerHost, value);
    }

    public string LocalServerPort
    {
        get => _localServerPort;
        set => SetProperty(ref _localServerPort, value);
    }

    public string LaravelSpkiSha256Pin
    {
        get => _laravelSpkiSha256Pin;
        set => SetProperty(ref _laravelSpkiSha256Pin, value);
    }

    public string PreviousLaravelSpkiSha256Pin
    {
        get => _previousLaravelSpkiSha256Pin;
        set => SetProperty(ref _previousLaravelSpkiSha256Pin, value);
    }

    public string LocalServerSpkiSha256Pin
    {
        get => _localServerSpkiSha256Pin;
        set => SetProperty(ref _localServerSpkiSha256Pin, value);
    }

    public string PreviousLocalServerSpkiSha256Pin
    {
        get => _previousLocalServerSpkiSha256Pin;
        set => SetProperty(ref _previousLocalServerSpkiSha256Pin, value);
    }

    public string RequestTimeoutSeconds
    {
        get => _requestTimeoutSeconds;
        set => SetProperty(ref _requestTimeoutSeconds, value);
    }

    public bool SynchronizationEnabled
    {
        get => _synchronizationEnabled;
        set => SetProperty(ref _synchronizationEnabled, value);
    }

    public string LaravelDeviceToken
    {
        get => _laravelDeviceToken;
        set => SetProperty(ref _laravelDeviceToken, value);
    }

    public string LocalServerDeviceCredential
    {
        get => _localServerDeviceCredential;
        set => SetProperty(ref _localServerDeviceCredential, value);
    }

    public string BackupDestinationDirectory
    {
        get => _backupDestinationDirectory;
        set => SetProperty(ref _backupDestinationDirectory, value);
    }

    public string RestoreBackupDirectory
    {
        get => _restoreBackupDirectory;
        set => SetProperty(ref _restoreBackupDirectory, value);
    }

    public string RestoreDestinationDatabasePath
    {
        get => _restoreDestinationDatabasePath;
        set => SetProperty(ref _restoreDestinationDatabasePath, value);
    }

    public string RestoreDestinationAttachmentDirectory
    {
        get => _restoreDestinationAttachmentDirectory;
        set => SetProperty(ref _restoreDestinationAttachmentDirectory, value);
    }

    public string BackupResultPath
    {
        get => _backupResultPath;
        private set => SetProperty(ref _backupResultPath, value);
    }

    public string RestoreResultDatabasePath
    {
        get => _restoreResultDatabasePath;
        private set => SetProperty(ref _restoreResultDatabasePath, value);
    }

    public string RestoreResultAttachmentDirectory
    {
        get => _restoreResultAttachmentDirectory;
        private set => SetProperty(ref _restoreResultAttachmentDirectory, value);
    }

    public string BackupStatusMessage
    {
        get => _backupStatusMessage;
        private set => SetProperty(ref _backupStatusMessage, value);
    }

    public string RestoreStatusMessage
    {
        get => _restoreStatusMessage;
        private set => SetProperty(ref _restoreStatusMessage, value);
    }

    public string StatusMessage
    {
        get => _statusMessage;
        private set => SetProperty(ref _statusMessage, value);
    }

    public string ConflictStatusText
    {
        get => _conflictStatusText;
        private set => SetProperty(ref _conflictStatusText, value);
    }

    public DateTimeOffset? LastLaravelSynchronizedAtUtc
    {
        get => _lastLaravelSynchronizedAtUtc;
        private set
        {
            if (SetProperty(ref _lastLaravelSynchronizedAtUtc, value))
            {
                OnPropertyChanged(nameof(LastLaravelSynchronizationText));
            }
        }
    }

    public DateTimeOffset? LastLocalServerSynchronizedAtUtc
    {
        get => _lastLocalServerSynchronizedAtUtc;
        private set
        {
            if (SetProperty(ref _lastLocalServerSynchronizedAtUtc, value))
            {
                OnPropertyChanged(nameof(LastLocalServerSynchronizationText));
            }
        }
    }

    public string LastLaravelSynchronizationText => FormatLastSynchronization(LastLaravelSynchronizedAtUtc);

    public string LastLocalServerSynchronizationText => FormatLastSynchronization(LastLocalServerSynchronizedAtUtc);

    public async Task LoadAsync()
    {
        NotifyPermissionsChanged();
        _diagnosticStatusMessage = null;
        if (!IsAdministrator)
        {
            Conflicts.Clear();
            OnPropertyChanged(nameof(ConflictCount));
            StatusMessage = "عرض إعدادات الاتصال وتعديلها ومراجعة التعارضات متاح للمدير فقط.";
            ConflictStatusText = "مراجعة التعارضات متاحة للمدير فقط.";
            return;
        }

        try
        {
            ConnectionSettingsData settings = await _service.GetSettingsAsync();
            ApplySettings(settings);
            StatusMessage = "تم تحميل الإعدادات عبر خدمة المزامنة دون فحص شبكي.";
        }
        catch (UnauthorizedAccessException)
        {
            StatusMessage = "إعدادات الاتصال متاحة للمدير فقط.";
        }
        catch (Exception)
        {
            StatusMessage = "تعذر تحميل إعدادات الاتصال؛ تبقى الأعمال المحلية متاحة.";
        }

        await LoadConflictsAsync();
        RefreshLastSynchronizationTimes();
    }

    private async Task SaveAsync()
    {
        if (!EnsureAdministrator())
        {
            return;
        }

        try
        {
            ConnectionSettingsData? settings = BuildSettings();
            if (settings is null)
            {
                return;
            }

            ConnectionSecrets? secrets = CreateTransientSecrets();
            await _service.SaveAsync(settings, secrets);
            _originalSettings = settings;
            StatusMessage = _diagnosticStatusMessage is null
                ? "تم حفظ الإعدادات عبر IPC دون انتظار فحص الشبكة؛ لم تتأثر الأعمال المحلية."
                : $"{_diagnosticStatusMessage} تم حفظ الإعدادات دون تغيير الأعمال المحلية.";
        }
        catch (UnauthorizedAccessException)
        {
            StatusMessage = "حفظ الإعدادات وتدوير البصمات متاح للمدير فقط.";
        }
        catch (OperationCanceledException)
        {
            StatusMessage = "أُلغي حفظ الإعدادات؛ تبقى الأعمال المحلية كما هي.";
        }
        catch (Exception)
        {
            StatusMessage = "تعذر حفظ الإعدادات عبر خدمة المزامنة؛ تبقى الأعمال المحلية متاحة.";
        }
        finally
        {
            ClearTransientSecrets();
        }
    }

    private async Task RestoreDefaultsAsync()
    {
        if (!EnsureAdministrator())
        {
            return;
        }

        try
        {
            await _service.RestoreDefaultsAsync();
            ConnectionSettingsData settings = await _service.GetSettingsAsync();
            ApplySettings(settings);
            _diagnosticStatusMessage = null;
            StatusMessage = "استُعيدت افتراضيات الاتصال فقط؛ لم تُمسح outbox أو ذاكرة التخزين المؤقت أو بيانات الأعمال.";
        }
        catch (UnauthorizedAccessException)
        {
            StatusMessage = "استعادة الافتراضيات متاحة للمدير فقط.";
        }
        catch (Exception)
        {
            StatusMessage = "تعذرت استعادة الافتراضيات؛ لم تُمسح أي أعمال محلية.";
        }
        finally
        {
            ClearTransientSecrets();
        }
    }

    private async Task CheckConnectionAsync(string sourcePrefix)
    {
        if (!EnsureAdministrator())
        {
            return;
        }

        string? sourceId = CreateSourceId(sourcePrefix);
        if (sourceId is null)
        {
            StatusMessage = "لا تتوفر هوية مصدر صالحة للفحص؛ تبقى الأعمال المحلية متاحة.";
            return;
        }

        try
        {
            StatusMessage = "جارٍ فحص الاتصال عبر خدمة المزامنة؛ يمكن متابعة الأعمال المحلية والحفظ.";
            ConnectionCheckStages.Clear();
            ConnectionCheckResult result = await _service.CheckConnectionAsync(sourceId);
            foreach (ConnectionCheckStageResult stage in result.Stages)
            {
                ConnectionCheckStages.Add(new ConnectionCheckStageViewModel
                {
                    Stage = stage.Stage,
                    StageName = LocalizeStage(stage.Stage),
                    Succeeded = stage.Succeeded,
                    StatusText = stage.Succeeded ? "ناجح" : "فشل",
                    ErrorCode = stage.ErrorCode
                });
            }

            ConnectionCheckStageResult? failedTls = result.Stages.FirstOrDefault(stage =>
                stage.Stage.Equals("TLS", StringComparison.OrdinalIgnoreCase) && !stage.Succeeded);
            if (failedTls is not null && IsPinOrCertificateFailure(failedTls.ErrorCode))
            {
                _diagnosticStatusMessage = "فشل التحقق من بصمة الشهادة؛ راجع SPKI ولا تتجاوز التحقق.";
            }
            else if (result.Stages.Any(stage => !stage.Succeeded))
            {
                _diagnosticStatusMessage = "فشل فحص الشبكة؛ لا يمنع ذلك الحفظ أو الأعمال المحلية.";
            }
            else
            {
                _diagnosticStatusMessage = null;
            }

            StatusMessage = _diagnosticStatusMessage ?? "نجحت مراحل DNS وTCP وTLS والمصادقة والتوافق.";
        }
        catch (UnauthorizedAccessException)
        {
            StatusMessage = "فحص الاتصال متاح للمدير فقط؛ تبقى الأعمال المحلية متاحة.";
        }
        catch (Exception)
        {
            _diagnosticStatusMessage = "تعذر فحص الشبكة عبر خدمة المزامنة؛ لا يمنع ذلك الحفظ أو الأعمال المحلية.";
            StatusMessage = _diagnosticStatusMessage;
        }
    }

    private async Task SynchronizeNowAsync(string sourcePrefix)
    {
        if (!CanSynchronize)
        {
            StatusMessage = "المزامنة اليدوية تتطلب صلاحية المزامنة في الجلسة المحلية.";
            return;
        }

        string? sourceId = CreateSourceId(sourcePrefix);
        if (sourceId is null)
        {
            StatusMessage = "لا تتوفر هوية مصدر ثابتة للمزامنة؛ لم تتأثر الأعمال المحلية.";
            return;
        }

        try
        {
            SynchronizeNowResult result = await _service.SynchronizeNowAsync(sourceId);
            RefreshLastSynchronizationTimes();
            StatusMessage = result.Status.Equals("Completed", StringComparison.OrdinalIgnoreCase) ||
                            result.Status.Equals("Succeeded", StringComparison.OrdinalIgnoreCase)
                ? "اكتملت المزامنة للمصدر المحدد مع الحفاظ على outbox والتعارضات."
                : "تم قبول طلب المزامنة؛ ستستمر الخدمة في الخلفية مع الحفاظ على الأعمال المحلية.";
        }
        catch (UnauthorizedAccessException)
        {
            StatusMessage = "المزامنة اليدوية غير مصرح بها لهذه الجلسة.";
        }
        catch (Exception)
        {
            StatusMessage = "تعذرت المزامنة الآن؛ بقيت outbox والأعمال المحلية محفوظة.";
        }
    }

    private async Task CreateBackupAsync()
    {
        if (!CanBackup)
        {
            BackupStatusMessage = "إنشاء النسخة الاحتياطية غير مصرح لهذه الجلسة.";
            return;
        }

        BackupResultPath = string.Empty;
        try
        {
            BackupStatusMessage = "جارٍ إنشاء النسخة الاحتياطية عبر SyncService دون فتح قاعدة البيانات في الواجهة.";
            BackupResponse response = await _backupClient.CreateBackupAsync(BackupDestinationDirectory);
            BackupResultPath = response.DirectoryPath;
            BackupStatusMessage = "تم إنشاء النسخة الاحتياطية بعد تأكيد SyncService.";
        }
        catch (UnauthorizedAccessException)
        {
            BackupStatusMessage = "إنشاء النسخة الاحتياطية غير مصرح لهذه الجلسة.";
        }
        catch (OperationCanceledException)
        {
            BackupStatusMessage = "أُلغي إنشاء النسخة الاحتياطية؛ لم يُسجل نجاح.";
        }
        catch (TimeoutException)
        {
            BackupStatusMessage = "انتهت مهلة النسخ الاحتياطي دون تأكيد نجاح من SyncService.";
        }
        catch (ArgumentException)
        {
            BackupStatusMessage = "مجلد النسخ الاحتياطي يجب أن يكون مساراً محلياً كاملاً وليس مسار شبكة.";
        }
        catch (Exception)
        {
            BackupStatusMessage = "تعذر إنشاء النسخة الاحتياطية عبر SyncService؛ لم يُعرض نجاح.";
        }
    }

    private async Task RestoreBackupAsync()
    {
        if (!CanRestore)
        {
            RestoreStatusMessage = "الاستعادة غير مصرح بها لهذه الجلسة.";
            return;
        }

        RestoreResultDatabasePath = string.Empty;
        RestoreResultAttachmentDirectory = string.Empty;
        try
        {
            RestoreStatusMessage = "جارٍ التحقق من النسخة والاستعادة إلى وجهة جديدة عبر SyncService.";
            RestoreResponse response = await _restoreClient.RestoreBackupAsync(
                RestoreBackupDirectory,
                RestoreDestinationDatabasePath,
                RestoreDestinationAttachmentDirectory);
            RestoreResultDatabasePath = response.DestinationDatabasePath;
            RestoreResultAttachmentDirectory = response.DestinationAttachmentDirectory;
            RestoreStatusMessage = "اكتملت الاستعادة إلى وجهة التحقق الجديدة بعد تأكيد SyncService؛ لم تُستبدل قاعدة الجهاز الحية.";
        }
        catch (UnauthorizedAccessException)
        {
            RestoreStatusMessage = "الاستعادة غير مصرح بها لهذه الجلسة.";
        }
        catch (OperationCanceledException)
        {
            RestoreStatusMessage = "أُلغيت الاستعادة؛ لم يُعرض نجاح.";
        }
        catch (TimeoutException)
        {
            RestoreStatusMessage = "انتهت مهلة الاستعادة دون تأكيد نجاح من SyncService.";
        }
        catch (ArgumentException)
        {
            RestoreStatusMessage = "مسارات النسخة والوجهة يجب أن تكون محلية كاملة وليست مسارات شبكة.";
        }
        catch (Exception)
        {
            RestoreStatusMessage = "تعذرت الاستعادة عبر SyncService؛ الخدمة تمنع الكتابة فوق المخزن الحي أو الوجهة الموجودة.";
        }
    }

    private async Task LoadConflictsAsync()
    {
        if (!CanResolveConflicts)
        {
            Conflicts.Clear();
            OnPropertyChanged(nameof(ConflictCount));
            ConflictStatusText = "مراجعة التعارضات متاحة للمدير فقط.";
            return;
        }

        try
        {
            IReadOnlyList<ConflictReviewData> conflicts = await _service.GetConflictsAsync();
            Conflicts.Clear();
            foreach (ConflictReviewData conflict in conflicts)
            {
                Conflicts.Add(conflict);
            }

            OnPropertyChanged(nameof(ConflictCount));
            ConflictStatusText = conflicts.Count == 0
                ? "لا توجد تعارضات معلقة."
                : $"عدد التعارضات المعلقة: {conflicts.Count.ToString(CultureInfo.InvariantCulture)}. مراجعتها متاحة للمدير فقط.";
        }
        catch (Exception)
        {
            ConflictStatusText = "تعذر تحميل التعارضات؛ لم تتأثر الأعمال المحلية أو outbox.";
        }
    }

    private ConnectionSettingsData? BuildSettings()
    {
        if (string.IsNullOrWhiteSpace(ConnectionName))
        {
            StatusMessage = "اسم الاتصال مطلوب.";
            return null;
        }

        if (!TryNormalizeLaravelEndpoint(out string laravelEndpoint))
        {
            StatusMessage = "عنوان Laravel يجب أن يكون HTTPS صالحاً دون بيانات اعتماد أو استعلام.";
            return null;
        }

        if (!TryBuildLocalServerEndpoint(out string localServerEndpoint))
        {
            StatusMessage = "اسم السيرفر المحلي والمنفذ مطلوبان؛ المنفذ بين 1 و65535.";
            return null;
        }

        if (!int.TryParse(RequestTimeoutSeconds, NumberStyles.None, CultureInfo.InvariantCulture, out int timeout) ||
            timeout is < 1 or > 300)
        {
            StatusMessage = "مهلة الطلب يجب أن تكون بين 1 و300 ثانية.";
            return null;
        }

        string laravelPin = NormalizePin(LaravelSpkiSha256Pin);
        string localPin = NormalizePin(LocalServerSpkiSha256Pin);
        string previousLaravelPin = NormalizePin(PreviousLaravelSpkiSha256Pin);
        string previousLocalPin = NormalizePin(PreviousLocalServerSpkiSha256Pin);
        if (!ConnectionSettingsService.IsValidSpkiSha256Pin(laravelPin) ||
            !ConnectionSettingsService.IsValidSpkiSha256Pin(localPin) ||
            (previousLaravelPin.Length > 0 && !ConnectionSettingsService.IsValidSpkiSha256Pin(previousLaravelPin)) ||
            (previousLocalPin.Length > 0 && !ConnectionSettingsService.IsValidSpkiSha256Pin(previousLocalPin)))
        {
            StatusMessage = "كل بصمة SPKI SHA-256 يجب أن تتكون من 64 محرفاً سداسياً.";
            return null;
        }

        if (!ValidateRotation(
                _originalSettings?.LaravelSpkiSha256Pin,
                laravelPin,
                previousLaravelPin,
                "Laravel") ||
            !ValidateRotation(
                _originalSettings?.LocalServerSpkiSha256Pin,
                localPin,
                previousLocalPin,
                "السيرفر المحلي"))
        {
            return null;
        }

        return new ConnectionSettingsData
        {
            ConnectionName = ConnectionName.Trim(),
            LaravelEndpoint = laravelEndpoint,
            LocalServerEndpoint = localServerEndpoint,
            LaravelSpkiSha256Pin = laravelPin,
            PreviousLaravelSpkiSha256Pin = previousLaravelPin.Length == 0 ? null : previousLaravelPin,
            LocalServerSpkiSha256Pin = localPin,
            PreviousLocalServerSpkiSha256Pin = previousLocalPin.Length == 0 ? null : previousLocalPin,
            RequestTimeoutSeconds = timeout,
            SynchronizationEnabled = SynchronizationEnabled
        };
    }

    private bool ValidateRotation(string? originalPin, string currentPin, string previousPin, string sourceName)
    {
        if (previousPin.Length > 0 && previousPin.Equals(currentPin, StringComparison.Ordinal))
        {
            StatusMessage = $"بصمة {sourceName} السابقة يجب أن تختلف عن الحالية أثناء التدوير المتداخل.";
            return false;
        }

        if (originalPin is not null &&
            !currentPin.Equals(originalPin, StringComparison.Ordinal) &&
            !previousPin.Equals(originalPin, StringComparison.Ordinal))
        {
            StatusMessage = $"عند تدوير بصمة {sourceName} يجب إدخال البصمة السابقة المحفوظة مع الجديدة لضمان التداخل.";
            return false;
        }

        return true;
    }

    private bool TryNormalizeLaravelEndpoint(out string normalizedEndpoint)
    {
        normalizedEndpoint = string.Empty;
        string value = LaravelEndpoint.Trim();
        if (!Uri.TryCreate(value, UriKind.Absolute, out Uri? endpoint) ||
            !endpoint.Scheme.Equals(Uri.UriSchemeHttps, StringComparison.Ordinal) ||
            string.IsNullOrWhiteSpace(endpoint.Host) ||
            endpoint.UserInfo.Length > 0 ||
            endpoint.Query.Length > 0 ||
            endpoint.Fragment.Length > 0)
        {
            return false;
        }

        normalizedEndpoint = endpoint.GetLeftPart(UriPartial.Path).TrimEnd('/');
        return true;
    }

    private bool TryBuildLocalServerEndpoint(out string endpoint)
    {
        endpoint = string.Empty;
        string host = LocalServerHost.Trim();
        if (host.Length == 0 ||
            host.Contains("://", StringComparison.Ordinal) ||
            !int.TryParse(LocalServerPort, NumberStyles.None, CultureInfo.InvariantCulture, out int port) ||
            port is < 1 or > 65535)
        {
            return false;
        }

        try
        {
            UriBuilder builder = new(Uri.UriSchemeHttps, host, port);
            endpoint = builder.Uri.GetLeftPart(UriPartial.Authority);
            return true;
        }
        catch (UriFormatException)
        {
            return false;
        }
    }

    private void ApplySettings(ConnectionSettingsData settings)
    {
        _originalSettings = settings;
        ConnectionName = settings.ConnectionName;
        LaravelEndpoint = settings.LaravelEndpoint;
        Uri localEndpoint = new(settings.LocalServerEndpoint, UriKind.Absolute);
        LocalServerHost = localEndpoint.Host;
        LocalServerPort = localEndpoint.Port.ToString(CultureInfo.InvariantCulture);
        LaravelSpkiSha256Pin = settings.LaravelSpkiSha256Pin;
        PreviousLaravelSpkiSha256Pin = settings.PreviousLaravelSpkiSha256Pin ?? string.Empty;
        LocalServerSpkiSha256Pin = settings.LocalServerSpkiSha256Pin;
        PreviousLocalServerSpkiSha256Pin = settings.PreviousLocalServerSpkiSha256Pin ?? string.Empty;
        RequestTimeoutSeconds = settings.RequestTimeoutSeconds.ToString(CultureInfo.InvariantCulture);
        SynchronizationEnabled = settings.SynchronizationEnabled;
    }

    private ConnectionSecrets? CreateTransientSecrets()
    {
        if (string.IsNullOrWhiteSpace(LaravelDeviceToken) &&
            string.IsNullOrWhiteSpace(LocalServerDeviceCredential))
        {
            return null;
        }

        return new ConnectionSecrets
        {
            LaravelDeviceToken = string.IsNullOrWhiteSpace(LaravelDeviceToken) ? null : LaravelDeviceToken,
            LocalServerDeviceCredential = string.IsNullOrWhiteSpace(LocalServerDeviceCredential) ? null : LocalServerDeviceCredential
        };
    }

    private void ClearTransientSecrets()
    {
        LaravelDeviceToken = string.Empty;
        LocalServerDeviceCredential = string.Empty;
        SecretsCleared?.Invoke(this, EventArgs.Empty);
    }

    private bool EnsureAdministrator()
    {
        if (IsAdministrator)
        {
            return true;
        }

        StatusMessage = "هذا الإجراء متاح للمدير فقط؛ يمكن متابعة الأعمال المحلية المصرح بها.";
        return false;
    }

    private string? CreateSourceId(string sourcePrefix)
    {
        string? sessionSourceId = _authentication.CurrentSession?.SourceId;
        if (sessionSourceId is null)
        {
            return null;
        }

        try
        {
            SyncSourceId sessionSource = new(sessionSourceId);
            string installationId = sessionSource.Value.Split(':', 2)[1];
            return new SyncSourceId($"{sourcePrefix}:{installationId}").Value;
        }
        catch (ArgumentException)
        {
            return null;
        }
    }

    private void RefreshLastSynchronizationTimes()
    {
        string? laravelSourceId = CreateSourceId("laravel");
        string? localSourceId = CreateSourceId("local");
        LastLaravelSynchronizedAtUtc = laravelSourceId is null ? null : _service.GetLastSynchronizedAtUtc(laravelSourceId);
        LastLocalServerSynchronizedAtUtc = localSourceId is null ? null : _service.GetLastSynchronizedAtUtc(localSourceId);
    }

    private void NotifyPermissionsChanged()
    {
        OnPropertyChanged(nameof(IsAdministrator));
        OnPropertyChanged(nameof(CanResolveConflicts));
        OnPropertyChanged(nameof(CanSynchronize));
        OnPropertyChanged(nameof(CanBackup));
        OnPropertyChanged(nameof(CanRestore));
    }

    private static string NormalizePin(string value) => value.Trim().ToUpperInvariant();

    private static bool IsPinOrCertificateFailure(string? errorCode) => errorCode is not null &&
        (errorCode.Contains("pin", StringComparison.OrdinalIgnoreCase) ||
         errorCode.Contains("certificate", StringComparison.OrdinalIgnoreCase));

    private static string LocalizeStage(string stage) => stage.ToUpperInvariant() switch
    {
        "DNS" => "تحليل اسم المجال DNS",
        "TCP" => "اتصال TCP",
        "TLS" => "أمان TLS وبصمة SPKI",
        "AUTH" => "المصادقة",
        "COMPATIBILITY" => "توافق الإصدارات",
        _ => stage
    };

    private static string FormatLastSynchronization(DateTimeOffset? value) => value is null
        ? "لم تُسجل مزامنة ناجحة في هذه الجلسة."
        : $"آخر مزامنة ناجحة في هذه الجلسة: {value.Value:yyyy-MM-dd HH:mm} UTC";
}
