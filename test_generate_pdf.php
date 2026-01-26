<?php
// تشغيل Job يدوياً لإنشاء PDF جديد

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use App\Models\Sponsorship;

$sponsorship = Sponsorship::find(198);

if (!$sponsorship) {
    echo "Sponsorship not found!\n";
    exit;
}

echo "=== Running GenerateOrphanReportPdf Job ===\n";
echo "Sponsorship ID: {$sponsorship->id}\n";
echo "File Number: {$sponsorship->internal_file_number}\n";
echo "Relation ID: {$sponsorship->relation_id_number}\n\n";

try {
    $job = new GenerateOrphanReportPdf($sponsorship->id);
    $job->handle();

    echo "\n✅ Job completed successfully!\n";
    echo "Check: storage/app/public/uploads/{$sponsorship->relation_id_number}/orphan_report_*.pdf\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
