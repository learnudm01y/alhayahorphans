<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

echo "=== فحص سريع للنظام ===" . PHP_EOL;

try {
    // فحص آخر 5 سجلات
    $recent = DB::table('attachments')
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get(['id', 'stored_file_name', 'file_type', 'created_at']);

    echo "آخر 5 سجلات:" . PHP_EOL;
    foreach ($recent as $record) {
        echo "ID: {$record->id} | ملف: {$record->stored_file_name} | نوع: {$record->file_type}" . PHP_EOL;
    }

    // إحصائيات file_type
    echo PHP_EOL . "=== إحصائيات أنواع الملفات ===" . PHP_EOL;
    $stats = DB::table('attachments')
        ->select('file_type', DB::raw('count(*) as count'))
        ->groupBy('file_type')
        ->orderBy('count', 'desc')
        ->limit(10)
        ->get();

    foreach ($stats as $stat) {
        echo "- {$stat->file_type}: {$stat->count} سجل" . PHP_EOL;
    }

    echo PHP_EOL . "=== فحص الكود الحالي ===" . PHP_EOL;

    // التحقق من وجود دالة استخراج نوع الوثيقة
    $controllerFile = 'app/Http/Controllers/UnifiedFileManagementController.php';
    if (file_exists($controllerFile)) {
        $content = file_get_contents($controllerFile);
        if (strpos($content, 'extractDocumentTypeFromFilename') !== false) {
            echo "✅ دالة استخراج نوع الوثيقة موجودة" . PHP_EOL;
        } else {
            echo "❌ دالة استخراج نوع الوثيقة غير موجودة" . PHP_EOL;
        }

        if (strpos($content, 'determineFileTypeFromExtension') !== false) {
            echo "⚠️  الدالة القديمة ما زالت موجودة - يجب استبدالها" . PHP_EOL;
        } else {
            echo "✅ تم استبدال الدالة القديمة" . PHP_EOL;
        }
    } else {
        echo "❌ ملف الكونترولر غير موجود" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . PHP_EOL;
}
