# نظام كشف الملفات المكررة

## نظرة عامة

تم إنشاء نظام شامل لكشف والتعامل مع الملفات المكررة عند رفع المجلدات. يقوم النظام بمقارنة أسماء الملفات مع الملفات الموجودة في قاعدة البيانات ويوفر خيارات متعددة للتعامل مع الملفات المكررة.

## المكونات الرئيسية

### 1. نموذج البيانات (Models)
- **DuplicateFileTemp**: نموذج لإدارة الملفات المكررة المؤقتة
- **Attachment**: محدث ليدعم عمود `file_name` للمقارنة

### 2. الخدمات (Services)
- **DuplicateFileDetectionService**: الخدمة الرئيسية لكشف ومعالجة الملفات المكررة

### 3. Controllers
- **DuplicateFileController**: Controller منفصل لإدارة عمليات الملفات المكررة
- **UnifiedFileManagementController**: محدث ليدعم كشف الملفات المكررة

### 4. واجهة المستخدم
- تم إضافة قسم "خيارات كشف الملفات المكررة" في الواجهة المتقدمة
- Modal لعرض الملفات المكررة
- أزرار لتحميل وحذف الملفات المكررة

## كيفية العمل

### 1. كشف الملفات المكررة
```php
// إنشاء جلسة جديدة لكشف المكررات
$sessionId = $duplicateDetectionService->initializeSession();

// فحص المجلد للملفات المكررة
$results = $duplicateDetectionService->processFolderForDuplicates($files, $folderName);
```

### 2. تحضير أسماء الملفات للمقارنة
- تحويل إلى أحرف صغيرة
- إزالة المسافات والأحرف الخاصة
- توحيد التنسيق

### 3. خيارات التعامل مع المكررات
- **store_temp**: حفظ في التخزين المؤقت (افتراضي)
- **skip**: تخطي الملفات المكررة
- **replace**: استبدال الملفات الموجودة

### 4. التخزين المؤقت
- الملفات المكررة تُحفظ في `storage/temp/[session_id]/`
- انتهاء صلاحية تلقائي خلال 7 أيام
- إمكانية تحميل كملف ZIP

## استخدام APIs

### كشف ملف واحد
```javascript
const formData = new FormData();
formData.append('file', file);
formData.append('folder_name', 'test_folder');

const response = await fetch('/api/files/check-single-duplicate', {
    method: 'POST',
    body: formData
});
```

### معالجة مجلد كامل
```javascript
const formData = new FormData();
files.forEach(file => formData.append('files[]', file));
formData.append('folder_name', folderName);

const response = await fetch('/api/files/process-folder-duplicates', {
    method: 'POST',
    body: formData
});
```

### جلب ملخص الملفات المكررة
```javascript
const response = await fetch(`/admin/file/duplicate-summary?session_id=${sessionId}`);
const data = await response.json();
```

### تحميل الملفات المكررة
```javascript
window.open(`/admin/file/download-duplicates?session_id=${sessionId}`);
```

### حذف الملفات المكررة
```javascript
const response = await fetch(`/admin/file/delete-duplicates?session_id=${sessionId}`, {
    method: 'DELETE'
});
```

## Routes المتاحة

### Admin Routes
- `GET /admin/file/download-duplicates` - تحميل جميع الملفات المكررة كـ ZIP
- `GET /admin/file/duplicate-summary` - جلب ملخص الملفات المكررة
- `DELETE /admin/file/delete-duplicates` - حذف الملفات المكررة
- `GET /admin/file/download-duplicate/{session_id}/{file_id}` - تحميل ملف مكرر واحد

### API Routes
- `POST /api/files/check-single-duplicate` - فحص ملف واحد
- `POST /api/files/process-folder-duplicates` - معالجة مجلد كامل
- `POST /api/files/process-folder-upload-with-duplicates` - رفع مجلد مع كشف المكررات
- `GET /api/files/duplicate-session-stats/{session_id}` - إحصائيات الجلسة
- `POST /api/files/clean-expired-duplicates` - تنظيف الملفات منتهية الصلاحية

## الأوامر (Commands)

### تنظيف الملفات منتهية الصلاحية
```bash
# تنظيف تلقائي
php artisan duplicates:clean-expired --force

# معاينة ما سيتم حذفه
php artisan duplicates:clean-expired --dry-run

# تنظيف تفاعلي
php artisan duplicates:clean-expired
```

## قاعدة البيانات

### جدول duplicate_files_temp
```sql
CREATE TABLE duplicate_files_temp (
    id BIGINT PRIMARY KEY,
    session_id VARCHAR(255) INDEX,
    original_name VARCHAR(255),
    duplicate_name VARCHAR(255),
    temp_path VARCHAR(255),
    original_folder VARCHAR(255),
    target_folder VARCHAR(255),
    existing_file_name VARCHAR(255),
    created_at TIMESTAMP,
    expires_at TIMESTAMP INDEX
);
```

### جدول attachments (محدث)
```sql
ALTER TABLE attachments ADD COLUMN file_name VARCHAR(255) INDEX AFTER stored_file_name;
ALTER TABLE attachments ADD COLUMN description TEXT AFTER file_size;
ALTER TABLE attachments ADD COLUMN uploaded_at TIMESTAMP AFTER description;
ALTER TABLE attachments ADD COLUMN upload_source VARCHAR(255) DEFAULT 'manual' AFTER uploaded_at;
```

## إعدادات التشغيل التلقائي

يتم تنظيف الملفات منتهية الصلاحية تلقائياً يومياً في الساعة 2:00 صباحاً.

## المزايا

1. **كشف ذكي**: يقارن أسماء الملفات بعد توحيد التنسيق
2. **مرونة في التعامل**: خيارات متعددة للملفات المكررة
3. **تخزين مؤقت آمن**: الملفات تنتهي صلاحيتها تلقائياً
4. **واجهة سهلة**: تكامل مع الواجهة الموجودة
5. **مراقبة شاملة**: logs مفصلة لجميع العمليات
6. **أداء محسن**: معالجة مجمعة للملفات

## الأمان

- جميع الملفات المؤقتة محمية في مجلد storage
- انتهاء صلاحية تلقائي للملفات
- تحقق من صحة جلسات المستخدمين
- logs مفصلة للمراقبة

## استكشاف الأخطاء

### مشاكل شائعة
1. **عدم ظهور الملفات المكررة**: تأكد من وجود عمود `file_name` في جدول attachments
2. **خطأ في التحميل**: تحقق من أذونات مجلد storage/temp
3. **عدم عمل التنظيف التلقائي**: تأكد من تفعيل cron jobs

### Logs
جميع العمليات مسجلة في:
- `storage/logs/laravel.log`
- البحث عن "Duplicate" في الـ logs
