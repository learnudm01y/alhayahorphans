using Alhayah.LocalServer.Data;
using Alhayah.LocalServer.Domain.Entities;
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

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.AidOperation", entity =>
        {
            entity.Property<Guid>("Id")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("ConcurrencyToken")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("concurrency_token");
            entity.Property<long>("RowVersion")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<string>("LastModifiedByDeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("last_modified_by_device_id");
            entity.Property<string>("DeletedByDeviceId")
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("deleted_by_device_id");
            entity.Property<Guid>("OperationId")
                .HasColumnType("TEXT")
                .HasColumnName("operation_id");
            entity.Property<string>("AidType")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("aid_type");
            entity.Property<Guid?>("EntitlementId")
                .HasColumnType("TEXT")
                .HasColumnName("entitlement_id");
            entity.Property<decimal>("Amount")
                .HasColumnType("TEXT")
                .HasColumnName("amount");
            entity.Property<string>("CurrencyCode")
                .IsRequired()
                .HasMaxLength(3)
                .HasColumnType("TEXT")
                .HasColumnName("currency_code");
            entity.Property<string>("Status")
                .IsRequired()
                .HasMaxLength(50)
                .HasColumnType("TEXT")
                .HasColumnName("status");
            entity.Property<DateTimeOffset>("OccurredAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("occurred_at");
            entity.Property<string>("Notes")
                .HasColumnType("TEXT")
                .HasColumnName("notes");

            entity.HasKey("Id");
            entity.HasIndex("OperationId").IsUnique();
            entity.HasQueryFilter((Expression<Func<AidOperation, bool>>)(item => item.DeletedAtUtc == null));
            entity.ToTable("aid_operations");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.Expense", entity =>
        {
            entity.Property<Guid>("Id")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("ConcurrencyToken")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("concurrency_token");
            entity.Property<long>("RowVersion")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<string>("LastModifiedByDeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("last_modified_by_device_id");
            entity.Property<string>("DeletedByDeviceId")
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("deleted_by_device_id");
            entity.Property<Guid>("OperationId")
                .HasColumnType("TEXT")
                .HasColumnName("operation_id");
            entity.Property<string>("Category")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("category");
            entity.Property<Guid?>("SupplierId")
                .HasColumnType("TEXT")
                .HasColumnName("supplier_id");
            entity.Property<decimal>("Amount")
                .HasColumnType("TEXT")
                .HasColumnName("amount");
            entity.Property<string>("CurrencyCode")
                .IsRequired()
                .HasMaxLength(3)
                .HasColumnType("TEXT")
                .HasColumnName("currency_code");
            entity.Property<DateTimeOffset>("IncurredAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("incurred_at");
            entity.Property<string>("Description")
                .HasColumnType("TEXT")
                .HasColumnName("description");

            entity.HasKey("Id");
            entity.HasIndex("OperationId").IsUnique();
            entity.HasQueryFilter((Expression<Func<Expense, bool>>)(item => item.DeletedAtUtc == null));
            entity.ToTable("expenses");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.Voucher", entity =>
        {
            entity.Property<Guid>("Id")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("ConcurrencyToken")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("concurrency_token");
            entity.Property<long>("RowVersion")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<string>("LastModifiedByDeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("last_modified_by_device_id");
            entity.Property<string>("DeletedByDeviceId")
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("deleted_by_device_id");
            entity.Property<Guid>("SequenceId")
                .HasColumnType("TEXT")
                .HasColumnName("sequence_id");
            entity.Property<string>("SerialNumber")
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("serial_number");
            entity.Property<Guid?>("DisbursementId")
                .HasColumnType("TEXT")
                .HasColumnName("disbursement_id");
            entity.Property<string>("BeneficiaryIdentityNumberSnapshot")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("beneficiary_identity_number_snapshot");
            entity.Property<string>("BeneficiaryNameSnapshot")
                .IsRequired()
                .HasMaxLength(400)
                .HasColumnType("TEXT")
                .HasColumnName("beneficiary_name_snapshot");
            entity.Property<string>("BeneficiaryPhoneSnapshot")
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("beneficiary_phone_snapshot");
            entity.Property<decimal>("Amount")
                .HasColumnType("TEXT")
                .HasColumnName("amount");
            entity.Property<string>("CurrencyCode")
                .IsRequired()
                .HasMaxLength(3)
                .HasColumnType("TEXT")
                .HasColumnName("currency_code");
            entity.Property<Guid>("SupplierId")
                .HasColumnType("TEXT")
                .HasColumnName("supplier_id");
            entity.Property<string>("Status")
                .IsRequired()
                .HasMaxLength(50)
                .HasColumnType("TEXT")
                .HasColumnName("status");
            entity.Property<DateTimeOffset>("IssuedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("issued_at");
            entity.Property<DateTimeOffset?>("PrintedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("printed_at");
            entity.Property<DateTimeOffset?>("RedeemedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("redeemed_at");
            entity.Property<DateTimeOffset?>("ExpiresAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("expires_at");
            entity.Property<string>("VoidReason")
                .HasColumnType("TEXT")
                .HasColumnName("void_reason");

            entity.HasKey("Id");
            entity.HasIndex("SequenceId");
            entity.HasIndex("SerialNumber").IsUnique();
            entity.HasIndex("SupplierId");
            entity.HasQueryFilter((Expression<Func<Voucher, bool>>)(item => item.DeletedAtUtc == null));
            entity.ToTable("vouchers");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.VoucherDelivery", entity =>
        {
            entity.Property<Guid>("Id")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("ConcurrencyToken")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("concurrency_token");
            entity.Property<long>("RowVersion")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<string>("LastModifiedByDeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("last_modified_by_device_id");
            entity.Property<string>("DeletedByDeviceId")
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("deleted_by_device_id");
            entity.Property<Guid>("VoucherId")
                .HasColumnType("TEXT")
                .HasColumnName("voucher_id");
            entity.Property<Guid>("OperationId")
                .HasColumnType("TEXT")
                .HasColumnName("operation_id");
            entity.Property<DateTimeOffset>("DeliveredAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("delivered_at");
            entity.Property<string>("DeliveryStatus")
                .IsRequired()
                .HasMaxLength(50)
                .HasColumnType("TEXT")
                .HasColumnName("delivery_status");
            entity.Property<string>("Notes")
                .HasColumnType("TEXT")
                .HasColumnName("notes");

            entity.HasKey("Id");
            entity.HasIndex("OperationId").IsUnique();
            entity.HasIndex("VoucherId");
            entity.HasQueryFilter((Expression<Func<VoucherDelivery, bool>>)(item => item.DeletedAtUtc == null));
            entity.ToTable("voucher_deliveries");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.VoucherRedemption", entity =>
        {
            entity.Property<Guid>("Id")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("ConcurrencyToken")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("concurrency_token");
            entity.Property<long>("RowVersion")
                .IsConcurrencyToken()
                .ValueGeneratedNever()
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<string>("LastModifiedByDeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("last_modified_by_device_id");
            entity.Property<string>("DeletedByDeviceId")
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("deleted_by_device_id");
            entity.Property<Guid>("VoucherId")
                .HasColumnType("TEXT")
                .HasColumnName("voucher_id");
            entity.Property<Guid>("OperationId")
                .HasColumnType("TEXT")
                .HasColumnName("operation_id");
            entity.Property<DateTimeOffset>("RedeemedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("redeemed_at");
            entity.Property<decimal>("Amount")
                .HasColumnType("TEXT")
                .HasColumnName("amount");
            entity.Property<string>("CurrencyCode")
                .IsRequired()
                .HasMaxLength(3)
                .HasColumnType("TEXT")
                .HasColumnName("currency_code");
            entity.Property<string>("RedemptionReference")
                .HasMaxLength(200)
                .HasColumnType("TEXT")
                .HasColumnName("redemption_reference");

            entity.HasKey("Id");
            entity.HasIndex("OperationId").IsUnique();
            entity.HasIndex("VoucherId");
            entity.HasQueryFilter((Expression<Func<VoucherRedemption, bool>>)(item => item.DeletedAtUtc == null));
            entity.ToTable("voucher_redemptions");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.IdempotencyRecord", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("OperationId")
                .HasColumnType("TEXT")
                .HasColumnName("operation_id");
            entity.Property<string>("IdempotencyKey")
                .HasMaxLength(200)
                .HasColumnType("TEXT")
                .HasColumnName("idempotency_key");
            entity.Property<string>("DeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("device_id");
            entity.Property<string>("OperationType")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("operation_type");
            entity.Property<string>("RequestHash")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("request_hash");
            entity.Property<string>("RequestJson")
                .IsRequired()
                .HasColumnType("TEXT")
                .HasColumnName("request_json");
            entity.Property<string>("Status")
                .IsRequired()
                .HasMaxLength(20)
                .HasColumnType("TEXT")
                .HasColumnName("status");
            entity.Property<string>("ResultJson")
                .HasColumnType("TEXT")
                .HasColumnName("result_json");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset>("ExpiresAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("expires_at");

            entity.HasKey("Id");
            entity.HasIndex("OperationId").IsUnique();
            entity.HasIndex("DeviceId", "IdempotencyKey")
                .IsUnique()
                .HasFilter("[idempotency_key] IS NOT NULL");
            entity.ToTable("idempotency_records");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.ClientIdentityMappingEntity", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<string>("EntityType")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("entity_type");
            entity.Property<Guid>("ClientUuid")
                .HasColumnType("TEXT")
                .HasColumnName("client_uuid");
            entity.Property<string>("CanonicalId")
                .HasMaxLength(200)
                .HasColumnType("TEXT")
                .HasColumnName("canonical_id");
            entity.Property<bool>("IsDeleted")
                .HasColumnType("INTEGER")
                .HasColumnName("is_deleted");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset>("UpdatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("updated_at");

            entity.HasKey("Id");
            entity.HasIndex("ClientUuid").IsUnique();
            entity.HasIndex("EntityType", "CanonicalId")
                .IsUnique()
                .HasFilter("[canonical_id] IS NOT NULL");
            entity.ToTable("client_identity_mappings");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.CriticalConflict", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("OperationId")
                .HasColumnType("TEXT")
                .HasColumnName("operation_id");
            entity.Property<string>("DeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("device_id");
            entity.Property<string>("EntityType")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("entity_type");
            entity.Property<Guid>("EntityId")
                .HasColumnType("TEXT")
                .HasColumnName("entity_id");
            entity.Property<string>("ConflictType")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("conflict_type");
            entity.Property<string>("DetailsJson")
                .IsRequired()
                .HasColumnType("TEXT")
                .HasColumnName("details_json");
            entity.Property<string>("Decision")
                .IsRequired()
                .HasMaxLength(20)
                .HasColumnType("TEXT")
                .HasColumnName("decision");
            entity.Property<DateTimeOffset>("CreatedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("created_at");
            entity.Property<DateTimeOffset?>("DecisionAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("decision_at");
            entity.Property<string>("DecisionByDeviceId")
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("decision_by_device_id");

            entity.HasKey("Id");
            entity.HasIndex("OperationId");
            entity.HasIndex("EntityType", "EntityId");
            entity.ToTable("critical_conflicts");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.VoucherNumberBlock", entity =>
        {
            entity.Property<Guid>("Id")
                .ValueGeneratedNever()
                .HasColumnType("TEXT")
                .HasColumnName("id");
            entity.Property<Guid>("SequenceId")
                .HasColumnType("TEXT")
                .HasColumnName("sequence_id");
            entity.Property<string>("DeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("device_id");
            entity.Property<long>("FirstNumber")
                .HasColumnType("INTEGER")
                .HasColumnName("first_number");
            entity.Property<long>("LastNumber")
                .HasColumnType("INTEGER")
                .HasColumnName("last_number");
            entity.Property<long>("NextNumber")
                .HasColumnType("INTEGER")
                .HasColumnName("next_number");

            entity.HasKey("Id");
            entity.HasIndex("SequenceId", "DeviceId").IsUnique();
            entity.ToTable("voucher_number_blocks");
        });

        modelBuilder.Entity("Alhayah.LocalServer.Domain.Entities.LocalChangeLog", entity =>
        {
            entity.Property<long>("ChangeLogId")
                .ValueGeneratedOnAdd()
                .HasColumnType("INTEGER")
                .HasColumnName("change_log_id");
            entity.Property<string>("EntityType")
                .IsRequired()
                .HasMaxLength(100)
                .HasColumnType("TEXT")
                .HasColumnName("entity_type");
            entity.Property<Guid>("EntityId")
                .HasColumnType("TEXT")
                .HasColumnName("entity_id");
            entity.Property<string>("OperationType")
                .IsRequired()
                .HasMaxLength(20)
                .HasColumnType("TEXT")
                .HasColumnName("operation_type");
            entity.Property<long>("RowVersion")
                .HasColumnType("INTEGER")
                .HasColumnName("row_version");
            entity.Property<Guid>("ConcurrencyToken")
                .HasColumnType("TEXT")
                .HasColumnName("concurrency_token");
            entity.Property<DateTimeOffset>("ChangedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("changed_at");
            entity.Property<string>("DeviceId")
                .IsRequired()
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("device_id");
            entity.Property<string>("PayloadJson")
                .HasColumnType("TEXT")
                .HasColumnName("payload_json");
            entity.Property<DateTimeOffset?>("DeletedAtUtc")
                .HasColumnType("TEXT")
                .HasColumnName("deleted_at");
            entity.Property<string>("DeletedByDeviceId")
                .HasMaxLength(64)
                .HasColumnType("TEXT")
                .HasColumnName("deleted_by_device_id");

            entity.HasKey("ChangeLogId");
            entity.HasIndex("EntityType", "EntityId", "RowVersion").IsUnique();
            entity.ToTable("local_change_logs");
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
