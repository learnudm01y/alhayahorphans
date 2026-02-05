# نظام رفع الملفات التلقائي عند عودة الإنترنت

## 🔥 التحديثات الجديدة (4 فبراير 2026)

### ✅ المشاكل التي تم حلها:

1. **الملفات تُرفع فقط مع إنترنت** ❌ → **الملفات تُحفظ وتُرفع تلقائيًا عند عودة الإنترنت** ✅
2. **الملفات الفاشلة لا تُرفع مرة أخرى** ❌ → **إعادة رفع تلقائية عند عودة الإنترنت** ✅
3. **لا يوجد نسخ احتياطي للصور** ❌ → **حفظ تلقائي في مجلد خارجي** ✅

### 📂 حفظ الصور في مجلد خارجي

الآن يتم حفظ نسخة من كل صورة في مجلد خاص على الهاتف:

```
📱 الهاتف
  └─ 📁 Internal Storage
      └─ 📁 sponsorships_alhayahorphans
          ├─ photo_908_1770233846731.jpg
          ├─ photo_909_1770233904357.jpg
          └─ ...
```

**المسار الكامل**: `/storage/emulated/0/sponsorships_alhayahorphans/`

### 🔄 سلوك النظام الجديد

#### **حالة 1: رفع ملف بدون إنترنت**
```
1. المستخدم يلتقط صورة
2. لا يوجد إنترنت
3. ✅ يتم حفظ الصورة في:
   - Internal Storage (للرفع)
   - External Storage (نسخة احتياطية)
4. ✅ يتم حفظ بيانات الرفع في SQLite
5. الحالة = PENDING
6. ⏳ انتظار عودة الإنترنت...
```

#### **حالة 2: عودة الإنترنت**
```
1. ✅ NetworkMonitor يكتشف عودة الإنترنت
2. ✅ يعيد تعيين جميع الملفات الفاشلة → PENDING
3. ✅ يبدأ رفع جميع الملفات المعلقة تلقائيًا
4. ✅ عند النجاح: حذف من Internal Storage
5. ✅ النسخة الاحتياطية تبقى في External Storage
```

#### **حالة 3: فشل الرفع مؤقتاً**
```
1. محاولة رفع الملف
2. فشل بسبب انقطاع الإنترنت
3. ❌ لا تُحتسب كمحاولة فاشلة
4. ✅ الحالة تبقى PENDING
5. ⏳ انتظار 30 ثانية قبل المحاولة التالية
6. ✅ عند عودة الإنترنت: رفع تلقائي
```

## التحسينات التقنية

### 1. إصلاح منطق إعادة المحاولة

**قبل التحديث** ❌:
- الملف يفشل 3 مرات → يُوضع كـ `failed` نهائيًا
- لا يتم رفعه مرة أخرى أبدًا

**بعد التحديث** ✅:
- الملف يفشل بسبب الشبكة → يبقى `pending`
- انتظار 30 ثانية بين المحاولات
- عند عودة الإنترنت: إعادة تعيين العداد والرفع

### 2. إضافة دالة `resetFailedFiles()`

في `UploadDatabaseHelper.java`:
```java
public int resetFailedFiles() {
    // إعادة جميع الملفات الفاشلة إلى pending
    // إعادة تعيين عداد المحاولات إلى 0
    // حذف رسائل الخطأ
    return عدد_الملفات_المعاد_تعيينها;
}
```

### 3. تحسين NetworkMonitor

عند اكتشاف عودة الإنترنت:
```java
handleNetworkAvailable() {
    1. إعادة تعيين الملفات الفاشلة → pending
    2. فحص عدد الملفات المعلقة
    3. بدء الرفع الفوري
}
```

### 4. حفظ الصور في مجلد خارجي

في `UploadServicePlugin.java`:
```java
// حفظ في Internal Storage (للرفع)
File internalFile = new File(getContext().getFilesDir(), "uploads/" + fileName);

// حفظ نسخة في External Storage (احتياطي)
File externalFile = new File(
    Environment.getExternalStorageDirectory(),
    "sponsorships_alhayahorphans/" + fileName
);
```

## السجلات (Logs) المتوقعة

### عند انقطاع الإنترنت:
```
UploadForegroundService: ❌ خطأ في رفع photo_908.jpg: Unable to resolve host
UploadForegroundService: ⚠️ فشل - سيُعاد عند عودة الإنترنت (محاولة 1)
UploadDatabaseHelper: تم تحديث حالة الملف 2 إلى: pending
```

### عند عودة الإنترنت:
```
NetworkMonitor: 🌐🌐🌐 تم الاتصال بالإنترنت! 🌐🌐🌐
NetworkMonitor: 🔄 تم إعادة تعيين 3 ملف فاشل للمحاولة مرة أخرى
NetworkMonitor: 📊 عدد الملفات المعلقة: 5
NetworkMonitor: 🔥🔥🔥 بدء رفع 5 ملف معلق! 🔥🔥🔥
UploadForegroundService: 📤 بدء رفع ملف...
UploadForegroundService: ✅✅✅ نجح الرفع!
```

### حفظ في External Storage:
```
UploadServicePlugin: ✅ File saved (Internal): /data/.../files/uploads/photo_908.jpg
UploadServicePlugin: ✅ نسخة محفوظة في External: /storage/emulated/0/sponsorships_alhayahorphans/photo_908.jpg
UploadServicePlugin: 📂 المجلد الخارجي: /storage/emulated/0/sponsorships_alhayahorphans/
```

## الصلاحيات الجديدة

تم إضافة في `AndroidManifest.xml`:
```xml
<!-- للكتابة على External Storage (Android 12 وأقل) -->
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" android:maxSdkVersion="32" />

<!-- للقراءة من External Storage (Android 12 وأقل) -->
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" android:maxSdkVersion="32" />

<!-- للوصول للصور (Android 13+) -->
<uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />
```

## اختبار النظام المحدّث

### اختبار 1: حفظ بدون إنترنت ورفع عند العودة

1. **أوقف الإنترنت تماماً** (Wi-Fi + بيانات)
2. افتح التطبيق والتقط 3 صور
3. **تحقق من Logcat**: يجب أن ترى حفظ في pending
4. **تحقق من المجلد**: `/storage/emulated/0/sponsorships_alhayahorphans/`
5. **فعّل الإنترنت**
6. **انتظر 10 ثوانٍ**
7. **تحقق من Logcat**: يجب أن ترى:
   ```
   NetworkMonitor: 🌐 تم الاتصال بالإنترنت!
   NetworkMonitor: 🔄 تم إعادة تعيين X ملف
   UploadForegroundService: 📤 بدء رفع...
   UploadForegroundService: ✅✅✅ نجح الرفع!
   ```

### اختبار 2: التحقق من المجلد الخارجي

```bash
# عبر ADB
adb shell ls -la /storage/emulated/0/sponsorships_alhayahorphans/

# يجب أن ترى:
# photo_908_1770233846731.jpg
# photo_909_1770233904357.jpg
# ...
```

### اختبار 3: انقطاع أثناء الرفع

1. ابدأ رفع صورة كبيرة (مع إنترنت)
2. أثناء الرفع: أوقف الإنترنت
3. **تحقق**: الملف يبقى pending
4. فعّل الإنترنت
5. **تحقق**: يتم رفع الملف تلقائيًا

## الملفات المعدّلة

### ملفات جديدة:
- ✅ `NetworkMonitor.java`

### ملفات محدّثة:
- ✅ `UploadDatabaseHelper.java` → إضافة `resetFailedFiles()`
- ✅ `UploadForegroundService.java` → تحسين منطق إعادة المحاولة
- ✅ `NetworkMonitor.java` → إعادة تعيين الملفات الفاشلة
- ✅ `UploadServicePlugin.java` → حفظ في External Storage
- ✅ `AndroidManifest.xml` → إضافة صلاحيات External Storage
- ✅ `AutoUploadApplication.java` → تفعيل NetworkMonitor

## الفوائد

### 1. **موثوقية 100%**
- لا تُفقد أي صورة حتى مع انقطاع الإنترنت
- الرفع التلقائي عند عودة الاتصال

### 2. **نسخ احتياطي تلقائي**
- جميع الصور محفوظة في مجلد خارجي
- يمكن الوصول إليها عبر File Manager

### 3. **تجربة مستخدم سلسة**
- لا حاجة لإعادة رفع الصور يدويًا
- النظام يعمل في الخلفية تلقائيًا

### 4. **استهلاك موارد منخفض**
- انتظار 30 ثانية بين المحاولات
- لا استهلاك مفرط للبطارية

## ملاحظات مهمة

1. **الصور في External Storage** لا تُحذف بعد الرفع الناجح (نسخة احتياطية دائمة)
2. **الصور في Internal Storage** تُحذف بعد الرفع لتوفير المساحة
3. **المجلد** `sponsorships_alhayahorphans` مرئي للمستخدم ويمكن الوصول إليه
4. **NetworkMonitor** يعمل طوال الوقت ويستهلك موارد ضئيلة جدًا

---

**تاريخ التحديث**: 4 فبراير 2026  
**النسخة**: 2.0  
**الحالة**: ✅ جاهز ومختبر

## المكونات الجديدة

### 1. NetworkMonitor.java
- **الموقع**: `android/app/src/main/java/com/aso/app/NetworkMonitor.java`
- **الوظيفة**: مراقبة حالة الإنترنت بشكل مستمر
- **المميزات**:
  - اكتشاف تلقائي لعودة الإنترنت
  - اكتشاف انقطاع الإنترنت
  - تشغيل عملية الرفع فورًا عند عودة الاتصال

### 2. التحديثات على AutoUploadApplication.java
- تم إضافة تفعيل NetworkMonitor عند تشغيل التطبيق
- يبدأ النظام تلقائيًا مع بدء التطبيق

## كيف يعمل النظام؟

### 1. عند بدء التطبيق
```
Application Start
    ↓
AutoUploadApplication.onCreate()
    ↓
NetworkMonitor.startMonitoring()
    ↓
تسجيل Network Callback
```

### 2. عند انقطاع الإنترنت
```
الإنترنت ينقطع
    ↓
NetworkCallback.onLost()
    ↓
isNetworkAvailable = false
    ↓
الملفات تُحفظ في قاعدة البيانات المحلية
```

### 3. عند عودة الإنترنت
```
الإنترنت يعود
    ↓
NetworkCallback.onAvailable()
    ↓
التحقق من الملفات المعلقة في DB
    ↓
إذا وُجدت ملفات معلقة
    ↓
UploadTaskScheduler.scheduleUploadTask()
    +
UploadTaskScheduler.startImmediateUpload()
    ↓
رفع جميع الملفات المعلقة
```

## سيناريوهات الاستخدام

### السيناريو 1: رفع ملف أثناء وجود إنترنت
1. المستخدم يختار ملف للرفع
2. يتم الرفع فورًا والحفظ في قاعدة البيانات
3. عند نجاح الرفع، يتم حذف السجل من قاعدة البيانات

### السيناريو 2: رفع ملف بدون إنترنت
1. المستخدم يختار ملف للرفع
2. يتم حفظ الملف في قاعدة البيانات المحلية (SQLite)
3. حالة الملف = PENDING
4. عند عودة الإنترنت:
   - NetworkMonitor يكتشف العودة
   - يتم رفع جميع الملفات المعلقة تلقائيًا
   - يتم حذف السجلات عند النجاح

### السيناريو 3: انقطاع الإنترنت أثناء الرفع
1. بدأ رفع الملف
2. انقطع الإنترنت
3. حالة الملف = PENDING (يتم إعادة تعيينها)
4. عند عودة الإنترنت، يتم رفع الملف مرة أخرى

## المميزات الرئيسية

### ✅ Offline-First
- التطبيق يعمل بدون إنترنت
- البيانات تُحفظ محليًا في SQLite
- الرفع يتم تلقائيًا عند توفر الإنترنت

### ✅ رفع تلقائي
- لا يحتاج المستخدم لإعادة محاولة الرفع يدويًا
- النظام يكتشف عودة الإنترنت ويبدأ الرفع

### ✅ مراقبة مستمرة
- NetworkMonitor يعمل طوال الوقت
- يكتشف التغيرات في حالة الشبكة فورًا

### ✅ معالجة الأخطاء
- إعادة محاولة حتى 3 مرات لكل ملف
- الملفات الفاشلة تُحفظ مع رسالة الخطأ
- السجلات الناجحة تُحذف تلقائيًا

### ✅ أداء محسّن
- رفع متزامن للملفات
- استخدام WorkManager للعمل في الخلفية
- WakeLock لمنع توقف الرفع

## السجلات (Logs)

يمكنك متابعة عمل النظام من خلال Logcat:

```bash
# مراقبة NetworkMonitor
adb logcat -s NetworkMonitor

# مراقبة النظام بالكامل
adb logcat -s NetworkMonitor AutoUploadApp UploadTaskScheduler

# البحث عن رسائل الإنترنت
adb logcat | grep "الإنترنت"
```

### أمثلة على السجلات المتوقعة:

#### عند بدء التطبيق:
```
NetworkMonitor: 🔍🔍🔍 بدء مراقبة حالة الإنترنت 🔍🔍🔍
NetworkMonitor: 📊 حالة الإنترنت الحالية: متصل ✅
NetworkMonitor: ✅ تم تفعيل المراقبة بنجاح
```

#### عند عودة الإنترنت:
```
NetworkMonitor: 📡 onAvailable() - شبكة متاحة
NetworkMonitor: 🌐🌐🌐 تم الاتصال بالإنترنت! 🌐🌐🌐
NetworkMonitor: 📊 عدد الملفات المعلقة: 5
NetworkMonitor: 🔥🔥🔥 بدء رفع 5 ملف معلق! 🔥🔥🔥
NetworkMonitor: ✅ تم تشغيل عملية الرفع بنجاح
```

#### عند انقطاع الإنترنت:
```
NetworkMonitor: 📡 onLost() - فقدان الشبكة
NetworkMonitor: ❌❌❌ انقطع الاتصال بالإنترنت ❌❌❌
NetworkMonitor: ℹ️  الملفات الجديدة ستبقى في قاعدة البيانات
NetworkMonitor: ℹ️  سيتم رفعها تلقائيًا عند عودة الإنترنت
```

## الاختبار

### اختبار عودة الإنترنت:

1. **تشغيل التطبيق بدون إنترنت**:
   - أوقف Wi-Fi والبيانات
   - شغّل التطبيق
   - أضف ملفات للرفع

2. **تفعيل الإنترنت**:
   - شغّل Wi-Fi أو البيانات
   - راقب Logcat
   - يجب أن يبدأ الرفع تلقائيًا خلال ثوانٍ

3. **التحقق من النتائج**:
   - افتح Logcat
   - ابحث عن "بدء رفع" أو "تم الاتصال بالإنترنت"
   - تحقق من رفع جميع الملفات

### اختبار انقطاع الإنترنت أثناء الرفع:

1. ابدأ رفع ملف كبير
2. أثناء الرفع، أوقف الإنترنت
3. يجب أن يتم إعادة الملف لحالة PENDING
4. عند تفعيل الإنترنت، يتم رفع الملف مرة أخرى

## الملفات المعدّلة

1. ✅ `NetworkMonitor.java` - ملف جديد
2. ✅ `AutoUploadApplication.java` - تم تحديثه
3. ✅ `AndroidManifest.xml` - الصلاحيات موجودة مسبقًا

## التوافق

- **Android 6.0 (API 23)** وما فوق: دعم كامل
- **Android 5.0 (API 21-22)**: دعم محدود (يستخدم NetworkInfo بدلاً من NetworkCallback)

## ملاحظات مهمة

1. **استهلاك البطارية**: منخفض جدًا - يستخدم Network Callback بدلاً من Polling
2. **استهلاك الذاكرة**: ضئيل - Singleton Pattern
3. **الأمان**: يستخدم HTTPS للرفع
4. **الخصوصية**: الملفات تُحفظ محليًا حتى الرفع الناجح

## التحسينات المستقبلية المحتملة

- [ ] إضافة تحكم في نوع الشبكة (Wi-Fi فقط / أي شبكة)
- [ ] إضافة إشعارات للمستخدم عند بدء الرفع
- [ ] إضافة إحصائيات عن الملفات المرفوعة
- [ ] إضافة خيار الرفع اليدوي

---

**تاريخ الإنشاء**: 4 فبراير 2026
**الحالة**: ✅ نشط ويعمل
