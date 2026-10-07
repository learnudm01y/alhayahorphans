# دليل ربط الواتساب (WhatsApp Chatbot) بموقع الأيتام – Laravel

> بوت واتساب لتسجيل الأوصياء والأيتام، وفحص النواقص ومتابعتها تلقائياً، على **WhatsApp Cloud API (Sandbox)** وبدون أي تكلفة في مرحلة التجربة.

---

## 1. نظرة عامة

| العنصر | القرار |
|---|---|
| الإطار | Laravel (يُفضّل 10 أو 11+) / PHP 8.1+ |
| قناة الواتساب | Meta WhatsApp Cloud API – رقم تجريبي (Test Number) |
| حفظ الجلسة | `Laravel Cache` (مدة الجلسة 30 دقيقة = 1800 ثانية) |
| تحميل الملفات | Media API من Meta، ويُحفظ في `storage/app/public` |
| الربط أثناء التطوير | Ngrok (أو Expose) |
| الذكاء الاصطناعي (اختياري) | Google Gemini – فحص الأوراق، ليس ضرورياً لتشغيل البوت |

**مسار الرسالة:**

```
مستخدم واتساب ─> Meta Cloud API ─> POST /api/whatsapp/webhook
                                        │
                              WhatsAppBotController
                                        │
                  Cache (الخطوة الحالية + البيانات المؤقتة)
                                        │
                  MySQL (OrphanApplication / ApplicationOrphan)
```

---

## 2. تجهيز حساب Meta (مرة واحدة)

1. ادخل إلى <https://developers.facebook.com> وأنشئ تطبيقاً من نوع **Business**.
2. أضف منتج **WhatsApp** للتطبيق.
3. من **WhatsApp > API Setup** ستجد:
   - **Phone Number ID** (رقم الاختبار).
   - **Temporary Access Token** (صالح 24 ساعة فقط للتجربة).
4. أضف أرقام المستلمين في خانة **To** وفعّلها برمز التحقق. الرقم التجريبي يسمح بعدد محدود من الأرقام المسجّلة (عادة 5)، فاختر أرقام فريق الاختبار.
5. للحصول على **توكن دائم** بدون انتهاء: أنشئ **System User** من Business Settings وأعطه صلاحيات `whatsapp_business_messaging` و `whatsapp_business_management`.
6. من **App Settings > Basic** انسخ **App Secret** (للتحقق من توقيع الطلبات).

---

## 3. الربط المحلي عبر Ngrok

```bash
php artisan serve                 # يعمل على 8000
ngrok http 8000                   # سينتج رابط https://xxxx.ngrok-free.app
```

في لوحة Meta: **WhatsApp > Configuration > Webhook**

- **Callback URL:** `https://xxxx.ngrok-free.app/api/whatsapp/webhook`
- **Verify Token:** أي نص سري تختاره (نفس القيمة في `.env`)
- اشترك في حقل **messages**.

> الرابط المجاني في Ngrok يتغير كلما أعدت تشغيله، فعدّل الـ Callback URL عند ذلك.

---

## 4. الإعدادات

`.env`

```env
WHATSAPP_TOKEN=EAAG...
WHATSAPP_PHONE_NUMBER_ID=1234567890
WHATSAPP_VERIFY_TOKEN=my_secret_verify_token
WHATSAPP_APP_SECRET=xxxxxxxxxxxxxxxx
WHATSAPP_API_VERSION=v21.0

CACHE_STORE=database      # أو redis / file  (لا تستخدم array)
```

`config/services.php`

```php
'whatsapp' => [
    'token'        => env('WHATSAPP_TOKEN'),
    'phone_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret'   => env('WHATSAPP_APP_SECRET'),
    'version'      => env('WHATSAPP_API_VERSION', 'v21.0'),
],
```

لو تستخدم `CACHE_STORE=database`:

```bash
php artisan cache:table && php artisan cache:lock-table 2>/dev/null; php artisan migrate
```

---

## 5. قاعدة البيانات

```bash
php artisan make:model OrphanApplication -m
php artisan make:model ApplicationOrphan -m
```

**Migration: orphan_applications**

```php
Schema::create('orphan_applications', function (Blueprint $t) {
    $t->id();
    $t->string('whatsapp_number')->index();
    $t->string('guardian_national_id')->index();
    $t->string('guardian_name');
    $t->string('address');
    $t->string('mobile_number');
    $t->string('city');
    $t->string('guardian_id_photo_path');
    $t->string('guardianship_doc_path')->nullable();
    $t->string('custody_agreement_path');
    $t->string('deceased_national_id');
    $t->string('deceased_name');
    $t->date('date_of_death');
    $t->string('death_certificate_photo_path');
    $t->string('status')->default('pending');
    $t->timestamps();
});
```

**Migration: application_orphans**

```php
Schema::create('application_orphans', function (Blueprint $t) {
    $t->id();
    $t->foreignId('orphan_application_id')->constrained('orphan_applications')->cascadeOnDelete();
    $t->string('orphan_name');
    $t->string('orphan_national_id');
    $t->date('orphan_dob');
    $t->unsignedTinyInteger('orphan_gender');          // 1 ذكر، 2 أنثى
    $t->string('birth_certificate_photo_path')->nullable();
    $t->string('personal_photo_path')->nullable();
    $t->timestamps();
});
```

**Models**

```php
// app/Models/OrphanApplication.php
class OrphanApplication extends Model
{
    protected $guarded = [];
    public function orphans() { return $this->hasMany(ApplicationOrphan::class); }
}

// app/Models/ApplicationOrphan.php
class ApplicationOrphan extends Model
{
    protected $guarded = [];
    public function application() { return $this->belongsTo(OrphanApplication::class, 'orphan_application_id'); }
}
```

---

## 6. المسارات (Routes)

`routes/api.php` (مسارات الـ API لا تخضع لـ CSRF)

```php
use App\Http\Controllers\WhatsAppBotController;

Route::get('/whatsapp/webhook',  [WhatsAppBotController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppBotController::class, 'handleWebhook']);
```

> إذا كان مشروعك على Laravel 11+ ولا يوجد `routes/api.php`: نفّذ `php artisan install:api`.

---

## 7. خدمة الواتساب `WhatsAppService`

`app/Services/WhatsAppService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppService
{
    private const MAX_BYTES = 10 * 1024 * 1024; // 10MB
    private const MIME_EXT = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    private function base(): string
    {
        return 'https://graph.facebook.com/' . config('services.whatsapp.version');
    }

    public function sendMessage(string $phone, string $message): void
    {
        $res = Http::withToken(config('services.whatsapp.token'))
            ->timeout(15)->retry(2, 300)
            ->post($this->base() . '/' . config('services.whatsapp.phone_id') . '/messages', [
                'messaging_product' => 'whatsapp',
                'to'   => $phone,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $message],
            ]);

        if ($res->failed()) {
            throw new RuntimeException('WhatsApp send failed: ' . $res->body());
        }
    }

    /** يرجع المسار النسبي داخل disk public مثل: guardian_ids/abc123.jpg */
    public function downloadMedia(string $mediaId, string $folderName): string
    {
        $token = config('services.whatsapp.token');

        // 1) جلب رابط الملف
        $meta = Http::withToken($token)->timeout(15)->retry(2, 300)
            ->get($this->base() . '/' . $mediaId);
        if ($meta->failed() || ! $meta->json('url')) {
            throw new RuntimeException('Media meta fetch failed');
        }

        $mime = $meta->json('mime_type');
        if (! isset(self::MIME_EXT[$mime])) {
            throw new RuntimeException('Unsupported mime: ' . $mime);
        }

        // 2) تحميل الملف إلى ملف مؤقت (stream) ثم نقله للتخزين
        $tmp = tempnam(sys_get_temp_dir(), 'wa_');
        try {
            $file = Http::withToken($token)->timeout(60)
                ->withOptions(['sink' => $tmp])
                ->get($meta->json('url'));

            if ($file->failed()) {
                throw new RuntimeException('Media download failed');
            }
            if (filesize($tmp) > self::MAX_BYTES) {
                throw new RuntimeException('File too large');
            }

            $name = Str::uuid() . '.' . self::MIME_EXT[$mime];
            Storage::disk('public')->putFileAs($folderName, new \Illuminate\Http\File($tmp), $name);

            return $folderName . '/' . $name;
        } finally {
            @unlink($tmp);
        }
    }
}
```

---

## 8. الكنترولر الكامل `WhatsAppBotController`

`app/Http/Controllers/WhatsAppBotController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\ApplicationOrphan;
use App\Models\OrphanApplication;
use App\Services\WhatsAppService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppBotController extends Controller
{
    private const TTL = 1800; // 30 دقيقة

    public function __construct(private WhatsAppService $wa) {}

    /* =====================================================
     |  GET: تحقق Meta من الـ Webhook
     ===================================================== */
    public function verify(Request $request)
    {
        if ($request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') === config('services.whatsapp.verify_token')) {
            return response($request->query('hub_challenge'), 200);
        }
        return response('Forbidden', 403);
    }

    /* =====================================================
     |  POST: استقبال الرسائل
     ===================================================== */
    public function handleWebhook(Request $request)
    {
        if (! $this->validSignature($request)) {
            return response('Invalid signature', 403);
        }

        $msg = $request->input('entry.0.changes.0.value.messages.0');
        if (! $msg) {
            return response('OK', 200); // حالات التسليم/القراءة (statuses) نتجاهلها
        }

        // منع معالجة نفس الرسالة مرتين (Meta تعيد الإرسال عند التأخر)
        if (! Cache::add('wa_msg_' . $msg['id'], 1, 3600)) {
            return response('OK', 200);
        }

        $phone   = $msg['from'];
        $type    = $msg['type'] ?? 'text';
        $input   = $this->normalizeDigits(trim($msg['text']['body'] ?? ''));
        $mediaId = $msg['image']['id'] ?? $msg['document']['id'] ?? null;

        // قفل لكل رقم حتى لا تتداخل رسالتان متتاليتان
        $lock = Cache::lock("bot_lock_{$phone}", 15);
        try {
            $lock->block(5);
            $this->process($phone, $type, $input, $mediaId);
        } catch (Throwable $e) {
            Log::error('WhatsApp bot error', ['phone' => $phone, 'error' => $e->getMessage()]);
            $this->safeSend($phone, "⚠️ حدث خطأ غير متوقع. حاول مرة أخرى، أو أرسل (0) للبدء من جديد.");
        } finally {
            optional($lock)->release();
        }

        return response('OK', 200); // دائماً 200 حتى لا تعيد Meta الإرسال
    }

    /* =====================================================
     |  آلة الحالات (State Machine)
     ===================================================== */
    private function process(string $phone, string $type, string $input, ?string $mediaId): void
    {
        // أمر الإلغاء/البداية من جديد
        if (in_array($input, ['0', 'إلغاء', 'الغاء'], true)) {
            $this->purge($phone);
            $this->send($phone, "تم إلغاء العملية. أرسل أي رسالة للبدء من جديد.");
            return;
        }

        $step = (string) Cache::get("bot_step_{$phone}", '1');

        switch ($step) {

            /* ---------- الجزء 1: بوابة التحقق ---------- */
            case '1':
                $this->ask($phone, '2', "أهلاً بك في نظام تسجيل الأيتام 🌸\nيرجى إدخال *رقم هوية الوصي* (9 أرقام):\n\n(أرسل 0 في أي وقت للإلغاء)");
                return;

            case '2':
                if (! $this->isNationalId($input)) {
                    $this->send($phone, "❌ رقم الهوية غير صحيح. أدخل 9 أرقام فقط:");
                    return;
                }

                $app = OrphanApplication::with('orphans')
                    ->where('guardian_national_id', $input)
                    ->where('whatsapp_number', $phone)
                    ->first();

                if (! $app) {                                   // CASE C: مستخدم جديد
                    $this->put($phone, 'guardian_national_id', $input);
                    $this->ask($phone, '3', "لم نجد طلباً سابقاً. لنبدأ التسجيل ✍️\nأدخل *اسم الوصي الكامل*:");
                    return;
                }

                $this->put($phone, 'existing_app_id', $app->id);

                $incomplete = $app->orphans->first(
                    fn ($o) => is_null($o->birth_certificate_photo_path) || is_null($o->personal_photo_path)
                );

                if ($incomplete) {                              // CASE A: ملف ناقص
                    $this->put($phone, 'incomplete_orphan_id', $incomplete->id);
                    $this->ask($phone, 'get_incomplete_birth_cert',
                        "وجدنا طلباً مسجلاً باسمك، لكن مستندات اليتيم *{$incomplete->orphan_name}* غير مكتملة.\nيرجى إرسال صورة *شهادة الميلاد* الآن:");
                    return;
                }

                // CASE B: ملف مكتمل
                $names = $app->orphans->values()
                    ->map(fn ($o, $i) => ($i + 1) . '. ' . $o->orphan_name)->implode("\n");
                $this->ask($phone, 'existing_choice',
                    "طلبك مكتمل ✅\nالأيتام المسجلون:\n{$names}\n\nأرسل:\n(1) لإضافة يتيم جديد\n(2) لتعديل بيانات الوصي");
                return;

            case 'existing_choice':
                if ($input === '1') {
                    $this->put($phone, 'mode', 'add_orphan');
                    $this->ask($phone, '14', "أدخل *اسم اليتيم الكامل*:");
                } elseif ($input === '2') {
                    $this->put($phone, 'mode', 'edit_guardian');
                    $this->ask($phone, '3', "أدخل *اسم الوصي الكامل* الجديد:");
                } else {
                    $this->send($phone, "❌ اختيار غير صحيح. أرسل (1) أو (2) فقط.");
                }
                return;

            /* ---------- الجزء 2: استكمال النواقص ---------- */
            case 'get_incomplete_birth_cert':
                $path = $this->receiveMedia($phone, $type, $mediaId, 'orphans/birth_certificates');
                if (! $path) return;
                $this->put($phone, 'inc_birth_path', $path);
                $this->ask($phone, 'get_incomplete_personal_photo', "تم الاستلام ✅\nالآن أرسل *الصورة الشخصية* لليتيم:");
                return;

            case 'get_incomplete_personal_photo':
                $path = $this->receiveMedia($phone, $type, $mediaId, 'orphans/personal_photos');
                if (! $path) return;

                $orphan = ApplicationOrphan::findOrFail($this->get($phone, 'incomplete_orphan_id'));
                $orphan->update([                               // تحديث الحقلين فقط، بدون حذف أو إنشاء
                    'birth_certificate_photo_path' => $this->get($phone, 'inc_birth_path'),
                    'personal_photo_path'          => $path,
                ]);

                // هل يوجد يتيم آخر ناقص؟
                $next = ApplicationOrphan::where('orphan_application_id', $orphan->orphan_application_id)
                    ->where(fn ($q) => $q->whereNull('birth_certificate_photo_path')->orWhereNull('personal_photo_path'))
                    ->first();

                if ($next) {
                    $this->put($phone, 'incomplete_orphan_id', $next->id);
                    $this->ask($phone, 'get_incomplete_birth_cert',
                        "تم حفظ مستندات {$orphan->orphan_name} ✅\nمستندات *{$next->orphan_name}* ناقصة أيضاً. أرسل صورة *شهادة الميلاد*:");
                    return;
                }

                $this->purge($phone);
                $this->send($phone, "🎉 تم استكمال جميع المستندات بنجاح. ملفك الآن مكتمل 100%. شكراً لك!");
                return;

            /* ---------- الجزء 3: بيانات الوصي ---------- */
            case '3':
                if ($input === '') { $this->send($phone, "❌ أدخل اسم الوصي كتابةً:"); return; }
                $this->put($phone, 'guardian_name', $input);
                $this->ask($phone, '4', "أدخل *العنوان الكامل*:");
                return;

            case '4':
                if ($input === '') { $this->send($phone, "❌ أدخل العنوان كتابةً:"); return; }
                $this->put($phone, 'address', $input);
                $this->ask($phone, '5', "أدخل *رقم الجوال* (أرقام فقط):");
                return;

            case '5':
                if (! preg_match('/^\+?\d{9,15}$/', $input)) {
                    $this->send($phone, "❌ رقم الجوال غير صحيح. أدخل أرقاماً فقط (9 إلى 15 رقم):");
                    return;
                }
                $this->put($phone, 'mobile_number', $input);
                $this->ask($phone, '6', "أدخل *المدينة*:");
                return;

            case '6':
                if ($input === '') { $this->send($phone, "❌ أدخل اسم المدينة كتابةً:"); return; }
                $this->put($phone, 'city', $input);

                // وضع تعديل بيانات الوصي: نحدّث ونخرج
                if ($this->get($phone, 'mode') === 'edit_guardian') {
                    OrphanApplication::findOrFail($this->get($phone, 'existing_app_id'))->update([
                        'guardian_name' => $this->get($phone, 'guardian_name'),
                        'address'       => $this->get($phone, 'address'),
                        'mobile_number' => $this->get($phone, 'mobile_number'),
                        'city'          => $this->get($phone, 'city'),
                    ]);
                    $this->purge($phone);
                    $this->send($phone, "✅ تم تحديث بيانات الوصي بنجاح.");
                    return;
                }

                $this->ask($phone, '7', "أرسل صورة *هوية الوصي* (صورة أو ملف PDF):");
                return;

            case '7':
                $path = $this->receiveMedia($phone, $type, $mediaId, 'guardians/ids');
                if (! $path) return;
                $this->put($phone, 'guardian_id_photo_path', $path);
                $this->ask($phone, '8', "أرسل *وثيقة الوصاية*.\nإن لم تكن موجودة اكتب: لا يوجد");
                return;

            case '8':
                if ($type === 'text' && $input === 'لا يوجد') {
                    $this->put($phone, 'guardianship_doc_path', null);
                } else {
                    $path = $this->receiveMedia($phone, $type, $mediaId, 'guardians/guardianship');
                    if (! $path) return;
                    $this->put($phone, 'guardianship_doc_path', $path);
                }
                $this->ask($phone, '9', "أرسل صورة *اتفاقية الحضانة*:");
                return;

            case '9':
                $path = $this->receiveMedia($phone, $type, $mediaId, 'guardians/custody');
                if (! $path) return;
                $this->put($phone, 'custody_agreement_path', $path);
                $this->ask($phone, '10', "أدخل *رقم هوية المتوفى* (9 أرقام):");
                return;

            /* ---------- بيانات المتوفى ---------- */
            case '10':
                if (! $this->isNationalId($input)) {
                    $this->send($phone, "❌ رقم الهوية غير صحيح. أدخل 9 أرقام فقط:");
                    return;
                }
                $this->put($phone, 'deceased_national_id', $input);
                $this->ask($phone, '11', "أدخل *اسم المتوفى الكامل*:");
                return;

            case '11':
                if ($input === '') { $this->send($phone, "❌ أدخل الاسم كتابةً:"); return; }
                $this->put($phone, 'deceased_name', $input);
                $this->ask($phone, '12', "أدخل *تاريخ الوفاة* بالصيغة YYYY-MM-DD\nمثال: 2023-05-21");
                return;

            case '12':
                if (! $this->validDate($input)) {
                    $this->send($phone, "❌ تاريخ غير صحيح. استخدم الصيغة YYYY-MM-DD مثل 2023-05-21:");
                    return;
                }
                $this->put($phone, 'date_of_death', $input);
                $this->ask($phone, '13', "أرسل صورة *شهادة الوفاة*:");
                return;

            case '13':
                $path = $this->receiveMedia($phone, $type, $mediaId, 'deceased/death_certificates');
                if (! $path) return;
                $this->put($phone, 'death_certificate_photo_path', $path);
                $this->ask($phone, '14', "الآن بيانات الأيتام 👶\nأدخل *اسم اليتيم الكامل*:");
                return;

            /* ---------- حلقة الأيتام (14 → 19 → 20) ---------- */
            case '14':
                if ($input === '') { $this->send($phone, "❌ أدخل اسم اليتيم كتابةً:"); return; }
                $this->put($phone, 'temp_orphan', ['orphan_name' => $input]);
                $this->ask($phone, '15', "أدخل *رقم هوية اليتيم* (9 أرقام):");
                return;

            case '15':
                if (! $this->isNationalId($input)) {
                    $this->send($phone, "❌ رقم الهوية غير صحيح. أدخل 9 أرقام فقط:");
                    return;
                }
                $this->putTemp($phone, 'orphan_national_id', $input);
                $this->ask($phone, '16', "أدخل *تاريخ ميلاد اليتيم* بالصيغة YYYY-MM-DD:");
                return;

            case '16':
                if (! $this->validDate($input)) {
                    $this->send($phone, "❌ تاريخ غير صحيح. استخدم الصيغة YYYY-MM-DD:");
                    return;
                }
                $this->putTemp($phone, 'orphan_dob', $input);
                $this->ask($phone, '17', "أدخل *الجنس*:\n(1) ذكر\n(2) أنثى");
                return;

            case '17':
                if (! in_array($input, ['1', '2'], true)) {
                    $this->send($phone, "❌ أرسل (1) للذكر أو (2) للأنثى فقط.");
                    return;
                }
                $this->putTemp($phone, 'orphan_gender', (int) $input);
                $this->ask($phone, '18', "أرسل صورة *شهادة ميلاد اليتيم*:");
                return;

            case '18':
                $path = $this->receiveMedia($phone, $type, $mediaId, 'orphans/birth_certificates');
                if (! $path) return;
                $this->putTemp($phone, 'birth_certificate_photo_path', $path);
                $this->ask($phone, '19', "أرسل *الصورة الشخصية* لليتيم:");
                return;

            case '19':
                $path = $this->receiveMedia($phone, $type, $mediaId, 'orphans/personal_photos');
                if (! $path) return;

                $child = $this->get($phone, 'temp_orphan', []);
                $child['personal_photo_path'] = $path;

                $list   = $this->get($phone, 'orphans_list', []);
                $list[] = $child;
                $this->put($phone, 'orphans_list', $list);
                $this->put($phone, 'temp_orphan', null);        // تفريغ المؤقت

                $this->ask($phone, '20', "تم حفظ بيانات اليتيم ✅\nهل تريد إضافة يتيم آخر لهذا الطلب؟\nأجب بـ (نعم) أو (لا)");
                return;

            case '20':
                if ($input === 'نعم') {
                    $this->ask($phone, '14', "أدخل *اسم اليتيم التالي*:");
                    return;
                }
                if ($input !== 'لا') {
                    $this->send($phone, "❌ أجب بـ (نعم) أو (لا) فقط.");
                    return;
                }
                $this->finalize($phone);
                return;

            default:
                // حالة غير معروفة (كاش تالف أو انتهت الجلسة): نبدأ من جديد
                $this->purge($phone);
                $this->ask($phone, '2', "لنبدأ من جديد. أدخل *رقم هوية الوصي*:");
                return;
        }
    }

    /* =====================================================
     |  الحفظ النهائي (Transaction)
     ===================================================== */
    private function finalize(string $phone): void
    {
        $list = $this->get($phone, 'orphans_list', []);
        if (empty($list)) {
            $this->ask($phone, '14', "لم يتم إدخال أي يتيم. أدخل *اسم اليتيم*:");
            return;
        }

        try {
            $appId = DB::transaction(function () use ($phone, $list) {
                $existingId = $this->get($phone, 'existing_app_id');

                if ($existingId) {
                    $parentId = OrphanApplication::findOrFail($existingId)->id;
                } else {
                    $parentId = OrphanApplication::create([
                        'whatsapp_number'              => $phone,
                        'guardian_national_id'         => $this->get($phone, 'guardian_national_id'),
                        'guardian_name'                => $this->get($phone, 'guardian_name'),
                        'address'                      => $this->get($phone, 'address'),
                        'mobile_number'                => $this->get($phone, 'mobile_number'),
                        'city'                         => $this->get($phone, 'city'),
                        'guardian_id_photo_path'       => $this->get($phone, 'guardian_id_photo_path'),
                        'guardianship_doc_path'        => $this->get($phone, 'guardianship_doc_path'),
                        'custody_agreement_path'       => $this->get($phone, 'custody_agreement_path'),
                        'deceased_national_id'         => $this->get($phone, 'deceased_national_id'),
                        'deceased_name'                => $this->get($phone, 'deceased_name'),
                        'date_of_death'                => $this->get($phone, 'date_of_death'),
                        'death_certificate_photo_path' => $this->get($phone, 'death_certificate_photo_path'),
                        'status'                       => 'pending',
                    ])->id;
                }

                foreach ($list as $o) {
                    ApplicationOrphan::create([
                        'orphan_application_id'        => $parentId,
                        'orphan_name'                  => $o['orphan_name'],
                        'orphan_national_id'           => $o['orphan_national_id'],
                        'orphan_dob'                   => $o['orphan_dob'],
                        'orphan_gender'                => $o['orphan_gender'],
                        'birth_certificate_photo_path' => $o['birth_certificate_photo_path'],
                        'personal_photo_path'          => $o['personal_photo_path'],
                    ]);
                }

                return $parentId;
            });
        } catch (Throwable $e) {
            Log::error('WhatsApp finalize failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            // نبقي الحالة على 20 والبيانات في الكاش ليعيد المستخدم المحاولة بإرسال (لا)
            $this->send($phone, "⚠️ تعذر حفظ الطلب الآن. أرسل (لا) للمحاولة مرة أخرى.");
            return;
        }

        $this->purge($phone);
        $this->send($phone, "🎉 تم تسجيل طلبك بنجاح!\nرقم الطلب: *#{$appId}*\nسيتم مراجعته من قبل الجمعية.");
    }

    /* =====================================================
     |  دوال مساعدة
     ===================================================== */
    private function receiveMedia(string $phone, string $type, ?string $mediaId, string $folder): ?string
    {
        if (! in_array($type, ['image', 'document'], true) || ! $mediaId) {
            $this->send($phone, "❌ يرجى إرسال *صورة أو ملف PDF* وليس نصاً.");
            return null;
        }
        try {
            return $this->wa->downloadMedia($mediaId, $folder);
        } catch (Throwable $e) {
            Log::warning('Media download failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            $this->send($phone, "❌ تعذر استلام الملف (الأنواع المقبولة: JPG, PNG, PDF حتى 10MB). أعد الإرسال.");
            return null;
        }
    }

    private function ask(string $phone, string $nextStep, string $message): void
    {
        Cache::put("bot_step_{$phone}", $nextStep, self::TTL);
        $this->send($phone, $message);
    }

    /** كل بيانات الجلسة في مصفوفة واحدة داخل الكاش: reg_data_{phone} */
    private function get(string $phone, string $key, $default = null)
    {
        return (Cache::get("reg_data_{$phone}", []))[$key] ?? $default;
    }

    private function put(string $phone, string $key, $value): void
    {
        $data = Cache::get("reg_data_{$phone}", []);
        $data[$key] = $value;
        Cache::put("reg_data_{$phone}", $data, self::TTL);
        Cache::put("bot_step_{$phone}", Cache::get("bot_step_{$phone}", '1'), self::TTL); // تجديد المدة
    }

    private function putTemp(string $phone, string $key, $value): void
    {
        $temp = $this->get($phone, 'temp_orphan', []);
        $temp[$key] = $value;
        $this->put($phone, 'temp_orphan', $temp);
    }

    private function purge(string $phone): void
    {
        Cache::forget("bot_step_{$phone}");
        Cache::forget("reg_data_{$phone}");
    }

    private function send(string $phone, string $message): void
    {
        $this->wa->sendMessage($phone, $message);
    }

    private function safeSend(string $phone, string $message): void
    {
        try { $this->wa->sendMessage($phone, $message); } catch (Throwable $e) { /* تجاهل */ }
    }

    private function isNationalId(string $v): bool
    {
        return (bool) preg_match('/^\d{9}$/', $v); // عدّل الشرط إذا اختلف طول الهوية
    }

    private function validDate(string $v): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v && $d <= new DateTime('today');
    }

    /** تحويل الأرقام العربية والهندية إلى لاتينية */
    private function normalizeDigits(string $s): string
    {
        return strtr($s, [
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
    }

    private function validSignature(Request $request): bool
    {
        $secret = config('services.whatsapp.app_secret');
        if (! $secret) return true; // للتجربة فقط؛ فعّله دائماً في الإنتاج
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, (string) $request->header('X-Hub-Signature-256'));
    }
}
```

---

## 9. مخطط الخطوات

| الخطوة | المطلوب | التحقق |
|---|---|---|
| 1 | رسالة ترحيب + طلب هوية الوصي | – |
| 2 | **البوابة**: A ناقص / B مكتمل / C جديد | 9 أرقام |
| `existing_choice` | 1 إضافة يتيم، 2 تعديل بيانات الوصي | 1 أو 2 |
| `get_incomplete_birth_cert` → `get_incomplete_personal_photo` | استكمال النواقص (تحديث فقط) | صورة/PDF |
| 3–6 | اسم الوصي، العنوان، الجوال، المدينة | الجوال أرقام |
| 7–9 | هوية الوصي، وثيقة الوصاية (يمكن "لا يوجد")، اتفاقية الحضانة | ملف |
| 10–13 | هوية المتوفى، اسمه، تاريخ الوفاة، شهادة الوفاة | تاريخ YYYY-MM-DD |
| 14–19 | اسم اليتيم، هويته، ميلاده، جنسه، شهادة ميلاده، صورته | الجنس 1/2 |
| 20 | نعم ← 14، لا ← حفظ في Transaction + مسح الكاش + رقم الطلب | نعم/لا |

---

## 10. الاختبار

1. شغّل `php artisan serve` و `ngrok http 8000` وسجّل الـ Webhook.
2. أرسل أي رسالة من رقم مسجّل كمستلم في Meta.
3. اختبر هذه السيناريوهات:
   - [ ] مستخدم جديد، يتيم واحد، حتى رقم الطلب.
   - [ ] مستخدم جديد، 3 أيتام (حلقة نعم/لا).
   - [ ] كتابة نص في خطوة تنتظر صورة، والتأكد أن الخطوة لا تتغير.
   - [ ] "لا يوجد" في وثيقة الوصاية، وحفظها `NULL`.
   - [ ] تاريخ خاطئ، جنس خاطئ، هوية خاطئة.
   - [ ] وصي موجود وناقص: يُطلب الناقص فقط ويُحدَّث بدون إنشاء سجل جديد.
   - [ ] وصي موجود ومكتمل: خيار (1) إضافة يتيم، خيار (2) تعديل البيانات.
   - [ ] أكثر من يتيم ناقص (يتكرر الطلب لكل يتيم).
   - [ ] انتهاء الجلسة بعد 30 دقيقة (غيّر TTL مؤقتاً لدقيقة للاختبار).
   - [ ] إرسال (0) للإلغاء.
4. راقب السجلات: `tail -f storage/logs/laravel.log`.

---

## 11. ملاحظات مهمة وتصحيحات على تقرير الجدوى

هذه نقاط يُنصح بمراجعتها قبل عرض التقرير على المدير، لأن شروط Meta وGoogle تتغير باستمرار، فتحقق منها من صفحاتهم الرسمية:

- **الرقم التجريبي**: يرسل ويستقبل فقط مع أرقام مضافة ومؤكدة في لوحة المطورين (حد صغير، عادة 5). لذلك لا يصلح لـ "عينة من الأوصياء" الحقيقيين. للتوسع تحتاج رقماً حقيقياً مضافاً للحساب.
- **"1000 محادثة مجانية"**: سياسة التسعير تغيّرت من نموذج المحادثات إلى نموذج الرسائل، فراجع صفحة التسعير الرسمية الحالية قبل ذكر أرقام للإدارة. الردود ضمن نافذة الـ 24 ساعة بعد رسالة المستخدم غالباً أرخص أو مجانية، وهذا ما يناسب هذا البوت.
- **"محمي تماماً من الحظر"**: صياغة مبالغ فيها. الالتزام بسياسات Meta هو ما يحمي الحساب.
- **Gemini 1.5 Flash**: تم إيقاف سلسلة 1.5، فاستخدم نموذج Flash الحالي من Google AI Studio وراجع حدود الاستخدام المجاني. وغالباً أن مستخدمي الطبقة المجانية قد تُستخدم بياناتهم لتحسين النماذج، وهذا **غير مناسب لصور هويات الأيتام والأوصياء**. لا ترسل بيانات حساسة للـ API المجاني.
- **توثيق الحساب**: توثيق الـ Business على Meta مجاني، لكنه يتطلب مستندات رسمية للجمعية وقد يستغرق وقتاً.

---

## 12. الأمان والخصوصية (مهم جداً: بيانات أيتام وهويات)

1. **لا تخزّن الصور الحساسة على disk `public`** في الإنتاج. أي شخص يعرف الرابط يستطيع فتحها. استخدم disk `local` (خاص) وقدّم الملفات عبر Route يتحقق من الصلاحيات أو Signed URLs. (الدليل يستخدم `public` فقط لأن المتطلبات حددت ذلك للتجربة.)
2. فعّل `WHATSAPP_APP_SECRET` للتحقق من توقيع كل طلب.
3. لا ترفع `.env` ولا التوكن على Git. دوّر التوكن إذا تسرّب.
4. أضف **موافقة صريحة** من الوصي على معالجة بياناته وبيانات الأيتام في أول رسالة.
5. أضف قيداً على التسجيل المتكرر: الشرط الحالي يطابق (الهوية + رقم الواتساب)، فنفس الهوية من رقم آخر ستُنشئ ملفاً جديداً. فكّر بجعل `guardian_national_id` فريداً أو مراجعة التكرار يدوياً.
6. استخدم **Rate Limiting** على مسار الـ webhook (`throttle`) وراقب الحجم.

---

## 13. الانتقال للإنتاج

| الآن (تجريبي) | الإنتاج |
|---|---|
| رقم Sandbox | رقم رسمي موثّق للجمعية |
| Ngrok | استضافة/VPS بـ HTTPS ودومين ثابت |
| توكن مؤقت | System User Token دائم |
| معالجة داخل الطلب مباشرة | تحويل `downloadMedia` والإرسال إلى **Queue** (Redis + Supervisor) لتسريع الرد على Meta |
| Cache: database/file | Redis |
| disk `public` | disk خاص + S3 مشفّر |

**ترتيب العمل المقترح:** تجهيز Meta ← Ngrok ← المايغريشن والموديلات ← الخدمة والكنترولر ← اختبار السيناريوهات ← مراجعة أمنية ← نقل للإنتاج.
