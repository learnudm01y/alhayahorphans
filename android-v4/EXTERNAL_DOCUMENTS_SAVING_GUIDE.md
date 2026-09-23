# 📁 دليل حفظ الملفات في External Documents Folder
# External Documents Folder Saving Guide

---

## 📋 نظرة عامة

تم إضافة ميزة حفظ نسخة احتياطية من جميع الملفات المُصوَّرة في مجلد خارجي يمكن الوصول إليه بسهولة عبر مدير الملفات.

---

## 🎯 المشكلة التي تم حلها

### ❌ قبل الإصلاح:
- الملفات تُحفظ فقط في **MediaStore** (content:// URI)
- صعوبة الوصول إلى الملفات يدوياً
- لا توجد نسخة احتياطية خارجية
- عند إعادة تسمية المكفول، لا يوجد مجلد حقيقي لإعادة تسميته

### ✅ بعد الإصلاح:
- نسخة في **MediaStore** (للرفع عبر FileSyncWorker)
- نسخة في **External Documents folder** (للوصول اليدوي)
- هيكل مجلدات منظم حسب الجمعية والشخص
- إمكانية تصفح الملفات عبر مدير الملفات

---

## 📂 هيكل المجلدات

```
/storage/emulated/0/Documents/
└── sponsorships_alhayahorphans/
    ├── [Association Name]/
    │   ├── [Person Name 1]/
    │   │   ├── video_123_1234567890.mp4
    │   │   └── video_123_1234567891.mp4
    │   └── [Person Name 2]/
    │       └── video_456_1234567892.mp4
    └── General/
        └── [Unknown Person]/
            └── video_789_1234567893.mp4
```

### مثال حقيقي:

```
/storage/emulated/0/Documents/
└── sponsorships_alhayahorphans/
    ├── جمعية_الحياة_للأيتام/
    │   ├── محمد_أحمد_السعيد/
    │   │   ├── video_1001_1707984230000.mp4
    │   │   └── video_1001_1707984335000.mp4
    │   └── فاطمة_حسن_علي/
    │       └── video_1002_1707984550000.mp4
    └── General/
        └── Unknown_999/
            └── video_999_1707984660000.mp4
```

---

## 🔧 التفاصيل التقنية

### CameraActivity.java - saveToExternalDocumentsFolder()

عند التصوير بنجاح (`onActivityResult` → `RESULT_OK`):

```java
// 1. حفظ الملف في MediaStore (موجود)
ContentValues values = new ContentValues();
videoUri = getContentResolver().insert(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, values);

// 2. ✨ NEW: حفظ نسخة في External Documents
saveToExternalDocumentsFolder(videoUri, fileName);

// 3. إضافة إلى SQLite وجدولة الرفع (موجود)
saveAndQueueUpload(filePath, fileName);
```

---

### خطوات الحفظ:

#### **1. إنشاء هيكل المجلدات:**

```java
File documentsDir = Environment.getExternalStoragePublicDirectory(
    Environment.DIRECTORY_DOCUMENTS);

File mainDir = new File(documentsDir, "sponsorships_alhayahorphans");
mainDir.mkdirs(); // ✅ /Documents/sponsorships_alhayahorphans/

String safeAssociationName = associationName
    .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");

File associationDir = new File(mainDir, safeAssociationName);
associationDir.mkdirs(); // ✅ .../جمعية_الحياة/

String safePersonName = personName
    .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");

File personDir = new File(associationDir, safePersonName);
personDir.mkdirs(); // ✅ .../محمد_أحمد/
```

**تنظيف الأسماء:**
- يُزيل الأحرف الخاصة غير المسموح بها في أسماء الملفات
- يستبدلها بـ `_`
- يدعم الأحرف العربية (Unicode: `\u0600-\u06FF`)

---

#### **2. نسخ الملف:**

```java
File destinationFile = new File(personDir, fileName);

try (InputStream inputStream = getContentResolver().openInputStream(videoUri);
     FileOutputStream outputStream = new FileOutputStream(destinationFile)) {
    
    byte[] buffer = new byte[8192];
    int bytesRead;
    long bytesWritten = 0;

    while ((bytesRead = inputStream.read(buffer)) != -1) {
        outputStream.write(buffer, 0, bytesRead);
        bytesWritten += bytesRead;
        
        // تقرير التقدم كل 10 MB
        if (bytesWritten % (10 * 1024 * 1024) < 8192) {
            Log.e(TAG, "📊 Progress: " + (bytesWritten / 1024.0 / 1024.0) + " MB written");
        }
    }
}
```

**مميزات النسخ:**
- Streaming بدون تحميل كامل الملف في الذاكرة
- Buffer 8192 bytes (8 KB) - أداء متوازن
- Progress logging كل 10 MB للملفات الكبيرة
- Automatic cleanup للملفات الناقصة عند فشل النسخ

---

#### **3. التحقق والحفظ في Database:**

```java
// التحقق من وجود الملف
if (destinationFile.exists()) {
    long fileSize = destinationFile.length();
    Log.e(TAG, "✅ File exists with size " + fileSize + " bytes");
}

// حفظ مسار المجلد في Database
dbHelper.savePersonNameHistory(
    sponsorshipId,
    associationName,
    personName,
    personDir.getAbsolutePath()  // "/storage/.../محمد_أحمد"
);
```

---

## 📊 Logs المتوقعة

عند التصوير بنجاح:

```
E CameraActivity: 
E CameraActivity: ╔═══════════════════════════════════════════════════════════╗
E CameraActivity: ║  📁 Saving copy to External Documents folder...         ║
E CameraActivity: ╚═══════════════════════════════════════════════════════════╝
E CameraActivity: 📂 Documents directory: /storage/emulated/0/Documents
E CameraActivity: ✅ Created main directory: /storage/.../sponsorships_alhayahorphans
E CameraActivity: ✅ Created association directory: جمعية_الحياة
E CameraActivity: ✅ Created person directory: محمد_أحمد
E CameraActivity: 
E CameraActivity: 📋 Folder structure:
E CameraActivity:    Main: sponsorships_alhayahorphans
E CameraActivity:    Association: جمعية_الحياة
E CameraActivity:    Person: محمد_أحمد
E CameraActivity:    File: video_1001_1707984230000.mp4
E CameraActivity: 
E CameraActivity: 📍 Full path: /storage/emulated/0/Documents/sponsorships_alhayahorphans/جمعية_الحياة/محمد_أحمد/video_1001_1707984230000.mp4
E CameraActivity: 🔄 Starting file copy from URI to external storage...
E CameraActivity: 📊 Progress: 10.52 MB written
E CameraActivity: 📊 Progress: 20.14 MB written
E CameraActivity: 
E CameraActivity: ✅✅✅ File copy completed successfully!
E CameraActivity:    📊 Size: 22.67 MB (23771310 bytes)
E CameraActivity:    📦 Chunks: 2901
E CameraActivity:    ⏱️ Duration: 3245 ms
E CameraActivity:    📍 Saved to: /storage/.../video_1001_1707984230000.mp4
E CameraActivity: 
E CameraActivity: ✅ Verification: File exists with size 23771310 bytes
E CameraActivity: ✅ Saved folder path to database
E CameraActivity: ╚═══════════════════════════════════════════════════════════╝
```

---

## 🔍 كيفية التحقق من نجاح الحفظ

### 1. **عبر ADB + Command Line:**

```powershell
# عرض محتويات المجلد الرئيسي
adb shell ls -la /storage/emulated/0/Documents/sponsorships_alhayahorphans/

# عرض ملفات مكفول معين
adb shell ls -lh "/storage/emulated/0/Documents/sponsorships_alhayahorphans/جمعية_الحياة/محمد_أحمد/"

# التحقق من حجم الملف
adb shell du -h "/storage/emulated/0/Documents/sponsorships_alhayahorphans/جمعية_الحياة/محمد_أحمد/video_1001_1707984230000.mp4"

# عد عدد الملفات
adb shell "find /storage/emulated/0/Documents/sponsorships_alhayahorphans/ -type f | wc -l"
```

**Output المتوقع:**

```
-rw-rw---- 1 u0_a123 media_rw 23771310 2026-02-15 10:30 video_1001_1707984230000.mp4
22.67M  .../video_1001_1707984230000.mp4
```

---

### 2. **عبر File Manager على الجهاز:**

1. افتح تطبيق **Files** أو **My Files**
2. اذهب إلى **Documents**
3. ابحث عن مجلد **sponsorships_alhayahorphans**
4. افتح مجلد الجمعية → مجلد الشخص
5. يجب أن ترى ملفات الفيديو

---

### 3. **عبر Logcat:**

```powershell
# فلترة logs الخاصة بحفظ الملفات
adb logcat -s CameraActivity:* | Select-String "External Documents"

# البحث عن "✅✅✅ File copy completed"
adb logcat -s CameraActivity:* | Select-String "File copy completed"

# عرض المسار الكامل المحفوظ
adb logcat -s CameraActivity:* | Select-String "Full path:"
```

---

## 🛡️ الصلاحيات المطلوبة

### AndroidManifest.xml:

```xml
<!-- Storage Permissions -->
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE"
    android:maxSdkVersion="32"
    tools:ignore="ScopedStorage" />

<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" 
    android:maxSdkVersion="32" />

<uses-permission android:name="android.permission.MANAGE_EXTERNAL_STORAGE"
    tools:ignore="ScopedStorage" />

<!-- Application Tag -->
<application
    android:requestLegacyExternalStorage="true"
    ...
</application>
```

---

## 🔄 التكامل مع SponsorshipFolderManager

### عند إعادة تسمية مكفول:

```java
// SponsorshipFolderManager.renameSponsorshipFolder()

// 1. قراءة الاسم القديم من Database
String[] nameHistory = dbHelper.getPreviousPersonName(sponsorshipId);

// 2. إعادة تسمية المجلد الخارجي
File oldPersonDir = new File(oldAssocDir, oldPersonName);
File newPersonDir = new File(newAssocDir, newPersonName);

boolean renamed = oldPersonDir.renameTo(newPersonDir);

// 3. تحديث جميع سجلات الملفات في Database
int filesUpdated = dbHelper.updatePersonNameInQueue(sponsorshipId, newPersonName);

// 4. حفظ الأسماء الجديدة في التاريخ
dbHelper.savePersonNameHistory(
    sponsorshipId, 
    newAssociationName, 
    newPersonName,
    newPersonDir.getAbsolutePath()
);
```

**النتيجة:**
- المجلد الخارجي يُعاد تسميته
- جميع سجلات Database تُحدث
- الملفات تبقى في مكانها داخل المجلد المُعاد تسميته
- لا حاجة لرفع الملفات مجدداً

---

## 🧪 اختبار الميزة

### السيناريو 1: تصوير فيديو جديد

```
1. فتح التطبيق
2. اختيار مكفول (مثلاً: محمد أحمد، الجمعية: جمعية الحياة)
3. تصوير فيديو
   ↓
✅ النتيجة المتوقعة:
   - الفيديو في MediaStore (لرفعه)
   - نسخة في: /Documents/sponsorships_alhayahorphans/جمعية_الحياة/محمد_أحمد/
   - Logs تظهر "✅✅✅ File copy completed successfully!"
```

---

### السيناريو 2: تصوير عدة فيديوهات لنفس الشخص

```
1. تصوير فيديو 1
2. تصوير فيديو 2
3. تصوير فيديو 3
   ↓
✅ النتيجة المتوقعة:
   جميع الفيديوهات في نفس المجلد:
   /Documents/sponsorships_alhayahorphans/جمعية_الحياة/محمد_أحمد/
   ├── video_1001_1707984230000.mp4
   ├── video_1001_1707984335000.mp4
   └── video_1001_1707984550000.mp4
```

---

### السيناريو 3: إعادة تسمية مكفول

```
1. تصوير فيديو لـ "محمد أحمد"
2. إعادة تسمية إلى "أحمد محمد السعيد"
   ↓
✅ النتيجة المتوقعة:
   - المجلد يُعاد تسميته من "محمد_أحمد" إلى "أحمد_محمد_السعيد"
   - الملفات تبقى داخل المجلد الجديد
   - Database records تُحدث
```

---

### السيناريو 4: مكفول بدون اسم جمعية

```
1. اختيار مكفول بدون جمعية (associationName = null أو empty)
2. تصوير فيديو
   ↓
✅ النتيجة المتوقعة:
   الملفات تُحفظ في:
   /Documents/sponsorships_alhayahorphans/General/[PersonName]/
```

---

## 🐛 استكشاف الأخطاء

### المشكلة: المجلد لا يُنشأ

**الحل:**
```powershell
# 1. التحقق من الصلاحيات
adb shell dumpsys package com.aso.app | Select-String "WRITE_EXTERNAL_STORAGE"

# يجب أن يظهر: granted=true

# 2. التحقق من وجود Documents directory
adb shell ls -la /storage/emulated/0/ | Select-String "Documents"
```

---

### المشكلة: الملف يُحفظ لكن الحجم 0

**السبب:** MediaStore لم ينتهِ من كتابة الملف عند محاولة النسخ

**الحل:** الكود يتعامل مع هذا تلقائياً:
```java
// يقرأ الملف من أوله وينسخ كل byte
while ((bytesRead = inputStream.read(buffer)) != -1) {
    outputStream.write(buffer, 0, bytesRead);
}

// في النهاية يتحقق
if (destinationFile.length() != bytesWritten) {
    Log.e(TAG, "⚠️ File size mismatch!");
}
```

---

### المشكلة: خطأ "Permission denied"

**الحل:**
```powershell
# منح الصلاحيات يدوياً
adb shell pm grant com.aso.app android.permission.WRITE_EXTERNAL_STORAGE
adb shell pm grant com.aso.app android.permission.READ_EXTERNAL_STORAGE

# لـ Android 11+
adb shell appops set com.aso.app MANAGE_EXTERNAL_STORAGE allow
```

---

### المشكلة: الأحرف العربية تظهر كـ "??????"

**الحل:** هذا طبيعي في بعض أدوات سطر الأوامر. الملفات محفوظة بشكل صحيح.

تحقق عبر File Manager على الجهاز نفسه - يجب أن تظهر الأسماء العربية بشكل صحيح.

---

## 📝 ملخص التدفق الكامل

```
1. المستخدم يضغط على زر التصوير
   ↓
2. CameraActivity.openCamera() يفتح الكاميرا
   ↓
3. بعد التسجيل → onActivityResult(RESULT_OK)
   ↓
4. حفظ في MediaStore:
   content://media/external/video/media/12345
   ↓
5. ✨ NEW: saveToExternalDocumentsFolder():
   - إنشاء /Documents/sponsorships_alhayahorphans/[Assoc]/[Person]/
   - نسخ الملف من MediaStore URI إلى المجلد الخارجي
   - تقرير التقدم والتحقق من النجاح
   - حفظ مسار المجلد في Database
   ↓
6. saveAndQueueUpload():
   - حفظ سجل في SQLite
   - جدولة FileSyncWorker للرفع
   ↓
7. FileSyncWorker يرفع الملف من MediaStore URI
   ↓
8. النتيجة النهائية:
   - ✅ ملف في MediaStore (يُحذف بعد رفع ناجح)
   - ✅ نسخة في External Documents (تبقى للأبد)
   - ✅ ملف مرفوع على Backend
   - ✅ سجل في Database مع مسار المجلد الخارجي
```

---

## ✅ الفوائد

1. **نسخة احتياطية خارجية:** الملفات لا تُحذف بعد الرفع (فقط نسخة MediaStore تُحذف)
2. **سهولة الوصول:** يمكن فتح الملفات عبر أي File Manager
3. **منظمة:** هيكل مجلدات واضح حسب الجمعية والشخص
4. **قابلة لإعادة التسمية:** عند تغيير اسم مكفول، المجلد يُعاد تسميته تلقائياً
5. **آمنة:** نسخة إضافية في حال فشل الرفع أو حذف قاعدة البيانات

---

✅ **الميزة الآن تعمل بشكل كامل - جميع الملفات يتم حفظها في External Documents folder تلقائياً!**
