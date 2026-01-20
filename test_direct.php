<?php
/**
 * اختبار مباشر للخوارزمية
 */

require_once __DIR__ . '/vendor/autoload.php';

mb_internal_encoding('UTF-8');

// تحميل الملف وقراءة محتوياته مباشرة
$filePath = __DIR__ . '/app/Helpers/NameSegmentation.php';
$content = file_get_contents($filePath);

echo "=== فحص ملف NameSegmentation.php ===\n\n";
echo "حجم الملف: " . strlen($content) . " بايت\n";
echo "ترميز الملف: " . mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'ASCII']) . "\n\n";

// البحث عن الكلمات العربية
preg_match_all('/[\x{0600}-\x{06FF}]+/u', $content, $arabicWords);
echo "الكلمات العربية في الملف:\n";
$uniqueWords = array_unique($arabicWords[0]);
echo implode(', ', array_slice($uniqueWords, 0, 20)) . "...\n\n";

// الآن نختبر الدالة مباشرة
require_once $filePath;

use App\Helpers\NameSegmentation;

echo "=== اختبار segment() مباشرة ===\n\n";

$testName = 'عمر احمد نظمي سعدة';
echo "الاسم المُدخل: [$testName]\n";

// استدعاء الدالة
$result = NameSegmentation::segment($testName);

echo "\nالنتيجة:\n";
echo "  full_name: [{$result['full_name']}]\n";
echo "  segments_count: {$result['segments_count']}\n";
echo "  original_segments: [" . implode('] [', $result['original_segments']) . "]\n";
echo "  merged_segments: [" . implode('] [', $result['merged_segments']) . "]\n";
echo "\nالتوزيع:\n";
echo "  first_name: [{$result['first_name']}]\n";
echo "  father_name: [{$result['father_name']}]\n";
echo "  grand_father_name: [{$result['grand_father_name']}]\n";
echo "  family_name: [{$result['family_name']}]\n";
