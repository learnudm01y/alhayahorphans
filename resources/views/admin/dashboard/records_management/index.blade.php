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
        box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
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
</style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">سجل الإدارة</h3>
                        <div class="card-tools">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-info btn-sm mt-4" data-bs-toggle="modal" data-bs-target="#searchModal">
                                    <i class="fas fa-search"></i> البحث الشامل في السجلات
                                </button>
                                
                                <!-- زر التصدير الرئيسي - Excel مع 4 Sheets و Portal Fields -->
                                <a href="{{ route('admin.records.management.exportAllCSV') }}" class="btn btn-success btn-sm mt-4" title="تصدير Excel شامل - 4 sheets مع الحقول الديناميكية من Portal">
                                    <i class="fas fa-file-excel"></i> تصدير Excel (4 sheets)
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

@endsection

@push('scriptsCode')
    {!! $dataTable->scripts() !!}
    <script>
        $(document).ready(function() {
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
                        <td><span class="badge bg-info">${sectionName}</span></td>
                        <td>${relationshipName}</td>
                        <td><span class="badge bg-success">${healthStatusName}</span></td>
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
                        <td><span class="badge ${gender === 'ذكر' ? 'bg-primary' : 'bg-warning'}">${gender}</span></td>
                        <td><span class="badge bg-success">${healthStatusName}</span></td>
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
