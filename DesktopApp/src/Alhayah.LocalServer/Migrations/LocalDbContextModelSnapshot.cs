using Alhayah.LocalServer.Data;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.EntityFrameworkCore.Storage.ValueConversion;
using System.Linq.Expressions;

#nullable disable

namespace Alhayah.LocalServer.Migrations;

[DbContext(typeof(LocalDbContext))]
public sealed class LocalDbContextModelSnapshot : ModelSnapshot
{
    protected override void BuildModel(ModelBuilder modelBuilder)
    {
        modelBuilder.HasAnnotation("ProductVersion", "10.0.0");

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeliveryEvidence", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedOnAdd()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<DateTimeOffset>("CapturedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("captured_at");
            entity.Property<long>("CapturedByLaravelUserId")
                .HasColumnType("INTEGER")
                .HasColumnName("captured_by_laravel_user_id");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<Guid>("DisbursementId")
                .HasColumnType("TEXT")
                .HasColumnName("disbursement_id");
            entity.Property<string>("EvidenceType")
                .IsRequired()
                .HasMaxLength(50)
                .HasColumnType("TEXT")
                .HasColumnName("evidence_type");
            entity.Property<string>("Notes")
                .HasColumnType("TEXT")
                .HasColumnName("notes");
            entity.Property<long>("RowVersion")
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<string>("Sha256")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("sha256");
            entity.Property<string>("SignatureMetadataJson")
                .HasColumnType("TEXT")
                .HasColumnName("signature_metadata_json");
            entity.Property<string>("StoragePath")
                .IsRequired()
                .HasColumnType("TEXT")
                .HasColumnName("storage_path");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");
            entity.Property<string>("VerificationStatus")
                .IsRequired()
                .HasMaxLength(50)
                .HasColumnType("TEXT")
                .HasColumnName("verification_status");

            entity.HasKey("Id");
            entity.HasIndex("DisbursementId");
            entity.HasQueryFilter((Expression<Func<DeliveryEvidence, bool>>)(item => item.DeletedAtUtc == null));
            entity.ToTable("delivery_evidences");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.Disbursement", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedOnAdd()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<decimal?>("Amount")
                .HasColumnType("TEXT")
                .HasColumnName("amount");
            entity.Property<Guid?>("BatchId")
                .HasColumnType("TEXT")
                .HasColumnName("batch_id");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<string>("CurrencyCode")
                .HasMaxLength(3)
                .HasColumnType("TEXT")
                .HasColumnName("currency_code");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<DateTimeOffset?>("DeliveredAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("delivered_at");
            entity.Property<Guid?>("DeliveredByRepresentativeId")
                .HasColumnType("TEXT")
                .HasColumnName("delivered_by_representative_id");
            entity.Property<Guid>("EntitlementId")
                .HasColumnType("TEXT")
                .HasColumnName("entitlement_id");
            entity.Property<decimal?>("Quantity")
                .HasColumnType("TEXT")
                .HasColumnName("quantity");
            entity.Property<string>("RecipientIdentityNumberSnapshot")
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("recipient_identity_number_snapshot");
            entity.Property<string>("RecipientNameSnapshot")
                .IsRequired()
                .HasMaxLength(400)
                .HasColumnType("TEXT")
                .HasColumnName("recipient_name_snapshot");
            entity.Property<string>("RecipientType")
                .IsRequired()
                .HasMaxLength(50)
                .HasColumnType("TEXT")
                .HasColumnName("recipient_type");
            entity.Property<DateTimeOffset>("RequestedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("requested_at");
            entity.Property<long>("RowVersion")
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<string>("Status")
                .IsRequired()
                .HasMaxLength(50)
                .HasColumnType("TEXT")
                .HasColumnName("status");
            entity.Property<Guid?>("SupplierId")
                .HasColumnType("TEXT")
                .HasColumnName("supplier_id");
            entity.Property<string>("TransactionNumber")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("transaction_number");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");
            entity.Property<string>("VerificationNotes")
                .HasColumnType("TEXT")
                .HasColumnName("verification_notes");

            entity.HasKey("Id");
            entity.HasIndex("TransactionNumber").IsUnique();
            entity.HasQueryFilter((Expression<Func<Disbursement, bool>>)(item => item.DeletedAtUtc == null));
            entity.ToTable("disbursements");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeviceRegistration", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedOnAdd()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<byte[]>("CredentialHash")
                .HasColumnType("BLOB")
                .HasColumnName("credential_hash");
            entity.Property<int>("CredentialVersion")
                .HasColumnType("INTEGER")
                .HasColumnName("credential_version");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<string>("DeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("device_id");
            entity.Property<byte[]>("EnrollmentCodeHash")
                .HasColumnType("BLOB")
                .HasColumnName("enrollment_code_hash");
            entity.Property<DateTimeOffset?>("EnrollmentCodeExpiresAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("enrollment_code_expires_at");
            entity.Property<DateTimeOffset?>("LastSeenAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("last_seen_at");
            entity.Property<int>("Permissions")
                .HasColumnType("INTEGER")
                .HasColumnName("permissions");
            entity.Property<DateTimeOffset?>("RevokedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("revoked_at");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");

            entity.HasKey("Id");
            entity.HasIndex("DeviceId").IsUnique();
            entity.ToTable("device_registrations");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeviceUserRegistration", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedOnAdd()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("DeviceRegistrationId")
                .HasColumnType("TEXT")
                .HasColumnName("device_registration_id");
            entity.Property<int>("Permissions")
                .HasColumnType("INTEGER")
                .HasColumnName("permissions");
            entity.Property<DateTimeOffset?>("RevokedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("revoked_at");
            entity.Property<string>("UserId")
                .IsRequired()
                .HasMaxLength(200)
                .HasColumnType("TEXT")
                .HasColumnName("user_id");

            entity.HasKey("Id");
            entity.HasIndex("DeviceRegistrationId", "UserId").IsUnique();
            entity.ToTable("device_users");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeliveryEvidence", entity =>
        {
            entity.HasOne("Alhayah.LocalServer.Data.Disbursement", "Disbursement")
                .WithMany("DeliveryEvidences")
                .HasForeignKey("DisbursementId")
                .OnDelete(DeleteBehavior.Cascade)
                .IsRequired();
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeviceUserRegistration", entity =>
        {
            entity.HasOne("Alhayah.LocalServer.Data.DeviceRegistration", "Device")
                .WithMany("Users")
                .HasForeignKey("DeviceRegistrationId")
                .OnDelete(DeleteBehavior.Cascade)
                .IsRequired();
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeliveryEvidence", entity =>
        {
            entity.Navigation("Disbursement");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeviceRegistration", entity =>
        {
            entity.Navigation("Users");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.DeviceUserRegistration", entity =>
        {
            entity.Navigation("Device");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Data.Disbursement", entity =>
        {
            entity.Navigation("DeliveryEvidences");
        });
    }
}
