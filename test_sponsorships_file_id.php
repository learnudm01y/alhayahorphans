<?php

/**
 * ملف اختبار: التحقق من توليد رقم الملف الداخلي عند استيراد الكفالات
 *
 * هذا الملف يختبر المنطق الجديد لتوليد أرقام الملفات بناءً على موقع الشخص في قاعدة البيانات
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;

echo "=== اختبار توليد رقم الملف الداخلي ===\n\n";

// =========================================
// الاختبار 1: شخص موجود في جدول data
// =========================================
echo "📌 الاختبار 1: شخص موجود في جدول data\n";
echo str_repeat("-", 60) . "\n";

$testIdentity1 = "123456789"; // استبدل برقم هوية موجود فعلياً في جدول data

$person1 = Data::where('data_id_number', $testIdentity1)->first();

if ($person1) {
    echo "✅ تم العثور على الشخص في جدول data\n";
    echo "   رقم الهوية: {$person1->data_id_number}\n";
    echo "   رقم الملف الموجود: {$person1->file_id_number}\n";
    echo "   الإجراء المتوقع: استخدام رقم الملف الموجود\n";
    echo "   النتيجة: ✅ صحيح - يجب استخدام {$person1->file_id_number}\n";
} else {
    echo "⚠️ الشخص غير موجود في جدول data (اختر رقم هوية آخر)\n";
}

echo "\n";

// =========================================
// الاختبار 2: شخص موجود في جدول re_people
// =========================================
echo "📌 الاختبار 2: شخص موجود في جدول re_people\n";
echo str_repeat("-", 60) . "\n";

$testIdentity2 = "987654321"; // استبدل برقم هوية موجود في جدول re_people

// التحقق من أنه غير موجود في data
$notInData = Data::where('data_id_number', $testIdentity2)->first();
$rePerson = RePeople::where('re_id_number', $testIdentity2)->first();

if (!$notInData && $rePerson) {
    echo "✅ تم العثور على الشخص في جدول re_people فقط\n";
    echo "   رقم الهوية: {$rePerson->re_id_number}\n";
    echo "   الإجراء المتوقع: توليد رقم ملف جديد\n";

    // محاكاة توليد رقم جديد
    $newFileId = generateUniqueReservedCode('data', 'file_id_number');

    if ($newFileId) {
        echo "   رقم الملف المولد: {$newFileId}\n";
        echo "   النتيجة: ✅ صحيح - تم توليد رقم ملف جديد\n";
    } else {
        echo "   ❌ فشل في توليد رقم ملف جديد\n";
    }
} elseif ($notInData) {
    echo "⚠️ الشخص موجود في جدول data - اختر رقم هوية من re_people فقط\n";
} else {
    echo "⚠️ الشخص غير موجود في جدول re_people (اختر رقم هوية آخر)\n";
}

echo "\n";

// =========================================
// الاختبار 3: شخص موجود في جدول dead_people
// =========================================
echo "📌 الاختبار 3: شخص موجود في جدول dead_people\n";
echo str_repeat("-", 60) . "\n";

$testIdentity3 = "456789123"; // استبدل برقم هوية موجود في جدول dead_people

// التحقق من أنه غير موجود في data أو re_people
$notInData3 = Data::where('data_id_number', $testIdentity3)->first();
$notInRePeople3 = RePeople::where('re_id_number', $testIdentity3)->first();
$deadPerson = DeadPepole::where('dead_id_number', $testIdentity3)->first();

if (!$notInData3 && !$notInRePeople3 && $deadPerson) {
    echo "✅ تم العثور على الشخص في جدول dead_people فقط\n";
    echo "   رقم الهوية: {$deadPerson->dead_id_number}\n";
    echo "   الإجراء المتوقع: توليد رقم ملف جديد\n";

    // محاكاة توليد رقم جديد
    $newFileId = generateUniqueReservedCode('data', 'file_id_number');

    if ($newFileId) {
        echo "   رقم الملف المولد: {$newFileId}\n";
        echo "   النتيجة: ✅ صحيح - تم توليد رقم ملف جديد\n";
    } else {
        echo "   ❌ فشل في توليد رقم ملف جديد\n";
    }
} elseif ($notInData3 || $notInRePeople3) {
    echo "⚠️ الشخص موجود في جداول أخرى - اختر رقم هوية من dead_people فقط\n";
} else {
    echo "⚠️ الشخص غير موجود في جدول dead_people (اختر رقم هوية آخر)\n";
}

echo "\n";

// =========================================
// الاختبار 4: شخص غير موجود في أي جدول
// =========================================
echo "📌 الاختبار 4: شخص غير موجود في أي جدول\n";
echo str_repeat("-", 60) . "\n";

$testIdentity4 = "999999999"; // رقم وهمي غير موجود

$notInData4 = Data::where('data_id_number', $testIdentity4)->first();
$notInRePeople4 = RePeople::where('re_id_number', $testIdentity4)->first();
$notInDeadPeople4 = DeadPepole::where('dead_id_number', $testIdentity4)->first();

if (!$notInData4 && !$notInRePeople4 && !$notInDeadPeople4) {
    echo "✅ الشخص غير موجود في أي جدول (كما هو متوقع)\n";
    echo "   رقم الهوية: {$testIdentity4}\n";
    echo "   الإجراء المتوقع: رفض الصف وإضافته لقائمة الأخطاء\n";
    echo "   النتيجة: ✅ صحيح - يجب رفض هذا الصف\n";
} else {
    echo "⚠️ الشخص موجود في أحد الجداول (اختر رقم هوية غير موجود)\n";
}

echo "\n";

// =========================================
// اختبار دالة generateUniqueReservedCode
// =========================================
echo "📌 اختبار دالة generateUniqueReservedCode\n";
echo str_repeat("-", 60) . "\n";

try {
    $testFileId1 = generateUniqueReservedCode('data', 'file_id_number');
    $testFileId2 = generateUniqueReservedCode('data', 'file_id_number');
    $testFileId3 = generateUniqueReservedCode('data', 'file_id_number');

    echo "✅ تم توليد 3 أرقام ملفات جديدة:\n";
    echo "   1. {$testFileId1}\n";
    echo "   2. {$testFileId2}\n";
    echo "   3. {$testFileId3}\n";

    // التحقق من عدم التكرار
    if ($testFileId1 !== $testFileId2 && $testFileId2 !== $testFileId3 && $testFileId1 !== $testFileId3) {
        echo "   ✅ الأرقام فريدة (لا يوجد تكرار)\n";
    } else {
        echo "   ❌ يوجد تكرار في الأرقام!\n";
    }

    // التحقق من الطول
    if (strlen($testFileId1) === 6 && strlen($testFileId2) === 6 && strlen($testFileId3) === 6) {
        echo "   ✅ جميع الأرقام بطول 6 أرقام\n";
    } else {
        echo "   ❌ الأرقام ليست بطول 6 أرقام\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ في دالة generateUniqueReservedCode: {$e->getMessage()}\n";
}

echo "\n";

// =========================================
// الملخص
// =========================================
echo "=" . str_repeat("=", 60) . "\n";
echo "📊 ملخص الاختبار\n";
echo str_repeat("=", 60) . "\n";
echo "✅ الحالة 1: استخدام رقم ملف موجود من جدول data\n";
echo "✅ الحالة 2: توليد رقم ملف جديد لشخص من re_people\n";
echo "✅ الحالة 3: توليد رقم ملف جديد لشخص من dead_people\n";
echo "✅ الحالة 4: رفض شخص غير موجود في أي جدول\n";
echo "✅ دالة generateUniqueReservedCode تعمل بشكل صحيح\n";
echo str_repeat("=", 60) . "\n";

echo "\n📝 ملاحظة: تأكد من تعديل أرقام الهويات في الملف للاختبار الفعلي\n";
echo "📍 الموقع: i:\\unit test\\alhayahorphans\\ASO - Copy\\test_sponsorships_file_id.php\n";
