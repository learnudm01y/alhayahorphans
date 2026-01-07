<?php
/**
 * اختبار تخزين المتوفين الإضافيين
 * هذا الملف يختبر سبب عدم وصول البيانات إلى جدول additional_deceased
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AdditionalDeceased;

echo "═══════════════════════════════════════════════════════════════\n";
echo "       🔍 اختبار تشخيص مشكلة additional_deceased\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. التحقق من وجود الجدول
echo "1️⃣ التحقق من وجود جدول additional_deceased...\n";
$tableExists = \Illuminate\Support\Facades\Schema::hasTable('additional_deceased');
echo "   الجدول موجود: " . ($tableExists ? "✅ نعم" : "❌ لا") . "\n\n";

if (!$tableExists) {
    echo "❌ الجدول غير موجود! يجب تشغيل: php artisan migrate\n";
    exit(1);
}

// 2. عرض هيكل الجدول
echo "2️⃣ هيكل جدول additional_deceased:\n";
$columns = DB::select("DESCRIBE additional_deceased");
foreach ($columns as $column) {
    echo "   • {$column->Field} ({$column->Type}) " . ($column->Null === 'YES' ? 'nullable' : 'required') . "\n";
}
echo "\n";

// 3. عدد السجلات الحالية
echo "3️⃣ عدد السجلات الحالية في الجدول:\n";
$count = AdditionalDeceased::count();
echo "   العدد: {$count} سجل\n\n";

// 4. اختبار الإدخال المباشر
echo "4️⃣ اختبار الإدخال المباشر في قاعدة البيانات...\n";
try {
    $testRecord = AdditionalDeceased::create([
        're_file_id' => 'TEST-' . date('YmdHis'),
        'person_id' => '123456789',
        'first_name' => 'اختبار',
        'second_name' => 'اختبار2',
        'third_name' => 'اختبار3',
        'last_name' => 'اختباري',
        'relationship' => 'brother',
        'death_date' => '2025-01-01',
        'death_reason' => null,
    ]);
    echo "   ✅ تم إنشاء سجل اختباري بنجاح! ID: {$testRecord->id}\n";

    // حذف السجل الاختباري
    $testRecord->delete();
    echo "   ✅ تم حذف السجل الاختباري\n\n";
} catch (\Exception $e) {
    echo "   ❌ فشل الإدخال: " . $e->getMessage() . "\n\n";
}

// 5. التحقق من الـ fillable في Model
echo "5️⃣ التحقق من fillable في AdditionalDeceased Model:\n";
$model = new AdditionalDeceased();
$fillable = $model->getFillable();
echo "   الحقول القابلة للتعبئة:\n";
foreach ($fillable as $field) {
    echo "   • {$field}\n";
}
echo "\n";

// 6. محاكاة البيانات المرسلة من النموذج
echo "6️⃣ محاكاة البيانات المرسلة من النموذج:\n";
$simulatedFormData = [
    'additional_deceased' => [
        [
            're_file_id' => '003370',
            'id_number' => '123456789',
            'first_name' => 'أحمد',
            'second_name' => 'محمد',
            'third_name' => 'علي',
            'last_name' => 'خالد',
            'relationship' => 'brother',
            'death_date' => '2024-06-15',
            'death_reason' => '2',
        ]
    ]
];

echo "   البيانات المحاكاة:\n";
print_r($simulatedFormData);

// 7. اختبار منطق الكنترولر
echo "\n7️⃣ اختبار منطق الكنترولر:\n";
$additionalDeceased = $simulatedFormData['additional_deceased'];
$fileIdNumber = '003370-TEST';

if (is_array($additionalDeceased) && count($additionalDeceased) > 0) {
    echo "   ✅ البيانات موجودة (" . count($additionalDeceased) . " سجل)\n";

    foreach ($additionalDeceased as $index => $deceased) {
        $personId = $deceased['id_number'] ?? $deceased['person_id'] ?? null;

        echo "   📋 سجل #{$index}:\n";
        echo "      - رقم الهوية: {$personId}\n";
        echo "      - الاسم: {$deceased['first_name']} {$deceased['last_name']}\n";
        echo "      - صلة القرابة: {$deceased['relationship']}\n";

        // تجاهل السجلات الفارغة
        if (empty($deceased['first_name']) && empty($deceased['last_name']) && empty($personId)) {
            echo "      ⏭️ تجاهل (سجل فارغ)\n";
            continue;
        }

        // اختبار الإنشاء
        try {
            $record = AdditionalDeceased::create([
                're_file_id' => $fileIdNumber,
                'person_id' => $personId,
                'first_name' => $deceased['first_name'] ?? null,
                'second_name' => $deceased['second_name'] ?? null,
                'third_name' => $deceased['third_name'] ?? null,
                'last_name' => $deceased['last_name'] ?? null,
                'relationship' => $deceased['relationship'] ?? 'other',
                'death_date' => $deceased['death_date'] ?? null,
                'death_reason' => !empty($deceased['death_reason']) ? $deceased['death_reason'] : null,
            ]);
            echo "      ✅ تم الحفظ بنجاح! ID: {$record->id}\n";

            // حذف السجل الاختباري
            $record->delete();
            echo "      ✅ تم حذف السجل الاختباري\n";
        } catch (\Exception $e) {
            echo "      ❌ فشل الحفظ: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "   ❌ لا توجد بيانات!\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "                    📊 التشخيص النهائي\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";
echo "المشكلة: البيانات لا تصل من النموذج (الواجهة) إلى الكنترولر\n";
echo "\n";
echo "الأسباب المحتملة:\n";
echo "1. النموذج المولد ديناميكياً قد يكون خارج الـ <form>\n";
echo "2. مشكلة في اسم الحقول (name attribute)\n";
echo "3. JavaScript قد يمنع إرسال البيانات\n";
echo "4. الحقول قد تكون disabled\n";
echo "\n";
echo "الحل المقترح:\n";
echo "• التحقق من أن additionalDeceasedContainer داخل الـ form\n";
echo "• إضافة console.log في JavaScript قبل الإرسال\n";
echo "• التحقق من أن الحقول ليست disabled\n";
echo "\n";
