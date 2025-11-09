<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=================================================================\n";
echo "        إصلاح مشكلة Foreign Key - marital_status\n";
echo "=================================================================\n\n";

// 1. فحص جدول marital_status
echo "1️⃣ فحص جدول marital_status\n";
echo "-----------------------------------------------------------------\n";

$maritalStatuses = DB::table('marital_status')->get();

if ($maritalStatuses->isEmpty()) {
    echo "   ❌ الجدول فارغ! سيتم إضافة البيانات الأساسية...\n\n";

    // إضافة البيانات الأساسية
    $statuses = [
        ['id' => 1, 'name' => 'أعزب/عزباء'],
        ['id' => 2, 'name' => 'متزوج/متزوجة'],
        ['id' => 3, 'name' => 'مطلق/مطلقة'],
        ['id' => 4, 'name' => 'أرمل/أرملة'],
    ];

    foreach ($statuses as $status) {
        DB::table('marital_status')->insert($status);
        echo "   ✅ تم إضافة: {$status['name']} (ID: {$status['id']})\n";
    }

    echo "\n   ✅ تم إضافة البيانات بنجاح!\n";
} else {
    echo "   📊 البيانات الموجودة:\n";
    foreach ($maritalStatuses as $status) {
        echo "      • ID: {$status->id} - {$status->name}\n";
    }
}

echo "\n=================================================================\n";
echo "✅ انتهى الإصلاح\n";
echo "=================================================================\n\n";

echo "الآن يمكنك إعادة رفع ملف Excel بدون مشاكل.\n\n";
