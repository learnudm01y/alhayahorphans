<?php

namespace App\Providers;

use App\Services\DuplicateFileDetectionService;
use Illuminate\Support\ServiceProvider;

class DuplicateFileServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DuplicateFileDetectionService::class, function ($app) {
            return new DuplicateFileDetectionService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register scheduled tasks for cleaning expired files
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

            // Clean expired duplicate files daily at 2 AM
            $schedule->command('duplicates:clean-expired --force')
                     ->dailyAt('02:00')
                     ->description('Clean expired duplicate files automatically');
        });
    }
}
