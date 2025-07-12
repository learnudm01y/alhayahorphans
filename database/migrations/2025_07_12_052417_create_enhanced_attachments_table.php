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
        Schema::create('enhanced_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('original_file_name');
            $table->string('stored_file_path');
            $table->string('file_type', 50); // excel, pdf, word, etc.
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('record_number')->nullable();
            $table->string('person_identity_number')->nullable();
            $table->string('processing_status', 50)->default('uploaded'); // uploaded, processing, completed, failed
            $table->text('extracted_text')->nullable();
            $table->text('business_notes')->nullable();
            $table->json('tags')->nullable();
            $table->string('access_level', 50)->default('standard'); // standard, restricted, confidential
            $table->timestamp('upload_date')->nullable();
            $table->json('metadata')->nullable(); // Additional file metadata
            $table->timestamps();

            // Indexes for better performance
            $table->index('file_type');
            $table->index('record_number');
            $table->index('person_identity_number');
            $table->index('processing_status');
            $table->index('upload_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_attachments');
    }
};
