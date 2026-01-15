<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تصحيح الأكواد غير المعلمة كمستخدمة ===\n\n";

// 1. تحديث 003624
$updated = DB::table('reserved_codes')
    ->where('code', '003624')
    ->update([
        'used' => true,
        'used_at' => now(),
        'notes' => 'تصحيح يدوي - كان مستخدم في data'
    ]);

echo $updated ? "✅ تم تحديث 003624\n" : "⚠️ 003624 لم يتم تحديثه\n";

// 2. فحص النتيجة
$reserved = DB::table('reserved_codes')->where('code', '003624')->first();
if ($reserved) {
    echo "\nحالة 003624 بعد التحديث:\n";
    echo "  - used: " . ($reserved->used ? 'true' : 'false') . "\n";
    echo "  - used_at: " . ($reserved->used_at ?? 'NULL') . "\n";
}

// 3. البحث عن أكواد أخرى مستخدمة لكن غير معلمة
echo "\n=== فحص الأكواد المستخدمة غير المعلمة ===\n";

// الأكواد في data
$dataCodesNotMarked = DB::table('data')
    ->join('reserved_codes', 'data.file_id_number', '=', 'reserved_codes.code')
    ->where('reserved_codes.used', false)
    ->select('data.file_id_number', 'data.data_first_name')
    ->limit(10)
    ->get();

if ($dataCodesNotMarked->count() > 0) {
    echo "\n⚠️ أكواد في data غير معلمة كمستخدمة:\n";
    foreach ($dataCodesNotMarked as $d) {
        echo "  - {$d->file_id_number} ({$d->data_first_name})\n";
    }
    
    // تصحيحها
    foreach ($dataCodesNotMarked as $d) {
        DB::table('reserved_codes')
            ->where('code', $d->file_id_number)
            ->update([
                'used' => true,
                'used_at' => now(),
                'notes' => 'تصحيح تلقائي - موجود في data'
            ]);
    }
    echo "✅ تم تصحيح " . $dataCodesNotMarked->count() . " كود\n";
} else {
    echo "✅ جميع الأكواد في data معلمة بشكل صحيح\n";
}

// الأكواد في re_people
$rePeopleCodesNotMarked = DB::table('re_people')
    ->join('reserved_codes', 're_people.registration_id', '=', 'reserved_codes.code')
    ->where('reserved_codes.used', false)
    ->select('re_people.registration_id', 're_people.first_name')
    ->limit(10)
    ->get();

if ($rePeopleCodesNotMarked->count() > 0) {
    echo "\n⚠️ أكواد في re_people غير معلمة كمستخدمة:\n";
    foreach ($rePeopleCodesNotMarked as $r) {
        echo "  - {$r->registration_id} ({$r->first_name})\n";
    }
    
    // تصحيحها
    foreach ($rePeopleCodesNotMarked as $r) {
        DB::table('reserved_codes')
            ->where('code', $r->registration_id)
            ->update([
                'used' => true,
                'used_at' => now(),
                'notes' => 'تصحيح تلقائي - موجود في re_people'
            ]);
    }
    echo "✅ تم تصحيح " . $rePeopleCodesNotMarked->count() . " كود\n";
} else {
    echo "✅ جميع الأكواد في re_people معلمة بشكل صحيح\n";
}

echo "\n=== انتهى ===\n";
