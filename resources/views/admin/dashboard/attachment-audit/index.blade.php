@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-6">
        <h1 class="h3 fw-bold">
            <i class="fas fa-search-plus me-2 text-primary"></i>
            فحص المرفقات
        </h1>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-light">
            <i class="fas fa-arrow-right me-2"></i>رجوع
        </a>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs nav-tabs-solid nav-tabs-line-border-2 border-primary mb-5" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#duplicates-tab" type="button" role="tab">
                <i class="fas fa-copy me-2"></i>الملفات المكررة
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#broken-links-tab" type="button" role="tab">
                <i class="fas fa-unlink me-2"></i>الروابط المكسورة
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#persons-tab" type="button" role="tab">
                <i class="fas fa-users me-2"></i>المعيلين والأفراد والمتوفين
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#orphan-tab" type="button" role="tab">
                <i class="fas fa-file-upload me-2"></i>ملفات بدون سجل
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ===== تبويب المكررات ===== --}}
        <div class="tab-pane fade show active" id="duplicates-tab" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-copy me-2 text-warning"></i>
                        كشف المكررات حسب رقم الهوية + نوع الوثيقة
                    </h5>
                    <button class="btn btn-primary" id="btn-find-duplicates">
                        <i class="fas fa-search me-2"></i>بدء الفحص
                    </button>
                </div>
                <div class="card-body">
                    <div id="duplicates-loading" class="text-center py-10" style="display:none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">جاري فحص المكررات...</p>
                    </div>

                    <div id="duplicates-stats" style="display:none;">
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card border-primary border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-primary mb-0" id="dup-total-groups">0</h3>
                                        <small class="text-muted">مجموعات</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-warning border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-warning mb-0" id="dup-total-count">0</h3>
                                        <small class="text-muted">إجمالي السجلات</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-danger border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-danger mb-0" id="dup-total-delete">0</h3>
                                        <small class="text-muted">للحذف</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-info mb-0" id="dup-selected-count">0</h3>
                                        <small class="text-muted">محددة للحذف</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <button class="btn btn-danger" id="btn-delete-selected">
                                <i class="fas fa-trash me-2"></i>حذف المحددة
                            </button>
                            <button class="btn btn-warning" id="btn-delete-keep-newest">
                                <i class="fas fa-clock me-2"></i>حذف القديم (الاحتفاظ بالأحدث)
                            </button>
                            <button class="btn btn-outline-warning" id="btn-delete-keep-oldest">
                                <i class="fas fa-history me-2"></i>حذف الجديد (الاحتفاظ بالأقدم)
                            </button>
                            <button class="btn btn-outline-secondary ms-auto" id="btn-select-none">
                                <i class="fas fa-times me-2"></i>إلغاء التحديد
                            </button>
                            <button class="btn btn-success" id="btn-export-duplicates">
                                <i class="fas fa-file-excel me-2"></i>تصدير CSV
                            </button>
                        </div>

                        <div id="duplicates-container"></div>
                    </div>

                    <div id="duplicates-empty" class="text-center py-10 text-muted">
                        <i class="fas fa-copy text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3">اضغط "بدء الفحص" لاكتشاف المكررات</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== تبويب الروابط المكسورة ===== --}}
        <div class="tab-pane fade" id="broken-links-tab" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-unlink me-2 text-danger"></i>
                        فحص صحة روابط المرفقات
                    </h5>
                    <button class="btn btn-primary" id="btn-check-broken">
                        <i class="fas fa-search me-2"></i>بدء الفحص
                    </button>
                </div>
                <div class="card-body">
                    <div id="broken-loading" class="text-center py-10" style="display:none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">جاري فحص الروابط...</p>
                    </div>

                    <div id="broken-stats" style="display:none;">
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card border-primary border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-primary mb-0" id="brk-total">0</h3>
                                        <small class="text-muted">إجمالي المرفقات</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-success mb-0" id="brk-valid">0</h3>
                                        <small class="text-muted">سليمة</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-danger border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-danger mb-0" id="brk-broken">0</h3>
                                        <small class="text-muted">مكسورة</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-secondary border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-secondary mb-0" id="brk-skipped">0</h3>
                                        <small class="text-muted">بدون مسار</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mb-4">
                            <button class="btn btn-danger" id="btn-delete-all-broken">
                                <i class="fas fa-trash-alt me-2"></i>حذف كل الروابط المكسورة
                            </button>
                            <button class="btn btn-success" id="btn-export-broken">
                                <i class="fas fa-file-excel me-2"></i>تصدير CSV
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:40px;">
                                            <input type="checkbox" id="select-all-broken" class="form-check-input">
                                        </th>
                                        <th class="text-center">ID</th>
                                        <th>رقم الهوية</th>
                                        <th>اسم الملف</th>
                                        <th>المسار</th>
                                        <th class="text-center">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody id="broken-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="broken-empty" class="text-center py-10 text-muted">
                        <i class="fas fa-check-circle text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3">اضغط "بدء الفحص" للتحقق من صحة الروابط</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== تبويب المعيلين والأفراد والمتوفين ===== --}}
        <div class="tab-pane fade" id="persons-tab" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-users me-2 text-info"></i>
                        فحص بيانات المعيلين والأفراد والمتوفين
                    </h5>
                    <button class="btn btn-primary" id="btn-find-persons">
                        <i class="fas fa-search me-2"></i>بدء الفحص
                    </button>
                </div>
                <div class="card-body">
                    <div id="persons-loading" class="text-center py-10" style="display:none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">جاري فحص البيانات...</p>
                    </div>

                    <div id="persons-stats" style="display:none;">
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card border-primary border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-primary mb-0" id="prs-total">0</h3>
                                        <small class="text-muted">إجمالي الأشخاص</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-success mb-0" id="prs-with">0</h3>
                                        <small class="text-muted">لديهم مرفقات</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-danger border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-danger mb-0" id="prs-without">0</h3>
                                        <small class="text-muted">بدون مرفقات</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-info mb-0" id="prs-in-spons">0</h3>
                                        <small class="text-muted">موجود في الكفالات</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mb-4">
                            <button class="btn btn-success" id="btn-export-persons">
                                <i class="fas fa-file-excel me-2"></i>تصدير Excel
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:50px;">#</th>
                                        <th>رقم الهوية</th>
                                        <th>الاسم الكامل</th>
                                        <th>نوع الشخص</th>
                                        <th class="text-center">موجود في الكفالات</th>
                                        <th class="text-center">عدد المرفقات</th>
                                    </tr>
                                </thead>
                                <tbody id="persons-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="persons-empty" class="text-center py-10 text-muted">
                        <i class="fas fa-users text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3">اضغط "بدء الفحص" لفحص بيانات المعيلين والأفراد والمتوفين</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== تبويب الملفات بدون سجل ===== --}}
        <div class="tab-pane fade" id="orphan-tab" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-file-upload me-2 text-success"></i>
                        الملفات الموجودة على القرص بدون سجل في قاعدة البيانات
                    </h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary" id="btn-find-orphan">
                            <i class="fas fa-search me-2"></i>بدء الفحص
                        </button>
                        <button class="btn btn-success" id="btn-add-all-orphan" style="display:none;">
                            <i class="fas fa-plus-circle me-2"></i>إضافة الكل
                        </button>
                        <button class="btn btn-info" id="btn-add-selected-orphan" style="display:none;">
                            <i class="fas fa-plus me-2"></i>إضافة المحدد
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="orphan-loading" class="text-center py-10" style="display:none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">جاري فحص الملفات على القرص...</p>
                    </div>

                    <div id="orphan-stats" style="display:none;">
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card border-primary border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-primary mb-0" id="orph-total-disk">0</h3>
                                        <small class="text-muted">إجمالي الملفات على القرص</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-success mb-0" id="orph-in-db">0</h3>
                                        <small class="text-muted">مسجلة في DB</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-danger border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-danger mb-0" id="orph-count">0</h3>
                                        <small class="text-muted">بدون سجل</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-info mb-0" id="orph-added">0</h3>
                                        <small class="text-muted">تمت إضافتها</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="text-center" style="width:40px;">
                                            <input type="checkbox" id="select-all-orphan" class="form-check-input">
                                        </th>
                                        <th class="text-center">#</th>
                                        <th>مسار الملف</th>
                                        <th class="text-center">الحجم</th>
                                        <th class="text-center">النوع</th>
                                        <th class="text-center">رقم الهوية</th>
                                        <th class="text-center">رقم الملف</th>
                                        <th class="text-center">النوع (ID)</th>
                                        <th class="text-center">الإجراء</th>
                                    </tr>
                                </thead>
                                <tbody id="orphan-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="orphan-empty" class="text-center py-10 text-muted">
                        <i class="fas fa-file-upload text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3">اضغط "بدء الفحص" للبحث عن الملفات بدون سجل</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<style>
    .dup-group-card { border: 2px solid #e0e0e0; border-radius: 12px; margin-bottom: 20px; overflow: hidden; transition: all 0.3s; }
    .dup-group-card:hover { border-color: #3699ff; box-shadow: 0 2px 8px rgba(54,153,255,0.15); }
    .dup-group-header { background: #f8f9fa; padding: 12px 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
    .dup-group-body { padding: 15px; }
    .dup-file-card { border: 2px solid #e0e0e0; border-radius: 10px; overflow: hidden; transition: all 0.2s; position: relative; cursor: pointer; height: 100%; }
    .dup-file-card:hover { border-color: #3699ff; box-shadow: 0 4px 12px rgba(54,153,255,0.2); }
    .dup-file-card.selected { border-color: #f64e60; background: #fff5f7; }
    .dup-checkbox { position: absolute; top: 8px; left: 8px; z-index: 3; width: 22px; height: 22px; cursor: pointer; accent-color: #f64e60; }
    .dup-thumb { width: 100%; height: 160px; object-fit: contain; background: #f8f9fa; border-bottom: 1px solid #e0e0e0; cursor: pointer; }
    .dup-file-icon { width: 100%; height: 160px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-bottom: 1px solid #e0e0e0; font-size: 3rem; cursor: pointer; }
    .dup-file-info { padding: 10px 12px; font-size: 0.8rem; pointer-events: none; }
    .dup-file-info .name { font-weight: 600; word-break: break-all; margin-bottom: 4px; }
    .dup-file-info .meta { color: #999; }
    .dup-preview-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column; cursor: pointer; }
    .dup-preview-overlay img { max-width: 90vw; max-height: 80vh; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 30px rgba(0,0,0,0.5); }
    .dup-preview-overlay .preview-info { color: #fff; margin-top: 15px; text-align: center; font-size: 0.95rem; }
    .dup-preview-overlay .preview-close { position: absolute; top: 20px; left: 20px; color: #fff; font-size: 2rem; cursor: pointer; background: rgba(255,255,255,0.15); border: none; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .dup-preview-overlay .preview-close:hover { background: rgba(255,255,255,0.3); }
</style>

@push('scriptsCode')
<script>
$(document).ready(function() {
    let duplicatesData = [];
    let brokenLinksData = [];
    let orphanFilesData = [];
    let selectedForDelete = new Set();

    const imageExts = ['jpg','jpeg','png','gif','webp','bmp'];

    function isImageFile(filename) {
        if (!filename) return false;
        const ext = filename.split('.').pop().toLowerCase();
        return imageExts.includes(ext);
    }

    function getFileUrl(filePath) {
        if (!filePath) return '';
        if (filePath.startsWith('storage/')) {
            return '/' + filePath;
        }
        if (filePath.includes(':')) {
            const parts = filePath.split('/');
            const fileName = parts.pop();
            const folder = parts.pop();
            return '/storage/uploads/' + folder + '/' + fileName;
        }
        return '/' + filePath;
    }

    function formatSize(bytes) {
        if (!bytes) return '0 B';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // ===== المكررات =====
    $('#btn-find-duplicates').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#duplicates-loading').show();
        $('#duplicates-stats').hide();
        $('#duplicates-empty').hide();
        selectedForDelete.clear();
        updateSelectedCount();

        $.ajax({
            url: '{{ route("attachment-audit.duplicates") }}',
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    duplicatesData = response.data.groups;
                    renderDuplicates(response.data);
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص المكررات', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');
                $('#duplicates-loading').hide();
            }
        });
    });

    function renderDuplicates(data) {
        if (data.total_groups === 0) {
            $('#duplicates-empty').show().html(
                '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>' +
                '<p class="mt-3 fw-bold text-success">لا توجد ملفات مكررة</p>'
            );
            return;
        }

        $('#dup-total-groups').text(data.total_groups);
        $('#dup-total-count').text(data.total_duplicates);
        $('#dup-total-delete').text(data.total_to_delete);
        $('#duplicates-stats').show();

        const container = $('#duplicates-container').empty();

        data.groups.forEach(function(group, gi) {
            let filesHtml = '';
            group.records.forEach(function(r, ri) {
                const url = getFileUrl(r.file_path);
                const isSelected = selectedForDelete.has(r.id);
                const isOldest = ri === 0;
                const selectedClass = isSelected ? 'selected' : (isOldest && !isSelected ? '' : '');
                const keepClass = (!isSelected && isOldest) ? '' : '';
                const isImg = r.file_path && isImageFile(r.stored_file_name);
                const thumbContent = isImg
                    ? `<img src="${url}" class="dup-thumb" alt="" onerror="this.parentElement.innerHTML='<div class=\\'dup-file-icon\\'><i class=\\'fas fa-file\\'></i></div>'">`
                    : `<div class="dup-file-icon"><i class="fas ${isImageFile(r.stored_file_name) ? 'fa-file-image' : 'fa-file'}"></i></div>`;

                filesHtml += `
                    <div class="col-md-3 col-sm-4 col-6 mb-3">
                        <div class="dup-file-card ${isSelected ? 'selected' : ''}" data-id="${r.id}" data-group="${gi}" data-url="${url}" data-name="${r.stored_file_name || ''}" data-isimg="${isImg ? '1' : '0'}">
                            <input type="checkbox" class="dup-checkbox" data-id="${r.id}" ${isSelected ? 'checked' : ''}>
                            ${thumbContent}
                            <div class="dup-file-info">
                                <div class="name">${r.stored_file_name || 'بدون اسم'}</div>
                                <div class="meta">
                                    ID: ${r.id} | ${formatSize(r.file_size)}
                                    ${r.created_at ? '<br>' + r.created_at : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.append(`
                <div class="dup-group-card">
                    <div class="dup-group-header">
                        <div>
                            <strong class="text-primary">الهوية: ${group.person_identity_number}</strong>
                            <span class="badge bg-secondary ms-2">النوع: ${group.file_type}</span>
                            <span class="badge bg-warning ms-1">${group.count} سجلات</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary select-group-newest" data-group="${gi}" title="تحديد الأحدث للحذف">
                                <i class="fas fa-clock me-1"></i>تحديد الجديد للحذف
                            </button>
                            <button class="btn btn-sm btn-outline-warning select-group-oldest" data-group="${gi}" title="تحديد الأقدم للحذف">
                                <i class="fas fa-history me-1"></i>تحديد القديم للحذف
                            </button>
                        </div>
                    </div>
                    <div class="dup-group-body">
                        <div class="row">${filesHtml}</div>
                    </div>
                </div>
            `);
        });
    }

    // إغلاق المعاينة بـ ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.dup-preview-overlay').remove();
        }
    });

    // نقر على ملف — عرض المعاينة
    $(document).on('click', '.dup-file-card', function(e) {
        if ($(e.target).hasClass('dup-checkbox')) return;
        const url = $(this).data('url');
        const name = $(this).data('name');
        const isImg = $(this).data('isimg');
        if (isImg && url) {
            const overlay = $('<div class="dup-preview-overlay">' +
                '<button class="preview-close" title="إغلاق">&times;</button>' +
                '<img src="' + url + '" alt="' + name + '">' +
                '<div class="preview-info"><strong>' + (name || '') + '</strong></div>' +
                '</div>');
            overlay.on('click', function(ev) {
                if (ev.target === this || $(ev.target).hasClass('preview-close')) {
                    $(this).remove();
                }
            });
            $('body').append(overlay);
        } else if (url) {
            window.open(url, '_blank');
        }
    });

    // تحديد / إلغاء تحديد بالـ checkbox
    $(document).on('change', '.dup-checkbox', function() {
        const id = parseInt($(this).data('id'));
        const $card = $(this).closest('.dup-file-card');
        if ($(this).is(':checked')) {
            selectedForDelete.add(id);
            $card.addClass('selected');
        } else {
            selectedForDelete.delete(id);
            $card.removeClass('selected');
        }
        updateSelectedCount();
    });

    function updateSelectedCount() {
        $('#dup-selected-count').text(selectedForDelete.size);
    }

    // تحديد الأحدث للحذف في مجموعة
    $(document).on('click', '.select-group-newest', function(e) {
        e.stopPropagation();
        const gi = parseInt($(this).data('group'));
        const group = duplicatesData[gi];
        if (!group) return;
        // الأقدم = الأول (نحتفظ فيه)، الباقي نحددهم للحذف
        for (let i = 1; i < group.records.length; i++) {
            selectedForDelete.add(group.records[i].id);
        }
        refreshGroupSelection(gi);
        updateSelectedCount();
    });

    // تحديد الأقدم للحذف في مجموعة
    $(document).on('click', '.select-group-oldest', function(e) {
        e.stopPropagation();
        const gi = parseInt($(this).data('group'));
        const group = duplicatesData[gi];
        if (!group) return;
        // الأحدث = الأخير (نحتفظ فيه)، الأقدم نحددهم للحذف
        for (let i = 0; i < group.records.length - 1; i++) {
            selectedForDelete.add(group.records[i].id);
        }
        refreshGroupSelection(gi);
        updateSelectedCount();
    });

    function refreshGroupSelection(gi) {
        $(`.dup-file-card[data-group="${gi}"]`).each(function() {
            const id = parseInt($(this).data('id'));
            const isSelected = selectedForDelete.has(id);
            $(this).toggleClass('selected', isSelected);
            $(this).find('.dup-checkbox').prop('checked', isSelected);
        });
    }

    // حذف المحددة
    $('#btn-delete-selected').on('click', function() {
        if (selectedForDelete.size === 0) {
            Swal.fire('تنبيه', 'لم تحدد أي ملف للحذف', 'info');
            return;
        }
        Swal.fire({
            title: 'حذف المحدد؟',
            html: `سيتم حذف <strong>${selectedForDelete.size}</strong> ملف`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف',
        }).then(result => {
            if (result.isConfirmed) {
                deleteMultiple(Array.from(selectedForDelete));
            }
        });
    });

    // حذف القديم (الاحتفاظ بالأحدث)
    $('#btn-delete-keep-newest').on('click', function() {
        const allOldIds = [];
        duplicatesData.forEach(function(g) {
            for (let i = 0; i < g.records.length - 1; i++) {
                allOldIds.push(g.records[i].id);
            }
        });
        if (allOldIds.length === 0) return;
        Swal.fire({
            title: 'حذف القديم؟',
            html: `سيتم حذف <strong>${allOldIds.length}</strong> ملف قديم والاحتفاظ بالأحدث في كل مجموعة`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف القديم',
        }).then(result => {
            if (result.isConfirmed) {
                deleteMultiple(allOldIds);
            }
        });
    });

    // حذف الجديد (الاحتفاظ بالأقدم)
    $('#btn-delete-keep-oldest').on('click', function() {
        const allNewIds = [];
        duplicatesData.forEach(function(g) {
            for (let i = 1; i < g.records.length; i++) {
                allNewIds.push(g.records[i].id);
            }
        });
        if (allNewIds.length === 0) return;
        Swal.fire({
            title: 'حذف الجديد؟',
            html: `سيتم حذف <strong>${allNewIds.length}</strong> ملف جديد والاحتفاظ بالأقدم في كل مجموعة`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف الجديد',
        }).then(result => {
            if (result.isConfirmed) {
                deleteMultiple(allNewIds);
            }
        });
    });

    function deleteMultiple(ids) {
        let deleted = 0;
        const total = ids.length;
        Swal.fire({ title: 'جاري الحذف...', html: `0 / ${total}`, allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        ids.forEach(function(id, i) {
            $.ajax({
                url: '{{ route("attachment-audit.duplicates.delete-single") }}',
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: id },
                async: false,
                success: function() { deleted++; }
            });
            Swal.getHtmlContainer().innerHTML = `<strong>${deleted} / ${total}</strong>`;
        });

        selectedForDelete.clear();
        updateSelectedCount();
        Swal.fire('تم', `تم حذف ${deleted} من ${total} ملف`, 'success');
        $('#btn-find-duplicates').click();
    }

    // إلغاء التحديد
    $('#btn-select-none').on('click', function() {
        selectedForDelete.clear();
        $('.dup-checkbox').prop('checked', false);
        $('.dup-file-card').removeClass('selected');
        updateSelectedCount();
    });

    // تصدير المكررات
    $('#btn-export-duplicates').on('click', function() {
        if (duplicatesData.length === 0) {
            Swal.fire('تنبيه', 'لا توجد بيانات للتصدير', 'info');
            return;
        }
        let flatData = [];
        duplicatesData.forEach(g => g.records.forEach(r => flatData.push({...r, file_type: g.file_type})));
        window.location.href = '{{ route("attachment-audit.export") }}?type=duplicates&data=' + encodeURIComponent(JSON.stringify(flatData));
    });

    // ===== الروابط المكسورة =====
    $('#btn-check-broken').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#broken-loading').show();
        $('#broken-stats').hide();
        $('#broken-empty').hide();

        $.ajax({
            url: '{{ route("attachment-audit.broken-links") }}',
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    brokenLinksData = response.data.broken;
                    renderBrokenLinks(response.data);
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص الروابط', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');
                $('#broken-loading').hide();
            }
        });
    });

    function renderBrokenLinks(data) {
        $('#brk-total').text(data.total);
        $('#brk-valid').text(data.valid);
        $('#brk-broken').text(data.broken_count);
        $('#brk-skipped').text(data.skipped);
        $('#broken-stats').show();

        if (data.broken_count === 0) {
            $('#broken-empty').show().html(
                '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>' +
                '<p class="mt-3 fw-bold text-success">جميع الروابط سليمة</p>'
            );
            return;
        }

        const tbody = $('#broken-tbody').empty();
        data.broken.forEach(function(item) {
            tbody.append(`
                <tr>
                    <td class="text-center"><input type="checkbox" class="form-check-input broken-checkbox" data-id="${item.id}"></td>
                    <td class="text-center">${item.id}</td>
                    <td>${item.person_identity_number || '-'}</td>
                    <td>${item.stored_file_name || '-'}</td>
                    <td><code class="text-danger" style="font-size:0.75rem;word-break:break-all;">${item.file_path}</code></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-danger delete-single-broken" data-id="${item.id}" title="حذف">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    $('#select-all-broken').on('change', function() {
        $('.broken-checkbox').prop('checked', $(this).is(':checked'));
    });

    $(document).on('click', '.delete-single-broken', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'حذف هذا السجل؟',
            text: 'لن تتمكن من التراجع',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف',
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("attachment-audit.broken-links.delete") }}',
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { id: id },
                    success: function() {
                        Swal.fire('تم', 'تم الحذف', 'success');
                        $('#btn-check-broken').click();
                    }
                });
            }
        });
    });

    $('#btn-delete-all-broken').on('click', function() {
        const selectedIds = $('.broken-checkbox:checked').map(function() { return $(this).data('id'); }).get();
        const idsToDelete = selectedIds.length > 0 ? selectedIds : brokenLinksData.map(b => b.id);

        if (idsToDelete.length === 0) {
            Swal.fire('تنبيه', 'لا توجد روابط مكسورة للحذف', 'info');
            return;
        }

        Swal.fire({
            title: 'حذف الروابط المكسورة؟',
            html: `سيتم حذف <strong>${idsToDelete.length}</strong> سجل`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف الكل',
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("attachment-audit.broken-links.delete-all") }}',
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: JSON.stringify({ ids: idsToDelete }),
                    contentType: 'application/json',
                    success: function(response) {
                        Swal.fire('تم', response.message, 'success');
                        $('#btn-check-broken').click();
                    },
                    error: function() {
                        Swal.fire('خطأ', 'حدث خطأ أثناء الحذف', 'error');
                    }
                });
            }
        });
    });

    $('#btn-export-broken').on('click', function() {
        if (brokenLinksData.length === 0) {
            Swal.fire('تنبيه', 'لا توجد بيانات للتصدير', 'info');
            return;
        }
        window.location.href = '{{ route("attachment-audit.export") }}?type=broken&data=' + encodeURIComponent(JSON.stringify(brokenLinksData));
    });

    // ===== المعيلين والأفراد والمتوفين =====
    let personsData = [];

    $('#btn-find-persons').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#persons-loading').show();
        $('#persons-stats').hide();
        $('#persons-empty').hide();

        $.ajax({
            url: '{{ route("attachment-audit.without-attachments") }}',
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    personsData = response.data.persons;
                    renderPersons(response.data);
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص البيانات', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');
                $('#persons-loading').hide();
            }
        });
    });

    function renderPersons(data) {
        $('#prs-total').text(data.total);
        $('#prs-with').text(data.with_attachments);
        $('#prs-without').text(data.without_attachments);
        $('#prs-in-spons').text(data.in_sponsorships);
        $('#persons-stats').show();

        if (data.total === 0) {
            $('#persons-empty').show().html(
                '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>' +
                '<p class="mt-3 fw-bold text-success">لا توجد بيانات</p>'
            );
            return;
        }

        const tbody = $('#persons-tbody').empty();
        data.persons.forEach(function(p, i) {
            const hasAttach = p.has_attachments;
            const inSpons = p.in_sponsorships;
            tbody.append(`
                <tr>
                    <td class="text-center">${i + 1}</td>
                    <td>${p.person_id || '-'}</td>
                    <td>${p.full_name || '-'}</td>
                    <td><span class="badge bg-secondary">${p.person_type_ar}</span></td>
                    <td class="text-center">
                        ${inSpons ? '<span class="badge bg-success">نعم</span>' : '<span class="badge bg-danger">لا</span>'}
                    </td>
                    <td class="text-center">
                        ${hasAttach ? '<span class="badge bg-success">' + p.attachment_count + '</span>' : '<span class="badge bg-danger">0</span>'}
                    </td>
                </tr>
            `);
        });
    }

    $('#btn-export-persons').on('click', function() {
        if (personsData.length === 0) {
            Swal.fire('تنبيه', 'لا توجد بيانات للتصدير', 'info');
            return;
        }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("attachment-audit.export-without-attachments") }}';
        form.target = '_blank';

        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);

        var dataInput = document.createElement('input');
        dataInput.type = 'hidden';
        dataInput.name = 'data';
        dataInput.value = JSON.stringify(personsData);
        form.appendChild(dataInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // ===== الملفات بدون سجل =====
    $('#btn-find-orphan').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#orphan-loading').show();
        $('#orphan-stats').hide();
        $('#orphan-empty').hide();

        $.ajax({
            url: '{{ route("attachment-audit.orphan-files") }}',
            method: 'GET',
            success: function(response) {
                $('#orphan-loading').hide();
                $btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');
                if (response.success) {
                    renderOrphanResults(response.data);
                }
            },
            error: function() {
                $('#orphan-loading').hide();
                $btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص الملفات', 'error');
            }
        });
    });

    function renderOrphanResults(data) {
        orphanFilesData = data.orphan_files || [];
        $('#orph-total-disk').text(data.total_files_on_disk || 0);
        $('#orph-in-db').text(data.total_in_db || 0);
        $('#orph-count').text(data.orphan_count || 0);
        $('#orph-added').text('0');

        if (orphanFilesData.length === 0) {
            $('#orphan-stats').hide();
            $('#orphan-empty').show().html(
                '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>' +
                '<p class="mt-3 fw-bold text-success">جميع الملفات مسجلة في قاعدة البيانات</p>'
            );
            $('#btn-add-all-orphan').hide();
            $('#btn-add-selected-orphan').hide();
            return;
        }

        $('#btn-add-all-orphan').show();
        $('#btn-add-selected-orphan').show();

        const tbody = $('#orphan-tbody').empty();
        orphanFilesData.forEach(function(item, idx) {
            const ext = (item.extension || '').toLowerCase();
            const isImg = ['jpg','jpeg','png','gif','webp','bmp','heic','heif'].includes(ext);
            const preview = isImg
                ? '<img src="/' + item.file_path + '" class="dup-thumb" style="height:50px;width:auto;border-radius:4px;">'
                : '<i class="fas fa-file fa-2x text-muted"></i>';

            tbody.append(`
                <tr>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input orphan-checkbox" data-idx="${idx}">
                    </td>
                    <td class="text-center">${idx + 1}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            ${preview}
                            <span class="small">${item.file_path}</span>
                        </div>
                    </td>
                    <td class="text-center">${formatSize(item.file_size)}</td>
                    <td class="text-center"><span class="badge bg-secondary">${ext.toUpperCase()}</span></td>
                    <td class="text-center">${item.identity_number || '-'}</td>
                    <td class="text-center">${item.file_id_number || '-'}</td>
                    <td class="text-center">${item.doc_type_id || '-'}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-success add-orphan-single" data-idx="${idx}" title="إضافة">
                            <i class="fas fa-plus"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        $('#orphan-stats').show();
        $('#orphan-empty').hide();
    }

    $(document).on('click', '.add-orphan-single', function() {
        const idx = $(this).data('idx');
        const file = orphanFilesData[idx];
        if (!file) return;

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: '{{ route("attachment-audit.add-orphan-files") }}',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: JSON.stringify({
                files: [file]
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire('تم', response.message, 'success');
                    $btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                    const current = parseInt($('#orph-added').text()) || 0;
                    $('#orph-added').text(current + 1);
                    const remaining = parseInt($('#orph-count').text()) - 1;
                    $('#orph-count').text(Math.max(0, remaining));
                } else {
                    Swal.fire('خطأ', response.message, 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-plus"></i>');
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ أثناء الإضافة', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-plus"></i>');
            }
        });
    });

    $('#select-all-orphan').on('change', function() {
        $('.orphan-checkbox').prop('checked', $(this).is(':checked'));
    });

    $('#btn-add-selected-orphan').on('click', function() {
        const selected = [];
        $('.orphan-checkbox:checked').each(function() {
            const idx = $(this).data('idx');
            if (orphanFilesData[idx]) selected.push(orphanFilesData[idx]);
        });

        if (selected.length === 0) {
            Swal.fire('تنبيه', 'لم يتم تحديد أي ملفات', 'warning');
            return;
        }

        Swal.fire({
            title: 'إضافة ' + selected.length + ' ملف؟',
            text: 'سيتم إنشاء سجل لكل ملف في جدول المرفقات',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'نعم، أضفها',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                addOrphanFilesToDb(selected);
            }
        });
    });

    $('#btn-add-all-orphan').on('click', function() {
        if (orphanFilesData.length === 0) return;

        Swal.fire({
            title: 'إضافة الكل؟',
            text: 'سيتم إنشاء سجل لـ ' + orphanFilesData.length + ' ملف في جدول المرفقات',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'نعم، أضف الكل',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                addOrphanFilesToDb(orphanFilesData);
            }
        });
    });

    function addOrphanFilesToDb(files) {
        Swal.fire({ title: 'جاري الإضافة...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        $.ajax({
            url: '{{ route("attachment-audit.add-orphan-files") }}',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: JSON.stringify({
                files: files
            }),
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire('تم', response.message, 'success');
                    const added = response.data.added_count || 0;
                    const current = parseInt($('#orph-added').text()) || 0;
                    $('#orph-added').text(current + added);
                    const remaining = parseInt($('#orph-count').text()) - added;
                    $('#orph-count').text(Math.max(0, remaining));
                    $('#btn-find-orphan').click();
                } else {
                    Swal.fire('خطأ', response.message, 'error');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('خطأ', 'حدث خطأ أثناء الإضافة', 'error');
            }
        });
    }
});
</script>
@endpush
@endsection
