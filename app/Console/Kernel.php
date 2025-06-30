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
        $schedule->call(function () {
            if (function_exists('cleanupOldReservedCodes')) {
                cleanupOldReservedCodes(1);
            }
        })->everyMinute();

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

