# 🧪 اختبار نظام مزامنة البيانات - دليل سريع

## ✅ تم إصلاح المشكلة

**المشكلة السابقة:** BackgroundSyncPlugin لم يكن مسجلاً في MainActivity  
**الحل:** تم إضافة `registerPlugin(BackgroundSyncPlugin.class)` في MainActivity.java

---

## 🚀 كيفية الاختبار من JavaScript Console

افتح DevTools في التطبيق واكتب:

### 1. التحقق من وجود Plugin

```javascript
// يجب أن يعرض الـ object
console.log(window.BackgroundSync);

// يجب أن يعرض الوظائف
console.log(Object.keys(window.BackgroundSync));
// Expected: ["addDataToQueue", "startService", "getSyncStatus", "retryFailedData", "clearCompletedData", ...]
```

### 2. إضافة بيانات للاختبار

```javascript
// إضافة بيانات تجريبية
const result = await window.BackgroundSync.addDataToQueue({
    dataType: "test",
    dataJson: JSON.stringify({ id: 1, name: "Test Data" }),
    endpoint: "/api/test"
});

console.log(result);
// Expected: { success: true, id: 1, message: "..." }
```

### 3. التحقق من الإحصائيات

```javascript
const stats = await window.BackgroundSync.getSyncStatus();
console.log(stats);
// Expected: { pending: 1, uploaded: 0, failed: 0, total: 1 }
```

### 4. بدء المزامنة يدوياً

```javascript
const result = await window.BackgroundSync.startService();
console.log(result);
// Expected: { success: true, message: "تم بدء خدمة المزامنة..." }
```

### 5. مراقبة الـ Logcat

في Android Studio أو Terminal:

```bash
adb logcat | grep -E "(DataSync|BackgroundSync)"
```

يجب أن ترى:
```
DataSyncDatabaseHelper: ✅ Data added to queue: ID=1
DataSyncForegroundService: 🚀🚀🚀 DataSyncForegroundService CREATED
DataSyncForegroundService: 🔄 Syncing: ID=1, Type=test
```

---

## 📋 الـ Logs المتوقعة (بعد الإصلاح)

### عند بدء التطبيق:

```
MainActivity: ✅ MainActivity - Both systems registered:
MainActivity:    📁 Files: UploadServicePlugin
MainActivity:    📊 Data: BackgroundSyncPlugin
```

### عند إضافة بيانات:

```
BackgroundSyncPlugin: ✅ Data added to queue: ID=1
DataSyncDatabaseHelper: Added new data to queue: test
```

### عند بدء المزامنة:

```
DataSyncForegroundService: 🚀🚀🚀 DataSyncForegroundService CREATED
DataSyncForegroundService: 🔄 Syncing: ID=1, Type=test
DataSyncForegroundService: ✅ Sync successful: ID=1
```

---

## 🔍 استكشاف الأخطاء

### إذا ظهر `BackgroundSync is undefined`:

1. تأكد من تثبيت APK الجديد
2. أعد تشغيل التطبيق بالكامل
3. تحقق من Logcat: يجب أن ترى "Both systems registered"

### إذا لم تبدأ المزامنة:

1. تحقق من وجود بيانات منتظرة:
   ```javascript
   const stats = await BackgroundSync.getSyncStatus();
   console.log('Pending:', stats.pending);
   ```

2. تحقق من الإنترنت:
   ```javascript
   console.log(navigator.onLine);
   ```

3. ابدأ المزامنة يدوياً:
   ```javascript
   await BackgroundSync.startService();
   ```

---

## 📊 مثال كامل من الكود الفعلي

```javascript
// في ملف synchronization.js أو أي ملف آخر

class DataSyncManager {
    
    async addSponsorship(sponsorshipData) {
        try {
            // إضافة للقائمة
            const result = await BackgroundSync.addDataToQueue({
                dataType: "sponsorship",
                dataJson: JSON.stringify(sponsorshipData),
                endpoint: "/api/mobile/sponsorships/sync"
            });
            
            console.log('✅ Sponsorship queued:', result.id);
            
            // بدء المزامنة
            await BackgroundSync.startService();
            
            return result;
            
        } catch (error) {
            console.error('❌ Failed to queue sponsorship:', error);
            throw error;
        }
    }
    
    async getStatus() {
        const stats = await BackgroundSync.getSyncStatus();
        return stats;
    }
    
    async retryFailed() {
        const result = await BackgroundSync.retryFailedData();
        console.log(`Retried ${result.retried} items`);
    }
}

// الاستخدام
const syncManager = new DataSyncManager();

// إضافة كفالة
await syncManager.addSponsorship({
    sponsor_id: 123,
    orphan_id: 456,
    amount: 1000,
    date: new Date().toISOString()
});

// التحقق من الحالة
const status = await syncManager.getStatus();
console.log('Pending:', status.pending);
console.log('Uploaded:', status.uploaded);
```

---

## ✅ التحقق النهائي

قبل الاعتماد على النظام، تأكد من:

1. ✅ `window.BackgroundSync` موجود
2. ✅ يمكن إضافة بيانات بنجاح
3. ✅ `getSyncStatus()` تعرض الأعداد الصحيحة
4. ✅ `startService()` يبدأ DataSyncForegroundService (شاهد Logcat)
5. ✅ البيانات تُرفع بنجاح للـ API

---

## 🎯 الملف الجديد

**المسار:** `android/app/build/outputs/apk/debug/app-debug.apk`  
**التغيير:** BackgroundSyncPlugin مسجل الآن في MainActivity

ثبّت الـ APK الجديد واختبر! 🚀
