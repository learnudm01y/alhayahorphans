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

echo "=== اختبار استخراج نوع الوثيقة من اسم الملف ===\n";

// دالة لاستخراج نوع الوثيقة من اسم الملف
function extractDocumentTypeFromFilename(string $processedFileName): string
{
    try {
        $nameWithoutExt = pathinfo($processedFileName, PATHINFO_FILENAME);
        $parts = explode('_', $nameWithoutExt);

        // Expected pattern: PREFIX_FOLDERID_IDENTITY
        if (count($parts) >= 3) {
            $documentType = $parts[0]; // Document type is in the first part
            return $documentType;
        }

        return 'unknown';
    } catch (\Exception $e) {
        return 'unknown';
    }
}

// اختبار أمثلة أسماء الملفات
$testFiles = [
    'TE102_001460_9214534563.jpg',
    'TES-11_001443_538002200.png',
    'TES_000029_1111111111.png',
    'undefined_000171_496.jpg',
    'A_566557550_4.jpg',
    'D_001430_4005245234.jpg'
];

foreach ($testFiles as $fileName) {
    $documentType = extractDocumentTypeFromFilename($fileName);
    echo "اسم الملف: {$fileName} -> نوع الوثيقة: '{$documentType}'\n";
}

echo "\n=== فحص أنواع الوثائق الموجودة في قاعدة البيانات ===\n";
$documentTypes = DB::table('document_types')
    ->select('id', 'pref', 'description')
    ->get();

foreach ($documentTypes as $docType) {
    echo "ID: {$docType->id} | البادئة: '{$docType->pref}' | الوصف: {$docType->description}\n";
}

echo "\n=== انتهى الاختبار ===\n";
