<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== فحص هياكل جداول المحافظات والمدن ===\n\n";

// 1. Check sponsorships table
echo "1. جدول sponsorships - الأعمدة المتعلقة بالملف:\n";
$sponsorshipColumns = DB::select('SHOW COLUMNS FROM sponsorships');
foreach ($sponsorshipColumns as $col) {
    if (stripos($col->Field, 'file') !== false || stripos($col->Field, 'id') !== false) {
        echo "   - {$col->Field}\n";
    }
}

// 2. Check data table
echo "\n2. جدول data - الأعمدة المتعلقة بالمحافظة والمدينة:\n";
$dataColumns = DB::select('SHOW COLUMNS FROM data');
foreach ($dataColumns as $col) {
    if (stripos($col->Field, 'province') !== false ||
        stripos($col->Field, 'city') !== false ||
        stripos($col->Field, 'file') !== false ||
        $col->Field === 'id') {
        echo "   - {$col->Field}\n";
    }
}

// 3. Check provinces table structure
echo "\n3. جدول provinces - الأعمدة:\n";
$provincesColumns = DB::select('SHOW COLUMNS FROM provinces');
foreach ($provincesColumns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}

// 4. Check city table structure
echo "\n4. جدول city - الأعمدة:\n";
$cityColumns = DB::select('SHOW COLUMNS FROM city');
foreach ($cityColumns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}

// 5. Check actual data for sponsorship 71
echo "\n5. بيانات الكفالة 71:\n";
$sponsorship = DB::table('sponsorships')->where('id', 71)->first();
if ($sponsorship) {
    echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";

    // Try to find data record
    $data = DB::table('data')->where('file_id_number', $sponsorship->internal_file_number)->first();
    if ($data) {
        echo "   - Data Record Found (ID: {$data->id})\n";
        echo "   - data_province: " . ($data->data_province ?? 'NULL') . "\n";
        echo "   - data_city: " . ($data->data_city ?? 'NULL') . "\n";

        // Fetch province name
        if (isset($data->data_province) && $data->data_province) {
            $province = DB::table('provinces')->where('id', $data->data_province)->first();
            if ($province) {
                echo "   - Province Name: {$province->description}\n";
            }
        }

        // Fetch city name
        if (isset($data->data_city) && $data->data_city) {
            $city = DB::table('city')->where('id', $data->data_city)->first();
            if ($city) {
                echo "   - City Name: {$city->city}\n";
            }
        }
    } else {
        echo "   - لم يتم العثور على سجل في جدول data\n";
    }
} else {
    echo "   - لم يتم العثور على الكفالة\n";
}

echo "\n=== انتهى الفحص ===\n";
