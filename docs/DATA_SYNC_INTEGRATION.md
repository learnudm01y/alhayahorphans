# 🔗 ربط نظام مزامنة البيانات بـ JavaScript

## ✅ الوضع الحالي

**Android (Java):** النظام مسجل بنجاح ✅
```
MainActivity: ✅ MainActivity - Both systems registered:
MainActivity:    📁 Files: UploadServicePlugin
MainActivity:    📊 Data: BackgroundSyncPlugin
```

**JavaScript:** غير مربوط ❌
- لا يوجد استدعاء لـ `BackgroundSync` من JavaScript
- البيانات تُحفظ في IndexedDB فقط
- **لا تُرسل للسيرفر!**

---

## 🚀 الحل السريع

### 1. إضافة `data-sync-manager.js` للصفحات

في `index.html` أو أي صفحة HTML:

```html
<script src="/js/data-sync-manager.js"></script>
```

### 2. تعديل `sync-service.js`

في دالة `saveLocalChange()`:

```javascript
async function saveLocalChange(sponsorshipId, updates) {
    console.log('💾 saveLocalChange - sponsorshipId:', sponsorshipId);
    console.log('💾 saveLocalChange - updates:', updates);
    
    // ===== الكود الموجود: حفظ في IndexedDB =====
    const db = await openDatabase();
    const tx = db.transaction('sponsorships', 'readwrite');
    const store = tx.objectStore('sponsorships');
    
    const currentSponsorship = await store.get(sponsorshipId);
    const updated = { ...currentSponsorship, ...updates };
    
    await store.put(updated);
    await tx.done;
    
    console.log('✅ Saved to IndexedDB');
    
    // ===== NEW: إضافة للمزامنة مع السيرفر =====
    try {
        if (window.BackgroundSync) {
            await window.BackgroundSync.addDataToQueue({
                dataType: 'sponsorship',
                dataJson: JSON.stringify(updated),
                endpoint: '/api/mobile/sponsorships/sync'
            });
            
            console.log('✅ Queued for server sync');
            
            // بدء المزامنة
            await window.BackgroundSync.startService();
        }
    } catch (error) {
        console.error('❌ Failed to queue for sync:', error);
    }
}
```

### 3. اختبار النظام

افتح في المتصفح: `http://localhost/test-data-sync.html`

أو من Android Logcat:

```bash
adb logcat | grep -E "(BackgroundSync|DataSync)"
```

---

## 📋 أمثلة الاستخدام

### مثال 1: حفظ كفالة

```javascript
// عند حفظ بيانات كفالة في detail.html
async function saveSponsorship(sponsorshipId, updates) {
    // 1. حفظ في IndexedDB
    await saveLocalChange(sponsorshipId, updates);
    
    // 2. إضافة للمزامنة مع السيرفر
    const sponsorship = await getLocalSponsorship(sponsorshipId);
    
    await window.BackgroundSync.addDataToQueue({
        dataType: 'sponsorship',
        dataJson: JSON.stringify(sponsorship),
        endpoint: '/api/mobile/sponsorships/sync'
    });
    
    // 3. بدء المزامنة
    await window.BackgroundSync.startService();
}
```

### مثال 2: حفظ دفعة مالية

```javascript
async function savePayment(paymentData) {
    // 1. حفظ في IndexedDB
    await savePaymentToIndexedDB(paymentData);
    
    // 2. إضافة للمزامنة
    await window.BackgroundSync.addDataToQueue({
        dataType: 'payment',
        dataJson: JSON.stringify(paymentData),
        endpoint: '/api/mobile/payments/sync'
    });
    
    await window.BackgroundSync.startService();
}
```

### مثال 3: الحصول على الحالة

```javascript
// عرض عدد البيانات المنتظرة
async function showSyncStatus() {
    const status = await window.BackgroundSync.getSyncStatus();
    
    console.log('📊 Sync Status:');
    console.log('   Pending:', status.pending);
    console.log('   Uploaded:', status.uploaded);
    console.log('   Failed:', status.failed);
    
    alert(`منتظر: ${status.pending}\nمرفوع: ${status.uploaded}\nفاشل: ${status.failed}`);
}
```

---

## 🔍 التحقق من التكامل

### في Console (F12):

```javascript
// 1. فحص وجود Plugin
console.log(window.BackgroundSync);
// يجب أن يعرض: {addDataToQueue: ƒ, startService: ƒ, getSyncStatus: ƒ, ...}

// 2. إضافة بيانات تجريبية
await window.BackgroundSync.addDataToQueue({
    dataType: "test",
    dataJson: JSON.stringify({ id: 1, name: "Test" }),
    endpoint: "/api/test"
});

// 3. الحصول على الحالة
const status = await window.BackgroundSync.getSyncStatus();
console.log(status);
// Expected: {pending: 1, uploaded: 0, failed: 0, total: 1}

// 4. بدء المزامنة
await window.BackgroundSync.startService();
```

### في Logcat:

يجب أن ترى:

```
DataSyncDatabaseHelper: ✅ Data added to queue: ID=1
DataSyncForegroundService: 🚀🚀🚀 DataSyncForegroundService CREATED
DataSyncForegroundService: 🔄 Syncing: ID=1, Type=test
DataSyncForegroundService: ✅ Sync successful: ID=1
```

---

## ⚠️ ملاحظات مهمة

1. **BackgroundSync ≠ UploadService**
   - `UploadService`: للملفات (صور، فيديوهات، PDFs)
   - `BackgroundSync`: للبيانات (JSON، localStorage، IndexedDB)

2. **كلاهما مستقلان**
   - يمكن استخدامهما معاً بالتوازي
   - لا يؤثر أحدهما على الآخر

3. **Laravel API مطلوب**
   - يجب إنشاء endpoints في Laravel:
     - `/api/mobile/sponsorships/sync`
     - `/api/mobile/payments/sync`
     - إلخ...

---

## 📁 الملفات الجديدة

1. `/public/js/data-sync-manager.js` - مدير المزامنة
2. `/public/test-data-sync.html` - صفحة اختبار
3. `/docs/DATA_SYNC_INTEGRATION.md` - هذا الملف

---

## 🎯 الخطوات التالية

1. ✅ إضافة `data-sync-manager.js` للصفحات
2. ✅ تعديل `sync-service.js` لإضافة البيانات للقائمة
3. ✅ اختبار من `test-data-sync.html`
4. ⏳ إنشاء Laravel endpoints
5. ⏳ اختبار المزامنة الكاملة

---

**APK الحالي:** `android/app/build/outputs/apk/debug/app-debug.apk`  
**الحالة:** ✅ BackgroundSyncPlugin مسجل ✅ جاهز للاستخدام
