<?php

/**
 * اختبار سيناريو: مكفول موجود في sponsorships ولكن لا توجد له بيانات مركزية
 *
 * السيناريو:
 * - الشخص موجود في جدول sponsorships
 * - لا توجد بيانات في جداول: data, dead_people, re_people
 * - يجب عرض الاسم من sponsorships + حقول لإعادة الإدخال
 * - عند الحفظ: إنشاء سجلات في الجداول المركزية + توليد relation_id_number
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Sponsorship;

echo "=" . str_repeat("=", 70) . "\n";
echo "  اختبار سيناريو: مكفول بدون بيانات مركزية\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// بيانات الاختبار
$testIdentity = '000073366';
$testInternalFile = '12345';

echo "📋 بيانات الاختبار:\n";
echo "   - رقم الهوية: {$testIdentity}\n";
echo "   - رقم الملف الداخلي: {$testInternalFile}\n\n";

// 1. البحث عن الكفالة في sponsorships
echo "🔍 البحث في جدول sponsorships:\n";
$sponsorship = Sponsorship::where('identity_number', $testIdentity)->first();

if (!$sponsorship) {
    echo "   ❌ لم يتم العثور على الكفالة!\n\n";

    // عرض الكفالات المتاحة
    echo "📌 الكفالات المتاحة (أول 10):\n";
    $available = Sponsorship::select('id', 'identity_number', 'internal_file_number', 'orphan_name', 'relation_id_number')
        ->limit(10)
        ->get();
    foreach ($available as $s) {
        echo "   - #{$s->id}: identity={$s->identity_number}, file={$s->internal_file_number}, name={$s->orphan_name}\n";
    }
    exit(1);
}

echo "   ✅ تم العثور على الكفالة #{$sponsorship->id}\n";
echo "   - اسم المكفول: {$sponsorship->orphan_name}\n";
echo "   - رقم الملف الداخلي: {$sponsorship->internal_file_number}\n";
echo "   - relation_id_number: " . ($sponsorship->relation_id_number ?: 'غير موجود') . "\n";

// 2. فحص هيكل جدول sponsorships
echo "\n📊 هيكل جدول sponsorships:\n";
$columns = Schema::getColumnListing('sponsorships');
$importantColumns = ['id', 'orphan_name', 'identity_number', 'internal_file_number', 'relation_id_number',
                     'birth_date', 'guardian_name', 'guardian_phone', 'guardian_relationship', 'status', 'sponsor_id'];

foreach ($columns as $col) {
    if (in_array($col, $importantColumns)) {
        $value = $sponsorship->$col ?? 'NULL';
        echo "   - {$col}: {$value}\n";
    }
}

// 3. فحص وجود بيانات في الجداول المركزية
echo "\n🔍 فحص الجداول المركزية:\n";

// 3.1 جدول data
echo "\n   📁 جدول data:\n";
$dataByFileId = null;
$dataByIdentity = null;

if ($sponsorship->relation_id_number) {
    $dataByFileId = DB::table('data')
        ->where('file_id_number', $sponsorship->relation_id_number)
        ->first();
    echo "      - بحث بـ file_id_number ({$sponsorship->relation_id_number}): " .
         ($dataByFileId ? "✅ موجود (id: {$dataByFileId->id})" : "❌ غير موجود") . "\n";
}

$dataByIdentity = DB::table('data')
    ->where('data_id_number', $testIdentity)
    ->first();
echo "      - بحث بـ data_id_number ({$testIdentity}): " .
     ($dataByIdentity ? "✅ موجود (id: {$dataByIdentity->id})" : "❌ غير موجود") . "\n";

$hasDataRecord = $dataByFileId || $dataByIdentity;
echo "      - النتيجة: " . ($hasDataRecord ? "✅ يوجد سجل" : "❌ لا يوجد سجل") . "\n";

// 3.2 جدول re_people
echo "\n   📁 جدول re_people:\n";
$rePeopleByIdentity = DB::table('re_people')
    ->where('person_id', $testIdentity)
    ->first();
echo "      - بحث بـ person_id ({$testIdentity}): " .
     ($rePeopleByIdentity ? "✅ موجود" : "❌ غير موجود") . "\n";

$rePeopleByRegId = null;
if ($sponsorship->relation_id_number) {
    $rePeopleByRegId = DB::table('re_people')
        ->where('registration_id', $sponsorship->relation_id_number)
        ->first();
    echo "      - بحث بـ registration_id ({$sponsorship->relation_id_number}): " .
         ($rePeopleByRegId ? "✅ موجود" : "❌ غير موجود") . "\n";
}

$hasRePeopleRecord = $rePeopleByIdentity || $rePeopleByRegId;
echo "      - النتيجة: " . ($hasRePeopleRecord ? "✅ يوجد سجل" : "❌ لا يوجد سجل") . "\n";

// 3.3 جدول dead_people
echo "\n   📁 جدول dead_people:\n";
$deadPeopleRecord = null;
if ($sponsorship->relation_id_number) {
    $deadPeopleRecord = DB::table('dead_people')
        ->where('re_file_id', $sponsorship->relation_id_number)
        ->first();
    echo "      - بحث بـ re_file_id ({$sponsorship->relation_id_number}): " .
         ($deadPeopleRecord ? "✅ موجود" : "❌ غير موجود") . "\n";
}
echo "      - النتيجة: " . ($deadPeopleRecord ? "✅ يوجد سجل" : "❌ لا يوجد سجل") . "\n";

// 4. الخلاصة
echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 ملخص الحالة:\n";
echo str_repeat("=", 70) . "\n";

$hasCentralData = $hasDataRecord || $hasRePeopleRecord || $deadPeopleRecord;

if (!$hasCentralData) {
    echo "⚠️ هذا المكفول لا يملك أي بيانات في الجداول المركزية!\n\n";
    echo "📝 الإجراءات المطلوبة:\n";
    echo "   1. عرض الاسم الكامل من sponsorships.orphan_name (غير قابل للتعديل)\n";
    echo "   2. عرض ملاحظة للمستخدم لإعادة إدخال البيانات\n";
    echo "   3. عرض 4 حقول منفصلة لإدخال الاسم الصحيح\n";
    echo "   4. عند الحفظ:\n";
    echo "      - توليد relation_id_number جديد\n";
    echo "      - إنشاء سجل في جدول data\n";
    echo "      - إنشاء سجل في جدول re_people\n";
    echo "      - إنشاء سجل في جدول dead_people\n";
    echo "      - تحديث sponsorships.relation_id_number\n";
} else {
    echo "✅ هذا المكفول لديه بيانات في الجداول المركزية\n";
}

// 5. فحص آخر relation_id_number مستخدم
echo "\n\n📊 فحص أرقام الملفات:\n";
$maxRelationId = DB::table('data')->max('file_id_number');
echo "   - أعلى file_id_number في data: {$maxRelationId}\n";

$maxReFileId = DB::table('dead_people')->max('re_file_id');
echo "   - أعلى re_file_id في dead_people: {$maxReFileId}\n";

$maxRegId = DB::table('re_people')->max('registration_id');
echo "   - أعلى registration_id في re_people: {$maxRegId}\n";

// حساب الرقم الجديد المقترح
$nextFileNumber = max(
    intval($maxRelationId ?? 0),
    intval($maxReFileId ?? 0),
    intval($maxRegId ?? 0)
) + 1;

$formattedNextFileNumber = str_pad($nextFileNumber, 6, '0', STR_PAD_LEFT);
echo "\n   🆕 الرقم الجديد المقترح: {$formattedNextFileNumber}\n";

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎉 انتهى الفحص!\n";
echo str_repeat("=", 70) . "\n";
