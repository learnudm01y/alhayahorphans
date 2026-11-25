using System.Net;
using System.Net.Security;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Text;
using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Connectors;
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Identity;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;
using Xunit;

namespace Alhayah.SyncService.Tests;

public sealed class LaravelConnectorTests
{
    private const string SourceId = "laravel:synthetic-a";
    private const string Token = "synthetic-device-token-that-must-not-leak";

    [Fact]
    public async Task HealthCapabilitiesAndConnectionCheck_UseDesktopV1AndReportEveryStage()
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery == "/api/desktop/v1/health")
            {
                return JsonResponse("{\"status\":\"ok\",\"api_version\":\"1.0.0\",\"server_time\":\"2026-11-25T10:00:00Z\"}");
            }

            if (request.PathAndQuery == "/api/desktop/v1/capabilities")
            {
                return JsonResponse("{\"capabilities\":[\"sync:read\",\"sync:write\"],\"device\":{\"deviceId\":\"synthetic-device\"},\"api_version\":\"1.0.0\",\"server_time\":\"2026-11-25T10:00:01Z\"}");
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });

        LaravelHealthResponse health = await fixture.Connector.GetHealthAsync(CancellationToken.None);
        LaravelCapabilitiesResponse capabilities = await fixture.Connector.GetCapabilitiesAsync(CancellationToken.None);
        ConnectionCheckResult check = await fixture.Connector.CheckConnectionAsync(CancellationToken.None);

        Assert.Equal("ok", health.Status);
        Assert.Equal("1.0.0", health.ApiVersion);
        Assert.Equal(["sync:read", "sync:write"], capabilities.Capabilities);
        Assert.Equal(["DNS", "TCP", "TLS", "Auth", "Compatibility"], check.Stages.Select(stage => stage.Stage));
        Assert.All(check.Stages, stage => Assert.True(stage.Succeeded));
        Assert.All(fixture.Handler.Requests, request =>
        {
            Assert.StartsWith("/api/desktop/v1/", request.PathAndQuery, StringComparison.Ordinal);
            Assert.DoesNotContain("mobile", request.PathAndQuery, StringComparison.OrdinalIgnoreCase);
        });
    }

    [Theory]
    [InlineData(HttpStatusCode.Unauthorized, "unauthorized")]
    [InlineData(HttpStatusCode.Forbidden, "forbidden")]
    public async Task PushBatchAsync_WithAuthenticationFailure_DoesNotRetryAndLeavesOutboxPending(
        HttpStatusCode statusCode,
        string expectedError)
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse("{\"error\":\"synthetic-auth-failure\"}", statusCode));
        SyncEnvelope envelope = await fixture.Harness.SeedCreateAsync(SourceId, Guid.NewGuid(), Guid.NewGuid());

        LaravelSyncException exception = await Assert.ThrowsAsync<LaravelSyncException>(() =>
            fixture.Connector.PushBatchAsync([envelope], CancellationToken.None));

        Assert.Equal(expectedError, exception.ErrorCode);
        Assert.Equal(statusCode, exception.StatusCode);
        Assert.Single(fixture.Handler.Requests);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, envelope.OperationId));
    }

    [Fact]
    public async Task PushBatchAsync_WithRateLimitThenSuccess_ReplaysIdenticalIdempotentOperation()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        int calls = 0;
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            calls++;
            if (calls == 1)
            {
                HttpResponseMessage response = JsonResponse("{\"retry_after\":0}", (HttpStatusCode)429);
                response.Headers.RetryAfter = new System.Net.Http.Headers.RetryConditionHeaderValue(TimeSpan.Zero);
                return response;
            }

            return JsonResponse(PushResultJson(operationId, clientUuid));
        });
        SyncEnvelope envelope = await fixture.Harness.SeedCreateAsync(SourceId, operationId, clientUuid);

        LaravelPushBatchResult result = await fixture.Connector.PushBatchAsync([envelope], CancellationToken.None);

        Assert.Equal("applied", Assert.Single(result.Results).Status);
        Assert.Equal(2, fixture.Handler.Requests.Count);
        Assert.Equal(fixture.Handler.Requests[0].BodyText, fixture.Handler.Requests[1].BodyText);
        using JsonDocument body = JsonDocument.Parse(fixture.Handler.Requests[0].BodyText!);
        JsonElement operation = body.RootElement.GetProperty("operations")[0];
        Assert.Equal(operationId, operation.GetProperty("operationId").GetGuid());
        Assert.Equal($"desktop:{operationId:N}", operation.GetProperty("idempotencyKey").GetString());
        Assert.Equal("fieldPatches", operation.EnumerateObject().Single(property => property.Name == "fieldPatches").Name);
    }

    [Fact]
    public async Task PushBatchAsync_WithUpdate_SendsBaseVersionAndFieldPatches()
    {
        Guid operationId = Guid.NewGuid();
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => JsonResponse(
            $"{{\"results\":[{{\"operationId\":\"{operationId}\",\"status\":\"conflict\",\"clientUuid\":\"{clientUuid}\",\"canonicalId\":\"canonical-42\",\"conflict\":{{\"type\":\"VersionMismatch\"}}}}],\"retentionWarnings\":[],\"server_time\":\"2026-11-25T10:00:00Z\"}}"));
        SyncEnvelope envelope = SynchronizationTestHarness.CreateEnvelope(SourceId, operationId, clientUuid) with
        {
            OperationType = SyncOperationType.Update,
            BaseVersion = 4,
            RowVersion = "row-4",
            Entity = new EntityDescriptor
            {
                EntityType = "synthetic.records",
                ClientUuid = clientUuid,
                CanonicalId = "canonical-42",
                References = []
            },
            Patches =
            [
                new FieldPatch
                {
                    FieldName = "displayName",
                    BaseValue = JsonSerializer.SerializeToElement("Before"),
                    LocalValue = JsonSerializer.SerializeToElement("After"),
                    Criticality = FieldCriticality.Mergeable
                }
            ]
        };

        LaravelPushBatchResult result = await fixture.Connector.PushBatchAsync([envelope], CancellationToken.None);

        Assert.Equal("conflict", Assert.Single(result.Results).Status);
        using JsonDocument body = JsonDocument.Parse(fixture.Handler.Requests.Single().BodyText!);
        JsonElement operation = body.RootElement.GetProperty("operations")[0];
        Assert.Equal(4, operation.GetProperty("baseVersion").GetUInt64());
        Assert.Equal("row-4", operation.GetProperty("rowVersion").GetString());
        JsonElement patch = operation.GetProperty("fieldPatches")[0];
        Assert.Equal("displayName", patch.GetProperty("fieldName").GetString());
        Assert.Equal("Before", patch.GetProperty("baseValue").GetString());
        Assert.Equal("After", patch.GetProperty("localValue").GetString());
        Assert.Equal("Mergeable", patch.GetProperty("criticality").GetString());
        Assert.False(operation.TryGetProperty("patches", out _));
    }

    [Fact]
    public async Task PushBatchAsync_WithTimeout_UsesBoundedRetryAndLeavesOperationPending()
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => throw new TimeoutException("synthetic timeout"));
        SyncEnvelope envelope = await fixture.Harness.SeedCreateAsync(SourceId, Guid.NewGuid(), Guid.NewGuid());

        LaravelSyncException exception = await Assert.ThrowsAsync<LaravelSyncException>(() =>
            fixture.Connector.PushBatchAsync([envelope], CancellationToken.None));

        Assert.True(exception.IsTransient);
        Assert.Equal("timeout", exception.ErrorCode);
        Assert.Equal(3, fixture.Handler.Requests.Count);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, envelope.OperationId));
    }

    [Fact]
    public async Task PushBatchAsync_WithCancellation_DoesNotRetryOrAcknowledgeOperation()
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => JsonResponse("{}"));
        SyncEnvelope envelope = await fixture.Harness.SeedCreateAsync(SourceId, Guid.NewGuid(), Guid.NewGuid());
        using CancellationTokenSource cancellation = new();
        cancellation.Cancel();

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() =>
            fixture.Connector.PushBatchAsync([envelope], cancellation.Token));

        Assert.Empty(fixture.Handler.Requests);
        Assert.Equal("Pending", await OutboxStatusAsync(fixture, envelope.OperationId));
    }

    [Fact]
    public async Task PushBatchAsync_WithSensitiveFailureData_DoesNotLogTokenPayloadOrResponseSecrets()
    {
        const string syntheticPii = "synthetic-private-display-name";
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ =>
            JsonResponse($"{{\"message\":\"{Token} {syntheticPii}\"}}", HttpStatusCode.InternalServerError));
        SyncEnvelope envelope = await fixture.Harness.SeedCreateAsync(SourceId, Guid.NewGuid(), Guid.NewGuid());

        LaravelSyncException exception = await Assert.ThrowsAsync<LaravelSyncException>(() =>
            fixture.Connector.PushBatchAsync([envelope], CancellationToken.None));

        Assert.NotEmpty(fixture.Logger.Messages);
        Assert.All(fixture.Logger.Messages, message =>
        {
            Assert.DoesNotContain(Token, message, StringComparison.Ordinal);
            Assert.DoesNotContain(syntheticPii, message, StringComparison.Ordinal);
            Assert.DoesNotContain("Synthetic record", message, StringComparison.Ordinal);
        });
        Assert.DoesNotContain(Token, exception.Message, StringComparison.Ordinal);
        Assert.DoesNotContain(syntheticPii, exception.Message, StringComparison.Ordinal);
    }

    [Fact]
    public async Task PullAsync_WithCursorPagesAndTombstone_CommitsEveryPageAndPreservesDeletion()
    {
        Guid clientUuid = Guid.NewGuid();
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.Contains("cursor=cursor-10", StringComparison.Ordinal))
            {
                return JsonResponse(
                    $"{{\"sourceId\":\"{SourceId}\",\"changes\":[{{\"change_log_id\":11,\"table_name\":\"synthetic.records\",\"primary_key\":\"canonical-11\",\"operation\":\"create\",\"client_uuid\":\"{clientUuid}\",\"row_version\":\"11\",\"row_version_source\":\"row_version\",\"soft_delete\":false,\"changed_at\":\"2026-11-25T10:00:00Z\",\"data\":{{\"displayName\":\"Remote synthetic\"}},\"tombstone\":null}}],\"requires_full_reconciliation\":false,\"reason\":null,\"has_more\":true,\"next_cursor\":\"cursor-11\",\"server_time\":\"2026-11-25T10:00:00Z\"}}");
            }

            return JsonResponse(
                $"{{\"sourceId\":\"{SourceId}\",\"changes\":[{{\"change_log_id\":12,\"table_name\":\"synthetic.records\",\"primary_key\":\"canonical-11\",\"operation\":\"delete\",\"client_uuid\":\"{clientUuid}\",\"row_version\":\"12\",\"row_version_source\":\"row_version\",\"soft_delete\":true,\"changed_at\":\"2026-11-25T10:01:00Z\",\"data\":null,\"tombstone\":{{\"table_name\":\"synthetic.records\",\"primary_key\":\"canonical-11\",\"client_uuid\":\"{clientUuid}\",\"row_version\":\"12\",\"deleted_at\":\"2026-11-25T10:01:00Z\",\"deleted_by_device_id\":\"remote-device\"}}}}],\"requires_full_reconciliation\":false,\"reason\":null,\"has_more\":false,\"next_cursor\":\"cursor-12\",\"server_time\":\"2026-11-25T10:01:00Z\"}}");
        });
        await fixture.Cursors.CommitPageAsync(
            SourceId,
            "changes",
            CursorTests.Page(10, "snapshot-seeded", "cursor-10", Guid.NewGuid(), completesReconciliation: true),
            CancellationToken.None);

        LaravelPullResult result = await fixture.Connector.PullAsync(CancellationToken.None);

        Assert.Equal(2, result.PageCount);
        Assert.Equal(2, result.Changes.Count);
        Assert.Equal("remote-device", result.Changes[1].Tombstone!.DeletedByDeviceId);
        Assert.Equal("cursor-12", (await fixture.Cursors.GetAsync(SourceId)).Cursor);
        Assert.Equal(2, fixture.Handler.Requests.Count);
        Assert.Contains("cursor=cursor-11", fixture.Handler.Requests[1].PathAndQuery, StringComparison.Ordinal);
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        CachedEntity deleted = await context.CachedEntities.IgnoreQueryFilters().SingleAsync(entity => entity.ClientUuid == clientUuid);
        Assert.Equal("canonical-11", deleted.CanonicalId);
        Assert.Equal("12", deleted.RowVersion);
        Assert.Equal("remote-device", deleted.DeletedByDeviceId);
        Assert.NotNull(deleted.DeletedAtUtc);
    }

    [Fact]
    public async Task PullAsync_WithSnapshotMismatch_DiscardsOnlySnapshotAndStartsFreshReconciliation()
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.PathAndQuery.Contains("snapshot_id=old-snapshot", StringComparison.Ordinal))
            {
                return JsonResponse(
                    $"{{\"sourceId\":\"{SourceId}\",\"snapshot_id\":null,\"records\":[],\"requires_full_reconciliation\":true,\"reason\":\"invalid_snapshot\",\"has_more\":false,\"next_page_cursor\":null,\"next_cursor\":null,\"change_range\":null,\"server_time\":\"2026-11-25T10:00:00Z\"}}");
            }

            return JsonResponse(
                $"{{\"sourceId\":\"{SourceId}\",\"snapshot_id\":\"new-snapshot\",\"records\":[],\"requires_full_reconciliation\":false,\"reason\":null,\"has_more\":false,\"next_page_cursor\":null,\"next_cursor\":\"cursor-60\",\"change_range\":{{\"after_change_log_id\":60,\"through_change_log_id\":60}},\"server_time\":\"2026-11-25T10:00:00Z\"}}");
        });
        await fixture.Cursors.CommitPageAsync(
            SourceId,
            "changes",
            CursorTests.Page(50, "old-snapshot", "old-page", Guid.NewGuid(), completesReconciliation: false),
            CancellationToken.None);

        LaravelPullResult result = await fixture.Connector.PullAsync(CancellationToken.None);

        Assert.Equal(2, fixture.Handler.Requests.Count);
        Assert.Contains("snapshot_id=old-snapshot", fixture.Handler.Requests[0].PathAndQuery, StringComparison.Ordinal);
        Assert.Contains("page_cursor=old-page", fixture.Handler.Requests[0].PathAndQuery, StringComparison.Ordinal);
        Assert.DoesNotContain("snapshot_id=", fixture.Handler.Requests[1].PathAndQuery, StringComparison.Ordinal);
        Assert.DoesNotContain("page_cursor=", fixture.Handler.Requests[1].PathAndQuery, StringComparison.Ordinal);
        CursorState state = await fixture.Cursors.GetAsync(SourceId);
        Assert.Equal("new-snapshot", state.SnapshotId);
        Assert.Equal("cursor-60", state.Cursor);
        Assert.Equal(ReconciliationPhase.Reconciled, state.Phase);
        Assert.Equal(1, result.PageCount);
    }

    [Fact]
    public async Task UploadAttachmentAsync_WithExistingChunk_UploadsOnlyMissingChunksWithChecksumsAndCompletes()
    {
        byte[] content = [1, 2, 3, 4, 5, 6, 7];
        Guid operationId = Guid.NewGuid();
        Guid attachmentUuid = Guid.NewGuid();
        string checksum = Convert.ToHexString(SHA256.HashData(content));
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(request =>
        {
            if (request.Method == HttpMethod.Post && request.PathAndQuery == "/api/desktop/v1/attachments")
            {
                return JsonResponse(AttachmentStatusJson(attachmentUuid, operationId, checksum, [0], [1, 2], false));
            }

            if (request.Method == HttpMethod.Put)
            {
                return JsonResponse(AttachmentStatusJson(attachmentUuid, operationId, checksum, [0, 1, 2], [], false));
            }

            if (request.Method == HttpMethod.Post && request.PathAndQuery.EndsWith("/complete", StringComparison.Ordinal))
            {
                return JsonResponse(AttachmentStatusJson(attachmentUuid, operationId, checksum, [0, 1, 2], [], true));
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        });
        LaravelAttachmentManifest manifest = Manifest(operationId, attachmentUuid, content.LongLength, checksum);

        LaravelAttachmentStatus completed = await fixture.Connector.UploadAttachmentAsync(
            manifest,
            new MemoryStream(content, writable: false),
            CancellationToken.None);

        Assert.True(completed.Completed);
        List<RecordedRequest> chunks = fixture.Handler.Requests.Where(request => request.Method == HttpMethod.Put).ToList();
        Assert.Equal(2, chunks.Count);
        Assert.Equal("/1", chunks[0].PathAndQuery[^2..]);
        Assert.Equal("/2", chunks[1].PathAndQuery[^2..]);
        Assert.Equal([4, 5, 6], chunks[0].Body);
        Assert.Equal([7], chunks[1].Body);
        Assert.Equal(Convert.ToHexString(SHA256.HashData(new byte[] { 4, 5, 6 })), chunks[0].Header("X-Chunk-Sha256"));
        Assert.Equal(Convert.ToHexString(SHA256.HashData(new byte[] { 7 })), chunks[1].Header("X-Chunk-Sha256"));
        Assert.NotEqual(operationId.ToString(), chunks[0].Header("X-Operation-Id"));
        Assert.NotEqual(chunks[0].Header("X-Operation-Id"), chunks[1].Header("X-Operation-Id"));
        Assert.Single(fixture.Handler.Requests, request => request.PathAndQuery.EndsWith("/complete", StringComparison.Ordinal));
    }

    [Fact]
    public async Task UploadAttachmentAsync_WithWrongLocalChecksum_DoesNotSendAnyRequest()
    {
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => JsonResponse("{}"));
        byte[] content = [9, 8, 7];
        LaravelAttachmentManifest manifest = Manifest(Guid.NewGuid(), Guid.NewGuid(), content.LongLength, new string('0', 64));

        await Assert.ThrowsAsync<InvalidDataException>(() => fixture.Connector.UploadAttachmentAsync(
            manifest,
            new MemoryStream(content, writable: false),
            CancellationToken.None));

        Assert.Empty(fixture.Handler.Requests);
    }

    [Fact]
    public async Task DownloadAttachmentAsync_WithMatchingChecksum_WritesContentAfterVerification()
    {
        byte[] content = [3, 1, 4, 1, 5];
        Guid attachmentUuid = Guid.NewGuid();
        string checksum = Convert.ToHexString(SHA256.HashData(content));
        await using ConnectorFixture fixture = await ConnectorFixture.CreateAsync(_ => new HttpResponseMessage(HttpStatusCode.OK)
        {
            Content = new ByteArrayContent(content)
        });
        using MemoryStream destination = new();

        await fixture.Connector.DownloadAttachmentAsync(
            attachmentUuid,
            checksum,
            destination,
            CancellationToken.None);

        Assert.Equal(content, destination.ToArray());
        Assert.Equal($"/api/desktop/v1/attachments/{attachmentUuid}/content", fixture.Handler.Requests.Single().PathAndQuery);
    }

    [Fact]
    public void CertificatePinValidator_WithMismatchedPin_RejectsCertificate()
    {
        using CertificateFixture certificates = new();
        CertificatePinValidator validator = new(certificates.OtherPin);

        bool valid = validator.Validate(certificates.Certificate, SslPolicyErrors.None);

        Assert.False(valid);
    }

    [Fact]
    public void CertificatePinValidator_WithCurrentOrPreviousPin_AcceptsRotationWithoutTrustBypass()
    {
        using CertificateFixture certificates = new();
        CertificatePinValidator current = new(certificates.CertificatePin, certificates.OtherPin);
        CertificatePinValidator previous = new(certificates.OtherPin, certificates.CertificatePin);

        Assert.True(current.Validate(certificates.Certificate, SslPolicyErrors.None));
        Assert.True(previous.Validate(certificates.Certificate, SslPolicyErrors.None));
        Assert.False(current.Validate(certificates.Certificate, SslPolicyErrors.RemoteCertificateChainErrors));
    }

    private static async Task<string> OutboxStatusAsync(ConnectorFixture fixture, Guid operationId)
    {
        await using LocalStoreDbContext context = fixture.Harness.Store.CreateContext();
        return (await context.OutboxOperations.SingleAsync(operation => operation.OperationId == operationId)).Status;
    }

    private static LaravelAttachmentManifest Manifest(
        Guid operationId,
        Guid attachmentUuid,
        long fileSize,
        string checksum) => new()
    {
        OperationId = operationId,
        AttachmentUuid = attachmentUuid,
        EntityType = "synthetic.records",
        PrimaryKey = "canonical-42",
        OriginalFileName = "synthetic.bin",
        MimeType = "text/plain",
        FileSize = fileSize,
        Sha256 = checksum,
        ChunkSize = 3,
        TotalChunks = (int)Math.Ceiling(fileSize / 3d)
    };

    private static string PushResultJson(Guid operationId, Guid clientUuid) =>
        $"{{\"results\":[{{\"operationId\":\"{operationId}\",\"status\":\"applied\",\"clientUuid\":\"{clientUuid}\",\"canonicalId\":\"canonical-{clientUuid:N}\",\"rowVersion\":\"server-row-1\",\"baseVersion\":1,\"conflict\":null}}],\"retentionWarnings\":[],\"server_time\":\"2026-11-25T10:00:00Z\"}}";

    private static string AttachmentStatusJson(
        Guid attachmentUuid,
        Guid operationId,
        string checksum,
        int[] received,
        int[] missing,
        bool completed) =>
        $"{{\"attachmentUuid\":\"{attachmentUuid}\",\"operationId\":\"{operationId}\",\"entityType\":\"synthetic.records\",\"primaryKey\":\"canonical-42\",\"clientUuid\":null,\"attachmentReferenceKind\":null,\"canonicalAttachmentId\":\"{attachmentUuid}\",\"originalFileName\":\"synthetic.bin\",\"mimeType\":\"text/plain\",\"fileSize\":7,\"sha256\":\"{checksum}\",\"chunkSize\":3,\"totalChunks\":3,\"receivedChunks\":{JsonSerializer.Serialize(received)},\"missingChunks\":{JsonSerializer.Serialize(missing)},\"status\":\"{(completed ? "completed" : "uploading")}\",\"completed\":{completed.ToString().ToLowerInvariant()},\"completedAt\":null,\"downloadUrl\":null,\"createdAt\":\"2026-11-25T10:00:00Z\",\"updatedAt\":\"2026-11-25T10:00:00Z\"}}";

    private static HttpResponseMessage JsonResponse(string json, HttpStatusCode statusCode = HttpStatusCode.OK) => new(statusCode)
    {
        Content = new StringContent(json, Encoding.UTF8, "application/json")
    };

    private sealed class ConnectorFixture : IAsyncDisposable
    {
        private ConnectorFixture(
            SynchronizationTestHarness harness,
            RecordingHttpMessageHandler handler,
            RecordingLogger<LaravelSyncConnector> logger,
            LaravelSyncConnector connector,
            CursorStore cursors)
        {
            Harness = harness;
            Handler = handler;
            Logger = logger;
            Connector = connector;
            Cursors = cursors;
        }

        public SynchronizationTestHarness Harness { get; }

        public RecordingHttpMessageHandler Handler { get; }

        public RecordingLogger<LaravelSyncConnector> Logger { get; }

        public LaravelSyncConnector Connector { get; }

        public CursorStore Cursors { get; }

        public static async Task<ConnectorFixture> CreateAsync(
            Func<RecordedRequest, HttpResponseMessage> responder)
        {
            SynchronizationTestHarness harness = await SynchronizationTestHarness.CreateAsync();
            RecordingHttpMessageHandler handler = new(responder);
            FakeSecretStore secrets = new();
            secrets.SetUtf8(SecretTargets.LaravelDeviceToken(SourceId), Token);
            CursorStore cursors = new(harness.Store, new LaravelChangePageApplier(), TimeProvider.System);
            ClientIdentityMapStore identities = new(harness.Store, TimeProvider.System);
            RecordingLogger<LaravelSyncConnector> logger = new();
            LaravelSyncConnector connector = new(
                new FakeHttpClientFactory(handler),
                new LaravelConnectorOptions
                {
                    SourceId = SourceId,
                    DeviceId = "synthetic-device",
                    AppVersion = "1.0.0",
                    MaxAttempts = 3,
                    PullPageSize = 200
                },
                harness.Store,
                cursors,
                identities,
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
            return new ConnectorFixture(harness, handler, logger, connector, cursors);
        }

        public ValueTask DisposeAsync() => Harness.DisposeAsync();
    }

    private sealed class FakeHttpClientFactory(RecordingHttpMessageHandler handler) : ILaravelHttpClientFactory
    {
        public Task<HttpClient> CreateAsync(CancellationToken cancellationToken) => Task.FromResult(new HttpClient(handler, disposeHandler: false)
        {
            BaseAddress = new Uri("https://synthetic.invalid/api/desktop/v1/"),
            Timeout = TimeSpan.FromSeconds(5)
        });
    }

    private sealed class RecordingHttpMessageHandler(Func<RecordedRequest, HttpResponseMessage> responder) : HttpMessageHandler
    {
        public List<RecordedRequest> Requests { get; } = [];

        protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            byte[] body = request.Content is null
                ? []
                : await request.Content.ReadAsByteArrayAsync(cancellationToken);
            Dictionary<string, string> headers = request.Headers.ToDictionary(header => header.Key, header => string.Join(",", header.Value));
            if (request.Content is not null)
            {
                foreach (KeyValuePair<string, IEnumerable<string>> header in request.Content.Headers)
                {
                    headers[header.Key] = string.Join(",", header.Value);
                }
            }

            RecordedRequest recorded = new(
                request.Method,
                request.RequestUri?.PathAndQuery ?? string.Empty,
                headers,
                body);
            Requests.Add(recorded);
            return responder(recorded);
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
                "CN=synthetic-laravel-pin-test",
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
