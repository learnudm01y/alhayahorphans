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
  let cropperReady = false;
  let timeoutTimer = null;
  let objectUrl = null;

  function isMobileChrome() {
    const ua = navigator.userAgent;
    return /Android|iPhone|iPad|iPod/i.test(ua) && /Chrome/i.test(ua);
  }

  function disableAllControls() {
    cropBtn.disabled = true;
    ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = true;
    });
  }
  function enableAllControls() {
    cropBtn.disabled = false;
    ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = false;
    });
  }
  disableAllControls();
  if (imgEl.cropperInstance) {
    imgEl.cropperInstance.destroy();
    imgEl.cropperInstance = null;
  }
  cropBtn.onclick = null;

  // تحرير الموارد عند إغلاق المودال
  function cleanup() {
    if (imgEl.cropperInstance) {
      imgEl.cropperInstance.destroy();
      imgEl.cropperInstance = null;
    }
    cropper = null;
    cropBtn.onclick = null;
    if (objectUrl) {
      try { URL.revokeObjectURL(objectUrl); } catch {}
      objectUrl = null;
    }
    if (timeoutTimer) {
      clearTimeout(timeoutTimer);
      timeoutTimer = null;
    }
  }
  modalEl.addEventListener('hidden.bs.modal', function cleanupListener() {
    cleanup();
    modalEl.removeEventListener('hidden.bs.modal', cleanupListener);
  });

  // timeout صارم (60 ثانية)
  timeoutTimer = setTimeout(() => {
    cleanup();
    loaderModal.hide();
    Swal.fire({ icon: 'error', title: 'انتهى الوقت', text: 'لم يتم استكمال قص الصورة خلال الوقت المحدد.' });
    callback(null);
  }, 60000);

  // تحميل الصورة (مع تحرير objectURL)
  const reader = new FileReader();
  reader.onload = function(e) {
    if (objectUrl) { try { URL.revokeObjectURL(objectUrl); } catch {} }
    objectUrl = e.target.result;
    imgEl.src = objectUrl;
    cropperReady = false;
    let tryCount = 0;
    const maxTries = 15;

    function initCropper(force = false) {
      if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
        imgEl.style.maxHeight = '70vh';
        imgEl.style.maxWidth = '95vw';
      } else {
        imgEl.style.maxHeight = '';
        imgEl.style.maxWidth = '';
      }
      if ((imgEl.naturalWidth > 0 && imgEl.naturalHeight > 0) || force) {
        if (imgEl.cropperInstance) {
          imgEl.cropperInstance.destroy();
          imgEl.cropperInstance = null;
        }
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
          ready() {
            cropperReady = true;
            enableAllControls();
            enableCropperControls();
            if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
              try {
                const containerData = cropper.getContainerData();
                const cropBoxWidth = Math.min(containerData.width * 0.9, imgEl.naturalWidth);
                const cropBoxHeight = Math.min(containerData.height * 0.7, imgEl.naturalHeight);
                cropper.setCropBoxData({
                  width: cropBoxWidth,
                  height: cropBoxHeight,
                  left: (containerData.width - cropBoxWidth) / 2,
                  top: (containerData.height - cropBoxHeight) / 2
                });
              } catch(e){}
            }
          }
        });
        imgEl.cropperInstance = cropper;
      } else if (tryCount < maxTries) {
        tryCount++;
        setTimeout(initCropper, 120 * tryCount);
      }
    }
    setTimeout(initCropper, 60);

    cropBtn.onclick = async function() {
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      if (!cropperReady || !cropper || !cropper.getCroppedCanvas) {
        if (isMobileChrome() && tryCount < maxTries) {
          initCropper(true);
          setTimeout(() => cropBtn.onclick(), 350 + 80 * tryCount);
          return;
        }
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'لم يتم تهيئة أداة القص بعد. يرجى الانتظار أو إعادة المحاولة.' });
        cleanup();
        callback(null);
        return;
      }
      if (!imgEl || !imgEl.src || imgEl.naturalWidth === 0 || imgEl.naturalHeight === 0) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'لم يتم تحميل الصورة بشكل صحيح. يرجى إعادة المحاولة أو اختيار صورة أخرى.' });
        cleanup();
        callback(null);
        return;
      }
      const data = cropper.getData(true);
      let canvas;
      try {
        canvas = cropper.getCroppedCanvas({
          width: Math.round(data.width),
          height: Math.round(data.height),
          imageSmoothingQuality: 'high'
        });
      } catch (err) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر قص الصورة. يرجى التأكد من أن الصورة ظاهرة بشكل صحيح ثم أعد المحاولة.' });
        loaderModal.hide();
        cleanup();
        callback(null);
        return;
      }
      if (!canvas || canvas.width === 0 || canvas.height === 0) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر قص الصورة. يرجى التأكد من أن الصورة ظاهرة بشكل صحيح ثم أعد المحاولة.' });
        loaderModal.hide();
        cleanup();
        callback(null);
        return;
      }
      function destroyCanvas(c) {
        try {
          if (c && c.parentNode) c.parentNode.removeChild(c);
        } catch {}
      }
      canvas.toBlob(async function(blob) {
        if (!blob) {
          Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر معالجة الصورة. يرجى إعادة المحاولة أو اختيار صورة أخرى.' });
          loaderModal.hide();
          cleanup();
          callback(null);
          return;
        }
        const originalName = file.name;
        const ext = originalName.substring(originalName.lastIndexOf('.'));
        const base = originalName.replace(ext, '');
        const uniqueSuffix = '_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
        let compressOptions = {
          maxSizeMB: isMobileChrome() ? 0.08 : 0.1,
          maxWidthOrHeight: isMobileChrome() ? 900 : Math.max(canvas.width, canvas.height),
          useWebWorker: true,
          initialQuality: isMobileChrome() ? 0.5 : 0.7,
          fileType: file.type
        };
        let compressedFile = blob;
        let compressedSize = blob.size;
        let maxTries = 7;
        if (blob.size > 100 * 1024) {
          for (let i = 0; i < maxTries && compressedSize > 100 * 1024; i++) {
            compressedFile = await imageCompression(compressedFile, compressOptions);
            compressedSize = compressedFile.size;
            compressOptions.initialQuality = Math.max(0.1, compressOptions.initialQuality - 0.1);
          }
        }
        const newFile = new File([compressedFile], base + uniqueSuffix + '_cropped' + ext, { type: file.type });
        cropBtn.blur && cropBtn.blur();
        setTimeout(() => {
          loaderModal.hide();
          cleanup();
          callback(newFile);
        }, 0);
        setTimeout(() => { loaderModal.hide(); }, 700);
        destroyCanvas(canvas);
      }, file.type, isMobileChrome() ? 0.5 : 0.7);
    };
  };
  reader.readAsDataURL(file);

  const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  bsModal.show();

  modalEl.addEventListener('shown.bs.modal', function onShown() {
    let shownTry = 0;
    function tryInitOnModal() {
      if ((!imgEl.cropperInstance || !imgEl.cropperInstance.getData) && imgEl.naturalWidth > 0) {
        if (imgEl.cropperInstance) {
          imgEl.cropperInstance.destroy();
          imgEl.cropperInstance = null;
        }
        if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
          imgEl.style.maxHeight = '70vh';
          imgEl.style.maxWidth = '95vw';
        } else {
          imgEl.style.maxHeight = '';
          imgEl.style.maxWidth = '';
        }
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
          ready() {
            cropperReady = true;
            enableAllControls();
            enableCropperControls();
            if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
              try {
                const containerData = cropper.getContainerData();
                const cropBoxWidth = Math.min(containerData.width * 0.9, imgEl.naturalWidth);
                const cropBoxHeight = Math.min(containerData.height * 0.7, imgEl.naturalHeight);
                cropper.setCropBoxData({
                  width: cropBoxWidth,
                  height: cropBoxHeight,
                  left: (containerData.width - cropBoxWidth) / 2,
                  top: (containerData.height - cropBoxHeight) / 2
                });
              } catch(e){}
            }
          }
        });
        imgEl.cropperInstance = cropper;
      } else if (shownTry < 8 && imgEl.naturalWidth === 0) {
        shownTry++;
        setTimeout(tryInitOnModal, 120 * shownTry);
      }
    }
    setTimeout(tryInitOnModal, 120);
    modalEl.removeEventListener('shown.bs.modal', onShown);
  });

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
};

</script>


