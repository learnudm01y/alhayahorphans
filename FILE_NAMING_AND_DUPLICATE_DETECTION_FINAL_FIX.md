# تقرير إصلاح نظام تسمية الملفات وكشف التكرار النهائي

## التاريخ: 2025-07-16
## المشاكل المُكتشفة من سجل Laravel

---

## تحليل المشاكل من السجل:

### 1. **مشكلة أعمدة جدول document_types**
```log
[2025-07-16 04:14:31] local.ERROR: خطأ في الحصول على بادئة نوع الوثيقة {"folder_name":"99222555","error":"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'name' in 'where clause'"}
```

**السبب:** النظام يبحث عن عمود `name` لكن الجدول يحتوي على `pref`

### 2. **مشكلة في تسمية الملفات**
```log
"saved_name":"9_001447_2.png" // خطأ - يجب أن يكون مثل TE102_001447_82369962.png
```

**المطلوب:** النمط `TE102_001447_82369962` حيث:
- `TE102` = البادئة من جدول `document_types.pref` 
- `001447` = ID المجلد
- `82369962` = رقم الهوية من المقطع الثاني في اسم الملف الأصلي

### 3. **مشكلة في كشف التكرار**
النظام لا يكشف التكرار بناءً على رقم الهوية، مما يؤدي إلى حفظ ملفات مكررة.

---

## الحلول المُطبقة:

### ✅ إصلاح نمط تسمية الملفات
تم تعديل دالة `generateCorrectFileName()`:

```php
protected function generateCorrectFileName(UploadedFile $file, string $targetFolderId, string $originalFolderName): string
{
    $originalName = $file->getClientOriginalName();
    $extension = $file->getClientOriginalExtension();
    
    // استخراج رقم الهوية من المقطع الثاني في اسم الملف الأصلي
    $identityNumber = $this->extractIdentityNumberFromOriginalName($originalName);
    
    // الحصول على البادئة من جدول document_types باستخدام المقطع الثالث
    $documentTypePrefix = $this->getDocumentTypePrefixFromDatabase($originalName);
    
    // إذا لم نجد البادئة، نستخدم البادئة الافتراضية
    if (empty($documentTypePrefix)) {
        $documentTypePrefix = 'DOC';
    }
    
    // النمط: prefix_folderID_identityNumber.extension
    $newFileName = sprintf('%s_%s_%s.%s', 
        $documentTypePrefix, 
        $targetFolderId, 
        $identityNumber, 
        $extension
    );
    
    return $newFileName;
}
```

### ✅ إصلاح جلب البادئة من قاعدة البيانات
```php
protected function getDocumentTypePrefixFromDatabase(string $originalFileName): ?string
{
    // استخراج المقطع الثالث من اسم الملف (مثل _2 من H_82573265_2)
    $parts = explode('_', pathinfo($originalFileName, PATHINFO_FILENAME));
    
    if (count($parts) >= 3) {
        $documentTypeId = $parts[2]; // المقطع الثالث
        
        // البحث في جدول document_types باستخدام الـ id
        $documentType = DB::table('document_types')
            ->where('id', $documentTypeId)
            ->first();
        
        if ($documentType && isset($documentType->pref)) {
            return $documentType->pref;
        }
    }
    
    return null;
}
```

### ✅ إصلاح استخراج رقم الهوية
```php
protected function extractIdentityNumberFromOriginalName(string $originalName): string
{
    // تحليل الاسم لاستخراج رقم الهوية من المقطع الثاني
    $parts = explode('_', pathinfo($originalName, PATHINFO_FILENAME));
    
    if (count($parts) >= 2) {
        // المقطع الثاني هو رقم الهوية (مثل 82573265 من H_82573265_2)
        return $parts[1];
    }
    
    return (string) time(); // fallback
}
```

### ✅ إصلاح كشف التكرار بناءً على رقم الهوية
```php
protected function findExistingFileByIdentityInFolder(string $identityNumber, string $targetFolderId)
{
    // البحث في قاعدة البيانات باستخدام رقم الهوية
    $attachment = Attachment::where('folder_id', $targetFolderId)
        ->where(function($query) use ($identityNumber) {
            $query->whereRaw('stored_file_name LIKE ?', ['%_' . $identityNumber . '.%'])
                  ->orWhereRaw('original_file_name LIKE ?', ['%_' . $identityNumber . '_%']);
        })->first();

    if ($attachment) {
        return $attachment;
    }

    // البحث في نظام الملفات
    $storageBasePath = storage_path('app/public/uploads/' . $targetFolderId);
    if (is_dir($storageBasePath)) {
        $files = File::files($storageBasePath);
        foreach ($files as $file) {
            $fileName = $file->getFilename();
            
            if (strpos($fileName, '_' . $identityNumber . '.') !== false) {
                return (object) [
                    'id' => null,
                    'file_name' => $fileName,
                    'stored_file_name' => $fileName,
                    'folder_id' => $targetFolderId,
                    'storage_type' => 'file_system'
                ];
            }
        }
    }

    return null;
}
```

---

## مثال على سير العمل الصحيح:

### ملف مُستلم: `H_82573265_2.jpg`

1. **استخراج رقم الهوية:** `82573265` (المقطع الثاني)
2. **استخراج ID نوع الوثيقة:** `2` (المقطع الثالث) 
3. **جلب البادئة من قاعدة البيانات:**
   ```sql
   SELECT pref FROM document_types WHERE id = 2
   -- النتيجة: TE102
   ```
4. **التسمية النهائية:** `TE102_001447_82573265.jpg`

### كشف التكرار:
- البحث عن ملفات تحتوي على `_82573265.` في نفس المجلد
- إذا وُجد، يُسجل كملف مكرر ولا يتم حفظه في قاعدة البيانات

---

## الاختبار المطلوب:

1. **رفع ملف جديد:** `H_82573265_2.jpg`
   - **النتيجة المتوقعة:** `TE102_001447_82573265.jpg`

2. **رفع نفس الملف مرة أخرى:**
   - **النتيجة المتوقعة:** يُكتشف كملف مكرر ولا يُحفظ

3. **رفع ملف بنفس رقم الهوية لكن امتداد مختلف:** `D_82573265_3.png`
   - **النتيجة المتوقعة:** يُكتشف كملف مكرر ولا يُحفظ

---

## الملفات المُعدلة:

1. `app/Services/FolderDuplicateDetectionService.php`
   - ✅ `generateCorrectFileName()`
   - ✅ `getDocumentTypePrefixFromDatabase()`
   - ✅ `extractIdentityNumberFromOriginalName()`
   - ✅ `findExistingFileByIdentityInFolder()`
   - ✅ `checkFileForDuplicateInFolder()`

---

## حالة النظام الحالية:

- ✅ **تسمية الملفات:** مُصححة وتستخدم النمط المطلوب
- ✅ **جلب البادئة:** مُصحح ويستخدم جدول `document_types.pref`
- ✅ **كشف التكرار:** مُصحح ويعتمد على رقم الهوية
- ✅ **منع تخزين المكررات:** الملفات المكررة لا تُحفظ في قاعدة البيانات

النظام جاهز للاختبار النهائي!
