<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel application instance
$app = require_once __DIR__ . '/bootstrap/app.php';

// Run the application to initialize facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== اختبار النظام المُحدث مع أسماء الأشخاص ===\n\n";

// محاكاة دالة getPersonName
function getPersonName($folderName)
{
    try {
        // محاولة 1: مقارنة مباشرة (للأرقام الكبيرة مثل 000029)
        $personData = DB::table('data')
            ->where('file_id_number', $folderName)
            ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
            ->first();

        // محاولة 2: إزالة الأصفار البادئة (للأرقام الصغيرة مثل 000010)
        if (!$personData && preg_match('/^0+(\d+)$/', $folderName, $matches)) {
            $numericPart = (int)$matches[1];
            $personData = DB::table('data')
                ->where('file_id_number', $numericPart)
                ->select('data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name')
                ->first();
        }

        if ($personData) {
            // تكوين الاسم الكامل
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

try {
    // اختبار مجلدات مختلفة
    $testFolders = ['000001', '000010', '000014', '000015', '000016', '000029', '000030'];

    echo "🧪 اختبار أسماء الأشخاص:\n";
    echo str_repeat('-', 50) . "\n";

    foreach ($testFolders as $folder) {
        $personName = getPersonName($folder);
        echo sprintf("%-10s -> %s\n", $folder, $personName);
    }

    // محاكاة دالة getImageFolders المُحدثة
    echo "\n📁 محاكاة قائمة المجلدات مع الأسماء:\n";
    echo str_repeat('=', 60) . "\n";

    // البحث في جدول attachments
    $attachmentFolders = DB::table('attachments')
        ->select(DB::raw("
            person_identity_number as folder_name,
            COUNT(*) as files_count,
            SUM(file_size) as total_size,
            MAX(updated_at) as last_modified
        "))
        ->whereNotNull('person_identity_number')
        ->where('person_identity_number', '!=', '')
        ->groupBy('person_identity_number')
        ->limit(5)
        ->get();

    foreach ($attachmentFolders as $folder) {
        $personName = getPersonName($folder->folder_name);
        $formattedSize = number_format($folder->total_size / 1024, 2) . ' KB';

        echo sprintf(
            "📂 %-10s | %-30s | %d ملف | %s\n",
            $folder->folder_name,
            $personName,
            $folder->files_count,
            $formattedSize
        );
    }

    // البحث في enhanced_attachments
    echo "\n📊 مجلدات enhanced_attachments مع الأسماء:\n";
    echo str_repeat('-', 60) . "\n";

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
        ->limit(5)
        ->get();

    foreach ($enhancedFolders as $folder) {
        $personName = getPersonName($folder->folder_name);
        $formattedSize = number_format($folder->total_size / 1024, 2) . ' KB';

        echo sprintf(
            "📂 %-10s | %-30s | %d ملف | %s\n",
            $folder->folder_name,
            $personName,
            $folder->files_count,
            $formattedSize
        );
    }

    echo "\n✅ النظام المُحدث جاهز مع عرض أسماء الأشخاص!\n";

} catch (Exception $e) {
    echo "❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
}
