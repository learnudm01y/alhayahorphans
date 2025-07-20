<?php

echo "=== فحص مشكلة Laravel.log ===" . PHP_EOL;

$logFile = 'storage/logs/laravel.log';

// فحص وجود الملف
if (!file_exists($logFile)) {
    echo "❌ ملف السجلات غير موجود" . PHP_EOL;
    exit;
}

// فحص الحجم
$fileSize = filesize($logFile);
echo "📊 حجم ملف السجلات: " . number_format($fileSize) . " بايت" . PHP_EOL;

// قراءة عينة من الملف
$content = file_get_contents($logFile, false, null, 0, 1000);
echo "📄 أول 1000 حرف من الملف:" . PHP_EOL;
echo "---" . PHP_EOL;
echo $content . PHP_EOL;
echo "---" . PHP_EOL;

// فحص ترميز الملف
$encoding = mb_detect_encoding($content, ['UTF-8', 'ASCII', 'ISO-8859-1', 'Windows-1252'], true);
echo "🔤 ترميز الملف المكتشف: " . ($encoding ?: 'غير معروف') . PHP_EOL;

// فحص إذا كان الملف يحتوي على أحرف غير طبيعية
$hasWeirdChars = preg_match('/[^\x20-\x7E\r\n\t]/', $content);
echo "⚠️  يحتوي على أحرف غريبة: " . ($hasWeirdChars ? 'نعم' : 'لا') . PHP_EOL;

// محاولة تحديد نوع المشكلة
if ($hasWeirdChars) {
    echo PHP_EOL . "🔍 تحليل المشكلة:" . PHP_EOL;

    // فحص إذا كان مشفر أو مضغوط
    $firstBytes = substr($content, 0, 10);
    $hex = bin2hex($firstBytes);
    echo "- أول 10 بايت بصيغة hex: " . $hex . PHP_EOL;

    // اقتراحات للحل
    echo PHP_EOL . "💡 اقتراحات الحل:" . PHP_EOL;
    echo "1. حذف ملف السجلات الحالي وإنشاء جديد" . PHP_EOL;
    echo "2. تحقق من إعدادات ترميز قاعدة البيانات" . PHP_EOL;
    echo "3. تحقق من إعدادات Laravel logging" . PHP_EOL;
}

echo PHP_EOL . "=== انتهى الفحص ===" . PHP_EOL;
