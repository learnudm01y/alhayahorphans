<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\GuardianBankAccount;

/**
 * اختبار التحقق من إزالة قيد FK من guardian_bank_accounts.guardian_registration
 *
 * المشكلة: guardian_registration يُخزّن قيماً من dead_people.re_file_id
 * لكن قيد FK كان يشير إلى data.file_id_number فقط → خطأ 1452
 *
 * الحل: حذف قيد FK عبر migration
 * هذا الاختبار يتحقق من:
 *   1. أن FK لم يعد موجوداً في قاعدة البيانات
 *   2. أن الإدخال بقيمة من dead_people ينجح بدون استثناء
 *   3. أن الإدخال بقيمة غير موجودة في أي جدول ينجح (لا يوجد قيد)
 */
class GuardianBankAccountFKFixTest extends TestCase
{
    // لا نستخدم RefreshDatabase — نريد قاعدة البيانات الحقيقية
    // ونقوم بالتنظيف يدوياً بعد كل اختبار

    private array $createdIds = [];

    protected function tearDown(): void
    {
        // تنظيف السجلات التجريبية التي أنشأناها
        if (!empty($this->createdIds)) {
            GuardianBankAccount::whereIn('id', $this->createdIds)->delete();
        }
        parent::tearDown();
    }

    /**
     * Test 1: التحقق من أن قيد FK غير موجود في قاعدة البيانات
     */
    public function test_fk_constraint_does_not_exist_on_guardian_bank_accounts(): void
    {
        $dbName = DB::connection()->getDatabaseName();

        $fkExists = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'guardian_bank_accounts'
              AND CONSTRAINT_NAME = 'guardian_bank_accounts_guardian_registration_foreign'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$dbName]);

        $this->assertNull(
            $fkExists,
            "❌ قيد FK لا يزال موجوداً! Migration لم يُطبَّق بشكل صحيح.\n" .
            "نفّذ: php artisan migrate"
        );
    }

    /**
     * Test 2: التحقق من إمكانية إدخال سجل باستخدام قيمة من dead_people.re_file_id
     *
     * هذا هو بالضبط السيناريو الذي كان يفشل: "أب متوفي" / "أم متوفية"
     * حيث guardian_registration = dead_people.re_file_id
     */
    public function test_can_insert_bank_account_with_dead_people_file_id(): void
    {
        // جلب أول سجل من dead_people للحصول على re_file_id حقيقي
        $deadRecord = DB::table('dead_people')->first();

        if (!$deadRecord) {
            $this->markTestSkipped('لا توجد سجلات في جدول dead_people لإجراء الاختبار');
        }

        $deadFileId = $deadRecord->re_file_id;

        // جلب أي bank_name موجود في قاعدة البيانات
        $bankName = DB::table('bank_names')->first();

        if (!$bankName) {
            $this->markTestSkipped('لا توجد سجلات في جدول bank_names لإجراء الاختبار');
        }

        // المحاولة: إدخال حساب بنكي بـ guardian_registration = dead_people.re_file_id
        // هذا كان يفشل بـ: SQLSTATE[23000] Integrity constraint violation: 1452
        $exception = null;
        $bankAccount = null;

        try {
            $bankAccount = GuardianBankAccount::create([
                'guardian_registration' => $deadFileId,
                'bank_name'             => $bankName->id,
                'iban_usd'              => 'TEST-' . $deadFileId,
            ]);
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($bankAccount) {
            $this->createdIds[] = $bankAccount->id;
        }

        $this->assertNull(
            $exception,
            "❌ الإدخال فشل بخطأ: " . ($exception?->getMessage() ?? '') . "\n" .
            "dead_people.re_file_id المستخدم: {$deadFileId}\n" .
            "يُرجى التأكد من تطبيق migration إزالة FK."
        );

        $this->assertNotNull(
            $bankAccount,
            "❌ لم يُنشأ السجل — GuardianBankAccount::create() أعادت null"
        );

        $this->assertDatabaseHas('guardian_bank_accounts', [
            'guardian_registration' => $deadFileId,
            'iban_usd'              => 'TEST-' . $deadFileId,
        ]);
    }

    /**
     * Test 3: التحقق من أن النتيجة صحيحة — السجل موجود في DB بالقيم الصحيحة
     */
    public function test_inserted_dead_people_bank_account_has_correct_data(): void
    {
        $deadRecord = DB::table('dead_people')->first();

        if (!$deadRecord) {
            $this->markTestSkipped('لا توجد سجلات في dead_people');
        }

        $bankName = DB::table('bank_names')->first();

        if (!$bankName) {
            $this->markTestSkipped('لا توجد سجلات في bank_names');
        }

        $deadFileId   = $deadRecord->re_file_id;
        $accountNum   = 'VERIFY-TEST-' . uniqid();

        $bankAccount = GuardianBankAccount::create([
            'guardian_registration' => $deadFileId,
            'bank_name'             => $bankName->id,
            'iban_usd'              => $accountNum,
        ]);

        $this->createdIds[] = $bankAccount->id;

        // التحقق من القراءة — بنفس الطريقة التي يستخدمها GeneralRegistrationController
        $fetched = GuardianBankAccount::where('guardian_registration', $deadFileId)
            ->where('iban_usd', $accountNum)
            ->first();

        $this->assertNotNull($fetched, "❌ السجل المُدرَج لا يمكن استرجاعه بـ guardian_registration = {$deadFileId}");
        $this->assertEquals($deadFileId, $fetched->guardian_registration);
        $this->assertEquals($bankName->id, $fetched->bank_name);
    }

    /**
     * Test 4: التحقق من أن الإدخال بقيمة لا تنتمي لأي جدول ينجح أيضاً
     *
     * بعد حذف FK، يجب أن تقبل قاعدة البيانات أي قيمة في العمود
     */
    public function test_can_insert_bank_account_with_any_value_after_fk_drop(): void
    {
        $bankName = DB::table('bank_names')->first();

        if (!$bankName) {
            $this->markTestSkipped('لا توجد سجلات في bank_names');
        }

        // قيمة وهمية لا توجد في أي جدول
        $fakeFileId = 'TEST-NONEXISTENT-99999';

        $bankAccount = GuardianBankAccount::create([
            'guardian_registration' => $fakeFileId,
            'bank_name'             => $bankName->id,
            'iban_usd'              => 'TEST-FAKE-ACCOUNT',
        ]);

        $this->createdIds[] = $bankAccount->id;

        $this->assertNotNull($bankAccount->id, "❌ الإدخال بقيمة وهمية فشل — قد يكون FK لا يزال موجوداً");
        $this->assertDatabaseHas('guardian_bank_accounts', [
            'guardian_registration' => $fakeFileId,
        ]);
    }

    /**
     * Test 5: التحقق من أن عمود guardian_registration وindex لا يزالان موجودَين
     *
     * حذف FK يجب ألا يحذف العمود أو الـ index
     */
    public function test_column_and_index_still_exist_after_fk_drop(): void
    {
        $dbName = DB::connection()->getDatabaseName();

        // التحقق من وجود العمود
        $column = DB::selectOne("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'guardian_bank_accounts'
              AND COLUMN_NAME = 'guardian_registration'
        ", [$dbName]);

        $this->assertNotNull($column, "❌ العمود guardian_registration غير موجود — تم حذفه بالخطأ!");

        // التحقق من وجود index على العمود
        $index = DB::selectOne("
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'guardian_bank_accounts'
              AND COLUMN_NAME = 'guardian_registration'
        ", [$dbName]);

        $this->assertNotNull($index, "⚠️ تحذير: لا يوجد index على guardian_registration (الأداء قد يتأثر)");
    }
}
