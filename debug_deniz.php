<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== تحليل بيانات جمعية دينيز ===\n\n";

// البحث عن جمعية دينيز
$deniz = DB::table('sponsors')->where('sponsor_name', 'LIKE', '%دينيز%')->first();

if (!$deniz) {
    echo "❌ لم يتم العثور على جمعية دينيز\n";
    exit;
}

echo "✅ تم العثور على جمعية دينيز:\n";
echo "   - ID: {$deniz->id}\n";
echo "   - الاسم: {$deniz->sponsor_name}\n\n";

// البحث في جدول sponsorships باستخدام sponsor_id
echo "🔍 البحث في جدول sponsorships باستخدام sponsor_id:\n";
$countBySponsorId = DB::table('sponsorships')
    ->where('sponsor_id', $deniz->id)
    ->count();
echo "   - عدد الكفالات: {$countBySponsorId}\n\n";

// البحث في جدول sponsorship_sponsor (Many-to-Many)
echo "🔍 البحث في جدول sponsorship_sponsor:\n";
$countByPivot = DB::table('sponsorship_sponsor')
    ->where('sponsor_id', $deniz->id)
    ->count();
echo "   - عدد الكفالات: {$countByPivot}\n\n";

// عرض أول 5 سجلات من sponsorships
echo "📋 أول 5 كفالات لـ دينيز (من sponsor_id):\n";
$sponsorships = DB::table('sponsorships')
    ->where('sponsor_id', $deniz->id)
    ->limit(5)
    ->get(['id', 'sponsor_id', 'sponsorship_status_id', 'orphan_name']);

foreach ($sponsorships as $s) {
    echo "   - ID: {$s->id}, اليتيم: {$s->orphan_name}, الحالة: {$s->sponsorship_status_id}\n";
}

echo "\n";

// عرض توزيع sponsor_id في جدول sponsorships
echo "📊 توزيع الكفالات على الجمعيات:\n";
$distribution = DB::table('sponsorships')
    ->select('sponsor_id', DB::raw('COUNT(*) as count'))
    ->groupBy('sponsor_id')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get();

foreach ($distribution as $d) {
    $sponsor = DB::table('sponsors')->where('id', $d->sponsor_id)->first();
    $name = $sponsor ? $sponsor->sponsor_name : "غير معروف (ID: {$d->sponsor_id})";
    echo "   - {$name}: {$d->count} كفالة\n";
}
