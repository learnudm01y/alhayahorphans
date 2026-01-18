<?php

/**
 * سكربت لإضافة الأشخاص المفقودين من ملف Excel إلى قاعدة البيانات
 *
 * هذا السكربت يقوم بـ:
 * 1. قراءة قائمة الأشخاص المفقودين من السجل أو من JSON
 * 2. إضافة كل شخص إلى الجدول المناسب:
 *    - فرد عائلة → re_people
 *    - معيل → data
 *
 * الاستخدام:
 * php artisan tinker < add_missing_persons.php
 * أو
 * php add_missing_persons.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Models\RePeople;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== بدء إضافة الأشخاص المفقودين ===\n\n";

// قائمة الأشخاص المفقودين (مستخرجة من السجل)
// يمكنك تعديل هذه القائمة حسب البيانات الفعلية
$missingPersons = [
    // أمثلة - استبدل هذه البيانات ببيانات الأشخاص المفقودين الفعلية
    // فرد عائلة - يتم إضافته إلى re_people
    [
        'type' => 'فرد عائلة',
        'identity' => '435280607',
        'name' => 'خديجة احمد سعد الدين ابو سويرح',
        'guardian_identity' => '0',
        'guardian_name' => '',
        'phone' => '0',
        'alt_phone' => '0',
    ],
    [
        'type' => 'فرد عائلة',
        'identity' => '441228590',
        'name' => 'مؤيد مجدي محمد أبومغيصيب',
        'guardian_identity' => '',
        'guardian_name' => '',
        'phone' => '',
        'alt_phone' => '',
    ],
    // أضف المزيد من الأشخاص هنا...
];

$addedToRePeople = 0;
$addedToData = 0;
$skipped = 0;
$errors = 0;

DB::beginTransaction();

try {
    foreach ($missingPersons as $person) {
        $type = normalizePersonType($person['type']);
        $identity = trim($person['identity']);
        $name = trim($person['name']);

        if (empty($identity) || $identity === '0') {
            echo "⚠️ تخطي: رقم هوية فارغ للشخص: {$name}\n";
            $skipped++;
            continue;
        }

        // تحديد الجدول المناسب حسب نوع الشخص
        if (in_array($type, ['فرد عائله', 'فرد عائلة', 'فرد اسره', 'فرد اسرة'])) {
            // فحص إذا كان موجوداً مسبقاً
            $exists = RePeople::where('person_id', $identity)->exists();

            if ($exists) {
                echo "⏭️ موجود مسبقاً في re_people: {$name} ({$identity})\n";
                $skipped++;
                continue;
            }

            // البحث عن registration_id من المعيل (guardian_identity)
            $registrationId = null;
            if (!empty($person['guardian_identity']) && $person['guardian_identity'] !== '0') {
                $guardian = Data::where('data_id_number', $person['guardian_identity'])->first();
                if ($guardian) {
                    $registrationId = $guardian->file_id_number;
                }
            }

            // إنشاء سجل جديد في re_people
            $newPerson = new RePeople();
            $newPerson->person_id = $identity;
            $newPerson->person_name = $name;
            $newPerson->registration_id = $registrationId;
            // يمكنك إضافة المزيد من الحقول حسب الحاجة
            $newPerson->save();

            echo "✅ تمت إضافة فرد عائلة: {$name} ({$identity})\n";
            $addedToRePeople++;

        } elseif (in_array($type, ['معيل', 'معيل اسره', 'معيل اسرة', 'معيل عائله', 'معيل عائلة'])) {
            // فحص إذا كان موجوداً مسبقاً
            $exists = Data::where('data_id_number', $identity)->exists();

            if ($exists) {
                echo "⏭️ موجود مسبقاً في data: {$name} ({$identity})\n";
                $skipped++;
                continue;
            }

            // توليد رقم ملف جديد
            $fileIdNumber = generateUniqueFileId();

            // إنشاء سجل جديد في data
            $newData = new Data();
            $newData->file_id_number = $fileIdNumber;
            $newData->data_id_number = $identity;
            $newData->data_name = $name;
            $newData->data_phone_number = !empty($person['phone']) && $person['phone'] !== '0' ? $person['phone'] : null;
            $newData->data_alt_phone_number = !empty($person['alt_phone']) && $person['alt_phone'] !== '0' ? $person['alt_phone'] : null;
            // يمكنك إضافة المزيد من الحقول حسب الحاجة
            $newData->save();

            echo "✅ تمت إضافة معيل: {$name} ({$identity}) - رقم الملف: {$fileIdNumber}\n";
            $addedToData++;
        } else {
            echo "⚠️ نوع غير معروف: {$type} للشخص: {$name}\n";
            $skipped++;
        }
    }

    DB::commit();

    echo "\n=== النتائج ===\n";
    echo "✅ تمت إضافة {$addedToRePeople} شخص إلى جدول re_people\n";
    echo "✅ تمت إضافة {$addedToData} معيل إلى جدول data\n";
    echo "⏭️ تم تخطي {$skipped} سجل (موجود مسبقاً أو بيانات ناقصة)\n";

    if ($errors > 0) {
        echo "❌ {$errors} خطأ\n";
    }

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ حدث خطأ: " . $e->getMessage() . "\n";
    echo "تم التراجع عن جميع التغييرات.\n";
}

/**
 * تطبيع نوع الشخص
 */
function normalizePersonType($type) {
    $type = trim($type);
    // تطبيع الحروف العربية
    $replacements = [
        'ه' => 'ه',
        'ة' => 'ه',
        'ی' => 'ي',
        'ى' => 'ي',
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
    ];

    foreach ($replacements as $from => $to) {
        $type = str_replace($from, $to, $type);
    }

    return $type;
}

/**
 * توليد رقم ملف فريد
 */
function generateUniqueFileId() {
    $maxId = Data::max('file_id_number') ?? 0;
    return $maxId + 1;
}
