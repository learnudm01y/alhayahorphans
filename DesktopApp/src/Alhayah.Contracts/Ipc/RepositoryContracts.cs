using System.Text.Json.Serialization;
using Alhayah.Contracts.Domain;

namespace Alhayah.Contracts.Ipc;

public static class RepositoryCommands
{
    public const string Read = "repository.read";
    public const string Search = "repository.search";
    public const string Save = "repository.save";
    public const string Query = "repository.query";
    public const string Backup = "repository.backup";
    public const string Restore = "repository.restore";
}

public sealed record ReadEntityRequest
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("clientUuid")]
    public required Guid ClientUuid { get; init; }
}

public sealed record ReadEntityResponse
{
    [JsonPropertyName("entity")]
    public RepositoryEntity? Entity { get; init; }
}

public sealed record SearchEntitiesRequest
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("query")]
    public required string Query { get; init; }

    [JsonPropertyName("limit")]
    public int Limit { get; init; } = 200;
}

public sealed record QueryEntitiesRequest
{
    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("skip")]
    public int Skip { get; init; }

    [JsonPropertyName("take")]
    public int Take { get; init; } = 200;

    [JsonPropertyName("includeDeleted")]
    public bool IncludeDeleted { get; init; }
}

public sealed record SaveEntityRequest
{
    [JsonPropertyName("envelope")]
    public required SyncEnvelope Envelope { get; init; }

    [JsonPropertyName("payloadJson")]
    public string? PayloadJson { get; init; }

    [JsonPropertyName("searchText")]
    public string? SearchText { get; init; }
}

public sealed record SaveEntityResponse
{
    [JsonPropertyName("entity")]
    public required RepositoryEntity Entity { get; init; }
}

public sealed record EntityListResponse
{
    [JsonPropertyName("items")]
    public required IReadOnlyList<RepositoryEntity> Items { get; init; }

    [JsonPropertyName("totalCount")]
    public required int TotalCount { get; init; }
}

public sealed record RepositoryEntity
{
    [JsonPropertyName("clientUuid")]
    public required Guid ClientUuid { get; init; }

    [JsonPropertyName("sourceId")]
    public required string SourceId { get; init; }

    [JsonPropertyName("entityType")]
    public required string EntityType { get; init; }

    [JsonPropertyName("canonicalId")]
    public string? CanonicalId { get; init; }

    [JsonPropertyName("payloadJson")]
    public required string PayloadJson { get; init; }

    [JsonPropertyName("baseVersion")]
    public string? BaseVersion { get; init; }

    [JsonPropertyName("rowVersion")]
    public string? RowVersion { get; init; }

    [JsonPropertyName("createdAtUtc")]
    public required DateTimeOffset CreatedAtUtc { get; init; }

    [JsonPropertyName("updatedAtUtc")]
    public required DateTimeOffset UpdatedAtUtc { get; init; }

    [JsonPropertyName("deletedAtUtc")]
    public DateTimeOffset? DeletedAtUtc { get; init; }

    [JsonPropertyName("deletedByDeviceId")]
    public string? DeletedByDeviceId { get; init; }
}

public sealed record BackupRequest
{
    [JsonPropertyName("destinationDirectory")]
    public string? DestinationDirectory { get; init; }
}

public sealed record BackupResponse
{
    [JsonPropertyName("directoryPath")]
    public required string DirectoryPath { get; init; }

    [JsonPropertyName("databasePath")]
    public required string DatabasePath { get; init; }

    [JsonPropertyName("manifestPath")]
    public required string ManifestPath { get; init; }
}

public sealed record RestoreRequest
{
    [JsonPropertyName("backupDirectory")]
    public required string BackupDirectory { get; init; }

    [JsonPropertyName("destinationDatabasePath")]
    public required string DestinationDatabasePath { get; init; }

    [JsonPropertyName("destinationAttachmentDirectory")]
    public required string DestinationAttachmentDirectory { get; init; }
}

public sealed record RestoreResponse
{
    [JsonPropertyName("destinationDatabasePath")]
    public required string DestinationDatabasePath { get; init; }

    [JsonPropertyName("destinationAttachmentDirectory")]
    public required string DestinationAttachmentDirectory { get; init; }
}
