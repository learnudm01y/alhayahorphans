<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enhanced_attachments', function (Blueprint $table) {
            // Change document_status from enum to varchar to allow longer values
            DB::statement("ALTER TABLE enhanced_attachments MODIFY COLUMN document_status VARCHAR(50) DEFAULT 'active'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enhanced_attachments', function (Blueprint $table) {
            // Revert back to enum if needed
            DB::statement("ALTER TABLE enhanced_attachments MODIFY COLUMN document_status ENUM('active', 'archived', 'deleted', 'pending') DEFAULT 'active'");
        });
    }
};
