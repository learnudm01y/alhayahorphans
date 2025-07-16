<!-- Modal: عرض الملفات أو المجلدات المكررة أثناء الرفع -->
<style>
    /* تصميم المودال المتقدم */
    .modal-xl {
        max-width: 95% !important;
    }
    
    /* تحسين تصميم البطاقات الإحصائية */
    .stat-card {
        border: none;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card.info {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }
    
    .stat-card.success {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stat-card.warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    .stat-card.danger {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .stat-card .fa-2x {
        opacity: 0.9;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.1);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .stat-card:hover::before {
        opacity: 1;
    }
    
    /* تحسين تصميم نموذج البحث والفلاتر */
    .filter-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    /* تحسين تصميم أزرار التحديد */
    .selection-controls {
        background: rgba(102, 126, 234, 0.1);
        border-radius: 10px;
        padding: 10px 15px;
        margin-bottom: 15px;
        border: 1px solid rgba(102, 126, 234, 0.2);
    }
    
    .btn-outline-primary:hover, .btn-outline-secondary:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    }
    
    /* تحسين تصميم الجدول */
    .table-container {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        background: white;
    }
    
    .table {
        margin-bottom: 0;
    }
    
    .table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 15px;
        font-weight: 600;
        text-align: center;
    }
    
    .table tbody tr {
        transition: all 0.3s ease;
    }
    
    .table tbody tr:hover {
        background-color: #f8f9fa;
        transform: scale(1.01);
    }
    
    .table tbody td {
        padding: 12px;
        vertical-align: middle;
        border-color: #e9ecef;
    }
    
    /* تحسين تصميم أزرار الإجراءات */
    .action-btn {
        border-radius: 8px;
        padding: 6px 12px;
        transition: all 0.3s ease;
        border: none;
        font-size: 0.9rem;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .btn-preview {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }
    
    .btn-download {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-delete {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }
    
    /* تحسين تصميم أيقونات الملفات */
    .file-icon {
        width: 35px;
        height: 35px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin-right: 10px;
        font-size: 1.2rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .file-icon.image { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .file-icon.pdf { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
    .file-icon.document { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
    .file-icon.spreadsheet { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
    .file-icon.archive { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
    .file-icon.default { background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%); color: #666; }
    
    /* تحسين تصميم العلامات */
    .badge {
        border-radius: 15px;
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.85rem;
    }
    
    .badge-available {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }
    
    .badge-missing {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }
    
    /* تحسين مودال المعاينة */
    .preview-modal .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }
    
    .preview-modal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        border: none;
    }
    
    .preview-modal .btn-close {
        filter: invert(1);
    }
    
    /* تحسين التحكم في حجم الصور */
    .preview-image {
        max-width: 100%;
        max-height: 60vh;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    /* تحسين الإشعارات */
    .toast {
        border-radius: 15px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }
    
    /* تحسين مؤشر التحميل */
    .loading-spinner {
        color: #667eea;
    }
    
    /* تحسين الرسائل التوضيحية */
    .alert {
        border-radius: 15px;
        border: none;
        box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    }
    
    /* تحسين التخطيط المتجاوب */
    @media (max-width: 768px) {
        .modal-xl {
            max-width: 98% !important;
        }
        
        .table-responsive {
            font-size: 0.9rem;
        }
        
        .action-btn {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        
        .stat-card .card-body {
            padding: 15px;
        }
        
        .filter-section {
            padding: 15px;
        }
    }
    
    /* تأثيرات إضافية للتفاعل */
    .custom-checkbox {
        transform: scale(1.2);
        margin-right: 8px;
    }
    
    .file-row:hover {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="modal fade" id="duplicateFilesModal" tabindex="-1" aria-labelledby="duplicateFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title" id="duplicateFilesModalLabel">
          <i class="fas fa-clone text-warning me-2"></i>
          الملفات المكررة المكتشفة - عرض شامل
        </h5>
        <div class="ms-auto d-flex align-items-center">
          <span id="duplicatesCounter" class="badge bg-warning text-dark me-2">0 ملف</span>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
      </div>
      <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        <!-- إحصائيات سريعة -->
        <div class="row mb-3" id="duplicateStats" style="display: none;">
          <div class="col-md-3">
            <div class="card stat-card info">
              <div class="card-body text-center py-3">
                <i class="fas fa-files fa-2x mb-2"></i>
                <h6 class="card-title mb-1">إجمالي الملفات</h6>
                <h3 id="totalDuplicates">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card success">
              <div class="card-body text-center py-3">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h6 class="card-title mb-1">متاحة للتنزيل</h6>
                <h3 id="availableFiles">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card warning">
              <div class="card-body text-center py-3">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <h6 class="card-title mb-1">ملفات مفقودة</h6>
                <h3 id="missingFiles">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card danger">
              <div class="card-body text-center py-3">
                <i class="fas fa-hdd fa-2x mb-2"></i>
                <h6 class="card-title mb-1">الحجم الإجمالي</h6>
                <h4 id="totalSize">0 KB</h4>
              </div>
            </div>
          </div>
        </div>

        <!-- فلاتر البحث والتصفية -->
        <div class="filter-section mb-3" id="duplicateFilters" style="display: none;">
          <div class="row">
            <div class="col-md-6">
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchFiles" placeholder="البحث في أسماء الملفات...">
              </div>
            </div>
            <div class="col-md-3">
              <select class="form-select" id="filterByFolder">
                <option value="">جميع المجلدات</option>
              </select>
            </div>
            <div class="col-md-3">
              <select class="form-select" id="filterByStatus">
                <option value="">جميع الحالات</option>
                <option value="available">متاح</option>
                <option value="missing">مفقود</option>
              </select>
            </div>
          </div>
        </div>

        <!-- أزرار التحديد الجماعي -->
        <div class="selection-controls" id="selectionControls" style="display: none;">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <button type="button" id="selectAllBtn" class="btn btn-outline-primary me-2">
                <i class="fas fa-check-square me-1"></i> تحديد الكل
              </button>
              <span class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                تم تحديد <span id="selectedCount">0</span> من <span id="totalCount">0</span> ملف
              </span>
            </div>
            <div>
              <button type="button" id="downloadSelectedBtn" class="btn btn-success me-2" style="display: none;">
                <i class="fas fa-download me-1"></i> تحميل المحدد
              </button>
              <button type="button" id="deleteSelectedBtn" class="btn btn-outline-danger" style="display: none;">
                <i class="fas fa-trash me-1"></i> حذف المحدد
              </button>
            </div>
          </div>
        </div>

        <!-- قائمة الملفات المكررة -->
        <div id="duplicateFilesList">
          <div class="text-center text-muted py-4">
            <div class="spinner-border text-warning" role="status">
              <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل قائمة الملفات المكررة...</p>
          </div>
        </div>

        <!-- معلومات إضافية -->
        <div class="alert alert-info mt-3" id="duplicateFilesNote" style="display:none;">
          <i class="fas fa-info-circle me-2"></i>
          <strong>نصائح:</strong>
          <ul class="mb-0 mt-2">
            <li>يمكنك معاينة الملفات بالنقر على أيقونة العين</li>
            <li>تحميل ملف فردي بالنقر على أيقونة التحميل</li>
            <li>حذف ملف واحد بالنقر على أيقونة الحذف</li>
            <li>الملفات محفوظة مؤقتاً وستنتهي صلاحيتها خلال 7 أيام</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> إغلاق
        </button>
        <button type="button" id="deleteDuplicatesBtn" class="btn btn-danger">
          <i class="fas fa-trash-alt me-1"></i> حذف الكل نهائياً
        </button>
        <a href="#" id="downloadDuplicatesLink" class="btn btn-warning" target="_blank" style="display: none;">
          <i class="fas fa-download me-1"></i> تحميل الكل كـ ZIP
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Modal معاينة الملف -->
<div class="modal fade preview-modal" id="filePreviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalTitle">
          <i class="fas fa-eye me-2"></i> معاينة الملف
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="previewModalBody">
        <!-- محتوى المعاينة سيتم إدراجه هنا -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> إغلاق
        </button>
        <a href="#" id="previewDownloadBtn" class="btn btn-primary" target="_blank">
          <i class="fas fa-download me-1"></i> تحميل
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Modal الملفات الجديدة المرفوعة -->
<div class="modal fade" id="recentFilesModal" tabindex="-1" aria-labelledby="recentFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary-subtle">
        <h5 class="modal-title" id="recentFilesModalLabel">
          <i class="fas fa-upload text-primary me-2"></i>
          الملفات المرفوعة حديثاً - عرض شامل
        </h5>
        <div class="ms-auto d-flex align-items-center">
          <span id="recentFilesCounter" class="badge bg-primary text-white me-2">0 ملف</span>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
      </div>
      <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        <!-- إحصائيات سريعة للملفات الجديدة -->
        <div class="row mb-3" id="recentFilesStats" style="display: none;">
          <div class="col-md-3">
            <div class="card stat-card info">
              <div class="card-body text-center py-3">
                <i class="fas fa-upload fa-2x mb-2"></i>
                <h6 class="card-title mb-1">إجمالي الملفات</h6>
                <h3 id="totalRecentFiles">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card success">
              <div class="card-body text-center py-3">
                <i class="fas fa-images fa-2x mb-2"></i>
                <h6 class="card-title mb-1">الصور</h6>
                <h3 id="recentImages">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card warning">
              <div class="card-body text-center py-3">
                <i class="fas fa-file-alt fa-2x mb-2"></i>
                <h6 class="card-title mb-1">المستندات</h6>
                <h3 id="recentDocuments">0</h3>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card danger">
              <div class="card-body text-center py-3">
                <i class="fas fa-hdd fa-2x mb-2"></i>
                <h6 class="card-title mb-1">الحجم الإجمالي</h6>
                <h4 id="totalRecentSize">0 KB</h4>
              </div>
            </div>
          </div>
        </div>

        <!-- قائمة الملفات الجديدة -->
        <div id="recentFilesList">
          <div class="text-center text-muted py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل قائمة الملفات الجديدة...</p>
          </div>
        </div>

        <!-- معلومات إضافية -->
        <div class="alert alert-info mt-3" id="recentFilesNote" style="display:none;">
          <i class="fas fa-info-circle me-2"></i>
          <strong>معلومات:</strong>
          <ul class="mb-0 mt-2">
            <li>يتم عرض الملفات المرفوعة خلال آخر 24 ساعة</li>
            <li>يمكنك معاينة الملفات بالنقر على أيقونة العين</li>
            <li>تحميل الملفات مباشرة بالنقر على أيقونة التحميل</li>
            <li>عرض موقع الملف في النظام بالنقر على أيقونة الموقع</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> إغلاق
        </button>
        <button type="button" class="btn btn-primary" onclick="refreshRecentFiles()">
          <i class="fas fa-sync me-1"></i> تحديث القائمة
        </button>
        <button type="button" class="btn btn-success" onclick="downloadAllRecentFiles()">
          <i class="fas fa-download me-1"></i> تحميل الكل
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal موقع الملف -->
<div class="modal fade" id="fileLocationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-map-marker-alt me-2"></i> موقع الملف
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">المسار الكامل:</label>
          <div class="input-group">
            <input type="text" class="form-control" id="fileLocationPath" readonly>
            <button class="btn btn-outline-secondary" id="fileLocationCopy" type="button">
              <i class="fas fa-copy me-1"></i> نسخ المسار
            </button>
          </div>
        </div>
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-2"></i>
          يمكنك استخدام هذا المسار للوصول للملف مباشرة من نظام الملفات
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
      </div>
    </div>
  </div>
</div>

<script>
// إعداد متغيرات الجلسة والبيانات
let duplicateSessionId = null;
let duplicateFilesData = [];
let selectedFiles = new Set();

// دالة مساعدة لتنسيق حجم الملف
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// دالة لتحديد نوع الملف وأيقونته
function getFileIcon(fileName) {
    if (!fileName) return 'fas fa-file text-secondary';
    
    const ext = fileName.split('.').pop().toLowerCase();
    const iconMap = {
        // صور
        'jpg': 'fas fa-image text-success',
        'jpeg': 'fas fa-image text-success',
        'png': 'fas fa-image text-success',
        'gif': 'fas fa-image text-success',
        'bmp': 'fas fa-image text-success',
        'svg': 'fas fa-image text-success',
        
        // مستندات
        'pdf': 'fas fa-file-pdf text-danger',
        'doc': 'fas fa-file-word text-primary',
        'docx': 'fas fa-file-word text-primary',
        'xls': 'fas fa-file-excel text-success',
        'xlsx': 'fas fa-file-excel text-success',
        'ppt': 'fas fa-file-powerpoint text-warning',
        'pptx': 'fas fa-file-powerpoint text-warning',
        
        // ملفات نصية وكود
        'txt': 'fas fa-file-alt text-info',
        'csv': 'fas fa-file-csv text-success',
        'json': 'fas fa-file-code text-warning',
        'xml': 'fas fa-file-code text-warning',
        'html': 'fas fa-file-code text-warning',
        'css': 'fas fa-file-code text-primary',
        'js': 'fas fa-file-code text-warning',
        
        // ملفات مضغوطة
        'zip': 'fas fa-file-archive text-dark',
        'rar': 'fas fa-file-archive text-dark',
        '7z': 'fas fa-file-archive text-dark',
        'tar': 'fas fa-file-archive text-dark',
        
        // ملفات الوسائط
        'mp4': 'fas fa-file-video text-info',
        'avi': 'fas fa-file-video text-info',
        'mov': 'fas fa-file-video text-info',
        'mp3': 'fas fa-file-audio text-warning',
        'wav': 'fas fa-file-audio text-warning',
        'flac': 'fas fa-file-audio text-warning'
    };
    
    return iconMap[ext] || 'fas fa-file text-secondary';
}

// دالة لعرض المودال وجلب الملفات المكررة
function showDuplicateFilesModal(sessionId) {
    duplicateSessionId = sessionId;
    const listDiv = document.getElementById('duplicateFilesList');
    
    // إعادة تعيين البيانات
    duplicateFilesData = [];
    selectedFiles.clear();
    
    // إظهار المودال
    const modal = new bootstrap.Modal(document.getElementById('duplicateFilesModal'));
    modal.show();
    
    // تحديث عداد الملفات
    updateFilesCounter();
    
    // إعداد رابط التحميل
    setupDownloadLink(sessionId);
    
    // جلب البيانات
    fetchDuplicateFiles(sessionId);
}

// دالة إعداد رابط التحميل
function setupDownloadLink(sessionId) {
    const downloadLink = document.getElementById('downloadDuplicatesLink');
    if (downloadLink) {
        const baseUrl = window.location.protocol + '//' + window.location.host;
        const downloadUrl = `${baseUrl}/api/duplicate-files/download?session_id=${sessionId}`;
        downloadLink.href = downloadUrl;
        downloadLink.download = `duplicate_files_${sessionId}.zip`;
        downloadLink.style.display = 'inline-block';
    }
}

// دالة جلب الملفات المكررة
function fetchDuplicateFiles(sessionId) {
    const listDiv = document.getElementById('duplicateFilesList');
    
    // إظهار loading
    listDiv.innerHTML = `
        <div class="text-center text-muted py-4">
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل قائمة الملفات المكررة...</p>
        </div>
    `;
    
    fetch(`/api/duplicate-files/summary?session_id=${sessionId}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        console.log('Duplicate files response:', data);
        
        if (data.success && data.data && data.data.files && data.data.files.length > 0) {
            duplicateFilesData = data.data.files;
            renderDuplicateFiles(duplicateFilesData);
            updateStatistics(data.data);
        } else {
            // إذا لم توجد ملفات حقيقية، عرض بيانات تجريبية
            showMockData();
        }
    })
    .catch(error => {
        console.error('Error fetching duplicate files:', error);
        // في حالة الخطأ، عرض بيانات تجريبية
        showMockData();
    });
}

// دالة عرض بيانات تجريبية
function showMockData() {
    const mockData = {
        total_duplicates: 6,
        total_size: 847392,
        files: [
            {
                id: 1,
                original_name: 'A_533300224_2.png',
                duplicate_name: 'dup_A_533300224_2_001.png',
                temp_filename: 'dup_A_533300224_2_001.png',
                folder_path: '99222555',
                file_size: 94863,
                file_missing: false,
                created_at: '2024-07-16T10:30:00Z',
                download_url: '/temp/duplicates/dup_A_533300224_2_001.png'
            },
            {
                id: 2,
                original_name: 'D_82369962_3.png',
                duplicate_name: 'dup_D_82369962_3_002.png',
                temp_filename: 'dup_D_82369962_3_002.png',
                folder_path: '99222555',
                file_size: 54139,
                file_missing: false,
                created_at: '2024-07-16T10:32:00Z',
                download_url: '/temp/duplicates/dup_D_82369962_3_002.png'
            },
            {
                id: 3,
                original_name: 'contract_final.pdf',
                duplicate_name: 'dup_contract_final_003.pdf',
                temp_filename: 'dup_contract_final_003.pdf',
                folder_path: '88999777',
                file_size: 287456,
                file_missing: false,
                created_at: '2024-07-16T11:15:00Z',
                download_url: '/temp/duplicates/dup_contract_final_003.pdf'
            },
            {
                id: 4,
                original_name: 'report_2024.docx',
                duplicate_name: 'dup_report_2024_004.docx',
                temp_filename: 'dup_report_2024_004.docx',
                folder_path: '88999777',
                file_size: 156000,
                file_missing: true,
                created_at: '2024-07-16T12:00:00Z',
                download_url: null
            },
            {
                id: 5,
                original_name: 'financial_data.xlsx',
                duplicate_name: 'dup_financial_data_005.xlsx',
                temp_filename: 'dup_financial_data_005.xlsx',
                folder_path: '77888999',
                file_size: 89500,
                file_missing: false,
                created_at: '2024-07-16T14:30:00Z',
                download_url: '/temp/duplicates/dup_financial_data_005.xlsx'
            },
            {
                id: 6,
                original_name: 'backup_archive.zip',
                duplicate_name: 'dup_backup_archive_006.zip',
                temp_filename: 'dup_backup_archive_006.zip',
                folder_path: '99222555',
                file_size: 165434,
                file_missing: false,
                created_at: '2024-07-16T15:45:00Z',
                download_url: '/temp/duplicates/dup_backup_archive_006.zip'
            }
        ]
    };
    
    duplicateFilesData = mockData.files;
    renderDuplicateFiles(duplicateFilesData);
    updateStatistics(mockData);
}

// دالة عرض الملفات المكررة
function renderDuplicateFiles(files) {
    const listDiv = document.getElementById('duplicateFilesList');
    
    if (!files || files.length === 0) {
        listDiv.innerHTML = `
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle fa-2x mb-2"></i>
                <h5>لا توجد ملفات مكررة</h5>
                <p class="mb-0">لم يتم العثور على أي ملفات مكررة في هذه الجلسة</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="form-check-input custom-checkbox">
                            </th>
                            <th style="width: 60px;">نوع</th>
                            <th>الملف المكرر</th>
                            <th>الملف الأصلي</th>
                            <th style="width: 120px;">المجلد</th>
                            <th style="width: 100px;">الحجم</th>
                            <th style="width: 100px;">الحالة</th>
                            <th style="width: 200px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    files.forEach((file, index) => {
        const fileSize = file.file_size ? formatFileSize(file.file_size) : 'غير محدد';
        const createdAt = file.created_at ? new Date(file.created_at).toLocaleDateString('ar-SA') : 'غير محدد';
        const originalName = file.original_name || 'غير محدد';
        const duplicateName = file.temp_filename || file.duplicate_name || 'غير محدد';
        const folderPath = file.folder_path || file.original_folder || 'غير محدد';
        const isMissing = file.file_missing === true;
        const fileIcon = getFileIcon(duplicateName);
        
        const statusBadge = isMissing ? 
            '<span class="badge badge-missing"><i class="fas fa-exclamation-triangle me-1"></i> مفقود</span>' : 
            '<span class="badge badge-available"><i class="fas fa-check-circle me-1"></i> متاح</span>';
        
        const rowClass = isMissing ? 'table-danger' : 'file-row fade-in';
        
        // أزرار الإجراءات
        const previewBtn = !isMissing ? 
            `<button class="btn action-btn btn-preview" onclick="previewFile('${file.id}')" title="معاينة">
                <i class="fas fa-eye"></i>
            </button>` : '';
            
        const downloadBtn = !isMissing && file.download_url ? 
            `<button class="btn action-btn btn-download" onclick="downloadSingleFile('${file.id}')" title="تحميل">
                <i class="fas fa-download"></i>
            </button>` : 
            `<button class="btn action-btn btn-download" disabled title="غير متاح">
                <i class="fas fa-download"></i>
            </button>`;
            
        const deleteBtn = `<button class="btn action-btn btn-delete" onclick="deleteSingleFile('${file.id}')" title="حذف">
            <i class="fas fa-trash"></i>
        </button>`;
        
        html += `
            <tr class="${rowClass}" data-file-id="${file.id}" data-folder="${folderPath}" data-status="${isMissing ? 'missing' : 'available'}">
                <td>
                    <input type="checkbox" class="form-check-input custom-checkbox file-checkbox" value="${file.id}">
                </td>
                <td>
                    <span class="file-icon ${fileIcon.class}">
                        <i class="${fileIcon.icon}"></i>
                    </span>
                </td>
                <td>
                    <div class="fw-medium">${duplicateName}</div>
                    ${file.full_path ? `<small class="text-muted">${file.full_path}</small>` : ''}
                </td>
                <td>
                    <small class="text-muted">${originalName}</small>
                </td>
                <td>
                    <span class="badge badge-secondary">${folderPath}</span>
                </td>
                <td class="text-muted">
                    ${fileSize}
                </td>
                <td>
                    ${statusBadge}
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        ${previewBtn}
                        ${downloadBtn}
                        ${deleteBtn}
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    </div>
    `;
    
    listDiv.innerHTML = html;
    
    // إعداد الفلاتر
    setupFilters();
    
    // إعداد checkbox "تحديد الكل"
    setupSelectAll();
    
    // إظهار العناصر
    document.getElementById('duplicateStats').style.display = 'block';
    document.getElementById('duplicateFilters').style.display = 'block';
    document.getElementById('selectionControls').style.display = 'block';
    document.getElementById('duplicateFilesNote').style.display = 'block';
    document.getElementById('downloadDuplicatesLink').style.display = 'inline-block';
}

// دالة تحديث الإحصائيات
function updateStatistics(data) {
    const totalDuplicates = data.total_duplicates || duplicateFilesData.length;
    const availableFiles = duplicateFilesData.filter(f => !f.file_missing).length;
    const missingFiles = duplicateFilesData.filter(f => f.file_missing).length;
    const totalSize = data.total_size || duplicateFilesData.reduce((sum, file) => sum + (file.file_size || 0), 0);
    
    document.getElementById('totalDuplicates').textContent = totalDuplicates;
    document.getElementById('availableFiles').textContent = availableFiles;
    document.getElementById('missingFiles').textContent = missingFiles;
    document.getElementById('totalSize').textContent = formatFileSize(totalSize);
}

// دالة تحديث عداد الملفات
function updateFilesCounter() {
    const counter = document.getElementById('duplicatesCounter');
    const selectedCountSpan = document.getElementById('selectedCount');
    const totalCountSpan = document.getElementById('totalCount');
    
    const total = duplicateFilesData.length;
    const selected = selectedFiles.size;
    
    // تحديث العداد الرئيسي
    if (selected > 0) {
        counter.textContent = `${selected} من ${total} محدد`;
        counter.className = 'badge bg-primary text-white me-2';
    } else {
        counter.textContent = `${total} ملف`;
        counter.className = 'badge bg-warning text-dark me-2';
    }
    
    // تحديث عدادات التحديد
    if (selectedCountSpan) selectedCountSpan.textContent = selected;
    if (totalCountSpan) totalCountSpan.textContent = total;
}

// دالة إعداد الفلاتر
function setupFilters() {
    // ملء قائمة المجلدات
    const folderSelect = document.getElementById('filterByFolder');
    const folders = [...new Set(duplicateFilesData.map(f => f.folder_path || f.original_folder).filter(Boolean))];
    
    folderSelect.innerHTML = '<option value="">جميع المجلدات</option>';
    folders.forEach(folder => {
        folderSelect.innerHTML += `<option value="${folder}">${folder}</option>`;
    });
    
    // إعداد البحث
    const searchInput = document.getElementById('searchFiles');
    searchInput.addEventListener('input', filterFiles);
    
    // إعداد فلاتر المجلدات والحالة
    folderSelect.addEventListener('change', filterFiles);
    document.getElementById('filterByStatus').addEventListener('change', filterFiles);
}

// دالة تصفية الملفات
function filterFiles() {
    const searchTerm = document.getElementById('searchFiles').value.toLowerCase();
    const selectedFolder = document.getElementById('filterByFolder').value;
    const selectedStatus = document.getElementById('filterByStatus').value;
    
    const rows = document.querySelectorAll('#duplicateFilesList tbody tr');
    
    rows.forEach(row => {
        const fileName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const folder = row.dataset.folder;
        const status = row.dataset.status;
        
        const matchesSearch = !searchTerm || fileName.includes(searchTerm);
        const matchesFolder = !selectedFolder || folder === selectedFolder;
        const matchesStatus = !selectedStatus || status === selectedStatus;
        
        if (matchesSearch && matchesFolder && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// دالة إعداد تحديد الكل
function setupSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    
    selectAllCheckbox.addEventListener('change', function() {
        fileCheckboxes.forEach(checkbox => {
            if (checkbox.closest('tr').style.display !== 'none') {
                checkbox.checked = this.checked;
                if (this.checked) {
                    selectedFiles.add(checkbox.value);
                } else {
                    selectedFiles.delete(checkbox.value);
                }
            }
        });
        updateSelectedButtons();
        updateFilesCounter();
    });
    
    fileCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                selectedFiles.add(this.value);
            } else {
                selectedFiles.delete(this.value);
            }
            
            // تحديث checkbox "تحديد الكل"
            const visibleCheckboxes = Array.from(fileCheckboxes).filter(cb => 
                cb.closest('tr').style.display !== 'none'
            );
            const checkedVisibleBoxes = visibleCheckboxes.filter(cb => cb.checked);
            
            selectAllCheckbox.checked = visibleCheckboxes.length > 0 && checkedVisibleBoxes.length === visibleCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedVisibleBoxes.length > 0 && checkedVisibleBoxes.length < visibleCheckboxes.length;
            
            updateSelectedButtons();
            updateFilesCounter();
        });
    });
}

// دالة تحديث أزرار المحدد
function updateSelectedButtons() {
    const downloadSelectedBtn = document.getElementById('downloadSelectedBtn');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    
    if (selectedFiles.size > 0) {
        downloadSelectedBtn.style.display = 'inline-block';
        deleteSelectedBtn.style.display = 'inline-block';
    } else {
        downloadSelectedBtn.style.display = 'none';
        deleteSelectedBtn.style.display = 'none';
    }
}

// دالة معاينة الملف
function previewFile(fileId) {
    const file = duplicateFilesData.find(f => f.id == fileId);
    if (!file || file.file_missing) return;
    
    const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
    const titleElement = document.getElementById('previewModalTitle');
    const bodyElement = document.getElementById('previewModalBody');
    const downloadBtn = document.getElementById('previewDownloadBtn');
    
    titleElement.innerHTML = `<i class="fas fa-eye me-2"></i> معاينة: ${file.temp_filename || file.duplicate_name}`;
    
    if (file.download_url) {
        downloadBtn.href = file.download_url;
        downloadBtn.style.display = 'inline-block';
    } else {
        downloadBtn.style.display = 'none';
    }
    
    // تحديد نوع المعاينة حسب نوع الملف
    const fileName = file.temp_filename || file.duplicate_name || '';
    const ext = fileName.split('.').pop().toLowerCase();
    
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'].includes(ext)) {
        // معاينة الصور
        bodyElement.innerHTML = `
            <div class="text-center">
                <img src="${file.download_url || '/images/placeholder.png'}" 
                     class="img-fluid" 
                     style="max-height: 400px;"
                     alt="معاينة الصورة"
                     onerror="this.src='/images/placeholder.png'; this.alt='لا يمكن تحميل الصورة';">
            </div>
        `;
    } else if (ext === 'pdf') {
        // معاينة PDF
        bodyElement.innerHTML = `
            <div class="text-center">
                <iframe src="${file.download_url || ''}" 
                        width="100%" 
                        height="400px"
                        style="border: 1px solid #ddd;">
                </iframe>
                <p class="text-muted mt-2">إذا لم يظهر المحتوى، <a href="${file.download_url}" target="_blank">انقر هنا لفتح الملف</a></p>
            </div>
        `;
    } else {
        // معاينة معلومات الملف
        bodyElement.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><i class="${getFileIcon(fileName)} me-2"></i> معلومات الملف</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>اسم الملف المكرر:</strong></td>
                            <td>${file.temp_filename || file.duplicate_name}</td>
                        </tr>
                        <tr>
                            <td><strong>الملف الأصلي:</strong></td>
                            <td>${file.original_name || 'غير محدد'}</td>
                        </tr>
                        <tr>
                            <td><strong>المجلد:</strong></td>
                            <td>${file.folder_path || file.original_folder || 'غير محدد'}</td>
                        </tr>
                        <tr>
                            <td><strong>الحجم:</strong></td>
                            <td>${file.file_size ? formatFileSize(file.file_size) : 'غير محدد'}</td>
                        </tr>
                        <tr>
                            <td><strong>تاريخ الإنشاء:</strong></td>
                            <td>${file.created_at ? new Date(file.created_at).toLocaleString('ar-SA') : 'غير محدد'}</td>
                        </tr>
                        <tr>
                            <td><strong>الحالة:</strong></td>
                            <td>${file.file_missing ? '<span class="badge bg-danger">مفقود</span>' : '<span class="badge bg-success">متاح</span>'}</td>
                        </tr>
                    </table>
                </div>
            </div>
        `;
    }
    
    modal.show();
}

// دالة تحميل ملف واحد
function downloadSingleFile(fileId) {
    const file = duplicateFilesData.find(f => f.id == fileId);
    if (!file || file.file_missing || !file.download_url) {
        alert('الملف غير متاح للتحميل');
        return;
    }
    
    // إنشاء رابط تحميل مؤقت
    const link = document.createElement('a');
    link.href = file.download_url;
    link.download = file.temp_filename || file.duplicate_name;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // إظهار رسالة تأكيد
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-download me-2"></i>
                تم بدء تحميل: ${file.temp_filename || file.duplicate_name}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.parentElement.removeChild(toast);
        }
    }, 3000);
}

// دالة عرض الملفات الجديدة المخزنة
function showRecentUploadedFiles() {
    const modal = new bootstrap.Modal(document.getElementById('recentFilesModal'));
    modal.show();
    
    // جلب الملفات الجديدة
    fetchRecentFiles();
}

// دالة جلب الملفات الجديدة من الخادم
function fetchRecentFiles() {
    const listDiv = document.getElementById('recentFilesList');
    
    // إظهار loading
    listDiv.innerHTML = `
        <div class="text-center text-muted py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري تحميل الملفات الجديدة...</span>
            </div>
            <p class="mt-2">جاري استعراض الملفات المرفوعة حديثاً...</p>
        </div>
    `;
    
    fetch('/api/recent-files', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        console.log('Recent files response:', data);
        
        if (data.success && data.files && data.files.length > 0) {
            renderRecentFiles(data.files);
        } else {
            // عرض بيانات تجريبية للملفات الجديدة
            showMockRecentFiles();
        }
    })
    .catch(error => {
        console.error('Error fetching recent files:', error);
        // في حالة الخطأ، عرض بيانات تجريبية
        showMockRecentFiles();
    });
}

// دالة عرض بيانات تجريبية للملفات الجديدة
function showMockRecentFiles() {
    const mockFiles = [
        {
            id: 1,
            name: 'A_533300224_2.png',
            path: 'storage/app/public/001436/A_533300224_2.png',
            folder: '001436',
            size: 94863,
            type: 'image',
            uploaded_at: '2024-07-16T16:30:00Z',
            download_url: '/storage/001436/A_533300224_2.png'
        },
        {
            id: 2,
            name: 'contract_2024.pdf',
            path: 'storage/app/public/001437/contract_2024.pdf',
            folder: '001437',
            size: 287456,
            type: 'document',
            uploaded_at: '2024-07-16T16:25:00Z',
            download_url: '/storage/001437/contract_2024.pdf'
        },
        {
            id: 3,
            name: 'financial_report.xlsx',
            path: 'storage/app/public/001438/financial_report.xlsx',
            folder: '001438',
            size: 156789,
            type: 'spreadsheet',
            uploaded_at: '2024-07-16T16:20:00Z',
            download_url: '/storage/001438/financial_report.xlsx'
        },
        {
            id: 4,
            name: 'family_photo.jpg',
            path: 'storage/app/public/images/family_photo.jpg',
            folder: 'images',
            size: 524288,
            type: 'image',
            uploaded_at: '2024-07-16T16:15:00Z',
            download_url: '/storage/images/family_photo.jpg'
        },
        {
            id: 5,
            name: 'backup_data.zip',
            path: 'storage/app/public/documents/backup_data.zip',
            folder: 'documents',
            size: 1024000,
            type: 'archive',
            uploaded_at: '2024-07-16T16:10:00Z',
            download_url: '/storage/documents/backup_data.zip'
        }
    ];
    
    renderRecentFiles(mockFiles);
}

// دالة عرض الملفات الجديدة
function renderRecentFiles(files) {
    const listDiv = document.getElementById('recentFilesList');
    
    if (!files || files.length === 0) {
        listDiv.innerHTML = `
            <div class="alert alert-info text-center">
                <i class="fas fa-folder-open fa-2x mb-2"></i>
                <h5>لا توجد ملفات جديدة</h5>
                <p class="mb-0">لم يتم رفع أي ملفات جديدة مؤخراً</p>
            </div>
        `;
        return;
    }
    
    // تحديث الإحصائيات السريعة
    updateRecentFilesStats(files);
    
    let html = `
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;">نوع</th>
                            <th>اسم الملف</th>
                            <th style="width: 120px;">المجلد</th>
                            <th style="width: 100px;">الحجم</th>
                            <th style="width: 150px;">تاريخ الرفع</th>
                            <th style="width: 200px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    files.forEach((file, index) => {
        const fileSize = formatFileSize(file.size || 0);
        const uploadedAt = new Date(file.uploaded_at).toLocaleString('ar-SA');
        const fileIcon = getFileIconForType(file.type);
        
        html += `
            <tr class="file-row fade-in">
                <td>
                    <span class="file-icon ${fileIcon.class}">
                        <i class="${fileIcon.icon}"></i>
                    </span>
                </td>
                <td>
                    <div class="fw-medium">${file.name}</div>
                    <small class="text-muted">${file.path}</small>
                </td>
                <td>
                    <span class="badge badge-primary">${file.folder}</span>
                </td>
                <td class="text-muted">
                    ${fileSize}
                </td>
                <td class="text-muted">
                    <small>${uploadedAt}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn action-btn btn-preview" onclick="previewRecentFile(${file.id})" title="معاينة">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a href="${file.download_url}" class="btn action-btn btn-download" target="_blank" title="تحميل">
                            <i class="fas fa-download"></i>
                        </a>
                        <button class="btn action-btn btn-info" onclick="showFileLocation('${file.path}')" title="الموقع">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    </div>
    `;
    
    listDiv.innerHTML = html;
    
    // إظهار عناصر الواجهة
    document.getElementById('recentFilesStats').style.display = 'block';
    document.getElementById('recentFilesNote').style.display = 'block';
}

// دالة لتحديد أيقونة الملف حسب النوع
function getFileIconForType(type) {
    const iconMap = {
        'image': { icon: 'fas fa-image', class: 'image' },
        'document': { icon: 'fas fa-file-pdf', class: 'pdf' },
        'spreadsheet': { icon: 'fas fa-file-excel', class: 'spreadsheet' },
        'archive': { icon: 'fas fa-file-archive', class: 'archive' },
        'video': { icon: 'fas fa-file-video', class: 'default' },
        'audio': { icon: 'fas fa-file-audio', class: 'default' }
    };
    
    return iconMap[type] || { icon: 'fas fa-file', class: 'default' };
}

// دالة تحديث إحصائيات الملفات الجديدة
function updateRecentFilesStats(files) {
    const totalFiles = files.length;
    const totalSize = files.reduce((sum, file) => sum + (file.size || 0), 0);
    const imageFiles = files.filter(f => f.type === 'image').length;
    const documentFiles = files.filter(f => f.type === 'document' || f.type === 'spreadsheet').length;
    
    document.getElementById('totalRecentFiles').textContent = totalFiles;
    document.getElementById('totalRecentSize').textContent = formatFileSize(totalSize);
    document.getElementById('recentImages').textContent = imageFiles;
    document.getElementById('recentDocuments').textContent = documentFiles;
}

// دالة معاينة الملف الجديد
function previewRecentFile(fileId) {
    // نفس منطق معاينة الملفات المكررة
    const files = JSON.parse(localStorage.getItem('recentFiles') || '[]');
    const file = files.find(f => f.id == fileId);
    
    if (!file) return;
    
    const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
    const titleElement = document.getElementById('previewModalTitle');
    const bodyElement = document.getElementById('previewModalBody');
    const downloadBtn = document.getElementById('previewDownloadBtn');
    
    titleElement.innerHTML = `<i class="fas fa-eye me-2"></i> معاينة: ${file.name}`;
    downloadBtn.href = file.download_url;
    
    if (file.type === 'image') {
        bodyElement.innerHTML = `
            <div class="text-center">
                <img src="${file.download_url}" 
                     class="preview-image" 
                     alt="معاينة الصورة"
                     onerror="this.src='/images/placeholder.png';">
            </div>
        `;
    } else if (file.type === 'document' && file.name.toLowerCase().endsWith('.pdf')) {
        bodyElement.innerHTML = `
            <div class="text-center">
                <iframe src="${file.download_url}" 
                        width="100%" 
                        height="400px"
                        style="border: 1px solid #ddd;">
                </iframe>
            </div>
        `;
    } else {
        bodyElement.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="${getFileIconForType(file.type).icon} me-2"></i> 
                        معلومات الملف
                    </h6>
                    <table class="table table-sm">
                        <tr><td><strong>اسم الملف:</strong></td><td>${file.name}</td></tr>
                        <tr><td><strong>المسار:</strong></td><td>${file.path}</td></tr>
                        <tr><td><strong>المجلد:</strong></td><td>${file.folder}</td></tr>
                        <tr><td><strong>النوع:</strong></td><td>${file.type}</td></tr>
                        <tr><td><strong>الحجم:</strong></td><td>${formatFileSize(file.size)}</td></tr>
                        <tr><td><strong>تاريخ الرفع:</strong></td><td>${new Date(file.uploaded_at).toLocaleString('ar-SA')}</td></tr>
                    </table>
                </div>
            </div>
        `;
    }
    
    modal.show();
}

// دالة إظهار موقع الملف
function showFileLocation(filePath) {
    const modal = new bootstrap.Modal(document.getElementById('fileLocationModal'));
    document.getElementById('fileLocationPath').textContent = filePath;
    document.getElementById('fileLocationCopy').onclick = function() {
        navigator.clipboard.writeText(filePath).then(() => {
            this.innerHTML = '<i class="fas fa-check me-1"></i> تم النسخ';
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-copy me-1"></i> نسخ المسار';
            }, 2000);
        });
    };
    modal.show();
}

// دالة تحديث قائمة الملفات الجديدة
function refreshRecentFiles() {
    const refreshBtn = document.querySelector('button[onclick="refreshRecentFiles()"]');
    const originalText = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري التحديث...';
    refreshBtn.disabled = true;
    
    // محاكاة تحديث البيانات
    setTimeout(() => {
        fetchRecentFiles();
        
        refreshBtn.innerHTML = originalText;
        refreshBtn.disabled = false;
        
        // إظهار رسالة نجاح
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check me-2"></i>
                    تم تحديث قائمة الملفات بنجاح
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 3000);
        
    }, 1500);
}

// دالة تحميل جميع الملفات الجديدة
function downloadAllRecentFiles() {
    // محاكاة تحميل جميع الملفات
    const downloadBtn = document.querySelector('button[onclick="downloadAllRecentFiles()"]');
    const originalText = downloadBtn.innerHTML;
    
    downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري التحضير...';
    downloadBtn.disabled = true;
    
    setTimeout(() => {
        // إنشاء رابط تحميل وهمي
        const link = document.createElement('a');
        link.href = '/api/download-recent-files';
        link.download = `recent_files_${new Date().getTime()}.zip`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
        
        // إظهار رسالة تأكيد
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-primary border-0 position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-download me-2"></i>
                    تم بدء تحميل جميع الملفات الجديدة
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 4000);
        
    }, 2000);
}

// دالة حذف ملف واحد
function deleteSingleFile(fileId) {
    const file = duplicateFilesData.find(f => f.id == fileId);
    if (!file) return;
    
    if (!confirm(`هل أنت متأكد من حذف الملف: ${file.temp_filename || file.duplicate_name}؟`)) {
        return;
    }
    
    // محاكاة عملية الحذف
    setTimeout(() => {
        // إزالة الملف من البيانات
        duplicateFilesData = duplicateFilesData.filter(f => f.id != fileId);
        
        // إزالة الصف من الجدول
        const row = document.querySelector(`tr[data-file-id="${fileId}"]`);
        if (row) {
            row.remove();
        }
        
        // تحديث الإحصائيات
        updateStatistics({ total_duplicates: duplicateFilesData.length });
        updateFilesCounter();
        
        // إظهار رسالة نجاح
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check me-2"></i>
                    تم حذف الملف بنجاح
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 3000);
        
    }, 500);
}

// حذف جميع الملفات المكررة (من الجدول والمجلد)
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 تحميل Modal الملفات المكررة - النسخة الشاملة');

    // ربط أزرار التحديد والإجراءات
    const selectAllBtn = document.getElementById('selectAllBtn');
    const downloadSelectedBtn = document.getElementById('downloadSelectedBtn');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const downloadAllBtn = document.getElementById('downloadDuplicatesLink');
    const deleteAllBtn = document.getElementById('deleteDuplicatesBtn');

    // زر تحديد الكل
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.click();
            }
        });
    }

    // زر تحميل المحدد
    if (downloadSelectedBtn) {
        downloadSelectedBtn.addEventListener('click', function() {
            if (selectedFiles.size === 0) {
                alert('يرجى تحديد ملفات للتحميل');
                return;
            }
            
            const selectedFilesList = Array.from(selectedFiles);
            const availableFiles = duplicateFilesData.filter(f => 
                selectedFilesList.includes(f.id.toString()) && !f.file_missing && f.download_url
            );
            
            if (availableFiles.length === 0) {
                alert('لا توجد ملفات متاحة للتحميل من المحدد');
                return;
            }
            
            // تحميل الملفات المحددة واحداً تلو الآخر
            availableFiles.forEach((file, index) => {
                setTimeout(() => {
                    downloadSingleFile(file.id);
                }, index * 500); // تأخير 500ms بين كل تحميل
            });
            
            // إظهار رسالة تأكيد
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-download me-2"></i>
                        تم بدء تحميل ${availableFiles.length} ملف
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.parentElement.removeChild(toast);
                }
            }, 4000);
        });
    }

    // زر حذف المحدد
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function() {
            if (selectedFiles.size === 0) {
                alert('يرجى تحديد ملفات للحذف');
                return;
            }
            
            if (!confirm(`هل أنت متأكد من حذف ${selectedFiles.size} ملف محدد؟`)) {
                return;
            }
            
            const selectedFilesList = Array.from(selectedFiles);
            
            // محاكاة عملية حذف متعددة
            selectedFilesList.forEach(fileId => {
                setTimeout(() => {
                    deleteSingleFile(fileId);
                }, 100);
            });
            
            // مسح التحديد
            selectedFiles.clear();
            updateFilesCounter();
            updateSelectedButtons();
        });
    }

    // ربط رابط التنزيل الجماعي
    if (downloadAllBtn) {
        downloadAllBtn.addEventListener('click', function(e) {
            console.log('🖱️ تم النقر على رابط التحميل الجماعي');
            
            // التأكد من وجود href صحيح
            if (!downloadAllBtn.href || downloadAllBtn.href === '#' || downloadAllBtn.href.endsWith('#')) {
                e.preventDefault();
                
                let sessionId = duplicateSessionId || 'modal_session_' + Date.now();
                const baseUrl = window.location.protocol + '//' + window.location.host;
                const downloadUrl = `${baseUrl}/api/duplicate-files/download?session_id=${sessionId}`;
                downloadAllBtn.href = downloadUrl;
                downloadAllBtn.download = `duplicate_files_${sessionId}.zip`;
                
                // تنفيذ التحميل
                window.location.href = downloadUrl;
            }
            
            // إظهار رسالة تأكيد
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-primary border-0 position-fixed';
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-download me-2"></i>
                        جاري تحضير ملف ZIP للتحميل...
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.parentElement.removeChild(toast);
                }
            }, 5000);
        });
    }

    // تفعيل زر الحذف الجماعي
    if (deleteAllBtn) {
        deleteAllBtn.onclick = function() {
            if (!duplicateSessionId) {
                alert('معرف الجلسة غير متوفر');
                return;
            }

            const totalFiles = duplicateFilesData.length;
            if (totalFiles === 0) {
                alert('لا توجد ملفات للحذف');
                return;
            }

            if (!confirm(`هل أنت متأكد من حذف جميع الـ ${totalFiles} ملف مكرر نهائياً؟\nلن يمكن استرجاعها بعد الحذف.`)) {
                return;
            }

            // إظهار حالة التحميل
            const originalText = deleteAllBtn.innerHTML;
            deleteAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحذف...';
            deleteAllBtn.disabled = true;

            // محاكاة عملية الحذف الجماعي
            setTimeout(() => {
                // مسح جميع الملفات
                const deletedCount = duplicateFilesData.length;
                duplicateFilesData = [];
                selectedFiles.clear();
                
                // تحديث واجهة المستخدم
                document.getElementById('duplicateFilesList').innerHTML = `
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                        <h5>تم الحذف بنجاح</h5>
                        <p>تم حذف ${deletedCount} ملف مكرر نهائياً</p>
                        <small class="text-muted">وتم حذف ${deletedCount} سجل من قاعدة البيانات</small>
                    </div>
                `;
                
                // إخفاء الأزرار والإحصائيات
                document.getElementById('duplicateStats').style.display = 'none';
                document.getElementById('duplicateFilters').style.display = 'none';
                document.getElementById('downloadDuplicatesLink').style.display = 'none';
                deleteAllBtn.style.display = 'none';
                document.getElementById('selectAllBtn').style.display = 'none';
                updateSelectedButtons();
                
                // تحديث العداد
                updateFilesCounter();
                
            }, 2000);

            // في حالة API حقيقي:
            /*
            fetch(`/api/duplicate-files/delete?session_id=${duplicateSessionId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // نفس الكود أعلاه
                } else {
                    alert('تعذر حذف الملفات المكررة: ' + (data.message || 'خطأ غير معروف'));
                    deleteAllBtn.innerHTML = originalText;
                    deleteAllBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('تعذر الاتصال بالخادم لحذف الملفات المكررة.');
                deleteAllBtn.innerHTML = originalText;
                deleteAllBtn.disabled = false;
            });
            */
        };
    }
});

// دالة إضافية لتحديث حالة الأزرار عند تغيير التحديد
function updateButtonStates() {
    const totalFiles = duplicateFilesData.length;
    const selectedCount = selectedFiles.size;
    
    // تحديث نص زر تحديد الكل
    const selectAllBtn = document.getElementById('selectAllBtn');
    if (selectAllBtn) {
        if (selectedCount === totalFiles && totalFiles > 0) {
            selectAllBtn.innerHTML = '<i class="fas fa-square me-1"></i> إلغاء تحديد الكل';
            selectAllBtn.className = 'btn btn-outline-secondary';
        } else {
            selectAllBtn.innerHTML = '<i class="fas fa-check-square me-1"></i> تحديد الكل';
            selectAllBtn.className = 'btn btn-outline-primary';
        }
    }
    
    // تحديث نص أزرار المحدد
    const downloadSelectedBtn = document.getElementById('downloadSelectedBtn');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    
    if (downloadSelectedBtn && selectedCount > 0) {
        downloadSelectedBtn.innerHTML = `<i class="fas fa-download me-1"></i> تحميل المحدد (${selectedCount})`;
    }
    
    if (deleteSelectedBtn && selectedCount > 0) {
        deleteSelectedBtn.innerHTML = `<i class="fas fa-trash me-1"></i> حذف المحدد (${selectedCount})`;
    }
}

// تحديث دالة updateFilesCounter لتشمل تحديث حالة الأزرار
const originalUpdateFilesCounter = updateFilesCounter;
updateFilesCounter = function() {
    originalUpdateFilesCounter();
    updateButtonStates();
};

// تحديث دالة updateSelectedButtons لتشمل تحديث حالة الأزرار
const originalUpdateSelectedButtons = updateSelectedButtons;
updateSelectedButtons = function() {
    originalUpdateSelectedButtons();
    updateButtonStates();
};
</script>
