<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * إضافة فهارس B-Tree لتسريع البحث في جدول relations
     */
    public function up(): void
    {
        if (!Schema::connection('civilregistry')->hasTable('relations')) {
            return;
        }

        $existing = collect(DB::connection('civilregistry')->select('SHOW INDEX FROM relations'))
            ->pluck('Key_name')
            ->all();

        Schema::connection('civilregistry')->table('relations', function (Blueprint $table) use ($existing) {
            if (!in_array('idx_cf_id_num', $existing, true)) {
                $table->index('CF_ID_NUM', 'idx_cf_id_num');
            }

            if (!in_array('idx_cf_id_relative', $existing, true)) {
                $table->index('CF_ID_RELATIVE', 'idx_cf_id_relative');
            }

            if (!in_array('idx_cf_relative_cd', $existing, true)) {
                $table->index('CF_RELATIVE_CD', 'idx_cf_relative_cd');
            }

            if (!in_array('idx_cf_id_num_relative_cd', $existing, true)) {
                $table->index(['CF_ID_NUM', 'CF_RELATIVE_CD'], 'idx_cf_id_num_relative_cd');
            }

            if (!in_array('idx_cf_id_relative_relative_cd', $existing, true)) {
                $table->index(['CF_ID_RELATIVE', 'CF_RELATIVE_CD'], 'idx_cf_id_relative_relative_cd');
            }
        });

        try {
            DB::connection('civilregistry')->statement('ANALYZE TABLE relations');
        } catch (\Exception $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::connection('civilregistry')->hasTable('relations')) {
            return;
        }

        $existing = collect(DB::connection('civilregistry')->select('SHOW INDEX FROM relations'))
            ->pluck('Key_name')
            ->all();

        Schema::connection('civilregistry')->table('relations', function (Blueprint $table) use ($existing) {
            if (in_array('idx_cf_id_num', $existing, true)) {
                $table->dropIndex('idx_cf_id_num');
            }
            if (in_array('idx_cf_id_relative', $existing, true)) {
                $table->dropIndex('idx_cf_id_relative');
            }
            if (in_array('idx_cf_relative_cd', $existing, true)) {
                $table->dropIndex('idx_cf_relative_cd');
            }
            if (in_array('idx_cf_id_num_relative_cd', $existing, true)) {
                $table->dropIndex('idx_cf_id_num_relative_cd');
            }
            if (in_array('idx_cf_id_relative_relative_cd', $existing, true)) {
                $table->dropIndex('idx_cf_id_relative_relative_cd');
            }
        });
    }
};
