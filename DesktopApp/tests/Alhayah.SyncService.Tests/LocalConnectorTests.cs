using System.Net;
using System.Net.Security;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.LocalData.Services;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Realtime;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class LocalConnectorTests
{
    private const string SourceId = "local:synthetic-a";
    private const string LaravelSourceId = "laravel:synthetic-a";
    private const string DeviceId = "synthetic-local-device";
    private const string UserId = "synthetic-local-user";
    private const string Credential = "synthetic-local-credential-that-must-not-leak";

    [Fact]
    public async Task HealthCapabilitiesAndConnectionCheck_UseActualLocalRoutesAndAuthentication()
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery == "/health")
            {
                return JsonResponse(HealthJson());
            }

            if (request.PathAndQuery == "/capabilities")
            {
                return JsonResponse(CapabilitiesJson());
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });

        LocalServerHealthResponse health = await fixture.Connector.GetHealthAsync(CancellationToken.None);
        LocalServerCapabilitiesResponse capabilities = await fixture.Connector.GetCapabilitiesAsync(CancellationToken.None);
        ConnectionCheckResult check = await fixture.Connector.CheckConnectionAsync(CancellationToken.None);

        Assert.Equal("ok", health.Status);
        Assert.Equal(SourceId, health.SourceId);
        Assert.Equal("independent-device-credential", capabilities.CredentialType);
        Assert.Equal("https-required", capabilities.Transport);
        Assert.Equal(["DNS", "TCP", "TLS", "Auth", "Compatibility"], check.Stages.Select(stage => stage.Stage));
        Assert.All(check.Stages, stage => Assert.True(stage.Succeeded));
        Assert.All(fixture.Handler.Requests.Where(request => request.PathAndQuery == "/health"), request =>
            Assert.False(request.Headers.ContainsKey("Authorization")));
        Assert.All(fixture.Handler.Requests.Where(request => request.PathAndQuery == "/capabilities"), request =>
        {
            Assert.Equal($"Device {Credential}", request.Header("Authorization"));
            Assert.Equal(DeviceId, request.Header("X-Alhayah-Device-Id"));
            Assert.Equal(UserId, request.Header("X-Alhayah-User-Id"));
        });
    }

    [Fact]
    public async Task SignalRFailure_LeavesPollingFallbackAbleToPushAndPull()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.Method == HttpMethod.Post && request.PathAndQuery == "/local/operations/aid")
            {
                return JsonResponse(OperationResultJson(operationId, clientUuid, "Completed"));
            }

            if (request.Method == HttpMethod.Get && request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson("cursor-20", false));
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });
        await SeedAidAsync(fixture.Harness, operationId, clientUuid);
        fixture.Hub.StartException = new InvalidOperationException("Synthetic SignalR outage.");

        await fixture.Coordinator.StartAsync(CancellationToken.None);
        SynchronizeNowResult result = await fixture.Coordinator.PollOnceAsync(CancellationToken.None);

        Assert.True(fixture.Coordinator.UsingPollingFallback);
        Assert.Equal("Completed", result.Status);
        Assert.Equal("Sent", await OutboxStatusAsync(fixture, operationId));
        Assert.Equal("cursor-20", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        Assert.Contains(fixture.Handler.Requests, request => request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal));
    }

    [Fact]
    public async Task SignalRNotification_IsOnlyPollingHintAndNotCommitEvidence()
    {
        Guid clientUuid = Guid.NewGuid();
        bool feedAvailable = false;
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal) && feedAvailable)
            {
                return JsonResponse(FeedJson(
                    "cursor-31",
                    false,
                    ChangeJson(31, clientUuid, "Create", "{\"aidType\":\"Synthetic remote aid\"}")));
            }

            if (request.PathAndQuery.StartsWith("/local/changes", StringComparison.Ordinal))
            {
                return JsonResponse("{\"message\":\"synthetic unavailable\"}", HttpStatusCode.ServiceUnavailable);
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });
        await fixture.Coordinator.StartAsync(CancellationToken.None);

        await fixture.Hub.EmitAsync();

        Assert.Null((await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            Assert.False(await context.CachedEntities.AnyAsync(entity => entity.ClientUuid == clientUuid));
        }

        feedAvailable = true;
        await fixture.Hub.EmitAsync();

        Assert.Equal("cursor-31", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        Assert.Single(await verification.CachedEntities.Where(entity => entity.ClientUuid == clientUuid).ToArrayAsync());
    }

    [Fact]
    public async Task PullAsync_WithCursorPagesAndTombstone_CommitsPagesAndReplayHasNoDuplicateEffect()
    {
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.Contains("cursor=cursor-10", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson(
                    "cursor-11",
                    true,
                    ChangeJson(11, clientUuid, "Create", "{\"aidType\":\"Synthetic remote aid\",\"currencyCode\":\"USD\"}")));
            }

            if (request.PathAndQuery.Contains("cursor=cursor-11", StringComparison.Ordinal))
            {
                return JsonResponse(FeedJson(
                    "cursor-12",
                    false,
                    ChangeJson(12, clientUuid, "Delete", null, includeTombstone: true)));
            }

            return JsonResponse("{}", HttpStatusCode.BadRequest);
        });
        await fixture.Cursors.CommitPageAsync(
            SourceId,
            "local-operations",
            EmptyPage(10, "cursor-10"),
            CancellationToken.None);

        LocalPullResult result = await fixture.Connector.PullAsync(CancellationToken.None);
        CursorCommitResult replay = await fixture.Cursors.CommitPageAsync(
            SourceId,
            "local-operations",
            EmptyPage(12, "cursor-12"),
            CancellationToken.None);

        Assert.Equal(2, result.PageCount);
        Assert.Equal(2, result.Changes.Count);
        Assert.Equal(CursorCommitResult.AlreadyCommitted, replay);
        Assert.Equal("remote-device", result.Changes[1].Tombstone!.DeletedByDeviceId);
        Assert.Equal("cursor-12", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        Assert.Equal(2, fixture.Handler.Requests.Count);
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        CachedEntity deleted = await context.CachedEntities.IgnoreQueryFilters().SingleAsync(entity => entity.ClientUuid == clientUuid);
        Assert.Equal("12", deleted.RowVersion);
        Assert.Equal("remote-device", deleted.DeletedByDeviceId);
        Assert.NotNull(deleted.DeletedAtUtc);
    }

    [Fact]
    public async Task PullAsync_WhenPageCommitFails_LeavesCursorForRetryWithoutDuplicateEffect()
    {
        CommitCrashInterceptor crash = new();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(
            _ => JsonResponse(FeedJson(
                "cursor-21",
                false,
                ChangeJson(21, Guid.Empty, "Create", "{\"aidType\":\"Synthetic committed aid\"}"))),
            crash);
        Guid clientUuid = Guid.NewGuid();
        fixture.Handler.Responder = _ => JsonResponse(FeedJson(
            "cursor-21",
            false,
            ChangeJson(21, clientUuid, "Create", "{\"aidType\":\"Synthetic committed aid\"}")));
        crash.ArmBeforeCommit();

        await Assert.ThrowsAsync<IOException>(() => fixture.Connector.PullAsync(CancellationToken.None));

        Assert.Null((await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            Assert.False(await context.CachedEntities.AnyAsync(entity => entity.ClientUuid == clientUuid));
        }

        await fixture.Connector.PullAsync(CancellationToken.None);

        Assert.Equal("cursor-21", (await fixture.Cursors.GetAsync(SourceId, "local-operations")).Cursor);
        await using LocalStoreDbContext verification = fixture.Harness.Store.CreateContext();
        Assert.Single(await verification.CachedEntities.Where(entity => entity.ClientUuid == clientUuid).ToArrayAsync());
    }

    [Fact]
    public async Task PushAsync_WithRateLimitThenSuccess_ReplaysIdenticalOperationWithoutDuplicateEffect()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        int calls = 0;
        int effectCount = 0;
        HashSet<Guid> applied = [];
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
        {
            calls++;
            if (applied.Add(operationId))
            {
                effectCount++;
            }

            if (calls == 1)
            {
                HttpResponseMessage response = JsonResponse("{\"retry_after\":0}", (HttpStatusCode)429);
                response.Headers.RetryAfter = new System.Net.Http.Headers.RetryConditionHeaderValue(TimeSpan.Zero);
                return response;
            }

            return JsonResponse(OperationResultJson(operationId, clientUuid, "Completed"));
        });
        await SeedAidAsync(fixture.Harness, operationId, clientUuid);

        LocalOperationResult result = await fixture.Connector.PushAsync(
            await LoadEnvelopeAsync(fixture, operationId),
            CancellationToken.None);

        Assert.Equal(LocalOperationStatus.Completed, result.Status);
        Assert.Equal(2, calls);
        Assert.Equal(1, effectCount);
        Assert.Equal(fixture.Handler.Requests[0].BodyText, fixture.Handler.Requests[1].BodyText);
        using JsonDocument body = JsonDocument.Parse(fixture.Handler.Requests[0].BodyText!);
        Assert.Equal(operationId, body.RootElement.GetProperty("operationId").GetGuid());
        Assert.Equal($"desktop:{operationId:N}", body.RootElement.GetProperty("idempotencyKey").GetString());
        Assert.Equal(operationId, body.RootElement.GetProperty("envelope").GetProperty("operationId").GetGuid());
        Assert.Equal("/local/operations/aid", fixture.Handler.Requests[0].PathAndQuery);
    }

    [Fact]
    public async Task PushAsync_WithVoucherRedemption_SendsVersionReferencesAndStableIdentity()
    {
        Guid operationId = Guid.NewGuid();
        Guid redemptionId = Guid.NewGuid();
        Guid voucherId = Guid.NewGuid();
        Guid concurrencyToken = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse(OperationResultJson(operationId, redemptionId, "Completed")));
        SyncEnvelope envelope = LocalEnvelope(operationId, redemptionId, "VoucherRedemption") with
        {
            BaseVersion = 7,
            RowVersion = concurrencyToken.ToString("D"),
            Entity = new EntityDescriptor
            {
                EntityType = "VoucherRedemption",
                ClientUuid = redemptionId,
                CanonicalId = null,
                References =
                [
                    new EntityReference
                    {
                        FieldName = "voucherId",
                        TargetEntityType = "Voucher",
                        TargetClientUuid = voucherId,
                        TargetCanonicalId = voucherId.ToString("D"),
                        Kind = EntityReferenceKind.Field
                    }
                ]
            }
        };
        string payload = JsonSerializer.Serialize(new
        {
            voucherId,
            expectedRowVersion = 7,
            expectedConcurrencyToken = concurrencyToken,
            amount = 15.25m,
            currencyCode = "USD",
            redeemedAtUtc = DateTimeOffset.Parse("2026-11-25T10:00:00Z"),
            redemptionReference = "synthetic-redemption"
        });
        await SeedLocalEntityAsync(fixture.Harness, envelope, payload, "synthetic-redemption");

        await fixture.Connector.PushAsync(envelope, CancellationToken.None);

        using JsonDocument body = JsonDocument.Parse(fixture.Handler.Requests.Single().BodyText!);
        JsonElement root = body.RootElement;
        Assert.Equal("/local/vouchers/redeem", fixture.Handler.Requests.Single().PathAndQuery);
        Assert.Equal(operationId, root.GetProperty("operationId").GetGuid());
        Assert.Equal(voucherId, root.GetProperty("voucherId").GetGuid());
        Assert.Equal(7, root.GetProperty("expectedRowVersion").GetInt64());
        Assert.Equal(concurrencyToken, root.GetProperty("expectedConcurrencyToken").GetGuid());
        Assert.Equal(voucherId, root.GetProperty("envelope").GetProperty("entity").GetProperty("references")[0]
            .GetProperty("targetClientUuid").GetGuid());
        Assert.Equal(7, root.GetProperty("envelope").GetProperty("baseVersion").GetUInt64());
    }

    [Theory]
    [InlineData(HttpStatusCode.Unauthorized, "unauthorized")]
    [InlineData(HttpStatusCode.Forbidden, "forbidden")]
    public async Task PushAsync_WithAuthenticationFailure_DoesNotRetryAndLeavesOutboxPending(
        HttpStatusCode statusCode,
        string expectedError)
    {
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse("{\"error\":\"synthetic-auth-failure\"}", statusCode));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);

        LocalSyncException exception = await Assert.ThrowsAsync<LocalSyncException>(() =>
            fixture.Connector.PushAsync(envelope, CancellationToken.None));

        Assert.Equal(expectedError, exception.ErrorCode);
        Assert.False(exception.IsTransient);
        Assert.Single(fixture.Handler.Requests);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
    }

    [Fact]
    public async Task PushAsync_WithTimeout_RetriesSameOperationAndLeavesOutboxPending()
    {
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            throw new TimeoutException("synthetic timeout"));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);

        LocalSyncException exception = await Assert.ThrowsAsync<LocalSyncException>(() =>
            fixture.Connector.PushAsync(envelope, CancellationToken.None));

        Assert.True(exception.IsTransient);
        Assert.Equal("timeout", exception.ErrorCode);
        Assert.Equal(3, fixture.Handler.Requests.Count);
        Assert.All(fixture.Handler.Requests, request => Assert.Equal(fixture.Handler.Requests[0].BodyText, request.BodyText));
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
    }

    [Fact]
    public async Task PushAsync_WithCancellation_DoesNotRetryOrAcknowledge()
    {
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => JsonResponse("{}"));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);
        using CancellationTokenSource cancellation = new();
        cancellation.Cancel();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() =>
            fixture.Connector.PushAsync(envelope, cancellation.Token));

        Assert.Empty(fixture.Handler.Requests);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, operationId));
    }

    [Fact]
    public async Task LocalFailure_DoesNotChangeLaravelCursorOrOutboxState()
    {
        Guid localOperationId = Guid.NewGuid();
        Guid laravelOperationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse("{\"error\":\"synthetic-local-auth-failure\"}", HttpStatusCode.Unauthorized));
        await SeedAidAsync(fixture.Harness, localOperationId, Guid.NewGuid());
        await fixture.Harness.SeedCreateAsync(LaravelSourceId, laravelOperationId, Guid.NewGuid());
        await using (LocalStoreDbContext context = fixture.Harness.Store.CreateContext())
        {
            OutboxOperation laravel = await context.OutboxOperations.SingleAsync(operation =>
                operation.OperationId == laravelOperationId);
            laravel.Status = "Sent";
            await context.SaveChangesAsync();
        }

        await fixture.Cursors.CommitPageAsync(
            LaravelSourceId,
            "changes",
            EmptyPage(50, "laravel-cursor"),
            CancellationToken.None);
        CursorState laravelBefore = await fixture.Cursors.GetAsync(LaravelSourceId, "changes");

        SynchronizeNowResult result = await fixture.Connector.SynchronizeNowAsync(CancellationToken.None);

        Assert.Equal("Pending", result.Status);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, localOperationId));
        Assert.Equal("Sent", await OutboxStatusAsync(fixture, laravelOperationId));
        Assert.Equal(laravelBefore, await fixture.Cursors.GetAsync(LaravelSourceId, "changes"));
    }

    [Fact]
    public async Task SensitiveFailure_DoesNotLogCredentialPayloadOrResponseSecrets()
    {
        const string syntheticPii = "synthetic-private-local-beneficiary";
        Guid operationId = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse($"{{\"message\":\"{Credential} {syntheticPii}\"}}", HttpStatusCode.InternalServerError));
        await SeedAidAsync(fixture.Harness, operationId, Guid.NewGuid());
        SyncEnvelope envelope = await LoadEnvelopeAsync(fixture, operationId);

        LocalSyncException exception = await Assert.ThrowsAsync<LocalSyncException>(() =>
            fixture.Connector.PushAsync(envelope, CancellationToken.None));

        Assert.NotEmpty(fixture.Logger.Messages);
        Assert.All(fixture.Logger.Messages, message =>
        {
            Assert.DoesNotContain(Credential, message, StringComparison.Ordinal);
            Assert.DoesNotContain(syntheticPii, message, StringComparison.Ordinal);
            Assert.DoesNotContain("SyntheticAid", message, StringComparison.Ordinal);
        });
        Assert.DoesNotContain(Credential, exception.Message, StringComparison.Ordinal);
        Assert.DoesNotContain(syntheticPii, exception.Message, StringComparison.Ordinal);
    }

    [Fact]
    public void LocalCertificatePinValidation_RejectsMismatchAndTrustFailure()
    {
        using CertificateFixture certificates = new();
        CertificatePinValidator validator = new(certificates.OtherPin);

        Assert.False(validator.Validate(certificates.Certificate, SslPolicyErrors.None));
        Assert.False(validator.Validate(certificates.Certificate, SslPolicyErrors.RemoteCertificateChainErrors));
    }

    [Fact]
    public void LocalCertificatePinValidation_AcceptsCurrentOrPreviousPinDuringRotation()
    {
        using CertificateFixture certificates = new();
        CertificatePinValidator current = new(certificates.CertificatePin, certificates.OtherPin);
        CertificatePinValidator previous = new(certificates.OtherPin, certificates.CertificatePin);

        Assert.True(current.Validate(certificates.Certificate, SslPolicyErrors.None));
        Assert.True(previous.Validate(certificates.Certificate, SslPolicyErrors.None));
    }

    private static RemoteChangePage EmptyPage(long changeId, string cursor) => new()
    {
        NextCursor = cursor,
        LastChangeLogId = changeId,
        CompletesReconciliation = true,
        Changes = []
    };

    private static SyncEnvelope LocalEnvelope(Guid operationId, Guid clientUuid, string entityType) => new()
    {
        OperationId = operationId,
        SourceId = new SyncSourceId(SourceId),
        DeviceId = DeviceId,
        Entity = new EntityDescriptor
        {
            EntityType = entityType,
            ClientUuid = clientUuid,
            CanonicalId = null,
            References = []
        },
        OperationType = SyncOperationType.Create,
        OccurredAtUtc = DateTimeOffset.Parse("2026-11-25T10:00:00Z"),
        Patches = []
    };

    private static Task SeedAidAsync(
        SynchronizationTestHarness harness,
        Guid operationId,
        Guid clientUuid)
    {
        SyncEnvelope envelope = LocalEnvelope(operationId, clientUuid, "AidOperation");
        string payload = JsonSerializer.Serialize(new
        {
            aidType = "SyntheticAid",
            entitlementId = (Guid?)null,
            amount = 12.50m,
            currencyCode = "USD",
            occurredAtUtc = DateTimeOffset.Parse("2026-11-25T10:00:00Z"),
            notes = (string?)null
        });
        return SeedLocalEntityAsync(harness, envelope, payload, "SyntheticAid");
    }

    private static async Task SeedLocalEntityAsync(
        SynchronizationTestHarness harness,
        SyncEnvelope envelope,
        string payload,
        string searchText)
    {
        await using LocalStoreDbContext context = harness.Store.CreateContext();
        await new UnitOfWork(context).CreateAsync(
            envelope,
            payload,
            searchText,
            new LocalAuditContext(null, "synthetic.write", 1));
    }

    private static async Task<SyncEnvelope> LoadEnvelopeAsync(ConnectorFixture fixture, Guid operationId)
    {
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        string json = (await context.OutboxOperations.SingleAsync(operation => operation.OperationId == operationId)).EnvelopeJson;
        return JsonSerializer.Deserialize<SyncEnvelope>(json)!;
    }

    private static async Task<string> OutboxStatusAsync(ConnectorFixture fixture, Guid operationId)
    {
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        return (await context.OutboxOperations.SingleAsync(operation => operation.OperationId == operationId)).Status;
    }

    private static string HealthJson() =>
        $"{{\"status\":\"ok\",\"api_version\":\"1\",\"source_id\":\"{SourceId}\",\"server_time\":\"2026-11-25T10:00:00Z\",\"certificate\":{{\"sha256_spki_pin\":\"{Convert.ToBase64String(new byte[32])}\",\"not_before\":\"2026-11-24T00:00:00Z\",\"not_after\":\"2027-11-24T00:00:00Z\"}}}}";

    private static string CapabilitiesJson() =>
        $"{{\"api_version\":\"1\",\"source_id\":\"{SourceId}\",\"server_time\":\"2026-11-25T10:00:01Z\",\"certificate\":{{\"sha256_spki_pin\":\"{Convert.ToBase64String(new byte[32])}\",\"not_before\":\"2026-11-24T00:00:00Z\",\"not_after\":\"2027-11-24T00:00:00Z\"}},\"credential_type\":\"independent-device-credential\",\"transport\":\"https-required\",\"client_certificate\":\"trusted-chain-and-sha256-spki-pin-required\",\"permissions\":[\"read\",\"operations\",\"admin\"]}}";

    private static string OperationResultJson(Guid operationId, Guid entityId, string status) =>
        $"{{\"operation_id\":\"{operationId}\",\"operation_type\":\"aid.create\",\"status\":\"{status}\",\"entity_id\":\"{entityId}\",\"row_version\":1,\"conflict_type\":null,\"expires_at\":\"2027-02-23T10:00:00Z\"}}";

    private static string FeedJson(string nextCursor, bool hasMore, params string[] changes) =>
        $"{{\"source_id\":\"{SourceId}\",\"server_time\":\"2026-11-25T10:01:00Z\",\"next_cursor\":\"{nextCursor}\",\"has_more\":{hasMore.ToString().ToLowerInvariant()},\"changes\":[{string.Join(',', changes)}]}}";

    private static string ChangeJson(
        long changeId,
        Guid clientUuid,
        string operation,
        string? entity,
        bool includeTombstone = false)
    {
        string tombstone = includeTombstone
            ? "{\"deleted_at\":\"2026-11-25T10:01:00Z\",\"deleted_by_device_id\":\"remote-device\"}"
            : "null";
        return $"{{\"change_log_id\":{changeId},\"entity_type\":\"AidOperation\",\"entity_id\":\"{clientUuid}\",\"operation_type\":\"{operation}\",\"row_version\":{changeId},\"concurrency_token\":\"{Guid.NewGuid()}\",\"changed_at\":\"2026-11-25T10:01:00Z\",\"device_id\":\"remote-device\",\"entity\":{entity ?? "null"},\"tombstone\":{tombstone}}}";
    }

    private static HttpResponseMessage JsonResponse(
        string json,
        HttpStatusCode statusCode = HttpStatusCode.OK) => new(statusCode)
    {
        Content = new StringContent(json, Encoding.UTF8, "application/json")
    };

    private sealed class ConnectorFixture : IAsyncDisposable
    {
        private ConnectorFixture(
            SynchronizationTestHarness harness,
            RecordingHttpMessageHandler handler,
            RecordingLogger<LocalServerSyncConnector> logger,
            LocalServerSyncConnector connector,
            CursorStore cursors,
            FakeLocalSyncHubClient hub,
            LocalSyncRealtimeCoordinator coordinator)
        {
            Harness = harness;
            Handler = handler;
            Logger = logger;
            Connector = connector;
            Cursors = cursors;
            Hub = hub;
            Coordinator = coordinator;
        }

        public SynchronizationTestHarness Harness { get; }

        public RecordingHttpMessageHandler Handler { get; }

        public RecordingLogger<LocalServerSyncConnector> Logger { get; }

        public LocalServerSyncConnector Connector { get; }

        public CursorStore Cursors { get; }

        public FakeLocalSyncHubClient Hub { get; }

        public LocalSyncRealtimeCoordinator Coordinator { get; }

        public static async Task<ConnectorFixture> CreateAsync(
            Func<RecordedRequest, HttpResponseMessage> responder,
            CommitCrashInterceptor? commitInterceptor = null)
        {
            SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync(commitInterceptor);
            RecordingHttpMessageHandler handler = new(responder);
            FakeSecretStore secrets = new();
            secrets.SetUtf8(SecretTargets.LocalServerDeviceCredential(SourceId), Credential);
            CursorStore cursors = new(harness.Store, new LocalChangePageApplier(), TimeProvider.System);
            RecordingLogger<LocalServerSyncConnector> logger = new();
            LocalServerSyncConnector connector = new(
                new FakeHttpClientFactory(handler),
                new LocalConnectorOptions
                {
                    SourceId = SourceId,
                    DeviceId = DeviceId,
                    UserId = UserId,
                    ClientCertificateThumbprint = "synthetic-client-thumbprint",
                    MaxAttempts = 3,
                    PullPageSize = 2
                },
                harness.Store,
                cursors,
                secrets,
                new RetryPolicy(
                    new RetryOptions
                    {
                        InitialDelay = TimeSpan.FromMilliseconds(1),
                        MaximumDelay = TimeSpan.FromMilliseconds(1)
                    },
                    () => 0),
                TimeProvider.System,
                logger);
            FakeLocalSyncHubClient hub = new();
            LocalSyncRealtimeCoordinator coordinator = new(
                hub,
                connector,
                new RecordingLogger<LocalSyncRealtimeCoordinator>());
            return new ConnectorFixture(harness, handler, logger, connector, cursors, hub, coordinator);
        }

        public ValueTask DisposeAsync() => Harness.DisposeAsync();
    }

    private sealed class FakeHttpClientFactory(RecordingHttpMessageHandler handler) : ILocalHttpClientFactory
    {
        public Task<HttpClient> CreateAsync(CancellationToken cancellationToken) => Task.FromResult(new HttpClient(
            handler,
            disposeHandler: false)
        {
            BaseAddress = new Uri("https://synthetic.invalid/", UriKind.Absolute),
            Timeout = TimeSpan.FromSeconds(5)
        });
    }

    private sealed class RecordingHttpMessageHandler : HttpMessageHandler
    {
        public RecordingHttpMessageHandler(Func<RecordedRequest, HttpResponseMessage> responder)
        {
            Responder = responder;
        }

        public Func<RecordedRequest, HttpResponseMessage> Responder { get; set; }

        public List<RecordedRequest> Requests { get; } = [];

        protected override async Task<HttpResponseMessage> SendAsync(
            HttpRequestMessage request,
            CancellationToken cancellationToken)
        {
            byte[] body = request.Content is null
                ? []
                : await request.Content.ReadAsByteArrayAsync(cancellationToken);
            Dictionary<string, string> headers = request.Headers.ToDictionary(
                header => header.Key,
                header => string.Join(',', header.Value));
            RecordedRequest recorded = new(
                request.Method,
                request.RequestUri?.PathAndQuery ?? string.Empty,
                headers,
                body);
            Requests.Add(recorded);
            return Responder(recorded);
        }
    }

    private sealed record RecordedRequest(
        HttpMethod Method,
        string PathAndQuery,
        IReadOnlyDictionary<string, string> Headers,
        byte[] Body)
    {
        public string? BodyText => Body.Length == 0 ? null : Encoding.UTF8.GetString(Body);

        public string Header(string name) => Headers.TryGetValue(name, out string? value)
            ? value
            : throw new KeyNotFoundException($"The synthetic request header {name} was not recorded.");
    }

    private sealed class FakeSecretStore : ISecretStore
    {
        private readonly Dictionary<string, byte[]> _values = new(StringComparer.Ordinal);

        public void SetUtf8(string target, string value) => _values[target] = Encoding.UTF8.GetBytes(value);

        public void SetBytes(string target, byte[] value) => _values[target] = value.ToArray();

        public byte[]? Read(string target) => _values.TryGetValue(target, out byte[]? value) ? value.ToArray() : null;

        public void Remove(string target) => _values.Remove(target);
    }

    private sealed class FakeLocalSyncHubClient : ILocalSyncHubClient
    {
        private Func<CancellationToken, Task>? _onChanged;

        public Exception? StartException { get; set; }

        public Task StartAsync(Func<CancellationToken, Task> onChanged, CancellationToken cancellationToken)
        {
            if (StartException is not null)
            {
                return Task.FromException(StartException);
            }

            _onChanged = onChanged;
            return Task.CompletedTask;
        }

        public Task EmitAsync() => _onChanged?.Invoke(CancellationToken.None) ?? Task.CompletedTask;

        public Task StopAsync(CancellationToken cancellationToken)
        {
            _onChanged = null;
            return Task.CompletedTask;
        }

        public ValueTask DisposeAsync() => ValueTask.CompletedTask;
    }

    private sealed class RecordingLogger<T> : ILogger<T>
    {
        public List<string> Messages { get; } = [];

        public IDisposable? BeginScope<TState>(TState state) where TState : notnull => null;

        public bool IsEnabled(LogLevel logLevel) => true;

        public void Log<TState>(
            LogLevel logLevel,
            EventId eventId,
            TState state,
            Exception? exception,
            Func<TState, Exception?, string> formatter) => Messages.Add(formatter(state, exception));
    }

    private sealed class CertificateFixture : IDisposable
    {
        private readonly RSA _key;

        public CertificateFixture()
        {
            _key = RSA.Create(2048);
            CertificatePin = Convert.ToHexString(SHA256.HashData(_key.ExportSubjectPublicKeyInfo()));
            CertificateRequest request = new(
                "CN=synthetic-local-server-pin-test",
                _key,
                HashAlgorithmName.SHA256,
                RSASignaturePadding.Pkcs1);
            Certificate = request.CreateSelfSigned(
                DateTimeOffset.Parse("2026-11-24T00:00:00Z"),
                DateTimeOffset.Parse("2027-11-24T00:00:00Z"));
            using RSA otherKey = RSA.Create(2048);
            OtherPin = Convert.ToHexString(SHA256.HashData(otherKey.ExportSubjectPublicKeyInfo()));
        }

        public X509Certificate2 Certificate { get; }

        public string CertificatePin { get; }

        public string OtherPin { get; }

        public void Dispose()
        {
            Certificate.Dispose();
            _key.Dispose();
        }
    }
}
