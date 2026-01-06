<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║               اختبار شامل لمصادر البيانات وعدد الحقول المعروضة                 ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
echo "║  المكفول: 42979087 | المعيل: 42060051                                        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

// أرقام الهوية للاختبار
$sponsoredId = '42979087'; // المكفول
$guardianId = '42060051';  // المعيل

// قاعدة بيانات السجل المدني
$civilDB = DB::connection('civilregistry');

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الأول: فحص جميع الجداول للمكفول
// ═══════════════════════════════════════════════════════════════════════════════

echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                    الجزء الأول: بيانات المكفول (42979087)                     │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n\n";

$sponsoredResults = [];

// 1. جدول sponsorships
echo "1️⃣  جدول sponsorships:\n";
$sponsorship = DB::table('sponsorships')->where('identity_number', $sponsoredId)->first();
if ($sponsorship) {
    $sponsoredResults['sponsorships'] = [
        'found' => true,
        'data' => [
            'orphan_name' => $sponsorship->orphan_name ?? 'NULL',
            'guardian_identity_number' => $sponsorship->guardian_identity_number ?? 'NULL',
            'guardian_name' => $sponsorship->guardian_name ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - اسم اليتيم: " . ($sponsorship->orphan_name ?? 'NULL') . "\n";
    echo "   - رقم هوية المعيل: " . ($sponsorship->guardian_identity_number ?? 'NULL') . "\n";
    echo "   - اسم المعيل: " . ($sponsorship->guardian_name ?? 'NULL') . "\n";
} else {
    $sponsoredResults['sponsorships'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 2. جدول re_people (أفراد العائلة)
echo "\n2️⃣  جدول re_people (أفراد العائلة):\n";
$rePeople = DB::table('re_people')->where('person_id', $sponsoredId)->first();
if ($rePeople) {
    $sponsoredResults['re_people'] = [
        'found' => true,
        'data' => [
            'first_name' => $rePeople->first_name ?? 'NULL',
            'second_name' => $rePeople->second_name ?? 'NULL',
            'third_name' => $rePeople->third_name ?? 'NULL',
            'last_name' => $rePeople->last_name ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - الاسم الأول: " . ($rePeople->first_name ?? 'NULL') . "\n";
    echo "   - اسم الأب: " . ($rePeople->second_name ?? 'NULL') . "\n";
    echo "   - اسم الجد: " . ($rePeople->third_name ?? 'NULL') . "\n";
    echo "   - اسم العائلة: " . ($rePeople->last_name ?? 'NULL') . "\n";
} else {
    $sponsoredResults['re_people'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 3. جدول dead_people (المتوفين)
echo "\n3️⃣  جدول dead_people (المتوفين):\n";
$deadPeople = DB::table('dead_people')
    ->where('father_id', $sponsoredId)
    ->orWhere('mother_id', $sponsoredId)
    ->first();
if ($deadPeople) {
    $sponsoredResults['dead_people'] = [
        'found' => true,
        'data' => $deadPeople
    ];
    echo "   ✓ وُجد السجل\n";
} else {
    $sponsoredResults['dead_people'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 4. جدول data (بيانات المعيلين)
echo "\n4️⃣  جدول data (بيانات المعيلين):\n";
$dataTable = DB::table('data')->where('data_id_number', $sponsoredId)->first();
if ($dataTable) {
    $sponsoredResults['data'] = [
        'found' => true,
        'data' => [
            'data_first_name' => $dataTable->data_first_name ?? 'NULL',
            'data_father_name' => $dataTable->data_father_name ?? 'NULL',
            'data_grandfather_name' => $dataTable->data_grandfather_name ?? 'NULL',
            'data_family_name' => $dataTable->data_family_name ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - الاسم الأول: " . ($dataTable->data_first_name ?? 'NULL') . "\n";
    echo "   - اسم الأب: " . ($dataTable->data_father_name ?? 'NULL') . "\n";
    echo "   - اسم الجد: " . ($dataTable->data_grandfather_name ?? 'NULL') . "\n";
    echo "   - اسم العائلة: " . ($dataTable->data_family_name ?? 'NULL') . "\n";
} else {
    $sponsoredResults['data'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 5. جدول persons (السجل المدني)
echo "\n5️⃣  جدول persons (السجل المدني - civilregistry):\n";
$civilPerson = $civilDB->table('persons')->where('CI_ID_NUM', $sponsoredId)->first();
if ($civilPerson) {
    $sponsoredResults['persons_civil'] = [
        'found' => true,
        'data' => [
            'CI_FIRST_ARB' => $civilPerson->CI_FIRST_ARB ?? 'NULL',
            'CI_FATHER_ARB' => $civilPerson->CI_FATHER_ARB ?? 'NULL',
            'CI_GRAND_FATHER_ARB' => $civilPerson->CI_GRAND_FATHER_ARB ?? 'NULL',
            'CI_FAMILY_ARB' => $civilPerson->CI_FAMILY_ARB ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - الاسم الأول: " . ($civilPerson->CI_FIRST_ARB ?? 'NULL') . "\n";
    echo "   - اسم الأب: " . ($civilPerson->CI_FATHER_ARB ?? 'NULL') . "\n";
    echo "   - اسم الجد: " . ($civilPerson->CI_GRAND_FATHER_ARB ?? 'NULL') . "\n";
    echo "   - اسم العائلة: " . ($civilPerson->CI_FAMILY_ARB ?? 'NULL') . "\n";
} else {
    $sponsoredResults['persons_civil'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الثاني: فحص جميع الجداول للمعيل
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n\n┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                    الجزء الثاني: بيانات المعيل (42060051)                     │\n";
echo "└──────────────────────────────────────────────────────────────────────────────┘\n\n";

$guardianResults = [];

// 1. جدول sponsorships (كمعيل)
echo "1️⃣  جدول sponsorships (كمعيل):\n";
$guardianSponsorship = DB::table('sponsorships')->where('guardian_identity_number', $guardianId)->first();
if ($guardianSponsorship) {
    $guardianResults['sponsorships'] = [
        'found' => true,
        'data' => [
            'guardian_name' => $guardianSponsorship->guardian_name ?? 'NULL',
            'orphan_name' => $guardianSponsorship->orphan_name ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - اسم المعيل: " . ($guardianSponsorship->guardian_name ?? 'NULL') . "\n";
    echo "   - اسم اليتيم: " . ($guardianSponsorship->orphan_name ?? 'NULL') . "\n";
} else {
    $guardianResults['sponsorships'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 2. جدول re_people (أفراد العائلة)
echo "\n2️⃣  جدول re_people (أفراد العائلة):\n";
$guardianRePeople = DB::table('re_people')->where('person_id', $guardianId)->first();
if ($guardianRePeople) {
    $guardianResults['re_people'] = [
        'found' => true,
        'data' => [
            'first_name' => $guardianRePeople->first_name ?? 'NULL',
            'second_name' => $guardianRePeople->second_name ?? 'NULL',
            'third_name' => $guardianRePeople->third_name ?? 'NULL',
            'last_name' => $guardianRePeople->last_name ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - الاسم الأول: " . ($guardianRePeople->first_name ?? 'NULL') . "\n";
    echo "   - اسم الأب: " . ($guardianRePeople->second_name ?? 'NULL') . "\n";
    echo "   - اسم الجد: " . ($guardianRePeople->third_name ?? 'NULL') . "\n";
    echo "   - اسم العائلة: " . ($guardianRePeople->last_name ?? 'NULL') . "\n";
} else {
    $guardianResults['re_people'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 3. جدول dead_people (المتوفين)
echo "\n3️⃣  جدول dead_people (المتوفين):\n";
$guardianDeadPeople = DB::table('dead_people')
    ->where('father_id', $guardianId)
    ->orWhere('mother_id', $guardianId)
    ->first();
if ($guardianDeadPeople) {
    $guardianResults['dead_people'] = [
        'found' => true,
        'data' => $guardianDeadPeople
    ];
    echo "   ✓ وُجد السجل\n";
} else {
    $guardianResults['dead_people'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 4. جدول data (بيانات المعيلين)
echo "\n4️⃣  جدول data (بيانات المعيلين):\n";
$guardianDataTable = DB::table('data')->where('data_id_number', $guardianId)->first();
if ($guardianDataTable) {
    $guardianResults['data'] = [
        'found' => true,
        'data' => [
            'data_first_name' => $guardianDataTable->data_first_name ?? 'NULL',
            'data_father_name' => $guardianDataTable->data_father_name ?? 'NULL',
            'data_grandfather_name' => $guardianDataTable->data_grandfather_name ?? 'NULL',
            'data_family_name' => $guardianDataTable->data_family_name ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - الاسم الأول: " . ($guardianDataTable->data_first_name ?? 'NULL') . "\n";
    echo "   - اسم الأب: " . ($guardianDataTable->data_father_name ?? 'NULL') . "\n";
    echo "   - اسم الجد: " . ($guardianDataTable->data_grandfather_name ?? 'NULL') . "\n";
    echo "   - اسم العائلة: " . ($guardianDataTable->data_family_name ?? 'NULL') . "\n";
} else {
    $guardianResults['data'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// 5. جدول persons (السجل المدني)
echo "\n5️⃣  جدول persons (السجل المدني - civilregistry):\n";
$guardianCivil = $civilDB->table('persons')->where('CI_ID_NUM', $guardianId)->first();
if ($guardianCivil) {
    $guardianResults['persons_civil'] = [
        'found' => true,
        'data' => [
            'CI_FIRST_ARB' => $guardianCivil->CI_FIRST_ARB ?? 'NULL',
            'CI_FATHER_ARB' => $guardianCivil->CI_FATHER_ARB ?? 'NULL',
            'CI_GRAND_FATHER_ARB' => $guardianCivil->CI_GRAND_FATHER_ARB ?? 'NULL',
            'CI_FAMILY_ARB' => $guardianCivil->CI_FAMILY_ARB ?? 'NULL',
        ]
    ];
    echo "   ✓ وُجد السجل\n";
    echo "   - الاسم الأول: " . ($guardianCivil->CI_FIRST_ARB ?? 'NULL') . "\n";
    echo "   - اسم الأب: " . ($guardianCivil->CI_FATHER_ARB ?? 'NULL') . "\n";
    echo "   - اسم الجد: " . ($guardianCivil->CI_GRAND_FATHER_ARB ?? 'NULL') . "\n";
    echo "   - اسم العائلة: " . ($guardianCivil->CI_FAMILY_ARB ?? 'NULL') . "\n";
} else {
    $guardianResults['persons_civil'] = ['found' => false];
    echo "   ✗ لا يوجد سجل\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الثالث: تحديد مصدر البيانات وعدد الحقول
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    الجزء الثالث: تحديد مصدر البيانات                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

// تحديد مصدر بيانات المكفول
function determineSponsoredDataSource($results) {
    // الأولوية: re_people > dead_people > civil_registry > sponsorship_only
    if ($results['re_people']['found']) {
        return ['source' => 're_people', 'fields' => 4, 'description' => 'أفراد العائلة'];
    }
    if ($results['dead_people']['found']) {
        return ['source' => 'dead_people', 'fields' => 4, 'description' => 'المتوفين'];
    }
    if ($results['persons_civil']['found']) {
        return ['source' => 'civil_registry', 'fields' => 4, 'description' => 'السجل المدني'];
    }
    if ($results['sponsorships']['found']) {
        return ['source' => 'sponsorship_only', 'fields' => 1, 'description' => 'جدول الكفالات فقط'];
    }
    return ['source' => 'none', 'fields' => 0, 'description' => 'لا توجد بيانات'];
}

// تحديد مصدر بيانات المعيل
function determineGuardianDataSource($results) {
    // الأولوية: data > re_people > dead_people > civil_registry > sponsorship_only
    if ($results['data']['found']) {
        return ['source' => 'data', 'fields' => 4, 'description' => 'جدول البيانات التفصيلية'];
    }
    if ($results['re_people']['found']) {
        return ['source' => 're_people', 'fields' => 4, 'description' => 'أفراد العائلة'];
    }
    if ($results['dead_people']['found']) {
        return ['source' => 'dead_people', 'fields' => 4, 'description' => 'المتوفين'];
    }
    if ($results['persons_civil']['found']) {
        return ['source' => 'civil_registry', 'fields' => 4, 'description' => 'السجل المدني'];
    }
    if ($results['sponsorships']['found']) {
        return ['source' => 'sponsorship_only', 'fields' => 1, 'description' => 'جدول الكفالات فقط'];
    }
    return ['source' => 'none', 'fields' => 0, 'description' => 'لا توجد بيانات'];
}

$sponsoredSource = determineSponsoredDataSource($sponsoredResults);
$guardianSource = determineGuardianDataSource($guardianResults);

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الرابع: ملخص النتائج
// ═══════════════════════════════════════════════════════════════════════════════

echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
echo "│                              ملخص الجداول                                     │\n";
echo "├──────────────────────────────────────────────────────────────────────────────┤\n";
echo "│          الجدول          │    المكفول    │     المعيل    │                   │\n";
echo "├──────────────────────────────────────────────────────────────────────────────┤\n";

$tables = ['sponsorships', 're_people', 'dead_people', 'data', 'persons_civil'];
$tableNames = [
    'sponsorships' => 'sponsorships (الكفالات)',
    're_people' => 're_people (أفراد العائلة)',
    'dead_people' => 'dead_people (المتوفين)',
    'data' => 'data (بيانات المعيلين)',
    'persons_civil' => 'persons (السجل المدني)',
];

foreach ($tables as $table) {
    $sponsoredStatus = $sponsoredResults[$table]['found'] ? '✓ موجود' : '✗ غير موجود';
    $guardianStatus = $guardianResults[$table]['found'] ? '✓ موجود' : '✗ غير موجود';

    printf("│ %-24s │ %-13s │ %-13s │\n", $tableNames[$table], $sponsoredStatus, $guardianStatus);
}

echo "└──────────────────────────────────────────────────────────────────────────────┘\n\n";

// عرض قرار عدد الحقول
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                          قرار عدد الحقول المعروضة                             ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";

echo "║                                                                              ║\n";
echo "║  ┌─────────────────── المكفول (42979087) ───────────────────┐               ║\n";
echo "║  │                                                          │               ║\n";
printf("║  │  مصدر البيانات: %-40s │               ║\n", $sponsoredSource['description']);
printf("║  │  عدد حقول الاسم: %-40s │               ║\n", $sponsoredSource['fields'] . " حقل/حقول");
echo "║  │                                                          │               ║\n";

if ($sponsoredSource['fields'] == 4) {
    echo "║  │  الحقول المعروضة:                                       │               ║\n";
    echo "║  │    ├── الاسم الأول                                      │               ║\n";
    echo "║  │    ├── اسم الأب                                         │               ║\n";
    echo "║  │    ├── اسم الجد                                         │               ║\n";
    echo "║  │    └── اسم العائلة                                      │               ║\n";

    if ($sponsoredSource['source'] == 'civil_registry') {
        $data = $sponsoredResults['persons_civil']['data'];
        echo "║  │                                                          │               ║\n";
        printf("║  │  القيم: %s %s %s %s                   │               ║\n",
            $data['CI_FIRST_ARB'], $data['CI_FATHER_ARB'],
            $data['CI_GRAND_FATHER_ARB'], $data['CI_FAMILY_ARB']);
    }
} else if ($sponsoredSource['fields'] == 1) {
    echo "║  │  الحقول المعروضة:                                       │               ║\n";
    echo "║  │    └── اسم المكفول (حقل واحد مدمج)                      │               ║\n";
    if ($sponsoredResults['sponsorships']['found']) {
        printf("║  │  القيمة: %-48s │               ║\n",
            $sponsoredResults['sponsorships']['data']['orphan_name']);
    }
}
echo "║  └──────────────────────────────────────────────────────────┘               ║\n";

echo "║                                                                              ║\n";
echo "║  ┌─────────────────── المعيل (42060051) ────────────────────┐               ║\n";
echo "║  │                                                          │               ║\n";
printf("║  │  مصدر البيانات: %-40s │               ║\n", $guardianSource['description']);
printf("║  │  عدد حقول الاسم: %-40s │               ║\n", $guardianSource['fields'] . " حقل/حقول");
echo "║  │                                                          │               ║\n";

if ($guardianSource['fields'] == 4) {
    echo "║  │  الحقول المعروضة:                                       │               ║\n";
    echo "║  │    ├── الاسم الأول                                      │               ║\n";
    echo "║  │    ├── اسم الأب                                         │               ║\n";
    echo "║  │    ├── اسم الجد                                         │               ║\n";
    echo "║  │    └── اسم العائلة                                      │               ║\n";

    if ($guardianSource['source'] == 'civil_registry') {
        $data = $guardianResults['persons_civil']['data'];
        echo "║  │                                                          │               ║\n";
        printf("║  │  القيم: %s %s %s %s                      │               ║\n",
            $data['CI_FIRST_ARB'], $data['CI_FATHER_ARB'],
            $data['CI_GRAND_FATHER_ARB'], $data['CI_FAMILY_ARB']);
    }
} else if ($guardianSource['fields'] == 1) {
    echo "║  │  الحقول المعروضة:                                       │               ║\n";
    echo "║  │    └── اسم المعيل (حقل واحد مدمج)                       │               ║\n";
    if ($guardianResults['sponsorships']['found']) {
        printf("║  │  القيمة: %-48s │               ║\n",
            $guardianResults['sponsorships']['data']['guardian_name'] ?? 'NULL');
    }
}
echo "║  └──────────────────────────────────────────────────────────┘               ║\n";
echo "║                                                                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء الخامس: اختبار Controller
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    الجزء الخامس: اختبار دالة extractFieldValues               ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    // محاكاة ما يفعله Controller
    $controller = new \App\Http\Controllers\Users\ShowGeneralRegisrationController();

    // الحصول على بيانات الكفالة
    if ($sponsorship) {
        echo "اختبار دالة searchCivilRegistry:\n";

        $reflection = new ReflectionMethod($controller, 'searchCivilRegistry');
        $reflection->setAccessible(true);

        // البحث عن المكفول
        $sponsoredCivil = $reflection->invoke($controller, $sponsoredId);
        echo "\n  المكفول ({$sponsoredId}):\n";
        if ($sponsoredCivil) {
            echo "    ✓ وُجد في السجل المدني\n";
            echo "    - الاسم الكامل: {$sponsoredCivil['first_name']} {$sponsoredCivil['second_name']} {$sponsoredCivil['third_name']} {$sponsoredCivil['last_name']}\n";
        } else {
            echo "    ✗ لم يُعثر عليه في السجل المدني\n";
        }

        // البحث عن المعيل
        $guardianCivil = $reflection->invoke($controller, $guardianId);
        echo "\n  المعيل ({$guardianId}):\n";
        if ($guardianCivil) {
            echo "    ✓ وُجد في السجل المدني\n";
            echo "    - الاسم الكامل: {$guardianCivil['first_name']} {$guardianCivil['second_name']} {$guardianCivil['third_name']} {$guardianCivil['last_name']}\n";
        } else {
            echo "    ✗ لم يُعثر عليه في السجل المدني\n";
        }
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
// الجزء السادس: الخلاصة النهائية
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                              الخلاصة النهائية                                 ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════════╣\n";
echo "║                                                                              ║\n";

// المكفول
$sponsoredFoundIn = [];
$sponsoredNotFoundIn = [];
foreach ($sponsoredResults as $table => $result) {
    if ($result['found']) {
        $sponsoredFoundIn[] = $tableNames[$table];
    } else {
        $sponsoredNotFoundIn[] = $tableNames[$table];
    }
}

echo "║  📊 المكفول (42979087):                                                       ║\n";
echo "║  ─────────────────────────────────────────────────────────────────────────── ║\n";
echo "║  ✓ وُجد في:                                                                  ║\n";
foreach ($sponsoredFoundIn as $table) {
    printf("║      • %-68s ║\n", $table);
}
echo "║  ✗ لم يُوجد في:                                                              ║\n";
foreach ($sponsoredNotFoundIn as $table) {
    printf("║      • %-68s ║\n", $table);
}
printf("║  📝 القرار: عرض %-60s ║\n", $sponsoredSource['fields'] . " حقل/حقول (من " . $sponsoredSource['description'] . ")");

echo "║                                                                              ║\n";

// المعيل
$guardianFoundIn = [];
$guardianNotFoundIn = [];
foreach ($guardianResults as $table => $result) {
    if ($result['found']) {
        $guardianFoundIn[] = $tableNames[$table];
    } else {
        $guardianNotFoundIn[] = $tableNames[$table];
    }
}

echo "║  📊 المعيل (42060051):                                                        ║\n";
echo "║  ─────────────────────────────────────────────────────────────────────────── ║\n";
echo "║  ✓ وُجد في:                                                                  ║\n";
foreach ($guardianFoundIn as $table) {
    printf("║      • %-68s ║\n", $table);
}
echo "║  ✗ لم يُوجد في:                                                              ║\n";
foreach ($guardianNotFoundIn as $table) {
    printf("║      • %-68s ║\n", $table);
}
printf("║  📝 القرار: عرض %-60s ║\n", $guardianSource['fields'] . " حقل/حقول (من " . $guardianSource['description'] . ")");

echo "║                                                                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";

echo "\n✅ انتهى الاختبار الشامل\n";
