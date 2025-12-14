<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== فحص جدول sponsorships ===\n\n";

$sponsorship = DB::table('sponsorships')->where('id', 71)->first();

if ($sponsorship) {
    echo "الأعمدة في جدول sponsorships:\n";
    foreach ($sponsorship as $key => $value) {
        echo "  - $key: " . (is_null($value) ? 'NULL' : substr($value, 0, 50)) . "\n";
    }
} else {
    echo "الكفالة 71 غير موجودة\n";
}
