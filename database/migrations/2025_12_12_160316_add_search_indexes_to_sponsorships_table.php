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
        Schema::table('sponsorships', function (Blueprint $table) {
            // إضافة indexes لتحسين أداء البحث
            $table->index('internal_file_number', 'idx_internal_file_number');
            $table->index('external_file_number', 'idx_external_file_number');
            $table->index('relation_id_number', 'idx_relation_id_number');
            $table->index('identity_number', 'idx_identity_number');
            $table->index('sponsorship_status_id', 'idx_sponsorship_status_id');
            $table->index('sponsorship_type_id', 'idx_sponsorship_type_id');
            $table->index('sponsoring_organization', 'idx_sponsoring_organization');

            // Composite indexes للبحث المركب
            $table->index(['sponsorship_status_id', 'sponsorship_type_id'], 'idx_status_type');
            $table->index(['created_at', 'sponsorship_status_id'], 'idx_created_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->dropIndex('idx_internal_file_number');
            $table->dropIndex('idx_external_file_number');
            $table->dropIndex('idx_relation_id_number');
            $table->dropIndex('idx_identity_number');
            $table->dropIndex('idx_sponsorship_status_id');
            $table->dropIndex('idx_sponsorship_type_id');
            $table->dropIndex('idx_sponsoring_organization');
            $table->dropIndex('idx_status_type');
            $table->dropIndex('idx_created_status');
        });
    }
};
