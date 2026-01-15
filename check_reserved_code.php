<?php
/**
 * فحص الرقم 053458 في جميع الجداول
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص الرقم 053458 ===\n\n";

// 1. فحص في reserved_codes
echo "1. البحث في جدول reserved_codes:\n";
$reserved = DB::table('reserved_codes')->where('code', '053458')->first();
if ($reserved) {
    echo "   موجود في reserved_codes\n";
    print_r($reserved);
} else {
    echo "   غير موجود في reserved_codes!\n";
}

// 2. فحص في data
echo "\n2. البحث في جدول data:\n";
$data = DB::table('data')->where('file_id_number', '053458')->first();
if ($data) {
    echo "   موجود في data\n";
    echo "   - id: " . $data->id . "\n";
    echo "   - data_first_name: " . ($data->data_first_name ?? 'NULL') . "\n";
} else {
    echo "   غير موجود في data!\n";
}

// 3. فحص في re_people
echo "\n3. البحث في جدول re_people:\n";
$rePeople = DB::table('re_people')->where('registration_id', '053458')->first();
if ($rePeople) {
    echo "   موجود في re_people\n";
    echo "   - id: " . $rePeople->id . "\n";
    echo "   - first_name: " . ($rePeople->first_name ?? 'NULL') . "\n";
} else {
    echo "   غير موجود في re_people!\n";
}

// 4. فحص في sponsorships
echo "\n4. البحث في جدول sponsorships:\n";
$sponsorship = DB::table('sponsorships')->where('relation_id_number', '053458')->first();
if ($sponsorship) {
    echo "   موجود في sponsorships\n";
    echo "   - id: " . $sponsorship->id . "\n";
    echo "   - orphan_name: " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
} else {
    echo "   غير موجود في sponsorships!\n";
}

// 5. فحص هيكل جدول reserved_codes
echo "\n5. هيكل جدول reserved_codes:\n";
try {
    $columns = DB::select("SHOW COLUMNS FROM reserved_codes");
    foreach ($columns as $col) {
        echo "   - " . $col->Field . " (" . $col->Type . ")\n";
    }
} catch (Exception $e) {
    echo "   خطأ: " . $e->getMessage() . "\n";
}

// 6. آخر 5 سجلات في reserved_codes
echo "\n6. آخر 5 سجلات في reserved_codes:\n";
try {
    $lastReserved = DB::table('reserved_codes')->orderBy('id', 'desc')->limit(5)->get();
    if ($lastReserved->count() > 0) {
        foreach ($lastReserved as $r) {
            echo "   - id: {$r->id}, code: " . ($r->code ?? 'NULL') . "\n";
        }
    } else {
        echo "   الجدول فارغ!\n";
    }
} catch (Exception $e) {
    echo "   خطأ: " . $e->getMessage() . "\n";
}

echo "\n";
