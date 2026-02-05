# 🔍 دليل تشخيص مشكلة أسماء المجلدات (General/unknown)

## ❌ المشكلة
الملفات تُحفظ في مجلد `General/unknown/` بدلاً من الأسماء الحقيقية للجمعية والشخص.

## 🎯 الحل المُطبق
تم تطوير نظام تلقائي يقرأ البيانات من IndexedDB مباشرة عبر WebView بدون الحاجة لأي تعديلات على JavaScript!

---

## 📋 خطوات التشخيص

### الخطوة 1: تثبيت النسخة الجديدة
```bash
# النسخة الجديدة في:
android/app/build/outputs/apk/debug/app-debug.apk

# التاريخ: 2026-02-05 03:34 AM
# الحجم: 28.1 MB
```

### الخطوة 2: تفعيل سجلات المتصفح (Browser Console)
**هذه الخطوة ضرورية جداً!**

1. افتح التطبيق
2. افتح Chrome على الكمبيوتر
3. اذهب إلى: `chrome://inspect/#devices`
4. انقر على **Inspect** بجانب التطبيق
5. افتح تبويب **Console**

### الخطوة 3: قم برفع صورة واحدة فقط
- اختر أي رعاية (Sponsorship) 
- التقط صورة أو ارفع صورة
- راقب السجلات!

---

## 📊 السجلات المتوقعة

### ✅ السيناريو الناجح (الملفات تُحفظ في المجلد الصحيح)

#### في Logcat (Android):
```
📥 Requesting data for sponsorshipId: 908
🔍 Executing JavaScript query...
📨 JavaScript raw response: "{\"associationName\":\"جمعية الخير\",\"personName\":\"محمد أحمد\"}"
✅ Got data from IndexedDB:
   Association: جمعية الخير
   Person: محمد أحمد
✅ Successfully fetched sponsorship data

✅✅✅ SUCCESS! Fetched from IndexedDB directly:
✅ Association: جمعية الخير
✅ Person: محمد أحمد
✅ Saved to mapping DB for future use

✅✅✅ Using REAL names from mapping database!
✅ Folder: Pictures/sponsorships_alhayahorphans/جمعية الخير/محمد أحمد/
```

#### في Browser Console:
```
[IndexedDBReader] Starting query for ID: 908
[IndexedDBReader] Database found: object
[IndexedDBReader] Has sponsorships? true
[IndexedDBReader] Has associations? true
[IndexedDBReader] Sponsorship result: FOUND
[IndexedDBReader] Association ID: 15
[IndexedDBReader] Association result: FOUND
[IndexedDBReader] SUCCESS! Result: {"associationName":"جمعية الخير","personName":"محمد أحمد"}
```

---

### ❌ السيناريو الفاشل (الملفات ما زالت تُحفظ في General/unknown)

#### حالة 1: متغير `db` غير موجود

**في Browser Console:**
```
[IndexedDBReader] Starting query for ID: 908
[IndexedDBReader] ERROR: db is undefined!
[IndexedDBReader] window keys: $db,appDatabase,myDB
```

**الحل:**
- المتغير الصحيح ليس `db`، بل شيء آخر مثل `$db` أو `appDatabase`
- نحتاج لتعديل `IndexedDBReader.java` في السطر 69:
  ```java
  const database = window.db || window.$db || db;
  ```
  غيّره إلى:
  ```java
  const database = window.appDatabase || window.$db || db;  // استخدم الاسم الصحيح
  ```

---

#### حالة 2: جداول IndexedDB أسماؤها مختلفة

**في Browser Console:**
```
[IndexedDBReader] Database found: object
[IndexedDBReader] Has sponsorships? false
[IndexedDBReader] ERROR: db.sponsorships is undefined!
[IndexedDBReader] Available tables: sponsor_data,association_data,uploads
```

**الحل:**
- الجداول ليست `sponsorships` و `associations`
- بل `sponsor_data` و `association_data` (مثلاً)
- نحتاج لتعديل `IndexedDBReader.java` في الأسطر 79-90:
  ```java
  const sp = await database.sponsor_data.get(sponsorshipId);      // بدلاً من sponsorships
  const assoc = await database.association_data.get(sp.association_id);  // بدلاً من associations
  ```

---

#### حالة 3: أسماء الحقول مختلفة

**في Browser Console:**
```
[IndexedDBReader] Sponsorship data: {"id":908,"assoc_id":15,"full_name":"محمد أحمد"}
[IndexedDBReader] Association ID: undefined
```

**التشخيص:**
- الحقل `association_id` غير موجود
- موجود `assoc_id` بدلاً منه

**الحل:**
نحتاج لتعديل `IndexedDBReader.java`:
```java
// السطر 95 - جلب association_id
console.log('[IndexedDBReader] Association ID:', sp.assoc_id);  // بدلاً من sp.association_id

// السطر 90 - استعلام الجمعية
const assoc = await database.associations.get(sp.assoc_id);  // بدلاً من sp.association_id

// الأسطر 101-102 - استخراج البيانات
const result = {
  associationName: assoc?.title || assoc?.name || 'General',           // قد يكون title بدلاً من name
  personName: sp.full_name || sp.person_name || sp.name || 'unknown'  // قد يكون full_name
};
```

---

#### حالة 4: البيانات غير موجودة في IndexedDB

**في Browser Console:**
```
[IndexedDBReader] Sponsorship result: NOT_FOUND
[IndexedDBReader] Sponsorship 908 not found in IndexedDB
```

**الحل:**
- الرعاية (Sponsorship) رقم 908 غير موجودة في IndexedDB
- تأكد من أن التطبيق قام بتنزيل البيانات من الخادم
- تأكد من أن التزامن (Sync) يعمل بشكل صحيح

---

## 🔧 كيفية فحص IndexedDB يدوياً

### في Browser Console (بعد فتح Inspect):

```javascript
// 1. تحقق من وجود db
console.log('DB exists?', typeof db !== 'undefined');
console.log('DB type:', typeof db);

// 2. اعرض أسماء الجداول
console.log('Tables:', Object.keys(db));

// 3. جرّب قراءة رعاية
const sp = await db.sponsorships.get(908);  // غيّر 908 إلى ID حقيقي
console.log('Sponsorship:', sp);

// 4. اعرض جميع الحقول
console.log('Fields:', Object.keys(sp));

// 5. جرّب قراءة الجمعية
const assoc = await db.associations.get(sp.association_id);
console.log('Association:', assoc);
console.log('Association fields:', Object.keys(assoc));
```

---

## 📤 ما يجب إرساله للتشخيص

إذا استمرت المشكلة، أرسل:

### 1. سجلات Logcat الكاملة:
```bash
adb logcat -s IndexedDBReader UploadServicePlugin
```

### 2. لقطة شاشة من Browser Console
- يجب أن تظهر سجلات `[IndexedDBReader]`

### 3. نتيجة الفحص اليدوي:
```javascript
// في Browser Console
const sp = await db.sponsorships.get(908);
console.log('Full sponsorship object:', JSON.stringify(sp, null, 2));

const assoc = await db.associations.get(sp.association_id);
console.log('Full association object:', JSON.stringify(assoc, null, 2));
```

---

## 🎯 الخلاصة

النظام الجديد **يجب أن يعمل تلقائياً** بدون أي تعديلات JavaScript!

إذا لم يعمل:
1. **افتح Browser Console** (chrome://inspect)
2. **ارفع صورة واحدة**
3. **ابحث عن `[IndexedDBReader]`** في Console
4. **أرسل السجلات** لتشخيص المشكلة الدقيقة

المشكلة غالباً ستكون واحدة من:
- ✅ اسم المتغير (`db` vs `$db` vs `appDatabase`)
- ✅ أسماء الجداول (`sponsorships` vs `sponsor_data`)
- ✅ أسماء الحقول (`association_id` vs `assoc_id`)
- ✅ البيانات غير موجودة في IndexedDB

**كل هذه المشاكل قابلة للإصلاح بتعديل بسيط في IndexedDBReader.java!**

---

## 📁 الملفات المعدلة

- `IndexedDBReader.java` - تم إضافة سجلات تفصيلية
- `UploadServicePlugin.java` - تم تحسين رسائل الأخطاء
- **APK الجديد:** `app-debug.apk` (2026-02-05 03:34 AM)
