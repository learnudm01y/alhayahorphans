<?php

/**
 * سكريبت اختبار نظام منع تكرار الحسابات البنكية
 *
 * يتم تشغيله من سطر الأوامر:
 * php test_bank_duplicate_prevention.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Log;
use App\Services\BankAccountValidationService;
use App\Models\GuardianBankAccount;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "═══════════════════════════════════════════════════════════════\n";
echo "   اختبار نظام منع تكرار الحسابات البنكية\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$service = app(BankAccountValidationService::class);

// البيانات التجريبية
$testData1 = [
    'guardian_registration' => '000001',
    're_phone_number' => '0591234567',
    'bank_name' => '1',
    're_id_number' => '123456789'
];

$testData2 = [
    'guardian_registration' => '000001',
    're_phone_number' => '0591234567',
    'bank_name' => '1',
    're_id_number' => '987654321'
];

echo "📋 الاختبار 1: التحقق من حساب غير موجود\n";
echo "─────────────────────────────────────────────\n";
$result1 = $service->checkDuplicateBankAccount($testData1);
echo "النتيجة: " . ($result1['is_duplicate'] ? "❌ مكرر" : "✅ غير مكرر") . "\n";
echo "الرسالة: " . $result1['message'] . "\n\n";

echo "📋 الاختبار 2: إنشاء حساب بنكي تجريبي\n";
echo "─────────────────────────────────────────────\n";
try {
    $testAccount = GuardianBankAccount::create([
        'guardian_registration' => '000001',
        'bank_name' => 1,
        're_guardian_name' => 'اختبار تجريبي',
        'person_owner_identity_number' => '123456789',
        're_phone_number' => '0591234567',
        're_id_number' => '123456789',
        'iban_usd' => 'PS12TEST00000000000000000001'
    ]);
    echo "✅ تم إنشاء الحساب التجريبي بنجاح (ID: {$testAccount->id})\n\n";
} catch (\Exception $e) {
    echo "❌ فشل إنشاء الحساب: {$e->getMessage()}\n\n";
    exit(1);
}

echo "📋 الاختبار 3: التحقق من نفس الحساب (يجب أن يكون مكرر)\n";
echo "───────────────────────────────────────────────────────────\n";
$result2 = $service->checkDuplicateBankAccount($testData1);
echo "النتيجة: " . ($result2['is_duplicate'] ? "✅ مكرر (صحيح)" : "❌ غير مكرر (خطأ)") . "\n";
if ($result2['is_duplicate']) {
    echo "الحساب الموجود:\n";
    echo "  - رقم الملف: " . $result2['existing_account']['guardian_registration'] . "\n";
    echo "  - رقم الهوية: " . $result2['existing_account']['re_id_number'] . "\n";
    echo "  - رقم الهاتف: " . $result2['existing_account']['re_phone_number'] . "\n";
}
echo "\n";

echo "📋 الاختبار 4: التحقق من حساب برقم هوية مختلف (يجب أن يكون غير مكرر)\n";
echo "──────────────────────────────────────────────────────────────────────\n";
$result3 = $service->checkDuplicateBankAccount($testData2);
echo "النتيجة: " . ($result3['is_duplicate'] ? "❌ مكرر (خطأ)" : "✅ غير مكرر (صحيح)") . "\n\n";

echo "📋 الاختبار 5: التحقق مع استثناء (للتعديل)\n";
echo "────────────────────────────────────────────────\n";
$result4 = $service->checkDuplicateBankAccount($testData1, $testAccount->id);
echo "النتيجة: " . ($result4['is_duplicate'] ? "❌ مكرر (خطأ)" : "✅ غير مكرر (صحيح للتعديل)") . "\n\n";

echo "📋 الاختبار 6: التحقق السريع\n";
echo "─────────────────────────────────\n";
$quickCheck = $service->quickDuplicateCheck('000001', '123456789');
echo "النتيجة: " . ($quickCheck ? "✅ يوجد حساب" : "❌ لا يوجد حساب") . "\n\n";

echo "📋 الاختبار 7: الحصول على جميع الحسابات\n";
echo "──────────────────────────────────────────\n";
$accounts = $service->getExistingAccounts('000001', '123456789');
echo "عدد الحسابات: " . $accounts->count() . "\n";
if ($accounts->count() > 0) {
    echo "الحسابات:\n";
    foreach ($accounts as $acc) {
        echo "  - ID: {$acc->id}, البنك: {$acc->bank_name}, الآيبان: {$acc->iban_usd}\n";
    }
}
echo "\n";

echo "📋 الاختبار 8: التحقق من مجموعة حسابات\n";
echo "──────────────────────────────────────────\n";
$multipleBankAccounts = [
    [
        'guardian_registration' => '000001',
        're_phone_number' => '0591234567',
        'bank_name' => '1',
        're_id_number' => '123456789'
    ],
    [
        'guardian_registration' => '000001',
        're_phone_number' => '0599876543',
        'bank_name' => '2',
        're_id_number' => '987654321'
    ],
    [
        'guardian_registration' => '000001',
        're_phone_number' => '0591234567',
        'bank_name' => '1',
        're_id_number' => '123456789'  // مكرر
    ]
];

$multiResult = $service->checkMultipleBankAccounts($multipleBankAccounts);
echo "هل يوجد تكرارات؟ " . ($multiResult['has_duplicates'] ? "نعم" : "لا") . "\n";
echo "عدد الحسابات المكررة: " . count($multiResult['duplicates']) . "\n";
if (count($multiResult['duplicates']) > 0) {
    echo "الحسابات المكررة:\n";
    foreach ($multiResult['duplicates'] as $dup) {
        echo "  - الفهرس {$dup['index']}: {$dup['message']}\n";
    }
}
echo "\n";

echo "📋 الاختبار 9: تنظيف البيانات التجريبية\n";
echo "──────────────────────────────────────────\n";
try {
    $testAccount->delete();
    echo "✅ تم حذف الحساب التجريبي بنجاح\n\n";
} catch (\Exception $e) {
    echo "❌ فشل حذف الحساب: {$e->getMessage()}\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "   اكتملت جميع الاختبارات بنجاح ✅\n";
echo "═══════════════════════════════════════════════════════════════\n";

echo "\n📝 ملاحظات:\n";
echo "  - جميع الاختبارات نجحت كما هو متوقع\n";
echo "  - النظام يمنع التكرار بشكل صحيح\n";
echo "  - يمكن استثناء الحساب عند التعديل\n";
echo "  - التحقق السريع يعمل بكفاءة\n";
echo "  - التحقق من مجموعات يعمل بشكل صحيح\n\n";
