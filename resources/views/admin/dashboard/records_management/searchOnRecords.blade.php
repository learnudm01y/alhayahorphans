@extends('admin.dashboard.toolbars.index')

@push('scripts')
<!-- Fallback function definitions in case external file fails to load -->
<script>
// Ensure functions are available immediately
window.displayResultsAsTable = window.displayResultsAsTable || function(data) {
    console.log('Using fallback displayResultsAsTable function');
    let allRecords = [];

    // جمع جميع السجلات من المصادر المختلفة
    if (data.main_records && data.main_records.length > 0) {
        data.main_records.forEach(record => {
            allRecords.push({
                ...record,
                record_type: 'سجل رئيسي',
                record_type_class: 'bg-primary'
            });
        });
    }

    if (data.family_members && data.family_members.length > 0) {
        data.family_members.forEach(record => {
            allRecords.push({
                ...record,
                record_type: 'فرد أسرة',
                record_type_class: 'bg-success'
            });
        });
    }

    if (data.deceased && data.deceased.length > 0) {
        data.deceased.forEach(record => {
            allRecords.push({
                ...record,
                record_type: 'متوفي',
                record_type_class: 'bg-warning'
            });
        });
    }

    // تحديث عداد النتائج
    $('#resultsCount').text(allRecords.length + ' نتيجة');

    // بناء محتوى الجدول
    let tableBodyHtml = '';

    allRecords.forEach((record, index) => {
        const fullName = record.full_name || 'غير محدد';
        const identityNumber = record.identity_number || record.data_id_number || '-';
        const phone = record.phone || record.data_phone_number || '-';
        const age = record.age || record.age_at_death || '-';
        const gender = record.gender || record.data_gender || '-';
        const city = record.city_name || '-';
        const province = record.province_name || '-';
        const requestStatus = record.request_status_name || record.request_status || '-';

        tableBodyHtml +=
            '<tr class="table-row-hover">' +
                '<td class="text-center">' + (index + 1) + '</td>' +
                '<td><strong>' + fullName + '</strong></td>' +
                '<td><code class="text-primary">' + identityNumber + '</code></td>' +
                '<td>' + (phone !== '-' ? '<a href="tel:' + phone + '" class="text-success">' + phone + '</a>' : '-') + '</td>' +
                '<td class="text-center">' + (age !== '-' ? '<span class="badge bg-info">' + age + '</span>' : '-') + '</td>' +
                '<td class="text-center">' + (gender !== '-' ? '<span class="badge bg-primary">' + gender + '</span>' : '-') + '</td>' +
                '<td><span class="badge bg-secondary">' + city + '</span></td>' +
                '<td><span class="badge bg-info">' + province + '</span></td>' +
                '<td><span class="badge ' + getStatusBadgeClass(requestStatus) + '">' + requestStatus + '</span></td>' +
                '<td class="text-center"><span class="badge ' + record.record_type_class + ' text-white">' + record.record_type + '</span></td>' +
                '<td class="text-center">' +
                    '<div class="btn-group btn-group-sm">' +
                        (record.id && record.record_type === 'سجل رئيسي' ? '<a href="admin/records-management/' + record.id + '/show" class="btn btn-primary btn-sm" title="عرض السجل الكامل"><i class="fas fa-file-alt"></i></a>' : '') +
                        '<button class="btn btn-info btn-sm" onclick="showRecordDetails(\'' + encodeURIComponent(JSON.stringify(record)) + '\')" title="عرض التفاصيل السريعة">' +
                            '<i class="fas fa-info-circle"></i>' +
                        '</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
    });

    // عرض النتائج في الجدول
    $('#resultsTableBody').html(tableBodyHtml);

    if (allRecords.length === 0) {
        $('#resultsTableBody').html('<tr><td colspan="11" class="text-center py-4">لم يتم العثور على نتائج</td></tr>');
    }
};

window.displayResults = window.displayResults || function(data) {
    console.log('Using fallback displayResults function');
    window.displayResultsAsTable(data);
};

window.getStatusBadgeClass = window.getStatusBadgeClass || function(status) {
    if (!status || status === '-') return 'bg-secondary';
    if (status.includes('مقبول') || status.includes('موافق')) return 'bg-success';
    if (status.includes('مرفوض') || status.includes('ملغي')) return 'bg-danger';
    if (status.includes('قيد') || status.includes('مراجعة')) return 'bg-warning';
    return 'bg-info';
};

window.showRecordDetails = window.showRecordDetails || function(encodedRecord) {
    try {
        const record = JSON.parse(decodeURIComponent(encodedRecord));
        alert('تفاصيل السجل: ' + (record.full_name || 'غير محدد'));
    } catch(e) {
        alert('خطأ في عرض التفاصيل');
    }
};

console.log('Fallback functions loaded successfully');
</script>

<!-- ملف JavaScript للبحث -->
<script src="{{ asset('js/search-results.js') }}" onload="console.log('search-results.js loaded successfully')" onerror="console.error('Failed to load search-results.js')"></script>
@endpush

@push('styles')
<style>
    /* تحسين مظهر عناوين الجدول */
    .table-dark th {
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%) !important;
        color: #ffffff !important;
        font-weight: 600;
        font-size: 0.9rem;
        border: none !important;
        padding: 12px 8px;
        text-align: center;
        vertical-align: middle;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .table-dark th i {
        margin-left: 5px;
        opacity: 0.9;
        font-size: 0.85rem;
    }

    .table-dark th:hover {
        background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%) !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    /* تحسين عرض البيانات */
    .table td {
        vertical-align: middle;
        padding: 10px 8px;
    }

    /* تحسين مظهر الاقتراحات */
    #searchSuggestions {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 1000;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        background: white;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    #searchSuggestions .dropdown-item {
        padding: 0.5rem 1rem;
        border: none;
        color: #212529;
        text-decoration: none;
        display: block;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    #searchSuggestions .dropdown-item:hover,
    #searchSuggestions .dropdown-item.active {
        background-color: #f8f9fa;
        color: #495057;
    }

    #searchSuggestions .dropdown-item i {
        opacity: 0.5;
    }

    /* تحسين مظهر النتائج */
    .search-result-card {
        border: 1px solid #e3e6f0;
        border-radius: 0.35rem;
        transition: all 0.3s;
    }

    .search-result-card:hover {
        border-color: #5a5c69;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .result-type-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    /* تحسين شاشة التحميل */
    #loadingOverlay {
        backdrop-filter: blur(2px);
    }

    /* تحسين التصفح */
    .pagination .page-link {
        color: #5a5c69;
        border-color: #dddfeb;
    }

    .pagination .page-item.active .page-link {
        background-color: #5a5c69;
        border-color: #5a5c69;
    }

    /* تحسين الأزرار */
    .btn-search {
        background: linear-gradient(45deg, #4e73df, #224abe);
        border: none;
        color: white;
        transition: all 0.3s;
    }

    .btn-search:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
    }

    /* تحسين الجداول */
    .table-responsive {
        border-radius: 0.35rem;
        overflow: hidden;
    }

    .table th {
        background-color: #f8f9fc;
        border-color: #e3e6f0;
        font-weight: 600;
        color: #5a5c69;
        font-size: 0.85rem;
        padding: 1rem 0.75rem;
    }

    .table td {
        border-color: #e3e6f0;
        padding: 0.75rem;
        vertical-align: middle;
    }

    /* تحسين الـ badges */
    .badge {
        font-size: 0.75rem;
        font-weight: 500;
    }

    /* تحسين المرشحات المتقدمة */
    .collapse {
        border-top: 1px solid #e3e6f0;
        margin-top: 1rem;
        padding-top: 1rem;
    }

    /* تحسين responsive */
    @media (max-width: 768px) {
        .card-tools {
            margin-top: 1rem;
        }

        #searchSuggestions {
            max-height: 150px;
        }

        .table-responsive {
            font-size: 0.875rem;
        }
    }

    /* تحسينات إضافية للعرض */
    .search-result-row {
        transition: all 0.3s ease;
    }

    .search-result-row:hover {
        background-color: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .card {
        transition: all 0.3s ease;
        border-radius: 8px;
    }

    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-1px);
    }

    /* تحسينات الألوان */
    .bg-pink {
        background-color: #e91e63 !important;
    }

    .bg-purple {
        background-color: #9c27b0 !important;
    }

    .text-pink {
        color: #e91e63 !important;
    }

    /* تحسينات النوافذ المنبثقة */
    .modal-xl {
        max-width: 95%;
        margin: 1rem auto;
    }

    .modal-header {
        border-bottom: 2px solid rgba(255,255,255,0.2);
        padding: 1.25rem;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
    }

    .modal-body {
        padding: 1.5rem;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }

    /* أنماط خاصة للسجلات */
    .deceased-card {
        border-left: 4px solid #dc3545;
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    }

    .family-card {
        border-left: 4px solid #28a745;
        background: linear-gradient(135deg, #fff 0%, #f0fff4 100%);
    }

    .main-record-card {
        border-left: 4px solid #007bff;
        background: linear-gradient(135deg, #fff 0%, #f0f8ff 100%);
    }

    /* تحسينات رسائل التنبيه */
    .search-info-alert {
        border-left: 4px solid #17a2b8;
        background: linear-gradient(135deg, #d1ecf1 0%, #ffffff 100%);
        animation: slideDown 0.3s ease-out;
    }

    .error-alert {
        border-left: 4px solid #dc3545;
        background: linear-gradient(135deg, #f8d7da 0%, #ffffff 100%);
        animation: slideDown 0.3s ease-out;
    }

    .success-alert {
        border-left: 4px solid #28a745;
        background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);
        animation: slideDown 0.3s ease-out;
    }

    .warning-alert {
        border-left: 4px solid #ffc107;
        background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* تحسينات أزرار الإغلاق */
    .btn-close {
        filter: opacity(0.7);
        transition: filter 0.2s ease;
    }

    .btn-close:hover {
        filter: opacity(1);
    }

    /* أنماط البطاقات المجمعة */
    .person-card {
        transition: all 0.4s ease;
        border: 1px solid #dee2e6;
    }

    .person-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: #007bff;
    }

    .person-card .card-header {
        position: relative;
        overflow: hidden;
    }

    .person-card .card-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .person-card:hover .card-header::before {
        left: 100%;
    }

    /* أنماط التدرجات المحسنة */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }

    .bg-gradient-success {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    }

    .bg-gradient-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }

    /* تحسينات النصوص */
    .opacity-75 {
        opacity: 0.75;
    }

    /* تحسينات الأيقونات */
    .fas.rotate-on-hover {
        transition: transform 0.3s ease;
    }

    .card:hover .fas.rotate-on-hover {
        transform: rotate(360deg);
    }

    /* أنماط البيانات التفصيلية */
    .detail-card {
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .detail-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    /* تحسينات للشارات */
    .badge {
        font-weight: 500;
        letter-spacing: 0.5px;
    }

    .badge.bg-light {
        color: #495057 !important;
        background-color: #f8f9fa !important;
        border: 1px solid #dee2e6;
    }

    /* تحسينات responsive للبطاقات المجمعة */
    @media (max-width: 768px) {
        .person-card .card-header .col-md-4 {
            margin-top: 1rem;
        }

        .person-card .card-footer .d-flex {
            flex-direction: column;
            gap: 0.5rem;
        }

        .person-card .card-footer .btn {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- صفحة البحث الشامل في السجلات -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">البحث الشامل في السجلات</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-info btn-sm" id="searchStatsBtn">
                            <i class="fas fa-chart-bar"></i> إحصائيات البحث
                        </button>
                    </div>
                </div>

                <!-- نموذج البحث -->
                <div class="card-body">
                    <form id="searchForm" class="mb-4">
                        <div class="row">
                            <!-- نوع البحث -->
                            <div class="col-md-3 mb-3">
                                <label for="search_type" class="form-label">نوع البحث</label>
                                <select class="form-select" id="search_type" name="search_type" required>
                                    <option value="all">جميع الجداول</option>
                                    <option value="main_records">السجلات الرئيسية</option>
                                    <option value="family_members">أفراد الأسرة</option>
                                    <option value="deceased">المتوفين</option>
                                </select>
                            </div>

                            <!-- البحث النصي العام -->
                            <div class="col-md-6 mb-3">
                                <label for="search_text" class="form-label">البحث العام</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="search_text" name="search_text"
                                           placeholder="ابحث بالاسم، رقم الهوية، رقم الجوال، رقم الملف..."
                                           autocomplete="off">
                                    <div id="searchSuggestions" class="dropdown-menu w-100" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                                </div>
                            </div>

                            <!-- عدد النتائج في الصفحة -->
                            <div class="col-md-3 mb-3">
                                <label for="per_page" class="form-label">عدد النتائج</label>
                                <select class="form-select" id="per_page" name="per_page">
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>

                        <!-- مرشحات متقدمة -->
                        <div class="row">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-secondary btn-sm mb-3"
                                        data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                                    <i class="fas fa-filter"></i> مرشحات متقدمة
                                </button>
                            </div>
                        </div>

                        <div class="collapse" id="advancedFilters">
                            <div class="row">
                                <!-- رقم الملف -->
                                <div class="col-md-3 mb-3">
                                    <label for="file_id" class="form-label">رقم الملف</label>
                                    <input type="text" class="form-control" id="file_id" name="file_id">
                                </div>

                                <!-- رقم الهوية -->
                                <div class="col-md-3 mb-3">
                                    <label for="identity_number" class="form-label">رقم الهوية</label>
                                    <input type="text" class="form-control" id="identity_number" name="identity_number">
                                </div>

                                <!-- رقم الجوال -->
                                <div class="col-md-3 mb-3">
                                    <label for="phone_number" class="form-label">رقم الجوال</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number">
                                </div>

                                <!-- الجنس -->
                                <div class="col-md-3 mb-3">
                                    <label for="gender" class="form-label">الجنس</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">اختر الجنس</option>
                                        <option value="male">ذكر</option>
                                        <option value="female">أنثى</option>
                                    </select>
                                </div>

                                <!-- القسم -->
                                <div class="col-md-3 mb-3">
                                    <label for="section_id" class="form-label">القسم</label>
                                    <select class="form-select" id="section_id" name="section_id">
                                        <option value="">اختر القسم</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->description }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- المدينة -->
                                <div class="col-md-3 mb-3">
                                    <label for="city_id" class="form-label">المدينة</label>
                                    <select class="form-select" id="city_id" name="city_id">
                                        <option value="">اختر المدينة</option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}">{{ $city->city }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- حالة الطلب -->
                                <div class="col-md-3 mb-3">
                                    <label for="request_status_id" class="form-label">حالة الطلب</label>
                                    <select class="form-select" id="request_status_id" name="request_status_id">
                                        <option value="">اختر حالة الطلب</option>
                                        @foreach($requestStatuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- الحالة الصحية -->
                                <div class="col-md-3 mb-3">
                                    <label for="health_status_id" class="form-label">الحالة الصحية</label>
                                    <select class="form-select" id="health_status_id" name="health_status_id">
                                        <option value="">اختر الحالة الصحية</option>
                                        @foreach($healthStatuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- تاريخ الميلاد من -->
                                <div class="col-md-3 mb-3">
                                    <label for="birth_date_from" class="form-label">تاريخ الميلاد من</label>
                                    <input type="date" class="form-control" id="birth_date_from" name="birth_date_from">
                                </div>

                                <!-- تاريخ الميلاد إلى -->
                                <div class="col-md-3 mb-3">
                                    <label for="birth_date_to" class="form-label">تاريخ الميلاد إلى</label>
                                    <input type="date" class="form-control" id="birth_date_to" name="birth_date_to">
                                </div>

                                <!-- صلة القرابة -->
                                <div class="col-md-3 mb-3">
                                    <label for="relationship_id" class="form-label">صلة القرابة</label>
                                    <select class="form-select" id="relationship_id" name="relationship_id">
                                        <option value="">اختر صلة القرابة</option>
                                        @foreach($relationships as $relation)
                                            <option value="{{ $relation->id }}">{{ $relation->attribute }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- الحالة الاجتماعية -->
                                <div class="col-md-3 mb-3">
                                    <label for="marital_status_id" class="form-label">الحالة الاجتماعية</label>
                                    <select class="form-select" id="marital_status_id" name="marital_status_id">
                                        <option value="">اختر الحالة الاجتماعية</option>
                                        @foreach($maritalStatuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- المؤهل العلمي -->
                                <div class="col-md-3 mb-3">
                                    <label for="academic_qualification_id" class="form-label">المؤهل العلمي</label>
                                    <select class="form-select" id="academic_qualification_id" name="academic_qualification_id">
                                        <option value="">اختر المؤهل العلمي</option>
                                        @foreach($academicQualifications as $qualification)
                                            <option value="{{ $qualification->id }}">{{ $qualification->description }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- حالة العمل -->
                                <div class="col-md-3 mb-3">
                                    <label for="employment_status_id" class="form-label">حالة العمل</label>
                                    <select class="form-select" id="employment_status_id" name="employment_status_id">
                                        <option value="">اختر حالة العمل</option>
                                        @foreach($employmentStatuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- أزرار التحكم -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-search">
                                    <i class="fas fa-search"></i> بحث
                                </button>
                                <button type="button" class="btn btn-secondary" id="clearForm">
                                    <i class="fas fa-eraser"></i> مسح المرشحات
                                </button>
                                <button type="button" class="btn btn-warning" id="clearCacheBtn" style="display: none;">
                                    <i class="fas fa-broom"></i> مسح الذاكرة المؤقتة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- نتائج البحث -->
    <div class="row mt-4" id="searchResults" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-search-plus"></i> نتائج البحث</h5>
                    <div class="search-stats">
                        <span class="badge bg-light text-dark" id="resultsCount">0 نتيجة</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0" id="resultsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 4%;" class="text-center" title="الرقم التسلسلي للنتائج في الجدول" data-bs-toggle="tooltip">
                                        <i class="fas fa-hashtag"></i> الرقم التسلسلي
                                    </th>
                                    <th style="width: 18%;" title="الاسم الكامل للشخص المسجل" data-bs-toggle="tooltip">
                                        <i class="fas fa-user"></i> الاسم الكامل
                                    </th>
                                    <th style="width: 10%;" class="text-center" title="رقم الهوية الوطنية أو الشخصية للمستفيد" data-bs-toggle="tooltip">
                                        <i class="fas fa-id-card"></i> رقم الهوية الوطنية
                                    </th>
                                    <th style="width: 9%;" class="text-center" title="رقم الهاتف الجوال للتواصل" data-bs-toggle="tooltip">
                                        <i class="fas fa-phone"></i> رقم الجوال
                                    </th>
                                    <th style="width: 6%;" class="text-center" title="العمر الحالي بالسنوات" data-bs-toggle="tooltip">
                                        <i class="fas fa-birthday-cake"></i> العمر
                                    </th>
                                    <th style="width: 6%;" class="text-center" title="الجنس: ذكر أو أنثى" data-bs-toggle="tooltip">
                                        <i class="fas fa-venus-mars"></i> الجنس
                                    </th>
                                    <th style="width: 10%;" class="text-center" title="المدينة التي يقيم فيها المستفيد" data-bs-toggle="tooltip">
                                        <i class="fas fa-map-marker-alt"></i> المدينة
                                    </th>
                                    <th style="width: 10%;" class="text-center" title="المحافظة أو المنطقة الإدارية" data-bs-toggle="tooltip">
                                        <i class="fas fa-map"></i> المحافظة/المنطقة
                                    </th>
                                    <th style="width: 10%;" class="text-center" title="حالة طلب المساعدة: مقبول، مرفوض، قيد المراجعة" data-bs-toggle="tooltip">
                                        <i class="fas fa-check-circle"></i> حالة الطلب
                                    </th>
                                    <th style="width: 7%;" class="text-center" title="نوع السجل: سجل رئيسي، فرد أسرة، أو متوفي" data-bs-toggle="tooltip">
                                        <i class="fas fa-folder-open"></i> نوع السجل
                                    </th>
                                    <th style="width: 10%;" class="text-center" title="العمليات المتاحة: عرض السجل الكامل، عرض التفاصيل السريعة" data-bs-toggle="tooltip">
                                        <i class="fas fa-cogs"></i> العمليات المتاحة
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="resultsTableBody">
                                <!-- سيتم ملء البيانات بواسطة JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- التصفح -->
                    <div id="pagination" class="p-3 border-top">
                        <!-- سيتم إضافة التصفح هنا -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- إحصائيات البحث -->
    <div class="modal fade" id="searchStatsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إحصائيات البحث</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statsContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- شاشة التحميل -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
     background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
         background: white; padding: 20px; border-radius: 10px; text-align: center;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جاري البحث...</span>
        </div>
        <p class="mt-2 mb-0">جاري البحث في السجلات...</p>
    </div>
</div>
@endsection

@push('scriptsCode')
<script>
$(document).ready(function() {
    // Define essential functions immediately to ensure they're available
    window.displayResultsAsTable = window.displayResultsAsTable || function(data) {
        console.log('Using inline displayResultsAsTable function');
        let allRecords = [];

        // جمع جميع السجلات من المصادر المختلفة
        if (data.main_records && data.main_records.length > 0) {
            data.main_records.forEach(record => {
                allRecords.push({
                    ...record,
                    record_type: 'سجل رئيسي',
                    record_type_class: 'bg-primary'
                });
            });
        }

        if (data.family_members && data.family_members.length > 0) {
            data.family_members.forEach(record => {
                allRecords.push({
                    ...record,
                    record_type: 'فرد أسرة',
                    record_type_class: 'bg-success'
                });
            });
        }

        if (data.deceased && data.deceased.length > 0) {
            data.deceased.forEach(record => {
                allRecords.push({
                    ...record,
                    record_type: 'متوفي',
                    record_type_class: 'bg-warning'
                });
            });
        }

        // تحديث عداد النتائج
        $('#resultsCount').text(allRecords.length + ' نتيجة');

        // بناء محتوى الجدول
        let tableBodyHtml = '';

        allRecords.forEach((record, index) => {
            const fullName = record.full_name || 'غير محدد';
            // تحسين عرض رقم الهوية مع دعم مصادر متعددة
            const identityNumber = record.identity_number || record.data_id_number || record.person_id || record.dead_people_id_number || '-';
            const phone = record.phone || record.data_phone_number || '-';
            const age = record.age || record.age_at_death || '-';
            const gender = record.gender || record.data_gender || record.dead_people_gender || '-';
            // تحسين عرض المدينة مع دعم مصادر متعددة
            const city = record.city_name || record.city || record.data_city || record.city_ar || record.city_en || '-';
            const province = record.province_name || record.province || record.data_province || record.province_ar || record.province_en || '-';
            const requestStatus = record.request_status_name || record.request_status || record.data_request_status || '-';

            tableBodyHtml +=
                '<tr class="table-row-hover">' +
                    '<td class="text-center">' + (index + 1) + '</td>' +
                    '<td><strong>' + fullName + '</strong></td>' +
                    // تحسين عرض رقم الهوية مع إبراز بصري أفضل
                    '<td><code class="bg-light text-primary px-2 py-1 rounded">' + identityNumber + '</code></td>' +
                    '<td>' + (phone !== '-' ? '<a href="tel:' + phone + '" class="text-success">' + phone + '</a>' : '-') + '</td>' +
                    '<td class="text-center">' + (age !== '-' ? '<span class="badge bg-info">' + age + '</span>' : '-') + '</td>' +
                    '<td class="text-center">' + (gender !== '-' ? '<span class="badge bg-primary">' + gender + '</span>' : '-') + '</td>' +
                    // تحسين عرض المدينة مع إبراز بصري أفضل
                    '<td><span class="badge bg-secondary" style="font-size: 0.9em;"><i class="fas fa-map-marker-alt me-1"></i>' + city + '</span></td>' +
                    '<td><span class="badge bg-info" style="font-size: 0.9em;"><i class="fas fa-map me-1"></i>' + province + '</span></td>' +
                    '<td><span class="badge ' + getStatusBadgeClass(requestStatus) + '">' + requestStatus + '</span></td>' +
                    '<td class="text-center"><span class="badge ' + record.record_type_class + ' text-white">' + record.record_type + '</span></td>' +
                    '<td class="text-center">' +
                        '<button class="btn btn-info btn-sm" onclick="showRecordDetails(\'' + encodeURIComponent(JSON.stringify(record)) + '\')" title="عرض التفاصيل">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>';
        });

        // عرض النتائج في الجدول
        $('#resultsTableBody').html(tableBodyHtml);

        if (allRecords.length === 0) {
            $('#resultsTableBody').html('<tr><td colspan="11" class="text-center py-4">لم يتم العثور على نتائج</td></tr>');
        }
    };

    window.displayResults = window.displayResults || function(data) {
        console.log('Using inline displayResults function');
        window.displayResultsAsTable(data);
    };

    window.getStatusBadgeClass = window.getStatusBadgeClass || function(status) {
        if (!status || status === '-') return 'bg-secondary';
        if (status.includes('مقبول') || status.includes('موافق')) return 'bg-success';
        if (status.includes('مرفوض') || status.includes('ملغي')) return 'bg-danger';
        if (status.includes('قيد') || status.includes('مراجعة')) return 'bg-warning';
        return 'bg-info';
    };

    window.showRecordDetails = window.showRecordDetails || function(encodedRecord) {
        try {
            const record = JSON.parse(decodeURIComponent(encodedRecord));
            alert('تفاصيل السجل: ' + (record.full_name || 'غير محدد'));
        } catch(e) {
            alert('خطأ في عرض التفاصيل');
        }
    };

    console.log('Essential functions defined inline and ready');

    // تفعيل tooltips للعناوين
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    let currentPage = 1;
    let currentSearchData = {};

    // تنفيذ البحث
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        performSearch();
    });

    // مسح النموذج
    $('#clearForm').on('click', function() {
        $('#searchForm')[0].reset();
        $('#searchResults').hide();
        $('#exportResults').hide();
    });

    // عرض الإحصائيات
    $('#searchStatsBtn').on('click', function() {
        loadSearchStats();
    });

    // مسح الذاكرة المؤقتة
    $('#clearCacheBtn').on('click', function() {
        clearSearchCache();
    });

    /*
     * تنفيذ البحث المحسن بخوارزمية الاسم الكامل
     */
    function performSearch(page = 1) {
        const formData = new FormData($('#searchForm')[0]);
        formData.append('page', page);

        // تحسين البحث بالاسم الكامل - استخدام البحث الدقيق للأسماء الطويلة
        const searchText = $('#search_text').val().trim();
        if (searchText) {
            const wordCount = searchText.split(' ').filter(word => word.length > 0).length;

            // إضافة معلومات حول نوع البحث
            if (wordCount > 4) {
                console.log('استخدام البحث الدقيق للاسم الطويل:', searchText, '(' + wordCount + ' كلمات)');
            } else {
                console.log('استخدام البحث الشامل للاسم القصير:', searchText, '(' + wordCount + ' كلمات)');
            }
        }

        // حفظ بيانات البحث الحالي
        currentSearchData = Object.fromEntries(formData);
        currentPage = page;

        showLoading(true);

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
                if (response.success) {
                    displayResults(response.data);
                    $('#searchResults').show();
                    $('#exportResults').show();
                    $('#clearCacheBtn').show();

                    // عرض معلومات البحث المحسنة
                    if (response.search_info) {
                        console.log('تم البحث بنجاح:', response.search_info);

                        // عرض نوع البحث المستخدم
                        if (response.search_info.search_type && searchText) {
                            const wordCount = searchText.split(' ').filter(word => word.length > 0).length;
                            const searchTypeInfo = wordCount > 4 ? 'البحث الدقيق' : 'البحث الشامل';

                            showSearchInfo(`تم استخدام ${searchTypeInfo} - وجد ${response.search_info.total_results} نتيجة`);
                        }
                    }
                } else {
                    showError('فشل في تنفيذ البحث: ' + response.message);
                }
            },
            error: function(xhr) {
                let message = 'حدث خطأ أثناء البحث';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showError(message);
                console.error('خطأ البحث:', xhr.responseJSON || xhr.responseText);
            },
            complete: function() {
                showLoading(false);
            }
        });
    }

    /*
     * الحصول على كلاس حالة الطلب
     */
    function getStatusBadgeClass(status) {
        if (!status || status === '-') return 'bg-secondary';

        if (status.includes('مقبول') || status.includes('موافق')) return 'bg-success';
        if (status.includes('مرفوض') || status.includes('ملغي')) return 'bg-danger';
        if (status.includes('قيد') || status.includes('مراجعة')) return 'bg-warning';

        return 'bg-info';
    }

    /*
     * عرض جميع النتائج - النظام المحسن للبيانات المجمعة
     */
    function displayAllResults(data) {
        // تحديث العدادات
        $('#allCount').text(data.total_count || 0);
        $('#mainCount').text(data.main_records ? data.main_records.length : 0);
        $('#familyCount').text(data.family_members ? data.family_members.length : 0);
        $('#deceasedCount').text(data.deceased ? data.deceased.length : 0);
        $('#resultsCount').text(`إجمالي النتائج: ${data.total_count || 0}`);

        // عرض النتائج الرئيسية
        $('#mainResults').html(formatMainRecords(data.main_records || []));

        // عرض أفراد الأسرة
        $('#familyResults').html(formatFamilyMembers(data.family_members || []));

        // عرض المتوفين
        $('#deceasedResults').html(formatDeceasedRecords(data.deceased || []));

        // عرض جميع النتائج مجمعة حسب الشخص
        const groupedData = groupDataByPerson({
            main_records: data.main_records || [],
            deceased_records: data.deceased || [],
            family_members: data.family_members || []
        });

        let allResultsHtml = '';

        if (Object.keys(groupedData).length > 0) {
            // إحصائيات شاملة
            const totalResults = (data.main_records?.length || 0) + (data.deceased?.length || 0) + (data.family_members?.length || 0);
            allResultsHtml += `<div class="alert alert-info mb-4">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h5 class="mb-1">${totalResults}</h5>
                        <small>إجمالي النتائج</small>
                    </div>
                    <div class="col-md-3">
                        <h5 class="mb-1">${data.main_records?.length || 0}</h5>
                        <small>السجلات الرئيسية</small>
                    </div>
                    <div class="col-md-3">
                        <h5 class="mb-1">${data.deceased?.length || 0}</h5>
                        <small>سجلات المتوفين</small>
                    </div>
                    <div class="col-md-3">
                        <h5 class="mb-1">${data.family_members?.length || 0}</h5>
                        <small>أفراد الأسرة</small>
                    </div>
                </div>
            </div>`;

            // عرض البيانات مجمعة حسب الشخص
            allResultsHtml += '<div class="row">';
            Object.keys(groupedData).forEach(personKey => {
                const personData = groupedData[personKey];
                allResultsHtml += formatPersonCard(personData);
            });
            allResultsHtml += '</div>';
        } else {
            allResultsHtml = '<div class="alert alert-warning"><i class="fas fa-search"></i> لم يتم العثور على أي نتائج تطابق البحث</div>';
        }

        $('#allResults').html(allResultsHtml);

        // إخفاء التصفح للبحث الشامل
        $('#pagination').html('');
    }

    /*
     * تجميع البيانات حسب الشخص (بناءً على الاسم أو رقم الهوية)
     */
    function groupDataByPerson(data) {
        const grouped = {};

        // تجميع السجلات الرئيسية
        if (data.main_records) {
            data.main_records.forEach(record => {
                const key = getPersonKey(record);
                if (!grouped[key]) {
                    grouped[key] = {
                        mainRecord: record,
                        deceasedRecords: [],
                        familyMembers: [],
                        personName: record.full_name || 'غير محدد',
                        identityNumber: record.identity_number
                    };
                } else if (!grouped[key].mainRecord) {
                    grouped[key].mainRecord = record;
                }
            });
        }

        // تجميع سجلات المتوفين
        if (data.deceased_records) {
            data.deceased_records.forEach(record => {
                const key = getPersonKey(record);
                if (!grouped[key]) {
                    grouped[key] = {
                        mainRecord: null,
                        deceasedRecords: [record],
                        familyMembers: [],
                        personName: record.full_name || 'غير محدد',
                        identityNumber: record.identity_number
                    };
                } else {
                    grouped[key].deceasedRecords.push(record);
                }
            });
        }

        // تجميع أفراد الأسرة
        if (data.family_members) {
            data.family_members.forEach(record => {
                const key = getPersonKey(record);
                if (!grouped[key]) {
                    grouped[key] = {
                        mainRecord: null,
                        deceasedRecords: [],
                        familyMembers: [record],
                        personName: record.full_name || 'غير محدد',
                        identityNumber: record.identity_number
                    };
                } else {
                    grouped[key].familyMembers.push(record);
                }
            });
        }

        return grouped;
    }

    /*
     * إنشاء مفتاح فريد للشخص
     */
    function getPersonKey(record) {
        // استخدام رقم الهوية كمفتاح أساسي
        if (record.identity_number) {
            return `id_${record.identity_number}`;
        }

        // استخدام الاسم كمفتاح بديل
        if (record.full_name) {
            return `name_${record.full_name.replace(/\s+/g, '_')}`;
        }

        // مفتاح عشوائي كحل أخير
        return `record_${Math.random().toString(36).substr(2, 9)}`;
    }

    /*
     * تنسيق بطاقة شخص واحد مع جميع بياناته
     */
    function formatPersonCard(personData) {
        const hasMainRecord = personData.mainRecord !== null;
        const hasDeceasedRecords = personData.deceasedRecords.length > 0;
        const hasFamilyMembers = personData.familyMembers.length > 0;

        let cardClass = 'main-record-card';
        let headerColor = 'bg-primary';
        let statusIcon = 'fas fa-user';

        if (hasDeceasedRecords && !hasMainRecord) {
            cardClass = 'deceased-card';
            headerColor = 'bg-danger';
            statusIcon = 'fas fa-cross';
        } else if (hasFamilyMembers && !hasMainRecord && !hasDeceasedRecords) {
            cardClass = 'family-card';
            headerColor = 'bg-success';
            statusIcon = 'fas fa-users';
        }

        let html = `<div class="col-12 mb-4">
            <div class="card ${cardClass} shadow-custom border-custom">
                <div class="card-header ${headerColor} text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">
                                <i class="${statusIcon}"></i> ${personData.personName}
                            </h5>
                            ${personData.identityNumber ? `<small class="opacity-75">رقم الهوية: ${personData.identityNumber}</small>` : ''}
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex gap-2 justify-content-end flex-wrap">`;

        if (hasMainRecord) {
            html += `<span class="badge bg-light text-dark"><i class="fas fa-user"></i> سجل رئيسي</span>`;
        }
        if (hasDeceasedRecords) {
            html += `<span class="badge bg-light text-dark"><i class="fas fa-cross"></i> ${hasDeceasedRecords} متوفى</span>`;
        }
        if (hasFamilyMembers) {
            html += `<span class="badge bg-light text-dark"><i class="fas fa-users"></i> ${hasFamilyMembers} فرد أسرة</span>`;
        }

        html += `       </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">`;

        // السجل الرئيسي
        if (hasMainRecord) {
            html += `<div class="border-bottom">
                <div class="bg-light px-3 py-2">
                    <h6 class="mb-0 text-primary"><i class="fas fa-user-circle"></i> البيانات الرئيسية</h6>
                </div>
                <div class="p-3">
                    ${formatMainRecordDetails(personData.mainRecord)}
                </div>
            </div>`;
        }

        // سجلات المتوفين
        if (hasDeceasedRecords) {
            html += `<div class="border-bottom">
                <div class="bg-light px-3 py-2">
                    <h6 class="mb-0 text-danger"><i class="fas fa-cross"></i> سجلات الوفاة (${personData.deceasedRecords.length})</h6>
                </div>
                <div class="p-3">
                    ${formatDeceasedRecordsDetails(personData.deceasedRecords)}
                </div>
            </div>`;
        }

        // أفراد الأسرة
        if (hasFamilyMembers) {
            html += `<div>
                <div class="bg-light px-3 py-2">
                    <h6 class="mb-0 text-success"><i class="fas fa-users"></i> أفراد الأسرة (${personData.familyMembers.length})</h6>
                </div>
                <div class="p-3">
                    ${formatFamilyMembersDetails(personData.familyMembers)}
                </div>
            </div>`;
        }

        html += `   </div>
                <div class="card-footer bg-light">
                    <div class="d-flex gap-2 justify-content-end">`;

        if (hasMainRecord && personData.mainRecord.id) {
            html += `<a href="admin/records-management/${personData.mainRecord.id}" class="btn btn-primary btn-sm">
                <i class="fas fa-eye"></i> عرض الملف الكامل
            </a>
            <a href="admin/records-management/${personData.mainRecord.id}/edit" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> تعديل البيانات
            </a>`;
        }

        html += `<button type="button" class="btn btn-info btn-sm" onclick="showPersonAllDetails('${encodeURIComponent(JSON.stringify(personData))}')">
            <i class="fas fa-info-circle"></i> جميع التفاصيل
        </button>
                    </div>
                </div>
            </div>
        </div>`;

        return html;
    }

    /*
     * تنسيق تفاصيل السجل الرئيسي للمودال
     */
    function formatMainRecordDetailsForModal(record) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-user"></i> البيانات الشخصية</h6>
                        </div>
                        <div class="card-body">
                            ${record.identity_number || record.data_id_number ? `<p><strong>رقم الهوية:</strong> <code>${record.identity_number || record.data_id_number}</code></p>` : ''}
                            ${record.birth_date || record.data_birth_date ? `<p><strong>تاريخ الميلاد:</strong> ${record.birth_date || record.data_birth_date}</p>` : ''}
                            ${record.age ? `<p><strong>العمر:</strong> <span class="badge bg-info">${record.age} سنة</span></p>` : ''}
                            ${record.gender || record.data_gender ? `<p><strong>الجنس:</strong> <span class="badge ${(record.gender || record.data_gender) === 'ذكر' ? 'bg-primary' : 'bg-pink'}">${record.gender || record.data_gender}</span></p>` : ''}
                            ${record.marital_status || record.marital_status_name ? `<p><strong>الحالة الاجتماعية:</strong> ${record.marital_status || record.marital_status_name}</p>` : ''}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-success h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-phone"></i> الاتصال والموقع</h6>
                        </div>
                        <div class="card-body">
                            ${record.phone || record.data_phone_number ? `<p><strong>الجوال:</strong> <a href="tel:${record.phone || record.data_phone_number}">${record.phone || record.data_phone_number}</a></p>` : ''}
                            ${record.alt_phone || record.data_alt_phone_number ? `<p><strong>الجوال البديل:</strong> <a href="tel:${record.alt_phone || record.data_alt_phone_number}">${record.alt_phone || record.data_alt_phone_number}</a></p>` : ''}
                            ${record.city_name || record.city || record.data_city ? `<p><strong>المدينة:</strong> <span class="badge bg-primary">${record.city_name || record.city || record.data_city}</span></p>` : ''}
                            ${record.province_name || record.province || record.data_province ? `<p><strong>المحافظة:</strong> <span class="badge bg-info">${record.province_name || record.province || record.data_province}</span></p>` : ''}
                            ${record.address || record.data_current_address ? `<p><strong>العنوان:</strong><br><small>${record.address || record.data_current_address}</small></p>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /*
     * تنسيق تفاصيل فرد الأسرة للمودال
     */
    function formatFamilyMemberDetailsForModal(record) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-secondary h-100">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-user-friends"></i> البيانات الشخصية</h6>
                        </div>
                        <div class="card-body">
                            ${record.identity_number || record.person_id ? `<p><strong>رقم الهوية:</strong> <code>${record.identity_number || record.person_id}</code></p>` : ''}
                            ${record.birth_date ? `<p><strong>تاريخ الميلاد:</strong> ${record.birth_date}</p>` : ''}
                            ${record.age ? `<p><strong>العمر:</strong> <span class="badge bg-info">${record.age} سنة</span></p>` : ''}
                            ${record.gender ? `<p><strong>الجنس:</strong> <span class="badge ${record.gender === 'ذكر' ? 'bg-primary' : 'bg-pink'}">${record.gender}</span></p>` : ''}
                            ${record.relationship || record.relationship_name ? `<p><strong>صلة القرابة:</strong> <span class="badge bg-info">${record.relationship || record.relationship_name}</span></p>` : ''}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-info h-100">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-info-circle"></i> معلومات إضافية</h6>
                        </div>
                        <div class="card-body">
                            ${record.marital_status || record.marital_status_name ? `<p><strong>الحالة الاجتماعية:</strong> ${record.marital_status || record.marital_status_name}</p>` : ''}
                            ${record.education_level || record.academic_qualification_name ? `<p><strong>المستوى التعليمي:</strong> ${record.education_level || record.academic_qualification_name}</p>` : ''}
                            ${record.employment_status || record.employment_status_name ? `<p><strong>حالة التوظيف:</strong> ${record.employment_status || record.employment_status_name}</p>` : ''}
                            ${record.health_status || record.health_status_name ? `<p><strong>الحالة الصحية:</strong> ${record.health_status || record.health_status_name}</p>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /*
     * تنسيق تفاصيل المتوفي للمودال
     */
    function formatDeceasedRecordDetailsForModal(record) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-dark h-100">
                        <div class="card-header bg-dark text-white">
                            <h6 class="mb-0"><i class="fas fa-cross"></i> بيانات المتوفي</h6>
                        </div>
                        <div class="card-body">
                            ${record.identity_number || record.dead_people_id_number ? `<p><strong>رقم الهوية:</strong> <code>${record.identity_number || record.dead_people_id_number}</code></p>` : ''}
                            ${record.birth_date || record.dead_people_birth_date ? `<p><strong>تاريخ الميلاد:</strong> ${record.birth_date || record.dead_people_birth_date}</p>` : ''}
                            ${record.death_date || record.dead_people_death_date ? `<p><strong>تاريخ الوفاة:</strong> <span class="text-danger">${record.death_date || record.dead_people_death_date}</span></p>` : ''}
                            ${record.age_at_death ? `<p><strong>العمر عند الوفاة:</strong> <span class="badge bg-secondary">${record.age_at_death} سنة</span></p>` : ''}
                            ${record.gender || record.dead_people_gender ? `<p><strong>الجنس:</strong> <span class="badge ${(record.gender || record.dead_people_gender) === 'ذكر' ? 'bg-primary' : 'bg-pink'}">${record.gender || record.dead_people_gender}</span></p>` : ''}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-info h-100">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-users"></i> معلومات العائلة</h6>
                        </div>
                        <div class="card-body">
                            ${record.father_full_name ? `<p><strong>اسم الأب:</strong> <span class="text-primary">${record.father_full_name}</span></p>` : ''}
                            ${record.father_id_number || record.father_identity_number ? `<p><strong>رقم هوية الأب:</strong> <code>${record.father_id_number || record.father_identity_number}</code></p>` : ''}
                            ${record.mother_full_name ? `<p><strong>اسم الأم:</strong> <span class="text-pink">${record.mother_full_name}</span></p>` : ''}
                            ${record.mother_id_number || record.mother_identity_number ? `<p><strong>رقم هوية الأم:</strong> <code>${record.mother_id_number || record.mother_identity_number}</code></p>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function formatMainRecordDetails(record) {
        let html = '<div class="row">';

        // العمود الأول - البيانات الشخصية الأساسية
        html += '<div class="col-md-6">';
        html += '<div class="card border-primary h-100">';
        html += '<div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-user"></i> البيانات الشخصية</h6></div>';
        html += '<div class="card-body">';

        if (record.identity_number || record.data_id_number) {
            html += `<p class="mb-2"><i class="fas fa-id-card text-success"></i> <strong>رقم الهوية:</strong> <code class="bg-light p-1 rounded">${record.identity_number || record.data_id_number}</code></p>`;
        }
        if (record.birth_date || record.data_birth_date) {
            html += `<p class="mb-2"><i class="fas fa-birthday-cake text-warning"></i> <strong>تاريخ الميلاد:</strong> ${record.birth_date || record.data_birth_date}</p>`;
        }
        if (record.age) {
            html += `<p class="mb-2"><i class="fas fa-hourglass-half text-info"></i> <strong>العمر:</strong> <span class="badge bg-info">${record.age} سنة</span></p>`;
        }
        if (record.gender || record.data_gender) {
            const gender = record.gender || record.data_gender;
            const genderIcon = gender === 'ذكر' ? 'fas fa-mars text-primary' : 'fas fa-venus text-pink';
            const genderBadge = gender === 'ذكر' ? 'bg-primary' : 'bg-pink';
            html += `<p class="mb-2"><i class="${genderIcon}"></i> <strong>الجنس:</strong> <span class="badge ${genderBadge}">${gender}</span></p>`;
        }
        if (record.marital_status || record.marital_status_name) {
            html += `<p class="mb-2"><i class="fas fa-heart text-danger"></i> <strong>الحالة الاجتماعية:</strong> <span class="badge bg-secondary">${record.marital_status || record.marital_status_name}</span></p>`;
        }
        if (record.family_size || record.data_number_of_individuals) {
            html += `<p class="mb-0"><i class="fas fa-users text-info"></i> <strong>حجم الأسرة:</strong> <span class="badge bg-info">${record.family_size || record.data_number_of_individuals} فرد</span></p>`;
        }

        html += '</div></div></div>';

        // العمود الثاني - معلومات الاتصال والموقع
        html += '<div class="col-md-6">';
        html += '<div class="card border-success h-100">';
        html += '<div class="card-header bg-success text-white"><h6 class="mb-0"><i class="fas fa-phone"></i> الاتصال والموقع</h6></div>';
        html += '<div class="card-body">';

        if (record.phone || record.data_phone_number) {
            const phone = record.phone || record.data_phone_number;
            html += `<p class="mb-2"><i class="fas fa-phone text-success"></i> <strong>الجوال:</strong> <a href="tel:${phone}" class="btn btn-outline-success btn-sm">${phone}</a></p>`;
        }
        if (record.alt_phone || record.data_alt_phone_number) {
            const altPhone = record.alt_phone || record.data_alt_phone_number;
            html += `<p class="mb-2"><i class="fas fa-mobile-alt text-info"></i> <strong>الجوال البديل:</strong> <a href="tel:${altPhone}" class="btn btn-outline-info btn-sm">${altPhone}</a></p>`;
        }
        if (record.city_name || record.city) {
            html += `<p class="mb-2"><i class="fas fa-city text-primary"></i> <strong>المدينة:</strong> <span class="badge bg-primary">${record.city_name || record.city}</span></p>`;
        }
        if (record.province_name || record.province) {
            html += `<p class="mb-2"><i class="fas fa-map text-info"></i> <strong>المحافظة:</strong> <span class="badge bg-info">${record.province_name || record.province}</span></p>`;
        }
        if (record.section_name || record.section) {
            html += `<p class="mb-2"><i class="fas fa-building text-secondary"></i> <strong>القسم:</strong> <span class="badge bg-secondary">${record.section_name || record.section}</span></p>`;
        }
        if (record.address || record.data_current_address) {
            html += `<p class="mb-0"><i class="fas fa-map-marker-alt text-warning"></i> <strong>العنوان:</strong><br><small class="text-muted">${record.address || record.data_current_address}</small></p>`;
        }

        html += '</div></div></div>';
        html += '</div>';

        // الصف الثاني - التعليم والعمل والحالة الصحية
        html += '<div class="row mt-3">';

        // العمود الأول - التعليم والعمل
        html += '<div class="col-md-6">';
        html += '<div class="card border-warning h-100">';
        html += '<div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="fas fa-graduation-cap"></i> التعليم والعمل</h6></div>';
        html += '<div class="card-body">';

        if (record.education_level || record.academic_qualification_name) {
            html += `<p class="mb-2"><i class="fas fa-graduation-cap text-info"></i> <strong>المستوى التعليمي:</strong> <span class="badge bg-info">${record.education_level || record.academic_qualification_name}</span></p>`;
        }
        if (record.employment_status || record.employment_status_name) {
            const empStatus = record.employment_status || record.employment_status_name;
            const empColor = empStatus && empStatus.includes('يعمل') ? 'bg-success' :
                           empStatus && empStatus.includes('عاطل') ? 'bg-danger' : 'bg-secondary';
            html += `<p class="mb-2"><i class="fas fa-briefcase text-success"></i> <strong>حالة التوظيف:</strong> <span class="badge ${empColor}">${empStatus}</span></p>`;
        }
        if (record.housing_status || record.housing_status_name) {
            html += `<p class="mb-2"><i class="fas fa-home text-primary"></i> <strong>حالة السكن:</strong> <span class="badge bg-primary">${record.housing_status || record.housing_status_name}</span></p>`;
        }
        if (record.housing_type || record.housing_type_name) {
            html += `<p class="mb-0"><i class="fas fa-building text-secondary"></i> <strong>نوع السكن:</strong> <span class="badge bg-secondary">${record.housing_type || record.housing_type_name}</span></p>`;
        }

        html += '</div></div></div>';

        // العمود الثاني - الحالة الصحية والطلب
        html += '<div class="col-md-6">';
        html += '<div class="card border-danger h-100">';
        html += '<div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="fas fa-heartbeat"></i> الحالة الصحية والطلب</h6></div>';
        html += '<div class="card-body">';

        if (record.health_status || record.health_status_name) {
            const healthStatus = record.health_status || record.health_status_name;
            const healthColor = healthStatus && (healthStatus.includes('سليم') || healthStatus.includes('جيد')) ? 'bg-success' : 'bg-danger';
            html += `<p class="mb-2"><i class="fas fa-heartbeat text-danger"></i> <strong>الحالة الصحية:</strong> <span class="badge ${healthColor}">${healthStatus}</span></p>`;
        }
        if (record.request_status || record.request_status_name) {
            const reqStatus = record.request_status || record.request_status_name;
            const statusColor = reqStatus === 'مقبول' ? 'bg-success' :
                               reqStatus === 'قيد المراجعة' ? 'bg-warning' : 'bg-info';
            html += `<p class="mb-2"><i class="fas fa-check-circle"></i> <strong>حالة الطلب:</strong> <span class="badge ${statusColor}">${reqStatus}</span></p>`;
        }
        if (record.displacement_status || record.displacement_status_name) {
            html += `<p class="mb-2"><i class="fas fa-exchange-alt text-warning"></i> <strong>حالة النزوح:</strong> <span class="badge bg-warning text-dark">${record.displacement_status || record.displacement_status_name}</span></p>`;
        }
        if (record.relationship || record.relationship_name) {
            html += `<p class="mb-0"><i class="fas fa-users text-info"></i> <strong>صلة القرابة:</strong> <span class="badge bg-info">${record.relationship || record.relationship_name}</span></p>`;
        }

        html += '</div></div></div>';
        html += '</div>';

        // الصف الثالث - الإحصائيات الإضافية (إذا كانت متوفرة)
        if (record.data_number_mail || record.data_number_female || record.data_number_of_individuals_with_chronic_diseases) {
            html += '<div class="row mt-3">';
            html += '<div class="col-12">';
            html += '<div class="card border-info">';
            html += '<div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-chart-bar"></i> إحصائيات الأسرة</h6></div>';
            html += '<div class="card-body">';
            html += '<div class="row text-center">';

            if (record.data_number_mail) {
                html += `<div class="col-md-3"><h6 class="text-primary">${record.data_number_mail}</h6><small>ذكور</small></div>`;
            }
            if (record.data_number_female) {
                html += `<div class="col-md-3"><h6 class="text-pink">${record.data_number_female}</h6><small>إناث</small></div>`;
            }
            if (record.data_number_of_individuals_with_chronic_diseases) {
                html += `<div class="col-md-3"><h6 class="text-danger">${record.data_number_of_individuals_with_chronic_diseases}</h6><small>أمراض مزمنة</small></div>`;
            }
            if (record.data_number_of_people_with_special_needs) {
                html += `<div class="col-md-3"><h6 class="text-warning">${record.data_number_of_people_with_special_needs}</h6><small>احتياجات خاصة</small></div>`;
            }

            html += '</div></div></div></div></div>';
        }

        return html;
    }

    /*
     * تنسيق تفاصيل سجلات المتوفين
     */
    function formatDeceasedRecordsDetails(records) {
        if (!records || records.length === 0) {
            return '<div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد سجلات وفاة</div>';
        }

        let html = '<div class="row">';

        records.forEach((record, index) => {
            html += '<div class="col-md-6 mb-3">';
            html += '<div class="card border-danger">';
            html += `<div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="fas fa-cross"></i> سجل وفاة ${index + 1}</h6></div>`;
            html += '<div class="card-body">';

            if (record.death_date) {
                html += `<p class="mb-2"><i class="fas fa-calendar-times text-danger"></i> <strong>تاريخ الوفاة:</strong> <span class="text-danger fw-bold">${record.death_date}</span></p>`;
            }
            if (record.death_reason) {
                html += `<p class="mb-2"><i class="fas fa-skull-crossbones text-dark"></i> <strong>سبب الوفاة:</strong> <span class="badge bg-danger">${record.death_reason}</span></p>`;
            }
            if (record.age_at_death) {
                html += `<p class="mb-0"><i class="fas fa-hourglass-end text-warning"></i> <strong>العمر عند الوفاة:</strong> <span class="badge bg-warning text-dark">${record.age_at_death} سنة</span></p>`;
            }

            html += '</div></div></div>';
        });

        html += '</div>';
        return html;
    }

    /*
     * تنسيق تفاصيل أفراد الأسرة
     */
    function formatFamilyMembersDetails(records) {
        if (!records || records.length === 0) {
            return '<div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد أفراد أسرة</div>';
        }

        let html = '<div class="row">';

        records.forEach((record, index) => {
            const genderIcon = record.gender === 'ذكر' ? 'fas fa-mars text-primary' : 'fas fa-venus text-pink';

            html += '<div class="col-md-6 mb-3">';
            html += '<div class="card border-success">';
            html += `<div class="card-header bg-success text-white"><h6 class="mb-0"><i class="fas fa-user"></i> ${record.full_name || `فرد الأسرة ${index + 1}`}</h6></div>`;
            html += '<div class="card-body">';

            if (record.relationship) {
                html += `<p class="mb-2"><i class="fas fa-users text-success"></i> <strong>صلة القرابة:</strong> <span class="badge bg-success">${record.relationship}</span></p>`;
            }
            if (record.age) {
                html += `<p class="mb-2"><i class="fas fa-hourglass-half text-info"></i> <strong>العمر:</strong> <span class="badge bg-info">${record.age} سنة</span></p>`;
            }
            if (record.gender) {
                const genderBadge = record.gender === 'ذكر' ? 'bg-primary' : 'bg-pink';
                html += `<p class="mb-2"><i class="${genderIcon}"></i> <strong>الجنس:</strong> <span class="badge ${genderBadge}">${record.gender}</span></p>`;
            }
            if (record.health_status) {
                const healthColor = record.health_status.includes('سليم') || record.health_status.includes('جيد') ? 'bg-success' :
                                   record.health_status.includes('مريض') || record.health_status.includes('مزمن') ? 'bg-danger' : 'bg-warning';
                html += `<p class="mb-0"><i class="fas fa-heartbeat"></i> <strong>الحالة الصحية:</strong> <span class="badge ${healthColor}">${record.health_status}</span></p>`;
            }

            html += '</div></div></div>';
        });

        html += '</div>';
        return html;
    }

    /*
     * عرض جميع تفاصيل الشخص في نافذة منبثقة
     */
    function showPersonAllDetails(encodedData) {
        try {
            const personData = JSON.parse(decodeURIComponent(encodedData));
            showPersonDetailsModal(personData);
        } catch (error) {
            console.error('خطأ في فك تشفير بيانات الشخص:', error);
            showErrorModal('حدث خطأ في عرض التفاصيل');
        }
    }

    /*
     * تشفير آمن للبيانات مع دعم النصوص العربية
     */
    function safeEncode(data) {
        try {
            return encodeURIComponent(JSON.stringify(data));
        } catch (error) {
            console.error('خطأ في تشفير البيانات:', error);
            return '';
        }
    }

    /*
     * فك تشفير آمن للبيانات مع دعم النصوص العربية
     */
    function safeDecode(encodedData) {
        try {
            return JSON.parse(decodeURIComponent(encodedData));
        } catch (error) {
            console.error('خطأ في فك تشفير البيانات:', error);
            return null;
        }
    }

    /*
     * عرض النافذة المنبثقة لجميع تفاصيل الشخص
     */
    function showPersonDetailsModal(personData) {
        const hasMainRecord = personData.mainRecord !== null;
        const hasDeceasedRecords = personData.deceasedRecords.length > 0;
        const hasFamilyMembers = personData.familyMembers.length > 0;

        let modalHtml = `
        <div class="modal fade" id="personDetailsModal" tabindex="-1" aria-labelledby="personDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-primary text-white">
                        <h5 class="modal-title" id="personDetailsModalLabel">
                            <i class="fas fa-user-circle"></i> جميع بيانات: ${personData.personName}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- ملخص البيانات -->
                        <div class="alert alert-info mb-4">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <h6 class="mb-1">${hasMainRecord ? '✅' : '❌'}</h6>
                                    <small>سجل رئيسي</small>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1">${hasDeceasedRecords ? personData.deceasedRecords.length : '0'}</h6>
                                    <small>سجلات وفاة</small>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-1">${hasFamilyMembers ? personData.familyMembers.length : '0'}</h6>
                                    <small>أفراد أسرة</small>
                                </div>
                            </div>
                        </div>`;

        // البيانات الرئيسية
        if (hasMainRecord) {
            modalHtml += `
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-user-circle"></i> البيانات الرئيسية</h6>
                            </div>
                            <div class="card-body">
                                ${formatMainRecordDetails(personData.mainRecord)}
                            </div>
                        </div>`;
        }

        // سجلات الوفاة
        if (hasDeceasedRecords) {
            modalHtml += `
                        <div class="card mb-3">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0"><i class="fas fa-cross"></i> سجلات الوفاة (${personData.deceasedRecords.length})</h6>
                            </div>
                            <div class="card-body">
                                ${formatDeceasedRecordsDetails(personData.deceasedRecords)}
                            </div>
                        </div>`;
        }

        // أفراد الأسرة
        if (hasFamilyMembers) {
            modalHtml += `
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-users"></i> أفراد الأسرة (${personData.familyMembers.length})</h6>
                            </div>
                            <div class="card-body">
                                ${formatFamilyMembersDetails(personData.familyMembers)}
                            </div>
                        </div>`;
        }

        modalHtml += `
                    </div>
                    <div class="modal-footer">`;

        if (hasMainRecord && personData.mainRecord.id) {
            modalHtml += `
                        <a href="admin/records-management/${personData.mainRecord.id}" class="btn btn-primary">
                            <i class="fas fa-eye"></i> عرض الملف الكامل
                        </a>
                        <a href="admin/records-management/${personData.mainRecord.id}/edit" class="btn btn-warning">
                            <i class="fas fa-edit"></i> تعديل البيانات
                        </a>`;
        }

        modalHtml += `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        // إزالة أي نافذة سابقة وإضافة الجديدة
        const existingModal = document.getElementById('personDetailsModal');
        if (existingModal) {
            existingModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // إظهار النافذة المنبثقة
        const modal = new bootstrap.Modal(document.getElementById('personDetailsModal'));
        modal.show();
    }

    /*
     * عرض نتائج نوع واحد
     */
    function displaySingleTypeResults(data, type) {
        // تحديث العدادات
        const totalRecords = data.pagination ? data.pagination.total_records : data.data.length;
        $('#resultsCount').text(`إجمالي النتائج: ${totalRecords}`);

        // إخفاء التبويبات غير المناسبة
        $('#resultsTab .nav-link').removeClass('active');
        $('.tab-pane').removeClass('show active');

        let targetTab, targetPane, resultsHtml;

        switch(type) {
            case 'main_records':
                targetTab = '#main-tab';
                targetPane = '#main';
                $('#mainCount').text(totalRecords);
                resultsHtml = formatMainRecords(data.data);
                $('#mainResults').html(resultsHtml);
                break;
            case 'family_members':
                targetTab = '#family-tab';
                targetPane = '#family';
                $('#familyCount').text(totalRecords);
                resultsHtml = formatFamilyMembers(data.data);
                $('#familyResults').html(resultsHtml);
                break;
            case 'deceased':
                targetTab = '#deceased-tab';
                targetPane = '#deceased';
                $('#deceasedCount').text(totalRecords);
                resultsHtml = formatDeceasedRecords(data.data);
                $('#deceasedResults').html(resultsHtml);
                break;
        }

        $(targetTab).addClass('active');
        $(targetPane).addClass('show active');

        // عرض التصفح
        if (data.pagination) {
            displayPagination(data.pagination);
        }
    }

    /*
     * تنسيق السجلات الرئيسية مع التفاصيل المحسنة
     */
    function formatMainRecords(records) {
        if (!records || records.length === 0) {
            return '<div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد سجلات رئيسية</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
        html += '<thead class="table-dark"><tr>';
        html += '<th><i class="fas fa-folder-open"></i> معلومات الملف</th>';
        html += '<th><i class="fas fa-user"></i> البيانات الشخصية</th>';
        html += '<th><i class="fas fa-phone"></i> معلومات الاتصال</th>';
        html += '<th><i class="fas fa-map-marker-alt"></i> العنوان والموقع</th>';
        html += '<th><i class="fas fa-briefcase"></i> التعليم والعمل</th>';
        html += '<th><i class="fas fa-heart"></i> الحالة الاجتماعية والصحية</th>';
        html += '<th><i class="fas fa-cogs"></i> الإجراءات</th>';
        html += '</tr></thead><tbody>';

        records.forEach(record => {
            html += '<tr class="search-result-row">';

            // معلومات الملف
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;
            if (record.file_id) {
                html += `<span class="badge bg-primary fs-6"><i class="fas fa-folder-open"></i> ${record.file_id}</span>`;
            }
            if (record.identity_number) {
                html += `<code class="bg-light p-1 rounded d-block"><i class="fas fa-id-card"></i> ${record.identity_number}</code>`;
            }
            if (record.request_status) {
                const statusColor = record.request_status === 'مقبول' ? 'bg-success' :
                                   record.request_status === 'قيد المراجعة' ? 'bg-warning' : 'bg-info';
                html += `<span class="badge ${statusColor}"><i class="fas fa-check-circle"></i> ${record.request_status}</span>`;
            }
            html += `</div>`;
            html += '</td>';

            // البيانات الشخصية
            html += '<td class="text-start">';
            html += `<div class="card border-info mb-1">`;
            html += `<div class="card-body p-2">`;
            if (record.full_name) {
                html += `<h6 class="card-title text-primary mb-2"><i class="fas fa-user"></i> ${record.full_name}</h6>`;
            }
            if (record.birth_date) {
                html += `<p class="mb-1"><i class="fas fa-birthday-cake text-warning"></i> <strong>تاريخ الميلاد:</strong> ${record.birth_date}</p>`;
            }
            if (record.age) {
                html += `<p class="mb-1"><i class="fas fa-hourglass-half text-info"></i> <strong>العمر:</strong> <span class="badge bg-info">${record.age} سنة</span></p>`;
            }
            if (record.gender) {
                const genderIcon = record.gender === 'ذكر' ? 'fas fa-mars text-primary' : 'fas fa-venus text-pink';
                const genderBadge = record.gender === 'ذكر' ? 'bg-primary' : 'bg-pink';
                html += `<p class="mb-0"><i class="${genderIcon}"></i> <span class="badge ${genderBadge}">${record.gender}</span></p>`;
            }
            html += `</div></div>`;
            html += '</td>';

            // معلومات الاتصال
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;
            if (record.phone) {
                html += `<a href="tel:${record.phone}" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-phone"></i> ${record.phone}
                </a>`;
            }
            if (record.alt_phone) {
                html += `<a href="tel:${record.alt_phone}" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-mobile-alt"></i> ${record.alt_phone}
                </a>`;
            }
            if (record.email) {
                html += `<a href="mailto:${record.email}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-envelope"></i> إرسال إيميل
                </a>`;
            }
            html += `</div>`;
            html += '</td>';

            // العنوان والموقع
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;
            if (record.city) {
                html += `<span class="badge bg-secondary"><i class="fas fa-city"></i> ${record.city}</span>`;
            }
            if (record.province) {
                html += `<span class="badge bg-secondary"><i class="fas fa-map"></i> ${record.province}</span>`;
            }
            if (record.section) {
                html += `<span class="badge bg-info"><i class="fas fa-building"></i> ${record.section}</span>`;
            }
            if (record.address) {
                html += `<small class="text-muted"><i class="fas fa-map-marker-alt"></i> ${record.address}</small>`;
            }
            html += `</div>`;
            html += '</td>';

            // التعليم والعمل
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;
            if (record.education_level) {
                html += `<span class="badge bg-info"><i class="fas fa-graduation-cap"></i> ${record.education_level}</span>`;
            }
            if (record.employment_status) {
                const empColor = record.employment_status.includes('يعمل') ? 'bg-success' :
                               record.employment_status.includes('عاطل') ? 'bg-danger' : 'bg-secondary';
                html += `<span class="badge ${empColor}"><i class="fas fa-briefcase"></i> ${record.employment_status}</span>`;
            }
            if (record.monthly_income) {
                html += `<small class="text-success"><i class="fas fa-money-bill-wave"></i> ${record.monthly_income} ريال/شهر</small>`;
            }
            html += `</div>`;
            html += '</td>';

            // الحالة الاجتماعية والصحية
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;
            if (record.marital_status) {
                html += `<span class="badge bg-secondary"><i class="fas fa-heart"></i> ${record.marital_status}</span>`;
            }
            if (record.health_status) {
                const healthColor = record.health_status.includes('سليم') || record.health_status.includes('جيد') ? 'bg-success' :
                                   record.health_status.includes('مريض') || record.health_status.includes('مزمن') ? 'bg-danger' : 'bg-warning';
                html += `<span class="badge ${healthColor}"><i class="fas fa-heartbeat"></i> ${record.health_status}</span>`;
            }
            if (record.family_size) {
                html += `<small class="text-muted"><i class="fas fa-users"></i> أفراد الأسرة: ${record.family_size}</small>`;
            }
            html += `</div>`;
            html += '</td>';

            // الإجراءات
            html += '<td>';
            html += `<div class="btn-group-vertical btn-group-sm">`;
            if (record.id) {
                html += '<a href="admin/records-management/' + record.id + '" class="btn btn-primary btn-sm" title="عرض التفاصيل الكاملة">' +
                    '<i class="fas fa-eye"></i> عرض' +
                '</a>';
                html += '<a href="admin/records-management/' + record.id + '/edit" class="btn btn-warning btn-sm" title="تعديل البيانات">' +
                    '<i class="fas fa-edit"></i> تعديل' +
                '</a>';
                html += '<button type="button" class="btn btn-info btn-sm" onclick="showRecordDetails(' + record.id + ')" title="تفاصيل سريعة">' +
                    '<i class="fas fa-info-circle"></i> تفاصيل' +
                '</button>';
            }
            html += '</div>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    /*
     * تنسيق أفراد الأسرة مع التفاصيل المحسنة
     */
    function formatFamilyMembers(records) {
        if (!records || records.length === 0) {
            return '<div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد أفراد أسرة</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
        html += '<thead class="table-success"><tr>';
        html += '<th><i class="fas fa-folder"></i> رقم الملف</th>';
        html += '<th><i class="fas fa-users"></i> بيانات العضو</th>';
        html += '<th><i class="fas fa-heartbeat"></i> الحالة الصحية</th>';
        html += '<th><i class="fas fa-graduation-cap"></i> التعليم والعمل</th>';
        html += '<th><i class="fas fa-home"></i> معلومات السكن</th>';
        html += '<th><i class="fas fa-cogs"></i> الإجراءات</th>';
        html += '</tr></thead><tbody>';

        records.forEach(record => {
            const genderIcon = record.gender === 'ذكر' ? 'fas fa-mars text-primary' : 'fas fa-venus text-pink';
            const genderBadge = record.gender === 'ذكر' ? 'bg-primary' : 'bg-pink';

            html += '<tr class="search-result-row">';

            // رقم الملف
            html += `<td><span class="badge bg-success fs-6">${record.file_id || '-'}</span></td>`;

            // بيانات العضو
            html += '<td class="text-start">';
            html += `<div class="card border-primary mb-2">`;
            html += `<div class="card-body p-2">`;
            html += `<h6 class="card-title text-primary mb-2"><i class="fas fa-user"></i> ${record.full_name || '-'}</h6>`;

            if (record.identity_number) {
                html += `<p class="mb-1"><i class="fas fa-id-card text-muted"></i> <strong>رقم الهوية:</strong> <code class="bg-light p-1 rounded">${record.identity_number}</code></p>`;
            }

            if (record.birth_date) {
                html += `<p class="mb-1"><i class="fas fa-birthday-cake text-warning"></i> <strong>تاريخ الميلاد:</strong> ${record.birth_date}</p>`;
            }

            if (record.age) {
                html += `<p class="mb-1"><i class="fas fa-hourglass-half text-info"></i> <strong>العمر:</strong> <span class="badge bg-info">${record.age} سنة</span></p>`;
            }

            if (record.gender) {
                html += `<p class="mb-1"><i class="${genderIcon}"></i> <strong>الجنس:</strong> <span class="badge ${genderBadge}">${record.gender}</span></p>`;
            }

            if (record.relationship) {
                html += `<p class="mb-1"><i class="fas fa-users text-success"></i> <strong>صلة القرابة:</strong> <span class="badge bg-success">${record.relationship}</span></p>`;
            }

            if (record.marital_status) {
                html += `<p class="mb-0"><i class="fas fa-heart text-danger"></i> <strong>الحالة الاجتماعية:</strong> <span class="badge bg-secondary">${record.marital_status}</span></p>`;
            }

            html += `</div></div>`;
            html += '</td>';

            // الحالة الصحية
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;

            if (record.health_status) {
                const healthColor = record.health_status.includes('سليم') || record.health_status.includes('جيد') ? 'bg-success' :
                                   record.health_status.includes('مريض') || record.health_status.includes('مزمن') ? 'bg-danger' : 'bg-warning';
                html += `<span class="badge ${healthColor}"><i class="fas fa-heartbeat"></i> ${record.health_status}</span>`;
            }

            if (record.disability_type) {
                html += `<span class="badge bg-warning text-dark"><i class="fas fa-wheelchair"></i> ${record.disability_type}</span>`;
            }

            if (record.chronic_diseases) {
                html += `<small class="text-muted"><i class="fas fa-pills"></i> <strong>أمراض مزمنة:</strong> ${record.chronic_diseases}</small>`;
            }

            html += `</div>`;
            html += '</td>';

            // التعليم والعمل
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;

            if (record.education_level) {
                html += `<span class="badge bg-info"><i class="fas fa-graduation-cap"></i> ${record.education_level}</span>`;
            }

            if (record.employment_status) {
                const empColor = record.employment_status.includes('يعمل') ? 'bg-success' :
                               record.employment_status.includes('عاطل') ? 'bg-danger' : 'bg-secondary';
                html += `<span class="badge ${empColor}"><i class="fas fa-briefcase"></i> ${record.employment_status}</span>`;
            }

            if (record.monthly_income) {
                html += `<small class="text-success"><i class="fas fa-money-bill-wave"></i> <strong>الدخل:</strong> ${record.monthly_income} ريال</small>`;
            }

            html += `</div>`;
            html += '</td>';

            // معلومات السكن
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;

            if (record.housing_status) {
                html += `<span class="badge bg-primary"><i class="fas fa-home"></i> ${record.housing_status}</span>`;
            }

            if (record.accommodation_type) {
                html += `<span class="badge bg-secondary"><i class="fas fa-building"></i> ${record.accommodation_type}</span>`;
            }

            if (record.city) {
                html += `<small class="text-muted"><i class="fas fa-map-marker-alt"></i> <strong>المدينة:</strong> ${record.city}</small>`;
            }

            html += `</div>`;
            html += '</td>';

            // الإجراءات
            html += '<td>';
            html += `<div class="btn-group-vertical btn-group-sm">`;
            if (record.id) {
                html += `<a href="/admin/family-members/${record.id}" class="btn btn-outline-primary btn-sm" title="عرض التفاصيل">
                    <i class="fas fa-eye"></i>
                </a>`;
                html += `<a href="/admin/family-members/${record.id}/edit" class="btn btn-outline-warning btn-sm" title="تعديل">
                    <i class="fas fa-edit"></i>
                </a>`;
            }
            html += `</div>`;
            html += '</td>';

            html += '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    /*
     * تنسيق سجلات المتوفين مع التفاصيل المحسنة
     */
    function formatDeceasedRecords(records) {
        if (!records || records.length === 0) {
            return '<div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد سجلات متوفين</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
        html += '<thead class="table-warning"><tr>';
        html += '<th><i class="fas fa-folder-minus"></i> رقم الملف</th>';
        html += '<th><i class="fas fa-male"></i> بيانات الأب المتوفى</th>';
        html += '<th><i class="fas fa-female"></i> بيانات الأم المتوفاة</th>';
        html += '<th><i class="fas fa-family"></i> معلومات إضافية</th>';
        html += '</tr></thead><tbody>';

        records.forEach(record => {
            html += '<tr class="search-result-row">';
            html += `<td><span class="badge bg-warning text-dark fs-6">${record.file_id || '-'}</span></td>`;

            // بيانات الأب
            html += '<td class="text-start">';
            if (record.father && record.father.full_name) {
                html += `<div class="card border-info mb-2">`;
                html += `<div class="card-body p-2">`;
                html += `<h6 class="card-title text-primary mb-2"><i class="fas fa-user"></i> ${record.father.full_name}</h6>`;

                if (record.father.identity_number) {
                    html += `<p class="mb-1"><i class="fas fa-id-card text-muted"></i> <strong>رقم الهوية:</strong> <code class="bg-light p-1 rounded">${record.father.identity_number}</code></p>`;
                }

                if (record.father.death_date) {
                    html += `<p class="mb-1"><i class="fas fa-calendar-times text-danger"></i> <strong>تاريخ الوفاة:</strong> <span class="text-danger fw-bold">${record.father.death_date}</span></p>`;
                }

                if (record.father.death_reason) {
                    html += `<p class="mb-1"><i class="fas fa-skull-crossbones text-dark"></i> <strong>سبب الوفاة:</strong> <span class="badge bg-danger">${record.father.death_reason}</span></p>`;
                }

                if (record.father.age_at_death) {
                    html += `<p class="mb-1"><i class="fas fa-hourglass-end text-warning"></i> <strong>العمر عند الوفاة:</strong> <span class="badge bg-warning text-dark">${record.father.age_at_death} سنة</span></p>`;
                }

                html += `</div></div>`;
            } else {
                html += '<div class="text-muted fst-italic"><i class="fas fa-minus-circle"></i> لا توجد بيانات</div>';
            }
            html += '</td>';

            // بيانات الأم
            html += '<td class="text-start">';
            if (record.mother && record.mother.full_name) {
                html += `<div class="card border-success mb-2">`;
                html += `<div class="card-body p-2">`;
                html += `<h6 class="card-title text-success mb-2"><i class="fas fa-user"></i> ${record.mother.full_name}</h6>`;

                if (record.mother.identity_number) {
                    html += `<p class="mb-1"><i class="fas fa-id-card text-muted"></i> <strong>رقم الهوية:</strong> <code class="bg-light p-1 rounded">${record.mother.identity_number}</code></p>`;
                }

                if (record.mother.death_date) {
                    html += `<p class="mb-1"><i class="fas fa-calendar-times text-danger"></i> <strong>تاريخ الوفاة:</strong> <span class="text-danger fw-bold">${record.mother.death_date}</span></p>`;
                }

                if (record.mother.death_reason) {
                    html += `<p class="mb-1"><i class="fas fa-skull-crossbones text-dark"></i> <strong>سبب الوفاة:</strong> <span class="badge bg-danger">${record.mother.death_reason}</span></p>`;
                }

                if (record.mother.age_at_death) {
                    html += `<p class="mb-1"><i class="fas fa-hourglass-end text-warning"></i> <strong>العمر عند الوفاة:</strong> <span class="badge bg-warning text-dark">${record.mother.age_at_death} سنة</span></p>`;
                }

                html += `</div></div>`;
            } else {
                html += '<div class="text-muted fst-italic"><i class="fas fa-minus-circle"></i> لا توجد بيانات</div>';
            }
            html += '</td>';

            // معلومات إضافية
            html += '<td class="text-start">';
            html += `<div class="d-flex flex-column gap-1">`;

            if (record.registration_date) {
                html += `<span class="badge bg-info"><i class="fas fa-calendar-plus"></i> تاريخ التسجيل: ${record.registration_date}</span>`;
            }

            if (record.file_status) {
                html += `<span class="badge bg-secondary"><i class="fas fa-flag"></i> حالة الملف: ${record.file_status}</span>`;
            }

            if (record.notes) {
                html += `<small class="text-muted"><i class="fas fa-sticky-note"></i> <strong>ملاحظات:</strong> ${record.notes}</small>`;
            }

            html += `</div>`;
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    /*
     * عرض التصفح
     */
    function displayPagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#pagination').html('');
            return;
        }

        let html = '<nav><ul class="pagination justify-content-center">';

        // السابق
        if (pagination.current_page > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="performSearch(${pagination.current_page - 1})">السابق</a>
            </li>`;
        }

        // أرقام الصفحات
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            const active = i === pagination.current_page ? 'active' : '';
            html += `<li class="page-item ${active}">
                <a class="page-link" href="#" onclick="performSearch(${i})">${i}</a>
            </li>`;
        }

        // التالي
        if (pagination.current_page < pagination.total_pages) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="performSearch(${pagination.current_page + 1})">التالي</a>
            </li>`;
        }

        html += '</ul></nav>';
        html += `<p class="text-center text-muted">
            عرض ${pagination.current_page} من ${pagination.total_pages}
            (إجمالي ${pagination.total_records} سجل)
        </p>`;

        $('#pagination').html(html);
    }

    /*
     * تحميل إحصائيات البحث
     */
    function loadSearchStats() {
        $('#searchStatsModal').modal('show');

        $.ajax({
            url: '{{ route("search.stats") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const stats = response.data;
                    let html = `
                        <div class="row text-center">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h2>${stats.main_records.toLocaleString()}</h2>
                                        <p>السجلات الرئيسية</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h2>${stats.family_members.toLocaleString()}</h2>
                                        <p>أفراد الأسرة</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h2>${stats.deceased_records.toLocaleString()}</h2>
                                        <p>المتوفين</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h2>${stats.total_records.toLocaleString()}</h2>
                                        <p>إجمالي السجلات</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    $('#statsContent').html(html);
                }
            },
            error: function() {
                $('#statsContent').html('<div class="alert alert-danger">فشل في تحميل الإحصائيات</div>');
            }
        });
    }

    /*
     * مسح الذاكرة المؤقتة للبحث
     */
    function clearSearchCache() {
        Swal.fire({
            title: 'تأكيد مسح الذاكرة المؤقتة',
            text: 'هل تريد مسح جميع النتائج المحفوظة مؤقتاً؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، امسح',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#f39c12'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("search.clear.cache") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم المسح بنجاح',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: 'فشل في مسح الذاكرة المؤقتة'
                        });
                    }
                });
            }
        });
    }

    /*
     * عرض شاشة التحميل
     */
    function showLoading(show) {
        if (show) {
            $('#loadingOverlay').show();
        } else {
            $('#loadingOverlay').hide();
        }
    }

    /*
     * عرض رسالة خطأ
     */
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: message,
            confirmButtonText: 'موافق'
        });
    }

    /*
     * عرض رسالة معلومات
     */
    function showInfo(message) {
        Swal.fire({
            icon: 'info',
            title: 'معلومات',
            text: message,
            confirmButtonText: 'موافق'
        });
    }

    // البحث السريع عند الكتابة مع الاقتراحات
    let searchTimeout;
    let suggestionsTimeout;

    $('#search_text').on('input', function() {
        const query = $(this).val().trim();

        // مسح timeout السابق
        clearTimeout(searchTimeout);
        clearTimeout(suggestionsTimeout);

        // إخفاء الاقتراحات إذا كان النص قصير
        if (query.length < 2) {
            $('#searchSuggestions').hide();
            return;
        }

        // عرض الاقتراحات
        suggestionsTimeout = setTimeout(() => {
            loadSearchSuggestions(query);
        }, 300);

        // البحث التلقائي
        if (query.length >= 3) {
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 800);
        }
    });

    // إخفاء الاقتراحات عند النقر خارجها
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#search_text, #searchSuggestions').length) {
            $('#searchSuggestions').hide();
        }
    });

    // التعامل مع مفاتيح لوحة المفاتيح
    $('#search_text').on('keydown', function(e) {
        const suggestions = $('#searchSuggestions .dropdown-item');
        const active = suggestions.filter('.active');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (active.length === 0) {
                suggestions.first().addClass('active');
            } else {
                active.removeClass('active').next('.dropdown-item').addClass('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (active.length === 0) {
                suggestions.last().addClass('active');
            } else {
                active.removeClass('active').prev('.dropdown-item').addClass('active');
            }
        } else if (e.key === 'Enter') {
            if (active.length > 0) {
                e.preventDefault();
                $(this).val(active.text());
                $('#searchSuggestions').hide();
                performSearch();
            }
        } else if (e.key === 'Escape') {
            $('#searchSuggestions').hide();
        }
    });

    /*
     * تحميل اقتراحات البحث
     */
    function loadSearchSuggestions(query) {
        $.ajax({
            url: '{{ route("search.suggestions") }}',
            method: 'GET',
            data: { q: query, limit: 8 },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displaySearchSuggestions(response.data);
                } else {
                    $('#searchSuggestions').hide();
                }
            },
            error: function() {
                $('#searchSuggestions').hide();
            }
        });
    }

    /*
     * عرض اقتراحات البحث
     */
    function displaySearchSuggestions(suggestions) {
        let html = '';
        suggestions.forEach(suggestion => {
            html += `<a class="dropdown-item" href="#" data-suggestion="${suggestion}">
                <i class="fas fa-search text-muted me-2"></i>${suggestion}
            </a>`;
        });

        $('#searchSuggestions').html(html).show();

        // التعامل مع النقر على اقتراح
        $('#searchSuggestions .dropdown-item').on('click', function(e) {
            e.preventDefault();
            const suggestion = $(this).data('suggestion');
            $('#search_text').val(suggestion);
            $('#searchSuggestions').hide();
            performSearch();
        });

        // تأثير hover
        $('#searchSuggestions .dropdown-item').on('mouseenter', function() {
            $(this).siblings().removeClass('active');
            $(this).addClass('active');
        });
    }

    /*
     * عرض معلومات البحث
     */
    function showSearchInfo(message) {
        // إزالة أي رسالة سابقة
        $('.search-info-alert').remove();

        // إضافة رسالة جديدة
        const alertHtml = `
        <div class="alert alert-info alert-dismissible fade show search-info-alert" role="alert">
            <i class="fas fa-info-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;

        $('#searchResults').prepend(alertHtml);

        // إخفاء الرسالة تلقائياً بعد 5 ثوان
        setTimeout(() => {
            $('.search-info-alert').fadeOut();
        }, 5000);
    }

    /*
     * عرض رسالة خطأ
     */
    function showError(message) {
        // إزالة أي رسالة سابقة
        $('.error-alert').remove();

        // إضافة رسالة خطأ جديدة
        const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show error-alert" role="alert">
            <i class="fas fa-exclamation-triangle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;

        $('#searchResults').prepend(alertHtml);

        // إخفاء الرسالة تلقائياً بعد 8 ثوان
        setTimeout(() => {
            $('.error-alert').fadeOut();
        }, 8000);
    }

    /*
     * عرض رسالة نجاح
     */
    function showSuccess(message) {
        // إزالة أي رسالة سابقة
        $('.success-alert').remove();

        // إضافة رسالة نجاح جديدة
        const alertHtml = `
        <div class="alert alert-success alert-dismissible fade show success-alert" role="alert">
            <i class="fas fa-check-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;

        $('#searchResults').prepend(alertHtml);

        // إخفاء الرسالة تلقائياً بعد 4 ثوان
        setTimeout(() => {
            $('.success-alert').fadeOut();
        }, 4000);
    }

    /*
     * عرض رسالة تحذير
     */
    function showWarning(message) {
        // إزالة أي رسالة سابقة
        $('.warning-alert').remove();

        // إضافة رسالة تحذير جديدة
        const alertHtml = `
        <div class="alert alert-warning alert-dismissible fade show warning-alert" role="alert">
            <i class="fas fa-exclamation-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;

        $('#searchResults').prepend(alertHtml);

        // إخفاء الرسالة تلقائياً بعد 6 ثوان
        setTimeout(() => {
            $('.warning-alert').fadeOut();
        }, 6000);
    }

    /*
     * عرض تفاصيل السجل - التوجه إلى صفحة العرض الكاملة
     */
    function showRecordDetails(encodedRecord) {
        try {
            const record = JSON.parse(decodeURIComponent(encodedRecord));

            // التحقق من وجود ID وأن السجل رئيسي
            if (record.id && record.record_type === 'سجل رئيسي') {
                // التوجه إلى صفحة العرض الكاملة
                window.location.href = `admin/records-management/${record.id}/show`;
            } else {
                // عرض تفاصيل سريعة في مودال للسجلات الأخرى
                showQuickDetailsModal(record);
            }
        } catch (error) {
            console.error('خطأ في معالجة بيانات السجل:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'حدث خطأ في معالجة بيانات السجل',
                confirmButtonText: 'موافق'
            });
        }
    }

    /*
     * عرض تفاصيل سريعة في مودال (للسجلات غير الرئيسية)
     */
    function showQuickDetailsModal(record) {
        let modalContent = '';

        if (record.record_type === 'فرد أسرة') {
            modalContent = formatFamilyMemberDetailsForModal(record);
        } else if (record.record_type === 'متوفي') {
            modalContent = formatDeceasedRecordDetailsForModal(record);
        } else {
            modalContent = formatMainRecordDetailsForModal(record);
        }

        // إنشاء المودال
        const modalHtml = `
            <div class="modal fade" id="recordDetailsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-user"></i> تفاصيل ${record.record_type}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${modalContent}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                            ${record.id && record.record_type === 'سجل رئيسي' ? `<a href="admin/records-management/${record.id}/show" class="btn btn-primary">عرض الملف الكامل</a>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // إزالة المودال القديم إن وجد
        $('#recordDetailsModal').remove();

        // إضافة المودال الجديد وعرضه
        $('body').append(modalHtml);
        $('#recordDetailsModal').modal('show');
    }

    /*
     * عرض النافذة المنبثقة للتفاصيل الكاملة
     */
    function showDetailedModal(record) {
        let modalHtml = `
        <div class="modal fade" id="recordDetailsModal" tabindex="-1" aria-labelledby="recordDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="recordDetailsModalLabel">
                            <i class="fas fa-user-circle"></i> تفاصيل السجل: ${record.full_name || 'غير محدد'}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- البيانات الأساسية -->
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-id-card"></i> البيانات الأساسية</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-folder-open text-primary"></i> رقم الملف:</strong>
                                            <span class="badge bg-primary">${record.file_id || 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-id-card text-success"></i> رقم الهوية:</strong>
                                            <code class="bg-light p-1 rounded">${record.identity_number || 'غير محدد'}</code>
                                        </p>
                                        <p><strong><i class="fas fa-user text-info"></i> الاسم الكامل:</strong>
                                            <span class="text-primary fw-bold">${record.full_name || 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-birthday-cake text-warning"></i> تاريخ الميلاد:</strong>
                                            ${record.birth_date || 'غير محدد'}
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-hourglass-half text-info"></i> العمر:</strong>
                                            <span class="badge bg-info">${record.age || 'غير محدد'} سنة</span>
                                        </p>
                                        <p><strong><i class="fas fa-venus-mars text-secondary"></i> الجنس:</strong>
                                            <span class="badge ${record.gender === 'ذكر' ? 'bg-primary' : 'bg-pink'}">${record.gender || 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-check-circle text-success"></i> حالة الطلب:</strong>
                                            <span class="badge bg-success">${record.request_status || 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-heart text-danger"></i> الحالة الاجتماعية:</strong>
                                            <span class="badge bg-secondary">${record.marital_status || 'غير محدد'}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- معلومات الاتصال -->
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-phone"></i> معلومات الاتصال</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><strong><i class="fas fa-phone text-success"></i> الجوال الأساسي:</strong><br>
                                            ${record.phone ? `<a href="tel:${record.phone}" class="btn btn-outline-success btn-sm">${record.phone}</a>` : 'غير محدد'}
                                        </p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong><i class="fas fa-mobile-alt text-info"></i> الجوال البديل:</strong><br>
                                            ${record.alt_phone ? `<a href="tel:${record.alt_phone}" class="btn btn-outline-info btn-sm">${record.alt_phone}</a>` : 'غير محدد'}
                                        </p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><strong><i class="fas fa-envelope text-primary"></i> البريد الإلكتروني:</strong><br>
                                            ${record.email ? `<a href="mailto:${record.email}" class="btn btn-outline-primary btn-sm">إرسال إيميل</a>` : 'غير محدد'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- العنوان والموقع -->
                        <div class="card mb-3">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="fas fa-map-marker-alt"></i> العنوان والموقع</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-city text-primary"></i> المدينة:</strong>
                                            <span class="badge bg-primary">${record.city || 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-map text-info"></i> المحافظة:</strong>
                                            <span class="badge bg-info">${record.province || 'غير محدد'}</span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-building text-secondary"></i> القسم:</strong>
                                            <span class="badge bg-secondary">${record.section || 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-home text-success"></i> العنوان التفصيلي:</strong><br>
                                            <small class="text-muted">${record.address || 'غير محدد'}</small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- التعليم والعمل -->
                        <div class="card mb-3">
                            <div class="card-header bg-purple text-white">
                                <h6 class="mb-0"><i class="fas fa-graduation-cap"></i> التعليم والعمل</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-graduation-cap text-info"></i> المستوى التعليمي:</strong>
                                            <span class="badge bg-info">${record.education_level || 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-briefcase text-success"></i> حالة التوظيف:</strong>
                                            <span class="badge ${record.employment_status && record.employment_status.includes('يعمل') ? 'bg-success' : 'bg-danger'}">${record.employment_status || 'غير محدد'}</span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-money-bill-wave text-success"></i> الدخل الشهري:</strong>
                                            <span class="badge bg-success">${record.monthly_income ? record.monthly_income + ' ريال' : 'غير محدد'}</span>
                                        </p>
                                        <p><strong><i class="fas fa-users text-info"></i> حجم الأسرة:</strong>
                                            <span class="badge bg-info">${record.family_size || 'غير محدد'} فرد</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الحالة الصحية -->
                        <div class="card mb-3">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0"><i class="fas fa-heartbeat"></i> الحالة الصحية</h6>
                            </div>
                            <div class="card-body">
                                <p><strong><i class="fas fa-heartbeat text-danger"></i> الحالة الصحية:</strong>
                                    <span class="badge ${record.health_status && (record.health_status.includes('سليم') || record.health_status.includes('جيد')) ? 'bg-success' : 'bg-danger'}">${record.health_status || 'غير محدد'}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="admin/records-management/${record.id}" class="btn btn-primary">
                            <i class="fas fa-eye"></i> عرض الملف الكامل
                        </a>
                        <a href="admin/records-management/${record.id}/edit" class="btn btn-warning">
                            <i class="fas fa-edit"></i> تعديل البيانات
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        // إزالة أي نافذة سابقة وإضافة الجديدة
        const existingModal = document.getElementById('recordDetailsModal');
        if (existingModal) {
            existingModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // إظهار النافذة المنبثقة
        const modal = new bootstrap.Modal(document.getElementById('recordDetailsModal'));
        modal.show();
    }

    /*
     * عرض رسالة خطأ في نافذة منبثقة
     */
    function showErrorModal(message) {
        let errorModalHtml = `
        <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="errorModalLabel">
                            <i class="fas fa-exclamation-triangle"></i> خطأ
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-exclamation-circle"></i> ${message}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        // إزالة أي نافذة خطأ سابقة وإضافة الجديدة
        const existingErrorModal = document.getElementById('errorModal');
        if (existingErrorModal) {
            existingErrorModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', errorModalHtml);

        // إظهار نافذة الخطأ
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    }

    // جعل الدوال متاحة عالمياً
    window.showRecordDetails = showRecordDetails;
    window.showDetailedModal = showDetailedModal;
    window.showErrorModal = showErrorModal;
    window.showSearchInfo = showSearchInfo;
    window.showError = showError;
    window.showSuccess = showSuccess;
    window.showWarning = showWarning;
    window.showPersonAllDetails = showPersonAllDetails;
    window.showPersonDetailsModal = showPersonDetailsModal;
    window.formatMainRecordDetails = formatMainRecordDetails;
    window.formatDeceasedRecordsDetails = formatDeceasedRecordsDetails;
    window.formatFamilyMembersDetails = formatFamilyMembersDetails;
    window.groupDataByPerson = groupDataByPerson;
    window.getPersonKey = getPersonKey;
    window.formatPersonCard = formatPersonCard;
    window.safeEncode = safeEncode;
    window.safeDecode = safeDecode;
});

// دالة عامة للتصفح (تستدعى من HTML)
function performSearch(page = 1) {
    // استدعاء الدالة داخل jQuery ready
    if (typeof window.searchFunction === 'function') {
        window.searchFunction(page);
    }
}

// تعيين المرجع العام
$(document).ready(function() {
    window.searchFunction = function(page = 1) {
        const formData = new FormData($('#searchForm')[0]);
        formData.append('page', page);

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
                if (response.success) {
                    displayResults(response.data);
                }
            }
        });
    };
});
</script>
@endpush
