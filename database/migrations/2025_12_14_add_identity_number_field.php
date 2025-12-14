<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            // إضافة حقل رقم الهوية بعد field_sponsor_name
            $table->boolean('field_identity_number')->default(0)->after('field_sponsor_name');
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->dropColumn('field_identity_number');
        });
    }
};
