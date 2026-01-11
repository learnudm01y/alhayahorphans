<?php

/**
 * فحص البنية الحقيقية للجداول في قاعدة البيانات
 */

echo "========================================\n";
echo "البنية الحقيقية للجداول\n";
echo "========================================\n\n";

// ========================================
// جدول data
// ========================================
echo "جدول data (عائلي الأسر):\n";
echo "----------------------------------------\n";
$data_fields = [
    'id' => 'bigint - المعرف',
    'file_id_number' => 'bigint - رقم الملف (unique)',
    'data_id_number' => 'int - رقم الهوية',
    'data_first_name' => 'string - الاسم الأول',
    'data_father_name' => 'string - اسم الأب',
    'data_grand_father_name' => 'string - اسم الجد',
    'data_family_name' => 'string - اسم العائلة',
    'data_gender' => 'int - الجنس',
    'data_birth_date' => 'date - تاريخ الميلاد',
    'data_phone_number' => 'int - رقم الهاتف',
    'data_alt_phone_number' => 'int - رقم هاتف بديل',
    'data_city' => 'bigint - المدينة',
    'data_current_address' => 'string - العنوان الحالي',
    'data_health_status' => 'bigint - الحالة الصحية',
];

foreach ($data_fields as $field => $desc) {
    echo "  - $field: $desc\n";
}
echo "\n";

echo "❌ لا يوجد حقول: person_*, orphan_*, sponsored_*\n";
echo "✅ الحقول الصحيحة: data_*\n\n";

// ========================================
// جدول re_people
// ========================================
echo "جدول re_people (أشخاص إعادة توطين):\n";
echo "----------------------------------------\n";
$re_people_fields = [
    'id' => 'bigint - المعرف',
    'registration_id' => 'bigint - معرف التسجيل',
    'first_name' => 'string - الاسم الأول',
    'second_name' => 'string - الاسم الثاني',
    'third_name' => 'string - الاسم الثالث',
    'last_name' => 'string - اسم العائلة',
    'person_id' => 'bigint - رقم الهوية',
    'person_birth_date' => 'date - تاريخ الميلاد',
    'person_gender' => 'int - الجنس',
    'person_health_status' => 'bigint - الحالة الصحية',
];

foreach ($re_people_fields as $field => $desc) {
    echo "  - $field: $desc\n";
}
echo "\n";

echo "❌ لا يوجد حقول: person_identity_number, person_first_name\n";
echo "✅ الحقول الصحيحة: first_name, second_name, third_name, last_name, person_id\n\n";

// ========================================
// جدول dead_people
// ========================================
echo "جدول dead_people (المتوفين - الأب والأم فقط!):\n";
echo "----------------------------------------\n";
$dead_people_fields = [
    'id' => 'bigint - المعرف',
    're_file_id' => 'bigint - رقم ملف اليتيم (FK to data.file_id_number)',
    'father_first_name' => 'string - اسم الأب الأول',
    'father_second_name' => 'string - اسم الأب الثاني',
    'father_third_name' => 'string - اسم الأب الثالث',
    'father_last_name' => 'string - اسم عائلة الأب',
    'father_id' => 'int - رقم هوية الأب',
    'father_death_date' => 'date - تاريخ وفاة الأب',
    'father_death_reason' => 'bigint - سبب الوفاة',
    'mother_first_name' => 'string - اسم الأم الأول',
    'mother_second_name' => 'string - اسم الأم الثاني',
    'mother_third_name' => 'string - اسم الأم الثالث',
    'mother_last_name' => 'string - اسم عائلة الأم',
    'mother_id' => 'int - رقم هوية الأم',
    'mother_death_reason' => 'bigint - سبب الوفاة',
];

foreach ($dead_people_fields as $field => $desc) {
    echo "  - $field: $desc\n";
}
echo "\n";

echo "❌ هذا الجدول للأب والأم المتوفين فقط - ليس للمكفول!\n";
echo "❌ لا يوجد حقول: person_*, orphan_*\n";
echo "✅ الحقول الصحيحة: father_*, mother_*\n\n";

// ========================================
// جدول sponsorships
// ========================================
echo "جدول sponsorships (الكفالات):\n";
echo "----------------------------------------\n";
$sponsorships_fields = [
    'id' => 'bigint - المعرف',
    'sponsor_id' => 'bigint - معرف الكافل',
    'internal_file_number' => 'string - رقم الملف الداخلي',
    'relation_id_number' => 'string - رقم الربط الداخلي (للربط مع data)',
    'external_file_number' => 'string - رقم الملف الخارجي',
    'identity_number' => 'string - رقم الهوية',
    'orphan_name' => 'string - اسم اليتيم',
    'guardian_name' => 'string - اسم المعيل',
    'guardian_identity_number' => 'string - رقم هوية المعيل',
    'sponsorship_type_id' => 'bigint - نوع الكفالة',
    'sponsorship_status_id' => 'bigint - حالة الكفالة',
    'person_type' => 'string - نوع الشخص (breadwinner/repeople/dead)',
];

foreach ($sponsorships_fields as $field => $desc) {
    echo "  - $field: $desc\n";
}
echo "\n";

echo "❌ لا يوجد حقول: orphan_gender, orphan_first_name, sponsored_birth_date\n";
echo "✅ الحقول الصحيحة: orphan_name, identity_number, guardian_name\n\n";

// ========================================
// العلاقات الصحيحة
// ========================================
echo "========================================\n";
echo "العلاقات الصحيحة بين الجداول:\n";
echo "========================================\n\n";

echo "1. sponsorships → data:\n";
echo "   sponsorships.relation_id_number = data.file_id_number\n";
echo "   (رقم الربط الداخلي يربط الكفالة بملف العائل)\n\n";

echo "2. dead_people → data:\n";
echo "   dead_people.re_file_id = data.file_id_number\n";
echo "   (ملف الأب/الأم المتوفين يرتبط بملف اليتيم)\n\n";

echo "3. re_people:\n";
echo "   جدول مستقل - لا يوجد ربط مباشر مع sponsorships\n";
echo "   (يتم الربط عبر person_id أو registration_id)\n\n";

// ========================================
// المشكلة الحقيقية
// ========================================
echo "========================================\n";
echo "المشكلة الحقيقية:\n";
echo "========================================\n\n";

echo "❌ الكود الحالي يحاول:\n";
echo "   1. تحديث person_identity_number في data/re_people/dead_people\n";
echo "   2. تحديث person_first_name, person_gender, person_birth_date\n";
echo "   3. استخدام person_type لتحديد الجدول\n\n";

echo "✅ الصواب:\n";
echo "   1. data: استخدام data_id_number (ليس person_identity_number)\n";
echo "   2. data: استخدام data_first_name, data_gender, data_birth_date\n";
echo "   3. re_people: استخدام person_id (ليس person_identity_number)\n";
echo "   4. re_people: استخدام first_name, person_gender, person_birth_date\n";
echo "   5. dead_people: للأب/الأم فقط - ليس للمكفول!\n\n";

// ========================================
// التصحيح المطلوب
// ========================================
echo "========================================\n";
echo "التصحيح المطلوب:\n";
echo "========================================\n\n";

echo "1. person_type في sponsorships:\n";
echo "   - breadwinner → يعني الشخص موجود في جدول data\n";
echo "   - repeople → يعني الشخص موجود في جدول re_people\n";
echo "   - dead → ❌ خطأ! dead_people للأب/الأم فقط\n\n";

echo "2. الربط الصحيح:\n";
echo "   if (person_type == 'breadwinner') {\n";
echo "       الجدول: data\n";
echo "       الربط: relation_id_number = file_id_number\n";
echo "       الحقول: data_first_name, data_gender, data_birth_date\n";
echo "   }\n";
echo "   if (person_type == 'repeople') {\n";
echo "       الجدول: re_people\n";
echo "       الربط: identity_number = person_id\n";
echo "       الحقول: first_name, second_name, third_name, last_name, person_gender\n";
echo "   }\n\n";

echo "3. dead_people:\n";
echo "   ❌ لا يُستخدم لتخزين بيانات المكفول!\n";
echo "   ✅ يُستخدم فقط لتخزين بيانات الأب/الأم المتوفين\n\n";

// ========================================
// النتيجة
// ========================================
echo "========================================\n";
echo "النتيجة:\n";
echo "========================================\n\n";

echo "❌ جميع الاختبارات السابقة خاطئة لأنها تستخدم:\n";
echo "   - person_identity_number (غير موجود في data)\n";
echo "   - person_first_name (غير موجود في data)\n";
echo "   - person_gender (غير موجود في data)\n";
echo "   - dead_people للمكفول (خطأ - فقط للأب/الأم)\n\n";

echo "✅ يجب إعادة كتابة updateOrphanDataByPersonType() لتستخدم:\n";
echo "   - data.file_id_number للربط (ليس person_identity_number)\n";
echo "   - data.data_first_name (ليس person_first_name)\n";
echo "   - data.data_gender (ليس person_gender)\n";
echo "   - re_people.person_id للربط (ليس person_identity_number)\n";
echo "   - re_people.first_name (ليس person_first_name)\n\n";

echo "🔴 الخلاصة: جميع الاختبارات فشلت لأن البنية الحقيقية مختلفة تماماً!\n\n";
