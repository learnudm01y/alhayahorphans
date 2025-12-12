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

foreach ($duplicates as $index => $duplicate) {
    $count = $duplicate->count;
    $duplicateCount = $count - 1;
    $totalDuplicates += $duplicateCount;

    echo "مجموعة #" . ($index + 1) . ":\n";
    echo "  رقم الملف: {$duplicate->guardian_registration}\n";
    echo "  رقم هوية صاحب الحساب: {$duplicate->person_owner_identity_number}\n";
    echo "  الهاتف: {$duplicate->re_phone_number}\n";
    echo "  البنك: {$duplicate->bank_name}\n";
    echo "  عدد التكرارات: {$count}\n";
    echo "  المعرفات: {$duplicate->ids}\n";
    echo "  سيتم الاحتفاظ بـ: ID {$duplicate->keep_id} (تاريخ: {$duplicate->first_created})\n";

    $ids = explode(',', $duplicate->ids);
    $keepId = $duplicate->keep_id;
    $toDelete = array_filter($ids, fn($id) => $id != $keepId);

    echo "  سيتم حذف: " . implode(', ', $toDelete) . "\n";
    echo str_repeat('-', 70) . "\n";
}

echo "\n=== ملخص ===\n";
echo "إجمالي المجموعات المكررة: " . count($duplicates) . "\n";
echo "إجمالي السجلات المكررة التي يجب حذفها: " . $totalDuplicates . "\n\n";

echo "لحذف السجلات المكررة، قم بتشغيل: php cleanup_duplicates.php\n";
