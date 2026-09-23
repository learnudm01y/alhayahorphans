<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * database/migrations/2026_09_23_000003_create_record_audit_log_v4_table.php
 *
 * اعتماد صاحب المشروع 2026-09-23: الخيار (أ) — إضافة جدول سجل التدقيق الآن.
 *
 * جدول جديد مستقل بالكامل — لا يلمس أي جدول أو عمود قائم.
 * المرجع: Doc/08_NEW_SCREENS_CATALOG.md §3 + Doc/09_PHASE5_PENDING_STATUS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('record_audit_log_v4')) {
            return;
        }

        Schema::create('record_audit_log_v4', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64);
            $table->char('client_uuid', 36);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('operation', 32); // create | update | blocked_sensitive | delete
            $table->string('field_name', 128)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->string('changed_by_device', 64)->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->char('idempotency_key', 64)->nullable();
            $table->string('source', 32)->default('app_v4');
            $table->dateTime('changed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_uuid', 'changed_at'], 'ral_client_time_idx');
            $table->index(['entity_type', 'record_id'], 'ral_entity_record_idx');
            $table->index(['changed_by_device', 'changed_at'], 'ral_device_time_idx');
            $table->index(['field_name', 'is_sensitive'], 'ral_field_sens_idx');
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_audit_log_v4');
    }
};
