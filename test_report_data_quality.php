<?php
// اختبار بيانات التقرير مباشرة

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\GenerateOrphanReportPdf;
use App\Models\Sponsorship;

$sponsorship = Sponsorship::find(198);
$job = new GenerateOrphanReportPdf($sponsorship->id);

// استخدم Reflection للوصول للميثود الخاص
$ref = new ReflectionClass($job);
$method = $ref->getMethod('collectReportData');
$method->setAccessible(true);
$reportData = $method->invokeArgs($job, [$sponsorship]);

echo "=== تقرير البيانات المُجمعة ===\n\n";

$keyFields = [
    'file_number' => 'رقم الملف',
    'orphan_name' => 'اسم اليتيم',
    'phone_number' => 'رقم الجوال',
    'city' => 'المدينة',
    'housing_status' => 'حالة السكن',
    'current_housing_type' => 'نوع السكن',
    'school_name' => 'اسم المدرسة',
    'school_address' => 'عنوان المدرسة',
    'grade_level' => 'الصف الدراسي',
    'student_level' => 'مستوى الطالب',
    'weakness_reason' => 'سبب الضعف',
    'psychological_status' => 'الحالة النفسية',
    'behavioral_status' => 'الحالة السلوكية',
    'religious_commitment' => 'الالتزام الديني',
    'quran_memorization' => 'حفظ القرآن',
    'health_status' => 'الحالة الصحية',
    'orphan_needs' => 'احتياجات اليتيم',
    'creativity_aspects' => 'جوانب الإبداع',
    'guardian_full_name' => 'اسم المعيل',
    'guardian_relation' => 'صلة القرابة',
    'guardian_health' => 'صحة المعيل',
    'guardian_job' => 'وظيفة المعيل',
    'dependents_count' => 'عدد المعالين',
    'sponsorship_impact' => 'تأثير الكفالة',
    'family_events' => 'الأحداث المهمة',
    'mother_name' => 'اسم الأم',
    'father_name' => 'اسم الأب',
    'timestamp' => 'الطابع الزمني'
];

foreach ($keyFields as $key => $label) {
    $value = $reportData[$key] ?? '(غير موجود)';
    $status = ($value === '(/)' || $value === 'Unknown' || empty($value)) ? '❌' : '✅';
    echo "{$status} {$label}: {$value}\n";
}

echo "\n=== أفراد الأسرة ===\n";
$familyMembers = $reportData['family_members'] ?? [];
if (empty($familyMembers)) {
    echo "❌ لا يوجد أفراد أسرة مسجلين\n";
} else {
    echo "✅ عدد أفراد الأسرة: " . count($familyMembers) . "\n";
    foreach ($familyMembers as $member) {
        echo "  - {$member['full_name']} ({$member['birth_date']})\n";
    }
}

echo "\n=== تقييم التقرير ===\n";
$filledFields = 0;
$totalFields = count($keyFields);

foreach ($keyFields as $key => $label) {
    $value = $reportData[$key] ?? '';
    if ($value !== '(/)' && $value !== 'Unknown' && !empty($value) && $value !== '(غير موجود)') {
        $filledFields++;
    }
}

$percentage = round(($filledFields / $totalFields) * 100);
echo "الحقول المليئة: {$filledFields} من {$totalFields} ({$percentage}%)\n";

if ($percentage >= 80) {
    echo "✅ التقرير ممتاز!\n";
} elseif ($percentage >= 60) {
    echo "⚠️ التقرير جيد لكن يمكن تحسينه\n";
} else {
    echo "❌ التقرير يحتاج تحسين كبير\n";
}
