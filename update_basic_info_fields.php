<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== تحديث فئة معلومات المكفول الأساسية ===\n\n";

$sponsorId = 5;
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

if ($fieldSettings) {
    // تحديث الحقول
    $updates = [
        // الحقول المطلوبة في "معلومات المكفول الأساسية"
        'field_sponsor_name' => 1,           // إسم المكفول ✓
        'field_identity_number' => 1,        // رقم الهوية ✓
        'field_person_birth_date' => 1,      // تاريخ الميلاد ✓

        // حذف/تعطيل الحقول غير المطلوبة من هذه الفئة
        'field_phone' => 0,                  // رقم الهاتف ✗
        'field_mother_name' => 0,            // إسم الأم ثلاثي ✗
        'field_father_death_date' => 0,      // تاريخ وفاة الأب ✗
    ];

    $fieldSettings->update($updates);

    echo "✓ تم تحديث الحقول بنجاح!\n\n";

    echo "حقول 'معلومات المكفول الأساسية':\n";
    echo "  ✓ إسم المكفول: " . ($fieldSettings->field_sponsor_name ? "مفعل" : "معطل") . "\n";
    echo "  ✓ رقم الهوية: " . ($fieldSettings->field_identity_number ? "مفعل" : "معطل") . "\n";
    echo "  ✓ تاريخ الميلاد: " . ($fieldSettings->field_person_birth_date ? "مفعل" : "معطل") . "\n";
    echo "\nحقول محذوفة:\n";
    echo "  ✗ رقم الهاتف: معطل\n";
    echo "  ✗ إسم الأم ثلاثي: معطل\n";
    echo "  ✗ تاريخ وفاة الأب: معطل\n";

    echo "\n✓ إجمالي الحقول الفعالة: " . $fieldSettings->getActiveFieldsCount() . "\n";
} else {
    echo "✗ إعدادات الحقول غير موجودة!\n";
}

echo "\n=== تم ===\n";
