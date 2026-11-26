using System.Text.Json;
using Alhayah.Contracts.Ipc;
using Alhayah.Desktop.Services;
using Alhayah.Desktop.ViewModels;
using Xunit;

namespace Alhayah.Desktop.Tests;

public sealed class ConflictReviewViewModelTests
{
    [Fact]
    public async Task LoadAsync_ForManager_ShowsSyntheticComparisonsAndPendingStatus()
    {
        ConflictReviewIpcFixture fixture = await ConflictReviewIpcFixture.CreateAsync(isManager: true);
        ConflictReviewViewModel viewModel = new(fixture.ReviewClient);

        await viewModel.LoadCommand.ExecuteAsync(null);

        ConflictReviewData conflict = Assert.Single(viewModel.Conflicts);
        Assert.Equal("PendingReview", conflict.Status);
        Assert.Equal(2, viewModel.ComparisonFields.Count);
        Assert.Equal("local-status", viewModel.ComparisonFields[0].LocalValue?.GetString());
        Assert.Equal("remote-status", viewModel.ComparisonFields[0].RemoteValue?.GetString());
        Assert.True(viewModel.CanResolveConflicts);
    }

    [Fact]
    public async Task LoadAsync_ForNonManager_ClearsConflictsAndDoesNotSendListCommand()
    {
        ConflictReviewIpcFixture fixture = await ConflictReviewIpcFixture.CreateAsync(isManager: false);
        ConflictReviewViewModel viewModel = new(fixture.ReviewClient);

        await viewModel.LoadCommand.ExecuteAsync(null);

        Assert.Empty(viewModel.Conflicts);
        Assert.False(viewModel.CanResolveConflicts);
        Assert.Contains("المدير", viewModel.StatusMessage, StringComparison.Ordinal);
        Assert.DoesNotContain(SyncServiceCommands.ListConflicts, fixture.Host.Commands);
    }

    [Fact]
    public async Task GetConflictsAsync_WhenPolledRepeatedly_NotifiesOnlyOnceForSamePendingState()
    {
        ConflictReviewIpcFixture fixture = await ConflictReviewIpcFixture.CreateAsync(isManager: true);
        List<ConflictReviewNotification> notifications = [];
        fixture.ReviewClient.NotificationReceived += (_, notification) => notifications.Add(notification);

        await fixture.ReviewClient.GetConflictsAsync();
        await fixture.ReviewClient.GetConflictsAsync();

        ConflictReviewNotification notification = Assert.Single(notifications);
        Assert.Equal("PendingReview", notification.Status);
        Assert.Equal("synthetic.entity", notification.EntityType);
    }

    [Fact]
    public async Task ResolveSelectedAsync_WithOfflinePendingResponse_DoesNotClaimSuccessBeforeConfirmation()
    {
        ConflictReviewIpcFixture fixture = await ConflictReviewIpcFixture.CreateAsync(isManager: true);
        ConflictReviewViewModel viewModel = new(fixture.ReviewClient);
        await viewModel.LoadCommand.ExecuteAsync(null);
        viewModel.ComparisonFields[0].IsSelected = true;

        await viewModel.ResolveSelectedFieldsCommand.ExecuteAsync(null);

        ConflictResolutionRequest request = Assert.Single(fixture.Resolutions);
        Assert.Equal("selected-fields", request.Decision);
        Assert.Equal(["status"], request.SelectedFields);
        Assert.Equal(1, request.ExpectedConflictVersion);
        Assert.Equal("PendingResolution", Assert.Single(viewModel.Conflicts).Status);
        Assert.Contains("بانتظار", viewModel.StatusMessage, StringComparison.Ordinal);
    }

    [Fact]
    public async Task ResolveLocalAsync_WithStaleDecision_KeepsConflictPendingAndReportsStaleness()
    {
        ConflictReviewIpcFixture fixture = await ConflictReviewIpcFixture.CreateAsync(isManager: true);
        fixture.ResolutionError = "version_mismatch";
        ConflictReviewViewModel viewModel = new(fixture.ReviewClient);
        await viewModel.LoadCommand.ExecuteAsync(null);

        await viewModel.ResolveLocalCommand.ExecuteAsync(null);

        Assert.Equal("PendingReview", Assert.Single(viewModel.Conflicts).Status);
        Assert.Contains("تغير", viewModel.StatusMessage, StringComparison.Ordinal);
    }

    [Fact]
    public async Task ResolveServerAsync_WithConfirmedResponse_MarksResolvedOnlyFromServerResponse()
    {
        ConflictReviewIpcFixture fixture = await ConflictReviewIpcFixture.CreateAsync(isManager: true);
        fixture.ResolutionStatus = "Resolved";
        ConflictReviewViewModel viewModel = new(fixture.ReviewClient);
        await viewModel.LoadCommand.ExecuteAsync(null);

        await viewModel.ResolveServerCommand.ExecuteAsync(null);

        Assert.Equal("Resolved", Assert.Single(viewModel.Conflicts).Status);
        Assert.Equal("server", Assert.Single(fixture.Resolutions).Decision);
    }
}

internal sealed class ConflictReviewIpcFixture
{
    private readonly string[] _permissions;

    private ConflictReviewIpcFixture(bool isManager)
    {
        _permissions = isManager
            ? [LocalPermissions.Read, LocalPermissions.ResolveConflicts]
            : [LocalPermissions.Read];
        Host = new FakeSyncServiceHost { EnforcePermissions = true };
        Host.Handler = HandleAsync;
        Client = IpcTestData.CreateClient(Host);
        Authentication = new LocalAuthService(Client);
        ReviewClient = new ConflictReviewClient(Client, Authentication, NullAppLogger.Instance);
    }

    public FakeSyncServiceHost Host { get; }
    public SyncServiceClient Client { get; }
    public LocalAuthService Authentication { get; }
    public ConflictReviewClient ReviewClient { get; }
    public List<ConflictResolutionRequest> Resolutions { get; } = [];
    public string? ResolutionError { get; set; }
    public string ResolutionStatus { get; set; } = "PendingResolution";

    public static async Task<ConflictReviewIpcFixture> CreateAsync(bool isManager)
    {
        ConflictReviewIpcFixture fixture = new(isManager);
        await fixture.Authentication.LoginAsync(IpcTestData.NewLoginRequest());
        return fixture;
    }

    private Task<SyncServiceResponse> HandleAsync(SyncServiceRequest request, CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        return Task.FromResult(request.Command switch
        {
            SyncServiceCommands.Login => Host.Ok(request, IpcTestData.NewSession(_permissions)),
            SyncServiceCommands.ListConflicts => Host.Ok(request, new ConflictListResponse
            {
                Items = [new ConflictReviewData
                {
                    ConflictId = Guid.Parse("80a51e6e-cc67-4ef5-a37e-e2a936b9d001"),
                    OperationId = Guid.Parse("80a51e6e-cc67-4ef5-a37e-e2a936b9d002"),
                    SourceId = "laravel:synthetic",
                    EntityType = "synthetic.entity",
                    ClientUuid = Guid.Parse("80a51e6e-cc67-4ef5-a37e-e2a936b9d003"),
                    ConflictClass = "CriticalField",
                    Status = "PendingReview",
                    RowVersion = "synthetic-row-version",
                    ConflictVersion = 1,
                    Fields =
                    [
                        new ConflictFieldReviewData
                        {
                            FieldName = "status",
                            BaseValue = JsonSerializer.SerializeToElement("base-status"),
                            LocalValue = JsonSerializer.SerializeToElement("local-status"),
                            RemoteValue = JsonSerializer.SerializeToElement("remote-status"),
                            Criticality = FieldCriticality.Critical
                        },
                        new ConflictFieldReviewData
                        {
                            FieldName = "displayName",
                            BaseValue = JsonSerializer.SerializeToElement("base-name"),
                            LocalValue = JsonSerializer.SerializeToElement("local-name"),
                            RemoteValue = JsonSerializer.SerializeToElement("remote-name"),
                            Criticality = FieldCriticality.Mergeable
                        }
                    ]
                }]
            }),
            SyncServiceCommands.ResolveConflict => Resolve(request),
            _ => Host.Error(request, "unexpected_command")
        });
    }

    private SyncServiceResponse Resolve(SyncServiceRequest request)
    {
        ConflictResolutionRequest resolution = request.Payload.Deserialize<ConflictResolutionRequest>()
            ?? throw new InvalidDataException();
        Resolutions.Add(resolution);
        return ResolutionError is null
            ? Host.Ok(request, new ConflictResolutionResponse
            {
                ConflictId = resolution.ConflictId,
                Status = ResolutionStatus,
                ResolutionOperationId = resolution.ResolutionOperationId,
                ConflictVersion = resolution.ExpectedConflictVersion
            })
            : Host.Error(request, ResolutionError);
    }
}
