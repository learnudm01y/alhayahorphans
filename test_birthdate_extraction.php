<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== اختبار تاريخ الميلاد ===\n\n";

$identityNumber = '666665457';

// 1. re_people
$rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
if ($rePerson) {
    echo "✓ re_people:\n";
    echo "  - person_id: {$rePerson->person_id}\n";
    echo "  - تاريخ الميلاد: {$rePerson->person_birth_date}\n";
    echo "  - العمر: {$rePerson->person_age}\n\n";
}

// 2. data
$dataRecord = DB::table('data')->where('data_id_number', $identityNumber)->first();
if ($dataRecord) {
    echo "✓ data:\n";
    echo "  - data_id_number: {$dataRecord->data_id_number}\n";
    echo "  - تاريخ الميلاد: " . ($dataRecord->data_birth_date ?? 'غير موجود') . "\n\n";
}

// 3. اختبار extractFieldValues
$sponsorship = App\Models\Sponsorship::where('identity_number', $identityNumber)->first();
if ($sponsorship) {
    $controller = new \App\Http\Controllers\Users\ShowGeneralRegisrationController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('extractFieldValues');
    $method->setAccessible(true);

    $values = $method->invoke($controller, $sponsorship);

    echo "=== نتيجة extractFieldValues ===\n";
    echo "field_person_birth_date: " . ($values['field_person_birth_date'] ?? 'غير موجود') . "\n";
}

echo "\n=== تم ===\n";
