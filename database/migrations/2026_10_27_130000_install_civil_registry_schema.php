<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * تثبيت بنية قاعدة بيانات السجل المدني (civilregistry) محلياً.
 *
 * المشكلة السابقة: migration الجدول الأساسي (2014_10_12) كان ينشئ persons
 * على القاعدة الافتراضية بدل اتصال civilregistry، فبقي الجدول مفقوداً.
 *
 * هذا الـ migration ينشئ ما يلزم على الاتصال الصحيح بشكل آمن ومتكرر التشغيل:
 *  1) جداول البحث المرجعية (city, ci_birth_cd, ci_birth_tb_cd, ci_personal_cd)
 *  2) جدول persons بالبنية الكاملة
 *  3) الأعمدة المولّعة *_NORMALIZED (تستخدمها NormalizedSearchService)
 *  4) الفهارس العادية + فهرس FULLTEXT (يستخدمه CustomScoutSearchService)
 */
return new class extends Migration
{
    private const CIVIL = 'civilregistry';

    /** جداول مرجعية تُنسخ بنيتها وبياناتها من القاعدة الرئيسية */
    private const REFERENCE_TABLES = ['city', 'ci_birth_cd', 'ci_birth_tb_cd', 'ci_personal_cd'];

    public function up(): void
    {
        if (!config("database.connections." . self::CIVIL)) {
            return;
        }

        try {
            DB::connection(self::CIVIL)->getPdo();
        } catch (\Throwable $e) {
            echo "  ⚠️ تعذر الاتصال بقاعدة civilregistry، تم التخطي: " . $e->getMessage() . "\n";
            return;
        }

        $this->createReferenceTables();
        $this->createPersonsTable();
        $this->addNormalizedColumns();
        $this->addIndexes();
    }

    public function down(): void
    {
        Schema::connection(self::CIVIL)->dropIfExists('persons');
    }

    /* ===================== 1) جداول البحث المرجعية ===================== */

    private function createReferenceTables(): void
    {
        foreach (self::REFERENCE_TABLES as $table) {
            if (Schema::connection(self::CIVIL)->hasTable($table)) {
                continue;
            }

            // نسخ البنية من القاعدة الرئيسية (CREATE TABLE LIKE لا ينقل القيود البيانية)
            try {
                $main = DB::connection()->getDatabaseName();
                DB::connection(self::CIVIL)->statement(
                    "CREATE TABLE `{$table}` LIKE `{$main}`.`{$table}`"
                );
            } catch (\Throwable $e) {
                echo "  ⚠️ تعذر نسخ بنية جدول {$table}: " . $e->getMessage() . "\n";
                continue;
            }

            // نسخ البيانات
            try {
                DB::connection(self::CIVIL)->statement(
                    "INSERT IGNORE INTO `{$table}` SELECT * FROM `{$main}`.`{$table}`"
                );
            } catch (\Throwable $e) {
                echo "  ⚠️ تعذر نسخ بيانات جدول {$table}: " . $e->getMessage() . "\n";
            }
        }
    }

    /* ===================== 2) جدول persons ===================== */

    private function createPersonsTable(): void
    {
        if (Schema::connection(self::CIVIL)->hasTable('persons')) {
            return;
        }

        Schema::connection(self::CIVIL)->create('persons', function (Blueprint $table) {
            $table->bigIncrements('ID');

            // رقم الهوية — مفتاح فريد (المعرّف المنطقي للسجل)
            $table->unsignedBigInteger('CI_ID_NUM')->unique();

            // الاسم الكامل (حقول مطلوبة)
            $table->string('CI_FIRST_ARB', 255);
            $table->string('CI_FATHER_ARB', 255);
            $table->string('CI_GRAND_FATHER_ARB', 255)->nullable();
            $table->string('CI_FAMILY_ARB', 255);

            // باقي الحقول nullable لأن الاستيراد والإدخال يقبلان قيماً فارغة
            $table->unsignedBigInteger('CI_BIRTH_TB_CD')->nullable();
            $table->unsignedBigInteger('CI_BIRTH_CD')->nullable();
            $table->date('CI_BIRTH_DT')->nullable();
            $table->integer('CI_SEX_CD')->nullable();
            $table->unsignedBigInteger('CI_PERSONAL_CD')->nullable();

            // علامة الوفاة: 0/NULL = حي، > 0 = متوفى (ليست تاريخاً تقويمياً)
            $table->unsignedBigInteger('CI_DEAD_DT')->nullable()->default(0);

            $table->string('MOTHER_NAME1', 255)->nullable();
            $table->unsignedBigInteger('CITY')->nullable();
            $table->string('STREET', 255)->nullable();
            $table->string('HOUSE_NO', 255)->nullable();

            $table->timestamps();

            // القيود البيانية — بنفس الأسماء المتوقعة في رسالة الخطأ
            $table->foreign('CI_BIRTH_TB_CD')->references('id')->on('ci_birth_tb_cd');
            $table->foreign('CI_BIRTH_CD')->references('id')->on('ci_birth_cd');
            $table->foreign('CI_PERSONAL_CD')->references('id')->on('ci_personal_cd');
            $table->foreign('CITY')->references('id')->on('city');
        });

        echo "  ✅ تم إنشاء جدول persons على الاتصال civilregistry\n";
    }

    /* ===================== 3) الأعمدة المولّعة ===================== */

    private function addNormalizedColumns(): void
    {
        $source = [
            'CI_FIRST_ARB'       => 'CI_FIRST_ARB_NORMALIZED',
            'CI_FATHER_ARB'      => 'CI_FATHER_ARB_NORMALIZED',
            'CI_GRAND_FATHER_ARB'=> 'CI_GRAND_FATHER_ARB_NORMALIZED',
            'CI_FAMILY_ARB'      => 'CI_FAMILY_ARB_NORMALIZED',
        ];

        $conn = DB::connection(self::CIVIL);

        foreach ($source as $col => $normalized) {
            if ($this->columnExists('persons', $normalized)) {
                continue;
            }

            $expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`{$col}`,'أ','ا'),'إ','ا'),'آ','ا'),'ى','ي'),'ة','ه')";

            // نجرب STORED أولاً ثم VIRTUAL كبديل
            $created = false;
            foreach (['STORED', 'VIRTUAL'] as $type) {
                try {
                    $conn->statement(
                        "ALTER TABLE `persons` ADD COLUMN `{$normalized}` VARCHAR(255) " .
                        "GENERATED ALWAYS AS ({$expr}) {$type}"
                    );
                    $created = true;
                    break;
                } catch (\Throwable $e) {
                    // المحاولة التالية
                }
            }

            if (!$created) {
                echo "  ⚠️ تعذر إنشاء العمود المولّد {$normalized}\n";
            }
        }
    }

    /* ===================== 4) الفهارس ===================== */

    private function addIndexes(): void
    {
        $conn = DB::connection(self::CIVIL);

        $indexes = [
            'idx_ci_id_num'          => 'CREATE INDEX idx_ci_id_num ON persons (CI_ID_NUM)',
            'idx_first_name_fast'    => 'CREATE INDEX idx_first_name_fast ON persons (CI_FIRST_ARB(10))',
            'idx_father_name_fast'   => 'CREATE INDEX idx_father_name_fast ON persons (CI_FATHER_ARB(10))',
            'idx_ci_family_arb'      => 'CREATE INDEX idx_ci_family_arb ON persons (CI_FAMILY_ARB(10))',
            'idx_sex_birth'          => 'CREATE INDEX idx_sex_birth ON persons (CI_SEX_CD, CI_BIRTH_DT)',
            'idx_dead_dt'            => 'CREATE INDEX idx_dead_dt ON persons (CI_DEAD_DT)',
            'idx_ci_first_normalized'=> 'CREATE INDEX idx_ci_first_normalized ON persons (CI_FIRST_ARB_NORMALIZED)',
            'idx_ci_father_normalized'   => 'CREATE INDEX idx_ci_father_normalized ON persons (CI_FATHER_ARB_NORMALIZED)',
            'idx_ci_grand_father_normalized' => 'CREATE INDEX idx_ci_grand_father_normalized ON persons (CI_GRAND_FATHER_ARB_NORMALIZED)',
            'idx_ci_family_normalized'    => 'CREATE INDEX idx_ci_family_normalized ON persons (CI_FAMILY_ARB_NORMALIZED)',
            'idx_normalized_first_family' => 'CREATE INDEX idx_normalized_first_family ON persons (CI_FIRST_ARB_NORMALIZED, CI_FAMILY_ARB_NORMALIZED)',
            'idx_normalized_first_father' => 'CREATE INDEX idx_normalized_first_father ON persons (CI_FIRST_ARB_NORMALIZED, CI_FATHER_ARB_NORMALIZED)',
            // FULLTEXT — يخدم CustomScoutSearchService (MATCH ... AGAINST)
            // ملاحظة: MATCH على عمود مفرد يتطلب فهرس FULLTEXT مفرد لذلك العمود
            'idx_ft_ci_first_arb'  => 'ALTER TABLE persons ADD FULLTEXT INDEX idx_ft_ci_first_arb (CI_FIRST_ARB)',
            'idx_ft_ci_father_arb' => 'ALTER TABLE persons ADD FULLTEXT INDEX idx_ft_ci_father_arb (CI_FATHER_ARB)',
            'idx_ft_ci_family_arb' => 'ALTER TABLE persons ADD FULLTEXT INDEX idx_ft_ci_family_arb (CI_FAMILY_ARB)',
            'idx_fulltext_search' => 'ALTER TABLE persons ADD FULLTEXT INDEX idx_fulltext_search (CI_FIRST_ARB, CI_FATHER_ARB, CI_GRAND_FATHER_ARB, CI_FAMILY_ARB, MOTHER_NAME1)',
        ];

        foreach ($indexes as $name => $sql) {
            if ($this->indexExists('persons', $name)) {
                continue;
            }
            try {
                $conn->statement($sql);
            } catch (\Throwable $e) {
                echo "  ⚠️ تعذر إنشاء الفهرس {$name}: " . $e->getMessage() . "\n";
            }
        }
    }

    /* ===================== أدوات مساعدة ===================== */

    private function columnExists(string $table, string $column): bool
    {
        $rows = DB::connection(self::CIVIL)->select(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        return (int) ($rows[0]->c ?? 0) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::connection(self::CIVIL)->select(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );
        return (int) ($rows[0]->c ?? 0) > 0;
    }
};
