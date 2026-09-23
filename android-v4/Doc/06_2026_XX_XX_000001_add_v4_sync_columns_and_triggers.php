<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * database/migrations/2026_XX_XX_000001_add_v4_sync_columns_and_triggers.php
 *
 * migration جديدة بالكامل — لا تُعدَّل أي migration قديمة.
 * تُطبَّق فقط: ALTER TABLE ADD COLUMN (nullable) + CREATE TRIGGER + CREATE TABLE.
 * لا حذف، لا إعادة تسمية، لا تعديل على أي عمود/جدول موجود مسبقاً.
 */
return new class extends Migration
{
    /** الجداول القديمة التي تحصل على الأعمدة الثلاثة الجديدة فقط */
    private array $targetTables = [
        'data',
        're_people',
        'dead_pepoles',
        'additional_deceased',
        'guardian_bank_accounts',
        'sponsorships',
    ];

    public function up(): void
    {
        // ------------------------------------------------------------
        // 1) أعمدة جديدة Nullable على الجداول القديمة (بدون لمس أي عمود قائم)
        // ------------------------------------------------------------
        foreach ($this->targetTables as $table) {
            if (!Schema::hasColumn($table, 'client_uuid')) {
                DB::statement("ALTER TABLE `{$table}`
                    ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `id`,
                    ADD COLUMN `sync_origin_device_id` VARCHAR(64) NULL AFTER `client_uuid`,
                    ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_origin_device_id`
                ");
            }
        }

        // ------------------------------------------------------------
        // 2) Backfill لمرة واحدة (على دفعات لتفادي قفل طويل على جداول كبيرة)
        // ------------------------------------------------------------
        foreach ($this->targetTables as $table) {
            do {
                $affected = DB::update(
                    "UPDATE `{$table}` SET client_uuid = UUID() WHERE client_uuid IS NULL LIMIT 5000"
                );
            } while ($affected > 0);
        }

        // ------------------------------------------------------------
        // 3) Triggers — تغطية أي إدراج مستقبلي من أي مصدر (موقع/v3/v4)
        //    Laravel Schema Builder لا يدعم Triggers → SQL خام إلزامي هنا
        // ------------------------------------------------------------
        foreach ($this->targetTables as $table) {
            $triggerName = "trg_{$table}_v4_client_uuid";
            DB::unprepared("DROP TRIGGER IF EXISTS `{$triggerName}`");
            DB::unprepared("
                CREATE TRIGGER `{$triggerName}`
                BEFORE INSERT ON `{$table}`
                FOR EACH ROW
                BEGIN
                    IF NEW.client_uuid IS NULL THEN
                        SET NEW.client_uuid = UUID();
                    END IF;
                END
            ");
        }

        // ------------------------------------------------------------
        // 4) جداول جديدة بالكامل
        // ------------------------------------------------------------
        Schema::create('sync_outbox_v4', function ($table) {
            $table->id();
            $table->char('client_uuid', 36);
            $table->string('device_id', 64);
            $table->string('entity_type', 64);
            $table->string('operation_type', 32);
            $table->json('payload_json');
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->dateTime('created_at_device');
            $table->dateTime('server_received_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'status']);
            $table->index(['entity_type', 'client_uuid']);
        });

        Schema::create('sync_idempotency_log_v4', function ($table) {
            $table->id();
            $table->char('idempotency_key', 64)->unique();
            $table->json('response_json');
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('device_registry_v4', function ($table) {
            $table->id();
            $table->string('device_id', 64)->unique();
            $table->string('device_label', 128)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->integer('pending_ops_count')->default(0);
            $table->string('health_status', 20)->default('unknown');
            $table->timestamps();
        });

        Schema::create('conflict_review_queue_v4', function ($table) {
            $table->id();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('existing_record_id');
            $table->char('existing_record_uuid', 36)->nullable();
            $table->json('incoming_payload_json');
            $table->string('incoming_source', 20);
            $table->string('incoming_device_id', 64)->nullable();
            $table->string('match_reason', 255);
            $table->decimal('match_confidence', 4, 2)->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('status');
            $table->index(['entity_type', 'existing_record_id']);
        });

        Schema::create('admin_dashboard_snapshot_v4', function ($table) {
            $table->id();
            $table->string('snapshot_key', 64)->unique();
            $table->json('snapshot_value_json');
            $table->dateTime('generated_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('admin_reports_source_v4', function ($table) {
            $table->id();
            $table->string('report_type', 64);
            $table->json('row_data_json');
            $table->char('source_client_uuid', 36)->nullable();
            $table->dateTime('last_updated_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index('report_type');
        });

        Schema::create('permissions_manifest_v4', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->json('manifest_json');
            $table->string('signature', 255);
            $table->dateTime('issued_at');
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // ترتيب معكوس: الجداول الجديدة أولاً، ثم الـ Triggers، ثم الأعمدة
        Schema::dropIfExists('permissions_manifest_v4');
        Schema::dropIfExists('admin_reports_source_v4');
        Schema::dropIfExists('admin_dashboard_snapshot_v4');
        Schema::dropIfExists('conflict_review_queue_v4');
        Schema::dropIfExists('device_registry_v4');
        Schema::dropIfExists('sync_idempotency_log_v4');
        Schema::dropIfExists('sync_outbox_v4');

        foreach ($this->targetTables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS `trg_{$table}_v4_client_uuid`");
        }

        foreach ($this->targetTables as $table) {
            if (Schema::hasColumn($table, 'client_uuid')) {
                Schema::table($table, function ($t) {
                    $t->dropColumn(['client_uuid', 'sync_origin_device_id', 'needs_review']);
                });
            }
        }
    }
};
