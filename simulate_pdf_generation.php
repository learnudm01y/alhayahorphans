<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;
use App\Models\Data;

echo "=== محاكاة معالجة PDF للكفالة ID: 1295 ===\n\n";

$sponsorshipId = 1295;
$sponsorship = Sponsorship::find($sponsorshipId);

if (!$sponsorship) {
    echo "❌ الكفالة غير موجودة\n";
    exit;
}

echo "✅ كفالة ID: {$sponsorship->id}\n";
echo "اسم اليتيم: {$sponsorship->orphan_name}\n";
echo "اسم المعيل: {$sponsorship->guardian_name}\n";
echo "رقم هوية المعيل: {$sponsorship->guardian_identity_number}\n\n";

$na = '(/)';

// محاكاة الكود الموجود في GenerateOrphanReportPdf
$guardianDataRecord = null;

if ($sponsorship->guardian_identity_number) {
    $guardianDataRecord = Data::where('data_id_number', $sponsorship->guardian_identity_number)->first();
}

echo "🔍 فحص guardianDataRecord:\n";
if ($guardianDataRecord) {
    echo "   ✅ تم العثور على سجل\n";
    echo "   - data_health_status: " . ($guardianDataRecord->data_health_status ?? 'NULL') . "\n";
    echo "   - data_employment_status_breadwinner: " . ($guardianDataRecord->data_employment_status_breadwinner ?? 'NULL') . "\n";
    echo "   - data_number_female: " . ($guardianDataRecord->data_number_female ?? 'NULL') . "\n";
    echo "   - data_number_mail: " . ($guardianDataRecord->data_number_mail ?? 'NULL') . "\n";
    echo "   - data_number_of_individuals: " . ($guardianDataRecord->data_number_of_individuals ?? 'NULL') . "\n";
} else {
    echo "   ❌ لا يوجد سجل\n";
}
echo "\n";

// محاكاة معالجة الحالة الصحية
echo "🏥 معالجة الحالة الصحية:\n";
$guardianHealthStatus = $na;
if ($guardianDataRecord && $guardianDataRecord->data_health_status) {
    $healthId = $guardianDataRecord->data_health_status;
    $healthDesc = DB::table('health_statuses')->where('id', $healthId)->value('description');
    $guardianHealthStatus = $healthDesc ?? $na;
    echo "   ID: {$healthId} → {$guardianHealthStatus}\n";
} else {
    echo "   ❌ data_health_status فارغ → {$guardianHealthStatus}\n";
}
echo "\n";

// محاكاة معالجة الوظيفة
echo "💼 معالجة الوظيفة:\n";
$guardianJob = $na;
if ($guardianDataRecord && $guardianDataRecord->data_employment_status_breadwinner) {
    $empId = $guardianDataRecord->data_employment_status_breadwinner;
    $empDesc = DB::table('employment')->where('id', $empId)->value('description');
    $guardianJob = $empDesc ?? $na;
    echo "   ID: {$empId} → {$guardianJob}\n";
} else {
    echo "   ❌ data_employment_status_breadwinner فارغ → {$guardianJob}\n";
}
echo "\n";

// محاكاة معالجة عدد المعالين
echo "👨‍👩‍👧‍👦 معالجة عدد المعالين:\n";
$totalDependents = 0;

if ($guardianDataRecord) {
    $dataFemale = (int) ($guardianDataRecord->data_number_female ?? 0);
    $dataMale = (int) ($guardianDataRecord->data_number_mail ?? 0);
    $totalDependents = $dataFemale + $dataMale;

    echo "   - data_number_female: {$dataFemale}\n";
    echo "   - data_number_mail: {$dataMale}\n";
    echo "   - المجموع: {$totalDependents}\n";

    if ($totalDependents == 0 && $guardianDataRecord->data_number_of_individuals) {
        $totalDependents = $guardianDataRecord->data_number_of_individuals;
        echo "   - استخدام data_number_of_individuals: {$totalDependents}\n";
    }
}

$dependentsCount = $totalDependents > 0 ? $totalDependents : $na;
echo "   النتيجة النهائية: {$dependentsCount}\n\n";

// حساب عدد الأطفال الفعلي في العائلة
echo "👶 عدد الأطفال في العائلة (من sponsorships):\n";
$childrenCount = Sponsorship::where('guardian_identity_number', $sponsorship->guardian_identity_number)
    ->count();
echo "   عدد الأطفال: {$childrenCount}\n\n";

// النتيجة النهائية
echo str_repeat('=', 80) . "\n";
echo "📊 النتيجة النهائية للـ PDF:\n";
echo str_repeat('=', 80) . "\n";
echo "الحالة الصحية: {$guardianHealthStatus}\n";
echo "الوظيفة: {$guardianJob}\n";
echo "عدد من يعيلهم: {$dependentsCount}\n";
echo "\n";

// التحليل
echo "🔍 التحليل:\n";
if ($guardianJob === $na && $guardianDataRecord && $guardianDataRecord->data_employment_status_breadwinner) {
    echo "   ⚠️ مشكلة: الوظيفة موجودة في قاعدة البيانات ولكن لا تظهر!\n";
} elseif ($guardianJob !== $na) {
    echo "   ✅ الوظيفة تظهر بشكل صحيح\n";
}

if ($guardianHealthStatus === $na && $guardianDataRecord && $guardianDataRecord->data_health_status) {
    echo "   ⚠️ مشكلة: الحالة الصحية موجودة في قاعدة البيانات ولكن لا تظهر!\n";
} elseif ($guardianHealthStatus === $na) {
    echo "   ⚠️ الحالة الصحية غير مدخلة في قاعدة البيانات\n";
} else {
    echo "   ✅ الحالة الصحية تظهر بشكل صحيح\n";
}

if ($dependentsCount == $na || ($totalDependents > 0 && $totalDependents != $childrenCount)) {
    echo "   ⚠️ يجب حساب عدد المعالين = data_number_of_individuals + عدد الأطفال\n";
    echo "   💡 الاقتراح: {$totalDependents} (من data) + {$childrenCount} (أطفال) = " . ($totalDependents + $childrenCount) . "\n";
}
