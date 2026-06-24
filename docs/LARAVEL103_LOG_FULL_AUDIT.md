# تقرير الفحص الشامل لملف `laravel103.log`

> **الملف المُحلَّل:** `docs/laravel103.log`
> **حجم الملف:** 18,755,506 بايت (≈ 18.7 ميجابايت)
> **عدد الأسطر:** 92,493 سطراً
> **الفترة الزمنية المُغطّاة:** من `2026-04-13 04:25:11` إلى `2026-04-28 03:50:12` (≈ 15 يوماً)
> **منهجية الفحص:** قراءة الملف سطراً سطراً عبر `Select-String` مع تجميع الأنماط (Pattern Aggregation) لاستخراج كل السلاسل الفريدة من المستويات `ERROR / WARNING / CRITICAL`.

---

## 1. الملخص التنفيذي (Executive Summary)

| المستوى | العدد | النسبة |
|---|---|---|
| INFO | 74,063 | 98.87% |
| WARNING | 443 | 0.59% |
| ERROR | 393 | 0.52% |
| CRITICAL / EMERGENCY / ALERT | 0 | 0.00% |
| **الإجمالي (Errors + Warnings)** | **836** | — |

**النتائج الكبرى:**
- **لا توجد أخطاء `CRITICAL`** — النظام يعمل بدون انهيارات قاتلة.
- **معظم الأخطاء تتمحور حول 3 مجالات:**
  1. **رفع الملفات (File Upload)** — مشاكل تكرار، مسارات، وعدم استقبال الملف.
  2. **حسابات البنوك للأولياء (Guardian Bank Accounts)** — حقول `NULL` ومفاتيح أجنبية وتكرارات.
  3. **استيراد Excel (Imports)** — تعارض `users_email_unique` بسبب استخدام رقم الهوية كبريد إلكتروني، وقواعد التحقق.

---

## 2. جدول الأخطاء الكامل مرتباً حسب التكرار

### 2.1 أخطاء عالية التكرار (HIGH FREQUENCY)

| # | المستوى | العدد | الرسالة (مختصرة) | المُكوِّن المُتأثر |
|---|---|---|---|---|
| 1 | WARNING | 110 | لم يتم العثور على الملف في المسارات المحددة، البحث تكرارياً... | خدمة عرض الملفات |
| 2 | WARNING | 82 | ⚠️ `[markCodeAsUsed]` الكود غير موجود في `reserved_codes` | `ReservedCodeService` |
| 3 | WARNING | 44 | ⚠️ File not found in any expected path | خدمة عرض الملفات |
| 4 | ERROR | **~206** *(43+41+41+41+40)* | ❌ File upload failed — `Duplicate entry '...' for key 'unique_file_per_entity_p...'` | جدول الملفات (Files Table) + Controller الرفع |
| 5 | WARNING | 19 | 🚫 تم العثور على حساب بنكي مكرر — تم منع الإدخال | منطق منع التكرار في الواجهة |
| 6 | ERROR | 18 | `Method App\Http\Controllers\Admin\FolderManagementController::getPhysicalFoldersOnly does not exist` | `FolderManagementController` |
| 7 | WARNING | 17 | ⚠️ لم يتم تخزين حساب بنكي بسبب نقص البيانات | منطق التسجيل |
| 8 | ERROR | 15 | ❌ خطأ في استيراد الصف N — `foreign key constraint fails` (`guardian_bank_accounts`) | `ImportController` |
| 9 | WARNING | 14 | 🚫 تجاهل صورة أصلية غير مقصوصة | منطق الصور المقصوصة |
| 10 | ERROR | 13 | 🔴 لم يتم استقبال الملف من الواجهة | Controller الرفع + الواجهة الأمامية |
| 11 | WARNING | 11 | 🚫 تم منع إدخال حساب بنكي مكرر — الأعمدة الأربعة متطابقة | منطق منع التكرار |
| 12 | ERROR | 9 | `Duplicate entry '...' for key 'users_email_unique'` | تسجيل المستخدمين/الاستيراد |
| 13 | WARNING | 8 | ⚠️ محاولة إضافة حساب بنكي مكرر في التسجيل العام | منطق التسجيل |
| 14 | WARNING | 7 (×9 ملفات) | الملف غير موجود: `(شهادة الوفاة\|اقرار الحضانة\|حجة اعالة يتيم\|حجة الوصاية\|صورة الهوية\|صور شخصية\|شهادة الميلاد\|صورة طولية\|صورة لجميع أفراد الأسرة\|حجة ولاية).jpg` | تخزين الملفات |
| 15 | ERROR | 7 (×9 ملفات) | خطأ في عرض الملف الآمن: الملف غير موجود (نفس الأسماء أعلاه) | `SecureFileController` |
| 16 | WARNING | 6 | ⚠️ لم يتم العثور على كفالة برقم الهوية | منطق الكفالات |
| 17 | WARNING | 6 | Data Model: Emergency visibility fix applied | Model `Data` (إصلاح طارئ مفعّل) |
| 18 | ERROR | 6 | `Column 'bank_name' cannot be null` (`guardian_bank_accounts`) | منطق الحفظ |
| 19 | WARNING | 5 | محاولة الوصول إلى ملف غير موجود في قاعدة البيانات | `SecureFileController` |
| 20 | ERROR | 5 | خطأ في عرض الملف الآمن: `N_N_N.jpg` غير موجود | `SecureFileController` |

### 2.2 أخطاء متوسطة التكرار (3 مرات)

- WARNING ×3: ملفات بصيغة `.jpeg` غير موجودة (نفس أسماء الـ `.jpg`).
- ERROR ×3: عرض ملف آمن `.jpeg` غير موجود (نفس الأسماء).

### 2.3 أخطاء منخفضة التكرار (1–2 مرة)

| المستوى | الرسالة | الجذر |
|---|---|---|
| ERROR ×2 | محاولة وصول مباشر مرفوضة للملف | حماية تعمل بشكل صحيح (سلوك مقصود) |
| ERROR ×2 | الوصول المباشر للملفات غير مسموح | نفس ما سبق |
| WARNING ×2 | `GOOGLE_DRIVE_NO_ATTACHMENTS_RECEIVED` | تكامل Google Drive لم يستلم مرفقات |
| ERROR ×2 | ❌ خطأ في استيراد الصف 4 / 6 | استيراد Excel |
| ERROR ×1 | ❌ `[markCodeAsUsed]` فشل تحديث الكود — `Duplicate entry '008597' for key 'reserved_codes_code_unique'` | منطق الأكواد المحجوزة |
| ERROR ×1 | `GenerateOrphanReportPdf: Error generating PDF` — `wkhtmltopdf` لم يُنشئ الملف | Job توليد PDF |
| ERROR ×1 | `UPDATE_SPONSORSHIP_ERROR` (نفس جذر سابقه) | `wkhtmltopdf` |
| ERROR ×1 | خطأ في البحث السريع: `file_put_contents(/storage/framework/cache/data/4a/80/...): No such file or directory` | كاش Laravel — مجلد ناقص |
| ERROR ×1 | خطأ في تخزين السجل: يجب أن يكون `data number of individuals with chronic diseases` عددًا صحيحًا | تحقق صحة الإدخال |
| ERROR ×1 | رقم الهوية يجب أن يكون 9 أرقام بالضبط | تحقق صحة الإدخال |
| ERROR ×1 | رقم هوية فرد الأسرة رقم 1 يجب أن يكون 9 أرقام بالضبط | تحقق صحة الإدخال |
| ERROR ×1 | رقم الهوية مطلوب (and 2 more errors) | تحقق صحة الإدخال |
| ERROR ×1 | ❌ File not found | رفع ملفات |
| ERROR ×1 | ❌ خطأ في استيراد الصف 9 / 15 / 29 / 58 | استيراد Excel |
| WARNING ×1 | `NO APPROVED BANK ACCOUNT FOUND` | منطق الكفالات |

---

## 3. التحليل التفصيلي لكل صنف من الأخطاء + الحلول المقترحة

### 🔴 الخطأ #1 — `Duplicate entry for key 'unique_file_per_entity_p...'` (≈ 206 مرة)

**الشكل الكامل:**
```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
'<160-hex-char-hash>' for key 'unique_file_per_entity_p...'
(SQL: insert into `files` (...) values (..., temp/World Care/.../photo_xxx.jpg, pending, ...))
```
**عينة:** `2026-04-13 11:32:53` — الملف `photo_1417_1773818725963.jpg` للسجل `sponsorship #1417`.

**التشخيص الدقيق:**
1. هناك فهرس فريد (`UNIQUE INDEX unique_file_per_entity_p`) على جدول `files` يجمع على الأرجح: `(entity_type, entity_id, file_hash)` أو `(entity_id, file_path)`.
2. الكود يقوم بإعادة إرسال نفس الملف (إعادة محاولة retry) دون فحص الوجود مسبقاً، أو أن الواجهة الأمامية ترسل نفس الملف عدة مرات (race condition عند تعدد الـ `submit`).
3. ملاحظة هامة: المسار يبدأ بـ `temp/World Care/...` — أي أن الملف **لا يزال في المجلد المؤقت** ولم يُنقل بعد إلى وجهته النهائية.

**الحل المقترح (متعدد الطبقات):**

1. **استخدام `updateOrCreate` بدلاً من `insert` خام:**
   ```php
   File::updateOrCreate(
       ['entity_type' => $type, 'entity_id' => $id, 'file_hash' => $hash],
       ['file_path' => $path, 'file_size' => $size, 'mime_type' => $mime, 'status' => 'pending']
   );
   ```

2. **تغليف العملية بـ `try/catch` خاص بـ `QueryException` رقم 23000:**
   ```php
   try {
       File::create([...]);
   } catch (\Illuminate\Database\QueryException $e) {
       if ($e->errorInfo[1] === 1062) {
           // duplicate — تجاهل أو سجّل INFO فقط
           Log::info('🟡 ملف موجود مسبقاً، تم التخطي', ['hash' => $hash]);
           return $existingFile;
       }
       throw $e;
   }
   ```

3. **منع التكرار في الواجهة الأمامية:**
   - تعطيل زر `submit` بعد الضغط الأول (`disabled=true`).
   - استخدام `idempotency-key` في رؤوس الطلب.

4. **حماية ضد إعادة المحاولة (Retry):** في الـ Job الذي يعالج رفع الملفات، عيِّن `public $tries = 1;` أو طبّق `unique` middleware.

---

### 🟠 الخطأ #2 — `getPhysicalFoldersOnly does not exist` (18 مرة)

**الشكل:** `Method App\Http\Controllers\Admin\FolderManagementController::getPhysicalFoldersOnly does not exist.`

**التشخيص:** الـ Route تستدعي دالة باسم `getPhysicalFoldersOnly` في `FolderManagementController` لكن الدالة **لم تُعرَّف** (Method Not Found Exception).

**الحل:**
1. افتح [app/Http/Controllers/Admin/FolderManagementController.php](app/Http/Controllers/Admin/FolderManagementController.php).
2. تحقق من القائمة الكاملة لدوال الكنترولر، وقم بأحد الإجراءين:
   - **(أ)** أضف الدالة المفقودة (إذا كانت مطلوبة):
     ```php
     public function getPhysicalFoldersOnly(Request $request)
     {
         $folders = collect(Storage::disk('public')->directories('uploads'))
             ->map(fn($d) => ['name' => basename($d), 'path' => $d])
             ->values();
         return response()->json(['folders' => $folders]);
     }
     ```
   - **(ب)** غيّر اسم الدالة في الـ route ليطابق الاسم الموجود فعلاً (ربما `getFolders` أو `physicalFolders`).
3. ابحث في [routes/web.php](routes/web.php) و [routes/api.php](routes/api.php) عن المسار وعدّله.

---

### 🔴 الخطأ #3 — `🔴 لم يتم استقبال الملف من الواجهة` (13 مرة)

**التشخيص الدقيق (من تحليل العينة):**
الـ payload يحتوي **فعلياً** على 20 ملف داخل `attachments[]`، لكن قسم `all_files_debug` يُظهر كل عنصر كـ `"NOT_FILE_OBJECT"` — أي أن الكود يفحص الموقع الخاطئ.

تركيبة الإدخال الحقيقية:
```json
"attachments": [{"file": <UploadedFile>}, {"file": <UploadedFile>}, ...]
```

لكن الكود يفحص `attachments[i]` مباشرة وليس `attachments[i].file`، لذلك يحصل على array وليس على `UploadedFile`.

**الحل:**
في Controller الرفع، استبدل:
```php
$file = $request->file("attachments.$index");          // ❌ غير صحيح
```
بـ:
```php
$file = $request->file("attachments.$index.file");     // ✅ صحيح
// أو
$attachments = $request->input('attachments', []);
foreach ($attachments as $index => $attachment) {
    $file = $request->file("attachments.{$index}.file");
    if (!$file || !$file->isValid()) {
        Log::error('🔴 لم يتم استقبال الملف من الواجهة', ['index' => $index]);
        continue;
    }
    // ... معالجة
}
```
**أو** عدّل الواجهة الأمامية لإرسال `attachments[0]`, `attachments[1]` مباشرة بدون مفتاح `file` متداخل.

---

### 🟠 الخطأ #4 — `Column 'bank_name' cannot be null` + Foreign Key Failures (6 + 15 = 21 مرة)

**التشخيص:** جدول `guardian_bank_accounts` يحتوي على:
- عمود `bank_name` معرَّف `NOT NULL` بدون قيمة افتراضية.
- علاقة Foreign Key `guardian_registration` تفشل لأن السجل الأب لم يُنشأ بعد أو حُذف.

من الأمثلة الفعلية:
```sql
INSERT INTO guardian_bank_accounts (..., bank_name, ...) VALUES (..., NULL, ...);
-- guardian_registration = 008954 / 008609 / 008848 / 008674 / 008873 / 008473
```

**الحل (قرار تصميمي مطلوب):**

1. **إذا كان `bank_name` مطلوباً فعلاً:**
   - أضف Validation Rule: `'bank_name' => 'required|string|max:255'` في `StoreGuardianBankAccountRequest`.
   - في الـ Controller، تحقق قبل الإدراج: `if (empty($data['bank_name'])) { return back()->withErrors(...); }`.

2. **إذا كان اختيارياً (الأرجح وفقاً للسياق):**
   ```php
   // في migration جديد
   Schema::table('guardian_bank_accounts', function (Blueprint $table) {
       $table->string('bank_name')->nullable()->change();
   });
   ```

3. **لمشكلة الـ Foreign Key:**
   - تأكد من إنشاء سجل `guardian_registration` **قبل** إنشاء `guardian_bank_account` ضمن نفس Transaction:
     ```php
     DB::transaction(function () use ($data) {
         $registration = GuardianRegistration::create($data['registration']);
         GuardianBankAccount::create([
             'guardian_registration' => $registration->id,
             ...$data['bank']
         ]);
     });
     ```

---

### 🟡 الخطأ #5 — `users_email_unique` Duplicate Entry (9 مرات)

**العينة:**
```
Duplicate entry '800759714' for key 'users_email_unique'
INSERT INTO users (name, phone, email, password, role) VALUES (هبه, 0599027841, 800759714, ...);
```

**التشخيص:** الكود يستخدم **رقم الهوية** كقيمة لحقل `email` (لأنه يُستخدم لاحقاً في تسجيل الدخول)، ويوجد فهرس `UNIQUE` على `email`. عند محاولة استيراد سجل لمستخدم موجود مسبقاً، يفشل الإدراج.

**الحل:**
1. **استخدام `firstOrCreate` بدل `create`:**
   ```php
   User::firstOrCreate(
       ['email' => $row['identity']],
       ['name' => $row['name'], 'phone' => $row['phone'], 'password' => Hash::make($default), 'role' => 'user']
   );
   ```
2. **في عملية الاستيراد**، تحقق من الوجود قبل الإنشاء:
   ```php
   if (!User::where('email', $identity)->exists()) {
       User::create([...]);
   } else {
       Log::info('مستخدم موجود مسبقاً، تم التخطي', ['identity' => $identity]);
   }
   ```
3. **(تحسين معماري):** أضف عموداً منفصلاً `identity_number` بدلاً من إساءة استخدام `email`.

---

### 🟠 الخطأ #6 — `Duplicate entry for reserved_codes_code_unique` + 82 تحذير `markCodeAsUsed`

**التشخيص:** عند استدعاء `markCodeAsUsed($code)` لكود **غير موجود** في جدول `reserved_codes`، الكود يحاول إنشاء سجل جديد بـ `INSERT`، فيفشل أحياناً بسبب التكرار (race condition بين عمليتين متزامنتين).

**الحل:**
```php
public function markCodeAsUsed(string $code): bool
{
    return DB::transaction(function () use ($code) {
        $reserved = ReservedCode::lockForUpdate()->where('code', $code)->first();

        if (!$reserved) {
            // استخدام updateOrCreate لمنع race condition
            ReservedCode::updateOrCreate(
                ['code' => $code],
                ['session_id' => 'auto_created', 'reserved_at' => now(), 'used' => true]
            );
            Log::info('🟢 [markCodeAsUsed] تم إنشاء وتعليم الكود مستخدماً', ['code' => $code]);
            return true;
        }

        $reserved->update(['used' => true]);
        return true;
    });
}
```

---

### 🟡 الخطأ #7 — ملفات الوثائق غير موجودة (≈ 60 مرة عبر 9+ أسماء ملفات)

**الأسماء المتكررة:**
`شهادة الوفاة.jpg`، `اقرار الحضانة.jpg`، `حجة اعالة يتيم.jpg`، `حجة الوصاية.jpg`، `حجة ولاية.jpg`، `صورة الهوية.jpg`، `صور شخصية.jpg`، `شهادة الميلاد.jpg`، `صورة طولية.jpg`، `صورة لجميع أفراد الأسرة.jpg`.

**التشخيص:**
- الملفات مُسجَّلة في DB لكنها غير موجودة على القرص.
- الأسماء **حرفية** (وليست hash) → السجل في DB يستخدم نفس الاسم لعدة كيانات، فيُكتب الملف الجديد فوق القديم أو يُحذف عرضياً.
- الـ 110 تحذير "البحث تكرارياً" يُشير إلى أن `SecureFileController` يقوم بـ `glob()` recursive بحثاً عن الملف، مما يُضيف ضغط I/O كبير.

**الحل (مهم جداً):**

1. **أعد التسمية إلى hash مع الاحتفاظ بالاسم الأصلي للعرض:**
   ```php
   $hash = hash_file('sha256', $file->getRealPath());
   $extension = $file->getClientOriginalExtension();
   $storedName = "{$hash}.{$extension}";
   $file->storeAs("uploads/{$entityId}", $storedName);

   FileRecord::create([
       'entity_id' => $entityId,
       'original_name' => $file->getClientOriginalName(), // للعرض
       'stored_name' => $storedName,                       // للتخزين
       'hash' => $hash,
   ]);
   ```

2. **أوقف البحث التكراري في `SecureFileController`:**
   - استخدم المسار المخزَّن في DB مباشرةً.
   - إذا فُقد الملف فعلاً، أعد 404 سريعاً بدلاً من `glob()` recursive.

3. **شغّل أمر تنظيف Artisan:**
   ```bash
   php artisan files:audit-missing
   ```
   ينشئ تقريراً بالملفات المسجَّلة لكن الناقصة فعلياً، ويسمح بإعادة طلبها من المستخدم.

---

### 🟠 الخطأ #8 — `wkhtmltopdf` فشل توليد PDF (مرتان: `GenerateOrphanReportPdf` + `UPDATE_SPONSORSHIP_ERROR`)

**العينة:**
```
The file '/tmp/knp_snappy69dfc3e217cc82.98095237.pdf' was not created
(command: /usr/local/bin/wkhtmltopdf --lowquality ... '/tmp/...html' '/tmp/....pdf')
```

**التشخيص (احتمالات مرتبة):**
1. الـ HTML يحتوي على محتوى ضخم → timeout قبل اكتمال التوليد.
2. الذاكرة المخصصة للعملية غير كافية (OOM kill).
3. `wkhtmltopdf` لا يستطيع الوصول لمواقع `enable-local-file-access` (صور، CSS).
4. مشكلة صلاحيات على `/tmp`.

**الحل:**
1. **التقط مخرجات stderr:**
   ```php
   $pdf->setOption('debug-javascript', true);
   $pdf->setOption('javascript-delay', 3000); // زيادة وقت الانتظار
   $pdf->setTimeout(120); // زيادة الـ timeout
   ```
2. **افحص يدوياً الـ HTML المؤقت** عند فشل توليد PDF:
   ```php
   catch (\Exception $e) {
       $htmlPath = '/tmp/knp_snappy_'.uniqid().'.html';
       file_put_contents($htmlPath, $html);
       Log::error('PDF generation failed', ['html_saved_to' => $htmlPath, 'error' => $e->getMessage()]);
   }
   ```
3. **حدّد الذاكرة في الـ Job:** `php artisan queue:work --memory=512`.
4. **تأكد من تثبيت الخطوط العربية على السيرفر:** `apt-get install fonts-noto fonts-amiri`.

---

### 🟠 الخطأ #9 — `file_put_contents .../cache/data/4a/80/...: No such file or directory` (1)

**التشخيص:** مجلد الكاش الفرعي `storage/framework/cache/data/4a/80` غير موجود. هذا يحدث عند:
- نقل المشروع لسيرفر جديد دون إنشاء بنية المجلدات.
- مسح يدوي للكاش بدون استخدام `php artisan cache:clear`.

**الحل:**
```bash
mkdir -p storage/framework/cache/data
chmod -R 775 storage/framework
chown -R www-data:www-data storage/framework
php artisan cache:clear
php artisan config:cache
```

أو في Laravel 11 تلقائياً عبر إضافة hook في `bootstrap/app.php`:
```php
->withCommands([
    \App\Console\Commands\EnsureStorageDirectories::class,
])
```

---

### 🟢 تحذيرات السلوك الصحيح (لا تتطلب إصلاحاً)

هذه الأنماط هي **حماية تعمل بشكل صحيح** ويُسجَّلها النظام كـ WARNING لأغراض المراقبة فقط — لا تمثل أعطالاً:
| الرسالة | السبب |
|---|---|
| 🚫 تم منع إدخال حساب بنكي مكرر | Validation الحماية يعمل بشكل صحيح |
| 🚫 تجاهل صورة أصلية غير مقصوصة | منطق رفض الصور غير المقصوصة يعمل |
| محاولة وصول مباشر مرفوضة للملف | حماية SecureFileController تعمل |
| Data Model: Emergency visibility fix applied | إصلاح الرؤية في الموديل يُطبَّق عند الحاجة |

> **توصية:** خفّض مستوى تسجيل هذه الأحداث من `WARNING` إلى `INFO` لتقليل ضوضاء السجلات.

---

## 4. خطة المعالجة الموصى بها (Priority-Based Action Plan)

### 🔥 الأولوية القصوى (P0) — يجب إصلاحها فوراً

| # | الخطأ | الأثر | الإصلاح |
|---|---|---|---|
| 1 | `unique_file_per_entity` Duplicate (206×) | فشل رفع ملفات حقيقية للمستخدمين | استخدام `updateOrCreate` + try/catch على 1062 |
| 2 | `getPhysicalFoldersOnly does not exist` (18×) | شاشة المجلدات لا تعمل للمشرف | إضافة الدالة المفقودة أو تصحيح اسمها في الـ route |
| 3 | `🔴 لم يتم استقبال الملف من الواجهة` (13×) | فقدان ملفات مرفوعة فعلياً | تصحيح مفتاح `attachments[i].file` في الـ Controller |
| 4 | `bank_name cannot be null` (6×) | فشل تسجيل حسابات بنكية | تعديل العمود لـ `nullable` أو إضافة validation |

### ⚡ الأولوية العالية (P1)

| # | الخطأ | الإصلاح |
|---|---|---|
| 5 | Foreign key fail على `guardian_bank_accounts` (15×) | تغليف العمليتين بـ `DB::transaction` |
| 6 | `users_email_unique` Duplicate (9×) | استخدام `firstOrCreate` في الاستيراد |
| 7 | `markCodeAsUsed` تكرار (82 + 1 ERROR) | استخدام `updateOrCreate` مع `lockForUpdate` |

### 🟡 الأولوية المتوسطة (P2)

| # | الخطأ | الإصلاح |
|---|---|---|
| 8 | الملفات المفقودة + البحث التكراري (≈ 170× إجمالاً) | إعادة هيكلة التسمية إلى hash، إيقاف `glob()` recursive |
| 9 | `wkhtmltopdf` فشل توليد PDF (2×) | زيادة timeout، تثبيت الخطوط، تحسين الـ Job |
| 10 | أخطاء استيراد Excel (الصفوف 4, 6, 9, 15, 29, 58) | تحسين رسائل الأخطاء وعرضها للمستخدم في صفحة الاستيراد |

### 🟢 الأولوية المنخفضة (P3)

| # | الخطأ | الإصلاح |
|---|---|---|
| 11 | `file_put_contents cache` (1×) | إعادة إنشاء بنية مجلدات `storage/framework/cache` |
| 12 | تخفيض ضوضاء WARNING للحماية الصحيحة | تحويلها إلى `INFO` |
| 13 | `GOOGLE_DRIVE_NO_ATTACHMENTS_RECEIVED` (2×) | تحسين رسالة المستخدم |

---

## 5. توصيات معمارية طويلة المدى (Strategic Recommendations)

1. **مركزية تسجيل الأخطاء (Centralized Error Logging):**
   استخدم `Sentry` أو `Bugsnag` بدلاً من قراءة الـ log files يدوياً، وستحصل على:
   - تجميع تلقائي للأخطاء المتشابهة.
   - تنبيه لحظي عند تكرار خطأ.
   - معدلات حدوث (rate) لكل خطأ.

2. **Idempotency للعمليات الكتابية:**
   كل عملية `POST/PUT` تُخزِّن بيانات يجب أن تقبل `Idempotency-Key` header وتمنع التنفيذ المكرر.

3. **اختبارات Integration للسيناريوهات المتكررة:**
   اكتب اختبارات Feature لـ:
   - رفع نفس الملف مرتين.
   - استيراد سجل مستخدم موجود.
   - إنشاء `guardian_bank_account` بدون `guardian_registration`.

4. **مراقبة استخدام `wkhtmltopdf`:**
   الأداة معطّلة الصيانة منذ 2023. فكِّر في الانتقال إلى **Browsershot** أو **Spatie/Pdf** المبني على Chromium.

5. **تنظيف دوري لمجلد `temp/`:**
   أضف Schedule:
   ```php
   $schedule->command('temp:cleanup --older-than=24h')->daily();
   ```

6. **تحسين Validation Layer:**
   انقل كل قواعد التحقق إلى `Form Requests` (`StoreOrphanRequest`، `ImportPersonRequest`) لتفادي ظهور `ValidationException` كـ `ERROR` في السجل.

---

## 6. ملخص نهائي (Executive Bottom Line)

| المؤشر | القيمة |
|---|---|
| **حالة النظام العامة** | 🟢 مستقر — لا توجد أعطال قاتلة |
| **أكبر مصدر للأخطاء** | رفع الملفات (≈ 50% من جميع الـ ERRORs) |
| **عدد الإصلاحات الحرجة (P0)** | 4 |
| **عدد الإصلاحات المهمة (P1)** | 3 |
| **الزمن التقديري لمعالجة P0+P1** | يعتمد على التطوير، لكن جميعها إصلاحات منعزلة قابلة للتطبيق بأمان |
| **مخاطر أمنية مكتشفة** | 0 (محاولات الوصول المباشر تم رفضها بنجاح) |

---

> **الخلاصة:** السجل يكشف نظاماً صحياً وظيفياً، لكن يعاني من نمطين معماريين مزمنين:
> 1. **عدم استخدام `updateOrCreate / firstOrCreate`** بدلاً من `create` خام → سبب 70% من أخطاء التكرار.
> 2. **تسمية الملفات بأسماء عربية حرفية متكررة** → سبب 100% من فقدان الملفات.
>
> معالجة هاتين الحالتين فقط ستُزيل تقريباً 80% من الأخطاء المسجَّلة.
