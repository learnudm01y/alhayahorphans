<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║               اختبار شامل لمنطق عرض الحقول في Controller                      ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
echo "║  المكفول: 42979087 | المعيل: بدون رقم هوية                                   ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

$sponsoredId = '42979087';
$civilDB = DB::connection('civilregistry');

// جلب سجل الكفالة
$sponsorship = DB::table('sponsorships')->where('identity_number', $sponsoredId)->first();

if (!$sponsorship) {
    echo "❌ لا يوجد سجل كفالة للمكفول {$sponsoredId}\n";
    exit(1);
}

echo "📋 بيانات سجل الكفالة:\n";
echo "   - رقم هوية المكفول: " . $sponsorship->identity_number . "\n";
echo "   - اسم المكفول: " . $sponsorship->orphan_name . "\n";
echo "   - رقم هوية المعيل: " . ($sponsorship->guardian_identity_number ?: '❌ فارغ') . "\n";
echo "   - اسم المعيل: " . $sponsorship->guardian_name . "\n";

echo "\n" . str_repeat("═", 78) . "\n";
echo "                     محاكاة منطق extractFieldValues                           \n";
echo str_repeat("═", 78) . "\n\n";

$identityNumber = $sponsorship->identity_number;
$guardianIdentityNumber = $sponsorship->guardian_identity_number;

// === المتغيرات ===
$personType = null;
$guardianData = null;
$guardianDataSource = null;
$sponsoredPersonName = $sponsorship->orphan_name;
$civilRegistryData = null;
$guardianCivilRegistryData = null;

echo "1️⃣  البحث في re_people...\n";
$rePerson = DB::table('re_people')->where('person_id', $identityNumber)->first();
if ($rePerson) {
    $personType = 're_people';
    $sponsoredPersonName = trim("{$rePerson->first_name} {$rePerson->second_name} {$rePerson->third_name} {$rePerson->last_name}");
    echo "   ✓ وُجد: {$sponsoredPersonName}\n";

    $guardianData = DB::table('data')->where('file_id_number', $rePerson->registration_id)->first();
    if ($guardianData) $guardianDataSource = 'data';
} else {
    echo "   ✗ لم يُعثر عليه\n";
}

echo "\n2️⃣  البحث في data...\n";
$dataByIdentity = null;
if (!$personType) {
    $dataByIdentity = DB::table('data')->where('data_id_number', $identityNumber)->first();
    if ($dataByIdentity) {
        $personType = 'data';
        $guardianData = $dataByIdentity;
        $guardianDataSource = 'data';
        $sponsoredPersonName = trim("{$dataByIdentity->data_first_name} {$dataByIdentity->data_father_name} {$dataByIdentity->data_grand_father_name} {$dataByIdentity->data_family_name}");
        echo "   ✓ وُجد: {$sponsoredPersonName}\n";
    } else {
        echo "   ✗ لم يُعثر عليه\n";
    }
}

echo "\n3️⃣  البحث في dead_people...\n";
$deadPerson = null;
if (!$personType) {
    $deadPerson = DB::table('dead_people')
        ->where('father_id', $identityNumber)
        ->orWhere('mother_id', $identityNumber)
        ->first();
    if ($deadPerson) {
        $personType = 'dead_people';
        if ($deadPerson->father_id == $identityNumber) {
            $sponsoredPersonName = trim("{$deadPerson->father_first_name} {$deadPerson->father_second_name} {$deadPerson->father_third_name} {$deadPerson->father_last_name}");
        } else {
            $sponsoredPersonName = trim("{$deadPerson->mother_first_name} {$deadPerson->mother_second_name} {$deadPerson->mother_third_name} {$deadPerson->mother_last_name}");
        }
        echo "   ✓ وُجد: {$sponsoredPersonName}\n";

        $guardianData = DB::table('data')->where('file_id_number', $deadPerson->re_file_id)->first();
        if ($guardianData) $guardianDataSource = 'data';
    } else {
        echo "   ✗ لم يُعثر عليه\n";
    }
}

echo "\n4️⃣  البحث في السجل المدني (persons)...\n";
if (!$personType && $identityNumber) {
    $civilPerson = $civilDB->table('persons')->where('CI_ID_NUM', $identityNumber)->first();
    if ($civilPerson) {
        $personType = 'civil_registry';
        $civilRegistryData = [
            'first_name' => $civilPerson->CI_FIRST_ARB ?? '',
            'second_name' => $civilPerson->CI_FATHER_ARB ?? '',
            'third_name' => $civilPerson->CI_GRAND_FATHER_ARB ?? '',
            'last_name' => $civilPerson->CI_FAMILY_ARB ?? '',
        ];
        $sponsoredPersonName = trim("{$civilRegistryData['first_name']} {$civilRegistryData['second_name']} {$civilRegistryData['third_name']} {$civilRegistryData['last_name']}");
        echo "   ✓ وُجد: {$sponsoredPersonName}\n";
    } else {
        echo "   ✗ لم يُعثر عليه\n";
    }
}

echo "\n5️⃣  البحث عن بيانات المعيل...\n";
if (!$guardianData && $guardianIdentityNumber) {
    echo "   البحث برقم هوية المعيل: {$guardianIdentityNumber}\n";

    $guardianData = DB::table('data')->where('data_id_number', $guardianIdentityNumber)->first();
    if ($guardianData) {
        $guardianDataSource = 'data';
        echo "   ✓ وُجد في جدول data\n";
    } else {
        $civilGuardian = $civilDB->table('persons')->where('CI_ID_NUM', $guardianIdentityNumber)->first();
        if ($civilGuardian) {
            $guardianDataSource = 'civil_registry';
            $guardianCivilRegistryData = [
                'first_name' => $civilGuardian->CI_FIRST_ARB ?? '',
                'second_name' => $civilGuardian->CI_FATHER_ARB ?? '',
                'third_name' => $civilGuardian->CI_GRAND_FATHER_ARB ?? '',
                'last_name' => $civilGuardian->CI_FAMILY_ARB ?? '',
            ];
            echo "   ✓ وُجد في السجل المدني\n";
        } else {
            echo "   ✗ لم يُعثر عليه في أي مكان\n";
        }
    }
} else if (!$guardianIdentityNumber) {
    echo "   ⚠️ لا يوجد رقم هوية للمعيل في سجل الكفالة\n";
}

// تحديد مصدر بيانات المعيل
if (!$guardianDataSource) {
    if ($sponsorship->guardian_name && !empty(trim($sponsorship->guardian_name))) {
        $guardianDataSource = 'sponsorship_only';
    } else {
        $guardianDataSource = 'none';
    }
}

echo "\n" . str_repeat("═", 78) . "\n";
echo "                          نتائج التحليل                                       \n";
echo str_repeat("═", 78) . "\n\n";

// حساب المتغيرات
$sponsoredDataSource = $personType ?: ($sponsorship->orphan_name ? 'sponsorship_only' : 'none');
$sponsoredShow4Fields = in_array($personType, ['re_people', 'data', 'dead_people', 'civil_registry']);
$guardianShow4Fields = in_array($guardianDataSource, ['data', 'civil_registry']);
$guardianHasNoIdentity = empty($guardianIdentityNumber);
$guardianNeedsCivilSearch = $guardianHasNoIdentity;

echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                              متغيرات التحكم                                   │\n";
echo "├──────────────────────────────────────────────────────────────────────────────┤\n";
printf("│  _sponsored_data_source:      %-46s │\n", $sponsoredDataSource);
printf("│  _guardian_data_source:       %-46s │\n", $guardianDataSource);
printf("│  _sponsored_show_4_fields:    %-46s │\n", $sponsoredShow4Fields ? 'true ✓' : 'false ✗');
printf("│  _guardian_show_4_fields:     %-46s │\n", $guardianShow4Fields ? 'true ✓' : 'false ✗');
printf("│  _guardian_needs_civil_search: %-45s │\n", $guardianNeedsCivilSearch ? 'true ✓' : 'false ✗');
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

echo "\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                        ما سيُعرض في الواجهة                                   │\n";
echo "├──────────────────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                              │\n";

// المكفول
echo "│  🧑 المكفول:                                                                 │\n";
if ($sponsoredShow4Fields) {
    echo "│     └── 4 حقول منفصلة للاسم                                               │\n";
    if ($civilRegistryData) {
        printf("│         • الاسم الأول: %-52s │\n", $civilRegistryData['first_name']);
        printf("│         • اسم الأب: %-55s │\n", $civilRegistryData['second_name']);
        printf("│         • اسم الجد: %-55s │\n", $civilRegistryData['third_name']);
        printf("│         • اسم العائلة: %-52s │\n", $civilRegistryData['last_name']);
    }
} else {
    echo "│     └── حقل واحد مدمج                                                     │\n";
    printf("│         • الاسم: %-58s │\n", $sponsoredPersonName);
}

echo "│                                                                              │\n";

// المعيل
echo "│  👤 المعيل:                                                                  │\n";
if ($guardianShow4Fields) {
    echo "│     └── 4 حقول منفصلة للاسم                                               │\n";
    if ($guardianCivilRegistryData) {
        printf("│         • الاسم الأول: %-52s │\n", $guardianCivilRegistryData['first_name']);
        printf("│         • اسم الأب: %-55s │\n", $guardianCivilRegistryData['second_name']);
        printf("│         • اسم الجد: %-55s │\n", $guardianCivilRegistryData['third_name']);
        printf("│         • اسم العائلة: %-52s │\n", $guardianCivilRegistryData['last_name']);
    }
} else {
    echo "│     └── حقل واحد مدمج                                                     │\n";
    printf("│         • الاسم: %-58s │\n", $sponsorship->guardian_name);
}

echo "│                                                                              │\n";

// زر البحث
echo "│  🔍 زر البحث في السجل المدني:                                               │\n";
if ($guardianNeedsCivilSearch) {
    echo "│     └── ✅ مُفعّل (لا يوجد رقم هوية للمعيل)                                │\n";
} else {
    echo "│     └── ❌ غير مُفعّل (رقم الهوية موجود)                                   │\n";
}

echo "│                                                                              │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

// === اختبار الـ Controller الفعلي ===
echo "\n" . str_repeat("═", 78) . "\n";
echo "                    اختبار Controller الفعلي                                   \n";
echo str_repeat("═", 78) . "\n\n";

try {
    $controller = new \App\Http\Controllers\Users\ShowGeneralRegisrationController();

    // استخدام Reflection للوصول للدالة الخاصة
    $reflection = new ReflectionMethod($controller, 'extractFieldValues');
    $reflection->setAccessible(true);

    // تحويل الكفالة إلى كائن مع العلاقات
    $sponsorshipWithRelations = DB::table('sponsorships')
        ->where('identity_number', $sponsoredId)
        ->first();

    // إضافة relationData
    $sponsorshipWithRelations->relationData = null;
    if ($sponsorshipWithRelations) {
        $dataRecord = DB::table('data')
            ->where('data_id_number', $guardianIdentityNumber)
            ->first();
        if ($dataRecord) {
            $sponsorshipWithRelations->relationData = $dataRecord;
        }
    }

    // استدعاء الدالة
    $fieldValues = $reflection->invoke($controller, $sponsorshipWithRelations);

    echo "📊 نتائج extractFieldValues:\n\n";

    echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
    echo "│                           متغيرات التحكم من Controller                       │\n";
    echo "├──────────────────────────────────────────────────────────────────────────────┤\n";
    printf("│  _sponsored_data_source:      %-46s │\n", $fieldValues['_sponsored_data_source'] ?? 'غير محدد');
    printf("│  _guardian_data_source:       %-46s │\n", $fieldValues['_guardian_data_source'] ?? 'غير محدد');
    printf("│  _sponsored_show_4_fields:    %-46s │\n", ($fieldValues['_sponsored_show_4_fields'] ?? false) ? 'true ✓' : 'false ✗');
    printf("│  _guardian_show_4_fields:     %-46s │\n", ($fieldValues['_guardian_show_4_fields'] ?? false) ? 'true ✓' : 'false ✗');
    printf("│  _guardian_needs_civil_search: %-45s │\n", ($fieldValues['_guardian_needs_civil_search'] ?? false) ? 'true ✓' : 'false ✗');
    echo "└──────────────────────────────────────────────────────────────────────────────┘\n";

    echo "\n📋 حقول الأسماء:\n";
    echo "   المكفول:\n";
    printf("     • field_sponsor_name: %s\n", $fieldValues['field_sponsor_name'] ?? 'فارغ');
    printf("     • field_sponsored_first_name: %s\n", $fieldValues['field_sponsored_first_name'] ?? 'فارغ');
    printf("     • field_sponsored_second_name: %s\n", $fieldValues['field_sponsored_second_name'] ?? 'فارغ');
    printf("     • field_sponsored_third_name: %s\n", $fieldValues['field_sponsored_third_name'] ?? 'فارغ');
    printf("     • field_sponsored_last_name: %s\n", $fieldValues['field_sponsored_last_name'] ?? 'فارغ');

    echo "\n   المعيل:\n";
    printf("     • field_guardian_name: %s\n", $fieldValues['field_guardian_name'] ?? 'فارغ');
    printf("     • field_guardian_identity_number: %s\n", $fieldValues['field_guardian_identity_number'] ?? 'فارغ');
    printf("     • field_data_first_name: %s\n", $fieldValues['field_data_first_name'] ?? 'فارغ');
    printf("     • field_data_father_name: %s\n", $fieldValues['field_data_father_name'] ?? 'فارغ');

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
}

echo "\n✅ انتهى الاختبار الشامل\n";
