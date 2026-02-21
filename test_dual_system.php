<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Sponsor;

echo "=== اختبار النظام الموحد (Dual System Support) ===\n\n";

echo "📋 اختبار الجمعيات:\n";
echo str_repeat('-', 80) . "\n\n";

// اختبار دينيز (Many-to-Many)
$deniz = Sponsor::where('sponsor_name', 'LIKE', '%دينيز%')->first();
if ($deniz) {
    $count = $deniz->getAllSponsorships()->count();
    echo "✅ دينيز (Many-to-Many): {$count} كفالة\n";
}

// اختبار الخير (Direct sponsor_id)
$khayr = Sponsor::where('sponsor_name', 'LIKE', '%الخير%')->first();
if ($khayr) {
    $count = $khayr->getAllSponsorships()->count();
    echo "✅ الخير فاونديشين (Direct sponsor_id): {$count} كفالة\n";
}

// اختبار سلسبيل
$salsabeel = Sponsor::where('sponsor_name', 'LIKE', '%سلسبيل%')->first();
if ($salsabeel) {
    $count = $salsabeel->getAllSponsorships()->count();
    echo "✅ سلسبيل: {$count} كفالة\n";
}

echo "\n";
echo "📊 جميع الجمعيات:\n";
echo str_repeat('-', 80) . "\n";

$sponsors = Sponsor::all();
foreach ($sponsors as $sponsor) {
    $count = $sponsor->getAllSponsorships()->count();
    if ($count > 0) {
        echo "   {$sponsor->sponsor_name}: {$count} كفالة\n";
    }
}

echo "\n✅ اكتمل الاختبار\n";
