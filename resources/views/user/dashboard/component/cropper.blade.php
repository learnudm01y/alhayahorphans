<!-- ===========================
   HTML: ضع هذا في الـ layout العام (مرة واحدة)
=========================== -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">قص الصورة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <img id="cropperImage" src="" alt="صورة للقص">
        </div>
        <div class="modal-footer">
          <button id="cropperSaveBtn" class="btn btn-primary">حفظ الصورة</button>
          <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        </div>
      </div>
    </div>
  </div>
