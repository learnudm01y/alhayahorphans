<?php

/**
 * سكربت مباشر لإضافة جميع الأشخاص المفقودين من ملف missing_persons.json
 * إلى قاعدة البيانات
 *
 * الاستخدام:
 * php import_all_missing_persons.php
 *
 * أو للفحص فقط بدون إضافة:
 * php import_all_missing_persons.php --dry-run
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Models\RePeople;

// فحص خيار dry-run
$dryRun = in_array('--dry-run', $argv ?? []);

$filePath = __DIR__ . '/missing_persons.json';

echo "=== بدء استيراد الأشخاص المفقودين ===\n\n";

if ($dryRun) {
    echo "⚠️  وضع الفحص فقط - لن يتم إضافة أي بيانات\n\n";
}

if (!file_exists($filePath)) {
    die("❌ ملف JSON غير موجود: {$filePath}\nتأكد من تشغيل سكربت extract_missing_persons.php أولاً\n");
}

$data = json_decode(file_get_contents($filePath), true);

if (empty($data)) {
    die("❌ لم يتم العثور على بيانات في الملف\n");
}

echo "📊 إجمالي الأشخاص المفقودين: " . $data['total_count'] . "\n";
echo "   - أفراد عائلة: " . $data['family_members_count'] . "\n";
echo "   - معيلين: " . $data['guardians_count'] . "\n\n";

$addedToRePeople = 0;
$addedToData = 0;
$skipped = 0;
$errors = 0;

if (!$dryRun) {
    DB::beginTransaction();
}

try {
    // إضافة أفراد العائلة
    echo "🔄 إضافة أفراد العائلة...\n";
    $total = count($data['family_members']);
    $current = 0;

    foreach ($data['family_members'] as $person) {
        $current++;
        $identity = trim($person['identity'] ?? '');
        $name = trim($person['name'] ?? '');

        // تقدم العملية
        if ($current % 50 == 0 || $current == $total) {
            echo "   [{$current}/{$total}]\n";
        }

        if (empty($identity) || $identity === '0') {
            $skipped++;
            continue;
        }

        // فحص إذا كان موجوداً مسبقاً
        $exists = RePeople::where('person_id', $identity)->exists();

        if ($exists) {
            $skipped++;
            continue;
        }

        if ($dryRun) {
            $addedToRePeople++;
            continue;
        }

        // البحث عن registration_id من المعيل
        $registrationId = null;
        $guardianIdentity = $person['guardian_identity'] ?? '';

        if (!empty($guardianIdentity) && $guardianIdentity !== '0') {
            $guardian = Data::where('data_id_number', $guardianIdentity)->first();
            if ($guardian) {
                $registrationId = $guardian->file_id_number;
            }
        }

        // إنشاء سجل جديد
        $newPerson = new RePeople();
        $newPerson->person_id = $identity;
        $newPerson->person_name = $name;
        $newPerson->registration_id = $registrationId;
        $newPerson->save();

        $addedToRePeople++;
    }

    echo "\n";

    // إضافة المعيلين
    echo "🔄 إضافة المعيلين...\n";
    $total = count($data['guardians']);
    $current = 0;

    // الحصول على أعلى رقم ملف حالي
    $maxFileId = Data::max('file_id_number') ?? 0;

    foreach ($data['guardians'] as $person) {
        $current++;
        $identity = trim($person['identity'] ?? '');
        $name = trim($person['name'] ?? '');

        // تقدم العملية
        if ($current % 50 == 0 || $current == $total) {
            echo "   [{$current}/{$total}]\n";
        }

        if (empty($identity) || $identity === '0') {
            $skipped++;
            continue;
        }

        // فحص إذا كان موجوداً مسبقاً
        $exists = Data::where('data_id_number', $identity)->exists();

        if ($exists) {
            $skipped++;
            continue;
        }

        if ($dryRun) {
            $addedToData++;
            continue;
        }

        // توليد رقم ملف جديد
        $maxFileId++;

        // إنشاء سجل جديد
        $newData = new Data();
        $newData->file_id_number = $maxFileId;
        $newData->data_id_number = $identity;
        $newData->data_name = $name;

        // أرقام الهاتف
        $phone = $person['phone'] ?? '';
        $altPhone = $person['alt_phone'] ?? '';

        if (!empty($phone) && $phone !== '0') {
            $newData->data_phone_number = $phone;
        }
        if (!empty($altPhone) && $altPhone !== '0') {
            $newData->data_alt_phone_number = $altPhone;
        }

        $newData->save();
        $addedToData++;
    }

    if (!$dryRun) {
        DB::commit();
        echo "\n✅ تم حفظ جميع التغييرات بنجاح\n";
    }

    echo "\n=== النتائج ===\n";

    if ($dryRun) {
        echo "سيتم إضافة {$addedToRePeople} شخص إلى جدول re_people\n";
        echo "سيتم إضافة {$addedToData} معيل إلى جدول data\n";
    } else {
        echo "✅ تمت إضافة {$addedToRePeople} شخص إلى جدول re_people\n";
        echo "✅ تمت إضافة {$addedToData} معيل إلى جدول data\n";
    }
    echo "⏭️  تم تخطي {$skipped} سجل (موجود مسبقاً أو بيانات ناقصة)\n";

    if ($errors > 0) {
        echo "❌ {$errors} خطأ\n";
    }

    // تحديث ملف missing_persons.json بالنتائج
    if (!$dryRun) {
        $data['import_results'] = [
            'imported_at' => date('Y-m-d H:i:s'),
            'added_to_re_people' => $addedToRePeople,
            'added_to_data' => $addedToData,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "\n📝 تم تحديث ملف missing_persons.json بنتائج الاستيراد\n";
    }

} catch (\Exception $e) {
    if (!$dryRun) {
        DB::rollBack();
    }
    echo "❌ حدث خطأ: " . $e->getMessage() . "\n";
    echo "تم التراجع عن جميع التغييرات.\n";
    exit(1);
}

echo "\n=== انتهى ===\n";
