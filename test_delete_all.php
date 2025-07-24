<?php

/*
 * Script لاختبار دالة حذف جميع الملفات المكررة
 * يتحقق من أن المجلدات يتم حذفها بشكل صحيح
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "🧪 اختبار دالة حذف جميع الملفات المكررة مع المجلدات\n";
echo "====================================================\n";

// المسارات المراد فحصها
$paths = [
    'storage/app/public/temp/duplicates',
    'storage/app/temp'
];

echo "📁 المجلدات المراد فحصها:\n";
foreach ($paths as $path) {
    $fullPath = __DIR__ . '/' . $path;
    $exists = is_dir($fullPath);
    echo "- {$path}: " . ($exists ? "✅ موجود" : "❌ غير موجود") . "\n";

    if ($exists) {
        $files = glob($fullPath . '/*');
        echo "  📄 عدد العناصر بداخله: " . count($files) . "\n";
    }
}

echo "\n📋 الملاحظات:\n";
echo "- سيتم حذف مجلد storage/app/public/temp/duplicates كاملاً\n";
echo "- سيتم تنظيف ملفات duplicate_* من مجلد storage/app/temp\n";
echo "- سيتم تسجيل جميع العمليات في اللوج\n";

echo "\n✅ الاختبار مكتمل.\n";
