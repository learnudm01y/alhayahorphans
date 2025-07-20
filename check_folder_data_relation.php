<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel application instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application to initialize facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== البحث عن العلاقة بين أرقام المجلدات وجدول data ===\n\n";

// البحث عن رقم 10 (بدون الأصفار)
$folders = ['000010', '000014', '000015', '000016'];

foreach ($folders as $folder) {
    $numericPart = (int)ltrim($folder, '0');
    echo "المجلد: $folder -> الرقم المجرد: $numericPart\n";

    $person = DB::table('data')
        ->where('file_id_number', $numericPart)
        ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
        ->first();

    if ($person) {
        $fullName = trim(
            ($person->data_first_name ?? '') . ' ' .
            ($person->data_father_name ?? '') . ' ' .
            ($person->data_grand_father_name ?? '') . ' ' .
            ($person->data_family_name ?? '')
        );
        echo "   ✅ موجود: $fullName\n";
    } else {
        echo "   ❌ غير موجود\n";
    }
    echo "\n";
}

// فحص المقارنة بتنسيقات مختلفة
echo "🔍 فحص تنسيقات مختلفة:\n";
$testFolder = '000010';

// محاولة 1: مقارنة مباشرة
$person1 = DB::table('data')
    ->where('file_id_number', $testFolder)
    ->first();
echo "1. مقارنة مباشرة '$testFolder': " . ($person1 ? 'موجود' : 'غير موجود') . "\n";

// محاولة 2: إزالة الأصفار
$numericPart = (int)ltrim($testFolder, '0');
$person2 = DB::table('data')
    ->where('file_id_number', $numericPart)
    ->first();
echo "2. الرقم المجرد '$numericPart': " . ($person2 ? 'موجود' : 'غير موجود') . "\n";

// محاولة 3: البحث كنص
$person3 = DB::table('data')
    ->where('file_id_number', 'LIKE', "%$testFolder%")
    ->first();
echo "3. البحث كنص: " . ($person3 ? 'موجود' : 'غير موجود') . "\n";

// عرض عينة من البيانات للمقارنة
echo "\n📊 عينة من file_id_number في جدول data:\n";
$samples = DB::table('data')
    ->select('file_id_number')
    ->whereNotNull('file_id_number')
    ->distinct()
    ->orderBy('file_id_number')
    ->limit(20)
    ->get();

foreach ($samples as $sample) {
    echo "   - {$sample->file_id_number}\n";
}
