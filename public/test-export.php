<?php
// ملف اختبار مؤقت - احذفه بعد الانتهاء

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(0);

echo "<h1>اختبار إعدادات PHP</h1>";
echo "<pre>";
echo "Current memory_limit: " . ini_get('memory_limit') . "\n";
echo "Current max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "Memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "Peak memory: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
echo "</pre>";

echo "<h2>محاولة تحميل Laravel...</h2>";
echo "<pre>";

try {
    // Load Laravel
    require __DIR__ . '/../vendor/autoload.php';
    echo "✅ Autoload نجح\n";
    
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    echo "✅ Bootstrap نجح\n";
    
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "✅ Kernel Bootstrap نجح\n";
    
    echo "\nMemory after Laravel boot: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    
    // Test database connection
    $count = DB::table('data')->count();
    echo "✅ Database connection نجح - عدد السجلات في Data: $count\n";
    
    echo "\nMemory after DB query: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    
    echo "\n<h2 style='color: green;'>✅ كل شيء يعمل بشكل صحيح!</h2>";
    echo "<p><a href='/admin/records-management/export-all'>جرب التصدير الآن</a></p>";
    
} catch (Exception $e) {
    echo "\n<h2 style='color: red;'>❌ خطأ!</h2>";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
echo "<p>Memory peak: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB</p>";
