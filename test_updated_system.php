<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel application instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application to initialize facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== اختبار النظام المُحدث ===\n\n";

try {
    // اختبار المجلد 000010 (موجود فعلاً)
    $testFolder = '000010';
    echo "🧪 اختبار المجلد: $testFolder\n\n";

    // 1. البحث في جدول attachments
    echo "1️⃣ البحث في جدول attachments:\n";
    $attachmentFiles = DB::table('attachments')
        ->where('person_identity_number', $testFolder)
        ->get();

    if ($attachmentFiles->isEmpty()) {
        echo "   ❌ لا توجد ملفات في جدول attachments\n";

        // البحث في record_number
        $recordFiles = DB::table('attachments')
            ->where('record_number', $testFolder)
            ->get();

        if ($recordFiles->isEmpty()) {
            echo "   ❌ لا توجد ملفات في عمود record_number أيضاً\n";
        } else {
            echo "   ✅ وُجد {$recordFiles->count()} ملف في عمود record_number\n";
            foreach ($recordFiles as $file) {
                echo "     - {$file->stored_file_name} ({$file->file_type})\n";
            }
        }
    } else {
        echo "   ✅ وُجد {$attachmentFiles->count()} ملف\n";
        foreach ($attachmentFiles as $file) {
            echo "     - {$file->stored_file_name} ({$file->file_type})\n";
        }
    }

    // 2. البحث في جدول enhanced_attachments
    echo "\n2️⃣ البحث في جدول enhanced_attachments:\n";
    if (Schema::hasTable('enhanced_attachments')) {
        $columns = Schema::getColumnListing('enhanced_attachments');
        $recordNumberColumn = 'record_number';

        if (!in_array('record_number', $columns)) {
            if (in_array('folder_id', $columns)) {
                $recordNumberColumn = 'folder_id';
            } elseif (in_array('original_folder_name', $columns)) {
                $recordNumberColumn = 'original_folder_name';
            }
        }

        $enhancedFiles = DB::table('enhanced_attachments')
            ->where($recordNumberColumn, $testFolder)
            ->whereNull('deleted_at')
            ->get();

        if ($enhancedFiles->isEmpty()) {
            echo "   ❌ لا توجد ملفات في جدول enhanced_attachments\n";
        } else {
            echo "   ✅ وُجد {$enhancedFiles->count()} ملف\n";
            foreach ($enhancedFiles as $file) {
                echo "     - {$file->stored_file_name} ({$file->file_type})\n";
            }
        }
    } else {
        echo "   ⚠️ جدول enhanced_attachments غير موجود\n";
    }

    // 3. فحص الملفات الفيزيائية
    echo "\n3️⃣ فحص الملفات الفيزيائية:\n";
    $physicalPath = __DIR__ . "/public/storage/uploads/$testFolder";

    if (is_dir($physicalPath)) {
        $files = array_diff(scandir($physicalPath), ['.', '..']);
        echo "   ✅ المجلد الفيزيائي موجود ويحتوي على " . count($files) . " ملف\n";

        foreach ($files as $file) {
            if (is_file($physicalPath . '/' . $file)) {
                $size = filesize($physicalPath . '/' . $file);
                echo "     - $file (" . number_format($size / 1024, 2) . " KB)\n";
            }
        }
    } else {
        echo "   ❌ المجلد الفيزيائي غير موجود\n";
    }

    // 4. محاكاة طلب getFolderContents
    echo "\n4️⃣ محاكاة طلب getFolderContents:\n";

    // البحث في attachments أولاً
    $allFiles = collect();

    $attachmentFiles = DB::table('attachments')
        ->where('person_identity_number', $testFolder)
        ->orWhere('record_number', $testFolder)
        ->get();

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
        $allFiles->push($fileObject);
    }

    echo "   📊 إجمالي الملفات المجمعة: {$allFiles->count()}\n";

    if ($allFiles->count() > 0) {
        echo "   📁 تفاصيل الملفات:\n";
        foreach ($allFiles as $file) {
            echo "     - الاسم: {$file->original_file_name}\n";
            echo "       المسار: {$file->file_path}\n";
            echo "       النوع: {$file->file_type}\n";
            echo "       المصدر: {$file->source}\n";
            echo "       ---\n";
        }
    }

    echo "\n✅ انتهى الاختبار بنجاح!\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
}
