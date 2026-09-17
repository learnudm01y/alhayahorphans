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
        if (Schema::hasTable('document_types') && !Schema::hasColumn('document_types', 'file_type')) {
            Schema::table('document_types', function (Blueprint $table) {
                $table->string('file_type', 20)->default('document')->after('pref');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('document_types') && Schema::hasColumn('document_types', 'file_type')) {
            Schema::table('document_types', function (Blueprint $table) {
                $table->dropColumn('file_type');
            });
        }
    }
};
