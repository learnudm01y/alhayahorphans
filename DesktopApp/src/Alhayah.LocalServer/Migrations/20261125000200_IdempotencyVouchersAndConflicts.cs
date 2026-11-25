using Alhayah.LocalServer.Data;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Alhayah.LocalServer.Migrations;

[DbContext(typeof(LocalDbContext))]
[Migration("20261125000200_IdempotencyVouchersAndConflicts")]
public sealed class IdempotencyVouchersAndConflicts : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.CreateTable(
            name: "idempotency_records",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                operation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                idempotency_key = table.Column<string>(type: "TEXT", maxLength: 200, nullable: true),
                device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                operation_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                request_hash = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                request_json = table.Column<string>(type: "TEXT", nullable: false),
                status = table.Column<string>(type: "TEXT", maxLength: 20, nullable: false),
                result_json = table.Column<string>(type: "TEXT", nullable: true),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                expires_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false)
            },
            constraints: table => table.PrimaryKey("PK_idempotency_records", item => item.id));

        migrationBuilder.CreateTable(
            name: "client_identity_mappings",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                entity_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                client_uuid = table.Column<Guid>(type: "TEXT", nullable: false),
                canonical_id = table.Column<string>(type: "TEXT", maxLength: 200, nullable: true),
                is_deleted = table.Column<bool>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false)
            },
            constraints: table => table.PrimaryKey("PK_client_identity_mappings", item => item.id));

        migrationBuilder.CreateTable(
            name: "critical_conflicts",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                operation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                entity_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                entity_id = table.Column<Guid>(type: "TEXT", nullable: false),
                conflict_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                details_json = table.Column<string>(type: "TEXT", nullable: false),
                decision = table.Column<string>(type: "TEXT", maxLength: 20, nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                decision_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                decision_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: true)
            },
            constraints: table => table.PrimaryKey("PK_critical_conflicts", item => item.id));

        migrationBuilder.CreateTable(
            name: "voucher_number_blocks",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                sequence_id = table.Column<Guid>(type: "TEXT", nullable: false),
                device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                first_number = table.Column<long>(type: "INTEGER", nullable: false),
                last_number = table.Column<long>(type: "INTEGER", nullable: false),
                next_number = table.Column<long>(type: "INTEGER", nullable: false)
            },
            constraints: table => table.PrimaryKey("PK_voucher_number_blocks", item => item.id));

        migrationBuilder.CreateIndex(
            name: "IX_idempotency_records_operation_id",
            table: "idempotency_records",
            column: "operation_id",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_idempotency_records_device_id_idempotency_key",
            table: "idempotency_records",
            columns: new[] { "device_id", "idempotency_key" },
            unique: true,
            filter: "[idempotency_key] IS NOT NULL");

        migrationBuilder.CreateIndex(
            name: "IX_client_identity_mappings_client_uuid",
            table: "client_identity_mappings",
            column: "client_uuid",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_client_identity_mappings_entity_type_canonical_id",
            table: "client_identity_mappings",
            columns: new[] { "entity_type", "canonical_id" },
            unique: true,
            filter: "[canonical_id] IS NOT NULL");

        migrationBuilder.CreateIndex(
            name: "IX_critical_conflicts_operation_id",
            table: "critical_conflicts",
            column: "operation_id");

        migrationBuilder.CreateIndex(
            name: "IX_critical_conflicts_entity_type_entity_id",
            table: "critical_conflicts",
            columns: new[] { "entity_type", "entity_id" });

        migrationBuilder.CreateIndex(
            name: "IX_voucher_number_blocks_sequence_id_device_id",
            table: "voucher_number_blocks",
            columns: new[] { "sequence_id", "device_id" },
            unique: true);
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropTable(name: "voucher_number_blocks");
        migrationBuilder.DropTable(name: "critical_conflicts");
        migrationBuilder.DropTable(name: "client_identity_mappings");
        migrationBuilder.DropTable(name: "idempotency_records");
    }
}
