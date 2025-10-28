<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اتصال قاعدة البيانات المخصص للسجل المدني
     */
    protected $connection = 'civilregistry';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة indexes لجدول data للبحث السريع
        Schema::connection('civilregistry')->table('data', function (Blueprint $table) {
            // فهرس للبحث بالهوية (الأهم)
            if (!$this->indexExists('data', 'idx_data_identity_number')) {
                $table->index('identity_number', 'idx_data_identity_number');
            }

            // فهرس للبحث بالاسم الأول
            if (!$this->indexExists('data', 'idx_data_first_name')) {
                $table->index('first_name', 'idx_data_first_name');
            }

            // فهرس للبحث بالاسم الكامل
            if (!$this->indexExists('data', 'idx_data_full_name')) {
                $table->index(['first_name', 'father_name', 'grandfather_name', 'last_name'], 'idx_data_full_name');
            }

            // فهرس مركب للبحث السريع
            if (!$this->indexExists('data', 'idx_data_search_combo')) {
                $table->index(['identity_number', 'first_name'], 'idx_data_search_combo');
            }
        });

        // إضافة indexes لجدول attachments (إذا كان موجوداً في قاعدة البيانات الجديدة)
        if (Schema::connection('civilregistry')->hasTable('attachments')) {
            Schema::connection('civilregistry')->table('attachments', function (Blueprint $table) {
                // فهرس للبحث برقم الهوية
                if (!$this->indexExists('attachments', 'idx_attachments_identity')) {
                    $table->index('person_identity_number', 'idx_attachments_identity');
                }

                // فهرس لنوع الملف
                if (!$this->indexExists('attachments', 'idx_attachments_file_type')) {
                    $table->index('file_type', 'idx_attachments_file_type');
                }

                // فهرس مركب للبحث السريع
                if (!$this->indexExists('attachments', 'idx_attachments_combo')) {
                    $table->index(['person_identity_number', 'file_type'], 'idx_attachments_combo');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف indexes من جدول data
        Schema::connection('civilregistry')->table('data', function (Blueprint $table) {
            $table->dropIndex('idx_data_identity_number');
            $table->dropIndex('idx_data_first_name');
            $table->dropIndex('idx_data_full_name');
            $table->dropIndex('idx_data_search_combo');
        });

        // حذف indexes من جدول attachments
        if (Schema::connection('civilregistry')->hasTable('attachments')) {
            Schema::connection('civilregistry')->table('attachments', function (Blueprint $table) {
                $table->dropIndex('idx_attachments_identity');
                $table->dropIndex('idx_attachments_file_type');
                $table->dropIndex('idx_attachments_combo');
            });
        }
    }

    /**
     * التحقق من وجود فهرس مسبقاً
     */
    private function indexExists($table, $indexName): bool
    {
        try {
            $indexes = Schema::connection('civilregistry')->getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes($table);

            return isset($indexes[$indexName]);
        } catch (\Exception $e) {
            return false;
        }
    }
};
