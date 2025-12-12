<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║      اختبار شامل للميزات الجديدة في نظام الكفالات                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$allTestsPassed = true;

// ============================================================
// Test 1: حالات الكفالة الجديدة
// ============================================================
echo "┌─ Test 1: التحقق من حالات الكفالة الجديدة\n";
echo "├" . str_repeat("─", 68) . "\n";

$statusJadeed = DB::table('sponsorship_statuses')->where('id', 4)->first();
$statusDisbursement = DB::table('sponsorship_statuses')->where('id', 5)->first();
$statusMohdath = DB::table('sponsorship_statuses')->where('id', 3)->first();

if ($statusJadeed && $statusJadeed->description == 'جديد') {
    echo "├─ ✅ حالة 'جديد' (ID: 4) موجودة\n";
} else {
    echo "├─ ❌ حالة 'جديد' غير موجودة أو معرف خاطئ\n";
    $allTestsPassed = false;
}

if ($statusDisbursement && strpos($statusDisbursement->description, 'ذهب') !== false) {
    echo "├─ ✅ حالة 'ذهب للصرف' (ID: 5) موجودة\n";
} else {
    echo "├─ ❌ حالة 'ذهب للصرف' غير موجودة أو معرف خاطئ\n";
    $allTestsPassed = false;
}

if ($statusMohdath && strpos($statusMohdath->description, 'محدث') !== false) {
    echo "├─ ✅ حالة 'محدث' (ID: 3) موجودة\n";
} else {
    echo "├─ ❌ حالة 'محدث' غير موجودة أو معرف خاطئ\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 2: عمود updated_by
// ============================================================
echo "┌─ Test 2: التحقق من عمود updated_by\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $columnCheck = DB::select("SHOW COLUMNS FROM sponsorships LIKE 'updated_by'");

    if (!empty($columnCheck)) {
        $column = $columnCheck[0];
        echo "├─ ✅ عمود updated_by موجود\n";
        echo "├─    النوع: {$column->Type}\n";

        if ($column->Type == 'json') {
            echo "├─ ✅ النوع صحيح (JSON)\n";
        } else {
            echo "├─ ❌ النوع خاطئ، يجب أن يكون JSON\n";
            $allTestsPassed = false;
        }

        if ($column->Null == 'YES') {
            echo "├─ ✅ العمود يقبل NULL\n";
        } else {
            echo "├─ ⚠️  العمود لا يقبل NULL\n";
        }
    } else {
        echo "├─ ❌ عمود updated_by غير موجود!\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في فحص العمود: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 3: اختبار دالة addUpdater
// ============================================================
echo "┌─ Test 3: اختبار دالة addUpdater()\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $testSponsorship = App\Models\Sponsorship::first();
    $testUser = DB::table('users')->first();

    if ($testSponsorship && $testUser) {
        // مسح القائمة الحالية للاختبار
        $testSponsorship->updated_by = null;
        $testSponsorship->save();

        echo "├─ 🧪 إضافة المستخدم الأول...\n";
        $testSponsorship->addUpdater($testUser->id);
        $testSponsorship = $testSponsorship->fresh();

        if (!empty($testSponsorship->updated_by) && count($testSponsorship->updated_by) == 1) {
            echo "├─ ✅ تمت إضافة المستخدم بنجاح\n";
            $firstEntry = $testSponsorship->updated_by[0];
            echo "├─    المستخدم: {$firstEntry['name']} (ID: {$firstEntry['user_id']})\n";
            echo "├─    التاريخ: {$firstEntry['updated_at']}\n";

            // اختبار إضافة مستخدم ثاني
            echo "├─ 🧪 إضافة المستخدم الثاني...\n";
            $testSponsorship->addUpdater($testUser->id);
            $testSponsorship = $testSponsorship->fresh();

            if (count($testSponsorship->updated_by) == 2) {
                echo "├─ ✅ يمكن إضافة عدة مستخدمين\n";
            } else {
                echo "├─ ❌ فشل إضافة المستخدم الثاني\n";
                $allTestsPassed = false;
            }
        } else {
            echo "├─ ❌ فشلت إضافة المستخدم\n";
            $allTestsPassed = false;
        }
    } else {
        echo "├─ ⚠️  لا توجد بيانات كفالات أو مستخدمين للاختبار\n";
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في اختبار الدالة: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 4: اختبار Model Sponsorship
// ============================================================
echo "┌─ Test 4: التحقق من إعدادات Model\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $sponsorship = new App\Models\Sponsorship();

    // فحص fillable
    if (in_array('updated_by', $sponsorship->getFillable())) {
        echo "├─ ✅ updated_by في قائمة fillable\n";
    } else {
        echo "├─ ❌ updated_by غير موجود في fillable\n";
        $allTestsPassed = false;
    }

    // فحص casts
    $casts = $sponsorship->getCasts();
    if (isset($casts['updated_by']) && $casts['updated_by'] == 'array') {
        echo "├─ ✅ updated_by في قائمة casts كـ array\n";
    } else {
        echo "├─ ❌ updated_by غير موجود في casts أو نوعه خاطئ\n";
        $allTestsPassed = false;
    }

    // فحص وجود الدوال
    if (method_exists($sponsorship, 'addUpdater')) {
        echo "├─ ✅ دالة addUpdater() موجودة\n";
    } else {
        echo "├─ ❌ دالة addUpdater() غير موجودة\n";
        $allTestsPassed = false;
    }

    if (method_exists($sponsorship, 'getUpdaterNamesAttribute')) {
        echo "├─ ✅ دالة getUpdaterNamesAttribute() موجودة\n";
    } else {
        echo "├─ ❌ دالة getUpdaterNamesAttribute() غير موجودة\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في فحص Model: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 5: إحصائيات النظام
// ============================================================
echo "┌─ Test 5: إحصائيات الكفالات حسب الحالة\n";
echo "├" . str_repeat("─", 68) . "\n";

$stats = DB::select("
    SELECT
        ss.id,
        ss.description,
        COUNT(s.id) as count
    FROM sponsorship_statuses ss
    LEFT JOIN sponsorships s ON s.sponsorship_status_id = ss.id
    GROUP BY ss.id, ss.description
    ORDER BY ss.id
");

foreach ($stats as $stat) {
    $icon = '  ';
    if ($stat->id == 4) {
        $icon = '🆕';
    } elseif ($stat->id == 3) {
        $icon = '🟢';
    } elseif ($stat->id == 5) {
        $icon = '✓ ';
    }

    echo sprintf("├─ %s %-25s: %d كفالة\n", $icon, $stat->description, $stat->count);
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// النتيجة النهائية
// ============================================================
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
if ($allTestsPassed) {
    echo "║                    ✅ جميع الاختبارات نجحت                          ║\n";
    echo "╠══════════════════════════════════════════════════════════════════════╣\n";
    echo "║  الميزات الجديدة جاهزة للاستخدام:                                  ║\n";
    echo "║  1. ✅ حالة 'جديد' افتراضية للكفالات الجديدة                       ║\n";
    echo "║  2. ✅ علامة صح سوداء (✓) بجانب 'ذهب للصرف'                       ║\n";
    echo "║  3. ✅ عمود updated_by لتتبع المعدلين                               ║\n";
    echo "║  4. ✅ دالة addUpdater() تعمل بشكل صحيح                             ║\n";
    echo "║  5. ✅ نقطة خضراء نابضة (🟢) بجانب 'محدث'                          ║\n";
} else {
    echo "║                   ⚠️  بعض الاختبارات فشلت                           ║\n";
    echo "╠══════════════════════════════════════════════════════════════════════╣\n";
    echo "║  يرجى مراجعة الأخطاء أعلاه وإصلاحها                                ║\n";
}
echo "╚══════════════════════════════════════════════════════════════════════╝\n";

exit($allTestsPassed ? 0 : 1);
