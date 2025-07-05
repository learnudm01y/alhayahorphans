# حل مشكلة أداة القص (Cropper)

## المشكلة المحددة
أداة القص لا تعمل في أي من البوابات (الرئيسية أو أفراد الأسرة).

## التشخيص المُطبق

### 1. فحص ترتيب تحميل الملفات
- ملف `documentUpload.blade.php` يتم تحميله قبل `manageForm.blade.php`
- أداة القص (`showCropperModal`) مُعرَّفة في `cropper.blade.php` التي يتم تضمينها في `manageForm.blade.php`
- هذا يعني أن `documentUpload` يحاول استدعاء أداة قص غير موجودة بعد

### 2. الحلول المُطبقة

#### أ) تحسين دالة انتظار أداة القص
```javascript
function waitForCropper(attempts = 0) {
    const maxAttempts = 50; // 50 محاولة × 200ms = 10 ثواني
    
    if (finished) return;
    
    // البحث عن دالة أداة القص
    let showCropper = window.showCropperModal || window.showCropper;
    
    if (!showCropper) {
        // البحث في جميع الدوال المتاحة
        for (let key in window) {
            if (typeof window[key] === 'function' && /cropper/i.test(key)) {
                showCropper = window[key];
                console.log('[showCropperModalPromise] تم العثور على دالة القص:', key);
                break;
            }
        }
    }
    
    if (showCropper) {
        // تم العثور على أداة القص، بدء المعالجة
        startCropperProcess(showCropper);
    } else if (attempts < maxAttempts) {
        // لم يتم العثور على أداة القص، انتظار قليلاً ثم المحاولة مرة أخرى
        console.log('[showCropperModalPromise] انتظار تحميل أداة القص... المحاولة', attempts + 1);
        setTimeout(() => waitForCropper(attempts + 1), 200);
    } else {
        // فشل في العثور على أداة القص بعد جميع المحاولات
        finished = true;
        reject(new Error('أداة قص الصور غير متوفرة بعد انتظار 10 ثواني'));
    }
}
```

#### ب) إضافة فحص تشخيصي في بداية التحميل
```javascript
// فحص تشخيصي لحالة أداة القص
console.log('[documentUpload] فحص حالة أداة القص...');
console.log('[documentUpload] window.showCropperModal:', typeof window.showCropperModal);
console.log('[documentUpload] window.showCropper:', typeof window.showCropper);

// انتظار تحميل أداة القص إذا لم تكن متوفرة
if (!window.showCropperModal && !window.showCropper) {
    console.warn('[documentUpload] أداة القص غير متوفرة عند تحميل الصفحة، انتظار التحميل...');
    let checkCount = 0;
    const checkCropper = () => {
        if (window.showCropperModal || window.showCropper) {
            console.log('[documentUpload] ✅ تم تحميل أداة القص بنجاح');
        } else if (checkCount < 50) { // انتظار 10 ثواني
            checkCount++;
            setTimeout(checkCropper, 200);
        } else {
            console.error('[documentUpload] ❌ فشل في تحميل أداة القص بعد 10 ثواني');
        }
    };
    checkCropper();
} else {
    console.log('[documentUpload] ✅ أداة القص متوفرة ومجهزة');
}
```

#### ج) تحسين دالة معالجة الصورة
```javascript
// التحقق من توفر أداة القص قبل البدء
if (!window.showCropperModal && !window.showCropper) {
    console.warn('[processAttachment] أداة القص غير متوفرة، انتظار تحميلها...');
    // انتظار قصير لتحميل أداة القص
    let waitCount = 0;
    const waitForCropper = () => {
        if (window.showCropperModal || window.showCropper) {
            console.log('[processAttachment] تم العثور على أداة القص، بدء المعالجة');
            processCropperTask();
        } else if (waitCount < 25) { // انتظار 5 ثواني (25 × 200ms)
            waitCount++;
            setTimeout(waitForCropper, 200);
        } else {
            console.error('[processAttachment] فشل في العثور على أداة القص بعد 5 ثواني');
            updateAttachmentTaskStatus(id, 'failed', null, 'أداة قص الصور غير متوفرة. يرجى تحديث الصفحة.');
        }
    };
    waitForCropper();
    return;
}
```

## خطوات التشخيص

### 1. افتح أدوات المطورين (F12)
### 2. تحقق من هذه الرسائل في الكونسول عند تحميل الصفحة:
```
[documentUpload] فحص حالة أداة القص...
[documentUpload] window.showCropperModal: function (أو undefined)
[documentUpload] ✅ أداة القص متوفرة ومجهزة
```

### 3. إذا ظهرت رسالة "أداة القص غير متوفرة":
```
[documentUpload] ❌ أداة القص غير متوفرة عند تحميل الصفحة، انتظار التحميل...
[documentUpload] انتظار تحميل أداة القص... المحاولة 1
[documentUpload] ✅ تم تحميل أداة القص بنجاح
```

### 4. عند محاولة رفع صورة، تحقق من:
```
[processAttachment] بدء معالجة الصورة: att_... filename.jpg
[showCropperModalPromise] بدء معالجة ملف: filename.jpg الحجم: 12345
[showCropperModalPromise] استدعاء أداة القص للملف: filename.jpg
```

## إذا استمرت المشكلة

### فحص إضافي - أدخل هذا في الكونسول:
```javascript
console.log('Available functions:', Object.keys(window).filter(k => typeof window[k] === 'function' && /crop/i.test(k)));
console.log('showCropperModal:', typeof window.showCropperModal);
console.log('Bootstrap Modal:', typeof bootstrap !== 'undefined' ? 'Available' : 'Missing');
console.log('Cropper elements:', {
    modal: document.getElementById('cropperModal'),
    image: document.getElementById('cropperImage'),
    cropBtn: document.getElementById('cropperCropBtn')
});
```

### فحص تحميل العناصر الأساسية:
```javascript
// تحقق من وجود عناصر أداة القص في DOM
const modal = document.getElementById('cropperModal');
const image = document.getElementById('cropperImage');
const cropBtn = document.getElementById('cropperCropBtn');

console.log('Cropper elements check:', {
    modal: modal ? 'Found' : 'Missing',
    image: image ? 'Found' : 'Missing', 
    cropBtn: cropBtn ? 'Found' : 'Missing'
});
```

## الحلول البديلة المحتملة

### 1. إعادة ترتيب تحميل الملفات
إذا لم تنجح الحلول أعلاه، يمكن تعديل `javascript.blade.php`:
```php
@include('user.generalRegistration.javascript.taps')
@include('user.generalRegistration.javascript.manageDaedTap')
@include('user.generalRegistration.javascript.manageForm') // نقل هذا قبل documentUpload
@include('user.generalRegistration.javascript.documentUpload')
@include('user.generalRegistration.javascript.ageCalculating')
@include('user.generalRegistration.javascript.errorTracker')
@include('user.generalRegistration.javascript.autoComplete')
@include('user.generalRegistration.javascript.showInsertedData')
```

### 2. تحميل أداة القص مبكراً
إضافة أداة القص في بداية `documentUpload.blade.php`:
```php
{{-- تضمين أداة القص مبكراً --}}
@include('user.generalRegistration.javascript.cropper')
```
