/**
 * ملف JavaScript لصفحة البحث في السجلات
 */

/*
 * عرض النتائج في شكل جدول - النظام الجديد
 */
function displayResultsAsTable(data) {
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
            const fullName = record.full_name ||
                           (record.first_name + ' ' + (record.father_name || record.data_father_name || '') + ' ' + (record.grandfather_name || record.data_grand_father_name || '') + ' ' + (record.family_name || record.data_family_name || '')).trim() ||
                           (record.father_full_name + ' ' + record.mother_full_name).trim() ||
                           'غير محدد';

            const identityNumber = record.identity_number || record.data_id_number || record.person_id || record.dead_people_id_number || '-';
            const phone = record.phone || record.data_phone_number || '-';
            const age = record.age || record.age_at_death || '-';
            const gender = record.gender || record.data_gender || record.dead_people_gender || '-';
            const city = record.city_name || '-';
            const province = record.province_name || '-';
            const requestStatus = record.request_status_name || record.request_status || record.data_request_status || '-';

            tableBodyHtml +=
                '<tr class="table-row-hover">' +
                    '<td class="text-center">' + (index + 1) + '</td>' +
                    '<td><strong>' + fullName + '</strong></td>' +
                    '<td><code class="text-primary">' + identityNumber + '</code></td>' +
                    '<td>' + (phone !== '-' ? '<a href="tel:' + phone + '" class="text-success">' + phone + '</a>' : '-') + '</td>' +
                    '<td class="text-center">' +
                        (age !== '-' ? '<span class="badge bg-info">' + age + '</span>' : '-') +
                    '</td>' +
                    '<td class="text-center">' +
                        (gender !== '-' ? '<span class="badge ' + (gender === 'ذكر' ? 'bg-primary' : 'bg-pink') + '">' + gender + '</span>' : '-') +
                    '</td>' +
                    '<td><span class="badge bg-secondary">' + city + '</span></td>' +
                    '<td><span class="badge bg-info">' + province + '</span></td>' +
                    '<td>' +
                        '<span class="badge ' + getStatusBadgeClass(requestStatus) + '">' + requestStatus + '</span>' +
                    '</td>' +
                    '<td class="text-center">' +
                        '<span class="badge ' + record.record_type_class + ' text-white">' + record.record_type + '</span>' +
                    '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            (record.id && record.record_type === 'سجل رئيسي' ? '<a href="/admin/records/management/' + record.id + '/show" class="btn btn-primary btn-sm" title="عرض السجل الكامل"><i class="fas fa-file-alt"></i></a>' : '') +
                            '<button class="btn btn-info btn-sm" onclick="showRecordDetails(' + JSON.stringify(record).replace(/"/g, '&quot;') + ')" title="عرض التفاصيل السريعة">' +
                                '<i class="fas fa-info-circle"></i>' +
                            '</button>' +
                            (record.view_url ? '<a href="' + record.view_url + '" class="btn btn-success btn-sm" title="فتح الملف الكامل"><i class="fas fa-external-link-alt"></i></a>' : '') +
                        '</div>' +
                    '</td>' +
                '</tr>';
        });

        // عرض النتائج في الجدول
        $('#resultsTableBody').html(tableBodyHtml);

        if (allRecords.length === 0) {
            $('#resultsTableBody').html(
                '<tr>' +
                    '<td colspan="11" class="text-center py-4">' +
                        '<div class="text-muted">' +
                            '<i class="fas fa-search fa-2x mb-2"></i>' +
                            '<p>لم يتم العثور على نتائج</p>' +
                        '</div>' +
                    '</td>' +
                '</tr>'
            );
        }
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
 * عرض تفاصيل السجل - التوجه إلى صفحة العرض الكاملة
 */
function showRecordDetails(record) {
    // التحقق من وجود ID وأن السجل رئيسي
    if (record.id && record.record_type === 'سجل رئيسي') {
        // التوجه إلى صفحة العرض الكاملة
        window.location.href = `/admin/records/management/${record.id}/show`;
    } else {
        // عرض تفاصيل سريعة في مودال للسجلات الأخرى
        showQuickDetailsModal(record);
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
    const modalHtml =
        '<div class="modal fade" id="recordDetailsModal" tabindex="-1">' +
            '<div class="modal-dialog modal-lg">' +
                '<div class="modal-content">' +
                    '<div class="modal-header bg-primary text-white">' +
                        '<h5 class="modal-title">' +
                            '<i class="fas fa-user"></i> تفاصيل ' + record.record_type +
                        '</h5>' +
                        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        modalContent +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>' +
                        (record.id && record.record_type === 'سجل رئيسي' ? '<a href="/admin/records/management/' + record.id + '/show" class="btn btn-primary">عرض الملف الكامل</a>' : '') +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

    // إزالة المودال القديم إن وجد
    $('#recordDetailsModal').remove();

    // إضافة المودال الجديد وعرضه
    $('body').append(modalHtml);
    $('#recordDetailsModal').modal('show');
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

/*
 * عرض النتائج
 */
function displayResults(data) {
    displayResultsAsTable(data);
}

// تصدير الدوال للنطاق العام
window.displayResults = displayResults;
    window.showRecordDetails = showRecordDetails;
    window.getStatusBadgeClass = getStatusBadgeClass;
    window.displayResultsAsTable = displayResultsAsTable;

$(document).ready(function() {
    console.log('Search JavaScript loaded successfully');
});
