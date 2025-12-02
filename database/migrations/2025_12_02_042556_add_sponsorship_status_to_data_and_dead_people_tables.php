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
        // Add sponsorship_status to data table
        Schema::table('data', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsorship_status')->nullable()->after('data_request_status');
            $table->foreign('sponsorship_status')->references('id')->on('sponsorship_statuses')->onDelete('no action')->onUpdate('no action');
        });

        // Add sponsorship_status to dead_people table
        Schema::table('dead_people', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsorship_status')->nullable()->after('id');
            $table->foreign('sponsorship_status')->references('id')->on('sponsorship_statuses')->onDelete('no action')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropForeign(['sponsorship_status']);
            $table->dropColumn('sponsorship_status');
        });

        Schema::table('dead_people', function (Blueprint $table) {
            $table->dropForeign(['sponsorship_status']);
            $table->dropColumn('sponsorship_status');
        });
    }
};
