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
                $table->unsignedBigInteger('file_size')->nullable()->after('folder_id');
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
