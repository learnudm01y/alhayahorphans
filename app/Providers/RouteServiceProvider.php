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
            // استثناء مُعلن: webhook الواتساب محمي بمصادقة apikey + throttle مخصص
            // (throttle:300,1 على المسار نفسه)، وحدّ api العام (60/دقيقة) كان سيرفض
            // دفعات Meta المتزامنة (رسائل + تحديثات حالة) بأرقام وصلات مختلفة.
            if ($request->is('api/whatsapp/webhook')) {
                return Limit::none();
            }

            // صور الكفالات: تنزيل دفعة واحدة للشاشة يطلب مئات الصور في دقائق.
            // حدّ ٦٠/دقيقة كان يُرجع 429 في منتصف التنزيل والعميل يبتلع الفشل بصمت.
            if ($request->is(
                'api/mobile/registration/photo/*',
                'api/mobile/registration/photo/*/exists',
                'api/mobile/photos/*',
                'api/mobile/photos/*/exists'
            )) {
                return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
            }

            // ⚠️ حدّ ٦٠ طلباً/دقيقة كان يجعل رفع أي ملف كبير مستحيلاً.
            // الرفع المُجزَّأ يُرسل طلباً لكل جزء: فيديو ٢٠٠ ميجابايت بأجزاء
            // ١ ميجابايت = ٢٠٠ طلب، أي أن الجهاز يُحظر بعد أول ٦٠ جزءاً ويتلقى
            // صفحة 429 بصيغة HTML لا JSON، فيفسّرها العميل كفشل ويُعيد من الصفر.
            // مسارات الأجزاء لها حدّ خاص واسع، والباقي يبقى على ٦٠.
            if ($request->is(
                'api/mobile/upload-chunk',
                'api/uploads/chunk',
                'api/mobile/upload-status/*',
                // نُسخ mobile/v4 من مسارات الرفع (نسخ كامل تحت v4)
                'api/mobile/v4/upload-chunk',
                'api/mobile/v4/uploads/chunk',
                'api/mobile/v4/upload-status/*',
                'api/mobile/v4/registration/upload-chunk'
            )) {
                return Limit::perMinute(1200)->by($request->user()?->id ?: $request->ip());
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-v4', function (Request $request) {
            // نفس منطق محدِّد 'api' — التنزيل الجماعي للصور يحتاج سقفاً واسعاً.
            if ($request->is(
                'api/mobile/v4/registration/photo/*',
                'api/mobile/v4/registration/photo/*/exists',
                'api/mobile/v4/photos/*',
                'api/mobile/v4/photos/*/exists'
            )) {
                return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
            }

            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // android-v4 — مجموعة مسارات مستقلة جديدة (لا تُمس routes/api.php)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api_v4.php'));

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
