<?php
/**
 * اختبار أن الأرقام المولدة تُحجز في reserved_codes
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "🧪 اختبار حجز الأرقام في reserved_codes\n";
echo "=================================================================\n\n";

// 1. اختبار توليد رقم جديد
echo "1. توليد رقم جديد باستخدام generateFileIdFromDataTable():\n";
echo str_repeat("-", 50) . "\n";

$newCode = generateFileIdFromDataTable();
echo "   الرقم المولد: $newCode\n\n";

// 2. التحقق من وجود الرقم في reserved_codes
echo "2. التحقق من وجود الرقم في reserved_codes:\n";
echo str_repeat("-", 50) . "\n";

$reserved = DB::table('reserved_codes')->where('code', $newCode)->first();
if ($reserved) {
    echo "   ✅ الرقم موجود في reserved_codes!\n";
    echo "   - id: {$reserved->id}\n";
    echo "   - code: {$reserved->code}\n";
    echo "   - reserved_at: {$reserved->reserved_at}\n";
    echo "   - used: " . ($reserved->used ? 'نعم' : 'لا') . "\n";
    echo "   - session_id: " . ($reserved->session_id ?? 'NULL') . "\n";
} else {
    echo "   ❌ الرقم غير موجود في reserved_codes!\n";
}

echo "\n";

// 3. توليد 3 أرقام إضافية للتأكد
echo "3. توليد 3 أرقام إضافية للتأكد:\n";
echo str_repeat("-", 50) . "\n";

$allPassed = true;
for ($i = 1; $i <= 3; $i++) {
    $code = generateFileIdFromDataTable();
    $exists = DB::table('reserved_codes')->where('code', $code)->exists();

    $status = $exists ? "✅" : "❌";
    echo "   $status رقم $i: $code - " . ($exists ? "محجوز" : "غير محجوز!") . "\n";

    if (!$exists) $allPassed = false;
}

echo "\n";

// 4. فحص الرقم القديم 053458
echo "4. فحص الرقم القديم 053458:\n";
echo str_repeat("-", 50) . "\n";

$oldReserved = DB::table('reserved_codes')->where('code', '053458')->first();
if ($oldReserved) {
    echo "   ✅ الرقم 053458 موجود في reserved_codes\n";
} else {
    echo "   ⚠️ الرقم 053458 غير موجود في reserved_codes (تم توليده قبل الإصلاح)\n";
    echo "   💡 الأرقام الجديدة فقط ستُحجز تلقائياً\n";
}

echo "\n";

// 5. ملخص
echo "=================================================================\n";
echo "📊 الملخص:\n";
echo "=================================================================\n";

if ($allPassed && $reserved) {
    echo "🎉 جميع الأرقام الجديدة تُحجز بشكل صحيح في reserved_codes!\n";
} else {
    echo "⚠️ يوجد مشكلة في حجز بعض الأرقام\n";
}

// 6. عرض آخر 5 سجلات
echo "\n6. آخر 5 سجلات في reserved_codes:\n";
echo str_repeat("-", 50) . "\n";

$lastReserved = DB::table('reserved_codes')->orderBy('id', 'desc')->limit(5)->get();
foreach ($lastReserved as $r) {
    echo "   - code: {$r->code}, reserved_at: " . ($r->reserved_at ?? 'NULL') . "\n";
}

echo "\n";
