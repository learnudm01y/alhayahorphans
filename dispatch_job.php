<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use App\Models\Sponsorship;

echo "=== إضافة Job جديد للـ Queue ===\n";

$sponsorship = Sponsorship::find(198);
if (!$sponsorship) {
    echo "❌ لم يتم العثور على كفالة رقم 198\n";
    exit;
}

echo "✅ تم العثور على الكفالة ID: {$sponsorship->id}\n";
echo "✅ relation_id_number: {$sponsorship->relation_id_number}\n";

// إضافة Job للـ Queue
GenerateOrphanReportPdf::dispatch($sponsorship->id);

echo "✅ تم إضافة Job للـ Queue بنجاح!\n";
echo "\nالآن قم بتشغيل:\n";
echo "php artisan queue:work --timeout=60\n";
echo "\nوتحقق من المجلد:\n";
echo "storage/app/public/uploads/{$sponsorship->relation_id_number}/\n";
