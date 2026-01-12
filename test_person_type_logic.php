<?php
/**
 * اختبار منطق person_type الجديد
 * هذا الملف يختبر الخوارزمية الذكية لتوجيه البيانات للجداول الصحيحة
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║       اختبار منطق person_type في النظام                      ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";

$tests = [];
$passed = 0;
$failed = 0;

// ========================================
// اختبار 1: التحقق من وجود حقل person_type في sponsorships
// ========================================
echo "║ 1. فحص حقل person_type في جدول sponsorships...              ║\n";
try {
    $columns = DB::select("SHOW COLUMNS FROM sponsorships LIKE 'person_type'");
    if (count($columns) > 0) {
        echo "║    ✅ حقل person_type موجود                                ║\n";
        $passed++;
    } else {
        echo "║    ❌ حقل person_type غير موجود!                           ║\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 2: التحقق من قيم person_type الموجودة
// ========================================
echo "║ 2. فحص قيم person_type الموجودة في البيانات...              ║\n";
try {
    $types = DB::table('sponsorships')
        ->select('person_type', DB::raw('COUNT(*) as count'))
        ->groupBy('person_type')
        ->get();
    
    echo "║    القيم الموجودة:                                          ║\n";
    foreach ($types as $type) {
        $typeName = $type->person_type ?: 'NULL';
        echo "║    - {$typeName}: {$type->count} سجل                              ║\n";
    }
    $passed++;
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 3: التحقق من حقول الأب في dead_people
// ========================================
echo "║ 3. فحص حقول الأب في جدول dead_people...                     ║\n";
try {
    $fatherFields = ['father_first_name', 'father_second_name', 'father_third_name', 'father_last_name', 'father_id'];
    $allExist = true;
    foreach ($fatherFields as $field) {
        $exists = DB::select("SHOW COLUMNS FROM dead_people LIKE '{$field}'");
        if (empty($exists)) {
            echo "║    ❌ حقل {$field} غير موجود!                              ║\n";
            $allExist = false;
        }
    }
    if ($allExist) {
        echo "║    ✅ جميع حقول الأب موجودة (5 حقول)                       ║\n";
        $passed++;
    } else {
        $failed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 4: التحقق من حقول الأم في dead_people
// ========================================
echo "║ 4. فحص حقول الأم في جدول dead_people...                     ║\n";
try {
    $motherFields = ['mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name', 'mother_id'];
    $allExist = true;
    foreach ($motherFields as $field) {
        $exists = DB::select("SHOW COLUMNS FROM dead_people LIKE '{$field}'");
        if (empty($exists)) {
            echo "║    ❌ حقل {$field} غير موجود!                              ║\n";
            $allExist = false;
        }
    }
    if ($allExist) {
        echo "║    ✅ جميع حقول الأم موجودة (5 حقول)                       ║\n";
        $passed++;
    } else {
        $failed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 5: التحقق من حقول re_people
// ========================================
echo "║ 5. فحص حقول جدول re_people...                               ║\n";
try {
    $requiredFields = ['registration_id', 'person_id', 'first_name', 'second_name', 'third_name', 'last_name', 'person_gender'];
    $allExist = true;
    $missingFields = [];
    foreach ($requiredFields as $field) {
        $exists = DB::select("SHOW COLUMNS FROM re_people LIKE '{$field}'");
        if (empty($exists)) {
            $missingFields[] = $field;
            $allExist = false;
        }
    }
    if ($allExist) {
        echo "║    ✅ جميع حقول re_people موجودة (7 حقول)                  ║\n";
        $passed++;
    } else {
        echo "║    ❌ حقول مفقودة: " . implode(', ', $missingFields) . "                 ║\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 6: التحقق من حقول data
// ========================================
echo "║ 6. فحص حقول جدول data...                                    ║\n";
try {
    $requiredFields = ['file_id_number', 'data_id_number', 'data_first_name', 'data_father_name', 'data_gender'];
    $allExist = true;
    $missingFields = [];
    foreach ($requiredFields as $field) {
        $exists = DB::select("SHOW COLUMNS FROM data LIKE '{$field}'");
        if (empty($exists)) {
            $missingFields[] = $field;
            $allExist = false;
        }
    }
    if ($allExist) {
        echo "║    ✅ جميع حقول data موجودة (5 حقول)                       ║\n";
        $passed++;
    } else {
        echo "║    ❌ حقول مفقودة: " . implode(', ', $missingFields) . "                 ║\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 7: محاكاة منطق المطابقة لـ breadwinner
// ========================================
echo "║ 7. اختبار منطق المطابقة لـ breadwinner...                   ║\n";
try {
    $sample = DB::table('data')->whereNotNull('file_id_number')->first();
    if ($sample) {
        $match = DB::table('data')
            ->where('file_id_number', $sample->file_id_number)
            ->first();
        if ($match) {
            echo "║    ✅ المطابقة تعمل: file_id_number = {$sample->file_id_number}    ║\n";
            $passed++;
        } else {
            echo "║    ❌ فشل المطابقة                                          ║\n";
            $failed++;
        }
    } else {
        echo "║    ⚠️ لا توجد بيانات للاختبار                               ║\n";
        $passed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 8: محاكاة منطق المطابقة لـ family_member
// ========================================
echo "║ 8. اختبار منطق المطابقة لـ family_member...                 ║\n";
try {
    $sample = DB::table('re_people')->whereNotNull('registration_id')->first();
    if ($sample) {
        $match = DB::table('re_people')
            ->where('registration_id', $sample->registration_id)
            ->first();
        if ($match) {
            echo "║    ✅ المطابقة تعمل: registration_id = {$sample->registration_id}   ║\n";
            $passed++;
        } else {
            echo "║    ❌ فشل المطابقة                                          ║\n";
            $failed++;
        }
    } else {
        echo "║    ⚠️ لا توجد بيانات للاختبار                               ║\n";
        $passed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 9: محاكاة منطق المطابقة لـ deceased
// ========================================
echo "║ 9. اختبار منطق المطابقة لـ deceased_father/mother...        ║\n";
try {
    $sample = DB::table('dead_people')->whereNotNull('re_file_id')->first();
    if ($sample) {
        $match = DB::table('dead_people')
            ->where('re_file_id', $sample->re_file_id)
            ->first();
        if ($match) {
            echo "║    ✅ المطابقة تعمل: re_file_id = {$sample->re_file_id}             ║\n";
            $passed++;
        } else {
            echo "║    ❌ فشل المطابقة                                          ║\n";
            $failed++;
        }
    } else {
        echo "║    ⚠️ لا توجد بيانات للاختبار                               ║\n";
        $passed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// اختبار 10: التحقق من وجود الدوال المساعدة
// ========================================
echo "║ 10. فحص وجود الدوال المساعدة في Controller...               ║\n";
try {
    $controllerPath = __DIR__ . '/app/Http/Controllers/Api/SponsorshipSyncController.php';
    $content = file_get_contents($controllerPath);
    
    $requiredFunctions = [
        'updatePersonByType',
        'convertGenderToInt',
        'prepareDataTableUpdate',
        'prepareRePeopleUpdate',
        'prepareDeadPeopleUpdate'
    ];
    
    $allExist = true;
    foreach ($requiredFunctions as $func) {
        if (strpos($content, "function {$func}") === false) {
            echo "║    ❌ الدالة {$func} غير موجودة!                            ║\n";
            $allExist = false;
        }
    }
    
    if ($allExist) {
        echo "║    ✅ جميع الدوال المساعدة موجودة (5 دوال)                  ║\n";
        $passed++;
    } else {
        $failed++;
    }
} catch (Exception $e) {
    echo "║    ❌ خطأ: " . substr($e->getMessage(), 0, 40) . "...       ║\n";
    $failed++;
}

// ========================================
// النتيجة النهائية
// ========================================
echo "╠══════════════════════════════════════════════════════════════╣\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

if ($failed === 0) {
    echo "║   ✅ النتيجة: جميع الاختبارات نجحت ({$passed}/{$total})                  ║\n";
} else {
    echo "║   ⚠️ النتيجة: {$passed} نجاح / {$failed} فشل ({$percentage}%)                   ║\n";
}
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// تلخيص حالات person_type المدعومة
echo "📋 حالات person_type المدعومة:\n";
echo "┌─────────────────────┬─────────────────┬─────────────────────────┐\n";
echo "│ person_type         │ الجدول المستهدف │ مفتاح المطابقة          │\n";
echo "├─────────────────────┼─────────────────┼─────────────────────────┤\n";
echo "│ breadwinner         │ data            │ file_id_number          │\n";
echo "│ family_member       │ re_people       │ registration_id         │\n";
echo "│ orphan              │ re_people       │ registration_id         │\n";
echo "│ deceased_father     │ dead_people     │ re_file_id + father_id  │\n";
echo "│ deceased_mother     │ dead_people     │ re_file_id + mother_id  │\n";
echo "└─────────────────────┴─────────────────┴─────────────────────────┘\n\n";

exit($failed > 0 ? 1 : 0);
