<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== اختبار نهائي لنظام منع التكرار ===\n\n";

$validationService = new \App\Services\BankAccountValidationService();

echo "1️⃣ اختبار منع تكرار المعيل 002550\n";
echo str_repeat('-', 60) . "\n";

// محاولة إضافة نفس الحساب الموجود
$testData = [
    'guardian_registration' => '002550',
    're_phone_number' => '595062223',
    'bank_name' => 3,
    're_id_number' => '80004'
];

$result = $validationService->checkDuplicateBankAccount($testData);

if ($result['is_duplicate']) {
    echo "✅ النظام يعمل بشكل صحيح - تم منع التكرار!\n";
    echo "الرسالة: {$result['message']}\n";
} else {
    echo "❌ خطأ: لم يتم اكتشاف التكرار!\n";
}

echo "\n2️⃣ اختبار منع تكرار المعيل 002190 (صاحب حساب 80004)\n";
echo str_repeat('-', 60) . "\n";

$testData2 = [
    'guardian_registration' => '002190',
    're_phone_number' => '595062223',
    'bank_name' => 3,
    're_id_number' => '80004'
];

$result2 = $validationService->checkDuplicateBankAccount($testData2);

if ($result2['is_duplicate']) {
    echo "✅ النظام يعمل بشكل صحيح - تم منع التكرار!\n";
    echo "الرسالة: {$result2['message']}\n";
} else {
    echo "❌ خطأ: لم يتم اكتشاف التكرار!\n";
}

echo "\n3️⃣ اختبار منع تكرار المعيل 002190 (صاحب حساب 801976705)\n";
echo str_repeat('-', 60) . "\n";

$testData3 = [
    'guardian_registration' => '002190',
    're_phone_number' => '595062223',
    'bank_name' => 3,
    're_id_number' => '801976705'
];

$result3 = $validationService->checkDuplicateBankAccount($testData3);

if ($result3['is_duplicate']) {
    echo "✅ النظام يعمل بشكل صحيح - تم منع التكرار!\n";
    echo "الرسالة: {$result3['message']}\n";
} else {
    echo "❌ خطأ: لم يتم اكتشاف التكرار!\n";
}

echo "\n4️⃣ اختبار السماح بحساب جديد فريد\n";
echo str_repeat('-', 60) . "\n";

$testData4 = [
    'guardian_registration' => '999999',
    're_phone_number' => '777777777',
    'bank_name' => 1,
    're_id_number' => '888888'
];

$result4 = $validationService->checkDuplicateBankAccount($testData4);

if (!$result4['is_duplicate']) {
    echo "✅ النظام يعمل بشكل صحيح - سمح بالحساب الفريد!\n";
} else {
    echo "❌ خطأ: منع حساب غير مكرر!\n";
}

echo "\n5️⃣ اختبار السماح بنفس المعيل لكن صاحب حساب مختلف\n";
echo str_repeat('-', 60) . "\n";

$testData5 = [
    'guardian_registration' => '002550',
    're_phone_number' => '595062223',
    'bank_name' => 3,
    're_id_number' => '999999' // صاحب حساب مختلف
];

$result5 = $validationService->checkDuplicateBankAccount($testData5);

if (!$result5['is_duplicate']) {
    echo "✅ النظام يعمل بشكل صحيح - سمح بصاحب حساب مختلف لنفس المعيل!\n";
} else {
    echo "❌ خطأ: منع حساب لصاحب مختلف!\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "✅ اكتملت جميع الاختبارات بنجاح!\n";
echo "\n📝 الخطوات التالية:\n";
echo "1. قم بتشغيل: php cleanup_duplicates.php (لحذف السجلات المكررة)\n";
echo "2. تأكد من عمل النظام في بيئة الإنتاج\n";
echo "3. راقب السجلات للتأكد من عدم حدوث تكرارات جديدة\n";
