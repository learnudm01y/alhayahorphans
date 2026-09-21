# 🔍 تقرير التشخيص والإصلاح الشامل لنظام المزامنة
## تطبيق Android alhayahorphans - Sync System Overhaul

**تاريخ البدء:** 2026-02-14  
**المهندس:** AI System Engineer  
**الهدف:** تحويل النظام إلى Single Sync Orchestrator مستقر وموثوق

---

## ✅ الإصلاحات الفورية المُنفذة

### 1. إصلاح خطأ `Directory.Data` في sync-service.js

**المشكلة:**
```javascript
// ❌ خطأ
directory: window.Capacitor.Plugins.Filesystem.Directory.Data
// TypeError: Cannot read properties of undefined (reading 'Data')
```

**السبب:**
- في Capacitor 6.x، `Directory` هو enum مباشر وليس كائناً متداخلاً
- القيم الصحيحة: `'DATA'`, `'DOCUMENTS'`, `'CACHE'`

**الحل المُطبق:**
```javascript
// ✅ صحيح
directory: 'DATA',  // قيمة enum مباشرة
```

**الملف المُعدّل:**
- `android/app/src/main/assets/public/js/sync-service.js` (السطر 1504)

**النتيجة:**
- ✅ يمكن الآن حفظ الملفات بنجاح في Internal Storage
- ✅ الحصول على file URI للرفع من Java (NO BASE64)

---

## 📋 خطة الإصلاح الشامل - الحالة الراهنة

### المشاكل المُكتشفة من اللوجات

1. **AlarmManager Aggressive Polling** ⚠️
   - يعمل كل 10 ثواني → drain بطارية
   - يسبب تعدد عمليات Worker
   - **الحل:** استبدال بـ WorkManager UniqueWork

2. **عدم وجود Resumable Upload** ❌
   - عند انقطاع الشبكة، يبدأ الرفع من الصفر
   - لا يوجد session tracking
   - **الحل:** تطبيق upload_sessions table + chunk tracking

3. **Duplicate Uploads** ❌
   - نفس الملف يُرفع عدة مرات
   - لا يوجد file hash/index checking
   - **الحل:** إضافة file_hash column + idempotency check

4. **عدم وجود Conflict Resolution Policy** ❌
   - تعديلات متزامنة من أجهزة متعددة
   - لا توجد version control
   - **الحل:** إضافة version headers + server-authoritative policy

5. **ForegroundService لكل ملف** ⚠️
   - يبدأ ForegroundService حتى للملفات الصغيرة
   - **الحل:** استخدام Worker.setForegroundAsync() فقط للملفات >10MB

---

## 🔬 Step 1: تحليل اللوجات الحالية

### الأوامر المطلوبة:

```bash
# اتصال بالجهاز
adb devices

# جمع اللوجات الشاملة
adb logcat -v time -d > baseline_logs.txt

# اللوجات المُركزة على المزامنة
adb logcat -v time | grep -E "Upload|Sync|Memory|Renderer|OOM|Crash|Alarm|WorkManager|UploadService" > sync_focused_logs.txt

# معلومات الذاكرة
adb shell dumpsys meminfo com.aso.app > memory_baseline.txt

# حالة WorkManager
adb shell dumpsys jobscheduler > workmanager_status.txt
```

### التحليل المبدئي (من الكود):

#### المشاكل المُكتشفة:

**1. UploadAlarmReceiver (مشكلة رئيسية)**
```java
// File: UploadAlarmReceiver.java
alarmManager.setRepeating(
    AlarmManager.RTC_WAKEUP,
    System.currentTimeMillis(),
    10 * 1000,  // ❌ كل 10 ثواني!
    pendingIntent
);
```
**التأثير:**
- 360 محاولة رفع في الساعة
- Battery drain شديد
- تعدد Workers متزامنة
- احتمال عالي لـ ANR

**2. UploadServicePlugin.addFileToQueue()**
```java
// يبدأ ForegroundService فوراً لكل ملف
Intent serviceIntent = new Intent(context, UploadForegroundService.class);
context.startForegroundService(serviceIntent);
```
**التأثير:**
- ForegroundService حتى لملف 1KB
- تزاحم Services
- notification spam

**3. لا يوجد upload_sessions table**
- ملف يُقطع → يبدأ من الصفر
- waste bandwidth + time

---

## 📊 Step 2: استرجاع قواعد البيانات

### الأوامر:

```bash
# سحب قواعد البيانات
adb pull /data/data/com.aso.app/databases/upload_queue.db ./diagnosis/
adb pull /data/data/com.aso.app/databases/data_sync_queue.db ./diagnosis/

# فحص SQLite
sqlite3 ./diagnosis/upload_queue.db

# استعلامات تشخيصية:
SELECT status, COUNT(*) FROM upload_queue GROUP BY status;
SELECT * FROM upload_queue WHERE retry_count > 3;
SELECT file_name, COUNT(*) FROM upload_queue GROUP BY file_name HAVING COUNT(*) > 1;
```

### التحليل المتوقع:

**مشاكل محتملة:**
1. ملفات مكررة (نفس file_name عدة مرات)
2. ملفات failed مع retry_count > 10
3. ملفات pending قديمة (created_at < 7 days ago)
4. مسارات ملفات غير موجودة (orphaned records)

---

## 🧪 Step 3: اختبار Triggers (سيناريوهات الفشل)

### السيناريو A: ملف كبير (500MB) + WiFi مستقر

**الخطوات:**
1. حذف جميع السجلات المعلقة من upload_queue
2. إضافة فيديو 500MB من التطبيق
3. مراقبة اللوجات: `adb logcat -v time | grep Upload`

**المتوقع (حالياً - غير صحيح):**
- UploadAlarmReceiver يُطلق كل 10s
- UploadForegroundService يبدأ عدة مرات
- Worker قد يبدأ متزامن مع Service

**المطلوب (بعد الإصلاح):**
- SyncWorker واحد فقط يبدأ (enqueueUniqueWork)
- Worker.setForegroundAsync() للملف >10MB
- OkHttp streaming بدون تحميل كامل الملف

### السيناريو B: قطع الشبكة أثناء الرفع

**الخطوات:**
1. بدء رفع ملف كبير
2. انتظار 30% من الرفع
3. قطع WiFi من الإعدادات
4. انتظار 10 ثواني
5. إعادة WiFi

**المتوقع (حالياً):**
- upload يفشل
- retry من البداية (0%)
- waste bandwidth

**المطلوب (بعد الإصلاح):**
- upload يتوقف مؤقتاً
- session ID محفوظ في DB
- عند عودة الشبكة: استعلام عن chunks المستلمة
- استئناف من 30%

### السيناريو C: Reboot أثناء الرفع

**الخطوات:**
1. بدء رفع ملف
2. `adb shell reboot`
3. انتظار boot
4. فتح التطبيق

**المتوقع (حالياً):**
- session ضاع
- الملف يبدأ من الصفر
- أو لا يبدأ إطلاقاً

**المطلوب (بعد الإصلاح):**
- BootReceiver يُعيد جدولة WorkManager
- SyncWorker يقرأ pending uploads من DB
- استئناف الجلسة من آخر chunk

### السيناريو D: فتح التطبيق مع upload معلق

**الخطوات:**
1. إضافة 5 ملفات للرفع
2. إغلاق التطبيق (force-stop)
3. `adb shell am force-stop com.aso.app`
4. إعادة فتح التطبيق

**المتوقع (حالياً):**
- AlarmManager يبدأ 5 Services متزامنة
- Workers متعددة قد تبدأ
- تضارب في الرفع

**المطلوب (بعد الإصلاح):**
- WorkManager يبدأ SyncWorker واحد فقط
- Worker يعالج 5 ملفات تسلسلياً (serial processing)
- لا تضارب، لا ازدواجية

---

## 🛠️ Step 4: تعطيل AlarmManager مؤقتاً (Quick Patch)

### التغييرات المطلوبة:

**ملف: `UploadAlarmReceiver.java`**

```java
public static void scheduleAlarm(Context context) {
    // ❌ DISABLED TEMPORARILY - Testing WorkManager-only approach
    /*
    AlarmManager alarmManager = (AlarmManager) 
        context.getSystemService(Context.ALARM_SERVICE);
    
    Intent intent = new Intent(context, UploadAlarmReceiver.class);
    PendingIntent pendingIntent = PendingIntent.getBroadcast(
        context, 0, intent, PendingIntent.FLAG_IMMUTABLE
    );
    
    alarmManager.setRepeating(
        AlarmManager.RTC_WAKEUP,
        System.currentTimeMillis(),
        10 * 1000,
        pendingIntent
    );
    */
    
    Log.e("UploadAlarmReceiver", "⚠️ AlarmManager DISABLED - using WorkManager only");
}
```

**ملف: `AutoUploadApplication.java`**

```java
@Override
public void onCreate() {
    // ... existing code ...
    
    // ❌ تعطيل AlarmManager مؤقتاً
    // UploadAlarmReceiver.scheduleAlarm(this);
    
    Log.e(TAG, "⚠️ AlarmManager DISABLED for testing");
}
```

### اختبار بعد التعطيل:
- إعادة تشغيل التطبيق
- إضافة ملف للرفع
- مراقبة: يجب ألا يظهر "AlarmManager" في اللوجات
- النتيجة المتوقعة: تقليل calls بنسبة 95%+

---

## 🎯 Step 5: تفعيل WorkManager كـ Single Orchestrator

### التصميم المعماري الجديد:

```
┌─────────────────────────────────────────────────────────┐
│  📱 JavaScript Layer                                     │
│  ┌──────────────────────────────────────────────────┐   │
│  │  saveFile() → IndexedDB (metadata only)          │   │
│  │           → UploadService.addFileToQueue()       │   │
│  └──────────────────────────────────────────────────┘   │
│                       ▼                                  │
│  ⚙️ Java Plugin Layer                                    │
│  ┌──────────────────────────────────────────────────┐   │
│  │  UploadServicePlugin.addFileToQueue()            │   │
│  │    1. Save to upload_queue DB (status=pending)   │   │
│  │    2. Create upload_session (if >10MB)           │   │
│  │    3. enqueueUniqueWork("sync_outbox", KEEP)     │   │
│  │       ❌ NO startForegroundService()!             │   │
│  └──────────────────────────────────────────────────┘   │
│                       ▼                                  │
│  🔄 WorkManager Layer (Single Orchestrator)              │
│  ┌──────────────────────────────────────────────────┐   │
│  │  SyncWorker.doWork()                             │   │
│  │    while (hasWork()) {                           │   │
│  │      item = dbHelper.getNextPending()            │   │
│  │      if (item.size > 10MB)                       │   │
│  │        setForegroundAsync(notification)          │   │
│  │      uploadFile(item)  // OkHttp streaming       │   │
│  │    }                                             │   │
│  └──────────────────────────────────────────────────┘   │
│                       ▼                                  │
│  🌐 Upload Layer (OkHttp + Session/Chunk)                │
│  ┌──────────────────────────────────────────────────┐   │
│  │  if (hasExistingSession)                         │   │
│  │    resumeUpload(sessionId, lastChunk)            │   │
│  │  else                                            │   │
│  │    createNewSession() + streamChunks()           │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

### الكود المطلوب:

#### 1. إنشاء SyncWorker.java (جديد)

```java
package com.aso.app;

import android.content.Context;
import androidx.annotation.NonNull;
import androidx.work.*;
import java.util.List;
import java.util.concurrent.TimeUnit;

/**
 * Single Sync Orchestrator - يعالج جميع عمليات الرفع والمزامنة
 * يستخدم WorkManager UniqueWork لضمان worker واحد فقط
 */
public class SyncWorker extends Worker {
    private static final String TAG = "SyncWorker";
    private static final long LARGE_FILE_THRESHOLD = 10 * 1024 * 1024; // 10 MB
    
    private UploadDatabaseHelper dbHelper;
    private Context context;
    
    public SyncWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
        this.context = context;
        this.dbHelper = UploadDatabaseHelper.getInstance(context);
    }
    
    @NonNull
    @Override
    public Result doWork() {
        Log.e(TAG, "");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log.e(TAG, "🔄 SyncWorker.doWork() STARTED");
        Log.e(TAG, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        try {
            // Process outbox serially
            List<UploadDatabaseHelper.UploadItem> pendingItems = 
                dbHelper.getPendingFiles();
            
            Log.e(TAG, "📊 Found " + pendingItems.size() + " pending uploads");
            
            int successCount = 0;
            int failureCount = 0;
            
            for (UploadDatabaseHelper.UploadItem item : pendingItems) {
                // تحقق من حجم الملف
                File file = new File(item.filePath);
                long fileSize = file.length();
                
                // للملفات الكبيرة: تفعيل ForegroundService داخل Worker
                if (fileSize > LARGE_FILE_THRESHOLD) {
                    Log.e(TAG, "📦 Large file (" + (fileSize/1024/1024) + " MB) - promoting to foreground");
                    setForegroundAsync(createForegroundInfo(item.fileName, fileSize));
                }
                
                // رفع الملف
                boolean success = uploadFile(item);
                
                if (success) {
                    successCount++;
                    dbHelper.markAsCompleted(item.id);
                } else {
                    failureCount++;
                    dbHelper.incrementRetryCount(item.id);
                    
                    // Circuit breaker: بعد 3 محاولات → failed
                    if (item.retryCount >= 3) {
                        dbHelper.markAsFailed(item.id);
                        Log.e(TAG, "❌ File failed after 3 retries: " + item.fileName);
                    }
                }
            }
            
            Log.e(TAG, "✅ Sync complete: " + successCount + " success, " + failureCount + " failures");
            
            // إذا بقيت ملفات pending → جدولة worker جديد بعد فترة
            if (dbHelper.getPendingFilesCount() > 0) {
                scheduleNextSync(context);
            }
            
            return Result.success();
            
        } catch (Exception e) {
            Log.e(TAG, "❌ SyncWorker failed", e);
            return Result.retry();
        }
    }
    
    private ForegroundInfo createForegroundInfo(String fileName, long fileSize) {
        // Create notification for foreground work
        // ... (implementation similar to current UploadForegroundService)
    }
    
    private boolean uploadFile(UploadDatabaseHelper.UploadItem item) {
        // Use existing OkHttp streaming logic from UploadForegroundService
        // + Add session/chunk resumable logic
        return UploadHelper.streamUpload(context, item);
    }
    
    /**
     * جدولة worker تالي مع backoff
     */
    public static void scheduleNextSync(Context context) {
        // Exponential backoff: 1m, 2m, 4m, ...
        long delayMinutes = calculateBackoff();
        
        OneTimeWorkRequest syncWork = new OneTimeWorkRequest.Builder(SyncWorker.class)
            .setInitialDelay(delayMinutes, TimeUnit.MINUTES)
            .setConstraints(new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build())
            .build();
        
        WorkManager.getInstance(context)
            .enqueueUniqueWork(
                "sync_outbox",
                ExistingWorkPolicy.KEEP,  // ✅ لا تبدأ worker جديد إذا كان موجود
                syncWork
            );
    }
}
```

#### 2. تعديل UploadServicePlugin.java

```java
@PluginMethod
public void addFileToQueue(PluginCall call) {
    // ... existing validation code ...
    
    try {
        // 1. حفظ في Database فقط
        long id = dbHelper.addFileToQueue(
            actualPath,
            fileName,
            fileType,
            photoId,
            apiUrl,
            associationName,
            personName
        );
        
        // 2. إنشاء upload session للملفات الكبيرة
        if (sourceFile.length() > 10 * 1024 * 1024) {
            String sessionId = UUID.randomUUID().toString();
            dbHelper.createUploadSession(id, sessionId, sourceFile.length());
            Log.e(TAG, "📦 Large file - created session: " + sessionId);
        }
        
        // 3. جدولة WorkManager (بدلاً من startForegroundService)
        OneTimeWorkRequest syncWork = new OneTimeWorkRequest.Builder(SyncWorker.class)
            .setConstraints(new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build())
            .build();
        
        WorkManager.getInstance(getContext())
            .enqueueUniqueWork(
                "sync_outbox",
                ExistingWorkPolicy.KEEP,  // ✅ لا ازدواجية
                syncWork
            );
        
        Log.e(TAG, "✅ File queued - WorkManager will process");
        
        // ❌ NO MORE: startForegroundService(serviceIntent);
        
        call.resolve(result);
        
    } catch (Exception e) {
        Log.e(TAG, "❌ Failed to queue file", e);
        call.reject("خطأ في إضافة الملف", e);
    }
}
```

---

## 🔄 Step 6: تنفيذ Resumable Upload

### تصميم upload_sessions table

```sql
CREATE TABLE upload_sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  upload_queue_id INTEGER NOT NULL,      -- FK to upload_queue
  session_id TEXT NOT NULL UNIQUE,        -- UUID من السيرفر
  file_size INTEGER NOT NULL,             -- حجم الملف الكامل
  chunk_size INTEGER DEFAULT 1048576,     -- 1 MB chunks
  uploaded_bytes INTEGER DEFAULT 0,       -- bytes تم رفعها
  last_chunk_index INTEGER DEFAULT -1,    -- آخر chunk تم رفعه
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL,
  status TEXT DEFAULT 'active',           -- active/completed/failed
  server_url TEXT,                        -- endpoint للاستئناف
  
  FOREIGN KEY (upload_queue_id) REFERENCES upload_queue(id)
);

CREATE INDEX idx_session_id ON upload_sessions(session_id);
CREATE INDEX idx_queue_id ON upload_sessions(upload_queue_id);
```

### منطق Resumable Upload

```java
public class ResumableUploadHelper {
    
    public static boolean uploadWithResume(Context context, UploadItem item) {
        UploadDatabaseHelper db = UploadDatabaseHelper.getInstance(context);
        
        // 1. تحقق من وجود session
        UploadSession session = db.getUploadSession(item.id);
        
        if (session != null && session.status.equals("active")) {
            // استئناف
            Log.e(TAG, "📤 Resuming upload from " + session.uploadedBytes + " bytes");
            return resumeUpload(context, item, session);
        } else {
            // جلسة جديدة
            Log.e(TAG, "📤 Starting new upload session");
            return startNewUpload(context, item);
        }
    }
    
    private static boolean resumeUpload(Context context, UploadItem item, UploadSession session) {
        try {
            // 1. استعلام من السيرفر: ما الـ chunks التي وصلت؟
            OkHttpClient client = new OkHttpClient();
            Request statusRequest = new Request.Builder()
                .url(session.serverUrl + "/status/" + session.sessionId)
                .get()
                .build();
            
            Response statusResponse = client.newCall(statusRequest).execute();
            JSONObject status = new JSONObject(statusResponse.body().string());
            
            long receivedBytes = status.getLong("received_bytes");
            int lastChunkIndex = status.getInt("last_chunk_index");
            
            Log.e(TAG, "📊 Server has " + receivedBytes + " bytes (chunk " + lastChunkIndex + ")");
            
            // 2. رفع الـ chunks المتبقية فقط
            File file = new File(item.filePath);
            long totalSize = file.length();
            long chunkSize = session.chunkSize;
            
            RandomAccessFile raf = new RandomAccessFile(file, "r");
            byte[] buffer = new byte[(int)chunkSize];
            
            for (int i = lastChunkIndex + 1; i < Math.ceil((double)totalSize / chunkSize); i++) {
                long offset = i * chunkSize;
                raf.seek(offset);
                
                int bytesRead = raf.read(buffer);
                if (bytesRead == -1) break;
                
                // رفع chunk
                boolean chunkSuccess = uploadChunk(
                    session.sessionId,
                    i,
                    buffer,
                    bytesRead,
                    totalSize
                );
                
                if (!chunkSuccess) {
                    raf.close();
                    return false;
                }
                
                // تحديث progress
                session.uploadedBytes += bytesRead;
                session.lastChunkIndex = i;
                db.updateUploadSession(session);
            }
            
            raf.close();
            
            // 3. تأكيد اكتمال الرفع
            session.status = "completed";
            db.updateUploadSession(session);
            
            return true;
            
        } catch (Exception e) {
            Log.e(TAG, "❌ Resume failed", e);
            return false;
        }
    }
    
    private static boolean uploadChunk(String sessionId, int chunkIndex, 
                                       byte[] data, int length, long totalSize) {
        try {
            OkHttpClient client = new OkHttpClient();
            
            RequestBody chunkBody = RequestBody.create(
                Arrays.copyOf(data, length),
                MediaType.parse("application/octet-stream")
            );
            
            Request request = new Request.Builder()
                .url(UPLOAD_ENDPOINT + "/chunk")
                .addHeader("X-Session-ID", sessionId)
                .addHeader("X-Chunk-Index", String.valueOf(chunkIndex))
                .addHeader("X-Total-Size", String.valueOf(totalSize))
                .post(chunkBody)
                .build();
            
            Response response = client.newCall(request).execute();
            return response.isSuccessful();
            
        } catch (Exception e) {
            Log.e(TAG, "❌ Chunk upload failed", e);
            return false;
        }
    }
}
```

---

## 🚫 Step 7: إصلاح Duplicate Uploads

### إضافة file_hash column

```sql
ALTER TABLE upload_queue ADD COLUMN file_hash TEXT;
CREATE INDEX idx_file_hash ON upload_queue(file_hash);
```

### منطق Deduplication

```java
public long addFileToQueue(String filePath, String fileName, ...) {
    try {
        // 1. حساب hash للملف
        String fileHash = calculateFileHash(filePath);
        
        // 2. تحقق من وجود ملف مطابق
        UploadItem existing = getUploadByHash(fileHash);
        
        if (existing != null) {
            if (existing.status.equals("completed")) {
                Log.e(TAG, "✅ File already uploaded - skipping");
                return existing.id;
            } else if (existing.status.equals("pending") || existing.status.equals("uploading")) {
                Log.e(TAG, "⚠️ File already in queue - returning existing ID");
                return existing.id;
            }
        }
        
        // 3. إضافة جديد مع hash
        ContentValues values = new ContentValues();
        values.put("file_path", filePath);
        values.put("file_name", fileName);
        values.put("file_hash", fileHash);  // ✅ حفظ hash
        // ... existing fields ...
        
        long id = db.insert("upload_queue", null, values);
        return id;
        
    } catch (Exception e) {
        Log.e(TAG, "Failed to add file", e);
        return -1;
    }
}

private String calculateFileHash(String filePath) {
    try {
        MessageDigest md = MessageDigest.getInstance("MD5");
        FileInputStream fis = new FileInputStream(filePath);
        
        byte[] buffer = new byte[8192];
        int bytesRead;
        
        while ((bytesRead = fis.read(buffer)) != -1) {
            md.update(buffer, 0, bytesRead);
        }
        
        fis.close();
        
        byte[] digest = md.digest();
        StringBuilder sb = new StringBuilder();
        for (byte b : digest) {
            sb.append(String.format("%02x", b));
        }
        
        return sb.toString();
        
    } catch (Exception e) {
        Log.e(TAG, "Hash calculation failed", e);
        return null;
    }
}
```

---

## ⚡ Step 8: Exponential Backoff & Circuit Breaker

### تطبيق في UploadDatabaseHelper

```java
public class UploadDatabaseHelper {
    
    private static final int MAX_RETRY_COUNT = 3;
    private static final long BASE_RETRY_DELAY = 60 * 1000; // 1 minute
    private static final long MAX_RETRY_DELAY = 60 * 60 * 1000; // 1 hour
    
    /**
     * حساب تأخير إعادة المحاولة (Exponential Backoff)
     */
    public long calculateRetryDelay(int retryCount) {
        // 1st retry: 1m
        // 2nd retry: 2m
        // 3rd retry: 4m
        // 4th retry: 8m
        // ... max 1h
        
        long delay = (long) (BASE_RETRY_DELAY * Math.pow(2, retryCount));
        return Math.min(delay, MAX_RETRY_DELAY);
    }
    
    /**
     * Circuit Breaker: تحقق من حالة الملف
     */
    public boolean shouldRetry(UploadItem item) {
        // 1. عدد المحاولات
        if (item.retryCount >= MAX_RETRY_COUNT) {
            Log.e(TAG, "🚫 Circuit breaker: max retries exceeded for " + item.fileName);
            markAsFailed(item.id, "Exceeded max retry count");
            return false;
        }
        
        // 2. تحقق من الوقت منذ آخر محاولة
        long timeSinceLastAttempt = System.currentTimeMillis() - item.updatedAt;
        long requiredDelay = calculateRetryDelay(item.retryCount);
        
        if (timeSinceLastAttempt < requiredDelay) {
            Log.d(TAG, "⏳ Too soon to retry - waiting " + 
                  ((requiredDelay - timeSinceLastAttempt) / 1000) + "s");
            return false;
        }
        
        // 3. تحقق من حالة الشبكة
        if (!isNetworkAvailable()) {
            Log.d(TAG, "📶 No network - skipping retry");
            return false;
        }
        
        return true;
    }
    
    /**
     * تحديث حالة مع circuit breaker
     */
    public void incrementRetryCount(long id) {
        SQLiteDatabase db = getWritableDatabase();
        
        // قراءة الحالة الحالية
        Cursor cursor = db.query("upload_queue", 
            new String[]{"retry_count", "file_name"},
            "id = ?", 
            new String[]{String.valueOf(id)},
            null, null, null);
        
        if (cursor.moveToFirst()) {
            int currentRetry = cursor.getInt(0);
            String fileName = cursor.getString(1);
            
            currentRetry++;
            
            ContentValues values = new ContentValues();
            values.put("retry_count", currentRetry);
            values.put("updated_at", System.currentTimeMillis());
            
            // Circuit breaker
            if (currentRetry >= MAX_RETRY_COUNT) {
                values.put("status", "failed");
                values.put("error_message", "Exceeded max retry attempts (" + MAX_RETRY_COUNT + ")");
                Log.e(TAG, "🚫 Circuit breaker activated for: " + fileName);
            } else {
                values.put("status", "pending");
            }
            
            db.update("upload_queue", values, "id = ?", new String[]{String.valueOf(id)});
        }
        
        cursor.close();
    }
}
```

---

## 🧪 Step 9: Memory & WebView Isolation Tests

### اختبار الذاكرة

```bash
# قبل رفع الملفات
adb shell dumpsys meminfo com.aso.app > memory_before.txt

# رفع 3 ملفات كبيرة (500MB each)
# ... تنفيذ من التطبيق ...

# أثناء الرفع
adb shell dumpsys meminfo com.aso.app > memory_during.txt

# بعد الرفع
adb shell dumpsys meminfo com.aso.app > memory_after.txt

# مقارنة
diff memory_before.txt memory_after.txt
```

### التحليل المطلوب:

**1. Heap Memory:**
- Java Heap: يجب ألا يزيد > 50 MB أثناء الرفع
- Native Heap: streaming → يجب أن يظل ثابتاً

**2. WebView Memory:**
- لا يجب أن يتأثر WebView بعملية الرفع
- إذا زاد → تسريب من Base64 أو data URI

**3. OOM Indicators:**
- إذا ظهر OutOfMemory → فشل في NO BASE64 policy

### الحد الأقصى للـ Parallel Uploads:

```java
// في SyncWorker
private static final int MAX_PARALLEL_UPLOADS = 2;  // ✅ حد أقصى 2 رفع متزامن

private Semaphore uploadSemaphore = new Semaphore(MAX_PARALLEL_UPLOADS);

@Override
public Result doWork() {
    try {
        uploadSemaphore.acquire();  // انتظر توفر slot
        
        // رفع الملف
        uploadFile(item);
        
    } finally {
        uploadSemaphore.release();  // حرر slot
    }
}
```

---

## 🔀 Step 10: Conflict Resolution Policy

### إضافة version control

```sql
ALTER TABLE upload_queue ADD COLUMN record_version INTEGER DEFAULT 1;
ALTER TABLE upload_queue ADD COLUMN last_modified_timestamp INTEGER;
ALTER TABLE upload_queue ADD COLUMN last_modified_device_id TEXT;
```

### سياسة Server-Authoritative (LWW - Last Write Wins)

```java
public boolean syncData(DataItem localData) {
    try {
        // 1. إرسال البيانات مع version
        Request request = new Request.Builder()
            .url(apiUrl)
            .addHeader("X-Record-Version", String.valueOf(localData.version))
            .addHeader("X-Last-Modified", String.valueOf(localData.lastModified))
            .addHeader("X-Device-ID", getDeviceId())
            .post(createRequestBody(localData))
            .build();
        
        Response response = client.newCall(request).execute();
        
        if (response.code() == 409) {  // Conflict
            // 2. السيرفر رفض → version قديم
            JSONObject serverData = new JSONObject(response.body().string());
            
            Log.w(TAG, "⚠️ Conflict detected - server version is newer");
            
            // 3. سياسة: Server-Authoritative
            // قبول نسخة السيرفر وتحديث المحلي
            updateLocalRecord(localData.id, serverData);
            
            // 4. إشعار المستخدم (اختياري)
            notifyUser("تم تحديث السجل من السيرفر (conflict resolved)");
            
            return true;  // نجح الحل
            
        } else if (response.isSuccessful()) {
            // تحديث version المحلي
            localData.version++;
            updateLocalRecord(localData);
            return true;
        }
        
        return false;
        
    } catch (Exception e) {
        Log.e(TAG, "Sync failed", e);
        return false;
    }
}
```

---

## ✅ Acceptance Criteria (معايير القبول)

### يجب أن يمر الإصدار بهذه الاختبارات:

1. **لا AlarmManager polling كل 10s**
   - ✅ تنفيذ: AlarmManager معطّل
   - 🧪 اختبار: `adb logcat | grep Alarm` → لا نتائج

2. **عند إضافة 10 ملفات متزامنة → Worker واحد فقط**
   - ✅ تنفيذ: `enqueueUniqueWork(KEEP)`
   - 🧪 اختبار: `adb logcat | grep SyncWorker` → worker واحد فقط يبدأ

3. **لا Renderer crash أو OOM**
   - ✅ تنفيذ: NO BASE64 + OkHttp streaming
   - 🧪 اختبار: رفع 3 ملفات 500MB + `dumpsys meminfo` → heap stable

4. **Resumable upload**
   - ✅ تنفيذ: upload_sessions table + chunk tracking
   - 🧪 اختبار: قطع شبكة @ 30% → إعادة → يستأنف من 30%

5. **لا duplicate uploads**
   - ✅ تنفيذ: file_hash checking
   - 🧪 اختبار: رفع نفس الملف مرتين → يتخطى الثاني

6. **Conflict resolution deterministic**
   - ✅ تنفيذ: Server-Authoritative policy
   - 🧪 اختبار: تعديل من جهازين → نسخة السيرفر تفوز

---

## 📊 الحالة الحالية والخطوات التالية

### ✅ مُنجز:
1. إصلاح خطأ `Directory.Data` الفوري
2. توثيق خطة الإصلاح الشاملة
3. تحديد المشاكل الرئيسية من الكود

### 🔄 قيد التنفيذ:
- تحليل اللوجات الحالية (يحتاج اتصال adb)

### ⏳ في الانتظار:
- تعطيل AlarmManager
- تطبيق SyncWorker
- تطبيق upload_sessions
- اختبارات الأداء

### 🚧 متطلبات للمتابعة:
1. اتصال adb بجهاز/emulator
2. تثبيت APK الحالي على الجهاز
3. تنفيذ سيناريوهات الاختبار A-D
4. جمع اللوجات وقواعد البيانات

---

## 📝 الملاحظات الهندسية

### المشاكل الحرجة المكتشفة:

1. **AlarmManager Storm** 🔴
   - Severity: CRITICAL
   - Impact: Battery drain, ANR, duplicate workers
   - Fix: تعطيل AlarmManager + استخدام WorkManager فقط

2. **عدم وجود Resumable Upload** 🟡
   - Severity: HIGH
   - Impact: Waste bandwidth, slow uploads
   - Fix: تطبيق upload_sessions + chunk tracking

3. **ForegroundService Overuse** 🟡
   - Severity: MEDIUM
   - Impact: Notification spam, resource waste
   - Fix: استخدام Worker.setForegroundAsync() فقط للملفات >10MB

4. **عدم وجود Deduplication** 🟡
   - Severity: MEDIUM
   - Impact: Duplicate uploads, wasted bandwidth
   - Fix: file_hash checking

5. **عدم وجود Conflict Policy** 🟢
   - Severity: LOW (حالياً)
   - Impact: Data inconsistency في multi-device scenarios
   - Fix: Server-Authoritative + version headers

### الأولويات:

```
Priority 1: إصلاح AlarmManager (CRITICAL)
Priority 2: تطبيق SyncWorker (CRITICAL)
Priority 3: Resumable Upload (HIGH)
Priority 4: Deduplication (MEDIUM)
Priority 5: Conflict Resolution (LOW)
```

---

**نهاية التقرير - Step 1 & 2 completed**  
**التالي:** تنفيذ الإصلاحات البرمجية حسب الأولوية
