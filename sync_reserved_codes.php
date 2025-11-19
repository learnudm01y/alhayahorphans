<?php

/**
 * سكريبت مزامنة جدول reserved_codes مع جدول data
 * يُستخدم لإصلاح المشكلة الحالية حيث الأكواد محفوظة في data لكن used = 0 في reserved_codes
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "🔄 بدء مزامنة جدول reserved_codes مع جدول data...\n\n";

try {
    // الخطوة 1: جلب جميع الأكواد من جدول data
    echo "📊 جلب الأكواد من جدول data...\n";
    $usedCodes = DB::table('data')
        ->select('file_id_number')
        ->whereNotNull('file_id_number')
        ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
        ->whereRaw("LENGTH(file_id_number) = 6")
        ->pluck('file_id_number')
        ->toArray();

    $totalUsedCodes = count($usedCodes);
    echo "✅ تم العثور على {$totalUsedCodes} كود مستخدم في جدول data\n\n";

    // الخطوة 2: التحقق من الأكواد في reserved_codes
    echo "🔍 التحقق من حالة الأكواد في reserved_codes...\n";
    $updated = 0;
    $added = 0;
    $alreadyMarked = 0;

    foreach ($usedCodes as $code) {
        // التحقق من وجود الكود في reserved_codes
        $reservedCode = DB::table('reserved_codes')
            ->where('code', $code)
            ->first();

        if ($reservedCode) {
            // الكود موجود، تحديثه إذا كان used = 0
            if ($reservedCode->used == 0) {
                DB::table('reserved_codes')
                    ->where('code', $code)
                    ->update([
                        'used' => 1,
                        'updated_at' => now()
                    ]);
                $updated++;
                echo "  ✏️  تم تحديث الكود: {$code} (من used=0 إلى used=1)\n";
            } else {
                $alreadyMarked++;
            }
        } else {
            // الكود غير موجود، إضافته
            DB::table('reserved_codes')->insert([
                'code' => $code,
                'session_id' => 'sync_manual_' . time(),
                'reserved_at' => now(),
                'used' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $added++;
            echo "  ➕ تم إضافة الكود: {$code}\n";
        }
    }

    echo "\n";
    echo "=" . str_repeat("=", 60) . "\n";
    echo "✅ انتهت المزامنة بنجاح!\n";
    echo "=" . str_repeat("=", 60) . "\n";
    echo "📊 الإحصائيات:\n";
    echo "  • إجمالي الأكواد في data: {$totalUsedCodes}\n";
    echo "  • تم التحديث (used=0 → used=1): {$updated}\n";
    echo "  • تم الإضافة (أكواد مفقودة): {$added}\n";
    echo "  • بالفعل محدث (used=1): {$alreadyMarked}\n";
    echo "=" . str_repeat("=", 60) . "\n";

    // الخطوة 3: إحصائيات نهائية
    $statsAfter = [
        'total_in_reserved' => DB::table('reserved_codes')->count(),
        'used' => DB::table('reserved_codes')->where('used', 1)->count(),
        'unused' => DB::table('reserved_codes')->where('used', 0)->count(),
    ];

    echo "\n📈 حالة جدول reserved_codes بعد المزامنة:\n";
    echo "  • إجمالي السجلات: {$statsAfter['total_in_reserved']}\n";
    echo "  • مستخدم (used=1): {$statsAfter['used']}\n";
    echo "  • غير مستخدم (used=0): {$statsAfter['unused']}\n";

    Log::info('Manual sync of reserved_codes completed', [
        'updated' => $updated,
        'added' => $added,
        'already_marked' => $alreadyMarked,
        'total_used_codes' => $totalUsedCodes,
        'stats_after' => $statsAfter
    ]);

} catch (\Exception $e) {
    echo "\n";
    echo "❌ حدث خطأ: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
    Log::error('Manual sync of reserved_codes failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

echo "\n✅ تم!\n";
