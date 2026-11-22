<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attachments', 'document_enhancement_status')) {
            Schema::table('attachments', function (Blueprint $table): void {
                $table->string('document_enhancement_status', 20)->nullable();
            });
        }
        if (!Schema::hasColumn('attachments', 'document_enhancement_metadata')) {
            Schema::table('attachments', function (Blueprint $table): void {
                $table->json('document_enhancement_metadata')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['document_enhancement_status', 'document_enhancement_metadata'] as $column) {
            if (Schema::hasColumn('attachments', $column)) {
                Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
