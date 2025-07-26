<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// تكوين قاعدة البيانات
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'aso',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== تحليل مشكلة البحث للاسم: Kyle Christine Byers Brenda Mason Nayda Ayers ===\n\n";

$searchName = "Kyle Christine Byers Brenda Mason Nayda Ayers";
$searchTerms = ["Kyle", "Christine", "Byers", "Brenda", "Mason", "Nayda", "Ayers"];

echo "1. التحقق من وجود البيانات في جدول data:\n";
echo "--------------------------------------------\n";

// البحث عن السجل المطابق
$exactRecord = Capsule::table('data')
    ->where('data_first_name', 'Kyle')
    ->orWhere('data_first_name', 'LIKE', '%Kyle%')
    ->get();

echo "البحث بـ Kyle في data_first_name: " . $exactRecord->count() . " نتيجة\n";

if ($exactRecord->count() > 0) {
    foreach ($exactRecord as $record) {
        $fullName = trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}");
        echo "ID: {$record->id} - الاسم: {$fullName}\n";
        echo "  data_first_name: '{$record->data_first_name}'\n";
        echo "  data_father_name: '{$record->data_father_name}'\n";
        echo "  data_grand_father_name: '{$record->data_grand_father_name}'\n";
        echo "  data_family_name: '{$record->data_family_name}'\n";
        echo "---\n";
    }
}

echo "\n2. اختبار استراتيجيات البحث المختلفة:\n";
echo "----------------------------------------\n";

// 1. البحث بـ CONCAT
echo "أ. البحث بـ CONCAT:\n";
$concatResults = Capsule::table('data')
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$searchName}%"])
    ->get();
echo "  النتائج: " . $concatResults->count() . "\n";

// 2. البحث بـ CONCAT مع أول كلمتين
$partialName = "Kyle Christine";
$concatPartialResults = Capsule::table('data')
    ->whereRaw("CONCAT(data_first_name, ' ', data_father_name, ' ', data_grand_father_name, ' ', data_family_name) LIKE ?", ["%{$partialName}%"])
    ->get();
echo "  البحث الجزئي (Kyle Christine): " . $concatPartialResults->count() . "\n";

// 3. البحث المرن (كل كلمة في أي حقل)
echo "\nب. البحث المرن:\n";
$flexibleQuery = Capsule::table('data');

foreach ($searchTerms as $term) {
    $flexibleQuery->where(function($q) use ($term) {
        $q->where('data_first_name', 'LIKE', "%{$term}%")
          ->orWhere('data_father_name', 'LIKE', "%{$term}%")
          ->orWhere('data_grand_father_name', 'LIKE', "%{$term}%")
          ->orWhere('data_family_name', 'LIKE', "%{$term}%");
    });
}

$flexibleResults = $flexibleQuery->get();
echo "  النتائج: " . $flexibleResults->count() . "\n";

// 4. البحث بكلمة واحدة فقط
echo "\nج. البحث بكلمة Kyle فقط:\n";
$singleWordResults = Capsule::table('data')
    ->where('data_first_name', 'LIKE', '%Kyle%')
    ->orWhere('data_father_name', 'LIKE', '%Kyle%')
    ->orWhere('data_grand_father_name', 'LIKE', '%Kyle%')
    ->orWhere('data_family_name', 'LIKE', '%Kyle%')
    ->get();
echo "  النتائج: " . $singleWordResults->count() . "\n";

// 5. فحص الشروط الإضافية (مقبول)
echo "\n3. فحص شرط requestStatus = مقبول:\n";
echo "-----------------------------------\n";

$withStatusFilter = Capsule::table('data')
    ->join('request_status', 'data.request_status_id', '=', 'request_status.id')
    ->where('request_status.description', 'مقبول')
    ->where('data_first_name', 'LIKE', '%Kyle%')
    ->count();

echo "السجلات مع Kyle وحالة مقبول: {$withStatusFilter}\n";

// فحص جميع حالات الطلبات
$allStatuses = Capsule::table('request_status')->get();
echo "\nجميع حالات الطلبات المتاحة:\n";
foreach ($allStatuses as $status) {
    echo "- ID: {$status->id}, الوصف: '{$status->description}'\n";
}

// فحص السجل بدون شرط الحالة
$recordWithoutStatus = Capsule::table('data')
    ->where('data_first_name', 'LIKE', '%Kyle%')
    ->first();

if ($recordWithoutStatus) {
    echo "\nتفاصيل السجل:\n";
    echo "- ID: {$recordWithoutStatus->id}\n";
    echo "- request_status_id: {$recordWithoutStatus->request_status_id}\n";

    $statusDetails = Capsule::table('request_status')
        ->where('id', $recordWithoutStatus->request_status_id)
        ->first();

    if ($statusDetails) {
        echo "- حالة الطلب: '{$statusDetails->description}'\n";
    }
}

echo "\n4. التوصيات:\n";
echo "------------\n";
if ($exactRecord->count() > 0 && $withStatusFilter == 0) {
    echo "❗ المشكلة: السجل موجود لكن حالة الطلب ليست 'مقبول'\n";
    echo "✅ الحل: إزالة أو تعديل شرط requestStatus في SearchService\n";
} elseif ($exactRecord->count() == 0) {
    echo "❗ المشكلة: السجل غير موجود في قاعدة البيانات\n";
    echo "✅ تحقق من صحة الاسم أو أضف السجل\n";
} else {
    echo "✅ السجل موجود وقابل للعثور عليه\n";
}

?>
