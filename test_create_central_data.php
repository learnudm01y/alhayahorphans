<?php

/**
 * اختبار سيناريو إنشاء البيانات المركزية للمكفول بدون بيانات
 *
 * السيناريو:
 * - مكفول موجود في sponsorships ولكن لا توجد بيانات في الجداول المركزية
 * - عند الحفظ يتم:
 *   1. توليد رقم ملف جديد (relation_id_number)
 *   2. إنشاء سجل في data
 *   3. إنشاء سجل في re_people
 *   4. إنشاء سجل في dead_people
 *   5. تحديث sponsorships.relation_id_number
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;

echo "=" . str_repeat("=", 70) . "\n";
echo "  اختبار إنشاء البيانات المركزية للمكفول\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// بيانات الاختبار
$testIdentity = '000073366';
$testInternalFile = '12345';

echo "📋 بيانات الاختبار:\n";
echo "   - رقم الهوية: {$testIdentity}\n";
echo "   - رقم الملف الداخلي: {$testInternalFile}\n\n";

// 1. البحث عن الكفالة
echo "🔍 البحث عن الكفالة...\n";
$sponsorship = Sponsorship::where('identity_number', $testIdentity)->first();

if (!$sponsorship) {
    echo "   ❌ لم يتم العثور على الكفالة!\n";
    exit(1);
}

echo "   ✅ تم العثور على الكفالة #{$sponsorship->id}\n";
echo "   - اسم المكفول: {$sponsorship->orphan_name}\n";
echo "   - اسم المعيل: {$sponsorship->guardian_name}\n";
echo "   - relation_id_number قبل: " . ($sponsorship->relation_id_number ?: 'NULL') . "\n";

// 2. التحقق من عدم وجود بيانات مركزية
echo "\n🔍 التحقق من عدم وجود بيانات مركزية...\n";

$hasData = DB::table('data')
    ->where('file_id_number', $sponsorship->relation_id_number)
    ->orWhere('data_id_number', $testIdentity)
    ->exists();

$hasRePeople = DB::table('re_people')
    ->where('registration_id', $sponsorship->relation_id_number)
    ->orWhere('person_id', $testIdentity)
    ->exists();

$hasDeadPeople = DB::table('dead_people')
    ->where('re_file_id', $sponsorship->relation_id_number)
    ->exists();

if ($hasData || $hasRePeople || $hasDeadPeople) {
    echo "   ⚠️ تحذير: توجد بعض البيانات المركزية بالفعل\n";
    echo "      - data: " . ($hasData ? 'موجود' : 'غير موجود') . "\n";
    echo "      - re_people: " . ($hasRePeople ? 'موجود' : 'غير موجود') . "\n";
    echo "      - dead_people: " . ($hasDeadPeople ? 'موجود' : 'غير موجود') . "\n";
} else {
    echo "   ✅ لا توجد بيانات مركزية - جاهز للاختبار\n";
}

// 3. حساب رقم الملف الجديد
echo "\n📊 حساب رقم الملف الجديد...\n";

$maxFromData = DB::table('data')
    ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
    ->max(DB::raw('CAST(file_id_number AS UNSIGNED)'));

$maxFromDeadPeople = DB::table('dead_people')
    ->whereRaw("re_file_id REGEXP '^[0-9]+$'")
    ->max(DB::raw('CAST(re_file_id AS UNSIGNED)'));

$maxFromRePeople = DB::table('re_people')
    ->whereRaw("registration_id REGEXP '^[0-9]+$'")
    ->max(DB::raw('CAST(registration_id AS UNSIGNED)'));

$nextNumber = max(
    intval($maxFromData ?? 0),
    intval($maxFromDeadPeople ?? 0),
    intval($maxFromRePeople ?? 0)
) + 1;

$newFileNumber = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

echo "   - أعلى رقم في data: {$maxFromData}\n";
echo "   - أعلى رقم في dead_people: {$maxFromDeadPeople}\n";
echo "   - أعلى رقم في re_people: {$maxFromRePeople}\n";
echo "   - الرقم الجديد المقترح: {$newFileNumber}\n";

// 4. محاكاة بيانات الإدخال
echo "\n📝 محاكاة بيانات الإدخال...\n";

$namesData = [
    'sponsored' => [
        'first_name' => 'عماد',
        'second_name' => 'محمود',
        'third_name' => 'عبد الحفيظ',
        'last_name' => 'جحجوح'
    ],
    'guardian' => [
        'first_name' => 'فاطمة',
        'second_name' => 'أحمد',
        'third_name' => 'محمد',
        'last_name' => 'جحجوح'
    ],
    'father' => [
        'first_name' => 'محمود',
        'second_name' => 'عبد الحفيظ',
        'third_name' => 'حسين',
        'last_name' => 'جحجوح'
    ]
];

$fieldsData = [
    'field_person_birth_date' => '2015-03-15',
    'field_person_gender' => 'ذكر',
    'field_father_id' => '987654321',
    'field_father_death_date' => '2023-01-10',
];

echo "   - اسم المكفول: {$namesData['sponsored']['first_name']} {$namesData['sponsored']['second_name']} {$namesData['sponsored']['third_name']} {$namesData['sponsored']['last_name']}\n";
echo "   - اسم المعيل: {$namesData['guardian']['first_name']} {$namesData['guardian']['second_name']} {$namesData['guardian']['third_name']} {$namesData['guardian']['last_name']}\n";
echo "   - اسم الأب: {$namesData['father']['first_name']} {$namesData['father']['second_name']} {$namesData['father']['third_name']} {$namesData['father']['last_name']}\n";

// 5. سؤال المستخدم قبل الإنشاء
echo "\n" . str_repeat("=", 70) . "\n";
echo "⚠️ هل تريد إنشاء البيانات المركزية؟ (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (strtolower(trim($line)) !== 'y') {
    echo "\n❌ تم إلغاء العملية\n";
    exit(0);
}

// 6. بدء إنشاء البيانات
echo "\n🚀 بدء إنشاء البيانات المركزية...\n";

try {
    DB::beginTransaction();

    // 6.1 إنشاء سجل في data
    echo "\n   📁 إنشاء سجل في جدول data...\n";
    $dataRecord = [
        'file_id_number' => $newFileNumber,
        'data_id_number' => $testIdentity,
        'data_first_name' => $namesData['guardian']['first_name'],
        'data_father_name' => $namesData['guardian']['second_name'],
        'data_grand_father_name' => $namesData['guardian']['third_name'],
        'data_family_name' => $namesData['guardian']['last_name'],
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $dataId = DB::table('data')->insertGetId($dataRecord);
    echo "      ✅ تم إنشاء السجل (id: {$dataId}, file_id_number: {$newFileNumber})\n";

    // 6.2 إنشاء سجل في re_people
    echo "\n   📁 إنشاء سجل في جدول re_people...\n";
    $rePeopleRecord = [
        'registration_id' => $newFileNumber,
        'person_id' => $testIdentity,
        'first_name' => $namesData['sponsored']['first_name'],
        'second_name' => $namesData['sponsored']['second_name'],
        'third_name' => $namesData['sponsored']['third_name'],
        'last_name' => $namesData['sponsored']['last_name'],
        'person_birth_date' => $fieldsData['field_person_birth_date'],
        'person_gender' => 1, // ذكر
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $rePeopleId = DB::table('re_people')->insertGetId($rePeopleRecord);
    echo "      ✅ تم إنشاء السجل (id: {$rePeopleId}, registration_id: {$newFileNumber})\n";

    // 6.3 إنشاء سجل في dead_people
    echo "\n   📁 إنشاء سجل في جدول dead_people...\n";
    $deadPeopleRecord = [
        're_file_id' => $newFileNumber,
        'father_first_name' => $namesData['father']['first_name'],
        'father_second_name' => $namesData['father']['second_name'],
        'father_third_name' => $namesData['father']['third_name'],
        'father_last_name' => $namesData['father']['last_name'],
        'father_id' => $fieldsData['field_father_id'],
        'father_death_date' => $fieldsData['field_father_death_date'],
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $deadPeopleId = DB::table('dead_people')->insertGetId($deadPeopleRecord);
    echo "      ✅ تم إنشاء السجل (id: {$deadPeopleId}, re_file_id: {$newFileNumber})\n";

    // 6.4 تحديث sponsorships
    echo "\n   📁 تحديث جدول sponsorships...\n";
    $fullSponsoredName = trim("{$namesData['sponsored']['first_name']} {$namesData['sponsored']['second_name']} {$namesData['sponsored']['third_name']} {$namesData['sponsored']['last_name']}");
    $fullGuardianName = trim("{$namesData['guardian']['first_name']} {$namesData['guardian']['second_name']} {$namesData['guardian']['third_name']} {$namesData['guardian']['last_name']}");

    DB::table('sponsorships')
        ->where('id', $sponsorship->id)
        ->update([
            'relation_id_number' => $newFileNumber,
            'orphan_name' => $fullSponsoredName,
            'guardian_name' => $fullGuardianName,
            'sponsored_birth_date' => $fieldsData['field_person_birth_date'],
            'updated_at' => now(),
        ]);

    echo "      ✅ تم تحديث relation_id_number إلى: {$newFileNumber}\n";
    echo "      ✅ تم تحديث orphan_name إلى: {$fullSponsoredName}\n";
    echo "      ✅ تم تحديث guardian_name إلى: {$fullGuardianName}\n";

    DB::commit();

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🎉 تم إنشاء البيانات المركزية بنجاح!\n";
    echo str_repeat("=", 70) . "\n\n";

    // 7. التحقق النهائي
    echo "🔍 التحقق النهائي...\n\n";

    $sponsorship->refresh();
    echo "   📌 الكفالة #{$sponsorship->id}:\n";
    echo "      - relation_id_number: {$sponsorship->relation_id_number}\n";
    echo "      - orphan_name: {$sponsorship->orphan_name}\n";
    echo "      - guardian_name: {$sponsorship->guardian_name}\n";
    echo "      - sponsored_birth_date: {$sponsorship->sponsored_birth_date}\n\n";

    $dataCheck = DB::table('data')->where('file_id_number', $newFileNumber)->first();
    echo "   📌 جدول data:\n";
    echo "      - file_id_number: {$dataCheck->file_id_number}\n";
    echo "      - data_id_number: {$dataCheck->data_id_number}\n";
    echo "      - الاسم: {$dataCheck->data_first_name} {$dataCheck->data_father_name} {$dataCheck->data_grand_father_name} {$dataCheck->data_family_name}\n\n";

    $rePeopleCheck = DB::table('re_people')->where('registration_id', $newFileNumber)->first();
    echo "   📌 جدول re_people:\n";
    echo "      - registration_id: {$rePeopleCheck->registration_id}\n";
    echo "      - person_id: {$rePeopleCheck->person_id}\n";
    echo "      - الاسم: {$rePeopleCheck->first_name} {$rePeopleCheck->second_name} {$rePeopleCheck->third_name} {$rePeopleCheck->last_name}\n\n";

    $deadPeopleCheck = DB::table('dead_people')->where('re_file_id', $newFileNumber)->first();
    echo "   📌 جدول dead_people:\n";
    echo "      - re_file_id: {$deadPeopleCheck->re_file_id}\n";
    echo "      - اسم الأب: {$deadPeopleCheck->father_first_name} {$deadPeopleCheck->father_second_name} {$deadPeopleCheck->father_third_name} {$deadPeopleCheck->father_last_name}\n";
    echo "      - رقم هوية الأب: {$deadPeopleCheck->father_id}\n";
    echo "      - تاريخ الوفاة: {$deadPeopleCheck->father_death_date}\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ حدث خطأ: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ انتهى الاختبار بنجاح!\n";
echo str_repeat("=", 70) . "\n";
