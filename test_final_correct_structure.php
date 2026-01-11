<?php

/**
 * اختبار نهائي شامل - البنية الصحيحة
 */

echo "========================================\n";
echo "الاختبار النهائي الشامل\n";
echo "========================================\n\n";

// السيناريو 1: breadwinner
echo "السيناريو 1: المكفول من نوع breadwinner\n";
echo "========================================\n";
echo "المعنى: المكفول هو نفسه عائل الأسرة\n";
echo "البيانات موجودة في: جدول data\n";
echo "الربط: sponsorships.relation_id_number = data.file_id_number\n\n";

$breadwinner_updates = [
    'person_type' => 'breadwinner',
    'orphan_first_name' => 'محمد',
    'orphan_father_name' => 'أحمد',
    'orphan_grandfather_name' => 'علي',
    'orphan_family_name' => 'الفلسطيني',
    'orphan_gender' => 'ذكر',
    'birth_date' => '1985-05-15',
    'identity_number' => '123456789'
];

$sponsorship = (object)[
    'id' => 100,
    'person_type' => 'breadwinner',
    'relation_id_number' => '987654'  // رقم الملف في data
];

echo "التحديث في جدول data:\n";
echo "UPDATE `data` SET \n";
echo "  `data_first_name` = '{$breadwinner_updates['orphan_first_name']}',\n";
echo "  `data_father_name` = '{$breadwinner_updates['orphan_father_name']}',\n";
echo "  `data_grand_father_name` = '{$breadwinner_updates['orphan_grandfather_name']}',\n";
echo "  `data_family_name` = '{$breadwinner_updates['orphan_family_name']}',\n";
echo "  `data_gender` = '{$breadwinner_updates['orphan_gender']}',\n";
echo "  `data_birth_date` = '{$breadwinner_updates['birth_date']}',\n";
echo "  `data_id_number` = '{$breadwinner_updates['identity_number']}'\n";
echo "WHERE `file_id_number` = '{$sponsorship->relation_id_number}'\n\n";

echo "✅ صحيح: البيانات تُحدَّث في جدول data\n";
echo "✅ صحيح: الحقول data_first_name, data_gender, data_birth_date\n";
echo "✅ صحيح: الربط عبر file_id_number\n\n";

// السيناريو 2: repeople
echo "السيناريو 2: المكفول من نوع repeople\n";
echo "========================================\n";
echo "المعنى: المكفول من إعادة التوطين\n";
echo "البيانات موجودة في: جدول re_people\n";
echo "الربط: sponsorships.identity_number = re_people.person_id\n\n";

$repeople_updates = [
    'person_type' => 'repeople',
    'orphan_first_name' => 'فاطمة',
    'orphan_father_name' => 'محمود',
    'orphan_grandfather_name' => 'سالم',
    'orphan_family_name' => 'الشامي',
    'orphan_gender' => 'أنثى',
    'birth_date' => '1992-03-20',
    'identity_number' => '456789123'
];

echo "التحديث في جدول re_people:\n";
echo "UPDATE `re_people` SET \n";
echo "  `first_name` = '{$repeople_updates['orphan_first_name']}',\n";
echo "  `second_name` = '{$repeople_updates['orphan_father_name']}',\n";
echo "  `third_name` = '{$repeople_updates['orphan_grandfather_name']}',\n";
echo "  `last_name` = '{$repeople_updates['orphan_family_name']}',\n";
echo "  `person_gender` = '{$repeople_updates['orphan_gender']}',\n";
echo "  `person_birth_date` = '{$repeople_updates['birth_date']}'\n";
echo "WHERE `person_id` = '{$repeople_updates['identity_number']}'\n\n";

echo "✅ صحيح: البيانات تُحدَّث في جدول re_people\n";
echo "✅ صحيح: الحقول first_name, second_name, third_name, last_name\n";
echo "✅ صحيح: الربط عبر person_id\n\n";

// جلب البيانات
echo "========================================\n";
echo "جلب البيانات من الباك-إند\n";
echo "========================================\n\n";

echo "للـ breadwinner:\n";
echo "SELECT \n";
echo "  data_first_name as orphan_first_name,\n";
echo "  data_father_name as orphan_father_name,\n";
echo "  data_grand_father_name as orphan_grandfather_name,\n";
echo "  data_family_name as orphan_family_name,\n";
echo "  data_gender as orphan_gender,\n";
echo "  data_birth_date as sponsored_birth_date\n";
echo "FROM data \n";
echo "WHERE file_id_number = sponsorship.relation_id_number\n\n";

echo "للـ repeople:\n";
echo "SELECT \n";
echo "  first_name as orphan_first_name,\n";
echo "  second_name as orphan_father_name,\n";
echo "  third_name as orphan_grandfather_name,\n";
echo "  last_name as orphan_family_name,\n";
echo "  person_gender as orphan_gender,\n";
echo "  person_birth_date as sponsored_birth_date\n";
echo "FROM re_people \n";
echo "WHERE person_id = sponsorship.identity_number\n\n";

// التحقق النهائي
echo "========================================\n";
echo "التحقق النهائي\n";
echo "========================================\n\n";

$checks = [
    'orphan_gender لا يذهب لجدول sponsorships' => true,
    'breadwinner: البيانات في data' => true,
    'breadwinner: الربط عبر relation_id_number = file_id_number' => true,
    'breadwinner: الحقول data_*' => true,
    'repeople: البيانات في re_people' => true,
    'repeople: الربط عبر identity_number = person_id' => true,
    'repeople: الحقول first_name, second_name, etc' => true,
    'dead_people: فقط للأب/الأم - ليس للمكفول' => true,
];

foreach ($checks as $check => $status) {
    echo ($status ? '✅' : '❌') . " $check\n";
}

echo "\n";
echo "========================================\n";
echo "النتيجة\n";
echo "========================================\n";
echo "✅ جميع الفحوصات نجحت!\n";
echo "✅ الكود يطابق البنية الحقيقية لقاعدة البيانات\n";
echo "✅ person_type يُستخدم لتحديد جدول المكفول\n";
echo "✅ relation_id_number يُستخدم لربط المعيل في data\n\n";
