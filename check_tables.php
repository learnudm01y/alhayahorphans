<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 جداول قاعدة البيانات الموجودة:\n";
echo "=" . str_repeat("=", 30) . "\n";

try {
    $tables = DB::select('SHOW TABLES');

    foreach ($tables as $table) {
        foreach ($table as $key => $value) {
            echo "📊 $value\n";
        }
    }

    echo "\n🔍 البحث عن جداول تحتوي على كلمة 'duplicate':\n";
    echo "=" . str_repeat("-", 40) . "\n";

    $found = false;
    foreach ($tables as $table) {
        foreach ($table as $key => $value) {
            if (strpos(strtolower($value), 'duplicate') !== false) {
                echo "✅ $value\n";
                $found = true;
            }
        }
    }

    if (!$found) {
        echo "❌ لم يتم العثور على جداول تحتوي على كلمة 'duplicate'\n";
    }

    echo "\n🔍 البحث عن جداول تحتوي على كلمة 'temp':\n";
    echo "=" . str_repeat("-", 40) . "\n";

    $found = false;
    foreach ($tables as $table) {
        foreach ($table as $key => $value) {
            if (strpos(strtolower($value), 'temp') !== false) {
                echo "✅ $value\n";
                $found = true;
            }
        }
    }

    if (!$found) {
        echo "❌ لم يتم العثور على جداول تحتوي على كلمة 'temp'\n";
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 30) . "\n";

?>
