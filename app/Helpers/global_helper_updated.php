<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

if (!function_exists('generateUniqueReservedCode')) {
    /**
     * Generate unique 6-digit code without reserved_codes table for shared hosting
     * يولد رقم فريد من 6 أرقام بدون استخدام جدول reserved_codes للاستضافة المشتركة
     */
    function generateUniqueReservedCode(string $table, string $column, ?string $sessionId = null): ?string
    {
        return DB::transaction(function () use ($table, $column, $sessionId) {
            // جلب أكبر رقم رقمي فقط من الجدول الأساسي
            $maxMain = DB::table($table)
                ->select(DB::raw("MAX(CAST($column as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH($column) = 6 AND $column REGEXP '^[0-9]+$'")
                ->value('max_code');

            // بدء من الرقم التالي للأكبر
            $next = (int)$maxMain + 1;
            $attempts = 0;
            $maxAttempts = 50;

            while ($attempts < $maxAttempts) {
                $code = str_pad($next, 6, '0', STR_PAD_LEFT);

                // فحص عدم وجود الرقم في الجدول
                $exists = DB::table($table)->where($column, $code)->exists();

                if (!$exists) {
                    Log::info("✅ تم توليد رقم فريد: {$code} للجدول {$table}");
                    return $code;
                }

                $next++;
                $attempts++;
            }

            // في حالة فشل التوليد التسلسلي، استخدم رقم عشوائي
            for ($i = 0; $i < 20; $i++) {
                $randomCode = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                $exists = DB::table($table)->where($column, $randomCode)->exists();

                if (!$exists) {
                    Log::warning("⚠️ تم استخدام رقم عشوائي: {$randomCode} بعد فشل التوليد التسلسلي");
                    return $randomCode;
                }
            }

            Log::error("❌ فشل في توليد رقم فريد للجدول {$table}");
            return null;
        });
    }
}

if (!function_exists('markCodeAsUsed')) {
    /**
     * Mark code as used - simplified for shared hosting
     * وضع علامة على الرقم كمستخدم - مبسط للاستضافة المشتركة
     */
    function markCodeAsUsed($code)
    {
        // لا حاجة لعمل شيء عند عدم وجود reserved_codes
        // الرقم محمي بالفعل في الجدول الرئيسي
        Log::info("✅ تم وضع علامة على الرقم {$code} كمستخدم");
        return true;
    }
}

if (!function_exists('generateExcCode')) {
    /**
     * Generate unique exception code without reserved_codes table
     * يولد رقم استثناء فريد بدون استخدام جدول reserved_codes
     */
    function generateExcCode($sessionId = null)
    {
        return DB::transaction(function () use ($sessionId) {
            // جلب أكبر رقم من جدول people_data
            try {
                $maxExisting = DB::table('people_data')
                    ->select(DB::raw("MAX(CAST(SUBSTRING(EXC_ID, 5) as UNSIGNED)) as max_code"))
                    ->whereRaw("EXC_ID LIKE 'exc_%' AND EXC_ID REGEXP '^exc_[0-9]+$'")
                    ->value('max_code');

                $next = ((int)$maxExisting) + 1;
                $attempts = 0;
                $maxAttempts = 30;

                while ($attempts < $maxAttempts) {
                    $code = "exc_" . $next;

                    // فحص عدم وجود الرقم
                    $exists = DB::table('people_data')->where('EXC_ID', $code)->exists();

                    if (!$exists) {
                        Log::info("✅ تم توليد رقم استثناء: {$code}");
                        return $code;
                    }

                    $next++;
                    $attempts++;
                }
            } catch (Exception $e) {
                Log::warning("⚠️ خطأ في توليد رقم استثناء تسلسلي: " . $e->getMessage());
            }

            // في حالة فشل التوليد التسلسلي، استخدم رقم عشوائي
            for ($i = 0; $i < 20; $i++) {
                $randomPart = rand(1000000, 9999999);
                $code = "exc_" . $randomPart;

                try {
                    $exists = DB::table('people_data')->where('EXC_ID', $code)->exists();
                    if (!$exists) {
                        Log::info("✅ تم توليد رقم استثناء عشوائي: {$code}");
                        return $code;
                    }
                } catch (Exception $e) {
                    // في حالة عدم وجود الجدول أو خطأ في الاستعلام
                    Log::info("✅ تم توليد رقم استثناء (بدون فحص): {$code}");
                    return $code;
                }
            }

            // في حالة فشل كل شيء
            $fallbackCode = "exc_" . time() . rand(100, 999);
            Log::warning("⚠️ تم استخدام رقم احتياطي: {$fallbackCode}");
            return $fallbackCode;
        });
    }
}

if (!function_exists('generateUniqueCode')) {
    /**
     * Alias for generateUniqueReservedCode for backward compatibility
     * اسم مستعار للتوافق مع الكود القديم
     */
    function generateUniqueCode($table = null, $column = null, $sessionId = null)
    {
        if ($table && $column) {
            return generateUniqueReservedCode($table, $column, $sessionId);
        }

        // توليد رقم عشوائي عام
        $attempts = 0;
        while ($attempts < 20) {
            $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            // لا يمكن فحص التفرد بدون تحديد جدول، لذا نعتمد على العشوائية
            return $code;
        }

        return substr(str_replace('.', '', microtime(true) * 1000), -6);
    }
}
