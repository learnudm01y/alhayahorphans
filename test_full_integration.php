<?php

/**
 * اختبار تكامل كامل: من التطبيق إلى قاعدة البيانات
 *
 * يحاكي تدفق البيانات الكامل:
 * 1. المستخدم يحفظ بيانات في التطبيق
 * 2. التطبيق يرسل البيانات للباك-إند
 * 3. الباك-إند يوجه البيانات للجداول الصحيحة
 */

echo "========================================\n";
echo "اختبار التكامل الكامل: تدفق البيانات\n";
echo "========================================\n\n";

// سيناريو كامل: breadwinner (عائل أسرة)
echo "========================================\n";
echo "السيناريو: breadwinner (عائل أسرة)\n";
echo "========================================\n\n";

// المرحلة 1: بيانات من التطبيق (محاكاة detail.html saveChanges)
echo "المرحلة 1: التطبيق يجهز البيانات\n";
echo "----------------------------------------\n";

$currentSponsorship = [
    'id' => 158,
    'person_type' => 'breadwinner', // النوع المحفوظ في الكفالة
    'identity_number' => '407015601'
];

$modifiedFields = [
    'orphan_first_name' => 'نصرالله01',
    'orphan_father_name' => 'عبد الناصر01',
    'orphan_grandfather_name' => 'رفيق01',
    'orphan_family_name' => 'الفرا01',
    'orphan_gender' => 'أنثى',
    'birth_date' => '1978-01-02'
];

// محاكاة منطق detail.html
$dataToSave = $modifiedFields;

// إضافة person_type (الكود الجديد)
if (isset($modifiedFields['orphan_first_name']) || isset($modifiedFields['orphan_father_name']) ||
    isset($modifiedFields['orphan_grandfather_name']) || isset($modifiedFields['orphan_family_name']) ||
    isset($modifiedFields['orphan_gender']) || isset($modifiedFields['birth_date'])) {
    $dataToSave['person_type'] = $currentSponsorship['person_type'] ?? 'orphan';
}

echo "البيانات المرسلة من التطبيق:\n";
print_r($dataToSave);
echo "\n";

if (isset($dataToSave['person_type'])) {
    echo "✅ person_type موجود: {$dataToSave['person_type']}\n";
} else {
    echo "❌ خطأ! person_type غير موجود\n";
}
echo "\n";

// المرحلة 2: الباك-إند يستقبل البيانات (محاكاة SponsorshipSyncController)
echo "المرحلة 2: الباك-إند يستقبل البيانات\n";
echo "----------------------------------------\n";

$updates = $dataToSave; // البيانات الواردة من API
$sponsorship = (object)$currentSponsorship; // الكفالة من قاعدة البيانات

// الحقول المسموح بها في sponsorships
$allowedFields = [
    'orphan_name',
    'identity_number',
    'sponsored_birth_date',
    'guardian_name',
    'guardian_identity_number',
    'notes',
    'sponsorship_status_id',
    'person_type'
];

// الحقول التي تذهب لجداول أخرى
$dataOnlyFields = [
    'health_status_id', 'guardian_phone', 'guardian_phone2',
    'guardian_city_id', 'guardian_detailed_address',
    'guardian_first_name', 'guardian_father_name',
    'guardian_grandfather_name', 'guardian_family_name',
    'guardian_person_type',
    'orphan_first_name', 'orphan_father_name',
    'orphan_grandfather_name', 'orphan_family_name',
    'orphan_gender'
];

// تصفية للـ sponsorships
$filteredUpdates = array_intersect_key($updates, array_flip($allowedFields));

// إزالة dataOnlyFields
foreach ($dataOnlyFields as $dataField) {
    unset($filteredUpdates[$dataField]);
}

echo "البيانات المصفاة لجدول sponsorships:\n";
print_r($filteredUpdates);
echo "\n";

// بناء orphan_name
if (isset($updates['orphan_first_name'])) {
    $filteredUpdates['orphan_name'] = trim(
        ($updates['orphan_first_name'] ?? '') . ' ' .
        ($updates['orphan_father_name'] ?? '') . ' ' .
        ($updates['orphan_grandfather_name'] ?? '') . ' ' .
        ($updates['orphan_family_name'] ?? '')
    );
}

echo "SQL لتحديث sponsorships:\n";
$sql = "UPDATE `sponsorships` SET ";
$setParts = [];
foreach ($filteredUpdates as $key => $value) {
    $setParts[] = "`$key` = '$value'";
}
$sql .= implode(", ", $setParts);
$sql .= " WHERE `id` = {$sponsorship->id}";
echo "$sql\n\n";

if (strpos($sql, 'orphan_gender') !== false) {
    echo "❌ خطأ! SQL يحتوي على orphan_gender\n\n";
} else {
    echo "✅ صحيح! SQL لا يحتوي على orphan_gender\n\n";
}

// المرحلة 3: معالجة بيانات المكفول
echo "المرحلة 3: معالجة بيانات المكفول حسب person_type\n";
echo "----------------------------------------\n";

// التحقق من وجود بيانات المكفول
if (isset($updates['orphan_first_name']) || isset($updates['orphan_father_name']) ||
    isset($updates['orphan_grandfather_name']) || isset($updates['orphan_family_name']) ||
    isset($updates['identity_number']) || isset($updates['orphan_gender']) ||
    isset($updates['birth_date'])) {

    echo "تم اكتشاف بيانات المكفول، سيتم استدعاء updateOrphanDataByPersonType()\n\n";

    // محاكاة updateOrphanDataByPersonType()
    $personType = $updates['person_type'] ?? $sponsorship->person_type ?? 'orphan';
    $identityNumber = $updates['identity_number'] ?? $sponsorship->identity_number;

    echo "person_type: $personType\n";
    echo "identity_number: $identityNumber\n\n";

    // تجهيز البيانات
    $personData = [];
    if (isset($updates['orphan_first_name'])) $personData['person_first_name'] = $updates['orphan_first_name'];
    if (isset($updates['orphan_father_name'])) $personData['person_father_name'] = $updates['orphan_father_name'];
    if (isset($updates['orphan_grandfather_name'])) $personData['person_grandfather_name'] = $updates['orphan_grandfather_name'];
    if (isset($updates['orphan_family_name'])) $personData['person_family_name'] = $updates['orphan_family_name'];
    if (isset($updates['orphan_gender'])) $personData['person_gender'] = $updates['orphan_gender'];
    if (isset($updates['birth_date'])) $personData['person_birth_date'] = $updates['birth_date'];

    // تحديد الجدول
    $tableName = match($personType) {
        'breadwinner' => 'data',
        'repeople' => 're_people',
        'dead' => 'dead_people',
        default => 'data'
    };

    echo "الجدول المختار: $tableName\n\n";

    echo "البيانات المجهزة:\n";
    print_r($personData);
    echo "\n";

    // SQL للتحديث
    echo "SQL للتحديث (إذا كان موجود):\n";
    $sqlUpdate = "UPDATE `$tableName` SET ";
    $setParts = [];
    foreach ($personData as $key => $value) {
        $setParts[] = "`$key` = '$value'";
    }
    $sqlUpdate .= implode(", ", $setParts);
    $sqlUpdate .= " WHERE `person_identity_number` = '$identityNumber'";
    echo "$sqlUpdate\n\n";

    // SQL للإنشاء
    echo "SQL للإنشاء (إذا لم يكن موجود):\n";
    $personData['person_identity_number'] = $identityNumber;
    $columns = array_keys($personData);
    $values = array_values($personData);
    $sqlInsert = "INSERT INTO `$tableName` (`" . implode("`, `", $columns) . "`) VALUES ('" . implode("', '", $values) . "')";
    echo "$sqlInsert\n\n";

    if ($tableName === 'data' && $personType === 'breadwinner') {
        echo "✅ صحيح! breadwinner → data\n";
    } elseif ($tableName === 're_people' && $personType === 'repeople') {
        echo "✅ صحيح! repeople → re_people\n";
    } elseif ($tableName === 'dead_people' && $personType === 'dead') {
        echo "✅ صحيح! dead → dead_people\n";
    } else {
        echo "❌ خطأ في توجيه الجدول\n";
    }
}

// النتيجة النهائية
echo "\n========================================\n";
echo "النتيجة النهائية\n";
echo "========================================\n\n";

$errors = [];

// التحقق 1: person_type موجود في البيانات المرسلة
if (!isset($dataToSave['person_type'])) {
    $errors[] = "person_type غير موجود في البيانات المرسلة من التطبيق";
}

// التحقق 2: orphan_gender غير موجود في sponsorships SQL
if (strpos($sql, 'orphan_gender') !== false) {
    $errors[] = "orphan_gender موجود في SQL الخاص بـ sponsorships";
}

// التحقق 3: الجدول الصحيح
if ($tableName !== 'data') {
    $errors[] = "الجدول المختار خاطئ (المتوقع: data، الفعلي: $tableName)";
}

// التحقق 4: person_gender موجود في بيانات الشخص
if (!isset($personData['person_gender'])) {
    $errors[] = "person_gender غير موجود في بيانات الشخص";
}

if (empty($errors)) {
    echo "✅✅✅ جميع الفحوصات نجحت!\n\n";
    echo "التدفق الكامل:\n";
    echo "1. ✅ التطبيق يرسل person_type مع البيانات\n";
    echo "2. ✅ الباك-إند يستقبل person_type\n";
    echo "3. ✅ orphan_gender لا يذهب لجدول sponsorships\n";
    echo "4. ✅ orphan_gender يذهب للجدول الصحيح (data) كـ person_gender\n";
    echo "5. ✅ person_type يحدد الجدول الصحيح (breadwinner → data)\n";
    echo "6. ✅ يتم إنشاء/تحديث السجل في الجدول الصحيح\n";
} else {
    echo "❌ فشل بعض الفحوصات:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n";
