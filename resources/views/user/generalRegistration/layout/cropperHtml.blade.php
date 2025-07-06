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

<!-- Cropper Modal - محسن للجوال -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <!-- Header -->
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="cropperModalLabel">
          <i class="fas fa-crop me-2"></i>قص وتعديل الصورة
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>

      <!-- Body -->
      <div class="cropper-modal-body p-3">
        <!-- منطقة الصورة مع حاوية محددة -->
        <div class="crop-area mb-3" style="
          width: 100%;
          height: 400px;
          background: #f8f9fa;
          border: 2px dashed #dee2e6;
          border-radius: 8px;
          overflow: hidden;
          position: relative;
          display: flex;
          align-items: center;
          justify-content: center;
        ">
          <img id="cropperImage" src="" alt="الصورة المراد قصها" style="
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
          " />
        </div>

        <!-- أزرار الحفظ والإلغاء -->
        <div class="action-buttons d-flex gap-3 justify-content-center">
          <button id="cropperCropBtn" class="btn btn-success btn-lg flex-fill" disabled>
            <i class="fas fa-check me-2"></i>
            <span>حفظ التعديلات</span>
          </button>
          <button type="button" class="btn btn-secondary btn-lg flex-fill" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>
            <span>إلغاء</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
