/**
 * نظام إدارة الوثائق - النسخة الجديدة البسيطة
 * هذا الملف يحتوي على كود بسيط ومباشر لإدارة الوثائق
 */

// متغيرات عامة
let currentSponsorId = null;
let documentsData = [];

/**
 * تحميل الوثائق عند فتح التبويب
 */
function loadDocuments(sponsorId) {
    currentSponsorId = sponsorId;
    console.log('📥 ==== بدء تحميل الوثائق ====');
    console.log('📥 معرف الجمعية:', sponsorId);
    console.log('📥 URL:', `/admin/sponsors/${sponsorId}/documents`);

    // إظهار loading
    console.log('🔄 إظهار شاشة التحميل...');
    $('#documents_loading').show();
    $('#documents_list').hide();

    $.ajax({
        url: `/admin/sponsors/${sponsorId}/documents`,
        method: 'GET',
        beforeSend: function() {
            console.log('📡 إرسال طلب AJAX...');
        },
        success: function(response) {
            console.log('✅ ==== تم استلام الرد بنجاح ====');
            console.log('✅ البيانات الكاملة:', response);
            console.log('✅ success:', response.success);
            console.log('✅ data:', response.data);
            console.log('✅ عدد الوثائق:', response.data ? response.data.length : 0);

            if (response.success && response.data) {
                documentsData = response.data;
                console.log('📊 تم حفظ البيانات في documentsData');
                console.log('📊 عدد العناصر:', documentsData.length);
                renderDocuments();
            } else {
                console.error('❌ البيانات غير صحيحة في الرد');
                showError('فشل تحميل الوثائق');
            }

            console.log('🔄 إخفاء شاشة التحميل وإظهار القائمة');
            $('#documents_loading').hide();
            $('#documents_list').show();
        },
        error: function(xhr, status, error) {
            console.error('❌ ==== فشل طلب AJAX ====');
            console.error('❌ الحالة:', status);
            console.error('❌ الخطأ:', error);
            console.error('❌ xhr:', xhr);
            console.error('❌ responseText:', xhr.responseText);
            showError('حدث خطأ أثناء تحميل الوثائق');
            $('#documents_loading').hide();
        }
    });
}

/**
 * عرض الوثائق في الجدول
 */
function renderDocuments() {
    console.log('🎨 ==== بدء عرض الوثائق ====');
    console.log('🎨 عدد الوثائق للعرض:', documentsData.length);

    const tbody = $('#documents_tbody');
    console.log('🎨 عنصر tbody موجود:', tbody.length > 0);

    tbody.empty();
    console.log('🎨 تم تفريغ الجدول');

    let enabledCount = 0;

    if (documentsData.length === 0) {
        console.warn('⚠️ لا توجد وثائق للعرض!');
        tbody.html('<tr><td colspan="6" class="text-center text-muted py-5">لا توجد وثائق</td></tr>');
        return;
    }

    documentsData.forEach(function(doc, index) {
        console.log(`📄 عرض الوثيقة ${index + 1}:`, doc);

        const isChecked = doc.is_enabled ? 'checked' : '';
        const statusBadge = doc.is_enabled
            ? '<span class="badge badge-light-success">مفعل</span>'
            : '<span class="badge badge-light-secondary">معطل</span>';

        const isSaveLocal = doc.save_local ? 'checked' : '';

        if (doc.is_enabled) enabledCount++;

        const row = `
            <tr data-doc-id="${doc.id}">
                <td class="text-center">
                    <input type="checkbox"
                           class="form-check-input doc-checkbox"
                           data-doc-id="${doc.id}"
                           ${isChecked}>
                </td>
                <td class="fw-bold">${doc.id}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-pdf text-danger fs-3 me-3"></i>
                        <span class="fw-semibold">${doc.description}</span>
                    </div>
                </td>
                <td>
                    <span class="badge badge-light-primary">${doc.pref}</span>
                </td>
                <td class="text-center status-badge">
                    ${statusBadge}
                </td>
                <td class="text-center">
                    <div class="form-check form-switch form-check-custom">
                        <input type="checkbox"
                               class="form-check-input save-local-toggle"
                               data-doc-id="${doc.id}"
                               ${isSaveLocal}
                               title="حفظ محلي بجانب Google Drive">
                    </div>
                </td>
            </tr>
        `;

        tbody.append(row);
    });

    console.log(`🎨 تم إضافة ${documentsData.length} صف إلى الجدول`);

    updateEnabledCount(enabledCount);

    console.log(`✅ ==== اكتمل عرض الوثائق ====`);
    console.log(`✅ إجمالي الوثائق: ${documentsData.length}`);
    console.log(`✅ الوثائق المفعلة: ${enabledCount}`);
    console.log(`✅ عدد الصفوف في الجدول: ${tbody.find('tr').length}`);
}

/**
 * تحديث عدد الوثائق المفعلة
 */
function updateEnabledCount(count) {
    $('#enabled_count').text(count);
}

/**
 * حفظ التغييرات
 */
function saveDocuments() {
    if (!currentSponsorId) {
        showError('لم يتم تحديد الجمعية');
        return;
    }

    console.log('💾 بدء عملية الحفظ...');

    // جمع البيانات
    const documents = [];
    $('.doc-checkbox').each(function() {
        const docId = parseInt($(this).data('doc-id'));
        const isEnabled = $(this).is(':checked');
        const saveLocal = $(`.save-local-toggle[data-doc-id="${docId}"]`).is(':checked');

        documents.push({
            document_type_id: docId,
            is_enabled: isEnabled,
            save_local: saveLocal
        });
    });

    console.log('📊 البيانات المراد حفظها:', documents);

    // إظهار loader على الزر
    const $btn = $('#save_documents_btn');
    const originalText = $btn.html();
    $btn.html('<span class="spinner-border spinner-border-sm me-2"></span>جاري الحفظ...').prop('disabled', true);

    // إرسال البيانات
    $.ajax({
        url: `/admin/sponsors/${currentSponsorId}/documents`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Content-Type': 'application/json'
        },
        data: JSON.stringify({ documents: documents }),
        dataType: 'json',
        success: function(response) {
            console.log('✅ تم الحفظ بنجاح:', response);

            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: 'تم حفظ إعدادات الوثائق بنجاح',
                timer: 2000,
                showConfirmButton: false
            });

            // إعادة تحميل البيانات للتأكد
            setTimeout(function() {
                loadDocuments(currentSponsorId);
            }, 500);
        },
        error: function(xhr) {
            console.error('❌ فشل الحفظ:', xhr);

            let errorMsg = 'حدث خطأ أثناء الحفظ';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }

            Swal.fire({
                icon: 'error',
                title: 'فشل!',
                text: errorMsg
            });
        },
        complete: function() {
            $btn.html(originalText).prop('disabled', false);
        }
    });
}

/**
 * عرض رسالة خطأ
 */
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'خطأ',
        text: message
    });
}

/**
 * التهيئة عند تحميل الصفحة
 */
$(document).ready(function() {
    console.log('🚀 ==== نظام الوثائق الجديد - التهيئة ====');
    console.log('✅ تم تحميل documents-management-new.js');
    console.log('✅ jQuery جاهز:', typeof $ !== 'undefined');
    console.log('✅ دالة loadDocuments متاحة عالمياً:', typeof loadDocuments === 'function');
    console.log('✅ عنصر documents_tbody موجود:', $('#documents_tbody').length > 0);

    // عند تغيير checkbox
    $(document).on('change', '.doc-checkbox', function() {
        const $row = $(this).closest('tr');
        const isChecked = $(this).is(':checked');

        // تحديث badge
        const statusBadge = isChecked
            ? '<span class="badge badge-light-success">مفعل</span>'
            : '<span class="badge badge-light-secondary">معطل</span>';

        $row.find('.status-badge').html(statusBadge);

        // تحديث العداد
        const enabledCount = $('.doc-checkbox:checked').length;
        updateEnabledCount(enabledCount);

        console.log(`🔄 تغيير حالة الوثيقة ${$(this).data('doc-id')} إلى: ${isChecked ? 'مفعل' : 'معطل'}`);
    });

    // زر تحديد الكل
    $('#select_all_docs').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.doc-checkbox').prop('checked', isChecked).trigger('change');
        console.log(`🔄 تحديد الكل: ${isChecked}`);
    });

    // زر الحفظ
    $('#save_documents_btn').on('click', function(e) {
        e.preventDefault();
        console.log('🖱️ تم الضغط على زر الحفظ');
        saveDocuments();
    });

    console.log('✅ جميع Event Listeners جاهزة');
});
