using System.Net;
using System.Text.Json;
using System.Text.Json.Serialization;
using Alhayah.Contracts.Domain;
using Alhayah.SyncService.Cursors;

namespace Alhayah.SyncService.Connectors;

public sealed class LocalConnectorOptions
{
    public required string SourceId { get; init; }

    public required string DeviceId { get; init; }

    public required string UserId { get; init; }

    public required string ClientCertificateThumbprint { get; init; }

    public int MaxAttempts { get; init; } = 3;

    public int PullPageSize { get; init; } = 100;
}

public sealed record LocalServerCertificatePublicInfo(
    [property: JsonPropertyName("sha256_spki_pin")] string Sha256SpkiPin,
    [property: JsonPropertyName("not_before")] DateTimeOffset NotBefore,
    [property: JsonPropertyName("not_after")] DateTimeOffset NotAfter);

public sealed record LocalServerHealthResponse(
    [property: JsonPropertyName("status")] string Status,
    [property: JsonPropertyName("api_version")] string ApiVersion,
    [property: JsonPropertyName("source_id")] string SourceId,
    [property: JsonPropertyName("server_time")] DateTimeOffset ServerTime,
    [property: JsonPropertyName("certificate")] LocalServerCertificatePublicInfo Certificate);

public sealed record LocalServerCapabilitiesResponse(
    [property: JsonPropertyName("api_version")] string ApiVersion,
    [property: JsonPropertyName("source_id")] string SourceId,
    [property: JsonPropertyName("server_time")] DateTimeOffset ServerTime,
    [property: JsonPropertyName("certificate")] LocalServerCertificatePublicInfo Certificate,
    [property: JsonPropertyName("credential_type")] string CredentialType,
    [property: JsonPropertyName("transport")] string Transport,
    [property: JsonPropertyName("client_certificate")] string ClientCertificate,
    [property: JsonPropertyName("permissions")] IReadOnlyList<string> Permissions);

public sealed record LocalChangeTombstone(
    [property: JsonPropertyName("deleted_at")] DateTimeOffset DeletedAtUtc,
    [property: JsonPropertyName("deleted_by_device_id")] string DeletedByDeviceId);

public sealed record LocalChangeFeedItem(
    [property: JsonPropertyName("change_log_id")] long ChangeLogId,
    [property: JsonPropertyName("entity_type")] string EntityType,
    [property: JsonPropertyName("entity_id")] Guid EntityId,
    [property: JsonPropertyName("operation_type")] SyncOperationType OperationType,
    [property: JsonPropertyName("row_version")] long RowVersion,
    [property: JsonPropertyName("concurrency_token")] Guid ConcurrencyToken,
    [property: JsonPropertyName("changed_at")] DateTimeOffset ChangedAtUtc,
    [property: JsonPropertyName("device_id")] string DeviceId,
    [property: JsonPropertyName("entity")] JsonElement? Entity,
    [property: JsonPropertyName("tombstone")] LocalChangeTombstone? Tombstone);

public sealed record LocalChangeFeedResponse(
    [property: JsonPropertyName("source_id")] string SourceId,
    [property: JsonPropertyName("server_time")] DateTimeOffset ServerTime,
    [property: JsonPropertyName("next_cursor")] string NextCursor,
    [property: JsonPropertyName("has_more")] bool HasMore,
    [property: JsonPropertyName("changes")] IReadOnlyList<LocalChangeFeedItem> Changes);

[JsonConverter(typeof(JsonStringEnumConverter<LocalOperationStatus>))]
public enum LocalOperationStatus
{
    Pending,
    Completed,
    Conflict
}

public sealed record LocalOperationResult(
    [property: JsonPropertyName("operation_id")] Guid OperationId,
    [property: JsonPropertyName("operation_type")] string OperationType,
    [property: JsonPropertyName("status")] LocalOperationStatus Status,
    [property: JsonPropertyName("entity_id")] Guid? EntityId,
    [property: JsonPropertyName("row_version")] long? RowVersion,
    [property: JsonPropertyName("conflict_type")] string? ConflictType,
    [property: JsonPropertyName("expires_at")] DateTimeOffset ExpiresAtUtc);

public sealed record LocalPullResult
{
    public required IReadOnlyList<LocalChangeFeedItem> Changes { get; init; }

    public required int PageCount { get; init; }

    public required CursorState Cursor { get; init; }
}

public sealed class LocalSyncException : Exception
{
    public LocalSyncException(
        string errorCode,
        string message,
        bool isTransient = false,
        HttpStatusCode? statusCode = null,
        TimeSpan? retryAfter = null,
        Exception? innerException = null)
        : base(message, innerException)
    {
        ErrorCode = errorCode;
        IsTransient = isTransient;
        StatusCode = statusCode;
        RetryAfter = retryAfter;
    }

    public string ErrorCode { get; }

    public bool IsTransient { get; }

    public HttpStatusCode? StatusCode { get; }

    public TimeSpan? RetryAfter { get; }
}
