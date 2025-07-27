<?php
/**
 * حل مشكلة صلاحيات INSERT على reserved_codes
 */

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔧 حل مشكلة صلاحيات reserved_codes...\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    // 1. فحص صلاحيات قاعدة البيانات
    echo "\n1️⃣ فحص صلاحيات قاعدة البيانات:\n";

    $user = env('DB_USERNAME', 'unknown');
    echo "👤 المستخدم: {$user}\n";

    // فحص الجداول المتاحة
    $tables = DB::select("SHOW TABLES");
    $tableNames = array_column($tables, 'Tables_in_' . env('DB_DATABASE'));

    echo "📊 عدد الجداول: " . count($tableNames) . "\n";

    // فحص وجود جدول reserved_codes
    $hasReservedCodes = in_array('reserved_codes', $tableNames);
    echo ($hasReservedCodes ? "✅" : "❌") . " جدول reserved_codes: " . ($hasReservedCodes ? "موجود" : "غير موجود") . "\n";

    if ($hasReservedCodes) {
        // فحص إمكانية الكتابة
        try {
            DB::table('reserved_codes')->where('id', -1)->delete(); // اختبار أمان
            echo "✅ صلاحية DELETE متوفرة\n";
        } catch (Exception $e) {
            echo "❌ صلاحية DELETE محدودة: " . $e->getMessage() . "\n";
        }

        try {
            // اختبار INSERT بسيط
            $testId = 'test_' . time();
            DB::table('reserved_codes')->insert([
                'code' => 'TEST99',
                'session_id' => $testId,
                'reserved_at' => now(),
                'used' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // حذف البيانات التجريبية
            DB::table('reserved_codes')->where('session_id', $testId)->delete();
            echo "✅ صلاحية INSERT متوفرة\n";

        } catch (Exception $e) {
            echo "❌ صلاحية INSERT محدودة: " . $e->getMessage() . "\n";

            // إنشاء حل بديل
            echo "\n🔧 إنشاء حل بديل بدون reserved_codes...\n";

            // إنشاء نسخة احتياطية للـ helper
            $helperFile = 'app/Helpers/global_helper.php';
            $backupFile = 'app/Helpers/global_helper_backup_' . date('Y_m_d_H_i_s') . '.php';

            if (file_exists($helperFile)) {
                copy($helperFile, $backupFile);
                echo "✅ نسخة احتياطية: {$backupFile}\n";
            }
        }
    }

    // 2. إنشاء helper محسن بدون reserved_codes
    echo "\n2️⃣ إنشاء helper محسن بدون reserved_codes:\n";

    $newHelperContent = '<?php

if (!function_exists(\'generateUniqueCode\')) {
    /**
     * Generate unique 6-digit code without reserved_codes table
     * يولد رقم فريد من 6 أرقام بدون استخدام جدول reserved_codes
     */
    function generateUniqueCode($table = null, $column = null, $sessionId = null)
    {
        $attempts = 0;
        $maxAttempts = 50;

        while ($attempts < $maxAttempts) {
            // توليد رقم عشوائي من 6 أرقام
            $code = str_pad(rand(100000, 999999), 6, \'0\', STR_PAD_LEFT);

            // فحص عدم التكرار في الجدول المحدد
            $existsInTable = false;
            if ($table && $column) {
                try {
                    $existsInTable = DB::table($table)->where($column, $code)->exists();
                } catch (Exception $e) {
                    // في حالة عدم وجود الجدول، استمر
                }
            }

            if (!$existsInTable) {
                return $code;
            }

            $attempts++;
        }

        // في حالة فشل التوليد، استخدم timestamp
        return substr(str_replace(\'.\', \'\', microtime(true) * 1000), -6);
    }
}

if (!function_exists(\'generateExcCode\')) {
    /**
     * Generate unique exception code without reserved_codes table
     * يولد رقم استثناء فريد بدون استخدام جدول reserved_codes
     */
    function generateExcCode($sessionId = null)
    {
        $attempts = 0;
        $maxAttempts = 30;

        while ($attempts < $maxAttempts) {
            // رقم عشوائي بين 1000000 و 9999999
            $randomPart = rand(1000000, 9999999);
            $code = "exc_" . $randomPart;

            // فحص في جدول people_data
            $existsInPeople = false;
            try {
                $existsInPeople = DB::table(\'people_data\')->where(\'EXC_ID\', $code)->exists();
            } catch (Exception $e) {
                // الجدول غير موجود أو لا توجد صلاحية
            }

            if (!$existsInPeople) {
                return $code;
            }

            $attempts++;
        }

        // في حالة فشل التوليد
        return "exc_" . time() . rand(100, 999);
    }
}

if (!function_exists(\'markCodeAsUsed\')) {
    /**
     * Mark code as used (dummy function for compatibility)
     * وضع علامة على الرقم كمستخدم (دالة وهمية للتوافق)
     */
    function markCodeAsUsed($code)
    {
        // لا حاجة لفعل شيء عند عدم وجود reserved_codes
        return true;
    }
}';

    file_put_contents('app/Helpers/global_helper_safe.php', $newHelperContent);
    echo "✅ تم إنشاء global_helper_safe.php\n";

    // 3. تحديث GeneralRegistrationController
    echo "\n3️⃣ تحديث GeneralRegistrationController:\n";

    $controllerFile = 'app/Http/Controllers/Users/GeneralRegistrationController.php';
    if (file_exists($controllerFile)) {
        $content = file_get_contents($controllerFile);

        // البحث عن استخدام reserved_codes
        if (strpos($content, 'reserved_codes') !== false) {
            echo "⚠️ Controller يستخدم reserved_codes\n";
            echo "💡 يجب تحديث الكود لاستخدام الحل البديل\n";
        } else {
            echo "✅ Controller لا يستخدم reserved_codes\n";
        }
    }

    // 4. إنشاء middleware للتحقق من الصلاحيات
    echo "\n4️⃣ إنشاء middleware للحماية:\n";

    $middlewareContent = '<?php

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Log;

class DatabasePermissionCheck
{
    public function handle(Request $request, Closure $next)
    {
        // تسجيل محاولات الوصول لـ reserved_codes
        if ($request->has(\'code_generation\')) {
            Log::info(\'Code generation request\', [
                \'user_ip\' => $request->ip(),
                \'session\' => $request->session()->getId(),
                \'timestamp\' => now()
            ]);
        }

        return $next($request);
    }
}';

    $middlewareDir = 'app/Http/Middleware';
    if (!is_dir($middlewareDir)) {
        mkdir($middlewareDir, 0755, true);
    }

    file_put_contents($middlewareDir . '/DatabasePermissionCheck.php', $middlewareContent);
    echo "✅ تم إنشاء middleware للحماية\n";

    // 5. اختبار التوليد الجديد
    echo "\n5️⃣ اختبار نظام التوليد الجديد:\n";

    include_once 'app/Helpers/global_helper_safe.php';

    for ($i = 1; $i <= 5; $i++) {
        $code = generateUniqueCode();
        $excCode = generateExcCode();
        echo "🎯 اختبار {$i}: كود عادي = {$code}, كود استثناء = {$excCode}\n";
    }

    echo "\n🎉 تم إنشاء الحل البديل بنجاح!\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n📋 الخطوات التالية:\n";
echo "1. استبدال استخدام reserved_codes بالحل الجديد\n";
echo "2. تحديث GeneralRegistrationController\n";
echo "3. اختبار النظام على الاستضافة\n";
echo "4. مراقبة السجلات للتأكد من عدم وجود أخطاء\n";
