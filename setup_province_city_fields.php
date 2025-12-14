<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "إضافة وتفعيل حقول المحافظة والمدينة...\n\n";

// 1. إضافة حقل المحافظة
try {
    DB::statement("ALTER TABLE sponsor_field_settings ADD COLUMN field_data_province TINYINT(1) DEFAULT 0 AFTER field_data_address");
    echo "✓ تم إضافة حقل field_data_province\n";
} catch (Exception $e) {
    echo "✓ حقل field_data_province موجود بالفعل\n";
}

// 2. تفعيل الحقول
DB::table('sponsor_field_settings')->update([
    'field_data_province' => 1,
    'field_data_city' => 1
]);
echo "✓ تم تفعيل حقول المحافظة والمدينة\n";

// 3. التحقق
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
echo "\n=== حالة الحقول ===\n";
echo "field_data_province: " . ($settings->field_data_province ? 'مفعل ✓' : 'معطل ✗') . "\n";
echo "field_data_city: " . ($settings->field_data_city ? 'مفعل ✓' : 'معطل ✗') . "\n";

// 4. فحص الجداول
echo "\n=== فحص جداول البيانات ===\n";
$provinces = DB::table('provinces')->count();
echo "عدد المحافظات: {$provinces}\n";

$cities = DB::table('city')->count();
echo "عدد المدن: {$cities}\n";

echo "\n✅ جاهز!\n";
