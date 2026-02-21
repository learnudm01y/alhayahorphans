<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsor;
use App\Models\Sponsorship;

echo "=== فحص بيانات جمعية الخير ===\n\n";

$khayr = Sponsor::where('sponsor_name', 'LIKE', '%الخير%')->first();

if (!$khayr) {
    echo "❌ لم يتم العثور على جمعية الخير\n";
    exit;
}

echo "✅ جمعية الخير:\n";
echo "   - ID: {$khayr->id}\n";
echo "   - الاسم: {$khayr->sponsor_name}\n\n";

// استخدام العلاقة Many-to-Many
$countManyToMany = $khayr->sponsorships()->count();
echo "📊 عدد الكفالات (Many-to-Many من sponsorship_sponsor): {$countManyToMany}\n";

// استخدام sponsor_id المباشر
$countDirectId = Sponsorship::where('sponsor_id', $khayr->id)->count();
echo "📊 عدد الكفالات (من sponsor_id في جدول sponsorships): {$countDirectId}\n\n";

// فحص جدول sponsorship_sponsor
$pivotCount = DB::table('sponsorship_sponsor')
    ->where('sponsor_id', $khayr->id)
    ->count();
echo "📊 عدد السجلات في sponsorship_sponsor: {$pivotCount}\n\n";

// عرض أول 5 سجلات من sponsorship_sponsor
echo "📋 أول 5 سجلات من sponsorship_sponsor:\n";
$pivotRecords = DB::table('sponsorship_sponsor')
    ->where('sponsor_id', $khayr->id)
    ->limit(5)
    ->get();

foreach ($pivotRecords as $record) {
    $sponsorship = Sponsorship::find($record->sponsorship_id);
    echo "   - Sponsorship ID: {$record->sponsorship_id}, اليتيم: " . ($sponsorship ? $sponsorship->orphan_name : 'غير معروف') . "\n";
}
