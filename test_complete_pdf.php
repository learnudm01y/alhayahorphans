<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

echo "=== إنشاء جميع المتغيرات المطلوبة ===\n";

// جميع المتغيرات المطلوبة للقالب
$allVariables = [
    'file_number' => '032454',
    'orphan_name' => 'يوسف وجدي يوسف جروان',
    'phone_number' => '595757929',
    'housing_status' => 'ممتاز',
    'housing_type' => 'ملك',
    'city' => 'البريج',
    'school_address' => 'الخلافاء',
    'grade_level' => 'اعدادي',
    'academic_stage' => 'اعدادي',
    'student_level' => 'ممتاز',
    'weakness_reason' => 'غير موجود',
    'psychological_status' => 'ممتاز',
    'behavioral_status' => 'ممتاز',
    'religious_commitment' => 'ملتزم',
    'quran_memorization' => 'جزء عم',
    'orphan_health_status' => 'سليم',
    'weight' => '45',
    'height' => '150',
    'mother_name' => 'فاطمة أحمد',
    'mother_id' => '123456789',
    'mother_alive' => 'متوفاة',
    'father_name' => 'وجدي يوسف جروان',
    'father_id' => '987654321',
    'father_alive' => 'متوفي',
    'guardian_full_name' => 'حنان محمد أحمد جروان',
    'guardian_relation' => 'ام',
    'guardian_name' => 'حنان محمد أحمد جروان',
    'guardian_relationship' => 'ام',
    'guardian_health' => 'سليم',
    'guardian_job' => 'لا يعمل',
    'dependents_count' => '2',
    'sponsorship_impact' => 'إيجابي جدًا',
    'important_events' => 'لا يوجد',
    'creativity_aspects' => 'Unknown',
    'family_members' => [], // قائمة فارغة لأفراد الأسرة
    'timestamp' => '2026-01-01'
];

try {
    echo "إنشاء HTML بجميع المتغيرات...\n";
    $html = view('user.dashboard.pdf.orphan-report', $allVariables)->render();

    echo "✅ تم إنشاء HTML بنجاح\n";
    echo "طول HTML: " . strlen($html) . " حرف\n";

    // إنشاء PDF
    echo "إنشاء PDF...\n";
    $pdf = PDF::loadHTML($html)
        ->setOption('encoding', 'UTF-8')
        ->setOption('enable-local-file-access', true)
        ->setOption('page-size', 'A4')
        ->setOption('disable-smart-shrinking', true);

    $pdfContent = $pdf->output();
    $pdfSize = strlen($pdfContent);

    file_put_contents('complete_test.pdf', $pdfContent);
    echo "✅ تم إنشاء complete_test.pdf - الحجم: {$pdfSize} bytes\n";

    if ($pdfSize > 15000) {
        echo "✅ PDF يحتوي على البيانات!\n";
        echo "\nالرجاء فتح الملف complete_test.pdf للتأكد من المحتوى\n";
    } else {
        echo "❌ PDF قد يكون صغير جداً\n";
    }

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "في السطر: " . $e->getLine() . "\n";
    echo "في الملف: " . $e->getFile() . "\n";
}
