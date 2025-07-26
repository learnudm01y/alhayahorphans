<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// فحص جدول المدن
echo "فحص جدول المدن:\n";
echo "================\n";

try {
    $cities = App\Models\City::all();
    if ($cities->count() > 0) {
        echo "عدد المدن في الجدول: " . $cities->count() . "\n\n";
        echo "أول 10 مدن:\n";
        foreach ($cities->take(10) as $city) {
            echo "ID: " . $city->id . " - الوصف: " . ($city->description ?? 'فارغ') . " - الاسم: " . ($city->name ?? 'فارغ') . "\n";
        }
    } else {
        echo "جدول المدن فارغ!\n";
    }
} catch (Exception $e) {
    echo "خطأ في الوصول لجدول المدن: " . $e->getMessage() . "\n";
}

echo "\n";

// فحص ما إذا كانت المدينة رقم 7 موجودة
echo "فحص المدينة رقم 7:\n";
echo "==================\n";

try {
    $city7 = App\Models\City::find(7);
    if ($city7) {
        echo "المدينة رقم 7 موجودة: " . ($city7->description ?? $city7->name ?? 'بدون وصف') . "\n";
    } else {
        echo "المدينة رقم 7 غير موجودة!\n";
    }
} catch (Exception $e) {
    echo "خطأ في البحث عن المدينة رقم 7: " . $e->getMessage() . "\n";
}

echo "\n";

// فحص البيانات التي لديها data_city = 7
echo "فحص السجلات التي لديها data_city = 7:\n";
echo "====================================\n";

try {
    $recordsWithCity7 = App\Models\Data::with('city')
        ->where('data_city', 7)
        ->limit(3)
        ->get();

    foreach ($recordsWithCity7 as $record) {
        echo "Record ID: " . $record->id . "\n";
        echo "data_city: " . $record->data_city . "\n";
        echo "City relation loaded: " . ($record->city ? 'نعم' : 'لا') . "\n";
        if ($record->city) {
            echo "City description: " . ($record->city->description ?? $record->city->name ?? 'فارغ') . "\n";
        }
        echo "---\n";
    }
} catch (Exception $e) {
    echo "خطأ في فحص السجلات: " . $e->getMessage() . "\n";
}

?>
