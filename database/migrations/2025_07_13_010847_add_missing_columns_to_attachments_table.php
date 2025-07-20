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
        Schema::table('attachments', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('attachments', 'file_size')) {
                // Add file_size after file_type since folder_id doesn't exist in this table
                $table->unsignedBigInteger('file_size')->nullable()->after('file_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn([
                'file_size',
            ]);
        });
    }
};
