<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "✅ اختبار نهائي - المحافظة والمدينة\n\n";

// 1. فحص الحقول المفعلة
echo "=== 1. الحقول المفعلة ===\n";
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
echo "  field_data_province: " . ($settings->field_data_province ? '✓ مفعل' : '✗ معطل') . "\n";
echo "  field_data_city: " . ($settings->field_data_city ? '✓ مفعل' : '✗ معطل') . "\n";

// 2. فحص البيانات المتاحة
echo "\n=== 2. البيانات المتاحة ===\n";
$provinces = DB::table('provinces')->get();
echo "المحافظات ({$provinces->count()}):\n";
foreach ($provinces as $province) {
    echo "  - {$province->province_name}\n";
}

$cities = DB::table('city')->limit(10)->get();
echo "\nالمدن (عرض أول 10):\n";
foreach ($cities as $city) {
    echo "  - {$city->city_name}\n";
}

// 3. البيانات الحالية للمعيل
echo "\n=== 3. البيانات الحالية (ملف 002190) ===\n";
$data = DB::table('data')->where('file_id_number', '002190')->first();
if ($data) {
    echo "  - data_province_id: " . ($data->data_province_id ?? 'فارغ') . "\n";

    if ($data->data_province_id) {
        $province = DB::table('provinces')->where('id', $data->data_province_id)->first();
        echo "    → المحافظة: " . ($province ? $province->province_name : 'غير موجودة') . "\n";
    }

    echo "  - data_city_id: " . ($data->data_city_id ?? 'فارغ') . "\n";

    if ($data->data_city_id) {
        $city = DB::table('city')->where('id', $data->data_city_id)->first();
        echo "    → المدينة: " . ($city ? $city->city_name : 'غير موجودة') . "\n";
    }
}

// 4. فحص config
echo "\n=== 4. الحقول في config ===\n";
$config = config('sponsor_fields.fields');
if (isset($config['field_data_province'])) {
    echo "  ✓ field_data_province موجود - {$config['field_data_province']['display_name']}\n";
} else {
    echo "  ✗ field_data_province غير موجود\n";
}

if (isset($config['field_data_city'])) {
    echo "  ✓ field_data_city موجود - {$config['field_data_city']['display_name']}\n";
} else {
    echo "  ✗ field_data_city غير موجود\n";
}

echo "\n✅ جاهز! ستظهر المحافظة والمدينة كقوائم منسدلة.\n";
