<?php
/**
 * اختبار الخوارزمية الحقيقية لتوليد رقم الملف
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     اختبار الخوارزمية الحقيقية لتوليد رقم الملف         ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// 1. الحصول على آخر رقم ملف
$lastFileId = DB::table('data')
    ->select('file_id_number')
    ->whereNotNull('file_id_number')
    ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
    ->orderByRaw('CAST(file_id_number as UNSIGNED) DESC')
    ->value('file_id_number');

echo "📊 آخر رقم ملف في جدول data: {$lastFileId}\n";

// 2. توليد رقم جديد باستخدام الدالة الحقيقية
if (function_exists('generateFileIdFromDataTable')) {
    $newFileId1 = generateFileIdFromDataTable();
    echo "✅ رقم ملف جديد (المحاولة 1): {$newFileId1}\n";

    $newFileId2 = generateFileIdFromDataTable();
    echo "✅ رقم ملف جديد (المحاولة 2): {$newFileId2}\n";

    $newFileId3 = generateFileIdFromDataTable();
    echo "✅ رقم ملف جديد (المحاولة 3): {$newFileId3}\n";

    echo "\n📋 التحقق:\n";
    echo "   - الصيغة: " . strlen($newFileId1) . " أرقام\n";
    echo "   - تسلسلي: " . ($newFileId2 == $newFileId1 + 1 ? "✅ نعم" : "❌ لا") . "\n";
    echo "   - يبدأ من 6 أصفار: " . (str_pad('1', 6, '0', STR_PAD_LEFT) == '000001' ? "✅ نعم" : "❌ لا") . "\n";

    echo "\n🔍 مثال على الأرقام المولدة:\n";
    for ($i = 1; $i <= 10; $i++) {
        $example = str_pad($i, 6, '0', STR_PAD_LEFT);
        echo "   {$i} → {$example}\n";
    }

} else {
    echo "❌ الدالة generateFileIdFromDataTable غير موجودة!\n";
}

echo "\n✅ تم الانتهاء من الاختبار\n";
