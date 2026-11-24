<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('desktop_devices_v1')) {
            Schema::create('desktop_devices_v1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('device_id', 64);
                $table->string('source_id', 191);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('device_label', 128)->nullable();
                $table->string('app_version', 32)->nullable();
                $table->unsignedBigInteger('personal_access_token_id')->nullable();
                $table->char('token_hash', 64)->nullable();
                $table->char('enrollment_code_hash', 64)->nullable();
                $table->timestamp('enrollment_code_expires_at')->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique('device_id', 'dsv1_devices_device_unique');
                $table->index(['source_id', 'status'], 'dsv1_devices_source_status_idx');
                $table->index('user_id', 'dsv1_devices_user_idx');
            });
        }

        if (!Schema::hasTable('desktop_change_log_v1')) {
            Schema::create('desktop_change_log_v1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('table_name', 128);
                $table->string('primary_key', 191);
                $table->string('operation', 16);
                $table->char('client_uuid', 36)->nullable();
                $table->string('row_version', 191)->nullable();
                $table->string('row_version_source', 32);
                $table->boolean('soft_delete')->default(false);
                $table->dateTime('changed_at', 6);
                $table->timestamp('created_at', 6)->useCurrent();

                $table->index(['table_name', 'primary_key'], 'dsv1_log_table_pk_idx');
                $table->index(['table_name', 'id'], 'dsv1_log_table_id_idx');
                $table->index('client_uuid', 'dsv1_log_client_idx');
                $table->index('created_at', 'dsv1_log_created_idx');
            });
        }

        if (!Schema::hasTable('desktop_tombstones_v1')) {
            Schema::create('desktop_tombstones_v1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('change_log_id');
                $table->string('table_name', 128);
                $table->string('primary_key', 191);
                $table->char('client_uuid', 36)->nullable();
                $table->string('row_version', 191)->nullable();
                $table->dateTime('deleted_at', 6);
                $table->string('deleted_by_device_id', 64)->nullable();
                $table->timestamp('created_at', 6)->useCurrent();

                $table->unique('change_log_id', 'dsv1_tombstone_change_unique');
                $table->index(['table_name', 'primary_key'], 'dsv1_tombstone_table_pk_idx');
                $table->index('client_uuid', 'dsv1_tombstone_client_idx');
                $table->index('deleted_at', 'dsv1_tombstone_deleted_idx');
            });
        }

        if (!Schema::hasTable('desktop_sync_state_v1')) {
            Schema::create('desktop_sync_state_v1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('device_id', 64);
                $table->string('source_id', 191);
                $table->string('channel', 64);
                $table->unsignedBigInteger('last_change_log_id')->default(0);
                $table->text('cursor')->nullable();
                $table->boolean('full_reconciliation_required')->default(false);
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();

                $table->unique(['device_id', 'source_id', 'channel'], 'dsv1_state_checkpoint_unique');
                $table->index('last_change_log_id', 'dsv1_state_change_idx');
            });
        }

        if (!Schema::hasTable('desktop_operations_v1')) {
            Schema::create('desktop_operations_v1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('operation_id', 36);
                $table->string('device_id', 64);
                $table->string('source_id', 191);
                $table->string('entity_type', 128);
                $table->string('primary_key', 191)->nullable();
                $table->char('client_uuid', 36)->nullable();
                $table->string('operation_type', 32);
                $table->json('payload_json')->nullable();
                $table->unsignedBigInteger('base_version')->nullable();
                $table->string('row_version', 191)->nullable();
                $table->string('status', 32)->default('received');
                $table->json('response_json')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique('operation_id', 'dsv1_operations_operation_unique');
                $table->index(['device_id', 'status'], 'dsv1_operations_device_status_idx');
                $table->index(['entity_type', 'client_uuid'], 'dsv1_operations_entity_client_idx');
            });
        }

        if (!Schema::hasTable('desktop_conflicts_v1')) {
            Schema::create('desktop_conflicts_v1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('conflict_uuid', 36);
                $table->char('operation_id', 36)->nullable();
                $table->string('device_id', 64);
                $table->string('source_id', 191);
                $table->string('entity_type', 128);
                $table->string('primary_key', 191)->nullable();
                $table->char('client_uuid', 36)->nullable();
                $table->string('conflict_type', 64);
                $table->json('base_payload_json')->nullable();
                $table->json('local_payload_json')->nullable();
                $table->json('remote_payload_json')->nullable();
                $table->json('conflicting_fields_json')->nullable();
                $table->string('status', 32)->default('open');
                $table->json('resolution_payload_json')->nullable();
                $table->unsignedBigInteger('resolved_by_user_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->unique('conflict_uuid', 'dsv1_conflicts_uuid_unique');
                $table->index('operation_id', 'dsv1_conflicts_operation_idx');
                $table->index(['entity_type', 'client_uuid'], 'dsv1_conflicts_entity_client_idx');
                $table->index('status', 'dsv1_conflicts_status_idx');
            });
        }

        if (!Schema::hasTable('desktop_attachments_v1')) {
            Schema::create('desktop_attachments_v1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('attachment_uuid', 36);
                $table->char('operation_id', 36)->nullable();
                $table->string('device_id', 64);
                $table->string('source_id', 191);
                $table->string('entity_type', 128);
                $table->string('primary_key', 191)->nullable();
                $table->char('client_uuid', 36)->nullable();
                $table->string('attachment_reference_kind', 64)->nullable();
                $table->string('canonical_attachment_id', 191)->nullable();
                $table->string('original_file_name', 255)->nullable();
                $table->string('mime_type', 191)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('sha256', 64)->nullable();
                $table->text('storage_path')->nullable();
                $table->string('status', 32)->default('pending');
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->unique('attachment_uuid', 'dsv1_attachments_uuid_unique');
                $table->index('operation_id', 'dsv1_attachments_operation_idx');
                $table->index(['entity_type', 'client_uuid'], 'dsv1_attachments_entity_client_idx');
                $table->index(['device_id', 'status'], 'dsv1_attachments_device_status_idx');
                $table->index('sha256', 'dsv1_attachments_hash_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_attachments_v1');
        Schema::dropIfExists('desktop_conflicts_v1');
        Schema::dropIfExists('desktop_operations_v1');
        Schema::dropIfExists('desktop_sync_state_v1');
        Schema::dropIfExists('desktop_tombstones_v1');
        Schema::dropIfExists('desktop_change_log_v1');
        Schema::dropIfExists('desktop_devices_v1');
    }
};
