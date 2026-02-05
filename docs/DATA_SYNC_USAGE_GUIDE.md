# 📊 نظام مزامنة البيانات القوي - دليل الاستخدام

## 🎯 نظرة عامة

تم بناء نظام مزامنة بيانات قوي ومستقل **معزول تماماً** عن نظام رفع الملفات.

**النظامان:**
- 📁 **نظام رفع الملفات**: `UploadForegroundService` (com.aso.app)
- 📊 **نظام مزامنة البيانات**: `DataSyncForegroundService` (org.alhayah.sponsorships)

---

## ✅ المميزات الرئيسية

### نظام مزامنة البيانات الجديد:

1. **✅ WorkManager** - جدولة دورية كل 15 دقيقة
2. **✅ BootReceiver** - إعادة تشغيل تلقائي بعد إعادة تشغيل الجهاز
3. **✅ DataSyncNetworkMonitor** - مراقبة الإنترنت ومزامنة فورية عند العودة
4. **✅ DataSyncDatabaseHelper** - قاعدة بيانات SQLite لحفظ الحالة
5. **✅ Retry Logic** - 3 محاولات لكل عنصر قبل وضعه في "فشل"
6. **✅ WakeLock + Foreground** - لا ينام حتى ينتهي
7. **✅ معزول تماماً** - لا يؤثر على نظام الملفات

---

## 📦 المكونات الجديدة

### 1. DataSyncDatabaseHelper.java
قاعدة بيانات SQLite منفصلة:
- **Database**: `data_sync.db`
- **Table**: `sync_queue`
- **Fields**:
  - `id` - معرف فريد
  - `data_type` - نوع البيانات (sponsorship, payment, orphan...)
  - `data_json` - البيانات بصيغة JSON
  - `endpoint` - API endpoint
  - `status` - pending | uploading | uploaded | failed
  - `retry_count` - عدد المحاولات
  - `error_message` - رسالة الخطأ الأخيرة
  - `created_at` - تاريخ الإنشاء
  - `uploaded_at` - تاريخ الرفع

### 2. DataSyncForegroundService.java
خدمة Foreground قوية:
- قراءة من Database
- Retry logic (3 محاولات)
- WakeLock (ساعة واحدة)
- إشعارات مع تقدم
- حفظ الحالة باستمرار

### 3. DataSyncWorker.java
WorkManager Worker:
- يعمل كل 15 دقيقة
- يفحص قاعدة البيانات
- يبدأ الخدمة إذا وُجدت بيانات منتظرة

### 4. DataSyncBootReceiver.java
BroadcastReceiver:
- يستمع لـ BOOT_COMPLETED
- يبدأ المزامنة تلقائياً عند إعادة التشغيل

### 5. DataSyncNetworkMonitor.java
مراقب الإنترنت:
- يراقب حالة الاتصال
- يبدأ المزامنة فور عودة الإنترنت

---

## 🚀 الاستخدام من JavaScript

### 1. إضافة بيانات للقائمة

```javascript
import { Plugins } from '@capacitor/core';
const { BackgroundSync } = Plugins;

// إضافة بيانات جديدة
async function addDataToSyncQueue(dataType, dataObject, apiEndpoint) {
    try {
        const result = await BackgroundSync.addDataToQueue({
            dataType: dataType,        // مثال: "sponsorship"
            dataJson: JSON.stringify(dataObject),  // بيانات JSON
            endpoint: apiEndpoint      // مثال: "/api/sync/sponsorship"
        });
        
        console.log('✅ Data added to queue:', result);
        // result.id - معرف العنصر في قاعدة البيانات
        
    } catch (error) {
        console.error('❌ Failed to add data:', error);
    }
}

// مثال عملي
const sponsorshipData = {
    sponsor_id: 123,
    orphan_id: 456,
    amount: 1000,
    date: "2026-02-05"
};

await addDataToSyncQueue(
    "sponsorship",
    sponsorshipData,
    "/api/sync/sponsorship"
);
```

### 2. بدء المزامنة يدوياً

```javascript
async function startManualSync() {
    try {
        const result = await BackgroundSync.startService();
        console.log('✅ Sync started:', result.message);
    } catch (error) {
        console.error('❌ Failed to start sync:', error);
    }
}

// مثال: زر في الواجهة
document.getElementById('sync-btn').addEventListener('click', () => {
    startManualSync();
});
```

### 3. الحصول على الإحصائيات

```javascript
async function getSyncStatistics() {
    try {
        const stats = await BackgroundSync.getSyncStatus();
        
        console.log('📊 Sync Statistics:');
        console.log('   Pending:', stats.pending);
        console.log('   Uploaded:', stats.uploaded);
        console.log('   Failed:', stats.failed);
        console.log('   Total:', stats.total);
        
        // عرض في الواجهة
        document.getElementById('pending-count').textContent = stats.pending;
        document.getElementById('uploaded-count').textContent = stats.uploaded;
        document.getElementById('failed-count').textContent = stats.failed;
        
        return stats;
        
    } catch (error) {
        console.error('❌ Failed to get stats:', error);
    }
}

// تحديث الإحصائيات كل 5 ثواني
setInterval(getSyncStatistics, 5000);
```

### 4. إعادة محاولة البيانات الفاشلة

```javascript
async function retryFailedData() {
    try {
        const result = await BackgroundSync.retryFailedData();
        
        console.log(`✅ Retried ${result.retried} failed items`);
        alert(`تمت إعادة ${result.retried} عنصر فاشل إلى قائمة الانتظار`);
        
    } catch (error) {
        console.error('❌ Failed to retry:', error);
    }
}

// مثال: زر "إعادة المحاولة"
document.getElementById('retry-btn').addEventListener('click', () => {
    retryFailedData();
});
```

### 5. حذف البيانات المكتملة

```javascript
async function clearCompletedData() {
    try {
        const result = await BackgroundSync.clearCompletedData();
        
        console.log(`🗑️ Cleared ${result.cleared} completed items`);
        
        // تحديث الإحصائيات
        await getSyncStatistics();
        
    } catch (error) {
        console.error('❌ Failed to clear:', error);
    }
}

// مثال: تنظيف تلقائي كل 24 ساعة
setInterval(clearCompletedData, 24 * 60 * 60 * 1000);
```

### 6. إيقاف المزامنة

```javascript
async function stopSync() {
    try {
        const result = await BackgroundSync.stopService();
        console.log('⏸️ Sync stopped');
    } catch (error) {
        console.error('❌ Failed to stop sync:', error);
    }
}
```

---

## 📋 أمثلة عملية

### مثال 1: حفظ كفالة جديدة

```javascript
async function saveSponsorship(sponsorId, orphanId, amount) {
    const sponsorshipData = {
        sponsor_id: sponsorId,
        orphan_id: orphanId,
        amount: amount,
        date: new Date().toISOString(),
        status: "active"
    };
    
    // إضافة للقائمة
    await BackgroundSync.addDataToQueue({
        dataType: "sponsorship",
        dataJson: JSON.stringify(sponsorshipData),
        endpoint: "/api/mobile/sponsorships/sync"
    });
    
    console.log('✅ Sponsorship queued for sync');
}
```

### مثال 2: حفظ دفعة مالية

```javascript
async function savePayment(sponsorshipId, amount, method) {
    const paymentData = {
        sponsorship_id: sponsorshipId,
        amount: amount,
        payment_method: method,
        date: new Date().toISOString()
    };
    
    await BackgroundSync.addDataToQueue({
        dataType: "payment",
        dataJson: JSON.stringify(paymentData),
        endpoint: "/api/mobile/payments/sync"
    });
    
    console.log('✅ Payment queued for sync');
}
```

### مثال 3: لوحة تحكم المزامنة

```html
<div id="sync-dashboard">
    <h3>📊 حالة المزامنة</h3>
    <div>
        <span>منتظر: <strong id="pending-count">0</strong></span>
        <span>مكتمل: <strong id="uploaded-count">0</strong></span>
        <span>فاشل: <strong id="failed-count">0</strong></span>
    </div>
    
    <button onclick="startManualSync()">🔄 مزامنة الآن</button>
    <button onclick="retryFailedData()">♻️ إعادة المحاولة</button>
    <button onclick="clearCompletedData()">🗑️ تنظيف</button>
</div>

<script>
// تحديث تلقائي كل 5 ثواني
setInterval(async () => {
    const stats = await BackgroundSync.getSyncStatus();
    document.getElementById('pending-count').textContent = stats.pending;
    document.getElementById('uploaded-count').textContent = stats.uploaded;
    document.getElementById('failed-count').textContent = stats.failed;
}, 5000);
</script>
```

---

## 🔧 متطلبات Laravel API

يجب أن يكون لديك endpoints في Laravel تستقبل البيانات:

### مثال: SponsorshipSyncController.php

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SponsorshipSyncController extends Controller
{
    public function syncSponsorship(Request $request)
    {
        // التحقق من Token
        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $validated = $request->validate([
            'sponsor_id' => 'required|integer',
            'orphan_id' => 'required|integer',
            'amount' => 'required|numeric',
            'date' => 'required|date'
        ]);
        
        // حفظ في قاعدة البيانات
        $sponsorship = Sponsorship::create($validated);
        
        return response()->json([
            'success' => true,
            'id' => $sponsorship->id
        ], 200);
    }
    
    public function syncPayment(Request $request)
    {
        // مشابه للأعلى
    }
}
```

### routes/api.php

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/mobile/sponsorships/sync', [SponsorshipSyncController::class, 'syncSponsorship']);
    Route::post('/mobile/payments/sync', [SponsorshipSyncController::class, 'syncPayment']);
});
```

---

## 🛡️ الأمان والموثوقية

### مقارنة بنظام رفع الملفات:

| الميزة | نظام الملفات | نظام البيانات (الجديد) |
|--------|--------------|------------------------|
| WorkManager | ✅ | ✅ |
| BootReceiver | ✅ | ✅ |
| NetworkMonitor | ✅ | ✅ |
| Database Persistence | ✅ | ✅ |
| Retry Logic | ✅ (3 محاولات) | ✅ (3 محاولات) |
| ForegroundService | ✅ | ✅ |
| WakeLock | ✅ | ✅ |
| معزول | ✅ | ✅ |

**النتيجة:** نفس القوة والموثوقية! 🎯

---

## 📝 ملاحظات مهمة

1. **العزل التام**: لا يؤثر نظام البيانات على نظام الملفات
2. **قاعدة بيانات منفصلة**: `data_sync.db` (ليست `upload_queue.db`)
3. **خدمة منفصلة**: `DataSyncForegroundService` (ليست `UploadForegroundService`)
4. **WorkManager منفصل**: يعملان بالتوازي كل 15 دقيقة
5. **Token مطلوب**: يستخدم SharedPreferences للحصول على API token

---

## 🧪 اختبار النظام

### 1. اختبار إضافة بيانات

```javascript
// إضافة 5 عناصر للاختبار
for (let i = 1; i <= 5; i++) {
    await BackgroundSync.addDataToQueue({
        dataType: "test",
        dataJson: JSON.stringify({ id: i, name: `Test ${i}` }),
        endpoint: "/api/test"
    });
}

// التحقق
const stats = await BackgroundSync.getSyncStatus();
console.log('Pending:', stats.pending); // يجب أن يكون 5
```

### 2. اختبار المزامنة

```javascript
// بدء المزامنة
await BackgroundSync.startService();

// الانتظار 10 ثواني
await new Promise(resolve => setTimeout(resolve, 10000));

// التحقق
const stats = await BackgroundSync.getSyncStatus();
console.log('Uploaded:', stats.uploaded); // يجب أن تزيد
```

### 3. اختبار Retry

```javascript
// الحصول على عدد الفاشلة
const before = await BackgroundSync.getSyncStatus();
console.log('Failed before:', before.failed);

// إعادة المحاولة
await BackgroundSync.retryFailedData();

// التحقق
const after = await BackgroundSync.getSyncStatus();
console.log('Failed after:', after.failed); // يجب أن تصبح 0
console.log('Pending after:', after.pending); // يجب أن تزيد
```

---

## 🎉 الخلاصة

الآن لديك نظامان قويان:
1. 📁 **UploadForegroundService** - رفع الملفات (صور، فيديوهات، PDFs)
2. 📊 **DataSyncForegroundService** - مزامنة البيانات (JSON، localStorage، IndexedDB)

كلاهما يعمل بنفس القوة والموثوقية، معزولان تماماً عن بعضهما البعض!

**الملف الناتج:** `app-debug.apk` (28.1 MB)
**موقع الملف:** `android/app/build/outputs/apk/debug/app-debug.apk`
