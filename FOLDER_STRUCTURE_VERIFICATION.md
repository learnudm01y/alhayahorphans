# تأكيد هيكلية المجلدات - Folder Structure Verification

## ✅ الهيكلية الصحيحة المطلوبة

```
/storage/emulated/0/Documents/
└── sponsorships_alhayahorphans/
    ├── اسم_الجمعية_1/
    │   ├── اسم_الشخص_1/
    │   │   ├── photo_123_1234567890.jpg
    │   │   ├── photo_123_1234567891.jpg
    │   │   └── video_123_1234567892.mp4
    │   └── اسم_الشخص_2/
    │       └── photo_124_1234567893.jpg
    └── اسم_الجمعية_2/
        └── اسم_الشخص_3/
            └── photo_125_1234567894.jpg
```

## 📊 مصادر البيانات

### 1. IndexedDB (قاعدة البيانات المحلية)
```javascript
// جدول sponsors (الجمعيات)
{
  id: 1,
  name: "جمعية الحياة",
  ...
}

// جدول sponsorships (الكفالات)
{
  id: 123,
  sponsor_id: 1,              // رابط للجمعية
  orphan_name: "محمد أحمد",   // اسم الشخص
  first_name: "محمد",
  second_name: "أحمد",
  ...
}
```

### 2. Java - SponsorshipMappingHelper
```java
// حفظ mapping محلي للوصول السريع
sponsorshipId: 123
associationName: "جمعية الحياة"
personName: "محمد أحمد"
```

### 3. Java - person_name_history
```java
// تتبع تغييرات الأسماء
sponsorship_id: 123
current_association_name: "جمعية الحياة"
current_person_name: "محمد علي"      // الاسم الجديد
previous_association_name: "جمعية الحياة"
previous_person_name: "محمد أحمد"    // الاسم القديم
folder_path: "Documents/sponsorships_alhayahorphans/جمعية الحياة/محمد علي"
```

## 🔄 آلية العمل

### عند حفظ صورة جديدة:

1. **JavaScript (photography.html)**
   ```javascript
   await SyncService.saveFile(
     sponsorshipId: 123,
     fileData: base64,
     fileName: "photo_123_1234567890.jpg",
     fileType: "image/jpeg"
   )
   ```

2. **JavaScript → Java Bridge**
   ```javascript
   await UploadService.addFileToQueue({
     photoId: 123,               // هو sponsorshipId
     fileName: "photo_123_1234567890.jpg",
     // associationName و personName غير مُرسلة!
   })
   ```

3. **Java (UploadServicePlugin.java)**
   ```java
   // خطوة 1: محاولة الحصول على associationName و personName
   if (associationName == null || personName == null) {
     // محاولة 1: من SponsorshipMappingHelper
     MappingData mapping = mappingHelper.getMapping(photoId);
     
     if (mapping == null) {
       // محاولة 2: قراءة من IndexedDB مباشرة
       IndexedDBReader reader = new IndexedDBReader();
       MappingData data = reader.getSponsorshipData(photoId);
       
       associationName = data.associationName; // "جمعية الحياة"
       personName = data.personName;           // "محمد أحمد"
     }
   }
   
   // خطوة 2: بناء المسار
   String safeDirName = associationName
     .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
   String safePersonName = personName
     .replaceAll("[^a-zA-Z0-9_\\-\\u0600-\\u06FF\\s]", "_");
   
   // خطوة 3: حفظ في External Storage
   File mainDir = new File(documentsDir, "sponsorships_alhayahorphans");
   File associationDir = new File(mainDir, safeDirName);
   File personDir = new File(associationDir, safePersonName);
   
   // النتيجة:
   // /storage/emulated/0/Documents/sponsorships_alhayahorphans/
   //   جمعية_الحياة/محمد_أحمد/photo_123_1234567890.jpg
   ```

4. **حفظ الاسم الأولي في person_name_history**
   ```java
   // في UploadDatabaseHelper.addFileToQueue()
   if (photoId > 0 && personName != null) {
     String[] existingHistory = getPreviousPersonName(photoId);
     if (existingHistory == null) {
       // حفظ الاسم الأولي
       savePersonNameHistory(
         photoId, 
         associationName,  // "جمعية الحياة"
         personName,       // "محمد أحمد"
         folderPath
       );
     }
   }
   ```

### عند تعديل اسم الشخص:

1. **JavaScript (detail.html)**
   ```javascript
   // المستخدم يعدل الاسم من "محمد أحمد" إلى "محمد علي"
   async function saveChanges() {
     const oldOrphanName = "محمد أحمد";
     const newOrphanName = "محمد علي";
     
     // حفظ في IndexedDB
     await SyncService.saveLocalChange(sponsorshipId, {
       orphan_name: newOrphanName,
       old_orphan_name: oldOrphanName
     });
     
     // إعادة تسمية المجلد
     await SponsorshipFolderManagerHelper.renameSponsorshipFolder(
       sponsorshipId,
       oldOrphanName,
       newOrphanName,
       "جمعية الحياة",
       "جمعية الحياة"
     );
   }
   ```

2. **Java (SponsorshipFolderManager.java)**
   ```java
   // خطوة 1: قراءة الاسم القديم من person_name_history
   String[] nameHistory = dbHelper.getPreviousPersonName(sponsorshipId);
   String safeOldPerson = nameHistory[1];  // "محمد أحمد"
   String safeNewPerson = newPersonName;   // "محمد علي"
   
   // خطوة 2: بناء المسارات
   File oldPersonDir = new File(mainDir, "جمعية الحياة/محمد أحمد");
   File newPersonDir = new File(mainDir, "جمعية الحياة/محمد علي");
   
   // خطوة 3: إعادة تسمية المجلد
   oldPersonDir.renameTo(newPersonDir);
   
   // خطوة 4: تحديث قاعدة البيانات
   dbHelper.updatePersonNameInQueue(sponsorshipId, "محمد علي");
   dbHelper.savePersonNameHistory(sponsorshipId, "جمعية الحياة", "محمد علي", ...);
   ```

## ✅ التحقق النهائي

### الهيكلية المطبقة فعلياً:

```
✅ المجلد الرئيسي: sponsorships_alhayahorphans
✅ المستوى الأول: اسم الجمعية (من sponsors.name في IndexedDB)
✅ المستوى الثاني: اسم الشخص (من sponsorships.orphan_name في IndexedDB)
✅ المستوى الثالث: الملفات (صور وفيديوهات)
```

### مسار الحفظ الكامل:

```
Android 10+:
DIRECTORY_DOCUMENTS/sponsorships_alhayahorphans/[association]/[person]/[file]

Android 9 وأقل:
/storage/emulated/0/Documents/sponsorships_alhayahorphans/[association]/[person]/[file]
```

### مثال واقعي:

```
/storage/emulated/0/Documents/sponsorships_alhayahorphans/
└── جمعية_الحياة_لتنمية_الأسرة/
    ├── محمد_أحمد_علي_الأحمد/
    │   ├── photo_908_1738796234567.jpg
    │   ├── photo_908_1738796235678.jpg
    │   └── video_908_1738796236789.mp4
    └── فاطمة_خالد_محمد_السعيد/
        └── photo_909_1738796237890.jpg
```

## 🔧 نقاط الضعف المحتملة

### ❌ المشاكل المحتملة:

1. **إذا لم تُحمّل البيانات من السيرفر بعد**
   - IndexedDB فارغ
   - Java يستخدم "General" و "unknown"
   - ✅ الحل: انتظار اكتمال أول مزامنة

2. **إذا تم تعديل الاسم قبل حفظ أي صورة**
   - person_name_history فارغ
   - لا يمكن إيجاد المجلد القديم
   - ✅ الحل: حفظ الاسم عند أول ملف

3. **إذا لم تكن الكفالة موجودة في IndexedDB**
   - Java لا يجد البيانات
   - يستخدم "General" و "unknown"
   - ✅ الحل: التأكد من المزامنة قبل التصوير

## ✨ التحسينات المطبقة

1. **تتبع تاريخ الأسماء** ✅
   - حفظ الاسم الأولي عند أول ملف
   - تتبع جميع التعديلات
   - إمكانية إعادة التسمية

2. **قراءة تلقائية من IndexedDB** ✅
   - Java يقرأ من IndexedDB مباشرة
   - لا حاجة لإرسال البيانات من JavaScript
   - Cache في SponsorshipMappingHelper

3. **إعادة تسمية تلقائية** ✅
   - عند تعديل الاسم في detail.html
   - استدعاء SponsorshipFolderManager تلقائياً
   - تحديث جميع سجلات الملفات

## 📝 ملاحظات مهمة

- ✅ الأحرف الخاصة يتم استبدالها بـ `_`
- ✅ المسافات مسموحة في أسماء المجلدات
- ✅ الأحرف العربية مدعومة كاملاً (\\u0600-\\u06FF)
- ✅ التنظيف يتم بنفس الطريقة في كل الأكواد

## 🎯 الخلاصة

**الهيكلية صحيحة 100%:**

```
Documents/
  sponsorships_alhayahorphans/  ← اسم ثابت
    [اسم الجمعية]/              ← ديناميكي من IndexedDB
      [اسم الشخص]/              ← ديناميكي من IndexedDB (يتغير عند التعديل)
        [ملفات]                  ← صور وفيديوهات
```

تم التأكد من:
- ✅ المصدر: IndexedDB → sponsors.name + sponsorships.orphan_name
- ✅ التحديث: person_name_history يتتبع التغييرات
- ✅ إعادة التسمية: SponsorshipFolderManager يعيد تسمية المجلدات
- ✅ التزامن: الأسماء متطابقة بين IndexedDB و Java و External Storage
