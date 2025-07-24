<?php
// اختبار سريع للتأكد من أن الخادم يعمل
echo "✅ الخادم يعمل بشكل صحيح<br>";
echo "⏰ الوقت الحالي: " . date('Y-m-d H:i:s') . "<br>";
echo "📍 المجلد الحالي: " . __DIR__ . "<br>";
echo "🔧 إصدار PHP: " . PHP_VERSION . "<br>";

// فحص الوظائف المطلوبة
echo "<h3>🔍 فحص الوظائف المطلوبة:</h3>";
echo "cURL: " . (function_exists('curl_init') ? '✅ متوفرة' : '❌ غير متوفرة') . "<br>";
echo "JSON: " . (function_exists('json_encode') ? '✅ متوفرة' : '❌ غير متوفرة') . "<br>";
echo "File Upload: " . (ini_get('file_uploads') ? '✅ مفعلة' : '❌ معطلة') . "<br>";

echo "<h3>📊 إعدادات الرفع:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";

echo "<h3>🚀 النظام الجديد:</h3>";
echo "الحد الأدنى للدفعة: 10 ملفات<br>";
echo "الحد الأقصى لحجم الدفعة: 6 ميجابايت<br>";
echo "الحد الأقصى لحجم الملف: 1.5 ميجابايت<br>";

echo "<h3>🔗 روابط الاختبار:</h3>";
echo '<a href="/batch-upload-test.html" target="_blank">🧪 صفحة اختبار النظام الجديد</a><br>';
echo '<a href="/admin/file-management" target="_blank">📁 إدارة الملفات الأساسية</a><br>';
?>
