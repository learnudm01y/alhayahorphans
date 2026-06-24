<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف قيد المفتاح الأجنبي guardian_bank_accounts_guardian_registration_foreign
 *
 * السبب: عمود guardian_registration يحمل قيمة من أحد ثلاثة جداول:
 *   - data.file_id_number          (للأحياء العاديين)
 *   - dead_people.re_file_id       (للأب/الأم المتوفين)
 *   - re_people.re_file_id         (لأفراد الأسرة من re_people)
 *
 * قيد FK الذي يشير فقط إلى data.file_id_number يمنع حفظ أي حساب بنكي
 * لكفالات "أب متوفي" أو "أم متوفية" — وهو خلل تصميمي بنيوي.
 *
 * الحل: حذف FK وإبقاء الـ index فقط. الترابط يُدار برمجياً بالكود.
 */
return new class extends Migration
{
    public function up(): void
    {
        // التحقق من وجود قيد FK قبل محاولة حذفه (آمن على كل البيئات)
        $connection = Schema::getConnection();
        $dbName = $connection->getDatabaseName();

        $fkExists = $connection->selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'guardian_bank_accounts'
              AND CONSTRAINT_NAME = 'guardian_bank_accounts_guardian_registration_foreign'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$dbName]);

        if ($fkExists) {
            Schema::table('guardian_bank_accounts', function (Blueprint $table) {
                // حذف قيد المفتاح الأجنبي فقط — العمود والـ index يبقيان
                $table->dropForeign('guardian_bank_accounts_guardian_registration_foreign');
            });
        }
    }

    public function down(): void
    {
        // إعادة القيد فقط إذا كانت جميع القيم موجودة في data.file_id_number
        // تحذير: ستفشل إذا كانت هناك قيم من dead_people أو re_people
        Schema::table('guardian_bank_accounts', function (Blueprint $table) {
            $table->foreign('guardian_registration')
                  ->references('file_id_number')
                  ->on('data')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }
};
