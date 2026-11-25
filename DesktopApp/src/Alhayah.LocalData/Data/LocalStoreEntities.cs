namespace Alhayah.LocalData.Data;

public sealed class CachedEntity
{
    public Guid ClientUuid { get; set; }

    public required string SourceId { get; set; }

    public required string EntityType { get; set; }

    public string? CanonicalId { get; set; }

    public required string PayloadJson { get; set; }

    public required string SearchTextNormalized { get; set; }

    public string? BaseVersion { get; set; }

    public string? RowVersion { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset UpdatedAtUtc { get; set; }

    public DateTimeOffset? DeletedAtUtc { get; set; }

    public string? DeletedByDeviceId { get; set; }
}

public sealed class OutboxOperation
{
    public Guid OperationId { get; set; }

    public required string SourceId { get; set; }

    public required string DeviceId { get; set; }

    public required string EntityType { get; set; }

    public Guid ClientUuid { get; set; }

    public required string OperationType { get; set; }

    public required string EnvelopeJson { get; set; }

    public required string Status { get; set; }

    public int AttemptCount { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset? LastAttemptAtUtc { get; set; }

    public CachedEntity? Entity { get; set; }
}

public sealed class LocalChangeLogEntry
{
    public long ChangeId { get; set; }

    public Guid OperationId { get; set; }

    public required string SourceId { get; set; }

    public required string EntityType { get; set; }

    public Guid ClientUuid { get; set; }

    public required string OperationType { get; set; }

    public string? RowVersion { get; set; }

    public required string PayloadJson { get; set; }

    public required string DeviceId { get; set; }

    public DateTimeOffset OccurredAtUtc { get; set; }
}

public sealed class SyncConflict
{
    public Guid Id { get; set; }

    public Guid OperationId { get; set; }

    public required string SourceId { get; set; }

    public required string EntityType { get; set; }

    public Guid ClientUuid { get; set; }

    public required string ConflictClass { get; set; }

    public required string EnvelopeJson { get; set; }

    public required string Status { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset? ResolvedAtUtc { get; set; }

    public Guid? ResolvedByUserId { get; set; }
}

public sealed class SyncCheckpoint
{
    public required string SourceId { get; set; }

    public required string Channel { get; set; }

    public string? Cursor { get; set; }

    public long? LastCommittedChangeId { get; set; }

    public bool ReconciliationRequired { get; set; }

    public DateTimeOffset UpdatedAtUtc { get; set; }
}

public sealed class ClientIdentityMappingEntity
{
    public Guid ClientUuid { get; set; }

    public required string EntityType { get; set; }

    public string? CanonicalId { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset? ResolvedAtUtc { get; set; }
}

public sealed class LocalUser
{
    public Guid Id { get; set; }

    public required string UserNameNormalized { get; set; }

    public required string DisplayName { get; set; }

    public required byte[] PasswordHash { get; set; }

    public required byte[] PasswordSalt { get; set; }

    public int Argon2MemoryKib { get; set; }

    public int Argon2Iterations { get; set; }

    public int Argon2Parallelism { get; set; }

    public required string PermissionManifestJson { get; set; }

    public required byte[] ManifestSignature { get; set; }

    public long ManifestVersion { get; set; }

    public DateTimeOffset ManifestIssuedAtUtc { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset? DisabledAtUtc { get; set; }

    public List<LocalSession> Sessions { get; set; } = [];
}

public sealed class LocalSession
{
    public Guid Id { get; set; }

    public Guid UserId { get; set; }

    public required string DeviceId { get; set; }

    public required byte[] SessionProofHash { get; set; }

    public long ManifestVersion { get; set; }

    public DateTimeOffset CreatedAtUtc { get; set; }

    public DateTimeOffset ExpiresAtUtc { get; set; }

    public DateTimeOffset LastSeenAtUtc { get; set; }

    public DateTimeOffset? RevokedAtUtc { get; set; }

    public LocalUser? User { get; set; }
}

public sealed class AuditEntry
{
    public long AuditId { get; set; }

    public Guid OperationId { get; set; }

    public Guid? OfflineUserId { get; set; }

    public required string DeviceId { get; set; }

    public required string SourceId { get; set; }

    public required string PermissionUsed { get; set; }

    public long? ManifestVersion { get; set; }

    public DateTimeOffset OccurredAtUtc { get; set; }

    public required string PayloadSha256 { get; set; }

    public required string Result { get; set; }
}
