<?php
/**
 * البحث عن جدول البنوك
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// البحث عن جدول البنوك
$tables = DB::select('SHOW TABLES LIKE "%bank%"');
echo "جداول البنوك:\n";
print_r($tables);

echo "\n\n";

// محاولة جلب البنوك
$tables2 = DB::select('SHOW TABLES');
echo "البحث عن جداول قد تحتوي على أسماء البنوك:\n";
foreach ($tables2 as $table) {
    $tableName = reset($table);
    if (stripos($tableName, 'bank') !== false || stripos($tableName, 'name') !== false) {
        echo "- " . $tableName . "\n";
    }
}

// محاولة جلب البنوك من جدول bank_names إذا وجد
echo "\n\nمحاولة جلب البنوك من bank_names:\n";
try {
    $banks = DB::table('bank_names')->get();
    foreach ($banks as $bank) {
        print_r($bank);
    }
} catch (\Exception $e) {
    echo "الجدول غير موجود أو خطأ: " . $e->getMessage() . "\n";
}

// محاولة جلب البنوك من banks إذا وجد
echo "\n\nمحاولة جلب البنوك من banks:\n";
try {
    $banks = DB::table('banks')->get();
    foreach ($banks as $bank) {
        print_r($bank);
    }
} catch (\Exception $e) {
    echo "الجدول غير موجود أو خطأ: " . $e->getMessage() . "\n";
}
