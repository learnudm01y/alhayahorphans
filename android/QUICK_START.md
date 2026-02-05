# 🚀 تعليمات التثبيت والاختبار الفوري

## 📦 النسخة الجديدة
**الملف:** `android/app/build/outputs/apk/debug/app-debug.apk`  
**التاريخ:** 2026-02-05 03:44 AM  
**الحجم:** 28.1 MB

---

## ⚡ خطوات سريعة (5 دقائق)

### 1️⃣ ثبّت APK الجديد
```bash
# إلغاء التثبيت القديم (اختياري - للتأكد من نظافة التثبيت)
adb uninstall org.alhayah.sponsorships

# تثبيت النسخة الجديدة
adb install -r "android/app/build/outputs/apk/debug/app-debug.apk"
```

### 2️⃣ افتح Chrome DevTools
1. افتح Chrome على الكمبيوتر
2. اذهب إلى: `chrome://inspect/#devices`
3. انتظر ظهور الجهاز (5-10 ثواني)
4. افتح التطبيق على الهاتف
5. انقر **Inspect** في Chrome

### 3️⃣ ارفع صورة واحدة للاختبار
- اختر أي رعاية (مثلاً رقم 908)
- التقط صورة
- **راقب Browser Console وLOGCAT معاً**

---

## 📊 السجلات المتوقعة

### ✅ حالة النجاح

#### في Browser Console:
```
[IndexedDBReader] Starting query for ID: 908
[IndexedDBReader] Database found: object
[IndexedDBReader] Has sponsorships? true
[IndexedDBReader] Has associations? true
[IndexedDBReader] Sponsorship result: FOUND
[IndexedDBReader] Sponsorship data: {"id":908,"person_name":"محمد أحمد",...}
[IndexedDBReader] Association ID: 15
[IndexedDBReader] Association result: FOUND
[IndexedDBReader] SUCCESS! Result: {"associationName":"جمعية الخير","personName":"محمد أحمد"}
```

#### في Logcat:
```
IndexedDBReader: ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IndexedDBReader: 📥 getSponsorshipData() CALLED for ID: 908
IndexedDBReader: ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IndexedDBReader: ✅ Bridge and WebView available
IndexedDBReader: 🔍 Executing JavaScript query...
IndexedDBReader: 📝 JavaScript code length: 1845 chars
IndexedDBReader: 📨 JavaScript raw response: "{\"associationName\":\"جمعية الخير\",\"personName\":\"محمد أحمد\"}"
IndexedDBReader: 🔄 Parsing JSON: {"associationName":"جمعية الخير","personName":"محمد أحمد"}
IndexedDBReader: ✅✅✅ SUCCESS! Got data from IndexedDB:
IndexedDBReader:    ✅ Association: جمعية الخير
IndexedDBReader:    ✅ Person: محمد أحمد
IndexedDBReader: ⏳ Wait completed: RESPONSE RECEIVED
IndexedDBReader: ✅ Successfully fetched sponsorship data
IndexedDBReader: ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

UploadServicePlugin:    ✅✅✅ SUCCESS! Fetched from IndexedDB directly:
UploadServicePlugin:    ✅ Association: جمعية الخير
UploadServicePlugin:    ✅ Person: محمد أحمد
UploadServicePlugin: ✅✅✅ Using REAL names from mapping database!
UploadServicePlugin: ✅ Folder: Pictures/sponsorships_alhayahorphans/جمعية الخير/محمد أحمد/
```

**✅ المجلد سيكون بالاسم الصحيح!**

---

### ❌ حالة الفشل 1: متغير db غير موجود

#### في Browser Console:
```
[IndexedDBReader] Starting query for ID: 908
[IndexedDBReader] ERROR: db is undefined!
[IndexedDBReader] window keys: $db,appDatabase,myDB
```

#### في Logcat:
```
IndexedDBReader: ❌❌❌ ERROR from JavaScript:
IndexedDBReader:    ❌ Error: db_undefined
IndexedDBReader:    📊 Available: $db,appDatabase,myDB
IndexedDBReader:    💡 CHECK BROWSER CONSOLE (chrome://inspect)
```

**🔧 الحل:**
المتغير الصحيح ليس `db`، أرسل لقطة شاشة من Console وسنعدل الكود.

---

### ❌ حالة الفشل 2: جدول sponsorships غير موجود

#### في Browser Console:
```
[IndexedDBReader] Database found: object
[IndexedDBReader] Has sponsorships? false
[IndexedDBReader] ERROR: db.sponsorships is undefined!
[IndexedDBReader] Available tables: sponsor_data,associations,files
```

#### في Logcat:
```
IndexedDBReader: ❌❌❌ ERROR from JavaScript:
IndexedDBReader:    ❌ Error: no_sponsorships_table
IndexedDBReader:    📊 Tables: sponsor_data,associations,files
```

**🔧 الحل:**
الجدول الصحيح هو `sponsor_data` وليس `sponsorships`، سنعدل الكود.

---

### ❌ حالة الفشل 3: Sponsorship غير موجود

#### في Browser Console:
```
[IndexedDBReader] Sponsorship result: NOT_FOUND
[IndexedDBReader] Sponsorship 908 not found in IndexedDB
```

**🔧 الحل:**
الرعاية رقم 908 غير موجودة في قاعدة البيانات - تأكد من تنزيل البيانات من الخادم أولاً.

---

## 🛠️ الأمر السريع للمراقبة

افتح نافذتين terminal:

### Terminal 1 - Logcat:
```powershell
adb logcat -s IndexedDBReader UploadServicePlugin | Select-String -Pattern "(Association|Person|ERROR|SUCCESS)"
```

### Terminal 2 - Chrome DevTools:
افتح `chrome://inspect` → انقر Inspect → Console

---

## 📤 ما يجب إرساله إذا فشل

### 1. لقطة شاشة كاملة من Browser Console
- يجب أن تظهر سجلات `[IndexedDBReader]`
- إذا لم تظهر، هذه مشكلة منفصلة

### 2. نص Logcat الكامل:
```bash
adb logcat -s IndexedDBReader UploadServicePlugin > logs.txt
# أرسل ملف logs.txt
```

### 3. اختبار يدوي في Browser Console:
```javascript
// نسخ ولصق في Console
console.log('DB exists?', typeof db);
console.log('DB type:', db);
console.log('Tables:', Object.keys(db));

// اختبار استعلام
const sp = await db.sponsorships.get(908);
console.log('Sponsorship 908:', sp);

// إذا فشل، جرب أسماء بديلة:
const sp2 = await db.sponsor_data?.get(908);
console.log('From sponsor_data:', sp2);
```

---

## ⏱️ توقيت التثبيت

- **التثبيت:** 10-20 ثانية
- **فتح Chrome Inspect:** 5-10 ثواني
- **اختبار رفع صورة:** 5 ثواني
- **المجموع:** ~1 دقيقة

---

## ✅ علامات النجاح

1. ✅ في Console: `[IndexedDBReader] SUCCESS! Result:`
2. ✅ في Logcat: `✅✅✅ SUCCESS! Fetched from IndexedDB directly`
3. ✅ في Logcat: `✅ Folder: Pictures/.../[اسم_حقيقي]/[اسم_حقيقي]/`
4. ✅ في معرض الصور: مجلد بالاسم الصحيح (ليس General/unknown)

---

## 🎯 الهدف النهائي

الملفات تُحفظ في:
```
Pictures/sponsorships_alhayahorphans/[اسم_الجمعية]/[اسم_الشخص]/photo_908_xxx.jpg
```

وليس:
```
Pictures/sponsorships_alhayahorphans/General/unknown/photo_908_xxx.jpg
```

---

**🚀 جرّب الآن وأرسل النتيجة!**
