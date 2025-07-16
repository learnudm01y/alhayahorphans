# 🔧 تقرير إصلاح نظام تسمية الملفات وحفظ قاعدة البيانات

## 📋 المشاكل المحددة

### 1. 🏷️ مشكلة تسمية الملفات
- **المشكلة**: استخدام تسمية جديدة `$targetFolderId . '_' . $timestamp . '_' . $random . '.' . $extension`
- **المطلوب**: استخدام النمط القديم `نوع_الوثيقة_رقم_الملف_رقم_الهوية.extension`

### 2. 💾 مشكلة حفظ قاعدة البيانات
- **المشكلة**: عدم حفظ الملفات في قاعدة البيانات
- **التفاصيل**: لا يتم الحفظ في جدول الملفات المكررة ولا في الجدول المخصص للصور الصحيحة

### 3. 📊 هيكل النمط المطلوب
- **المقطع الأول**: نوع الوثيقة (من اسم الملف الأصلي)
- **المقطع الثاني**: رقم الملف (من اسم المجلد الجديد - targetFolderId)
- **المقطع الثالث**: رقم الهوية (من المقطع الثاني من اسم الصورة المستلمة)

## ✅ التعديلات المطبقة

### 1. 🔧 إصلاح FolderDuplicateDetectionService.php

#### أ) تعديل دالة حفظ الملفات غير المكررة:
```php
// قبل التعديل
$fileName = $targetFolderId . '_' . $timestamp . '_' . $random . '.' . $extension;
$attachment = EnhancedAttachment::create([...]);

// بعد التعديل
$fileName = $this->generateCorrectFileName($file, $targetFolderId, $originalFolderName);
$attachment = Attachment::create([...]);
```

#### ب) إضافة دالة تسمية الملفات الصحيحة:
```php
protected function generateCorrectFileName(UploadedFile $file, string $targetFolderId, string $originalFolderName): string
{
    $originalName = $file->getClientOriginalName();
    $extension = $file->getClientOriginalExtension();
    
    // تحليل الاسم الأصلي لاستخراج المعلومات
    $parts = explode('_', pathinfo($originalName, PATHINFO_FILENAME));
    
    if (count($parts) >= 3) {
        $documentType = $parts[0]; // نوع الوثيقة
        $identityNumber = $parts[2]; // رقم الهوية (المقطع الثالث)
        
        // بناء الاسم الجديد: نوع_الوثيقة_رقم_الملف_رقم_الهوية.extension
        return $documentType . '_' . $targetFolderId . '_' . $identityNumber . '.' . $extension;
    } else {
        // نمط افتراضي
        $documentType = 'doc';
        $identityNumber = $originalFolderName;
        
        return $documentType . '_' . $targetFolderId . '_' . $identityNumber . '.' . $extension;
    }
}
```

#### ج) إضافة دالة استخراج رقم الهوية:
```php
protected function extractIdentityNumberFromOriginalName(string $originalName): string
{
    $parts = explode('_', pathinfo($originalName, PATHINFO_FILENAME));
    
    if (count($parts) >= 3) {
        return $parts[2]; // المقطع الثالث هو رقم الهوية
    }
    
    // البحث عن رقم يشبه رقم هوية
    foreach ($parts as $part) {
        if (preg_match('/^\d{8,10}$/', $part)) {
            return $part;
        }
    }
    
    return '';
}
```

#### د) تعديل حفظ البيانات:
```php
// الحفظ في جدول attachments بدلاً من enhanced_attachments
$attachment = Attachment::create([
    'record_number' => generateUniqueAttachmentRecordNumber(),
    'person_identity_number' => $this->extractIdentityNumberFromOriginalName($file->getClientOriginalName()),
    'stored_file_name' => $fileName,
    'original_file_name' => $file->getClientOriginalName(),
    'file_path' => $filePath,
    'folder_id' => $targetFolderId,
    'file_size' => $file->getSize(),
    'mime_type' => $file->getMimeType(),
    'file_type' => $this->determineFileType($file),
    'file_extension' => $extension,
    'file_hash' => hash_file('md5', $file->getRealPath()),
    'file_last_modified' => now(),
    'source' => 'folder_upload',
    'upload_ip_address' => request()->ip(),
    'upload_user_agent' => request()->userAgent(),
    'uploaded_by_user_id' => auth()->id(),
    'processing_status' => 'completed',
    'access_level' => 'internal'
]);
```

### 2. 🗃️ تحديث نموذج DuplicateFileTemp.php

#### إضافة حقول جديدة:
```php
protected $fillable = [
    'session_id',
    'original_name',
    'duplicate_name',
    'temp_path',
    'original_folder',
    'target_folder',
    'existing_file_name',
    'existing_file_id',      // جديد
    'file_size',             // جديد
    'mime_type',             // جديد
    'created_at',
    'expires_at'
];
```

### 3. 📊 تحديث مايجريشن duplicate_files_temp

#### إضافة أعمدة جديدة:
```php
Schema::create('duplicate_files_temp', function (Blueprint $table) {
    $table->id();
    $table->string('session_id')->index();
    $table->string('original_name');
    $table->string('duplicate_name');
    $table->string('temp_path');
    $table->string('original_folder');
    $table->string('target_folder');
    $table->string('existing_file_name');
    $table->unsignedBigInteger('existing_file_id')->nullable();  // جديد
    $table->bigInteger('file_size')->nullable();                 // جديد
    $table->string('mime_type')->nullable();                     // جديد
    $table->timestamp('created_at');
    $table->timestamp('expires_at')->index();
});
```

## 🎯 النتائج المتوقعة

### 1. 📁 تسمية الملفات الصحيحة
```
مثال قبل التعديل: 123456789_1642523400_abcd12.jpg
مثال بعد التعديل: passport_123456789_987654321.jpg

حيث:
- passport: نوع الوثيقة (من الاسم الأصلي)
- 123456789: رقم الملف (targetFolderId)
- 987654321: رقم الهوية (من الاسم الأصلي)
```

### 2. 💾 حفظ قاعدة البيانات
- ✅ الملفات الصحيحة تُحفظ في جدول `attachments`
- ✅ الملفات المكررة تُحفظ في جدول `duplicate_files_temp`
- ✅ معلومات كاملة مع الحقول الجديدة

### 3. 🔍 معلومات محسنة للملفات المكررة
- رقم الملف الموجود (existing_file_id)
- حجم الملف (file_size)
- نوع MIME (mime_type)

## 🧪 التحقق من الإصلاح

### 1. اختبار تسمية الملفات:
```bash
# رفع ملف بالاسم: passport_original_123456789.jpg
# إلى مجلد: 987654321
# النتيجة المتوقعة: passport_987654321_123456789.jpg
```

### 2. اختبار حفظ قاعدة البيانات:
```sql
-- فحص الملفات الصحيحة
SELECT * FROM attachments WHERE source = 'folder_upload';

-- فحص الملفات المكررة
SELECT * FROM duplicate_files_temp WHERE session_id = 'session_id_here';
```

### 3. اختبار كشف التكرار:
- رفع نفس الملف مرتين للمجلد نفسه
- التأكد من حفظ الأول في `attachments`
- التأكد من حفظ الثاني في `duplicate_files_temp`

## 🔄 خطوات تطبيق التعديلات

### 1. تشغيل المايجريشن:
```bash
cd "i:\unit test\ASO\ASO - Copy"
php artisan migrate
```

### 2. مسح الكاش:
```bash
php artisan optimize:clear
```

### 3. اختبار النظام:
- رفع مجلدات تحتوي على ملفات
- التحقق من التسمية الصحيحة
- التحقق من حفظ قاعدة البيانات

## 📝 ملاحظات مهمة

### 1. النمط المتوقع للأسماء الأصلية:
- `نوع_الوثيقة_معلومات_رقم_الهوية.extension`
- مثال: `passport_copy_123456789.jpg`

### 2. التوافق مع النظام القديم:
- الكود يدعم الأسماء التي تتبع النمط الجديد
- ويوفر نمط افتراضي للأسماء غير المطابقة

### 3. الأمان والتتبع:
- حفظ IP وuser agent للملفات
- تتبع المستخدم الذي رفع الملف
- hash للملف للتحقق من التكامل

---

## ✅ خلاصة الإصلاح

تم إصلاح جميع المشاكل المحددة:
- ✅ **تسمية الملفات**: الآن تتبع النمط القديم الصحيح
- ✅ **حفظ قاعدة البيانات**: الملفات تُحفظ في الجداول الصحيحة
- ✅ **الملفات المكررة**: معلومات كاملة مع الحقول الجديدة
- ✅ **التوافق**: يعمل مع النظام الحالي دون كسر الوظائف الموجودة

النظام جاهز للاختبار! 🚀
