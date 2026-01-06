<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           اختبار البحث في السجل المدني\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// اختبار البحث برقم هوية
$identityNumber = '400001285';

echo "البحث عن: $identityNumber\n\n";

try {
    $person = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', $identityNumber)
        ->first();

    if ($person) {
        echo "✓ تم العثور على الشخص:\n";
        echo "   - رقم الهوية: {$person->CI_ID_NUM}\n";
        echo "   - الاسم الأول: {$person->CI_FIRST_ARB}\n";
        echo "   - اسم الأب: {$person->CI_FATHER_ARB}\n";
        echo "   - اسم الجد: {$person->CI_GRAND_FATHER_ARB}\n";
        echo "   - العائلة: {$person->CI_FAMILY_ARB}\n";
        echo "   - تاريخ الميلاد: " . ($person->CI_BIRTH_DATE ?? 'غير متوفر') . "\n";
    } else {
        echo "✗ لم يتم العثور على الشخص\n";
    }
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
}

// اختبار التحقق من وجود العمود الجديد
echo "\n═══════════════════════════════════════════════════════════════\n";
echo "           التحقق من عمود sponsored_birth_date\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('sponsorships', 'sponsored_birth_date');
echo "عمود sponsored_birth_date: " . ($hasColumn ? '✓ موجود' : '✗ غير موجود') . "\n";

// اختبار تحديث كفالة بتاريخ ميلاد
if ($hasColumn) {
    echo "\nاختبار تحديث كفالة بتاريخ ميلاد...\n";
    $updated = DB::table('sponsorships')
        ->where('identity_number', '42979087')
        ->update(['sponsored_birth_date' => '2010-05-15']);

    echo "تم تحديث {$updated} سجل\n";

    $sponsorship = DB::table('sponsorships')->where('identity_number', '42979087')->first();
    echo "sponsored_birth_date: " . ($sponsorship->sponsored_birth_date ?? 'فارغ') . "\n";
}

echo "\n✅ الاختبار مكتمل!\n";
