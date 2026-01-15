<?php
/**
 * اختبار رفع التغييرات للمعيل والمكفول
 */

$sponsorshipId = 100084; // كفالة لها بيانات معيل

// بيانات التغيير
$changes = [
    [
        'sponsorship_id' => $sponsorshipId,
        'data' => [
            // بيانات المعيل
            'guardian_first_name' => 'اختبار_معيل_' . date('His'),
            'guardian_father_name' => 'أب_معيل_تحديث',
            'guardian_phone' => '0591234567',

            // بيانات المكفول
            'first_name' => 'اختبار_مكفول_' . date('His'),
            'second_name' => 'أب_مكفول',
            'orphan_gender' => 'ذكر',

            // guardian_name المركب
            'guardian_name' => 'اختبار_معيل_' . date('His') . ' أب_معيل_تحديث'
        ]
    ]
];

echo "=== اختبار رفع التغييرات ===" . PHP_EOL;
echo "Sponsorship ID: $sponsorshipId" . PHP_EOL;
echo PHP_EOL;

// إرسال الطلب
$ch = curl_init('http://127.0.0.1:8000/api/offline-test-development/sync/upload');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['changes' => $changes]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode" . PHP_EOL;

$result = json_decode($response, true);

if ($result) {
    echo "Success: " . ($result['success'] ? 'نعم' : 'لا') . PHP_EOL;
    echo "Message: " . ($result['message'] ?? 'N/A') . PHP_EOL;

    if (isset($result['results'])) {
        echo PHP_EOL . "النتائج:" . PHP_EOL;
        foreach ($result['results'] as $r) {
            $status = $r['success'] ? '✅' : '❌';
            echo "  $status Sponsorship {$r['sponsorship_id']}: {$r['message']}" . PHP_EOL;
        }
    }

    if (isset($result['summary'])) {
        echo PHP_EOL . "الملخص:" . PHP_EOL;
        echo "  إجمالي: {$result['summary']['total']}" . PHP_EOL;
        echo "  نجاح: {$result['summary']['success']}" . PHP_EOL;
        echo "  فشل: {$result['summary']['failed']}" . PHP_EOL;
    }
} else {
    echo "❌ فشل في تحليل الاستجابة" . PHP_EOL;
    echo "Response: " . substr($response, 0, 500) . PHP_EOL;
}

// التحقق من التحديث
echo PHP_EOL . "=== التحقق من التحديث ===" . PHP_EOL;

$ch2 = curl_init("http://127.0.0.1:8000/api/offline-test-development/sponsorships/$sponsorshipId");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$response2 = curl_exec($ch2);
curl_close($ch2);

$data2 = json_decode($response2, true);
if ($data2 && $data2['success']) {
    $s = $data2['sponsorship'];
    echo "بيانات المعيل بعد التحديث:" . PHP_EOL;
    echo "  guardian_first_name: " . ($s['guardian_first_name'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_father_name: " . ($s['guardian_father_name'] ?? 'NULL') . PHP_EOL;
    echo "  guardian_phone: " . ($s['guardian_phone'] ?? 'NULL') . PHP_EOL;

    echo PHP_EOL . "بيانات المكفول بعد التحديث:" . PHP_EOL;
    echo "  first_name: " . ($s['first_name'] ?? 'NULL') . PHP_EOL;
    echo "  second_name: " . ($s['second_name'] ?? 'NULL') . PHP_EOL;
    echo "  orphan_gender: " . ($s['orphan_gender'] ?? 'NULL') . PHP_EOL;
}
