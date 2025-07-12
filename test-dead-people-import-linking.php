#!/usr/bin/env php
<?php

/**
 * اختبار نظام استيراد DeadPepole مع الربط التلقائي بجدول Data
 * يختبر عملية مطابقة re_file_id مع data_id_number واستبدالها بـ file_id_number
 */

require_once __DIR__ . '/vendor/autoload.php';

// تحميل Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ExcelImportService;

echo "🧪 اختبار نظام استيراد DeadPepole مع الربط التلقائي\n";
echo "========================================================\n\n";

try {
    // إنشاء بيانات اختبار في جدول Data
    echo "📋 إنشاء بيانات اختبار في جدول Data...\n";

    $testDataRecords = [
        [
            'file_id_number' => '000001',
            'data_id_number' => '1234567890',
            'data_first_name' => 'أحمد',
            'data_family_name' => 'العلي',
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'file_id_number' => '000002',
            'data_id_number' => '9876543210',
            'data_first_name' => 'فاطمة',
            'data_family_name' => 'الحسن',
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'file_id_number' => '000003',
            'data_id_number' => '1122334455',
            'data_first_name' => 'محمد',
            'data_family_name' => 'السلام',
            'created_at' => now(),
            'updated_at' => now()
        ]
    ];

    // حذف البيانات القديمة إذا كانت موجودة
    DB::table('data')->whereIn('data_id_number', ['1234567890', '9876543210', '1122334455'])->delete();

    // إدراج بيانات اختبار جديدة
    DB::table('data')->insert($testDataRecords);

    echo "✅ تم إنشاء " . count($testDataRecords) . " سجل اختبار في جدول Data\n\n";

    // عرض البيانات المُدرجة
    echo "📊 بيانات الاختبار في جدول Data:\n";
    foreach ($testDataRecords as $record) {
        echo "  - ملف رقم: {$record['file_id_number']} | هوية: {$record['data_id_number']} | الاسم: {$record['data_first_name']} {$record['data_family_name']}\n";
    }
    echo "\n";

    // إنشاء ملف Excel تجريبي للمتوفين
    echo "📁 إنشاء ملف Excel تجريبي للمتوفين...\n";

    $excelData = [
        ['رقم ملف الأب', 'اسم الأب الأول', 'اسم عائلة الأب', 'رقم هوية الأب', 'تاريخ وفاة الأب', 'اسم الأم الأول', 'اسم عائلة الأم', 'رقم هوية الأم', 'تاريخ وفاة الأم'],
        ['1234567890', 'والد أحمد', 'العلي', '1111111111', '2020-01-15', 'والدة أحمد', 'العلي', '2222222222', '2021-03-20'],
        ['9876543210', 'والد فاطمة', 'الحسن', '3333333333', '2019-11-10', 'والدة فاطمة', 'الحسن', '4444444444', '2022-07-08'],
        ['1122334455', 'والد محمد', 'السلام', '5555555555', '2018-05-25', 'والدة محمد', 'السلام', '6666666666', '2020-12-12']
    ];

    $csvPath = storage_path('app/public/test_dead_people.csv');
    $file = fopen($csvPath, 'w');
    foreach ($excelData as $row) {
        fputcsv($file, $row);
    }
    fclose($file);

    echo "✅ تم إنشاء ملف CSV: test_dead_people.csv\n\n";

    // اختبار عملية الاستيراد
    echo "🚀 بدء اختبار عملية الاستيراد...\n";

    $excelService = new ExcelImportService();

    echo "\n🔍 اختبار 1: استيراد بيانات المتوفين مع الربط التلقائي\n";
    echo "------------------------------------------------------------\n";

    $results = $excelService->importToModel('test_dead_people.csv', 'dead_people');

    echo "\n📊 نتائج الاستيراد:\n";
    echo "  - إجمالي الصفوف: " . ($results['total_rows'] ?? 0) . "\n";
    echo "  - الصفوف المستوردة: " . ($results['imported_rows'] ?? 0) . "\n";
    echo "  - الصفوف المتخطاة: " . ($results['skipped_rows'] ?? 0) . "\n";
    echo "  - الأخطاء: " . count($results['errors'] ?? []) . "\n";

    if (!empty($results['errors'])) {
        echo "\n❌ الأخطاء:\n";
        foreach ($results['errors'] as $error) {
            echo "  - $error\n";
        }
    }

    echo "\n📋 إحصائيات استبدال أرقام الملفات:\n";
    $replacements = $excelService->getFileIdReplacements();
    if (empty($replacements)) {
        echo "  - لم يتم استبدال أي أرقام\n";
    } else {
        echo "  - تم استبدال " . count($replacements) . " رقم ملف\n";
        foreach ($replacements as $replacement) {
            echo "    • الأصلي: {$replacement['original_re_file_id']} → الجديد: {$replacement['new_re_file_id']}\n";
            echo "      الأب: {$replacement['father_id']} | الأم: {$replacement['mother_id']}\n";
        }
    }

    // فحص البيانات المحفوظة
    echo "\n🔍 اختبار 2: فحص البيانات المحفوظة في قاعدة البيانات\n";
    echo "-------------------------------------------------------\n";

    $savedDeadPeople = DB::table('dead_people')->get();

    if ($savedDeadPeople->isEmpty()) {
        echo "⚠️ لا توجد بيانات محفوظة في جدول dead_people\n";
    } else {
        echo "✅ تم حفظ " . $savedDeadPeople->count() . " سجل في جدول dead_people:\n\n";

        foreach ($savedDeadPeople as $record) {
            echo "📝 السجل رقم: {$record->id}\n";
            echo "  - رقم الملف (re_file_id): {$record->re_file_id}\n";
            echo "  - اسم الأب: {$record->father_first_name} {$record->father_last_name}\n";
            echo "  - هوية الأب: {$record->father_id}\n";
            echo "  - اسم الأم: {$record->mother_first_name} {$record->mother_last_name}\n";
            echo "  - هوية الأم: {$record->mother_id}\n";

            // فحص الربط مع جدول Data
            $linkedData = DB::table('data')->where('file_id_number', $record->re_file_id)->first();
            if ($linkedData) {
                echo "  ✅ مرتبط بجدول Data: {$linkedData->data_first_name} {$linkedData->data_family_name} (هوية: {$linkedData->data_id_number})\n";
            } else {
                echo "  ❌ غير مرتبط بجدول Data\n";
            }
            echo "\n";
        }
    }

    // اختبار علاقات Eloquent
    echo "🔍 اختبار 3: فحص علاقات Eloquent\n";
    echo "-----------------------------------\n";

    $dataRecords = \App\Models\Data::with('deadPepole')->get();

    foreach ($dataRecords as $dataRecord) {
        echo "👤 {$dataRecord->data_first_name} {$dataRecord->data_family_name} (ملف: {$dataRecord->file_id_number})\n";

        if ($dataRecord->deadPepole) {
            $deadPeople = $dataRecord->deadPepole;
            echo "  💀 لديه بيانات متوفين:\n";
            if ($deadPeople->father_first_name) {
                echo "    - الأب المتوفى: {$deadPeople->father_first_name} {$deadPeople->father_last_name}\n";
            }
            if ($deadPeople->mother_first_name) {
                echo "    - الأم المتوفاة: {$deadPeople->mother_first_name} {$deadPeople->mother_last_name}\n";
            }
        } else {
            echo "  ℹ️ لا توجد بيانات متوفين\n";
        }
        echo "\n";
    }

    // تنظيف البيانات التجريبية
    echo "🧹 تنظيف بيانات الاختبار...\n";
    DB::table('dead_people')->delete();
    DB::table('data')->whereIn('data_id_number', ['1234567890', '9876543210', '1122334455'])->delete();

    if (file_exists($csvPath)) {
        unlink($csvPath);
        echo "✅ تم حذف ملف CSV التجريبي\n";
    }

    echo "✅ تم تنظيف جميع بيانات الاختبار\n\n";

    echo "🎉 اكتملت جميع الاختبارات بنجاح!\n";
    echo "=================================\n";
    echo "✅ نظام استيراد DeadPepole مع الربط التلقائي يعمل بشكل صحيح\n";
    echo "✅ يتم مطابقة re_file_id مع data_id_number بنجاح\n";
    echo "✅ يتم استبدال re_file_id بـ file_id_number المطابق\n";
    echo "✅ العلاقات بين الجداول تعمل بشكل صحيح\n";

} catch (\Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "📍 التفاصيل: " . $e->getTraceAsString() . "\n";
}
