@include('user.generalRegistration.layout.cropperStyle')
@include('user.generalRegistration.layout.cropperHtml')

<script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>

<script>
window.showCropperModal = function(file, callback) {
  console.log('[showCropperModal] بدء فحص الملف:', file);

  // التحقق من صحة الملف
  if (!file || !(file instanceof File) && !(file instanceof Blob)) {
    console.error('[showCropperModal] ملف غير صالح:', file);
    if (callback) callback(null, 'ملف غير صالح');
    return;
  }

  if (!file.type || !file.type.startsWith('image/')) {
    console.error('[showCropperModal] الملف ليس صورة:', file.type);
    if (callback) callback(null, 'الملف ليس صورة');
    return;
  }

  if (file.size === 0 || file.size > 50 * 1024 * 1024) {
    console.error('[showCropperModal] حجم الملف غير صالح:', file.size);
    if (callback) callback(null, 'حجم الملف غير صالح');
    return;
  }

  // الحصول على العناصر المطلوبة
  const elements = {
    modal: document.getElementById('cropperModal'),
    image: document.getElementById('cropperImage'),
    cropBtn: document.getElementById('cropperCropBtn'),
    loaderModal: document.getElementById('compressLoaderModal')
  };

  // التحقق من وجود العناصر
  for (const [key, element] of Object.entries(elements)) {
    if (!element) {
      console.error(`[showCropperModal] عنصر ${key} مفقود`);
      if (callback) callback(null, `عنصر ${key} مفقود`);
      return;
    }
  }

  console.log('[showCropperModal] ✅ جميع العناصر موجودة');

  // المتغيرات الرئيسية
  let cropper = null;
  let cropperReady = false;
  let timeoutTimer = null;
  let objectUrl = null;

  // وظائف مساعدة
  function isMobileDevice() {
    return /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  }

  function enableControls(enable = true) {
    elements.cropBtn.disabled = !enable;
    ['MoveUp', 'MoveDown', 'MoveLeft', 'MoveRight', 'ZoomIn', 'ZoomOut', 'RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = !enable;
    });
  }

  function setupCropperControls() {
    if (!cropper) return;

    const controls = {
      MoveUp: () => cropper.move(0, -10),
      MoveDown: () => cropper.move(0, 10),
      MoveLeft: () => cropper.move(-10, 0),
      MoveRight: () => cropper.move(10, 0),
      ZoomIn: () => cropper.zoom(0.1),
      ZoomOut: () => cropper.zoom(-0.1),
      RotateRight: () => cropper.rotate(90)
    };

    Object.entries(controls).forEach(([action, handler]) => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.onclick = handler;
    });
  }

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

  // تنظيف عند إغلاق المودال
  elements.modal.addEventListener('hidden.bs.modal', cleanup, { once: true });

  // مهلة زمنية (5 دقائق)
  timeoutTimer = setTimeout(() => {
    cleanup();
    bootstrap.Modal.getInstance(elements.loaderModal)?.hide();
    Swal.fire({
      icon: 'error',
      title: 'انتهى الوقت',
      text: 'لم يتم استكمال قص الصورة خلال الوقت المحدد'
    });
    callback(null);
  }, 300000);

  // تحميل الصورة
  const reader = new FileReader();

  reader.onerror = () => {
    console.error('[showCropperModal] خطأ في قراءة الملف');
    cleanup();
    callback(null, 'خطأ في قراءة الملف');
  };

  reader.onload = (e) => {
    if (!e.target.result) {
      console.error('[showCropperModal] لا توجد بيانات');
      cleanup();
      callback(null, 'لا توجد بيانات في الملف');
      return;
    }

    objectUrl = e.target.result;
    elements.image.src = objectUrl;

    // تحسين حجم الصورة
    elements.image.onload = () => {
      const container = elements.image.parentElement;
      const containerWidth = container.clientWidth || 800;
      const containerHeight = container.clientHeight || 600;

      // تطبيق حجم محسن
      elements.image.style.maxWidth = '95%';
      elements.image.style.maxHeight = '95%';

      console.log('[showCropperModal] تم تحميل الصورة بنجاح');
      initializeCropper();
    };

    elements.image.onerror = () => {
      console.error('[showCropperModal] خطأ في تحميل الصورة');
      cleanup();
      callback(null, 'خطأ في تحميل الصورة');
    };
  };

  function initializeCropper() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }

    // إعداد الصورة للعرض المثلى
    const isMobile = window.innerWidth <= 991;

    // تنظيف الأنماط السابقة
    elements.image.style.width = '';
    elements.image.style.height = '';
    elements.image.style.minWidth = '';
    elements.image.style.minHeight = '';
    elements.image.style.maxWidth = '';
    elements.image.style.maxHeight = '';

    try {
      cropper = new Cropper(elements.image, {
        aspectRatio: NaN,
        viewMode: 1,
        responsive: true,
        restore: false,
        autoCropArea: 0.9,
        movable: true,
        zoomable: true,
        rotatable: true,
        scalable: true,
        background: false,
        guides: true,
        center: true,
        highlight: true,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
        minCropBoxWidth: 100,
        minCropBoxHeight: 100,
        modal: true,
        ready() {
          console.log('[showCropperModal] ✅ تم تهيئة أداة القص بنجاح');
          cropperReady = true;
          enableControls(true);
          setupCropperControls();

          // تحسين العرض الأولي
          setTimeout(() => {
            try {
              const containerData = cropper.getContainerData();
              const canvasData = cropper.getCanvasData();
              const imageData = cropper.getImageData();

              console.log('Container:', containerData);
              console.log('Canvas:', canvasData);
              console.log('Image:', imageData);

              // تعيين منطقة القص المثلى
              const cropWidth = Math.min(canvasData.width * 0.8, containerData.width * 0.8);
              const cropHeight = Math.min(canvasData.height * 0.8, containerData.height * 0.8);

              cropper.setCropBoxData({
                left: canvasData.left + (canvasData.width - cropWidth) / 2,
                top: canvasData.top + (canvasData.height - cropHeight) / 2,
                width: cropWidth,
                height: cropHeight
              });

              // تأكد من ظهور الصورة بالكامل
              if (canvasData.width < containerData.width || canvasData.height < containerData.height) {
                const scaleX = containerData.width / imageData.naturalWidth;
                const scaleY = containerData.height / imageData.naturalHeight;
                const scale = Math.min(scaleX, scaleY) * 0.8;
                cropper.zoomTo(scale);
              }

            } catch (e) {
              console.warn('تحذير في إعداد منطقة القص:', e);
            }
          }, 500);
        },
        error(err) {
          console.error('[showCropperModal] خطأ في تهيئة أداة القص:', err);
          cleanup();
          callback(null, 'خطأ في تهيئة أداة القص');
        }
      });

      elements.image.cropperInstance = cropper;

    } catch (error) {
      console.error('[showCropperModal] استثناء في إنشاء أداة القص:', error);
      cleanup();
      callback(null, 'استثناء في أداة القص');
    }
  }

  // معالج زر القص
  elements.cropBtn.onclick = async function() {
    if (!cropperReady || !cropper) {
      Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: 'أداة القص غير جاهزة'
      });
      return;
    }

    // عرض شريط التقدم
    Swal.fire({
      title: 'جاري معالجة الصورة...',
      html: '<div class="progress"><div class="progress-bar" style="width: 0%">0%</div></div>',
      allowOutsideClick: false,
      showConfirmButton: false
    });

    bootstrap.Modal.getInstance(elements.modal).hide();

    try {
      const canvas = cropper.getCroppedCanvas({
        imageSmoothingQuality: 'high'
      });

      if (!canvas) {
        throw new Error('فشل في قص الصورة');
      }

      // ضغط الصورة
      const blob = await new Promise(resolve => canvas.toBlob(resolve, file.type, 0.8));
      const fileName = file.name.replace(/\.[^/.]+$/, '') + '_cropped' + file.name.match(/\.[^/.]+$/)[0];
      const croppedFile = new File([blob], fileName, { type: file.type });

      Swal.close();
      cleanup();
      callback(croppedFile);

    } catch (error) {
      console.error('[showCropperModal] خطأ في المعالجة:', error);
      Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: 'فشل في معالجة الصورة'
      });
      cleanup();
      callback(null);
    }
  };

  // قراءة الملف
  reader.readAsDataURL(file);

  // عرض المودال مع ضمان الحجم الصحيح
  const modal = new bootstrap.Modal(elements.modal, {
    backdrop: 'static',
    keyboard: false,
    focus: true
  });

  // ضمان إعادة تهيئة Cropper عند عرض المودال
  elements.modal.addEventListener('shown.bs.modal', function modalShownHandler() {
    console.log('[showCropperModal] تم عرض المودال');

    setTimeout(() => {
      if (elements.image.naturalWidth > 0) {
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        initializeCropper();
      } else {
        console.warn('[showCropperModal] الصورة لم تحمل بعد، إعادة محاولة...');
        setTimeout(() => {
          if (elements.image.naturalWidth > 0) {
            initializeCropper();
          }
        }, 500);
      }
    }, 200);

    // إزالة المستمع بعد أول استخدام
    elements.modal.removeEventListener('shown.bs.modal', modalShownHandler);
  });

  modal.show();
};

// FileUploadHandler للمناطق المتعددة
document.addEventListener('DOMContentLoaded', function() {
  const uploadZones = document.querySelectorAll('[data-upload-role="zone"]');

  uploadZones.forEach(zone => {
    const fileInput = zone.querySelector('[data-file-role="input"]');
    const selectBtn = zone.querySelector('[data-file-role="select"]');
    const preview = zone.querySelector('[data-file-role="preview"]');
    const hiddenFileId = zone.querySelector('[data-file-role="file_id"]');
    const hiddenTempName = zone.querySelector('[data-file-role="temp_file_name"]');
    const hiddenDocType = zone.querySelector('[data-file-role="document_type_value"]');

    if (selectBtn && fileInput) {
      selectBtn.addEventListener('click', (e) => {
        e.preventDefault();
        fileInput.click();
      });
    }

    if (fileInput) {
      fileInput.addEventListener('change', (e) => {
        const file = fileInput.files[0];
        if (!file) return;

        if (file.type.startsWith('image/')) {
          window.showCropperModal(file, (croppedFile) => {
            if (croppedFile) {
              uploadFileAJAX(croppedFile, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
            } else {
              fileInput.value = '';
            }
          });
        } else {
          uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
        }
      });
    }
  });

  function uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType) {
    const loaderModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('compressLoaderModal'));
    loaderModal?.show();

    const formData = new FormData();
    formData.append('file', file);

    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) formData.append('_token', csrf.getAttribute('content'));

    if (hiddenDocType?.value) {
      formData.append('document_type_value', hiddenDocType.value);
    }

    fetch('/ajax/file-upload', {
      method: 'POST',
      body: formData,
      headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
      loaderModal?.hide();

      if (data.success && data.file_id && data.temp_file_name) {
        if (hiddenFileId) hiddenFileId.value = data.file_id;
        if (hiddenTempName) hiddenTempName.value = data.temp_file_name;

        if (preview && file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = '';
          };
          reader.readAsDataURL(file);
        } else if (preview) {
          preview.textContent = file.name;
          preview.style.display = '';
        }
      } else {
        Swal.fire({
          icon: 'error',
          title: 'خطأ',
          text: data.message || 'فشل رفع الملف'
        });
        resetFields();
      }
    })
    .catch(err => {
      loaderModal?.hide();
      Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: 'حدث خطأ أثناء رفع الملف'
      });
      resetFields();
    });

    function resetFields() {
      if (hiddenFileId) hiddenFileId.value = '';
      if (hiddenTempName) hiddenTempName.value = '';
      if (preview) preview.style.display = 'none';
    }
  }
});

// دالة مختصرة
window.showCropper = window.showCropperModal;

// إشارة الجاهزية
window.cropperReady = true;
console.log('[Cropper] ✅ تم تحميل أداة القص بنجاح');

if (window.dispatchEvent) {
  window.dispatchEvent(new CustomEvent('cropperReady', {
    detail: { showCropperModal: window.showCropperModal }
  }));
}
</script>


<!-- دالة مختصرة لسهولة الوصول -->
<script>
window.showCropper = window.showCropperModal;

// فحص تشخيصي لتأكيد توفر أداة القص
console.log('[Cropper] ✅ تم تحميل أداة القص بنجاح');
console.log('[Cropper] window.showCropperModal:', typeof window.showCropperModal);
console.log('[Cropper] window.showCropper:', typeof window.showCropper);

// إرسال إشارة عالمية بأن أداة القص جاهزة
window.cropperReady = true;
if (window.dispatchEvent) {
  window.dispatchEvent(new CustomEvent('cropperReady', {
    detail: {
      showCropperModal: window.showCropperModal,
      showCropper: window.showCropper
    }
  }));
}
</script>


