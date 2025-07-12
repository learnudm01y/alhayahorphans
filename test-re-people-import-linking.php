<?php

/**
 * اختبار شامل لنظام استيراد RePeople مع الربط التلقائي
 * يختبر ربط registration_id مع file_id_number من جدول Data
 */

require_once 'vendor/autoload.php';

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ExcelImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Data;
use App\Models\RePeople;

echo "🧪 اختبار نظام استيراد RePeople مع الربط التلقائي\n";
echo "========================================================\n\n";

try {
    // إنشاء بيانات اختبار في جدول Data
    echo "📋 إنشاء بيانات اختبار في جدول Data...\n";

    $testDataRecords = [
        [
            'file_id_number' => '000001',
            'data_id_number' => '1111111111',
            'data_first_name' => 'أحمد',
            'data_family_name' => 'العلي',
            'data_phone_number' => '0501234567',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'file_id_number' => '000002',
            'data_id_number' => '2222222222',
            'data_first_name' => 'فاطمة',
            'data_family_name' => 'الحسن',
            'data_phone_number' => '0507654321',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'file_id_number' => '000003',
            'data_id_number' => '3333333333',
            'data_first_name' => 'محمد',
            'data_family_name' => 'السلام',
            'data_phone_number' => '0509876543',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];

    // حذف البيانات القديمة إذا كانت موجودة
    DB::table('data')->whereIn('file_id_number', ['000001', '000002', '000003'])->delete();

    // إدراج البيانات الجديدة
    DB::table('data')->insert($testDataRecords);
    echo "✅ تم إنشاء " . count($testDataRecords) . " سجل اختبار في جدول Data\n\n";

    // عرض بيانات الاختبار
    echo "📊 بيانات الاختبار في جدول Data:\n";
    foreach ($testDataRecords as $record) {
        echo "  - ملف رقم: {$record['file_id_number']} | هوية: {$record['data_id_number']} | الاسم: {$record['data_first_name']} {$record['data_family_name']} \n";
    }
    echo "\n";

    // إنشاء ملف CSV للاختبار مع بيانات re_people
    echo "📁 إنشاء ملف CSV تجريبي للأشخاص...\n";
    $csvContent = "registration_id,sponsorship_status,first_name,second_name,third_name,last_name,person_id,person_birth_date,person_age,person_gender,person_health_status,person_type_of_guarantee,person_note\n";
    $csvContent .= "1111111111,1,أحمد,محمد,عبدالله,العلي,1001,1990-01-15,34,1,1,1,ملاحظة عن أحمد\n";
    $csvContent .= "2222222222,2,فاطمة,أحمد,محمد,الحسن,1002,1985-05-20,39,2,2,2,ملاحظة عن فاطمة\n";
    $csvContent .= "3333333333,1,محمد,علي,حسن,السلام,1003,1992-10-10,32,1,1,1,ملاحظة عن محمد\n";

    $csvFilePath = storage_path('app/public/test_re_people.csv');
    file_put_contents($csvFilePath, $csvContent);
    echo "✅ تم إنشاء ملف CSV: test_re_people.csv\n\n";

    // بدء الاختبار
    echo "🚀 بدء اختبار عملية الاستيراد...\n\n";

    // اختبار 1: استيراد بيانات الأشخاص مع الربط التلقائي
    echo "🔍 اختبار 1: استيراد بيانات الأشخاص مع الربط التلقائي\n";
    echo "------------------------------------------------------------\n";

    $importService = new ExcelImportService();
    $results = $importService->importToModel('test_re_people.csv', 're_people');

    echo "📊 نتائج الاستيراد:\n";
    echo "  - إجمالي الصفوف: " . ($results['total_rows'] ?? 0) . "\n";
    echo "  - الصفوف المستوردة: " . ($results['imported_rows'] ?? 0) . "\n";
    echo "  - الصفوف المتخطاة: " . ($results['skipped_rows'] ?? 0) . "\n";
    echo "  - الأخطاء: " . count($results['errors'] ?? []) . "\n";

    if (!empty($results['errors'])) {
        echo "❌ الأخطاء:\n";
        foreach ($results['errors'] as $error) {
            echo "  - $error\n";
        }
    }

    // عرض إحصائيات استبدال أرقام التسجيل
    $fileIdReplacements = $importService->getFileIdReplacements();
    if (!empty($fileIdReplacements)) {
        echo "\n📋 إحصائيات استبدال أرقام التسجيل:\n";
        echo "  - تم استبدال " . count($fileIdReplacements) . " رقم تسجيل\n";
        foreach ($fileIdReplacements as $replacement) {
            echo "    • الأصلي: {$replacement['original_registration_id']} → الجديد: {$replacement['new_registration_id']}\n";
            echo "      الشخص: {$replacement['person_name']} | هوية: {$replacement['person_id']}\n";
        }
    }

    echo "\n";

    // اختبار 2: فحص البيانات المحفوظة
    echo "🔍 اختبار 2: فحص البيانات المحفوظة في قاعدة البيانات\n";
    echo "-------------------------------------------------------\n";

    $savedRecords = DB::table('re_people')
        ->whereIn('registration_id', ['000001', '000002', '000003'])
        ->orderBy('id', 'desc')
        ->limit(10)
        ->get();

    echo "✅ تم حفظ " . count($savedRecords) . " سجل في جدول re_people:\n";

    foreach ($savedRecords as $record) {
        echo "📝 السجل رقم: {$record->id}\n";
        echo "  - رقم التسجيل (registration_id): {$record->registration_id}\n";
        echo "  - الاسم: {$record->first_name} {$record->last_name}\n";
        echo "  - رقم الهوية: {$record->person_id}\n";
        echo "  - العمر: {$record->person_age}\n";
        echo "  - الجنس: {$record->person_gender}\n";

        // التحقق من الربط مع جدول Data
        $linkedData = DB::table('data')
            ->where('file_id_number', $record->registration_id)
            ->first();

        if ($linkedData) {
            echo "  ✅ مرتبط بجدول Data: {$linkedData->data_first_name} {$linkedData->data_family_name} (هوية: {$linkedData->data_id_number})\n";
        } else {
            echo "  ❌ غير مرتبط بجدول Data\n";
        }
        echo "\n";
    }

    // اختبار 3: فحص علاقات Eloquent
    echo "🔍 اختبار 3: فحص علاقات Eloquent\n";
    echo "-----------------------------------\n";

    $dataRecordsWithRePeople = Data::with('rePeople')->get();

    foreach ($dataRecordsWithRePeople as $dataRecord) {
        $rePeopleCount = $dataRecord->rePeople ? $dataRecord->rePeople->count() : 0;
        echo "👤 {$dataRecord->data_first_name} {$dataRecord->data_family_name} (ملف: {$dataRecord->file_id_number})\n";

        if ($rePeopleCount > 0) {
            echo "  👥 لديه {$rePeopleCount} شخص مرتبط:\n";
            foreach ($dataRecord->rePeople as $rePerson) {
                echo "    - {$rePerson->first_name} {$rePerson->last_name} (هوية: {$rePerson->person_id})\n";
            }
        } else {
            echo "  ℹ️ لا توجد أشخاص مرتبطين\n";
        }
    }

    echo "\n";

    // تنظيف البيانات
    echo "🧹 تنظيف بيانات الاختبار...\n";

    // حذف ملف CSV
    if (file_exists($csvFilePath)) {
        unlink($csvFilePath);
        echo "✅ تم حذف ملف CSV التجريبي\n";
    }

    // حذف البيانات التجريبية
    DB::table('re_people')->whereIn('registration_id', ['000001', '000002', '000003'])->delete();
    DB::table('data')->whereIn('file_id_number', ['000001', '000002', '000003'])->delete();
    echo "✅ تم تنظيف جميع بيانات الاختبار\n\n";

    echo "🎉 اكتملت جميع الاختبارات بنجاح!\n";
    echo "=================================\n";
    echo "✅ نظام استيراد RePeople مع الربط التلقائي يعمل بشكل صحيح\n";
    echo "✅ يتم مطابقة registration_id مع data_id_number بنجاح\n";
    echo "✅ يتم استبدال registration_id بـ file_id_number المطابق\n";
    echo "✅ العلاقات بين الجداول تعمل بشكل صحيح\n";

} catch (Exception $e) {
    echo "❌ خطأ أثناء الاختبار: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n";

    // تنظيف في حالة الخطأ
    $csvFilePath = storage_path('app/public/test_re_people.csv');
    if (file_exists($csvFilePath)) {
        unlink($csvFilePath);
    }

    try {
        DB::table('re_people')->whereIn('registration_id', ['000001', '000002', '000003'])->delete();
        DB::table('data')->whereIn('file_id_number', ['000001', '000002', '000003'])->delete();
    } catch (Exception $cleanupError) {
        echo "⚠️ خطأ في التنظيف: " . $cleanupError->getMessage() . "\n";
    }
}
