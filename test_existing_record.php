<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار المحافظة والمدينة مع سجل موجود ===\n\n";

// Find data with province and city
$data = DB::table('data')->whereNotNull('data_province')->whereNotNull('data_city')->first();

if ($data) {
    echo "1. وجدنا سجل في data:\n";
    echo "   - ID: {$data->id}\n";
    echo "   - file_id_number: {$data->file_id_number}\n";
    echo "   - data_province: {$data->data_province}\n";
    echo "   - data_city: {$data->data_city}\n\n";

    // Fetch province
    $province = DB::table('provinces')->where('id', $data->data_province)->first();
    echo "2. المحافظة:\n";
    if ($province) {
        echo "   - ID: {$province->id}\n";
        echo "   - description: {$province->description}\n\n";
    } else {
        echo "   - لم يتم العثور على المحافظة\n\n";
    }

    // Fetch city
    $city = DB::table('city')->where('id', $data->data_city)->first();
    echo "3. المدينة:\n";
    if ($city) {
        echo "   - ID: {$city->id}\n";
        echo "   - city: {$city->city}\n\n";
    } else {
        echo "   - لم يتم العثور على المدينة\n\n";
    }

    // Find sponsorship
    $sponsorship = DB::table('sponsorships')->where('internal_file_number', $data->file_id_number)->first();
    echo "4. الكفالة المرتبطة:\n";
    if ($sponsorship) {
        echo "   - ID: {$sponsorship->id}\n";
        echo "   - internal_file_number: {$sponsorship->internal_file_number}\n";
        echo "   - identity_number: {$sponsorship->identity_number}\n\n";
        echo "   ✓ يمكن استخدام هذه الكفالة للاختبار!\n";
    } else {
        echo "   - لم يتم العثور على كفالة مرتبطة\n";
    }
} else {
    echo "لم يتم العثور على أي سجل يحتوي على محافظة ومدينة\n";
}

echo "\n=== انتهى الاختبار ===\n";
