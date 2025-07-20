<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel application instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application to initialize facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== اختبار الاستعلامات المُصححة ===\n\n";

try {
    // 1. اختبار استعلام المجلدات من attachments
    echo "1️⃣ اختبار استعلام المجلدات من attachments:\n";
    echo str_repeat('-', 50) . "\n";

    $attachmentFolders = DB::table('attachments')
        ->select(DB::raw("
            person_identity_number as folder_name,
            COUNT(*) as files_count,
            SUM(file_size) as total_size,
            MAX(updated_at) as last_modified,
            GROUP_CONCAT(DISTINCT file_type) as file_types
        "))
        ->whereNotNull('person_identity_number')
        ->where('person_identity_number', '!=', '')
        ->groupBy('person_identity_number')
        ->limit(5)
        ->get();

    echo "✅ تم الاستعلام بنجاح!\n";
    echo "📊 عدد المجلدات: " . $attachmentFolders->count() . "\n\n";

    foreach ($attachmentFolders as $folder) {
        echo sprintf(
            "📂 %-15s | %d ملف | %s KB | %s\n",
            $folder->folder_name,
            $folder->files_count,
            number_format($folder->total_size / 1024, 2),
            $folder->last_modified
        );
    }

    // 2. اختبار استعلام محتويات المجلد
    echo "\n\n2️⃣ اختبار محتويات مجلد معين:\n";
    echo str_repeat('-', 50) . "\n";

    // اختيار أول مجلد
    $testFolder = $attachmentFolders->first()->folder_name ?? '000055';
    echo "اختبار المجلد: $testFolder\n\n";

    $folderFiles = DB::table('attachments')
        ->where('person_identity_number', $testFolder)
        ->orderBy('updated_at', 'desc')
        ->get();

    echo "✅ تم الاستعلام بنجاح!\n";
    echo "📊 عدد الملفات: " . $folderFiles->count() . "\n\n";

    foreach ($folderFiles->take(3) as $file) {
        echo sprintf(
            "📄 %-30s | %s | %s KB\n",
            $file->stored_file_name,
            $file->file_type,
            number_format($file->file_size / 1024, 2)
        );
    }

    // 3. اختبار دالة getPersonName
    echo "\n\n3️⃣ اختبار جلب أسماء الأشخاص:\n";
    echo str_repeat('-', 50) . "\n";

    function getPersonName($folderName)
    {
        try {
            // محاولة 1: مقارنة مباشرة
            $personData = DB::table('data')
                ->where('file_id_number', $folderName)
                ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
                ->first();

            // محاولة 2: إزالة الأصفار البادئة
            if (!$personData && preg_match('/^0+(\d+)$/', $folderName, $matches)) {
                $numericPart = (int)$matches[1];
                $personData = DB::table('data')
                    ->where('file_id_number', $numericPart)
                    ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
                    ->first();
            }

            if ($personData) {
                $nameComponents = array_filter([
                    $personData->data_first_name ?? '',
                    $personData->data_father_name ?? '',
                    $personData->data_grand_father_name ?? '',
                    $personData->data_family_name ?? ''
                ]);

                $fullName = trim(implode(' ', $nameComponents));
                return $fullName ?: 'غير محدد';
            }

            return 'غير مسجل';

        } catch (Exception $e) {
            return 'خطأ في البيانات';
        }
    }

    foreach ($attachmentFolders->take(5) as $folder) {
        $personName = getPersonName($folder->folder_name);
        echo sprintf(
            "👤 %-15s -> %s\n",
            $folder->folder_name,
            $personName
        );
    }

    // 4. محاكاة الاستعلام المدمج
    echo "\n\n4️⃣ محاكاة الاستعلام المدمج النهائي:\n";
    echo str_repeat('=', 60) . "\n";

    $finalResult = collect();

    // من attachments
    foreach ($attachmentFolders as $folder) {
        $folder->person_name = getPersonName($folder->folder_name);
        $folder->source = 'attachments';
        $finalResult->push($folder);
    }

    // من enhanced_attachments (لو موجود)
    if (Schema::hasTable('enhanced_attachments')) {
        $enhancedFolders = DB::table('enhanced_attachments')
            ->select(DB::raw("
                record_number as folder_name,
                COUNT(*) as files_count,
                SUM(file_size) as total_size,
                MAX(updated_at) as last_modified
            "))
            ->whereNotNull('record_number')
            ->where('record_number', '!=', '')
            ->whereIn('file_type', ['image', 'photo', 'document', 'pdf'])
            ->whereNull('deleted_at')
            ->groupBy('record_number')
            ->limit(3)
            ->get();

        foreach ($enhancedFolders as $folder) {
            $folder->person_name = getPersonName($folder->folder_name);
            $folder->source = 'enhanced_attachments';
            $finalResult->push($folder);
        }
    }

    echo "📊 إجمالي المجلدات المدمجة: " . $finalResult->count() . "\n\n";

    foreach ($finalResult->take(8) as $folder) {
        echo sprintf(
            "📂 %-12s | %-30s | %d ملف | %s\n",
            $folder->folder_name,
            $folder->person_name,
            $folder->files_count,
            $folder->source
        );
    }

    echo "\n✅ جميع الاستعلامات تعمل بشكل صحيح!\n";
    echo "🎉 النظام جاهز للاستخدام!\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    echo "📍 التفاصيل: " . $e->getTraceAsString() . "\n";
}
