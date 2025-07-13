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
            if (!Schema::hasColumn('attachments', 'person_identity_number')) {
                $table->string('person_identity_number')->nullable()->after('id');
            }
            if (!Schema::hasColumn('attachments', 'stored_file_name')) {
                $table->string('stored_file_name')->nullable()->after('person_identity_number');
            }
            if (!Schema::hasColumn('attachments', 'file_path')) {
                $table->string('file_path')->nullable()->after('stored_file_name');
            }
            if (!Schema::hasColumn('attachments', 'file_type')) {
                $table->string('file_type')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('attachments', 'folder_id')) {
                $table->string('folder_id')->nullable()->after('file_type');
            }
            if (!Schema::hasColumn('attachments', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('folder_id');
            }
            if (!Schema::hasColumn('attachments', 'original_name')) {
                $table->string('original_name')->nullable()->after('file_size');
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
                'person_identity_number',
                'stored_file_name', 
                'file_path',
                'file_type',
                'folder_id',
                'file_size',
                'original_name'
            ]);
        });
    }
};
