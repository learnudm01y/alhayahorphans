@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-6">
        <h1 class="h3 fw-bold">
            <i class="fas fa-search-plus me-2 text-primary"></i>
            فحص المرفقات
        </h1>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-light">
            <i class="fas fa-arrow-right me-2"></i>رجوع
        </a>
    </div>

    <ul class="nav nav-tabs nav-tabs-solid nav-tabs-line-border-2 border-primary mb-5" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#duplicates-tab" type="button" role="tab">
                <i class="fas fa-copy me-2"></i>الملفات المكررة
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#dup-paths-tab" type="button" role="tab">
                <i class="fas fa-file-export me-2"></i>الروابط المكررة للمسار
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

                        <div class="text-center mt-4" id="dup-load-more-section" style="display:none;">
                            <p class="text-muted mb-2" id="dup-page-info"></p>
                            <button class="btn btn-outline-primary" id="btn-load-more-duplicates">
                                <i class="fas fa-arrow-down me-2"></i>تحميل المزيد
                            </button>
                        </div>
                    </div>

                    <div id="duplicates-empty" class="text-center py-10 text-muted">
                        <i class="fas fa-copy text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3">اضغط "بدء الفحص" لاكتشاف المكررات</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== تبويب الروابط المكررة للمسار ===== --}}
        <div class="tab-pane fade" id="dup-paths-tab" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-file-export me-2 text-info"></i>
                        كشف المكررات حسب المسار (نفس الملف مسجل أكثر من مرة)
                    </h5>
                    <button class="btn btn-primary" id="btn-find-dup-paths">
                        <i class="fas fa-search me-2"></i>بدء الفحص
                    </button>
                </div>
                <div class="card-body">
                    <div id="dup-paths-loading" class="text-center py-10" style="display:none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">جاري فحص المكررات حسب المسار...</p>
                    </div>

                    <div id="dup-paths-stats" style="display:none;">
                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="card border-primary border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-primary mb-0" id="dp-groups">0</h3>
                                        <small class="text-muted">مجموعات مكررة</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-warning border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-warning mb-0" id="dp-total">0</h3>
                                        <small class="text-muted">إجمالي السجلات المكررة</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-danger border-2">
                                    <div class="card-body text-center py-3">
                                        <h3 class="fw-bold text-danger mb-0" id="dp-to-delete">0</h3>
                                        <small class="text-muted">قابل للحذف</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="dup-paths-list"></div>
                    </div>

                    <div id="dup-paths-empty" class="text-center py-10 text-muted">
                        <i class="fas fa-file-export text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3">اضغط "بدء الفحص" لاكتشاف المكررات حسب المسار</p>
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

                    <div class="text-center mt-4" id="prs-load-more-section" style="display:none;">
                        <p class="text-muted mb-2" id="prs-page-info"></p>
                        <button class="btn btn-outline-primary" id="btn-load-more-persons">
                            <i class="fas fa-arrow-down me-2"></i>تحميل المزيد
                        </button>
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
                        <p class="mt-3 text-muted" id="orphan-loading-text">جاري فحص الملفات على القرص...</p>
                        <div class="progress mt-3" style="height: 6px; max-width: 400px; margin: 0 auto;">
                            <div id="orphan-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                        </div>
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

                    <div class="text-center mt-4" id="orphan-load-more-section" style="display:none;">
                        <p class="text-muted mb-2" id="orphan-page-info"></p>
                        <button class="btn btn-outline-primary" id="btn-load-more-orphan">
                            <i class="fas fa-arrow-down me-2"></i>تحميل المزيد
                        </button>
                    </div>
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

    // ===== تكوين CSRF عام =====
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // ===== المتغيرات العامة =====
    let duplicatesData = [];
    let allDuplicatesGroups = [];
    let dupCurrentPage = 0;
    const DUP_PAGE_SIZE = 20;
    let brokenLinksData = [];
    let personsData = [];
    let allPersonsData = [];
    let prsCurrentPage = 0;
    const PRS_PAGE_SIZE = 50;
    let selectedForDelete = new Set();
    let orphanCurrentPage = 1;
    let orphanTotalPages = 0;
    let orphanTotalCount = 0;
    let orphanFilesDataMap = {};

    // ===== ثوابت =====
    const IMAGE_EXTS = ['jpg','jpeg','png','gif','webp','bmp'];
    const ORPHAN_IMAGE_EXTS = ['jpg','jpeg','png','gif','webp','bmp','heic','heif'];

    // ===== دوال مساعدة عامة =====
    function isImageFile(filename) {
        if (!filename) return false;
        const ext = filename.split('.').pop().toLowerCase();
        return IMAGE_EXTS.includes(ext);
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
        if (/^(attachments|uploads|documents)\//.test(filePath)) {
            return '/storage/' + filePath;
        }
        return '/' + filePath;
    }

    function formatSize(bytes) {
        if (!bytes) return '0 B';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function resetBtnSearch($btn) {
        $btn.prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');
    }

    // ===== دوال حذف المرفقات =====
    function deleteAttachmentById(id) {
        return $.ajax({
            url: '{{ route("attachment-audit.broken-links.delete") }}',
            method: 'DELETE',
            data: { id: id }
        });
    }

    function deleteDuplicateById(id) {
        return $.ajax({
            url: '{{ route("attachment-audit.duplicates.delete-single") }}',
            method: 'DELETE',
            data: { id: id }
        });
    }

    function deleteSequentially(ids, deleteFn, onProgress) {
        const deferred = $.Deferred();
        let deleted = 0;
        let i = 0;

        function next() {
            if (i >= ids.length) {
                deferred.resolve(deleted);
                return;
            }
            const id = ids[i];
            i++;
            deleteFn(id).then(function() {
                deleted++;
            }).always(function() {
                if (onProgress) onProgress(deleted, ids.length);
                next();
            });
        }
        next();
        return deferred.promise();
    }

    // ===== بناء FormData للملفات اليتيمة =====
    function buildOrphanFormData(files, offset) {
        const fd = new FormData();
        fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
        files.forEach(function(file, i) {
            const prefix = 'files[' + (offset + i) + ']';
            fd.append(prefix + '[file_path]', file.file_path || '');
            fd.append(prefix + '[full_path]', file.full_path || '');
            fd.append(prefix + '[file_name]', file.file_name || '');
            fd.append(prefix + '[file_size]', file.file_size || 0);
            fd.append(prefix + '[extension]', file.extension || '');
            fd.append(prefix + '[folder]', file.folder || '');
            fd.append(prefix + '[identity_number]', file.identity_number || '');
            fd.append(prefix + '[doc_type_id]', file.doc_type_id || '');
            fd.append(prefix + '[file_id_number]', file.file_id_number || '');
        });
        return fd;
    }

    // =====================================================================
    // تبويب المكررات
    // =====================================================================
    $('#btn-find-duplicates').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#duplicates-loading').show();
        $('#duplicates-stats').hide();
        $('#duplicates-empty').hide();
        $('#dup-load-more-section').hide();
        $('#duplicates-container').empty();
        selectedForDelete.clear();
        updateSelectedCount();
        allDuplicatesGroups = [];
        dupCurrentPage = 0;

        $.ajax({
            url: '{{ route("attachment-audit.duplicates") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    duplicatesData = response.data.groups;
                    allDuplicatesGroups = response.data.groups;
                    renderDuplicatesStats(response.data);
                    renderDuplicatesPage();
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص المكررات', 'error');
            },
            complete: function() {
                resetBtnSearch($btn);
                $('#duplicates-loading').hide();
            }
        });
    });

    function renderDuplicatesStats(data) {
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
    }

    function renderDuplicatesPage() {
        const start = dupCurrentPage * DUP_PAGE_SIZE;
        const end = Math.min(start + DUP_PAGE_SIZE, allDuplicatesGroups.length);
        const pageGroups = allDuplicatesGroups.slice(start, end);

        if (pageGroups.length === 0 && dupCurrentPage === 0) {
            $('#duplicates-empty').show().html(
                '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>' +
                '<p class="mt-3 fw-bold text-success">لا توجد ملفات مكررة</p>'
            );
            return;
        }

        const container = $('#duplicates-container');

        pageGroups.forEach(function(group, gi) {
            const globalGi = start + gi;
            let filesHtml = '';
            group.records.forEach(function(r) {
                const url = getFileUrl(r.file_path);
                const isSelected = selectedForDelete.has(r.id);
                const isImg = r.file_path && isImageFile(r.stored_file_name);
                const thumbContent = isImg
                    ? `<img src="${url}" class="dup-thumb" alt="" onerror="this.parentElement.innerHTML='<div class=\\'dup-file-icon\\'><i class=\\'fas fa-file\\'></i></div>'">`
                    : `<div class="dup-file-icon"><i class="fas ${isImageFile(r.stored_file_name) ? 'fa-file-image' : 'fa-file'}"></i></div>`;

                filesHtml += `
                    <div class="col-md-3 col-sm-4 col-6 mb-3">
                        <div class="dup-file-card ${isSelected ? 'selected' : ''}" data-id="${r.id}" data-group="${globalGi}" data-url="${url}" data-name="${r.stored_file_name || ''}" data-isimg="${isImg ? '1' : '0'}">
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
                            <button class="btn btn-sm btn-outline-primary select-group-newest" data-group="${globalGi}" title="تحديد الأحدث للحذف">
                                <i class="fas fa-clock me-1"></i>تحديد الجديد للحذف
                            </button>
                            <button class="btn btn-sm btn-outline-warning select-group-oldest" data-group="${globalGi}" title="تحديد الأقدم للحذف">
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

        dupCurrentPage++;

        if (dupCurrentPage * DUP_PAGE_SIZE < allDuplicatesGroups.length) {
            const showing = Math.min(dupCurrentPage * DUP_PAGE_SIZE, allDuplicatesGroups.length);
            $('#dup-page-info').text('تم عرض ' + showing + ' من ' + allDuplicatesGroups.length + ' مجموعة');
            $('#dup-load-more-section').show();
        } else {
            $('#dup-page-info').text('تم عرض جميع المجموعات (' + allDuplicatesGroups.length + ')');
            $('#dup-load-more-section').hide();
        }
    }

    $('#btn-load-more-duplicates').on('click', function() {
        renderDuplicatesPage();
    });

    // معاينة الصور
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.dup-preview-overlay').remove();
        }
    });

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

    // تحديد المكررات
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

    $(document).on('click', '.select-group-newest', function(e) {
        e.stopPropagation();
        const gi = parseInt($(this).data('group'));
        const group = duplicatesData[gi];
        if (!group) return;
        for (let i = 1; i < group.records.length; i++) {
            selectedForDelete.add(group.records[i].id);
        }
        refreshGroupSelection(gi);
        updateSelectedCount();
    });

    $(document).on('click', '.select-group-oldest', function(e) {
        e.stopPropagation();
        const gi = parseInt($(this).data('group'));
        const group = duplicatesData[gi];
        if (!group) return;
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

    // حذف المكررات
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
        const total = ids.length;
        Swal.fire({ title: 'جاري الحذف...', html: `0 / ${total}`, allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        deleteSequentially(ids, deleteDuplicateById, function(deleted, t) {
            Swal.getHtmlContainer().innerHTML = '<strong>' + deleted + ' / ' + t + '</strong>';
        }).then(function(deleted) {
            selectedForDelete.clear();
            updateSelectedCount();
            Swal.fire('تم', 'تم حذف ' + deleted + ' من ' + total + ' ملف', 'success');
            $('#btn-find-duplicates').click();
        });
    }

    $('#btn-select-none').on('click', function() {
        selectedForDelete.clear();
        $('.dup-checkbox').prop('checked', false);
        $('.dup-file-card').removeClass('selected');
        updateSelectedCount();
    });

    $('#btn-export-duplicates').on('click', function() {
        if (duplicatesData.length === 0) {
            Swal.fire('تنبيه', 'لا توجد بيانات للتصدير', 'info');
            return;
        }
        let flatData = [];
        duplicatesData.forEach(g => g.records.forEach(r => flatData.push({...r, file_type: g.file_type})));
        window.location.href = '{{ route("attachment-audit.export") }}?type=duplicates&data=' + encodeURIComponent(JSON.stringify(flatData));
    });

    // =====================================================================
    // تبويب الروابط المكررة للمسار
    // =====================================================================
    $('#btn-find-dup-paths').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#dup-paths-loading').show();
        $('#dup-paths-stats').hide();
        $('#dup-paths-empty').hide();

        $.ajax({
            url: '{{ route("attachment-audit.duplicate-paths") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    renderDupPaths(response.data);
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص المكررات', 'error');
            },
            complete: function() {
                resetBtnSearch($btn);
                $('#dup-paths-loading').hide();
            }
        });
    });

    function renderDupPaths(data) {
        if (data.total_groups === 0) {
            $('#dup-paths-empty').show().html(
                '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>' +
                '<p class="mt-3 fw-bold text-success">لا توجد مكررات حسب المسار</p>'
            );
            return;
        }

        $('#dp-groups').text(data.total_groups);
        $('#dp-total').text(data.total_duplicates);
        $('#dp-to-delete').text(data.total_to_delete);
        $('#dup-paths-stats').show();

        const container = $('#dup-paths-list').empty();

        data.groups.forEach(function(group, gi) {
            let filesHtml = '';
            group.records.forEach(function(r) {
                const isOldest = r.id === group.keep_id;
                filesHtml += `
                    <tr class="${isOldest ? 'table-success' : ''}">
                        <td class="text-center"><input type="checkbox" class="form-check-input dup-path-checkbox" data-id="${r.id}" ${isOldest ? 'disabled checked title="السجل الأقدم - يُحتفظ به"' : ''}></td>
                        <td class="text-center">${r.id}</td>
                        <td>${r.person_identity_number || '-'}</td>
                        <td>${r.stored_file_name || '-'}</td>
                        <td class="text-center">${r.file_type || '-'}</td>
                        <td class="text-center">${formatSize(r.file_size)}</td>
                        <td class="text-center">${isOldest ? '<span class="badge bg-success">الأقدم</span>' : '<span class="badge bg-danger">مكرر</span>'}</td>
                        <td class="text-center">
                            ${!isOldest ? '<button class="btn btn-sm btn-outline-danger delete-single-dup-path" data-id="' + r.id + '" title="حذف"><i class="fas fa-trash"></i></button>' : ''}
                        </td>
                    </tr>
                `;
            });

            container.append(`
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#${gi + 1}</strong> — <code class="text-muted">${group.file_path}</code>
                            <span class="badge bg-warning text-dark ms-2">${group.count} سجلات</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-danger delete-selected-dup-path" title="حذف المحدد">
                                <i class="fas fa-trash me-1"></i>حذف المحدد
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-dup-path-group" data-filepath="${group.file_path}" title="حذف المكررات والاحتفاظ بالأقدم">
                                <i class="fas fa-trash me-1"></i>حذف المكررات
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:40px"><input type="checkbox" class="form-check-input select-all-dup-path"></th>
                                    <th class="text-center">ID</th>
                                    <th>رقم الهوية</th>
                                    <th>اسم الملف</th>
                                    <th class="text-center">النوع</th>
                                    <th class="text-center">الحجم</th>
                                    <th class="text-center">الحالة</th>
                                    <th class="text-center">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>${filesHtml}</tbody>
                        </table>
                    </div>
                </div>
            `);
        });
    }

    $(document).on('change', '.select-all-dup-path', function() {
        const $card = $(this).closest('.card');
        $card.find('.dup-path-checkbox:not(:disabled)').prop('checked', $(this).is(':checked'));
    });

    $(document).on('click', '.delete-dup-path-group', function() {
        const filePath = $(this).data('filepath');
        const $card = $(this).closest('.card');

        Swal.fire({
            title: 'حذف المكررات؟',
            text: 'سيتم حذف جميع السجلات المكررة للمسار: ' + filePath,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                const ids = [];
                $card.find('.dup-path-checkbox:not(:disabled):checked').each(function() {
                    ids.push($(this).data('id'));
                });
                if (ids.length === 0) {
                    Swal.fire('تنبيه', 'لم يتم تحديد أي سجلات', 'warning');
                    return;
                }
                Swal.fire({ title: 'جاري الحذف...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                deleteSequentially(ids, deleteAttachmentById, function() {}).then(function(deleted) {
                    Swal.fire('تم', 'تم حذف ' + deleted + ' سجل', 'success');
                    $('#btn-find-dup-paths').click();
                });
            }
        });
    });

    $(document).on('click', '.delete-single-dup-path', function() {
        const id = $(this).data('id');
        const $row = $(this).closest('tr');
        Swal.fire({
            title: 'حذف السجل؟',
            text: 'سيتم حذف سجل المرفق رقم: ' + id,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'جاري الحذف...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                deleteAttachmentById(id).then(function() {
                    Swal.fire('تم', 'تم الحذف بنجاح', 'success');
                    $row.fadeOut(300, function() { $(this).remove(); });
                }).fail(function() {
                    Swal.fire('خطأ', 'حدث خطأ أثناء الحذف', 'error');
                });
            }
        });
    });

    $(document).on('click', '.delete-selected-dup-path', function() {
        const $card = $(this).closest('.card');
        const ids = [];
        $card.find('.dup-path-checkbox:not(:disabled):checked').each(function() {
            ids.push($(this).data('id'));
        });
        if (ids.length === 0) {
            Swal.fire('تنبيه', 'لم يتم تحديد أي سجلات', 'warning');
            return;
        }
        Swal.fire({
            title: 'حذف المحدد؟',
            text: 'سيتم حذف ' + ids.length + ' سجل',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'جاري الحذف...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                deleteSequentially(ids, deleteAttachmentById, function() {}).then(function(deleted) {
                    Swal.fire('تم', 'تم حذف ' + deleted + ' سجل', 'success');
                    $('#btn-find-dup-paths').click();
                });
            }
        });
    });

    // =====================================================================
    // تبويب الروابط المكسورة
    // =====================================================================
    $('#btn-check-broken').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#broken-loading').show();
        $('#broken-stats').hide();
        $('#broken-empty').hide();

        $.ajax({
            url: '{{ route("attachment-audit.broken-links") }}',
            method: 'GET',
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
                resetBtnSearch($btn);
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
                deleteAttachmentById(id).then(function() {
                    Swal.fire('تم', 'تم الحذف', 'success');
                    $('#btn-check-broken').click();
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

    // =====================================================================
    // تبويب المعيلين والأفراد والمتوفين
    // =====================================================================
    $('#btn-find-persons').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#persons-loading').show();
        $('#persons-stats').hide();
        $('#persons-empty').hide();
        $('#prs-load-more-section').hide();
        $('#persons-tbody').empty();
        allPersonsData = [];
        prsCurrentPage = 0;

        $.ajax({
            url: '{{ route("attachment-audit.without-attachments") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    personsData = response.data.persons;
                    allPersonsData = response.data.persons;
                    renderPersonsStats(response.data);
                    renderPersonsPage();
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص البيانات', 'error');
            },
            complete: function() {
                resetBtnSearch($btn);
                $('#persons-loading').hide();
            }
        });
    });

    function renderPersonsStats(data) {
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
        }
    }

    function renderPersonsPage() {
        if (allPersonsData.length === 0) return;

        const start = prsCurrentPage * PRS_PAGE_SIZE;
        const end = Math.min(start + PRS_PAGE_SIZE, allPersonsData.length);
        const pageItems = allPersonsData.slice(start, end);
        const tbody = $('#persons-tbody');

        pageItems.forEach(function(p, i) {
            const hasAttach = p.has_attachments;
            const inSpons = p.in_sponsorships;
            tbody.append(`
                <tr>
                    <td class="text-center">${start + i + 1}</td>
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

        prsCurrentPage++;

        if (prsCurrentPage * PRS_PAGE_SIZE < allPersonsData.length) {
            const showing = Math.min(prsCurrentPage * PRS_PAGE_SIZE, allPersonsData.length);
            $('#prs-page-info').text('تم عرض ' + showing + ' من ' + allPersonsData.length + ' شخص');
            $('#prs-load-more-section').show();
        } else {
            $('#prs-page-info').text('تم عرض جميع النتائج (' + allPersonsData.length + ' شخص)');
            $('#prs-load-more-section').hide();
        }
    }

    $('#btn-load-more-persons').on('click', function() {
        renderPersonsPage();
    });

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

    // =====================================================================
    // تبويب الملفات بدون سجل
    // =====================================================================
    $('#btn-find-orphan').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الفحص...');
        $('#orphan-loading').show();
        $('#orphan-loading-text').text('جاري مسح الملفات على القرص...');
        $('#orphan-progress-bar').css('width', '0%');
        $('#orphan-stats').hide();
        $('#orphan-empty').hide();
        $('#orphan-load-more-section').hide();
        orphanCurrentPage = 1;
        orphanFilesDataMap = {};

        $.ajax({
            url: '{{ route("attachment-audit.orphan-files") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const d = response.data;
                    $('#orph-total-disk').text(d.total_files_on_disk || 0);
                    $('#orph-in-db').text(d.total_in_db || 0);
                    $('#orph-count').text(d.orphan_count || 0);
                    $('#orph-added').text('0');
                    orphanTotalCount = d.orphan_count || 0;
                    orphanTotalPages = d.total_pages || 0;

                    $('#orphan-progress-bar').css('width', '100%');
                    $('#orphan-loading-text').text('اكتمل الفحص! جاري تحميل النتائج...');

                    if (orphanTotalCount === 0) {
                        $('#orphan-loading').hide();
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
                    $('#orphan-tbody').empty();
                    loadOrphanPage(1);
                } else {
                    $('#orphan-loading').hide();
                    resetBtnSearch($btn);
                    Swal.fire('خطأ', response.message, 'error');
                }
            },
            error: function() {
                $('#orphan-loading').hide();
                resetBtnSearch($btn);
                Swal.fire('خطأ', 'حدث خطأ أثناء فحص الملفات', 'error');
            }
        });
    });

    function loadOrphanPage(page) {
        $('#orphan-loading-text').text('جاري تحميل الصفحة ' + page + ' من ' + orphanTotalPages + '...');
        $('#orphan-loading').show();

        $.ajax({
            url: '{{ route("attachment-audit.orphan-files-page") }}',
            method: 'GET',
            data: { page: page, per_page: 50 },
            success: function(response) {
                $('#orphan-loading').hide();
                $('#btn-find-orphan').prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');

                if (response.success) {
                    renderOrphanPage(response.data, page);
                }
            },
            error: function() {
                $('#orphan-loading').hide();
                $('#btn-find-orphan').prop('disabled', false).html('<i class="fas fa-search me-2"></i>بدء الفحص');
                Swal.fire('خطأ', 'حدث خطأ أثناء تحميل الصفحة', 'error');
            }
        });
    }

    function renderOrphanPage(data, page) {
        const tbody = $('#orphan-tbody');
        const files = data.orphan_files || [];
        const baseIdx = (page - 1) * 50;

        files.forEach(function(item, i) {
            const idx = baseIdx + i;
            orphanFilesDataMap[idx] = item;
            const ext = (item.extension || '').toLowerCase();
            const isImg = ORPHAN_IMAGE_EXTS.includes(ext);
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

        orphanCurrentPage = data.current_page || page;

        if (orphanCurrentPage < orphanTotalPages) {
            $('#orphan-page-info').text('صفحة ' + orphanCurrentPage + ' من ' + orphanTotalPages + ' — تم عرض ' + tbody.find('tr').length + ' من ' + orphanTotalCount);
            $('#orphan-load-more-section').show();
        } else {
            $('#orphan-page-info').text('تم عرض جميع النتائج (' + orphanTotalCount + ' ملف)');
            $('#orphan-load-more-section').hide();
        }

        $('#orphan-stats').show();
        $('#orphan-empty').hide();
    }

    $('#btn-load-more-orphan').on('click', function() {
        loadOrphanPage(orphanCurrentPage + 1);
    });

    // إضافة ملف يتيم واحد
    $(document).on('click', '.add-orphan-single', function() {
        const idx = $(this).data('idx');
        const file = orphanFilesDataMap[idx];
        if (!file) return;

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        const fd = buildOrphanFormData([file], 0);

        $.ajax({
            url: '{{ route("attachment-audit.add-orphan-files") }}',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
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

    // تحديد الكل في اليتيمة
    $('#select-all-orphan').on('change', function() {
        $('.orphan-checkbox').prop('checked', $(this).is(':checked'));
    });

    // إضافة المحدد
    $('#btn-add-selected-orphan').on('click', function() {
        const selected = [];
        $('.orphan-checkbox:checked').each(function() {
            const idx = $(this).data('idx');
            if (orphanFilesDataMap[idx]) selected.push(orphanFilesDataMap[idx]);
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

    // إضافة الكل
    $('#btn-add-all-orphan').on('click', function() {
        if (orphanTotalCount === 0) return;

        Swal.fire({
            title: 'إضافة الكل؟',
            text: 'سيتم جمع جميع الملفات من كل الصفحات ثم إضافتها على دفعات',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'نعم، أضف الكل',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                collectAllOrphansAndAdd();
            }
        });
    });

    function addOrphanFilesToDb(files) {
        Swal.fire({ title: 'جاري الإضافة...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const fd = buildOrphanFormData(files, 0);

        $.ajax({
            url: '{{ route("attachment-audit.add-orphan-files") }}',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
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

    function collectAllOrphansAndAdd() {
        Swal.fire({ title: 'جاري جمع جميع الملفات...', html: 'تم جمع <b id="swal-collected-count">0</b> من ' + orphanTotalCount, allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const allFiles = [];
        let page = 1;
        const bigPerPage = 5000;
        const totalPages = Math.ceil(orphanTotalCount / bigPerPage);

        function fetchNextPage() {
            if (page > totalPages) {
                addAllInBatches(allFiles, 0, allFiles.length);
                return;
            }
            $('#swal-collected-count').text(allFiles.length);
            $.ajax({
                url: '{{ route("attachment-audit.orphan-files-page") }}',
                method: 'GET',
                data: { page: page, per_page: bigPerPage },
                success: function(response) {
                    if (response.success && response.data.orphan_files) {
                        allFiles.push(...response.data.orphan_files);
                    }
                    page++;
                    fetchNextPage();
                },
                error: function() {
                    Swal.fire('خطأ', 'حدث خطأ أثناء جمع الملفات من الصفحة ' + page, 'error');
                }
            });
        }
        fetchNextPage();
    }

    function addAllInBatches(allFiles, startIdx, totalCount) {
        if (startIdx >= totalCount) {
            Swal.fire('تم!', 'تم إضافة ' + totalCount + ' ملف بنجاح', 'success');
            $('#btn-find-orphan').click();
            return;
        }

        const batch = allFiles.slice(startIdx, startIdx + 200);
        const progress = Math.round(((startIdx + batch.length) / totalCount) * 100);
        const batchNum = Math.floor(startIdx / 200) + 1;
        const totalBatches = Math.ceil(totalCount / 200);

        Swal.update({
            title: 'جاري الإضافة...',
            html: '<div>الدفعة ' + batchNum + ' من ' + totalBatches + ' — تم ' + (startIdx + batch.length) + ' من ' + totalCount + '</div>' +
                  '<div class="progress mt-2" style="height:6px;max-width:300px;margin:8px auto 0"><div class="progress-bar" style="width:' + progress + '%"></div></div>',
            showConfirmButton: false
        });

        const fd = buildOrphanFormData(batch, 0);

        $.ajax({
            url: '{{ route("attachment-audit.add-orphan-files") }}',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const serverAdded = (response.data && response.data.added_count) ? response.data.added_count : batch.length;
                    addAllInBatches(allFiles, startIdx + serverAdded, totalCount);
                } else {
                    Swal.fire('خطأ', response.message || 'فشلت إضافة الدفعة', 'error');
                }
            },
            error: function() {
                Swal.fire('خطأ', 'حدث خطأ في الدفعة ' + batchNum, 'error');
            }
        });
    }

});
</script>
@endpush
@endsection
