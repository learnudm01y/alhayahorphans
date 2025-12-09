<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            // إضافة عمود relation_id_number (رقم الربط الداخلي المخفي)
            // هذا الرقم يُستخدم للربط في قاعدة البيانات ولا يُعرض للمستخدم
            if (!Schema::hasColumn('sponsorships', 'relation_id_number')) {
                $table->string('relation_id_number', 100)
                    ->nullable()
                    ->after('internal_file_number')
                    ->comment('رقم الربط الداخلي (مخفي - للاستخدام الداخلي فقط)');

                // إضافة فهرس لتحسين الأداء
                $table->index('relation_id_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            if (Schema::hasColumn('sponsorships', 'relation_id_number')) {
                $table->dropIndex(['relation_id_number']);
                $table->dropColumn('relation_id_number');
            }
        });
    }
};
