<?php
/**
 * اختبار تدفق العمل الكامل للتطبيق المحمول
 * 1. تسجيل الدخول
 * 2. جلب الكفالات
 * 3. رفع بيانات المزامنة
 * 4. التحقق من النتائج
 */

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 اختبار تدفق العمل الكامل للتطبيق المحمول\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// إعدادات الاتصال
// للاختبار المحلي: http://127.0.0.1:8000/api/mobile
// للإنتاج: https://alhayahorphans.org/api/mobile
$baseUrl = 'http://127.0.0.1:8000/api/mobile';
$credentials = [
    'username' => 'admin@gmail.com',
    'password' => 'password'
];

$results = [
    'login' => false,
    'fetch_sponsorships' => false,
    'upload_sync' => false
];

// ═══════════════════════════════════════════════════════════════
// الخطوة 1: تسجيل الدخول
// ═══════════════════════════════════════════════════════════════
echo "📋 الخطوة 1: تسجيل الدخول\n";
echo str_repeat("-", 50) . "\n";

$loginUrl = $baseUrl . '/login';
$loginData = json_encode($credentials);

echo "📡 URL: {$loginUrl}\n";
echo "👤 Username: {$credentials['username']}\n";
echo "🔒 Password: " . str_repeat('*', strlen($credentials['password'])) . "\n\n";

$ch = curl_init($loginUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $loginData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$token = null;
$userId = null;

if ($error) {
    echo "❌ خطأ CURL: {$error}\n";
} else {
    $responseData = json_decode($response, true);

    if ($httpCode === 200 && isset($responseData['token'])) {
        $token = $responseData['token'];
        $userId = $responseData['user']['id'] ?? null;
        $results['login'] = true;

        echo "✅ تسجيل الدخول نجح!\n";
        echo "   - Token: " . substr($token, 0, 30) . "...\n";
        echo "   - User ID: {$userId}\n";
        echo "   - Username: " . ($responseData['user']['username'] ?? $responseData['user']['name'] ?? 'N/A') . "\n";
    } else {
        echo "❌ تسجيل الدخول فشل! HTTP: {$httpCode}\n";
        echo "   الرد: " . substr($response, 0, 200) . "\n";
    }
}

if (!$token) {
    echo "\n⚠️ لا يمكن المتابعة بدون token صالح\n";
    exit(1);
}

echo "\n" . str_repeat("═", 60) . "\n\n";

// ═══════════════════════════════════════════════════════════════
// الخطوة 2: جلب الكفالات
// ═══════════════════════════════════════════════════════════════
echo "📋 الخطوة 2: جلب الكفالات\n";
echo str_repeat("-", 50) . "\n";

$sponsorshipsUrl = $baseUrl . '/sync/sponsorships?page=1&per_page=5';

echo "📡 URL: {$sponsorshipsUrl}\n\n";

$ch = curl_init($sponsorshipsUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$sponsorships = [];

if ($error) {
    echo "❌ خطأ CURL: {$error}\n";
} else {
    $responseData = json_decode($response, true);

    if ($httpCode === 200 && isset($responseData['data'])) {
        $sponsorships = $responseData['data'];
        $results['fetch_sponsorships'] = true;

        echo "✅ تم جلب الكفالات بنجاح!\n";
        echo "   - عدد الكفالات: " . count($sponsorships) . "\n";
        echo "   - الإجمالي: " . ($responseData['meta']['total'] ?? 'N/A') . "\n\n";

        echo "📊 الكفالات المتاحة:\n";
        foreach ($sponsorships as $i => $sp) {
            $personType = $sp['person_type'] ?? 'غير محدد';
            $orphanName = $sp['orphan_name'] ?? 'غير محدد';
            $guardianName = $sp['guardian_name'] ?? 'غير محدد';

            echo "   " . ($i + 1) . ". ID: {$sp['id']} | النوع: {$personType}\n";
            echo "      المكفول: {$orphanName}\n";
            echo "      المعيل: {$guardianName}\n";
            echo "      رقم الملف: " . ($sp['internal_file_number'] ?? 'N/A') . "\n";
            echo "      relation_id: " . ($sp['relation_id_number'] ?? 'فارغ') . "\n\n";
        }
    } else {
        echo "❌ جلب الكفالات فشل! HTTP: {$httpCode}\n";
        echo "   الرد: " . substr($response, 0, 300) . "\n";
    }
}

if (empty($sponsorships)) {
    echo "\n⚠️ لا توجد كفالات للاختبار\n";
    exit(1);
}

echo "\n" . str_repeat("═", 60) . "\n\n";

// ═══════════════════════════════════════════════════════════════
// الخطوة 3: رفع بيانات المزامنة
// ═══════════════════════════════════════════════════════════════
echo "📋 الخطوة 3: رفع بيانات المزامنة\n";
echo str_repeat("-", 50) . "\n";

// اختيار كفالة للاختبار
$testSponsorship = $sponsorships[0];
$personType = $testSponsorship['person_type'] ?? 'family_member';

echo "🎯 الكفالة المختارة: ID {$testSponsorship['id']}\n";
echo "   النوع: {$personType}\n\n";

// تجهيز البيانات الوهمية حسب نوع الشخص
$testTimestamp = date('Y-m-d H:i:s');
$testPhone = '059' . rand(1000000, 9999999);
$testPhone2 = '059' . rand(1000000, 9999999);
$testAddress = 'غزة - اختبار API - ' . $testTimestamp;

$updates = [
    'person_type' => $personType,
    'first_name' => 'اختبار',
    'second_name' => 'API',
    'third_name' => 'مزامنة',
    'last_name' => 'سيرفر',
    'orphan_phone' => $testPhone,
    'orphan_phone2' => $testPhone2,
    'orphan_detailed_address' => $testAddress
];

// إضافة بيانات المعيل إذا كان النوع يدعمها
if (!in_array($personType, ['deceased_father', 'deceased_mother', 'breadwinner'])) {
    $updates['guardian_first_name'] = 'معيل';
    $updates['guardian_father_name'] = 'اختبار';
    $updates['guardian_grandfather_name'] = 'API';
    $updates['guardian_family_name'] = 'سيرفر';
    $updates['guardian_phone'] = '059' . rand(1000000, 9999999);
    $updates['guardian_phone2'] = '059' . rand(1000000, 9999999);
    $updates['guardian_detailed_address'] = 'عنوان المعيل - ' . $testTimestamp;
}

echo "📤 البيانات المُرسلة:\n";
echo "   - الهاتف: {$testPhone}\n";
echo "   - الهاتف البديل: {$testPhone2}\n";
echo "   - العنوان: {$testAddress}\n\n";

$uploadUrl = $baseUrl . '/sync/upload';
$uploadData = json_encode([
    'sponsorship_id' => $testSponsorship['id'],
    'updates' => $updates
]);

echo "📡 URL: {$uploadUrl}\n\n";

$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $uploadData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ خطأ CURL: {$error}\n";
} else {
    $responseData = json_decode($response, true);

    if ($httpCode === 200 && ($responseData['success'] ?? false)) {
        $results['upload_sync'] = true;

        echo "✅ رفع المزامنة نجح!\n";
        echo "   - الرسالة: " . ($responseData['message'] ?? 'N/A') . "\n";
        echo "   - sync_timestamp: " . ($responseData['sync_timestamp'] ?? 'N/A') . "\n";
    } else {
        echo "❌ رفع المزامنة فشل! HTTP: {$httpCode}\n";
        echo "   الرد: " . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n" . str_repeat("═", 60) . "\n\n";

// ═══════════════════════════════════════════════════════════════
// الخطوة 4: التحقق من البيانات
// ═══════════════════════════════════════════════════════════════
echo "📋 الخطوة 4: التحقق من البيانات\n";
echo str_repeat("-", 50) . "\n";

// جلب تفاصيل الكفالة بعد التحديث
$detailsUrl = $baseUrl . '/sync/sponsorship/' . $testSponsorship['id'];

echo "📡 URL: {$detailsUrl}\n\n";

$ch = curl_init($detailsUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ خطأ CURL: {$error}\n";
} else {
    $responseData = json_decode($response, true);

    if ($httpCode === 200) {
        echo "✅ تم جلب تفاصيل الكفالة بنجاح!\n\n";

        $sp = $responseData['sponsorship'] ?? $responseData;

        echo "📊 البيانات بعد التحديث:\n";
        echo "   - المكفول: " . ($sp['orphan_name'] ?? 'N/A') . "\n";
        echo "   - المعيل: " . ($sp['guardian_name'] ?? 'N/A') . "\n";
        echo "   - هوية المكفول: " . ($sp['identity_number'] ?? 'N/A') . "\n";
        echo "   - relation_id_number: " . ($sp['relation_id_number'] ?? 'N/A') . "\n";

        // التحقق من حفظ البيانات الإضافية
        if (isset($sp['orphan_phone']) || isset($sp['extra_fields'])) {
            echo "\n   📞 البيانات الإضافية:\n";
            echo "   - الهاتف: " . ($sp['orphan_phone'] ?? $sp['extra_fields']['orphan_phone'] ?? 'N/A') . "\n";
            echo "   - الهاتف البديل: " . ($sp['orphan_phone2'] ?? $sp['extra_fields']['orphan_phone2'] ?? 'N/A') . "\n";
            echo "   - العنوان: " . ($sp['orphan_detailed_address'] ?? $sp['extra_fields']['orphan_detailed_address'] ?? 'N/A') . "\n";
        }
    } else {
        echo "❌ جلب التفاصيل فشل! HTTP: {$httpCode}\n";
    }
}

echo "\n" . str_repeat("═", 60) . "\n";

// ═══════════════════════════════════════════════════════════════
// الملخص النهائي
// ═══════════════════════════════════════════════════════════════
echo "\n📊 ملخص الاختبار:\n";
echo str_repeat("═", 40) . "\n";
echo "   1. تسجيل الدخول: " . ($results['login'] ? "✅ نجاح" : "❌ فشل") . "\n";
echo "   2. جلب الكفالات: " . ($results['fetch_sponsorships'] ? "✅ نجاح" : "❌ فشل") . "\n";
echo "   3. رفع المزامنة: " . ($results['upload_sync'] ? "✅ نجاح" : "❌ فشل") . "\n";
echo str_repeat("═", 40) . "\n";

$allSuccess = $results['login'] && $results['fetch_sponsorships'] && $results['upload_sync'];
if ($allSuccess) {
    echo "\n🎉🎉🎉 جميع الاختبارات ناجحة! 🎉🎉🎉\n";

    echo "\n📝 معلومات للتحقق على السيرفر:\n";
    echo "   - Sponsorship ID: {$testSponsorship['id']}\n";
    echo "   - Phone: {$testPhone}\n";
    echo "   - Address: {$testAddress}\n";
    echo "\n   للتحقق على السيرفر:\n";
    echo "   ssh -i ~/.ssh/id_hos2025 root@148.230.114.43\n";
    echo "   mysql -u root -p aso_production\n";
    echo "   SELECT * FROM sponsorships WHERE id = {$testSponsorship['id']};\n";
} else {
    echo "\n⚠️ بعض الاختبارات فشلت\n";
}
