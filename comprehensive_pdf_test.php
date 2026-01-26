<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use App\Models\Sponsorship;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

echo "=== اختبار شامل لمحتوى PDF ===\n\n";

$sponsorshipId = 198;
$sponsorship = Sponsorship::find($sponsorshipId);

if (!$sponsorship) {
    echo "❌ لم يتم العثور على الكفالة\n";
    exit;
}

echo "الخطوة 1: جمع البيانات\n";
echo "========================\n";

$job = new GenerateOrphanReportPdf($sponsorshipId);
$ref = new ReflectionClass($job);
$method = $ref->getMethod('collectReportData');
$method->setAccessible(true);
$reportData = $method->invokeArgs($job, [$sponsorship]);

echo "عدد المتغيرات: " . count($reportData) . "\n";
echo "المتغيرات الرئيسية:\n";
foreach (['file_number', 'orphan_name', 'phone_number', 'city', 'school_address'] as $key) {
    $value = $reportData[$key] ?? 'غير موجود';
    echo "  - {$key}: {$value}\n";
}

echo "\nالخطوة 2: إنشاء HTML\n";
echo "========================\n";

try {
    $html = view('user.dashboard.pdf.orphan-report', $reportData)->render();
    $htmlLength = strlen($html);
    echo "✅ طول HTML: {$htmlLength} حرف\n";

    // حفظ HTML للفحص
    file_put_contents('inspection_html.html', $html);
    echo "✅ تم حفظ HTML في inspection_html.html\n";

    // فحص وجود البيانات في HTML
    $searchTerms = [
        '032454' => 'رقم الملف',
        'يوسف' => 'اسم اليتيم',
        '595757929' => 'رقم الجوال',
        'البريج' => 'المدينة'
    ];

    $foundCount = 0;
    echo "\nفحص محتوى HTML:\n";
    foreach ($searchTerms as $term => $label) {
        if (mb_strpos($html, $term) !== false) {
            echo "  ✅ {$label} ({$term}) موجود\n";
            $foundCount++;
        } else {
            echo "  ❌ {$label} ({$term}) غير موجود\n";
        }
    }

    if ($foundCount < count($searchTerms) / 2) {
        echo "\n❌ تحذير: HTML لا يحتوي على البيانات المتوقعة!\n";
        echo "عرض أول 500 حرف من HTML:\n";
        echo substr($html, 0, 500) . "\n...\n";
        exit;
    }

} catch (\Exception $e) {
    echo "❌ خطأ في إنشاء HTML: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit;
}

echo "\nالخطوة 3: إنشاء PDF\n";
echo "========================\n";

try {
    $pdfContent = PDF::loadHTML($html)
        ->setOption('encoding', 'UTF-8')
        ->setOption('page-size', 'A4')
        ->setOption('enable-local-file-access', true)
        ->setOption('disable-smart-shrinking', true)
        ->output();

    $pdfSize = strlen($pdfContent);
    echo "✅ حجم PDF: {$pdfSize} bytes\n";

    file_put_contents('inspection_report.pdf', $pdfContent);
    echo "✅ تم حفظ PDF في inspection_report.pdf\n";

    // فحص محتوى PDF (باستخدام strings)
    echo "\nالخطوة 4: فحص محتوى PDF\n";
    echo "========================\n";

    // محاولة قراءة النص من PDF
    $pdfText = '';
    foreach ($searchTerms as $term => $label) {
        if (strpos($pdfContent, $term) !== false) {
            echo "  ✅ {$label} ({$term}) موجود في PDF\n";
        } else {
            echo "  ❌ {$label} ({$term}) غير موجود في PDF\n";
        }
    }

    // فحص إضافي: هل PDF يحتوي على نصوص عربية؟
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $pdfContent)) {
        echo "  ✅ PDF يحتوي على نصوص عربية\n";
    } else {
        echo "  ❌ PDF لا يحتوي على نصوص عربية!\n";
    }

    // فحص حجم PDF
    if ($pdfSize < 5000) {
        echo "\n❌ تحذير: PDF صغير جداً ({$pdfSize} bytes)!\n";
        echo "هذا يعني أن PDF قد يكون فارغاً أو يحتوي على مشكلة\n";
    } elseif ($pdfSize < 10000) {
        echo "\n⚠️ تحذير: PDF صغير نسبياً ({$pdfSize} bytes)\n";
    } else {
        echo "\n✅ حجم PDF مناسب ({$pdfSize} bytes)\n";
    }

    echo "\nالخطوة 5: فحص wkhtmltopdf\n";
    echo "========================\n";

    // اختبار wkhtmltopdf مباشرة
    $wkhtmlPath = 'C:\\PROGRA~1\\WKHTML~1\\bin\\wkhtmltopdf.exe';
    if (file_exists($wkhtmlPath)) {
        echo "✅ wkhtmltopdf موجود في المسار الصحيح\n";

        // حفظ HTML وإنشاء PDF مباشرة
        file_put_contents('test_direct.html', $html);

        $cmd = "\"{$wkhtmlPath}\" --encoding UTF-8 --enable-local-file-access test_direct.html test_direct.pdf 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists('test_direct.pdf')) {
            $directPdfSize = filesize('test_direct.pdf');
            echo "✅ PDF مباشر من wkhtmltopdf: {$directPdfSize} bytes\n";

            if ($directPdfSize > $pdfSize + 1000 || $directPdfSize < $pdfSize - 1000) {
                echo "⚠️ فرق كبير في الحجم بين Snappy و wkhtmltopdf المباشر!\n";
            }
        } else {
            echo "❌ فشل إنشاء PDF مباشرة من wkhtmltopdf\n";
            echo "الناتج: " . implode("\n", $output) . "\n";
        }
    } else {
        echo "❌ wkhtmltopdf غير موجود في المسار المتوقع\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "النتيجة النهائية:\n";
    echo str_repeat("=", 50) . "\n";
    echo "الملفات المُنشأة:\n";
    echo "  - inspection_html.html (فحص HTML)\n";
    echo "  - inspection_report.pdf (PDF من Snappy)\n";
    echo "  - test_direct.pdf (PDF مباشر من wkhtmltopdf)\n";
    echo "\nالرجاء فتح هذه الملفات للفحص اليدوي\n";

} catch (\Exception $e) {
    echo "❌ خطأ في إنشاء PDF: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
