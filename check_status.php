<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sponsor;

$count = Sponsor::count();
echo "\n📊 إحصائيات قاعدة البيانات:\n";
echo "═══════════════════════════════════════\n\n";
echo "عدد الجمعيات: {$count}\n\n";

if ($count > 0) {
    echo "السجلات الموجودة:\n";
    echo "─────────────────────────────────────\n";
    foreach (Sponsor::all() as $sponsor) {
        echo "  • ID: {$sponsor->id} | file_id: {$sponsor->file_id} | {$sponsor->sponsor_name}\n";
    }
}

echo "\n✅ النظام جاهز للاختبار!\n\n";
