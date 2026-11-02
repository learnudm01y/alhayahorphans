<?php

namespace App\Providers;

use App\Observers\SponsorshipObserver;
use App\Models\Sponsorship;
use App\Services\FileOrganizationService;
use App\Services\CloudIntegrationService;
use App\Services\ImageProcessingService;
use App\Services\ExcelManagementService;
use App\Services\PdfManagementService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register file management services with error handling
        $this->app->singleton(FileOrganizationService::class, function ($app) {
            return new FileOrganizationService();
        });

        $this->app->singleton(CloudIntegrationService::class, function ($app) {
            return new CloudIntegrationService();
        });

        $this->app->singleton(ImageProcessingService::class, function ($app) {
            return new ImageProcessingService();
        });

        // Register Search Service
        $this->app->singleton(\App\Services\SearchService::class, function ($app) {
            return new \App\Services\SearchService();
        });

        $this->app->singleton(ExcelManagementService::class, function ($app) {
            return new ExcelManagementService();
        });

        $this->app->singleton(PdfManagementService::class, function ($app) {
            return new PdfManagementService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ضمان وجود مجلدات التخزين الأساسية (الكاش/المجلس/القوالب) لتفادي
        // خطأ file_put_contents: Failed to open stream عند كتابة حالة التصدير
        $this->ensureStorageDirectories();

        // مزامنة لحظية لحالة الكفالة عند أي تغيير في جدول sponsorships
        Sponsorship::observe(SponsorshipObserver::class);

        // (3.3) إبطال كاش قوائم نموذج التسجيل عند تعديل أي جدول قوائم
        $this->registerPortalLookupCacheInvalidation();

        // تحميل إعدادات الملفات الكبيرة
        $this->loadLargeFileSettings();
    }

    /**
     * (3.3) جداول القوائم تُعرض في نموذج التسجيل من كاش لمدة ساعة.
     * أي إنشاء/تعديل/حذف يُبطل الكاش فوراً.
     */
    private function registerPortalLookupCacheInvalidation(): void
    {
        $lookupModels = [
            \App\Models\GeneralCategory::class,
            \App\Models\CategoryOfRelation::class,
            \App\Models\CI_PERSONAL_CD::class,
            \App\Models\AcademicDegree::class,
            \App\Models\DisplacementStatus::class,
            \App\Models\City::class,
            \App\Models\Province::class,
            \App\Models\HealthStatus::class,
            \App\Models\Employment::class,
            \App\Models\HousingStatus::class,
            \App\Models\TypeOfAccommodation::class,
            \App\Models\DocumentType::class,
            \App\Models\SponsorshipStatus::class,
            \App\Models\TypeOfGuarantee::class,
            \App\Models\DeathReason::class,
            \App\Models\BankName::class,
        ];

        $forget = static function () {
            \Illuminate\Support\Facades\Cache::forget('portal.lookups');
        };

        foreach ($lookupModels as $lookupModel) {
            $lookupModel::saved($forget);
            $lookupModel::deleted($forget);
        }
    }

    /**
     * إنشاء مجلدات storage المولَّدة تلقائياً إن كانت مفقودة
     * (هي مجلدات لا تُرفع عادةً مع الكود على خوادم النشر).
     */
    private function ensureStorageDirectories(): void
    {
        $directories = [
            'framework/cache/data',
            'framework/cache',
            'framework/sessions',
            'framework/views',
            'logs',
        ];

        foreach ($directories as $directory) {
            $path = storage_path($directory);

            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
        }
    }

    /**
     * تحميل إعدادات دعم الملفات الكبيرة
     */
    private function loadLargeFileSettings(): void
    {
        // تطبيق إعدادات PHP برمجياً (دعم الفيديوهات الكبيرة - 2GB)
        if (function_exists('ini_set')) {
            @ini_set('upload_max_filesize', '2048M');
            @ini_set('post_max_size', '2048M');
            @ini_set('memory_limit', '4096M');
            @ini_set('max_execution_time', 7200);
            @ini_set('max_input_time', 7200);
            @ini_set('max_file_uploads', 100);
            @ini_set('file_uploads', 'On');
            @ini_set('max_input_vars', 10000);
        }

        // تعيين حد الوقت
        if (function_exists('set_time_limit') && !app()->runningInConsole()) {
            @set_time_limit(3600);
        }
    }
}
