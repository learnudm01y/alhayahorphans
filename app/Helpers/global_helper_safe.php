<?php

if (!function_exists('generateUniqueCode')) {
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
            $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            
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
        return substr(str_replace('.', '', microtime(true) * 1000), -6);
    }
}

if (!function_exists('generateExcCode')) {
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
                $existsInPeople = DB::table('people_data')->where('EXC_ID', $code)->exists();
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

if (!function_exists('markCodeAsUsed')) {
    /**
     * Mark code as used (dummy function for compatibility)
     * وضع علامة على الرقم كمستخدم (دالة وهمية للتوافق)
     */
    function markCodeAsUsed($code)
    {
        // لا حاجة لفعل شيء عند عدم وجود reserved_codes
        return true;
    }
}