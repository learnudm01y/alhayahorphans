using System.Text.Json;
using System.Text.Json.Serialization;

namespace Alhayah.Contracts.Domain;

[JsonConverter(typeof(JsonStringEnumConverter<EntityReferenceKind>))]
public enum EntityReferenceKind
{
    Field,
    Attachment
}

public sealed record EntityDescriptor
{
    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("clientUuid")]
    public required Guid ClientUuid { get; init; }

    [JsonPropertyName("canonicalId")]
    public string? CanonicalId { get; init; }

    [JsonPropertyName("references")]
    public required List<EntityReference> References { get; init; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? ExtensionData { get; init; }
}

public sealed record EntityReference
{
    [JsonPropertyName("fieldName")]
    public required string FieldName { get; init; }

    [JsonPropertyName("targetEntityType")]
    public required string TargetEntityType { get; init; }

    [JsonPropertyName("targetClientUuid")]
    public Guid? TargetClientUuid { get; init; }

    [JsonPropertyName("targetCanonicalId")]
    public string? TargetCanonicalId { get; init; }

    [JsonPropertyName("kind")]
    public required EntityReferenceKind Kind { get; init; }

    [JsonPropertyName("isDeleted")]
    public bool IsDeleted { get; set; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? ExtensionData { get; init; }
}
