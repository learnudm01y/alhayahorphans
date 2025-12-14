<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "✅ اختبار حقل تاريخ وفاة الأب\n\n";

// 1. فحص الحقل في sponsor_field_settings
echo "=== 1. الحقل في sponsor_field_settings ===\n";
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
if ($settings) {
    $enabled = $settings->field_father_death_date ?? 0;
    $status = $enabled ? '✓ مفعل' : '✗ معطل';
    echo "  field_father_death_date: {$status}\n";
}

// 2. فحص الحقل في config
echo "\n=== 2. الحقل في config/sponsor_fields.php ===\n";
$config = config('sponsor_fields.fields');
if (isset($config['field_father_death_date'])) {
    echo "  ✓ field_father_death_date موجود\n";
    echo "    - display_name: {$config['field_father_death_date']['display_name']}\n";
    echo "    - order: {$config['field_father_death_date']['order']}\n";
} else {
    echo "  ✗ field_father_death_date غير موجود\n";
}

// 3. البيانات الحالية
echo "\n=== 3. البيانات الحالية (ملف 002190) ===\n";
$deadPeople = DB::table('dead_people')->where('re_file_id', '002190')->first();
if ($deadPeople) {
    echo "  - father_death_date: " . ($deadPeople->father_death_date ?: 'فارغ') . "\n";
    echo "  - father_death_reason: {$deadPeople->father_death_reason}\n";

    $reason = DB::table('death_reasons')->where('id', $deadPeople->father_death_reason)->first();
    echo "    → سبب الوفاة: " . ($reason ? $reason->description : 'غير محدد') . "\n";
}

// 4. ترتيب الحقول في الفئة
echo "\n=== 4. ترتيب الحقول في فئة 'معلومات الوالدين المتوفين' ===\n";
$parentFields = array_filter($config, function($field) {
    return isset($field['category']) && $field['category'] === 'معلومات الوالدين المتوفين';
});

uasort($parentFields, function($a, $b) {
    return $a['order'] - $b['order'];
});

foreach ($parentFields as $key => $field) {
    $enabled = $settings->$key ?? 0;
    $status = $enabled ? '✓' : '✗';
    echo "  {$status} [{$field['order']}] {$field['display_name']} ({$key})\n";
}

echo "\n✅ الحقول ستظهر بالترتيب:\n";
echo "  1. الاسم الأول للأب المتوفي\n";
echo "  2. رقم هوية الأب\n";
echo "  3. تاريخ وفاة الأب ← date input\n";
echo "  4. سبب وفاة الأب ← dropdown\n";
echo "  5. الاسم الأول للأم\n";
echo "  6. رقم هوية الأم\n";
echo "  7. تاريخ وفاة الأم ← date input\n";
echo "  8. سبب وفاة الأم ← dropdown\n";
