# 🔍 دليل تشغيل نظام Logging للتطبيق

## 📋 الملفات المتوفرة

### 1️⃣ **START_LOGGING.ps1** (موصى به)
- PowerShell script
- يعرض اللوجات ملونة على الشاشة
- يحفظها تلقائياً في ملف مع timestamp

### 2️⃣ **START_LOGGING.bat**
- CMD/Batch script
- يحفظ اللوجات مباشرة في ملف
- للمستخدمين الذين يفضلون CMD

### 3️⃣ **START_LOGGING.txt**
- دليل الأوامر اليدوية
- شرح تفصيلي لكل أمر

---

## 🚀 طريقة الاستخدام السريعة

### الخطوات:

#### 1️⃣ **تجهيز الهاتف**
```
✅ وصل الهاتف بكابل USB
✅ فعّل USB Debugging في Developer Options
✅ اقبل رسالة التصريح على الهاتف
```

#### 2️⃣ **تثبيت التطبيق**
```bash
cd "I:\unit test\alhayahorphans\ASO - Copy\android"
adb install -r app\build\outputs\apk\debug\app-debug.apk
```

#### 3️⃣ **بدء Logging**

**PowerShell (موصى به):**
```powershell
cd "I:\unit test\alhayahorphans\ASO - Copy\android"
.\START_LOGGING.ps1
```

**أو CMD:**
```cmd
cd "I:\unit test\alhayahorphans\ASO - Copy\android"
START_LOGGING.bat
```

#### 4️⃣ **اختبار التطبيق**
```
1. افتح التطبيق على الهاتف
2. اختر مكفول
3. اضغط زر التصوير
4. التقط صورة
5. راقب اللوجات على الشاشة
```

---

## 📊 ما الذي سيظهر في اللوجات؟

### ✅ عند فتح التطبيق:
```
🔥🔥🔥 APPLICATION STARTING - AutoUploadApplication
✅ MainActivity.onCreate() STARTED
✅ UploadServicePlugin.load() - PLUGIN LOADED
✅ JavaScript can now call: UploadService.addFileToQueue()
```

### ✅ عند التقاط صورة:
```javascript
// من JavaScript (photography.html):
🔵 [1/6] capturePhoto() بدأت
✅ [2/6] المكفول المحدد: 123
✅ [3/6] Capacitor Camera متاح
📸 [4/6] استدعاء Camera.getPhoto...
✅ [5/6] تم التقاط الصورة
🚀 [6/6] استدعاء SyncService.saveFile

// من sync-service.js:
🔥🔥🔥 SyncService.saveFile() CALLED
🔍 [Step 1/5] Checking if Android Capacitor is available...
✅ [Step 2/5] UploadService Plugin is AVAILABLE!
📝 [Step 3/5] Preparing upload data...
🚀 [Step 4/5] Calling UploadService.addFileToQueue()
📞 BRIDGE CALL TO JAVA - UploadService.addFileToQueue
```

### ✅ في Java (UploadServicePlugin):
```
🔥🔥🔥 addFileToQueue() CALLED FROM JAVASCRIPT 🔥🔥🔥
📥 [Step 1/6] Extracting parameters...
📊 [Step 2/6] Parameters received:
   ├─ fileName: photo_123_1738594832123.jpg
   ├─ photoId: 123
   ├─ fileType: image/jpeg
   └─ apiUrl: https://...
✅ [Step 3/6] Validating parameters...
✅ [Step 4/6] Starting upload thread...
✅ [Step 5/6] HTTP request prepared
🌐 [Step 6/6] Uploading to server...
```

### ✅ عند النجاح:
```
✅✅✅ UPLOAD SUCCESS ✅✅✅
📢 Notification: تم رفع الصورة
✅✅✅ JAVA METHOD RETURNED SUCCESSFULLY ✅✅✅
```

### ❌ عند الفشل:
```
❌❌❌ UPLOAD FAILED ❌❌❌
❌ Error: Connection timeout
❌❌❌ ERROR OCCURRED ❌❌❌
```

---

## 🎯 الأوامر المفيدة

### مسح اللوجات القديمة:
```bash
adb logcat -c
```

### رؤية اللوجات الحالية فقط (بدون حفظ):
```bash
adb logcat -v threadtime MainActivity:E UploadServicePlugin:E AutoUploadApp:E chromium:I *:S
```

### حفظ اللوجات في ملف محدد:
```bash
adb logcat -v threadtime MainActivity:E UploadServicePlugin:E AutoUploadApp:E chromium:I *:S > my_logs.txt
```

### الأمر الكامل (مع JavaScript):
```bash
adb logcat -c && adb logcat -v threadtime MainActivity:E UploadServicePlugin:E AutoUploadApp:E UploadTaskScheduler:E BackgroundUploadWorker:E UploadDatabaseHelper:E UploadBootReceiver:E chromium:I *:S
```

---

## 🔧 حل المشاكل

### ❌ المشكلة: "adb not found"
**الحل:**
```powershell
# تثبيت adb عبر Android SDK Platform Tools
# أو إضافة مسار adb إلى PATH
```

### ❌ المشكلة: "No device connected"
**الحل:**
```
1. تأكد من توصيل الكابل
2. فعّل USB Debugging
3. جرب أمر: adb devices
4. إذا ظهر "unauthorized" - اقبل على الهاتف
```

### ❌ المشكلة: لا تظهر لوجات JavaScript
**الحل:**
```bash
# استخدم chromium:I أو chromium:D بدلاً من chromium:E
adb logcat -v threadtime MainActivity:E UploadServicePlugin:E chromium:D *:S
```

### ❌ المشكلة: اللوجات كثيرة جداً
**الحل:**
```bash
# استخدم فقط المكونات المهمة:
adb logcat -v threadtime MainActivity:E UploadServicePlugin:E *:S
```

---

## 📁 ملفات اللوجات المحفوظة

الملفات ستحفظ بصيغة:
```
aso_app_logs_2026-02-03_14-30-25.txt
```

موقع الحفظ: نفس مجلد تشغيل الأمر

---

## ✅ الخلاصة

1. **استخدم START_LOGGING.ps1 في PowerShell**
2. **افتح التطبيق والتقط صورة**
3. **راقب اللوجات لتحديد المشكلة بالضبط**
4. **اللوجات محفوظة تلقائياً في ملف مع timestamp**

---

تم بناء APK بنجاح في:
`android/app/build/outputs/apk/debug/app-debug.apk`
