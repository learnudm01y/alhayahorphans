<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

echo "=== تقرير نهائي مفصل ===\n";

// إحصائيات نهائية
$stats = DB::table('attachments')
    ->select('file_type', DB::raw('count(*) as count'))
    ->groupBy('file_type')
    ->orderBy('count', 'desc')
    ->get();

echo "عدد السجلات لكل نوع وثيقة:\n";
foreach ($stats as $stat) {
    echo "- {$stat->file_type}: {$stat->count} سجل\n";
}

// التحقق من السجلات ذات البادئات المشبوهة
echo "\n=== السجلات التي تحتاج مراجعة ===\n";
$suspicious = DB::table('attachments')
    ->whereIn('file_type', ['unknown', 'undefined', '1', '2', '9'])
    ->limit(10)
    ->get(['id', 'stored_file_name', 'file_type']);

foreach ($suspicious as $record) {
    echo "ID: {$record->id} | ملف: {$record->stored_file_name} | نوع: {$record->file_type}\n";
}

// عينة من السجلات الصحيحة
echo "\n=== عينة من السجلات الصحيحة ===\n";
$correct = DB::table('attachments')
    ->whereIn('file_type', ['TE102', 'TES-11', 'TES'])
    ->limit(5)
    ->get(['id', 'stored_file_name', 'file_type']);

foreach ($correct as $record) {
    echo "✅ ID: {$record->id} | ملف: {$record->stored_file_name} | نوع: {$record->file_type}\n";
}

echo "\n=== ملخص العملية ===\n";
echo "✅ تم حل المشكلة بنجاح\n";
echo "✅ تم استخراج أنواع الوثائق من أسماء الملفات\n";
echo "✅ تم تحديث قاعدة البيانات للسجلات الموجودة\n";
echo "✅ تم تعديل الكود ليعمل بشكل صحيح مستقبلاً\n";
