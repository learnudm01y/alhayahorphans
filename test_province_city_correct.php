<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار المحافظة والمدينة بالأعمدة الصحيحة ===\n\n";

// 1. Check provinces
echo "1. المحافظات المتاحة:\n";
$provinces = DB::table('provinces')->get();
foreach ($provinces as $province) {
    echo "   - ID: {$province->id}, الاسم: {$province->description}\n";
}

// 2. Check cities
echo "\n2. المدن المتاحة (أول 5):\n";
$cities = DB::table('city')->limit(5)->get();
foreach ($cities as $city) {
    echo "   - ID: {$city->id}, الاسم: {$city->city}\n";
}

// 3. Check current values for sponsorship 71
echo "\n3. القيم الحالية للكفالة 71:\n";
$data = DB::table('data')->where('id', 71)->first();
if ($data) {
    echo "   - data_province: " . ($data->data_province ?? 'NULL') . "\n";
    echo "   - data_city: " . ($data->data_city ?? 'NULL') . "\n";

    // Try to fetch relations
    if ($data->data_province) {
        $province = DB::table('provinces')->where('id', $data->data_province)->first();
        echo "   - اسم المحافظة: " . ($province ? $province->description : 'غير موجود') . "\n";
    }

    if ($data->data_city) {
        $city = DB::table('city')->where('id', $data->data_city)->first();
        echo "   - اسم المدينة: " . ($city ? $city->city : 'غير موجود') . "\n";
    }
}

echo "\n=== انتهى الاختبار ===\n";
