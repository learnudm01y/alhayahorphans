using System.Security.Principal;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Ipc;
using Alhayah.SyncService.Security;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class IpcIntegrationTests
{
    [Fact]
    public async Task LoginSaveSearchReadAndQuery_ThroughNamedPipe_WorkWithoutNetwork()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        await harness.SeedUserAsync("synthetic-admin", "synthetic-password", TestPermissions.All);
        LocalSession session = await harness.LoginAsync("synthetic-admin", "synthetic-password");
        SaveEntityRequest save = NewSaveRequest(Guid.NewGuid(), Guid.NewGuid());

        SyncServiceResponse savedResponse = await harness.Client.SendAsync(RepositoryCommands.Save, save, session);
        SyncServiceResponse searchResponse = await harness.Client.SendAsync(
            RepositoryCommands.Search,
            new SearchEntitiesRequest
            {
                SourceId = "laravel:synthetic",
                EntityType = "synthetic.entity",
                Query = "synthetic display",
                Limit = 20
            },
            session);
        SyncServiceResponse readResponse = await harness.Client.SendAsync(
            RepositoryCommands.Read,
            new ReadEntityRequest
            {
                SourceId = "laravel:synthetic",
                EntityType = "synthetic.entity",
                ClientUuid = save.Envelope.Entity.ClientUuid
            },
            session);
        SyncServiceResponse queryResponse = await harness.Client.SendAsync(
            RepositoryCommands.Query,
            new QueryEntitiesRequest
            {
                SourceId = "laravel:synthetic",
                EntityType = "synthetic.entity",
                Skip = 0,
                Take = 50
            },
            session);

        SaveEntityResponse saved = ReadPayload<SaveEntityResponse>(savedResponse);
        EntityListResponse search = ReadPayload<EntityListResponse>(searchResponse);
        ReadEntityResponse read = ReadPayload<ReadEntityResponse>(readResponse);
        EntityListResponse query = ReadPayload<EntityListResponse>(queryResponse);
        Assert.Equal(save.Envelope.Entity.ClientUuid, saved.Entity.ClientUuid);
        Assert.Equal(save.PayloadJson, saved.Entity.PayloadJson);
        Assert.Single(search.Items);
        Assert.NotNull(read.Entity);
        Assert.Equal(1, query.TotalCount);
        Assert.False(string.IsNullOrWhiteSpace(session.SessionProof));
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(1, await context.CachedEntities.CountAsync());
        Assert.Equal(1, await context.OutboxOperations.CountAsync());
        Assert.Equal(1, await context.LocalChangeLog.CountAsync());
    }

    [Fact]
    public async Task BackupAndRestore_ThroughNamedPipe_PreserveSavedEntity()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        await harness.SeedUserAsync("synthetic-backup-admin", "synthetic-password", TestPermissions.All);
        LocalSession session = await harness.LoginAsync("synthetic-backup-admin", "synthetic-password");
        SaveEntityRequest save = NewSaveRequest(Guid.NewGuid(), Guid.NewGuid());
        ReadPayload<SaveEntityResponse>(await harness.Client.SendAsync(RepositoryCommands.Save, save, session));

        SyncServiceResponse backupResponse = await harness.Client.SendAsync(
            RepositoryCommands.Backup,
            new BackupRequest { DestinationDirectory = harness.GetPath("selected-backups") },
            session);
        BackupResponse backup = ReadPayload<BackupResponse>(backupResponse);
        string restoredDatabase = harness.GetPath("restored", "device.db");
        string restoredAttachments = harness.GetPath("restored", "attachments");
        SyncServiceResponse restoreResponse = await harness.Client.SendAsync(
            RepositoryCommands.Restore,
            new RestoreRequest
            {
                BackupDirectory = backup.DirectoryPath,
                DestinationDatabasePath = restoredDatabase,
                DestinationAttachmentDirectory = restoredAttachments
            },
            session);

        RestoreResponse restored = ReadPayload<RestoreResponse>(restoreResponse);
        Assert.Equal(restoredDatabase, restored.DestinationDatabasePath);
        Assert.Equal(restoredAttachments, restored.DestinationAttachmentDirectory);
        Assert.True(File.Exists(backup.DatabasePath));
        Assert.True(File.Exists(backup.ManifestPath));
        Assert.True(File.Exists(restoredDatabase));
        await using LocalStoreDbContext restoredContext = harness.Store.CreateContext(restoredDatabase);
        CachedEntity restoredEntity = await restoredContext.CachedEntities.SingleAsync();
        Assert.Equal(save.Envelope.Entity.ClientUuid, restoredEntity.ClientUuid);
        Assert.Equal(save.PayloadJson, restoredEntity.PayloadJson);
        Assert.Equal(1, await restoredContext.OutboxOperations.CountAsync());
    }

    [Fact]
    public async Task SaveSettings_WithoutNetwork_StoresSecretsSeparatelyAndPreservesSyncState()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        await harness.SeedUserAsync("synthetic-settings-admin", "synthetic-password", TestPermissions.All);
        LocalSession session = await harness.LoginAsync("synthetic-settings-admin", "synthetic-password");
        ReadPayload<SaveEntityResponse>(await harness.Client.SendAsync(
            RepositoryCommands.Save,
            NewSaveRequest(Guid.NewGuid(), Guid.NewGuid()),
            session));
        Guid conflictId = Guid.NewGuid();
        await using (LocalStoreDbContext context = harness.Store.CreateContext())
        {
            CachedEntity entity = await context.CachedEntities.SingleAsync();
            context.SyncCheckpoints.Add(new SyncCheckpoint
            {
                SourceId = "laravel:synthetic",
                Channel = "changes",
                Cursor = "synthetic-cursor",
                LastCommittedChangeId = 7,
                UpdatedAtUtc = DateTimeOffset.Parse("2026-11-25T00:00:00Z")
            });
            context.SyncConflicts.Add(new SyncConflict
            {
                Id = conflictId,
                OperationId = Guid.NewGuid(),
                SourceId = "laravel:synthetic",
                EntityType = entity.EntityType,
                ClientUuid = entity.ClientUuid,
                ConflictClass = "ConcurrentField",
                EnvelopeJson = "{}",
                Status = "PendingReview",
                CreatedAtUtc = DateTimeOffset.Parse("2026-11-25T00:00:00Z")
            });
            await context.SaveChangesAsync();
        }

        ConnectionSettingsData settings = NewSettings();
        SyncServiceResponse saveResponse = await harness.Client.SendAsync(
            SyncServiceCommands.SaveSettings,
            new SaveConnectionSettingsRequest
            {
                Settings = settings,
                Secrets = new ConnectionSecrets
                {
                    LaravelDeviceToken = "synthetic-transient-laravel-secret",
                    LocalServerDeviceCredential = "synthetic-transient-local-secret"
                }
            },
            session);
        SyncServiceResponse getResponse = await harness.Client.SendAsync(
            SyncServiceCommands.GetSettings,
            new EmptyRequest(),
            session);
        SyncServiceResponse checkResponse = await harness.Client.SendAsync(
            SyncServiceCommands.CheckConnection,
            new ConnectionCheckRequest { SourceId = "laravel:synthetic" },
            session);
        SyncServiceResponse syncResponse = await harness.Client.SendAsync(
            SyncServiceCommands.SynchronizeNow,
            new SynchronizeNowRequest { SourceId = "laravel:synthetic" },
            session);

        Assert.True(ReadPayload<AcknowledgedResponse>(saveResponse).Acknowledged);
        ConnectionSettingsData returned = ReadPayload<ConnectionSettingsData>(getResponse);
        Assert.Equal(settings.ConnectionName, returned.ConnectionName);
        Assert.Equal(settings.LaravelEndpoint, returned.LaravelEndpoint);
        Assert.Equal("synthetic-transient-laravel-secret",
            harness.Secrets.GetUtf8(SecretTargets.LaravelDeviceToken("laravel:synthetic")));
        Assert.Equal("synthetic-transient-local-secret",
            harness.Secrets.GetUtf8(SecretTargets.LocalServerDeviceCredential("local:synthetic")));
        string returnedJson = JsonSerializer.Serialize(returned);
        string settingsFile = await File.ReadAllTextAsync(harness.SettingsFilePath);
        Assert.DoesNotContain("synthetic-transient-laravel-secret", returnedJson, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-transient-local-secret", returnedJson, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-transient-laravel-secret", settingsFile, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-transient-local-secret", settingsFile, StringComparison.Ordinal);
        ConnectionCheckResult check = ReadPayload<ConnectionCheckResult>(checkResponse);
        Assert.All(check.Stages, stage => Assert.False(stage.Succeeded));
        Assert.Equal("Pending", ReadPayload<SynchronizeNowResult>(syncResponse).Status);
        await using LocalStoreDbContext verification = harness.Store.CreateContext();
        Assert.Equal(1, await verification.CachedEntities.CountAsync());
        Assert.Equal(1, await verification.OutboxOperations.CountAsync());
        Assert.Equal(1, await verification.SyncCheckpoints.CountAsync());
        Assert.Equal(1, await verification.SyncConflicts.CountAsync());
        Assert.Equal("synthetic-cursor", (await verification.SyncCheckpoints.SingleAsync()).Cursor);
        Assert.Equal("PendingReview", (await verification.SyncConflicts.SingleAsync()).Status);
    }

    [Fact]
    public async Task NonAdministrator_SettingsAndConflictCommands_AreDenied()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        await harness.SeedUserAsync(
            "synthetic-limited-user",
            "synthetic-password",
            LocalPermissions.Read,
            LocalPermissions.WriteAudit);
        LocalSession session = await harness.LoginAsync("synthetic-limited-user", "synthetic-password");

        SyncServiceResponse settings = await harness.Client.SendAsync(
            SyncServiceCommands.SaveSettings,
            new SaveConnectionSettingsRequest { Settings = NewSettings() },
            session);
        SyncServiceResponse conflicts = await harness.Client.SendAsync(
            SyncServiceCommands.ListConflicts,
            new EmptyRequest(),
            session);
        SyncServiceResponse read = await harness.Client.SendAsync(
            RepositoryCommands.Read,
            new ReadEntityRequest
            {
                SourceId = "laravel:synthetic",
                EntityType = "synthetic.entity",
                ClientUuid = Guid.NewGuid()
            },
            session);

        Assert.False(settings.Success);
        Assert.Equal("permission_denied", settings.Error?.Code);
        Assert.False(conflicts.Success);
        Assert.Equal("permission_denied", conflicts.Error?.Code);
        Assert.True(read.Success, read.Error?.Code);
        Assert.Null(ReadPayload<ReadEntityResponse>(read).Entity);
    }

    [Fact]
    public async Task CommandsWithoutSessionProof_AreRejectedBeforeSaving()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();

        SyncServiceResponse response = await harness.Client.SendAsync(
            RepositoryCommands.Save,
            NewSaveRequest(Guid.NewGuid(), Guid.NewGuid()),
            session: null);

        Assert.False(response.Success);
        Assert.Equal("unauthorized", response.Error?.Code);
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(0, await context.CachedEntities.CountAsync());
        Assert.Equal(0, await context.OutboxOperations.CountAsync());
    }

    [Fact]
    public async Task Bootstrap_WithoutOnlineAuthority_IsUnavailableAndCreatesNoDefaultAdministrator()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        string nonce = Convert.ToBase64String(new byte[32]);

        SyncServiceResponse response = await harness.Client.SendAsync(
            SyncServiceCommands.BootstrapAdministrator,
            new BootstrapAdministratorRequest
            {
                UserName = "synthetic-first-admin",
                DisplayName = "Synthetic Administrator",
                Password = "synthetic-bootstrap-password",
                ConfirmationPassword = "synthetic-bootstrap-password",
                Nonce = nonce,
                DeviceId = "synthetic-device",
                SourceId = "laravel:synthetic"
            },
            session: null);

        Assert.False(response.Success);
        Assert.Equal("bootstrap_unavailable", response.Error?.Code);
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(0, await context.LocalUsers.CountAsync());
        Assert.Equal(0, await context.LocalSessions.CountAsync());
    }

    [Fact]
    public async Task MalformedJson_ThroughNamedPipe_IsRejectedWithoutPayloadLogging()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();

        SyncServiceResponse response = await harness.Client.SendRawFrameAsync(
            Encoding.UTF8.GetBytes("{\"requestId\":\"not-a-guid\",\"password\":\"synthetic-secret-value\""));

        Assert.False(response.Success);
        Assert.Equal("malformed_json", response.Error?.Code);
        Assert.DoesNotContain("synthetic-secret-value", string.Join('\n', harness.Logs.Messages), StringComparison.Ordinal);
    }

    [Theory]
    [InlineData(-1)]
    [InlineData(0)]
    [InlineData(SyncServiceProtocol.MaxFrameBytes + 1)]
    public async Task InvalidFrameLength_ThroughNamedPipe_IsRejected(int length)
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();

        SyncServiceResponse response = await harness.Client.SendLengthOnlyAsync(length);

        Assert.False(response.Success);
        Assert.Equal("invalid_frame", response.Error?.Code);
    }

    [Fact]
    public async Task WrongProtocolVersion_ThroughNamedPipe_IsRejected()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        Guid requestId = Guid.NewGuid();

        SyncServiceResponse response = await harness.Client.SendEnvelopeAsync(new SyncServiceRequest
        {
            RequestId = requestId,
            ProtocolVersion = SyncServiceProtocol.Version + 1,
            Command = SyncServiceCommands.GetSettings,
            Payload = JsonSerializer.SerializeToElement(new EmptyRequest())
        });

        Assert.False(response.Success);
        Assert.Equal(requestId, response.RequestId);
        Assert.Equal("unsupported_protocol", response.Error?.Code);
    }

    [Fact]
    public async Task EmptyRequestId_ThroughNamedPipe_IsRejected()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();

        SyncServiceResponse response = await harness.Client.SendEnvelopeAsync(new SyncServiceRequest
        {
            RequestId = Guid.Empty,
            ProtocolVersion = SyncServiceProtocol.Version,
            Command = SyncServiceCommands.GetSettings,
            Payload = JsonSerializer.SerializeToElement(new EmptyRequest())
        });

        Assert.False(response.Success);
        Assert.Equal("invalid_request", response.Error?.Code);
    }

    [Fact]
    public async Task CancelledClientRequest_DoesNotWriteToStore()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        using CancellationTokenSource cancellation = new();
        await cancellation.CancelAsync();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() => harness.Client.SendAsync(
            RepositoryCommands.Save,
            NewSaveRequest(Guid.NewGuid(), Guid.NewGuid()),
            session: null,
            cancellation.Token));

        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(0, await context.CachedEntities.CountAsync());
    }

    [Fact]
    public async Task UnauthorizedWindowsClient_IsRejectedByPipeAclBeforeLogin()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        string currentSid = WindowsIdentity.GetCurrent().User!.Value;
        string pipeName = $"Alhayah.Tests.Denied.{Guid.NewGuid():N}";
        NamedPipeHostOptions options = new()
        {
            PipeName = pipeName,
            AllowedSids = [currentSid],
            DeniedSids = [currentSid],
            RequestTimeout = TimeSpan.FromSeconds(2)
        };
        await using NamedPipeHost deniedHost = new(
            options,
            harness.Handlers,
            new WindowsNamedPipeAccepter(options),
            NullLogger<NamedPipeHost>.Instance);
        await deniedHost.StartAsync(CancellationToken.None);
        TestIpcClient client = new(pipeName);

        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => client.SendAsync(
            SyncServiceCommands.Login,
            NewLoginRequest("synthetic-denied-user", "synthetic-password"),
            session: null));

        await using LocalStoreDbContext context = harness.Store.CreateContext();
        Assert.Equal(0, await context.LocalSessions.CountAsync());
    }

    [Fact]
    public async Task AuthenticationAndSettings_DoNotWriteSecretsOrPiiToLogs()
    {
        await using SyncServiceIpcHarness harness = await SyncServiceIpcHarness.CreateAsync();
        await harness.SeedUserAsync("synthetic-private-user", "synthetic-private-password", TestPermissions.All);
        LocalSession session = await harness.LoginAsync("synthetic-private-user", "synthetic-private-password");

        await harness.Client.SendAsync(
            SyncServiceCommands.SaveSettings,
            new SaveConnectionSettingsRequest
            {
                Settings = NewSettings(),
                Secrets = new ConnectionSecrets { LaravelDeviceToken = "synthetic-private-token" }
            },
            session);

        string logs = string.Join('\n', harness.Logs.Messages);
        Assert.DoesNotContain("synthetic-private-user", logs, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-private-password", logs, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic-private-token", logs, StringComparison.Ordinal);
        Assert.DoesNotContain("synthetic display", logs, StringComparison.OrdinalIgnoreCase);
    }

    private static T ReadPayload<T>(SyncServiceResponse response)
    {
        Assert.True(response.Success, response.Error?.Code);
        Assert.NotNull(response.Payload);
        return response.Payload.Value.Deserialize<T>(SyncServiceIpcHarness.JsonOptions)
            ?? throw new InvalidDataException("The synthetic response payload is empty.");
    }

    private static LoginRequest NewLoginRequest(string userName, string password) => new()
    {
        UserName = userName,
        Password = password,
        DeviceId = "synthetic-device",
        SourceId = "laravel:synthetic"
    };

    private static SaveEntityRequest NewSaveRequest(Guid operationId, Guid clientUuid) => new()
    {
        Envelope = new SyncEnvelope
        {
            OperationId = operationId,
            SourceId = new SyncSourceId("laravel:synthetic"),
            DeviceId = "synthetic-device",
            Entity = new EntityDescriptor
            {
                EntityType = "synthetic.entity",
                ClientUuid = clientUuid,
                CanonicalId = null,
                References = []
            },
            OperationType = SyncOperationType.Create,
            OccurredAtUtc = DateTimeOffset.Parse("2026-11-25T01:00:00Z"),
            Patches = []
        },
        PayloadJson = "{\"displayName\":\"Synthetic Display\",\"syntheticValue\":42}",
        SearchText = "Synthetic Display 42"
    };

    private static ConnectionSettingsData NewSettings() => new()
    {
        ConnectionName = "synthetic-connection",
        LaravelEndpoint = "https://laravel.synthetic.invalid/api",
        LocalServerEndpoint = "https://local.synthetic.invalid:9443",
        LaravelSpkiSha256Pin = new string('A', 64),
        PreviousLaravelSpkiSha256Pin = new string('B', 64),
        LocalServerSpkiSha256Pin = new string('C', 64),
        PreviousLocalServerSpkiSha256Pin = new string('D', 64),
        RequestTimeoutSeconds = 45,
        SynchronizationEnabled = true
    };
}
