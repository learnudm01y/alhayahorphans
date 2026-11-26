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
using Alhayah.SyncService.Cursors;
using Alhayah.SyncService.Ipc;
using Alhayah.SyncService.Security;
using Alhayah.SyncService.Services;
using Alhayah.SyncService.Storage;
using Alhayah.SyncService.Synchronization;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.SyncService.Connectors;

public sealed class LocalServerSyncConnector : ISyncChannel
{
    public const string Channel = "local-operations";
    private const string RequestPathExtensionName = "desktopLocalRequestPath";
    private const string RequestBodyExtensionName = "desktopLocalRequestBody";
    private static readonly string[] StageNames = ["DNS", "TCP", "TLS", "Auth", "Compatibility"];
    private readonly ILocalHttpClientFactory _clientFactory;
    private readonly LocalConnectorOptions _options;
    private readonly ISyncServiceStore _store;
    private readonly CursorStore _cursors;
    private readonly ISecretStore _secrets;
    private readonly RetryPolicy _retryPolicy;
    private readonly TimeProvider _timeProvider;
    private readonly ILogger<LocalServerSyncConnector> _logger;
    private readonly string _sourceId;

    public LocalServerSyncConnector(
        ILocalHttpClientFactory clientFactory,
        LocalConnectorOptions options,
        ISyncServiceStore store,
        CursorStore cursors,
        ISecretStore secrets,
        RetryPolicy retryPolicy,
        TimeProvider timeProvider,
        ILogger<LocalServerSyncConnector> logger)
    {
        _clientFactory = clientFactory ?? throw new ArgumentNullException(nameof(clientFactory));
        _options = options ?? throw new ArgumentNullException(nameof(options));
        _store = store ?? throw new ArgumentNullException(nameof(store));
        _cursors = cursors ?? throw new ArgumentNullException(nameof(cursors));
        _secrets = secrets ?? throw new ArgumentNullException(nameof(secrets));
        _retryPolicy = retryPolicy ?? throw new ArgumentNullException(nameof(retryPolicy));
        _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
        string sourceId = new SyncSourceId(options.SourceId).Value;
        _sourceId = sourceId;
        if (!sourceId.StartsWith("local:", StringComparison.Ordinal))
        {
            throw new ArgumentException("The Local Server connector requires a local source identity.", nameof(options));
        }

        ArgumentException.ThrowIfNullOrWhiteSpace(options.DeviceId);
        if (options.MaxAttempts is < 1 or > 10 || options.PullPageSize is < 1 or > 500)
        {
            throw new ArgumentOutOfRangeException(nameof(options));
        }
    }

    public string SourceId => _sourceId;

    public async Task<LocalServerHealthResponse> GetHealthAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] response = await SendWithRetryAsync(
            client,
            () => new HttpRequestMessage(HttpMethod.Get, "health"),
            authenticated: false,
            cancellationToken).ConfigureAwait(false);
        return Deserialize<LocalServerHealthResponse>(response);
    }

    public async Task<LocalServerCapabilitiesResponse> GetCapabilitiesAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] response = await SendWithRetryAsync(
            client,
            () => new HttpRequestMessage(HttpMethod.Get, "capabilities"),
            authenticated: false,
            cancellationToken).ConfigureAwait(false);
        return Deserialize<LocalServerCapabilitiesResponse>(response);
    }

    public async Task<ConnectionCheckResult> CheckConnectionAsync(CancellationToken cancellationToken)
    {
        Dictionary<string, ConnectionCheckStageResult> stages = StageNames.ToDictionary(
            name => name,
            name => new ConnectionCheckStageResult
            {
                Stage = name,
                ErrorCode = "not_checked"
            },
            StringComparer.Ordinal);
        try
        {
            using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
            LocalServerHealthResponse health;
            try
            {
                byte[] response = await SendWithRetryAsync(
                    client,
                    () => new HttpRequestMessage(HttpMethod.Get, "health"),
                    authenticated: false,
                    cancellationToken).ConfigureAwait(false);
                health = Deserialize<LocalServerHealthResponse>(response);
                MarkTransportStages(stages, succeeded: true);
            }
            catch (LocalSyncException exception)
            {
                string failedStage = ClassifyFailure(exception);
                if (exception.StatusCode.HasValue)
                {
                    MarkTransportStages(stages, succeeded: true);
                    failedStage = "Compatibility";
                }

                SetStageFailure(stages, failedStage, exception.ErrorCode);
                return Result(stages);
            }
            catch (JsonException)
            {
                SetStageFailure(stages, "Compatibility", "invalid_response");
                return Result(stages);
            }

            LocalServerCapabilitiesResponse capabilities;
            try
            {
                byte[] response = await SendWithRetryAsync(
                    client,
                    () => new HttpRequestMessage(HttpMethod.Get, "capabilities"),
                    authenticated: false,
                    cancellationToken).ConfigureAwait(false);
                capabilities = Deserialize<LocalServerCapabilitiesResponse>(response);
            }
            catch (LocalSyncException exception)
            {
                SetStageFailure(stages, exception.StatusCode.HasValue ? "Compatibility" : ClassifyFailure(exception), exception.ErrorCode);
                return Result(stages);
            }
            catch (JsonException)
            {
                SetStageFailure(stages, "Compatibility", "invalid_response");
                return Result(stages);
            }

            try
            {
                byte[] response = await SendWithRetryAsync(
                    client,
                    () => CreateAuthenticatedRequest(HttpMethod.Get, "local/changes?limit=1"),
                    authenticated: true,
                    cancellationToken).ConfigureAwait(false);
                LocalChangeFeedResponse probe = Deserialize<LocalChangeFeedResponse>(response);
                EnsureSource(probe.SourceId);
                stages["Auth"] = new ConnectionCheckStageResult { Stage = "Auth", Succeeded = true };
            }
            catch (LocalSyncException exception)
            {
                string failedStage = exception.ErrorCode == "source_mismatch"
                    ? "Compatibility"
                    : exception.StatusCode.HasValue ? "Auth" : ClassifyFailure(exception);
                SetStageFailure(stages, failedStage, exception.ErrorCode);
                return Result(stages);
            }
            catch (JsonException)
            {
                SetStageFailure(stages, "Compatibility", "invalid_response");
                return Result(stages);
            }

            string compatibilityError = ValidateCompatibility(health, capabilities, SourceId);
            stages["Compatibility"] = new ConnectionCheckStageResult
            {
                Stage = "Compatibility",
                Succeeded = compatibilityError is null,
                ErrorCode = compatibilityError
            };
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (LocalSyncException exception)
        {
            SetStageFailure(stages, ClassifyFailure(exception), exception.ErrorCode);
        }
        catch (Exception exception) when (exception is HttpRequestException or IOException or AuthenticationException or JsonException)
        {
            string failedStage = ClassifyTransportException(exception);
            SetStageFailure(stages, failedStage, failedStage == "TLS" ? "tls_failed" : "connection_failed");
        }

        return Result(stages);
    }

    public async Task<LocalPullResult> PullAsync(CancellationToken cancellationToken)
    {
        cancellationToken.ThrowIfCancellationRequested();
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        CursorState state = await _cursors.GetAsync(SourceId, Channel, cancellationToken).ConfigureAwait(false);
        bool replaying = state.ReconciliationRequired || state.Phase != ReconciliationPhase.Reconciled;
        string? pageCursor = replaying && state.Phase != ReconciliationPhase.Reconciling ? null : state.Cursor;
        long lastChangeLogId = pageCursor is null ? 0 : state.LastChangeLogId ?? 0;
        bool restartedAfterRejectedCursor = false;
        List<LocalChangeFeedItem> changes = [];
        int pageCount = 0;
        while (true)
        {
            string path = "local/changes?limit=" + _options.PullPageSize.ToString(CultureInfo.InvariantCulture);
            if (!string.IsNullOrWhiteSpace(pageCursor))
            {
                path += "&cursor=" + Uri.EscapeDataString(pageCursor);
            }

            byte[] response;
            try
            {
                response = await SendWithRetryAsync(
                    client,
                    () => CreateAuthenticatedRequest(HttpMethod.Get, path),
                    authenticated: true,
                    cancellationToken).ConfigureAwait(false);
            }
            catch (LocalSyncException exception) when (
                exception.StatusCode == HttpStatusCode.BadRequest &&
                !string.IsNullOrWhiteSpace(pageCursor) &&
                !restartedAfterRejectedCursor)
            {
                await BeginFullReplayAsync(cancellationToken).ConfigureAwait(false);
                state = await _cursors.GetAsync(SourceId, Channel, cancellationToken).ConfigureAwait(false);
                pageCursor = null;
                lastChangeLogId = 0;
                changes.Clear();
                pageCount = 0;
                restartedAfterRejectedCursor = true;
                continue;
            }

            LocalChangeFeedResponse page = Deserialize<LocalChangeFeedResponse>(response);
            EnsureSource(page.SourceId);
            ValidateFeedPage(page);
            long pageLastChangeLogId = lastChangeLogId;
            long previousChangeLogId = lastChangeLogId;
            foreach (LocalChangeFeedItem change in page.Changes)
            {
                if (change.ChangeLogId <= previousChangeLogId)
                {
                    throw new LocalSyncException("invalid_response", "The Local Server change feed is not strictly advancing.");
                }

                previousChangeLogId = change.ChangeLogId;
            }

            if (page.Changes.Count > 0)
            {
                pageLastChangeLogId = page.Changes[^1].ChangeLogId;
            }

            if (page.HasMore && string.Equals(page.NextCursor, pageCursor, StringComparison.Ordinal) &&
                pageLastChangeLogId == lastChangeLogId)
            {
                throw new LocalSyncException("invalid_response", "The Local Server change feed made no progress.");
            }

            RemoteChangePage remotePage = new()
            {
                NextCursor = page.NextCursor,
                LastChangeLogId = pageLastChangeLogId,
                CompletesReconciliation = !page.HasMore,
                Changes = page.Changes.Select(ToRemoteChange).ToArray()
            };
            CursorCommitResult committed = await _cursors.CommitPageAsync(
                SourceId,
                Channel,
                remotePage,
                cancellationToken).ConfigureAwait(false);
            if (committed == CursorCommitResult.Applied)
            {
                changes.AddRange(page.Changes);
                pageCount++;
            }

            state = await _cursors.GetAsync(SourceId, Channel, cancellationToken).ConfigureAwait(false);
            if (!page.HasMore)
            {
                return new LocalPullResult
                {
                    Changes = changes,
                    PageCount = pageCount,
                    Cursor = state
                };
            }

            pageCursor = page.NextCursor;
            lastChangeLogId = pageLastChangeLogId;
        }
    }

    public async Task<LocalOperationResult> PushAsync(
        SyncEnvelope envelope,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(envelope);
        cancellationToken.ThrowIfCancellationRequested();
        EnsureEnvelope(envelope);
        (string path, string requestJson) = await BuildPushRequestAsync(envelope, cancellationToken).ConfigureAwait(false);
        using HttpClient client = await _clientFactory.CreateAsync(cancellationToken).ConfigureAwait(false);
        byte[] response = await SendWithRetryAsync(
            client,
            () => CreateAuthenticatedRequest(HttpMethod.Post, path, requestJson),
            authenticated: true,
            acceptConflictResult: true,
            cancellationToken).ConfigureAwait(false);
        LocalOperationResult result = Deserialize<LocalOperationResult>(response);
        if (result.OperationId != envelope.OperationId)
        {
            throw new LocalSyncException("invalid_response", "The Local Server returned a different operation identifier.");
        }

        if (result.Status == LocalOperationStatus.Completed &&
            (result.EntityId != envelope.Entity.ClientUuid || result.RowVersion is null or <= 0))
        {
            throw new LocalSyncException("invalid_response", "The Local Server returned incomplete commit metadata.");
        }

        return result;
    }

    public async Task<SynchronizeNowResult> SynchronizeNowAsync(CancellationToken cancellationToken)
    {
        bool pending = false;
        try
        {
            pending = await PushPendingAsync(cancellationToken).ConfigureAwait(false);
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception exception) when (exception is IOException or JsonException or InvalidOperationException or DbUpdateException)
        {
            pending = true;
            _logger.LogWarning(new EventId(29301), "Local Server push remains pending; no local operation was acknowledged.");
        }

        try
        {
            await PullAsync(cancellationToken).ConfigureAwait(false);
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception exception) when (exception is IOException or JsonException or InvalidOperationException or DbUpdateException)
        {
            pending = true;
            _logger.LogWarning(new EventId(29302), "Local Server pull remains pending; the durable cursor was not advanced.");
        }

        return new SynchronizeNowResult
        {
            SourceId = SourceId,
            Status = pending ? "Pending" : "Completed"
        };
    }

    private async Task<bool> PushPendingAsync(CancellationToken cancellationToken)
    {
        bool pending = false;
        while (true)
        {
            List<(Guid OperationId, string EnvelopeJson)> operations;
            await using (LocalStoreDbContext context = _store.CreateContext())
            {
                operations = await context.OutboxOperations
                    .AsNoTracking()
                    .Where(operation => operation.SourceId == SourceId && operation.Status == "Pending")
                    .OrderBy(operation => operation.CreatedAtUtc)
                    .Take(100)
                    .Select(operation => new { operation.OperationId, operation.EnvelopeJson })
                    .ToListAsync(cancellationToken)
                    .ConfigureAwait(false)
                    .Select(operation => (operation.OperationId, operation.EnvelopeJson))
                    .ToList();
            }

            if (operations.Count == 0)
            {
                return pending;
            }

            foreach ((Guid operationId, string envelopeJson) in operations)
            {
                SyncEnvelope envelope = JsonSerializer.Deserialize<SyncEnvelope>(envelopeJson)
                    ?? throw new JsonException("A pending Local Server outbox envelope is empty.");
                try
                {
                    LocalOperationResult result = await PushAsync(envelope, cancellationToken).ConfigureAwait(false);
                    if (result.Status == LocalOperationStatus.Completed)
                    {
                        await AcknowledgeCompletedAsync(operationId, cancellationToken).ConfigureAwait(false);
                    }
                    else
                    {
                        pending = true;
                    }
                }
                catch (LocalSyncException exception) when (exception.ErrorCode == "unsupported_operation")
                {
                    pending = true;
                }
            }

            if (pending || operations.Count < 100)
            {
                return pending;
            }
        }
    }

    private async Task AcknowledgeCompletedAsync(Guid operationId, CancellationToken cancellationToken)
    {
        await using LocalStoreDbContext context = _store.CreateContext();
        OutboxOperation? operation = await context.OutboxOperations.SingleOrDefaultAsync(
            item => item.OperationId == operationId && item.SourceId == SourceId && item.Status == "Pending",
            cancellationToken).ConfigureAwait(false);
        if (operation is null)
        {
            return;
        }

        operation.Status = "Sent";
        operation.AttemptCount++;
        operation.LastAttemptAtUtc = _timeProvider.GetUtcNow();
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
    }

    private async Task BeginFullReplayAsync(CancellationToken cancellationToken)
    {
        await using LocalStoreDbContext context = _store.CreateContext();
        SyncCheckpoint? checkpoint = await context.SyncCheckpoints.SingleOrDefaultAsync(
            item => item.SourceId == SourceId && item.Channel == Channel,
            cancellationToken).ConfigureAwait(false);
        if (checkpoint is null)
        {
            return;
        }

        checkpoint.Cursor = null;
        checkpoint.LastCommittedChangeId = null;
        checkpoint.ReconciliationRequired = true;
        checkpoint.UpdatedAtUtc = _timeProvider.GetUtcNow();
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
    }

    private async Task<(string Path, string Json)> BuildPushRequestAsync(
        SyncEnvelope envelope,
        CancellationToken cancellationToken)
    {
        if (envelope.OperationType != SyncOperationType.Create)
        {
            throw new LocalSyncException(
                "unsupported_operation",
                "The Local Server contract does not expose this update or delete operation.");
        }

        await using LocalStoreDbContext context = _store.CreateContext();
        OutboxOperation? operation = await context.OutboxOperations.SingleOrDefaultAsync(
            item => item.OperationId == envelope.OperationId && item.SourceId == SourceId,
            cancellationToken).ConfigureAwait(false);
        if (operation is null)
        {
            throw new LocalSyncException(
                "outbox_missing",
                "The durable Local Server outbox operation is unavailable.");
        }

        (string path, string json)? snapshot = ReadRequestSnapshot(operation.EnvelopeJson);
        if (snapshot is { } savedRequest)
        {
            return savedRequest;
        }

        envelope = JsonSerializer.Deserialize<SyncEnvelope>(operation.EnvelopeJson)
            ?? throw new JsonException("A Local Server outbox envelope is empty.");
        envelope = WithoutRequestSnapshot(NormalizeLocalIdentity(envelope));
        CachedEntity? cached = await context.CachedEntities
            .AsNoTracking()
            .SingleOrDefaultAsync(
                entity => entity.SourceId == SourceId &&
                    entity.EntityType == envelope.Entity.EntityType &&
                    entity.ClientUuid == envelope.Entity.ClientUuid,
                cancellationToken).ConfigureAwait(false);
        if (cached is null)
        {
            throw new LocalSyncException("payload_missing", "The cached payload for the Local Server operation is unavailable.");
        }

        using JsonDocument document = JsonDocument.Parse(cached.PayloadJson);
        if (document.RootElement.ValueKind != JsonValueKind.Object)
        {
            throw new JsonException("A Local Server operation payload must be a JSON object.");
        }

        JsonElement payload = document.RootElement;
        JsonObject request = new()
        {
            ["operationId"] = envelope.OperationId,
            ["idempotencyKey"] = $"desktop:{envelope.OperationId:N}",
            ["envelope"] = JsonSerializer.SerializeToNode(envelope)
        };
        string path = envelope.Entity.EntityType switch
        {
            "AidOperation" => BuildAidRequest(request, payload),
            "Expense" => BuildExpenseRequest(request, payload),
            "Voucher" => BuildVoucherIssueRequest(request, payload),
            "VoucherRedemption" => BuildVoucherRedemptionRequest(request, payload, envelope),
            _ => throw new LocalSyncException(
                "unsupported_operation",
                "The Local Server contract does not expose this entity operation.")
        };
        string json = request.ToJsonString();
        JsonObject envelopeNode = JsonNode.Parse(operation.EnvelopeJson) as JsonObject
            ?? throw new JsonException("A Local Server outbox envelope must be a JSON object.");
        envelopeNode[RequestPathExtensionName] = path;
        envelopeNode[RequestBodyExtensionName] = json;
        operation.EnvelopeJson = envelopeNode.ToJsonString();
        await context.SaveChangesAsync(cancellationToken).ConfigureAwait(false);
        return (path, json);
    }

    private static (string Path, string Json)? ReadRequestSnapshot(string envelopeJson)
    {
        using JsonDocument document = JsonDocument.Parse(envelopeJson);
        JsonElement root = document.RootElement;
        if (root.ValueKind == JsonValueKind.Object &&
            root.TryGetProperty(RequestPathExtensionName, out JsonElement path) &&
            root.TryGetProperty(RequestBodyExtensionName, out JsonElement body) &&
            path.ValueKind == JsonValueKind.String &&
            body.ValueKind == JsonValueKind.String &&
            !string.IsNullOrWhiteSpace(path.GetString()) &&
            !string.IsNullOrWhiteSpace(body.GetString()))
        {
            return (path.GetString()!, body.GetString()!);
        }

        return null;
    }

    private static SyncEnvelope WithoutRequestSnapshot(SyncEnvelope envelope)
    {
        if (envelope.ExtensionData is null ||
            (!envelope.ExtensionData.ContainsKey(RequestPathExtensionName) &&
                !envelope.ExtensionData.ContainsKey(RequestBodyExtensionName)))
        {
            return envelope;
        }

        Dictionary<string, JsonElement> extensionData = new(envelope.ExtensionData);
        extensionData.Remove(RequestPathExtensionName);
        extensionData.Remove(RequestBodyExtensionName);
        return envelope with { ExtensionData = extensionData.Count == 0 ? null : extensionData };
    }

    private static string BuildAidRequest(JsonObject request, JsonElement payload)
    {
        request["aidType"] = RequiredString(payload, "aidType");
        request["entitlementId"] = OptionalGuid(payload, "entitlementId");
        request["amount"] = RequiredDecimal(payload, "amount");
        request["currencyCode"] = RequiredString(payload, "currencyCode");
        request["occurredAtUtc"] = RequiredDate(payload, "occurredAtUtc");
        request["notes"] = OptionalString(payload, "notes");
        return "local/operations/aid";
    }

    private static string BuildExpenseRequest(JsonObject request, JsonElement payload)
    {
        request["category"] = RequiredString(payload, "category");
        request["supplierId"] = OptionalGuid(payload, "supplierId");
        request["amount"] = RequiredDecimal(payload, "amount");
        request["currencyCode"] = RequiredString(payload, "currencyCode");
        request["incurredAtUtc"] = RequiredDate(payload, "incurredAtUtc");
        request["description"] = OptionalString(payload, "description");
        return "local/operations/expenses";
    }

    private static string BuildVoucherIssueRequest(JsonObject request, JsonElement payload)
    {
        request["sequenceId"] = RequiredGuid(payload, "sequenceId");
        request["supplierId"] = RequiredGuid(payload, "supplierId");
        request["amount"] = RequiredDecimal(payload, "amount");
        request["currencyCode"] = RequiredString(payload, "currencyCode");
        request["beneficiaryNameSnapshot"] = RequiredString(payload, "beneficiaryNameSnapshot");
        request["beneficiaryIdentityNumberSnapshot"] = RequiredString(payload, "beneficiaryIdentityNumberSnapshot");
        request["beneficiaryPhoneSnapshot"] = OptionalString(payload, "beneficiaryPhoneSnapshot");
        request["disbursementId"] = OptionalGuid(payload, "disbursementId");
        request["issuedAtUtc"] = RequiredDate(payload, "issuedAtUtc");
        request["expiresAtUtc"] = OptionalDate(payload, "expiresAtUtc");
        return "local/vouchers/issue";
    }

    private static string BuildVoucherRedemptionRequest(
        JsonObject request,
        JsonElement payload,
        SyncEnvelope envelope)
    {
        if (envelope.BaseVersion is not ulong baseVersion || baseVersion > long.MaxValue)
        {
            throw new LocalSyncException("version_missing", "A voucher redemption requires its expected row version.");
        }

        if (!Guid.TryParse(envelope.RowVersion, out Guid concurrencyToken) || concurrencyToken == Guid.Empty)
        {
            throw new LocalSyncException("version_missing", "A voucher redemption requires its expected concurrency token.");
        }

        request["voucherId"] = RequiredGuid(payload, "voucherId");
        request["expectedRowVersion"] = (long)baseVersion;
        request["expectedConcurrencyToken"] = concurrencyToken;
        request["amount"] = RequiredDecimal(payload, "amount");
        request["currencyCode"] = RequiredString(payload, "currencyCode");
        request["redeemedAtUtc"] = RequiredDate(payload, "redeemedAtUtc");
        request["redemptionReference"] = OptionalString(payload, "redemptionReference");
        return "local/vouchers/redeem";
    }

    private async Task<byte[]> SendWithRetryAsync(
        HttpClient client,
        Func<HttpRequestMessage> requestFactory,
        bool authenticated,
        CancellationToken cancellationToken,
        bool acceptConflictResult = false)
    {
        for (int attempt = 1; attempt <= _options.MaxAttempts; attempt++)
        {
            cancellationToken.ThrowIfCancellationRequested();
            try
            {
                using HttpRequestMessage request = requestFactory();
                if (!authenticated && request.Headers.Authorization is not null)
                {
                    throw new InvalidOperationException("A public Local Server request cannot carry a device credential.");
                }

                using HttpResponseMessage response = await client.SendAsync(request, cancellationToken).ConfigureAwait(false);
                byte[] body = await response.Content.ReadAsByteArrayAsync(cancellationToken).ConfigureAwait(false);
                if (response.IsSuccessStatusCode || (acceptConflictResult && response.StatusCode == HttpStatusCode.Conflict))
                {
                    return body;
                }

                LocalSyncException failure = CreateHttpFailure(response.StatusCode, response.Headers, body);
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
            catch (LocalSyncException)
            {
                throw;
            }
            catch (Exception exception) when (
                exception is TimeoutException or TaskCanceledException or HttpRequestException or IOException or AuthenticationException)
            {
                LocalSyncException failure = TranslateTransportFailure(exception);
                LogFailure(failure, attempt);
                if (!failure.IsTransient || attempt == _options.MaxAttempts)
                {
                    throw failure;
                }

                await DelayBeforeRetryAsync(failure.RetryAfter, attempt, cancellationToken).ConfigureAwait(false);
            }
        }

        throw new LocalSyncException("retry_exhausted", "The Local Server request exhausted its retry policy.", isTransient: true);
    }

    private HttpRequestMessage CreateAuthenticatedRequest(
        HttpMethod method,
        string path,
        string? json = null)
    {
        if (string.IsNullOrWhiteSpace(_options.UserId))
        {
            throw new LocalSyncException("configuration_missing", "The Local Server user identifier is not configured.");
        }

        HttpRequestMessage request = new(method, path);
        byte[]? credential = _secrets.Read(SecretTargets.LocalServerDeviceCredential(SourceId));
        if (credential is null || credential.Length == 0)
        {
            throw new LocalSyncException("credential_missing", "The Local Server device credential is unavailable.");
        }

        try
        {
            string value = new UTF8Encoding(encoderShouldEmitUTF8Identifier: false, throwOnInvalidBytes: true).GetString(credential);
            if (string.IsNullOrWhiteSpace(value) || value.Any(char.IsControl))
            {
                throw new LocalSyncException("credential_invalid", "The Local Server device credential is invalid.");
            }

            request.Headers.Authorization = new AuthenticationHeaderValue("Device", value);
        }
        catch (DecoderFallbackException exception)
        {
            throw new LocalSyncException("credential_invalid", "The Local Server device credential is invalid.", innerException: exception);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(credential);
        }

        request.Headers.Add("X-Alhayah-Device-Id", _options.DeviceId);
        request.Headers.Add("X-Alhayah-User-Id", _options.UserId);
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
        delay = TimeSpan.FromMilliseconds(Math.Clamp(delay.TotalMilliseconds, 0, TimeSpan.FromMinutes(5).TotalMilliseconds));
        if (delay > TimeSpan.Zero)
        {
            await Task.Delay(delay, _timeProvider, cancellationToken).ConfigureAwait(false);
        }
    }

    private void LogFailure(LocalSyncException exception, int attempt) =>
        _logger.LogWarning(
            new EventId(29303),
            "Local Server request failed with {ErrorCode} at attempt {Attempt}.",
            exception.ErrorCode,
            attempt);

    private static LocalSyncException CreateHttpFailure(
        HttpStatusCode statusCode,
        HttpResponseHeaders headers,
        byte[] body)
    {
        (string code, string message, bool transient) = statusCode switch
        {
            HttpStatusCode.Unauthorized => ("unauthorized", "Local Server rejected the device credential.", false),
            HttpStatusCode.Forbidden => ("forbidden", "Local Server rejected the device permission.", false),
            (HttpStatusCode)429 => ("rate_limited", "Local Server rate-limited the synchronization request.", true),
            HttpStatusCode.RequestTimeout => ("timeout", "The Local Server request timed out.", true),
            HttpStatusCode.BadRequest or HttpStatusCode.UnprocessableEntity =>
                ("invalid_request", "Local Server rejected the synchronization request.", false),
            HttpStatusCode.Conflict => ("operation_rejected", "Local Server rejected the synchronization operation.", false),
            _ when (int)statusCode >= 500 => ("server_error", "Local Server is temporarily unavailable.", true),
            _ => ("http_error", "Local Server returned an unsuccessful response.", false)
        };
        return new LocalSyncException(code, message, transient, statusCode, ReadRetryAfter(headers, body));
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

    private static LocalSyncException TranslateTransportFailure(Exception exception)
    {
        if (FindException<LocalCertificatePinException>(exception) is not null)
        {
            return new LocalSyncException("certificate_pin_mismatch", "The Local Server certificate pin does not match.");
        }

        if (FindException<AuthenticationException>(exception) is not null)
        {
            return new LocalSyncException("tls_failed", "The Local Server TLS certificate validation failed.");
        }

        if (exception is TimeoutException or TaskCanceledException)
        {
            return new LocalSyncException("timeout", "The Local Server request timed out.", isTransient: true, innerException: exception);
        }

        return new LocalSyncException("connection_failed", "The Local Server connection failed temporarily.", isTransient: true, innerException: exception);
    }

    private static string ClassifyFailure(LocalSyncException exception) => exception.ErrorCode switch
    {
        "certificate_pin_mismatch" or "tls_failed" or "client_certificate_missing" or "client_certificate_invalid" => "TLS",
        "configuration_missing" or "credential_missing" or "credential_invalid" or "unauthorized" or "forbidden" => "Auth",
        "timeout" => "TCP",
        _ => ClassifyTransportException(exception)
    };

    private static string ClassifyTransportException(Exception exception)
    {
        if (FindException<AuthenticationException>(exception) is not null)
        {
            return "TLS";
        }

        SocketException? socket = FindException<SocketException>(exception);
        if (socket is not null)
        {
            return socket.SocketErrorCode is SocketError.HostNotFound or SocketError.NoData or SocketError.TryAgain
                ? "DNS"
                : "TCP";
        }

        return "TCP";
    }

    private static T? FindException<T>(Exception exception) where T : Exception
    {
        for (Exception? current = exception; current is not null; current = current.InnerException)
        {
            if (current is T match)
            {
                return match;
            }
        }

        return null;
    }

    private static T Deserialize<T>(byte[] response)
    {
        T? value = JsonSerializer.Deserialize<T>(response, SyncServiceJson.Options);
        return value ?? throw new JsonException("The Local Server response is empty.");
    }

    private void EnsureEnvelope(SyncEnvelope envelope)
    {
        if (!string.Equals(envelope.SourceId.Value, SourceId, StringComparison.Ordinal))
        {
            throw new LocalSyncException("source_mismatch", "The operation belongs to a different synchronization source.");
        }

        if (!string.Equals(envelope.DeviceId, _options.DeviceId, StringComparison.Ordinal))
        {
            throw new LocalSyncException("device_mismatch", "The operation belongs to a different device.");
        }

        if (envelope.OperationId == Guid.Empty || envelope.Entity.ClientUuid == Guid.Empty ||
            string.IsNullOrWhiteSpace(envelope.Entity.EntityType))
        {
            throw new LocalSyncException("invalid_request", "The Local Server operation identity is invalid.");
        }
    }

    private void EnsureSource(string sourceId)
    {
        if (!string.Equals(sourceId, SourceId, StringComparison.Ordinal))
        {
            throw new LocalSyncException("source_mismatch", "The Local Server response belongs to a different source.");
        }
    }

    private static void ValidateFeedPage(LocalChangeFeedResponse page)
    {
        ArgumentNullException.ThrowIfNull(page.Changes);
        if (string.IsNullOrWhiteSpace(page.NextCursor))
        {
            throw new LocalSyncException("invalid_response", "The Local Server change feed omitted its next cursor.");
        }

        foreach (LocalChangeFeedItem change in page.Changes)
        {
            if (change.ChangeLogId <= 0 || change.EntityId == Guid.Empty || change.RowVersion <= 0 ||
                change.ConcurrencyToken == Guid.Empty || string.IsNullOrWhiteSpace(change.EntityType) ||
                string.IsNullOrWhiteSpace(change.DeviceId))
            {
                throw new LocalSyncException("invalid_response", "The Local Server change feed contains an invalid item.");
            }

            if (change.OperationType == SyncOperationType.Delete)
            {
                if (change.Tombstone is null || string.IsNullOrWhiteSpace(change.Tombstone.DeletedByDeviceId) ||
                    change.Entity is not null)
                {
                    throw new LocalSyncException("invalid_response", "The Local Server delete item is incomplete.");
                }
            }
            else if (change.Entity is not JsonElement entity || entity.ValueKind != JsonValueKind.Object || change.Tombstone is not null)
            {
                throw new LocalSyncException("invalid_response", "The Local Server live change item is incomplete.");
            }
        }
    }

    private static SyncEnvelope NormalizeLocalIdentity(SyncEnvelope envelope)
    {
        EntityDescriptor entity = envelope.Entity with
        {
            CanonicalId = envelope.Entity.CanonicalId ?? LocalCanonicalId(envelope.Entity.ClientUuid),
            References = envelope.Entity.References.Select(reference => reference with
            {
                TargetCanonicalId = reference.TargetCanonicalId ??
                    (reference.TargetClientUuid is Guid targetUuid && targetUuid != Guid.Empty
                        ? LocalCanonicalId(targetUuid)
                        : null)
            }).ToList()
        };
        return envelope with { Entity = entity };
    }

    private static string LocalCanonicalId(Guid clientUuid) => $"local:{clientUuid:D}";

    private static RemoteChange ToRemoteChange(LocalChangeFeedItem change)
    {
        bool deleted = change.OperationType == SyncOperationType.Delete;
        string canonicalId = LocalCanonicalId(change.EntityId);
        return new RemoteChange
        {
            ChangeId = change.ChangeLogId,
            ClientUuid = change.EntityId,
            EntityType = change.EntityType,
            CanonicalId = canonicalId,
            Operation = change.OperationType.ToString().ToLowerInvariant(),
            PayloadJson = deleted ? "{}" : change.Entity!.Value.GetRawText(),
            SearchText = deleted ? canonicalId : ExtractSearchText(change.Entity!.Value, canonicalId),
            RowVersion = change.RowVersion.ToString(CultureInfo.InvariantCulture),
            ConcurrencyToken = change.ConcurrencyToken.ToString("D"),
            OccurredAtUtc = change.ChangedAtUtc,
            DeletedAtUtc = change.Tombstone?.DeletedAtUtc,
            DeletedByDeviceId = change.Tombstone?.DeletedByDeviceId
        };
    }

    private static string ExtractSearchText(JsonElement entity, string fallback)
    {
        foreach (string field in new[]
        {
            "aidType", "category", "beneficiaryNameSnapshot", "serialNumber", "redemptionReference", "description", "notes"
        })
        {
            if (entity.TryGetProperty(field, out JsonElement value) && value.ValueKind == JsonValueKind.String)
            {
                string? text = value.GetString();
                if (!string.IsNullOrWhiteSpace(text))
                {
                    return text;
                }
            }
        }

        return fallback;
    }

    private static string? ValidateCompatibility(
        LocalServerHealthResponse health,
        LocalServerCapabilitiesResponse capabilities,
        string sourceId)
    {
        if (!string.Equals(health.Status, "ok", StringComparison.OrdinalIgnoreCase) ||
            !string.Equals(health.SourceId, sourceId, StringComparison.Ordinal) ||
            !string.Equals(capabilities.SourceId, sourceId, StringComparison.Ordinal) ||
            !string.Equals(health.SourceId, capabilities.SourceId, StringComparison.Ordinal) ||
            !string.Equals(health.ApiVersion, capabilities.ApiVersion, StringComparison.Ordinal) ||
            !IsCompatibleApiVersion(health.ApiVersion))
        {
            return "incompatible_api";
        }

        if (!string.Equals(capabilities.Transport, "https-required", StringComparison.Ordinal) ||
            !string.Equals(capabilities.CredentialType, "independent-device-credential", StringComparison.Ordinal) ||
            !string.Equals(capabilities.ClientCertificate, "trusted-chain-and-sha256-spki-pin-required", StringComparison.Ordinal))
        {
            return "incompatible_security";
        }

        if (!IsValidPublicCertificateInfo(health.Certificate) ||
            !IsValidPublicCertificateInfo(capabilities.Certificate) ||
            !string.Equals(health.Certificate.Sha256SpkiPin, capabilities.Certificate.Sha256SpkiPin, StringComparison.Ordinal))
        {
            return "incompatible_certificate";
        }

        return null;
    }

    private static bool IsCompatibleApiVersion(string? version) =>
        string.Equals(version, "1", StringComparison.Ordinal) ||
        (version?.StartsWith("1.", StringComparison.Ordinal) ?? false);

    private static bool IsValidPublicCertificateInfo(LocalServerCertificatePublicInfo? certificate)
    {
        if (certificate is null || string.IsNullOrWhiteSpace(certificate.Sha256SpkiPin) ||
            certificate.NotAfter <= certificate.NotBefore)
        {
            return false;
        }

        try
        {
            return Convert.FromBase64String(certificate.Sha256SpkiPin).Length == 32;
        }
        catch (FormatException)
        {
            return false;
        }
    }

    private static string RequiredString(JsonElement payload, string property)
    {
        string? value = OptionalString(payload, property);
        return value ?? throw new JsonException($"The Local Server payload omitted {property}.");
    }

    private static string? OptionalString(JsonElement payload, string property) =>
        payload.TryGetProperty(property, out JsonElement value) &&
        value.ValueKind is JsonValueKind.String or JsonValueKind.Null
            ? value.GetString()
            : null;

    private static Guid RequiredGuid(JsonElement payload, string property)
    {
        if (!payload.TryGetProperty(property, out JsonElement value) || !value.TryGetGuid(out Guid result) || result == Guid.Empty)
        {
            throw new JsonException($"The Local Server payload omitted a valid {property}.");
        }

        return result;
    }

    private static Guid? OptionalGuid(JsonElement payload, string property) =>
        payload.TryGetProperty(property, out JsonElement value) && value.ValueKind != JsonValueKind.Null
            ? value.TryGetGuid(out Guid result) ? result : throw new JsonException($"The Local Server payload has an invalid {property}.")
            : null;

    private static decimal RequiredDecimal(JsonElement payload, string property)
    {
        if (!payload.TryGetProperty(property, out JsonElement value) || !value.TryGetDecimal(out decimal result))
        {
            throw new JsonException($"The Local Server payload omitted a valid {property}.");
        }

        return result;
    }

    private static DateTimeOffset RequiredDate(JsonElement payload, string property) =>
        OptionalDate(payload, property) ?? throw new JsonException($"The Local Server payload omitted {property}.");

    private static DateTimeOffset? OptionalDate(JsonElement payload, string property)
    {
        if (!payload.TryGetProperty(property, out JsonElement value) || value.ValueKind == JsonValueKind.Null)
        {
            return null;
        }

        return value.ValueKind == JsonValueKind.String &&
            DateTimeOffset.TryParse(value.GetString(), CultureInfo.InvariantCulture, DateTimeStyles.RoundtripKind, out DateTimeOffset parsed)
            ? parsed
            : throw new JsonException($"The Local Server payload has an invalid {property}.");
    }

    private static void MarkTransportStages(
        IDictionary<string, ConnectionCheckStageResult> stages,
        bool succeeded)
    {
        foreach (string stage in new[] { "DNS", "TCP", "TLS" })
        {
            stages[stage] = new ConnectionCheckStageResult
            {
                Stage = stage,
                Succeeded = succeeded,
                ErrorCode = succeeded ? null : "not_checked"
            };
        }
    }

    private static void SetStageFailure(
        IDictionary<string, ConnectionCheckStageResult> stages,
        string stage,
        string errorCode) => stages[stage] = new ConnectionCheckStageResult
        {
            Stage = stage,
            Succeeded = false,
            ErrorCode = errorCode
        };

    private ConnectionCheckResult Result(IReadOnlyDictionary<string, ConnectionCheckStageResult> stages) =>
        new() { SourceId = SourceId, Stages = stages.Values.ToArray() };
}
