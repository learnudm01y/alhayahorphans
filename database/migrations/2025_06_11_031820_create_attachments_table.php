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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // علاقة متعددة الأشكال
            $table->unsignedBigInteger('attachable_id');
            $table->string('attachable_type');

            // نوع الوثيقة
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->foreign('document_type_id')
                ->references('id')
                ->on('document_types')
                ->nullOnDelete();

            $table->string('file_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
