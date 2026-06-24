# 📊 تقرير تحليل شامل لملف Laravel Log

**التاريخ:** 2026-04-13  
**الفترة المغطاة:** 2026-04-05 → 2026-04-13 (8 أيام)  
**حجم الملف:** 7.58 MB | **عدد الأسطر:** 45,912  
**إجمالي الأخطاء (ERROR):** 76 | **التحذيرات (WARNING):** 410  

---

## 📋 ملخص تنفيذي

تم تحليل ملف `laravel.log` بالكامل سطراً بسطر. تم تصنيف **486 مشكلة** إلى **21 فئة مختلفة**. الجدول التالي يوضح ترتيب المشاكل حسب الخطورة والتكرار:

| # | الفئة | النوع | العدد | الخطورة |
|---|-------|-------|-------|---------|
| 1 | حسابات بنكية مكررة (موجودة مسبقاً) | WARNING | 133 | 🟡 متوسطة |
| 2 | أكواد غير موجودة في reserved_codes | WARNING | 78 | 🔴 عالية |
| 3 | كفالات موجودة مسبقاً | WARNING | 69 | 🟡 متوسطة |
| 4 | حسابات بنكية مكررة (4 أعمدة متطابقة) | WARNING | 64 | 🟡 متوسطة |
| 5 | فشل رفع Google Drive (مكرر) | ERROR | 53 | 🔴 حرجة |
| 6 | تكرارات في ملف Excel | WARNING | 25 | 🟡 متوسطة |
| 7 | تخطي إنشاء مكفول (بدون معيل) | WARNING | 18 | 🔴 عالية |
| 8 | فشل كتابة ملفات Cache | ERROR | 8 | 🔴 عالية |
| 9 | سجلات مكررة في قاعدة البيانات | WARNING | 8 | 🟡 متوسطة |
| 10 | ملفات غير موجودة على السيرفر | ERROR | 8 | 🔴 حرجة |
| 11 | لم يتم إرسال bank_accounts_updates | WARNING | 12 | 🟡 متوسطة |
| 12 | تكرارات في ملف Excel | WARNING | 4 | 🟡 متوسطة |
| 13 | أفراد عائلة بدون معيل | WARNING | 4 | 🟡 متوسطة |
| 14 | ملفات لم تستقبل من الواجهة | ERROR | 4 | 🔴 عالية |
| 15 | خطأ Avatar null | ERROR | 2 | 🔴 حرجة |
| 16 | بنك غير موجود (Palpay) | WARNING | 2 | 🟡 متوسطة |
| 17 | مستخدم مكرر (email_unique) | ERROR | 1 | 🟡 متوسطة |
| 18 | bank_name لا يمكن أن يكون null | ERROR | 1 | 🔴 عالية |
| 19 | خطأ تاريخ ميلاد | ERROR | 1 | 🟡 متوسطة |
| 20 | خطأ تحقق الاسم الأول | ERROR | 1 | 🟡 متوسطة |
| 21 | حذف ملفات قسري | WARNING | 1 | 🟢 منخفضة |

---

## 🔴 المشكلة #1: فشل رفع Google Drive المتكرر (53 خطأ — حرجة)

### الوصف
نظام رفع الملفات إلى Google Drive يحاول إدخال سجلات مكررة في جدول `google_drive_uploads` ويفشل بسبب مفتاح فريد `unique_file_per_entity`.

### تفاصيل الخطأ
```
SQLSTATE[23000]: Integrity constraint violation: 1062 
Duplicate entry '...' for key 'unique_file_per_entity'
```

### الملفات المتأثرة (5 ملفات عالقة تتكرر باستمرار)
| الملف | الكفالة | عدد المحاولات الفاشلة |
|-------|---------|----------------------|
| `photo_1417_1773818725963.jpg` (عمر ناجي غازي صلاح) | #1417 | ~15 مرة |
| `photo_1443_1773824371183.jpg` (ايوب جميل بشير وافي) | #1443 | ~15 مرة |
| `photo_1499_1775907385426.jpg` (ريتاج محمود غانم أبو كميل) | #1499 | ~8 مرات |
| `photo_1625_1775980985936.jpg` (غنى إسماعيل فايز السراج) | #1625 | ~5 مرات |
| `photo_1632_1775722065302.jpg` (مريم احمد معين عياش) | #1632 | ~5 مرات |

### السبب الجذري
الكود في `GoogleDriveUploadController.php` يستخدم `updateOrCreate` لكن هناك cron job أو scheduler يعيد محاولة رفع الملفات العالقة بشكل دوري. المشكلة أن الملف **موجود بالفعل في قاعدة البيانات** لكن نظام إعادة المحاولة لا يفحص ذلك قبل محاولة الإدخال.

### الموقع في الكود
- **الملف:** `app/Http/Controllers/Api/GoogleDriveUploadController.php`
- **الخطوط:** 85-135

### الحل النهائي
```php
// قبل محاولة الإدخال، تحقق من وجود الملف
$existingUpload = GoogleDriveUpload::where('local_file_hash', $fileHash)
    ->where('entity_type', $entityType)
    ->where('entity_id', $entityId)
    ->first();

if ($existingUpload) {
    // إذا كان الملف مرفوع بنجاح، تخطي
    if ($existingUpload->upload_status === 'completed') {
        Log::info('✅ الملف مرفوع مسبقاً، تم التخطي', [
            'file_hash' => $fileHash,
            'entity_id' => $entityId
        ]);
        return; // لا تحاول الإدخال مرة أخرى
    }
    // إذا كان عالق، حدّث بدلاً من الإدخال
    $existingUpload->update(['upload_status' => 'pending', 'retry_count' => 0]);
    return;
}
```

**الإجراء الفوري على السيرفر:**
```sql
-- حذف السجلات العالقة التي تسبب التكرار
DELETE FROM google_drive_uploads 
WHERE upload_status = 'pending' 
AND entity_id IN (1417, 1443, 1499, 1625, 1632)
AND synced_to_server = 0;
```

---

## 🔴 المشكلة #2: فشل كتابة ملفات Cache (8 أخطاء — عالية)

### الوصف
```
file_put_contents(/var/www/html/alhayahorphans/storage/framework/cache/data/b3/fe/...): 
Failed to open stream: No such file or directory
```

### المجلدات المتأثرة
- `cache/data/b3/fe/`
- `cache/data/b3/31/`
- `cache/data/4a/de/`
- `cache/data/29/1f/`
- `cache/data/b3/1e/`

### السبب الجذري
Laravel يستخدم file-based cache driver ومجلدات الـ cache الفرعية غير موجودة. هذا يحدث عادة بعد:
1. مسح الـ cache يدوياً بدون إعادة تهيئة المجلدات
2. صلاحيات غير كافية على مجلد `storage/framework/cache`
3. استخدام `php artisan cache:clear` على بيئة production

### الموقع في الكود
- **خطأ في:** `ProfileSearchController.php` (البحث السريع)
- **السطر:** استخدام `Cache::remember()` مع file driver

### الحل النهائي

**الخيار 1 (موصى به): تغيير cache driver إلى Redis أو Database**
```env
# في ملف .env على السيرفر
CACHE_DRIVER=database
# أو
CACHE_DRIVER=redis
```

**الخيار 2: إصلاح المجلدات**
```bash
# على السيرفر
cd /var/www/html/alhayahorphans
php artisan cache:clear
mkdir -p storage/framework/cache/data
chmod -R 775 storage/framework/cache
chown -R www-data:www-data storage/framework/cache
```

**الخيار 3: إضافة معالجة أخطاء في الكود**
```php
// في ProfileSearchController.php
try {
    $results = Cache::remember($cacheKey, 60, function () use ($query) {
        return $this->performQuickSearch($query);
    });
} catch (\Exception $e) {
    Log::warning('Cache write failed, executing without cache', ['error' => $e->getMessage()]);
    $results = $this->performQuickSearch($query);
}
```

---

## 🔴 المشكلة #3: خطأ Avatar Null — Attempt to read property on null (2 أخطاء — حرجة)

### الوصف
```
Attempt to read property "avatar" on null
View: resources/views/admin/dashboard/toolbars/index.blade.php
```

### السبب الجذري
المستخدم الحالي (`Auth::user()`) يكون `null` عند الوصول للصفحة. هذا يحدث عندما:
1. جلسة المستخدم انتهت لكن الصفحة لا تزال مفتوحة
2. طلب AJAX بعد انتهاء الجلسة
3. مشكلة في middleware الـ authentication

### الموقع في الكود
- **الملف:** `resources/views/admin/dashboard/toolbars/index.blade.php`
- **السطر:** ~267

### الحل النهائي
```blade
{{-- الكود الحالي (خاطئ) --}}
<img alt="Logo" src="{{ asset(Auth::user()->avatar) }}?v={{ time() }}" />

{{-- الكود الصحيح --}}
<img alt="Logo" 
    src="{{ Auth::user() && Auth::user()->avatar 
        ? asset(Auth::user()->avatar) 
        : asset('admin/assets/media/avatars/default.jpg') }}?v={{ time() }}" />
```

**إضافة في middleware:**
```php
// التأكد من أن الـ auth middleware مطبق على كل routes لوحة التحكم
Route::middleware(['auth', 'verified'])->group(function () {
    // dashboard routes
});
```

---

## 🔴 المشكلة #4: أكواد غير موجودة في reserved_codes (78 تحذير — عالية)

### الوصف
```
⚠️ [markCodeAsUsed] الكود غير موجود في reserved_codes {"code":"008124"}
```

### الأكواد المتأثرة (78 كود فريد)
```
008066, 008068, 008074-008080, 008124, 008126-008127, 008129, 008135,
008296-008298, 008300, 008302-008303, 008311-008317, 008319-008320,
008326, 008329, 008344-008345, 008350, 008353, 008356-008360,
008362-008363, 008366, 008369, 008374-008375, 008377, 008383,
008386-008388, 008392-008393, 008395, 008397-008400, 008402-008405,
008407-008411, 008413-008414, 008420, 008427, 008430-008431,
008433-008435, 008439, 008443
```

### السبب الجذري
عند إنشاء كفالة جديدة، يتم تعيين رقم ملف (file_number) من جدول `reserved_codes`. لكن الأكواد المذكورة لم يتم حجزها مسبقاً في هذا الجدول. هذا يعني:
1. تم إنشاء الكفالات مباشرة بدون المرور بنظام حجز الأكواد
2. أو تم حذف الأكواد من الجدول بعد الاستخدام
3. أو نظام المزامنة بين `data` و `reserved_codes` لم يعمل بشكل صحيح

### الحل النهائي
```php
// في دالة markCodeAsUsed - إضافة إدخال تلقائي إذا لم يكن موجوداً
public function markCodeAsUsed(string $code): bool
{
    $reserved = ReservedCode::where('code', $code)->first();
    
    if (!$reserved) {
        // إنشاء الكود تلقائياً وتعليمه كمستخدم
        ReservedCode::create([
            'code' => $code,
            'is_used' => true,
            'used_at' => now(),
        ]);
        Log::info('✅ [markCodeAsUsed] تم إنشاء الكود وتعليمه كمستخدم تلقائياً', [
            'code' => $code
        ]);
        return true;
    }
    
    $reserved->update(['is_used' => true, 'used_at' => now()]);
    return true;
}
```

**إجراء فوري على السيرفر:**
```sql
-- مزامنة الأكواد المفقودة من جدول data إلى reserved_codes
INSERT INTO reserved_codes (code, is_used, used_at, created_at, updated_at)
SELECT DISTINCT file_number, 1, NOW(), NOW(), NOW()
FROM data 
WHERE file_number IS NOT NULL 
  AND file_number NOT IN (SELECT code FROM reserved_codes);
```

---

## 🔴 المشكلة #5: ملفات غير موجودة على السيرفر (8 أخطاء — حرجة)

### الوصف
```
❌ File not found {"path":"logs/laravel.log",
  "full_path":"/var/www/html/alhayahorphans/storage/app/public/logs/laravel.log"}
❌ File not found {"path":"logs/error.log", ...}
❌ File not found {"path":".env", ...}
```

### السبب الجذري
شخص ما يحاول الوصول إلى ملفات حساسة عبر API أو route الملفات:
- `logs/laravel.log` — **ثغرة أمنية!** محاولة قراءة سجلات النظام
- `logs/error.log` — محاولة قراءة سجلات الأخطاء
- `.env` — **ثغرة أمنية خطيرة!** محاولة قراءة بيانات الاعتماد

### ⚠️ تنبيه أمني هام
هذه المحاولات قد تكون **هجمات اختراق** أو محاولات استكشاف من بوتات. يجب التأكد من أن route الملفات لا يسمح بالوصول للملفات خارج المجلد المحدد.

### الحل النهائي
```php
// في controller الملفات - إضافة فلترة أمنية صارمة
public function getFile(Request $request, $path)
{
    // منع الوصول لملفات حساسة
    $blockedPaths = ['.env', 'logs/', '.git', 'config/', 'vendor/'];
    foreach ($blockedPaths as $blocked) {
        if (str_starts_with($path, $blocked) || str_contains($path, '..')) {
            abort(403, 'Access denied');
        }
    }
    
    // التحقق من أن الملف ضمن المجلد المسموح فقط
    $fullPath = storage_path('app/public/' . $path);
    $realPath = realpath($fullPath);
    $allowedBase = realpath(storage_path('app/public'));
    
    if (!$realPath || !str_starts_with($realPath, $allowedBase)) {
        abort(403, 'Path traversal detected');
    }
    
    if (!file_exists($fullPath)) {
        abort(404);
    }
    
    return response()->file($fullPath);
}
```

---

## 🟡 المشكلة #6: حسابات بنكية مكررة (197 تحذير — متوسطة)

### الوصف
نوعان من التحذيرات:
1. **`تم العثور على حساب بنكي مكرر`** (133 مرة) — حساب موجود بنفس البيانات الخمسة
2. **`تم منع إدخال حساب بنكي مكرر - الأعمدة الأربعة متطابقة`** (64 مرة)

### السبب الجذري
عند استيراد بيانات من Excel، يتم محاولة إدخال حسابات بنكية لأيتام ينتمون لنفس العائلة. العائلة الواحدة عادة لها حساب بنكي واحد مشترك، فعند إدخال كل طفل يتم محاولة إدخال نفس الحساب.

### التأثير
**لا يوجد تأثير فعلي** — النظام يمنع الإدخال بنجاح ويسجل التحذير. هذا سلوك صحيح ولكنه يسبب:
- تضخم ملف الـ log بشكل غير ضروري
- صعوبة في العثور على الأخطاء الحقيقية

### الحل النهائي
```php
// تقليل مستوى الـ logging من WARNING إلى DEBUG
Log::debug('🚫 تم العثور على حساب بنكي مكرر - تم منع الإدخال:', [
    'existing_account_id' => $existingAccount->id,
    // ...
]);

// أو الأفضل: فحص التكرار على مستوى الدفعة قبل الإدخال
$uniqueBankAccounts = collect($bankAccounts)->unique(function ($item) {
    return $item['re_id_number'] . '_' . $item['bank_name'];
});
```

---

## 🟡 المشكلة #7: كفالات مكررة في قاعدة البيانات (69 + 8 تحذير)

### الوصف
```
⚠️ الكفالة موجودة مسبقاً - سيتم تخطي إنشاء كفالة جديدة
⚠️ تم اكتشاف سجلات مكررة في قاعدة البيانات: {"count":34,"sponsor_id":"10",...}
```

### السبب الجذري
تم رفع نفس ملف Excel أكثر من مرة (على الأقل 4 مرات بتاريخ 08-04-2026):
- 06:07:20
- 06:07:34
- 06:09:11
- 06:09:14

النظام يتعامل مع هذا بشكل صحيح بتخطي السجلات الموجودة.

### الحل النهائي
```php
// إضافة hash للملف المرفوع لمنع إعادة المعالجة
public function importExcel(Request $request)
{
    $fileHash = hash_file('sha256', $request->file('excel')->path());
    
    if (ImportLog::where('file_hash', $fileHash)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'هذا الملف تم استيراده مسبقاً'
        ], 422);
    }
    
    // المتابعة بالاستيراد ...
    ImportLog::create(['file_hash' => $fileHash, 'imported_at' => now()]);
}
```

---

## 🔴 المشكلة #8: أيتام بدون معيل — 18 يتيم (عالية)

### الوصف
```
⛔ تم تخطي إنشاء المكفول - المعيل غير موجود أو فشل إنشاؤه
```

### الأيتام المتأثرون (18 يتيم)
| الصف | الاسم | رقم الهوية |
|------|-------|-----------|
| 136 | يوسف محمد احمد الكرد | 435277876 |
| 137 | يوسف رجب غازي ابومزيد | 437179526 |
| 138 | رقيه علي الرحال | 442831327 |
| 139 | مسك محمد تهامي الصالحي | 444735153 |
| 140 | محمد خالد محمد مصلح | 438388233 |
| 141 | نور عبدالله صبحي جمعه | 430216200 |
| 142 | مؤمن سلام عبدالجبار الحاج | 444423941 |
| 143 | تميم محمدصالح احمد حسونة | 440513422 |
| 144 | ميرا يوسف محمود البطران | 434424321 |
| 145 | عبد الرحمن محمد إسماعيل أبوطماعة | 430128488 |
| 146 | سارة محمد شفيق دغيش | 441302973 |
| 147 | مريم فادي فرج جندية | 442876660 |
| 148 | ليلى يحيى زامل الوحيدي | 438937963 |
| 149 | عمر حسن محمد سكر | 442267795 |
| 150 | ليان وسيم احمد سلمان | 436812226 |
| 151 | سارة وسام جواد صيام | 435034087 |
| 152 | زيد رامي موسى أبورحمة | 440526812 |
| 153 | روميساء سامي عبدربه العبيد | 441119203 |

### السبب الجذري
ملف Excel يحتوي على أيتام في الصفوف 136-153 بدون عمود `guardian_identity` (رقم هوية المعيل فارغ). هذه بيانات ناقصة من المصدر.

### الحل النهائي
```php
// الخيار 1: السماح بإنشاء أيتام بدون ربط بمعيل مؤقتاً
if (empty($guardianIdentity)) {
    // إنشاء اليتيم وتعليمه كـ "بحاجة لربط معيل"
    $sponsorship = Sponsorship::create([
        ...$data,
        'needs_guardian_link' => true,
    ]);
    Log::info('⚠️ تم إنشاء يتيم بدون معيل - يحتاج ربط لاحق', [
        'identity' => $identity,
        'name' => $name
    ]);
}
```

**إجراء مطلوب:** يجب مراجعة ملف Excel الأساسي وإضافة رقم هوية المعيل للصفوف 136-153.

---

## 🟡 المشكلة #9: بنك غير معروف — Palpay (2 تحذير)

### الوصف
```
⚠️ Bank not found: {"original_name":"حساب محفظة Palpay بال بي بديل"}
```

### الحل
```sql
-- إضافة البنك لقاعدة البيانات
INSERT INTO banks (name, normalized_name, is_active) 
VALUES ('حساب محفظة Palpay بال بي بديل', 'حساب محفظه palpay بال بي بديل', 1);
```

```php
// أو إضافة في الـ bank name mapping
$bankMapping = [
    'حساب محفظه palpay بال بي بديل' => 'palpay',
    'حساب محفظة palpay بال بي بديل' => 'palpay',
    // ...
];
```

---

## 🔴 المشكلة #10: ملفات لم تستقبل من الواجهة (4 أخطاء)

### الوصف
```
🔴 لم يتم استقبال الملف من الواجهة {"index":20-23, 
  "data":{"person_identity_number":"413374042",...}}
```

### السبب الجذري
الواجهة الأمامية (الموبايل) ترسل 24+ مرفقاً في طلب واحد. ملفات index 20-23 لم تصل إلى السيرفر. هذا عادة بسبب:
1. **حد حجم الرفع** — `upload_max_filesize` أو `post_max_size` في PHP
2. **حد عدد الملفات** — `max_file_uploads` في PHP.ini
3. **timeout** — انتهت مهلة الاتصال قبل اكتمال الرفع

### الحل النهائي
```bash
# على السيرفر — تعديل php.ini
upload_max_filesize = 100M
post_max_size = 150M
max_file_uploads = 50
max_execution_time = 300
max_input_time = 300

# إعادة تشغيل PHP
sudo systemctl restart php8.2-fpm
```

```php
// في الكود — رفع الملفات على دفعات
// بدلاً من رفع 24 ملف دفعة واحدة، رفع 5 ملفات في كل طلب
```

---

## 🟡 المشكلة #11: عدم إرسال bank_accounts_updates (12 تحذير)

### الوصف
```
⚠️ لم يتم إرسال bank_accounts_updates أو ليست مصفوفة 
{"sponsorship_id":1556,"reason":"NOT SET"}
```

### الكفالات المتأثرة
`1556, 1656, 1660, 1664, 1665, 1669, 1672`

### السبب الجذري
تطبيق الموبايل يرسل تحديثات للكفالة بدون تضمين حقل `bank_accounts_updates`. هذا يعني:
- إصدار قديم من التطبيق لا يدعم هذا الحقل
- أو المستخدم لم يعدّل البيانات البنكية

### الحل
```php
// تغيير من warning إلى debug (سلوك طبيعي)
if (!isset($updates['bank_accounts_updates'])) {
    Log::debug('bank_accounts_updates not sent', [
        'sponsorship_id' => $sponsorshipId
    ]);
    // لا تسجل warning - هذا سلوك طبيعي
}
```

---

## 🟡 المشكلة #12: خطأ bank_name لا يمكن أن يكون null (1 خطأ)

### الوصف
```
Column 'bank_name' cannot be null (insert into guardian_bank_accounts)
```

### الحل
```php
// إضافة تحقق قبل الإدخال
if (empty($bankData['bank_name'])) {
    Log::warning('تم تخطي إنشاء حساب بنكي - اسم البنك مطلوب', $bankData);
    return null;
}
```

---

## 🟡 المشكلة #13: أخطاء تحقق بيانات متنوعة (2 خطأ)

### 1. مستخدم مكرر
```
Duplicate entry '900339250' for key 'users_email_unique'
```
**الحل:** إضافة `firstOrCreate` بدلاً من `create` عند تسجيل المستخدمين.

### 2. تاريخ ميلاد غير صالح
```
data birth date ليس تاريخًا صحيحًا
```
**الحل:** تنظيف البيانات في ملف Excel قبل الاستيراد.

### 3. الاسم الأول مطلوب
```
data first name مطلوب
```
**الحل:** التحقق من اكتمال البيانات في الواجهة قبل الإرسال.

---

## 🟢 مشاكل ثانوية أخرى

### 1. UPDATE_SPONSORSHIP_UNMAPPED_FIELDS (1 تحذير)
حقول وصلت من الفورم لكن لا يوجد عمود مطابق لها:
- `field_guardian_job_text`
- `field_living_mother_id`
- `field_living_mother_first_name/second_name/third_name/last_name`

**الإجراء:** إما إضافة هذه الأعمدة لجدول `data` أو إضافتها للـ mapping.

### 2. مجلد الجمعية غير موجود (1 تحذير)
```
مجلد الجمعية غير موجود {"sponsor":"أيادي الخير"}
```
**الحل:** إنشاء المجلد على السيرفر:
```bash
mkdir -p /var/www/html/alhayahorphans/storage/app/mobile_uploads/أيادي\ الخير
chmod 775 /var/www/html/alhayahorphans/storage/app/mobile_uploads/أيادي\ الخير
```

### 3. No report design found for sponsor (1 تحذير)
```
No report design found for sponsor {"sponsor_id":null}
```
**الحل:** التحقق من وجود `sponsor_id` قبل البحث عن تصميم التقرير.

### 4. تجاهل صورة أصلية غير مقصوصة (1 تحذير)
سلوك طبيعي — النظام يتجاهل الصور غير المقصوصة بشكل صحيح.

---

## 📊 خطة الإصلاح المرتبة حسب الأولوية

### 🔴 الأولوية القصوى (يجب إصلاحها فوراً)

| # | المشكلة | التأثير | الإصلاح |
|---|---------|---------|---------|
| 1 | **ثغرة أمنية — File not found (.env)** | تسريب بيانات | إضافة فلترة أمنية لـ route الملفات |
| 2 | **Avatar null crash** | تعطل الصفحة | إضافة null check في blade |
| 3 | **Google Drive upload loop** | 53 خطأ متكرر يومياً | فحص الوجود قبل الإدخال + تنظيف DB |
| 4 | **Cache write failures** | فشل البحث السريع | إصلاح صلاحيات أو تغيير cache driver |

### 🟡 الأولوية العالية (خلال أسبوع)

| # | المشكلة | التأثير | الإصلاح |
|---|---------|---------|---------|
| 5 | **reserved_codes مفقودة** | 78 كود غير مسجل | مزامنة قاعدة البيانات |
| 6 | **18 يتيم بدون معيل** | بيانات ناقصة | تصحيح ملف Excel |
| 7 | **الملفات لم تستقبل** | مرفقات مفقودة | زيادة حدود PHP |
| 8 | **bank_name null** | فشل إدخال | إضافة validation |

### 🟢 الأولوية المنخفضة (تحسينات)

| # | المشكلة | التأثير | الإصلاح |
|---|---------|---------|---------|
| 9 | حسابات بنكية مكررة | تضخم log فقط | تخفيض log level |
| 10 | كفالات مكررة | تضخم log فقط | إضافة file hash check |
| 11 | bank_accounts_updates | تحذيرات غير ضرورية | تغيير إلى debug |
| 12 | بنك Palpay غير معروف | بيانات ناقصة | إضافة للجدول |

---

## 🔧 أوامر الإصلاح الفوري على السيرفر

```bash
# 1. إصلاح صلاحيات الـ cache
cd /var/www/html/alhayahorphans
php artisan cache:clear
mkdir -p storage/framework/cache/data
chmod -R 775 storage/framework/cache
chown -R www-data:www-data storage/

# 2. إنشاء مجلد أيادي الخير
mkdir -p storage/app/mobile_uploads/أيادي\ الخير
chmod 775 storage/app/mobile_uploads/أيادي\ الخير

# 3. تنظيف سجلات Google Drive العالقة
php artisan tinker
>>> \App\Models\GoogleDriveUpload::where('upload_status', 'pending')
...     ->whereIn('entity_id', [1417, 1443, 1499, 1625, 1632])
...     ->where('synced_to_server', 0)
...     ->delete();

# 4. مزامنة reserved_codes
php artisan tinker
>>> DB::statement("INSERT IGNORE INTO reserved_codes (code, is_used, used_at, created_at, updated_at) SELECT DISTINCT file_number, 1, NOW(), NOW(), NOW() FROM data WHERE file_number IS NOT NULL AND file_number NOT IN (SELECT code FROM reserved_codes)");

# 5. زيادة حدود PHP
sudo nano /etc/php/8.2/fpm/php.ini
# upload_max_filesize = 100M
# post_max_size = 150M  
# max_file_uploads = 50
# max_execution_time = 300
sudo systemctl restart php8.2-fpm

# 6. تنظيف ملف الـ log
> storage/logs/laravel.log
```

---

**نهاية التقرير**  
*تم إنشاؤه تلقائياً بتحليل 45,912 سطر من laravel.log*
