<style>
    /* Custom styles for the cropper modal */
    .modal-fullscreen-sm-down .modal-content {
        border-radius: 0;
        margin: 0;
        height: 100vh; /* Ensure modal content fills screen height on small devices */
    }

    .cropper-cropper-modal-body {
        /* Use flex to arrange crop area and controls panel */
        display: flex;
        flex-direction: column; /* Stack on mobile by default */
        flex-grow: 1; /* Allow body to take available height */
        padding: 0 !important; /* Remove default padding to maximize space for image */
        overflow: hidden; /* Hide overflow */
        min-height: 400px;
        min-width: 320px;
    }

    /* Desktop layout: Side-by-side */
    @media (min-width: 768px) { /* Bootstrap's md breakpoint */
        .cropper-cropper-modal-body {
            flex-direction: row; /* Side-by-side on desktop */
        }
        .controls-panel-container {
            order: 2; /* Controls on the left for RTL, so it appears visually on the right */
            border-right: 1px solid #dee2e6; /* Add border for separation */
            border-left: none; /* Remove default border-start */
        }
    }

    .crop-area {
        flex-grow: 1; /* Allow crop area to take all available space */
        position: relative;
        display: flex; /* Use flexbox to center image vertically and horizontally */
        align-items: center; /* Center vertically */
        justify-content: center; /* Center horizontally */
        background-color: #f8f9fa; /* Light background */
        overflow: hidden; /* Important for Cropper.js */
        padding: 10px; /* Small padding inside crop area */
        min-width: 350px;
        min-height: 350px;
        /* زيادة الحجم الافتراضي */
    }

    .crop-area img {
        display: block; /* Important for Cropper.js */
        /* Use max-width/height here, Cropper.js will handle fitting */
        max-width: 100%;
        max-height: 100%;
        object-fit: contain; /* Ensure image fits without distortion */
    }

    @media (min-width: 992px) {
        .modal-dialog {
            max-width: 900px;
            width: 90vw;
        }
        .cropper-modal-body {
            flex-direction: row;
            min-height: 600px;
            min-width: 700px;
        }
        .crop-area {
            min-width: 600px;
            min-height: 600px;
            max-width: 700px;
            max-height: 700px;
        }
        .controls-panel-container {
            min-width: 160px;
            max-width: 200px;
        }
    }

    /* Controls Panel Styles */
    .controls-panel-container {
        width: 100%; /* Full width on mobile */
        padding: 1rem;
        background-color: #fff;
        display: flex;
        flex-direction: column;
        gap: 1rem; /* Spacing between sections */
        flex-shrink: 0; /* Don't shrink the controls panel */
        /* Ensure it's never smaller than its content on mobile */
        min-height: fit-content;
        min-width: 120px;
        z-index: 2;
    }

    @media (min-width: 768px) {
        .controls-panel-container {
            width: 120px; /* Fixed width on desktop */
            min-width: 120px; /* Prevent shrinking */
            flex-direction: column;
            justify-content: space-between; /* Push action buttons to bottom */
        }
    }

    .controls-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr); /* 3 columns for buttons */
        gap: 0.5rem; /* Spacing between buttons */
        justify-items: center; /* Center items in grid */
        align-items: center; /* Center items vertically */
    }

    /* Adjustments for move buttons in a cross pattern */
    .controls-grid .move-up { grid-column: 2; grid-row: 1; }
    .controls-grid .move-left { grid-column: 1; grid-row: 2; }
    .controls-grid .move-center { grid-column: 2; grid-row: 2; display: none; /* Placeholder or an action button */ }
    .controls-grid .move-right { grid-column: 3; grid-row: 2; }
    .controls-grid .move-down { grid-column: 2; grid-row: 3; }

    /* Zoom and Rotate Buttons */
    .controls-grid .zoom-in { grid-column: 1; grid-row: 4; }
    .controls-grid .zoom-out { grid-column: 2; grid-row: 4; }
    .controls-grid .rotate-right { grid-column: 3; grid-row: 4; }


    .controls-panel-container .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        width: 100%; /* Ensure buttons take full width */
        margin-top: 1rem; /* Space from controls */
    }

    @media (max-width: 767.98px) { /* Mobile specific styles */
        .cropper-modal-body {
            flex-direction: column;
            min-width: 0;
            min-height: 0;
        }
        .controls-panel-container {
            flex-direction: row; /* Horizontal on mobile */
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap; /* Allow buttons to wrap */
            padding: 0.5rem;
            border-top: 1px solid #dee2e6; /* Separator from image */
            min-width: 0;
            max-width: 100vw;
        }
        .controls-grid {
            grid-template-columns: repeat(auto-fit, minmax(40px, 1fr)); /* More flexible grid for mobile */
            width: 100%;
        }
        .controls-panel-container .action-buttons {
            flex-direction: row; /* Horizontal buttons on mobile */
            width: 100%;
            justify-content: space-around;
            margin-top: 0.5rem;
        }
        .controls-panel-container .action-buttons button {
            flex: 1; /* Distribute space evenly */
        }
    }


    /* Ensure Cropper.js points are visible and appropriately sized */
    .cropper-point {
        background-color: #fff !important; /* White background */
        border: 1px solid #337ab7 !important; /* Blue border */
        opacity: 0.7 !important; /* Slightly transparent */
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.2); /* Small shadow for visibility */
    }
    .cropper-view-box {
        outline: 2px solid #337ab7; /* Blue outline for crop box */
        outline-color: rgba(51, 122, 183, 0.75); /* Semi-transparent blue */
    }
    .cropper-line {
        background-color: #337ab7 !important; /* Blue lines */
        opacity: 0.7 !important;
    }
</style>
<style>
    /* Custom styles for the cropper modal */
    .modal-fullscreen-sm-down .modal-content {
        border-radius: 0;
        margin: 0;
        height: 100vh;
    }

    .cropper-cropper-modal-body {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        padding: 0 !important;
        overflow: hidden;
        min-height: 400px;
        min-width: 320px;
    }

    @media (min-width: 768px) {
        .cropper-cropper-modal-body {
            flex-direction: row;
        }
        .controls-panel-container {
            order: 2;
            border-right: 1px solid #dee2e6;
            border-left: none;
        }
    }

    .crop-area {
        flex-grow: 1;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        overflow: hidden;
        padding: 10px;
        min-width: 350px;
        min-height: 350px;
        /* زيادة الحجم الافتراضي */
    }

    .crop-area img {
        display: block;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    @media (min-width: 992px) {
        .modal-dialog {
            max-width: 900px;
            width: 90vw;
        }
        .cropper-modal-body {
            flex-direction: row;
            min-height: 600px;
            min-width: 700px;
        }
        .crop-area {
            min-width: 600px;
            min-height: 600px;
            max-width: 700px;
            max-height: 700px;
        }
        .controls-panel-container {
            min-width: 160px;
            max-width: 200px;
        }
    }

    .controls-panel-container {
        width: 100%;
        padding: 1rem;
        background-color: #fff;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        flex-shrink: 0;
        min-height: fit-content;
        min-width: 120px;
        z-index: 2;
    }

    @media (min-width: 768px) {
        .controls-panel-container {
            width: 120px;
            min-width: 120px;
            flex-direction: column;
            justify-content: space-between;
        }
    }

    .controls-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        justify-items: center;
        align-items: center;
    }

    .controls-grid .move-up { grid-column: 2; grid-row: 1; }
    .controls-grid .move-left { grid-column: 1; grid-row: 2; }
    .controls-grid .move-center { grid-column: 2; grid-row: 2; display: none; }
    .controls-grid .move-right { grid-column: 3; grid-row: 2; }
    .controls-grid .move-down { grid-column: 2; grid-row: 3; }

    .controls-grid .zoom-in { grid-column: 1; grid-row: 4; }
    .controls-grid .zoom-out { grid-column: 2; grid-row: 4; }
    .controls-grid .rotate-right { grid-column: 3; grid-row: 4; }

    .controls-panel-container .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        width: 100%;
        margin-top: 1rem;
    }

    @media (max-width: 767.98px) {
        .cropper-modal-body {
            flex-direction: column;
            min-width: 0;
            min-height: 0;
        }
        .controls-panel-container {
            flex-direction: row;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
            padding: 0.5rem;
            border-top: 1px solid #dee2e6;
            min-width: 0;
            max-width: 100vw;
        }
        .controls-grid {
            grid-template-columns: repeat(auto-fit, minmax(40px, 1fr));
            width: 100%;
        }
        .controls-panel-container .action-buttons {
            flex-direction: row;
            width: 100%;
            justify-content: space-around;
            margin-top: 0.5rem;
        }
        .controls-panel-container .action-buttons button {
            flex: 1;
        }
    }

    .cropper-point {
        width: 24px !important;
        height: 24px !important;
        background-color: #fff !important;
        border: 2px solid #337ab7 !important;
        opacity: 1 !important;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.2);
        border-radius: 50%;
    }

    .cropper-line {
        background-color: #337ab7 !important;
        opacity: 0.8 !important;
    }
    .cropper-line.line-e, .cropper-line.line-w {
        width: 3px !important;
    }
    .cropper-line.line-n, .cropper-line.line-s {
        height: 3px !important;
    }

    .cropper-view-box {
        outline: 3px solid #337ab7;
        outline-color: rgba(51, 122, 183, 0.9);
    }
</style>

<!-- Loader Modal -->
<div class="modal fade" id="compressLoaderModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <div class="spinner-border text-primary mb-3" role="status">
          <span class="visually-hidden">جاري إدراج الوثيقة...</span>
        </div>
        <div id="compressLoaderTitle" class="fw-bold">جاري إدراج الوثيقة</div>
      </div>
    </div>
  </div>
</div>

<!-- Cropper Modal Markup -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content d-flex flex-column vh-100">
      <div class="modal-header">
        <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="cropper-modal-body flex-grow-1 d-flex flex-column flex-md-row p-0 overflow-hidden">

        <!-- Crop Area -->
        <div class="crop-area flex-grow-1 d-flex justify-content-center align-items-center bg-light p-2">
          <img id="cropperImage" src="" alt="Image to crop" class="img-fluid" />
        </div>

        <!-- Controls Panel -->
        <div class="controls-panel-container bg-white d-flex flex-column justify-content-between p-3">
          <div class="controls-grid d-grid gap-2" style="grid-template-columns: repeat(3, 1fr);">
            <button id="cropperMoveUp" class="btn btn-outline-secondary" title="↑" disabled><i class="fas fa-arrow-up"></i></button>
            <button id="cropperMoveLeft" class="btn btn-outline-secondary" title="←" disabled><i class="fas fa-arrow-left"></i></button>
            <span class="move-center"></span>
            <button id="cropperMoveRight" class="btn btn-outline-secondary" title="→" disabled><i class="fas fa-arrow-right"></i></button>
            <button id="cropperMoveDown" class="btn btn-outline-secondary" title="↓" disabled><i class="fas fa-arrow-down"></i></button>
            <button id="cropperZoomIn" class="btn btn-outline-secondary" title="＋" disabled><i class="fas fa-search-plus"></i></button>
            <button id="cropperZoomOut" class="btn btn-outline-secondary" title="－" disabled><i class="fas fa-search-minus"></i></button>
            <button id="cropperRotateRight" class="btn btn-outline-secondary" title="↻" disabled><i class="fas fa-sync-alt"></i></button>
          </div>
          <div class="action-buttons d-flex flex-column gap-2 mt-3">
            <button id="cropperCropBtn" class="btn btn-success" disabled>قص وحفظ</button>
            <button class="btn btn-danger" data-bs-dismiss="modal">إلغاء</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- إضافة مكتبة browser-image-compression -->
<script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>

<script>
  window.showCropperModal = function(file, callback) {
    const modalEl = document.getElementById('cropperModal');
    const imgEl = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    const loaderModalEl = document.getElementById('compressLoaderModal');
    const loaderModal = bootstrap.Modal.getOrCreateInstance(loaderModalEl);
    let cropper;

    // تعطيل جميع الأزرار عند البداية
    function disableAllControls() {
      cropBtn.disabled = true;
      ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
        const btn = document.getElementById('cropper' + action);
        if (btn) btn.disabled = true;
      });
    }

    // تفعيل جميع الأزرار بعد تحميل الصورة وتهيئة cropper
    function enableAllControls() {
      cropBtn.disabled = false;
      ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
        const btn = document.getElementById('cropper' + action);
        if (btn) btn.disabled = false;
      });
    }

    // Reset any previous state
    disableAllControls();
    if (imgEl.cropperInstance) {
      imgEl.cropperInstance.destroy();
      imgEl.cropperInstance = null;
    }

    // إزالة أي حدث سابق من زر القص لتجنب تكرار التنفيذ
    cropBtn.onclick = null;

    // Read file
    const reader = new FileReader();
    reader.onload = function(e) {
      imgEl.src = e.target.result;
      imgEl.onload = function() {
        // Destroy previous cropper if exists
        if (imgEl.cropperInstance) {
          imgEl.cropperInstance.destroy();
          imgEl.cropperInstance = null;
        }
        // Initialize Cropper
        cropper = new Cropper(imgEl, {
          aspectRatio: NaN,
          viewMode: 1,
          responsive: true,
          autoCropArea: 1,
          movable: true,
          zoomable: true,
          rotatable: true,
          scalable: true,
          background: false,
          guides: true,
          center: true,
          dragMode: 'move',
        });
        imgEl.cropperInstance = cropper;
        enableAllControls();

        // Attach controls safely
        enableCropperControls();

        // إعادة ربط زر القص في كل مرة (يسمح بالقص عدة مرات)
        cropBtn.onclick = async function() {
          if (!cropper) return;
          // إذا كان حجم الملف الأصلي أقل من أو يساوي 100KiB، أدرجه مباشرة بدون قص أو ضغط وبدون إظهار اللودر
          if (file.size <= 100 * 1024) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            const originalName = file.name;
            const ext = originalName.substring(originalName.lastIndexOf('.'));
            const base = originalName.replace(ext, '');
            const newFile = new File([file], base + '_cropped' + ext, { type: file.type });
            // سجل الحجم بنفس تنسيق الكونسول
            console.log('تم قص الصورة:', {
              name: newFile.name,
              size: newFile.size,
              type: newFile.type
            });
            console.log('حجم الصورة بعد الضغط:', newFile.size, 'bytes');
            // معالجة مشكلة عدم إضافة الملف للمصفوفة أو العرض: إعادة استدعاء callback دائماً بعد التأكد من انتهاء المودال
            setTimeout(() => {
              loaderModal.hide();
              callback(newFile);
            }, 0);
            // تأكيد إخفاء اللودر بعد فترة قصيرة في حال لم يختفِ بسبب مشاكل في DOM
            setTimeout(() => { loaderModal.hide(); }, 700);
            return;
          }
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          document.getElementById('compressLoaderTitle').textContent = 'جاري إدراج الوثيقة';
          loaderModal.show();

          const data = cropper.getData(true);
          const canvas = cropper.getCroppedCanvas({
            width: Math.round(data.width),
            height: Math.round(data.height),
            imageSmoothingQuality: 'high'
          });

          canvas.toBlob(async function(blob) {
            // إذا كان حجم الصورة بعد القص أقل من أو يساوي 100KiB، أدرجها مباشرة بدون ضغط وبدون إظهار اللودر
            if (blob.size <= 100 * 1024) {
              const originalName = file.name;
              const ext = originalName.substring(originalName.lastIndexOf('.'));
              const base = originalName.replace(ext, '');
              const newFile = new File([blob], base + '_cropped' + ext, { type: file.type });
              console.log('تم قص الصورة:', {
                name: newFile.name,
                size: newFile.size,
                type: newFile.type
              });
              console.log('حجم الصورة بعد الضغط:', newFile.size, 'bytes');
              cropBtn.blur && cropBtn.blur();
              bootstrap.Modal.getOrCreateInstance(modalEl).hide();
              setTimeout(() => {
                loaderModal.hide();
                callback(newFile);
              }, 0);
              setTimeout(() => { loaderModal.hide(); }, 700);
              return;
            }
            // إذا كان الحجم أكبر من 100KiB، أظهر اللودر ثم اضغط الصورة
            document.getElementById('compressLoaderTitle').textContent = 'جاري إدراج الوثيقة';
            loaderModal.show();
            try {
              const originalName = file.name;
              const ext = originalName.substring(originalName.lastIndexOf('.'));
              const base = originalName.replace(ext, '');
              let compressedFile = blob;
              let compressedSize = blob.size;
              let quality = 0.7;
              let maxTries = 7;
              let options = {
                maxSizeMB: 0.1,
                maxWidthOrHeight: Math.max(canvas.width, canvas.height),
                useWebWorker: true,
                initialQuality: quality,
                fileType: file.type
              };
              for (let i = 0; i < maxTries && compressedSize > 100 * 1024; i++) {
                compressedFile = await imageCompression(compressedFile, options);
                compressedSize = compressedFile.size;
                options.initialQuality = Math.max(0.1, options.initialQuality - 0.1);
              }
              const newFile = new File([compressedFile], base + '_cropped' + ext, { type: file.type });
              console.log('تم قص الصورة:', {
                name: newFile.name,
                size: newFile.size,
                type: newFile.type
              });
              console.log('حجم الصورة بعد الضغط:', newFile.size, 'bytes');
              cropBtn.blur && cropBtn.blur();
              bootstrap.Modal.getOrCreateInstance(modalEl).hide();
              setTimeout(() => {
                loaderModal.hide();
                callback(newFile);
              }, 0);
              setTimeout(() => { loaderModal.hide(); }, 700);
            } catch (err) {
              loaderModal.hide();
              alert('حدث خطأ أثناء ضغط الصورة');
              callback(null);
            }
          }, file.type, 0.7);
        };
      };
    };
    reader.readAsDataURL(file);

    // Show modal
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();

    // Wire control buttons
    function enableCropperControls() {
      const actions = [
        ['MoveUp',    () => cropper && cropper.move(0, -10)],
        ['MoveDown',  () => cropper && cropper.move(0, 10)],
        ['MoveLeft',  () => cropper && cropper.move(-10, 0)],
        ['MoveRight', () => cropper && cropper.move(10, 0)],
        ['ZoomIn',    () => cropper && cropper.zoom(0.1)],
        ['ZoomOut',   () => cropper && cropper.zoom(-0.1)],
        ['RotateRight', () => cropper && cropper.rotate && cropper.rotate(45)]
      ];
      actions.forEach(([id, fn]) => {
        const btn = document.getElementById('cropper' + id);
        if (btn) btn.onclick = fn;
      });
    }

    // عند إغلاق المودال، دمر cropper لتفادي التسربات
    modalEl.addEventListener('hidden.bs.modal', function cleanup() {
      if (imgEl.cropperInstance) {
        imgEl.cropperInstance.destroy();
        imgEl.cropperInstance = null;
      }
      cropper = null;
      cropBtn.onclick = null;
      modalEl.removeEventListener('hidden.bs.modal', cleanup);
    });
  };

  // اعتراض جميع عمليات رفع الصور في الموقع
  document.addEventListener('change', function(e) {
    const input = e.target;
    if (input.type === 'file' && input.files && input.files.length > 0) {
      Array.from(input.files).forEach(file => {
        if (file.type.startsWith('image/')) {
          // سجل حجم الصورة الأصلية
          console.log('جم الصورة قبل الضغط:', file.size, 'bytes');
          // إذا لم يتم ضغط الصورة لاحقاً (مثلاً لم تمر على cropper)، سجلها مباشرة بعد الإضافة
          // إذا تم ضغطها عبر cropper، سيتم تسجيل الحجم بعد الضغط هناك
        }
      });
    }
  });

</script>
    }
  });

</script>

