<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 1. فحص جميع الجمعيات
echo "=== جميع الجمعيات ===\n";
$sponsors = App\Models\Sponsor::all(['id', 'sponsor_name']);
foreach ($sponsors as $sponsor) {
    echo "ID: {$sponsor->id} | Name: {$sponsor->sponsor_name}\n";
}

echo "\n=== جميع حالات الكفالة ===\n";
$statuses = App\Models\SponsorshipStatus::all(['id', 'description']);
foreach ($statuses as $status) {
    echo "ID: {$status->id} | Description: {$status->description}\n";
}

// 2. فحص عينة من جدول sponsorship_sponsor
echo "\n=== عينة من جدول sponsorship_sponsor ===\n";
$pivotData = DB::table('sponsorship_sponsor')->limit(10)->get();
foreach ($pivotData as $row) {
    echo "Sponsorship ID: {$row->sponsorship_id} | Sponsor ID: {$row->sponsor_id}\n";
}

// 3. فحص عدد الكفالات لكل جمعية
echo "\n=== عدد الكفالات لكل جمعية ===\n";
foreach ($sponsors as $sponsor) {
    $count = App\Models\Sponsorship::whereHas('sponsors', function($q) use ($sponsor) {
        $q->where('sponsors.id', $sponsor->id);
    })->count();
    echo "{$sponsor->sponsor_name}: {$count} كفالة\n";
}

// 4. فحص عينة من الكفالات
echo "\n=== عينة من الكفالات (أول 5) ===\n";
$sponsorships = App\Models\Sponsorship::limit(5)->get(['id', 'orphan_name', 'sponsorship_status_id']);
foreach ($sponsorships as $s) {
    $sponsors_list = $s->sponsors->pluck('sponsor_name')->implode(', ');
    echo "ID: {$s->id} | Name: {$s->orphan_name} | Status: {$s->sponsorship_status_id} | Sponsors: {$sponsors_list}\n";
}

echo "\n=== اختبار الاستعلام الفعلي ===\n";
// محاولة الجمعية "شهادة شكر"
$testSponsor = App\Models\Sponsor::where('sponsor_name', 'like', '%شهادة%')->first();
if ($testSponsor) {
    echo "Sponsor: {$testSponsor->sponsor_name} (ID: {$testSponsor->id})\n";

    // بدون فلترة حالة
    $count1 = App\Models\Sponsorship::whereHas('sponsors', function($q) use ($testSponsor) {
        $q->where('sponsors.id', $testSponsor->id);
    })->count();
    echo "عدد الكفالات (بدون فلترة الحالة): {$count1}\n";

    // مع فلترة حالة "جديد"
    $newStatus = App\Models\SponsorshipStatus::where('description', 'like', '%جديد%')->first();
    if ($newStatus) {
        echo "Status: {$newStatus->description} (ID: {$newStatus->id})\n";
        $count2 = App\Models\Sponsorship::whereHas('sponsors', function($q) use ($testSponsor) {
            $q->where('sponsors.id', $testSponsor->id);
        })->where('sponsorship_status_id', $newStatus->id)->count();
        echo "عدد الكفالات مع حالة {$newStatus->description}: {$count2}\n";
    }
}
