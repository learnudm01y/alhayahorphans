<?php

// اختبار دالة الإحصائيات الحقيقية مباشرة من الكنترولر
require_once 'vendor/autoload.php';

use App\Http\Controllers\UnifiedFileManagementController;
use Illuminate\Support\Facades\DB;

// إعداد Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 اختبار الإحصائيات الحقيقية للملفات المكررة مباشرة\n";
echo "=" . str_repeat("=", 55) . "\n\n";

try {
    // إنشاء instance من الكنترولر
    $controller = new UnifiedFileManagementController();

    echo "📊 استدعاء دالة getRealDuplicateFilesStatistics():\n";
    echo "=" . str_repeat("-", 40) . "\n";

    // استدعاء الدالة مباشرة
    $response = $controller->getRealDuplicateFilesStatistics();
    $content = $response->getContent();
    $data = json_decode($content, true);

    echo "✅ استجابة الكنترولر:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // التحقق من قاعدة البيانات مباشرة للمقارنة
    echo "🔍 التحقق من قاعدة البيانات مباشرة:\n";
    echo "=" . str_repeat("-", 30) . "\n";

    $totalFiles = DB::table('duplicate_files_temp')->count();
    $activeFiles = DB::table('duplicate_files_temp')
        ->where('expires_at', '>', now())
        ->count();
    $expiredFiles = DB::table('duplicate_files_temp')
        ->where('expires_at', '<=', now())
        ->count();
    $totalImages = DB::table('duplicate_files_temp')
        ->where('mime_type', 'like', 'image/%')
        ->count();
    $totalDocuments = DB::table('duplicate_files_temp')
        ->where(function ($query) {
            $query->where('mime_type', 'like', '%pdf%')
                  ->orWhere('mime_type', 'like', '%word%')
                  ->orWhere('mime_type', 'like', '%document%')
                  ->orWhere('mime_type', 'like', '%text%');
        })
        ->count();
    $totalSize = DB::table('duplicate_files_temp')->sum('file_size');

    echo "📊 إجمالي الملفات: $totalFiles\n";
    echo "🟢 الملفات النشطة: $activeFiles\n";
    echo "🟡 الملفات المنتهية: $expiredFiles\n";
    echo "🖼️ الصور: $totalImages\n";
    echo "📄 المستندات: $totalDocuments\n";
    echo "💾 المساحة الإجمالية: " . number_format($totalSize / (1024*1024), 2) . " MB\n\n";

    // مقارنة النتائج
    if ($data['success'] && isset($data['data'])) {
        $apiData = $data['data'];
        echo "🔄 مقارنة النتائج:\n";
        echo "=" . str_repeat("-", 30) . "\n";

        $matches = [
            'إجمالي الملفات' => $apiData['total_files'] == $totalFiles,
            'الملفات النشطة' => $apiData['active_files'] == $activeFiles,
            'الملفات المنتهية' => $apiData['expired_files'] == $expiredFiles,
            'الصور' => $apiData['total_images'] == $totalImages,
            'المستندات' => $apiData['total_documents'] == $totalDocuments,
            'المساحة' => $apiData['total_size'] == $totalSize
        ];

        foreach ($matches as $field => $match) {
            echo ($match ? "✅" : "❌") . " $field: " . ($match ? "متطابق" : "غير متطابق") . "\n";
            if (!$match) {
                $dbValue = match($field) {
                    'إجمالي الملفات' => $totalFiles,
                    'الملفات النشطة' => $activeFiles,
                    'الملفات المنتهية' => $expiredFiles,
                    'الصور' => $totalImages,
                    'المستندات' => $totalDocuments,
                    'المساحة' => $totalSize
                };
                $apiValue = match($field) {
                    'إجمالي الملفات' => $apiData['total_files'],
                    'الملفات النشطة' => $apiData['active_files'],
                    'الملفات المنتهية' => $apiData['expired_files'],
                    'الصور' => $apiData['total_images'],
                    'المستندات' => $apiData['total_documents'],
                    'المساحة' => $apiData['total_size']
                };
                echo "   📊 قاعدة البيانات: $dbValue, API: $apiValue\n";
            }
        }

        $allMatch = array_reduce($matches, function($carry, $item) {
            return $carry && $item;
        }, true);

        echo "\n" . ($allMatch ? "🎉 جميع النتائج متطابقة!" : "⚠️ هناك اختلافات في النتائج") . "\n";

        // عرض بيانات تفصيلية أكثر
        if ($totalFiles > 0) {
            echo "\n📋 تفاصيل إضافية:\n";
            echo "=" . str_repeat("-", 20) . "\n";

            // عينة من الملفات
            $sampleFiles = DB::table('duplicate_files_temp')
                ->select('original_name', 'mime_type', 'file_size', 'expires_at', 'created_at')
                ->limit(5)
                ->get();

            echo "📂 عينة من الملفات (أول 5 ملفات):\n";
            foreach ($sampleFiles as $file) {
                $isExpired = strtotime($file->expires_at) <= time();
                $status = $isExpired ? "منتهي" : "نشط";
                echo "  • {$file->original_name} ({$file->mime_type}) - {$status}\n";
            }
        } else {
            echo "\n⚠️ لا توجد ملفات مكررة في النظام\n";
        }
    }

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 في الملف: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "🔧 التفاصيل: " . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 55) . "\n";
echo "✅ انتهى الاختبار\n";

?>
