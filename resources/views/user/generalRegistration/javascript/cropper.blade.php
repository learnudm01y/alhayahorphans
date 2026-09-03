@include('user.generalRegistration.layout.cropperStyle')
@include('user.generalRegistration.layout.cropperHtml')

<script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

<script>
// دالة تحويل HEIC إلى JPEG
async function convertHeicToJpeg(file) {
  const isHeic = file.type === 'image/heic' || file.type === 'image/heif' ||
                 file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif');

  if (!isHeic) return file;

  console.log('[convertHeicToJpeg] تحويل ملف HEIC إلى JPEG:', file.name);

  // انتظار تحميل heic2any إذا لم يكن محملاً بعد
  if (typeof heic2any === 'undefined') {
    console.log('[convertHeicToJpeg] انتظار تحميل heic2any...');
    await new Promise((resolve, reject) => {
      let attempts = 0;
      const check = setInterval(() => {
        attempts++;
        if (typeof heic2any !== 'undefined') {
          clearInterval(check);
          resolve();
        } else if (attempts > 50) { // 5 ثوانٍ
          clearInterval(check);
          reject(new Error('مكتبة heic2any لم تُحمّل'));
        }
      }, 100);
    });
  }

  try {
    const jpegBlob = await heic2any({
      blob: file,
      toType: 'image/jpeg',
      quality: 0.92
    });

    // heic2any قد يُرجع مصفوفة blobs
    const blob = Array.isArray(jpegBlob) ? jpegBlob[0] : jpegBlob;

    const newName = file.name.replace(/\.(heic|heif)$/i, '.jpg');
    const convertedFile = new File([blob], newName, {
      type: 'image/jpeg',
      lastModified: Date.now()
    });

    console.log('[convertHeicToJpeg] تم التحويل بنجاح:', convertedFile.name, convertedFile.size);
    return convertedFile;

  } catch (error) {
    console.error('[convertHeicToJpeg] فشل التحويل:', error);
    throw new Error('فشل في تحويل صورة HEIC');
  }
}
// دالة ضغط الصورة مع شريط التقدم
async function compressImageWithProgress(file) {
  const targetSizeKB = 100;
  const targetSizeBytes = targetSizeKB * 1024;
  const maxSizeMB = 50;

  console.log('[compressImage] بدء ضغط الصورة:', {
    name: file.name,
    size: file.size,
    type: file.type
  });

  // التحقق من حجم الملف
  if (file.size > maxSizeMB * 1024 * 1024) {
    throw new Error(`حجم الملف يتجاوز ${maxSizeMB} ميجابايت`);
  }

  // تجاهل الملفات الصغيرة بالفعل
  if (file.size <= targetSizeBytes) {
    console.log('[compressImage] الملف صغير بالفعل، لا حاجة للضغط');
    return file;
  }

  // عرض شريط التقدم
  Swal.fire({
    title: 'جاري ضغط الصورة...',
    html: `
      <div class="progress mb-3" style="height: 20px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated"
             role="progressbar"
             style="width: 0%"
             id="compressionProgress">0%</div>
      </div>
      <div class="text-muted">
        <small>الحجم الأصلي: ${(file.size / 1024 / 1024).toFixed(2)} MB</small><br>
        <small>الهدف: ${targetSizeKB} KB</small>
      </div>
    `,
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => {
      // تحريك شريط التقدم
      let progress = 0;
      const progressBar = document.getElementById('compressionProgress');
      const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;

        progressBar.style.width = progress + '%';
        progressBar.textContent = Math.round(progress) + '%';
      }, 200);

      // حفظ المؤقت لتنظيفه لاحقاً
      Swal.getPopup().progressInterval = interval;
    }
  });

  try {
    // خيارات الضغط
    const options = {
      maxSizeMB: targetSizeKB / 1024, // تحويل KB إلى MB
      maxWidthOrHeight: 1920,
      useWebWorker: true,
      fileType: file.type,
      initialQuality: 0.8,
      alwaysKeepResolution: false,
      onProgress: (progress) => {
        const progressBar = document.getElementById('compressionProgress');
        if (progressBar) {
          const realProgress = Math.min(90 + (progress * 10), 100);
          progressBar.style.width = realProgress + '%';
          progressBar.textContent = Math.round(realProgress) + '%';
        }
      }
    };

    console.log('[compressImage] بدء الضغط بالخيارات:', options);

    const compressedFile = await imageCompression(file, options);

    // تنظيف المؤقت
    const popup = Swal.getPopup();
    if (popup && popup.progressInterval) {
      clearInterval(popup.progressInterval);
    }

    console.log('[compressImage] تم الضغط بنجاح:', {
      originalSize: file.size,
      compressedSize: compressedFile.size,
      compressionRatio: ((1 - compressedFile.size / file.size) * 100).toFixed(1) + '%'
    });

    // إخفاء شريط التقدم
    Swal.close();

    // عرض نتيجة الضغط
    const compressionRatio = ((1 - compressedFile.size / file.size) * 100).toFixed(1);
    Swal.fire({
      icon: 'success',
      title: 'تم ضغط الصورة بنجاح',
      html: `
        <div class="text-center">
          <p class="mb-2">الحجم الأصلي: <strong>${(file.size / 1024 / 1024).toFixed(2)} MB</strong></p>
          <p class="mb-2">الحجم الجديد: <strong>${(compressedFile.size / 1024).toFixed(2)} KB</strong></p>
          <p class="text-success">تم توفير ${compressionRatio}% من المساحة</p>
        </div>
      `,
      timer: 3000,
      showConfirmButton: false
    });

    return compressedFile;

  } catch (error) {
    // تنظيف المؤقت في حالة الخطأ
    const popup = Swal.getPopup();
    if (popup && popup.progressInterval) {
      clearInterval(popup.progressInterval);
    }

    console.error('[compressImage] خطأ في الضغط:', error);
    Swal.fire({
      icon: 'error',
      title: 'خطأ في ضغط الصورة',
      text: error.message || 'حدث خطأ غير متوقع'
    });
    throw error;
  }
}
// // إزالة أي cropper modal سابق
// document.querySelectorAll('.cropper-modal, .modal[data-cropper-modal]').forEach(m => m.remove());
// // أو إذا كان لديك ID ثابت:
// const oldModal = document.getElementById('cropperModal');
// if (oldModal) oldModal.remove();
// // دالة لإغلاق المودال وإزالة cropper

window.showCropperModal = async function(file, callback) {
  console.log('[showCropperModal] بدء فحص الملف:', file);

  // التحقق من صحة الملف
  if (!file || !(file instanceof File) && !(file instanceof Blob)) {
    console.error('[showCropperModal] ملف غير صالح:', file);
    if (callback) callback(null, 'ملف غير صالح');
    return;
  }

  // فحص: هل الملف صورة (بما فيها HEIC)?
  const isImage = (file.type && file.type.startsWith('image/')) ||
                  file.name.toLowerCase().endsWith('.heic') ||
                  file.name.toLowerCase().endsWith('.heif');

  if (!isImage) {
    console.error('[showCropperModal] الملف ليس صورة:', file.type);
    if (callback) callback(null, 'الملف ليس صورة');
    return;
  }

  // تحويل HEIC إلى JPEG إذا لزم الأمر
  try {
    file = await convertHeicToJpeg(file);
  } catch (heicError) {
    console.error('[showCropperModal] خطأ في تحويل HEIC:', heicError);
    if (callback) callback(null, 'فشل في تحويل صورة HEIC');
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

        //   function cleanup() {
        //     if (cropper) {
        //       cropper.destroy();
        //       cropper = null;
        //     }
        //     elements.cropBtn.onclick = null;
        //     if (objectUrl) {
        //       URL.revokeObjectURL(objectUrl);
        //       objectUrl = null;
        //     }
        //     if (timeoutTimer) {
        //       clearTimeout(timeoutTimer);
        //       timeoutTimer = null;
        //     }
        //   }
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
            cleanup();
            handleAttachmentCancelAndCleanup(file);
            callback(null);
            // إغلاق المودال إذا لم يكن مغلقاً تلقائياً
            bootstrap.Modal.getInstance(elements.modal)?.hide();
        };
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
     handleAttachmentCancelAndCleanup(file);
    callback(null);
  }, 300000);

  // تحميل الصورة
  const reader = new FileReader();

  reader.onerror = () => {
    console.error('[showCropperModal] خطأ في قراءة الملف');
    cleanup();
    handleAttachmentCancelAndCleanup(file);
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
       handleAttachmentCancelAndCleanup(file);
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

  // معالج زر القص - النسخة المصححة
  elements.cropBtn.onclick = async function() {
    if (!cropperReady || !cropper) {
      Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: 'أداة القص غير جاهزة'
      });
      return;
    }

    console.log('[CropButton] بدء عملية القص والمعالجة للملف:', file.name);

    // عرض شريط التقدم المحدث
    Swal.fire({
      title: 'جاري معالجة الصورة...',
      html: `
        <div class="progress mb-3" style="height: 20px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar"
               style="width: 10%"
               id="processingProgress">10%</div>
        </div>
        <div class="text-muted" id="statusText">
          <small>بدء عملية القص...</small>
        </div>
      `,
      allowOutsideClick: false,
      showConfirmButton: false
    });

    bootstrap.Modal.getInstance(elements.modal).hide();

    try {
      // تحديث شريط التقدم - بدء القص
      const progressBar = document.getElementById('processingProgress');
      const statusText = document.getElementById('statusText');

      if (progressBar && statusText) {
        progressBar.style.width = '30%';
        progressBar.textContent = '30%';
        statusText.innerHTML = '<small>جاري قص الصورة...</small>';
      }

      const canvas = cropper.getCroppedCanvas({
        imageSmoothingQuality: 'high',
        fillColor: '#ffffff'
      });

      if (!canvas) {
        throw new Error('فشل في قص الصورة');
      }

      console.log('[CropButton] تم إنشاء canvas مقصوص بنجاح');

      // تحديث شريط التقدم - تحويل إلى blob
      if (progressBar && statusText) {
        progressBar.style.width = '50%';
        progressBar.textContent = '50%';
        statusText.innerHTML = '<small>جاري تحويل الصورة...</small>';
      }

      // تحويل canvas إلى blob
      const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
          if (blob) {
            resolve(blob);
          } else {
            reject(new Error('فشل في تحويل الصورة'));
          }
        }, file.type, 0.9);
      });

      console.log('[CropButton] تم تحويل canvas إلى blob:', blob.size, 'bytes');

      // إنشاء اسم ملف جديد مع إشارة أنه مقصوص
      const originalName = file.name.replace(/\.[^/.]+$/, '');
      const extension = file.name.match(/\.[^/.]+$/)?.[0] || '.jpg';
      const croppedFileName = `${originalName}_cropped${extension}`;

      // إنشاء ملف جديد
      const croppedFile = new File([blob], croppedFileName, {
        type: file.type,
        lastModified: Date.now()
      });

      console.log('[CropButton] تم إنشاء ملف مقصوص:', {
        name: croppedFile.name,
        size: croppedFile.size,
        type: croppedFile.type
      });

      // تحديث شريط التقدم - فحص الحاجة للضغط
      if (progressBar && statusText) {
        progressBar.style.width = '70%';
        progressBar.textContent = '70%';
        statusText.innerHTML = '<small>فحص الحاجة للضغط...</small>';
      }

      let finalFile = croppedFile;

      // فحص: هل الضغط مفعّل من الإعداد؟
      const shouldCompress = window._compressAttachmentsEnabled !== false;

      // ضغط الملف المقصوص فقط إذا كان أكبر من 100KB والضغط مفعّل
      if (shouldCompress && croppedFile.size > 100 * 1024) {
        console.log('[CropButton] الملف يحتاج للضغط:', croppedFile.size, 'bytes');

        if (statusText) {
          statusText.innerHTML = '<small>جاري ضغط الصورة...</small>';
        }

        try {
          // ضغط بدون عرض واجهة إضافية
          const options = {
            maxSizeMB: 0.1, // 100KB
            maxWidthOrHeight: 1920,
            useWebWorker: true,
            fileType: croppedFile.type,
            initialQuality: 0.8,
            alwaysKeepResolution: false
          };

          finalFile = await imageCompression(croppedFile, options);
          console.log('[CropButton] تم ضغط الملف بنجاح:', finalFile.size, 'bytes');

          // تحديث اسم الملف ليتضمن أنه مضغوط أيضاً
          const compressedName = finalFile.name.replace('_cropped', '_cropped_compressed');
          finalFile = new File([finalFile], compressedName, {
            type: finalFile.type,
            lastModified: Date.now()
          });

        } catch (compressionError) {
          console.warn('[CropButton] فشل في ضغط الملف، سيتم استخدام النسخة المقصوصة:', compressionError);
          // استخدم الملف المقصوص بدون ضغط في حالة فشل الضغط
        }
      } else {
        if (!shouldCompress) {
          console.log('[CropButton] الضغط معطّل من إعدادات الجمعية');
        } else {
          console.log('[CropButton] الملف صغير بما فيه الكفاية، لا حاجة للضغط');
        }
      }

      // تحديث شريط التقدم - انتهاء المعالجة
      if (progressBar && statusText) {
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';
        statusText.innerHTML = '<small>تمت المعالجة بنجاح!</small>';
      }

      console.log('[CropButton] الملف النهائي:', {
        name: finalFile.name,
        size: finalFile.size,
        type: finalFile.type
      });

      // إخفاء شريط التقدم بعد فترة قصيرة
      setTimeout(() => {
        Swal.close();
        cleanup();
        console.log('[CropButton] تم إنجاز المعالجة بنجاح، استدعاء callback');
        callback(finalFile);
      }, 800);

    } catch (error) {
      console.error('[CropButton] خطأ في المعالجة:', error);
      Swal.fire({
        icon: 'error',
        title: 'خطأ في المعالجة',
        text: 'فشل في معالجة الصورة: ' + error.message
      });
      cleanup();
      handleAttachmentCancelAndCleanup(file);
      callback(null);
    }
  };

  // تنظيف عند إغلاق المودال
  elements.modal.addEventListener('hidden.bs.modal', cleanup, { once: true });

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

// معالجة المسافة البيضاء أسفل الأزرار في الجوال
function fixCropperAreaHeight() {
  const modal = document.getElementById('cropperModal');
  const cropperBody = document.querySelector('.cropper-modal-body');
  const cropArea = document.querySelector('.crop-area');
  const actionBtns = document.querySelector('.action-buttons');
  const header = modal ? modal.querySelector('.modal-header') : null;

  if (
    window.innerWidth <= 767.98 &&
    modal && cropperBody && cropArea && actionBtns && header
  ) {
    // حساب المساحة المتاحة
    const headerHeight = header.offsetHeight;
    const actionBtnsHeight = actionBtns.offsetHeight;
    const total = window.innerHeight;
    const available = total - headerHeight - actionBtnsHeight;

    cropperBody.style.height = (total - headerHeight) + 'px';
    cropArea.style.height = available + 'px';
    cropArea.style.minHeight = '0';
    cropArea.style.maxHeight = 'none';
    cropArea.style.margin = '0';
    cropArea.style.padding = '0';
  } else if (cropArea) {
    // إعادة تعيين في الشاشات الكبيرة
    cropArea.style.height = '';
    cropArea.style.minHeight = '';
    cropArea.style.maxHeight = '';
    cropArea.style.margin = '';
    cropArea.style.padding = '';
  }
}

// استدعاء عند فتح المودال وعند تغيير الحجم
window.addEventListener('resize', fixCropperAreaHeight);
document.addEventListener('shown.bs.modal', function(e) {
  if (e.target && e.target.id === 'cropperModal') {
    setTimeout(fixCropperAreaHeight, 100);
  }
});
// إضافة هذا السطر لضمان الضبط عند أول تحميل للصفحة
document.addEventListener('DOMContentLoaded', fixCropperAreaHeight);

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
      fileInput.addEventListener('change', async (e) => {
        const file = fileInput.files[0];
        if (!file) return;

        console.log('[FileInput] تم اختيار ملف:', {
          name: file.name,
          size: file.size,
          type: file.type
        });

        try {
          if (file.type.startsWith('image/')) {
            console.log('[FileInput] بدء عرض أداة القص للملف:', file.name);

            // عرض أداة القص مباشرة بدون ضغط مسبق
            window.showCropperModal(file, (processedFile) => {
              if (processedFile) {
                console.log('[FileInput] تم استلام الملف المعالج:', {
                  name: processedFile.name,
                  size: processedFile.size,
                  isCropped: processedFile.name.includes('_cropped')
                });
                uploadFileAJAX(processedFile, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
              } else {
                console.error('[FileInput] لم يتم استلام ملف معالج');
                fileInput.value = '';
                Swal.fire({
                  icon: 'warning',
                  title: 'تم الإلغاء',
                  text: 'تم إلغاء عملية معالجة الصورة'
                });
              }
            });
          } else {
            // للملفات غير الصور، رفع مباشر
            console.log('[FileInput] ملف غير صورة، رفع مباشر');
            uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
          }
        } catch (error) {
          console.error('[FileInput] خطأ في معالجة الملف:', error);
          Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'حدث خطأ أثناء معالجة الملف'
          });
          fileInput.value = '';
        }
      });
    }
  });

  function uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType) {
    console.log('[uploadFileAJAX] بدء رفع الملف:', {
      fileName: file.name,
      fileSize: file.size,
      fileType: file.type,
      docType: hiddenDocType?.value,
      isCropped: file.name.includes('_cropped'),
      isCompressed: file.name.includes('_compressed')
    });

    // التحقق من أن الملف صالح
    if (!file || !(file instanceof File)) {
      console.error('[uploadFileAJAX] الملف غير صالح:', file);
      Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: 'الملف غير صالح'
      });
      return;
    }

    const loaderModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('compressLoaderModal'));
    loaderModal?.show();

    // إنشاء FormData جديد مع التحقق من البيانات
    const formData = new FormData();

    // إضافة الملف مع التأكد من الاسم الصحيح
    formData.append('file', file, file.name);

    // إضافة معرف CSRF
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) {
      formData.append('_token', csrf.getAttribute('content'));
    }

    // إضافة نوع المستند
    if (hiddenDocType?.value) {
      formData.append('document_type_value', hiddenDocType.value);
      console.log('[uploadFileAJAX] نوع المستند:', hiddenDocType.value);
    }

    // إضافة معلومات إضافية للتتبع
    formData.append('is_cropped', file.name.includes('_cropped') ? '1' : '0');
    formData.append('is_compressed', file.name.includes('_compressed') ? '1' : '0');
    formData.append('original_size', file.size.toString());
    formData.append('file_type', file.type);

    // عرض محتويات FormData للتأكد
    console.log('[uploadFileAJAX] محتويات FormData:');
    for (let [key, value] of formData.entries()) {
      if (value instanceof File) {
        console.log(`${key}: [File] ${value.name} (${value.size} bytes, ${value.type})`);
      } else {
        console.log(`${key}: ${value}`);
      }
    }

    fetch('/ajax/file-upload', {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => {
      console.log('[uploadFileAJAX] استجابة الخادم - الحالة:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      loaderModal?.hide();
      console.log('[uploadFileAJAX] بيانات الاستجابة:', data);

      if (data.success && data.file_id && data.temp_file_name) {
        console.log('[uploadFileAJAX] تم رفع الملف بنجاح:', {
          fileId: data.file_id,
          tempFileName: data.temp_file_name,
          originalFileName: file.name
        });

        if (hiddenFileId) hiddenFileId.value = data.file_id;
        if (hiddenTempName) hiddenTempName.value = data.temp_file_name;

        if (preview && file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = '';
            console.log('[uploadFileAJAX] تم عرض معاينة الصورة');
          };
          reader.readAsDataURL(file);
        } else if (preview) {
          preview.textContent = file.name;
          preview.style.display = '';
          console.log('[uploadFileAJAX] تم عرض اسم الملف');
        }

        // إظهار رسالة نجاح
        Swal.fire({
          icon: 'success',
          title: 'تم الرفع بنجاح',
          text: `تم رفع ومعالجة الملف بنجاح`,
          timer: 2000,
          showConfirmButton: false
        });

      } else {
        console.error('[uploadFileAJAX] فشل رفع الملف:', data);
        Swal.fire({
          icon: 'error',
          title: 'خطأ في الرفع',
          text: data.message || 'فشل رفع الملف'
        });
        resetFields();
      }
    })
    .catch(err => {
      loaderModal?.hide();
      console.error('[uploadFileAJAX] خطأ في الشبكة:', err);
      Swal.fire({
        icon: 'error',
        title: 'خطأ في الشبكة',
        text: 'حدث خطأ أثناء رفع الملف. يرجى المحاولة مرة أخرى.'
      });
      resetFields();
    });

    function resetFields() {
      if (hiddenFileId) hiddenFileId.value = '';
      if (hiddenTempName) hiddenTempName.value = '';
      if (preview) preview.style.display = 'none';
      console.log('[uploadFileAJAX] تم إعادة تعيين الحقول');
    }
  }

  // ...existing code...
});
</script>


