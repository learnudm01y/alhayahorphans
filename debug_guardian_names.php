<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// تكوين قاعدة البيانات
$capsule = new DB;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'aso',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== فحص المشكلة الفعلية في تخزين أسماء المعيل ===\n\n";

// 1. فحص كفالة حقيقية من قاعدة البيانات
echo "🔍 البحث عن كفالة تحتوي على guardian_name...\n";
$sponsorship = DB::table('sponsorships')
    ->whereNotNull('guardian_name')
    ->where('guardian_name', '!=', '')
    ->first();

if ($sponsorship) {
    echo "✅ تم العثور على كفالة: {$sponsorship->id}\n";
    echo "guardian_name: '{$sponsorship->guardian_name}'\n";
    echo "relation_id_number: " . ($sponsorship->relation_id_number ?? 'NULL') . "\n";
    echo "person_type: " . ($sponsorship->person_type ?? 'NULL') . "\n\n";

    // 2. فحص البيانات المقترنة في جدول data
    if ($sponsorship->relation_id_number) {
        echo "🔍 فحص البيانات في جدول data...\n";
        $dataRecord = DB::table('data')
            ->where('file_id_number', $sponsorship->relation_id_number)
            ->first();

        if ($dataRecord) {
            echo "✅ تم العثور على سجل data:\n";
            echo "  data_first_name: '" . ($dataRecord->data_first_name ?? '') . "'\n";
            echo "  data_father_name: '" . ($dataRecord->data_father_name ?? '') . "'\n";
            echo "  data_grand_father_name: '" . ($dataRecord->data_grand_father_name ?? '') . "'\n";
            echo "  data_family_name: '" . ($dataRecord->data_family_name ?? '') . "'\n\n";

            // 3. تحليل المشكلة
            $hasAllNameParts = !empty($dataRecord->data_first_name) &&
                              !empty($dataRecord->data_father_name) &&
                              !empty($dataRecord->data_grand_father_name) &&
                              !empty($dataRecord->data_family_name);

            if ($hasAllNameParts) {
                echo "✅ الاسم محفوظ في 4 حقول بشكل صحيح\n";
            } else {
                echo "❌ المشكلة: الاسم غير محفوظ في 4 حقول!\n";
                echo "🔧 سبب المشكلة المحتمل:\n";
                echo "   - الكود لا يُستخدم في جميع الحالات\n";
                echo "   - هناك طريقة أخرى لتحديث البيانات تتجاهل التقسيم\n";
                echo "   - البيانات محدثة من مكان آخر غير SponsorshipSyncController\n\n";
            }
        } else {
            echo "❌ لم يتم العثور على سجل data مقترن!\n\n";
        }
    } else {
        echo "❌ لا يوجد relation_id_number!\n\n";
    }

    // 4. فحص dead_people إذا كان الشخص متوفي
    if (in_array($sponsorship->person_type ?? '', ['deceased_father', 'deceased_mother'])) {
        echo "🔍 فحص البيانات في جدول dead_people...\n";
        $deadRecord = DB::table('dead_people')
            ->where(function($q) use ($sponsorship) {
                $q->where('father_id', $sponsorship->identity_number)
                  ->orWhere('mother_id', $sponsorship->identity_number);
            })
            ->first();

        if ($deadRecord) {
            echo "✅ تم العثور على سجل dead_people:\n";
            if ($sponsorship->person_type === 'deceased_father') {
                echo "  father_first_name: '" . ($deadRecord->father_first_name ?? '') . "'\n";
                echo "  father_second_name: '" . ($deadRecord->father_second_name ?? '') . "'\n";
                echo "  father_third_name: '" . ($deadRecord->father_third_name ?? '') . "'\n";
                echo "  father_last_name: '" . ($deadRecord->father_last_name ?? '') . "'\n";
            } else {
                echo "  mother_first_name: '" . ($deadRecord->mother_first_name ?? '') . "'\n";
                echo "  mother_second_name: '" . ($deadRecord->mother_second_name ?? '') . "'\n";
                echo "  mother_third_name: '" . ($deadRecord->mother_third_name ?? '') . "'\n";
                echo "  mother_last_name: '" . ($deadRecord->mother_last_name ?? '') . "'\n";
            }
        } else {
            echo "❌ لم يتم العثور على سجل dead_people!\n";
        }
    }

    // 5. محاكاة التحديث لإصلاح المشكلة
    echo "\n🔧 محاكاة إصلاح المشكلة...\n";

    if (!empty($sponsorship->guardian_name)) {
        $fullName = trim($sponsorship->guardian_name);
        $nameParts = explode(' ', $fullName);
        $nameParts = array_filter($nameParts);
        $nameParts = array_values($nameParts);

        echo "الاسم الكامل: '$fullName'\n";
        echo "أجزاء الاسم:\n";
        echo "  [0]: " . ($nameParts[0] ?? '') . "\n";
        echo "  [1]: " . ($nameParts[1] ?? '') . "\n";
        echo "  [2]: " . ($nameParts[2] ?? '') . "\n";
        echo "  [3]: " . ($nameParts[3] ?? '') . "\n\n";

        // التحديث الإجباري في data
        if ($sponsorship->relation_id_number) {
            $updates = [
                'data_first_name' => $nameParts[0] ?? '',
                'data_father_name' => $nameParts[1] ?? '',
                'data_grand_father_name' => $nameParts[2] ?? '',
                'data_family_name' => $nameParts[3] ?? '',
                'updated_at' => now()
            ];

            $result = DB::table('data')
                ->where('file_id_number', $sponsorship->relation_id_number)
                ->update($updates);

            echo "✅ تم إصلاح البيانات في جدول data\n";
            echo "عدد الصفوف المحدثة: $result\n";

            // فحص النتيجة النهائية
            $updatedData = DB::table('data')
                ->where('file_id_number', $sponsorship->relation_id_number)
                ->first();

            if ($updatedData) {
                echo "\n📋 البيانات بعد الإصلاح:\n";
                echo "  data_first_name: '{$updatedData->data_first_name}'\n";
                echo "  data_father_name: '{$updatedData->data_father_name}'\n";
                echo "  data_grand_father_name: '{$updatedData->data_grand_father_name}'\n";
                echo "  data_family_name: '{$updatedData->data_family_name}'\n";
            }
        }
    }

} else {
    echo "❌ لم يتم العثور على كفالة تحتوي على guardian_name!\n";
}

echo "\n=== انتهى الفحص ===\n";
