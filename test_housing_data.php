<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار جلب معلومات السكن ===\n\n";

// البحث عن الكفالة
$sponsorship = App\Models\Sponsorship::where('identity_number', '666665457')->first();

if (!$sponsorship) {
    echo "✗ لم يتم العثور على الكفالة!\n";
    exit;
}

echo "✓ تم العثور على الكفالة:\n";
echo "  - ID: {$sponsorship->id}\n";
echo "  - اسم المكفول: {$sponsorship->orphan_name}\n";
echo "  - رقم الملف الداخلي: {$sponsorship->internal_file_number}\n\n";

// جلب بيانات المعيل من جدول data
$data = $sponsorship->relationData;

if (!$data) {
    echo "✗ لم يتم العثور على بيانات المعيل!\n";
    exit;
}

echo "✓ تم العثور على بيانات المعيل:\n";
echo "  - ID: {$data->id}\n";
echo "  - رقم الملف: {$data->file_id_number}\n";
echo "  - الاسم: {$data->data_first_name} {$data->data_father_name}\n\n";

// فحص حالة السكن
echo "=== حالة السكن ===\n";
echo "  - data_housing_status (ID): {$data->data_housing_status}\n";

$housingStatus = DB::table('housing_status')
    ->where('id', $data->data_housing_status)
    ->first();

if ($housingStatus) {
    echo "  ✓ حالة السكن: {$housingStatus->description}\n";
} else {
    echo "  ✗ لم يتم العثور على حالة السكن\n";
}

echo "\n=== نوع السكن ===\n";
echo "  - data_current_housing_type (ID): {$data->data_current_housing_type}\n";

$housingType = DB::table('type_of_accommodation')
    ->where('id', $data->data_current_housing_type)
    ->first();

if ($housingType) {
    echo "  ✓ نوع السكن: {$housingType->description}\n";
} else {
    echo "  ✗ لم يتم العثور على نوع السكن\n";
}

echo "\n=== فحص العلاقات في Model ===\n";

// فحص ما إذا كانت العلاقات معرفة
$dataModel = new App\Models\Data();
$methods = get_class_methods($dataModel);

echo "العلاقات المتوفرة في Data Model:\n";
if (in_array('housingStatus', $methods)) {
    echo "  ✓ housingStatus\n";
} else {
    echo "  ✗ housingStatus (غير موجودة)\n";
}

if (in_array('currentHousingType', $methods)) {
    echo "  ✓ currentHousingType\n";
} else {
    echo "  ✗ currentHousingType (غير موجودة)\n";
}

echo "\n=== اختبار مباشر من قاعدة البيانات ===\n";

// جلب البيانات مباشرة
$housingStatusDirect = DB::table('data')
    ->join('housing_status', 'data.data_housing_status', '=', 'housing_status.id')
    ->where('data.file_id_number', $sponsorship->internal_file_number)
    ->select('data.*', 'housing_status.description as housing_status_name')
    ->first();

if ($housingStatusDirect) {
    echo "✓ حالة السكن (مباشر): {$housingStatusDirect->housing_status_name}\n";
}

$housingTypeDirect = DB::table('data')
    ->join('type_of_accommodation', 'data.data_current_housing_type', '=', 'type_of_accommodation.id')
    ->where('data.file_id_number', $sponsorship->internal_file_number)
    ->select('data.*', 'type_of_accommodation.description as housing_type_name')
    ->first();

if ($housingTypeDirect) {
    echo "✓ نوع السكن (مباشر): {$housingTypeDirect->housing_type_name}\n";
}

echo "\n=== تم ===\n";
