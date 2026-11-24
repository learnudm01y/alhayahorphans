using Alhayah.LocalServer.Data;
using Microsoft.EntityFrameworkCore.Infrastructure;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Alhayah.LocalServer.Migrations;

[DbContext(typeof(LocalDbContext))]
[Migration("20261124000200_DeviceRegistry")]
public sealed class DeviceRegistry : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.CreateTable(
            name: "device_registrations",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                device_id = table.Column<string>(type: "TEXT", maxLength: 64, nullable: false),
                credential_hash = table.Column<byte[]>(type: "BLOB", nullable: true),
                enrollment_code_hash = table.Column<byte[]>(type: "BLOB", nullable: true),
                enrollment_code_expires_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                credential_version = table.Column<int>(type: "INTEGER", nullable: false),
                permissions = table.Column<int>(type: "INTEGER", nullable: false),
                created_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                updated_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: false),
                last_seen_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true),
                revoked_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true)
            },
            constraints: table =>
            {
                table.PrimaryKey("PK_device_registrations", item => item.id);
            });

        migrationBuilder.CreateTable(
            name: "device_users",
            columns: table => new
            {
                id = table.Column<Guid>(type: "TEXT", nullable: false),
                device_registration_id = table.Column<Guid>(type: "TEXT", nullable: false),
                user_id = table.Column<string>(type: "TEXT", maxLength: 200, nullable: false),
                permissions = table.Column<int>(type: "INTEGER", nullable: false),
                revoked_at = table.Column<DateTimeOffset>(type: "TEXT", nullable: true)
            },
            constraints: table =>
            {
                table.PrimaryKey("PK_device_users", item => item.id);
                table.ForeignKey(
                    name: "FK_device_users_device_registrations_device_registration_id",
                    column: item => item.device_registration_id,
                    principalTable: "device_registrations",
                    principalColumn: "id",
                    onDelete: ReferentialAction.Cascade);
            });

        migrationBuilder.CreateIndex(
            name: "IX_device_registrations_device_id",
            table: "device_registrations",
            column: "device_id",
            unique: true);

        migrationBuilder.CreateIndex(
            name: "IX_device_users_device_registration_id_user_id",
            table: "device_users",
            columns: new[] { "device_registration_id", "user_id" },
            unique: true);
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.DropTable(name: "device_users");
        migrationBuilder.DropTable(name: "device_registrations");
    }
}
