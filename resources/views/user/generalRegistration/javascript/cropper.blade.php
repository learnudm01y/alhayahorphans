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
<!-- Add this inside your <head> -->

{{-- <div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content d-flex flex-column" style="height: 100vh;">
            <div class="modal-header">
                <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="cropper-modal-body">
                <div class="crop-area">
                    <img id="cropperImage" src="" alt="Image to crop">
                </div>

                <div class="controls-panel-container">
                    <div class="controls-grid">
                        <button type="button" class="btn btn-outline-secondary move-up" id="cropperMoveUp" data-bs-toggle="tooltip" title="تحريك للأعلى"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary move-left" id="cropperMoveLeft" data-bs-toggle="tooltip" title="تحريك لليسار"><i class="fas fa-arrow-left"></i></button>
                        <span class="move-center"></span> <button type="button" class="btn btn-outline-secondary move-right" id="cropperMoveRight" data-bs-toggle="tooltip" title="تحريك لليمين"><i class="fas fa-arrow-right"></i></button>
                        <button type="button" class="btn btn-outline-secondary move-down" id="cropperMoveDown" data-bs-toggle="tooltip" title="تحريك للأسفل"><i class="fas fa-arrow-down"></i></button>

                        <button type="button" class="btn btn-outline-secondary zoom-in" id="cropperZoomIn" data-bs-toggle="tooltip" title="تكبير"><i class="fas fa-search-plus"></i></button>
                        <button type="button" class="btn btn-outline-secondary zoom-out" id="cropperZoomOut" data-bs-toggle="tooltip" title="تصغير"><i class="fas fa-search-minus"></i></button>
                        <button type="button" class="btn btn-outline-secondary rotate-right" id="cropperRotateRight" data-bs-toggle="tooltip" title="تدوير"><i class="fas fa-sync-alt"></i></button>
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="btn btn-success" id="cropperCropBtn">قص وحفظ</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">إلغاء</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles for the cropper modal */
    .modal-fullscreen-sm-down .modal-content {
        border-radius: 0;
        margin: 0;
        height: 100vh; /* Ensure modal content fills screen height on small devices */
    }

    .cropper-modal-body {
        /* Use flex to arrange crop area and controls panel */
        display: flex;
        flex-direction: column; /* Stack on mobile by default */
        flex-grow: 1; /* Allow body to take available height */
        padding: 0 !important; /* Remove default padding to maximize space for image */
        overflow: hidden; /* Hide overflow */
    }

    /* Desktop layout: Side-by-side */
    @media (min-width: 768px) { /* Bootstrap's md breakpoint */
        .cropper-modal-body {
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
    }

    .crop-area img {
        display: block; /* Important for Cropper.js */
        /* Use max-width/height here, Cropper.js will handle fitting */
        max-width: 100%;
        max-height: 100%;
        object-fit: contain; /* Ensure image fits without distortion */
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
        min-width: fit-content;
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
    .controls-grid .move-center { grid-column: 2; grid-row: 2; display: none; } /* Placeholder or an action button */
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
        .controls-panel-container {
            flex-direction: row; /* Horizontal on mobile */
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap; /* Allow buttons to wrap */
            padding: 0.5rem;
            border-top: 1px solid #dee2e6; /* Separator from image */
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

<script>
window.showCropperModal = function(file, callback) {
    const modalEl = document.getElementById('cropperModal');
    const imageEl = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    let cropper;

    // Initialize tooltips (re-initialize if modal content changes)
    const initTooltips = () => {
        // Dispose existing tooltips before creating new ones
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            const tooltip = bootstrap.Tooltip.getInstance(el);
            if (tooltip) {
                tooltip.dispose();
            }
            new bootstrap.Tooltip(el);
        });
    };

    // Reset previous cropper instance and button handlers
    const resetCropper = () => {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        if (imageEl.cropperInstance) {
            imageEl.cropperInstance.destroy();
            imageEl.cropperInstance = null;
        }
        cropBtn.onclick = null; // Clear previous click handler
    };

    resetCropper(); // Call reset when showing a new image

    // Read file
    const reader = new FileReader();
    reader.onload = e => {
        imageEl.src = e.target.result;
        // Wait for the image to fully load in the DOM
        imageEl.onload = () => {
            if (cropper) cropper.destroy(); // Destroy previous instance if any lingering

            cropper = new Cropper(imageEl, {
                // Ensure Cropper.js works well with highly vertical images
                viewMode: 1, // Restrict the crop box to stay within the canvas (good default)
                // Try viewMode: 2 or 3 if image is still too small, 3 allows image to be larger than canvas
                responsive: true,
                background: false,
                autoCropArea: 0.8, // Increased auto crop area to 80%
                checkOrientation: true,
                dragMode: 'move',
                guides: true,
                movable: true,
                zoomable: true,
                rotatable: true,
                scalable: true,
                cropBoxResizable: true,
                cropBoxMovable: true,
                toggleDragModeOnDblclick: false, // Prevent accidental drag mode changes

                // **Crucial for maximizing image display**
                ready: function () {
                    // Get current image and container data
                    const imageData = cropper.getImageData();
                    const containerData = cropper.getContainerData();

                    // Calculate aspect ratios
                    const imageAspectRatio = imageData.width / imageData.height;
                    const containerAspectRatio = containerData.width / containerData.height;

                    let canvasWidth, canvasHeight;

                    // If image is taller than container, fit by height
                    if (imageAspectRatio < containerAspectRatio) {
                        canvasHeight = containerData.height;
                        canvasWidth = canvasHeight * imageAspectRatio;
                    }
                    // If image is wider or same aspect ratio, fit by width
                    else {
                        canvasWidth = containerData.width;
                        canvasHeight = canvasWidth / imageAspectRatio;
                    }

                    // Ensure canvas is not larger than actual image (prevent pixelation if scaling up too much)
                    // If the image is very small, we might want to scale it up, but within reason.
                    // For now, let Cropper.js handle natural size and just adjust canvas.
                    // If canvas dimensions exceed natural image dimensions, Cropper will adjust.

                    // Set canvas data to fit the container as much as possible while maintaining aspect ratio
                    cropper.setCanvasData({
                        width: canvasWidth,
                        height: canvasHeight,
                        left: (containerData.width - canvasWidth) / 2,
                        top: (containerData.height - canvasHeight) / 2
                    });

                    // Set initial crop box to cover a significant portion of the image
                    // This ensures the user sees a good crop area from the start.
                    const initialCropBoxWidth = Math.min(imageData.width * 0.9, canvasWidth * 0.9);
                    const initialCropBoxHeight = Math.min(imageData.height * 0.9, canvasHeight * 0.9);

                    cropper.setCropBoxData({
                        width: initialCropBoxWidth,
                        height: initialCropBoxHeight,
                        left: (canvasWidth - initialCropBoxWidth) / 2 + (containerData.width - canvasWidth) / 2,
                        top: (canvasHeight - initialCropBoxHeight) / 2 + (containerData.height - canvasHeight) / 2
                    });

                    // Force an initial zoom to fill the container more aggressively if needed
                    // This can be tricky with viewMode:1, so monitor behavior.
                    // cropper.zoomTo(1); // This zooms the image to 100% of its natural size, might not fill container.
                    // Better to calculate a zoom level that fills the canvas
                    // const naturalRatio = Math.max(containerData.width / imageData.naturalWidth, containerData.height / imageData.naturalHeight);
                    // cropper.zoomTo(naturalRatio * 0.9); // Zoom to fill 90% of container based on natural image size.
                }
            });
            imageEl.cropperInstance = cropper; // Store instance for later access
            initTooltips(); // Initialize tooltips after cropper is ready
            modalEl.dispatchEvent(new Event('cropperReady')); // Custom event for external listeners
        };
    };
    reader.readAsDataURL(file);

    // Show modal
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    // Bind controls - using event listeners for robustness
    const bindCropperControls = () => {
        document.getElementById('cropperMoveUp').onclick = () => cropper.move(0, -10); // Smaller moves for precision
        document.getElementById('cropperMoveDown').onclick = () => cropper.move(0, 10);
        document.getElementById('cropperMoveLeft').onclick = () => cropper.move(-10, 0);
        document.getElementById('cropperMoveRight').onclick = () => cropper.move(10, 0);
        document.getElementById('cropperZoomIn').onclick = () => cropper.zoom(0.1); // Smaller zoom steps
        document.getElementById('cropperZoomOut').onclick = () => cropper.zoom(-0.1);
        document.getElementById('cropperRotateRight').onclick = () => cropper.rotate(15); // Smaller rotation step
    };

    // Bind controls whenever the modal is shown and Cropper is initialized
    modalEl.addEventListener('shown.bs.modal', bindCropperControls, { once: true });
    // Re-bind if a new file is loaded without closing the modal
    imageEl.onload = () => {
        if (cropper) {
            bindCropperControls();
        }
    };


    // Crop & Save
    cropBtn.onclick = () => {
        // Using `getData(true)` gets the cropped area data relative to the original image
        const data = cropper.getData(true);
        const canvas = cropper.getCroppedCanvas({
            width: Math.round(data.width),
            height: Math.round(data.height),
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        // Convert canvas to Blob
        canvas.toBlob(blob => {
            const ext = file.name.slice(file.name.lastIndexOf('.'));
            const base = file.name.replace(ext, '');
            // Create a new File object for the cropped image
            const croppedFile = new File([blob], `${base}_cropped${ext}`, { type: file.type });

            // Hide modal and destroy cropper instance
            bootstrap.Modal.getInstance(modalEl).hide();
            resetCropper(); // Ensure cropper is destroyed cleanly

            // Call the callback function with the cropped file
            callback(croppedFile);
        }, file.type, 0.9); // Use 0.9 for quality (for JPEG/WebP)
    };

    // Handle modal hide to destroy cropper instance cleanly
    modalEl.addEventListener('hidden.bs.modal', resetCropper, { once: true });
};
</script> --}}
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content d-flex flex-column" style="height: 100vh;">
            <div class="modal-header">
                <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="cropper-modal-body">
                <div class="crop-area">
                    <img id="cropperImage" src="" alt="Image to crop">
                </div>

                <div class="controls-panel-container">
                    <div class="controls-grid">
                        <button type="button" class="btn btn-outline-secondary move-up" id="cropperMoveUp" data-bs-toggle="tooltip" title="تحريك للأعلى" disabled><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary move-left" id="cropperMoveLeft" data-bs-toggle="tooltip" title="تحريك لليسار" disabled><i class="fas fa-arrow-left"></i></button>
                        <span class="move-center"></span> <button type="button" class="btn btn-outline-secondary move-right" id="cropperMoveRight" data-bs-toggle="tooltip" title="تحريك لليمين" disabled><i class="fas fa-arrow-right"></i></button>
                        <button type="button" class="btn btn-outline-secondary move-down" id="cropperMoveDown" data-bs-toggle="tooltip" title="تحريك للأسفل" disabled><i class="fas fa-arrow-down"></i></button>

                        <button type="button" class="btn btn-outline-secondary zoom-in" id="cropperZoomIn" data-bs-toggle="tooltip" title="تكبير" disabled><i class="fas fa-search-plus"></i></button>
                        <button type="button" class="btn btn-outline-secondary zoom-out" id="cropperZoomOut" data-bs-toggle="tooltip" title="تصغير" disabled><i class="fas fa-search-minus"></i></button>
                        <button type="button" class="btn btn-outline-secondary rotate-right" id="cropperRotateRight" data-bs-toggle="tooltip" title="تدوير" disabled><i class="fas fa-sync-alt"></i></button>
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="btn btn-success" id="cropperCropBtn" disabled>قص وحفظ</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">إلغاء</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles for the cropper modal */
    .modal-fullscreen-sm-down .modal-content {
        border-radius: 0;
        margin: 0;
        height: 100vh;
    }

    .cropper-modal-body {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        padding: 0 !important;
        overflow: hidden;
    }

    @media (min-width: 768px) {
        .cropper-modal-body {
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
    }

    .crop-area img {
        display: block;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
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
        min-width: fit-content;
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
        .controls-panel-container {
            flex-direction: row;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
            padding: 0.5rem;
            border-top: 1px solid #dee2e6;
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

<script>
window.showCropperModal = function(file, callback) {
    const modalEl = document.getElementById('cropperModal');
    const imageEl = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    const controlButtons = document.querySelectorAll('.controls-grid button');
    let cropperInstance = null; // Use a dedicated variable for the Cropper instance

    // --- Helper Functions ---
    const initTooltips = () => {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            const tooltip = bootstrap.Tooltip.getInstance(el);
            if (tooltip) {
                tooltip.dispose();
            }
            new bootstrap.Tooltip(el);
        });
    };

    const setControlButtonsDisabled = (isDisabled) => {
        controlButtons.forEach(btn => btn.disabled = isDisabled);
        cropBtn.disabled = isDisabled;
    };

    // Named function for crop button click handler to allow proper removal
    const handleCropButtonClick = () => {
        // Crucial check: Ensure cropperInstance is valid BEFORE calling any methods on it
        if (!cropperInstance) {
            console.error("Cropper instance is not initialized when crop button was clicked. Cannot crop.");
            alert("لا يمكن قص الصورة حاليًا. يرجى المحاولة مرة أخرى.");
            setControlButtonsDisabled(true); // Re-disable buttons if somehow enabled
            return;
        }

        try {
            // Attempt to get data
            const data = cropperInstance.getData(true);
            const canvas = cropperInstance.getCroppedCanvas({
                width: Math.round(data.width),
                height: Math.round(data.height),
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            canvas.toBlob(blob => {
                if (!blob) {
                    console.error("Failed to create blob from canvas.");
                    alert("حدث خطأ في معالجة الصورة. يرجى المحاولة مرة أخرى.");
                    resetCropper();
                    bootstrap.Modal.getInstance(modalEl).hide();
                    return;
                }

                const ext = file.name.slice(file.name.lastIndexOf('.'));
                const base = file.name.replace(ext, '');
                const croppedFile = new File([blob], `${base}_cropped${ext}`, { type: file.type });

                bootstrap.Modal.getInstance(modalEl).hide();
                resetCropper(); // Ensure cropper is destroyed cleanly

                callback(croppedFile);
            }, file.type, 0.9);
        } catch (e) {
            console.error("Error during cropping process:", e);
            alert("حدث خطأ غير متوقع أثناء عملية القص. يرجى المحاولة مرة أخرى.");
            resetCropper();
            bootstrap.Modal.getInstance(modalEl).hide();
        }
    };

    // Named functions for control buttons to allow proper removal
    const handleMoveUp = () => cropperInstance && cropperInstance.move(0, -10);
    const handleMoveDown = () => cropperInstance && cropperInstance.move(0, 10);
    const handleMoveLeft = () => cropperInstance && cropperInstance.move(-10, 0);
    const handleMoveRight = () => cropperInstance && cropperInstance.move(10, 0);
    const handleZoomIn = () => cropperInstance && cropperInstance.zoom(0.1);
    const handleZoomOut = () => cropperInstance && cropperInstance.zoom(-0.1);
    const handleRotateRight = () => cropperInstance && cropperInstance.rotate(15);

    // Function to add event listeners to control buttons
    const addCropperControlListeners = () => {
        document.getElementById('cropperMoveUp').addEventListener('click', handleMoveUp);
        document.getElementById('cropperMoveDown').addEventListener('click', handleMoveDown);
        document.getElementById('cropperMoveLeft').addEventListener('click', handleMoveLeft);
        document.getElementById('cropperMoveRight').addEventListener('click', handleMoveRight);
        document.getElementById('cropperZoomIn').addEventListener('click', handleZoomIn);
        document.getElementById('cropperZoomOut').addEventListener('click', handleZoomOut);
        document.getElementById('cropperRotateRight').addEventListener('click', handleRotateRight);
        cropBtn.addEventListener('click', handleCropButtonClick); // Bind main crop button
    };

    // Function to remove event listeners from control buttons
    const removeCropperControlListeners = () => {
        document.getElementById('cropperMoveUp').removeEventListener('click', handleMoveUp);
        document.getElementById('cropperMoveDown').removeEventListener('click', handleMoveDown);
        document.getElementById('cropperMoveLeft').removeEventListener('click', handleMoveLeft);
        document.getElementById('cropperMoveRight').removeEventListener('click', handleMoveRight);
        document.getElementById('cropperZoomIn').removeEventListener('click', handleZoomIn);
        document.getElementById('cropperZoomOut').removeEventListener('click', handleZoomOut);
        document.getElementById('cropperRotateRight').removeEventListener('click', handleRotateRight);
        cropBtn.removeEventListener('click', handleCropButtonClick); // Remove main crop button listener
    };

    // Reset previous cropper instance and all button handlers
    const resetCropper = () => {
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }
        if (imageEl.cropperInstance) {
            imageEl.cropperInstance.destroy(); // Ensure image element's stored instance is also destroyed
            imageEl.cropperInstance = null;
        }
        removeCropperControlListeners(); // Remove all listeners
        setControlButtonsDisabled(true); // Disable all buttons
    };

    // --- Main Logic Flow ---
    resetCropper(); // Always start with a clean slate

    const reader = new FileReader();
    reader.onload = e => {
        imageEl.src = e.target.result;
        imageEl.onload = () => {
            // Ensure no duplicate Cropper instance if image load triggers multiple times
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }

            cropperInstance = new Cropper(imageEl, {
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
                toggleDragModeOnDblclick: false,

                ready: function () {
                    // This `this` inside Cropper's ready refers to the Cropper instance
                    // We assign it to cropperInstance for consistent external access
                    if (!cropperInstance) { // Defensive check
                        console.error("Cropper instance is unexpectedly null in ready callback.");
                        setControlButtonsDisabled(true);
                        return;
                    }

                    const imageData = cropperInstance.getImageData();
                    const containerData = cropperInstance.getContainerData();

                    let canvasWidth, canvasHeight;
                    const imageAspectRatio = imageData.width / imageData.height;
                    const containerAspectRatio = containerData.width / containerData.height;

                    if (imageAspectRatio < containerAspectRatio) {
                        canvasHeight = containerData.height;
                        canvasWidth = canvasHeight * imageAspectRatio;
                        if (canvasWidth > containerData.width) {
                            canvasWidth = containerData.width;
                            canvasHeight = canvasWidth / imageAspectRatio;
                        }
                    } else {
                        canvasWidth = containerData.width;
                        canvasHeight = canvasWidth / imageAspectRatio;
                        if (canvasHeight > containerData.height) {
                            canvasHeight = containerData.height;
                            canvasWidth = canvasHeight / imageAspectRatio; // Fixed potential error here: was dividing by imageAspectRatio
                        }
                    }

                    cropperInstance.setCanvasData({
                        width: canvasWidth,
                        height: canvasHeight,
                        left: (containerData.width - canvasWidth) / 2,
                        top: (containerData.height - canvasHeight) / 2
                    });

                    const cropBoxArea = Math.min(canvasWidth, canvasHeight) * 0.9;
                    const initialCropBoxWidth = Math.min(imageData.width * 0.9, cropBoxArea);
                    const initialCropBoxHeight = Math.min(imageData.height * 0.9, cropBoxArea);

                    cropperInstance.setCropBoxData({
                        width: initialCropBoxWidth,
                        height: initialCropBoxHeight,
                        left: (canvasWidth - initialCropBoxWidth) / 2 + (containerData.width - canvasWidth) / 2,
                        top: (canvasHeight - initialCropBoxHeight) / 2 + (containerData.height - canvasHeight) / 2
                    });

                    setControlButtonsDisabled(false); // Enable all buttons
                    addCropperControlListeners(); // Add listeners now that instance is ready
                    initTooltips();
                    modalEl.dispatchEvent(new Event('cropperReady'));
                },
                error: function(err) {
                    console.error("Cropper.js initialization error:", err);
                    alert("تعذر تهيئة أداة القص. يرجى التأكد من أن الصورة صالحة.");
                    resetCropper();
                    bootstrap.Modal.getInstance(modalEl).hide();
                }
            });
            imageEl.cropperInstance = cropperInstance; // Store instance on image element as well
        };

        imageEl.onerror = () => {
            console.error("Image element failed to load the image src.");
            alert("تعذر تحميل الصورة. قد تكون تالفة أو غير مدعومة.");
            resetCropper();
            bootstrap.Modal.getInstance(modalEl).hide();
        };
    };

    reader.onerror = (e) => {
        console.error("FileReader error during reading file:", e);
        alert("حدث خطأ أثناء قراءة الملف. يرجى المحاولة مرة أخرى.");
        resetCropper();
        bootstrap.Modal.getInstance(modalEl).hide();
    };
    reader.readAsDataURL(file);

    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    // Ensure cleanup happens when modal is hidden
    modalEl.addEventListener('hidden.bs.modal', resetCropper, { once: true });
};
</script>
