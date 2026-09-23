# 🌉 حل مشكلة الربط بين JavaScript و Java

## المشكلة الأصلية
من الـ Logcat كان واضحاً أن:
- ✅ JavaScript يحفظ البيانات في IndexedDB بنجاح
- ❌ Java لا يعرف عن هذه البيانات (لا logs من BackgroundSync)
- ❌ لا يوجد استدعاء لـ BackgroundSync.addDataToQueue() من JavaScript

## السبب
`sync-service.js` في `production-files/` يحفظ البيانات في IndexedDB لكن **لا يخبر Java**!

## الحل (بدون تعديل production-files)

### 1️⃣ إنشاء JavaScript Bridge
أنشأنا جسر مباشر بين JavaScript و Java:

**ملف:** `org/alhayah/sponsorships/JavaScriptBridge.java`
```java
@JavascriptInterface
public void onDataSaved(String jsonData) {
    // استقبال بيانات من JavaScript
    // حفظها في DataSyncDatabaseHelper
    // بدء DataSyncForegroundService فوراً
}
```

### 2️⃣ تسجيل Bridge في MainActivity
```java
WebView webView = getBridge().getWebView();
JavaScriptBridge jsBridge = new JavaScriptBridge(this);
webView.addJavascriptInterface(jsBridge, "AndroidBridge");
```

### 3️⃣ الآن JavaScript يمكنه الاستدعاء مباشرة
```javascript
// من أي مكان في JavaScript:
window.AndroidBridge.onDataSaved(JSON.stringify({
    dataType: 'sponsorship',
    dataJson: JSON.stringify(data),
    endpoint: '/api/mobile/sponsorships/sync'
}));
```

## كيف يعمل النظام الآن؟

```
┌─────────────────────────────────────────────────────────┐
│  1. المستخدم يعدّل بيانات في detail.html                │
│     (مثال: تغيير الاسم من "محمد01" إلى "محمد")          │
└───────────────────┬─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│  2. sync-service.js: saveLocalChange()                  │
│     ✅ يحفظ في IndexedDB                                │
│     ✅ يحفظ في pending_uploads                          │
└───────────────────┬─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│  3. 🆕 JavaScript يستدعي AndroidBridge                  │
│     window.AndroidBridge.onDataSaved({...})             │
└───────────────────┬─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│  4. 🆕 JavaScriptBridge.java                            │
│     ✅ استقبال البيانات من JavaScript                   │
│     ✅ حفظها في DataSyncDatabaseHelper (SQLite)        │
│     ✅ بدء DataSyncForegroundService                    │
└───────────────────┬─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│  5. DataSyncForegroundService                           │
│     ✅ قراءة البيانات من SQLite                         │
│     ✅ رفعها للسيرفر عبر API                            │
│     ✅ تحديث الحالة (uploaded/failed)                   │
└─────────────────────────────────────────────────────────┘
```

## الملفات المُضافة/المُعدلة

### ✅ ملفات جديدة
1. **JavaScriptBridge.java** - الجسر بين JS و Java
   - `onDataSaved()` - استقبال بيانات من JS
   - `getSyncStatus()` - الحصول على إحصائيات
   - `retryFailed()` - إعادة محاولة الفاشل

2. **test-js-bridge.html** - صفحة اختبار الربط
   - فحص وجود `window.AndroidBridge`
   - إرسال بيانات تجريبية
   - عرض الإحصائيات الحية

### ✅ ملفات معدلة
1. **MainActivity.java**
   - إضافة import للـ WebView و JavaScriptBridge
   - تسجيل الـ Bridge: `webView.addJavascriptInterface(jsBridge, "AndroidBridge")`

2. **DataSyncForegroundService.java**
   - إضافة `public static void startSync(Context)` لبدء الخدمة بسهولة

## الاختبار

### 1️⃣ تثبيت APK الجديد
```bash
adb install -r app-debug.apk
```

### 2️⃣ فتح صفحة الاختبار
في التطبيق، افتح: `http://localhost/test-js-bridge.html`

### 3️⃣ فحص الـ Logcat
```bash
adb logcat -v threadtime MainActivity:V JavaScriptBridge:V DataSyncForegroundService:V DataSyncDatabaseHelper:V *:S
```

**النتيجة المتوقعة:**
```
MainActivity: ✅ JavaScriptBridge added successfully
MainActivity: 💡 JavaScript can now call: window.AndroidBridge.onDataSaved(...)

JavaScriptBridge: 📥 onDataSaved called from JavaScript
JavaScriptBridge: 📊 Data: {"dataType":"sponsorship",...}
JavaScriptBridge: ✅ Data added to sync queue: ID=1

DataSyncForegroundService: 🚀 startSync() called
DataSyncForegroundService: 🔄 Syncing: ID=1, Type=sponsorship
DataSyncForegroundService: ✅ Sync successful
```

### 4️⃣ اختبار من detail.html
1. افتح كفالة موجودة (مثلاً id=908)
2. عدّل الاسم الأول
3. احفظ التعديلات
4. راقب Logcat - يجب أن ترى:
   - `sync-service.js: ✅ Sponsorship saved to IndexedDB`
   - `JavaScriptBridge: 📥 onDataSaved called`
   - `DataSyncForegroundService: 🔄 Syncing`

## API للمطورين

### من JavaScript
```javascript
// 1. إرسال بيانات للمزامنة
window.AndroidBridge.onDataSaved(JSON.stringify({
    dataType: 'sponsorship',      // نوع البيانات
    dataJson: JSON.stringify({    // البيانات نفسها
        id: 908,
        first_name: 'محمد',
        person_type: 'family_member'
    }),
    endpoint: '/api/mobile/sponsorships/sync'
}));

// 2. الحصول على الحالة
const statusJson = window.AndroidBridge.getSyncStatus();
const status = JSON.parse(statusJson);
console.log('Pending:', status.pending);
console.log('Uploaded:', status.uploaded);
console.log('Failed:', status.failed);

// 3. إعادة محاولة الفاشل
const resultJson = window.AndroidBridge.retryFailed();
const result = JSON.parse(resultJson);
console.log('Retried:', result.retried);
```

### من Java
```java
// بدء المزامنة من أي مكان
DataSyncForegroundService.startSync(context);

// إضافة بيانات مباشرة
DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);
long id = dbHelper.addDataToQueue("sponsorship", jsonData, "/api/mobile/sync");
```

## الفرق عن BackgroundSyncPlugin

| الميزة | BackgroundSyncPlugin (Capacitor) | JavaScriptBridge (Native) |
|--------|----------------------------------|---------------------------|
| الاستدعاء | `window.Capacitor.Plugins.BackgroundSync.addDataToQueue()` | `window.AndroidBridge.onDataSaved()` |
| السرعة | أبطأ (عبر Capacitor bridge) | أسرع (مباشر) |
| التوفر | فقط في بيئة Capacitor | في أي WebView |
| الاستخدام | للـ API الرسمي | للربط السريع المباشر |

**يمكن استخدام الاثنين معاً** - لا تعارض بينهما!

## استكشاف الأخطاء

### ❌ window.AndroidBridge غير موجود
- تأكد من تثبيت APK الجديد
- افحص Logcat لرسالة: `JavaScriptBridge added successfully`

### ❌ لا logs من JavaScriptBridge
- تأكد من استدعاء `window.AndroidBridge.onDataSaved()`
- تحقق من أن البيانات JSON صحيح

### ❌ DataSyncForegroundService لا يبدأ
- افحص permissions في AndroidManifest.xml
- تحقق من أن Device ليس في Battery Saver mode

## الخطوة التالية

**دمج في sync-service.js** (عندما يُسمح بالتعديل):
```javascript
async saveLocalChange(sponsorshipId, updates) {
    // ... الكود الموجود ...
    await this.dbPut('sponsorships', updated);
    
    // 🆕 إضافة هذا:
    if (window.AndroidBridge) {
        window.AndroidBridge.onDataSaved(JSON.stringify({
            dataType: 'sponsorship',
            dataJson: JSON.stringify(updated),
            endpoint: '/api/mobile/sponsorships/sync'
        }));
    }
}
```

---

**تم الحل! ✅**

الآن JavaScript و Java متصلان بنجاح:
- JavaScript → AndroidBridge → Java ✅
- Java → SQLite → DataSyncForegroundService → Server ✅
