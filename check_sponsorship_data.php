<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== البحث عن بيانات الكفالة 71 ===\n\n";

// Get sponsorship
$sponsorship = DB::table('sponsorships')->where('id', 71)->first();
if ($sponsorship) {
    echo "Guardian File Number: {$sponsorship->internal_file_number}\n\n";

    // Get data by file_no
    $data = DB::table('data')->where('file_no', $sponsorship->internal_file_number)->first();
    if ($data) {
        echo "Data found:\n";
        echo "  - ID: {$data->id}\n";
        echo "  - data_province: " . ($data->data_province ?? 'NULL') . "\n";
        echo "  - data_city: " . ($data->data_city ?? 'NULL') . "\n";

        // Fetch relations
        if ($data->data_province) {
            $province = DB::table('provinces')->where('id', $data->data_province)->first();
            echo "  - Province Name: " . ($province ? $province->description : 'Not Found') . "\n";
        }

        if ($data->data_city) {
            $city = DB::table('city')->where('id', $data->data_city)->first();
            echo "  - City Name: " . ($city ? $city->city : 'Not Found') . "\n";
        }
    } else {
        echo "No data record found for file_no: {$sponsorship->file_no}\n";
    }
} else {
    echo "Sponsorship 71 not found\n";
}

echo "\n=== انتهى البحث ===\n";
