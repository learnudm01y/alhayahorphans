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

<!-- Cropper Modal -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog">
    <div class="modal-content">
      <!-- Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="cropperModalLabel">
          <i class="fas fa-crop me-2"></i>قص وتعديل الصورة
        </h5>
      </div>

      <!-- Body - منطقة القص -->
      <div class="cropper-modal-body">
        <div class="crop-area">
          <img id="cropperImage" src="" alt="الصورة المراد قصها" />
        </div>
      </div>

      <!-- Footer - أزرار الحفظ والإلغاء -->
      <div class="action-buttons">
        <button id="cropperCropBtn" class="btn btn-success btn-lg" disabled>
          <i class="fas fa-check me-2"></i>
          <span>حفظ التعديلات</span>
        </button>
        <button type="button" class="btn btn-danger btn-lg" data-bs-dismiss="modal" id="cropperCancelBtn">
          <i class="fas fa-times me-2"></i>
          <span>إلغاء</span>
        </button>
      </div>
    </div>
  </div>
</div>
