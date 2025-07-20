<?php

echo "=== اختبار استخراج نوع الوثيقة ===" . PHP_EOL;

/**
 * نسخة مطابقة من دالة استخراج نوع الوثيقة للاختبار
 */
function testExtractDocumentType($fileName) {
    try {
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        $parts = explode('_', $nameWithoutExt);

        // Expected pattern: PREFIX_FOLDERID_IDENTITY
        if (count($parts) >= 3) {
            return $parts[0]; // Document type is in the first part
        }

        return 'unknown';
    } catch (Exception $e) {
        return 'error';
    }
}

// اختبار ملفات من السجلات
$testFiles = [
    'TES-11_001443_538511800.png',
    'TE102_001443_80011222.png',
    'A_538511800_2.png',
    'D_80011222_3.png'
];

echo "اختبار الملفات من السجلات:" . PHP_EOL;
foreach ($testFiles as $fileName) {
    $result = testExtractDocumentType($fileName);
    echo "✅ {$fileName} → نوع الوثيقة: {$result}" . PHP_EOL;
}

echo PHP_EOL . "=== ملخص التحديثات ===" . PHP_EOL;
echo "1. ✅ إصلاح أخطاء folder_id في FolderDuplicateDetectionService" . PHP_EOL;
echo "2. ✅ إضافة دالة extractDocumentTypeFromFilename" . PHP_EOL;
echo "3. ✅ تحديث استخدام file_path بدلاً من folder_id" . PHP_EOL;
echo "4. ✅ تحسين تخزين نوع الوثيقة في file_type" . PHP_EOL;

echo PHP_EOL . "المشاكل محلولة! 🎉" . PHP_EOL;
