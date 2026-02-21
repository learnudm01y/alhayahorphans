<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== فحص أعمدة جدول sponsorships ===\n";
$columns = DB::select('DESCRIBE sponsorships');
foreach ($columns as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}

echo "\n=== فحص وجود sponsor_id في sponsorships ===\n";
$hasSponsorId = collect($columns)->contains(function($col) {
    return $col->Field === 'sponsor_id';
});
echo "sponsor_id موجود: " . ($hasSponsorId ? 'نعم' : 'لا') . "\n";

if ($hasSponsorId) {
    echo "\n=== عينة من الكفالات مع sponsor_id القديم ===\n";
    $sample = DB::table('sponsorships')
        ->select('id', 'orphan_name', 'sponsor_id', 'sponsorship_status_id')
        ->whereNotNull('sponsor_id')
        ->limit(10)
        ->get();

    foreach ($sample as $row) {
        $sponsor = DB::table('sponsors')->where('id', $row->sponsor_id)->first();
        echo "ID: {$row->id} | Name: {$row->orphan_name} | Old sponsor_id: {$row->sponsor_id} | Sponsor: " . ($sponsor ? $sponsor->sponsor_name : 'N/A') . "\n";
    }

    echo "\n=== إحصائيات الكفالات حسب sponsor_id القديم ===\n";
    $stats = DB::table('sponsorships')
        ->select('sponsor_id', DB::raw('COUNT(*) as count'))
        ->whereNotNull('sponsor_id')
        ->groupBy('sponsor_id')
        ->get();

    foreach ($stats as $stat) {
        $sponsor = DB::table('sponsors')->where('id', $stat->sponsor_id)->first();
        echo ($sponsor ? $sponsor->sponsor_name : "ID: {$stat->sponsor_id}") . ": {$stat->count} كفالة\n";
    }

    // فحص جمعية "شهادة شكر" بالتحديد
    echo "\n=== فحص جمعية 'شهادة شكر' ===\n";
    $shahada = DB::table('sponsors')->where('sponsor_name', 'like', '%شهادة%')->first();
    if ($shahada) {
        $count = DB::table('sponsorships')
            ->where('sponsor_id', $shahada->id)
            ->count();
        echo "Sponsor ID: {$shahada->id} | Name: {$shahada->sponsor_name}\n";
        echo "عدد الكفالات في عمود sponsor_id القديم: {$count}\n";
    }
}
