<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create file_id_registry table
 *
 * Purpose: Central registry for all generated file IDs with handshake verification
 * Features:
 * - Unique file ID generation tracking
 * - Handshake token verification
 * - Status lifecycle (reserved → active → cancelled)
 * - Audit trail for all file ID operations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_id_registry', function (Blueprint $table) {
            $table->id();

            // File ID (unique across all tables)
            $table->string('file_id', 20)->unique();

            // Target table and person type
            $table->enum('table_name', ['data', 're_people', 'dead_people']);
            $table->enum('person_type', ['guardian', 'orphan', 'deceased']);

            // Person identification
            $table->string('identity_number', 50)->nullable()->index();
            $table->string('person_name')->nullable();

            // Handshake verification
            $table->string('handshake_token')->index();
            $table->string('device_id', 100)->nullable();

            // Status tracking
            $table->enum('status', ['reserved', 'active', 'cancelled'])->default('reserved')->index();

            // Timestamps
            $table->timestamps();
            $table->timestamp('activated_at')->nullable();

            // Indexes
            $table->index(['person_type', 'created_at']);
            $table->index(['table_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('file_id_registry');
    }
};
