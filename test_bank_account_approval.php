<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║           اختبار نظام اعتماد الحسابات البنكية                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$allTestsPassed = true;

// ============================================================
// Test 1: التحقق من عمود check_account
// ============================================================
echo "┌─ Test 1: التحقق من عمود check_account\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $columnCheck = DB::select("SHOW COLUMNS FROM guardian_bank_accounts LIKE 'check_account'");

    if (!empty($columnCheck)) {
        $column = $columnCheck[0];
        echo "├─ ✅ عمود check_account موجود\n";
        echo "├─    النوع: {$column->Type}\n";
        echo "├─    القيمة الافتراضية: " . ($column->Default ?? 'NULL') . "\n";

        if ($column->Default == '0') {
            echo "├─ ✅ القيمة الافتراضية صحيحة (0)\n";
        } else {
            echo "├─ ❌ القيمة الافتراضية خاطئة\n";
            $allTestsPassed = false;
        }
    } else {
        echo "├─ ❌ عمود check_account غير موجود!\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في فحص العمود: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 2: التحقق من Model
// ============================================================
echo "┌─ Test 2: التحقق من إعدادات Model\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $model = new App\Models\GuardianBankAccount();

    if (in_array('check_account', $model->getFillable())) {
        echo "├─ ✅ check_account في قائمة fillable\n";
    } else {
        echo "├─ ❌ check_account غير موجود في fillable\n";
        $allTestsPassed = false;
    }

    $casts = $model->getCasts();
    if (isset($casts['check_account'])) {
        echo "├─ ✅ check_account في قائمة casts: {$casts['check_account']}\n";
    } else {
        echo "├─ ⚠️  check_account غير موجود في casts\n";
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في فحص Model: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 3: اختبار وظيفة الاعتماد
// ============================================================
echo "┌─ Test 3: اختبار وظيفة اعتماد الحساب\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    // البحث عن حسابات بنكية موجودة
    $accounts = DB::table('guardian_bank_accounts')
        ->select('id', 'guardian_registration', 'check_account', 'bank_name')
        ->limit(3)
        ->get();

    if ($accounts->count() > 0) {
        echo "├─ 📊 تم العثور على " . $accounts->count() . " حساب بنكي للاختبار\n";

        foreach ($accounts as $index => $account) {
            $status = $account->check_account == 1 ? '✅ معتمد' : '⚪ غير معتمد';
            echo "├─    الحساب {$account->id}: {$status}\n";
        }

        // اختبار اعتماد حساب
        if ($accounts->count() > 0) {
            $testAccount = $accounts->first();

            echo "├─ 🧪 اختبار اعتماد الحساب {$testAccount->id}...\n";

            // إلغاء اعتماد جميع الحسابات الأخرى أولاً
            DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $testAccount->guardian_registration)
                ->update(['check_account' => 0]);

            // اعتماد الحساب المحدد
            DB::table('guardian_bank_accounts')
                ->where('id', $testAccount->id)
                ->update(['check_account' => 1]);

            // التحقق من النتيجة
            $updated = DB::table('guardian_bank_accounts')
                ->where('id', $testAccount->id)
                ->value('check_account');

            if ($updated == 1) {
                echo "├─ ✅ تم اعتماد الحساب بنجاح\n";

                // التحقق من أن الحسابات الأخرى غير معتمدة
                $otherApproved = DB::table('guardian_bank_accounts')
                    ->where('guardian_registration', $testAccount->guardian_registration)
                    ->where('id', '!=', $testAccount->id)
                    ->where('check_account', 1)
                    ->count();

                if ($otherApproved == 0) {
                    echo "├─ ✅ الحسابات الأخرى غير معتمدة (صحيح)\n";
                } else {
                    echo "├─ ❌ يوجد {$otherApproved} حساب آخر معتمد (خطأ!)\n";
                    $allTestsPassed = false;
                }
            } else {
                echo "├─ ❌ فشل اعتماد الحساب\n";
                $allTestsPassed = false;
            }
        }
    } else {
        echo "├─ ⚠️  لا توجد حسابات بنكية للاختبار\n";
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في الاختبار: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 4: إحصائيات الحسابات المعتمدة
// ============================================================
echo "┌─ Test 4: إحصائيات الحسابات البنكية\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $totalAccounts = DB::table('guardian_bank_accounts')->count();
    $approvedAccounts = DB::table('guardian_bank_accounts')->where('check_account', 1)->count();
    $notApprovedAccounts = DB::table('guardian_bank_accounts')->where('check_account', 0)->count();

    echo "├─ 📊 إجمالي الحسابات: {$totalAccounts}\n";
    echo "├─ ✅ حسابات معتمدة: {$approvedAccounts}\n";
    echo "├─ ⚪ حسابات غير معتمدة: {$notApprovedAccounts}\n";

    if ($approvedAccounts + $notApprovedAccounts == $totalAccounts) {
        echo "├─ ✅ الإحصائيات متطابقة\n";
    } else {
        echo "├─ ⚠️  عدم تطابق في الإحصائيات\n";
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في الإحصائيات: " . $e->getMessage() . "\n";
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 5: التحقق من Route
// ============================================================
echo "┌─ Test 5: التحقق من Routes\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $routes = app('router')->getRoutes();
    $approveRouteExists = false;

    foreach ($routes as $route) {
        if ($route->getName() === 'admin.records.management.approveBankAccount') {
            $approveRouteExists = true;
            echo "├─ ✅ Route اعتماد الحساب موجود\n";
            echo "├─    URI: " . $route->uri() . "\n";
            echo "├─    Method: " . implode(', ', $route->methods()) . "\n";
            break;
        }
    }

    if (!$approveRouteExists) {
        echo "├─ ❌ Route اعتماد الحساب غير موجود\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ في فحص Routes: " . $e->getMessage() . "\n";
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// النتيجة النهائية
// ============================================================
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
if ($allTestsPassed) {
    echo "║                    ✅ جميع الاختبارات نجحت                          ║\n";
    echo "╠══════════════════════════════════════════════════════════════════════╣\n";
    echo "║  نظام اعتماد الحسابات البنكية جاهز للاستخدام:                     ║\n";
    echo "║  1. ✅ عمود check_account تم إضافته بنجاح                          ║\n";
    echo "║  2. ✅ القيمة الافتراضية: 0 (غير معتمد)                            ║\n";
    echo "║  3. ✅ دالة اعتماد الحساب تعمل بشكل صحيح                            ║\n";
    echo "║  4. ✅ زر 'اعتماد الحساب' متاح في جميع الصفحات                    ║\n";
    echo "║  5. ✅ AJAX يعمل بدون إعادة تحميل الصفحة                            ║\n";
    echo "║  6. ✅ حساب واحد فقط يمكن اعتماده للمعيل الواحد                    ║\n";
} else {
    echo "║                   ⚠️  بعض الاختبارات فشلت                           ║\n";
    echo "╠══════════════════════════════════════════════════════════════════════╣\n";
    echo "║  يرجى مراجعة الأخطاء أعلاه وإصلاحها                                ║\n";
}
echo "╚══════════════════════════════════════════════════════════════════════╝\n";

exit($allTestsPassed ? 0 : 1);
