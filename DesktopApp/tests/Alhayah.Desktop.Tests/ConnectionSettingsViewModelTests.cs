using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Alhayah.Desktop.Utilities;
using Alhayah.Desktop.ViewModels;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class ConnectionSettingsViewModelTests
{
    private const string PinA = "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";
    private const string PinB = "BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB";
    private const string PinC = "CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC";
    private const string PinD = "DDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD";
    private const string PinE = "EEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEE";

    [Fact]
    public async Task Save_WithoutConnectionCheck_PersistsAndReloadsThroughFakeIpc()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        viewModel.ConnectionName = "اتصال اصطناعي محفوظ";
        viewModel.LaravelEndpoint = "https://laravel.synthetic.invalid/api";
        viewModel.LocalServerHost = "local.synthetic.invalid";
        viewModel.LocalServerPort = "9443";
        viewModel.RequestTimeoutSeconds = "45";
        viewModel.SynchronizationEnabled = false;
        viewModel.LaravelDeviceToken = "synthetic-transient-laravel-secret";
        viewModel.LocalServerDeviceCredential = "synthetic-transient-local-secret";

        await viewModel.SaveCommand.ExecuteAsync(null);

        SaveConnectionSettingsRequest saved = Assert.Single(fixture.SaveRequests);
        Assert.Equal("اتصال اصطناعي محفوظ", saved.Settings.ConnectionName);
        Assert.Equal("https://laravel.synthetic.invalid/api", saved.Settings.LaravelEndpoint);
        Assert.Equal("https://local.synthetic.invalid:9443", saved.Settings.LocalServerEndpoint);
        Assert.Equal(45, saved.Settings.RequestTimeoutSeconds);
        Assert.False(saved.Settings.SynchronizationEnabled);
        Assert.Empty(fixture.CheckedSources);
        Assert.Equal("synthetic-transient-laravel-secret", fixture.LastSecrets?.LaravelDeviceToken);
        Assert.Equal("synthetic-transient-local-secret", fixture.LastSecrets?.LocalServerDeviceCredential);
        Assert.Equal(string.Empty, viewModel.LaravelDeviceToken);
        Assert.Equal(string.Empty, viewModel.LocalServerDeviceCredential);

        ConnectionSettingsViewModel recreated = await fixture.CreateLoadedViewModelAsync();
        Assert.Equal("اتصال اصطناعي محفوظ", recreated.ConnectionName);
        Assert.Equal("https://laravel.synthetic.invalid/api", recreated.LaravelEndpoint);
        Assert.Equal("local.synthetic.invalid", recreated.LocalServerHost);
        Assert.Equal("9443", recreated.LocalServerPort);
        Assert.Equal("45", recreated.RequestTimeoutSeconds);
        Assert.False(recreated.SynchronizationEnabled);
        Assert.Equal(string.Empty, recreated.LaravelDeviceToken);
        Assert.Equal(string.Empty, recreated.LocalServerDeviceCredential);
        string returnedSettingsJson = JsonSerializer.Serialize(fixture.CurrentSettings);
        Assert.DoesNotContain("synthetic-transient-laravel-secret", returnedSettingsJson, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-transient-local-secret", returnedSettingsJson, StringComparison.Ordinal);
    }

    [Fact]
    public async Task CheckConnection_WithPinMismatch_ReportsFailureAndDoesNotBlockSave()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        fixture.CheckResultFactory = sourceId => new ConnectionCheckResult
        {
            SourceId = sourceId,
            Stages =
            [
                new ConnectionCheckStageResult { Stage = "DNS", Succeeded = true },
                new ConnectionCheckStageResult { Stage = "TCP", Succeeded = true },
                new ConnectionCheckStageResult { Stage = "TLS", Succeeded = false, ErrorCode = "spki_pin_mismatch" },
                new ConnectionCheckStageResult { Stage = "Auth", Succeeded = false, ErrorCode = "not_attempted" },
                new ConnectionCheckStageResult { Stage = "Compatibility", Succeeded = false, ErrorCode = "not_attempted" }
            ]
        };
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();

        await viewModel.CheckLaravelConnectionCommand.ExecuteAsync(null);
        await viewModel.SaveCommand.ExecuteAsync(null);

        Assert.Equal("spki_pin_mismatch", Assert.Single(viewModel.ConnectionCheckStages, stage => stage.Stage == "TLS").ErrorCode);
        Assert.Contains("بصمة الشهادة", viewModel.StatusMessage, StringComparison.Ordinal);
        Assert.Single(fixture.SaveRequests);
        Assert.Equal(2, fixture.OutboxOperationCount);
    }

    [Fact]
    public async Task CheckConnection_WhenNetworkCheckFails_LocalSaveRemainsAvailable()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        fixture.CheckErrorCode = "dns_unavailable";
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();

        await viewModel.CheckLocalServerConnectionCommand.ExecuteAsync(null);
        await viewModel.SaveCommand.ExecuteAsync(null);

        Assert.Contains("الشبكة", viewModel.StatusMessage, StringComparison.Ordinal);
        Assert.Single(fixture.SaveRequests);
        Assert.Equal(2, fixture.OutboxOperationCount);
    }

    [Fact]
    public async Task Save_WithMalformedPin_IsRejectedBeforeIpc()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        viewModel.LaravelSpkiSha256Pin = "damaged-pin";

        await viewModel.SaveCommand.ExecuteAsync(null);

        Assert.Empty(fixture.SaveRequests);
        Assert.Contains("64", viewModel.StatusMessage, StringComparison.Ordinal);
    }

    [Fact]
    public async Task Save_WithUnexpectedPreviousPin_IsRejectedBeforeIpc()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        viewModel.LaravelSpkiSha256Pin = PinB;
        viewModel.PreviousLaravelSpkiSha256Pin = PinC;

        await viewModel.SaveCommand.ExecuteAsync(null);

        Assert.Empty(fixture.SaveRequests);
        Assert.Contains("السابقة", viewModel.StatusMessage, StringComparison.Ordinal);
    }

    [Fact]
    public async Task Save_WithOverlappingRotationPins_SendsCurrentAndPreviousPinsForBothSources()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        viewModel.LaravelSpkiSha256Pin = PinB;
        viewModel.PreviousLaravelSpkiSha256Pin = PinA;
        viewModel.LocalServerSpkiSha256Pin = PinE;
        viewModel.PreviousLocalServerSpkiSha256Pin = PinD;

        await viewModel.SaveCommand.ExecuteAsync(null);

        SaveConnectionSettingsRequest saved = Assert.Single(fixture.SaveRequests);
        Assert.Equal(PinB, saved.Settings.LaravelSpkiSha256Pin);
        Assert.Equal(PinA, saved.Settings.PreviousLaravelSpkiSha256Pin);
        Assert.Equal(PinE, saved.Settings.LocalServerSpkiSha256Pin);
        Assert.Equal(PinD, saved.Settings.PreviousLocalServerSpkiSha256Pin);
        Assert.Equal(2, fixture.OutboxOperationCount);
    }

    [Fact]
    public async Task NonAdministrator_SaveRotationAndConflictAccess_AreDeniedWithArabicMessage()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync(isAdministrator: false);
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        viewModel.LaravelSpkiSha256Pin = PinB;
        viewModel.PreviousLaravelSpkiSha256Pin = PinA;

        await viewModel.SaveCommand.ExecuteAsync(null);
        await viewModel.RestoreDefaultsCommand.ExecuteAsync(null);

        Assert.False(viewModel.IsAdministrator);
        Assert.False(viewModel.CanResolveConflicts);
        Assert.Contains("المدير", viewModel.StatusMessage, StringComparison.Ordinal);
        Assert.Contains("المدير", viewModel.ConflictStatusText, StringComparison.Ordinal);
        Assert.Empty(fixture.SaveRequests);
        Assert.DoesNotContain(SyncServiceCommands.GetSettings, fixture.Host.Commands);
        Assert.DoesNotContain(SyncServiceCommands.ListConflicts, fixture.Host.Commands);
    }

    [Fact]
    public async Task RestoreDefaults_ChangesOnlyConnectionSettingsAndPreservesOutboxAndPins()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();
        viewModel.RequestTimeoutSeconds = "120";
        viewModel.SynchronizationEnabled = false;

        await viewModel.RestoreDefaultsCommand.ExecuteAsync(null);

        SaveConnectionSettingsRequest restored = Assert.Single(fixture.SaveRequests);
        Assert.Equal("https://localhost", restored.Settings.LaravelEndpoint);
        Assert.Equal("https://localhost:8443", restored.Settings.LocalServerEndpoint);
        Assert.Equal(30, restored.Settings.RequestTimeoutSeconds);
        Assert.True(restored.Settings.SynchronizationEnabled);
        Assert.Equal(PinA, restored.Settings.LaravelSpkiSha256Pin);
        Assert.Equal(PinD, restored.Settings.LocalServerSpkiSha256Pin);
        Assert.Equal(2, fixture.OutboxOperationCount);
        Assert.DoesNotContain(fixture.Host.Commands, command => command.Contains("outbox", StringComparison.OrdinalIgnoreCase));
    }

    [Fact]
    public async Task CheckConnection_WithAllStages_DisplaysDnsTcpTlsAuthAndCompatibility()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();

        await viewModel.CheckLaravelConnectionCommand.ExecuteAsync(null);

        Assert.Equal(
            ["DNS", "TCP", "TLS", "Auth", "Compatibility"],
            viewModel.ConnectionCheckStages.Select(stage => stage.Stage));
        Assert.All(viewModel.ConnectionCheckStages, stage => Assert.True(stage.Succeeded));
    }

    [Fact]
    public async Task SynchronizeNow_ForEachSource_UsesStableSourceIdsAndUpdatesSessionStatus()
    {
        ConnectionSettingsIpcFixture fixture = await ConnectionSettingsIpcFixture.CreateAsync();
        fixture.Conflicts =
        [
            new ConflictReviewData
            {
                ConflictId = Guid.Parse("c91e1964-df65-4b15-b726-e533772f8a01"),
                OperationId = Guid.Parse("c91e1964-df65-4b15-b726-e533772f8a02"),
                SourceId = "laravel:synthetic",
                EntityType = "synthetic.entity",
                ClientUuid = Guid.Parse("c91e1964-df65-4b15-b726-e533772f8a03"),
                ConflictClass = "ConcurrentField",
                Status = "PendingReview",
                RowVersion = "synthetic-row-version"
            }
        ];
        ConnectionSettingsViewModel viewModel = await fixture.CreateLoadedViewModelAsync();

        await viewModel.SynchronizeLaravelNowCommand.ExecuteAsync(null);
        await viewModel.SynchronizeLocalServerNowCommand.ExecuteAsync(null);

        Assert.Equal(["laravel:synthetic", "local:synthetic"], fixture.SynchronizedSources);
        Assert.NotNull(viewModel.LastLaravelSynchronizedAtUtc);
        Assert.NotNull(viewModel.LastLocalServerSynchronizedAtUtc);
        Assert.Equal(1, viewModel.ConflictCount);
        Assert.Contains("محفوظة", viewModel.OutboxStatusText, StringComparison.Ordinal);
        Assert.Equal(2, fixture.OutboxOperationCount);
    }
}

internal sealed class ConnectionSettingsIpcFixture
{
    private readonly string[] _permissions;

    private ConnectionSettingsIpcFixture(bool isAdministrator)
    {
        _permissions = isAdministrator
            ? TestPermissions.All
            :
            [
                Alhayah.Contracts.Ipc.LocalPermissions.Read,
                Alhayah.Contracts.Ipc.LocalPermissions.Synchronize,
                Alhayah.Contracts.Ipc.LocalPermissions.WriteAudit
            ];
        CurrentSettings = IpcTestData.NewSettingsRequest().Settings with
        {
            LaravelSpkiSha256Pin = new string('A', 64),
            LocalServerSpkiSha256Pin = new string('D', 64)
        };
        Host = new FakeSyncServiceHost { EnforcePermissions = true };
        Host.Handler = HandleAsync;
        Client = IpcTestData.CreateClient(Host);
        Authentication = new LocalAuthService(Client);
        Service = new ConnectionSettingsService(Client, Authentication);
        BackupClient = new BackupClient(Client, NullAppLogger.Instance);
        RestoreClient = new RestoreClient(Client, NullAppLogger.Instance);
        SyntheticRootPath = Path.Combine(Path.GetTempPath(), $"alhayah-synthetic-ipc-{Guid.NewGuid():N}");
        SyntheticBackupDirectory = Path.Combine(SyntheticRootPath, "created-backup");
    }

    public FakeSyncServiceHost Host { get; }

    public SyncServiceClient Client { get; }

    public LocalAuthService Authentication { get; }

    public ConnectionSettingsService Service { get; }

    public BackupClient BackupClient { get; }

    public RestoreClient RestoreClient { get; }

    public string SyntheticRootPath { get; }

    public string SyntheticBackupDirectory { get; }

    public List<BackupRequest> BackupRequests { get; } = [];

    public List<RestoreRequest> RestoreRequests { get; } = [];

    public string? BackupErrorCode { get; set; }

    public string? RestoreErrorCode { get; set; }

    public ConnectionSettingsData CurrentSettings { get; private set; }

    public ConnectionSecrets? LastSecrets { get; private set; }

    public List<SaveConnectionSettingsRequest> SaveRequests { get; } = [];

    public List<string> CheckedSources { get; } = [];

    public List<string> SynchronizedSources { get; } = [];

    public List<ConflictReviewData> Conflicts { get; set; } = [];

    public string? CheckErrorCode { get; set; }

    public Func<string, ConnectionCheckResult>? CheckResultFactory { get; set; }

    public int OutboxOperationCount { get; set; } = 2;

    public static async Task<ConnectionSettingsIpcFixture> CreateAsync(bool isAdministrator = true)
    {
        ConnectionSettingsIpcFixture fixture = new(isAdministrator);
        await fixture.Authentication.LoginAsync(IpcTestData.NewLoginRequest());
        return fixture;
    }

    public async Task<ConnectionSettingsViewModel> CreateLoadedViewModelAsync()
    {
        ConnectionSettingsViewModel viewModel = new(Service, Authentication, BackupClient, RestoreClient);
        await viewModel.LoadAsync();
        return viewModel;
    }

    private Task<SyncServiceResponse> HandleAsync(SyncServiceRequest request, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        return Task.FromResult(request.Command switch
        {
            SyncServiceCommands.Login => Host.Ok(request, IpcTestData.NewSession(_permissions)),
            SyncServiceCommands.GetSettings => Host.Ok(request, CurrentSettings),
            SyncServiceCommands.SaveSettings => SaveSettings(request),
            SyncServiceCommands.CheckConnection => CheckConnection(request),
            SyncServiceCommands.SynchronizeNow => SynchronizeNow(request),
            SyncServiceCommands.ListConflicts => Host.Ok(request, new ConflictListResponse { Items = Conflicts }),
            RepositoryCommands.Backup => Backup(request),
            RepositoryCommands.Restore => Restore(request),
            _ => Host.Error(request, "unexpected_command")
        });
    }

    private SyncServiceResponse SaveSettings(SyncServiceRequest request)
    {
        SaveConnectionSettingsRequest saveRequest = request.Payload.Deserialize<SaveConnectionSettingsRequest>()
            ?? throw new InvalidDataException("The synthetic save request is missing.");
        SaveRequests.Add(saveRequest);
        CurrentSettings = saveRequest.Settings;
        LastSecrets = saveRequest.Secrets;
        return Host.Ok(request, new AcknowledgedResponse { Acknowledged = true });
    }

    private SyncServiceResponse CheckConnection(SyncServiceRequest request)
    {
        ConnectionCheckRequest checkRequest = request.Payload.Deserialize<ConnectionCheckRequest>()
            ?? throw new InvalidDataException("The synthetic check request is missing.");
        CheckedSources.Add(checkRequest.SourceId);
        if (CheckErrorCode is not null)
        {
            return Host.Error(request, CheckErrorCode);
        }

        ConnectionCheckResult result = CheckResultFactory?.Invoke(checkRequest.SourceId) ?? new ConnectionCheckResult
        {
            SourceId = checkRequest.SourceId,
            Stages =
            [
                new ConnectionCheckStageResult { Stage = "DNS", Succeeded = true },
                new ConnectionCheckStageResult { Stage = "TCP", Succeeded = true },
                new ConnectionCheckStageResult { Stage = "TLS", Succeeded = true },
                new ConnectionCheckStageResult { Stage = "Auth", Succeeded = true },
                new ConnectionCheckStageResult { Stage = "Compatibility", Succeeded = true }
            ]
        };
        return Host.Ok(request, result);
    }

    private SyncServiceResponse SynchronizeNow(SyncServiceRequest request)
    {
        SynchronizeNowRequest synchronizeRequest = request.Payload.Deserialize<SynchronizeNowRequest>()
            ?? throw new InvalidDataException("The synthetic synchronization request is missing.");
        SynchronizedSources.Add(synchronizeRequest.SourceId);
        return Host.Ok(request, new SynchronizeNowResult
        {
            SourceId = synchronizeRequest.SourceId,
            Status = "Completed"
        });
    }

    private SyncServiceResponse Backup(SyncServiceRequest request)
    {
        BackupRequest backupRequest = request.Payload.Deserialize<BackupRequest>()
            ?? throw new InvalidDataException("The synthetic backup request is missing.");
        BackupRequests.Add(backupRequest);
        if (BackupErrorCode is not null)
        {
            return Host.Error(request, BackupErrorCode);
        }

        return Host.Ok(request, new BackupResponse
        {
            DirectoryPath = SyntheticBackupDirectory,
            DatabasePath = Path.Combine(SyntheticBackupDirectory, "device.db"),
            ManifestPath = Path.Combine(SyntheticBackupDirectory, "manifest.json")
        });
    }

    private SyncServiceResponse Restore(SyncServiceRequest request)
    {
        RestoreRequest restoreRequest = request.Payload.Deserialize<RestoreRequest>()
            ?? throw new InvalidDataException("The synthetic restore request is missing.");
        RestoreRequests.Add(restoreRequest);
        if (RestoreErrorCode is not null)
        {
            return Host.Error(request, RestoreErrorCode);
        }

        return Host.Ok(request, new RestoreResponse
        {
            DestinationDatabasePath = restoreRequest.DestinationDatabasePath,
            DestinationAttachmentDirectory = restoreRequest.DestinationAttachmentDirectory
        });
    }
}

internal sealed class NullAppLogger : IAppLogger
{
    public static NullAppLogger Instance { get; } = new();

    public bool TryLog(AppLogEvent logEvent) => true;
}
