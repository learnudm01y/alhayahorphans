# دليل ربط الواتساب (WhatsApp Chatbot) بموقع الأيتام – Laravel

> **الحالة: منفَّذ ✅ (2026-10-08) — المراحل 1–8 كاملة.**
> هذه الوثيقة مواءمة لما نُفّذ فعلياً. المرجع المعتمد للقرارات وخريطة الملفات: `docs/WHATSAPP_IMPLEMENTATION_PLAN.md` (الذي رُفع حالته إلى "منفَّذ" ونتيجته في §16).

---

## 1. نظرة عامة

| العنصر | القرار المنفَّذ |
|---|---|
| الإطار | Laravel 10 / PHP 8.2 / MySQL 8 |
| قناة الواتساب | **Evolution API** (خادم خاص، بلا تكلفة Meta) — القرار10 في الخطة |
| المصادقة الواردة | حقل `apikey` داخل المغلّف (`WHATSAPP_API_KEY` + `hash_equals`) — رفض401 إن خاطئ، 503 إن فارغ |
| المصادقة الصادرة | ترويسة `apikey` في كل نداء لـEvolution |
| حفظ الجلسة | `Laravel Cache` (`bot_step_{phone}` + `reg_data_{phone}`، TTL 1800 ثانية متجددة) |
| المعالجة | استقبال200 فوري ← `WhatsAppInboundJob` (طابور `QUEUE_CONNECTION=database`) |
| مسار الحفظ | **ب1**: استدعاء `GeneralRegistrationController::store()` داخلياً بلا تعديل ملف واحد (بوابة §2.2) |
| الجداول | **لا جداول موازية** (قرار2: `orphan_applications`/`application_orphans` ملغاة) — التسجيل في جداول النظام نفسها |
| المرفقات | قرص `public` + `uploads/{file_id}` + جدول `attachments` (قرار3 — كما في الموقع) |
| الملفات المطلوبة | تُقرأ من جدول `document_types` الفعلية (لا قائمة صلبة) |
| الذكاء الاصطناعي | ملحق منفَّذ: `WhatsAppKnowledgeService` (Gemini) + مرشّح خصوصية + جدول `whatsapp_unanswered_questions` |
| الواجهة الإدارية | `/admin/whatsapp` + صلاحية `عرض طلبات الواتساب` |
| الاختبارات | 62 اختبار واتساب خضراء (7 حزم)؛ المشروع كاملاً 152 خضراء / 6 متوقفة / 2 فاشل سابقَين ممنوعَين |

**مسار الرسالة:**

```
مستخدم واتساب ─> Evolution API ─> POST /api/whatsapp/webhook  (مصادقة apikey)
                                       │
                        WhatsAppBotController (تحقق + dispatch)
                                       │
                        WhatsAppInboundJob  (طابور — 200 فوري)
                                       │
              WhatsAppFlowService (حوار) + WhatsAppMediaService (وسائط)
                                       │
     finalize() ─> Request::create + GeneralRegistrationController::store()  [ب1]
                                       │
   نفس جداول النظام: data / re_people / dead_people / portal_general_registration_field_values
                      / users / reserved_codes / attachments
                                       │
   WhatsAppService (sendText / getBase64FromMediaMessage) ─> Evolution ─> المستخدم
```

---

## 2. تجهيز Evolution API (مرة واحدة)

1. شغّل خادم Evolution API (Docker) على الخادم المحلي/الاستضافة وافتح لوحته.
2. أنشئ **Instance** واتساب وامسح رمز الربط برقم الجمعية.
3. من إعدادات الـInstance انسخ: **URL** (مثل `http://localhost:8080`) و**API Key** واسم الـInstance.
4. اضبط **Webhook** في لوحة Evolution:
   - **URL:** `{DOMAIN}/api/whatsapp/webhook`
   - الأحداث: `messages.upsert` و`messages.update` فقط.
   - **apikey:** يُرسل داخل المغلّف بنفس قيمة `WHATSAPP_API_KEY` (المصادقة إجبارية على الطرفين).
5. دومين HTTPS ثابت للإنتاج (المتغير محلي عبر ngrok — §3).

> نظام الرسائل الصادرة يمر عبر `/message/sendText/{instance}` والوسائط عبر `/chat/getBase64FromMediaMessage/{instance}` — ترويسة `apikey` في كليهما (انظر `WhatsAppService`).

---

## 3. الربط المحلي عبر Ngrok

```bash
php artisan serve                 # يعمل على 8000
ngrok http 8000                   # سينتج https://xxxx.ngrok-free.app
```

- عيّن Webhook في Evolution على `https://xxxx.ngrok-free.app/api/whatsapp/webhook`.
- شغّل عامل الطابور بجانبها: `php artisan queue:work --tries=1 --timeout=120` (الإنتاج: Supervisor — §13).
- الرابط المجاني يتغير كل تشغيل ⇒ عدّل Webhook URL عندئذ.

---

## 4. الإعدادات

`.env` (يضيفها المالك بنفسه):

```env
WHATSAPP_API_URL=http://localhost:8080
WHATSAPP_API_KEY=...
WHATSAPP_INSTANCE=...

GEMINI_API_KEY=...                # اختياري — ملحق الأسئلة (المرحلة7)
GEMINI_MODEL=gemini-2.5-flash     # اختياري — الافتراضي مضبوط في الكونفق

QUEUE_CONNECTION=database         # الوضع الحالي الفعلي
CACHE_DRIVER=file                 # يدعم Cache::lock على Laravel 10
```

`config/services.php` (الكتلتان المُنفَّذتان):

```php
'whatsapp' => [
    'base_url' => env('WHATSAPP_API_URL', 'http://localhost:8080'),
    'api_key'  => env('WHATSAPP_API_KEY'),
    'instance' => env('WHATSAPP_INSTANCE'),
],

'gemini' => [
    'key'   => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
],
```

---

## 5. قاعدة البيانات

**لا جداول موازية للتسجيل** (قرار2). التسجيل يكتب في جداول النظام عبر `store()`.

الجداول الجديدة الوحيدة للإضافة:

| الجدول | Migration | الغرض |
|---|---|---|
| `whatsapp_logs` | `2026_10_08_000001_create_whatsapp_logs_table.php` | كل رسالة واردة/صادرة: phone, direction, msg_id, type, text, media_id, status, error, payload json + فهارس phone/msg_id |
| `whatsapp_unanswered_questions` | `2026_10_08_000002_create_whatsapp_unanswered_questions_table.php` | أسئلة البوت بلا جواب (لراجعتها الإدارة) |

جداول النظام المستهدفة من `store()`: `data`, `re_people`, `dead_people`, `portal_general_registration_field_values`, `users`, `reserved_codes`, `attachments`.

---

## 6. المسارات (Routes)

`routes/api.php`:

```php
Route::post('/whatsapp/webhook', [WhatsAppBotController::class, 'handleWebhook'])
    ->middleware('throttle:300,1');
```

- **لا مسار GET تحقق** (حُذف مع التحول إلى Evolution — قرار §10 في الخطة).
- الويب هوك **مستثنى مُعلناً** من حد `api` العام (60/دقيقة) عبر `Limit::none()` في `RouteServiceProvider`، ويبقى `throttle:300,1` المخصص حامياً.

`routes/admin.php` (مجموعة واحدة أُضيفت بموافقة):

```php
Route::group(['prefix' => 'admin/whatsapp', 'as' => 'admin.whatsapp.'], function () {
    Route::get('/', [WhatsAppApplicationsController::class, 'index'])->name('index');
    Route::get('/{phone}', [WhatsAppApplicationsController::class, 'show'])->name('show');
});
```

- middleware: `auth` + `rolebreeze:admin` (المجموعة الرئيسية) + `permission:عرض طلبات الواتساب`.
- رابط فتح الملف داخل التفاصيل يستهدف `admin.records.management.show` (شاشة الكفالات مُفصولة عن الإضافة — قرار المالك).

---

## 7. خدمة الإرسال `WhatsAppService`

`app/Services/WhatsAppService.php` — نقاط النهاية المنفَّذة:

| الطريقة | Endpoint | ملاحظة |
|---|---|---|
| `sendText($phone, $text)` | `POST /message/sendText/{instance}` | يسجّل الصادر في `whatsapp_logs` (sent/failed) |
| `fetchMedia($messageId)` | `POST /chat/getBase64FromMediaMessage/{instance}` | base64 ← bytes + MIME (مع sniffing احتياطي) |
| `isConfigured()` | – | اكتمال المفاتيح الثلاثة |

خريطة MIME المقبولة للوسائط في `WhatsAppMediaService` (مرحلة4): `image/jpeg|png|webp|gif|heic|heif` + `application/pdf`، حد5MB (`5242880`)، بصمة SHA-1، كتابة مؤقتة في `storage/app/public/temp_uploads/whatsapp/{phone}/{messageId}.{ext}` ثم نقلها `store()` إلى `uploads/{file_id}`.

---

## 8. المحرك: `WhatsAppBotController` + `WhatsAppInboundJob`

**الكونترولر** (حواف فقط):
1. `WHATSAPP_API_KEY` فارغ ← 503. مفتاح غير مطابق ← 401 (`hash_equals`).
2. حدث `messages.upsert`/`messages.update` مع `data` مصفوفة ← `WhatsAppInboundJob::dispatch($event, $data)`.
3. رد فوري200 `{status: ok}` (المعالجة في الطابور).

**المهنة** (`WhatsAppInboundJob`، `$tries=1`، `$timeout=120`):
- تجاهل `fromMe` والجموع و`@lid` والأرقام غير الرقمية.
- idempotency: `whatsapp:done:{msgId}` (7 أيام) + `whatsapp:lock:{msgId}` + قفل لكل رقم (`Cache::lock('whatsapp:phone:{phone}')` بانتظار5 ثوانٍ).
- تسجيل الوارد في `whatsapp_logs` ← `flow->handle()` (نص) أو `flow->handleMedia()` (وسائط) ← تنظيف `temp_uploads/whatsapp/{phone}` إذا انتهت الجلسة ← إرسال الرد.
- `messages.update` ← تحديث حالة الصادر (`sent/delivered/read/failed` + رسالة خطأ عربية).

---

## 9. محرك الحوار `WhatsAppFlowService` + اللوحة الإدارية

**القائمة الرئيسية** (أول تواصل أو بعد `0`):

```
أهلاً بك في مؤسسة الأيتام.
(1) تسجيل أو تحديث ملف يتيم
(2) اسأل سؤالاً
(0) إلغاء
```

**خطوات التسجيل (1):**

| المجموعة | الخطوات | ملاحظات |
|---|---|---|
| بوابة الهوية | `reg_id` — هوية الوصي9 أرقام | موجود+مكتمل ← "مكتمل" وتحديث؛ موجود+ناقص ← استكمال؛ غير موجود ← جديد |
| بيانات الوصي | أ4 أسماء، ميلاد، جنس، جوال، عنوان، مدينة، محافظة | من `provinces` المرتّبة |
| الأم (3 حالات) | المعيل هي الأم ← نسخ من `data` / حية ← أ4 أسماء+هوية+ميلاد / متوفية ← `dead_people` + portal | `mother_choice` |
| الأب | حي/متوفى ← بيانات وشهادة وفاة | من `death_reasons` |
| الأيتام | حلقة: هوية9، أ4 أسماء، ميلاد، جنس1/2، صحة، نوع كفالة | من `sponsorship_statuses`؛ منع تكرار داخل الطلب |
| المرفقات | `reg_attachments` ← `reg_attach_person` ← `reg_attach_send` | قائمة من `document_types`؛ كلها معفاة من قاعدة `0=إلغاء`؛ حد10 ملفات؛ تكرار hash داخل الجلسة يُتخطى |
| الإنهاء | `finalize()` ← `store()` (ب1) + رقم ملف6 أرقام + رسالة نجاح | |

**خطوة الأسئلة (2):** `faq_mode` ← `WhatsAppKnowledgeService::answer()` — انظر `WHATSAPP_AI_KNOWLEDGE_ADDON.md` + §15 في الخطة (حدود10/ساعة لكل رقم، مرشّح خصوصية، تسجيل بلا جواب).

**الوسائط:** داخل خطوات المرفقات تُهضم فوراً؛ داخل `faq_mode` ← "اكتب سؤالك نصاً"؛ غير ذلك ← رسالة إكمال التسجيل.

**اللوحة الإدارية** (`/admin/whatsapp`): قائمة الطلبات (رقم الملف من سجل الإرسال + الاسم + الحالة) + تفاصيل محادثة/ملف/مرفقات + بحث بالهاتف + ترقيم صفحات — بصلاحية `عرض طلبات الواتساب` فقط (403 لغير المصرح، login للضيف).

---

## 10. الاختبار اليدوي (ngrok) — *المعدّل للمنفَّذ*

> الآلية الآلية مغطاة: `php artisan test` (152 خضراء). ما يلي للتشغيل الحي على بيئة حقيقية:

**التهيئة:** `php artisan serve` + `ngrok http 8000` + Webhook في Evolution + `php artisan queue:work --tries=1 --timeout=120` + `WHATSAPP_*` في `.env`.

- [ ] أول رسالة من رقم جديد ← القائمة الرئيسية.
- [ ] `(1)` تسجيل جديد كامل حتى رقم الملف6 أرقام ← التحقق في `data`/`re_people`/`users`/`attachments`.
- [ ] هوية موجودة مكتملة ← رسالة "مكتمل" وتحديث بلا ملف جديد.
- [ ] هوية موجودة ناقصة ← يُطلب الناقص فقط.
- [ ] نفس الهوية من رقم واتساب آخر ← تحديث نفس الملف (لا تكرار).
- [ ] الأم: المعيل هي الأم / حية / متوفية (3 محاولات).
- [ ] حلقة أيتام: اثنان + تكرار هوية يتيم داخل الطلب ← رفض مهذب.
- [ ] مرفقات: صورة PNG وPDF ينجحان ويظهران في `uploads/{file_id}`؛ صوت ← رفض؛ ملف >5MB ← رفض؛ نفس الملف مرتين ← "مُرسَل مسبقاً"؛ HEIC ينجح؛ `0` عند القائمة ينهي بلا مرفقات.
- [ ] `(0)` و`إلغاء` يمسحان الجلسة؛ انتهاء الجلسة بعد30 دقيقة.
- [ ] `(2)` ثم سؤال من `faq.md` ← جواب؛ سؤال بلا جواب ← رسالة اعتذار + صف في `whatsapp_unanswered_questions`؛ سؤال يحوي9 أرقام ← رفض بلا نداء Google؛ سؤال ثالث عشر (بعد10) ← رسالة حد الاستخدام.
- [ ] حالات التسليم: قراءة/تسليم/فشل عبر `messages.update` في `admin/whatsapp/show`.
- [ ] apikey خاطئ في المغلّف ← 401 (جرّب `curl -X POST` يدوياً).
- [ ] غير مصرح له بصلاحية الإدارة ← 403؛ زائر ← login.
- [ ] راقب: `tail -f storage/logs/laravel.log` + `php artisan queue:failed`.

---

## 11. ملاحظات تشغيلية

- **Evolution بدل Meta**: لا رقم تجريبي ولا حدّ5 أرقام ولا نافذة24 ساعة — التكاليف والأرقام تدار على خادمك. دوّر `apikey` لو تسرّب.
- **الوسائط المقبولة صوت/فيديو لا يُقبلان كمرفقات تسجيل** عمداً (قرار §4.3) رغم قبول `WhatsAppService` لتنزيلهما في سياقات أخرى.
- **Gemini**: حدود الطبقة المجانية تتغير — راجعها من AI Studio؛ الاسم قابل للتغيير بـ`GEMINI_MODEL`. لا تُرسل بيانات شخصية (متحقق بالكود §7.3).
- **`document_types`**: أضف/احذف وثائق من جدول `document_types` مباشرة — القائمة تتغير بلا تعديل كود.

---

## 12. الأمان والخصوصية (كما نُفّذ ومُختبَر)

1. **مصادقة الويب هوك إجبارية**: `apikey` عبر `hash_equals` + رفض503 عند غيار الإعداد — مغطى بـ`WhatsAppWebhookTest` (14 اختباراً).
2. **Throttling**: `throttle:300,1` على المسار + استثناء مُعلن من حد `api` (فرع `Limit::none()` في `RouteServiceProvider`).
3. **لا أسرار في Git**: `.env` غير متتبَّع، وفحص `git grep` على الملفات المتتبَّعة لا يعثر على مفاتيح حقيقية (AIza / قيم `*_API_KEY=` طويلة).
4. **المرفقات على قرص `public` + `uploads/{file_id}`** — كما في الموقع (قرار3 المعتمد)؛ الحد5MB + أنواع whitelisted منع رفع أي شيء آخر؛ `temp_uploads/whatsapp/*` يُنظَّف بعد انتهاء الجلسة.
5. **خصوصية الذكاء الاصطناعي**: أي مقطع ≥6 أرقام في السؤال يُرفض قبل أي نداء Google (`Http::assertNothingSent` مُختبَر)؛ يُرسل للنموذج سؤال المستخدم + `faq.md` العام فقط.
6. **حدود AI**:10 أسئلة/ساعة لكل رقم + حد عام120/دقيقة.
7. **مسار الحفظ معزول**: `store()` غير معدَّل إطلاقاً (شرط ذهبي1)؛ أي خلل في الإضافة لا يمس تسجيل الموقع — اختبارات الموقع محمية (الحزم الممنوعة تبقى2 فشلاً فقط قبل الإضافة وبعدها).
8. **الصلاحيات**: لوحة الواتساب خلف `permission:عرض طلبات الواتساب` (+403 مُختبَر)؛ مسارات الإدارة خلف `auth` + `rolebreeze:admin`.

---

## 13. الانتقال للإنتاج (خطوات المالك)

| الآن (تطوير) | الإنتاج |
|---|---|
| `php artisan queue:work` يدوياً | Supervisor: إعادة تشغيل تلقائي لـ`queue:work --tries=1 --timeout=120` |
| `QUEUE_CONNECTION=database` + `CACHE_DRIVER=file` (يعملان فعلاً) | اختياري: Redis للـcache والقفل (`QUEUE_CONNECTION=redis` + `CACHE_DRIVER=redis`) |
| ngrok رابط متغير | دومين HTTPS ثابت + Webhook عليه |
| خادم Evolution محلي | نفس الخادم أو استضافة مستقرة + نسخة احتياطية |
| `WHATSAPP_API_KEY`/`GEMINI_API_KEY` في `.env` محلياً | قيم إنتاج مخزّنة خارج Git + دورة استبدال عند التسرّب |

**Rollback:** حذف مسار webhook من `routes/api.php` + كتلة `whatsapp` من `config/services.php` يعطّل الإضافة كاملاً دون أثر على بقية الموقع (لا جداول موازية تُحذف، ولا ملف معدَّل خارج جرد الخطة).
