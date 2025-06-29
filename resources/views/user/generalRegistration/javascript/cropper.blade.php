{{-- <div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
    <div class="modal-dialog cropper-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- أزرار التحكم ستضاف دينامياً من دالة showCropperModal -->
                <img id="cropperImage" src="" alt="Image to crop" style="width: 100%; display: block;">
            </div>
            <div class="modal-footer d-flex flex-wrap justify-content-between">
                <button type="button" class="btn btn-primary" id="cropperCropBtn">قص وحفظ</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
            </div>
        </div>
    </div>
</div>
<style>
    @media (min-width: 768px) {
        .cropper-modal-dialog {
            max-width: 600px;
            width: 60vw;
        }
    }
    @media (max-width: 767.98px) {
        .cropper-modal-dialog {
            max-width: 98vw;
            min-width: 90vw;
            width: 98vw;
            margin: 0 auto;
        }
    }
    /* مركز الحاوية الخاصة بالصورة */
    .cropper-center-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 350px;
        background: #f8f9fa;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        margin-bottom: 60px; /* مباعدة إضافية من الأسفل */
        position: relative;
    }
    /* تكبير نقاط التحديد */
    .cropper-point {
        width: 18px !important;
        height: 18px !important;
        background: #2196f3 !important;
        border: 2px solid #fff !important;
        box-shadow: 0 0 6px #2196f3cc;
    }
    /* أزرار التحكم */
    .cropper-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
        margin-bottom: 1rem;
    }
    .cropper-controls button {
        min-width: 44px;
        min-height: 44px;
        font-size: 1.3rem;
        border-radius: 50%;
        border: none;
        background: #f1f3f4;
        color: #333;
        transition: background 0.2s;
        box-shadow: 0 1px 4px #0001;
    }
    .cropper-controls button:hover {
        background: #e3f2fd;
        color: #1976d2;
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet" />
<script>
window.showCropperModal = function(file, callback) {
    const modalEl = document.getElementById('cropperModal');
    const cropperImage = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    let cropper = null;
    // إزالة أي حدث سابق لمنع التكرار
    cropBtn.onclick = null;
    // إزالة أي cropper سابق
    if (cropperImage.cropperInstance) {
        cropperImage.cropperInstance.destroy();
        cropperImage.cropperInstance = null;
    }
    // قراءة الصورة
    const reader = new FileReader();
    reader.onload = function(e) {
        cropperImage.src = e.target.result;
        cropperImage.onload = function() {
            if (cropperImage.cropperInstance) {
                cropperImage.cropperInstance.destroy();
            }
            cropper = new Cropper(cropperImage, {
                aspectRatio: NaN,
                viewMode: 1,
                responsive: true,
                autoCropArea: 1,
                movable: true,
                zoomable: true,
                rotatable: true,
                scalable: true,
                background: false,
                minContainerWidth: 320,
                minContainerHeight: 320,
                guides: true,
                center: true,
                highlight: true,
                dragMode: 'move',
                cropBoxResizable: true,
                cropBoxMovable: true,
            });
            cropperImage.cropperInstance = cropper;
        };
    };
    reader.readAsDataURL(file);
    // إظهار المودال
    let bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
    // إضافة أزرار التحكم مع الأيقونات (تم حذف آخر خمس أدوات)
    let controls = modalEl.querySelector('.cropper-controls');
    if (!controls) {
        controls = document.createElement('div');
        controls.className = 'cropper-controls';
        controls.innerHTML = `
            <button type="button" title="تحريك للأعلى" id="cropperMoveUp"><i class="fas fa-arrow-up"></i></button>
            <button type="button" title="تحريك لليسار" id="cropperMoveLeft"><i class="fas fa-arrow-left"></i></button>
            <button type="button" title="تحريك لليمين" id="cropperMoveRight"><i class="fas fa-arrow-right"></i></button>
            <button type="button" title="تحريك للأسفل" id="cropperMoveDown"><i class="fas fa-arrow-down"></i></button>
            <button type="button" title="تكبير" id="cropperZoomIn"><i class="fas fa-search-plus"></i></button>
            <button type="button" title="تصغير" id="cropperZoomOut"><i class="fas fa-search-minus"></i></button>
            <button type="button" title="تدوير يمين" id="cropperRotateRight"><i class="fas fa-undo"></i></button>
        `;
        // أضفها أعلى الصورة
        const modalBody = modalEl.querySelector('.modal-body');
        modalBody.insertBefore(controls, modalBody.firstChild);
    }
    // وضع الصورة داخل حاوية وسطية
    let container = modalEl.querySelector('.cropper-center-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'cropper-center-container';
        const img = cropperImage;
        container.appendChild(img);
        // أضف الحاوية مكان الصورة الأصلية
        const modalBody = modalEl.querySelector('.modal-body');
        modalBody.appendChild(container);
    } else {
        if (!container.contains(cropperImage)) {
            container.innerHTML = '';
            container.appendChild(cropperImage);
        }
    }
    // تفعيل أزرار التحكم
    setTimeout(() => {
        if (!cropper) return;
        controls.querySelector('#cropperMoveUp').onclick = () => cropper.move(0, -10);
        controls.querySelector('#cropperMoveDown').onclick = () => cropper.move(0, 10);
        controls.querySelector('#cropperMoveLeft').onclick = () => cropper.move(-10, 0);
        controls.querySelector('#cropperMoveRight').onclick = () => cropper.move(10, 0);
        controls.querySelector('#cropperZoomIn').onclick = () => cropper.zoom(0.1);
        controls.querySelector('#cropperZoomOut').onclick = () => cropper.zoom(-0.1);
        controls.querySelector('#cropperRotateRight').onclick = () => cropper.rotate(45);
    }, 500);
    // زر القص
    cropBtn.onclick = function() {
        if (!cropperImage.cropperInstance) return;
        // الحصول على بيانات cropBox الفعلية
        const cropData = cropperImage.cropperInstance.getData(true);
        const canvas = cropperImage.cropperInstance.getCroppedCanvas({
            width: Math.round(cropData.width),
            height: Math.round(cropData.height),
            imageSmoothingQuality: 'high'
        });
        if (!canvas) return;
        canvas.toBlob(function(blob) {
            if (!blob) return;
            // اسم جديد للملف المقصوص
            const originalName = file.name;
            const dotIdx = originalName.lastIndexOf('.');
            const base = dotIdx !== -1 ? originalName.substring(0, dotIdx) : originalName;
            const ext = dotIdx !== -1 ? originalName.substring(dotIdx) : '';
            const croppedName = base + '_cropped' + ext;
            const croppedFile = new File([blob], croppedName, {
                type: file.type
            });
            // فحص blob الناتج
            console.log('croppedFile', croppedFile);
            bsModal.hide();
            cropperImage.cropperInstance.destroy();
            cropperImage.cropperInstance = null;
            callback(croppedFile);
        }, file.type);
    };
}
</script> --}}

<!-- Modal for cropping (fullscreen on small screens) -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down cropper-modal-dialog">
    <div class="modal-content d-flex flex-column" style="height: 100vh;">
      <!-- Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <!-- Body: Crop area takes most space -->
      <div class="modal-body flex-grow-1 p-0 position-relative" style="overflow: hidden;">
        <div class="cropper-center-container d-flex align-items-center justify-content-center h-100">
          <img id="cropperImage" src="" alt="Image to crop" class="w-100 h-auto" style="object-fit: contain;">
        </div>
        <!-- Cropper Controls: overlay on image bottom -->
        <div class="cropper-controls d-flex justify-content-center">
          <button type="button" id="cropperMoveUp" data-bs-toggle="tooltip" title="تحريك للأعلى"><i class="fas fa-arrow-up"></i></button>
          <button type="button" id="cropperMoveLeft" data-bs-toggle="tooltip" title="تحريك لليسار"><i class="fas fa-arrow-left"></i></button>
          <button type="button" id="cropperMoveRight" data-bs-toggle="tooltip" title="تحريك لليمين"><i class="fas fa-arrow-right"></i></button>
          <button type="button" id="cropperMoveDown" data-bs-toggle="tooltip" title="تحريك للأسفل"><i class="fas fa-arrow-down"></i></button>
          <button type="button" id="cropperZoomIn" data-bs-toggle="tooltip" title="تكبير"><i class="fas fa-search-plus"></i></button>
          <button type="button" id="cropperZoomOut" data-bs-toggle="tooltip" title="تصغير"><i class="fas fa-search-minus"></i></button>
          <button type="button" id="cropperRotateRight" data-bs-toggle="tooltip" title="تدوير"><i class="fas fa-sync-alt"></i></button>
        </div>
      </div>
      <!-- Footer: action buttons -->
      <div class="modal-footer">
        <div class="d-flex justify-content-between w-100">
          <button type="button" class="btn btn-outline-secondary flex-fill me-1" data-bs-dismiss="modal">إلغاء</button>
          <button type="button" class="btn btn-primary flex-fill ms-1" id="cropperCropBtn">قص وحفظ</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Cropper.js CSS & JS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<style>
  /* Fullscreen on small devices */
  .modal-fullscreen-sm-down .modal-content { margin: 0; border-radius: 0; }
  .cropper-center-container { background: #f8f9fa; }
  .cropper-controls {
    position: absolute;
    bottom: 4rem;
    left: 50%;
    transform: translateX(-50%);
    gap: 0.5rem;
    background: rgba(255,255,255,0.8);
    padding: 0.5rem 1rem;
    border-radius: 8px;
  }
  .cropper-controls button {
    width: 48px;
    height: 48px;
    font-size: 1.2rem;
    border-radius: 8px;
    margin: 0 4px;
    background: #fff;
    border: 1px solid #ccc;
  }
  .cropper-point { width: 28px !important; height: 28px !important; }
</style>

<script>
window.showCropperModal = function(file, callback) {
  const modalEl = document.getElementById('cropperModal');
  const imageEl = document.getElementById('cropperImage');
  const cropBtn = document.getElementById('cropperCropBtn');
  let cropper;

  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

  // Reset previous instance
  cropBtn.onclick = null;
  if (imageEl.cropperInstance) {
    imageEl.cropperInstance.destroy();
    imageEl.cropperInstance = null;
  }

  // Prepare FileReader correctly
  const reader = new FileReader();
  reader.addEventListener('load', (e) => {
    imageEl.src = e.target.result;
    imageEl.onload = () => {
      if (cropper) {
        cropper.destroy();
      }
      // Initialize cropper
      cropper = new Cropper(imageEl, {
        viewMode: 1,
        responsive: true,
        background: false,
        autoCropArea: 0.8,
        checkOrientation: true,
        dragMode: 'move',
        guides: true,
        movable: true,
        zoomable: true,
        rotatable: true,
        scalable: true,
        cropBoxResizable: true,
        cropBoxMovable: true,
        ready: function() {
          // Use cropper instance to get container data
          const containerData = cropper.getContainerData();
          const boxWidth = containerData.width * 0.8;
          const boxHeight = containerData.height * 0.8;
          cropper.setCropBoxData({
            left: (containerData.width - boxWidth) / 2,
            top: (containerData.height - boxHeight) / 2,
            width: boxWidth,
            height: boxHeight
          });
        }
      });
      imageEl.cropperInstance = cropper;
    };
  });
  reader.readAsDataURL(file);

  // Show modal
  const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  bsModal.show();

  // Bind controls after modal shows
  setTimeout(() => {
    document.getElementById('cropperMoveUp').onclick    = () => cropper.move(0, -20);
    document.getElementById('cropperMoveDown').onclick  = () => cropper.move(0, 20);
    document.getElementById('cropperMoveLeft').onclick  = () => cropper.move(-20, 0);
    document.getElementById('cropperMoveRight').onclick = () => cropper.move(20, 0);
    document.getElementById('cropperZoomIn').onclick    = () => cropper.zoom(0.2);
    document.getElementById('cropperZoomOut').onclick   = () => cropper.zoom(-0.2);
    document.getElementById('cropperRotateRight').onclick = () => cropper.rotate(45);
  }, 300);

  cropBtn.onclick = () => {
    const data = cropper.getData(true);
    const canvas = cropper.getCroppedCanvas({
      width: Math.round(data.width),
      height: Math.round(data.height)
    });
    canvas.toBlob(blob => {
      const name = file.name;
      const ext = name.slice(name.lastIndexOf('.'));
      const base = name.replace(ext, '');
      const croppedFile = new File([blob], `${base}_cropped${ext}`, { type: file.type });
      bsModal.hide();
      cropper.destroy();
      callback(croppedFile);
    }, file.type);
  };
};
</script>
