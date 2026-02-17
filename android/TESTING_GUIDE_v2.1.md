# دليل الاختبار السريع - APK v2.1

**التاريخ**: 16 فبراير 2026  
**الإصدار**: v2.1
**حجم APK**: 28.66 MB

---

## ✅ قبل التثبيت

### 1. إلغاء تثبيت النسخة القديمة
```bash
adb uninstall com.aso.app
```

### 2. تثبيت APK v2.1
```bash
adb install "i:\unit test\alhayahorphans\ASO - Copy\android\app\build\outputs\apk\debug\app-debug.apk"
```

### 3. بدء LogCat
```bash
adb logcat -c  # مسح اللوجات القديمة
adb logcat | findstr /C:"FileSyncWorker" /C:"NetworkMonitor" /C:"UploadServicePlugin" /C:"MainActivity"
```

---

## 🧪 سيناريوهات الاختبار

### اختبار 1: التقاط صورة بدون إنترنت ✅

**الخطوات**:
1. ✈️ قم بإيقاف WiFi و Mobile Data
2. افتح التطبيق
3. التقط صورة جديدة
4. راقب اللوجات

**النتيجة المتوقعة**:
```log
✅ Saved to SQLite with Queue ID: X
📤 [1/5] scheduleImmediateSync() CALLED
📤 [2/5] Pending files count: 1
📤 [3/5] Network constraint: WorkManager will auto-start when connected
✅ scheduleImmediateSync() COMPLETE!
   ⏳ Worker will start when network available + constraints met

❌ لا يجب أن ترى:
   "Network available NOW: false"
   "NO NETWORK FOUND!"
```

**✅ النجاح**: الملف محفوظ في قاعدة البيانات، Worker في انتظار الشبكة

---

### اختبار 2: عودة الإنترنت ✅

**الخطوات**:
1. من الاختبار السابق (صورة محفوظة، إنترنت مطفأ)
2. 🌐 تفعيل WiFi
3. راقب اللوجات

**النتيجة المتوقعة**:
```log
📡 onAvailable() - شبكة متاحة
🔥 Transition: offline → online - triggering upload
📤 scheduleImmediateSync() CALLED
✅ scheduleImmediateSync() COMPLETE!

🏭 FileSyncWorker CONSTRUCTOR called
🔄 FileSyncWorker.doWork() STARTED
🌐 Network available in doWork(): YES ✅

📤 [Upload 1/1] Starting upload for file: photo_XXX.jpg
⏳ Starting upload to: https://alhayahorphans.org/...
✅ Upload SUCCESSFUL!
💾 Updating status to: uploaded
```

**✅ النجاح**: Worker بدأ تلقائياً والرفع نجح

---

### اختبار 3: لا حلقات لانهائية ✅

**الخطوات**:
1. بعد عودة الإنترنت (من الاختبار 2)
2. انتظر 30 ثانية
3. راقب عدد Workers

**النتيجة المتوقعة**:
```log
✅ يجب أن ترى:
   - Worker واحد فقط يعمل
   - بعد الانتهاء، لا يوجد Workers جديدة

❌ لا يجب أن ترى:
   - 📡 onCapabilitiesChanged() → handleNetworkAvailable() (كل ثانية)
   - FileSyncWorker CONSTRUCTOR called (متكرر 30+ مرة)
   - scheduleImmediateSync() ALREADY IN PROGRESS - SKIPPING! (كثير جداً)
```

**✅ النجاح**: Worker واحد فقط، لا تكرار

---

### اختبار 4: حفظ الصور لا يكسر الذاكرة ✅

**الخطوات**:
1. التقط 5 صور متتالية
2. راقب اللوجات

**النتيجة المتوقعة**:
```log
✅ File exists: 1.77 MB
ℹ️ File saved in app directory (no public Documents copy)
ℹ️ Reason: Prevents memory crashes during heavy save operations

❌ لا يجب أن ترى:
   "💾 Starting memory-safe document save..."
   "⚠️ Document save failed: فشل الحفظ"
   AsyncDocumentSaver logs
```

**✅ النجاح**: لا رسائل فشل، الحفظ سريع وبسيط

---

### اختبار 5: قفل Deduplication يعمل ✅

**الخطوات**:
1. التقط صورة
2. سريعاً، التقط صورة ثانية (قبل بدء الرفع)
3. راقب اللوجات

**النتيجة المتوقعة**:
```log
// الصورة الأولى:
📤 [1/5] scheduleImmediateSync() CALLED
✅ scheduleImmediateSync() COMPLETE!
🔓 Lock released

// الصورة الثانية (مباشرة بعدها):
📤 [1/5] scheduleImmediateSync() CALLED
⚠️⚠️⚠️ scheduleImmediateSync() ALREADY IN PROGRESS - SKIPPING!
   (This prevents infinite loop from duplicate triggers)
```

**✅ النجاح**: القفل يمنع الاستدعاءات المتزامنة

---

## 🔍 نقاط التحقق من اللوجات

### ✅ عند بدء التطبيق:
```log
🚨🚨🚨   v22:56 - FILE SIZE FIX   🚨🚨🚨
✅ v22:56 APPLICATION READY - File Size Calculation Fixed
🏭🏭🏭 FileSyncWorker CONSTRUCTOR called 🏭🏭🏭
🔌 UploadServicePlugin.load() - PLUGIN LOADED
🔥🔥🔥 MainActivity.onCreate() - APK v23:45 🔥🔥🔥
```

### ✅ عند التقاط صورة (بدون إنترنت):
```log
🔥🔥🔥 addFileToQueue() CALLED FROM JAVASCRIPT 🔥🔥🔥
✅ File exists: X.XX MB
ℹ️ File saved in app directory (no public Documents copy)
💾 [Step 5/5] Saving to SQLite upload queue...
✅ Saved to SQLite with Queue ID: X
📤 scheduleImmediateSync() CALLED
📤 [2/5] Pending files count: 1
✅ scheduleImmediateSync() COMPLETE!
```

### ✅ عند عودة الإنترنت:
```log
📡 onAvailable() - شبكة متاحة
🔥 Transition: offline → online - triggering upload
🔄 FileSyncWorker.doWork() STARTED
🌐 Network available in doWork(): YES ✅
📤 [Upload 1/X] Starting upload for file: ...
✅ Upload SUCCESSFUL!
💾 Updating status to: uploaded
```

### ❌ لا يجب أن ترى أبداً:
```log
❌ 📡 onCapabilitiesChanged() - الإنترنت متاح الآن
   handleNetworkAvailable()  // ← يجب ألا يُستدعى من هنا

❌ 📤 [3/7] Network available NOW: false
   ❌❌❌ NO NETWORK FOUND!

❌ 💾 Starting memory-safe document save...
   ⚠️ Document save failed: فشل الحفظ

❌ FileSyncWorker CONSTRUCTOR called (متكرر 30+ مرة في دقيقة)
```

---

## 📊 مقاييس الأداء

### استهلاك الذاكرة:
- **قبل**: 200-300 MB (بسبب AsyncDocumentSaver)
- **بعد**: 100-150 MB (normal app usage)

### عدد Workers:
- **قبل**: 30+ في 5 ثواني (infinite loop)
- **بعد**: 1 worker فقط

### استهلاك البطارية:
- **قبل**: نزول سريع (بسبب Workers المتكررة)
- **بعد**: استهلاك عادي

---

## 🚨 علامات المشاكل

إذا رأيت أي من هذه الرسائل، **المشكلة لم تُحل**:

1. ❌ `onCapabilitiesChanged() - الإنترنت متاح الآن` → handleNetworkAvailable()
   - **المشكلة**: Infinite loop لم يُصلح
   - **الحل**: تحقق من NetworkMonitor.java

2. ❌ `scheduleImmediateSync() ALREADY IN PROGRESS` (كثير جداً، 10+ في ثانية)
   - **المشكلة**: Multiple triggers
   - **الحل**: تحقق من NetworkMonitor وقفل Deduplication

3. ❌ `Document save failed: فشل الحفظ`
   - **المشكلة**: AsyncDocumentSaver ما زال نشطاً
   - **الحل**: تحقق من UploadServicePlugin.java line 234

4. ❌ `Network available NOW: false` (عند وجود إنترنت)
   - **المشكلة**: Network check اليدوي ما زال موجود
   - **الحل**: تحقق من FileSyncWorker.scheduleImmediateSync()

---

## ✅ تأكيد النجاح الكامل

**جميع** النقاط التالية يجب أن تكون صحيحة:

- ✅ Worker واحد فقط يعمل (لا infinite loop)
- ✅ لا رسائل "Document save failed"
- ✅ الرفع التلقائي يعمل عند عودة الإنترنت
- ✅ لا رسائل "NO NETWORK FOUND" عند الجدولة
- ✅ `onAvailable()` فقط يُطلق trigger (ليس `onCapabilitiesChanged()`)
- ✅ استهلاك الذاكرة طبيعي (<150 MB)
- ✅ البطارية لا تستنزف

---

## 📝 ملاحظات

### حذف الملفات عند إلغاء التثبيت
- الملفات **لن تبقى** في Public Documents بعد إلغاء تثبيت التطبيق
- **السبب**: تم تعطيل AsyncDocumentSaver لمنع مشاكل الذاكرة
- **الحل**: الملفات **مرفوعة للسيرفر** - النسخة الأساسية محفوظة

### فحص الملفات المرفوعة
```bash
# تحقق من قاعدة البيانات المحلية
adb shell "run-as com.aso.app sqlite3 /data/data/com.aso.app/databases/upload_queue.db 'SELECT * FROM upload_queue;'"
```

---

## 🎯 الخلاصة

إذا نجحت جميع الاختبارات أعلاه:
- ✅ **المشكلة 1 محلولة**: لا infinite loop
- ✅ **المشكلة 2 محلولة**: لا memory crashes
- ✅ **المشكلة 3 محلولة**: الرفع التلقائي يعمل

**التطبيق الآن جاهز للاستخدام الفعلي! 🎉**
