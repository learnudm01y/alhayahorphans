using System.Text.Json;
using Alhayah.Contracts.Domain;
using Alhayah.LocalServer.Domain.Entities;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.ChangeTracking;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Alhayah.LocalServer.Data;

public sealed class LocalDbContext(
    DbContextOptions<LocalDbContext> options,
    TimeProvider? timeProvider = null) : DbContext(options)
{
    private static readonly JsonSerializerOptions PayloadSerializerOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase
    };

    private readonly TimeProvider _timeProvider = timeProvider ?? TimeProvider.System;

    public DbSet<Disbursement> Disbursements => Set<Disbursement>();

    public DbSet<DeliveryEvidence> DeliveryEvidences => Set<DeliveryEvidence>();

    public DbSet<DeviceRegistration> DeviceRegistrations => Set<DeviceRegistration>();

    public DbSet<DeviceUserRegistration> DeviceUsers => Set<DeviceUserRegistration>();

    public DbSet<AidOperation> AidOperations => Set<AidOperation>();

    public DbSet<Expense> Expenses => Set<Expense>();

    public DbSet<Voucher> Vouchers => Set<Voucher>();

    public DbSet<VoucherDelivery> VoucherDeliveries => Set<VoucherDelivery>();

    public DbSet<VoucherRedemption> VoucherRedemptions => Set<VoucherRedemption>();

    public DbSet<LocalChangeLog> LocalChangeLogs => Set<LocalChangeLog>();

    public DbSet<IdempotencyRecord> IdempotencyRecords => Set<IdempotencyRecord>();

    public DbSet<ClientIdentityMappingEntity> ClientIdentities => Set<ClientIdentityMappingEntity>();

    public DbSet<CriticalConflict> CriticalConflicts => Set<CriticalConflict>();

    public DbSet<VoucherNumberBlock> VoucherNumberBlocks => Set<VoucherNumberBlock>();

    public override int SaveChanges() => SaveChanges(true);

    public override int SaveChanges(bool acceptAllChangesOnSuccess)
    {
        EntityEntry<LocalEntity>[] entries = GetPendingLocalEntries();
        if (entries.Length == 0)
        {
            return base.SaveChanges(acceptAllChangesOnSuccess);
        }

        PrepareLocalChanges(entries);
        if (Database.CurrentTransaction is not null)
        {
            return base.SaveChanges(acceptAllChangesOnSuccess);
        }

        using Microsoft.EntityFrameworkCore.Storage.IDbContextTransaction transaction = Database.BeginTransaction();
        try
        {
            int result = base.SaveChanges(acceptAllChangesOnSuccess);
            transaction.Commit();
            return result;
        }
        catch
        {
            transaction.Rollback();
            throw;
        }
    }

    public override Task<int> SaveChangesAsync(CancellationToken cancellationToken = default) =>
        SaveChangesAsync(true, cancellationToken);

    public override async Task<int> SaveChangesAsync(
        bool acceptAllChangesOnSuccess,
        CancellationToken cancellationToken = default)
    {
        EntityEntry<LocalEntity>[] entries = GetPendingLocalEntries();
        if (entries.Length == 0)
        {
            return await base.SaveChangesAsync(acceptAllChangesOnSuccess, cancellationToken).ConfigureAwait(false);
        }

        PrepareLocalChanges(entries);
        if (Database.CurrentTransaction is not null)
        {
            return await base.SaveChangesAsync(acceptAllChangesOnSuccess, cancellationToken).ConfigureAwait(false);
        }

        await using Microsoft.EntityFrameworkCore.Storage.IDbContextTransaction transaction =
            await Database.BeginTransactionAsync(cancellationToken).ConfigureAwait(false);
        try
        {
            int result = await base.SaveChangesAsync(acceptAllChangesOnSuccess, cancellationToken).ConfigureAwait(false);
            await transaction.CommitAsync(cancellationToken).ConfigureAwait(false);
            return result;
        }
        catch
        {
            await transaction.RollbackAsync(cancellationToken).ConfigureAwait(false);
            throw;
        }
    }

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.Entity<Disbursement>(entity =>
        {
            entity.ToTable("disbursements");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id");
            entity.Property(item => item.TransactionNumber).HasColumnName("transaction_number").HasMaxLength(100).IsRequired();
            entity.Property(item => item.RowVersion).HasColumnName("row_version");
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.UpdatedAtUtc).HasColumnName("updated_at");
            entity.Property(item => item.DeletedAtUtc).HasColumnName("deleted_at");
            entity.Property(item => item.EntitlementId).HasColumnName("entitlement_id");
            entity.Property(item => item.BatchId).HasColumnName("batch_id");
            entity.Property(item => item.SupplierId).HasColumnName("supplier_id");
            entity.Property(item => item.Amount).HasColumnName("amount");
            entity.Property(item => item.Quantity).HasColumnName("quantity");
            entity.Property(item => item.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3);
            entity.Property(item => item.Status).HasColumnName("status").HasMaxLength(50).IsRequired();
            entity.Property(item => item.RequestedAtUtc).HasColumnName("requested_at");
            entity.Property(item => item.DeliveredAtUtc).HasColumnName("delivered_at");
            entity.Property(item => item.RecipientType).HasColumnName("recipient_type").HasMaxLength(50).IsRequired();
            entity.Property(item => item.RecipientNameSnapshot).HasColumnName("recipient_name_snapshot").HasMaxLength(400).IsRequired();
            entity.Property(item => item.RecipientIdentityNumberSnapshot).HasColumnName("recipient_identity_number_snapshot").HasMaxLength(100);
            entity.Property(item => item.DeliveredByRepresentativeId).HasColumnName("delivered_by_representative_id");
            entity.Property(item => item.VerificationNotes).HasColumnName("verification_notes");
            entity.HasIndex(item => item.TransactionNumber).IsUnique();
            entity.HasQueryFilter(item => item.DeletedAtUtc == null);
        });

        modelBuilder.Entity<DeliveryEvidence>(entity =>
        {
            entity.ToTable("delivery_evidences");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id");
            entity.Property(item => item.DisbursementId).HasColumnName("disbursement_id");
            entity.Property(item => item.RowVersion).HasColumnName("row_version");
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.UpdatedAtUtc).HasColumnName("updated_at");
            entity.Property(item => item.DeletedAtUtc).HasColumnName("deleted_at");
            entity.Property(item => item.EvidenceType).HasColumnName("evidence_type").HasMaxLength(50).IsRequired();
            entity.Property(item => item.StoragePath).HasColumnName("storage_path").IsRequired();
            entity.Property(item => item.Sha256).HasColumnName("sha256").HasMaxLength(64).IsRequired();
            entity.Property(item => item.CapturedAtUtc).HasColumnName("captured_at");
            entity.Property(item => item.CapturedByLaravelUserId).HasColumnName("captured_by_laravel_user_id");
            entity.Property(item => item.SignatureMetadataJson).HasColumnName("signature_metadata_json");
            entity.Property(item => item.VerificationStatus).HasColumnName("verification_status").HasMaxLength(50).IsRequired();
            entity.Property(item => item.Notes).HasColumnName("notes");
            entity.HasIndex(item => item.DisbursementId);
            entity.HasOne(item => item.Disbursement)
                .WithMany(item => item.DeliveryEvidences)
                .HasForeignKey(item => item.DisbursementId)
                .OnDelete(DeleteBehavior.Cascade);
            entity.HasQueryFilter(item => item.DeletedAtUtc == null);
        });

        modelBuilder.Entity<DeviceRegistration>(entity =>
        {
            entity.ToTable("device_registrations");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id");
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(64).IsRequired();
            entity.Property(item => item.CredentialHash).HasColumnName("credential_hash");
            entity.Property(item => item.EnrollmentCodeHash).HasColumnName("enrollment_code_hash");
            entity.Property(item => item.EnrollmentCodeExpiresAtUtc).HasColumnName("enrollment_code_expires_at");
            entity.Property(item => item.CredentialVersion).HasColumnName("credential_version");
            entity.Property(item => item.Permissions).HasColumnName("permissions").HasConversion<int>();
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.UpdatedAtUtc).HasColumnName("updated_at");
            entity.Property(item => item.LastSeenAtUtc).HasColumnName("last_seen_at");
            entity.Property(item => item.RevokedAtUtc).HasColumnName("revoked_at");
            entity.HasIndex(item => item.DeviceId).IsUnique();
        });

        modelBuilder.Entity<DeviceUserRegistration>(entity =>
        {
            entity.ToTable("device_users");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id");
            entity.Property(item => item.DeviceRegistrationId).HasColumnName("device_registration_id");
            entity.Property(item => item.UserId).HasColumnName("user_id").HasMaxLength(200).IsRequired();
            entity.Property(item => item.Permissions).HasColumnName("permissions").HasConversion<int>();
            entity.Property(item => item.RevokedAtUtc).HasColumnName("revoked_at");
            entity.HasIndex(item => new { item.DeviceRegistrationId, item.UserId }).IsUnique();
            entity.HasOne(item => item.Device)
                .WithMany(item => item.Users)
                .HasForeignKey(item => item.DeviceRegistrationId)
                .OnDelete(DeleteBehavior.Cascade);
        });

        modelBuilder.Entity<AidOperation>(entity =>
        {
            ConfigureLocalEntity(entity, "aid_operations");
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.AidType).HasColumnName("aid_type").HasMaxLength(100).IsRequired();
            entity.Property(item => item.EntitlementId).HasColumnName("entitlement_id");
            entity.Property(item => item.Amount).HasColumnName("amount");
            entity.Property(item => item.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
            entity.Property(item => item.Status).HasColumnName("status").HasMaxLength(50).IsRequired();
            entity.Property(item => item.OccurredAtUtc).HasColumnName("occurred_at");
            entity.Property(item => item.Notes).HasColumnName("notes");
            entity.HasIndex(item => item.OperationId).IsUnique();
        });

        modelBuilder.Entity<Expense>(entity =>
        {
            ConfigureLocalEntity(entity, "expenses");
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.Category).HasColumnName("category").HasMaxLength(100).IsRequired();
            entity.Property(item => item.SupplierId).HasColumnName("supplier_id");
            entity.Property(item => item.Amount).HasColumnName("amount");
            entity.Property(item => item.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
            entity.Property(item => item.IncurredAtUtc).HasColumnName("incurred_at");
            entity.Property(item => item.Description).HasColumnName("description");
            entity.HasIndex(item => item.OperationId).IsUnique();
        });

        modelBuilder.Entity<Voucher>(entity =>
        {
            ConfigureLocalEntity(entity, "vouchers");
            entity.Property(item => item.SequenceId).HasColumnName("sequence_id");
            entity.Property(item => item.SerialNumber).HasColumnName("serial_number").HasMaxLength(100);
            entity.Property(item => item.DisbursementId).HasColumnName("disbursement_id");
            entity.Property(item => item.BeneficiaryIdentityNumberSnapshot).HasColumnName("beneficiary_identity_number_snapshot").HasMaxLength(100).IsRequired();
            entity.Property(item => item.BeneficiaryNameSnapshot).HasColumnName("beneficiary_name_snapshot").HasMaxLength(400).IsRequired();
            entity.Property(item => item.BeneficiaryPhoneSnapshot).HasColumnName("beneficiary_phone_snapshot").HasMaxLength(100);
            entity.Property(item => item.Amount).HasColumnName("amount");
            entity.Property(item => item.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
            entity.Property(item => item.SupplierId).HasColumnName("supplier_id");
            entity.Property(item => item.Status).HasColumnName("status").HasMaxLength(50).IsRequired();
            entity.Property(item => item.IssuedAtUtc).HasColumnName("issued_at");
            entity.Property(item => item.PrintedAtUtc).HasColumnName("printed_at");
            entity.Property(item => item.RedeemedAtUtc).HasColumnName("redeemed_at");
            entity.Property(item => item.ExpiresAtUtc).HasColumnName("expires_at");
            entity.Property(item => item.VoidReason).HasColumnName("void_reason");
            entity.HasIndex(item => item.SerialNumber).IsUnique();
            entity.HasIndex(item => item.SequenceId);
            entity.HasIndex(item => item.SupplierId);
        });

        modelBuilder.Entity<VoucherDelivery>(entity =>
        {
            ConfigureLocalEntity(entity, "voucher_deliveries");
            entity.Property(item => item.VoucherId).HasColumnName("voucher_id");
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.DeliveredAtUtc).HasColumnName("delivered_at");
            entity.Property(item => item.DeliveryStatus).HasColumnName("delivery_status").HasMaxLength(50).IsRequired();
            entity.Property(item => item.Notes).HasColumnName("notes");
            entity.HasIndex(item => item.VoucherId);
            entity.HasIndex(item => item.OperationId).IsUnique();
        });

        modelBuilder.Entity<VoucherRedemption>(entity =>
        {
            ConfigureLocalEntity(entity, "voucher_redemptions");
            entity.Property(item => item.VoucherId).HasColumnName("voucher_id");
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.RedeemedAtUtc).HasColumnName("redeemed_at");
            entity.Property(item => item.Amount).HasColumnName("amount");
            entity.Property(item => item.CurrencyCode).HasColumnName("currency_code").HasMaxLength(3).IsRequired();
            entity.Property(item => item.RedemptionReference).HasColumnName("redemption_reference").HasMaxLength(200);
            entity.HasIndex(item => item.VoucherId);
            entity.HasIndex(item => item.OperationId).IsUnique();
        });

        modelBuilder.Entity<IdempotencyRecord>(entity =>
        {
            entity.ToTable("idempotency_records");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id").ValueGeneratedNever();
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.IdempotencyKey).HasColumnName("idempotency_key").HasMaxLength(200);
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(64).IsRequired();
            entity.Property(item => item.OperationType).HasColumnName("operation_type").HasMaxLength(100).IsRequired();
            entity.Property(item => item.RequestHash).HasColumnName("request_hash").HasMaxLength(64).IsRequired();
            entity.Property(item => item.RequestJson).HasColumnName("request_json").IsRequired();
            entity.Property(item => item.Status).HasColumnName("status").HasConversion<string>().HasMaxLength(20).IsRequired();
            entity.Property(item => item.ResultJson).HasColumnName("result_json");
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.ExpiresAtUtc).HasColumnName("expires_at");
            entity.HasIndex(item => item.OperationId).IsUnique();
            entity.HasIndex(item => new { item.DeviceId, item.IdempotencyKey })
                .IsUnique()
                .HasFilter("[idempotency_key] IS NOT NULL");
        });

        modelBuilder.Entity<ClientIdentityMappingEntity>(entity =>
        {
            entity.ToTable("client_identity_mappings");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id").ValueGeneratedNever();
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(100).IsRequired();
            entity.Property(item => item.ClientUuid).HasColumnName("client_uuid");
            entity.Property(item => item.CanonicalId).HasColumnName("canonical_id").HasMaxLength(200);
            entity.Property(item => item.IsDeleted).HasColumnName("is_deleted");
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.UpdatedAtUtc).HasColumnName("updated_at");
            entity.HasIndex(item => item.ClientUuid).IsUnique();
            entity.HasIndex(item => new { item.EntityType, item.CanonicalId })
                .IsUnique()
                .HasFilter("[canonical_id] IS NOT NULL");
        });

        modelBuilder.Entity<CriticalConflict>(entity =>
        {
            entity.ToTable("critical_conflicts");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id").ValueGeneratedNever();
            entity.Property(item => item.OperationId).HasColumnName("operation_id");
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(64).IsRequired();
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(100).IsRequired();
            entity.Property(item => item.EntityId).HasColumnName("entity_id");
            entity.Property(item => item.ConflictType).HasColumnName("conflict_type").HasMaxLength(100).IsRequired();
            entity.Property(item => item.DetailsJson).HasColumnName("details_json").IsRequired();
            entity.Property(item => item.Decision).HasColumnName("decision").HasMaxLength(20).IsRequired();
            entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
            entity.Property(item => item.DecisionAtUtc).HasColumnName("decision_at");
            entity.Property(item => item.DecisionByDeviceId).HasColumnName("decision_by_device_id").HasMaxLength(64);
            entity.HasIndex(item => item.OperationId);
            entity.HasIndex(item => new { item.EntityType, item.EntityId });
        });

        modelBuilder.Entity<VoucherNumberBlock>(entity =>
        {
            entity.ToTable("voucher_number_blocks");
            entity.HasKey(item => item.Id);
            entity.Property(item => item.Id).HasColumnName("id").ValueGeneratedNever();
            entity.Property(item => item.SequenceId).HasColumnName("sequence_id");
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(64).IsRequired();
            entity.Property(item => item.FirstNumber).HasColumnName("first_number");
            entity.Property(item => item.LastNumber).HasColumnName("last_number");
            entity.Property(item => item.NextNumber).HasColumnName("next_number");
            entity.HasIndex(item => new { item.SequenceId, item.DeviceId }).IsUnique();
        });

        modelBuilder.Entity<LocalChangeLog>(entity =>
        {
            entity.ToTable("local_change_logs");
            entity.HasKey(item => item.ChangeLogId);
            entity.Property(item => item.ChangeLogId).HasColumnName("change_log_id").ValueGeneratedOnAdd();
            entity.Property(item => item.EntityType).HasColumnName("entity_type").HasMaxLength(100).IsRequired();
            entity.Property(item => item.EntityId).HasColumnName("entity_id");
            entity.Property(item => item.OperationType).HasColumnName("operation_type").HasConversion<string>().HasMaxLength(20).IsRequired();
            entity.Property(item => item.RowVersion).HasColumnName("row_version");
            entity.Property(item => item.ConcurrencyToken).HasColumnName("concurrency_token");
            entity.Property(item => item.ChangedAtUtc).HasColumnName("changed_at");
            entity.Property(item => item.DeviceId).HasColumnName("device_id").HasMaxLength(64).IsRequired();
            entity.Property(item => item.PayloadJson).HasColumnName("payload_json");
            entity.Property(item => item.DeletedAtUtc).HasColumnName("deleted_at");
            entity.Property(item => item.DeletedByDeviceId).HasColumnName("deleted_by_device_id").HasMaxLength(64);
            entity.HasIndex(item => new { item.EntityType, item.EntityId, item.RowVersion }).IsUnique();
        });
    }

    private static void ConfigureLocalEntity<TEntity>(EntityTypeBuilder<TEntity> entity, string tableName)
        where TEntity : LocalEntity
    {
        entity.ToTable(tableName);
        entity.HasKey(item => item.Id);
        entity.Property(item => item.Id).HasColumnName("id").ValueGeneratedNever().IsConcurrencyToken();
        entity.Property(item => item.ConcurrencyToken).HasColumnName("concurrency_token").ValueGeneratedNever().IsConcurrencyToken();
        entity.Property(item => item.RowVersion).HasColumnName("row_version").ValueGeneratedNever().IsConcurrencyToken();
        entity.Property(item => item.CreatedAtUtc).HasColumnName("created_at");
        entity.Property(item => item.UpdatedAtUtc).HasColumnName("updated_at");
        entity.Property(item => item.DeletedAtUtc).HasColumnName("deleted_at");
        entity.Property(item => item.LastModifiedByDeviceId).HasColumnName("last_modified_by_device_id").HasMaxLength(64).IsRequired();
        entity.Property(item => item.DeletedByDeviceId).HasColumnName("deleted_by_device_id").HasMaxLength(64);
        entity.HasQueryFilter(item => item.DeletedAtUtc == null);
    }

    private EntityEntry<LocalEntity>[] GetPendingLocalEntries() => ChangeTracker
        .Entries<LocalEntity>()
        .Where(entry => entry.State is EntityState.Added or EntityState.Modified or EntityState.Deleted)
        .ToArray();

    private void PrepareLocalChanges(IEnumerable<EntityEntry<LocalEntity>> entries)
    {
        DateTimeOffset now = _timeProvider.GetUtcNow();

        foreach (EntityEntry<LocalEntity> entry in entries)
        {
            LocalEntity entity = entry.Entity;
            if (string.IsNullOrWhiteSpace(entity.LastModifiedByDeviceId) || entity.LastModifiedByDeviceId.Length > 64)
            {
                throw new InvalidOperationException("A registered device id of at most 64 characters is required for local changes.");
            }

            if (entry.State == EntityState.Added)
            {
                if (entity.DeletedAtUtc is not null)
                {
                    throw new InvalidOperationException("A new local entity cannot be created as deleted.");
                }

                entity.Id = entity.Id == Guid.Empty ? Guid.NewGuid() : entity.Id;
                entity.ConcurrencyToken = Guid.NewGuid();
                entity.RowVersion = 1;
                entity.CreatedAtUtc = now;
                entity.UpdatedAtUtc = now;
                AddChangeLog(entity, SyncOperationType.Create, now);
                continue;
            }

            DateTimeOffset? originalDeletedAtUtc = entry.Property(item => item.DeletedAtUtc).OriginalValue;
            if (originalDeletedAtUtc is not null)
            {
                throw new InvalidOperationException("A soft-deleted local entity cannot be modified.");
            }

            bool isDeleting = entry.State == EntityState.Deleted || entity.DeletedAtUtc is not null;
            long originalRowVersion = entry.Property(item => item.RowVersion).OriginalValue;
            if (originalRowVersion < 0 || originalRowVersion == long.MaxValue)
            {
                throw new InvalidOperationException("The local row version is invalid.");
            }

            entry.State = EntityState.Modified;
            entity.RowVersion = originalRowVersion + 1;
            entity.ConcurrencyToken = Guid.NewGuid();
            entity.UpdatedAtUtc = now;

            if (isDeleting)
            {
                entity.DeletedAtUtc ??= now;
                entity.DeletedByDeviceId = entity.LastModifiedByDeviceId;
                AddChangeLog(entity, SyncOperationType.Delete, now);
            }
            else
            {
                AddChangeLog(entity, SyncOperationType.Update, now);
            }
        }
    }

    private void AddChangeLog(LocalEntity entity, SyncOperationType operationType, DateTimeOffset changedAtUtc)
    {
        string? payload = operationType == SyncOperationType.Delete
            ? null
            : JsonSerializer.Serialize(entity, entity.GetType(), PayloadSerializerOptions);

        LocalChangeLogs.Add(new LocalChangeLog
        {
            EntityType = entity.GetType().Name,
            EntityId = entity.Id,
            OperationType = operationType,
            RowVersion = entity.RowVersion,
            ConcurrencyToken = entity.ConcurrencyToken,
            ChangedAtUtc = changedAtUtc,
            DeviceId = entity.LastModifiedByDeviceId,
            PayloadJson = payload,
            DeletedAtUtc = operationType == SyncOperationType.Delete ? entity.DeletedAtUtc : null,
            DeletedByDeviceId = operationType == SyncOperationType.Delete ? entity.DeletedByDeviceId : null
        });
    }
}
