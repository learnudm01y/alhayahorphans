<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔍 Checking sponsorship_id = 909                           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$sponsorship = DB::table('sponsorships')
    ->leftJoin('sponsors', 'sponsorships.sponsor_id', '=', 'sponsors.id')
    ->where('sponsorships.id', 909)
    ->select([
        'sponsorships.id',
        'sponsorships.identity_number',
        'sponsorships.orphan_name',
        'sponsors.sponsor_name'
    ])
    ->first();

if ($sponsorship) {
    echo "✅ FOUND!\n";
    echo "   ID: " . $sponsorship->id . "\n";
    echo "   Identity Number: " . $sponsorship->identity_number . "\n";
    echo "   Orphan Name: " . $sponsorship->orphan_name . "\n";
    echo "   Sponsor Name: " . ($sponsorship->sponsor_name ?? 'NULL') . "\n";
} else {
    echo "❌ NOT FOUND!\n";
    echo "   Sponsorship with id=909 does not exist in database\n\n";

    echo "📊 Sample sponsorships (first 10):\n";
    $sample = DB::table('sponsorships')->orderBy('id')->limit(10)->get(['id', 'orphan_name']);
    foreach ($sample as $s) {
        echo "   - ID: {$s->id}, Name: {$s->orphan_name}\n";
    }
}

echo "\n";
