using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalData.Data;

public sealed class LocalStoreDbContext(DbContextOptions<LocalStoreDbContext> options) : DbContext(options)
{
    public DbSet<CachedEntity> CachedEntities => Set<CachedEntity>();

    public DbSet<OutboxOperation> OutboxOperations => Set<OutboxOperation>();

    public DbSet<LocalChangeLogEntry> LocalChangeLog => Set<LocalChangeLogEntry>();

    public DbSet<SyncConflict> SyncConflicts => Set<SyncConflict>();

    public DbSet<SyncCheckpoint> SyncCheckpoints => Set<SyncCheckpoint>();

    public DbSet<ClientIdentityMappingEntity> ClientIdentityMap => Set<ClientIdentityMappingEntity>();

    public DbSet<LocalUser> LocalUsers => Set<LocalUser>();

    public DbSet<LocalSession> LocalSessions => Set<LocalSession>();

    public DbSet<AuditEntry> AuditEntries => Set<AuditEntry>();

    protected override void OnModelCreating(ModelBuilder modelBuilder) => ConfigureModel(modelBuilder);

    internal static void ConfigureModel(ModelBuilder modelBuilder)
    {
        modelBuilder.Entity<CachedEntity>(entity =>
        {
            entity.ToTable("cached_entities");
            entity.HasKey(item => item.ClientUuid);
            entity.Property(item => item.ClientUuid).HasColumnName("client_uuid");
            entity.Property(item => item.SourceId).HasColumnName("source_id").HasMaxLength(180).IsRequired();
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(120).IsRequired();
            entity.Property(item => item.CanonicalId).HasColumnName("canonical_id").HasMaxLength(200);
            entity.Property(item => item.PayloadJson).HasColumnName("payload_json").IsRequired();
            entity.Property(item => item.SearchTextNormalized).HasColumnName("search_text_normalized").IsRequired();
            entity.Property(item => item.BaseVersion).HasColumnName("base_version").HasMaxLength(40);
            entity.Property(item => item.RowVersion).HasColumnName("row_version").HasMaxLength(200).IsConcurrencyToken();
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.UpdatedAtUtc).HasColumnName("updated_at");
            entity.Property(item => item.DeletedAtUtc).HasColumnName("deleted_at");
            entity.Property(item => item.DeletedByDeviceId).HasColumnName("deleted_by_device_id").HasMaxLength(128);
            entity.HasIndex(item => new { item.SourceId, item.EntityType, item.SearchTextNormalized });
            entity.HasIndex(item => new { item.SourceId, item.EntityType, item.CanonicalId })
                .IsUnique()
                .HasFilter("[canonical_id] IS NOT NULL");
            entity.HasQueryFilter(item => item.DeletedAtUtc == null);
        });

        modelBuilder.Entity<OutboxOperation>(entity =>
        {
            entity.ToTable("outbox_operations");
            entity.HasKey(item => item.OperationId);
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.SourceId).HasColumnName("source_id").HasMaxLength(180).IsRequired();
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(128).IsRequired();
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(120).IsRequired();
            entity.Property(item => item.ClientUuid).HasColumnName("client_uuid");
            entity.Property(item => item.OperationType).HasColumnName("operation_type").HasMaxLength(20).IsRequired();
            entity.Property(item => item.EnvelopeJson).HasColumnName("envelope_json").IsRequired();
            entity.Property(item => item.Status).HasColumnName("status").HasMaxLength(32).IsRequired();
            entity.Property(item => item.AttemptCount).HasColumnName("attempt_count");
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.LastAttemptAtUtc).HasColumnName("last_attempt_at");
            entity.HasIndex(item => new { item.Status, item.CreatedAtUtc });
            entity.HasIndex(item => item.ClientUuid);
            entity.HasOne(item => item.Entity)
                .WithMany()
                .HasForeignKey(item => item.ClientUuid)
                .OnDelete(DeleteBehavior.Restrict)
                .IsRequired();
        });

        modelBuilder.Entity<LocalChangeLogEntry>(entity =>
        {
            entity.ToTable("local_change_log");
            entity.HasKey(item => item.ChangeId);
            entity.Property(item => item.ChangeId).HasColumnName("change_id").ValueGeneratedOnAdd();
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.SourceId).HasColumnName("source_id").HasMaxLength(180).IsRequired();
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(120).IsRequired();
            entity.Property(item => item.ClientUuid).HasColumnName("client_uuid");
            entity.Property(item => item.OperationType).HasColumnName("operation_type").HasMaxLength(20).IsRequired();
            entity.Property(item => item.RowVersion).HasColumnName("row_version").HasMaxLength(200);
            entity.Property(item => item.PayloadJson).HasColumnName("payload_json").IsRequired();
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(128).IsRequired();
            entity.Property(item => item.OccurredAtUtc).HasColumnName("occurred_at");
            entity.HasIndex(item => item.OperationId).IsUnique();
            entity.HasIndex(item => new { item.SourceId, item.EntityType, item.ClientUuid, item.ChangeId });
        });

        modelBuilder.Entity<SyncConflict>(entity =>
        {
            entity.ToTable("sync_conflicts");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id");
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.SourceId).HasColumnName("source_id").HasMaxLength(180).IsRequired();
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(120).IsRequired();
            entity.Property(item => item.ClientUuid).HasColumnName("client_uuid");
            entity.Property(item => item.ConflictClass).HasColumnName("conflict_class").HasMaxLength(60).IsRequired();
            entity.Property(item => item.EnvelopeJson).HasColumnName("envelope_json").IsRequired();
            entity.Property(item => item.Status).HasColumnName("status").HasMaxLength(32).IsRequired();
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.ResolvedAtUtc).HasColumnName("resolved_at");
            entity.Property(item => item.ResolvedByUserId).HasColumnName("resolved_by_user_id");
            entity.HasIndex(item => new { item.Status, item.CreatedAtUtc });
            entity.HasIndex(item => item.OperationId);
        });

        modelBuilder.Entity<SyncCheckpoint>(entity =>
        {
            entity.ToTable("sync_checkpoints");
            entity.HasKey(item => new { item.SourceId, item.Channel });
            entity.Property(item => item.SourceId).HasColumnName("source_id").HasMaxLength(180);
            entity.Property(item => item.Channel).HasColumnName("channel").HasMaxLength(120);
            entity.Property(item => item.Cursor).HasColumnName("cursor");
            entity.Property(item => item.LastCommittedChangeId).HasColumnName("last_committed_change_id");
            entity.Property(item => item.ReconciliationRequired).HasColumnName("reconciliation_required");
            entity.Property(item => item.UpdatedAtUtc).HasColumnName("updated_at");
        });

        modelBuilder.Entity<ClientIdentityMappingEntity>(entity =>
        {
            entity.ToTable("client_identity_map");
            entity.HasKey(item => item.ClientUuid);
            entity.Property(item => item.ClientUuid).HasColumnName("client_uuid");
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(120).IsRequired();
            entity.Property(item => item.CanonicalId).HasColumnName("canonical_id").HasMaxLength(200);
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.ResolvedAtUtc).HasColumnName("resolved_at");
            entity.HasIndex(item => new { item.EntityType, item.CanonicalId })
                .IsUnique()
                .HasFilter("[canonical_id] IS NOT NULL");
        });

        modelBuilder.Entity<LocalUser>(entity =>
        {
            entity.ToTable("local_users");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id");
            entity.Property(item => item.UserNameNormalized).HasColumnName("user_name_normalized").HasMaxLength(200).IsRequired();
            entity.Property(item => item.DisplayName).HasColumnName("display_name").HasMaxLength(400).IsRequired();
            entity.Property(item => item.PasswordHash).HasColumnName("password_hash").IsRequired();
            entity.Property(item => item.PasswordSalt).HasColumnName("password_salt").IsRequired();
            entity.Property(item => item.Argon2MemoryKib).HasColumnName("argon2_memory_kib");
            entity.Property(item => item.Argon2Iterations).HasColumnName("argon2_iterations");
            entity.Property(item => item.Argon2Parallelism).HasColumnName("argon2_parallelism");
            entity.Property(item => item.PermissionManifestJson).HasColumnName("permission_manifest_json").IsRequired();
            entity.Property(item => item.ManifestSignature).HasColumnName("manifest_signature").IsRequired();
            entity.Property(item => item.ManifestVersion).HasColumnName("manifest_version");
            entity.Property(item => item.ManifestIssuedAtUtc).HasColumnName("manifest_issued_at");
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.DisabledAtUtc).HasColumnName("disabled_at");
            entity.HasIndex(item => item.UserNameNormalized).IsUnique();
        });

        modelBuilder.Entity<LocalSession>(entity =>
        {
            entity.ToTable("local_sessions");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id");
            entity.Property(item => item.UserId).HasColumnName("user_id");
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(128).IsRequired();
            entity.Property(item => item.SessionProofHash).HasColumnName("session_proof_hash").IsRequired();
            entity.Property(item => item.ManifestVersion).HasColumnName("manifest_version");
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.ExpiresAtUtc).HasColumnName("expires_at");
            entity.Property(item => item.LastSeenAtUtc).HasColumnName("last_seen_at");
            entity.Property(item => item.RevokedAtUtc).HasColumnName("revoked_at");
            entity.HasIndex(item => new { item.UserId, item.ExpiresAtUtc });
            entity.HasIndex(item => item.UserId);
            entity.HasOne(item => item.User)
                .WithMany(item => item.Sessions)
                .HasForeignKey(item => item.UserId)
                .OnDelete(DeleteBehavior.Cascade)
                .IsRequired();
        });

        modelBuilder.Entity<AuditEntry>(entity =>
        {
            entity.ToTable("audit_entries");
            entity.HasKey(item => item.AuditId);
            entity.Property(item => item.AuditId).HasColumnName("audit_id").ValueGeneratedOnAdd();
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.OfflineUserId).HasColumnName("offline_user_id");
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(128).IsRequired();
            entity.Property(item => item.SourceId).HasColumnName("source_id").HasMaxLength(180).IsRequired();
            entity.Property(item => item.PermissionUsed).HasColumnName("permission_used").HasMaxLength(200).IsRequired();
            entity.Property(item => item.ManifestVersion).HasColumnName("manifest_version");
            entity.Property(item => item.OccurredAtUtc).HasColumnName("occurred_at");
            entity.Property(item => item.PayloadSha256).HasColumnName("payload_sha256").HasMaxLength(64).IsRequired();
            entity.Property(item => item.Result).HasColumnName("result").HasMaxLength(60).IsRequired();
            entity.HasIndex(item => new { item.OperationId, item.AuditId });
        });
    }
}
