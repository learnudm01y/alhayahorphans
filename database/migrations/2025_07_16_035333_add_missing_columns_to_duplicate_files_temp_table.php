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
        Schema::table('duplicate_files_temp', function (Blueprint $table) {
            if (!Schema::hasColumn('duplicate_files_temp', 'existing_file_id')) {
                $table->unsignedBigInteger('existing_file_id')->nullable()->after('existing_file_name');
            }
            if (!Schema::hasColumn('duplicate_files_temp', 'file_size')) {
                $table->bigInteger('file_size')->nullable()->after('existing_file_id');
            }
            if (!Schema::hasColumn('duplicate_files_temp', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duplicate_files_temp', function (Blueprint $table) {
            $table->dropColumn(['existing_file_id', 'file_size', 'mime_type']);
        });
    }
};
