/**
 * إدارة حقول الجمعيات - Fields Management
 * هذا الملف يحتوي على كل الوظائف المتعلقة بإدارة حقول الجمعيات
 */

class SponsorFieldsManager {
    constructor() {
        this.currentSponsorId = null;
        this.dataTable = null;
        this.fieldsData = [];
        this.init();
    }

    init() {
        this.initializeDataTable();
        this.attachEventListeners();
        this.loadAvailableFields();
    }

    /**
     * تهيئة DataTable
     */
    initializeDataTable() {
        const self = this;

        this.dataTable = $('#sponsors_fields_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: window.location.origin + '/admin/sponsors/fields-management/data',
                type: 'GET',
                data: function(d) {
                    d.search = {
                        value: $('#kt_sponsors_fields_search').val()
                    };
                }
            },
            columns: [
                {
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                },
                {
                    data: 'file_id',
                    name: 'file_id',
                    className: 'text-end'
                },
                {
                    data: 'sponsor_name',
                    name: 'sponsor_name',
                    className: 'text-end'
                },
                {
                    data: 'country_name',
                    name: 'country_name',
                    orderable: false,
                    searchable: false,
                    className: 'text-end'
                },
                {
                    data: 'sponsor_phone_number',
                    name: 'sponsor_phone_number',
                    className: 'text-end'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    defaultContent: '',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `
                            <div class="text-center">
                                <button type="button"
                                        class="btn btn-sm btn-primary manage-fields-btn"
                                        data-sponsor-id="${row.id}"
                                        data-sponsor-name="${row.sponsor_name}">
                                    <i class="fas fa-cogs me-2"></i>
                                    إدارة الحقول
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/ar.json'
            },
            pageLength: 10,
            order: [[1, 'asc']],
            autoWidth: false,
            scrollX: false,
            responsive: false,
            columnDefs: [
                { width: '80px', targets: 0 },
                { width: '120px', targets: 1 },
                { width: 'auto', targets: 2 },
                { width: '150px', targets: 3 },
                { width: '150px', targets: 4 },
                { width: '180px', targets: 5 }
            ]
        });

        // البحث المخصص
        $('#kt_sponsors_fields_search').on('keyup', function() {
            self.dataTable.draw();
        });
    }

    /**
     * ربط الأحداث
     */
    attachEventListeners() {
        const self = this;

        // فتح المودال
        $(document).on('click', '.manage-fields-btn', function() {
            const sponsorId = $(this).data('sponsor-id');
            const sponsorName = $(this).data('sponsor-name');
            self.openFieldsModal(sponsorId, sponsorName);
        });

        // تبديل حالة الحقل
        $(document).on('change', '.field-switch', function() {
            const fieldId = $(this).data('field-id');
            const isActive = $(this).is(':checked');
            self.toggleField(fieldId, isActive);
        });

        // البحث في الحقول
        $('#searchFields').on('keyup', function() {
            self.searchFields($(this).val());
        });

        // حفظ التغييرات
        $('#saveFieldsBtnFooter').on('click', function() {
            self.saveFieldsConfiguration();
        });

        // النقر على عنصر الحقل (لتفعيله/تعطيله)
        $(document).on('click', '.field-item', function(e) {
            if (!$(e.target).is('.field-switch')) {
                const checkbox = $(this).find('.field-switch');
                checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
            }
        });
    }

    /**
     * فتح مودال إدارة الحقول
     */
    openFieldsModal(sponsorId, sponsorName) {
        this.currentSponsorId = sponsorId;
        $('#sponsor_name_display').text(sponsorName);
        $('#fieldsManagementModal').modal('show');
        this.loadSponsorFields(sponsorId);
    }

    /**
     * تحميل حقول الجمعية
     */
    loadSponsorFields(sponsorId) {
        const self = this;

        // عرض Loading
        this.showLoadingState();

        // تحميل البيانات من السيرفر
        $.ajax({
            url: `/admin/sponsors/${sponsorId}/fields`,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    self.fieldsData = response.fields;
                    self.displayFields();
                } else {
                    self.showError('حدث خطأ أثناء تحميل الحقول');
                }
            },
            error: function(xhr) {
                console.error('Error loading fields:', xhr);
                self.showError('حدث خطأ أثناء تحميل الحقول');
            }
        });
    }

    /**
     * (Deprecated - Not used anymore)
     * This function was used for demo purposes only.
     * Real data is now loaded from the API endpoint.
     */

    /**
     * عرض الحقول في الواجهة
     */
    displayFields() {
        const categories = this.groupFieldsByCategory();
        let fieldsHtml = '';
        let activeFieldsHtml = '';

        // تجميع الحقول حسب الفئة
        Object.keys(categories).forEach(category => {
            fieldsHtml += `<div class="mb-4">
                <h5 class="text-gray-700 fw-bold mb-3">${category}</h5>`;

            categories[category].forEach(field => {
                fieldsHtml += this.createFieldItem(field);

                if (field.active) {
                    activeFieldsHtml += this.createActiveFieldItem(field);
                }
            });

            fieldsHtml += '</div>';
        });

        $('#fieldsListContainer').html(fieldsHtml);
        $('#activeFieldsContainer').html(
            activeFieldsHtml || '<div class="text-center py-10"><p class="text-muted">لا توجد حقول مفعلة</p></div>'
        );
    }

    /**
     * تجميع الحقول حسب الفئة
     */
    groupFieldsByCategory() {
        const grouped = {};

        this.fieldsData.forEach(field => {
            if (!grouped[field.category]) {
                grouped[field.category] = [];
            }
            grouped[field.category].push(field);
        });

        return grouped;
    }

    /**
     * إنشاء عنصر حقل
     */
    createFieldItem(field) {
        const checkedAttr = field.active ? 'checked' : '';
        const activeClass = field.active ? 'active' : '';
        const requiredBadge = field.required ? '<span class="badge badge-danger badge-sm ms-2">إلزامي</span>' : '';

        return `
            <div class="field-item ${activeClass}" data-field-id="${field.id}">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-gray-800">
                            ${field.name}
                            ${requiredBadge}
                        </div>
                        <div class="text-muted fs-7">
                            <span class="badge badge-light-primary field-category-badge">${field.category}</span>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input field-switch"
                               type="checkbox"
                               id="field_${field.id}"
                               data-field-id="${field.id}"
                               ${checkedAttr}
                               ${field.required ? 'disabled' : ''}>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * إنشاء عنصر حقل مفعل
     */
    createActiveFieldItem(field) {
        const requiredBadge = field.required
            ? '<span class="badge badge-danger badge-sm">إلزامي</span>'
            : '<span class="badge badge-success">مفعل</span>';

        return `
            <div class="field-item active mb-3" data-field-id="${field.id}">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-gray-800">${field.name}</div>
                        <div class="text-muted fs-7">
                            <span class="badge badge-light-primary field-category-badge">${field.category}</span>
                            <span class="badge badge-light-info ms-2">الترتيب: ${field.order}</span>
                        </div>
                    </div>
                    <div>
                        ${requiredBadge}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * تبديل حالة الحقل
     */
    toggleField(fieldId, isActive) {
        const field = this.fieldsData.find(f => f.id === fieldId);
        if (field) {
            field.active = isActive;
        }

        const fieldItem = $(`.field-item[data-field-id="${fieldId}"]`);

        if (isActive) {
            fieldItem.addClass('active');
        } else {
            fieldItem.removeClass('active');
        }

        this.updateActiveFieldsDisplay();
    }

    /**
     * تحديث عرض الحقول المفعلة
     */
    updateActiveFieldsDisplay() {
        let activeFieldsHtml = '';

        const activeFields = this.fieldsData.filter(f => f.active);

        if (activeFields.length === 0) {
            activeFieldsHtml = '<div class="text-center py-10"><p class="text-muted">لا توجد حقول مفعلة</p></div>';
        } else {
            activeFields.forEach(field => {
                activeFieldsHtml += this.createActiveFieldItem(field);
            });
        }

        $('#activeFieldsContainer').html(activeFieldsHtml);
    }

    /**
     * البحث في الحقول
     */
    searchFields(searchValue) {
        const value = searchValue.toLowerCase();

        $('.field-item').each(function() {
            const fieldName = $(this).find('.fw-bold').first().text().toLowerCase();
            const fieldCategory = $(this).find('.field-category-badge').text().toLowerCase();

            if (fieldName.includes(value) || fieldCategory.includes(value)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    /**
     * حفظ إعدادات الحقول
     */
    saveFieldsConfiguration() {
        const self = this;

        // جمع أسماء الأعمدة للحقول المفعلة فقط
        const activeFieldsDbColumns = this.fieldsData
            .filter(f => f.active)
            .map(f => f.db_column);

        Swal.fire({
            title: 'جاري الحفظ...',
            text: 'يرجى الانتظار',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // حفظ البيانات في قاعدة البيانات
        $.ajax({
            url: `/admin/sponsors/${this.currentSponsorId}/fields`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                fields: activeFieldsDbColumns
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحفظ بنجاح',
                        text: `تم حفظ ${response.active_fields_count} حقل مفعل`,
                        showConfirmButton: true,
                        confirmButtonText: 'حسناً',
                        timer: 3000
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'حدث خطأ',
                        text: response.message || 'فشل حفظ التغييرات'
                    });
                }
            },
            error: function(xhr) {
                console.error('Error saving fields:', xhr);
                let errorMessage = 'فشل حفظ التغييرات';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'حدث خطأ',
                    text: errorMessage
                });
            }
        });
    }

    /**
     * عرض حالة التحميل
     */
    showLoadingState() {
        const loadingHtml = `
            <div class="text-center py-10">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
                <p class="mt-3">جاري تحميل الحقول...</p>
            </div>
        `;

        $('#fieldsListContainer').html(loadingHtml);
        $('#activeFieldsContainer').html(loadingHtml);
    }

    /**
     * عرض رسالة خطأ
     */
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: message
        });
    }

    /**
     * تحميل الحقول المتاحة (يمكن استخدامها لاحقاً)
     */
    loadAvailableFields() {
        // TODO: تحميل الحقول المتاحة من الخادم
        // يمكن استخدامها لإضافة حقول جديدة
    }
}

// تهيئة المدير عند تحميل الصفحة
$(document).ready(function() {
    if ($('#sponsors_fields_table').length) {
        window.sponsorFieldsManager = new SponsorFieldsManager();
    }
});
