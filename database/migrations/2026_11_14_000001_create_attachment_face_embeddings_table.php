<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attachments') || Schema::hasTable('attachment_face_embeddings')) {
            return;
        }

        Schema::create('attachment_face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->unique()->constrained('attachments')->cascadeOnDelete();
            $table->string('model_version', 80);
            $table->string('status', 24)->default('pending')->index();
            $table->json('embedding')->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->decimal('detection_score', 7, 4)->nullable();
            $table->unsignedSmallInteger('face_count')->nullable();
            $table->json('face_box')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_face_embeddings');
    }
};
