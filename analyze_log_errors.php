<?php

echo "=== تحليل أخطاء Laravel ===" . PHP_EOL;

$logFile = 'storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "❌ ملف السجلات غير موجود" . PHP_EOL;
    exit;
}

// قراءة الملف وتحويل الترميز
$content = file_get_contents($logFile);

// محاولة تنظيف الترميز
$content = mb_convert_encoding($content, 'UTF-8', 'auto');

// تقسيم إلى سطور
$lines = explode("\n", $content);

echo "📊 عدد الأسطر الإجمالي: " . count($lines) . PHP_EOL;

// البحث عن الأخطاء
$errors = [];
$sqlErrors = [];

foreach ($lines as $lineNum => $line) {
    // البحث عن أخطاء SQL
    if (strpos($line, 'SQLSTATE') !== false || strpos($line, 'ERROR') !== false) {
        $errors[] = [
            'line' => $lineNum + 1,
            'content' => trim($line)
        ];

        // استخراج تفاصيل أخطاء SQL
        if (strpos($line, 'SQLSTATE[42S22]') !== false) {
            $sqlErrors[] = $line;
        }
    }
}

echo "🚨 عدد الأخطاء الموجودة: " . count($errors) . PHP_EOL;
echo "🗃️ عدد أخطاء SQL (العمود غير موجود): " . count($sqlErrors) . PHP_EOL;

if (!empty($errors)) {
    echo PHP_EOL . "🔍 أخطاء مفصلة:" . PHP_EOL;
    foreach (array_slice($errors, 0, 5) as $error) {
        echo "السطر {$error['line']}: " . substr($error['content'], 0, 200) . "..." . PHP_EOL;
    }
}

if (!empty($sqlErrors)) {
    echo PHP_EOL . "🎯 تحليل أخطاء قاعدة البيانات:" . PHP_EOL;

    // محاولة استخراج اسم العمود المفقود
    foreach (array_slice($sqlErrors, 0, 3) as $sqlError) {
        if (preg_match('/Unknown column \'([^\']+)\'/', $sqlError, $matches)) {
            echo "❌ العمود المفقود: " . $matches[1] . PHP_EOL;
        }

        // البحث عن اسم الجدول
        if (preg_match('/from `([^`]+)`/', $sqlError, $matches)) {
            echo "📋 الجدول: " . $matches[1] . PHP_EOL;
        }
    }
}

echo PHP_EOL . "💡 الحلول المقترحة:" . PHP_EOL;
echo "1. فحص هيكل جدول attachments" . PHP_EOL;
echo "2. تشغيل migration للتأكد من الأعمدة" . PHP_EOL;
echo "3. حذف ملف السجلات المعطوب" . PHP_EOL;
echo "4. إعادة تشغيل العملية" . PHP_EOL;

echo PHP_EOL . "=== انتهى التحليل ===" . PHP_EOL;
