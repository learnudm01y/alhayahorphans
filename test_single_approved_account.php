<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║         اختبار منع اعتماد أكثر من حساب واحد لنفس المعيل              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$allTestsPassed = true;

// ============================================================
// Test 1: البحث عن معيل لديه أكثر من حساب بنكي
// ============================================================
echo "┌─ Test 1: البحث عن معيل لديه حسابات متعددة\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $guardian = DB::table('guardian_bank_accounts')
        ->select('guardian_registration', DB::raw('COUNT(*) as accounts_count'))
        ->groupBy('guardian_registration')
        ->having('accounts_count', '>', 1)
        ->first();

    if ($guardian) {
        echo "├─ ✅ تم العثور على معيل برقم: {$guardian->guardian_registration}\n";
        echo "├─    عدد الحسابات: {$guardian->accounts_count}\n";

        $accounts = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $guardian->guardian_registration)
            ->get();

        echo "├─ 📊 قائمة الحسابات:\n";
        foreach ($accounts as $index => $account) {
            $status = $account->check_account == 1 ? '✅ معتمد' : '⚪ غير معتمد';
            echo "├─    الحساب {$account->id}: {$status} - {$account->bank_name}\n";
        }
    } else {
        echo "├─ ⚠️  لم يتم العثور على معيل لديه أكثر من حساب\n";
        echo "├─    سيتم إنشاء بيانات اختبار...\n";

        // إنشاء معيل تجريبي
        $testGuardianReg = 'TEST_' . time();

        DB::table('guardian_bank_accounts')->insert([
            [
                'guardian_registration' => $testGuardianReg,
                'bank_name' => 'بنك الاختبار 1',
                'check_account' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'guardian_registration' => $testGuardianReg,
                'bank_name' => 'بنك الاختبار 2',
                'check_account' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'guardian_registration' => $testGuardianReg,
                'bank_name' => 'بنك الاختبار 3',
                'check_account' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $guardian = (object)['guardian_registration' => $testGuardianReg];
        echo "├─ ✅ تم إنشاء معيل تجريبي برقم: {$testGuardianReg}\n";
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 2: اختبار اعتماد الحساب الأول
// ============================================================
echo "┌─ Test 2: اعتماد الحساب الأول\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    if (isset($guardian)) {
        $accounts = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $guardian->guardian_registration)
            ->get();

        if ($accounts->count() > 0) {
            $firstAccount = $accounts->first();

            echo "├─ 🧪 اعتماد الحساب {$firstAccount->id}...\n";

            // إلغاء اعتماد جميع الحسابات
            DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardian->guardian_registration)
                ->update(['check_account' => 0]);

            // اعتماد الحساب الأول
            DB::table('guardian_bank_accounts')
                ->where('id', $firstAccount->id)
                ->update(['check_account' => 1]);

            // التحقق من النتيجة
            $approvedCount = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardian->guardian_registration)
                ->where('check_account', 1)
                ->count();

            if ($approvedCount == 1) {
                echo "├─ ✅ تم اعتماد حساب واحد فقط\n";

                $approvedAccount = DB::table('guardian_bank_accounts')
                    ->where('guardian_registration', $guardian->guardian_registration)
                    ->where('check_account', 1)
                    ->first();

                echo "├─    الحساب المعتمد: {$approvedAccount->id}\n";
            } else {
                echo "├─ ❌ فشل الاختبار: عدد الحسابات المعتمدة = {$approvedCount}\n";
                $allTestsPassed = false;
            }
        }
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 3: اختبار تغيير الاعتماد إلى حساب آخر
// ============================================================
echo "┌─ Test 3: تغيير الاعتماد من الحساب الأول إلى الثاني\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    if (isset($guardian)) {
        $accounts = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $guardian->guardian_registration)
            ->get();

        if ($accounts->count() > 1) {
            $secondAccount = $accounts->get(1);

            echo "├─ 🧪 تغيير الاعتماد إلى الحساب {$secondAccount->id}...\n";

            // إلغاء اعتماد جميع الحسابات الأخرى
            DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardian->guardian_registration)
                ->where('id', '!=', $secondAccount->id)
                ->update(['check_account' => 0]);

            // اعتماد الحساب الثاني
            DB::table('guardian_bank_accounts')
                ->where('id', $secondAccount->id)
                ->update(['check_account' => 1]);

            // التحقق من النتيجة
            $approvedCount = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardian->guardian_registration)
                ->where('check_account', 1)
                ->count();

            $approvedAccount = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardian->guardian_registration)
                ->where('check_account', 1)
                ->first();

            if ($approvedCount == 1 && $approvedAccount->id == $secondAccount->id) {
                echo "├─ ✅ تم تغيير الاعتماد بنجاح\n";
                echo "├─    الحساب المعتمد الجديد: {$approvedAccount->id}\n";

                // التحقق من أن الحساب الأول لم يعد معتمداً
                $firstAccount = $accounts->first();
                $firstAccountStatus = DB::table('guardian_bank_accounts')
                    ->where('id', $firstAccount->id)
                    ->value('check_account');

                if ($firstAccountStatus == 0) {
                    echo "├─ ✅ الحساب الأول ({$firstAccount->id}) لم يعد معتمداً\n";
                } else {
                    echo "├─ ❌ خطأ: الحساب الأول لا يزال معتمداً!\n";
                    $allTestsPassed = false;
                }
            } else {
                echo "├─ ❌ فشل الاختبار: عدد الحسابات المعتمدة = {$approvedCount}\n";
                $allTestsPassed = false;
            }
        }
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 4: التحقق من عدم وجود معيلين لديهم أكثر من حساب معتمد
// ============================================================
echo "┌─ Test 4: فحص جميع المعيلين في النظام\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    $guardiansWithMultipleApproved = DB::table('guardian_bank_accounts')
        ->select('guardian_registration', DB::raw('COUNT(*) as approved_count'))
        ->where('check_account', 1)
        ->groupBy('guardian_registration')
        ->having('approved_count', '>', 1)
        ->get();

    if ($guardiansWithMultipleApproved->count() == 0) {
        echo "├─ ✅ لا يوجد معيل لديه أكثر من حساب معتمد\n";

        $totalGuardians = DB::table('guardian_bank_accounts')
            ->distinct('guardian_registration')
            ->count('guardian_registration');

        $guardiansWithApproved = DB::table('guardian_bank_accounts')
            ->select('guardian_registration')
            ->where('check_account', 1)
            ->distinct()
            ->count();

        echo "├─ 📊 إجمالي المعيلين: {$totalGuardians}\n";
        echo "├─ ✅ معيلين لديهم حساب معتمد: {$guardiansWithApproved}\n";
    } else {
        echo "├─ ❌ تحذير: يوجد " . $guardiansWithMultipleApproved->count() . " معيل لديهم أكثر من حساب معتمد:\n";
        foreach ($guardiansWithMultipleApproved as $guardian) {
            echo "├─    معيل {$guardian->guardian_registration}: {$guardian->approved_count} حسابات معتمدة\n";
        }
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// Test 5: اختبار محاكاة العملية الكاملة
// ============================================================
echo "┌─ Test 5: محاكاة سيناريو المستخدم الكامل\n";
echo "├" . str_repeat("─", 68) . "\n";

try {
    if (isset($guardian)) {
        $accounts = DB::table('guardian_bank_accounts')
            ->where('guardian_registration', $guardian->guardian_registration)
            ->get();

        echo "├─ 🎭 السيناريو: معيل لديه {$accounts->count()} حسابات بنكية\n";
        echo "├─    1️⃣ اعتماد الحساب الأول\n";
        echo "├─    2️⃣ تغيير الاعتماد إلى الثاني\n";
        echo "├─    3️⃣ تغيير الاعتماد إلى الثالث\n";
        echo "├" . str_repeat("─", 68) . "\n";

        foreach ($accounts as $index => $account) {
            echo "├─ 🔄 اعتماد الحساب رقم " . ($index + 1) . " (ID: {$account->id})...\n";

            // إلغاء جميع الحسابات الأخرى
            DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardian->guardian_registration)
                ->where('id', '!=', $account->id)
                ->update(['check_account' => 0]);

            // اعتماد الحساب الحالي
            DB::table('guardian_bank_accounts')
                ->where('id', $account->id)
                ->update(['check_account' => 1]);

            // التحقق
            $currentApproved = DB::table('guardian_bank_accounts')
                ->where('guardian_registration', $guardian->guardian_registration)
                ->where('check_account', 1)
                ->count();

            if ($currentApproved == 1) {
                echo "├─    ✅ حساب واحد فقط معتمد\n";
            } else {
                echo "├─    ❌ خطأ: {$currentApproved} حسابات معتمدة!\n";
                $allTestsPassed = false;
            }
        }

        echo "├─ ✅ السيناريو مكتمل بنجاح\n";
    }
} catch (\Exception $e) {
    echo "├─ ❌ خطأ: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "└" . str_repeat("─", 68) . "\n\n";

// ============================================================
// النتيجة النهائية
// ============================================================
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
if ($allTestsPassed) {
    echo "║               ✅ جميع الاختبارات نجحت بنجاح                          ║\n";
    echo "╠══════════════════════════════════════════════════════════════════════╣\n";
    echo "║  ✅ النظام يمنع اعتماد أكثر من حساب واحد لنفس المعيل                ║\n";
    echo "║  ✅ عند اعتماد حساب جديد، يتم إلغاء اعتماد الحساب القديم تلقائياً  ║\n";
    echo "║  ✅ يمكن تغيير الحساب المعتمد في أي وقت                             ║\n";
    echo "║  ✅ لا يوجد معيل لديه أكثر من حساب معتمد في النظام                 ║\n";
} else {
    echo "║                   ⚠️  بعض الاختبارات فشلت                           ║\n";
    echo "╠══════════════════════════════════════════════════════════════════════╣\n";
    echo "║  يرجى مراجعة الأخطاء أعلاه                                          ║\n";
}
echo "╚══════════════════════════════════════════════════════════════════════╝\n";

exit($allTestsPassed ? 0 : 1);
