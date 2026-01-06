<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Sponsorship;

echo "═══════════════════════════════════════════════════════════════\n";
echo "           فحص الكفالة والعلاقات\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. جلب الكفالة
$sponsorship = Sponsorship::where('identity_number', '42979087')->first();

echo "1️⃣  بيانات الكفالة:\n";
echo "   - id: {$sponsorship->id}\n";
echo "   - identity_number: {$sponsorship->identity_number}\n";
echo "   - internal_file_number: " . ($sponsorship->internal_file_number ?? 'فارغ') . "\n";
echo "   - relation_id_number: " . ($sponsorship->relation_id_number ?? 'فارغ') . "\n";
echo "   - guardian_identity_number: " . ($sponsorship->guardian_identity_number ?? 'فارغ') . "\n";

// 2. فحص العلاقات
echo "\n2️⃣  فحص العلاقات:\n";

// guardianData
echo "   - guardianData(): ";
try {
    $guardianData = $sponsorship->guardianData;
    if ($guardianData) {
        echo "وُجد\n";
        echo "     - data_id_number: {$guardianData->data_id_number}\n";
        echo "     - data_first_name: {$guardianData->data_first_name}\n";
        echo "     - data_father_name: {$guardianData->data_father_name}\n";
    } else {
        echo "لم يوجد\n";
    }
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// relationData
echo "   - relationData(): ";
try {
    $relationData = $sponsorship->relationData;
    if ($relationData) {
        echo "وُجد\n";
        echo "     - data_id_number: {$relationData->data_id_number}\n";
        echo "     - data_first_name: {$relationData->data_first_name}\n";
    } else {
        echo "لم يوجد\n";
    }
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// 3. البحث عن المعيل بالهوية مباشرة
echo "\n3️⃣  البحث عن المعيل بالهوية 400001285 في data:\n";
$dataByIdentity = DB::table('data')->where('data_id_number', '400001285')->first();
if ($dataByIdentity) {
    echo "   وُجد!\n";
    echo "   - file_id_number: {$dataByIdentity->file_id_number}\n";
    echo "   - data_first_name: {$dataByIdentity->data_first_name}\n";
    echo "   - data_father_name: {$dataByIdentity->data_father_name}\n";

    // تحديث الكفالة للربط
    echo "\n   📝 تحديث الكفالة لربطها بهذا السجل...\n";
    $sponsorship->update([
        'internal_file_number' => $dataByIdentity->file_id_number,
        'relation_id_number' => $dataByIdentity->file_id_number
    ]);
    echo "   ✓ تم تحديث internal_file_number و relation_id_number إلى: {$dataByIdentity->file_id_number}\n";
} else {
    echo "   لم يوجد\n";

    // البحث في السجل المدني
    echo "\n4️⃣  البحث في السجل المدني:\n";
    $civilData = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', '400001285')
        ->first();

    if ($civilData) {
        echo "   وُجد في السجل المدني!\n";
        echo "   - CI_FIRST_ARB: {$civilData->CI_FIRST_ARB}\n";
        echo "   - CI_FATHER_ARB: {$civilData->CI_FATHER_ARB}\n";
        echo "   - CI_GRAND_FATHER_ARB: {$civilData->CI_GRAND_FATHER_ARB}\n";
        echo "   - CI_FAMILY_ARB: {$civilData->CI_FAMILY_ARB}\n";
    }
}

echo "\n";
