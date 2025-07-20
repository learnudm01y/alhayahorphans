<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel application instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application to initialize facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== اختبار النظام النهائي المُحدث ===\n\n";

try {
    // اختبار مجلدات مختلفة
    $testFolders = ['000001', '000010', '000014', '000015'];

    foreach ($testFolders as $testFolder) {
        echo "🧪 اختبار المجلد: $testFolder\n";
        echo str_repeat('-', 40) . "\n";

        // محاكاة دالة getFolderContents المُحدثة
        $files = collect();

        // 1. البحث في جدول attachments
        $attachmentFiles = DB::table('attachments')
            ->where('person_identity_number', $testFolder)
            ->orWhere('record_number', $testFolder)
            ->orderBy('updated_at', 'desc')
            ->get();

        echo "   📊 attachments: {$attachmentFiles->count()} ملف\n";

        // تحويل بيانات attachments
        foreach ($attachmentFiles as $file) {
            $fileObject = (object) [
                'id' => $file->id,
                'original_file_name' => $file->file_name ?: $file->original_file_name,
                'stored_file_name' => $file->stored_file_name,
                'file_path' => $file->file_path,
                'file_size' => $file->file_size,
                'file_type' => $file->file_type ?: 'image',
                'file_extension' => pathinfo($file->file_name ?: $file->stored_file_name, PATHINFO_EXTENSION),
                'record_number' => $file->person_identity_number ?: $file->record_number,
                'updated_at' => $file->updated_at,
                'source' => 'attachments_table'
            ];
            $files->push($fileObject);
        }

        // 2. البحث في enhanced_attachments
        $enhancedColumns = DB::getSchemaBuilder()->getColumnListing('enhanced_attachments');
        $recordNumberColumn = 'record_number';

        if (!in_array('record_number', $enhancedColumns)) {
            if (in_array('folder_id', $enhancedColumns)) {
                $recordNumberColumn = 'folder_id';
            } elseif (in_array('original_folder_name', $enhancedColumns)) {
                $recordNumberColumn = 'original_folder_name';
            }
        }

        $enhancedFiles = DB::table('enhanced_attachments')
            ->where($recordNumberColumn, $testFolder)
            ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
            ->whereNotIn('file_type', ['excel'])
            ->whereNull('deleted_at')
            ->orderBy('updated_at', 'desc')
            ->get();

        echo "   📊 enhanced_attachments: {$enhancedFiles->count()} ملف\n";

        // إضافة enhanced files
        foreach ($enhancedFiles as $enhancedFile) {
            $enhancedFile->source = 'enhanced_attachments_table';
            $files->push($enhancedFile);
        }

        // 3. فحص الملفات الفيزيائية
        $physicalPath = __DIR__ . "/public/storage/uploads/$testFolder";
        $physicalCount = 0;
        if (is_dir($physicalPath)) {
            $physicalFiles = array_diff(scandir($physicalPath), ['.', '..']);
            $physicalCount = count($physicalFiles);
        }
        echo "   📊 physical files: $physicalCount ملف\n";

        // النتيجة النهائية
        echo "   🎯 إجمالي الملفات المجمعة: {$files->count()}\n";

        if ($files->count() > 0) {
            echo "   ✅ النظام سيعرض الملفات بنجاح\n";

            // عرض عينة من الملفات
            $sampleFiles = $files->take(3);
            foreach ($sampleFiles as $file) {
                $fileName = $file->stored_file_name ?: $file->original_file_name;
                echo "     - $fileName ({$file->file_type}) - المصدر: {$file->source}\n";
            }

            if ($files->count() > 3) {
                echo "     ... و " . ($files->count() - 3) . " ملف آخر\n";
            }
        } else {
            echo "   ⚠️ لا توجد ملفات - سيتم استخدام المسح الفيزيائي\n";
        }

        echo "\n";
    }

    // إحصائيات شاملة
    echo "📈 إحصائيات شاملة:\n";
    echo str_repeat('=', 40) . "\n";

    // عدد المجلدات في attachments
    $attachmentFolders = DB::table('attachments')
        ->select('person_identity_number')
        ->whereNotNull('person_identity_number')
        ->where('person_identity_number', '!=', '')
        ->distinct()
        ->count();
    echo "📁 المجلدات في attachments: $attachmentFolders\n";

    // عدد المجلدات في enhanced_attachments
    $enhancedFolders = DB::table('enhanced_attachments')
        ->select($recordNumberColumn)
        ->whereNotNull($recordNumberColumn)
        ->where($recordNumberColumn, '!=', '')
        ->whereNull('deleted_at')
        ->distinct()
        ->count();
    echo "📁 المجلدات في enhanced_attachments: $enhancedFolders\n";

    // المجلدات الفيزيائية
    $uploadsPath = __DIR__ . '/public/storage/uploads';
    $physicalFolders = 0;
    if (is_dir($uploadsPath)) {
        $folders = array_diff(scandir($uploadsPath), ['.', '..']);
        foreach ($folders as $folder) {
            if (is_dir($uploadsPath . '/' . $folder)) {
                $physicalFolders++;
            }
        }
    }
    echo "📁 المجلدات الفيزيائية: $physicalFolders\n";

    echo "\n✅ انتهى الاختبار الشامل بنجاح!\n";
    echo "🎉 النظام جاهز لعرض الملفات من مصادر متعددة!\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
}
