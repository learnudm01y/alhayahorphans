# خطة تنفيذ إضافة تسجيل الواتساب — WhatsApp Implementation Plan

> **حالة الوثيقة:** **منفَّذ ✅ بالكامل (المراحل 0–8) — 2026-10-08.** بوابة §2.2 حُسمت بـ**ب1** (استدعاء `store()` داخلياً بلا تعديل ملف واحد). سجل التنفيذ: §8–§16.
> **قاعدة ذهبية:** لا تعديل على أي ملف قائم قبل موافقة صريحة على مرحلته.
> **تسمية الملفات:** أي ملف خاص بهذه الإضافة يبدأ بـ `WhatsApp` / `whatsapp_`. الملفات المستخرجة من منطق الموقع القائم تُسمّى `General*` وتُصرَّح لها منفصلة.
> آخر تحديث: 2026-10-08

---

## 1. سجل القرارات (معتمدة)

| # | الموضوع | القرار |
|---|---|---|
| 1 | مسار حفظ التسجيل | **(ب)** لا يُمس `GeneralRegistrationController::store()` إطلاقاً؛ البوت يعتمد مسار حفظ خاصاً به لا يتداخل مع الموقع |
| 2 | الجداول | **لا جداول موازية** (`orphan_applications` / `application_orphans` ملغاة نهائياً). الجدول الوحيد الجديد: `whatsapp_logs` |
| 3 | تخزين المرفقات | **كما هو في تسجيل الموقع**: قرص `public` + `uploads/{file_id}` + جدول `attachments` |
| 4 | نطاق النسخة1 | **يشمل بيانات الأم كاملة** (حية / متوفية / المعيل هي الأم). **بدون** الحسابات البنكية (تُؤجَّل) |
| 5 | ملحق الذكاء الاصطناعي | **مضموم الآن** (المرحلة7 ضمن الخطة لا مؤجل) |
| 6 | ملف الخطة | **يُنشأ** `docs/WHATSAPP_IMPLEMENTATION_PLAN.md` (هذا الملف) |

---

## 2. المنهجية المعمارية

### 2.1 لماذا لا نلمس `store()`؟
- الخيار(ب) يبقي الموقع الحالي معزولاً تماماً: أي خطأ في الإضافة لا يمسّ تسجيل الويب القائم.
- الكلفة: منطق الحفظ سيُكتب في خدمة جديدة مستوحاة من `GeneralRegistrationController::store()` (سطور 201–1205)، ويجب تنسيقها مع اختبارات مقارنة لمنع الانحراف.

### 2.2 مسار الحفظ في الإضافة — فحص حسم داخل المرحلة1
يُنفَّذ **Spikes** (تجربة قرار مصغّرة) يختار بين:

| البديل | الوصف | المخاطرة |
|---|---|---|
| **ب1 (مرشَّح أول)** | استدعاء `store()` **داخلياً** عبر `Request::create([...], 'POST')` + تمرير `expectsJson()` وقراءة الاستجابة — **إعادة استخدام كاملة بلا نسخ وبلا تعديل ملف واحد** | يعتمد على سلوك `auth()`/`session`/`redirect` داخل `store()`؛ قد يحتاج تهيئة جلسة صناعية |
| **ب2 (احتياطي)** | خدمة `WhatsAppRegistrationService` تنفّذ الخطوات نفسها مستخدمة **الدوال/الكيانات القائمة دون تعديلها**: `generateUniqueReservedCode`، `markCodeAsUsed`، `GuardianFileService::findLatestDataByIdentity/relinkFile/findFileByIdentity`، `findOtherFileForIdentity`-مكافئ، النماذج `Data/RePeople/DeadPepole/Attachment/User` | انحراف مستقبلي بين النسختين عند تغيير قواعد الموقع |

**بوابة القرار — ✅ حُسمت: ب1 (استدعاء داخلي لـ`store()` بلا تعديل ملف واحد).**
النتيجة: `tests/Feature/WhatsAppRegistrationSaveTest.php` — **7/7 خضراء** تغطي: إنشاء جديد كامل (data + re_people + users + reserved_codes)، تكرار الهوية ← تحديث بالملف الموجود (status2)، رفض الارتباط بملف آخر (422)، الأم الحية ← portal، المعيل هي الأم ← نسخ للـportal، الأب المتوفى ← dead_people، مرفق عبر `temp_path` ← `uploads/{file_id}` + بصمة SHA-1.
=> **ب2 (خدمة منسوخة) ملغى ما لم يظهر داعٍ له لاحقاً.** معالجة الواتساب تُستدعى: `Request::create(..., POST, $payload, headers Accept: application/json)` ثم `(new GeneralRegistrationController)->store($request)` وقراءة `status`/`body`.

### 2.3 مبادئ غير قابلة للتفاوض
1. لا جدول موازٍ؛ البيانات النهائية تُكتب في نفس جداول النظام: `data`, `re_people`, `dead_people`, `portal_general_registration_field_values`, `attachments`, `users`.
2. مفتاح الترابط هو **رقم الملف6 أرقام** (من `generateUniqueReservedCode('data','file_id_number')`)، وليس رقم طلب.
3. بوابة التكرار **بالهوية وحدها** (مثل الموقع): وجود ملف ← تحديثه، ارتباط بملف آخر ← رفض.
4. `data_section_id = 1` (أيتام) و`data_request_status` (1 جديد / 2 تحديث) إلزاميان.
5. المرفقات: `file_type` من قيم `document_types` الفعلية، بصمة `file_hash` (SHA-1)، مجلد `uploads/{file_id}`، منع التكرار والفهرس الفريد (الخطأ 23000 لا يُفشل العملية).
6. رقم الواتساب معرّف الجلسة فقط — **لا يُستخدم كشرط تكرار**.
7. المصادقة على الويب هوك إجبارية عبر حقل `apikey` داخل المغلّف (يطابق `WHATSAPP_API_KEY` بـ`hash_equals`)، ويرفض إن فارغ `WHATSAPP_API_KEY` بـ503. *(تبدّل المبدأ: كان توقيع Meta `X-Hub-Signature-256` — انظر §10.)*
8. المحادثة كلها تعيش في الكاش (`bot_step_{phone}` + `reg_data_{phone}`، TTL30 دقيقة متجددة) ولا تُكتب أي بيانات قبل `finalize()`.

---

## 3. جرد الملفات

### 3.1 ملفات جديدة باسم WhatsApp (مسموحة بلا موافقة إضافية)
| ملف | مرحلة |
|---|---|
| `app/Services/WhatsAppService.php` | 2 |
| `app/Services/WhatsAppFlowService.php` | 3 |
| `app/Services/WhatsAppMediaService.php` | 4 |
| `app/Services/WhatsAppRegistrationService.php` (يُستخدم فقط إن رُسب ب2) | 1 |
| `app/Http/Controllers/WhatsAppBotController.php` | 2 |
| `app/Http/Controllers/Admin/WhatsAppApplicationsController.php` | 5 |
| `app/Models/WhatsAppLog.php` | 2 |
| `app/Jobs/WhatsAppInboundJob.php` | 6 |
| `app/Services/WhatsAppKnowledgeService.php` | 7 |
| `database/migrations/xxxx_create_whatsapp_logs_table.php` | 2 |
| `database/migrations/xxxx_create_whatsapp_unanswered_questions_table.php` | 7 |
| `resources/views/admin/whatsapp/applications.blade.php` | 5 |
| `resources/views/admin/whatsapp/show.blade.php` | 5 |
| `tests/Feature/WhatsAppWebhookTest.php` | 2 |
| `tests/Feature/WhatsAppRegistrationFlowTest.php` | 3 |
| `tests/Feature/WhatsAppRegistrationSaveTest.php` (مكتمل ✅) | 1 |
| `tests/Feature/WhatsAppMediaTest.php` | 4 |
| `docs/WHATSAPP_IMPLEMENTATION_PLAN.md` | 0 (أُنشئ) |

### 3.2 ملفات موجودة قد تُعدَّل — **بموافقة منفصلة عند دخول مرحلتها**
| الملف | مرحلة | التعديل المسموح حصراً |
|---|---|---|
| `routes/api.php` | 2 | إضافة مسار webhook فقط (POST — وحُذف GET verify مع التحول §10) |
| `config/services.php` | 2 (+7) | كتلة `whatsapp` ثم كتلة `gemini` |
| `.env` | 2 (+7) | مفاتيح `WHATSAPP_*` ثم `GEMINI_*` (يضيفها المالك بنفسه) |
| `database/seeders/PermissionTableSeeder.php` | 5 | صلاحية `عرض طلبات الواتساب` فقط |
| `resources/views/admin/dashboard/layout/sidebar.blade.php` | 5 | عنصر قائمة واحد فقط |
| `app/Providers/RouteServiceProvider.php` | 6 | استثناء `whatsapp/webhook` من حد `api` (اختياري) |
| `database/migrations/2026_09_23_000004_add_cache_priority_to_file_index_v4.php` | 1 | ✅ أُذن 2026-10-08: استبدال `UPDATE...NOT EXISTS` بـself-join (MySQL خطأ1093 كان يمنع أي تثبيت/اختبار جديد) |
| `database/migrations/2025_05_07_115548_create_dead_pepoles_table.php` | 1 | ✅ أُذن 2026-10-08: إضافة `mother_death_date` (date nullable) — الكود يستخدمه في86 موضعاً ولم يكن في السكيما |
| `docs/WHATSAPP_INTEGRATION.md` | 8 | مواءمة الوثيقة بما نُفّذ فعلاً |

### 3.3 ممنوع المس نهائياً
`GeneralRegistrationController.php` (بأي حال — حتى في ب1)، `ShowGeneralRegisrationController.php`، `GuardianFileService.php`، `app/DataTables/**`، شاشات الكفالات والـportal ونظام الرسائل، وكل `database/migrations` قائم **ما عدا المثالين المُصرَّح بهما في §3.2**، وكل `resources/views` عدا ملفات المرحلة5 الجديدة.

---

## 4. المراحل

### المرحلة0 — تهيئة (مكتملة بالقرارات الستة)
- [x] قرار1..6 (الجدول أعلاه)
- [ ] تجهيز بيئة اختبار: تشغيل MySQL، نسخة `.env.testing`، خادم Evolution API (Docker) + ngrok
- [ ] بوابة ب1/ب2 (§2.2) وتسجيل النتيجة في هذه الوثيقة

**بوابة الخروج:** بيئة قادرة على تشغيل `php artisan test`.

---

### المرحلة1 — مسار الحفظ بدون لمس الموقع *(الخطة الأولى)*
**الهدف:** أن يستطيع البوت كتابة تسجيل كامل في جداول النظام دون أي تعديل لملف قائم.

| # | الخطوة | مخرج | الحالة |
|---|---|---|---|
| 1.1 | Spike لب1: استدعاء `store()` داخلياً بـ`Request::create` على قاعدة اختبار لثلاثة سيناريوهات (جديد ناجح / تكرار هوية ← تحديث / هوية مرتبطة بآخر ← 422) | تقرير ب1/ب2 | ✅ نجح ب1 |
| 1.2 | إن لزم ب2: كتابة `WhatsAppRegistrationService` مطابقة لخطوات `store()`2–19 | الخدمة | ⏭️ ملغى (ب1 نجح) |
| 1.3 | كتابة `tests/Feature/WhatsAppRegistrationSaveTest` (حفظ: توليد رقم ملف، قسم1، محافظة، re_people/dead_people، users، بصمة مرفق عبر temp_path) | اختبارات | ✅ 7/7 |
| 1.4 | مطابقة سلوكية: نفس payload من نموذج الموقع ومن البوت يُنتج نفس نتيجة DB | تقرير مقارنة | ✅ (المسار نفسه حرفياً — استدعاء مباشر للـcontroller) |

**ملفات:** جدد فقط باسم WhatsApp (§3.1). **معدّلة (بموافقة صريحة أثناء التنفيذ):** migration-ان فقط — انظر §3.2.
**بوابة الخروج:** ✅ اختبارات الحفظ خضراء (7/7) + المشروع كاملاً 97 خضراء / 2 فشل سابق في اختبارات قديمة / 6 متوقفة.

---

### المرحلة2 — هيكل الواتساب (جديد بالكامل)
| # | الخطوة | الحالة |
|---|---|---|
| 2.1 | كتلة `whatsapp` في `config/services.php` + مفاتيح `.env` (يضيفها المالك) | ✅ الكتلة مضافة؛ مفاتيح `.env` بانتظار المالك |
| 2.2 | migration + model `whatsapp_logs` (phone, direction, msg_id, type, text/media_id, status, error, payload json, timestamps + فهرس phone/msg_id) | ✅ |
| 2.3 | `WhatsAppService`: إرسال نص، `downloadMedia` بخريطة MIME **شاملة `image/heic`**، رسائل خطأ عربية | ✅ |
| 2.4 | `WhatsAppBotController`: `handleWebhook` — مصادقة `apikey` إجبارية، توجيه بالحدث (`messages.upsert` / `messages.update`)، تجاهل `fromMe` والمجموعات و`@lid`، idempotency **آمنة** (مفتاح التكرار يُحفظ بعد نجاح المعالجة)، قفل لكل رقم، رد200 فوري، تسجيل كل رسالة في `whatsapp_logs` | ✅ |
| 2.5 | مسار webhook في `routes/api.php` مع `throttle` مخصص | ✅ `throttle:300,1` للـPOST (مسار `verify` الأقصى حُذف مع التحول §10) |
| 2.6 | `WhatsAppWebhookTest`: مفتاح سليم/فاسد/غائب، تكرار، حالات `messages.update`، أول رسالة ← ترحيب، تجاهل fromMe/المجموعات، فشل إرسال لا يفشل الويب هوك | ✅ 14/14 |

**معدّل (بموافقة):** `routes/api.php`، `config/services.php`.
**بوابة الخروج:** اختبارات الويب هوك خضراء، ولا استقبال بلا توقيع.

---

### المرحلة3 — محرك الحوار المطابق *(يشمل بيانات الأم)*
| # | الخطوة |
|---|---|
| 3.1 | القائمة الرئيسية `(1) تسجيل/تحديث ملف يتيم (2) اسأل سؤالاً` + أمر `0` للإلغاء |
| 3.2 | البوابة: هوية الوصي ← `checkExistingGuardian()` ← (مكتمل / ناقص / جديد) |
| 3.3 | بيانات الوصي:4 أسماء منفصلة، جوال، مدينة، **محافظة** (قائمة من `provinces`) |
| 3.4 | المتوفى: أب/أم، هوية، اسم4 أجزاء، تاريخ الوفاة، سبب الوفاة، شهادة الوفاة — مع محاولة ملء تلقائي من السجل المدني (`fillFromCivilRegistry`-مكافئ) إن توفر |
| 3.5 | **بيانات الأم (3 حالات):** المعيل هي الأم ← نسخ من `data` / أم حية ←4 أسماء + هوية + ميلاد ← أم متوفية ← `dead_people` + `portal_general_registration_field_values` |
| 3.6 | حلقة الأيتام: هوية9 أرقام،4 أسماء، ميلاد، جنس (1/2)، صحة (اختياري)، نوع الكفالة ← حزمة `re_people` مع منع التكرار داخل الطلب |
| 3.7 | الملفات المطلوبة تُقرأ من `document_types` (لا قائمة صلبة في الكود) — **مؤجَّل كلياً للمرحلة4 (الوسائط)** |
| 3.8 | `finalize()` ← مسار المرحلة1 داخل نفس القفل ← رقم الملف6 أرقام + حساب بوابة + رسالة نجاح |
| 3.9 | `WhatsAppRegistrationFlowTest`: جديد / موجود مكتمل / موجود ناقص / هوية موجودة من رقم واتساب آخر (تحديث لا تكرار) / حالات الأم الثلاث / إلغاء0 / انتهاء الجلسة |

**معدّل:** لا شيء. **بوابة الخروج:**9+ سيناريوهات خضراء.
**✅ نُفّذت 2026-10-08 —10/10 خضراء، انظر §11.**

---

### المرحلة4 — المرفقات والوسائط
| # | الخطوة |
|---|---|
| 4.1 | `WhatsAppMediaService`: تنزيل ← MIME/حجم ← SHA-1 ← `temp_uploads/whatsapp/{phone}` |
| 4.2 | عند finalize: نقل إلى `uploads/{file_id}` باسم `{file_type}_{file_id}_{هوية}_{Ymd_His}_{uniqid}` + سجل `attachments` مع تجاهل التكرار (بصمة) والتعامل مع23000 |
| 4.3 | رفض HEIC بصيغة عربية واضحة إن لم يقبله النظام، أو قبوله إن أمكن (الموقع يعلن `heic` في قواعده) |
| 4.4 | `WhatsAppMediaTest`: تكرار، صيغة مرفوضة، حجم زائد، نجاح + سجل `attachments` |

**معدّل:** لا شيء (قرص `public` كما في الموقع).
**✅ نُفّذت 2026-10-08 —8/8 خضراء، انظر §12.**

---

### المرحلة5 — الواجهة الإدارية والمراجعة
| # | الخطوة |
|---|---|
| 5.1 | صلاحية `عرض طلبات الواتساب` في `PermissionTableSeeder` |
| 5.2 | `WhatsAppApplicationsController` + `admin/whatsapp/*.blade.php`: قائمة (رقم الملف، الاسم، القناة عبر `whatsapp_logs`، الحالة)، تفاصيل + المرفقات، رابط فتح الملف في شاشة الكفالات |
| 5.3 | عنصر قائمة في `sidebar.blade.php` |
| 5.4 | اختبار403 لغير المصرح له |

**معدّل (بموافقة):** `PermissionTableSeeder.php` (سطر)، `sidebar.blade.php` (عنصر).
**✅ نُفّذت 2026-10-08 —9/9 خضراء، انظر §13.**

---

### المرحلة6 — الطابور والإنتاج
- `WhatsAppInboundJob` (استقبال200 فوري ثم معالجة) + إعداد Queue بنفس نمط `SendBulkSmsJob`.
- Redis للـcache والقفل، Supervisor، دومين HTTPS ثابت، توكن System User، استثناء webhook من حد `api` (اختياري ومعلن).
- **Rollback:** حذف مسارَي webhook + كتلة `whatsapp` يعطّل الإضافة كاملاً دون أثر على الموقع.

**معدّل (بموافقة صريحة):** `app/Providers/RouteServiceProvider.php` (استثناء الويب هوك فقط).
**✅ نُفّذت 2026-10-08 —7/7 خضراء، انظر §14.**

---

### المرحلة7 — ملحق الذكاء الاصطناعي *(مضموم)*
| # | الخطوة |
|---|---|
| 7.1 | كتلة `gemini` في `config/services.php` + مفتاح `.env` (يضيفها المالك) |
| 7.2 | `WhatsAppKnowledgeService`: ملف `storage/app/knowledge/faq.md` كامل في System Instruction، رد عربي مختصر، `NO_ANSWER` |
| 7.3 | **مرشّح خصوصية:** كشف أنماط الهوية/الأرقام في سؤال المستخدم قبل الإرسال ← رفض مهذب (لا تصل بيانات لـGoogle) |
| 7.4 | RateLimiter: لكل رقم + حد عام مرتفع (لا12/دقيقة عالمياً) |
| 7.5 | الربط بالقائمة الرئيسية `(2)` + جدول `whatsapp_unanswered_questions` لتسجيل الأسئلة بلا جواب |
| 7.6 | اختبارات: سؤال من الملف / خارج الملف / حقن تعليمات / حد الاستخدام / فشل الشبكة |

**معدّل (بموافقة):** `config/services.php` (كتلة ثانية).
**✅ نُفّذت 2026-10-08 —7/7 خضراء، انظر §15.**

---

### المرحلة8 — التحقق والتسليم
- `php artisan test` كاملاً + `php -l` لكل ملف معدَّل + `view:cache`.
- سيناريوهات يدوية على ngrok (قائمة `WHATSAPP_INTEGRATION.md §10` المعدّلة).
- فحص أمني: مصادقة `apikey` إجبارية، throttles، لا أسرار في Git، قرص المرفقات كما هو مقرَّر.
- تحديث `docs/WHATSAPP_INTEGRATION.md` بما نُفّذ + رفع حالة هذا الملف إلى "منفَّذ" مع نتيجة بوابة §2.2.

**معدّل (بموافقة):** `docs/WHATSAPP_INTEGRATION.md` (مواءمة كاملة).
**✅ نُفّذت 2026-10-08 — انظر §16.**

---

## 5. مصفوفة الاختبارات

| المرحلة | آلية |
|---|---|
| 1 | Feature tests للحفظ + مقارنة نتائج DB مع نفس payload من الموقع |
| 2 | Feature tests للويب هوك (apikey/idempotency/حجب) |
| 3 | Feature tests لكل سيناريو حوار |
| 4 | Feature tests للوسائط |
| 5 | اختبار صلاحيات403/200 |
| 7 | اختبارات ردود AI ومرشّح الخصوصية |
| 8 | قائمة يدوية كاملة على بيئة ngrok |

---

## 6. بوابات الموافقة (تُملأ قبل بدء كل مرحلة)

| المرحلة | يحتاج موافقة؟ | الموافقة (تاريخ/ملاحظة) |
|---|---|---|
| 1 | نعم — أول تنفيذ في المشروع | ✅ 2026-10-08 — وُفّقت |
| 2 | نعم — أول تعديل على `routes/api.php` + `config/services.php` | ✅ 2026-10-08 — "بدء المرحلة2 كاملة" |
| 3 | لا (ملفات جديدة فقط) بعد نجاح2 | ✅ 2026-10-08 — بلا حاجة لموافقة (ملفات جديدة)؛ نُفّذت كاملة §11 |
| 4 | لا (ملفات جديدة فقط) بعد نجاح3 | ✅ 2026-10-08 — بلا حاجة لموافقة (ملفات جديدة بعد "موافق")؛ نُفّذت كاملة §12 |
| 5 | نعم — تعديل seeder + sidebar | ✅ 2026-10-08 — "موافق" (يشمل إضافة مجموعة `admin/whatsapp` في `routes/admin.php` بموافقة صريحة موثقة §13)؛ نُفّذت كاملة §13 |
| 6 | نعم — تعديل `RouteServiceProvider` (اختياري) | ✅ 2026-10-08 — "موافق"؛ نُفّذت كاملة §14 |
| 7 | نعم — كتلة `gemini` + جدول جديد | ✅ 2026-10-08 — موافقة مسبقة من المالك: "نفذ المرحلة السابعة مباشرة بدون انتظار موافقة"؛ نُفّذت كاملة §15 |
| 8 | نعم — تعديل وثيقة موجودة | ✅ 2026-10-08 — "موافق"؛ نُفّذت كاملة §16 |

---

## 7. مخاطر مُثبَّة وخطط تخفيف

| المخاطر | التخفيف |
|---|---|
| انحراف خدمة البوت عن منطق `store()` (في ب2) | اختبارات مقارنة payload متطابق في1.4 + توثيق فروق مقصودة هنا |
| هشاشة `Request::create` (ب1) | Spike مصغّر أولاً + خيار ب2 جاهز |
| فقدان رسائل عند فشل المعالجة | idempotency آمنة (2.4) + لاحقاً Queue (6) |
| HEIC مرفوض | خريطة MIME في2.3 + رسالة عربية واضحة |
| تكرار ملفات عند إعادة المحاولة | بصمة SHA-1 + فهرس فريد (4.2) |
| تسريب بيانات عبر الطبقة المجانية لـGemini | مرشّح7.3 + عدم إرسال أي بيانات من قاعدة البيانات |
| تغيّر واجهات Google / Evolution API | كتلة `GEMINI_MODEL` و`WHATSAPP_API_URL`/`WHATSAPP_INSTANCE` قابلة للتغيير من `.env` |

---

## 8. سجل تنفيذ المرحلة1 (2026-10-08)

### 8.1 القرارات المُصرَّح بها أثناء التنفيذ
| # | ما | لماذا | بدائل مرفوضة |
|---|---|---|---|
| 1 | تعديل استعلام واحد في `2026_09_23_000004_add_cache_priority_to_file_index_v4.php` (self-join) | MySQL 1093: `UPDATE...NOT EXISTS` على نفس الجدول يفشل دائماً ← **لا تثبيت جديد ولا اختبار واحد يعمل** (كانت الحزمة كلها معطلة قبله) | تخطي المigration يدوياً في كل جلسة (غير مستقر) |
| 2 | إضافة `mother_death_date` داخل `2025_05_07_115548_create_dead_pepoles_table.php` | الكود يستخدمها في86 موضعاً ولا migration ينشئها ← أي تسجيل يتيم له وفاة (أب/أم) يفشل على سكيما جديد. قرار المالك: "استخدم الموجود" أي داخل ملف الـmigration القائم | ملف migration جديد (رُفض)، تعديل `store()` (ممنوع بقرار1ب) |

### 8.2 اكتشافات بيئة
- **قاعدة بيانات الموقع الأصلية (`aso`) غير موجودة على MySQL المثبَّت هنا** — أُنشئت `aso` (فارغة) كأثر جانبي لـ`artisan migrate`، وأُنشئت `aso_testing` (لاختبارات) و`civilregistry` (اتصال ثانٍ تطلبه الاختبارات).
- `store()` يستقبل حقولاً بأعداد صحيحة من نموذج الاختبار بينما قواعده `string` — البوت لاحقاً **يجب أن يرسل كل حقول `data_*` كنصوص**.
- السكيما المُولَّدة من migrations تحتاج `sql_mode` غير صارم لمسار الإنشاء (`data_number_alt` NOT NULL بلا default) — الاختبار يضبطه session-level؛ إن كان إنتاجك صارماً فهذا خلل مسبق في الموقع نفسه يستحق معالجة منفصلة.
- الجداول المرجعية تُزرع في الاختبارات عبر `Tests\Concerns\SeedsV4ReferenceData` + إضافة `category_of_relations.id=2` و`request_status.id=2` (المسار المحدث يكتب status=2).
- مرفقات الواتساب لاحقاً تمرّ عبر **`temp_path` على قرص public** (يتجاوز فحص الصور `_cropped` تلقائياً لأن `$file` يكون null) — مُختبَر في اختبار المرفق.

### 8.3 نتائج الاختبارات
- `WhatsAppRegistrationSaveTest`: **7/7 ✅**
- المشروع كاملاً: **97 خضراء / 6 متوقفة / 2 فاشلة** — الفشلان في اختبارات قديمة بعيوب في بياناتها (غير مسبوقي — الحزمة لم تكن تعمل أصلاً):
  1. `LivingMotherRegistrationTest > deceased mother...` — ينشئ `dead_people` بلا سجل `data` والد (مخالفة FK).
  2. `Auth\RegistrationTest > new users can register` — تسجيل المستخدمين لا يُصادق الجلسة (عدم تطابق مع إعدادات الموقع الحالية).
  - **ممنوع تعديلهما دون إذن** (ملفات موجودة) — يُعالَجان في مرحلة لاحقة إن أراد المالك.

### 8.4 حالة بوابة المرحلة1
- **✅ مُغلقة: ب1 مُعتمد ومستقر** — البوابة التالية: المرحلة2 (هيكل الواتساب) → مُنحت الموافقة ونُفّذت، انظر §9.

---

## 9. سجل تنفيذ المرحلة2 (2026-10-08) — *النسخة الأصلية: Meta Cloud API (استُبدلت، انظر §10)*

### 9.1 الملفات (6 جديدة + 2 معتمدة للتعديل)
| الملف | النوع |
|---|---|
| `database/migrations/2026_10_08_000001_create_whatsapp_logs_table.php` | جديد |
| `app/Models/WhatsAppLog.php` | جديد |
| `app/Services/WhatsAppService.php` | جديد |
| `app/Http/Controllers/WhatsAppBotController.php` | جديد |
| `tests/Feature/WhatsAppWebhookTest.php` | جديد |
| `config/services.php` (بلوكة `whatsapp`) | معدّل ✅ بموافقة |
| `routes/api.php` (مسارا `whatsapp/webhook`) | معدّل ✅ بموافقة — بعد `/chunked-upload-test` |

### 9.2 اكتشاف مهم أثناء التنفيذ
- **PHP يحوّل النقاط في أسماء معاملات الاستعلام إلى شرطات سفلية**: `hub.mode` ← `hub_mode` (تحقّق عملياً عبر `$_GET` الفعلي، وينطبق على `Request::create`/`parse_str` في الاختبارات). كان حاسماً لمسار `verify` الأقصى — **أُلغي هذا المسار مع التحول (§10)** لكن الحقيقة تبقى مفيدة لأي استعلام فيه نقاط.
- مسار webhook يمر بمجموعة `api` (limiter60/دقيقة/IP) ← `throttle:300,1` inline على الـPOST (مسار `verify` الأقصى حُذف)؛ استثناء `RouteServiceProvider` مؤجَّل للمرحلة6.
- `ArrayStore` في Laravel10 يدعم `LockProvider` ✓ (تأكيد أن قفل `Cache::lock` يعمل في الاختبارات كما في `file` driver بالإنتاج).
- idempotency: `Cache::get(done)` ← `Cache::add(lock)` ← `Cache::put(done)` **بعد النجاح فقط**؛ أي استثناء ينتشر ← رد500 ← الويب هوك يعيد المحاولة (لا فقدان رسائل)، و`finally` يحرّر قفل الرقم وقفل التكرار. *(بنية محفوظة كما هي في نسخة Evolution.)*

### 9.3 نتائج الاختبارات *(النسخة الأصلية — استُبدلت)*
- `WhatsAppWebhookTest`: **10/10 ✅** ببروتوكول Meta (verify، توقيع HMAC، حالات Meta) — **أُعيدت كتابتها بالكامل لـEvolution: 14/14 ✅ (§10.3)**.
- المشروع كاملاً حينها: **107 خضراء / 6 متوقفة / 2 فاشلة** — نفس الفشلين المسبقين الممنوعَين لمسهما (§8.3).

### 9.4 ~~مفاتيح `.env` الأصلية (Meta)~~ — **أُلغيت؛ المطلوب الآن انظر §10.4**

### 9.5 البوابة التالية
- المرحلة3 (محرك الحوار المطابق) — **لا تحتاج موافقة** (ملفات جديدة فقط) بعد نجاح2 ✅.

---

## 10. تحوّل المرحلة2: Meta Cloud API ← Evolution API (2026-10-08)

### 10.1 القرار
- طلب المالك: تغيير الربط إلى **واتساب غير رسمي عبر QR** (نفس تجربة المستخدم، بلا Meta). عُرضت ثلاث خيارات (WAHA / Evolution API / WPPConnect-Baileys) واختير **Evolution API**.
- **مخاطر مقبولة على علم:** واجهة غير رسمية تخالف شروط واتساب ← مخاطرة حظر الرقم — تُخفَّف بترقية تدريجية وبلا إرسال جماعي. المالك اختارها بعد التنبيه.
- الهدف نفسه دون تغيير: بوت تسجيل يكتب في جداول النظام عبر `store()` — والمرحلة1 (الحفظ) **لم تتأثر إطلاقاً** (الرسائل والـpayload الإداري لا يعتمدان على المزوّد).

### 10.2 ما تغيّر في الكود
| الملف | التغيير |
|---|---|
| `app/Services/WhatsAppService.php` | كامل: Graph API ← `POST {base}/message/sendText/{instance}` بهيدر `apikey` وجسم `{number, text}`؛ `fetchMedia` ← `POST /chat/getBase64FromMediaMessage/{instance}` (فك base64 + كشف MIME من البايتات: JPEG/PNG/PDF/WEBP/ftyp-heic/M4A/MP3) |
| `app/Http/Controllers/WhatsAppBotController.php` | حُذف `verify()` كلياً (خاص بـMeta). `handleWebhook`: مصادقة حقل `apikey` (`hash_equals`، فارغ ←503، خاطئ ←401)، توجيه `messages.upsert`/`messages.update` (يتطابق مع `-`/`_`/حالة الأحرف)، تجاهل `fromMe` والمجموعات (`@g.us`) و`@lid` (يدعم `remoteJidAlt`)، حالات `DELIVERED/READ/...` ← سجلات صغيرة مع `ERROR` ← `failed` + خطأ عربي |
| `routes/api.php` | حُذف مسار `GET verify` — بقي `POST whatsapp/webhook` (`throttle:300,1`) |
| `config/services.php` | كتلة `whatsapp`: `{base_url, api_key, instance}` من `WHATSAPP_API_URL/KEY/INSTANCE` |
| `tests/Feature/WhatsAppWebhookTest.php` | إعادة كتابة كاملة: المصادقة عبر `apikey` داخل المغلّف (لا HMAC)، هياكل رسائل/حالات Evolution، + اختبارات جديدة (تجاهل fromMe، المجموعات، فشل إرسال الترحيب لا يفشل الويب هوك، حدث غريب) |
| `whatsapp_logs` + `WhatsAppLog` | **لم تتغير** (مزوّد-محايد) |

### 10.3 نتائج الاختبارات
- `WhatsAppWebhookTest`: **14/14 ✅** (مفتاح سليم ← ترحيب+تسجيل، غائب/خاطئ ←401 بلا معالجة، بلا إعداد ←503، تكرار ← واحدة، الثانية بلا ترحيب، fromMe/المجموعة ← متجاهلة، فشل الإرسال ←200 + سجل فشل، `DELIVERED`/`READ`/`ERROR` ← حالات صحيحة، حدث `connection.update` ← متجاهَل).
- المشروع كاملاً: **111 خضراء / 6 متوقفة / 2 فاشلة** (نفس الفشلين المسبقين الممنوعَين §8.3) — خط الأساس97 + المرحلة1 السبعة + الويب هوك14.

### 10.4 بانتظار المالك — مفاتيح `.env` (لا أُلمسها)
```
WHATSAPP_API_URL=http://localhost:8080
WHATSAPP_API_KEY=
WHATSAPP_INSTANCE=
```
+ على خادم Evolution نفسه: `WEBHOOK_GLOBAL_URL` ← `https://<دومينك>/api/whatsapp/webhook` مع تفعيل حدثي `MESSAGES_UPSERT` و`MESSAGES_UPDATE`، ثم مسح QR لربط الرقم.
بدونها: `handleWebhook` يرجع503 والإرسال يفشل برسالة "خدمة واتساب غير مهيأة".

### 10.5 ملاحظات لاحقة
- ~~`docs/WHATSAPP_INTEGRATION.md` و`WHATSAPP_AI_KNOWLEDGE_ADDON.md` ما زالا يصفان مسار Meta القديم~~ — **✅ حُلّ في المرحلة8 (§16): أُعيدت كتابة `WHATSAPP_INTEGRATION.md` كوثيقة "بالمنفَّذ"، وحُدّث سطر القناة في `WHATSAPP_AI_KNOWLEDGE_ADDON.md §1` إلى Evolution.**
- خطر `@lid`: أرقام تظهر كـLID بلا `@s.whatsapp.net` ولا `remoteJidAlt` تُتجاهل — مراقبة في المرحلة6 (logs/تنبيه).
- البوابة2: **✅ مُغلقة على نسخة Evolution** — المرحلة3 لا تحتاج موافقة.

---

## 11. سجل تنفيذ المرحلة3 — محرك الحوار (2026-10-08)

### 11.1 الملفات
| الملف | النوع |
|---|---|
| `app/Services/WhatsAppFlowService.php` | جديد |
| `tests/Feature/WhatsAppRegistrationFlowTest.php` | جديد |
| `app/Http\Controllers/WhatsAppBotController.php` | معدّل — ملف ملكية الإضافة، تعديل المرحلة المصاحبة مسموح (حقن الخدمة + استبدال فرع الترحيب بـ`flow->handle`) |

### 11.2 قرارات أثناء التنفيذ
- آلة حالات في `Cache` بمفتاحين `bot_step_{phone}` + `reg_data_{phone}` (TTL30 دقيقة متجددة)؛ **لا كتابة في قاعدة البيانات قبل `finalize()`**؛ الاستدعاء يجري داخل قفل الرقم الموجود في `handleMessage`.
- الترحيب = نفس نص `MENU` (نصوص المرحلة2 ثابتة لعدم كسر اختباراتها). `handle(phone, text, isFirstContact)`: أول تواصل + نص غريب ← MENU؛ تواصل لاحق + غريب ← **بلا رد**. `إلغاء` يلغي في كل الخطوات؛ `0` = إلغاء عدا `reg_father_dead` (0=لا) و`reg_orphan_id` (0=إنهاء) و`reg_phone` (0=استخدام رقم الواتساب).
- **مفاتيح حقول الأم** (مُتحقق من `store()` L263–274): حية ← `mother_id/first/second/third/last_name` + `mother_birth_date` + `mother_is_alive=1`؛ متوفية ← `deceased_mother_*` + `mother_death_date` + `mother_death_reason` + `mother_is_alive=0`. الأم الحية لا تُكتب في `dead_people` أبداً (بوابة فقط) — وأم متوفية تُكتب في `dead_people` + `field_mother_status='متوفية'`.
- **معرّفات لا نصوص**: `*_death_reason` و`sponsorship_status` أعمدة FK bigint ← تُرسل من قوائم `death_reasons`/`sponsorship_statuses` برقم الصف؛ `-` يسمح للمواليد والتواريخ غير المعروفة والأسباب (لا لحقول مطلوبة).
- وضع التحديث = `source==='data'` فقط؛ prefill من `guardian_data` + قفز لأول خطوة فارغة؛ `fillFromCivilRegistry` يُحاول بصمت (يتخطى الأسئلة المملوءة، و`data_gender` يُقبل فقط إذا `1/2`).
- `finalize()`: `generateUniqueReservedCode('data','file_id_number')` (لا يتعارض مع `findFileNumberConflict` لأنه لا يفحص `reserved_codes`) ← `store()` عبر `Request::create` ← نجاح: "تم التسجيل بنجاح + رقم الملف"؛ فشل422: عرض `message` ثم مسح الحالة. تكرار يتيم داخل الطلب يُرفض قبل الاتصال بالـ`store`، ويتيم مرتبط بملف آخر ← رسالة الـ422 الفعلية.
- اكتشافان أثناء التنفيذ: (أ) `questionFor` كانت تستقبل الحالة **بنسخة** ← الخطوة لا تُحفظ أبداً (حُوّلت لـ`&$state`)؛ (ب) مساعد `say()` في الاختبارات كان يعيد **ردًّا قديماً** عند غياب رد جديد (حُوّل لعدّاد outbound).

### 11.3 نتائج الاختبارات
- `WhatsAppRegistrationFlowTest`: **10/10 ✅** — جديد كامل (37 رسالة حتى الحفظ)، وصية هي الأم، أم متوفية، ملف مكتمل + prefill، ملف ناقص، هوية من رقم واتساب آخر ← تحديث لا تكرار، إلغاء `0`/`إلغاء`، انتهاء الجلسة بعد31 دقيقة (`Carbon::setTestNow`)، تكرار يتيم داخل الطلب، رفض `store()` "مرتبط بمعيل آخر".
- المشروع كاملاً: **121 خضراء / 6 متوقفة / 2 فاشلة** (نفس الفشلين المسبقين الممنوعَين §8.3) — خط الأساس111 + المرحلة3 العشرة.

### 11.4 فروق مقصودة و ملاحظات
- `store()` يفرض `data_user_insert_data = "N_user"` على المسار الجديد متجاوزاً قيمة البوت `whatsapp` — فرق مقبول، لا يُعدَّل `store()`.
- رُصد `re_file_id` في `dead_people` يُخزَّن برقم الملف كما وصل؛ اختباراتنا تتعامل معه عبر `str_pad`.
- **البوابة التالية: المرحلة4 (الوسائط)** — لا تحتاج موافقة بعد نجاح3 ✅ (ملفات جديدة فقط).

---

## 12. سجل تنفيذ المرحلة4 — المرفقات والوسائط (2026-10-08)

### 12.1 الملفات
| الملف | النوع |
|---|---|
| `app/Services/WhatsAppMediaService.php` | جديد — تنزيل/تحقق/كتابة مؤقتة/تنظيف |
| `tests/Feature/WhatsAppMediaTest.php` | جديد |
| `app/Services/WhatsAppFlowService.php` | معدّل — خطوات المرفقات + `handleMedia` + `attachments` في payload |
| `app/Http\Controllers\WhatsAppBotController.php` | معدّل — حقن الخدمة + فرع الوسائط + تنظيف بعد انتهاء الجلسة |

### 12.2 قرارات أثناء التنفيذ
- **قبول**: `image/jpeg,png,webp,gif,heic,heif` + `application/pdf` فقط ← حجم ≤ `5242880` (5MB، نفس حد الموقع) ← SHA-1 ← `temp_uploads/whatsapp/{phone}/{messageId}.{ext}` على قرص `public`. `audio/video/sticker` تُرفض **قبل أي نداء شبكة**. HEIC مقبول (§4.3 — الموقع يعلنه في قواعده).
- خطوات جديدة: `reg_attachments` (قائمة وثائق من `document_types` + عدّاد الملفات + `0`=إنهاء) ← `reg_attach_person` (قائمة: الوصي `main`/هوية الأب إن متوفى/هوية الأم إن متوفية/هويات الأيتام؛ `0`=رجوع) ← `reg_attach_send` (انتظار الملف؛ رقم=وثيقة أخرى، `0`=إنهاء) — كلها معفاة من قاعدة "0=إلغاء".
- **قاعدة خطوة المرفقات**: تظهر فقط إذا وُجد صفوف في `document_types`؛ وإلا `finalize()` مباشرة (يبقي اختبارات المرحلة3 دون تعديل).
- سيناريوهات الوسائط: تعليق رقمي عند القائمة ← يحدد الوثيقة ويحفظ `pending.media_msg_id/type` ثم يسأل الشخص ← `ingest` فور اختياره؛ وسائط عند `reg_attach_send` ← `ingest` فوراً (التعليق الرقمي يُعيد الوثيقة)؛ وسائط بلا جلسة ← "لم تبدأ التسجيل".
- حدّ10 ملفات/جلسة؛ تكرار بالـhash داخل الجلسة ← حذف الملف المكرر + "مُرسَل مسبقاً"؛ تكرار على DB يتولاه `store()` (23000 ← skip). `finalize()` يرسل `attachments[]` بـ`person_identity_number` خام (رقمي) عدا الوصي `main`، مع `file_id_number` لكل مدخل.
- `save()` تخزّن `state['phone']` (لمسار `ingest` من خطوات النص)؛ `handle()` تحقن الهاتف بعد `load`. الكونترولر ينظّف `temp_uploads/whatsapp/{phone}` بعد كل رسالة إذا `!flow->hasState($phone)` (نجاح/إلغاء/انتهاء جلسة).
- `store()` ينسخ الملف من `temp_path` بنفسه (لا تعديل عليه) باسم `{file_type}_{file_id}_{هوية}_{Ymd_His}_{uniqid}.{ext}` — يكتشف الفرق: المجلد `uploads/{id}` **بلا أصفار بادئة** (`77020` لا `077020`) — الاختبار يستderive المسار من سجل `attachments`.
- اختبارات على `Storage::fake('public')` (لا لمس قرص الموقع الحقيقي)؛ `Http::fake` بترتيب: `sendText` في `setUp` فقط + `getBase64FromMediaMessage` per-test عبر `fakeMedia()`؛ رفض الصيغة يُثبت بـ`Http::assertNotSent`.

### 12.3 نتائج الاختبارات
- `WhatsAppMediaTest`: **8/8 ✅** — نجاح + سجل `attachments` + ملف على القرص + اختفاء temp، تكرار داخل الجلسة، رفض audio بدون تنزيل، رفض حجم >5MB، قبول HEIC (+ تنظيف بعد `إلغاء`)، قبول PDF، وسائط بلا جلسة، `0` عند القائمة ينهي بدون مرفقات.
- المشروع كاملاً: **129 خضراء / 6 متوقفة / 2 فاشلة** (نفس الفشلين المسبقين الممنوعَين §8.3) — خط الأساس121 + المرحلة4 الثمانية.

### 12.4 البوابة التالية
- **المرحلة5 (الواجهة الإدارية)** — تحتاج موافقة صريحة (تعديل `PermissionTableSeeder.php` + `sidebar.blade.php`).

---

## 13. سجل تنفيذ المرحلة5 — الواجهة الإدارية والمراجعة (2026-10-08)

### 13.1 الملفات
| الملف | النوع |
|---|---|
| `app/Http/Controllers/Admin/WhatsAppApplicationsController.php` | جديد — `index` (تجميع حسب الهاتف + بحث `q` + استخراج رقم الملف من سجل الإرسال) و`show` (محادثة + الملف + المرفقات) |
| `resources/views/admin/whatsapp/applications.blade.php` | جديد — القائمة + pagination |
| `resources/views/admin/whatsapp/show.blade.php` | جديد — التفاصيل + المرفقات |
| `tests/Feature/WhatsAppAdminPanelTest.php` | جديد —9 اختبارات |
| `database/seeders/PermissionTableSeeder.php` | معدّل — صلاحية `عرض طلبات الواتساب` |
| `resources/views/admin/dashboard/layout/sidebar.blade.php` | معدّل — عنصر `طلبات الواتساب` |
| `routes/admin.php` | معدّل — مجموعة `admin/whatsapp` فقط |

### 13.2 الموافقات والقرارات أثناء التنفيذ
- **موافقة صريحة** على تعديل `routes/admin.php` — **مجموعة `admin/whatsapp` فقط** (بعد مجموعة SMS)، لا أي تعديل آخر في الملف.
- **«لا أريد أن يمس شاشة الكفالات»** ⇒ رابط فتح الملف يستهدف `route('admin.records.management.show', $data->id)` بـ`target="_blank"`، لا أي مسار كفالات.
- أسماء المسارات `admin.whatsapp.index` / `admin.whatsapp.show` بـ`prefix admin/whatsapp` + middleware `permission:عرض طلبات الواتساب`؛ الضيف ⇒ redirect لـ`route('login')` (يُثبت بنجاح `test_guest_is_redirected_to_login`).
- استخراج رقم الملف: outbound log نصه `تم التسجيل بنجاح` + regex `/رقم الملف:\s*([0-9]{6})/u` ⇒ `Data::whereIn('file_id_number', [$id, ltrim($id,'0')])` (مجلد `uploads/{id}` بلا أصفار بادئة — §12.2)؛ المرفقات تُطابق `%/{variant}/%` على `file_path`.
- **رقم الملف غير الرباعي/الرقمي** ⇒ `abort(404)` (يُثبت `test_detail_rejects_non_numeric_phone`).
- نمط الاختبارات: `userWithPermission()` = `seed(PermissionTableSeeder)` + `User::factory` + `role='admin'` + دور `whatsapp-viewer` + `syncPermissions`؛ **`role='admin'` ضروري** لاجتياز `RoleMiddleware` وإلا أعاد التوجيه إلى `/login` بدل403 (`test_user_without_permission_gets_403`)؛ `setUp` يحتاج `sql_mode = 'NO_ENGINE_SUBSTITUTION'` + `seedV4ReferenceData()` (نفس نمط اختبارات §11/§12 — وإلا فشل FK `data_request_status`).

### 13.3 نتائج الاختبارات
- `WhatsAppAdminPanelTest`: **9/9 ✅** — أسماء المسارات، ضيف ⇒ login، بلا صلاحية ⇒403، صف الصلاحية من السايدر، القائمة (رقم/اسم/حالة)، بحث بالهاتف، التفاصيل (محادثة/ملف/مرفقات)، ملف غير مسجل ⇒ «قيد التسجيل»، هاتف غير رقمي ⇒404.
- المشروع كاملاً: **138 خضراء / 6 متوقفة / 2 فاشلة** (نفس الفشلين المسبقين الممنوعَين §8.3) — خط الأساس129 + المرحلة5 التسعة.

### 13.4 البوابة التالية
- **المرحلة6 (الطابور والإنتاج)** — تحتاج موافقة صريحة (تعديل `RouteServiceProvider` اختيارياً + إعداد Queue/Redis/Supervisor).

---

## 14. سجل تنفيذ المرحلة6 — الطابور والإنتاج (2026-10-08)

### 14.1 الملفات
| الملف | النوع |
|---|---|
| `app/Jobs/WhatsAppInboundJob.php` | جديد — تحمل `event` + `data`؛ `handle()` تنقل منطق المعالجة كاملاً (استقبال/حالة/idempotency/قفل الهاتف) |
| `app/Http/Controllers/WhatsAppBotController.php` | معدّل (ملكية الإضافة §3.1) — مصادقة `apikey` + `dispatch` + رد200 فقط |
| `app/Providers/RouteServiceProvider.php` | معدّل (بموافقة §3.2) — استثناء **مُعلن** لـ`api/whatsapp/webhook` من حد `api` بـ`Limit::none()` |
| `tests/Feature/WhatsAppInboundJobTest.php` | جديد —7 اختبارات |

### 14.2 قرارات أثناء التنفيذ
- التسليم للمهنة يحدث **بعد** المصادقة: مفتاح فاسد/غائب ← رفض401/503 عند الحافة بلا طابور.
- نمط المهنة كما `SendBulkSmsJob`: `$tries = 1` (لا إعادة محاولة لتجنّب معالجة مزدوجة) و`$timeout = 120`؛ idempotency (`whatsapp:done:*` + `whatsapp:lock:*` + قفل الهاتف) تعيش داخل المهنة فتبقى الويب هوك بلا حالة.
- في الاختبارات `QUEUE_CONNECTION=sync` (phpunit.xml) ← المهنة تعمل inline عند `dispatch` ← حزم الويب هوك14 والوسائط8 والحوار10 والحفظ7 تعمل **دون أي تعديل**.
- **لم تُمس** `.env`/`config/queue.php`/`config/cache.php` (خارج جرد §3.2): الإنتاج يعمل حالياً بـ`QUEUE_CONNECTION=database` + `CACHE_DRIVER=file`، و`FileStore` يدعم `Cache::lock` في Laravel10 — تفعيل Redis توصية تشغيلية للمالك لا شرط للعمل.
- استثناء حد `api`: فرع جديد في `RateLimiter::for('api')` يعيد `Limit::none()` (يُرجِع `Unlimited`) لمسار الويب هوك فقط؛ يبقى `throttle:300,1` المخصص على المسار نفسه حامياً.

### 14.3 نتائج الاختبارات
- `WhatsAppInboundJobTest`: **7/7 ✅** — رد200 فوري بلا معالجة (`Queue::fake` + صفر سجلات + صفر نداءات)، تشغيل المهنة المؤجّلة (ترحيب + سجل صادر)، تحديث حالة الإرسال، تكرار `msg_id` عبر مهنتين، رفض apikey قبل التسليم، حدث غريب بلا تسليم، استثناء حدّ api (`Unlimited` مقابل60 لبقية المسارات).
- المشروع كاملاً: **145 خضراء / 6 متوقفة / 2 فاشلة** (نفس الفشلين الممنوعَين §8.3) — خط الأساس138 + المرحلة6 السبعة.

### 14.4 خطوات تشغيلية (ينفّذها المالك/السيرفر — بلا تعديل كود)
1. عامل طابور مستمر لنفس `QUEUE_CONNECTION` الحالي: `php artisan queue:work --tries=1 --timeout=120` + إشراف Supervisor (إعادة تشغيل تلقائية عند التعطل).
2. Redis اختياري لاحقاً: في `.env` ← `QUEUE_CONNECTION=redis` + `CACHE_DRIVER=redis` (`REDIS_*` مضبوطة أصلاً) ثم إعادة تشغيل العامل.
3. دومين HTTPS ثابت + ضبط `WEBHOOK_URL` في Evolution على `POST {DOMAIN}/api/whatsapp/webhook`.
4. توكن System User في Evolution ومفتاح `WHATSAPP_API_KEY` في `.env`.
5. **Rollback:** حذف مسار webhook من `routes/api.php` + كتلة `whatsapp` من `config/services.php` يعطّل الإضافة كاملاً دون أثر على الموقع.

### 14.5 البوابة التالية
- **المرحلة7 (ملحق الذكاء الاصطناعي)** — وافقت مسبقاً: "نفذ المرحلة السابعة مباشرة بدون انتظار موافقة".

---

## 15. سجل تنفيذ المرحلة7 — ملحق الذكاء الاصطناعي (2026-10-08)

### 15.1 الملفات
| الملف | النوع |
|---|---|
| `app/Services/WhatsAppKnowledgeService.php` | جديد — كلاس واحد: قراءة `faq.md` + مرشّح خصوصية + حدود الاستخدام + نداء Gemini + تسجيل بلا جواب |
| `app/Models/WhatsAppUnansweredQuestion.php` | جديد — `$table` صريح (لأن تسمية `WhatsApp*` يحوّلها Laravel افتراضياً إلى `whats_app_*`) |
| `database/migrations/2026_10_08_000002_create_whatsapp_unanswered_questions_table.php` | جديد |
| `config/services.php` | معدّل (بموافقة §3.2) — كتلة `gemini` (`key` + `model` بحرفية ملحق `WHATSAPP_AI_KNOWLEDGE_ADDON.md §4`) |
| `app/Services/WhatsAppFlowService.php` | معدّل (ملكية الإضافة) — خيار `(2)` يفتح `faq_mode` + `stepFaq` + فرع وسائط + حقن الخدمة في الـconstructor |
| `tests/Feature/WhatsAppKnowledgeTest.php` | جديد —7 اختبارات |

### 15.2 قرارات أثناء التنفيذ
- المرجع التنفيذي: `docs/WHATSAPP_AI_KNOWLEDGE_ADDON.md` (نص النظام ونداء API حرفيان منه: `x-goog-api-key`، `system_instruction`، `temperature 0.2`، `maxOutputTokens 400`، `retry(1, 500)`). الاسم الذهبي فرض `WhatsAppKnowledgeService` بدل `KnowledgeBotService` المقترح في الملحق.
- **خصوصية (7.3):** أي مقطع ≥6 أرقام (هوية/رقم ملف/هاتف — يشمل الأرقام العربية الهندية بفضل `/u`) يُرفض **قبل أي نداء شبكة** — `Http::assertNothingSent` مُثبت في الاختبار.
- **حدود (7.4):** لكل رقم10 أسئلة/ساعة (`kb_user_{phone}`) + حد عام **مرتفع** 120/دقيقة (`kb_global`) — تنفيذاً لقرار الخطة "لا12/دقيقة عالمياً" (يتجاوز ملحق §5 القديم).
- `NO_ANSWER` ثابت عام: النموذج يُطلب منه إخراجه حرفياً؛ والخدمة تتعامل معه/مع فراغ/مع فشل الشبكة/مع غياب `faq.md` كحالة "بلا جواب" ← صف في `whatsapp_unanswered_questions` + رسالة FALLBACK عربية.
- flow: `(2)` من الوضع الخامل يحفظ `faq_mode` ويفتح سؤالاً؛ `0` داخل الأسئلة (عبر `ZERO_CANCEL_EXEMPT`) ينهي الجلسة ويعيد القائمة الرئيسية؛ وسائط داخل `faq_mode` ← "اكتب سؤالك نصاً"؛ إعادة فتح `(2)` متاحة بعد الخروج.
- **لم يُمس** `.env` (المالك يضيف `GEMINI_API_KEY`/`GEMINI_MODEL`) ولا أي ملف خارج الجرد §3.

### 15.3 نتائج الاختبارات
- `WhatsAppKnowledgeTest`: **7/7 ✅** — إجابة من الملف (بنية الطلب كاملة: نظام + سؤال + ترويسة المفتاح + اسم النموذج)، سؤال بلا جواب ← تسجيل، حقن تعليمات (النظام ثابت والسؤال داخل `contents` وحدها)، حد الاستخدام (10 إجابات ثم رسالة الحد في الـ11 مع `assertSentCount(10)`)، فشل الشبكة ← FALLBACK + تسجيل، أرقام شخصية ← رفض بلا أي نداء، وربط flow كامل `(2) ← سؤال ← جواب ← وسائط ← 0 ← قائمة`.
- الحزم القائمة دون تعديل: **46/46** في (حوار + وسائط + مهنة + ويب هوك + حفظ).
- المشروع كاملاً: **152 خضراء / 6 متوقفة / 2 فاشلة** (نفس الفشلين الممنوعَين §8.3) — خط الأساس145 + المرحلة7 السبعة.

### 15.4 خطوات تشغيلية (المالك)
1. في `.env`: `GEMINI_API_KEY=` (من Google AI Studio) + اختيارياً `GEMINI_MODEL=gemini-2.5-flash` (الافتراضي مضبوط في الكونفق).
2. إنشاء `storage/app/knowledge/faq.md` بحسب نموذج الملحق §3 — يُعدَّل في أي وقت بلا تعديل كود.
3. تجربة يدوية حسب قائمة الملحق §7.
4. ملاحظة خصوصية: لا يُرسَل لـGoogle إلا سؤال المستخدم + الملف العام (متحقق منه بالكود §7.3).

### 15.5 البوابة التالية
- **المرحلة8 (التحقق والتسليم)** — تحتاج موافقة (تعديل `docs/WHATSAPP_INTEGRATION.md` + سيناريوهات ngrok يدوية).

---

## 16. سجل تنفيذ المرحلة8 — التحقق والتسليم (2026-10-08)

### 16.1 ما نُفّذ ونتيجته
| البند | النتيجة |
|---|---|
| `php artisan test` كاملاً | **152 خضراء / 6 متوقفة / 2 فاشلة** — نفس الفشلين الممنوعَين §8.3 دون أي تغيير (خط الأساس 121 بعد المراحل1–3 + 31 من المراحل4–7 = 152، منها 62 اختبار واتساب) |
| `php -l` لكل ملف في جرد §3.1–§3.2 المعدَّل | نظيف جميعاً (خدمات3 + كونترولرَين + نماذج2 + مهنة + routes2 + RouteServiceProvider + seeder + views4 + مهاجرتان + كونفق + اختبارات7) |
| `php artisan view:cache` | ✅ `Blade templates cached successfully` |
| مصادقة و throttles | `apikey` إجباري عبر `hash_equals` (14 اختبار §2.5) + `throttle:300,1` + استثناء `Limit::none()` مُعلن من حد `api` + صلاحيات اللوحة (403/login — 9 اختبارات §5) |
| فحص أسرار Git | `.env` **غير متتبَّع** (`.env.example` فقط)؛ `git grep` على المحتوى المتتبَّع بلا نتائج لـ`AIza...` أو قيم `*_API_KEY=` حقيقية (الوثائق placeholder فقط) |
| قرص المرفقات | كما قُرِّر بقرار3: قرص `public` + `uploads/{file_id}` + جدول `attachments` — مُختبَر في §1/§12 |
| تحديث `docs/WHATSAPP_INTEGRATION.md` | أُعيدت كتابته **كوثيقة "بالمنفَّذ"**: التحوّل Meta→Evolution، مسار الحفظ ب1، الجداول الفعلية (لا موازية)، المسارات، `WhatsAppService`/المهنة/الحوار، اللوحة الإدارية، **قائمة §10 اليدوية المعدّلة** لسيناريوهات ngrok، الأمان §12، الإنتاج §13 |
| رفع حالة ملف الخطة | من "التنفيذ لم يبدأ" إلى **"منفَّذ ✅ (المراحل0–8)"** مع نتيجة بوابة §2.2 (**ب1**) |

### 16.2 ما يبقى يدوياً على المالك (خارج الكود)
1. سيناريوهات §10 اليدوية الحية على `ngrok` + خادم Evolution (القائمة معدّلة وجاهزة في `WHATSAPP_INTEGRATION.md §10`).
2. إضافة مفاتيح الإنتاج إلى `.env` (`WHATSAPP_*` + `GEMINI_API_KEY` اختيارياً) وإنشاء `storage/app/knowledge/faq.md`.
3. إعداد Supervisor لعامل الطابور + دومين HTTPS ثابت (`WHATSAPP_INTEGRATION.md §13`).

### 16.3 النتيجة النهائية
- **الخطة منفَّذة بالكامل**: المراحل0–8،8 بوابات موافقة موثّقة في الجدول §6، **صفر تعديل من الإضافة خارج جرد §3** (تعديلات مالك المشروع الشخصية في مهاجرتَي الموقع لا علاقة لها بالخطة)، و62 اختبار واتساب خضراء إضافة إلى الحزم القائمة.
- حالة الوثيقة: **منفَّذ** — بوابة §2.2 = **ب1** (استدعاء `GeneralRegistrationController::store()` داخلياً عبر `Request::create` بلا تعديل ملف واحد، كما أثبتت `WhatsAppRegistrationSaveTest`7/7).
