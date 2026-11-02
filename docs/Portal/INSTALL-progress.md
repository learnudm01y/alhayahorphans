# تركيب شريط تقدّم الحفظ

تم تركيب شريط التقدّم وربطه بنموذج تحديث بيانات الكفالة. لا حاجة لإعادة إضافة المسار أو وسم تحميل JavaScript الموضحين أدناه.

## الملفات
| الملف | مكانه في المشروع |
|---|---|
| `ShowGeneralRegisrationController.php` | `app/Http/Controllers/Users/` |
| `PortalSaveProgress.php` | `app/Support/PortalSaveProgress.php` |
| `portal-save-progress.js` | `public/js/portal-save-progress.js` |

## 1) المسار (routes/web.php)
المسار مسجّل **خارج** مجموعة `auth`، ويُستعلم عنه بدون جلسة حتى لا ينتظر قفل جلسة طلب الحفظ:

```php
use App\Http\Controllers\Users\ShowGeneralRegisrationController;

Route::get('/users/generalRegistration/save-progress/{token}', [ShowGeneralRegisrationController::class, 'saveProgress'])
    ->where('token', '[A-Za-z0-9\-]{16,64}')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    ])
    ->name('user.generalRegistration.saveProgress');
```

## 2) الصفحة (Blade النموذج)
يُحمّل السكربت في نهاية نموذج التحديث، بعد JavaScript الخاص بالتحقق قبل الإرسال:

```blade
<script src="{{ asset('js/portal-save-progress.js') }}"></script>
```
ويحتاج وجود `<meta name="csrf-token" content="{{ csrf_token() }}">` في `<head>` (أو يكفي حقل `_token` داخل النموذج، فهو يُرسل ضمن FormData).

## 3) متطلبات
- الكاش يجب أن يكون مشتركاً بين الطلبات (`file` / `redis` / `database`) وليس `array`.
- إذا غيّرت رابط المسار: `window.PORTAL_SAVE_PROGRESS_URL = '/المسار/الجديد';` قبل تحميل الـ JS.

## كيف يعمل
1. المتصفح يرسل النموذج عبر XHR مع `progress_token` عشوائي ويحسب نسبة وسرعة الرفع الفعلية إلى السيرفر.
2. السيرفر يسجّل مراحله في الكاش: حفظ البيانات، ثم كل ملف (رفع إلى Drive/التخزين)، ثم تحديث الحالة وإنشاء التقرير.
3. المتصفح يستعلم كل 0.8 ثانية ويعرض: النسبة، السرعة، عدد الملفات، الحجم المرفوع، والرسالة الحالية.
4. عند النجاح يعيد السيرفر JSON فيه `redirect` إلى صفحة الشكر. عند الخطأ تظهر الرسالة ويبقى النموذج بمدخلاته.
