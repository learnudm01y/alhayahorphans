<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة بصمة محتوى الملف إلى جدول attachments + فهرس فريدة
     * يمنع نفس الشخص + نفس نوع الوثيقة + نفس المحتوى (منعاً لتكرار المرفقات).
     *
     * السجلات القديمة تبقى file_hash = NULL، وMySQL يسمح بقيم NULL متعددة
     * داخل الفهرس الفريد، فلا يحطم الفهرس أي بيانات موجودة.
     */
    public function up(): void
    {
        if (!Schema::hasTable('attachments')) {
            return;
        }

        if (!Schema::hasColumn('attachments', 'file_hash')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->string('file_hash', 40)->nullable();
            });
        }

        if (!$this->indexExists('uniq_person_type_hash')) {
            // أطوال البادئة تحافظ على حجم المفتاح ضمن حدود InnoDB مهما كان ترميز الجدول
            DB::statement(
                'ALTER TABLE `attachments` ADD UNIQUE `uniq_person_type_hash`'
                . ' (`person_identity_number`(64), `file_type`(16), `file_hash`)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('uniq_person_type_hash')) {
            DB::statement('ALTER TABLE `attachments` DROP INDEX `uniq_person_type_hash`');
        }

        if (Schema::hasTable('attachments') && Schema::hasColumn('attachments', 'file_hash')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->dropColumn('file_hash');
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        $rows = DB::select(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['attachments', $indexName]
        );

        return (int) ($rows[0]->c ?? 0) > 0;
    }
};
