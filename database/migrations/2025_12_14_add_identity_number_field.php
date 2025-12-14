<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // إضافة حقل رقم الهوية
            if (!Schema::hasColumn('sponsor_field_settings', 'field_identity_number')) {
                $table->boolean('field_identity_number')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->dropColumn('field_identity_number');
        });
    }
};
