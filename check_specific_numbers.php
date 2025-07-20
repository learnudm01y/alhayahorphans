<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص الأرقام من الصورة المرسلة ===\n\n";

// الأرقام الظاهرة في الصورة
$numbersFromImage = [
    '9214534563',
    '2962453453',
    '2944445',
    '44444424',
    '80944443',
    '82364444',
    '8244445'
];

echo "🔍 البحث عن الأرقام الظاهرة في الصورة:\n";
echo str_repeat('-', 60) . "\n";

foreach ($numbersFromImage as $number) {
    echo "\n📋 البحث عن الرقم: $number\n";

    // 1. البحث في جدول attachments
    $attachmentExists = DB::table('attachments')
        ->where('person_identity_number', $number)
        ->exists();
    echo "   📂 في attachments: " . ($attachmentExists ? '✅ موجود' : '❌ غير موجود') . "\n";

    // 2. البحث في جدول data (مباشر)
    $personDirect = DB::table('data')
        ->where('file_id_number', $number)
        ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
        ->first();

    if ($personDirect) {
        $fullName = trim(implode(' ', array_filter([
            $personDirect->data_first_name ?? '',
            $personDirect->data_father_name ?? '',
            $personDirect->data_grand_father_name ?? '',
            $personDirect->data_family_name ?? ''
        ])));
        echo "   👤 في data (مباشر): ✅ $fullName\n";
    } else {
        echo "   👤 في data (مباشر): ❌ غير موجود\n";

        // 3. البحث بعد إزالة الأصفار
        $numericPart = (int)ltrim($number, '0');
        if ($numericPart > 0) {
            $personNumeric = DB::table('data')
                ->where('file_id_number', $numericPart)
                ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
                ->first();

            if ($personNumeric) {
                $fullName = trim(implode(' ', array_filter([
                    $personNumeric->data_first_name ?? '',
                    $personNumeric->data_father_name ?? '',
                    $personNumeric->data_grand_father_name ?? '',
                    $personNumeric->data_family_name ?? ''
                ])));
                echo "   👤 في data (رقم مجرد $numericPart): ✅ $fullName\n";
            } else {
                echo "   👤 في data (رقم مجرد $numericPart): ❌ غير موجود\n";
            }
        }
    }
}

// فحص عينة من الأرقام الفعلية الموجودة
echo "\n\n📊 عينة من الأرقام الفعلية الموجودة:\n";
echo str_repeat('=', 60) . "\n";

echo "\n1️⃣ في جدول attachments:\n";
$attachmentNumbers = DB::table('attachments')
    ->select('person_identity_number')
    ->whereNotNull('person_identity_number')
    ->distinct()
    ->limit(10)
    ->get();

foreach ($attachmentNumbers as $att) {
    echo "   - {$att->person_identity_number}\n";
}

echo "\n2️⃣ في جدول data:\n";
$dataNumbers = DB::table('data')
    ->select('file_id_number')
    ->whereNotNull('file_id_number')
    ->limit(10)
    ->get();

foreach ($dataNumbers as $data) {
    echo "   - {$data->file_id_number}\n";
}

echo "\n✅ انتهى الفحص!\n";
