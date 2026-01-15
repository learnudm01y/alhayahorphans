<?php
/**
 * اختبار السيناريو الرابع
 * المكفول شخص متوفي (أب متوفي deceased_father أو أم متوفية deceased_mother)
 *
 * ملاحظات مهمة:
 * - لا يُسمح بتعديل internal_file_number
 * - لا يتم إدخال guardian_name و guardian_identity_number (لا يوجد معيل)
 * - فقط بيانات المكفول (orphan_name + identity_number) يتم تعديلها
 * - التخزين في جدول dead_people عبر re_file_id
 *
 * السيناريو 4أ: كفالة مربوطة مسبقاً - تحديث (أب متوفي)
 * السيناريو 4ب: كفالة غير مربوطة - إنشاء جديد (أم متوفية)
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 السيناريو الرابع: المكفول شخص متوفي (deceased_father/mother)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

Log::info('═══ السيناريو الرابع: deceased ═══');

// ═══════════════════════════════════════════════════════════════
// السيناريو 4أ: كفالة أب متوفي مربوطة مسبقاً - تحديث
// ═══════════════════════════════════════════════════════════════
function runScenario4A() {
    echo "\n" . str_repeat("▓", 60) . "\n";
    echo "📋 السيناريو 4أ: كفالة أب متوفي (deceased_father) مربوطة - تحديث\n";
    echo str_repeat("▓", 60) . "\n\n";

    Log::info('═══ السيناريو 4أ: deceased_father مربوط ═══');

    // البحث عن كفالة deceased_father مربوطة
    $sponsorship = DB::table('sponsorships')
        ->where('person_type', 'deceased_father')
        ->whereNotNull('relation_id_number')
        ->where('relation_id_number', '!=', '')
        ->whereNotNull('internal_file_number')
        ->where('internal_file_number', '!=', '')
        ->first();

    if (!$sponsorship) {
        echo "⚠️ لا توجد كفالة deceased_father مربوطة - سيتم البحث عن أي نوع متوفي...\n";

        // البحث عن deceased_mother
        $sponsorship = DB::table('sponsorships')
            ->where('person_type', 'deceased_mother')
            ->whereNotNull('relation_id_number')
            ->where('relation_id_number', '!=', '')
            ->first();

        if (!$sponsorship) {
            // تحويل كفالة موجودة للاختبار
            $anySponsorship = DB::table('sponsorships')
                ->whereNotNull('relation_id_number')
                ->where('relation_id_number', '!=', '')
                ->first();

            if ($anySponsorship) {
                DB::table('sponsorships')
                    ->where('internal_file_number', $anySponsorship->internal_file_number)
                    ->update(['person_type' => 'deceased_father']);

                $sponsorship = DB::table('sponsorships')
                    ->where('internal_file_number', $anySponsorship->internal_file_number)
                    ->first();

                echo "✅ تم تحويل الكفالة {$sponsorship->internal_file_number} إلى deceased_father للاختبار\n";
            } else {
                echo "❌ لا توجد كفالات مربوطة!\n";
                return false;
            }
        }
    }

    $internalFileNumber = $sponsorship->internal_file_number;
    $relationIdNumber = $sponsorship->relation_id_number;
    $personType = $sponsorship->person_type;

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - internal_file_number: {$internalFileNumber} (للقراءة فقط - لا يُعدّل)\n";
    echo "   - relation_id_number: [{$relationIdNumber}]\n";
    echo "   - person_type: {$personType}\n";
    echo "   - orphan_name (حالي): " . ($sponsorship->orphan_name ?? 'NULL') . "\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: جلب السجلات المرتبطة
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 1: جلب السجلات المرتبطة\n";
    echo str_repeat("-", 50) . "\n";

    $existingDeadPerson = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
    $existingBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $relationIdNumber)->first();

    echo "   - dead_people: " . ($existingDeadPerson ? "موجود (ID: {$existingDeadPerson->id})" : "❌ غير موجود") . "\n";
    echo "   - guardian_bank_accounts: " . ($existingBank ? "موجود (ID: {$existingBank->id})" : "❌ غير موجود") . "\n";
    echo "   ℹ️ في حالة {$personType}: الشخص المكفول متوفي (لا يوجد معيل)\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: تجهيز بيانات المتوفي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: تجهيز بيانات المتوفي\n";
    echo str_repeat("-", 50) . "\n";

    $isFather = ($personType == 'deceased_father');

    $deceasedData = [
        'first_name' => $isFather ? 'أحمد' : 'فاطمة',
        'second_name' => 'محمد',
        'third_name' => 'علي',
        'last_name' => 'المتوفي',
        'identity_number' => ($isFather ? '806' : '807') . rand(100000, 999999),
        'death_date' => '2020-05-15',
        'phone' => '059' . rand(1000000, 9999999) // رقم هاتف المستفيد/الوريث
    ];
    $deceasedFullName = "{$deceasedData['first_name']} {$deceasedData['second_name']} {$deceasedData['third_name']} {$deceasedData['last_name']}";

    echo "   المتوفي (" . ($isFather ? 'أب' : 'أم') . "): {$deceasedFullName}\n";
    echo "   الهوية: {$deceasedData['identity_number']}\n";
    echo "   تاريخ الوفاة: {$deceasedData['death_date']}\n";
    echo "   رقم الهاتف: {$deceasedData['phone']}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: تحديث/إدخال في جدول dead_people
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: تحديث/إدخال في جدول dead_people\n";
    echo str_repeat("-", 50) . "\n";

    if ($isFather) {
        $deadPeopleData = [
            'father_first_name' => $deceasedData['first_name'],
            'father_second_name' => $deceasedData['second_name'],
            'father_third_name' => $deceasedData['third_name'],
            'father_last_name' => $deceasedData['last_name'],
            'father_id' => (int)preg_replace('/[^0-9]/', '', $deceasedData['identity_number']),
            'father_death_date' => $deceasedData['death_date'],
            'updated_at' => now()
        ];
    } else {
        $deadPeopleData = [
            'mother_first_name' => $deceasedData['first_name'],
            'mother_second_name' => $deceasedData['second_name'],
            'mother_third_name' => $deceasedData['third_name'],
            'mother_last_name' => $deceasedData['last_name'],
            'mother_id' => (int)preg_replace('/[^0-9]/', '', $deceasedData['identity_number']),
            'mother_death_date' => $deceasedData['death_date'],
            'updated_at' => now()
        ];
    }

    if ($existingDeadPerson) {
        try {
            $updatedRows = DB::table('dead_people')
                ->where('re_file_id', $relationIdNumber)
                ->update($deadPeopleData);
            echo "   ✅ تم تحديث البيانات في dead_people! (Rows: {$updatedRows})\n";
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
            return false;
        }
    } else {
        $deadPeopleData['re_file_id'] = $relationIdNumber;
        $deadPeopleData['created_at'] = now();

        try {
            $newId = DB::table('dead_people')->insertGetId($deadPeopleData);
            echo "   ✅ تم إدخال سجل جديد في dead_people! ID: {$newId}\n";
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: تحديث/إضافة المعلومات البنكية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: تحديث/إضافة المعلومات البنكية\n";
    echo str_repeat("-", 50) . "\n";

    $bankData = [
        'bank_name' => 4, // بنك فلسطين
        'iban_usd' => 'PS92PALS000000000DEC' . rand(10000, 99999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000DEC' . rand(10000, 99999) . 'ILS',
        're_id_number' => $deceasedData['identity_number'],
        're_guardian_name' => $deceasedFullName,
        're_phone_number' => $deceasedData['phone'],
        'person_owner_identity_number' => $deceasedData['identity_number'],
        'check_account' => 1,
        'updated_at' => now()
    ];

    if ($existingBank) {
        try {
            $updatedRows = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $relationIdNumber)
                ->update($bankData);
            echo "   ✅ تم تحديث المعلومات البنكية! (Rows: {$updatedRows})\n";
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    } else {
        $bankData['guardian_registration'] = $relationIdNumber;
        $bankData['created_at'] = now();

        try {
            $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankData);
            echo "   ✅ تم إضافة معلومات بنكية جديدة! ID: {$newBankId}\n";
        } catch (\Exception $e) {
            echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        }
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: تحديث جدول sponsorships (المكفول فقط)
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: تحديث جدول sponsorships (بيانات المكفول فقط)\n";
    echo str_repeat("-", 50) . "\n";

    // في حالة deceased: فقط orphan_name و identity_number
    // لا نعدل guardian_name و guardian_identity_number (لا يوجد معيل)
    $sponsorshipUpdate = [
        'orphan_name' => $deceasedFullName,
        'identity_number' => $deceasedData['identity_number'],
        'updated_at' => now()
    ];

    echo "   التحديثات ({$personType} - المكفول فقط):\n";
    echo "      - orphan_name: {$deceasedFullName}\n";
    echo "      - identity_number: {$deceasedData['identity_number']}\n";
    echo "      ⚠️ لا يتم تعديل guardian_name و guardian_identity_number (لا يوجد معيل)\n";

    try {
        $updated = DB::table('sponsorships')
            ->where('internal_file_number', $internalFileNumber)
            ->update($sponsorshipUpdate);

        echo "\n   ✅ تم تحديث الكفالة! (Rows: {$updated})\n";
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        return false;
    }

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->where('internal_file_number', $internalFileNumber)->first();
    $updatedDeadPerson = DB::table('dead_people')->where('re_file_id', $relationIdNumber)->first();
    $updatedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $relationIdNumber)->first();

    echo "\n🎯 الكفالة بعد التحديث:\n";
    echo "   - internal_file_number: {$updatedSponsorship->internal_file_number} (لم يُعدّل ✅)\n";
    echo "   - relation_id_number: [{$updatedSponsorship->relation_id_number}]\n";
    echo "   - person_type: {$updatedSponsorship->person_type}\n";
    echo "   - orphan_name: {$updatedSponsorship->orphan_name} ← تم التحديث\n";
    echo "   - identity_number: {$updatedSponsorship->identity_number} ← تم التحديث\n";
    echo "   - guardian_name: " . ($updatedSponsorship->guardian_name ?? 'NULL') . " (لا يوجد معيل)\n";
    echo "   - guardian_identity_number: " . ($updatedSponsorship->guardian_identity_number ?? 'NULL') . " (لا يوجد معيل)\n";

    echo "\n⚰️ بيانات المتوفي (dead_people):\n";
    if ($updatedDeadPerson) {
        if ($isFather) {
            $deadFullName = "{$updatedDeadPerson->father_first_name} {$updatedDeadPerson->father_second_name} {$updatedDeadPerson->father_third_name} {$updatedDeadPerson->father_last_name}";
            echo "   - نوع: أب متوفي\n";
            echo "   - الاسم: {$deadFullName}\n";
            echo "   - الهوية: {$updatedDeadPerson->father_id}\n";
            echo "   - تاريخ الوفاة: {$updatedDeadPerson->father_death_date}\n";
        } else {
            $deadFullName = "{$updatedDeadPerson->mother_first_name} {$updatedDeadPerson->mother_second_name} {$updatedDeadPerson->mother_third_name} {$updatedDeadPerson->mother_last_name}";
            echo "   - نوع: أم متوفية\n";
            echo "   - الاسم: {$deadFullName}\n";
            echo "   - الهوية: {$updatedDeadPerson->mother_id}\n";
            echo "   - تاريخ الوفاة: {$updatedDeadPerson->mother_death_date}\n";
        }
    }

    echo "\n🏦 الحساب البنكي:\n";
    if ($updatedBank) {
        echo "   - البنك: {$updatedBank->bank_name} (بنك فلسطين)\n";
        echo "   - IBAN USD: {$updatedBank->iban_usd}\n";
        echo "   - check_account: {$updatedBank->check_account}\n";
    }

    $success = $updatedSponsorship->orphan_name == $deceasedFullName;

    if ($success) {
        echo "\n✅✅✅ السيناريو 4أ: نجاح تام! ✅✅✅\n";
        return true;
    } else {
        echo "\n❌ هناك مشكلة!\n";
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// السيناريو 4ب: كفالة أم متوفية غير مربوطة - إنشاء جديد
// ═══════════════════════════════════════════════════════════════
function runScenario4B() {
    echo "\n" . str_repeat("▓", 60) . "\n";
    echo "📋 السيناريو 4ب: كفالة أم متوفية (deceased_mother) غير مربوطة - إنشاء جديد\n";
    echo str_repeat("▓", 60) . "\n\n";

    Log::info('═══ السيناريو 4ب: deceased_mother غير مربوط ═══');

    // البحث عن كفالة deceased غير مربوطة
    $sponsorship = DB::table('sponsorships')
        ->whereIn('person_type', ['deceased_father', 'deceased_mother'])
        ->where(function($q) {
            $q->whereNull('relation_id_number')
              ->orWhere('relation_id_number', '');
        })
        ->whereNotNull('internal_file_number')
        ->where('internal_file_number', '!=', '')
        ->first();

    if (!$sponsorship) {
        echo "⚠️ لا توجد كفالة deceased غير مربوطة - سيتم إنشاء واحدة للاختبار\n";

        // إنشاء كفالة وهمية للاختبار
        $testInternalNumber = 'TEST' . rand(5000, 5999);

        try {
            DB::table('sponsorships')->insert([
                'internal_file_number' => $testInternalNumber,
                'person_type' => 'deceased_mother',
                'relation_id_number' => null,
                'orphan_name' => 'اختبار أم متوفية',
                'guardian_name' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $sponsorship = DB::table('sponsorships')
                ->where('internal_file_number', $testInternalNumber)
                ->first();

            echo "✅ تم إنشاء كفالة deceased_mother وهمية: {$testInternalNumber}\n";
        } catch (\Exception $e) {
            echo "❌ فشل إنشاء كفالة وهمية: " . $e->getMessage() . "\n";
            return false;
        }
    }

    $internalFileNumber = $sponsorship->internal_file_number;
    $personType = $sponsorship->person_type ?? 'deceased_mother';

    // تحديد إذا أب أو أم
    $isFather = ($personType == 'deceased_father');

    echo "📋 الكفالة المستهدفة:\n";
    echo "   - internal_file_number: {$internalFileNumber} (للقراءة فقط - لا يُعدّل)\n";
    echo "   - relation_id_number: [" . ($sponsorship->relation_id_number ?: 'فارغ') . "]\n";
    echo "   - person_type: {$personType}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 1: توليد رقم ملف جديد
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 1: توليد رقم ملف جديد\n";
    echo str_repeat("-", 50) . "\n";

    $newFileIdNumber = generateFileIdFromDataTable();
    echo "   ✅ رقم الملف الجديد (relation_id_number): {$newFileIdNumber}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 2: تجهيز بيانات المتوفي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 2: تجهيز بيانات المتوفي\n";
    echo str_repeat("-", 50) . "\n";

    $deceasedData = [
        'first_name' => $isFather ? 'خالد' : 'مريم',
        'second_name' => 'إبراهيم',
        'third_name' => 'سعيد',
        'last_name' => 'الشهيد',
        'identity_number' => ($isFather ? '808' : '809') . rand(100000, 999999),
        'death_date' => '2021-08-20',
        'phone' => '059' . rand(1000000, 9999999) // رقم هاتف المستفيد/الوريث
    ];
    $deceasedFullName = "{$deceasedData['first_name']} {$deceasedData['second_name']} {$deceasedData['third_name']} {$deceasedData['last_name']}";

    echo "   المتوفي (" . ($isFather ? 'أب' : 'أم') . "): {$deceasedFullName}\n";
    echo "   الهوية: {$deceasedData['identity_number']}\n";
    echo "   تاريخ الوفاة: {$deceasedData['death_date']}\n";
    echo "   رقم الهاتف: {$deceasedData['phone']}\n";

    // ─────────────────────────────────────────────────────────
    // الخطوة 3: إدخال سجل جديد في dead_people
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 3: إدخال سجل جديد في dead_people\n";
    echo str_repeat("-", 50) . "\n";

    if ($isFather) {
        $deadPeopleInsert = [
            're_file_id' => $newFileIdNumber,
            'father_first_name' => $deceasedData['first_name'],
            'father_second_name' => $deceasedData['second_name'],
            'father_third_name' => $deceasedData['third_name'],
            'father_last_name' => $deceasedData['last_name'],
            'father_id' => (int)preg_replace('/[^0-9]/', '', $deceasedData['identity_number']),
            'father_death_date' => $deceasedData['death_date'],
            'created_at' => now(),
            'updated_at' => now()
        ];
    } else {
        $deadPeopleInsert = [
            're_file_id' => $newFileIdNumber,
            'mother_first_name' => $deceasedData['first_name'],
            'mother_second_name' => $deceasedData['second_name'],
            'mother_third_name' => $deceasedData['third_name'],
            'mother_last_name' => $deceasedData['last_name'],
            'mother_id' => (int)preg_replace('/[^0-9]/', '', $deceasedData['identity_number']),
            'mother_death_date' => $deceasedData['death_date'],
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    try {
        $newDeadId = DB::table('dead_people')->insertGetId($deadPeopleInsert);
        echo "   ✅ تم إدخال البيانات في dead_people! ID: {$newDeadId}\n";
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        return false;
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 4: إدخال المعلومات البنكية
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 4: إدخال المعلومات البنكية\n";
    echo str_repeat("-", 50) . "\n";

    $bankInsert = [
        'guardian_registration' => $newFileIdNumber,
        'bank_name' => 4, // بنك فلسطين
        'iban_usd' => 'PS92PALS000000000DEC' . rand(10000, 99999) . 'USD',
        'iban_shekel' => 'PS92PALS000000000DEC' . rand(10000, 99999) . 'ILS',
        're_id_number' => $deceasedData['identity_number'],
        're_guardian_name' => $deceasedFullName,
        're_phone_number' => $deceasedData['phone'],
        'person_owner_identity_number' => $deceasedData['identity_number'],
        'check_account' => 1,
        'created_at' => now(),
        'updated_at' => now()
    ];

    try {
        $newBankId = DB::table('guardian_bank_accounts')->insertGetId($bankInsert);
        echo "   ✅ تم إدخال المعلومات البنكية! ID: {$newBankId}\n";
        echo "   ✅ check_account = 1 (مؤكد)\n";
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        $newBankId = null;
    }

    // ─────────────────────────────────────────────────────────
    // الخطوة 5: ربط وتحديث جدول sponsorships (المكفول فقط)
    // ─────────────────────────────────────────────────────────
    echo "\n📋 الخطوة 5: ربط وتحديث جدول sponsorships (المكفول فقط)\n";
    echo str_repeat("-", 50) . "\n";

    // في حالة deceased: فقط relation_id_number + orphan_name + identity_number
    // لا نعدل guardian_name و guardian_identity_number (لا يوجد معيل)
    // لا نعدل internal_file_number
    $sponsorshipUpdate = [
        'relation_id_number' => $newFileIdNumber,
        'orphan_name' => $deceasedFullName,
        'identity_number' => $deceasedData['identity_number'],
        'updated_at' => now()
    ];

    echo "   التحديثات ({$personType} - المكفول فقط):\n";
    echo "      - relation_id_number: {$newFileIdNumber} ← ربط جديد\n";
    echo "      - orphan_name: {$deceasedFullName}\n";
    echo "      - identity_number: {$deceasedData['identity_number']}\n";
    echo "      ⚠️ لا يتم تعديل guardian_name و guardian_identity_number (لا يوجد معيل)\n";
    echo "      ⚠️ لا يتم تعديل internal_file_number\n";

    try {
        $updated = DB::table('sponsorships')
            ->where('internal_file_number', $internalFileNumber)
            ->update($sponsorshipUpdate);

        echo "\n   ✅ تم ربط وتحديث الكفالة! (Rows: {$updated})\n";
    } catch (\Exception $e) {
        echo "   ❌ خطأ: " . $e->getMessage() . "\n";
        return false;
    }

    // ─────────────────────────────────────────────────────────
    // التحقق النهائي
    // ─────────────────────────────────────────────────────────
    echo "\n📋 التحقق النهائي:\n";
    echo str_repeat("═", 50) . "\n";

    $updatedSponsorship = DB::table('sponsorships')->where('internal_file_number', $internalFileNumber)->first();
    $linkedDeadPerson = DB::table('dead_people')->where('re_file_id', $newFileIdNumber)->first();
    $linkedBank = DB::table('guardian_bank_accounts')->where('guardian_registration', $newFileIdNumber)->first();

    echo "\n🎯 الكفالة بعد التحديث:\n";
    echo "   - internal_file_number: {$updatedSponsorship->internal_file_number} (لم يُعدّل ✅)\n";
    echo "   - relation_id_number: [{$updatedSponsorship->relation_id_number}] ← تم الربط\n";
    echo "   - person_type: " . ($updatedSponsorship->person_type ?? 'غير محدد') . "\n";
    echo "   - orphan_name: {$updatedSponsorship->orphan_name} ← تم التحديث\n";
    echo "   - identity_number: {$updatedSponsorship->identity_number} ← تم التحديث\n";
    echo "   - guardian_name: " . ($updatedSponsorship->guardian_name ?? 'NULL') . " (لا يوجد معيل)\n";
    echo "   - guardian_identity_number: " . ($updatedSponsorship->guardian_identity_number ?? 'NULL') . " (لا يوجد معيل)\n";

    echo "\n⚰️ بيانات المتوفي (dead_people):\n";
    if ($linkedDeadPerson) {
        if ($isFather) {
            $deadFullName = "{$linkedDeadPerson->father_first_name} {$linkedDeadPerson->father_second_name} {$linkedDeadPerson->father_third_name} {$linkedDeadPerson->father_last_name}";
            echo "   - نوع: أب متوفي\n";
            echo "   - الاسم: {$deadFullName}\n";
            echo "   - الهوية: {$linkedDeadPerson->father_id}\n";
            echo "   - تاريخ الوفاة: {$linkedDeadPerson->father_death_date}\n";
        } else {
            $deadFullName = "{$linkedDeadPerson->mother_first_name} {$linkedDeadPerson->mother_second_name} {$linkedDeadPerson->mother_third_name} {$linkedDeadPerson->mother_last_name}";
            echo "   - نوع: أم متوفية\n";
            echo "   - الاسم: {$deadFullName}\n";
            echo "   - الهوية: {$linkedDeadPerson->mother_id}\n";
            echo "   - تاريخ الوفاة: {$linkedDeadPerson->mother_death_date}\n";
        }
    }

    echo "\n🏦 الحساب البنكي:\n";
    if ($linkedBank) {
        echo "   - البنك: {$linkedBank->bank_name} (بنك فلسطين)\n";
        echo "   - IBAN USD: {$linkedBank->iban_usd}\n";
        echo "   - check_account: {$linkedBank->check_account}\n";
    }

    echo "\n🔗 الربط:\n";
    echo "   sponsorships.internal_file_number = {$internalFileNumber} (ثابت)\n";
    echo "   sponsorships.relation_id_number → {$newFileIdNumber}\n";
    echo "   dead_people.re_file_id → " . ($linkedDeadPerson ? $linkedDeadPerson->re_file_id : 'NOT FOUND') . "\n";
    echo "   guardian_bank_accounts.guardian_registration → " . ($linkedBank ? $linkedBank->guardian_registration : 'NOT FOUND') . "\n";

    $success = $updatedSponsorship->relation_id_number == $newFileIdNumber &&
               $linkedDeadPerson &&
               $linkedBank &&
               $updatedSponsorship->internal_file_number == $internalFileNumber;

    if ($success) {
        echo "\n✅✅✅ السيناريو 4ب: نجاح تام! ✅✅✅\n";
        return true;
    } else {
        echo "\n❌ هناك مشكلة!\n";
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════
// تشغيل السيناريوهات
// ═══════════════════════════════════════════════════════════════

$result4A = runScenario4A();
$result4B = runScenario4B();

// ─────────────────────────────────────────────────────────
// ملخص نهائي
// ─────────────────────────────────────────────────────────
echo "\n" . str_repeat("═", 60) . "\n";
echo "📊 ملخص السيناريو الرابع (deceased):\n";
echo str_repeat("═", 60) . "\n";
echo "   السيناريو 4أ (مربوط - تحديث): " . ($result4A ? "✅ نجح" : "❌ فشل") . "\n";
echo "   السيناريو 4ب (غير مربوط - إنشاء): " . ($result4B ? "✅ نجح" : "❌ فشل") . "\n";
echo str_repeat("═", 60) . "\n";
echo "\n📝 ملاحظات مهمة:\n";
echo "   ❌ لا يُسمح بتعديل internal_file_number\n";
echo "   ❌ لا يتم إدخال guardian_name و guardian_identity_number (لا يوجد معيل)\n";
echo "   ✅ فقط orphan_name و identity_number يتم تعديلها\n";
echo "   ✅ في حالة deceased: الشخص المكفول متوفي\n";
echo "   ✅ يتم استخدام جدول dead_people عبر re_file_id\n";
echo "   ✅ أعمدة الأب: father_first_name, father_id, father_death_date...\n";
echo "   ✅ أعمدة الأم: mother_first_name, mother_id, mother_death_date...\n";
echo str_repeat("═", 60) . "\n";

Log::info('═══ انتهى السيناريو الرابع ═══');
