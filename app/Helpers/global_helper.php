<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

if (!function_exists('generateUniqueReservedCode')) {
    /**
     * Generate unique 6-digit code without reserved_codes table for shared hosting
     * يولد رقم فريد من 6 أرقام بدون استخدام جدول reserved_codes للاستضافة المشتركة
     */
    // function generateUniqueReservedCode(string $table, string $column, ?string $sessionId = null): ?string
    // {
    //     // استخدام Static variable لضمان زيادة الأرقام في نفس الجلسة
    //     static $lastGenerated = [];
    //     $tableKey = "{$table}.{$column}";

    //     return DB::transaction(function () use ($table, $column, $sessionId, $tableKey, &$lastGenerated) {
    //         // جلب أكبر رقم رقمي فقط من الجدول الأساسي
    //         $maxMain = DB::table($table)
    //             ->select(DB::raw("MAX(CAST($column as UNSIGNED)) as max_code"))
    //             ->whereRaw("LENGTH($column) = 6 AND $column REGEXP '^[0-9]+$'")
    //             ->value('max_code');

    //         // استخدام الرقم الأخير المولد أو الأكبر من الجدول
    //         $lastGenerated[$tableKey] = $lastGenerated[$tableKey] ?? (int)$maxMain;
    //         $next = max($lastGenerated[$tableKey], (int)$maxMain) + 1;

    //         $attempts = 0;
    //         $maxAttempts = 50;

    //         while ($attempts < $maxAttempts) {
    //             $code = str_pad($next, 6, '0', STR_PAD_LEFT);

    //             // فحص عدم وجود الرقم في الجدول
    //             $exists = DB::table($table)->where($column, $code)->exists();

    //             if (!$exists) {
    //                 $lastGenerated[$tableKey] = $next; // حفظ الرقم المولد
    //                 Log::info("✅ تم توليد رقم فريد: {$code} للجدول {$table}");
    //                 return $code;
    //             }

    //             $next++;
    //             $attempts++;
    //         }

    //         // في حالة فشل التوليد التسلسلي، استخدم رقم عشوائي
    //         for ($i = 0; $i < 20; $i++) {
    //             $randomCode = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    //             $exists = DB::table($table)->where($column, $randomCode)->exists();

    //             if (!$exists) {
    //                 Log::warning("⚠️ تم استخدام رقم عشوائي: {$randomCode} بعد فشل التوليد التسلسلي");
    //                 return $randomCode;
    //             }
    //         }

    //         Log::error("❌ فشل في توليد رقم فريد للجدول {$table}");
    //         return null;
    //     });
    // }
     function generateUniqueReservedCode(string $table, string $column, ?string $sessionId = null): ?string
    {
        return DB::transaction(function () use ($table, $column, $sessionId) {
            // جلب أكبر رقم رقمي فقط من الجدول الأساسي
            $maxMain = DB::table($table)
                ->select(DB::raw("MAX(CAST($column as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH($column) = 6 AND $column REGEXP '^[0-9]+$'")
                ->value('max_code');

            // جلب أكبر رقم رقمي فقط من جدول reserved_codes مع قفل للكتابة
            $maxReserved = DB::table('reserved_codes')
                ->select(DB::raw("MAX(CAST(code as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH(code) = 6 AND code REGEXP '^[0-9]+$'")
                ->lockForUpdate()
                ->value('max_code');

            // احسب الرقم التالي
            $next = max((int)$maxMain, (int)$maxReserved) + 1;
            $code = str_pad($next, 6, '0', STR_PAD_LEFT);

            // تحقق من عدم وجود الرقم في reserved_codes (داخل نفس المعاملة)
            $exists = DB::table('reserved_codes')->where('code', $code)->lockForUpdate()->exists();
            if (!$exists) {
                DB::table('reserved_codes')->insert([
                    'code' => $code,
                    'session_id' => $sessionId ?? Str::uuid(),
                    'reserved_at' => now(),
                    'used' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return $code;
            }
            // إذا كان الرقم مستخدم بالفعل (حالة نادرة)، كرر حتى تجد رقم غير مستخدم
            for ($i = 1; $i <= 10; $i++) {
                $next++;
                $code = str_pad($next, 6, '0', STR_PAD_LEFT);
                $exists = DB::table('reserved_codes')->where('code', $code)->lockForUpdate()->exists();
                if (!$exists) {
                    DB::table('reserved_codes')->insert([
                        'code' => $code,
                        'session_id' => $sessionId ?? Str::uuid(),
                        'reserved_at' => now(),
                        'used' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    return $code;
                }
            }
            // إذا لم يتمكن من توليد رقم فريد
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

if (!function_exists('generateUniqueAttachmentRecordNumber')) {
    /**
     * توليد رقم مرفق فريد بالبادئة exc_ للاستخدام في جدول enhanced_attachments
     * يضمن التفرد مع استخدام طريقة مبسطة للاستضافة المشتركة
     *
     * @param string $sessionId معرف الجلسة الفريد
     * @return string الرقم المولد بصيغة exc_XXXXXX
     */
    function generateUniqueAttachmentRecordNumber(string $sessionId = null): string
    {
        $sessionId = $sessionId ?: 'attachment_' . session()->getId() . '_' . time();

        return DB::transaction(function () use ($sessionId) {
            $maxAttempts = 50;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                // الحصول على أكبر رقم من enhanced_attachments (إزالة البادئة للمقارنة)
                $maxAttachment = DB::table('enhanced_attachments')
                    ->where('record_number', 'LIKE', 'exc_%')
                    ->selectRaw('MAX(CAST(SUBSTRING(record_number, 5) AS UNSIGNED)) as max_num')
                    ->value('max_num');

                // حساب الرقم التالي
                $nextNumber = (int)$maxAttachment + 1;
                $newRecordNumber = 'exc_' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

                // التحقق من عدم وجود الرقم في enhanced_attachments
                $existsInAttachments = DB::table('enhanced_attachments')
                    ->where('record_number', $newRecordNumber)
                    ->exists();

                if (!$existsInAttachments) {
                    Log::info('Generated unique attachment record number', [
                        'record_number' => $newRecordNumber,
                        'session_id' => $sessionId,
                        'attempt' => $attempt,
                        'max_attachment' => $maxAttachment
                    ]);

                    return $newRecordNumber;
                }
            }

            // إذا فشل في التوليد، استخدم نظام طوارئ
            $emergencyNumber = 'exc_' . substr(time(), -6) . rand(10, 99);
            Log::warning('Using emergency attachment record number', [
                'emergency_number' => $emergencyNumber,
                'session_id' => $sessionId
            ]);

            return $emergencyNumber;
        });
    }
}

if (!function_exists('generateFileIdFromDataTable')) {
    /**
     * Generate file_id_number using the data table sequence (same algorithm as existing system)
     *
     * @return string
     */
    function generateFileIdFromDataTable(): string
    {
        return DB::transaction(function () {
            // جلب آخر file_id_number من جدول data
            $lastFileId = DB::table('data')
                ->select('file_id_number')
                ->whereNotNull('file_id_number')
                ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
                ->orderByRaw('CAST(file_id_number as UNSIGNED) DESC')
                ->value('file_id_number');

            // حساب الرقم التالي
            $nextNumber = $lastFileId ? ((int)$lastFileId + 1) : 1;

            // إرجاع الرقم مع 6 خانات
            return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }
}

if (!function_exists('getFileIdByIdentityNumber')) {
    /**
     * Get file_id_number for a given identity number from data table
     * If not found, create a new one using the standard algorithm
     *
     * @param string $identityNumber
     * @return string|null
     */
    function getFileIdByIdentityNumber(string $identityNumber): ?string
    {
        // البحث عن رقم الهوية في جدول data
        $record = DB::table('data')
            ->select('file_id_number')
            ->where('person_id', $identityNumber)
            ->orWhere('guardian_id', $identityNumber)
            ->first();

        if ($record && $record->file_id_number) {
            return $record->file_id_number;
        }

        // إذا لم يوجد، إنشاء رقم جديد باستخدام الخوارزمية المعتمدة
        return generateFileIdFromDataTable();
    }
}
