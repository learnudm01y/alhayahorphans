using System.Text.Json;
using System.Text.Json.Serialization;

namespace Alhayah.Contracts.Ipc;

public static class SyncServiceProtocol
{
    public const int Version = 1;
    public const int MaxFrameBytes = 1024 * 1024;
    public const string DefaultPipeName = "Alhayah.SyncService";
    public const string DefaultServiceName = "AlhayahSyncService";
}

public static class SyncServiceCommands
{
    public const string Login = "auth.login";
    public const string BootstrapAdministrator = "auth.bootstrap-administrator";
    public const string Logout = "auth.logout";
    public const string GetSettings = "settings.get";
    public const string SaveSettings = "settings.save";
    public const string CheckConnection = "settings.check-connection";
    public const string SynchronizeNow = "sync.now";
    public const string ListConflicts = "conflicts.list";
    public const string ResolveConflict = "conflicts.resolve";
    public const string WriteAudit = "audit.write";
}

public static class LocalPermissions
{
    public const string Read = "desktop.read";
    public const string Search = "desktop.search";
    public const string Save = "desktop.save";
    public const string Query = "desktop.query";
    public const string Backup = "desktop.backup";
    public const string Restore = "desktop.restore";
    public const string ManageSettings = "desktop.settings.manage";
    public const string ResolveConflicts = "desktop.conflicts.resolve";
    public const string Synchronize = "desktop.sync.now";
    public const string WriteAudit = "desktop.audit.write";
}

public sealed record SyncServiceRequest
{
    [JsonPropertyName("requestId")]
    public required Guid RequestId { get; init; }

    [JsonPropertyName("protocolVersion")]
    public required int ProtocolVersion { get; init; }

    [JsonPropertyName("command")]
    public required string Command { get; init; }

    [JsonPropertyName("payload")]
    public required JsonElement Payload { get; init; }

    [JsonPropertyName("sessionId")]
    public Guid? SessionId { get; init; }

    [JsonPropertyName("sessionProof")]
    public string? SessionProof { get; init; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? ExtensionData { get; init; }
}

public sealed record SyncServiceResponse
{
    [JsonPropertyName("requestId")]
    public required Guid RequestId { get; init; }

    [JsonPropertyName("protocolVersion")]
    public required int ProtocolVersion { get; init; }

    [JsonPropertyName("success")]
    public required bool Success { get; init; }

    [JsonPropertyName("payload")]
    public JsonElement? Payload { get; init; }

    [JsonPropertyName("error")]
    public SyncServiceError? Error { get; init; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? ExtensionData { get; init; }
}

public sealed record SyncServiceError
{
    [JsonPropertyName("code")]
    public required string Code { get; init; }
}

public sealed record EmptyRequest;

public sealed record AcknowledgedResponse
{
    [JsonPropertyName("acknowledged")]
    public required bool Acknowledged { get; init; }
}

public sealed record LoginRequest
{
    [JsonPropertyName("userName")]
    public required string UserName { get; init; }

    [JsonPropertyName("password")]
    public required string Password { get; init; }

    [JsonPropertyName("deviceId")]
    public required string DeviceId { get; init; }

    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }
}

public sealed record BootstrapAdministratorRequest
{
    [JsonPropertyName("userName")]
    public required string UserName { get; init; }

    [JsonPropertyName("displayName")]
    public required string DisplayName { get; init; }

    [JsonPropertyName("password")]
    public required string Password { get; init; }

    [JsonPropertyName("confirmationPassword")]
    public required string ConfirmationPassword { get; init; }

    [JsonPropertyName("nonce")]
    public required string Nonce { get; init; }

    [JsonPropertyName("deviceId")]
    public required string DeviceId { get; init; }

    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }
}

public sealed record LocalSession
{
    [JsonPropertyName("sessionId")]
    public required Guid SessionId { get; init; }

    [JsonPropertyName("sessionProof")]
    public required string SessionProof { get; init; }

    [JsonPropertyName("userId")]
    public required Guid UserId { get; init; }

    [JsonPropertyName("deviceId")]
    public required string DeviceId { get; init; }

    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("permissions")]
    public required IReadOnlyList<string> Permissions { get; init; }

    [JsonPropertyName("manifestVersion")]
    public required long ManifestVersion { get; init; }

    [JsonPropertyName("manifestIssuedAtUtc")]
    public required DateTimeOffset ManifestIssuedAtUtc { get; init; }

    [JsonPropertyName("expiresAtUtc")]
    public required DateTimeOffset ExpiresAtUtc { get; init; }
}

public sealed record ConnectionSettingsData
{
    [JsonPropertyName("connectionName")]
    public required string ConnectionName { get; init; }

    [JsonPropertyName("laravelEndpoint")]
    public required string LaravelEndpoint { get; init; }

    [JsonPropertyName("localServerEndpoint")]
    public required string LocalServerEndpoint { get; init; }

    [JsonPropertyName("laravelSpkiSha256Pin")]
    public required string LaravelSpkiSha256Pin { get; init; }

    [JsonPropertyName("previousLaravelSpkiSha256Pin")]
    public string? PreviousLaravelSpkiSha256Pin { get; init; }

    [JsonPropertyName("localServerSpkiSha256Pin")]
    public required string LocalServerSpkiSha256Pin { get; init; }

    [JsonPropertyName("previousLocalServerSpkiSha256Pin")]
    public string? PreviousLocalServerSpkiSha256Pin { get; init; }

    [JsonPropertyName("requestTimeoutSeconds")]
    public required int RequestTimeoutSeconds { get; init; }

    [JsonPropertyName("synchronizationEnabled")]
    public required bool SynchronizationEnabled { get; init; }
}

public sealed record ConnectionSecrets
{
    [JsonPropertyName("laravelDeviceToken")]
    public string? LaravelDeviceToken { get; init; }

    [JsonPropertyName("localServerDeviceCredential")]
    public string? LocalServerDeviceCredential { get; init; }
}

public sealed record SaveConnectionSettingsRequest
{
    [JsonPropertyName("settings")]
    public required ConnectionSettingsData Settings { get; init; }

    [JsonPropertyName("secrets")]
    public ConnectionSecrets? Secrets { get; init; }
}

public sealed record ConnectionCheckRequest
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }
}

public sealed record ConnectionCheckResult
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("stages")]
    public required IReadOnlyList<ConnectionCheckStageResult> Stages { get; init; }
}

public sealed record ConnectionCheckStageResult
{
    [JsonPropertyName("stage")]
    public required string Stage { get; init; }

    [JsonPropertyName("succeeded")]
    public required bool Succeeded { get; init; }

    [JsonPropertyName("errorCode")]
    public string? ErrorCode { get; init; }
}

public sealed record SynchronizeNowRequest
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }
}

public sealed record SynchronizeNowResult
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("status")]
    public required string Status { get; init; }
}

public sealed record ConflictReviewData
{
    [JsonPropertyName("conflictId")]
    public required Guid ConflictId { get; init; }

    [JsonPropertyName("operationId")]
    public required Guid OperationId { get; init; }

    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("clientUuid")]
    public required Guid ClientUuid { get; init; }

    [JsonPropertyName("conflictClass")]
    public required string ConflictClass { get; init; }

    [JsonPropertyName("status")]
    public required string Status { get; init; }

    [JsonPropertyName("rowVersion")]
    public string? RowVersion { get; init; }
}

public sealed record ConflictListResponse
{
    [JsonPropertyName("items")]
    public required IReadOnlyList<ConflictReviewData> Items { get; init; }
}

public sealed record ConflictResolutionRequest
{
    [JsonPropertyName("conflictId")]
    public required Guid ConflictId { get; init; }

    [JsonPropertyName("expectedRowVersion")]
    public required string ExpectedRowVersion { get; init; }

    [JsonPropertyName("decision")]
    public required string Decision { get; init; }
}

public sealed record ConflictResolutionResponse
{
    [JsonPropertyName("conflictId")]
    public required Guid ConflictId { get; init; }

    [JsonPropertyName("status")]
    public required string Status { get; init; }
}

[JsonConverter(typeof(JsonStringEnumConverter<AuditResult>))]
public enum AuditResult
{
    Committed,
    Rejected,
    Failed
}

public sealed record AuditEventRequest
{
    [JsonPropertyName("operationId")]
    public required Guid OperationId { get; init; }

    [JsonPropertyName("offlineUserId")]
    public Guid? OfflineUserId { get; init; }

    [JsonPropertyName("deviceId")]
    public required string DeviceId { get; init; }

    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("permissionUsed")]
    public required string PermissionUsed { get; init; }

    [JsonPropertyName("manifestVersion")]
    public long? ManifestVersion { get; init; }

    [JsonPropertyName("occurredAtUtc")]
    public required DateTimeOffset OccurredAtUtc { get; init; }

    [JsonPropertyName("payloadSha256")]
    public required string PayloadSha256 { get; init; }

    [JsonPropertyName("result")]
    public required AuditResult Result { get; init; }
}
