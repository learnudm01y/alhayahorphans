<?php
/**
 * اختبار تسجيل الدخول عبر API
 */

// بيانات الاختبار
$apiUrl = 'https://alhayahorphans.org/api/mobile/login';
$username = 'test_user'; // استبدل بالمستخدم الفعلي
$password = 'test_password'; // استبدل بكلمة المرور الفعلية

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║              اختبار تسجيل الدخول عبر API                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

echo "📡 URL: {$apiUrl}\n";
echo "👤 Username: {$username}\n";
echo "🔒 Password: " . str_repeat('*', strlen($password)) . "\n\n";

// إنشاء البيانات
$data = json_encode([
    'username' => $username,
    'password' => $password
]);

echo "📤 إرسال الطلب...\n\n";

// إرسال الطلب
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "📥 الاستجابة:\n";
echo str_repeat("─", 60) . "\n";
echo "HTTP Code: {$httpCode}\n";

if ($error) {
    echo "❌ خطأ CURL: {$error}\n";
} else {
    echo "\n";
    $responseData = json_decode($response, true);

    if ($httpCode === 200) {
        echo "✅ تسجيل الدخول نجح!\n\n";
        echo "Token: " . (isset($responseData['token']) ? substr($responseData['token'], 0, 20) . '...' : 'N/A') . "\n";
        echo "User ID: " . ($responseData['user']['id'] ?? 'N/A') . "\n";
        echo "Username: " . ($responseData['user']['username'] ?? 'N/A') . "\n";
    } else {
        echo "❌ تسجيل الدخول فشل!\n\n";
        echo "الرد الكامل:\n";
        echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n" . str_repeat("─", 60) . "\n";
echo "\n💡 ملاحظة: استبدل username و password ببيانات حقيقية للاختبار\n";
