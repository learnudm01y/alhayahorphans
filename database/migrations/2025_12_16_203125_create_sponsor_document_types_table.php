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
        Schema::create('sponsor_document_types', function (Blueprint $table) {
            $table->id();

            // العلاقة مع الجمعية
            $table->unsignedBigInteger('sponsor_id');
            $table->foreign('sponsor_id')
                  ->references('id')
                  ->on('sponsors')
                  ->onDelete('cascade');

            // العلاقة مع نوع الوثيقة
            $table->unsignedBigInteger('document_type_id');
            $table->foreign('document_type_id')
                  ->references('id')
                  ->on('document_types')
                  ->onDelete('cascade');

            // هل الوثيقة مفعلة لهذه الجمعية
            $table->boolean('is_enabled')->default(true);

            // تفعيل للأقسام المختلفة
            $table->boolean('basic_enabled')->default(false)->comment('مفعل للبيانات الأساسية');
            $table->boolean('family_enabled')->default(false)->comment('مفعل لأفراد الأسرة');
            $table->boolean('deceased_enabled')->default(false)->comment('مفعل للمتوفين');

            // هل الوثيقة إلزامية
            $table->boolean('is_required')->default(false);

            // ملاحظات إضافية
            $table->text('notes')->nullable();

            $table->timestamps();

            // فهرس فريد لمنع التكرار
            $table->unique(['sponsor_id', 'document_type_id'], 'sponsor_document_unique');

            // فهارس للأداء
            $table->index('sponsor_id');
            $table->index('document_type_id');
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsor_document_types');
    }
};
