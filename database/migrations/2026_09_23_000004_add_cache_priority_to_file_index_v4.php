<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * database/migrations/2026_09_23_000004_add_cache_priority_to_file_index_v4.php
 *
 * android-v4 — Doc/11: الصور الشخصية محلياً دائماً / باقي الوثائق on-demand فقط.
 * عمود cache_priority + local_cache_path + cached_at على جدول v4 file_index_v4.
 *
 * لا حذف، لا إعادة تسمية، لا تعديل على أي جدول/عمود موجود — إضافة فقط على جدول v4.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('file_index_v4')) {
            return;
        }

        Schema::table('file_index_v4', function (Blueprint $table) {
            if (!Schema::hasColumn('file_index_v4', 'cache_priority')) {
                $table->string('cache_priority', 20)->default('on_demand')->after('status');
            }
            if (!Schema::hasColumn('file_index_v4', 'local_cache_path')) {
                $table->string('local_cache_path', 500)->nullable()->after('cache_priority');
            }
            if (!Schema::hasColumn('file_index_v4', 'cached_at')) {
                $table->dateTime('cached_at')->nullable()->after('local_cache_path');
            }
        });

        // تصنيف تلقائي للصور الشخصية المحفوظة فعلاً (لا نغيّر on_demand القديم)
        // sponsorships.orphan_photo_path / guardian_photo_path قراءة فقط.
        DB::statement("
            UPDATE file_index_v4 f
            LEFT JOIN sponsorships s
                ON f.table_name = 'sponsorships'
                AND f.source = 'attachments'
                AND (
                    (s.orphan_photo_path IS NOT NULL AND f.file_path = s.orphan_photo_path)
                    OR (s.guardian_photo_path IS NOT NULL AND f.file_path = s.guardian_photo_path)
                )
            SET f.cache_priority = 'profile_photo'
            WHERE (s.id IS NOT NULL)
              AND f.cache_priority = 'on_demand'
              AND f.local_cache_path IS NULL
        ");

        // fallback: أول صورة jpg/png/jpeg لكل هوية مرتبة بـ created_at تصاعدياً
        DB::statement("
            UPDATE file_index_v4 f
            SET f.cache_priority = 'profile_photo'
            WHERE f.file_type IN ('jpg','jpeg','png','image/jpeg','image/png')
              AND f.cache_priority = 'on_demand'
              AND f.local_cache_path IS NULL
              AND f.identity_number IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM file_index_v4 f2
                  WHERE f2.identity_number = f.identity_number
                    AND f2.file_type IN ('jpg','jpeg','png','image/jpeg','image/png')
                    AND (f2.created_at < f.created_at
                         OR (f2.created_at = f.created_at AND f2.id < f.id))
              )
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('file_index_v4')) {
            return;
        }
        Schema::table('file_index_v4', function (Blueprint $table) {
            if (Schema::hasColumn('file_index_v4', 'cache_priority')) {
                $table->dropColumn('cache_priority');
            }
            if (Schema::hasColumn('file_index_v4', 'local_cache_path')) {
                $table->dropColumn('local_cache_path');
            }
            if (Schema::hasColumn('file_index_v4', 'cached_at')) {
                $table->dropColumn('cached_at');
            }
        });
    }
};
