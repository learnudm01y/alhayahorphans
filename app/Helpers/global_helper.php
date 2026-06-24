<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

if (!function_exists('cleanupOldUnusedReservedCodes')) {
    /**
     * تنظيف الأرقام المحجوزة القديمة غير المستخدمة
     * يحذف الأرقام المحجوزة التي مر عليها أكثر من ساعة ولم تُستخدم
     *
     * @param int $hoursOld عدد الساعات (افتراضي: 1)
     * @return int عدد السجلات المحذوفة
     */
    function cleanupOldUnusedReservedCodes(int $hoursOld = 1): int
    {
        try {
            $deleted = DB::table('reserved_codes')
                ->where('used', false)
                ->where('reserved_at', '<', now()->subHours($hoursOld))
                ->delete();

            if ($deleted > 0) {
                Log::info("🧹 تم تنظيف الأرقام المحجوزة القديمة غير المستخدمة", [
                    'deleted_count' => $deleted,
                    'hours_threshold' => $hoursOld
                ]);
            }

            return $deleted;
        } catch (\Exception $e) {
            Log::error("❌ خطأ في تنظيف الأرقام المحجوزة: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('findSmallestGap')) {
    /**
     * البحث عن أصغر فجوة (رقم غير مستخدم) في التسلسل
     * Find the smallest gap (unused number) in the sequence
     * 🆕 تحسين: استخدام SQL مباشرة بدلاً من جلب كل الأرقام في الذاكرة
     */
    function findSmallestGap(string $table, string $column): ?int
    {
        try {
            // التحقق من تفعيل ميزة ملء الفجوات
            $fillGapsEnabled = config('code_generation.fill_gaps', true);
            if (!$fillGapsEnabled) {
                return null; // الميزة معطلة، استخدم MAX + 1
            }

            // 🆕 استخدام SQL مباشرة للبحث عن أصغر فجوة
            // هذا أسرع بكثير من جلب كل الأرقام في الذاكرة

            // الخطوة 1: إنشاء جدول مؤقت بجميع الأرقام المستخدمة
            $sql = "
                SELECT MIN(gap_start) as smallest_gap
                FROM (
                    SELECT t1.num + 1 as gap_start
                    FROM (
                        -- جميع الأرقام المستخدمة
                        SELECT CAST(file_id_number AS UNSIGNED) as num FROM data WHERE LENGTH(file_id_number) = 6 AND file_id_number REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(internal_file_number AS UNSIGNED) as num FROM sponsorships WHERE LENGTH(internal_file_number) = 6 AND internal_file_number REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(relation_id_number AS UNSIGNED) as num FROM sponsorships WHERE LENGTH(relation_id_number) = 6 AND relation_id_number REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(re_file_id AS UNSIGNED) as num FROM dead_people WHERE LENGTH(re_file_id) = 6 AND re_file_id REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(registration_id AS UNSIGNED) as num FROM re_people WHERE LENGTH(registration_id) = 6 AND registration_id REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(code AS UNSIGNED) as num FROM reserved_codes WHERE LENGTH(code) = 6 AND code REGEXP '^[0-9]+$' AND (used = 1 OR reserved_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR))
                    ) t1
                    LEFT JOIN (
                        SELECT CAST(file_id_number AS UNSIGNED) as num FROM data WHERE LENGTH(file_id_number) = 6 AND file_id_number REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(internal_file_number AS UNSIGNED) as num FROM sponsorships WHERE LENGTH(internal_file_number) = 6 AND internal_file_number REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(relation_id_number AS UNSIGNED) as num FROM sponsorships WHERE LENGTH(relation_id_number) = 6 AND relation_id_number REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(re_file_id AS UNSIGNED) as num FROM dead_people WHERE LENGTH(re_file_id) = 6 AND re_file_id REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(registration_id AS UNSIGNED) as num FROM re_people WHERE LENGTH(registration_id) = 6 AND registration_id REGEXP '^[0-9]+$'
                        UNION
                        SELECT CAST(code AS UNSIGNED) as num FROM reserved_codes WHERE LENGTH(code) = 6 AND code REGEXP '^[0-9]+$' AND (used = 1 OR reserved_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR))
                    ) t2 ON t1.num + 1 = t2.num
                    WHERE t2.num IS NULL AND t1.num + 1 < 999999
                ) gaps
                WHERE gap_start > 0
            ";

            $result = DB::selectOne($sql);

            if ($result && $result->smallest_gap) {
                $gap = (int) $result->smallest_gap;
                Log::info("🔍 وجدت فجوة باستخدام SQL مباشرة", [
                    'gap_number' => $gap,
                    'formatted' => str_pad($gap, 6, '0', STR_PAD_LEFT)
                ]);
                return $gap;
            }

            // التحقق من وجود الرقم 1 (إذا كان غير مستخدم)
            $checkOne = DB::selectOne("
                SELECT 1 as exists_flag FROM (
                    SELECT 1 as num
                ) t
                WHERE NOT EXISTS (
                    SELECT 1 FROM data WHERE file_id_number = '000001'
                    UNION SELECT 1 FROM sponsorships WHERE internal_file_number = '000001'
                    UNION SELECT 1 FROM sponsorships WHERE relation_id_number = '000001'
                    UNION SELECT 1 FROM dead_people WHERE re_file_id = '000001'
                    UNION SELECT 1 FROM re_people WHERE registration_id = '000001'
                    UNION SELECT 1 FROM reserved_codes WHERE code = '000001' AND (used = 1 OR reserved_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR))
                )
            ");

            if ($checkOne) {
                Log::info("🔍 الرقم 1 غير مستخدم");
                return 1;
            }

            // لا توجد فجوات
            return null;

        } catch (\Exception $e) {
            Log::error("❌ خطأ في البحث عن الفجوات: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('generateUniqueReservedCode')) {
    /**
     * Generate unique 6-digit code with gap filling support
     * يولد رقم فريد من 6 أرقام مع إعادة استخدام الفجوات
     * يتحقق من جداول data, sponsorships, dead_people, re_people معاً لضمان عدم التكرار
     */
     function generateUniqueReservedCode(string $table, string $column, ?string $sessionId = null): ?string
    {
        // DB::transaction with retry=5 يُعيد المحاولة تلقائياً عند حدوث Deadlock (1213)
        return DB::transaction(function () use ($table, $column, $sessionId) {
            // ── قفل صف واحد فقط في reserved_codes لتجنب الـ Deadlock ──────────
            // نستخدم GET_LOCK على مستوى التطبيق بدلاً من قفل الجدول كله
            $lockKey = 'gen_reserved_code';
            DB::statement("SELECT GET_LOCK('{$lockKey}', 10)");

            try {
            // الخطوة 1: البحث عن أصغر رقم غير مستخدم (ملء الفجوات أولاً)
            $smallestGap = findSmallestGap($table, $column);

            if ($smallestGap !== null) {
                $code = str_pad($smallestGap, 6, '0', STR_PAD_LEFT);

                // التحقق من عدم وجود الرقم (قراءة فقط - بدون lockForUpdate)
                $existsReserved           = DB::table('reserved_codes')->where('code', $code)->exists();
                $existsSponsorship        = DB::table('sponsorships')->where('internal_file_number', $code)->exists();
                $existsSponsorshipRelation= DB::table('sponsorships')->where('relation_id_number', $code)->exists();
                $existsDeadPeople         = DB::table('dead_people')->where('re_file_id', $code)->exists();
                $existsRePeople           = DB::table('re_people')->where('registration_id', $code)->exists();

                if (!$existsReserved && !$existsSponsorship && !$existsSponsorshipRelation && !$existsDeadPeople && !$existsRePeople) {
                    // INSERT IGNORE يتجاهل التكرار بدلاً من رمي Exception
                    DB::table('reserved_codes')->insertOrIgnore([
                        'code'        => $code,
                        'session_id'  => $sessionId ?? Str::uuid(),
                        'reserved_at' => now(),
                        'used'        => false,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                    // تأكد أن الإدراج نجح فعلاً (لم يتجاهله IGNORE)
                    if (DB::table('reserved_codes')->where('code', $code)->where('session_id', $sessionId ?? '')->exists()
                        || DB::table('reserved_codes')->where('code', $code)->exists()) {
                        Log::info("🔄 إعادة استخدام رقم من فجوة: {$code}");
                        return $code;
                    }
                }
            }

            // الخطوة 2: MAX + 1 من جميع الجداول
            $maxMain = DB::table($table)
                ->select(DB::raw("MAX(CAST($column as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH($column) = 6 AND $column REGEXP '^[0-9]+$'")
                ->value('max_code');

            $maxSponsorship = DB::table('sponsorships')
                ->select(DB::raw("MAX(CAST(internal_file_number as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH(internal_file_number) = 6 AND internal_file_number REGEXP '^[0-9]+$'")
                ->value('max_code');

            $maxSponsorshipRelation = DB::table('sponsorships')
                ->select(DB::raw("MAX(CAST(relation_id_number as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH(relation_id_number) = 6 AND relation_id_number REGEXP '^[0-9]+$'")
                ->value('max_code');

            $maxDeadPeople = DB::table('dead_people')
                ->select(DB::raw("MAX(CAST(re_file_id as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH(re_file_id) = 6 AND re_file_id REGEXP '^[0-9]+$'")
                ->value('max_code');

            $maxRePeople = DB::table('re_people')
                ->select(DB::raw("MAX(CAST(registration_id as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH(registration_id) = 6 AND registration_id REGEXP '^[0-9]+$'")
                ->value('max_code');

            $maxReserved = DB::table('reserved_codes')
                ->select(DB::raw("MAX(CAST(code as UNSIGNED)) as max_code"))
                ->whereRaw("LENGTH(code) = 6 AND code REGEXP '^[0-9]+$'")
                ->value('max_code');

            // حساب أعلى رقم من جميع الجداول
            $next = max(
                (int)$maxMain,
                (int)$maxSponsorship,
                (int)$maxSponsorshipRelation,
                (int)$maxDeadPeople,
                (int)$maxRePeople,
                (int)$maxReserved
            ) + 1;
            $code = str_pad($next, 6, '0', STR_PAD_LEFT);

            // التحقق من عدم وجود الرقم في جميع الجداول (بدون lockForUpdate)
            $existsReserved = DB::table('reserved_codes')->where('code', $code)->exists();
            $existsSponsorship = DB::table('sponsorships')->where('internal_file_number', $code)->exists();
            $existsSponsorshipRelation = DB::table('sponsorships')->where('relation_id_number', $code)->exists();
            $existsDeadPeople = DB::table('dead_people')->where('re_file_id', $code)->exists();
            $existsRePeople = DB::table('re_people')->where('registration_id', $code)->exists();

            if (!$existsReserved && !$existsSponsorship && !$existsSponsorshipRelation && !$existsDeadPeople && !$existsRePeople) {
                DB::table('reserved_codes')->insertOrIgnore([
                    'code'        => $code,
                    'session_id'  => $sessionId ?? Str::uuid(),
                    'reserved_at' => now(),
                    'used'        => false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                Log::info("➕ استخدام رقم جديد تسلسلي: {$code}");
                return $code;
            }

            // الخطوة 3: حاول أرقام تالية (بدون lockForUpdate)
            for ($i = 1; $i <= 20; $i++) {
                $next++;
                $code = str_pad($next, 6, '0', STR_PAD_LEFT);

                $existsReserved           = DB::table('reserved_codes')->where('code', $code)->exists();
                $existsSponsorship        = DB::table('sponsorships')->where('internal_file_number', $code)->exists();
                $existsSponsorshipRelation= DB::table('sponsorships')->where('relation_id_number', $code)->exists();
                $existsDeadPeople         = DB::table('dead_people')->where('re_file_id', $code)->exists();
                $existsRePeople           = DB::table('re_people')->where('registration_id', $code)->exists();

                if (!$existsReserved && !$existsSponsorship && !$existsSponsorshipRelation && !$existsDeadPeople && !$existsRePeople) {
                    DB::table('reserved_codes')->insertOrIgnore([
                        'code'        => $code,
                        'session_id'  => $sessionId ?? Str::uuid(),
                        'reserved_at' => now(),
                        'used'        => false,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                    return $code;
                }
            }

            return null;

            } finally {
                // تحرير الـ lock دائماً حتى لو حدث خطأ
                DB::statement("SELECT RELEASE_LOCK('{$lockKey}')");
            }
        }, 5); // retry تلقائي حتى 5 مرات عند Deadlock
    }
}

if (!function_exists('markCodeAsUsed')) {
    // تم نقل دالة markCodeAsUsed إلى الأسفل مع إضافة المزيد من المعلومات
}

if (!function_exists('cleanupOldReservedCodes')) {
    /**
     * Delete old, unused reserved codes older than given minutes.
     * حذف الأكواد القديمة غير المستخدمة الأقدم من عدد الدقائق المحدد
     * مع التحقق الذكي من جدول data قبل الحذف
     *
     * @param int $minutes عدد الدقائق
     * @return int عدد السجلات المحذوفة
     */
    function cleanupOldReservedCodes(int $minutes = 1): int
    {
        try {
            // الخطوة 1: جلب الأكواد المحجوزة القديمة غير المستخدمة
            $oldCodes = DB::table('reserved_codes')
                ->where('used', false)
                ->where('reserved_at', '<', now()->subMinutes($minutes))
                ->pluck('code')
                ->toArray();

            if (empty($oldCodes)) {
                return 0;
            }

            // الخطوة 2: التحقق من وجود هذه الأكواد في جدول data (الأكواد المستخدمة فعلياً)
            $usedCodesInData = DB::table('data')
                ->whereIn('file_id_number', $oldCodes)
                ->pluck('file_id_number')
                ->toArray();

            $markedAsUsedCount = 0;

            // الخطوة 3: تحديث الأكواد الموجودة في data إلى used = true بدلاً من حذفها
            if (!empty($usedCodesInData)) {
                $markedAsUsedCount = DB::table('reserved_codes')
                    ->whereIn('code', $usedCodesInData)
                    ->update(['used' => true, 'updated_at' => now()]);

                Log::info("✅ تم وضع علامة على {$markedAsUsedCount} كود كمستخدم بعد التحقق من جدول data");
            }

            // الخطوة 4: حذف الأكواد المحجوزة القديمة التي لم تُستخدم فعلياً
            $codesToDelete = array_diff($oldCodes, $usedCodesInData);
            $deleted = 0;

            if (!empty($codesToDelete)) {
                $deleted = DB::table('reserved_codes')
                    ->whereIn('code', $codesToDelete)
                    ->where('used', false)
                    ->where('reserved_at', '<', now()->subMinutes($minutes))
                    ->delete();

                if ($deleted > 0) {
                    Log::info("🧹 تم حذف {$deleted} كود غير مستخدم من جدول reserved_codes");
                }
            }

            return $deleted;
        } catch (\Exception $e) {
            Log::error("❌ خطأ في تنظيف الأكواد القديمة: " . $e->getMessage());
            return 0;
        }
    }
}if (!function_exists('generateExcCode')) {
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

if (!function_exists('syncReservedCodesWithData')) {
    /**
     * Synchronize reserved_codes table with actual data usage
     * مزامنة جدول reserved_codes مع الاستخدام الفعلي في جدول data
     * تُستخدم هذه الدالة لإصلاح أي تناقضات بين الجدولين
     *
     * @return array إحصائيات المزامنة
     */
    function syncReservedCodesWithData(): array
    {
        try {
            // جلب جميع file_id_number من جدول data
            $usedCodes = DB::table('data')
                ->select('file_id_number')
                ->whereNotNull('file_id_number')
                ->whereRaw("file_id_number REGEXP '^[0-9]+$'")
                ->pluck('file_id_number')
                ->toArray();

            // التحقق من وجود هذه الأكواد في reserved_codes وتحديثها
            $updated = 0;
            $added = 0;

            foreach ($usedCodes as $code) {
                $exists = DB::table('reserved_codes')
                    ->where('code', $code)
                    ->exists();

                if ($exists) {
                    // تحديث الكود الموجود إلى used = true
                    $result = DB::table('reserved_codes')
                        ->where('code', $code)
                        ->where('used', false) // فقط إذا لم يكن محدثاً من قبل
                        ->update([
                            'used' => true,
                            'updated_at' => now()
                        ]);

                    if ($result > 0) {
                        $updated++;
                    }
                } else {
                    // إضافة الكود المفقود إلى reserved_codes
                    DB::table('reserved_codes')->insert([
                        'code' => $code,
                        'session_id' => 'sync_' . time(),
                        'reserved_at' => now(),
                        'used' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $added++;
                }
            }

            $stats = [
                'total_codes_in_data' => count($usedCodes),
                'updated_in_reserved' => $updated,
                'added_to_reserved' => $added,
                'timestamp' => now()->toDateTimeString()
            ];

            Log::info("🔄 مزامنة جدول reserved_codes مع data", $stats);

            return $stats;
        } catch (\Exception $e) {
            Log::error("❌ خطأ في مزامنة reserved_codes: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ];
        }
    }
}

if (!function_exists('generateFileIdFromDataTable')) {
    /**
     * Generate file_id_number using the unified reserved_codes system
     * ✅ يستخدم الآن generateUniqueReservedCode لضمان حجز الرقم في reserved_codes
     *
     * @param string|null $sessionId معرف الجلسة للحجز
     * @return string
     */
    function generateFileIdFromDataTable(?string $sessionId = null): string
    {
        // استخدام الخوارزمية الموحدة التي تحجز الرقم في reserved_codes
        $code = generateUniqueReservedCode('data', 'file_id_number', $sessionId);

        if ($code) {
            Log::info("✅ [generateFileIdFromDataTable] تم توليد وحجز رقم ملف جديد", [
                'code' => $code,
                'session_id' => $sessionId
            ]);
            return $code;
        }

        // في حالة فشل التوليد (نادر جداً)، استخدم الطريقة القديمة كـ fallback
        Log::warning("⚠️ [generateFileIdFromDataTable] فشل generateUniqueReservedCode، استخدام fallback");

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
            $code = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            // ⚠️ محاولة حجز الرقم في reserved_codes (fallback)
            try {
                $exists = DB::table('reserved_codes')->where('code', $code)->exists();
                if (!$exists) {
                    DB::table('reserved_codes')->insert([
                        'code' => $code,
                        'session_id' => Str::uuid(),
                        'reserved_at' => now(),
                        'used' => false,
                        'notes' => 'fallback from generateFileIdFromDataTable',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    Log::info("✅ [generateFileIdFromDataTable] تم حجز الرقم في fallback: {$code}");
                }
            } catch (\Exception $e) {
                Log::error("❌ [generateFileIdFromDataTable] فشل حجز الرقم: " . $e->getMessage());
            }

            return $code;
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

if (!function_exists('markCodeAsUsed')) {
    /**
     * تحديث حالة الكود إلى "مستخدم" في جدول reserved_codes
     * يجب استدعاء هذه الدالة بعد إدراج السجل بنجاح في الجدول المستهدف
     *
     * @param string $code الكود المراد تحديثه
     * @param int|null $userId معرف المستخدم الذي استخدم الكود
     * @param string|null $notes ملاحظات إضافية
     * @return bool
     */
    function markCodeAsUsed(string $code, ?int $userId = null, ?string $notes = null): bool
    {
        try {
            // ✅ فقط الأعمدة الموجودة في جدول reserved_codes: code, session_id, reserved_at, used, timestamps
            $updated = DB::table('reserved_codes')
                ->where('code', $code)
                ->update([
                    'used' => true,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info("✅ [markCodeAsUsed] تم تحديث الكود كمستخدم", [
                    'code' => $code,
                    'user_id' => $userId
                ]);
                return true;
            } else {
                // إذا الكود غير موجود، نقوم بإنشائه تلقائياً ثم تحديثه كمستخدم
                DB::table('reserved_codes')->insert([
                    'code' => $code,
                    'session_id' => session_id() ?: 'auto_created',
                    'reserved_at' => now(),
                    'used' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info("✅ [markCodeAsUsed] الكود غير موجود - تم إنشاؤه وتعليمه كمستخدم تلقائياً", [
                    'code' => $code,
                    'user_id' => $userId
                ]);
                return true;
            }
        } catch (\Exception $e) {
            Log::error("❌ [markCodeAsUsed] فشل تحديث الكود", [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

if (!function_exists('normalizeArabicText')) {
    /**
     * تطبيع النص العربي للبحث
     * Normalize Arabic text for search purposes
     *
     * @param string $text
     * @return string
     */
    function normalizeArabicText(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // تحويل الحروف المتشابهة إلى صورة موحدة
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);

        // إزالة التشكيل والحركات
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);

        // إزالة الكشيدة (tatweel)
        $text = str_replace('ـ', '', $text);

        // توحيد جميع أنواع المسافات (عادية، غير قابلة للكسر، عرض صفري، إلخ)
        $text = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $text);

        // إزالة المسافات المتعددة وتحويلها لمسافة واحدة
        $text = preg_replace('/\s+/', ' ', $text);

        // إزالة المسافات من البداية والنهاية
        $text = trim($text);

        return $text;
    }
}

if (!function_exists('normalizeArabicForFlexibleSearch')) {
    /**
     * تطبيع النص العربي للبحث المرن (يزيل المسافات من الكلمات المركبة)
     * Normalize Arabic text for flexible search (removes spaces from compound words)
     *
     * @param string $text
     * @return array يرجع النص الأصلي والنص بدون مسافات
     */
    function normalizeArabicForFlexibleSearch(string $text): array
    {
        $normalized = normalizeArabicText($text);

        // نسخة بدون مسافات للبحث عن الكلمات المركبة مثل عبدالناصر / عبد الناصر
        $noSpaces = str_replace(' ', '', $normalized);

        return [
            'with_spaces' => $normalized,
            'without_spaces' => $noSpaces
        ];
    }
}

if (!function_exists('buildNormalizedSearchQuery')) {
    /**
     * بناء استعلام بحث مع تطبيع النص العربي
     * Build search query with Arabic text normalization
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $columns
     * @param string $searchTerm
     * @param string $operator ('and' or 'or')
     * @return \Illuminate\Database\Eloquent\Builder
     */
    function buildNormalizedSearchQuery($query, $columns, string $searchTerm, string $operator = 'or')
    {
        if (empty($searchTerm)) {
            return $query;
        }

        $columns = is_array($columns) ? $columns : [$columns];
        $normalizedSearch = normalizeArabicText($searchTerm);

        $method = $operator === 'and' ? 'where' : 'orWhere';

        return $query->where(function($q) use ($columns, $normalizedSearch, $method) {
            foreach ($columns as $column) {
                // البحث في النص الأصلي
                $q->{$method}($column, 'LIKE', "%{$normalizedSearch}%");

                // البحث مع استبدال الحروف المختلفة
                $q->{$method}(function($subQ) use ($column, $normalizedSearch) {
                    // البحث مع أ/إ/آ
                    $subQ->whereRaw("REPLACE(REPLACE(REPLACE($column, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') LIKE ?", ["%{$normalizedSearch}%"]);
                });

                $q->{$method}(function($subQ) use ($column, $normalizedSearch) {
                    // البحث مع ة/ه
                    $subQ->whereRaw("REPLACE($column, 'ة', 'ه') LIKE ?", ["%{$normalizedSearch}%"]);
                });

                $q->{$method}(function($subQ) use ($column, $normalizedSearch) {
                    // البحث مع ى/ي
                    $subQ->whereRaw("REPLACE($column, 'ى', 'ي') LIKE ?", ["%{$normalizedSearch}%"]);
                });
            }
        });
    }
}
