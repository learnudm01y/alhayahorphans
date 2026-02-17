# 📡 دليل تكامل نظام الرفع في الوقت الفعلي
# Real-Time Upload Status Sync - Integration Guide

---

## 📋 نظرة عامة

تم إصلاح مشكلة عدم تحديث واجهة المستخدم بعد رفع الملفات بنجاح. النظام الآن يستخدم **Capacitor Event System** لإرسال أحداث من `FileSyncWorker` (Java) إلى JavaScript UI في الوقت الفعلي.

---

## ✅ ما تم إصلاحه

### المشكلة السابقة:
- ✅ الملف يتم رفعه بنجاح (200 OK response)
- ✅ Database يتم تحديثه إلى `STATUS_COMPLETED`
- ❌ **لكن** واجهة JavaScript لا تعلم بالتحديث
- ❌ عداد "الملفات المعلقة" يبقى ثابتاً
- ❌ صفحة upload.html لا تُظهر "تم الرفع"

### الحل:
تم إنشاء **Event Broadcasting System** يربط FileSyncWorker بـ JavaScript:

```
FileSyncWorker (Java)
    ↓ على نجاح/فشل الرفع
UploadServicePlugin.notifyUploadStatusChanged()
    ↓ يبث event
Capacitor Event System
    ↓ يرسل إلى
JavaScript Event Listener
    ↓ يحدث
UI Update (عداد + قوائم + إشعارات)
```

---

## 🔧 التعديلات التي تمت (Java Side)

### 1. **UploadServicePlugin.java** - Event Broadcasting Infrastructure

```java
@CapacitorPlugin(name = "UploadService")
public class UploadServicePlugin extends Plugin {
    private static UploadServicePlugin instance;
    
    @Override
    public void load() {
        super.load();
        instance = this; // تخزين للوصول الستاتيكي
        createNotificationChannel();
    }
    
    /**
     * ✨ NEW: بث حدث تغيير حالة الرفع إلى JavaScript
     */
    public static void notifyUploadStatusChanged(long fileId, String status, String error) {
        if (instance != null) {
            Log.e(TAG, "📡 Broadcasting upload status to JavaScript");
            
            JSObject data = new JSObject();
            data.put("fileId", fileId);
            data.put("status", status);
            if (error != null) data.put("error", error);
            
            // إرسال Event إلى JavaScript
            instance.notifyListeners("uploadStatusChanged", data);
        }
    }
    
    /**
     * ✨ NEW: الحصول على إحصائيات الرفع من SQLite
     */
    @PluginMethod
    public void getUploadStats(PluginCall call) {
        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
        
        int pending = dbHelper.getFilesByStatus(STATUS_PENDING).size();
        int uploading = dbHelper.getFilesByStatus(STATUS_UPLOADING).size();
        int completed = dbHelper.getFilesByStatus(STATUS_COMPLETED).size();
        int failed = dbHelper.getFilesByStatus(STATUS_FAILED).size();
        
        JSObject result = new JSObject();
        result.put("pending", pending);
        result.put("uploading", uploading);
        result.put("completed", completed);
        result.put("failed", failed);
        result.put("total", pending + uploading + completed + failed);
        
        call.resolve(result);
    }
    
    /**
     * ✨ NEW: جلب قائمة ملفات حسب الحالة
     */
    @PluginMethod
    public void getFilesByStatus(PluginCall call) {
        String status = call.getString("status", STATUS_PENDING);
        UploadDatabaseHelper dbHelper = UploadDatabaseHelper.getInstance(getContext());
        
        List<UploadItem> items = dbHelper.getFilesByStatus(status);
        
        JSArray filesArray = new JSArray();
        for (UploadItem item : items) {
            JSObject fileObj = new JSObject();
            fileObj.put("id", item.id);
            fileObj.put("fileName", item.fileName);
            fileObj.put("status", item.status);
            // ... باقي الحقول
            filesArray.put(fileObj);
        }
        
        JSObject result = new JSObject();
        result.put("files", filesArray);
        result.put("count", items.size());
        
        call.resolve(result);
    }
}
```

---

### 2. **FileSyncWorker.java** - Event Triggering

```java
// في doWork() بعد uploadFile()
if (success) {
    Log.e(TAG, "✅ Upload successful!");
    successCount++;
    
    // تحديث Database
    dbHelper.updateFileStatus(item.id, STATUS_COMPLETED, null);
    
    // ✨ NEW: إرسال event إلى JavaScript
    try {
        UploadServicePlugin.notifyUploadStatusChanged(
            item.id, 
            STATUS_COMPLETED, 
            null
        );
        Log.e(TAG, "📡 JavaScript notified of upload success");
    } catch (Exception e) {
        Log.w(TAG, "⚠️ Failed to notify JavaScript: " + e.getMessage());
    }
    
    // حذف الملف المحلي بعد النجاح
    deleteLocalFile(item.filePath);
    
} else {
    // إعادة المحاولة أو فشل نهائي
    if (item.retryCount >= 2) {
        String errorMsg = "Exceeded max retry attempts (3)";
        
        // تحديث Database
        dbHelper.updateFileStatus(item.id, STATUS_FAILED, errorMsg);
        
        // ✨ NEW: إرسال event للفشل
        UploadServicePlugin.notifyUploadStatusChanged(
            item.id, 
            STATUS_FAILED, 
            errorMsg
        );
    }
}
```

---

## 🌐 التكامل مع JavaScript

### 1. **إضافة Event Listener**

```javascript
import { UploadService } from '@capacitor-community/upload-service';

// عند تحميل التطبيق
document.addEventListener('DOMContentLoaded', () => {
    
    // 📡 الاستماع لأحداث تغيير حالة الرفع
    UploadService.addListener('uploadStatusChanged', (event) => {
        console.log('📡 Upload status changed:', event);
        
        /*
         * Event Structure:
         * {
         *   fileId: number,
         *   status: "completed" | "failed",
         *   error?: string
         * }
         */
        
        if (event.status === 'completed') {
            // ✅ نجح الرفع
            handleUploadSuccess(event.fileId);
            
        } else if (event.status === 'failed') {
            // ❌ فشل الرفع
            handleUploadFailure(event.fileId, event.error);
        }
        
        // تحديث الإحصائيات
        updateUploadStats();
    });
    
    console.log('✅ Upload event listener registered');
});
```

---

### 2. **تحديث عداد الملفات المعلقة**

```javascript
async function updateUploadStats() {
    try {
        // استدعاء Plugin للحصول على الإحصائيات من SQLite
        const stats = await UploadService.getUploadStats();
        
        console.log('📊 Upload stats:', stats);
        /*
         * Response:
         * {
         *   pending: 5,
         *   uploading: 1,
         *   completed: 120,
         *   failed: 2,
         *   total: 128
         * }
         */
        
        // تحديث عنصر العداد في الواجهة
        const statFilesElement = document.getElementById('stat-files');
        if (statFilesElement) {
            statFilesElement.textContent = stats.pending;
            
            // تأثير بصري
            statFilesElement.classList.add('flash-update');
            setTimeout(() => {
                statFilesElement.classList.remove('flash-update');
            }, 1000);
        }
        
        // تحديث badge في زر الرفع
        const uploadBtn = document.getElementById('uploadBtn');
        if (uploadBtn) {
            const badge = uploadBtn.querySelector('.badge') || 
                          document.createElement('span');
            badge.className = 'badge badge-danger';
            badge.textContent = stats.pending;
            badge.style.display = stats.pending > 0 ? 'inline-block' : 'none';
            
            if (!uploadBtn.contains(badge)) {
                uploadBtn.appendChild(badge);
            }
        }
        
    } catch (error) {
        console.error('❌ Error fetching stats:', error);
    }
}
```

---

### 3. **تحديث UI للملف الخاص**

```javascript
function handleUploadSuccess(fileId) {
    console.log(`✅ File #${fileId} uploaded successfully`);
    
    // البحث عن بطاقة الملف في الواجهة
    const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);
    
    if (fileCard) {
        // تحديث الحالة
        fileCard.classList.remove('uploading', 'pending', 'error');
        fileCard.classList.add('success');
        
        // تحديث النص
        const statusElement = fileCard.querySelector('.upload-status');
        if (statusElement) {
            statusElement.innerHTML = `
                <span class="text-success">
                    <i class="fas fa-check-circle"></i>
                    تم الرفع بنجاح
                </span>
            `;
        }
        
        // تأثير Fade Out وإزالة من قائمة المعلقة
        setTimeout(() => {
            fileCard.style.transition = 'opacity 0.5s';
            fileCard.style.opacity = '0';
            setTimeout(() => fileCard.remove(), 500);
        }, 2000);
    }
    
    // إظهار إشعار
    showToast('✅ تم رفع الملف بنجاح', 'success');
}

function handleUploadFailure(fileId, error) {
    console.error(`❌ File #${fileId} upload failed:`, error);
    
    const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);
    
    if (fileCard) {
        fileCard.classList.add('error');
        
        const statusElement = fileCard.querySelector('.upload-status');
        if (statusElement) {
            statusElement.innerHTML = `
                <span class="text-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    فشل: ${error}
                </span>
            `;
        }
    }
    
    // إظهار إشعار خطأ
    showToast(`❌ فشل رفع الملف: ${error}`, 'error');
}
```

---

### 4. **جلب وعرض قائمة الملفات المعلقة**

```javascript
async function displayPendingFiles() {
    try {
        // جلب قائمة الملفات المعلقة من SQLite
        const result = await UploadService.getFilesByStatus({
            status: 'pending'
        });
        
        console.log('📋 Pending files:', result);
        /*
         * Response:
         * {
         *   files: [
         *     {
         *       id: 123,
         *       fileName: "video_20231215.mp4",
         *       fileType: "video/mp4",
         *       status: "pending",
         *       retryCount: 0,
         *       createdAt: "2023-12-15T10:30:00"
         *     },
         *     ...
         *   ],
         *   count: 5
         * }
         */
        
        const container = document.getElementById('pending-files-list');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (result.count === 0) {
            container.innerHTML = `
                <p class="text-muted text-center">
                    ✅ لا توجد ملفات معلقة
                </p>
            `;
            return;
        }
        
        // عرض كل ملف
        result.files.forEach(file => {
            const fileCard = createFileCard(file);
            container.appendChild(fileCard);
        });
        
    } catch (error) {
        console.error('❌ Error fetching pending files:', error);
    }
}

function createFileCard(file) {
    const card = document.createElement('div');
    card.className = 'file-card card mb-2';
    card.setAttribute('data-file-id', file.id);
    
    card.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${file.fileName}</strong>
                    <small class="text-muted d-block">${file.fileType}</small>
                </div>
                <div class="upload-status">
                    <span class="badge badge-warning">معلق</span>
                </div>
            </div>
        </div>
    `;
    
    return card;
}
```

---

## 🎨 إضافة CSS للتأثيرات

```css
/* تأثير الوميض عند التحديث */
@keyframes flash {
    0%, 100% { background-color: transparent; }
    50% { background-color: #ffc107; }
}

.flash-update {
    animation: flash 0.5s;
}

/* تأثير النجاح */
@keyframes pulse-success {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 rgba(40, 167, 69, 0);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
    }
}

.file-card.success {
    border-left: 4px solid #28a745;
    animation: pulse-success 0.5s;
}

/* تأثير الخطأ */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.file-card.error {
    border-left: 4px solid #dc3545;
    animation: shake 0.5s;
}

/* Badge */
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 10px;
    margin-left: 5px;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-success {
    background-color: #28a745;
    color: white;
}
```

---

## 🧪 اختبار النظام

### 1. **Build و Install APK**

```powershell
cd android
.\gradlew assembleDebug --quiet

# تثبيت على الجهاز
adb uninstall com.aso.app
adb install app\build\outputs\apk\debug\app-debug.apk
```

---

### 2. **مراقبة Logs**

```powershell
# فلترة logs الخاصة بالرفع
adb logcat -s FileSyncWorker:* UploadServicePlugin:*
```

**Logs المتوقعة عند نجاح الرفع:**

```
E FileSyncWorker: 🔍🔍🔍 STEP 1: Calculating actual file size...
E FileSyncWorker: ✅✅✅ File size calculation COMPLETE: 23771310 bytes (22.67 MB)
E FileSyncWorker: 🔍🔍🔍 STEP 2: Creating OkHttp RequestBody...
E FileSyncWorker: 📏 contentLength() called - returning: 23771310
E FileSyncWorker: 🔍🔍🔍 STEP 3: writeTo() called - Starting to stream...
E FileSyncWorker: 📊 Progress: 20% (chunk 10/50)
E FileSyncWorker: 📊 Progress: 40% (chunk 20/50)
E FileSyncWorker: 📊 Progress: 60% (chunk 30/50)
E FileSyncWorker: 📊 Progress: 80% (chunk 40/50)
E FileSyncWorker: 📊 Progress: 100% (chunk 50/50)
E FileSyncWorker: ✅✅✅ writeTo() COMPLETE: Match YES ✅
E FileSyncWorker: 🔍🔍🔍 STEP 4: Executing OkHttp request...
E FileSyncWorker: 📡 SERVER RESPONSE: code=200, success=true, time=45237 ms
E FileSyncWorker: ✅ Upload successful!
E FileSyncWorker: 📡 JavaScript notified of upload success
E UploadServicePlugin: 📡 Broadcasting upload status to JavaScript: fileId=1, status=completed
```

---

### 3. **اختبار JavaScript Console**

افتح Chrome DevTools (في التطبيق أو عبر Remote Debugging):

```javascript
// يجب أن ترى هذه الرسائل:
📡 Upload status changed: { fileId: 1, status: "completed" }
📊 Upload stats: { pending: 4, uploading: 0, completed: 1, failed: 0, total: 5 }
🔢 Updating pending files counter: 4
✅ File #1 uploaded successfully
🔔 Toast: ✅ تم رفع الملف بنجاح (success)
```

---

### 4. **التحقق من UI**

✅ **ما يجب أن يحدث:**
- عداد "الملفات المعلقة" ينقص: `5 → 4`
- بطاقة الملف تتحول إلى اللون الأخضر مع علامة ✅
- إشعار Toast يظهر: "تم الرفع بنجاح"
- بعد ثانيتين، بطاقة الملف تختفي من قائمة المعلقة

---

## 🔧 استكشاف الأخطاء

### المشكلة: Event لا يصل إلى JavaScript

**الحل:**
1. تأكد من تسجيل Event Listener قبل بدء الرفع:
   ```javascript
   UploadService.addListener('uploadStatusChanged', ...);
   ```

2. تأكد من import صحيح:
   ```javascript
   import { UploadService } from '@capacitor-community/upload-service';
   ```

3. تحقق من Logs:
   ```
   E UploadServicePlugin: 📡 Broadcasting upload status to JavaScript
   ```
   إذا ظهر هذا السطر، المشكلة في JavaScript.

---

### المشكلة: `getUploadStats()` يرجع null

**الحل:**
- تأكد من استخدام `await`:
  ```javascript
  const stats = await UploadService.getUploadStats();
  ```

- تحقق من الأخطاء في Console:
  ```javascript
  try {
      const stats = await UploadService.getUploadStats();
  } catch (error) {
      console.error('Error:', error);
  }
  ```

---

### المشكلة: عداد الملفات المعلقة غير دقيق

**الحل:**
- قد يكون هناك ملفات معلقة قديمة في Database.
- يمكن مسح Database:
  ```javascript
  // في Chrome DevTools Console أو عبر adb
  adb shell
  run-as com.aso.app
  rm /data/data/com.aso.app/databases/file_upload_queue.db
  ```

---

## 📝 ملخص التدفق الكامل

```
1. المستخدم يسجل فيديو
   ↓
2. CameraActivity.java يحفظ في MediaStore + SQLite
   ↓
3. FileSyncWorker (background) يبدأ:
   - يقرأ الملف ويحسب الحجم الحقيقي
   - ينشئ OkHttp RequestBody
   - يرفع الملف بنظام Streaming
   - يرسل POST إلى Backend
   ↓
4. Backend (SponsorshipSyncController) يستقبل:
   - يتحقق من sponsorship_id
   - يحفظ الملف محلياً
   - يدخل سجل في uploads table
   - يرد بـ 200 OK
   ↓
5. FileSyncWorker على استقبال 200:
   - يحدث Database إلى STATUS_COMPLETED
   - يحذف الملف المحلي
   - 🎯 ✨ NEW: يبث event عبر UploadServicePlugin
   ↓
6. UploadServicePlugin.notifyUploadStatusChanged():
   - يرسل JSObject عبر Capacitor
   - Event name: "uploadStatusChanged"
   - Data: { fileId, status, error }
   ↓
7. JavaScript Event Listener يستقبل:
   - يحدث عداد الملفات المعلقة
   - يحدث UI للملف الخاص
   - يعرض إشعار Toast
   - يزيل الملف من قائمة المعلقة بعد ثانيتين
   ↓
8. المستخدم يرى التحديث فوراً ✅
```

---

## 📚 ملفات مرجعية

- **UploadServicePlugin.java** - Capacitor Plugin مع Event Broadcasting + Stats Methods
- **FileSyncWorker.java** - Background Worker مع Event Triggering
- **UPLOAD_SYNC_JAVASCRIPT.js** - JavaScript Integration Example (كامل)
- **INTEGRATION_GUIDE.md** - هذا الملف

---

## ⚡ خطوات سريعة للبدء

```javascript
// 1. أضف Listener في app.js أو main.js
import { UploadService } from '@capacitor-community/upload-service';

document.addEventListener('DOMContentLoaded', () => {
    UploadService.addListener('uploadStatusChanged', async (event) => {
        if (event.status === 'completed') {
            const stats = await UploadService.getUploadStats();
            document.getElementById('stat-files').textContent = stats.pending;
        }
    });
});

// 2. Build APK
// cd android
// .\gradlew assembleDebug

// 3. Install
// adb install app\build\outputs\apk\debug\app-debug.apk

// 4. اختبر عبر Chrome DevTools
// chrome://inspect
```

---

✅ **النظام الآن يحدث الواجهة في الوقت الفعلي بدون الحاجة لإعادة تحميل الصفحة!**
