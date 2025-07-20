<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel application instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application to initialize facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== فحص بنية جدول data ===\n\n";

try {
    // فحص وجود جدول data
    if (!Schema::hasTable('data')) {
        echo "❌ جدول data غير موجود!\n";
        exit(1);
    }

    echo "✅ جدول data موجود\n\n";

    // فحص الأعمدة المتوفرة
    $columns = Schema::getColumnListing('data');
    echo "📊 الأعمدة المتوفرة في جدول data:\n";
    foreach ($columns as $column) {
        echo "   - $column\n";
    }
    echo "\n";

    // فحص عينة من البيانات
    $sampleData = DB::table('data')
        ->select('file_id_number', 'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
        ->whereNotNull('file_id_number')
        ->limit(10)
        ->get();

    echo "📋 عينة من البيانات:\n";
    foreach ($sampleData as $person) {
        $fullName = trim(
            ($person->data_first_name ?? '') . ' ' .
            ($person->data_father_name ?? '') . ' ' .
            ($person->data_grand_father_name ?? '') . ' ' .
            ($person->data_family_name ?? '')
        );
        echo "   - رقم الملف: {$person->file_id_number} - الاسم: $fullName\n";
    }

    // فحص الأرقام التي نتعامل معها
    echo "\n🔍 فحص الأرقام المطابقة:\n";
    $testNumbers = ['000010', '000014', '000015', '000016'];

    foreach ($testNumbers as $number) {
        $person = DB::table('data')
            ->where('file_id_number', $number)
            ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
            ->first();

        if ($person) {
            $fullName = trim(
                ($person->data_first_name ?? '') . ' ' .
                ($person->data_father_name ?? '') . ' ' .
                ($person->data_grand_father_name ?? '') . ' ' .
                ($person->data_family_name ?? '')
            );
            echo "   ✅ $number: $fullName\n";
        } else {
            echo "   ❌ $number: غير موجود\n";
        }
    }

    echo "\n✅ انتهى فحص جدول data بنجاح!\n";

} catch (Exception $e) {
    echo "❌ خطأ في فحص جدول data: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
}
