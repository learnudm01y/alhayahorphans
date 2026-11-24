using System.Text.Json;
using System.Text.Json.Serialization;

namespace Alhayah.Contracts.Domain;

[JsonConverter(typeof(JsonStringEnumConverter<FieldCriticality>))]
public enum FieldCriticality
{
    Mergeable,
    Critical,
    Reference,
    ServerOwned
}

public sealed record FieldPatch
{
    [JsonPropertyName("fieldName")]
    public required string FieldName { get; init; }

    [JsonPropertyName("baseValue")]
    public JsonElement? BaseValue { get; init; }

    [JsonPropertyName("localValue")]
    public JsonElement? LocalValue { get; init; }

    [JsonPropertyName("remoteValue")]
    public JsonElement? RemoteValue { get; init; }

    [JsonPropertyName("criticality")]
    public required FieldCriticality Criticality { get; init; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? ExtensionData { get; init; }
}
