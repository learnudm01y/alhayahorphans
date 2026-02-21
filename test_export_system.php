<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsor;
use App\Models\Sponsorship;

echo "=== اختبار نظام التصدير الجديد ===\n\n";

// 1. عرض قائمة الجمعيات مع عدد الكفالات
echo "1️⃣ الجمعيات وعدد الكفالات:\n";
echo str_repeat('-', 80) . "\n";

$sponsors = Sponsor::all();

foreach ($sponsors as $sponsor) {
    // استخدام العلاقة Many-to-Many الصحيحة
    $totalCount = $sponsor->sponsorships()->count();

    $activeCount = $sponsor->sponsorships()
        ->where('sponsorship_status_id', 1) // افترض أن 1 = نشط
        ->count();

    echo "📋 {$sponsor->sponsor_name}\n";
    echo "   - إجمالي الكفالات: {$totalCount}\n";
    echo "   - الكفالات النشطة: {$activeCount}\n";
    echo "   - Google Drive: " . ($sponsor->google_drive_enabled ? '✅ مفعل' : '❌ غير مفعل') . "\n";
    echo "\n";
}

// 2. فحص الـ jobs
echo "\n2️⃣ حالة قائمة الانتظار:\n";
echo str_repeat('-', 80) . "\n";
echo "Jobs في قائمة الانتظار: " . DB::table('jobs')->count() . "\n";
echo "Failed Jobs: " . DB::table('failed_jobs')->count() . "\n\n";

// 3. عرض إحصائيات الكفالات حسب الحالة
echo "3️⃣ إحصائيات الكفالات:\n";
echo str_repeat('-', 80) . "\n";

$statusStats = DB::table('sponsorships')
    ->select('sponsorship_status_id', DB::raw('COUNT(*) as count'))
    ->groupBy('sponsorship_status_id')
    ->get();

foreach ($statusStats as $stat) {
    echo "حالة #{$stat->sponsorship_status_id}: {$stat->count} كفالة\n";
}

echo "\n✅ اكتمل الاختبار\n";
