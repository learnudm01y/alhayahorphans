<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel application instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application to initialize facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== فحص بيانات جدول attachments ===\n\n";

try {
    // فحص وجود جدول attachments
    if (!Schema::hasTable('attachments')) {
        echo "❌ جدول attachments غير موجود!\n";
        exit(1);
    }

    echo "✅ جدول attachments موجود\n\n";

    // فحص الأعمدة المتوفرة
    $columns = Schema::getColumnListing('attachments');
    echo "📊 الأعمدة المتوفرة في جدول attachments:\n";
    foreach ($columns as $column) {
        echo "   - $column\n";
    }
    echo "\n";

    // إحصائيات عامة
    $totalFiles = DB::table('attachments')->count();
    echo "📈 إحصائيات عامة:\n";
    echo "   - إجمالي الملفات: $totalFiles\n";

    // فحص السجلات حسب person_identity_number
    $recordCounts = DB::table('attachments')
        ->select('person_identity_number', DB::raw('COUNT(*) as count'))
        ->whereNotNull('person_identity_number')
        ->where('person_identity_number', '!=', '')
        ->groupBy('person_identity_number')
        ->orderBy('count', 'desc')
        ->limit(10)
        ->get();

    echo "   - عدد الأشخاص الذين لديهم ملفات: " . $recordCounts->count() . "\n\n";

    echo "🔍 أكثر 10 أشخاص لديهم ملفات:\n";
    foreach ($recordCounts as $record) {
        echo "   - الرقم: {$record->person_identity_number} - عدد الملفات: {$record->count}\n";
    }
    echo "\n";

    // فحص أنواع الملفات
    $fileTypes = DB::table('attachments')
        ->select('file_type', DB::raw('COUNT(*) as count'))
        ->whereNotNull('file_type')
        ->groupBy('file_type')
        ->orderBy('count', 'desc')
        ->get();

    echo "📁 أنواع الملفات المتوفرة:\n";
    foreach ($fileTypes as $type) {
        echo "   - النوع: " . ($type->file_type ?: 'غير محدد') . " - العدد: {$type->count}\n";
    }
    echo "\n";

    // فحص المسارات
    $pathSamples = DB::table('attachments')
        ->select('person_identity_number', 'file_name', 'stored_file_name', 'file_path', 'file_type')
        ->whereNotNull('person_identity_number')
        ->where('person_identity_number', '!=', '')
        ->limit(5)
        ->get();

    echo "📂 عينة من مسارات الملفات:\n";
    foreach ($pathSamples as $sample) {
        echo "   رقم الشخص: {$sample->person_identity_number}\n";
        echo "   اسم الملف: {$sample->file_name}\n";
        echo "   اسم الملف المخزن: {$sample->stored_file_name}\n";
        echo "   المسار: {$sample->file_path}\n";
        echo "   النوع: {$sample->file_type}\n";
        echo "   ---\n";
    }

    // فحص السجل المحدد 000001
    echo "\n🎯 فحص السجل المحدد '000001':\n";
    $record001Files = DB::table('attachments')
        ->where('person_identity_number', '000001')
        ->get();

    if ($record001Files->isEmpty()) {
        echo "   ❌ لا توجد ملفات للسجل 000001 في جدول attachments\n";

        // البحث عن أرقام مشابهة
        $similarRecords = DB::table('attachments')
            ->where('person_identity_number', 'LIKE', '%1%')
            ->orWhere('person_identity_number', 'LIKE', '0001%')
            ->orWhere('person_identity_number', 'LIKE', '%0001%')
            ->select('person_identity_number', DB::raw('COUNT(*) as count'))
            ->groupBy('person_identity_number')
            ->get();

        if (!$similarRecords->isEmpty()) {
            echo "   🔍 أرقام مشابهة موجودة:\n";
            foreach ($similarRecords as $similar) {
                echo "     - {$similar->person_identity_number}: {$similar->count} ملف\n";
            }
        }
    } else {
        echo "   ✅ وُجد {$record001Files->count()} ملف للسجل 000001\n";
        foreach ($record001Files as $file) {
            echo "     - {$file->file_name} ({$file->file_type})\n";
        }
    }

    // فحص الملفات الفيزيائية للسجل 000001
    echo "\n📁 فحص الملفات الفيزيائية للسجل 000001:\n";
    $physicalPath = __DIR__ . '/public/storage/uploads/000001';

    if (is_dir($physicalPath)) {
        $files = array_diff(scandir($physicalPath), ['.', '..']);
        echo "   ✅ المجلد الفيزيائي موجود ويحتوي على " . count($files) . " ملف\n";

        echo "   📄 عينة من الملفات الفيزيائية:\n";
        $counter = 0;
        foreach ($files as $file) {
            if ($counter >= 5) break;
            $filePath = $physicalPath . '/' . $file;
            if (is_file($filePath)) {
                $size = filesize($filePath);
                echo "     - $file (" . number_format($size / 1024, 2) . " KB)\n";
                $counter++;
            }
        }
    } else {
        echo "   ❌ المجلد الفيزيائي غير موجود: $physicalPath\n";

        // البحث عن مجلدات أخرى
        $uploadsPath = __DIR__ . '/public/storage/uploads';
        if (is_dir($uploadsPath)) {
            $folders = array_diff(scandir($uploadsPath), ['.', '..']);
            echo "   🔍 المجلدات المتوفرة في uploads:\n";
            $counter = 0;
            foreach ($folders as $folder) {
                if ($counter >= 10) break;
                if (is_dir($uploadsPath . '/' . $folder)) {
                    $fileCount = count(array_diff(scandir($uploadsPath . '/' . $folder), ['.', '..']));
                    echo "     - $folder ($fileCount ملف)\n";
                    $counter++;
                }
            }
        }
    }

    echo "\n✅ انتهى فحص جدول attachments بنجاح!\n";

} catch (Exception $e) {
    echo "❌ خطأ في فحص جدول attachments: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
}
