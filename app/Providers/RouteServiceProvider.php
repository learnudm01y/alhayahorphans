<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = 'user/dashboard';
    public const ADMIN = 'admin/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // ⚠️ حدّ ٦٠ طلباً/دقيقة كان يجعل رفع أي ملف كبير مستحيلاً.
            // الرفع المُجزَّأ يُرسل طلباً لكل جزء: فيديو ٢٠٠ ميجابايت بأجزاء
            // ١ ميجابايت = ٢٠٠ طلب، أي أن الجهاز يُحظر بعد أول ٦٠ جزءاً ويتلقى
            // صفحة 429 بصيغة HTML لا JSON، فيفسّرها العميل كفشل ويُعيد من الصفر.
            // مسارات الأجزاء لها حدّ خاص واسع، والباقي يبقى على ٦٠.
            if ($request->is('api/mobile/upload-chunk', 'api/uploads/chunk', 'api/mobile/upload-status/*')) {
                return Limit::perMinute(1200)->by($request->user()?->id ?: $request->ip());
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Offline Test Development Routes - للاختبار المحلي فقط
            // بدون middleware لتسهيل الاختبار
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/offline-test-development.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
            Route::middleware(['web','auth','rolebreeze:admin'])
                ->group(base_path('routes/admin.php'));
        });
    }
}
