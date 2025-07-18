# LibreSpeed Integration with Laravel

## نظام قياس سرعة الإنترنت المتكامل

تم تطوير نظام قياس سرعة الإنترنت باستخدام أداة LibreSpeed مفتوحة المصدر مع تكامل كامل مع Laravel.

## المميزات

### ✅ المميزات المحققة:
1. **تحميل LibreSpeed من المستودع الرسمي**: تم تحميل أحدث إصدار من https://github.com/librespeed/speedtest
2. **تشغيل الواجهة الكاملة**: تدعم اختبار Download, Upload, Ping, Jitter
3. **العمل دون API خارجي**: يعمل محلياً دون الحاجة لخدمات خارجية
4. **النسخة المستقلة**: HTML + JS + PHP بدون Node.js
5. **ملفات PHP مفعلة**: getIP.php, empty.php, garbage.php
6. **لا يستخدم خدمات مدفوعة**: مجاني تماماً
7. **العمل من السيرفر المحلي**: يعمل مع Laravel backend
8. **حماية بتسجيل الدخول**: متاح فقط للمسؤولين المسجلين

## البنية التقنية

### الملفات المضافة:
1. **Controller**: `app/Http/Controllers/Admin/SpeedTestController.php`
2. **Views**: 
   - `resources/views/admin/speedtest/index.blade.php` (الواجهة الرئيسية)
   - `resources/views/admin/speedtest/standalone.blade.php` (الواجهة المستقلة)
3. **Assets**:
   - `public/speedtest/` (ملفات LibreSpeed الأصلية)
   - `public/speedtest/index-laravel.html` (واجهة محسنة للعمل مع Laravel)
   - `public/js/real-speed-test-libre.js` (تكامل JavaScript)

### Routes المضافة:
```php
// في admin.php
Route::get('speedtest', [SpeedTestController::class, 'index'])->name('admin.speedtest.index');
Route::get('speedtest/standalone', function() {
    return view('admin.speedtest.standalone');
})->name('admin.speedtest.standalone');
Route::match(['get', 'post'], 'speedtest/api', [SpeedTestController::class, 'api'])->name('admin.speedtest.api');
Route::get('speedtest/stats', [SpeedTestController::class, 'stats'])->name('admin.speedtest.stats');
```

## كيفية الاستخدام

### الوصول للنظام:
1. قم بتسجيل الدخول كمسؤول
2. اذهب إلى "أدوات النظام" > "اختبار سرعة الإنترنت"
3. أو استخدم الرابط المباشر: `/admin/speedtest`

### الواجهات المتاحة:
1. **الواجهة الرئيسية**: تعرض LibreSpeed داخل iframe
2. **الواجهة المستقلة**: واجهة مخصصة مع تصميم عربي محسن

## التكامل مع Laravel

### API Endpoints:
- `GET/POST /admin/speedtest/api?endpoint=empty` - اختبار Ping/Upload
- `GET /admin/speedtest/api?endpoint=garbage&ckSize=X` - اختبار Download
- `GET /admin/speedtest/api?endpoint=getIP` - الحصول على معلومات IP
- `GET /admin/speedtest/stats` - حالة النظام

### الأمان:
- محمي بـ middleware للمسؤولين فقط
- CSRF protection مع استثناءات مناسبة
- لا يحتاج لـ API keys خارجية

## الاختبارات المدعومة

### 1. اختبار التحميل (Download Test):
- يقيس سرعة تحميل البيانات
- يستخدم أحجام مختلفة من البيانات
- نتائج بالـ Mbps

### 2. اختبار الرفع (Upload Test):
- يقيس سرعة رفع البيانات
- يستخدم FormData
- نتائج بالـ Mbps

### 3. اختبار Ping:
- يقيس زمن الاستجابة
- 10 اختبارات متتالية
- نتائج بالـ ms

### 4. اختبار Jitter:
- يقيس تذبذب الاتصال
- محسوب من نتائج Ping
- نتائج بالـ ms

## التحسينات المضافة

### واجهة المستخدم:
- تصميم responsive يدعم الجوال
- الدعم الكامل للغة العربية
- تصميم Bootstrap محسن
- رسوم بيانية وإحصائيات

### الأداء:
- تحميل غير متزامن
- معالجة للأخطاء
- تقارير مفصلة
- تكامل مع نظام إدارة الملفات

## الملفات المهمة

### LibreSpeed الأصلية:
```
public/speedtest/
├── backend/
│   ├── empty.php
│   ├── garbage.php
│   └── getIP.php
├── index.html
├── speedtest.js
└── speedtest_worker.js
```

### تخصيصات Laravel:
```
public/speedtest/
├── index-laravel.html (واجهة محسنة)
public/js/
├── real-speed-test-libre.js (تكامل JavaScript)
```

## التشغيل والاختبار

### تشغيل اختبار كامل:
```javascript
// في وحدة التحكم
window.realSpeedTestLibre.runFullTest().then(results => {
    console.log('نتائج الاختبار:', results);
});
```

### اختبار منفرد:
```javascript
// اختبار التحميل فقط
window.realSpeedTestLibre.testDownloadSpeed();

// اختبار الرفع فقط
window.realSpeedTestLibre.testUploadSpeed();

// اختبار Ping فقط
window.realSpeedTestLibre.testPing();
```

## المتطلبات

### متطلبات الخادم:
- PHP 7.4+ (متوفر مع Laravel)
- Laravel 8+ (متوفر)
- إذن كتابة في `public/`

### متطلبات المتصفح:
- متصفح حديث يدعم Fetch API
- JavaScript مفعل
- لا يحتاج Flash أو إضافات

## الدعم والصيانة

### مراقبة الأداء:
- فحص حالة النظام في `/admin/speedtest/stats`
- لوق الأخطاء في وحدة التحكم
- تقارير مفصلة للاختبارات

### استكشاف الأخطاء:
1. تحقق من وجود ملفات LibreSpeed
2. تأكد من صحة routes
3. فحص CSRF tokens
4. تحقق من أذونات الملفات

## التحديثات المستقبلية

### مخطط للتحسينات:
1. حفظ نتائج الاختبارات في قاعدة البيانات
2. إحصائيات تاريخية
3. تنبيهات عند انخفاض السرعة
4. تصدير التقارير
5. مقارنة الأداء بمرور الوقت

---

## الخلاصة

تم تطوير نظام قياس سرعة إنترنت متكامل وآمن يعمل محلياً مع Laravel، يوفر قياسات دقيقة ومفصلة لسرعة الاتصال دون الحاجة لأي خدمات خارجية مدفوعة.

النظام جاهز للاستخدام ومحمي بتسجيل الدخول، ويمكن الوصول إليه من القائمة الجانبية في لوحة التحكم.
