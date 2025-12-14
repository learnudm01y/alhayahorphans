<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== اختبار المحافظة والمدينة - كامل ===\n\n";

// 1. التحقق من الحقول المفعلة
echo "1. الحقول المفعلة:\n";
$fieldSettings = DB::table('sponsor_field_settings')
    ->where('sponsor_id', 262)
    ->first();

if ($fieldSettings) {
    echo "   - field_data_province: " . ($fieldSettings->field_data_province ? '✓ مفعل' : '✗ غير مفعل') . "\n";
    echo "   - field_data_city: " . ($fieldSettings->field_data_city ? '✓ مفعل' : '✗ غير مفعل') . "\n";
}

// 2. عرض البيانات المتاحة
echo "\n2. البيانات المتاحة:\n";
$provinces = DB::table('provinces')->get();
echo "   المحافظات (" . $provinces->count() . "):\n";
foreach ($provinces as $p) {
    echo "      - ID: {$p->id}, description: {$p->description}\n";
}

$cities = DB::table('city')->limit(5)->get();
echo "   المدن (أول 5 من " . DB::table('city')->count() . "):\n";
foreach ($cities as $c) {
    echo "      - ID: {$c->id}, city: {$c->city}\n";
}

// 3. التحقق من جدول data باستخدام file_id_number
echo "\n3. بيانات الكفالة:\n";
$sponsorship = DB::table('sponsorships')->where('id', 71)->first();
if ($sponsorship) {
    echo "   رقم ملف المعيل: {$sponsorship->guardian_file_number}\n";

    // البحث باستخدام file_id_number
    $data = DB::table('data')
        ->where('file_id_number', $sponsorship->guardian_file_number)
        ->first();

    if ($data) {
        echo "   ✓ تم العثور على السجل في data\n";
        echo "   - data_province: " . ($data->data_province ?? 'NULL') . "\n";
        echo "   - data_city: " . ($data->data_city ?? 'NULL') . "\n";

        // 4. اختبار العلاقات
        echo "\n4. اختبار العلاقات:\n";
        if ($data->data_province) {
            $province = DB::table('provinces')->where('id', $data->data_province)->first();
            if ($province) {
                echo "   ✓ المحافظة: {$province->description} (ID: {$province->id})\n";
            } else {
                echo "   ✗ المحافظة غير موجودة في الجدول\n";
            }
        } else {
            echo "   - data_province فارغ\n";
        }

        if ($data->data_city) {
            $city = DB::table('city')->where('id', $data->data_city)->first();
            if ($city) {
                echo "   ✓ المدينة: {$city->city} (ID: {$city->id})\n";
            } else {
                echo "   ✗ المدينة غير موجودة في الجدول\n";
            }
        } else {
            echo "   - data_city فارغ\n";
        }
    } else {
        echo "   ✗ لا يوجد سجل في data لرقم الملف: {$sponsorship->guardian_file_number}\n";
    }
} else {
    echo "   ✗ الكفالة 71 غير موجودة\n";
}

echo "\n=== انتهى الاختبار ===\n";
