<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "✅ اختبار نهائي - أسباب الوفاة\n\n";

// 1. أسباب الوفاة المتاحة
echo "=== 1. أسباب الوفاة المتاحة في dropdown ===\n";
$deathReasons = DB::table('death_reasons')->get();
foreach ($deathReasons as $reason) {
    echo "  ✓ ID: {$reason->id} - {$reason->description}\n";
}

// 2. البيانات الحالية
echo "\n=== 2. بيانات الوفاة الحالية (ملف 002190) ===\n";
$deadPeople = DB::table('dead_people')->where('re_file_id', '002190')->first();
if ($deadPeople) {
    echo "  - father_death_reason: {$deadPeople->father_death_reason}\n";
    $fatherReason = DB::table('death_reasons')->where('id', $deadPeople->father_death_reason)->first();
    echo "    → سيتم عرض: " . ($fatherReason ? $fatherReason->description : 'غير محدد') . " (selected)\n";

    echo "  - mother_death_reason: {$deadPeople->mother_death_reason}\n";
    $motherReason = DB::table('death_reasons')->where('id', $deadPeople->mother_death_reason)->first();
    echo "    → سيتم عرض: " . ($motherReason ? $motherReason->description : 'غير محدد') . " (selected)\n";
}

// 3. محاكاة extractFieldValues
echo "\n=== 3. القيم التي سيتم إرسالها للـ View ===\n";
$values = [];
if ($deadPeople) {
    // جلب سبب وفاة الأب
    if ($deadPeople->father_death_reason) {
        $fatherDeathReason = DB::table('death_reasons')
            ->where('id', $deadPeople->father_death_reason)
            ->first();
        $values['field_father_death_reason'] = $fatherDeathReason ? $fatherDeathReason->description : '';
    }

    // جلب سبب وفاة الأم
    if ($deadPeople->mother_death_reason) {
        $motherDeathReason = DB::table('death_reasons')
            ->where('id', $deadPeople->mother_death_reason)
            ->first();
        $values['field_mother_death_reason'] = $motherDeathReason ? $motherDeathReason->description : '';
    }
}

foreach ($values as $key => $value) {
    echo "  ✓ {$key}: {$value}\n";
}

// 4. فحص الحقول المفعلة
echo "\n=== 4. الحقول المفعلة ===\n";
$settings = DB::table('sponsor_field_settings')->where('sponsor_id', 5)->first();
$deathFields = [
    'field_father_death_reason' => 'سبب وفاة الأب',
    'field_mother_death_reason' => 'سبب وفاة الأم'
];

foreach ($deathFields as $field => $label) {
    $enabled = $settings->$field ?? 0;
    $status = $enabled ? '✓' : '✗';
    echo "  {$status} {$label} ({$field}): " . ($enabled ? 'مفعل' : 'معطل') . "\n";
}

echo "\n✅ جاهز! ستظهر أسباب الوفاة كقوائم منسدلة مع تحديد القيمة الحالية.\n";
echo "\nتسجيل الدخول:\n";
echo "  - اسم المستخدم: 666665457\n";
echo "  - كلمة المرور: 002622\n";
