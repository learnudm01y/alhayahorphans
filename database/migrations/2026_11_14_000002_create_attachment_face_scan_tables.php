<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attachment_face_scans')) {
            Schema::create('attachment_face_scans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('requested_by')->index();
                $table->string('status', 24)->default('queued')->index();
                $table->string('person_identity_number')->nullable();
                $table->string('model_version', 80);
                $table->decimal('auto_threshold', 8, 6);
                $table->decimal('review_threshold', 8, 6);
                $table->unsignedInteger('total_groups')->default(0);
                $table->unsignedInteger('processed_groups')->default(0);
                $table->unsignedInteger('total_images')->default(0);
                $table->unsignedInteger('processed_images')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->unsignedInteger('review_count')->default(0);
                $table->string('last_error')->nullable();
                $table->timestamp('prepared_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('attachment_face_scan_groups')) {
            Schema::create('attachment_face_scan_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scan_id')->constrained('attachment_face_scans')->cascadeOnDelete();
                $table->string('group_hash', 40);
                $table->string('person_identity_number');
                $table->string('file_type');
                $table->string('status', 24)->default('pending');
                $table->unsignedInteger('image_count')->default(0);
                $table->unsignedInteger('processed_images')->default(0);
                $table->unsignedBigInteger('last_attachment_id')->default(0);
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamps();
                $table->unique(['scan_id', 'group_hash'], 'face_scan_group_unique');
                $table->index(['scan_id', 'status', 'id'], 'face_scan_group_work_index');
            });
        }

        if (!Schema::hasTable('attachment_face_results')) {
            Schema::create('attachment_face_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scan_id')->constrained('attachment_face_scans')->cascadeOnDelete();
                $table->string('decision', 24);
                $table->string('person_identity_number');
                $table->string('file_type');
                $table->unsignedBigInteger('attachment_id')->index();
                $table->unsignedBigInteger('matched_attachment_id')->index();
                $table->decimal('distance', 8, 6);
                $table->timestamps();
                $table->unique(
                    ['scan_id', 'decision', 'attachment_id', 'matched_attachment_id'],
                    'face_scan_result_unique',
                );
                $table->index(['scan_id', 'decision', 'id'], 'face_scan_result_decision_index');
            });
        }

        if (!Schema::hasTable('attachment_face_scan_events')) {
            Schema::create('attachment_face_scan_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scan_id')->constrained('attachment_face_scans')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event', 40);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['scan_id', 'event'], 'face_scan_event_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_face_scan_events');
        Schema::dropIfExists('attachment_face_results');
        Schema::dropIfExists('attachment_face_scan_groups');
        Schema::dropIfExists('attachment_face_scans');
    }
};
