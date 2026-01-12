<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\Api\SponsorshipSyncController;
use Illuminate\Http\Request;

echo "=== اختبار API استجابة enrichSponsorshipData ===" . PHP_EOL . PHP_EOL;

$controller = new SponsorshipSyncController();

// محاكاة طلب جلب بيانات كفالة
$request = new Request();
$request->merge([
    'identity_number' => '402555678', // رقم هوية يتيم موجود
]);

// جلب الكفالة
$sponsorship = DB::table('sponsorships')
    ->where('id', 4879)
    ->first();

if (!$sponsorship) {
    echo "❌ الكفالة غير موجودة" . PHP_EOL;
    exit;
}

echo "الكفالة موجودة: ID {$sponsorship->id}" . PHP_EOL;

// استخدام reflection للوصول إلى الدالة الخاصة
$method = new ReflectionMethod($controller, 'enrichSponsorshipData');
$method->setAccessible(true);

$result = $method->invoke($controller, $sponsorship);

echo PHP_EOL . "=== بيانات المعيل المُرجعة ===" . PHP_EOL;
echo "guardian_first_name: " . ($result['guardian_first_name'] ?? 'غير موجود') . PHP_EOL;
echo "guardian_father_name: " . ($result['guardian_father_name'] ?? 'غير موجود') . PHP_EOL;
echo "guardian_grandfather_name: " . ($result['guardian_grandfather_name'] ?? 'غير موجود') . PHP_EOL;
echo "guardian_family_name: " . ($result['guardian_family_name'] ?? 'غير موجود') . PHP_EOL;
echo "guardian_data_source: " . ($result['guardian_data_source'] ?? 'غير موجود') . PHP_EOL;

echo PHP_EOL . "=== التحقق ===" . PHP_EOL;
if (($result['guardian_father_name'] ?? '') === 'عبد الناصر') {
    echo "✅ اسم الأب صحيح: عبد الناصر (بدون تقسيم خاطئ)" . PHP_EOL;
} else {
    echo "❌ اسم الأب: " . ($result['guardian_father_name'] ?? 'فارغ') . " (خطأ!)" . PHP_EOL;
}

if (($result['guardian_data_source'] ?? '') === 'data_table') {
    echo "✅ المصدر: جدول data (الأفضل)" . PHP_EOL;
} else {
    echo "⚠️ المصدر: " . ($result['guardian_data_source'] ?? 'غير محدد') . PHP_EOL;
}

echo PHP_EOL . "=== كامل البيانات ===" . PHP_EOL;
$guardianFields = array_filter(array_keys($result), function($k) {
    return strpos($k, 'guardian') !== false;
});
foreach ($guardianFields as $field) {
    echo "$field: " . ($result[$field] ?? 'NULL') . PHP_EOL;
}

echo PHP_EOL . "=== انتهى ===" . PHP_EOL;
