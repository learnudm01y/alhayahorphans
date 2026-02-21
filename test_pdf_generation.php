<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use Illuminate\Support\Facades\Log;

echo "=== اختبار توليد PDF للكفالة 1295 ===\n\n";

$sponsorshipId = 1295;
$sponsorId = 1; // دينيز

try {
    echo "🔄 بدء توليد PDF...\n";

    // إنشاء الـ job مباشرة
    $job = new GenerateOrphanReportPdf($sponsorshipId, $sponsorId);

    // تشغيله
    $job->handle();

    echo "\n✅ تم توليد PDF بنجاح!\n";
    echo "\n📝 تحقق من السجلات في storage/logs/laravel.log\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
