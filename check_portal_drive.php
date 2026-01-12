<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== جدول portal_general_registration_field_values ===" . PHP_EOL;
try {
    $cols = DB::getSchemaBuilder()->getColumnListing('portal_general_registration_field_values');
    echo "الحقول: " . implode(', ', $cols) . PHP_EOL;

    // عينة من البيانات
    $sample = DB::table('portal_general_registration_field_values')->first();
    if ($sample) {
        echo PHP_EOL . "عينة:" . PHP_EOL;
        foreach((array)$sample as $k => $v) {
            echo "  $k: $v" . PHP_EOL;
        }
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== فحص جدول google_drive_uploads ===" . PHP_EOL;
try {
    $cols = DB::getSchemaBuilder()->getColumnListing('google_drive_uploads');
    echo "الحقول: " . implode(', ', $cols) . PHP_EOL;

    // عينة
    $sample = DB::table('google_drive_uploads')->first();
    if ($sample) {
        echo PHP_EOL . "عينة:" . PHP_EOL;
        foreach((array)$sample as $k => $v) {
            if ($v) echo "  $k: " . substr($v, 0, 50) . PHP_EOL;
        }
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . PHP_EOL;
}
