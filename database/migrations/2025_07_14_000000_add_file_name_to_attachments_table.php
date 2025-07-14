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
            if (!Schema::hasColumn('attachments', 'file_name')) {
                $table->string('file_name')->nullable()->after('stored_file_name')->index();
                $table->text('description')->nullable()->after('file_size');
                $table->timestamp('uploaded_at')->nullable()->after('description');
                $table->string('upload_source')->default('manual')->after('uploaded_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (Schema::hasColumn('attachments', 'file_name')) {
                $table->dropIndex(['file_name']);
                $table->dropColumn(['file_name', 'description', 'uploaded_at', 'upload_source']);
            }
        });
    }
};
