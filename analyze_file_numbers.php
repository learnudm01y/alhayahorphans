<?php
/**
 * تحليل أرقام الملفات واختبار الإصلاحات
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "====================================================\n";
echo "🧪 تحليل أرقام الملفات واختبار الإصلاحات\n";
echo "====================================================\n\n";

// ======================================
// 1. تحليل أرقام الملفات
// ======================================
echo "📊 1. أعلى الأرقام في كل جدول:\n";
$maxData = DB::table('data')
    ->selectRaw("MAX(CAST(file_id_number as UNSIGNED)) as max_code")
    ->whereRaw("LENGTH(file_id_number) = 6 AND file_id_number REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "   - data.file_id_number: " . ($maxData ?? 'NULL') . "\n";

$maxSponsorshipInternal = DB::table('sponsorships')
    ->selectRaw("MAX(CAST(internal_file_number as UNSIGNED)) as max_code")
    ->whereRaw("LENGTH(internal_file_number) = 6 AND internal_file_number REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "   - sponsorships.internal_file_number: " . ($maxSponsorshipInternal ?? 'NULL') . "\n";

$maxReserved = DB::table('reserved_codes')
    ->selectRaw("MAX(CAST(code as UNSIGNED)) as max_code")
    ->whereRaw("LENGTH(code) = 6 AND code REGEXP '^[0-9]+$'")
    ->value('max_code');
echo "   - reserved_codes.code: " . ($maxReserved ?? 'NULL') . "\n";

$maxAll = max((int)$maxData, (int)$maxSponsorshipInternal, (int)$maxReserved);
echo "\n📌 أعلى رقم: $maxAll\n";

// ======================================
// 2. تحليل الأرقام المحجوزة
// ======================================
echo "\n📊 2. تحليل جدول reserved_codes:\n";
$reservedTotal = DB::table('reserved_codes')->count();
$reservedUsed = DB::table('reserved_codes')->where('used', true)->count();
$reservedUnused = DB::table('reserved_codes')->where('used', false)->count();
$reservedOldUnused = DB::table('reserved_codes')
    ->where('used', false)
    ->where('reserved_at', '<', now()->subHour())
    ->count();

echo "   - إجمالي السجلات: $reservedTotal\n";
echo "   - مستخدم: $reservedUsed\n";
echo "   - غير مستخدم: $reservedUnused\n";
echo "   - غير مستخدم (قديم > ساعة): $reservedOldUnused\n";

// ======================================
// 3. تنظيف الأرقام المحجوزة القديمة
// ======================================
if ($reservedOldUnused > 0) {
    echo "\n🧹 3. تنظيف الأرقام المحجوزة القديمة غير المستخدمة...\n";
    $deleted = cleanupOldUnusedReservedCodes(1);
    echo "   ✅ تم حذف $deleted سجل\n";
}

// ======================================
// 4. اختبار البحث عن الفجوات بعد التنظيف
// ======================================
echo "\n📊 4. اختبار البحث عن الفجوات بعد التنظيف:\n";
$gap = findSmallestGap('data', 'file_id_number');
if ($gap) {
    echo "   ✅ وجدت فجوة: " . str_pad($gap, 6, '0', STR_PAD_LEFT) . "\n";
} else {
    echo "   ℹ️ لا توجد فجوات متاحة\n";
}

// ======================================
// 5. اختبار توليد رقم جديد
// ======================================
echo "\n📊 5. اختبار توليد رقم جديد:\n";
DB::beginTransaction();
try {
    $newCode = generateUniqueReservedCode('data', 'file_id_number');
    echo "   ✅ الرقم الجديد: $newCode\n";

    // التحقق من أنه يستخدم الفجوة إذا كانت موجودة
    $codeInt = (int) $newCode;
    if ($gap && $codeInt == $gap) {
        echo "   🎯 استخدم الفجوة بنجاح!\n";
    } elseif ($codeInt == $maxAll + 1) {
        echo "   📈 استخدم الرقم التسلسلي التالي\n";
    } else {
        echo "   🔢 استخدم رقم: $codeInt\n";
    }
} finally {
    DB::rollBack();
    echo "   ℹ️ تم التراجع عن الاختبار\n";
}

// ======================================
// 6. إحصائيات الكفالات المكررة
// ======================================
echo "\n📊 6. إحصائيات الكفالات المكررة (نفس الشخص مع نفس الجمعية):\n";
$duplicates = DB::table('sponsorships')
    ->select('identity_number', 'sponsor_id', DB::raw('COUNT(*) as count'))
    ->whereNotNull('identity_number')
    ->whereNotNull('sponsor_id')
    ->groupBy('identity_number', 'sponsor_id')
    ->having(DB::raw('COUNT(*)'), '>', 1)
    ->get();

if ($duplicates->count() > 0) {
    echo "   ⚠️ وجدت " . $duplicates->count() . " حالة تكرار\n";
    foreach ($duplicates->take(5) as $dup) {
        echo "      - هوية: {$dup->identity_number}, جمعية: {$dup->sponsor_id}, عدد: {$dup->count}\n";
    }
} else {
    echo "   ✅ لا توجد كفالات مكررة\n";
}

echo "\n====================================================\n";
echo "✅ انتهى التحليل\n";
echo "====================================================\n";
