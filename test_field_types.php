<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "اختبار أنواع الحقول في View...\n\n";

$config = config('sponsor_fields.fields');

// الحقول التي يجب أن تكون date
$dateFields = [];
// الحقول التي يجب أن تكون text
$textFields = [];
// الحقول التي يجب أن تكون textarea
$textareaFields = [];

foreach ($config as $key => $field) {
    $fieldKey = $field['db_column'];
    $displayName = $field['display_name'];

    // تحديد النوع المتوقع
    if (str_contains($fieldKey, '_date') ||
        (str_contains($fieldKey, 'death') && str_contains($fieldKey, 'date'))) {
        $dateFields[] = [
            'key' => $fieldKey,
            'name' => $displayName,
            'type' => 'date'
        ];
    } elseif (str_contains($fieldKey, 'note') && !str_contains($fieldKey, 'reason')) {
        $textareaFields[] = [
            'key' => $fieldKey,
            'name' => $displayName,
            'type' => 'textarea'
        ];
    } elseif (str_contains($fieldKey, 'reason') || str_contains($displayName, 'سبب')) {
        $textFields[] = [
            'key' => $fieldKey,
            'name' => $displayName,
            'type' => 'text',
            'note' => 'سبب وفاة - يجب أن يكون text'
        ];
    }
}

echo "=== حقول التاريخ (Date) ===\n";
foreach ($dateFields as $field) {
    echo "  ✓ {$field['name']} ({$field['key']})\n";
}

echo "\n=== حقول النص الطويل (Textarea) ===\n";
foreach ($textareaFields as $field) {
    echo "  ✓ {$field['name']} ({$field['key']})\n";
}

echo "\n=== حقول النص (Text) - سبب الوفاة ===\n";
foreach ($textFields as $field) {
    echo "  ✓ {$field['name']} ({$field['key']})";
    if (isset($field['note'])) {
        echo " - {$field['note']}";
    }
    echo "\n";
}

// اختبار المنطق
echo "\n=== اختبار منطق العرض ===\n";
$testFields = [
    'field_father_death_date' => 'date',
    'field_mother_death_date' => 'date',
    'field_father_death_reason' => 'text',
    'field_mother_death_reason' => 'text',
    'field_person_birth_date' => 'date',
    'field_person_note' => 'textarea',
    'field_guardian_account_owner_name' => 'text',
    'field_guardian_phone_number' => 'tel',
    'field_guardian_iban_usd' => 'text',
];

foreach ($testFields as $fieldKey => $expectedType) {
    $actualType = 'text'; // default

    // محاكاة منطق View
    if (str_contains($fieldKey, 'bank_name')) {
        $actualType = 'select';
    } elseif (str_contains($fieldKey, 'health_status') || str_contains($fieldKey, 'housing')) {
        $actualType = 'select';
    } elseif (str_contains($fieldKey, 'note') && !str_contains($fieldKey, 'reason')) {
        $actualType = 'textarea';
    } elseif (str_contains($fieldKey, '_date') ||
              (str_contains($fieldKey, 'death') && str_contains($fieldKey, 'date'))) {
        $actualType = 'date';
    } elseif (str_contains($fieldKey, 'phone') || str_contains($fieldKey, 'mobile')) {
        $actualType = 'tel';
    } elseif (str_contains($fieldKey, 'iban') || str_contains($fieldKey, 'id_owner') || str_contains($fieldKey, 'account_owner')) {
        $actualType = 'text';
    } elseif (str_contains($fieldKey, 'age') || str_contains($fieldKey, 'count') || str_contains($fieldKey, 'duration')) {
        if (!str_contains($fieldKey, 'account') && !str_contains($fieldKey, 'phone') && !str_contains($fieldKey, 'iban')) {
            $actualType = 'number';
        }
    }

    $status = $actualType == $expectedType ? '✓' : '✗';
    echo "  {$status} {$fieldKey}: متوقع={$expectedType}, فعلي={$actualType}\n";
}
