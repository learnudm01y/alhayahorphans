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
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            if (Schema::hasColumn('sponsor_field_settings', 'field_disease_type')) {
                $table->dropColumn('field_disease_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->boolean('field_disease_type')->default(1)->comment('نوع المرض او الإصابة ان وجدت');
        });
    }
};
