<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "===========================================\n";
echo "  اختبار الاتصال بقاعدة البيانات civilregistry\n";
echo "===========================================\n\n";

// أرقام الهوية للاختبار
$sponsoredId = '42979087'; // المكفول
$guardianId = '42060051';  // المعيل

// ملاحظة: سنستخدم الاتصال civilregistry بدلاً من الافتراضي
$civilDB = DB::connection('civilregistry');

echo "رقم هوية المكفول: {$sponsoredId}\n";
echo "رقم هوية المعيل: {$guardianId}\n\n";

// 1. فحص الاتصال بقاعدة البيانات civilregistry
echo "=== 1. فحص الاتصال بقاعدة البيانات civilregistry ===\n";
try {
    $dbName = $civilDB->getDatabaseName();
    echo "✓ متصل بقاعدة البيانات: {$dbName}\n";
} catch (\Exception $e) {
    echo "خطأ في الاتصال: " . $e->getMessage() . "\n";
}

// 2. فحص وجود جدول persons
echo "\n=== 2. فحص جدول persons ===\n";
try {
    $tables = $civilDB->select('SHOW TABLES');
    $tableNames = [];
    foreach ($tables as $table) {
        $tableNames[] = array_values((array)$table)[0];
    }

    if (in_array('persons', $tableNames)) {
        echo "✓ جدول persons موجود في civilregistry\n";
    } else {
        echo "✗ جدول persons غير موجود\n";
        echo "الجداول المتاحة:\n";
        foreach ($tableNames as $t) {
            echo "  - {$t}\n";
        }
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// 3. فحص أعمدة جدول persons
echo "\n=== 3. أعمدة جدول persons ===\n";
try {
    $columns = $civilDB->select('SHOW COLUMNS FROM persons');
    echo "الأعمدة في جدول persons:\n";
    foreach ($columns as $col) {
        echo "  - {$col->Field} ({$col->Type})\n";
    }
} catch (\Exception $e) {
    echo "خطأ في قراءة الأعمدة: " . $e->getMessage() . "\n";
}

// 4. البحث عن المكفول في السجل المدني
echo "\n=== 4. البحث عن المكفول ({$sponsoredId}) ===\n";
try {
    $sponsored = $civilDB->table('persons')->where('CI_ID_NUM', $sponsoredId)->first();

    if ($sponsored) {
        echo "✓ تم العثور على المكفول:\n";
        echo "  - CI_ID_NUM: " . ($sponsored->CI_ID_NUM ?? 'NULL') . "\n";
        echo "  - CI_FIRST_ARB: " . ($sponsored->CI_FIRST_ARB ?? 'NULL') . "\n";
        echo "  - CI_FATHER_ARB: " . ($sponsored->CI_FATHER_ARB ?? 'NULL') . "\n";
        echo "  - CI_GRAND_FATHER_ARB: " . ($sponsored->CI_GRAND_FATHER_ARB ?? 'NULL') . "\n";
        echo "  - CI_FAMILY_ARB: " . ($sponsored->CI_FAMILY_ARB ?? 'NULL') . "\n";
        echo "  - CI_BIRTH_DT: " . ($sponsored->CI_BIRTH_DT ?? 'NULL') . "\n";
        echo "  - CI_SEX_CD: " . ($sponsored->CI_SEX_CD ?? 'NULL') . "\n";
        echo "  - CITY: " . ($sponsored->CITY ?? 'NULL') . "\n";
        echo "  - MOTHER_NAME1: " . ($sponsored->MOTHER_NAME1 ?? 'NULL') . "\n";
    } else {
        echo "✗ لم يتم العثور على المكفول\n";
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// 5. البحث عن المعيل في السجل المدني
echo "\n=== 5. البحث عن المعيل ({$guardianId}) ===\n";
try {
    $guardian = $civilDB->table('persons')->where('CI_ID_NUM', $guardianId)->first();

    if ($guardian) {
        echo "✓ تم العثور على المعيل:\n";
        echo "  - CI_ID_NUM: " . ($guardian->CI_ID_NUM ?? 'NULL') . "\n";
        echo "  - CI_FIRST_ARB: " . ($guardian->CI_FIRST_ARB ?? 'NULL') . "\n";
        echo "  - CI_FATHER_ARB: " . ($guardian->CI_FATHER_ARB ?? 'NULL') . "\n";
        echo "  - CI_GRAND_FATHER_ARB: " . ($guardian->CI_GRAND_FATHER_ARB ?? 'NULL') . "\n";
        echo "  - CI_FAMILY_ARB: " . ($guardian->CI_FAMILY_ARB ?? 'NULL') . "\n";
        echo "  - CI_BIRTH_DT: " . ($guardian->CI_BIRTH_DT ?? 'NULL') . "\n";
        echo "  - CI_SEX_CD: " . ($guardian->CI_SEX_CD ?? 'NULL') . "\n";
        echo "  - CITY: " . ($guardian->CITY ?? 'NULL') . "\n";
        echo "  - MOTHER_NAME1: " . ($guardian->MOTHER_NAME1 ?? 'NULL') . "\n";
    } else {
        echo "✗ لم يتم العثور على المعيل\n";
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// 6. عرض عينة من البيانات
echo "\n=== 6. عينة من بيانات جدول persons في civilregistry ===\n";
try {
    $sample = $civilDB->table('persons')->limit(5)->get();
    echo "أول 5 سجلات:\n";
    foreach ($sample as $row) {
        echo "  - CI_ID_NUM: " . ($row->CI_ID_NUM ?? 'NULL');
        echo " | الاسم: " . ($row->CI_FIRST_ARB ?? '') . " " . ($row->CI_FAMILY_ARB ?? '') . "\n";
    }

    $total = $civilDB->table('persons')->count();
    echo "\nإجمالي السجلات: {$total}\n";
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// 7. عرض البيانات الكاملة بتنسيق جميل
echo "\n=== 7. ملخص البيانات ===\n";
try {
    $sponsoredData = $civilDB->table('persons')->where('CI_ID_NUM', $sponsoredId)->first();
    $guardianData = $civilDB->table('persons')->where('CI_ID_NUM', $guardianId)->first();

    if ($sponsoredData) {
        echo "\n┌─────────────────────────────────────────────┐\n";
        echo "│          بيانات المكفول (42979087)          │\n";
        echo "├─────────────────────────────────────────────┤\n";
        echo "│ الاسم الأول: " . str_pad($sponsoredData->CI_FIRST_ARB ?? '', 30) . "│\n";
        echo "│ اسم الأب: " . str_pad($sponsoredData->CI_FATHER_ARB ?? '', 33) . "│\n";
        echo "│ اسم الجد: " . str_pad($sponsoredData->CI_GRAND_FATHER_ARB ?? '', 33) . "│\n";
        echo "│ اسم العائلة: " . str_pad($sponsoredData->CI_FAMILY_ARB ?? '', 29) . "│\n";
        echo "│ تاريخ الميلاد: " . str_pad($sponsoredData->CI_BIRTH_DT ?? '', 27) . "│\n";
        echo "│ الجنس: " . str_pad($sponsoredData->CI_SEX_CD ?? '', 35) . "│\n";
        echo "│ المدينة: " . str_pad($sponsoredData->CITY ?? '', 33) . "│\n";
        echo "└─────────────────────────────────────────────┘\n";
    }

    if ($guardianData) {
        echo "\n┌─────────────────────────────────────────────┐\n";
        echo "│          بيانات المعيل (42060051)           │\n";
        echo "├─────────────────────────────────────────────┤\n";
        echo "│ الاسم الأول: " . str_pad($guardianData->CI_FIRST_ARB ?? '', 30) . "│\n";
        echo "│ اسم الأب: " . str_pad($guardianData->CI_FATHER_ARB ?? '', 33) . "│\n";
        echo "│ اسم الجد: " . str_pad($guardianData->CI_GRAND_FATHER_ARB ?? '', 33) . "│\n";
        echo "│ اسم العائلة: " . str_pad($guardianData->CI_FAMILY_ARB ?? '', 29) . "│\n";
        echo "│ تاريخ الميلاد: " . str_pad($guardianData->CI_BIRTH_DT ?? '', 27) . "│\n";
        echo "│ الجنس: " . str_pad($guardianData->CI_SEX_CD ?? '', 35) . "│\n";
        echo "│ المدينة: " . str_pad($guardianData->CITY ?? '', 33) . "│\n";
        echo "└─────────────────────────────────────────────┘\n";
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}

// 8. اختبار دالة البحث في Controller (بعد التصحيح)
echo "\n=== 8. اختبار دالة البحث في Controller ===\n";
try {
    $controller = new \App\Http\Controllers\Users\ShowGeneralRegisrationController();

    // استخدام Reflection للوصول للدالة الخاصة
    $reflection = new ReflectionMethod($controller, 'searchCivilRegistry');
    $reflection->setAccessible(true);

    echo "البحث عن المكفول ({$sponsoredId}):\n";
    $sponsoredResult = $reflection->invoke($controller, $sponsoredId);
    if ($sponsoredResult) {
        echo "  ✓ وُجد:\n";
        echo "    - الاسم: {$sponsoredResult['first_name']} {$sponsoredResult['second_name']} {$sponsoredResult['third_name']} {$sponsoredResult['last_name']}\n";
        echo "    - رقم الهوية: {$sponsoredResult['identity_number']}\n";
    } else {
        echo "  ✗ لم يُعثر عليه\n";
    }

    echo "\nالبحث عن المعيل ({$guardianId}):\n";
    $guardianResult = $reflection->invoke($controller, $guardianId);
    if ($guardianResult) {
        echo "  ✓ وُجد:\n";
        echo "    - الاسم: {$guardianResult['first_name']} {$guardianResult['second_name']} {$guardianResult['third_name']} {$guardianResult['last_name']}\n";
        echo "    - رقم الهوية: {$guardianResult['identity_number']}\n";
    } else {
        echo "  ✗ لم يُعثر عليه\n";
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
    echo "تفاصيل: " . $e->getTraceAsString() . "\n";
}

echo "\n===========================================\n";
echo "  انتهى الاختبار\n";
echo "===========================================\n";
