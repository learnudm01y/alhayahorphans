<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Data;
use App\Observers\DataExcelObserver;

class DataObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // تسجيل Observer للتأكد من حالة السجلات المستوردة من Excel
        Data::observe(DataExcelObserver::class);
    }
}
