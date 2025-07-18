# 🚀 دليل تكامل Speedtest CLI مع Laravel

## 📋 نظرة عامة

تم تطوير نظام متكامل لقياس سرعة الإنترنت في مشروع Laravel باستخدام أداة **Speedtest CLI** من Ookla. النظام يقوم بقياس سرعة الإنترنت الحقيقية أولاً، وفي حالة عدم توفر الأداة أو فشل القياس، يتم استخدام نظام محاكاة محلي كبديل.

## 🎯 المميزات

- ✅ قياس سرعة حقيقية باستخدام Speedtest CLI من Ookla
- ✅ نظام احتياطي (Fallback) في حالة فشل القياس الحقيقي
- ✅ واجهة مستخدم متقدمة مع عدادات دائرية وأشرطة تقدم
- ✅ عرض معلومات مفصلة (الخادم، المدينة، مقدم الخدمة)
- ✅ تصميم متجاوب مع الأجهزة المحمولة
- ✅ معالجة شاملة للأخطاء
- ✅ دعم كامل للغة العربية

## 📁 الملفات المضافة/المحدثة

### 1. Controller جديد
```
app/Http/Controllers/SpeedTestController.php
```
- `run()` - تشغيل قياس السرعة الحقيقي
- `checkAvailability()` - فحص توفر أداة Speedtest CLI

### 2. Routes جديدة
```
routes/web.php
```
- `GET /api/speedtest` - تشغيل قياس السرعة
- `GET /api/speedtest/check` - فحص توفر الأداة

### 3. Frontend محدث
```
resources/views/file-management/advanced-interface.blade.php
```
- JavaScript محسن لاستخدام API الحقيقي
- نظام احتياطي للمحاكاة
- واجهة مستخدم محسنة

### 4. ملف التثبيت
```
install-speedtest.sh
```
- سكريبت تثبيت تلقائي لأداة Speedtest CLI

## 🔧 تعليمات التثبيت

### 1. تثبيت Speedtest CLI

#### على Linux (Ubuntu/Debian):
```bash
curl -s https://install.speedtest.net/app/cli/install.sh | sudo bash
```

#### على CentOS/RHEL:
```bash
curl -s https://install.speedtest.net/app/cli/install.sh | sudo bash
```

#### على macOS:
```bash
brew tap teamookla/speedtest
brew install speedtest --force
```

#### على Windows:
```powershell
choco install speedtest
```

### 2. استخدام سكريبت التثبيت المرفق:
```bash
chmod +x install-speedtest.sh
sudo ./install-speedtest.sh
```

### 3. التحقق من التثبيت:
```bash
speedtest --version
```

## 🚀 الاستخدام

### 1. تشغيل قياس السرعة:
```javascript
// في المتصفح
fetch('/api/speedtest')
  .then(response => response.json())
  .then(data => {
    console.log('Download:', data.download_mbps, 'Mbps');
    console.log('Upload:', data.upload_mbps, 'Mbps');
    console.log('Ping:', data.ping_ms, 'ms');
  });
```

### 2. فحص توفر الأداة:
```javascript
fetch('/api/speedtest/check')
  .then(response => response.json())
  .then(data => {
    if (data.available) {
      console.log('Speedtest CLI متوفر');
    } else {
      console.log('سيتم استخدام النظام الاحتياطي');
    }
  });
```

## 📊 مثال على الاستجابة

### نجاح القياس:
```json
{
  "success": true,
  "download_mbps": 46.25,
  "upload_mbps": 11.78,
  "ping_ms": 18.5,
  "server": "Telecom Egypt",
  "location": "Cairo, Egypt",
  "timestamp": "2025-07-17T08:00:00Z",
  "isp": "TE Data",
  "raw_data": { ... }
}
```

### فشل القياس (مع البيانات الاحتياطية):
```json
{
  "success": false,
  "error": "Speed test failed",
  "message": "Command not found",
  "fallback_data": {
    "download_mbps": 35.2,
    "upload_mbps": 8.5,
    "ping_ms": 25,
    "server": "Fallback Server",
    "location": "Cairo, Egypt",
    "timestamp": "2025-07-17T08:00:00Z",
    "isp": "Unknown ISP"
  }
}
```

## 🎨 واجهة المستخدم

### العناصر الرئيسية:
- **كارتان منفصلتان**: واحدة للتنزيل وأخرى للرفع
- **عدادات دائرية**: لعرض السرعة بصرياً
- **أشرطة تقدم**: لتتبع حالة القياس
- **معلومات مفصلة**: الخادم، المدينة، مقدم الخدمة

### الألوان والتصميم:
- **تدرج أزرق**: لكارت التنزيل
- **تدرج أخضر**: لكارت الرفع
- **نصوص بيضاء**: لتحسين الوضوح
- **تصميم متجاوب**: يتكيف مع جميع الأجهزة

## 🔒 الأمان والأداء

### الأمان:
- ✅ تحقق من صحة المدخلات
- ✅ معالجة أخطاء النظام
- ✅ timeout للأوامر (120 ثانية)
- ✅ حماية من هجمات الحقن

### الأداء:
- ✅ تخزين مؤقت للنتائج
- ✅ تشغيل غير متزامن
- ✅ معالجة الأخطاء المرنة
- ✅ نظام احتياطي سريع

## 🛠️ استكشاف الأخطاء

### الأخطاء الشائعة:

#### 1. "Command not found"
```bash
# تأكد من تثبيت الأداة
which speedtest
# أو
speedtest --version
```

#### 2. "Permission denied"
```bash
# تأكد من صلاحيات التشغيل
sudo chmod +x /usr/bin/speedtest
```

#### 3. "Process timeout"
```bash
# تحقق من الاتصال بالإنترنت
ping google.com
```

#### 4. "Server not found"
```bash
# تشغيل يدوي للتحقق
speedtest --format=json
```

## 🔧 تخصيص الإعدادات

### في SpeedTestController.php:
```php
// تغيير timeout
$process->setTimeout(180); // 3 دقائق

// تخصيص الخادم
$process = new Process(['speedtest', '--server-id=12345', '--format=json']);
```

### في JavaScript:
```javascript
// تخصيص فترة التحديث
const updateInterval = 100; // milliseconds

// تخصيص قيم الاحتياطي
const fallbackDownload = 30; // Mbps
const fallbackUpload = 10; // Mbps
```

## 📱 الدعم للأجهزة المحمولة

- ✅ تصميم متجاوب بالكامل
- ✅ أزرار مناسبة للمس
- ✅ نصوص قابلة للقراءة
- ✅ تحسين للشاشات الصغيرة

## 🌐 الدعم الدولي

- ✅ واجهة باللغة العربية
- ✅ دعم RTL
- ✅ تنسيق الأرقام العربية
- ✅ رسائل خطأ مترجمة

## 🚀 التطوير المستقبلي

### مميزات مقترحة:
- [ ] حفظ تاريخ القياسات
- [ ] مقارنة السرعات عبر الوقت
- [ ] إشعارات عند انخفاض السرعة
- [ ] تقارير مفصلة
- [ ] API للتطبيقات الخارجية

## 📞 الدعم الفني

لأي مشاكل أو استفسارات:
1. تحقق من logs Laravel: `storage/logs/laravel.log`
2. اختبر الأداة يدوياً: `speedtest --format=json`
3. تحقق من الصلاحيات والمسارات
4. راجع console المتصفح للأخطاء JavaScript

---

**تم تطوير هذا النظام لتوفير تجربة مستخدم متميزة وموثوقة لقياس سرعة الإنترنت في تطبيقات Laravel** 🎉
