# 🧪 دليل الاختبار السريع - Quick Testing Guide

## 🎯 اختبار الإصلاحات على جهازك

### **1. بناء التطبيق**

```powershell
cd "I:\unit test\alhayahorphans\ASO - Copy\android"
./gradlew clean
./gradlew assembleDebug
```

**النتيجة المتوقعة:**
```
BUILD SUCCESSFUL in ~42s
```

**مكان الملف:**
```
android\app\build\outputs\apk\debug\app-debug.apk
```

---

### **2. تثبيت على الهاتف**

**الطريقة 1: عبر USB**
```powershell
adb install app-debug.apk
```

**الطريقة 2: نقل الملف للهاتف**
- انسخ `app-debug.apk` للهاتف
- افتحه وثبّته يدوياً

---

### **3. اختبارات الصلاحيات**

#### **أول فتح للتطبيق:**

✅ **يجب أن يظهر:**
1. طلب صلاحية **الإشعارات** (Android 13+)
2. طلب صلاحية **المنبهات الدقيقة** (Android 12+)
3. طلب تعطيل **Battery Optimization**

**👉 وافق على الثلاثة!**

---

### **4. اختبار العمل في الخلفية**

#### **Test 1: Upload من Foreground**
1. افتح التطبيق
2. أضف ملف للرفع
3. راقب Logcat:
```bash
adb logcat -v time *:E
```

**النتيجة المتوقعة:**
```
✅ UploadForegroundService started successfully
```

---

#### **Test 2: Upload من Background (الاختبار الحرج)**

1. أضف ملف للرفع
2. **أغلق التطبيق من Recent Apps** (Swipe up)
3. انتظر 10-15 ثانية
4. راقب Logcat

**على Android 12+:**
```
⏰ UploadAlarmReceiver triggered
⚠️ Cannot start FGS from background
🔄 Using WorkManager fallback
✅ Upload scheduled via WorkManager
```

**على Android < 12:**
```
⏰ UploadAlarmReceiver triggered
✅ UploadForegroundService started
```

---

#### **Test 3: بعد Reboot**

1. أعد تشغيل الهاتف
2. **لا تفتح التطبيق**
3. راقب Logcat

**النتيجة المتوقعة:**
```
📱 تم إعادة تشغيل الجهاز
✅ تمت إعادة جدولة مهام الرفع
⏰ Starting AlarmManager
```

---

### **5. اختبار عدم التوقف (No Crash)**

#### **Test Crash on Android 12+:**

1. **قبل الإصلاح:** كان يتوقف فوراً
2. **بعد الإصلاح:** يجب أن يعمل بدون توقف

**كيفية التأكد:**
```bash
adb logcat -v time AndroidRuntime:E *:S
```

**يجب ألا ترى:**
```
FATAL EXCEPTION: main
ForegroundServiceStartNotAllowedException
```

**إذا رأيت هذا = المشكلة لم تُحل!**

---

### **6. اختبار ProGuard (Release)**

```powershell
./gradlew assembleRelease
```

**النتيجة:**
```
BUILD SUCCESSFUL
```

**ثم ثبّت:**
```powershell
adb install app-release.apk
```

**اختبر نفس السيناريوهات السابقة**

**يجب أن يعمل مثل Debug تماماً!**

---

## 📱 اختبار على أجهزة مختلفة

### **Recommended Devices:**

| Device | Android | Battery | Notes |
|--------|---------|---------|-------|
| Xiaomi (MIUI) | 12+ | متساهل | ✅ يعمل جيداً |
| Samsung (OneUI) | 12+ | صارم | 🔥 اختبار حرج |
| Google Pixel | 12+ | صارم جداً | 🔥🔥 الأهم |
| Oppo/Realme | 12+ | صارم | 🔥 اختبار مهم |

---

## 🔍 أوامر Logcat المفيدة

### **مراقبة الأخطاء فقط:**
```bash
adb logcat -v time *:E
```

### **مراقبة Upload System:**
```bash
adb logcat -v time UploadAlarmReceiver:D UploadForegroundService:D *:E
```

### **مراقبة MainActivity:**
```bash
adb logcat -v time MainActivity:E *:S
```

### **البحث عن Crashes:**
```bash
adb logcat -v time AndroidRuntime:E *:S
```

---

## ✅ Checklist التطبيق الجاهز

- [ ] Build ينجح (Debug)
- [ ] Build ينجح (Release)
- [ ] الصلاحيات تُطلب عند الفتح
- [ ] Upload يعمل من Foreground
- [ ] Upload يعمل من Background
- [ ] لا crashes على Android 12+
- [ ] AlarmManager يعمل كل 10 ثواني
- [ ] يعمل بعد Reboot
- [ ] Release APK يعمل مثل Debug
- [ ] ProGuard لا يحذف Classes

---

## 🚨 إذا واجهت مشاكل

### **المشكلة 1: Build فشل**
```
Solution: ./gradlew clean assembleDebug
```

### **المشكلة 2: لا إشعارات**
```
Check: الصلاحيات → Applications → Sponsorships → Notifications
```

### **المشكلة 3: لا رفع من Background**
```
Check: 
1. Battery Optimization → Disabled
2. Logcat → AlarmManager triggered?
3. Exact Alarms permission granted?
```

### **المشكلة 4: Release APK يتوقف**
```
Check: proguard-rules.pro → Rules correct?
Rebuild: ./gradlew clean assembleRelease
```

---

## 📊 النتائج المتوقعة

| Test | Android < 12 | Android 12+ |
|------|-------------|-------------|
| Upload (Foreground) | ✅ FGS | ✅ FGS |
| Upload (Background) | ✅ FGS | ✅ WorkManager |
| After Reboot | ✅ FGS | ✅ WorkManager |
| Notifications | ✅ Auto | ✅ Permission |
| AlarmManager | ✅ Auto | ✅ Permission |
| Release APK | ✅ Works | ✅ Works |

---

**إذا جميع الاختبارات نجحت = التطبيق جاهز 100%! 🎉**
