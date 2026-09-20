# خطة إصلاح ملفات Cropper وفحص الوجه

> [!IMPORTANT]
> جميع الإصلاحات تحافظ على الميزات الموجودة 100%. لا حذف لأي وظيفة، فقط إصلاح أخطاء وتحسين الموجود.

## الملفات المتأثرة

| # | الملف | نوع التعديل |
|---|-------|------------|
| 1 | [generalRegistration/javascript/cropper.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/generalRegistration/javascript/cropper.blade.php) | MODIFY - إصلاحات فحص الوجه + cleanup + dead code |
| 2 | [admin/dashboard/javascript/cropper.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/admin/dashboard/javascript/cropper.blade.php) | MODIFY - إصلاح ترتيب التحقق + HTTP error + disable button |
| 3 | [user/dashboard/javascript/cropper.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/dashboard/javascript/cropper.blade.php) | MODIFY - نفس إصلاحات Admin + validation |

> [!NOTE]
> ملفات HTML ([cropperHtml.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/generalRegistration/layout/cropperHtml.blade.php)) و CSS ([cropperStyle.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/generalRegistration/layout/cropperStyle.blade.php)) وملفات component للأدمن/يوزر لن تتغير - لا يوجد فيها bugs فعلية.

---

## المرحلة 1: إصلاح ملف التسجيل العام (الأكبر والأهم)

#### [MODIFY] [cropper.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/generalRegistration/javascript/cropper.blade.php)

---

### 1.1 إصلاح `isValidGroup` - إضافة فحص `y` (سطر 124)

**قبل:**
```javascript
const isValidGroup = (pts) => pts.every(p => p && typeof p.x === 'number' && typeof p.y === 'number' && !isNaN(p.x) && isFinite(p.x));
```

**بعد:**
```javascript
const isValidGroup = (pts) => pts.every(p => p && typeof p.x === 'number' && typeof p.y === 'number' && !isNaN(p.x) && isFinite(p.x) && !isNaN(p.y) && isFinite(p.y));
```

---

### 1.2 إضافة فحص الحاجبين (بعد سطر 129)

**إضافة بعد فحص الفك:**
```javascript
const rightEyebrow = positions.slice(17, 22);
const leftEyebrow = positions.slice(22, 27);
if (rightEyebrow.length !== 5 || leftEyebrow.length !== 5 || !isValidGroup(rightEyebrow) || !isValidGroup(leftEyebrow)) {
    return { valid: false, reason: 'معالم الحاجبين غير واضحة - يرجى استخدام صورة وجه واضحة من الأمام' };
}
```

---

### 1.3 إصلاح WASM fallback (سطر 28-36)

**قبل:**
```javascript
} catch (e) {
    console.warn('[FaceCheck] WebGL غير متاح، محاولة WASM...');
    try {
      await faceapi.tf.ready();
    } catch (e2) {
      console.error('[FaceCheck] فشل تهيئة TensorFlow.js:', e2);
      return false;
    }
}
```

**بعد:**
```javascript
} catch (e) {
    console.warn('[FaceCheck] WebGL غير متاح، محاولة WASM...');
    try {
      await faceapi.tf.setBackend('wasm');
      await faceapi.tf.ready();
      console.log('[FaceCheck] تم تهيئة TensorFlow.js بـ WASM backend');
    } catch (e2) {
      console.warn('[FaceCheck] WASM غير متاح، محاولة CPU...');
      try {
        await faceapi.tf.setBackend('cpu');
        await faceapi.tf.ready();
        console.log('[FaceCheck] تم تهيئة TensorFlow.js بـ CPU backend (أبطأ)');
      } catch (e3) {
        console.error('[FaceCheck] فشل تهيئة TensorFlow.js:', e3);
        return false;
      }
    }
}
```

---

### 1.4 تنظيف `zoomCanvas` من الذاكرة (بعد سطر 145)

**إضافة قبل `return { valid: true }` في سطر 148:**
```javascript
// تنظيف zoomCanvas من الذاكرة
zoomCanvas.width = 0;
zoomCanvas.height = 0;
```

**وإضافة نفس التنظيف في كل مسار return مبكر بعد إنشاء الـ zoomCanvas (سطور 116 و 121 و 128 و 133 و 139 و 144).**

---

### 1.5 إصلاح `objectUrl` - استخدام createObjectURL بدل data URL (سطر 499-539)

**قبل:**
```javascript
const reader = new FileReader();

reader.onerror = () => { ... };

reader.onload = (e) => {
    if (!e.target.result) { ... }
    objectUrl = e.target.result;
    elements.image.src = objectUrl;
    elements.image.onload = () => { ... };
    elements.image.onerror = () => { ... };
};

// ... (سطر 881)
reader.readAsDataURL(file);
```

**بعد:**
```javascript
// إنشاء Object URL مباشرة (أسرع وأخف من FileReader + data URL)
try {
    objectUrl = URL.createObjectURL(file);
} catch (urlError) {
    console.error('[showCropperModal] خطأ في إنشاء URL للملف:', urlError);
    cleanup();
    handleAttachmentCancelAndCleanup(file);
    callback(null, 'خطأ في قراءة الملف');
    return;
}

elements.image.src = objectUrl;

elements.image.onload = () => {
    const container = elements.image.parentElement;
    const containerWidth = container.clientWidth || 800;
    const containerHeight = container.clientHeight || 600;

    elements.image.style.maxWidth = '95%';
    elements.image.style.maxHeight = '95%';

    console.log('[showCropperModal] تم تحميل الصورة بنجاح');
    initializeCropper();
};

elements.image.onerror = () => {
    console.error('[showCropperModal] خطأ في تحميل الصورة');
    cleanup();
    handleAttachmentCancelAndCleanup(file);
    callback(null, 'خطأ في تحميل الصورة');
};
```

> [!NOTE]
> هذا يُبقي نفس السلوك تماماً لكن بدون FileReader. الآن `URL.revokeObjectURL(objectUrl)` في `cleanup()` سيعمل فعلاً ويحرر الذاكرة.

---

### 1.6 إزالة تسجيل `hidden.bs.modal` المكرر (سطر 878)

**حذف السطر 878:**
```javascript
// هذا السطر مكرر - موجود في سطر 483:
elements.modal.addEventListener('hidden.bs.modal', cleanup, { once: true });
```

---

### 1.7 إصلاح Indentation لدالة `cleanup` وكود `cancelBtn` (سطر 452-478)

**قبل (indentation غير متناسق):**
```javascript
  function cleanup() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        elements.cropBtn.onclick = null;
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        if (timeoutTimer) {
            clearTimeout(timeoutTimer);
            timeoutTimer = null;
        }
        }

        // أضف هذا بعد تعريف cleanup مباشرة
        const cancelBtn = document.getElementById('cropperCancelBtn');
        if (cancelBtn) {
        cancelBtn.onclick = function() {
```

**بعد (indentation موحد):**
```javascript
  function cleanup() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    elements.cropBtn.onclick = null;
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
    if (timeoutTimer) {
      clearTimeout(timeoutTimer);
      timeoutTimer = null;
    }
  }

  // زر الإلغاء
  const cancelBtn = document.getElementById('cropperCancelBtn');
  if (cancelBtn) {
    cancelBtn.onclick = function() {
      cleanup();
      handleAttachmentCancelAndCleanup(file);
      callback(null);
      bootstrap.Modal.getInstance(elements.modal)?.hide();
    };
  }
```

---

### 1.8 حذف dead code: دالة `compressImageWithProgress` (سطر 206-334)

حذف الدالة بالكامل لأنها **غير مستخدمة** في أي مكان. الضغط الفعلي يحدث مباشرة عبر `imageCompression()` داخل `cropBtn.onclick` (سطر 821).

---

### 1.9 حذف الكود المعلّق (سطر 335-340)

**حذف:**
```javascript
// // إزالة أي cropper modal سابق
// document.querySelectorAll('.cropper-modal, .modal[data-cropper-modal]').forEach(m => m.remove());
// // أو إذا كان لديك ID ثابت:
// const oldModal = document.getElementById('cropperModal');
// if (oldModal) oldModal.remove();
// // دالة لإغلاق المودال وإزالة cropper
```

---

### 1.10 إضافة log لـ confidence score (سطر 81)

**إضافة بعد `const face = detections[0]`:**
```javascript
console.log('[FaceCheck] Confidence Score:', face.detection.score.toFixed(3));
```

---

### 1.11 جعل Two-Pass أكثر مرونة عند الفشل (سطر 115-117)

**قبل:**
```javascript
if (!detailed) {
    return { valid: false, reason: 'تعذر تحليل تفاصيل الوجه - يرجى استخدام صورة وجه واضحة' };
}
```

**بعد:**
```javascript
if (!detailed) {
    // Pass 2 فشل لكن Pass 1 نجح - نعتمد على نتائج Pass 1 مع تحذير
    console.warn('[FaceCheck] Pass 2 فشل، الاعتماد على نتائج Pass 1');
    const pass1Landmarks = face.landmarks;
    if (pass1Landmarks && pass1Landmarks.positions && pass1Landmarks.positions.length >= 68) {
        // Pass 1 لديه معالم كافية، نتحقق منها
        const positions = pass1Landmarks.positions;
        // نكمل فحص المعالم باستخدام بيانات Pass 1
        // (الكود يستمر لفحص jaw, nose, eyes, mouth أدناه)
    } else {
        return { valid: false, reason: 'تعذر تحليل تفاصيل الوجه - يرجى استخدام صورة وجه واضحة' };
    }
}
```

> [!IMPORTANT]
> هذا يمنع رفض صور صالحة (false positive) عندما يفشل Pass 2 رغم نجاح Pass 1. الميزة الأصلية (Two-Pass) تبقى كما هي، لكن نضيف fallback على Pass 1.

---

## المرحلة 2: إصلاح ملفات Admin/User Dashboard

#### [MODIFY] [admin/javascript/cropper.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/admin/dashboard/javascript/cropper.blade.php)
#### [MODIFY] [user/javascript/cropper.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/dashboard/javascript/cropper.blade.php)

---

### 2.1 إصلاح ترتيب التحقق من العناصر (سطر 3-12)

**قبل (في كلا الملفين):**
```javascript
const modalEl     = document.getElementById('cropperModal');
const imageEl     = document.getElementById('cropperImage');
const saveBtn     = document.getElementById('cropperSaveBtn');
let   cropper     = null;
const cropperModal = new bootstrap.Modal(modalEl);  // ← يُنشئ قبل التحقق!

if (!modalEl || !imageEl || !saveBtn) {
    console.warn('❗ Cropper modal elements missing:', { modalEl, imageEl, saveBtn });
    return;
}
```

**بعد:**
```javascript
const modalEl     = document.getElementById('cropperModal');
const imageEl     = document.getElementById('cropperImage');
const saveBtn     = document.getElementById('cropperSaveBtn');
let   cropper     = null;

if (!modalEl || !imageEl || !saveBtn) {
    console.warn('❗ Cropper modal elements missing:', { modalEl, imageEl, saveBtn });
    return;
}

const cropperModal = new bootstrap.Modal(modalEl);  // ← الآن بعد التحقق ✅
```

---

### 2.2 إضافة تحقق من HTTP status قبل `.json()` (سطر 84)

**قبل (في كلا الملفين):**
```javascript
.then(res => res.json())
```

**بعد:**
```javascript
.then(res => {
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    return res.json();
})
```

---

### 2.3 إضافة disable للزر أثناء الرفع (سطر 66)

**إضافة في بداية `saveBtn.addEventListener('click', ...)`:**
```javascript
saveBtn.disabled = true;
saveBtn.textContent = 'جاري الرفع...';
```

**وإضافة إعادة التفعيل في `.catch` و `.finally`:**
```javascript
.finally(() => {
    saveBtn.disabled = false;
    saveBtn.textContent = 'حفظ الصورة';
});
```

---

### 2.4 إضافة تحقق من حجم الملف في نسخة User Dashboard

**إضافة في [user/javascript/cropper.blade.php](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/dashboard/javascript/cropper.blade.php) بعد سطر 18:**
```javascript
if (file.size > 10 * 1024 * 1024) {
    Swal.fire({
        icon: 'warning',
        title: 'الملف كبير جداً',
        text: 'الحد الأقصى لحجم الصورة 10 ميجابايت'
    });
    e.target.value = '';
    return;
}
```

---

## المرحلة 3: تنظيف عام

### 3.1 ملف `public/js/cropper.js` الفارغ

هذا الملف يُحمّل ديناميكياً في [generalRegisrationIndex.blade.php سطر 2838](file:///c:/xampp/htdocs/alhayahorphans/resources/views/user/dashboard/component/generalRegisrationIndex.blade.php#L2838) لكنه **فارغ**. هناك احتمالان:

> [!WARNING]
> **سؤال للمستخدم**: هل يُفترض أن يحتوي `public/js/cropper.js` على كود؟ أم أن المشروع يعتمد فقط على `cropper.min.js` و `cropper.bundle.js`؟ إذا كان الاعتماد على `cropper.min.js` فقط، فالملف الفارغ لا يسبب مشكلة عملية (يُحمّل بصمت). يمكنني تركه كما هو.

---

## Verification Plan

### اختبار يدوي

بعد تطبيق التعديلات:

1. **فحص الوجه**: رفع صورة شخصية واضحة → يجب أن تُقبل ✅
2. **صورة بدون وجه**: رفع صورة منظر طبيعي → يجب أن تُرفض ❌
3. **صورة بوجهين**: رفع صورة جماعية → يجب أن تُرفض ❌
4. **وثيقة** (document `_fileType`): رفع صورة وثيقة → يجب أن يتخطى فحص الوجه ✅
5. **Admin avatar**: تغيير الصورة الشخصية من لوحة الأدمن → يجب أن يعمل بدون خطأ ✅
6. **User avatar**: تغيير الصورة من لوحة المستخدم → يجب أن يعمل ✅
7. **ملف كبير في User Dashboard**: رفع صورة >10MB → يجب أن تُرفض ❌
8. **جهاز بدون WebGL**: اختبار على جهاز ضعيف → يجب أن ينتقل لـ WASM ثم CPU ✅
9. **فحص Console**: التأكد من عدم ظهور أخطاء JavaScript في الـ DevTools

### فحص الكود
- التأكد من أن جميع الميزات الموجودة (قص، تدوير، تكبير، ضغط، تحويل HEIC، رفع AJAX) تعمل كما كانت
