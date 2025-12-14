<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== إنشاء سجل اختباري للكفالة 71 ===\n\n";

// Get sponsorship 71
$sponsorship = DB::table('sponsorships')->where('id', 71)->first();

if (!$sponsorship) {
    echo "❌ لم يتم العثور على الكفالة 71\n";
    exit;
}

echo "الكفالة 71:\n";
echo "  - internal_file_number: {$sponsorship->internal_file_number}\n\n";

// Check if data already exists
$existingData = DB::table('data')->where('file_id_number', $sponsorship->internal_file_number)->first();

if ($existingData) {
    echo "✓ السجل موجود بالفعل (ID: {$existingData->id})\n";
    echo "  - data_province: " . ($existingData->data_province ?? 'NULL') . "\n";
    echo "  - data_city: " . ($existingData->data_city ?? 'NULL') . "\n\n";

    // Update if needed
    if (!$existingData->data_province || !$existingData->data_city) {
        DB::table('data')
            ->where('id', $existingData->id)
            ->update([
                'data_province' => 2,
                'data_city' => 5,
                'updated_at' => now()
            ]);
        echo "✓ تم تحديث المحافظة والمدينة\n";
    }
} else {
    // Create new record
    $dataId = DB::table('data')->insertGetId([
        'file_id_number' => $sponsorship->internal_file_number,
        'data_province' => 2,
        'data_city' => 5,
        'created_at' => now(),
        'updated_at' => now()
    ]);

    echo "✓ تم إنشاء سجل جديد (ID: {$dataId})\n";
    echo "  - data_province: 2 (اختبار المحافظة)\n";
    echo "  - data_city: 5 (غزة)\n";
}

echo "\n=== جاهز للاختبار! ===\n";
echo "يمكنك الآن تسجيل الدخول باستخدام:\n";
echo "  - البريد: 666665457\n";
echo "  - كلمة المرور: 002622\n";
