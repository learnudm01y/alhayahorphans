using Microsoft.EntityFrameworkCore;

namespace Alhayah.LocalServer.Data;

public sealed class LocalDbContext(DbContextOptions<LocalDbContext> options) : DbContext(options)
{
    public DbSet<Disbursement> Disbursements => Set<Disbursement>();

    public DbSet<DeliveryEvidence> DeliveryEvidences => Set<DeliveryEvidence>();

    public DbSet<DeviceRegistration> DeviceRegistrations => Set<DeviceRegistration>();

    public DbSet<DeviceUserRegistration> DeviceUsers => Set<DeviceUserRegistration>();

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
    }
}
