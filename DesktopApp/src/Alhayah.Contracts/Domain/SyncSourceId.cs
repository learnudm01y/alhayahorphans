using System.Text.Json;
using System.Text.Json.Serialization;

namespace Alhayah.Contracts.Domain;

[JsonConverter(typeof(SyncSourceIdJsonConverter))]
public sealed record SyncSourceId
{
    public SyncSourceId(string value)
    {
        Value = Normalize(value);
    }

    public string Value { get; }

    public override string ToString() => Value;

    private static string Normalize(string value)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(value);

        var normalized = value.Trim().ToLowerInvariant();
        var separator = normalized.IndexOf(':');
        if (separator < 1 || normalized.Length - separator - 1 is < 1 or > 128)
        {
            throw new ArgumentException("Source identity must use laravel:<installation-id> or local:<installation-id>.", nameof(value));
        }

        var prefix = normalized[..separator];
        if (prefix is not ("laravel" or "local"))
        {
            throw new ArgumentException("Source identity must use laravel:<installation-id> or local:<installation-id>.", nameof(value));
        }

        var installationId = normalized[(separator + 1)..];
        if (!char.IsAsciiLetterOrDigit(installationId[0]) ||
            installationId.Any(character => !char.IsAsciiLetterOrDigit(character) && character is not ('.' or '_' or '-')))
        {
            throw new ArgumentException("Source installation identity contains invalid characters.", nameof(value));
        }

        return normalized;
    }
}

public sealed class SyncSourceIdJsonConverter : JsonConverter<SyncSourceId>
{
    public override SyncSourceId? Read(ref Utf8JsonReader reader, Type typeToConvert, JsonSerializerOptions options)
    {
        if (reader.TokenType != JsonTokenType.String)
        {
            throw new JsonException("Source identity must be a JSON string.");
        }

        var value = reader.GetString();
        return value is null ? null : new SyncSourceId(value);
    }

    public override void Write(Utf8JsonWriter writer, SyncSourceId value, JsonSerializerOptions options) =>
        writer.WriteStringValue(value.Value);
}
