<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== تعطيل الحقول غير المطلوبة ===\n\n";

$sponsorId = 5;
$fieldSettings = App\Models\SponsorFieldSetting::where('sponsor_id', $sponsorId)->first();

if ($fieldSettings) {
    $updates = [
        // معلومات المعيل
        'field_current_guardian' => 0,
        'field_relationship' => 0,
        'field_guardian_health' => 0,
        'field_guardian_job' => 0,
        'field_dependents_female' => 0,
        'field_dependents_male' => 0,
        'field_family_members_count' => 0,

        // معلومات الوصي
        'field_re_guardian_name' => 0,
        'field_re_guardian_phone' => 0,
        'field_re_guardian_id' => 0,

        // معلومات اليتيم
        'field_first_name' => 0,
        'field_person_id' => 0,
        'field_person_age' => 0,
        'field_person_gender' => 0,
        'field_person_health_status' => 0,
        'field_person_type_of_guarantee' => 0,
        'field_person_note' => 0,

        // معلومات اخوة المكفول
        'field_siblings_names' => 0,
        'field_sibling_birthdate' => 0,
        'field_sibling_grade' => 0,
        'field_sibling_notes' => 0,

        // معلومات المؤسسة والمرفقات
        'field_institution_name' => 0,
        'field_attachments' => 0,

        // معلومات الكفالة
        'field_sponsoring_organization' => 0,
        'field_external_file_number' => 0,
        'field_guardian_identity_number' => 0,
        'field_sponsorship_duration_months' => 0,
        'field_sponsorship_start_date' => 0,
        'field_sponsorship_end_date' => 0,
        'field_sponsorship_type_id' => 0,
        'field_sponsorship_status_id' => 0,
    ];

    $fieldSettings->update($updates);

    echo "✓ تم تعطيل الحقول بنجاح!\n";
    echo "✓ عدد الحقول المعطلة: " . count($updates) . "\n";
    echo "✓ إجمالي الحقول الفعالة المتبقية: " . $fieldSettings->getActiveFieldsCount() . "\n\n";

    echo "الفئات المعطلة:\n";
    echo "  ✗ معلومات المعيل (7 حقول)\n";
    echo "  ✗ معلومات الوصي (3 حقول)\n";
    echo "  ✗ معلومات اليتيم (7 حقول)\n";
    echo "  ✗ معلومات اخوة المكفول (4 حقول)\n";
    echo "  ✗ معلومات المؤسسة والمرفقات (2 حقول)\n";
    echo "  ✗ معلومات الكفالة (8 حقول)\n";

} else {
    echo "✗ إعدادات الحقول غير موجودة!\n";
}

echo "\n=== تم ===\n";
