<?php
/**
 * اختبار شامل لنظام تعليم الأكواد كمستخدمة
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "🧪 اختبار نظام تعليم الأكواد كمستخدمة\n";
echo "=================================================================\n\n";

// 1. توليد كود جديد
echo "📋 1. توليد كود جديد:\n";
echo str_repeat("-", 50) . "\n";

$newCode = generateFileIdFromDataTable();
echo "   الكود المولد: $newCode\n";

// فحص حالته
$reserved = DB::table('reserved_codes')->where('code', $newCode)->first();
if ($reserved) {
    echo "   - موجود في reserved_codes: ✅\n";
    echo "   - used: " . ($reserved->used ? 'true ⚠️ (يجب أن يكون false)' : 'false ✅') . "\n";
} else {
    echo "   - غير موجود في reserved_codes! ❌\n";
}

echo "\n";

// 2. اختبار دالة markCodeAsUsed
echo "📋 2. اختبار دالة markCodeAsUsed:\n";
echo str_repeat("-", 50) . "\n";

$marked = markCodeAsUsed($newCode, null, 'اختبار');
echo "   - نتيجة markCodeAsUsed: " . ($marked ? '✅ نجاح' : '❌ فشل') . "\n";

// فحص الحالة بعد التعليم
$reservedAfter = DB::table('reserved_codes')->where('code', $newCode)->first();
if ($reservedAfter) {
    echo "   - used بعد التعليم: " . ($reservedAfter->used ? 'true ✅' : 'false ❌') . "\n";
    echo "   - used_at: " . ($reservedAfter->used_at ?? 'NULL') . "\n";
}

echo "\n";

// 3. فحص الكود في OfflineTestController
echo "📋 3. فحص استدعاءات markCodeAsUsed في OfflineTestController:\n";
echo str_repeat("-", 50) . "\n";

$controllerPath = __DIR__ . '/app/Http/Controllers/Api/OfflineTestController.php';
$controllerContent = file_get_contents($controllerPath);

$markCodeCalls = substr_count($controllerContent, 'markCodeAsUsed(');
echo "   - عدد استدعاءات markCodeAsUsed: $markCodeCalls\n";

$expectedCalls = 6; // المعيل الرئيسي + relation_id + breadwinner + orphan + deceased_father + deceased_mother
$status = $markCodeCalls >= $expectedCalls ? '✅' : '⚠️';
echo "   - المتوقع: $expectedCalls أو أكثر $status\n";

// المواقع
$locations = [
    'معيل/ولي في data' => strpos($controllerContent, "markCodeAsUsed(\$unifiedFileIdNumber, null, 'معيل/ولي في data')") !== false,
    'relation_id_number' => strpos($controllerContent, "markCodeAsUsed(\$unifiedFileIdNumber, null, 'استخدم في sponsorship_id:") !== false,
    'breadwinner' => strpos($controllerContent, "markCodeAsUsed(\$newFileId, null, 'breadwinner في data')") !== false,
    'orphan/family_member' => strpos($controllerContent, "markCodeAsUsed(\$newFileId, null, 'orphan/family_member في re_people')") !== false,
    'deceased_father' => strpos($controllerContent, "markCodeAsUsed(\$newFileId, null, 'deceased_father في dead_people')") !== false,
    'deceased_mother' => strpos($controllerContent, "markCodeAsUsed(\$newFileId, null, 'deceased_mother في dead_people')") !== false,
];

echo "\n   المواقع:\n";
foreach ($locations as $loc => $exists) {
    echo "   - $loc: " . ($exists ? '✅' : '❌') . "\n";
}

echo "\n";

// 4. ملخص
echo "=================================================================\n";
echo "📊 الملخص:\n";
echo "=================================================================\n";

$allPassed = $reserved && $marked && $reservedAfter && $reservedAfter->used;
foreach ($locations as $exists) {
    if (!$exists) $allPassed = false;
}

if ($allPassed) {
    echo "🎉 نظام تعليم الأكواد يعمل بشكل صحيح!\n";
    echo "   - الأكواد الجديدة تُحجز في reserved_codes\n";
    echo "   - عند الاستخدام، يتم تعليمها كـ used = true\n";
    echo "   - يمنع هذا التضارب في الأرقام\n";
} else {
    echo "⚠️ يوجد مشاكل تحتاج مراجعة\n";
}

echo "\n";

// تنظيف الكود التجريبي (اختياري - لإبقائه للاختبار)
// DB::table('reserved_codes')->where('code', $newCode)->delete();

echo "=================================================================\n";
