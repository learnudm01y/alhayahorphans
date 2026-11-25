using Alhayah.LocalServer.Data;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Alhayah.LocalServer.Migrations;

[DbContext(typeof(LocalDbContext))]
[Migration("20261125000100_LocalOperationsAndChangeFeed")]
public sealed class LocalOperationsAndChangeFeed : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.CreateTable(
            name: "aid_operations",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                concurrency_token = table.Column<Guid>(type: "TEXT", nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                last_modified_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                deleted_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: true),
                operation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                aid_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                entitlement_id = table.Column<Guid>(type: "TEXT", nullable: true),
                amount = table.Column<decimal>(type: "TEXT", nullable: false),
                currency_code = table.Column<string>(type: "TEXT", maxLength: 3, nullable: false),
                status = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                occurred_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                notes = table.Column<string>(type: "TEXT", nullable: true)
            },
            constraints: table => table.PrimaryKey("PK_aid_operations", item => item.id));

        migrationBuilder.CreateTable(
            name: "expenses",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                concurrency_token = table.Column<Guid>(type: "TEXT", nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                last_modified_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                deleted_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: true),
                operation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                category = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                supplier_id = table.Column<Guid>(type: "TEXT", nullable: true),
                amount = table.Column<decimal>(type: "TEXT", nullable: false),
                currency_code = table.Column<string>(type: "TEXT", maxLength: 3, nullable: false),
                incurred_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                description = table.Column<string>(type: "TEXT", nullable: true)
            },
            constraints: table => table.PrimaryKey("PK_expenses", item => item.id));

        migrationBuilder.CreateTable(
            name: "vouchers",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                concurrency_token = table.Column<Guid>(type: "TEXT", nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                last_modified_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                deleted_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: true),
                sequence_id = table.Column<Guid>(type: "TEXT", nullable: false),
                serial_number = table.Column<string>(type: "TEXT", maxLength: 100, nullable: true),
                disbursement_id = table.Column<Guid>(type: "TEXT", nullable: true),
                beneficiary_identity_number_snapshot = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                beneficiary_name_snapshot = table.Column<string>(type: "TEXT", maxLength: 400, nullable: false),
                beneficiary_phone_snapshot = table.Column<string>(type: "TEXT", maxLength: 100, nullable: true),
                amount = table.Column<decimal>(type: "TEXT", nullable: false),
                currency_code = table.Column<string>(type: "TEXT", maxLength: 3, nullable: false),
                supplier_id = table.Column<Guid>(type: "TEXT", nullable: false),
                status = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                issued_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                printed_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                redeemed_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                expires_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                void_reason = table.Column<string>(type: "TEXT", nullable: true)
            },
            constraints: table => table.PrimaryKey("PK_vouchers", item => item.id));

        migrationBuilder.CreateTable(
            name: "voucher_deliveries",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                concurrency_token = table.Column<Guid>(type: "TEXT", nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                last_modified_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                deleted_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: true),
                voucher_id = table.Column<Guid>(type: "TEXT", nullable: false),
                operation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                delivered_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                delivery_status = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                notes = table.Column<string>(type: "TEXT", nullable: true)
            },
            constraints: table => table.PrimaryKey("PK_voucher_deliveries", item => item.id));

        migrationBuilder.CreateTable(
            name: "voucher_redemptions",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                concurrency_token = table.Column<Guid>(type: "TEXT", nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                last_modified_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                deleted_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: true),
                voucher_id = table.Column<Guid>(type: "TEXT", nullable: false),
                operation_id = table.Column<Guid>(type: "TEXT", nullable: false),
                redeemed_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                amount = table.Column<decimal>(type: "TEXT", nullable: false),
                currency_code = table.Column<string>(type: "TEXT", maxLength: 3, nullable: false),
                redemption_reference = table.Column<string>(type: "TEXT", maxLength: 200, nullable: true)
            },
            constraints: table => table.PrimaryKey("PK_voucher_redemptions", item => item.id));

        migrationBuilder.CreateTable(
            name: "local_change_logs",
            columns: table => new
            {
                change_log_id = table.Column<long>(type: "INTEGER", nullable: false)
                    .Annotation("Sqlite:Autoincrement", true),
                entity_type = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                entity_id = table.Column<Guid>(type: "TEXT", nullable: false),
                operation_type = table.Column<string>(type: "TEXT", maxLength: 20, nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                concurrency_token = table.Column<Guid>(type: "TEXT", nullable: false),
                changed_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                payload_json = table.Column<string>(type: "TEXT", nullable: true),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                deleted_by_device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: true)
            },
            constraints: table => table.PrimaryKey("PK_local_change_logs", item => item.change_log_id));

        migrationBuilder.CreateIndex(
            name: "IX_aid_operations_operation_id",
            table: "aid_operations",
            column: "operation_id",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_expenses_operation_id",
            table: "expenses",
            column: "operation_id",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_vouchers_sequence_id",
            table: "vouchers",
            column: "sequence_id");

        migrationBuilder.CreateIndex(
            name: "IX_vouchers_serial_number",
            table: "vouchers",
            column: "serial_number",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_vouchers_supplier_id",
            table: "vouchers",
            column: "supplier_id");

        migrationBuilder.CreateIndex(
            name: "IX_voucher_deliveries_operation_id",
            table: "voucher_deliveries",
            column: "operation_id",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_voucher_deliveries_voucher_id",
            table: "voucher_deliveries",
            column: "voucher_id");

        migrationBuilder.CreateIndex(
            name: "IX_voucher_redemptions_operation_id",
            table: "voucher_redemptions",
            column: "operation_id",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_voucher_redemptions_voucher_id",
            table: "voucher_redemptions",
            column: "voucher_id");

        migrationBuilder.CreateIndex(
            name: "IX_local_change_logs_entity_type_entity_id_row_version",
            table: "local_change_logs",
            columns: new[] { "entity_type", "entity_id", "row_version" },
            unique: true);
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropTable(name: "local_change_logs");
        migrationBuilder.DropTable(name: "voucher_redemptions");
        migrationBuilder.DropTable(name: "voucher_deliveries");
        migrationBuilder.DropTable(name: "vouchers");
        migrationBuilder.DropTable(name: "expenses");
        migrationBuilder.DropTable(name: "aid_operations");
    }
}
