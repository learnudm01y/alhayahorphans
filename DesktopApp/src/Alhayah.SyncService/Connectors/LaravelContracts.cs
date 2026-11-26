using System.Net;
using System.Text.Json;
using System.Text.Json.Serialization;
using Alhayah.SyncService.Cursors;

namespace Alhayah.SyncService.Connectors;

public sealed record LaravelConnectorOptions
{
    public required string SourceId { get; init; }

    public required string DeviceId { get; init; }

    public required string AppVersion { get; init; }

    public int MaxAttempts { get; init; } = 3;

    public int PullPageSize { get; init; } = 200;
}

public sealed class LaravelSyncException : IOException
{
    public LaravelSyncException(
        string errorCode,
        string safeMessage,
        HttpStatusCode? statusCode = null,
        bool isTransient = false,
        TimeSpan? retryAfter = null,
        Exception? innerException = null)
        : base(safeMessage, innerException)
    {
        ErrorCode = string.IsNullOrWhiteSpace(errorCode)
            ? throw new ArgumentException("A safe connector error code is required.", nameof(errorCode))
            : errorCode;
        StatusCode = statusCode;
        IsTransient = isTransient;
        RetryAfter = retryAfter;
    }

    public string ErrorCode { get; }

    public HttpStatusCode? StatusCode { get; }

    public bool IsTransient { get; }

    public TimeSpan? RetryAfter { get; }
}

public sealed record LaravelHealthResponse
{
    [JsonPropertyName("status")]
    public required string Status { get; init; }

    [JsonPropertyName("api_version")]
    public required string ApiVersion { get; init; }

    [JsonPropertyName("server_time")]
    public DateTimeOffset ServerTime { get; init; }
}

public sealed record LaravelCapabilitiesResponse
{
    [JsonPropertyName("capabilities")]
    public IReadOnlyList<string> Capabilities { get; init; } = [];

    [JsonPropertyName("device")]
    public JsonElement? Device { get; init; }

    [JsonPropertyName("api_version")]
    public required string ApiVersion { get; init; }

    [JsonPropertyName("server_time")]
    public DateTimeOffset ServerTime { get; init; }
}

public sealed record LaravelPullResult
{
    public IReadOnlyList<LaravelChange> Changes { get; init; } = [];

    public IReadOnlyList<LaravelSnapshotRecord> SnapshotRecords { get; init; } = [];

    public int PageCount { get; init; }

    public required CursorState Cursor { get; init; }
}

public sealed record LaravelPullResponse
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("changes")]
    public IReadOnlyList<LaravelChange> Changes { get; init; } = [];

    [JsonPropertyName("requires_full_reconciliation")]
    public bool RequiresFullReconciliation { get; init; }

    [JsonPropertyName("reason")]
    public string? Reason { get; init; }

    [JsonPropertyName("has_more")]
    public bool HasMore { get; init; }

    [JsonPropertyName("next_cursor")]
    public string? NextCursor { get; init; }

    [JsonPropertyName("server_time")]
    public DateTimeOffset ServerTime { get; init; }
}

public sealed record LaravelChange
{
    [JsonPropertyName("change_log_id")]
    public long ChangeLogId { get; init; }

    [JsonPropertyName("table_name")]
    public required string TableName { get; init; }

    [JsonPropertyName("primary_key")]
    public required string PrimaryKey { get; init; }

    [JsonPropertyName("operation")]
    public required string Operation { get; init; }

    [JsonPropertyName("client_uuid")]
    public Guid? ClientUuid { get; init; }

    [JsonPropertyName("row_version")]
    public string? RowVersion { get; init; }

    [JsonPropertyName("row_version_source")]
    public string? RowVersionSource { get; init; }

    [JsonPropertyName("soft_delete")]
    public bool SoftDelete { get; init; }

    [JsonPropertyName("changed_at")]
    public DateTimeOffset ChangedAt { get; init; }

    [JsonPropertyName("data")]
    public JsonElement? Data { get; init; }

    [JsonPropertyName("tombstone")]
    public LaravelTombstone? Tombstone { get; init; }
}

public sealed record LaravelTombstone
{
    [JsonPropertyName("table_name")]
    public required string TableName { get; init; }

    [JsonPropertyName("primary_key")]
    public required string PrimaryKey { get; init; }

    [JsonPropertyName("client_uuid")]
    public Guid? ClientUuid { get; init; }

    [JsonPropertyName("row_version")]
    public string? RowVersion { get; init; }

    [JsonPropertyName("deleted_at")]
    public DateTimeOffset DeletedAt { get; init; }

    [JsonPropertyName("deleted_by_device_id")]
    public required string DeletedByDeviceId { get; init; }
}

public sealed record LaravelReconciliationResponse
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("snapshot_id")]
    public string? SnapshotId { get; init; }

    [JsonPropertyName("records")]
    public IReadOnlyList<LaravelSnapshotRecord> Records { get; init; } = [];

    [JsonPropertyName("requires_full_reconciliation")]
    public bool RequiresFullReconciliation { get; init; }

    [JsonPropertyName("reason")]
    public string? Reason { get; init; }

    [JsonPropertyName("has_more")]
    public bool HasMore { get; init; }

    [JsonPropertyName("next_page_cursor")]
    public string? NextPageCursor { get; init; }

    [JsonPropertyName("next_cursor")]
    public string? NextCursor { get; init; }

    [JsonPropertyName("change_range")]
    public LaravelChangeRange? ChangeRange { get; init; }

    [JsonPropertyName("server_time")]
    public DateTimeOffset ServerTime { get; init; }
}

public sealed record LaravelSnapshotRecord
{
    [JsonPropertyName("table_name")]
    public required string TableName { get; init; }

    [JsonPropertyName("primary_key")]
    public required string PrimaryKey { get; init; }

    [JsonPropertyName("client_uuid")]
    public Guid? ClientUuid { get; init; }

    [JsonPropertyName("data")]
    public JsonElement Data { get; init; }
}

public sealed record LaravelChangeRange
{
    [JsonPropertyName("after_change_log_id")]
    public long AfterChangeLogId { get; init; }

    [JsonPropertyName("through_change_log_id")]
    public long ThroughChangeLogId { get; init; }
}

public sealed record LaravelPushOperationResult
{
    [JsonPropertyName("operationId")]
    public Guid OperationId { get; init; }

    [JsonPropertyName("status")]
    public required string Status { get; init; }

    [JsonPropertyName("clientUuid")]
    public Guid? ClientUuid { get; init; }

    [JsonPropertyName("canonicalId")]
    public string? CanonicalId { get; init; }

    [JsonPropertyName("rowVersion")]
    public string? RowVersion { get; init; }

    [JsonPropertyName("baseVersion")]
    public ulong? BaseVersion { get; init; }

    [JsonPropertyName("conflict")]
    public JsonElement? Conflict { get; init; }

    [JsonPropertyName("error")]
    public string? Error { get; init; }
}

public sealed record LaravelPushBatchResult
{
    public IReadOnlyList<LaravelPushOperationResult> Results { get; init; } = [];

    public IReadOnlyList<Guid> PendingOperationIds { get; init; } = [];
}

public sealed record LaravelConflictResolutionResponse
{
    [JsonPropertyName("status")]
    public required string Status { get; init; }

    [JsonPropertyName("conflictVersion")]
    public int ConflictVersion { get; init; }

    [JsonPropertyName("resolution")]
    public string? Resolution { get; init; }

    [JsonPropertyName("selectedFields")]
    public IReadOnlyList<string> SelectedFields { get; init; } = [];
}

public sealed record LaravelAttachmentManifest
{
    [JsonPropertyName("operationId")]
    public required Guid OperationId { get; init; }

    [JsonPropertyName("attachmentUuid")]
    public required Guid AttachmentUuid { get; init; }

    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("primaryKey")]
    public string? PrimaryKey { get; init; }

    [JsonPropertyName("clientUuid")]
    public Guid? ClientUuid { get; init; }

    [JsonPropertyName("attachmentReferenceKind")]
    public string? AttachmentReferenceKind { get; init; }

    [JsonPropertyName("canonicalAttachmentId")]
    public string? CanonicalAttachmentId { get; init; }

    [JsonPropertyName("originalFileName")]
    public required string OriginalFileName { get; init; }

    [JsonPropertyName("mimeType")]
    public required string MimeType { get; init; }

    [JsonPropertyName("fileSize")]
    public required long FileSize { get; init; }

    [JsonPropertyName("sha256")]
    public required string Sha256 { get; init; }

    [JsonPropertyName("chunkSize")]
    public required int ChunkSize { get; init; }

    [JsonPropertyName("totalChunks")]
    public required int TotalChunks { get; init; }
}

public sealed record LaravelAttachmentStatus
{
    [JsonPropertyName("attachmentUuid")]
    public Guid AttachmentUuid { get; init; }

    [JsonPropertyName("operationId")]
    public Guid OperationId { get; init; }

    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("primaryKey")]
    public string? PrimaryKey { get; init; }

    [JsonPropertyName("clientUuid")]
    public Guid? ClientUuid { get; init; }

    [JsonPropertyName("attachmentReferenceKind")]
    public string? AttachmentReferenceKind { get; init; }

    [JsonPropertyName("canonicalAttachmentId")]
    public string? CanonicalAttachmentId { get; init; }

    [JsonPropertyName("originalFileName")]
    public required string OriginalFileName { get; init; }

    [JsonPropertyName("mimeType")]
    public required string MimeType { get; init; }

    [JsonPropertyName("fileSize")]
    public long FileSize { get; init; }

    [JsonPropertyName("sha256")]
    public required string Sha256 { get; init; }

    [JsonPropertyName("chunkSize")]
    public int ChunkSize { get; init; }

    [JsonPropertyName("totalChunks")]
    public int TotalChunks { get; init; }

    [JsonPropertyName("receivedChunks")]
    public IReadOnlyList<int> ReceivedChunks { get; init; } = [];

    [JsonPropertyName("missingChunks")]
    public IReadOnlyList<int> MissingChunks { get; init; } = [];

    [JsonPropertyName("status")]
    public required string Status { get; init; }

    [JsonPropertyName("completed")]
    public bool Completed { get; init; }

    [JsonPropertyName("completedAt")]
    public DateTimeOffset? CompletedAt { get; init; }

    [JsonPropertyName("downloadUrl")]
    public string? DownloadUrl { get; init; }

    [JsonPropertyName("createdAt")]
    public DateTimeOffset? CreatedAt { get; init; }

    [JsonPropertyName("updatedAt")]
    public DateTimeOffset? UpdatedAt { get; init; }
}
