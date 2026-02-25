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

        // تنظيف الأكواد القديمة غير المستخدمة كل دقيقة
        $schedule->call(function () {
            if (function_exists('cleanupOldReservedCodes')) {
                cleanupOldReservedCodes(1);
            }
        })->everyMinute();

        // مزامنة جدول reserved_codes مع جدول data كل 5 دقائق
        $schedule->call(function () {
            if (function_exists('syncReservedCodesWithData')) {
                syncReservedCodesWithData();
            }
        })->everyFiveMinutes();

        // مهمة اختبارية للتأكد من عمل الجدولة
        $schedule->call(function () {
            file_put_contents(storage_path('logs/scheduler-test.log'), now() . "\n", FILE_APPEND);
        })->everyMinute();
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

