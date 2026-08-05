<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // مزامنة احتياطية شاملة لحالة الكفالة مرة واحدة يومياً (الـ Observer يعمل بشكل لحظي عند كل تغيير)
        $schedule->job(new \App\Jobs\SyncSponsorshipStatusJob())
                 ->dailyAt('03:00')
                 ->withoutOverlapping(120)
                 ->name('sync-sponsorship-status-daily')
                 ->onFailure(function () {
                     \Illuminate\Support\Facades\Log::error('SyncSponsorshipStatusJob: فشل التنفيذ اليومي');
                 });

        // تنظيف الأكواد القديمة غير المستخدمة كل 5 دقائق (تقليل التنافس مع طلبات الويب)
        $schedule->call(function () {
            if (function_exists('cleanupOldReservedCodes')) {
                cleanupOldReservedCodes(5);
            }
        })->everyFiveMinutes()
          ->name('cleanup-old-reserved-codes')
          ->withoutOverlapping();

        // مزامنة جدول reserved_codes مع جدول data كل 5 دقائق
        $schedule->call(function () {
            if (function_exists('syncReservedCodesWithData')) {
                syncReservedCodesWithData();
            }
        })->everyFiveMinutes()
          ->name('sync-reserved-codes-with-data');

        // ═══════════════════════════════════════════════════════════════
        // 🧹 كنس منظومة الرفع
        //
        // لم يكن هناك أي مهمة مجدولة تلمس مسار الرفع إطلاقاً. النتيجة:
        //  • storage/app/chunks و temp_uploads ينموان بلا حد من الرفعات
        //    المهجورة (رُصد ١٧٨ ملفاً مسرّباً).
        //  • سجلات عالقة في 'uploading' لا يُعيد أحد تشغيلها.
        // ═══════════════════════════════════════════════════════════════
        $schedule->call(function () {
            $this->sweepAbandonedChunks();
        })->hourly()
          ->name('sweep-abandoned-chunks')
          ->withoutOverlapping();

        $schedule->call(function () {
            $this->redriveStuckDriveUploads();
        })->everyThirtyMinutes()
          ->name('redrive-stuck-drive-uploads')
          ->withoutOverlapping();

        // مهمة اختبارية للتأكد من عمل الجدولة
        $schedule->call(function () {
            file_put_contents(storage_path('logs/scheduler-test.log'), now() . "\n", FILE_APPEND);
        })->everyMinute();
    }

    /**
     * حذف مجلدات الأجزاء وعلامات الاكتمال والملفات المؤقتة المهجورة.
     * المهلة كريمة عمداً: جهاز على شبكة ضعيفة قد يحتاج ساعات لإكمال فيديو،
     * والحذف المبكر يعني إجباره على البدء من الصفر.
     */
    private function sweepAbandonedChunks(): void
    {
        $cutoff = now()->subHours(48)->getTimestamp();
        $removed = 0;

        foreach ([storage_path('app/chunks')] as $root) {
            if (!is_dir($root)) {
                continue;
            }
            foreach ((array) glob($root . '/*', GLOB_ONLYDIR) as $dir) {
                if (basename($dir) === '_done') {
                    continue;
                }
                if (@filemtime($dir) !== false && filemtime($dir) < $cutoff) {
                    foreach ((array) glob($dir . '/*') as $f) {
                        @unlink($f);
                    }
                    @rmdir($dir);
                    $removed++;
                }
            }

            // علامات الاكتمال تُحفظ أطول: هي ما يمنع إعادة رفع ملف اكتمل فعلاً.
            $markerCutoff = now()->subDays(14)->getTimestamp();
            foreach ((array) glob($root . '/_done/*.json') as $marker) {
                if (@filemtime($marker) !== false && filemtime($marker) < $markerCutoff) {
                    @unlink($marker);
                    $removed++;
                }
            }
        }

        // ملفات مُجمَّعة لم تلتقطها أي مهمة rclone.
        $tempDir = storage_path('app/temp_uploads');
        if (is_dir($tempDir)) {
            foreach ((array) glob($tempDir . '/*') as $file) {
                if (!is_file($file) || @filemtime($file) === false || filemtime($file) >= $cutoff) {
                    continue;
                }
                $stillReferenced = \App\Models\GoogleDriveUpload::where('local_file_path', $file)
                    ->whereIn('upload_status', ['pending', 'uploading'])
                    ->exists();
                if (!$stillReferenced) {
                    @unlink($file);
                    $removed++;
                }
            }
        }

        if ($removed > 0) {
            \Illuminate\Support\Facades\Log::info("🧹 كنس الرفع: أُزيل {$removed} عنصر مهجور");
        }
    }

    /**
     * إعادة تشغيل السجلات العالقة في 'uploading' على الخادم.
     *
     * إن مات عامل الطابور أو أُعيد تشغيل الخادم أثناء رفع rclone، يبقى السجل
     * 'uploading' بلا مهمة في الطابور — والجهاز ينتظر خبراً لن يأتي أبداً.
     */
    private function redriveStuckDriveUploads(): void
    {
        $stuck = \App\Models\GoogleDriveUpload::where('upload_status', 'uploading')
            ->where('updated_at', '<', now()->subHours(2))
            ->where('retry_count', '<', 5)
            ->limit(25)
            ->get();

        foreach ($stuck as $record) {
            if (!$record->local_file_path || !file_exists($record->local_file_path)) {
                // الملف المصدر اختفى: أعلِن الفشل ليعرف الجهاز أنه يجب أن يُعيد الرفع.
                $record->update([
                    'upload_status' => 'failed',
                    'error_message' => 'الملف المؤقت على الخادم لم يعد موجوداً',
                ]);
                \Illuminate\Support\Facades\DB::table('offline_upload_statuses')->insert([
                    'user_id' => $record->uploaded_by,
                    'file_name' => $record->file_name,
                    'status' => 'failed',
                    'error_message' => 'الملف المؤقت على الخادم لم يعد موجوداً',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                continue;
            }

            $record->increment('retry_count');

            \App\Jobs\ProcessRcloneUploadJob::dispatch(
                (int) $record->server_attachment_id,
                (int) $record->id,
                $record->local_file_path,
                'General',
                'Unknown_' . $record->entity_id,
                pathinfo($record->file_name, PATHINFO_FILENAME),
                pathinfo($record->file_name, PATHINFO_EXTENSION)
            );

            \Illuminate\Support\Facades\Log::warning(
                "♻️ أُعيد تشغيل رفع عالق إلى Drive: سجل #{$record->id}"
            );
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

