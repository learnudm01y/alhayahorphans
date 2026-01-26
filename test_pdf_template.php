<?php
// اختبار قالب PDF مباشرة مع البيانات

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use App\Models\Sponsorship;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

$sponsorship = Sponsorship::find(198);
$job = new GenerateOrphanReportPdf($sponsorship->id);

// جلب البيانات
$ref = new ReflectionClass($job);
$method = $ref->getMethod('collectReportData');
$method->setAccessible(true);
$reportData = $method->invokeArgs($job, [$sponsorship]);

echo "=== اختبار قالب PDF ===\n";
echo "عدد المتغيرات المُمررة: " . count($reportData) . "\n\n";

echo "المتغيرات الرئيسية:\n";
foreach (['file_number', 'orphan_name', 'phone_number', 'city', 'school_name'] as $key) {
    echo "- {$key}: " . ($reportData[$key] ?? 'غير موجود') . "\n";
}

echo "\n=== إنشاء HTML ===\n";
try {
    $html = view('user.dashboard.pdf.orphan-report', $reportData)->render();

    $htmlLength = strlen($html);
    echo "طول HTML: {$htmlLength} حرف\n";

    // فحص وجود البيانات في HTML
    $hasData = false;
    $searchTerms = ['032454', 'يوسف', '595757929', 'الجميل', 'البريج'];
    foreach ($searchTerms as $term) {
        if (strpos($html, $term) !== false) {
            echo "✅ وُجد '{$term}' في HTML\n";
            $hasData = true;
        } else {
            echo "❌ لم يوجد '{$term}' في HTML\n";
        }
    }

    if ($hasData) {
        echo "\n✅ HTML يحتوي على البيانات!\n";

        // حفظ HTML للفحص اليدوي
        file_put_contents('debug_report.html', $html);
        echo "✅ تم حفظ HTML في debug_report.html\n";

    } else {
        echo "\n❌ HTML لا يحتوي على البيانات!\n";
    }

    // اختبار إنشاء PDF
    echo "\n=== اختبار PDF ===\n";
    $pdfContent = PDF::loadHTML($html)
        ->setOption('encoding', 'UTF-8')
        ->setOption('page-size', 'A4')
        ->setOption('enable-local-file-access', true)
        ->output();

    $pdfSize = strlen($pdfContent);
    echo "حجم PDF: {$pdfSize} bytes\n";

    if ($pdfSize > 10000) {
        echo "✅ PDF يبدو جيداً\n";
    } else {
        echo "❌ PDF قد يكون فارغاً أو صغير جداً\n";
    }

    // حفظ PDF للفحص
    file_put_contents('debug_report.pdf', $pdfContent);
    echo "✅ تم حفظ PDF في debug_report.pdf\n";

} catch (\Exception $e) {
    echo "❌ خطأ في إنشاء HTML/PDF: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
