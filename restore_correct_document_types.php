<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// تحديد بيانات قاعدة البيانات
$config = [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'aso',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
];

$capsule = new DB;
$capsule->addConnection($config);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== استعادة أنواع الوثائق الصحيحة في جدول attachments ===\n";

// دالة لاستخراج نوع الوثيقة من اسم الملف
function extractDocumentTypeFromFilename($fileName)
{
    try {
        // التحقق من أن المدخل ليس null أو فارغ
        if (empty($fileName) || !is_string($fileName)) {
            return 'unknown';
        }

        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        $parts = explode('_', $nameWithoutExt);

        // Expected pattern: PREFIX_FOLDERID_IDENTITY
        if (count($parts) >= 3) {
            $documentType = $parts[0]; // Document type is in the first part
            return $documentType;
        }

        // إذا لم يتطابق النمط، حاول استخراج النوع من أول جزء
        if (count($parts) >= 1) {
            $firstPart = $parts[0];
            // تحقق من أن الجزء الأول يشبه نوع وثيقة
            if (preg_match('/^[A-Z]+(-\d+)?$/', $firstPart)) {
                return $firstPart;
            }
        }

        return 'unknown';
    } catch (\Exception $e) {
        return 'unknown';
    }
}

// البحث عن جميع السجلات
$allRecords = DB::table('attachments')
    ->select('id', 'stored_file_name', 'file_type')
    ->get();

echo "عدد السجلات الإجمالي: " . count($allRecords) . "\n";

$updatedCount = 0;
$errorCount = 0;
$noChangeCount = 0;

foreach ($allRecords as $record) {
    try {
        // تحقق من أن اسم الملف ليس null
        if (empty($record->stored_file_name)) {
            echo "⚠️ تم تخطي ID: {$record->id} - اسم الملف فارغ\n";
            $noChangeCount++;
            continue;
        }

        // استخراج نوع الوثيقة من اسم الملف
        $correctDocumentType = extractDocumentTypeFromFilename($record->stored_file_name);

        // تحقق إذا كان نوع الوثيقة مختلف عن المحفوظ حالياً
        if ($correctDocumentType !== $record->file_type) {
            // تحديث السجل
            $updated = DB::table('attachments')
                ->where('id', $record->id)
                ->update([
                    'file_type' => $correctDocumentType,
                    'updated_at' => now()
                ]);

            if ($updated) {
                echo "✅ تم تحديث ID: {$record->id} | الملف: {$record->stored_file_name} | من: '{$record->file_type}' إلى: '{$correctDocumentType}'\n";
                $updatedCount++;
            } else {
                echo "⚠️ فشل تحديث ID: {$record->id}\n";
                $errorCount++;
            }
        } else {
            $noChangeCount++;
        }

    } catch (\Exception $e) {
        echo "❌ خطأ في ID: {$record->id} - {$e->getMessage()}\n";
        $errorCount++;
    }
}

echo "\n=== تقرير الاستعادة ===\n";
echo "تم تحديث: {$updatedCount} سجل\n";
echo "لا يحتاج تحديث: {$noChangeCount} سجل\n";
echo "أخطاء: {$errorCount} سجل\n";

// التحقق من النتائج
echo "\n=== التحقق من النتائج النهائية ===\n";
$fileTypeCounts = DB::table('attachments')
    ->select('file_type', DB::raw('COUNT(*) as count'))
    ->groupBy('file_type')
    ->orderBy('count', 'desc')
    ->get();

foreach ($fileTypeCounts as $type) {
    echo "نوع الوثيقة: '{$type->file_type}' - العدد: {$type->count}\n";
}

echo "\n=== عينة من السجلات المحدثة ===\n";
$sampleRecords = DB::table('attachments')
    ->select('id', 'stored_file_name', 'file_type', 'updated_at')
    ->orderBy('updated_at', 'desc')
    ->limit(10)
    ->get();

foreach ($sampleRecords as $record) {
    echo "ID: {$record->id} | الملف: {$record->stored_file_name} | نوع الوثيقة: '{$record->file_type}' | آخر تحديث: {$record->updated_at}\n";
}

echo "\n=== انتهت الاستعادة ===\n";
