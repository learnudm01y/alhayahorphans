# تقرير تحليل أخطاء السيرفر - Laravel Log
**التاريخ:** 2026-04-05 إلى 2026-04-06  
**الملف:** `from_server/laravel.log`  
**البيئة:** Production Server — `https://alhayahorphans.org`

---

## ملخص تنفيذي

تم رصد **4 مشاكل رئيسية** في السيرفر العالمي، تتراوح بين أخطاء حرجة متكررة وتحذيرات تشير إلى خلل منطقي في الكود. الأكثر خطورة هو خطأ الـ **Duplicate Entry** في جدول `google_drive_uploads` والذي يتكرر مرات عديدة ويمنع رفع الصور.

---

## المشكلة الأولى — 🔴 CRITICAL: خطأ رفع الملفات إلى Google Drive (Duplicate Entry)

### الخطأ
```
[2026-04-06 05:38:20] local.ERROR: ❌ File upload failed - Exception caught
SQLSTATE[23000]: Integrity constraint violation: 1062 
Duplicate entry 'b8d6dfcdd9dfd76c2aefd6f7d662f7c7...' for key 'unique_file_per_entity'
```

### التفاصيل
- **الجدول المتأثر:** `google_drive_uploads`
- **عدد مرات التكرار:** الخطأ ظهر **6 مرات على الأقل** خلال ساعات مختلفة (05:38، 06:20، 06:39) لنفس الملفين:
  - `photo_1417_1773818725963.jpg` — (sponsorship_id: 1417)
  - `photo_1443_1773824371183.jpg` — (sponsorship_id: 1443)
- **السبب المباشر:** يحاول الكود إدراج سجل جديد لملف موجود مسبقاً في الجدول (القيد `unique_file_per_entity` يمنع ذلك).
- **السبب الجذري:** لا يوجد فحص `INSERT OR IGNORE` أو `updateOrCreate` / `firstOrCreate` قبل محاولة الإدراج. الكود يفترض دائماً أن الملف جديد.

### الحل
```php
// بدلاً من:
GoogleDriveUpload::create([...]);

// استخدم:
GoogleDriveUpload::firstOrCreate(
    ['local_file_hash' => $hash, 'entity_type' => $entityType, 'entity_id' => $entityId],
    [...البيانات الكاملة...]
);
```
أو على مستوى قاعدة البيانات:
```sql
INSERT IGNORE INTO google_drive_uploads (...) VALUES (...);
```

---

## المشكلة الثانية — 🔴 ERROR: فشل كتابة ملف الـ Cache

### الخطأ
```
[2026-04-05 09:15:03] local.ERROR: خطأ في البحث السريع:
file_put_contents(/var/www/html/alhayahorphans/storage/framework/cache/data/b3/fe/
b3fe6566805e480e4f41a035dcf7986bdcef59e8): 
Failed to open stream: No such file or directory
```

### التفاصيل
- **عدد مرات التكرار:** مرتان على الأقل (09:15:03 و 09:17:21).
- **السبب:** مجلد الـ Cache الفرعي `storage/framework/cache/data/b3/fe/` غير موجود على السيرفر.
- **التأثير:** فشل عملية البحث السريع في لوحة التحكم، وقد يؤثر على الأداء العام للتطبيق.

### الحل
```bash
# على السيرفر نفذ:
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# أو يدوياً:
mkdir -p /var/www/html/alhayahorphans/storage/framework/cache/data
chmod -R 775 /var/www/html/alhayahorphans/storage
chown -R www-data:www-data /var/www/html/alhayahorphans/storage
```

---

## المشكلة الثالثة — 🟡 WARNING: الأكواد غير موجودة في `reserved_codes` عند الاستخدام

### التحذيرات
```
[2026-04-05 09:55:40] local.WARNING: ⚠️ [markCodeAsUsed] الكود غير موجود في reserved_codes {"code":"008066"}
[2026-04-06 10:07:15] local.WARNING: ⚠️ [markCodeAsUsed] الكود غير موجود في reserved_codes {"code":"008075"}
[2026-04-06 10:25:45] local.WARNING: ⚠️ [markCodeAsUsed] الكود غير موجود في reserved_codes {"code":"008068"}
```

### التفاصيل
- **الأكواد المتأثرة:** `008066`, `008068`, `008075`, `008077`
- **السبب الجذري:** نظام الأكواد يعمل بمنطق مزدوج:
  1. **الـ Scheduler** يحجز الأكواد في `reserved_codes` مسبقاً.
  2. **المستخدم** يستخدم الكود (وظيفة `markCodeAsUsed`) لكن الكود لم يكن محجوزاً أصلاً في الجدول.
- **الملاحظة:** الكود رقم `008074` يظهر كـ "فجوة" ويُعاد استخدامه مرات عديدة جداً (أكثر من 10 مرات في يوم واحد) دون أن يُسجَّل في `data`، مما يشير إلى أن المستخدمين يحجزونه ثم يتركونه.

### الحل
- تأكد من أن منطق `getNextAvailableCode()` يُدرج الكود في `reserved_codes` قبل إرجاعه للمستخدم دائماً.
- إضافة `upsert` بدلاً من البحث والإدراج المنفصلَين لتجنب race conditions.

---

## المشكلة الرابعة — 🟡 WARNING: حقول غير مدعومة تصل من نموذج Sponsorship

### التحذير
```
[2026-04-05 12:37:00] local.WARNING: UPDATE_SPONSORSHIP_UNMAPPED_FIELDS
{
  "sponsorship_id": 1474,
  "unmapped_count": 6,
  "unmapped_keys_sample": [
    "field_guardian_job_text",
    "field_living_mother_id",
    "field_living_mother_first_name",
    "field_living_mother_second_name",
    "field_living_mother_third_name",
    "field_living_mother_last_name"
  ]
}
```

### التفاصيل
- **السبب:** نموذج تعديل الكفالة (Sponsorship) في الواجهة الأمامية يُرسل حقولاً جديدة (`field_living_mother_*`, `field_guardian_job_text`) لكن لا يوجد عمود مطابق في جدول `data` في قاعدة البيانات، ولا يوجد mapping لها في الكود.
- **التأثير:** البيانات المُرسلة من هذه الحقول تُتجاهل ولا تُحفظ.

### الحل
خيار 1 — إضافة الأعمدة إلى قاعدة البيانات:
```bash
php artisan make:migration add_mother_info_to_data_table
```
```php
$table->string('field_living_mother_id')->nullable();
$table->string('field_living_mother_first_name')->nullable();
// ... إلخ
```

خيار 2 — إزالة الحقول من النموذج إذا لم تكن مطلوبة.

---

## المشكلة الخامسة — 🟡 WARNING: تنفيذ Update مزدوج لنفس الطلب

### الملاحظة
```
[2026-04-05 09:16:22] local.INFO: --- Start update with nested attachmentsByPerson ---
[2026-04-05 09:16:22] local.INFO: --- Start update with nested attachmentsByPerson ---
[2026-04-05 09:16:22] local.INFO: --- Update success ---
[2026-04-05 09:16:22] local.INFO: --- Update success ---
```

### التفاصيل
- كل طلب `PUT` لتعديل سجل يُنفِّذ منطق التحديث **مرتين** في نفس الثانية وبنفس الـ `_token`.
- **السبب المحتمل:** وجود Route مكرر أو Middleware يُعيد تشغيل نفس الـ Controller action مرتين، أو Event Listener مسجَّل مرتين (`boot()` تُستدعى مرتين).
- **الخطر:** قد يؤدي إلى كتابة بيانات مكررة أو تضارب في تحديث `re_people` أو `attachments`.

### الحل
1. فحص Route definitions للتأكد من عدم تكرار نفس الـ action.
2. فحص `AppServiceProvider` أو `EventServiceProvider` لضمان عدم تسجيل الـ Listeners مرتين.
3. إضافة حماية Idempotency بـ transaction lock:
```php
DB::transaction(function() use ($request) {
    // منطق التحديث
});
```

---

## المشكلة السادسة — ℹ️ INFO: تضارب في مسارات تخزين المرفقات

### الملاحظة
من قراءة السجلات، المرفقات تُخزَّن في مسارات مختلفة:
- `storage/uploads/003737/...` ← المسار القديم
- `storage/attachments/003737/...` ← المسار الجديد (بعد Update)

### التفاصيل
- سجل ما قبل التعديل: `file_path: "storage/uploads/003737/3_003737_400160727.jpg"`
- سجل ما بعد التعديل: `file_path: "storage/attachments/003737/3_003737_400160727.jpg"`
- هذا يعني أن هناك migration غير مكتمل في البنية بين مجلد `uploads/` القديم ومجلد `attachments/` الجديد.

### الحل
تنفيذ سكريبت ترحيل يُحدِّث جميع السجلات القديمة في جدول `attachments` لتوحيد المسارات:
```bash
php artisan migrate:files --from=uploads --to=attachments
```

---

## جدول ملخص الأخطاء

| # | النوع | المشكلة | التكرار | الأولوية |
|---|-------|---------|---------|---------|
| 1 | ERROR | Duplicate Entry في `google_drive_uploads` | 6+ مرات | 🔴 عالية |
| 2 | ERROR | فشل كتابة Cache (مجلد مفقود) | 2+ مرات | 🔴 عالية |
| 3 | WARNING | أكواد غير موجودة في `reserved_codes` | 4 أكواد | 🟡 متوسطة |
| 4 | WARNING | حقول Sponsorship غير مُعيَّن لها أعمدة | مرة واحدة | 🟡 متوسطة |
| 5 | INFO | تنفيذ مزدوج لطلبات التحديث | كل Update | 🟡 متوسطة |
| 6 | INFO | تضارب مسارات تخزين المرفقات | متفرق | 🔵 منخفضة |

---

## الإجراءات الفورية المطلوبة (بالترتيب)

1. **الأسرع تأثيراً:** إصلاح `storage/framework/cache` بتشغيل `php artisan cache:clear` على السيرفر.
2. **الأهم برمجياً:** تغيير `GoogleDriveUpload::create()` إلى `firstOrCreate()` لمنع تكرار الـ 6 أخطاء اليومية.
3. **منع الفقدان الصامت للبيانات:** إضافة الأعمدة الجديدة للـ Sponsorship أو إزالة الحقول من الفورم.
4. **استقرار النظام:** التحقيق في سبب التنفيذ المزدوج لطلبات الـ Update.
