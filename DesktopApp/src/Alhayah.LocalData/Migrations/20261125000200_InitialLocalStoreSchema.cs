using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace Alhayah.LocalData.Migrations;

public sealed partial class InitialLocalStoreSchema : Migration
{
    protected override void Up(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.Sql("""
            CREATE TABLE "cached_entities" (
                "client_uuid" TEXT NOT NULL CONSTRAINT "PK_cached_entities" PRIMARY KEY,
                "source_id" TEXT NOT NULL,
                "entity_type" TEXT NOT NULL,
                "canonical_id" TEXT NULL,
                "payload_json" TEXT NOT NULL,
                "search_text_normalized" TEXT NOT NULL,
                "base_version" TEXT NULL,
                "row_version" TEXT NULL,
                "created_at" TEXT NOT NULL,
                "updated_at" TEXT NOT NULL,
                "deleted_at" TEXT NULL,
                "deleted_by_device_id" TEXT NULL
            );

            CREATE TABLE "client_identity_map" (
                "client_uuid" TEXT NOT NULL CONSTRAINT "PK_client_identity_map" PRIMARY KEY,
                "entity_type" TEXT NOT NULL,
                "canonical_id" TEXT NULL,
                "created_at" TEXT NOT NULL,
                "resolved_at" TEXT NULL
            );

            CREATE TABLE "local_users" (
                "id" TEXT NOT NULL CONSTRAINT "PK_local_users" PRIMARY KEY,
                "user_name_normalized" TEXT NOT NULL,
                "display_name" TEXT NOT NULL,
                "password_hash" BLOB NOT NULL,
                "password_salt" BLOB NOT NULL,
                "argon2_memory_kib" INTEGER NOT NULL,
                "argon2_iterations" INTEGER NOT NULL,
                "argon2_parallelism" INTEGER NOT NULL,
                "permission_manifest_json" TEXT NOT NULL,
                "manifest_signature" BLOB NOT NULL,
                "manifest_version" INTEGER NOT NULL,
                "manifest_issued_at" TEXT NOT NULL,
                "created_at" TEXT NOT NULL,
                "disabled_at" TEXT NULL
            );

            CREATE TABLE "sync_checkpoints" (
                "source_id" TEXT NOT NULL,
                "channel" TEXT NOT NULL,
                "cursor" TEXT NULL,
                "last_committed_change_id" INTEGER NULL,
                "reconciliation_required" INTEGER NOT NULL,
                "updated_at" TEXT NOT NULL,
                CONSTRAINT "PK_sync_checkpoints" PRIMARY KEY ("source_id", "channel")
            );

            CREATE TABLE "sync_conflicts" (
                "id" TEXT NOT NULL CONSTRAINT "PK_sync_conflicts" PRIMARY KEY,
                "operation_id" TEXT NOT NULL,
                "source_id" TEXT NOT NULL,
                "entity_type" TEXT NOT NULL,
                "client_uuid" TEXT NOT NULL,
                "conflict_class" TEXT NOT NULL,
                "envelope_json" TEXT NOT NULL,
                "status" TEXT NOT NULL,
                "created_at" TEXT NOT NULL,
                "resolved_at" TEXT NULL,
                "resolved_by_user_id" TEXT NULL
            );

            CREATE TABLE "outbox_operations" (
                "operation_id" TEXT NOT NULL CONSTRAINT "PK_outbox_operations" PRIMARY KEY,
                "source_id" TEXT NOT NULL,
                "device_id" TEXT NOT NULL,
                "entity_type" TEXT NOT NULL,
                "client_uuid" TEXT NOT NULL,
                "operation_type" TEXT NOT NULL,
                "envelope_json" TEXT NOT NULL,
                "status" TEXT NOT NULL,
                "attempt_count" INTEGER NOT NULL,
                "created_at" TEXT NOT NULL,
                "last_attempt_at" TEXT NULL,
                CONSTRAINT "FK_outbox_operations_cached_entities_client_uuid" FOREIGN KEY ("client_uuid") REFERENCES "cached_entities" ("client_uuid") ON DELETE RESTRICT
            );

            CREATE TABLE "local_change_log" (
                "change_id" INTEGER NOT NULL CONSTRAINT "PK_local_change_log" PRIMARY KEY AUTOINCREMENT,
                "operation_id" TEXT NOT NULL,
                "source_id" TEXT NOT NULL,
                "entity_type" TEXT NOT NULL,
                "client_uuid" TEXT NOT NULL,
                "operation_type" TEXT NOT NULL,
                "row_version" TEXT NULL,
                "payload_json" TEXT NOT NULL,
                "device_id" TEXT NOT NULL,
                "occurred_at" TEXT NOT NULL
            );

            CREATE TABLE "local_sessions" (
                "id" TEXT NOT NULL CONSTRAINT "PK_local_sessions" PRIMARY KEY,
                "user_id" TEXT NOT NULL,
                "device_id" TEXT NOT NULL,
                "session_proof_hash" BLOB NOT NULL,
                "manifest_version" INTEGER NOT NULL,
                "created_at" TEXT NOT NULL,
                "expires_at" TEXT NOT NULL,
                "last_seen_at" TEXT NOT NULL,
                "revoked_at" TEXT NULL,
                CONSTRAINT "FK_local_sessions_local_users_user_id" FOREIGN KEY ("user_id") REFERENCES "local_users" ("id") ON DELETE CASCADE
            );

            CREATE TABLE "audit_entries" (
                "audit_id" INTEGER NOT NULL CONSTRAINT "PK_audit_entries" PRIMARY KEY AUTOINCREMENT,
                "operation_id" TEXT NOT NULL,
                "offline_user_id" TEXT NULL,
                "device_id" TEXT NOT NULL,
                "source_id" TEXT NOT NULL,
                "permission_used" TEXT NOT NULL,
                "manifest_version" INTEGER NULL,
                "occurred_at" TEXT NOT NULL,
                "payload_sha256" TEXT NOT NULL,
                "result" TEXT NOT NULL
            );

            CREATE INDEX "IX_cached_entities_source_id_entity_type_search_text_normalized" ON "cached_entities" ("source_id", "entity_type", "search_text_normalized");
            CREATE UNIQUE INDEX "IX_cached_entities_source_id_entity_type_canonical_id" ON "cached_entities" ("source_id", "entity_type", "canonical_id") WHERE "canonical_id" IS NOT NULL;
            CREATE UNIQUE INDEX "IX_client_identity_map_entity_type_canonical_id" ON "client_identity_map" ("entity_type", "canonical_id") WHERE "canonical_id" IS NOT NULL;
            CREATE UNIQUE INDEX "IX_local_users_user_name_normalized" ON "local_users" ("user_name_normalized");
            CREATE INDEX "IX_outbox_operations_status_created_at" ON "outbox_operations" ("status", "created_at");
            CREATE INDEX "IX_outbox_operations_client_uuid" ON "outbox_operations" ("client_uuid");
            CREATE UNIQUE INDEX "IX_local_change_log_operation_id" ON "local_change_log" ("operation_id");
            CREATE INDEX "IX_local_change_log_source_id_entity_type_client_uuid_change_id" ON "local_change_log" ("source_id", "entity_type", "client_uuid", "change_id");
            CREATE INDEX "IX_sync_conflicts_status_created_at" ON "sync_conflicts" ("status", "created_at");
            CREATE INDEX "IX_sync_conflicts_operation_id" ON "sync_conflicts" ("operation_id");
            CREATE INDEX "IX_local_sessions_user_id_expires_at" ON "local_sessions" ("user_id", "expires_at");
            CREATE INDEX "IX_local_sessions_user_id" ON "local_sessions" ("user_id");
            CREATE INDEX "IX_audit_entries_operation_id_audit_id" ON "audit_entries" ("operation_id", "audit_id");
            """);
    }

    protected override void Down(MigrationBuilder migrationBuilder)
    {
        migrationBuilder.Sql("""
            DROP TABLE "audit_entries";
            DROP TABLE "local_sessions";
            DROP TABLE "local_change_log";
            DROP TABLE "outbox_operations";
            DROP TABLE "sync_conflicts";
            DROP TABLE "sync_checkpoints";
            DROP TABLE "local_users";
            DROP TABLE "client_identity_map";
            DROP TABLE "cached_entities";
            """);
    }
}
