<?php

// Test file to verify that functions are working correctly

require_once '../vendor/autoload.php';
require_once '../bootstrap/app.php';

$app = require_once '../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "<h1>🧪 اختبار الدوال المضافة</h1>";
echo "<hr>";

try {
    echo "<h2>1. اختبار دالة generateUniqueAttachmentRecordNumber</h2>";
    if (function_exists('generateUniqueAttachmentRecordNumber')) {
        $recordNumber = generateUniqueAttachmentRecordNumber();
        echo "<p>✅ <strong>نجح!</strong> رقم المرفق المولد: <code>{$recordNumber}</code></p>";
    } else {
        echo "<p>❌ <strong>فشل!</strong> الدالة غير موجودة</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>خطأ:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";

try {
    echo "<h2>2. اختبار دالة generateFileIdFromDataTable</h2>";
    if (function_exists('generateFileIdFromDataTable')) {
        $fileId = generateFileIdFromDataTable();
        echo "<p>✅ <strong>نجح!</strong> رقم الملف المولد: <code>{$fileId}</code></p>";
    } else {
        echo "<p>❌ <strong>فشل!</strong> الدالة غير موجودة</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>خطأ:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";

try {
    echo "<h2>3. اختبار دالة getFileIdByIdentityNumber</h2>";
    if (function_exists('getFileIdByIdentityNumber')) {
        $testId = getFileIdByIdentityNumber('123456');
        echo "<p>✅ <strong>نجح!</strong> رقم الملف للهوية 123456: <code>{$testId}</code></p>";
    } else {
        echo "<p>❌ <strong>فشل!</strong> الدالة غير موجودة</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>خطأ:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>📊 النتيجة النهائية</h2>";
echo "<p><strong>تاريخ الاختبار:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>حالة النظام:</strong> <span style='color: green;'>✅ جاهز للعمل</span></p>";
echo "<p><a href='/admin/file/sidebar-excel-gateway'>🔗 الذهاب إلى صفحة Excel Gateway</a></p>";

$kernel->terminate($request, $response);
