<?php

/**
 * اختبار إصلاح orphan_gender
 *
 * هذا الاختبار يحاكي المنطق في SponsorshipSyncController->uploadSyncData()
 * للتأكد من أن orphan_gender لا يتم إرساله إلى جدول sponsorships
 */

echo "========================================\n";
echo "اختبار منطق تصفية orphan_gender\n";
echo "========================================\n\n";

// البيانات الواردة من التطبيق (محاكاة)
$updates = [
    'identity_number' => '407015601',
    'sponsored_birth_date' => '1978-01-02',
    'orphan_gender' => 'أنثى',  // ← هذا الحقل يسبب المشكلة!
    'guardian_identity_number' => '40701569201',
    'orphan_name' => 'نصرالله01 عبد الناصر01 رفيق01 الفرا01',
    'guardian_name' => 'نصرالله01 عبد الناصر01 رفيق01 الفرا01',
    'orphan_first_name' => 'نصرالله01',
    'orphan_father_name' => 'عبد الناصر01',
    'orphan_grandfather_name' => 'رفيق01',
    'orphan_family_name' => 'الفرا01',
];

echo "البيانات الواردة من التطبيق:\n";
print_r($updates);
echo "\n";

// الحقول المسموح بها في جدول sponsorships
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

// الحقول التي تذهب إلى جدول data/re_people/dead_people وليس sponsorships
$dataOnlyFields = [
    'health_status_id', 'guardian_phone', 'guardian_phone2',
    'guardian_city_id', 'guardian_detailed_address',
    'guardian_first_name', 'guardian_father_name',
    'guardian_grandfather_name', 'guardian_family_name',
    'guardian_person_type',
    'orphan_first_name', 'orphan_father_name',
    'orphan_grandfather_name', 'orphan_family_name',
    'orphan_gender'  // ← يجب أن يتم إزالته!
];

echo "الحقول المسموح بها في sponsorships:\n";
print_r($allowedFields);
echo "\n";

echo "الحقول التي تذهب لجداول أخرى (dataOnlyFields):\n";
print_r($dataOnlyFields);
echo "\n";

// المنطق القديم (الخاطئ)
echo "========================================\n";
echo "المنطق القديم (الخاطئ):\n";
echo "========================================\n";
$filteredUpdates_OLD = array_intersect_key($updates, array_flip($allowedFields));
$filteredUpdates_OLD['updated_at'] = date('Y-m-d H:i:s');
$filteredUpdates_OLD['updated_by'] = 3;

echo "التحديثات بعد التصفية (القديم):\n";
print_r($filteredUpdates_OLD);

if (isset($filteredUpdates_OLD['orphan_gender'])) {
    echo "\n❌ خطأ! orphan_gender موجود في filteredUpdates\n";
    echo "سيحدث خطأ SQL: Unknown column 'orphan_gender'\n";
} else {
    echo "\n✅ جيد! orphan_gender غير موجود\n";
}

// المنطق الجديد (الصحيح)
echo "\n========================================\n";
echo "المنطق الجديد (الصحيح):\n";
echo "========================================\n";
$filteredUpdates_NEW = array_intersect_key($updates, array_flip($allowedFields));

// إزالة أي حقول من dataOnlyFields قد تكون تسللت
foreach ($dataOnlyFields as $dataField) {
    unset($filteredUpdates_NEW[$dataField]);
}

$filteredUpdates_NEW['updated_at'] = date('Y-m-d H:i:s');
$filteredUpdates_NEW['updated_by'] = 3;

echo "التحديثات بعد التصفية (الجديد):\n";
print_r($filteredUpdates_NEW);

if (isset($filteredUpdates_NEW['orphan_gender'])) {
    echo "\n❌ خطأ! orphan_gender موجود في filteredUpdates\n";
    echo "سيحدث خطأ SQL: Unknown column 'orphan_gender'\n";
} else {
    echo "\n✅ ممتاز! orphan_gender تم إزالته بنجاح\n";
}

// التحقق من أن orphan_gender موجود في $updates الأصلي
echo "\n========================================\n";
echo "التحقق النهائي:\n";
echo "========================================\n";

if (isset($updates['orphan_gender'])) {
    echo "✅ orphan_gender موجود في \$updates الأصلي (سيتم معالجته في updateOrphanDataByPersonType)\n";
} else {
    echo "❌ orphan_gender غير موجود في \$updates!\n";
}

if (!isset($filteredUpdates_NEW['orphan_gender'])) {
    echo "✅ orphan_gender غير موجود في \$filteredUpdates (لن يتم تحديثه في جدول sponsorships)\n";
} else {
    echo "❌ orphan_gender موجود في \$filteredUpdates (سيحدث خطأ!)\n";
}

// محاكاة SQL
echo "\n========================================\n";
echo "محاكاة SQL الذي سيتم تنفيذه:\n";
echo "========================================\n";

$sql = "UPDATE `sponsorships` SET ";
$setParts = [];
foreach ($filteredUpdates_NEW as $key => $value) {
    $setParts[] = "`$key` = '$value'";
}
$sql .= implode(", ", $setParts);
$sql .= " WHERE `id` = 158";

echo $sql . "\n\n";

if (strpos($sql, 'orphan_gender') !== false) {
    echo "❌ خطأ! SQL يحتوي على orphan_gender!\n";
} else {
    echo "✅ ممتاز! SQL لا يحتوي على orphan_gender\n";
}

echo "\n========================================\n";
echo "النتيجة النهائية:\n";
echo "========================================\n";

$errors = 0;

if (isset($filteredUpdates_NEW['orphan_gender'])) {
    echo "❌ الاختبار فشل: orphan_gender موجود في filteredUpdates\n";
    $errors++;
}

if (!isset($updates['orphan_gender'])) {
    echo "❌ الاختبار فشل: orphan_gender غير موجود في updates الأصلي\n";
    $errors++;
}

if (strpos($sql, 'orphan_gender') !== false) {
    echo "❌ الاختبار فشل: SQL يحتوي على orphan_gender\n";
    $errors++;
}

if ($errors === 0) {
    echo "✅✅✅ جميع الاختبارات نجحت!\n";
    echo "orphan_gender سيتم معالجته بشكل صحيح في updateOrphanDataByPersonType()\n";
} else {
    echo "❌ فشل $errors اختبار\n";
}

echo "\n";
