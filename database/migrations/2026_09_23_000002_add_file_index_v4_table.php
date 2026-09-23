<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * database/migrations/2026_09_23_000002_add_file_index_v4_table.php
 *
 * android-v4 — Doc/03 §2: file_index_v4 (فهرس كل المرفقات بدون تحميل الملفات).
 * مصدر البيانات: file_id_registry + attachments (الموجودين أصلاً — لا يُمسان).
 *
 * لا حذف، لا إعادة تسمية، لا تعديل على أي جدول/عمود موجود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('file_index_v4')) {
            return;
        }

        Schema::create('file_index_v4', function (Blueprint $table) {
            $table->id();
            $table->char('client_uuid', 36)->unique();
            $table->string('file_id', 64)->nullable()->index();
            $table->string('table_name', 64)->nullable()->index();
            $table->string('person_type', 32)->nullable();
            $table->string('identity_number', 50)->nullable()->index();
            $table->string('person_name')->nullable();
            $table->string('stored_file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type', 32)->nullable();
            $table->string('source', 32)->default('attachments')->comment('attachments|file_id_registry');
            $table->string('status', 32)->default('active')->index();
            $table->unsignedBigInteger('source_id')->nullable()->comment('PK in attachments or file_id_registry');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['table_name', 'status']);
        });

        // Backfill من attachments — batch مع حد أقصى للمحاولات (أمان ضد الحلقة اللانهائية)
        if (Schema::hasTable('attachments')) {
            $guard = 0;
            do {
                $affected = DB::insert("
                    INSERT INTO file_index_v4
                        (client_uuid, identity_number, stored_file_name, file_path, file_type,
                         source, source_id, status, last_synced_at, created_at, updated_at)
                    SELECT UUID(), a.person_identity_number, a.stored_file_name, a.file_path, a.file_type,
                           'attachments', a.id, 'active', a.updated_at, a.created_at, a.updated_at
                    FROM attachments a
                    LEFT JOIN file_index_v4 f
                        ON f.source = 'attachments' AND f.source_id = a.id
                    WHERE f.id IS NULL
                    LIMIT 5000
                ");
                $guard++;
            } while ($affected > 0 && $guard < 500);
        }

        // Backfill من file_id_registry — batch مع حد أقصى للمحاولات
        if (Schema::hasTable('file_id_registry')) {
            $guard = 0;
            do {
                $affected = DB::insert("
                    INSERT INTO file_index_v4
                        (client_uuid, file_id, table_name, person_type, identity_number, person_name,
                         source, source_id, status, last_synced_at, created_at, updated_at)
                    SELECT UUID(), r.file_id, r.table_name, r.person_type, r.identity_number, r.person_name,
                           'file_id_registry', r.id, r.status, r.updated_at, r.created_at, r.updated_at
                    FROM file_id_registry r
                    LEFT JOIN file_index_v4 f
                        ON f.source = 'file_id_registry' AND f.source_id = r.id
                    WHERE f.id IS NULL
                    LIMIT 5000
                ");
                $guard++;
            } while ($affected > 0 && $guard < 500);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('file_index_v4');
    }
};
