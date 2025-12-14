<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== تعطيل حقل رقم الهاتف ===\n\n";

$sponsorId = 5;
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

if ($fieldSettings) {
    // تعطيل حقل رقم الهاتف
    $fieldSettings->update([
        'field_phone' => 0,  // تعطيل
    ]);

    echo "✓ تم تعطيل حقل رقم الهاتف\n";
    echo "✓ الحقول الفعالة المتبقية: " . ($fieldSettings->getActiveFieldsCount()) . "\n";

    // عرض الحقول المفعلة في فئة معلومات المكفول الأساسية
    $basicFields = [
        'field_sponsor_name' => $fieldSettings->field_sponsor_name,
        'field_phone' => $fieldSettings->field_phone,
        'field_mother_name' => $fieldSettings->field_mother_name,
        'field_father_death_date' => $fieldSettings->field_father_death_date,
    ];

    echo "\nحقول 'معلومات المكفول الأساسية':\n";
    foreach ($basicFields as $field => $status) {
        $displayName = match($field) {
            'field_sponsor_name' => 'إسم المكفول',
            'field_phone' => 'رقم الهاتف',
            'field_mother_name' => 'إسم الأم ثلاثي',
            'field_father_death_date' => 'تاريخ وفاة الأب',
        };
        $icon = $status ? '✓' : '✗';
        echo "  {$icon} {$displayName}: " . ($status ? "مفعل" : "معطل") . "\n";
    }
} else {
    echo "✗ إعدادات الحقول غير موجودة!\n";
}

echo "\n=== تم ===\n";
