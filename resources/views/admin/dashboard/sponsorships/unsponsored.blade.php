@extends('admin.dashboard.toolbars.index')

@push('styles')
<style>
    /* تحسين مظهر الـ Modal */
    .modal-xl {
        max-width: 95%;
    }

    .modal-body {
        padding: 1.5rem;
    }

    /* تحسين الـ DataTable في الصفحة الرئيسية */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        margin: 0.5rem 0;
    }

    /* تحسين الأزرار */
    .btn-info:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    /* تحسين modal التفاصيل */
    #recordDetailsModal .modal-body {
        padding: 0;
    }

    #recordDetailsModal .modal-body .container-fluid {
        padding: 1.5rem;
    }

    /* إخفاء navbar و sidebars في modal */
    #recordDetailsModal .navbar,
    #recordDetailsModal .sidebar,
    #recordDetailsModal .main-sidebar,
    #recordDetailsModal .content-header {
        display: none !important;
    }

    /* تحسين الجداول داخل modal التفاصيل */
    #recordDetailsModal .table {
        margin-bottom: 0;
    }

    #recordDetailsModal .card {
        border: none;
        box-shadow: none;
    }

    /* تصميم حقل البحث القوي */
    .unified-search-container {
        position: relative;
        max-width: 600px;
        margin: 0 auto 20px;
        padding: 0 15px;
    }

    .unified-search-input {
        width: 100%;
        padding: 12px 55px 12px 55px;
        border: 2px solid #000000;
        border-radius: 25px;
        font-size: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        direction: rtl;
        text-align: right;
    }

    .unified-search-input::placeholder {
        text-align: right;
        direction: rtl;
    }

    .unified-search-input:focus {
        outline: none;
        border-color: #000000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .unified-search-btn {
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: #000000;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }

    .unified-search-btn:hover {
        background: #333333;
        transform: translateY(-50%) scale(1.05);
    }

    .unified-search-btn i {
        color: white;
        font-size: 16px;
    }

    .search-loading {
        position: absolute;
        left: 27px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 9;
    }

    .clear-search-btn {
        position: absolute;
        right: 25px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        cursor: pointer;
        color: #666666;
        font-size: 18px;
        display: none;
        z-index: 10;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .clear-search-btn:hover {
        color: #000000;
    }

    /* نتائج البحث */
    #unifiedSearchResults {
        margin-top: 20px;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .search-result-card {
        border: 1px solid #cccccc;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .search-result-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }

    .search-result-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .search-result-name {
        font-size: 18px;
        font-weight: bold;
        color: #000000;
    }

    .search-result-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .info-item {
        display: flex;
        align-items: center;
        font-size: 14px;
    }

    .info-item i {
        margin-left: 8px;
        color: #000000;
    }

    .no-results {
        text-align: center;
        padding: 40px;
        color: #666666;
    }

    /* تحسين عرض نتائج البحث */
    #unifiedSearchResults {
        text-align: center;
        margin: 20px auto;
    }

    #unifiedSearchResults h5 {
        margin-bottom: 15px;
        color: #000000;
        font-weight: bold;
    }

    #unifiedSearchResults .search-categories {
        text-align: right;
    }

    /* تنسيق عنوان نتائج البحث - متجاوب مع جميع الأجهزة */
    .search-results-header {
        margin-top: 24px !important;
    }

    /* تجاوب مع الشاشات الصغيرة (الهواتف) */
    @media (max-width: 576px) {
        .search-results-header {
            margin-top: 16px !important;
        }

        .unified-search-container {
            max-width: 100%;
            padding: 0 10px;
            margin: 0 0 20px 0;
        }

        .unified-search-input {
            padding: 10px 50px 10px 50px;
            font-size: 14px;
            border-radius: 20px;
        }

        .unified-search-btn {
            width: 36px;
            height: 36px;
            left: 12px;
        }

        .unified-search-btn i {
            font-size: 14px;
        }

        .clear-search-btn {
            right: 15px;
            font-size: 16px;
            width: 28px;
            height: 28px;
        }

        .search-loading {
            left: 19px;
        }

        .search-result-card {
            padding: 12px;
        }

        .search-result-name {
            font-size: 16px;
        }

        .search-result-info {
            grid-template-columns: 1fr;
        }
    }

    /* تجاوب مع الأجهزة اللوحية */
    @media (min-width: 577px) and (max-width: 768px) {
        .search-results-header {
            margin-top: 20px !important;
        }

        .unified-search-container {
            padding: 0 15px;
        }

        .unified-search-input {
            padding: 11px 52px 11px 52px;
            font-size: 15px;
        }

        .unified-search-btn {
            width: 38px;
            height: 38px;
            left: 17px;
        }

        .unified-search-btn i {
            font-size: 15px;
        }

        .clear-search-btn {
            right: 20px;
            font-size: 17px;
        }

        .search-result-info {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* تجاوب مع الشاشات المتوسطة */
    @media (min-width: 769px) and (max-width: 992px) {
        .search-results-header {
            margin-top: 22px !important;
        }

        .unified-search-btn:hover {
            transform: translateY(-50%) scale(1.05);
        }
    }

    /* الشاشات الكبيرة */
    @media (min-width: 993px) {

    }
</style>
@endpush

@section('content')
    <div class="container-fluid">
        <!-- محرك البحث القوي -->
        <div class="unified-search-container">
            <input type="text"
                   id="unifiedSearchInput"
                   class="unified-search-input"
                   placeholder=" ابحث بالاسم، رقم الهوية، رقم الملف، أو رقم الجوال..."
                   autocomplete="off">
            <button type="button" class="clear-search-btn" id="clearSearchBtn" title="مسح البحث">
                <i class="fas fa-times"></i>
            </button>
            <button type="button" class="unified-search-btn" id="unifiedSearchBtn" title="بحث">
                <i class="fas fa-search"></i>
            </button>
            <div class="search-loading" id="searchLoading" style="display: none;">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">جاري البحث...</span>
                </div>
            </div>
        </div>

        <!-- نتائج البحث -->
        <div id="unifiedSearchResults" style="display: none;"></div>

        <!-- جدول السجلات الأصلي -->
        <div id="originalTableContainer">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">سجل الإدارة</h3>
                            <div class="card-tools">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('admin.records.management.exportAll') }}" class="btn btn-success btn-sm mt-4">
                                        <i class="fas fa-file-export"></i> استخراج الكل (Excel)
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                           {!! $dataTable->table(['class' => 'table table-bordered table-striped text-center align-middle w-100'], true) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal البحث الشامل -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="searchModalLabel">
                        <i class="fas fa-search"></i> البحث الشامل في السجلات
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                    <!-- سيتم تحميل محتوى البحث هنا -->
                    <div id="searchModalContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                            <p class="mt-3 text-muted">جاري تحميل نموذج البحث...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> إغلاق
                    </button>
                    <a href="{{ route('search.records.index') }}" class="btn btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> فتح في صفحة منفصلة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Creating Sponsorship -->
    <div class="modal fade" id="createSponsorshipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h2 class="fw-bold text-white" id="modalTitle">
                        <i class="bi bi-heart-fill"></i> تنفيذ كفالة جديدة
                    </h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="createSponsorshipForm">
                    @csrf
                    <input type="hidden" id="record_id" name="record_id">
                    <input type="hidden" id="record_type" name="record_type">

                    <div class="modal-body py-4 px-lg-5">
                        <div class="scroll-y" style="max-height: 600px; overflow-y: auto;">

                            <!--begin::معلومات السجل-->
                            <div class="alert alert-info mb-5">
                                <h5 class="mb-2">
                                    <i class="bi bi-info-circle"></i> معلومات السجل المختار
                                    <span id="person_type_badge" class="float-end"></span>
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>رقم الملف:</strong> <span id="display_file_id">-</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>الاسم:</strong> <span id="display_record_name">-</span>
                                    </div>
                                </div>
                            </div>

                            <!--begin::معلومات الكافل-->
                            <div class="mb-5">
                                <h4 class="fw-bold text-gray-900 mb-3">
                                    <i class="bi bi-person-badge"></i> معلومات الكافل
                                </h4>
                                <div class="separator mb-4"></div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold required">إسم المؤسسة الكافلة</label>
                                    <select class="form-select" name="sponsor_id" id="create_sponsor_id" required>
                                        <option value="">اختر المؤسسة الكافلة</option>
                                        @foreach($sponsors as $sponsor)
                                            <option value="{{ $sponsor->id }}">{{ $sponsor->sponsor_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">الكافل</label>
                                    <input type="text" class="form-control" placeholder="اسم الكافل"
                                           name="sponsoring_organization" id="create_sponsoring_organization" />
                                </div>
                            </div>

                            <!--begin::معلومات المكفول-->
                            <div class="mb-5 mt-4">
                                <h4 class="fw-bold text-gray-900 mb-3">
                                    <i class="bi bi-person"></i> معلومات المكفول
                                </h4>
                                <div class="separator mb-4"></div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">رقم الملف الداخلي</label>
                                    <input type="text" class="form-control" placeholder="رقم الملف الداخلي"
                                           name="internal_file_number" id="create_internal_file_number" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">رقم الملف الخارجي</label>
                                    <input type="text" class="form-control" placeholder="رقم الملف الخارجي"
                                           name="external_file_number" id="create_external_file_number" />
                                </div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">رقم الهوية</label>
                                    <input type="text" class="form-control" placeholder="رقم الهوية"
                                           name="identity_number" id="create_identity_number" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">اسم المكفول</label>
                                    <input type="text" class="form-control" placeholder="إسم المكفول"
                                           name="orphan_name" id="create_orphan_name" />
                                </div>
                            </div>

                            <div class="mb-5" id="guardian_manual_field_wrapper">
                                <label class="form-label fw-semibold">إسم المعيل</label>
                                <input type="text" class="form-control" placeholder="إسم المعيل"
                                       name="guardian_name" id="create_guardian_name" />
                            </div>

                            <div class="mb-5" id="guardian_field_wrapper" style="display: none;">
                                <label class="form-label fw-semibold">إسم المعيل الأساسي</label>
                                <input type="text" class="form-control bg-light" placeholder="سيتم جلبه تلقائياً"
                                       name="guardian_name_auto" id="create_guardian_name_auto" readonly />
                                <small class="text-muted">تم جلب اسم المعيل تلقائياً من قاعدة البيانات</small>
                            </div>                            <!--begin::تفاصيل الكفالة-->
                            <div class="mb-5 mt-4">
                                <h4 class="fw-bold text-gray-900 mb-3">
                                    <i class="bi bi-calendar-check"></i> تفاصيل الكفالة
                                </h4>
                                <div class="separator mb-4"></div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold required">مدة الكفالة (بالأشهر)</label>
                                    <input type="number" class="form-control" placeholder="عدد الأشهر"
                                           name="sponsorship_duration_months" id="create_sponsorship_duration_months"
                                           min="1" required />
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold required">تاريخ البداية</label>
                                    <input type="date" class="form-control"
                                           name="sponsorship_start_date" id="create_sponsorship_start_date" required />
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">تاريخ النهاية</label>
                                    <input type="date" class="form-control"
                                           name="sponsorship_end_date" id="create_sponsorship_end_date" />
                                </div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold required">نوع الكفالة</label>
                                    <select class="form-select" name="sponsorship_type_id" id="create_sponsorship_type_id" required>
                                        <option value="">اختر نوع الكفالة</option>
                                        @foreach($sponsorshipTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->description }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold required">حالة الكفالة</label>
                                    <select class="form-select" name="sponsorship_status_id" id="create_sponsorship_status_id" required>
                                        <option value="">اختر حالة الكفالة</option>
                                        @foreach($sponsorshipStatuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">ملاحظات</label>
                                <textarea class="form-control" rows="3" name="notes" id="create_notes"
                                          placeholder="أي ملاحظات إضافية"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> إلغاء
                        </button>
                        <button type="submit" class="btn btn-success" id="createSponsorshipSubmitBtn">
                            <span class="indicator-label">
                                <i class="bi bi-check-circle"></i> حفظ الكفالة
                            </span>
                            <span class="indicator-progress d-none">
                                جاري الحفظ...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}
    <script>
        $(document).ready(function() {
            // ============================================
            // محرك البحث القوي الموحد
            // ============================================

            let searchTimeout;
            const $searchInput = $('#unifiedSearchInput');
            const $searchBtn = $('#unifiedSearchBtn');
            const $clearBtn = $('#clearSearchBtn');
            const $searchLoading = $('#searchLoading');
            const $searchResults = $('#unifiedSearchResults');
            const $originalTable = $('#originalTableContainer');

            // البحث عند الكتابة (مع تأخير)
            $searchInput.on('input', function() {
                const query = $(this).val().trim();

                if (query.length > 0) {
                    $clearBtn.css('display', 'flex');
                } else {
                    $clearBtn.css('display', 'none');
                    clearSearchResults();
                    return;
                }

                clearTimeout(searchTimeout);

                if (query.length >= 3) {
                    searchTimeout = setTimeout(() => {
                        performUnifiedSearch(query);
                    }, 500);
                }
            });

            // البحث عند الضغط على زر البحث
            $searchBtn.on('click', function() {
                const query = $searchInput.val().trim();
                if (query.length >= 2) {
                    performUnifiedSearch(query);
                }
            });

            // البحث عند الضغط على Enter
            $searchInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const query = $(this).val().trim();
                    if (query.length >= 2) {
                        performUnifiedSearch(query);
                    }
                }
            });

            // مسح البحث
            $clearBtn.on('click', function() {
                $searchInput.val('');
                $(this).css('display', 'none');
                clearSearchResults();
            });

            // تنفيذ البحث الموحد
            function performUnifiedSearch(query) {
                console.log('Searching for:', query);

                $searchLoading.show();
                $searchBtn.prop('disabled', true);

                $.ajax({
                    url: '{{ route("admin.sponsorships.unifiedSearch") }}',
                    method: 'POST',
                    data: {
                        query: query,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        console.log('Search results:', response);

                        if (response.success && response.total_count > 0) {
                            displaySearchResults(response);
                            $originalTable.hide();
                            $searchResults.show();
                        } else {
                            displayNoResults(query);
                            $originalTable.hide();
                            $searchResults.show();
                        }
                    },
                    error: function(xhr) {
                        console.error('Search error:', xhr);

                        let errorMessage = 'حدث خطأ في البحث';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        $searchResults.html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> ${errorMessage}
                            </div>
                        `).show();
                        $originalTable.hide();
                    },
                    complete: function() {
                        $searchLoading.hide();
                        $searchBtn.prop('disabled', false);
                    }
                });
            }

            // عرض نتائج البحث
            function displaySearchResults(response) {
                let html = `
                    <div class="card">
                        <div class="card-header bg-success text-white search-results-header">
                            <h4 class="mb-0">
                                <i class="fas fa-check-circle"></i>
                                تم العثور على ${response.total_count} نتيجة للبحث عن: "${response.query}"
                            </h4>
                        </div>
                        <div class="card-body">
                `;

                // المعيلين
                if (response.results.breadwinners && response.results.breadwinners.length > 0) {
                    html += `
                        <h5 class="mb-3">
                            <i class="fas fa-user-tie"></i> المعيلين (${response.results.breadwinners.length})
                        </h5>
                        <div class="row">
                    `;

                    response.results.breadwinners.forEach(person => {
                        html += createResultCard(person);
                    });

                    html += '</div><hr>';
                }

                // أفراد العائلة والأيتام
                if (response.results.family_members && response.results.family_members.length > 0) {
                    html += `
                        <h5 class="mb-3">
                            <i class="fas fa-users"></i> أفراد العائلة والأيتام (${response.results.family_members.length})
                        </h5>
                        <div class="row">
                    `;

                    response.results.family_members.forEach(person => {
                        html += createResultCard(person);
                    });

                    html += '</div><hr>';
                }

                // المتوفين
                if (response.results.deceased && response.results.deceased.length > 0) {
                    html += `
                        <h5 class="mb-3">
                            <i class="fas fa-cross"></i> المتوفين (${response.results.deceased.length})
                        </h5>
                        <div class="row">
                    `;

                    response.results.deceased.forEach(records => {
                        if (Array.isArray(records)) {
                            records.forEach(person => {
                                html += createResultCard(person);
                            });
                        } else {
                            html += createResultCard(records);
                        }
                    });

                    html += '</div>';
                }

                html += `
                        </div>
                    </div>
                `;

                $searchResults.html(html);
            }

            // إنشاء بطاقة نتيجة
            function createResultCard(person) {
                const personTypeBadges = {
                    'breadwinner': '<span class="badge border border-dark text-dark">معيل</span>',
                    'orphan': '<span class="badge border border-dark text-dark">يتيم</span>',
                    'family_member': '<span class="badge border border-dark text-dark">فرد عائلة</span>',
                    'deceased_father': '<span class="badge border border-dark text-dark">أب متوفي</span>',
                    'deceased_mother': '<span class="badge border border-dark text-dark">أم متوفية</span>'
                };

                const badge = personTypeBadges[person.person_type] || '<span class="badge border border-dark text-dark">غير محدد</span>';

                return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="search-result-card"
                             onclick="selectPersonForSponsorship('${person.id}', '${person.record_type}', '${person.person_type}', '${person.full_name}', '${person.file_id}', '${person.identity_number}', '${person.guardian_name || ''}', '${person.guardian_id || ''}')">
                            <div class="search-result-header">
                                <div class="search-result-name">${person.full_name}</div>
                                ${badge}
                            </div>
                            <div class="search-result-info">
                                <div class="info-item">
                                    <i class="fas fa-folder"></i>
                                    <span>رقم الملف: ${person.file_id || '-'}</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-id-card"></i>
                                    <span>الهوية: ${person.identity_number || '-'}</span>
                                </div>
                                ${person.phone ? `
                                <div class="info-item">
                                    <i class="fas fa-phone"></i>
                                    <span>${person.phone}</span>
                                </div>
                                ` : ''}
                                ${person.guardian_name ? `
                                <div class="info-item">
                                    <i class="fas fa-user-shield"></i>
                                    <span>المعيل: ${person.guardian_name}</span>
                                </div>
                                ` : ''}
                                ${person.city ? `
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>${person.city}</span>
                                </div>
                                ` : ''}
                            </div>
                            <div class="mt-2 text-end">
                                <button class="btn btn-sm btn-success">
                                    <i class="bi bi-heart-fill"></i> تنفيذ كفالة
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            // عرض رسالة عدم وجود نتائج
            function displayNoResults(query) {
                $searchResults.html(`
                    <div class="card">
                        <div class="card-body">
                            <div class="no-results">
                                <i class="fas fa-search fa-3x mb-3"></i>
                                <h4>لا توجد نتائج للبحث عن: "${query}"</h4>
                                <p>حاول البحث باستخدام كلمات مفتاحية أخرى</p>
                            </div>
                        </div>
                    </div>
                `);
            }

            // مسح نتائج البحث
            function clearSearchResults() {
                $searchResults.hide().html('');
                $originalTable.show();
            }

            // اختيار شخص للكفالة من نتائج البحث
            window.selectPersonForSponsorship = function(recordId, recordType, personType, fullName, fileId, identityNumber, guardianName, guardianId) {
                console.log('Selected person:', { recordId, recordType, personType, fullName, fileId, identityNumber, guardianName, guardianId });

                // استدعاء نفس الوظيفة الموجودة لزر تنفيذ الكفالة
                $.ajax({
                    url: '{{ route("admin.sponsorships.getPersonDetails") }}',
                    method: 'POST',
                    data: {
                        record_id: recordId,
                        record_type: recordType,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        console.log('Person details:', response);

                        // تعبئة بيانات السجل الأساسية
                        $('#record_id').val(recordId);
                        $('#record_type').val(recordType);
                        $('#display_file_id').text(response.file_id || fileId);
                        $('#display_record_name').text(response.full_name || fullName);

                        // تعبئة الحقول الأساسية
                        $('#create_orphan_name').val(response.full_name || fullName);
                        $('#create_internal_file_number').val(response.file_id || fileId);
                        $('#create_identity_number').val(response.identity_number || identityNumber);

                        // معالجة حقل المعيل حسب نوع الشخص
                        const guardianFieldWrapper = $('#guardian_field_wrapper');
                        const guardianManualWrapper = $('#guardian_manual_field_wrapper');
                        const guardianAutoInput = $('#create_guardian_name_auto');
                        const guardianManualInput = $('#create_guardian_name');

                        if (response.needs_guardian) {
                            guardianFieldWrapper.show();
                            guardianManualWrapper.hide();
                            guardianAutoInput.val(response.guardian_name || guardianName || '');
                            guardianManualInput.val(response.guardian_name || guardianName || '');
                        } else {
                            guardianFieldWrapper.hide();
                            guardianManualWrapper.hide();
                            guardianManualInput.val('');
                            guardianAutoInput.val('');
                        }

                        // إضافة badge يوضح نوع الشخص
                        let personTypeBadge = '';
                        switch(response.person_type) {
                            case 'breadwinner':
                                personTypeBadge = '<span class="badge border border-dark text-dark">معيل</span>';
                                break;
                            case 'orphan':
                                personTypeBadge = '<span class="badge border border-dark text-dark">يتيم</span>';
                                break;
                            case 'family_member':
                                personTypeBadge = '<span class="badge border border-dark text-dark">فرد عائلة</span>';
                                break;
                            case 'deceased_father':
                            case 'deceased_mother':
                                personTypeBadge = '<span class="badge border border-dark text-dark">متوفي</span>';
                                break;
                        }
                        $('#person_type_badge').html(personTypeBadge);

                        // إظهار المودال
                        $('#createSponsorshipModal').modal('show');
                    },
                    error: function(xhr) {
                        console.error('Error fetching person details:', xhr);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: 'حدث خطأ في جلب معلومات الشخص',
                            confirmButtonColor: '#000000'
                        });
                    }
                });
            };

            // ============================================
            // باقي الكود الأصلي
            // ============================================
            // التأكد من أن dropdowns تعمل بشكل صحيح
            $(document).on('click', '.dropdown-toggle', function(e) {
                e.preventDefault();
                $(this).dropdown('toggle');
            });

            // إعادة تهيئة Bootstrap dropdowns بعد تحديث DataTable
            $('#recordsmanagemente-table').on('draw.dt', function() {
                // إعادة تهيئة dropdowns
                var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
                var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                    return new bootstrap.Dropdown(dropdownToggleEl);
                });
            });

            // معالجة النقر على زر تنفيذ كفالة
            $(document).on('click', '.sponsorship-btn', function(e) {
                e.preventDefault();

                const recordId = $(this).data('record-id');
                const recordType = $(this).data('record-type');
                const personType = $(this).data('person-type');
                const recordName = $(this).data('record-name');
                const fileId = $(this).data('file-id');
                const identityNumber = $(this).data('identity-number');
                const guardianName = $(this).data('guardian-name');
                const guardianId = $(this).data('guardian-id');

                console.log('Opening sponsorship modal for:', {
                    recordId, recordType, personType, recordName, fileId, identityNumber, guardianName, guardianId
                });

                // جلب تفاصيل الشخص من السيرفر
                $.ajax({
                    url: '{{ route("admin.sponsorships.getPersonDetails") }}',
                    method: 'POST',
                    data: {
                        record_id: recordId,
                        record_type: recordType,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        console.log('Person details:', response);

                        // تعبئة بيانات السجل الأساسية
                        $('#record_id').val(recordId);
                        $('#record_type').val(recordType);
                        $('#display_file_id').text(response.file_id || fileId);
                        $('#display_record_name').text(response.full_name || recordName);

                        // تعبئة الحقول الأساسية
                        $('#create_orphan_name').val(response.full_name || recordName);
                        $('#create_internal_file_number').val(response.file_id || fileId);
                        $('#create_identity_number').val(response.identity_number || identityNumber);

                        // معالجة حقل المعيل حسب نوع الشخص
                        const guardianFieldWrapper = $('#guardian_field_wrapper');
                        const guardianManualWrapper = $('#guardian_manual_field_wrapper');
                        const guardianAutoInput = $('#create_guardian_name_auto');
                        const guardianManualInput = $('#create_guardian_name');

                        if (response.needs_guardian) {
                            // الشخص يحتاج معيل (يتيم أو فرد عائلة)
                            guardianFieldWrapper.show(); // إظهار حقل المعيل الأوتوماتيكي
                            guardianManualWrapper.hide(); // إخفاء الحقل اليدوي
                            guardianAutoInput.val(response.guardian_name || guardianName || '');

                            // نسخ القيمة إلى الحقل الذي سيتم إرساله
                            guardianManualInput.val(response.guardian_name || guardianName || '');
                        } else {
                            // الشخص لا يحتاج معيل (معيل أو متوفي)
                            guardianFieldWrapper.hide(); // إخفاء حقل المعيل الأوتوماتيكي
                            guardianManualWrapper.hide(); // إخفاء الحقل اليدوي أيضاً
                            guardianManualInput.val('');
                            guardianAutoInput.val('');
                        }

                        // إضافة badge يوضح نوع الشخص
                        let personTypeBadge = '';
                        switch(response.person_type) {
                            case 'breadwinner':
                                personTypeBadge = '<span class="badge border border-dark text-dark">معيل</span>';
                                break;
                            case 'orphan':
                                personTypeBadge = '<span class="badge border border-dark text-dark">يتيم</span>';
                                break;
                            case 'family_member':
                                personTypeBadge = '<span class="badge border border-dark text-dark">فرد عائلة</span>';
                                break;
                            case 'deceased_father':
                            case 'deceased_mother':
                                personTypeBadge = '<span class="badge border border-dark text-dark">متوفي</span>';
                                break;
                        }
                        $('#person_type_badge').html(personTypeBadge);

                        // إظهار المودال
                        $('#createSponsorshipModal').modal('show');
                    },
                    error: function(xhr) {
                        console.error('Error fetching person details:', xhr);

                        // في حالة الخطأ، استخدم البيانات الموجودة
                        $('#record_id').val(recordId);
                        $('#record_type').val(recordType);
                        $('#display_file_id').text(fileId);
                        $('#display_record_name').text(recordName);
                        $('#create_orphan_name').val(recordName);
                        $('#create_internal_file_number').val(fileId);
                        $('#create_identity_number').val(identityNumber);

                        // إخفاء جميع حقول المعيل في حالة الخطأ
                        $('#guardian_field_wrapper').hide();
                        $('#guardian_manual_field_wrapper').hide();

                        $('#person_type_badge').html('<span class="badge border border-dark text-dark">غير محدد</span>');

                        // إظهار المودال حتى في حالة الخطأ
                        $('#createSponsorshipModal').modal('show');
                    }
                });
            });            // معالجة إرسال نموذج إنشاء الكفالة
            $('#createSponsorshipForm').on('submit', function(e) {
                e.preventDefault();

                const submitBtn = $('#createSponsorshipSubmitBtn');
                const indicatorLabel = submitBtn.find('.indicator-label');
                const indicatorProgress = submitBtn.find('.indicator-progress');

                // تعطيل الزر وإظهار التحميل
                submitBtn.prop('disabled', true);
                indicatorLabel.addClass('d-none');
                indicatorProgress.removeClass('d-none');

                const formData = new FormData(this);

                $.ajax({
                    url: '{{ route("admin.sponsorships.store") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        console.log('Sponsorship created successfully:', response);

                        // إخفاء المودال
                        $('#createSponsorshipModal').modal('hide');

                        // إعادة تعيين النموذج
                        $('#createSponsorshipForm')[0].reset();

                        // إظهار رسالة نجاح
                        Swal.fire({
                            icon: 'success',
                            title: 'تم بنجاح!',
                            text: response.message || 'تم إنشاء الكفالة بنجاح',
                            confirmButtonText: 'حسناً',
                            confirmButtonColor: '#000000'
                        });

                        // إعادة تحميل الجدول
                        if ($.fn.DataTable.isDataTable('#recordsmanagemente-table')) {
                            $('#recordsmanagemente-table').DataTable().ajax.reload(null, false);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error creating sponsorship:', xhr);

                        let errorMessage = 'حدث خطأ أثناء إنشاء الكفالة';

                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }

                            // عرض أخطاء التحقق
                            if (xhr.responseJSON.errors) {
                                let errorsList = '<ul class="text-start">';
                                $.each(xhr.responseJSON.errors, function(field, errors) {
                                    errors.forEach(function(error) {
                                        errorsList += '<li>' + error + '</li>';
                                    });
                                });
                                errorsList += '</ul>';
                                errorMessage += errorsList;
                            }
                        } else if (xhr.status === 422) {
                            errorMessage = 'بيانات الكفالة غير صحيحة، يرجى التحقق من جميع الحقول';
                        } else if (xhr.status === 500) {
                            errorMessage = 'خطأ في الخادم، يرجى المحاولة لاحقاً';
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            html: errorMessage,
                            confirmButtonText: 'حسناً',
                            confirmButtonColor: '#000000'
                        });
                    },
                    complete: function() {
                        // إعادة تفعيل الزر
                        submitBtn.prop('disabled', false);
                        indicatorLabel.removeClass('d-none');
                        indicatorProgress.addClass('d-none');
                    }
                });
            });

            // إعادة تعيين النموذج عند إغلاق المودال
            $('#createSponsorshipModal').on('hidden.bs.modal', function() {
                $('#createSponsorshipForm')[0].reset();
                $('#record_id').val('');
                $('#record_type').val('');
                $('#display_file_id').text('-');
                $('#display_record_name').text('-');
                $('#person_type_badge').html('');

                // إعادة تعيين حقول المعيل
                $('#guardian_field_wrapper').hide();
                $('#guardian_manual_field_wrapper').hide();
                $('#create_guardian_name').val('');
                $('#create_guardian_name_auto').val('');
            });

            // تحميل محتوى البحث عند فتح الـ modal
            $('#searchModal').on('show.bs.modal', function() {
                loadSearchContent();
            });

            // مسح محتوى الـ modal عند إغلاقه
            $('#searchModal').on('hidden.bs.modal', function() {
                $('#searchModalContent').html(`
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="mt-2">جاري تحميل نموذج البحث...</p>
                    </div>
                `);
            });

            function loadSearchContent() {
                $.ajax({
                    url: '{{ route("search.records.modal") }}',
                    method: 'GET',
                    success: function(response) {
                        $('#searchModalContent').html(response);

                        // إعادة تهيئة العناصر التفاعلية
                        initializeSearchModal();
                    },
                    error: function(xhr, status, error) {
                        console.error('خطأ في تحميل البحث:', error);
                        $('#searchModalContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                حدث خطأ في تحميل نموذج البحث. يرجى المحاولة مرة أخرى.
                            </div>
                        `);
                    }
                });
            }

            function initializeSearchModal() {
                // إعادة تهيئة الأحداث والوظائف الخاصة بالبحث
                const $modalContent = $('#searchModalContent');

                // التأكد من وجود CSRF token
                if (!$('meta[name="csrf-token"]').attr('content')) {
                    console.error('CSRF token not found');
                    $modalContent.prepend('<meta name="csrf-token" content="{{ csrf_token() }}">');
                }

                // تنفيذ البحث
                $modalContent.find('#searchForm').on('submit', function(e) {
                    e.preventDefault();
                    console.log('Form submitted'); // للتتبع
                    performModalSearch();
                });

                // البحث السريع
                let searchTimeout;
                $modalContent.find('#search_text').on('input', function() {
                    const query = $(this).val().trim();
                    clearTimeout(searchTimeout);

                    if (query.length >= 3) {
                        searchTimeout = setTimeout(() => {
                            console.log('Auto search triggered for:', query); // للتتبع
                            performModalSearch();
                        }, 500);
                    }
                });

                // مسح النموذج
                $modalContent.find('#clearForm').on('click', function() {
                    console.log('Clearing form'); // للتتبع
                    $modalContent.find('#searchForm')[0].reset();
                    $modalContent.find('#searchResults').hide();
                });

                console.log('Search modal initialized successfully'); // للتتبع
            }

            function performModalSearch(page = 1) {
                const $form = $('#searchModalContent #searchForm');

                if (!$form.length) {
                    console.error('Search form not found');
                    return;
                }

                const formData = new FormData($form[0]);
                formData.append('page', page);

                // التحقق من وجود نص البحث أو مرشحات
                const searchText = formData.get('search_text');
                const searchType = formData.get('search_type');

                console.log('Search params:', {
                    search_text: searchText,
                    search_type: searchType,
                    page: page
                });

                if (!searchText && !formData.get('file_id') && !formData.get('identity_number') && !formData.get('phone_number')) {
                    $('#searchModalContent #searchResults').html(`
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            يرجى إدخال نص للبحث أو تحديد المرشحات
                        </div>
                    `).show();
                    return;
                }

                // عرض شاشة التحميل
                const $loadingOverlay = $('#searchModalContent #loadingOverlay');
                $loadingOverlay.show();

                $.ajax({
                    url: '{{ route("search.records") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        console.log('Search response:', response); // للتتبع

                        if (response.success && response.data) {
                            displayModalResults(response.data);
                        } else {
                            $('#searchModalContent #searchResults').html(`
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    ${response.message || 'لم يتم العثور على نتائج'}
                                </div>
                            `).show();
                        }
                    },
                    error: function(xhr) {
                        console.error('Search error:', xhr); // للتتبع

                        let message = 'حدث خطأ أثناء البحث';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        } else if (xhr.status === 422) {
                            message = 'بيانات البحث غير صحيحة';
                        } else if (xhr.status === 429) {
                            message = 'تم تجاوز الحد المسموح لطلبات البحث. يرجى المحاولة لاحقاً';
                        }

                        $('#searchModalContent #searchResults').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> ${message}
                            </div>
                        `).show();
                    },
                    complete: function() {
                        $loadingOverlay.hide();
                    }
                });
            }

            function displayModalResults(data) {
                console.log('=== Displaying Modal Results ===');
                console.log('Raw data:', data);
                console.log('Data type:', typeof data);
                console.log('Data keys:', Object.keys(data));

                console.log('=== Search Response Data Structure ===');
                console.log('Full data object:', data);
                console.log('Data type:', typeof data);
                console.log('Is array:', Array.isArray(data));

                if (data.data) {
                    console.log('data.data:', data.data);
                    console.log('data.data type:', typeof data.data);
                    console.log('data.data is array:', Array.isArray(data.data));
                    if (Array.isArray(data.data) && data.data.length > 0) {
                        console.log('First record sample:', data.data[0]);
                        console.log('First record keys:', Object.keys(data.data[0]));
                    }
                }

                const $searchResults = $('#searchModalContent #searchResults');

                // التحقق من وجود بيانات
                let totalCount = 0;
                let hasResults = false;

                // حساب العدد الإجمالي بناءً على هيكل البيانات
                if (data.total_count !== undefined) {
                    totalCount = data.total_count;
                    console.log('Using total_count:', totalCount);
                } else if (data.main_records || data.family_members || data.deceased) {
                    totalCount = (data.main_records ? data.main_records.length : 0) +
                                (data.family_members ? data.family_members.length : 0) +
                                (data.deceased ? data.deceased.length : 0);
                    console.log('Calculated from arrays:', totalCount);
                } else if (data.data && Array.isArray(data.data)) {
                    totalCount = data.data.length;
                    console.log('Using data array length:', totalCount);
                } else if (data.pagination && data.pagination.total_records) {
                    totalCount = data.pagination.total_records;
                    console.log('Using pagination total:', totalCount);
                }

                console.log('Final total count:', totalCount);
                hasResults = totalCount > 0;

                if (hasResults) {
                    let resultsHtml = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            تم العثور على ${totalCount} نتيجة
                        </div>
                    `;

                    // عرض النتائج حسب النوع
                    if (data.main_records && data.main_records.length > 0) {
                        console.log('Processing main_records:', data.main_records.length);
                        resultsHtml += '<h6><i class="fas fa-folder"></i> السجلات الرئيسية (' + data.main_records.length + '):</h6>';
                        resultsHtml += formatModalResults(data.main_records);
                    }

                    if (data.family_members && data.family_members.length > 0) {
                        console.log('Processing family_members:', data.family_members.length);
                        resultsHtml += '<h6 class="mt-3"><i class="fas fa-users"></i> أفراد الأسرة (' + data.family_members.length + '):</h6>';
                        resultsHtml += formatFamilyModalResults(data.family_members);
                    }

                    if (data.deceased && data.deceased.length > 0) {
                        console.log('Processing deceased:', data.deceased.length);
                        resultsHtml += '<h6 class="mt-3"><i class="fas fa-cross"></i> المتوفين (' + data.deceased.length + '):</h6>';
                        resultsHtml += formatDeceasedModalResults(data.deceased);
                    }

                    // إذا كانت البيانات في data.data (للبحث في نوع واحد)
                    if (data.data && Array.isArray(data.data) && data.data.length > 0) {
                        console.log('Processing data.data:', data.data.length);
                        resultsHtml += '<h6><i class="fas fa-list"></i> النتائج:</h6>';
                        resultsHtml += formatModalResults(data.data);
                    }

                    console.log('Setting results HTML');
                    $searchResults.html(resultsHtml).show();
                } else {
                    console.log('No results found');
                    $searchResults.html(`
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            لم يتم العثور على نتائج مطابقة لمعايير البحث
                        </div>
                    `).show();
                }

                console.log('=== End Display Results ===');
            }

            function formatModalResults(records) {
                if (!records || records.length === 0) {
                    return '<p class="text-muted">لا توجد سجلات</p>';
                }

                let html = '<div class="table-responsive"><table class="table table-sm table-striped">';
                html += '<thead><tr>';
                html += '<th>رقم الملف</th><th>الاسم الكامل</th><th>رقم الهوية</th><th>تاريخ الميلاد</th><th>الجوال</th><th>القسم</th><th>صلة القرابة</th><th>الحالة الصحية</th><th>الحالة الاجتماعية</th><th>المؤهل العلمي</th><th>المدينة</th><th>الإجراء</th>';
                html += '</tr></thead><tbody>';

                records.forEach(record => {
                    // التعامل مع الأسماء المختلفة
                    let fullName = '';
                    if (record.data_first_name || record.data_father_name || record.data_grand_father_name || record.data_family_name) {
                        fullName = `${record.data_first_name || ''} ${record.data_father_name || ''} ${record.data_grand_father_name || ''} ${record.data_family_name || ''}`.trim();
                    } else if (record.first_name || record.father_name || record.grand_father_name || record.family_name) {
                        fullName = `${record.first_name || ''} ${record.father_name || ''} ${record.grand_father_name || ''} ${record.family_name || ''}`.trim();
                    } else if (record.full_name) {
                        fullName = record.full_name;
                    }

                    // استخدام البيانات من العلاقات أو القيم المباشرة
                    const sectionName = (record.section && record.section.description) ? record.section.description :
                                       (record.section_name || '-');
                    const fileId = record.file_id_number || record.file_id || '-';
                    const idNumber = record.data_id_number || record.id_number || '-';
                    const phoneNumber = record.data_phone_number || record.phone_number || '-';
                    const birthDate = record.data_birth_date || record.birth_date || '-';

                    const relationshipName = (record.category_of_relation && record.category_of_relation.attribute) ?
                                           record.category_of_relation.attribute :
                                           ((record.categoryOfRelation && record.categoryOfRelation.attribute) ?
                                           record.categoryOfRelation.attribute :
                                           (record.relationship_name || '-'));

                    const healthStatusName = (record.health_status && record.health_status.description) ?
                                           record.health_status.description :
                                           ((record.healthStatus && record.healthStatus.description) ?
                                           record.healthStatus.description :
                                           (record.health_status_name || '-'));

                    const maritalStatusName = (record.marital_status && record.marital_status.description) ?
                                            record.marital_status.description :
                                            ((record.maritalStatus && record.maritalStatus.description) ?
                                            record.maritalStatus.description :
                                            (record.marital_status_name || '-'));

                    const academicQualificationName = (record.academic_qualification && record.academic_qualification.description) ?
                                                    record.academic_qualification.description :
                                                    ((record.academicQualification && record.academicQualification.description) ?
                                                    record.academicQualification.description :
                                                    (record.academic_qualification_name || '-'));

                    const cityName = (record.city && record.city.city) ?
                                   record.city.city :
                                   (record.city_name || '-');

                    html += `<tr>
                        <td>${fileId}</td>
                        <td><strong>${fullName || '-'}</strong></td>
                        <td>${idNumber}</td>
                        <td>${birthDate}</td>
                        <td>${phoneNumber}</td>
                        <td><span class="badge border border-dark text-dark">${sectionName}</span></td>
                        <td>${relationshipName}</td>
                        <td><span class="badge border border-dark text-dark">${healthStatusName}</span></td>
                        <td>${maritalStatusName}</td>
                        <td>${academicQualificationName}</td>
                        <td>${cityName}</td>
                        <td>
                            ${record.id ? `<a href="/admin/records-management/${record.id}/show" class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-eye"></i> عرض
                            </a>` : '-'}
                        </td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                return html;
            }

            function formatFamilyModalResults(records) {
                if (!records || records.length === 0) {
                    return '<p class="text-muted">لا توجد سجلات لأفراد الأسرة</p>';
                }

                let html = '<div class="table-responsive"><table class="table table-sm table-striped table-success">';
                html += '<thead><tr>';
                html += '<th>رقم الملف</th><th>الاسم الكامل</th><th>رقم الهوية</th><th>تاريخ الميلاد</th><th>العمر</th><th>الجنس</th><th>الحالة الصحية</th>';
                html += '</tr></thead><tbody>';

                records.forEach(record => {
                    let fullName = '';
                    if (record.data_first_name || record.data_father_name || record.data_grand_father_name || record.data_family_name) {
                        fullName = `${record.data_first_name || ''} ${record.data_father_name || ''} ${record.data_grand_father_name || ''} ${record.data_family_name || ''}`.trim();
                    } else if (record.first_name || record.father_name || record.grand_father_name || record.family_name) {
                        fullName = `${record.first_name || ''} ${record.father_name || ''} ${record.grand_father_name || ''} ${record.family_name || ''}`.trim();
                    }

                    // تحويل الجنس: 1 = ذكر، 2 = أنثى، أو استخدام القيمة النصية مباشرة
                    let gender = '-';
                    if (record.person_gender == 1 || record.gender === 'ذكر') {
                        gender = 'ذكر';
                    } else if (record.person_gender == 2 || record.gender === 'أنثى') {
                        gender = 'أنثى';
                    } else if (record.gender) {
                        gender = record.gender;
                    }

                    const fileId = record.file_id_number || record.file_id || '-';
                    const idNumber = record.data_id_number || record.id_number || '-';
                    const birthDate = record.data_birth_date || record.birth_date || '-';

                    const healthStatusName = (record.health_status && record.health_status.description) ?
                                           record.health_status.description :
                                           ((record.healthStatus && record.healthStatus.description) ?
                                           record.healthStatus.description :
                                           (record.health_status_name || '-'));

                    html += `<tr>
                        <td>${fileId}</td>
                        <td><strong>${fullName || '-'}</strong></td>
                        <td>${idNumber}</td>
                        <td>${birthDate}</td>
                        <td>${record.age || '-'}</td>
                        <td><span class="badge border border-dark text-dark">${gender}</span></td>
                        <td><span class="badge border border-dark text-dark">${healthStatusName}</span></td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                return html;
            }

            function formatDeceasedModalResults(records) {
                if (!records || records.length === 0) {
                    return '<p class="text-muted">لا توجد سجلات للمتوفين</p>';
                }

                let html = '<div class="table-responsive"><table class="table table-sm table-striped table-warning">';
                html += '<thead><tr>';
                html += '<th>رقم الملف</th><th>بيانات الأب</th><th>بيانات الأم</th>';
                html += '</tr></thead><tbody>';

                records.forEach(record => {
                    const fileId = record.file_id_number || record.file_id || '-';
                    let fatherInfo = '-';
                    let motherInfo = '-';

                    if (record.father_name || record.father_id_number) {
                        fatherInfo = `${record.father_name || ''} - ${record.father_id_number || ''}`.replace(' - ', ' ').trim();
                        if (fatherInfo === '-') fatherInfo = record.father_name || record.father_id_number || '-';
                    }

                    if (record.mother_name || record.mother_id_number) {
                        motherInfo = `${record.mother_name || ''} - ${record.mother_id_number || ''}`.replace(' - ', ' ').trim();
                        if (motherInfo === '-') motherInfo = record.mother_name || record.mother_id_number || '-';
                    }

                    html += `<tr>
                        <td>${fileId}</td>
                        <td>${fatherInfo}</td>
                        <td>${motherInfo}</td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                return html;
            }

            // دالة لتحميل تفاصيل السجل داخل modal منفصل
            window.loadRecordDetails = function(recordId) {
                console.log('Loading record details for ID:', recordId);

                // إنشاء modal جديد لعرض تفاصيل السجل
                const detailsModal = `
                    <div class="modal fade" id="recordDetailsModal" tabindex="-1" aria-labelledby="recordDetailsLabel" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title" id="recordDetailsLabel">
                                        <i class="fas fa-file-alt"></i> تفاصيل السجل رقم ${recordId}
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0" style="max-height: 80vh; overflow-y: auto;">
                                    <div id="recordDetailsContent" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">جاري التحميل...</span>
                                        </div>
                                        <p class="mt-3 text-muted">جاري تحميل تفاصيل السجل...</p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> إغلاق
                                    </button>
                                    <a href="/admin/records-management/${recordId}/show" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> فتح في صفحة منفصلة
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // إزالة modal السابق إن وجد
                $('#recordDetailsModal').remove();

                // إضافة modal جديد
                $('body').append(detailsModal);

                // إخفاء modal البحث مؤقتاً لتجنب التضارب
                $('#searchModal').modal('hide');

                // عرض modal التفاصيل
                $('#recordDetailsModal').modal('show');

                // عند إغلاق modal التفاصيل، إعادة عرض modal البحث إذا كان مفتوحاً
                $('#recordDetailsModal').on('hidden.bs.modal', function () {
                    $(this).remove();
                    // يمكن إعادة فتح modal البحث إذا كان المستخدم يريد ذلك
                    // $('#searchModal').modal('show');
                });

                // تحميل محتوى السجل
                $.ajax({
                    url: `/admin/records-management/${recordId}/show`,
                    method: 'GET',
                    success: function(response) {
                        console.log('Record details loaded successfully');

                        // استخراج محتوى الصفحة
                        const $response = $(response);
                        let content = '';

                        // محاولة العثور على المحتوى الرئيسي
                        if ($response.find('.container-fluid').length) {
                            content = $response.find('.container-fluid').html();
                        } else if ($response.find('.content').length) {
                            content = $response.find('.content').html();
                        } else if ($response.find('main').length) {
                            content = $response.find('main').html();
                        } else if ($response.find('.card-body').length) {
                            content = $response.find('.card-body').html();
                        } else {
                            // أخذ الـ body كامل مع تنظيف الـ scripts
                            content = $response.find('body').html();
                            if (!content) {
                                content = response;
                            }
                        }

                        $('#recordDetailsContent').html(content);

                        // إعادة تهيئة أي bootstrap components
                        setTimeout(() => {
                            $('#recordDetailsModal .nav-tabs a').on('click', function (e) {
                                e.preventDefault();
                                $(this).tab('show');
                            });

                            // إعادة تهيئة tooltips إن وجدت
                            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                                var tooltipTriggerList = [].slice.call(document.querySelectorAll('#recordDetailsModal [data-bs-toggle="tooltip"]'));
                                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                                    return new bootstrap.Tooltip(tooltipTriggerEl);
                                });
                            }
                        }, 100);
                    },
                    error: function(xhr) {
                        console.error('Error loading record details:', xhr);
                        let errorMessage = 'حدث خطأ في تحميل تفاصيل السجل';

                        if (xhr.status === 404) {
                            errorMessage = 'السجل المطلوب غير موجود';
                        } else if (xhr.status === 403) {
                            errorMessage = 'ليس لديك صلاحية لعرض هذا السجل';
                        } else if (xhr.status === 500) {
                            errorMessage = 'خطأ في الخادم. يرجى المحاولة لاحقاً';
                        }

                        $('#recordDetailsContent').html(`
                            <div class="alert alert-danger m-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                ${errorMessage}
                                <br><small>كود الخطأ: ${xhr.status}</small>
                            </div>
                        `);
                    }
                });
            };
        });
    </script>
@endpush
