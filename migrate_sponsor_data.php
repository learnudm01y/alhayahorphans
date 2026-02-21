<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== نقل البيانات من sponsor_id القديم إلى جدول sponsorship_sponsor ===\n\n";

// 1. حذف البيانات الحالية في جدول sponsorship_sponsor لتجنب التكرار
echo "1. حذف البيانات الحالية في sponsorship_sponsor...\n";
DB::table('sponsorship_sponsor')->truncate();
echo "   تم الحذف.\n\n";

// 2. نقل جميع الكفالات من sponsor_id القديم
echo "2. نقل البيانات من sponsor_id إلى sponsorship_sponsor...\n";

$sponsorships = DB::table('sponsorships')
    ->whereNotNull('sponsor_id')
    ->select('id', 'sponsor_id')
    ->get();

$inserted = 0;
$skipped = 0;

foreach ($sponsorships as $sponsorship) {
    try {
        DB::table('sponsorship_sponsor')->insert([
            'sponsorship_id' => $sponsorship->id,
            'sponsor_id' => $sponsorship->sponsor_id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $inserted++;

        if ($inserted % 100 == 0) {
            echo "   تم إدراج {$inserted} سجل...\n";
        }
    } catch (\Exception $e) {
        $skipped++;
        echo "   تخطي Sponsorship ID {$sponsorship->id}: {$e->getMessage()}\n";
    }
}

echo "\n   ✓ تم الإدراج: {$inserted} سجل\n";
echo "   ✗ تم التخطي: {$skipped} سجل\n\n";

// 3. التحقق من النتائج
echo "3. التحقق من النتائج النهائية...\n\n";

$sponsors = DB::table('sponsors')->whereIn('id', [4, 12])->get(['id', 'sponsor_name']);

foreach ($sponsors as $sponsor) {
    // عدد في العمود القديم
    $oldCount = DB::table('sponsorships')
        ->where('sponsor_id', $sponsor->id)
        ->count();

    // عدد في الجدول الجديد
    $newCount = DB::table('sponsorship_sponsor')
        ->where('sponsor_id', $sponsor->id)
        ->count();

    echo "   {$sponsor->sponsor_name}:\n";
    echo "      - العمود القديم (sponsor_id): {$oldCount}\n";
    echo "      - الجدول الجديد (sponsorship_sponsor): {$newCount}\n";

    if ($oldCount == $newCount) {
        echo "      ✓ تطابق تام!\n";
    } else {
        echo "      ✗ عدم تطابق!\n";
    }
    echo "\n";
}

echo "=== اكتمل النقل بنجاح! ===\n";
