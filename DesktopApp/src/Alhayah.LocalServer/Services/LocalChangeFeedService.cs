using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalServer.Services;

public sealed class LocalChangeFeedService(
    LocalDbContext dbContext,
    LocalServerSecurityOptions securityOptions,
    TimeProvider timeProvider)
{
    public const int DefaultLimit = 100;
    public const int MaximumLimit = 500;

    private const string Channel = "local-operations";
    private const int CursorVersion = 1;

    private readonly LocalDbContext _dbContext = dbContext ?? throw new ArgumentNullException(nameof(dbContext));
    private readonly LocalServerSecurityOptions _securityOptions = securityOptions
        ?? throw new ArgumentNullException(nameof(securityOptions));
    private readonly TimeProvider _timeProvider = timeProvider ?? throw new ArgumentNullException(nameof(timeProvider));

    public async Task<LocalChangeFeedResponse> GetChangesAsync(
        string? cursor,
        int limit = DefaultLimit,
        CancellationToken cancellationToken = default)
    {
        if (limit is < 1 or > MaximumLimit)
        {
            throw new ArgumentOutOfRangeException(nameof(limit), $"The change-feed limit must be between 1 and {MaximumLimit}.");
        }

        long after = DecodeCursor(cursor);
        List<LocalChangeLog> rows = await _dbContext.LocalChangeLogs
            .AsNoTracking()
            .Where(item => item.ChangeLogId > after)
            .OrderBy(item => item.ChangeLogId)
            .Take(limit + 1)
            .ToListAsync(cancellationToken)
            .ConfigureAwait(false);

        bool hasMore = rows.Count > limit;
        List<LocalChangeLog> page = hasMore ? rows.Take(limit).ToList() : rows;
        long nextPosition = page.Count == 0 ? after : page[^1].ChangeLogId;
        IReadOnlyList<LocalChangeFeedItem> changes = page.Select(ToFeedItem).ToArray();

        return new LocalChangeFeedResponse(
            _securityOptions.SourceId.Value,
            _timeProvider.GetUtcNow(),
            EncodeCursor(nextPosition),
            hasMore,
            changes);
    }

    private string EncodeCursor(long after)
    {
        CursorPayload payload = new(CursorVersion, _securityOptions.SourceId.Value, Channel, after);
        return Base64UrlEncode(JsonSerializer.SerializeToUtf8Bytes(payload));
    }

    private string DecodeCursor(string? cursor)
    {
        if (string.IsNullOrWhiteSpace(cursor))
        {
            return 0;
        }

        try
        {
            byte[] bytes = Base64UrlDecode(cursor);
            CursorPayload payload = JsonSerializer.Deserialize<CursorPayload>(bytes)
                ?? throw new JsonException("The cursor payload is empty.");
            if (payload.Version != CursorVersion ||
                payload.After < 0 ||
                !string.Equals(payload.Channel, Channel, StringComparison.Ordinal) ||
                !string.Equals(payload.SourceId, _securityOptions.SourceId.Value, StringComparison.Ordinal))
            {
                throw new InvalidChangeCursorException();
            }

            return payload.After;
        }
        catch (Exception exception) when (exception is FormatException or JsonException or DecoderFallbackException)
        {
            throw new InvalidChangeCursorException();
        }
    }

    private static LocalChangeFeedItem ToFeedItem(LocalChangeLog change)
    {
        JsonElement? entity = change.PayloadJson is null
            ? null
            : JsonSerializer.Deserialize<JsonElement>(change.PayloadJson);
        LocalChangeTombstone? tombstone = change.OperationType == SyncOperationType.Delete
            ? new LocalChangeTombstone(
                change.DeletedAtUtc ?? throw new InvalidOperationException("A delete change must include its deletion timestamp."),
                change.DeletedByDeviceId ?? throw new InvalidOperationException("A delete change must include its device id."))
            : null;

        return new LocalChangeFeedItem(
            change.ChangeLogId,
            change.EntityType,
            change.EntityId,
            change.OperationType,
            change.RowVersion,
            change.ConcurrencyToken,
            change.ChangedAtUtc,
            change.DeviceId,
            entity,
            tombstone);
    }

    private static string Base64UrlEncode(byte[] bytes) => Convert.ToBase64String(bytes)
        .TrimEnd('=')
        .Replace('+', '-')
        .Replace('/', '_');

    private static byte[] Base64UrlDecode(string value)
    {
        string base64 = value.Replace('-', '+').Replace('_', '/');
        string padded = base64.PadRight(base64.Length + ((4 - (base64.Length % 4)) % 4), '=');
        return Convert.FromBase64String(padded);
    }

    private sealed record CursorPayload(
        [property: JsonPropertyName("version")] int Version,
        [property: JsonPropertyName("source_id")] string SourceId,
        [property: JsonPropertyName("channel")] string Channel,
        [property: JsonPropertyName("after")] long After);
}

public sealed class InvalidChangeCursorException() : Exception("The change-feed cursor is invalid for this source and channel.");

public sealed record LocalChangeFeedResponse(
    [property: JsonPropertyName("source_id")] string SourceId,
    [property: JsonPropertyName("server_time")] DateTimeOffset ServerTime,
    [property: JsonPropertyName("next_cursor")] string NextCursor,
    [property: JsonPropertyName("has_more")] bool HasMore,
    [property: JsonPropertyName("changes")] IReadOnlyList<LocalChangeFeedItem> Changes);

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

public sealed record LocalChangeTombstone(
    [property: JsonPropertyName("deleted_at")] DateTimeOffset DeletedAtUtc,
    [property: JsonPropertyName("deleted_by_device_id")] string DeletedByDeviceId);
