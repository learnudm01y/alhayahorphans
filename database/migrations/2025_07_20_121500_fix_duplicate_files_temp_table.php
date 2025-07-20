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
        // Check if table exists first
        if (!Schema::hasTable('duplicate_files_temp')) {
            // Table doesn't exist, create it normally
            Schema::create('duplicate_files_temp', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 100);
                $table->string('original_name', 255);
                $table->string('duplicate_name', 255);
                $table->string('temp_path', 500);
                $table->string('original_folder', 100)->nullable();
                $table->string('target_folder', 100)->nullable();
                $table->string('existing_file_name', 255)->nullable();
                $table->timestamp('created_at');
                $table->timestamp('expires_at');

                // Indexes for performance
                $table->index('session_id');
                $table->index(['session_id', 'expires_at']);
                $table->index('expires_at');
            });
        } else {
            // Table exists, check if indexes exist before adding them

            // Check and add session_id index if not exists
            $indexes = DB::select("SHOW INDEX FROM duplicate_files_temp WHERE Key_name = 'duplicate_files_temp_session_id_index'");
            if (empty($indexes)) {
                Schema::table('duplicate_files_temp', function (Blueprint $table) {
                    $table->index('session_id');
                });
            }

            // Check and add expires_at index if not exists
            $indexes = DB::select("SHOW INDEX FROM duplicate_files_temp WHERE Key_name = 'duplicate_files_temp_expires_at_index'");
            if (empty($indexes)) {
                Schema::table('duplicate_files_temp', function (Blueprint $table) {
                    $table->index('expires_at');
                });
            }

            // Check and add composite index if not exists
            $indexes = DB::select("SHOW INDEX FROM duplicate_files_temp WHERE Key_name = 'duplicate_files_temp_session_id_expires_at_index'");
            if (empty($indexes)) {
                Schema::table('duplicate_files_temp', function (Blueprint $table) {
                    $table->index(['session_id', 'expires_at']);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_files_temp');
    }
};
