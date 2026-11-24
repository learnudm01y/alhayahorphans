using System.Text.Json;
using System.Text.Json.Serialization;

namespace Alhayah.Contracts.Domain;

[JsonConverter(typeof(JsonStringEnumConverter<SyncOperationType>))]
public enum SyncOperationType
{
    Create,
    Update,
    Delete
}

public sealed record SyncEnvelope
{
    [JsonPropertyName("operationId")]
    public required Guid OperationId { get; init; }

    [JsonPropertyName("sourceId")]
    public required SyncSourceId SourceId { get; init; }

    [JsonPropertyName("deviceId")]
    public required string DeviceId { get; init; }

    [JsonPropertyName("entity")]
    public required EntityDescriptor Entity { get; init; }

    [JsonPropertyName("operationType")]
    public required SyncOperationType OperationType { get; init; }

    [JsonPropertyName("baseVersion")]
    public ulong? BaseVersion { get; init; }

    [JsonPropertyName("rowVersion")]
    public string? RowVersion { get; init; }

    [JsonPropertyName("cursor")]
    public string? Cursor { get; init; }

    [JsonPropertyName("occurredAtUtc")]
    public required DateTimeOffset OccurredAtUtc { get; init; }

    [JsonPropertyName("tombstone")]
    public Tombstone? Tombstone { get; init; }

    [JsonPropertyName("patches")]
    public required IReadOnlyList<FieldPatch> Patches { get; init; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? ExtensionData { get; init; }
}

public sealed record Tombstone
{
    [JsonPropertyName("deletedAtUtc")]
    public required DateTimeOffset DeletedAtUtc { get; init; }

    [JsonPropertyName("deletedByDeviceId")]
    public required string DeletedByDeviceId { get; init; }

    [JsonPropertyName("reason")]
    public string? Reason { get; init; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? ExtensionData { get; init; }
}
