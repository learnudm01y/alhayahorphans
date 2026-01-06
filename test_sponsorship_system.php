<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Models\RePeople;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           اختبار نظام إضافة المكفول والمعيل\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. التحقق من عمود sponsored_birth_date
$hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('sponsorships', 'sponsored_birth_date');
echo "1️⃣  عمود sponsored_birth_date في sponsorships: " . ($hasColumn ? '✓ موجود' : '✗ غير موجود') . "\n";

// 2. التحقق من Data model
echo "\n2️⃣  اختبار إنشاء سجل في جدول data:\n";
$testFileId = 'TEST001';
$existingData = Data::where('file_id_number', $testFileId)->first();
if ($existingData) {
    echo "   السجل موجود بالفعل، سيتم حذفه...\n";
    $existingData->delete();
}

try {
    $newData = Data::create([
        'file_id_number' => $testFileId,
        'data_id_number' => 999999999,
        'data_first_name' => 'اختبار',
        'data_first_name_normalized' => 'اختبار',
        'data_father_name' => 'أب',
        'data_father_name_normalized' => 'اب',
        'data_grand_father_name' => 'جد',
        'data_grand_father_name_normalized' => 'جد',
        'data_family_name' => 'عائلة',
        'data_family_name_normalized' => 'عائله',
        'data_section_id' => 1,
        'data_request_status' => 4,
        'data_user_insert_data' => 'Test',
    ]);
    echo "   ✓ تم إنشاء سجل Data بنجاح (ID: {$newData->id})\n";

    // حذف السجل التجريبي
    $newData->delete();
    echo "   ✓ تم حذف السجل التجريبي\n";
} catch (Exception $e) {
    echo "   ✗ خطأ: " . $e->getMessage() . "\n";
}

// 3. التحقق من RePeople model
echo "\n3️⃣  اختبار إنشاء سجل في جدول re_people:\n";
$existingRePerson = RePeople::where('person_id', 999999998)->first();
if ($existingRePerson) {
    echo "   السجل موجود بالفعل، سيتم حذفه...\n";
    $existingRePerson->delete();
}

try {
    $newRePerson = RePeople::create([
        'registration_id' => $testFileId,
        'person_id' => 999999998,
        'first_name' => 'مكفول',
        'first_name_normalized' => 'مكفول',
        'second_name' => 'ثاني',
        'second_name_normalized' => 'ثاني',
        'third_name' => 'ثالث',
        'third_name_normalized' => 'ثالث',
        'last_name' => 'رابع',
        'last_name_normalized' => 'رابع',
        'person_birth_date' => '2015-01-01',
    ]);
    echo "   ✓ تم إنشاء سجل RePeople بنجاح (ID: {$newRePerson->id})\n";

    // حذف السجل التجريبي
    $newRePerson->delete();
    echo "   ✓ تم حذف السجل التجريبي\n";
} catch (Exception $e) {
    echo "   ✗ خطأ: " . $e->getMessage() . "\n";
}

// 4. اختبار توليد رقم ملف فريد
echo "\n4️⃣  اختبار توليد رقم ملف فريد:\n";
if (function_exists('generateUniqueReservedCode')) {
    $uniqueCode = generateUniqueReservedCode('data', 'file_id_number');
    echo "   ✓ تم توليد رقم ملف فريد: $uniqueCode\n";
} else {
    echo "   ✗ الدالة generateUniqueReservedCode غير موجودة\n";
}

echo "\n✅ اكتمل الاختبار!\n";
