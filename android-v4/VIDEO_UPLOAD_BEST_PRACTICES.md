# 🎥 دليل رفع الفيديوهات الكبيرة - أفضل الممارسات

## ⚡ نصائح سريعة

### ✅ افعل:
- استخدم جودة 720p للفيديوهات الطويلة
- اضغط الفيديو قبل الرفع إذا كان أكبر من 100 MB
- تأكد من وجود اتصال إنترنت مستقر
- احفظ الفيديو كملف فيزيائي (file://) وليس Base64

### ❌ لا تفعل:
- لا ترفع فيديوهات بجودة 4K (ستكون كبيرة جداً)
- لا تستخدم Base64 للفيديوهات الكبيرة (> 10 MB)
- لا تغلق التطبيق أثناء الرفع
- لا ترفع عدة فيديوهات كبيرة في نفس الوقت

---

## 📊 الأحجام الموصى بها

| **النوع** | **الحجم الأقصى** | **الملاحظات** |
|-----------|------------------|---------------|
| صورة | 5 MB | يمكن استخدام Base64 |
| فيديو قصير (< 30 ث) | 20 MB | استخدم 720p |
| فيديو متوسط (1-3 د) | 50 MB | استخدم 720p |
| فيديو طويل (> 3 د) | 100 MB | اضغط إلى 720p |
| فيديو كبير جداً | 200 MB | اضغط بشكل قوي |

---

## 🔧 ضغط الفيديو (Recommended)

### الطريقة 1: استخدام JavaScript (في التطبيق)

```javascript
/**
 * ضغط فيديو قبل الرفع
 */
async function compressVideo(videoFile) {
  // استخدم مكتبة ضغط الفيديو
  const compressor = new VideoCompressor({
    quality: 0.7,  // 70% جودة
    maxWidth: 1280,   // 720p
    maxHeight: 720,
    bitrate: 1000000  // 1 Mbps
  });
  
  const compressed = await compressor.compress(videoFile);
  console.log('Original:', formatSize(videoFile.size));
  console.log('Compressed:', formatSize(compressed.size));
  
  return compressed;
}
```

### الطريقة 2: ضبط إعدادات الكاميرا

```javascript
// عند فتح الكاميرا للتصوير
const constraints = {
  video: {
    width: { ideal: 1280 },
    height: { ideal: 720 },
    facingMode: 'environment',
    frameRate: { ideal: 30 }  // 30 fps
  }
};

const stream = await navigator.mediaDevices.getUserMedia(constraints);
```

### الطريقة 3: استخدام FFmpeg (على السيرفر)

إذا كان الفيديو كبيراً جداً، يمكن ضغطه على السيرفر:

```bash
ffmpeg -i input.mp4 \
  -c:v libx264 \
  -crf 28 \
  -preset fast \
  -vf "scale=1280:720" \
  -c:a aac \
  -b:a 128k \
  output.mp4
```

---

## 📱 مراقبة حجم الملف

```javascript
/**
 * تحقق من حجم الملف قبل الرفع
 */
function checkFileSize(file) {
  const maxSize = 100 * 1024 * 1024; // 100 MB
  
  if (file.size > maxSize) {
    alert(`الملف كبير جداً: ${formatSize(file.size)}\n` +
          `الحد الأقصى: ${formatSize(maxSize)}\n` +
          `يرجى ضغط الفيديو أولاً.`);
    return false;
  }
  
  return true;
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
  if (bytes < 1024 * 1024 * 1024) return (bytes / 1024 / 1024).toFixed(2) + ' MB';
  return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
}
```

---

## 🎯 التعامل مع الأخطاء

### خطأ OOM (نفاد الذاكرة)

```javascript
try {
  await uploadFile(largeVideo);
} catch (error) {
  if (error.message.includes('OutOfMemoryError')) {
    alert('⚠️ الفيديو كبير جداً!\n\n' +
          'حلول:\n' +
          '1. اضغط الفيديو إلى 720p\n' +
          '2. قسّم الفيديو إلى أجزاء أصغر\n' +
          '3. أغلق التطبيقات الأخرى لتحرير الذاكرة');
  }
}
```

---

## 📈 عرض التقدم

```javascript
/**
 * عرض تقدم رفع الفيديو
 */
function uploadWithProgress(file) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        updateProgressBar(percent);
        console.log(`📤 تقدم: ${percent}%`);
      }
    });
    
    xhr.addEventListener('load', () => {
      if (xhr.status === 200) {
        resolve(xhr.response);
      } else {
        reject(new Error('Upload failed'));
      }
    });
    
    xhr.open('POST', '/upload');
    xhr.send(file);
  });
}
```

---

## 🔍 التشخيص

### معرفة سبب الفشل

```javascript
// أضف هذا في catch block
catch (error) {
  console.error('Upload Error Details:', {
    fileName: file.name,
    fileSize: formatSize(file.size),
    fileType: file.type,
    timestamp: new Date().toISOString(),
    errorMessage: error.message,
    errorStack: error.stack
  });
  
  // أرسل للسيرفر للتحليل
  logErrorToServer({
    error: error.message,
    fileSize: file.size,
    deviceInfo: getDeviceInfo()
  });
}

function getDeviceInfo() {
  return {
    platform: navigator.platform,
    userAgent: navigator.userAgent,
    memory: navigator.deviceMemory || 'unknown',
    connection: navigator.connection?.effectiveType || 'unknown'
  };
}
```

---

## 🛠️ حلول متقدمة

### 1. تقسيم الملف (Chunked Upload)

للملفات الكبيرة جداً (> 200 MB):

```javascript
async function uploadInChunks(file, chunkSize = 5 * 1024 * 1024) { // 5 MB chunks
  const totalChunks = Math.ceil(file.size / chunkSize);
  
  for (let i = 0; i < totalChunks; i++) {
    const start = i * chunkSize;
    const end = Math.min(start + chunkSize, file.size);
    const chunk = file.slice(start, end);
    
    await uploadChunk(chunk, i, totalChunks);
    console.log(`Uploaded chunk ${i + 1}/${totalChunks}`);
  }
}
```

### 2. استئناف الرفع (Resume Upload)

حفظ التقدم للمتابعة لاحقاً:

```javascript
async function uploadWithResume(file) {
  const uploadId = `upload_${Date.now()}`;
  let uploadedBytes = getUploadProgress(uploadId) || 0;
  
  // استئناف من حيث توقفنا
  const remainingFile = file.slice(uploadedBytes);
  
  try {
    await upload(remainingFile);
    clearUploadProgress(uploadId);
  } catch (error) {
    saveUploadProgress(uploadId, uploadedBytes + transferred);
    throw error;
  }
}
```

---

## 📚 مكتبات موصى بها

### للضغط:
- **video-compressor.js** - ضغط فيديو في المتصفح
- **ffmpeg.js** - FFmpeg في JavaScript
- **compressorjs** - ضغط صور وفيديوهات

### للرفع:
- **axios** - HTTP client مع progress tracking
- **resumablejs** - رفع قابل للاستئناف
- **tus-js-client** - بروتوكول رفع قوي

---

## 🎓 أمثلة عملية

### مثال كامل: رفع فيديو مع جميع التحسينات

```javascript
async function uploadVideoOptimized(videoFile) {
  try {
    // 1. التحقق من الحجم
    if (!checkFileSize(videoFile)) {
      throw new Error('File too large');
    }
    
    // 2. ضغط إذا لزم الأمر
    let fileToUpload = videoFile;
    if (videoFile.size > 50 * 1024 * 1024) {
      console.log('🔧 ضغط الفيديو...');
      fileToUpload = await compressVideo(videoFile);
    }
    
    // 3. حفظ فيزيائياً (لا Base64)
    const savedPath = await saveToFileSystem(fileToUpload);
    
    // 4. رفع مع تتبع التقدم
    await uploadWithProgress({
      filePath: savedPath,
      fileName: videoFile.name,
      fileType: videoFile.type
    });
    
    console.log('✅ تم الرفع بنجاح!');
    
  } catch (error) {
    console.error('❌ فشل الرفع:', error);
    handleUploadError(error);
  }
}
```

---

## ⚙️ الإعدادات المثالية

### للكاميرا:
```javascript
const camSettings = {
  resolution: '1280x720',  // 720p
  frameRate: 30,
  bitrate: 2000000,        // 2 Mbps
  codec: 'h264'
};
```

### للرفع:
```javascript
const uploadSettings = {
  chunkSize: 8192,         // 8 KB
  timeout: 300000,         // 5 min
  retries: 3,
  retryDelay: 2000         // 2 sec
};
```

---

## 🎉 النتيجة النهائية

باتباع هذه الإرشادات:
- ✅ رفع أسرع (ملفات أصغر)
- ✅ استقرار أفضل (لا OOM)
- ✅ تجربة مستخدم محسّنة (تقدم واضح)
- ✅ توفير البيانات (ضغط فعّال)

---

**تاريخ التحديث**: 12 فبراير 2026
**الإصدار**: v1.0
