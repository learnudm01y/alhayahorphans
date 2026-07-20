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
        Schema::table('server_sync_actions', function (Blueprint $table) {
            $table->enum('delivery_status', ['pending', 'delivered_to_app', 'executed_in_app', 'failed'])->default('pending')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_sync_actions', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });
    }
};
