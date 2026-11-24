using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Alhayah.LocalServer.Migrations;

public sealed partial class InitialLocalSchema : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.CreateTable(
            name: "disbursements",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                transaction_number = table.Column<string>(type: "TEXT", maxLength: 100, nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                entitlement_id = table.Column<Guid>(type: "TEXT", nullable: false),
                batch_id = table.Column<Guid>(type: "TEXT", nullable: true),
                supplier_id = table.Column<Guid>(type: "TEXT", nullable: true),
                amount = table.Column<decimal>(type: "TEXT", nullable: true),
                quantity = table.Column<decimal>(type: "TEXT", nullable: true),
                currency_code = table.Column<string>(type: "TEXT", maxLength: 3, nullable: true),
                status = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                requested_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                delivered_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                recipient_type = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                recipient_name_snapshot = table.Column<string>(type: "TEXT", maxLength: 400, nullable: false),
                recipient_identity_number_snapshot = table.Column<string>(type: "TEXT", maxLength: 100, nullable: true),
                delivered_by_representative_id = table.Column<Guid>(type: "TEXT", nullable: true),
                verification_notes = table.Column<string>(type: "TEXT", nullable: true)
            },
            constraints: table =>
            {
                table.PrimaryKey("PK_disbursements", item => item.id);
            });

        migrationBuilder.CreateTable(
            name: "delivery_evidences",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                disbursement_id = table.Column<Guid>(type: "TEXT", nullable: false),
                row_version = table.Column<long>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                deleted_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                evidence_type = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                storage_path = table.Column<string>(type: "TEXT", nullable: false),
                sha256 = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                captured_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                captured_by_laravel_user_id = table.Column<long>(type: "INTEGER", nullable: false),
                signature_metadata_json = table.Column<string>(type: "TEXT", nullable: true),
                verification_status = table.Column<string>(type: "TEXT", maxLength: 50, nullable: false),
                notes = table.Column<string>(type: "TEXT", nullable: true)
            },
            constraints: table =>
            {
                table.PrimaryKey("PK_delivery_evidences", item => item.id);
                table.ForeignKey(
                    name: "FK_delivery_evidences_disbursements_disbursement_id",
                    column: item => item.disbursement_id,
                    principalTable: "disbursements",
                    principalColumn: "id",
                    onDelete: ReferentialAction.Cascade);
            });

        migrationBuilder.CreateIndex(
            name: "IX_delivery_evidences_disbursement_id",
            table: "delivery_evidences",
            column: "disbursement_id");

        migrationBuilder.CreateIndex(
            name: "IX_disbursements_transaction_number",
            table: "disbursements",
            column: "transaction_number",
            unique: true);
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropTable(name: "delivery_evidences");
        migrationBuilder.DropTable(name: "disbursements");
    }
}
