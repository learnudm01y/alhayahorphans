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
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>

      <!-- Body -->
      <div class="cropper-modal-body">
        <!-- منطقة الصورة مع حاوية محددة -->
        <div class="crop-area">
          <img id="cropperImage" src="" alt="الصورة المراد قصها" />
        </div>

        <!-- منطقة التحكم -->
        {{-- <div class="controls-panel-container">
          <!-- أزرار التحكم في الحركة والتكبير - ترتيب محسن -->
          <div class="controls-section">
            <h6 class="controls-title">أدوات التحكم</h6>

            <!-- أسهم الحركة -->
            <div class="movement-controls">
              <div class="movement-row">
                <button id="cropperMoveUp" class="control-btn movement-btn" title="تحريك للأعلى" disabled>
                  <i class="fas fa-arrow-up"></i>
                </button>
              </div>
              <div class="movement-row">
                <button id="cropperMoveLeft" class="control-btn movement-btn" title="تحريك لليسار" disabled>
                  <i class="fas fa-arrow-left"></i>
                </button>
                <span class="movement-center"></span>
                <button id="cropperMoveRight" class="control-btn movement-btn" title="تحريك لليمين" disabled>
                  <i class="fas fa-arrow-right"></i>
                </button>
              </div>
              <div class="movement-row">
                <button id="cropperMoveDown" class="control-btn movement-btn" title="تحريك للأسفل" disabled>
                  <i class="fas fa-arrow-down"></i>
                </button>
              </div>
            </div>

            <!-- أزرار التكبير والدوران -->
            <div class="action-controls">
              <button id="cropperZoomIn" class="control-btn zoom-btn" title="تكبير" disabled>
                <i class="fas fa-search-plus"></i>
                <span>تكبير</span>
              </button>
              <button id="cropperZoomOut" class="control-btn zoom-btn" title="تصغير" disabled>
                <i class="fas fa-search-minus"></i>
                <span>تصغير</span>
              </button>
              <button id="cropperRotateRight" class="control-btn rotate-btn" title="دوران 90 درجة" disabled>
                <i class="fas fa-sync-alt"></i>
                <span>دوران</span>
              </button>
            </div>
          </div>

        </div> --}}
        <!-- أزرار الحفظ والإلغاء -->
        <div class="action-buttons" style="display: flex !important; visibility: visible !important;">
          <button id="cropperCropBtn" class="btn btn-success btn-lg" disabled style="display: flex !important;">
            <i class="fas fa-check me-2"></i>
            <span>حفظ التعديلات</span>
          </button>
          <button type="button" class="btn btn-danger btn-lg" data-bs-dismiss="modal" style="display: flex !important;">
            <i class="fas fa-times me-2"></i>
            <span>إلغاء</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
