using System.Globalization;
using System.Net;
using System.Net.Http.Headers;
using System.Net.Sockets;
using System.Security.Authentication;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using Alhayah.Contracts.Domain;
using Alhayah.Contracts.Ipc;
using Alhayah.LocalData.Data;
using Alhayah.SyncService.Conflicts;
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Identity;
using Alhayah.SyncService.Outbox;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Services;
using Alhayah.SyncService.Storage;
using Alhayah.SyncService.Synchronization;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Microsoft.Extensions.Logging;

namespace Alhayah.SyncService.Connectors;

public sealed class LaravelSyncConnector : ISyncChannel, IConfirmableOutboxTransport
{
    private const string Channel = "changes";
    private static readonly string[] StageNames = ["DNS", "TCP", "TLS", "Auth", "Compatibility"];
    private readonly ILaravelHttpClientFactory _clientFactory;
    private readonly LaravelConnectorOptions _options;
    private readonly ISyncServiceStore _store;
    private readonly CursorStore _cursors;
    private readonly ClientIdentityMapStore _identities;
    private readonly ISecretStore _secrets;
    private readonly RetryPolicy _retryPolicy;
    private readonly TimeProvider _timeProvider;
    private readonly ILogger<LaravelSyncConnector> _logger;

    public LaravelSyncConnector(
        ILaravelHttpClientFactory clientFactory,
        LaravelConnectorOptions options,
        ISyncServiceStore store,
        CursorStore cursors,
        ClientIdentityMapStore identities,
        ISecretStore secrets,
        RetryPolicy retryPolicy,
        TimeProvider timeProvider,
        ILogger<LaravelSyncConnector> logger)
    {
        _clientFactory = clientFactory ?? throw new ArgumentNullException(nameof(clientFactory));
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _store = store ?? throw new ArgumentNullException(nameof(store));
        _cursors = cursors ?? throw new ArgumentNullException(nameof(cursors));
        _identities = identities ?? throw new ArgumentNullException(nameof(identities));
        _secrets = secrets ?? throw new ArgumentNullException(nameof(secrets));
        _retryPolicy = retryPolicy ?? throw new ArgumentNullException(nameof(retryPolicy));
        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
        _ = new SyncSourceId(options.SourceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(options.DeviceId);
        ArgumentException.ThrowIfNullOrWhiteSpace(options.AppVersion);
        if (options.MaxAttempts < 1 || options.PullPageSize is < 1 or > 1000)
        {
            throw new ArgumentOutOfRangeException(nameof(options));
        }
    }

    public string SourceId => _options.SourceId;

    public async Task<LaravelHealthResponse> GetHealthAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] response = await SendWithRetryAsync(
            client,
            () => new HttpRequestMessage(HttpMethod.Get, "health"),
            authenticated: false,
            cancellationToken).ConfigureAwait(false);
        return Deserialize<LaravelHealthResponse>(response);
    }

    public async Task<LaravelCapabilitiesResponse> GetCapabilitiesAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] response = await SendWithRetryAsync(
            client,
            () => CreateAuthenticatedRequest(HttpMethod.Get, "capabilities"),
            authenticated: true,
            cancellationToken).ConfigureAwait(false);
        return Deserialize<LaravelCapabilitiesResponse>(response);
    }

    public async Task<ConnectionCheckResult> CheckConnectionAsync(CancellationToken cancellationToken)
    {
        Dictionary<string, ConnectionCheckStageResult> stages = StageNames.ToDictionary(
            name => name,
            name => new ConnectionCheckStageResult
            {
                Stage = name,
                Succeeded = false,
                ErrorCode = "not_checked"
            },
            StringComparer.Ordinal);
        try
        {
            using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
            LaravelHealthResponse health;
            try
            {
                byte[] response = await SendWithRetryAsync(
                    client,
                    () => new HttpRequestMessage(HttpMethod.Get, "health"),
                    authenticated: false,
                    cancellationToken).ConfigureAwait(false);
                health = Deserialize<LaravelHealthResponse>(response);
                MarkTransportStages(stages, succeeded: true);
            }
            catch (LaravelSyncException exception)
            {
                string failedStage = ClassifyFailure(exception);
                if (exception.StatusCode.HasValue)
                {
                    MarkTransportStages(stages, succeeded: true);
                    failedStage = exception.StatusCode is HttpStatusCode.Unauthorized or HttpStatusCode.Forbidden
                        ? "Auth"
                        : "Compatibility";
                }

                SetStageFailure(stages, failedStage, exception.ErrorCode);
                return new ConnectionCheckResult { SourceId = SourceId, Stages = stages.Values.ToArray() };
            }

            try
            {
                byte[] response = await SendWithRetryAsync(
                    client,
                    () => CreateAuthenticatedRequest(HttpMethod.Get, "capabilities"),
                    authenticated: true,
                    cancellationToken).ConfigureAwait(false);
                LaravelCapabilitiesResponse capabilities = Deserialize<LaravelCapabilitiesResponse>(response);
                SetStageSuccess(stages, "Auth");
                bool compatible = IsCompatible(health.ApiVersion) && IsCompatible(capabilities.ApiVersion);
                stages["Compatibility"] = new ConnectionCheckStageResult
                {
                    Stage = "Compatibility",
                    Succeeded = compatible,
                    ErrorCode = compatible ? null : "incompatible_api"
                };
            }
            catch (LaravelSyncException exception)
            {
                string failedStage = exception.StatusCode.HasValue ? "Auth" : ClassifyFailure(exception);
                SetStageFailure(stages, failedStage, exception.ErrorCode);
            }
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception exception) when (exception is HttpRequestException or IOException or AuthenticationException)
        {
            string failedStage = FindException<AuthenticationException>(exception) is not null ? "TLS" : "TCP";
            SetStageFailure(stages, failedStage, failedStage == "TLS" ? "tls_failed" : "connection_failed");
        }

        return new ConnectionCheckResult { SourceId = SourceId, Stages = stages.Values.ToArray() };
    }

    public async Task<LaravelPullResult> PullAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        CursorState state = await _cursors.GetAsync(SourceId, Channel, cancellationToken).ConfigureAwait(false);
        if (state.ReconciliationRequired || state.Phase != ReconciliationPhase.Reconciled)
        {
            return await ReconcileAsync(client, state, cancellationToken).ConfigureAwait(false);
        }

        List<LaravelChange> changes = [];
        int pageCount = 0;
        while (true)
        {
            string path = "sync/pull?limit=" + _options.PullPageSize.ToString(CultureInfo.InvariantCulture);
            if (!string.IsNullOrWhiteSpace(state.Cursor))
            {
                path += "&cursor=" + Uri.EscapeDataString(state.Cursor);
            }

            byte[] response = await SendWithRetryAsync(
                client,
                () => CreateAuthenticatedRequest(HttpMethod.Get, path),
                authenticated: true,
                cancellationToken).ConfigureAwait(false);
            LaravelPullResponse page = Deserialize<LaravelPullResponse>(response);
            EnsureSource(page.SourceId);
            if (page.RequiresFullReconciliation)
            {
                CursorState fresh = state with
                {
                    Cursor = null,
                    SnapshotId = null,
                    Phase = ReconciliationPhase.FullReconciliation,
                    ReconciliationRequired = true
                };
                LaravelPullResult reconciliation = await ReconcileAsync(client, fresh, cancellationToken).ConfigureAwait(false);
                return new LaravelPullResult
                {
                    Changes = changes.Concat(reconciliation.Changes).ToArray(),
                    SnapshotRecords = reconciliation.SnapshotRecords,
                    PageCount = pageCount + reconciliation.PageCount,
                    Cursor = reconciliation.Cursor
                };
            }

            if (string.IsNullOrWhiteSpace(page.NextCursor))
            {
                throw new LaravelSyncException("invalid_response", "The Laravel pull response omitted its next cursor.");
            }

            RemoteChangePage remotePage = new()
            {
                SnapshotId = state.SnapshotId,
                NextCursor = page.NextCursor,
                LastChangeLogId = page.Changes.Count == 0
                    ? state.LastChangeLogId ?? 0
                    : page.Changes.Max(change => change.ChangeLogId),
                CompletesReconciliation = true,
                Changes = page.Changes.Select(ToRemoteChange).ToArray()
            };
            await _cursors.CommitPageAsync(SourceId, Channel, remotePage, cancellationToken).ConfigureAwait(false);
            changes.AddRange(page.Changes);
            pageCount++;
            state = await _cursors.GetAsync(SourceId, Channel, cancellationToken).ConfigureAwait(false);
            if (!page.HasMore)
            {
                return new LaravelPullResult
                {
                    Changes = changes,
                    PageCount = pageCount,
                    Cursor = state
                };
            }
        }
    }

    public async Task<LaravelPushBatchResult> PushBatchAsync(
        IReadOnlyList<SyncEnvelope> envelopes,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(envelopes);
        cancellationToken.ThrowIfCancellationRequested();
        if (envelopes.Count is < 1 or > 100)
        {
            throw new ArgumentException("A Laravel push batch must contain between 1 and 100 operations.", nameof(envelopes));
        }

        if (envelopes.Select(envelope => envelope.OperationId).Distinct().Count() != envelopes.Count)
        {
            throw new ArgumentException("A push batch cannot contain duplicate operation identifiers.", nameof(envelopes));
        }

        List<Guid> pending = [];
        List<SyncEnvelope> ready = [];
        foreach (SyncEnvelope envelope in envelopes)
        {
            EnsureEnvelopeSource(envelope);
            if (IsReady(envelope))
            {
                ready.Add(envelope);
            }
            else
            {
                pending.Add(envelope.OperationId);
            }
        }

        if (ready.Count == 0)
        {
            return new LaravelPushBatchResult { PendingOperationIds = pending };
        }

        Dictionary<Guid, JsonElement> createPayloads = await LoadCreatePayloadsAsync(ready, cancellationToken)
            .ConfigureAwait(false);
        JsonArray operations = [];
        foreach (SyncEnvelope envelope in ready)
        {
            operations.Add(BuildPushOperation(envelope, createPayloads));
        }

        string requestJson = new JsonObject { ["operations"] = operations }.ToJsonString();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] response = await SendWithRetryAsync(
            client,
            () => CreateAuthenticatedRequest(HttpMethod.Post, "sync/push", requestJson),
            authenticated: true,
            cancellationToken).ConfigureAwait(false);
        LaravelPushResponseEnvelope payload = Deserialize<LaravelPushResponseEnvelope>(response);
        HashSet<Guid> returnedIds = payload.Results.Select(result => result.OperationId).ToHashSet();
        if (returnedIds.Count != payload.Results.Count || payload.Results.Any(result => !ready.Any(item => item.OperationId == result.OperationId)))
        {
            throw new LaravelSyncException("invalid_response", "The Laravel push response contains unknown or duplicate operations.");
        }

        pending.AddRange(ready
            .Where(envelope => !returnedIds.Contains(envelope.OperationId))
            .Select(envelope => envelope.OperationId));
        pending.AddRange(payload.Results
            .Where(result => !string.Equals(result.Status, "applied", StringComparison.OrdinalIgnoreCase))
            .Select(result => result.OperationId));
        await _identities.ApplyPushResultsAsync(payload.Results, cancellationToken).ConfigureAwait(false);
        await CaptureServerConflictsAsync(payload.Results, cancellationToken).ConfigureAwait(false);
        return new LaravelPushBatchResult
        {
            Results = payload.Results,
            PendingOperationIds = pending.Distinct().ToArray()
        };
    }

    public async Task<LaravelAttachmentStatus> UploadAttachmentAsync(
        LaravelAttachmentManifest manifest,
        Stream content,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(manifest);
        ArgumentNullException.ThrowIfNull(content);
        cancellationToken.ThrowIfCancellationRequested();
        ValidateManifest(manifest);
        await using Stream verified = await VerifyLocalAttachmentAsync(manifest, content, cancellationToken).ConfigureAwait(false);
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        string manifestJson = JsonSerializer.Serialize(manifest);
        byte[] response = await SendWithRetryAsync(
            client,
            () => CreateAuthenticatedRequest(HttpMethod.Post, "attachments", manifestJson),
            authenticated: true,
            cancellationToken).ConfigureAwait(false);
        LaravelAttachmentStatus status = Deserialize<LaravelAttachmentStatus>(response);
        EnsureAttachment(status, manifest.AttachmentUuid);
        foreach (int chunkIndex in status.MissingChunks.Distinct().Order())
        {
            if (chunkIndex < 0 || chunkIndex >= manifest.TotalChunks)
            {
                throw new LaravelSyncException("invalid_response", "The Laravel attachment response contains an invalid chunk index.");
            }

            byte[] chunk = await ReadChunkAsync(verified, manifest, chunkIndex, cancellationToken).ConfigureAwait(false);
            string chunkChecksum = Convert.ToHexString(SHA256.HashData(chunk));
            Guid chunkOperationId = DeriveOperationId(manifest, "chunk", chunkIndex);
            byte[] chunkResponse = await SendWithRetryAsync(
                client,
                () =>
                {
                    HttpRequestMessage request = CreateAuthenticatedRequest(
                        HttpMethod.Put,
                        $"attachments/{manifest.AttachmentUuid}/chunks/{chunkIndex}");
                    request.Content = new ByteArrayContent(chunk);
                    request.Content.Headers.ContentType = new MediaTypeHeaderValue("application/octet-stream");
                    request.Headers.Add("X-Operation-Id", chunkOperationId.ToString());
                    request.Headers.Add("X-Chunk-Sha256", chunkChecksum);
                    return request;
                },
                authenticated: true,
                cancellationToken).ConfigureAwait(false);
            EnsureAttachment(
                Deserialize<LaravelAttachmentStatus>(chunkResponse),
                manifest.AttachmentUuid);
        }

        Guid completionOperationId = DeriveOperationId(manifest, "complete");
        string completionJson = new JsonObject
        {
            ["operationId"] = completionOperationId
        }.ToJsonString();
        byte[] completionResponse = await SendWithRetryAsync(
            client,
            () => CreateAuthenticatedRequest(
                HttpMethod.Post,
                $"attachments/{manifest.AttachmentUuid}/complete",
                completionJson),
            authenticated: true,
            cancellationToken).ConfigureAwait(false);
        LaravelAttachmentStatus completed = Deserialize<LaravelAttachmentStatus>(completionResponse);
        EnsureAttachment(completed, manifest.AttachmentUuid);
        if (!completed.Completed)
        {
            throw new LaravelSyncException("attachment_incomplete", "The Laravel attachment upload did not complete.");
        }

        return completed;
    }

    public async Task DownloadAttachmentAsync(
        Guid attachmentUuid,
        string expectedSha256,
        Stream destination,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(destination);
        if (!destination.CanWrite)
        {
            throw new ArgumentException("The attachment destination must be writable.", nameof(destination));
        }

        byte[] expectedChecksum = ParseChecksum(expectedSha256);
        cancellationToken.ThrowIfCancellationRequested();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] content = await SendWithRetryAsync(
            client,
            () => CreateAuthenticatedRequest(HttpMethod.Get, $"attachments/{attachmentUuid}/content"),
            authenticated: true,
            cancellationToken).ConfigureAwait(false);
        byte[] actualChecksum = SHA256.HashData(content);
        if (!CryptographicOperations.FixedTimeEquals(expectedChecksum, actualChecksum))
        {
            throw new InvalidDataException("The downloaded attachment checksum does not match.");
        }

        await destination.WriteAsync(content, cancellationToken).ConfigureAwait(false);
    }

    public async Task SendAsync(OutboxDelivery delivery, CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(delivery);
        if (!await TrySendAsync(delivery, cancellationToken).ConfigureAwait(false))
        {
            throw new LaravelSyncException("operation_pending", "The Laravel operation was not applied and remains pending.");
        }
    }

    public async Task<bool> TrySendAsync(OutboxDelivery delivery, CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(delivery);
        if (!string.Equals(delivery.SourceId, SourceId, StringComparison.Ordinal))
        {
            throw new LaravelSyncException("source_mismatch", "The outbox operation belongs to a different synchronization source.");
        }

        ConflictResolutionDispatch? resolution = ConflictQueue.TryReadDispatch(delivery.Envelope);
        if (resolution is not null)
        {
            return await ResolveConflictAsync(delivery, resolution, cancellationToken).ConfigureAwait(false);
        }

        LaravelPushBatchResult result = await PushBatchAsync([delivery.Envelope], cancellationToken).ConfigureAwait(false);
        LaravelPushOperationResult? applied = result.Results.SingleOrDefault(item => item.OperationId == delivery.OperationId);
        return applied is not null && string.Equals(applied.Status, "applied", StringComparison.OrdinalIgnoreCase);
    }

    private async Task<bool> ResolveConflictAsync(
        OutboxDelivery delivery,
        ConflictResolutionDispatch resolution,
        CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(resolution.ServerConflictUuid))
        {
            return false;
        }

        string requestJson = JsonSerializer.Serialize(new
        {
            expectedConflictVersion = resolution.ExpectedConflictVersion,
            resolution = resolution.Resolution,
            selectedFields = resolution.SelectedFields
        });
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] response;
        try
        {
            response = await SendWithRetryAsync(
                client,
                () =>
                {
                    HttpRequestMessage request = CreateAuthenticatedRequest(
                        HttpMethod.Post,
                        $"conflicts/{Uri.EscapeDataString(resolution.ServerConflictUuid)}/resolve",
                        requestJson);
                    request.Headers.Add("X-Operation-Id", delivery.OperationId.ToString());
                    return request;
                },
                authenticated: true,
                cancellationToken).ConfigureAwait(false);
        }
        catch (LaravelSyncException exception) when (exception.StatusCode == HttpStatusCode.Conflict)
        {
            throw new ConflictResolutionRejectedException(delivery.OperationId, exception.ErrorCode);
        }

        LaravelConflictResolutionResponse result = Deserialize<LaravelConflictResolutionResponse>(response);
        string[] returnedFields = result.SelectedFields.Distinct(StringComparer.Ordinal).Order(StringComparer.Ordinal).ToArray();
        string[] requestedFields = resolution.SelectedFields.Distinct(StringComparer.Ordinal).Order(StringComparer.Ordinal).ToArray();
        if (!string.Equals(result.Status, "resolved", StringComparison.OrdinalIgnoreCase) ||
            result.ConflictVersion != resolution.ExpectedConflictVersion + 1 ||
            !string.Equals(result.Resolution, resolution.Resolution, StringComparison.Ordinal) ||
            !returnedFields.SequenceEqual(requestedFields))
        {
            throw new LaravelSyncException("invalid_response", "The Laravel conflict confirmation is invalid.");
        }

        return true;
    }

    public async Task<SynchronizeNowResult> SynchronizeNowAsync(CancellationToken cancellationToken)
    {
        try
        {
            await _identities.ResolvePendingReferencesAsync(cancellationToken).ConfigureAwait(false);
            bool pending = false;
            while (true)
            {
                List<string> envelopeJson;
                await using (LocalStoreDbContext context = _store.CreateContext())
                {
                    envelopeJson = await context.OutboxOperations
                        .AsNoTracking()
                        .Where(operation => operation.SourceId == SourceId && operation.Status == "Pending")
                        .OrderBy(operation => operation.CreatedAtUtc)
                        .Take(100)
                        .Select(operation => operation.EnvelopeJson)
                        .ToListAsync(cancellationToken)
                        .ConfigureAwait(false);
                }

                List<SyncEnvelope> envelopes = envelopeJson.Select(json =>
                    JsonSerializer.Deserialize<SyncEnvelope>(json)
                    ?? throw new JsonException("A pending outbox envelope is empty.")).ToList();
                if (envelopes.Count == 0)
                {
                    break;
                }

                List<SyncEnvelope> resolutions = envelopes.Where(envelope => ConflictQueue.TryReadDispatch(envelope) is not null).ToList();
                List<LaravelPushOperationResult> confirmed = [];
                foreach (SyncEnvelope envelope in resolutions)
                {
                    OutboxDelivery delivery = new(envelope.OperationId, SourceId, envelope);
                    try
                    {
                        if (await TrySendAsync(delivery, cancellationToken).ConfigureAwait(false))
                        {
                            confirmed.Add(new LaravelPushOperationResult { OperationId = envelope.OperationId, Status = "applied" });
                        }
                        else
                        {
                            pending = true;
                        }
                    }
                    catch (ConflictResolutionRejectedException)
                    {
                        await using LocalStoreDbContext context = _store.CreateContext();
                        await new ConflictQueue(_timeProvider).RejectAsync(
                            context, delivery.OperationId, _timeProvider.GetUtcNow(), cancellationToken).ConfigureAwait(false);
                        pending = true;
                    }
                }

                await AcknowledgeAppliedAsync(confirmed, cancellationToken).ConfigureAwait(false);
                List<SyncEnvelope> normal = envelopes.Where(envelope => ConflictQueue.TryReadDispatch(envelope) is null).ToList();
                if (normal.Count > 0)
                {
                    LaravelPushBatchResult pushed = await PushBatchAsync(normal, cancellationToken).ConfigureAwait(false);
                    await AcknowledgeAppliedAsync(pushed.Results, cancellationToken).ConfigureAwait(false);
                    confirmed.AddRange(pushed.Results);
                    pending |= pushed.PendingOperationIds.Count > 0;
                }

                if (pending && confirmed.All(result => !string.Equals(result.Status, "applied", StringComparison.OrdinalIgnoreCase)))
                {
                    break;
                }

                if (envelopes.Count < 100)
                {
                    break;
                }
            }

            await PullAsync(cancellationToken).ConfigureAwait(false);
            return new SynchronizeNowResult
            {
                SourceId = SourceId,
                Status = pending ? "Pending" : "Completed"
            };
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception exception) when (exception is IOException or JsonException or InvalidOperationException or DbUpdateException)
        {
            _logger.LogWarning(new EventId(29201), "Laravel synchronization remains pending; no local operation was acknowledged.");
            return new SynchronizeNowResult { SourceId = SourceId, Status = "Pending" };
        }
    }

    private async Task<LaravelPullResult> ReconcileAsync(
        HttpClient client,
        CursorState initialState,
        CancellationToken cancellationToken)
    {
        string? snapshotId = initialState.Phase == ReconciliationPhase.Reconciling
            ? initialState.SnapshotId
            : null;
        string? pageCursor = initialState.Phase == ReconciliationPhase.Reconciling
            ? initialState.Cursor
            : null;
        long lastChangeLogId = initialState.LastChangeLogId ?? 0;
        bool restartedAfterMismatch = false;
        int pageCount = 0;
        List<LaravelSnapshotRecord> records = [];
        while (true)
        {
            string path = "sync/reconciliation?limit=" + _options.PullPageSize.ToString(CultureInfo.InvariantCulture);
            if (!string.IsNullOrWhiteSpace(snapshotId))
            {
                path += "&snapshot_id=" + Uri.EscapeDataString(snapshotId);
            }

            if (!string.IsNullOrWhiteSpace(pageCursor))
            {
                path += "&page_cursor=" + Uri.EscapeDataString(pageCursor);
            }

            byte[] response = await SendWithRetryAsync(
                client,
                () => CreateAuthenticatedRequest(HttpMethod.Get, path),
                authenticated: true,
                cancellationToken).ConfigureAwait(false);
            LaravelReconciliationResponse page = Deserialize<LaravelReconciliationResponse>(response);
            EnsureSource(page.SourceId);
            if (page.RequiresFullReconciliation)
            {
                if (restartedAfterMismatch || snapshotId is null)
                {
                    throw new LaravelSyncException("reconciliation_required", "Laravel could not start a fresh snapshot reconciliation.");
                }

                snapshotId = null;
                pageCursor = null;
                restartedAfterMismatch = true;
                continue;
            }

            if (string.IsNullOrWhiteSpace(page.SnapshotId))
            {
                throw new LaravelSyncException("invalid_response", "The Laravel reconciliation response omitted its snapshot identifier.");
            }

            string? nextCursor = page.HasMore ? page.NextPageCursor : page.NextCursor;
            if (string.IsNullOrWhiteSpace(nextCursor) || (page.HasMore && string.IsNullOrWhiteSpace(page.NextPageCursor)))
            {
                throw new LaravelSyncException("invalid_response", "The Laravel reconciliation response omitted its next cursor.");
            }

            lastChangeLogId = page.ChangeRange?.ThroughChangeLogId ?? lastChangeLogId;
            RemoteChangePage remotePage = new()
            {
                SnapshotId = page.SnapshotId,
                NextCursor = nextCursor,
                LastChangeLogId = lastChangeLogId,
                CompletesReconciliation = !page.HasMore,
                Changes = page.Records.Select(ToRemoteChange).ToArray()
            };
            await _cursors.CommitPageAsync(SourceId, Channel, remotePage, cancellationToken).ConfigureAwait(false);
            records.AddRange(page.Records);
            pageCount++;
            if (!page.HasMore)
            {
                CursorState committed = await _cursors.GetAsync(SourceId, Channel, cancellationToken).ConfigureAwait(false);
                return new LaravelPullResult
                {
                    SnapshotRecords = records,
                    PageCount = pageCount,
                    Cursor = committed
                };
            }

            snapshotId = page.SnapshotId;
            pageCursor = page.NextPageCursor;
        }
    }

    private async Task<byte[]> SendWithRetryAsync(
        HttpClient client,
        Func<HttpRequestMessage> requestFactory,
        bool authenticated,
        CancellationToken cancellationToken)
    {
        for (int attempt = 1; attempt <= _options.MaxAttempts; attempt++)
        {
            cancellationToken.ThrowIfCancellationRequested();
            try
            {
                using HttpRequestMessage request = requestFactory();
                if (!authenticated && request.Headers.Authorization is not null)
                {
                    throw new InvalidOperationException("An unauthenticated health request cannot carry a credential.");
                }

                using HttpResponseMessage response = await client.SendAsync(request, cancellationToken).ConfigureAwait(false);
                byte[] body = await response.Content.ReadAsByteArrayAsync(cancellationToken).ConfigureAwait(false);
                if (response.IsSuccessStatusCode)
                {
                    return body;
                }

                LaravelSyncException failure = CreateHttpFailure(response.StatusCode, response.Headers, body);
                LogFailure(failure, attempt);
                if (!failure.IsTransient || attempt == _options.MaxAttempts)
                {
                    throw failure;
                }

                await DelayBeforeRetryAsync(failure.RetryAfter, attempt, cancellationToken).ConfigureAwait(false);
            }
            catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
            {
                throw;
            }
            catch (LaravelSyncException)
            {
                throw;
            }
            catch (Exception exception) when (exception is TimeoutException or TaskCanceledException or HttpRequestException or IOException or AuthenticationException)
            {
                LaravelSyncException failure = TranslateTransportFailure(exception);
                LogFailure(failure, attempt);
                if (!failure.IsTransient || attempt == _options.MaxAttempts)
                {
                    throw failure;
                }

                await DelayBeforeRetryAsync(failure.RetryAfter, attempt, cancellationToken).ConfigureAwait(false);
            }
        }

        throw new LaravelSyncException("retry_exhausted", "The Laravel request exhausted its retry policy.", isTransient: true);
    }

    private HttpRequestMessage CreateAuthenticatedRequest(HttpMethod method, string path, string? json = null)
    {
        HttpRequestMessage request = new(method, path);
        byte[]? token = _secrets.Read(SecretTargets.LaravelDeviceToken(SourceId));
        if (token is null || token.Length == 0)
        {
            throw new LaravelSyncException("credential_missing", "The Laravel device credential is unavailable.");
        }

        try
        {
            request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", Encoding.UTF8.GetString(token));
        }
        finally
        {
            CryptographicOperations.ZeroMemory(token);
        }

        request.Headers.Add("X-Device-Id", _options.DeviceId);
        request.Headers.Add("X-Source-Id", SourceId);
        request.Headers.Add("X-App-Version", _options.AppVersion);
        if (json is not null)
        {
            request.Content = new StringContent(json, Encoding.UTF8, "application/json");
        }

        return request;
    }

    private async Task DelayBeforeRetryAsync(
        TimeSpan? retryAfter,
        int attempt,
        CancellationToken cancellationToken)
    {
        TimeSpan delay = retryAfter ?? _retryPolicy.GetDelay(attempt);
        if (delay < TimeSpan.Zero)
        {
            delay = TimeSpan.Zero;
        }

        if (delay > TimeSpan.FromMinutes(5))
        {
            delay = TimeSpan.FromMinutes(5);
        }

        if (delay > TimeSpan.Zero)
        {
            await Task.Delay(delay, _timeProvider, cancellationToken).ConfigureAwait(false);
        }
    }

    private void LogFailure(LaravelSyncException exception, int attempt) =>
        _logger.LogWarning(
            new EventId(29101),
            "Laravel request failed with {ErrorCode} at attempt {Attempt}.",
            exception.ErrorCode,
            attempt);

    private static LaravelSyncException CreateHttpFailure(
        HttpStatusCode statusCode,
        HttpResponseHeaders headers,
        byte[] body)
    {
        (string code, string message, bool transient) = statusCode switch
        {
            HttpStatusCode.Unauthorized => ("unauthorized", "Laravel rejected the device credential.", false),
            HttpStatusCode.Forbidden => ("forbidden", "Laravel rejected the device permission.", false),
            (HttpStatusCode)429 => ("rate_limited", "Laravel rate-limited the synchronization request.", true),
            HttpStatusCode.RequestTimeout => ("timeout", "The Laravel request timed out.", true),
            HttpStatusCode.BadRequest or HttpStatusCode.UnprocessableEntity =>
                ("invalid_request", "Laravel rejected the synchronization request.", false),
            HttpStatusCode.Conflict => ("operation_rejected", "Laravel rejected the synchronization operation.", false),
            _ when (int)statusCode >= 500 => ("server_error", "Laravel is temporarily unavailable.", true),
            _ => ("http_error", "Laravel returned an unsuccessful response.", false)
        };
        return new LaravelSyncException(
            code,
            message,
            statusCode,
            transient,
            ReadRetryAfter(headers, body));
    }

    private static TimeSpan? ReadRetryAfter(HttpResponseHeaders headers, byte[] body)
    {
        if (headers.RetryAfter?.Delta is TimeSpan delta)
        {
            return delta;
        }

        if (headers.RetryAfter?.Date is DateTimeOffset date)
        {
            return date - DateTimeOffset.UtcNow;
        }

        try
        {
            using JsonDocument document = JsonDocument.Parse(body);
            if (document.RootElement.ValueKind == JsonValueKind.Object &&
                document.RootElement.TryGetProperty("retry_after", out JsonElement retryAfter) &&
                retryAfter.ValueKind == JsonValueKind.Number &&
                retryAfter.TryGetDouble(out double seconds) &&
                double.IsFinite(seconds))
            {
                return TimeSpan.FromSeconds(Math.Clamp(seconds, 0, 300));
            }
        }
        catch (JsonException)
        {
        }

        return null;
    }

    private static LaravelSyncException TranslateTransportFailure(Exception exception)
    {
        if (FindException<LaravelCertificatePinException>(exception) is not null)
        {
            return new LaravelSyncException("certificate_pin_mismatch", "The Laravel certificate pin does not match.", isTransient: false);
        }

        if (FindException<AuthenticationException>(exception) is not null)
        {
            return new LaravelSyncException("tls_failed", "The Laravel TLS certificate validation failed.", isTransient: false);
        }

        if (exception is TimeoutException or TaskCanceledException)
        {
            return new LaravelSyncException("timeout", "The Laravel request timed out.", isTransient: true, innerException: exception);
        }

        return new LaravelSyncException("connection_failed", "The Laravel connection failed temporarily.", isTransient: true, innerException: exception);
    }

    private async Task<Dictionary<Guid, JsonElement>> LoadCreatePayloadsAsync(
        IEnumerable<SyncEnvelope> envelopes,
        CancellationToken cancellationToken)
    {
        Guid[] creates = envelopes
            .Where(envelope => envelope.OperationType == SyncOperationType.Create)
            .Select(envelope => envelope.Entity.ClientUuid)
            .ToArray();
        Dictionary<Guid, JsonElement> payloads = new();
        if (creates.Length == 0)
        {
            return payloads;
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        List<CachedEntity> cached = await context.CachedEntities
            .AsNoTracking()
            .Where(entity => entity.SourceId == SourceId && creates.Contains(entity.ClientUuid))
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        foreach (CachedEntity entity in cached)
        {
            using JsonDocument document = JsonDocument.Parse(entity.PayloadJson);
            if (document.RootElement.ValueKind != JsonValueKind.Object)
            {
                throw new JsonException("A cached create payload must be a JSON object.");
            }

            payloads[entity.ClientUuid] = document.RootElement.Clone();
        }

        return payloads;
    }

    private JsonObject BuildPushOperation(
        SyncEnvelope envelope,
        IReadOnlyDictionary<Guid, JsonElement> createPayloads)
    {
        if (envelope.OperationType != SyncOperationType.Create && envelope.BaseVersion is null)
        {
            throw new InvalidOperationException("Update and delete operations require a base version.");
        }

        if (envelope.OperationType == SyncOperationType.Delete &&
            (envelope.Tombstone is null || string.IsNullOrWhiteSpace(envelope.Tombstone.DeletedByDeviceId)))
        {
            throw new InvalidOperationException("A delete operation requires a complete tombstone.");
        }

        JsonObject operation = new()
        {
            ["operationId"] = envelope.OperationId,
            ["idempotencyKey"] = $"desktop:{envelope.OperationId:N}",
            ["sourceId"] = SourceId,
            ["deviceId"] = _options.DeviceId,
            ["entityType"] = envelope.Entity.EntityType,
            ["clientUuid"] = envelope.Entity.ClientUuid,
            ["canonicalId"] = envelope.Entity.CanonicalId,
            ["operationType"] = envelope.OperationType.ToString(),
            ["rowVersion"] = envelope.RowVersion,
            ["occurredAtUtc"] = envelope.OccurredAtUtc,
            ["fieldPatches"] = new JsonArray(envelope.Patches.Select(patch => new JsonObject
            {
                ["fieldName"] = patch.FieldName,
                ["baseValue"] = ToJsonNode(patch.BaseValue),
                ["localValue"] = ToJsonNode(patch.LocalValue),
                ["remoteValue"] = ToJsonNode(patch.RemoteValue),
                ["criticality"] = patch.Criticality.ToString()
            }).ToArray()),
            ["references"] = new JsonArray(envelope.Entity.References
                .Where(reference => !reference.IsDeleted)
                .Select(reference => new JsonObject
                {
                    ["fieldName"] = reference.FieldName,
                    ["targetEntityType"] = reference.TargetEntityType,
                    ["targetClientUuid"] = reference.TargetClientUuid,
                    ["targetCanonicalId"] = reference.TargetCanonicalId,
                    ["kind"] = reference.Kind.ToString(),
                    ["isDeleted"] = false
                }).ToArray())
        };
        if (envelope.BaseVersion is ulong baseVersion)
        {
            operation["baseVersion"] = baseVersion;
        }

        if (envelope.OperationType == SyncOperationType.Create)
        {
            if (!createPayloads.TryGetValue(envelope.Entity.ClientUuid, out JsonElement payload))
            {
                throw new InvalidOperationException("A create operation requires its cached local payload.");
            }

            operation["payload"] = JsonNode.Parse(payload.GetRawText());
        }

        if (envelope.Tombstone is not null)
        {
            operation["tombstone"] = new JsonObject
            {
                ["deletedAtUtc"] = envelope.Tombstone.DeletedAtUtc,
                ["deletedByDeviceId"] = envelope.Tombstone.DeletedByDeviceId,
                ["reason"] = envelope.Tombstone.Reason
            };
        }

        return operation;
    }

    private async Task CaptureServerConflictsAsync(
        IReadOnlyList<LaravelPushOperationResult> results,
        CancellationToken cancellationToken)
    {
        Dictionary<Guid, ServerConflictMetadata> conflicts = [];
        foreach (LaravelPushOperationResult result in results.Where(item => item.Conflict.HasValue))
        {
            ServerConflictMetadata? metadata = result.Conflict!.Value.Deserialize<ServerConflictMetadata>();
            if (metadata is null || !Guid.TryParse(metadata.ConflictUuid, out _))
            {
                throw new LaravelSyncException("invalid_response", "Laravel returned an invalid conflict identifier.");
            }

            conflicts[result.OperationId] = metadata;
        }

        if (conflicts.Count == 0)
        {
            return;
        }

        Guid[] conflictIds = [.. conflicts.Keys];
        await using LocalStoreDbContext context = _store.CreateContext();
        List<OutboxOperation> operations = await context.OutboxOperations
            .Where(operation => operation.Status == "Pending" && conflictIds.Contains(operation.OperationId))
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);
        foreach (OutboxOperation operation in operations)
        {
            SyncEnvelope envelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
                ?? throw new JsonException("The pending outbox envelope is empty.");
            Dictionary<string, JsonElement> extensions = envelope.ExtensionData is null
                ? []
                : new Dictionary<string, JsonElement>(envelope.ExtensionData)
            {
                [ServerConflictMetadata.ExtensionName] = JsonSerializer.SerializeToElement(conflicts[operation.OperationId])
            };
            operation.EnvelopeJson = JsonSerializer.Serialize(envelope with { ExtensionData = extensions });
        }

        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
    }

    private async Task AcknowledgeAppliedAsync(
        IReadOnlyList<LaravelPushOperationResult> results,
        CancellationToken cancellationToken)
    {
        Guid[] appliedIds = results
            .Where(result => string.Equals(result.Status, "applied", StringComparison.OrdinalIgnoreCase))
            .Select(result => result.OperationId)
            .ToArray();
        if (appliedIds.Length == 0)
        {
            return;
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        await using IDbContextTransaction transaction = await context.Database
            .BeginTransactionAsync(cancellationToken)
            .ConfigureAwait(false);
        try
        {
            List<OutboxOperation> operations = await context.OutboxOperations
                .Where(operation => appliedIds.Contains(operation.OperationId) && operation.Status == "Pending")
                .ToListAsync(cancellationToken)
                .ConfigureAwait(false);
            foreach (OutboxOperation operation in operations)
            {
                operation.Status = "Sent";
                operation.AttemptCount++;
                operation.LastAttemptAtUtc = _timeProvider.GetUtcNow();
                await new ConflictQueue(_timeProvider).ConfirmAsync(
                    context, operation.OperationId, operation.LastAttemptAtUtc.Value, cancellationToken).ConfigureAwait(false);
            }

            await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
            await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken).ConfigureAwait(false);
            throw;
        }
    }

    private static bool IsReady(SyncEnvelope envelope)
    {
        if (envelope.Entity.References.Any(reference => !reference.IsDeleted && string.IsNullOrWhiteSpace(reference.TargetCanonicalId)))
        {
            return false;
        }

        return envelope.OperationType == SyncOperationType.Create || !string.IsNullOrWhiteSpace(envelope.Entity.CanonicalId);
    }

    private static RemoteChange ToRemoteChange(LaravelChange change)
    {
        Guid clientUuid = change.ClientUuid ?? change.Tombstone?.ClientUuid
            ?? throw new LaravelSyncException("invalid_response", "A Laravel change omitted its client UUID.");
        if (string.Equals(change.Operation, "delete", StringComparison.OrdinalIgnoreCase) && change.Tombstone is null)
        {
            throw new LaravelSyncException("invalid_response", "A Laravel delete change omitted its tombstone.");
        }

        return new RemoteChange
        {
            ChangeId = change.ChangeLogId,
            ClientUuid = clientUuid,
            EntityType = change.TableName,
            CanonicalId = change.PrimaryKey,
            Operation = change.Operation,
            PayloadJson = change.Data?.GetRawText() ?? "{}",
            SearchText = ExtractSearchText(change.Data) ?? change.PrimaryKey,
            RowVersion = change.Tombstone?.RowVersion ?? change.RowVersion,
            OccurredAtUtc = change.ChangedAt,
            DeletedAtUtc = change.Tombstone?.DeletedAt,
            DeletedByDeviceId = change.Tombstone?.DeletedByDeviceId
        };
    }

    private static RemoteChange ToRemoteChange(LaravelSnapshotRecord record)
    {
        Guid clientUuid = record.ClientUuid
            ?? throw new LaravelSyncException("invalid_response", "A Laravel snapshot record omitted its client UUID.");
        return new RemoteChange
        {
            ClientUuid = clientUuid,
            EntityType = record.TableName,
            CanonicalId = record.PrimaryKey,
            Operation = "snapshot",
            PayloadJson = record.Data.GetRawText(),
            SearchText = ExtractSearchText(record.Data) ?? record.PrimaryKey,
            RowVersion = ReadString(record.Data, "row_version"),
            OccurredAtUtc = ReadDate(record.Data, "updated_at") ?? DateTimeOffset.UnixEpoch
        };
    }

    private static string? ExtractSearchText(JsonElement? data)
    {
        if (data is not JsonElement element || element.ValueKind != JsonValueKind.Object)
        {
            return null;
        }

        foreach (string field in new[] { "displayName", "display_name", "full_name", "name", "original_file_name" })
        {
            string? value = ReadString(element, field);
            if (!string.IsNullOrWhiteSpace(value))
            {
                return value;
            }
        }

        return null;
    }

    private static string? ReadString(JsonElement element, string property) =>
        element.ValueKind == JsonValueKind.Object &&
        element.TryGetProperty(property, out JsonElement value) &&
        value.ValueKind == JsonValueKind.String
            ? value.GetString()
            : null;

    private static DateTimeOffset? ReadDate(JsonElement element, string property)
    {
        string? value = ReadString(element, property);
        return DateTimeOffset.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.RoundtripKind, out DateTimeOffset parsed)
            ? parsed
            : null;
    }

    private static T Deserialize<T>(byte[] json)
    {
        try
        {
            return JsonSerializer.Deserialize<T>(json)
                ?? throw new LaravelSyncException("invalid_response", "Laravel returned an empty JSON response.");
        }
        catch (JsonException exception)
        {
            throw new LaravelSyncException("invalid_response", "Laravel returned an invalid JSON response.", innerException: exception);
        }
    }

    private static JsonNode? ToJsonNode(JsonElement? value) => value.HasValue
        ? JsonNode.Parse(value.Value.GetRawText())
        : null;

    private static bool IsCompatible(string version) =>
        Version.TryParse(version, out Version? parsed) && parsed.Major == 1;

    private static void ValidateManifest(LaravelAttachmentManifest manifest)
    {
        _ = ParseChecksum(manifest.Sha256);
        if (manifest.FileSize < 1 || manifest.ChunkSize < 1 || manifest.TotalChunks < 1 ||
            manifest.TotalChunks != (long)Math.Ceiling(manifest.FileSize / (double)manifest.ChunkSize))
        {
            throw new InvalidDataException("The attachment manifest size or chunk layout is invalid.");
        }
    }

    private static async Task<Stream> VerifyLocalAttachmentAsync(
        LaravelAttachmentManifest manifest,
        Stream content,
        CancellationToken cancellationToken)
    {
        if (!content.CanRead)
        {
            throw new ArgumentException("The attachment content must be readable.", nameof(content));
        }

        Stream readable = content;
        MemoryStream? copy = null;
        if (!content.CanSeek)
        {
            copy = new MemoryStream();
            await content.CopyToAsync(copy, cancellationToken).ConfigureAwait(false);
            readable = copy;
        }

        readable.Position = 0;
        using IncrementalHash hash = IncrementalHash.CreateHash(HashAlgorithmName.SHA256);
        byte[] buffer = new byte[81920];
        long length = 0;
        while (true)
        {
            int read = await readable.ReadAsync(buffer, cancellationToken).ConfigureAwait(false);
            if (read == 0)
            {
                break;
            }

            hash.AppendData(buffer, 0, read);
            length += read;
        }

        if (length != manifest.FileSize ||
            !CryptographicOperations.FixedTimeEquals(hash.GetHashAndReset(), ParseChecksum(manifest.Sha256)))
        {
            copy?.Dispose();
            throw new InvalidDataException("The local attachment size or checksum does not match its manifest.");
        }

        readable.Position = 0;
        if (ReferenceEquals(readable, content))
        {
            return new NonDisposingStream(content);
        }

        return copy!;
    }

    private static async Task<byte[]> ReadChunkAsync(
        Stream content,
        LaravelAttachmentManifest manifest,
        int chunkIndex,
        CancellationToken cancellationToken)
    {
        content.Position = (long)chunkIndex * manifest.ChunkSize;
        int expectedLength = (int)Math.Min(manifest.ChunkSize, manifest.FileSize - content.Position);
        byte[] chunk = new byte[expectedLength];
        await content.ReadExactlyAsync(chunk, cancellationToken).ConfigureAwait(false);
        return chunk;
    }

    private static Guid DeriveOperationId(LaravelAttachmentManifest manifest, string purpose, int? chunkIndex = null)
    {
        string identity = $"alhayah.desktop.attachment.{purpose}:{manifest.OperationId:N}:{manifest.AttachmentUuid:N}:{chunkIndex}";
        byte[] hash = SHA256.HashData(Encoding.UTF8.GetBytes(identity));
        return new Guid(hash.AsSpan(0, 16));
    }

    private static byte[] ParseChecksum(string checksum)
    {
        if (checksum.Length != 64 || !checksum.All(Uri.IsHexDigit))
        {
            throw new InvalidDataException("An attachment SHA-256 checksum must contain exactly 64 hexadecimal characters.");
        }

        return Convert.FromHexString(checksum);
    }

    private static void EnsureAttachment(LaravelAttachmentStatus status, Guid attachmentUuid)
    {
        if (status.AttachmentUuid != attachmentUuid)
        {
            throw new LaravelSyncException("invalid_response", "The Laravel attachment response identity does not match.");
        }
    }

    private void EnsureSource(string sourceId)
    {
        if (!string.Equals(sourceId, SourceId, StringComparison.Ordinal))
        {
            throw new LaravelSyncException("source_mismatch", "The Laravel response source identity does not match.");
        }
    }

    private void EnsureEnvelopeSource(SyncEnvelope envelope)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        if (!string.Equals(envelope.SourceId.Value, SourceId, StringComparison.Ordinal) ||
            envelope.OperationId == Guid.Empty || envelope.Entity.ClientUuid == Guid.Empty)
        {
            throw new InvalidOperationException("An outbox envelope has an invalid source or operation identity.");
        }
    }

    private static string ClassifyFailure(LaravelSyncException exception)
    {
        if (exception.ErrorCode is "tls_failed" or "certificate_pin_mismatch")
        {
            return "TLS";
        }

        if (exception.StatusCode.HasValue || exception.ErrorCode is "unauthorized" or "forbidden" or "credential_missing")
        {
            return "Auth";
        }

        Exception? inner = exception.InnerException;
        SocketException? socket = inner is null ? null : FindException<SocketException>(inner);
        return socket?.SocketErrorCode is SocketError.HostNotFound or SocketError.NoData or SocketError.TryAgain
            ? "DNS"
            : "TCP";
    }

    private static TException? FindException<TException>(Exception exception) where TException : Exception
    {
        for (Exception? current = exception; current is not null; current = current.InnerException)
        {
            if (current is TException matched)
            {
                return matched;
            }
        }

        return null;
    }

    private static void MarkTransportStages(
        IDictionary<string, ConnectionCheckStageResult> stages,
        bool succeeded)
    {
        foreach (string name in new[] { "DNS", "TCP", "TLS" })
        {
            if (succeeded)
            {
                SetStageSuccess(stages, name);
            }
        }
    }

    private static void SetStageSuccess(
        IDictionary<string, ConnectionCheckStageResult> stages,
        string name) => stages[name] = new ConnectionCheckStageResult
    {
        Stage = name,
        Succeeded = true
    };

    private static void SetStageFailure(
        IDictionary<string, ConnectionCheckStageResult> stages,
        string name,
        string errorCode) => stages[name] = new ConnectionCheckStageResult
    {
        Stage = name,
        Succeeded = false,
        ErrorCode = errorCode
    };

    private sealed record LaravelPushResponseEnvelope
    {
        [System.Text.Json.Serialization.JsonPropertyName("results")]
        public IReadOnlyList<LaravelPushOperationResult> Results { get; init; } = [];
    }

    private sealed class NonDisposingStream(Stream inner) : Stream
    {
        public override bool CanRead => inner.CanRead;
        public override bool CanSeek => inner.CanSeek;
        public override bool CanWrite => false;
        public override long Length => inner.Length;
        public override long Position { get => inner.Position; set => inner.Position = value; }
        public override void Flush() { }
        public override int Read(byte[] buffer, int offset, int count) => inner.Read(buffer, offset, count);
        public override ValueTask<int> ReadAsync(Memory<byte> buffer, CancellationToken cancellationToken = default) =>
            inner.ReadAsync(buffer, cancellationToken);
        public override long Seek(long offset, SeekOrigin origin) => inner.Seek(offset, origin);
        public override void SetLength(long value) => throw new NotSupportedException();
        public override void Write(byte[] buffer, int offset, int count) => throw new NotSupportedException();
    }
}
