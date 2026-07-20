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
        Schema::create('chunked_uploads', function (Blueprint $table) {
            $table->uuid('upload_id')->primary();
            $table->string('file_name');
            $table->bigInteger('total_size');
            $table->integer('total_chunks');
            $table->integer('received_chunks')->default(0);
            $table->bigInteger('sponsorship_id');
            $table->string('status')->default('in_progress'); // in_progress, completed, failed
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunked_uploads');
    }
};
