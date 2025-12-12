<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== البحث عن جميع الحسابات البنكية المكررة ===\n\n";

// البحث عن جميع السجلات المكررة
$duplicates = DB::select("
    SELECT
        guardian_registration,
        person_owner_identity_number,
        re_phone_number,
        bank_name,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY id) as ids,
        MIN(id) as keep_id,
        MIN(created_at) as first_created
    FROM guardian_bank_accounts
    GROUP BY
        guardian_registration,
        person_owner_identity_number,
        re_phone_number,
        bank_name
    HAVING COUNT(*) > 1
    ORDER BY count DESC
");

if (empty($duplicates)) {
    echo "✅ لا توجد حسابات مكررة!\n";
    exit(0);
}

echo "❌ تم العثور على " . count($duplicates) . " مجموعة من الحسابات المكررة:\n\n";

$totalDuplicates = 0;
$idsToDelete = [];

foreach ($duplicates as $index => $duplicate) {
    $count = $duplicate->count;
    $duplicateCount = $count - 1; // عدد النسخ المكررة (نحتفظ بواحدة)
    $totalDuplicates += $duplicateCount;

    echo "مجموعة #" . ($index + 1) . ":\n";
    echo "  رقم الملف: {$duplicate->guardian_registration}\n";
    echo "  رقم هوية صاحب الحساب: {$duplicate->person_owner_identity_number}\n";
    echo "  الهاتف: {$duplicate->re_phone_number}\n";
    echo "  البنك: {$duplicate->bank_name}\n";
    echo "  عدد التكرارات: {$count}\n";
    echo "  المعرفات: {$duplicate->ids}\n";
    echo "  سيتم الاحتفاظ بـ: ID {$duplicate->keep_id} (تاريخ: {$duplicate->first_created})\n";

    // إضافة جميع المعرفات ما عدا الأول للحذف
    $ids = explode(',', $duplicate->ids);
    $keepId = $duplicate->keep_id;

    foreach ($ids as $id) {
        if ($id != $keepId) {
            $idsToDelete[] = $id;
        }
    }

    echo "  سيتم حذف: " . implode(', ', array_filter($ids, fn($id) => $id != $keepId)) . "\n";
    echo str_repeat('-', 70) . "\n";
}

echo "\n=== ملخص ===\n";
echo "إجمالي المجموعات المكررة: " . count($duplicates) . "\n";
echo "إجمالي السجلات المكررة التي سيتم حذفها: " . $totalDuplicates . "\n";
echo "إجمالي المعرفات التي سيتم حذفها: " . count($idsToDelete) . "\n\n";

// طلب التأكيد
echo "⚠️ هل تريد حذف السجلات المكررة؟ (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes') {
    echo "❌ تم إلغاء عملية الحذف\n";
    exit(0);
}

// تنفيذ الحذف
echo "\n🔄 جاري حذف السجلات المكررة...\n";

try {
    DB::beginTransaction();

    // حذف السجلات المكررة
    $deleted = DB::table('guardian_bank_accounts')
        ->whereIn('id', $idsToDelete)
        ->delete();

    DB::commit();

    echo "✅ تم حذف {$deleted} سجل بنجاح!\n\n";

    // التحقق من النتيجة
    $remainingDuplicates = DB::select("
        SELECT COUNT(*) as count
        FROM (
            SELECT
                guardian_registration,
                person_owner_identity_number,
                re_phone_number,
                bank_name,
                COUNT(*) as cnt
            FROM guardian_bank_accounts
            GROUP BY
                guardian_registration,
                person_owner_identity_number,
                re_phone_number,
                bank_name
            HAVING COUNT(*) > 1
        ) as duplicates
    ");

    $remaining = $remainingDuplicates[0]->count ?? 0;

    if ($remaining == 0) {
        echo "✅ جميع السجلات المكررة تم حذفها بنجاح!\n";
    } else {
        echo "⚠️ لا تزال هناك {$remaining} مجموعة مكررة\n";
    }

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ خطأ في حذف السجلات: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== اكتملت العملية ===\n";
