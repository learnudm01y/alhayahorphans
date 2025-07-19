<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=================================\n";
echo "📊 تقرير ملفات Excel في النظام\n";
echo "=================================\n\n";

// حساب إجمالي ملفات Excel
$totalExcelFiles = DB::table('enhanced_attachments')
    ->where('file_type', 'excel')
    ->whereNull('deleted_at')
    ->count();

echo "📈 إجمالي ملفات Excel: {$totalExcelFiles} ملف\n\n";

// حساب المجلدات
$totalFolders = DB::table('enhanced_attachments')
    ->where('file_type', 'excel')
    ->whereNull('deleted_at')
    ->distinct('record_number')
    ->count('record_number');

echo "📁 عدد المجلدات: {$totalFolders} مجلد\n\n";

// تفاصيل توزيع الملفات على المجلدات
$folders = DB::table('enhanced_attachments')
    ->select('record_number', DB::raw('COUNT(*) as files_count'), DB::raw('SUM(file_size) as total_size'))
    ->where('file_type', 'excel')
    ->whereNull('deleted_at')
    ->groupBy('record_number')
    ->orderBy('record_number')
    ->get();

echo "📋 تفاصيل التوزيع:\n";
echo "==================\n";

foreach($folders as $folder) {
    $sizeInKB = round(($folder->total_size ?? 0) / 1024, 2);
    echo "📂 مجلد {$folder->record_number}: {$folder->files_count} ملف ({$sizeInKB} KB)\n";
}

echo "\n🎯 الملخص النهائي:\n";
echo "=================\n";
echo "✅ المجموع الكلي: {$totalExcelFiles} ملف Excel\n";
echo "✅ موزعة على: {$totalFolders} مجلد\n";
echo "✅ متوسط الملفات لكل مجلد: " . round($totalExcelFiles / max($totalFolders, 1), 2) . " ملف\n\n";

// إحصائيات إضافية
$fileExtensions = DB::table('enhanced_attachments')
    ->select('file_extension', DB::raw('COUNT(*) as count'))
    ->where('file_type', 'excel')
    ->whereNull('deleted_at')
    ->groupBy('file_extension')
    ->get();

echo "📊 أنواع ملفات Excel:\n";
echo "=====================\n";
foreach($fileExtensions as $ext) {
    echo "📄 .{$ext->file_extension}: {$ext->count} ملف\n";
}

echo "\n✨ تم إكمال التقرير بنجاح!\n";
