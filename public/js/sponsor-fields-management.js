/**
 * إدارة حقول الجمعيات - Fields Management
 * هذا الملف يحتوي على كل الوظائف المتعلقة بإدارة حقول الجمعيات
 */

// إعداد CSRF token لجميع طلبات AJAX
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

class SponsorFieldsManager {
    constructor() {
        this.currentSponsorId = null;
        this.dataTable = null;
        this.fieldsData = [];
        this.documentsData = [];
        this.currentTab = 'fields'; // 'fields' or 'documents'
        this.compressAttachmentsEnabled = true; // إعداد الضغط
        this.init();
    }

    init() {
        this.initializeDataTable();
        this.attachEventListeners();
        this.loadAvailableFields();
        this.attachTabEventListeners();
        this.initializeToggleLabels();
    }

    /**
     * تهيئة نصوص الحالة عند التحميل
     */
    initializeToggleLabels() {
        $('.document-enabled-toggle').each(function() {
            const isChecked = $(this).is(':checked');
            $(this).closest('.form-check').find('.enabled-text').toggle(isChecked);
            $(this).closest('.form-check').find('.disabled-text').toggle(!isChecked);
        });
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
                        const driveEnabled = row.google_drive_enabled || false;

                        // تصميم جديد كلياً: زر كامل مع badge
                        const driveButtonClass = driveEnabled
                            ? 'btn-success'
                            : 'btn-light-danger';
                        const driveIcon = driveEnabled
                            ? 'fa-cloud-arrow-up'
                            : 'fa-cloud-slash';
                        const driveText = driveEnabled
                            ? '<span class="fw-bold">Google Drive</span>'
                            : '<span class="fw-bold">Google Drive</span>';
                        const driveBadge = driveEnabled
                            ? '<span class="badge badge-light-success ms-2">مفعل</span>'
                            : '<span class="badge badge-light-secondary ms-2">معطل</span>';

                        return `
                            <div class="d-flex flex-column gap-2">
                                <button type="button"
                                        class="btn btn-sm ${driveButtonClass} toggle-google-drive-btn w-100"
                                        data-sponsor-id="${row.id}"
                                        data-enabled="${driveEnabled ? '1' : '0'}"
                                        style="min-width: 160px;">
                                    <i class="fas ${driveIcon} me-2"></i>
                                    ${driveText}
                                    ${driveBadge}
                                </button>
                                <button type="button"
                                        class="btn btn-sm btn-primary manage-fields-btn w-100"
                                        data-sponsor-id="${row.id}"
                                        data-sponsor-name="${row.sponsor_name}"
                                        style="min-width: 160px;">
                                    <i class="fas fa-cogs me-2"></i>
                                    إدارة الحقول
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                search: "بحث:",
                lengthMenu: "عرض _MENU_ سجلات",
                info: "عرض _START_ إلى _END_ من أصل _TOTAL_ سجل",
                infoEmpty: "لا توجد سجلات متاحة",
                infoFiltered: "(تصفية من _MAX_ إجمالي السجلات)",
                loadingRecords: "جاري التحميل...",
                zeroRecords: "لم يتم العثور على سجلات مطابقة",
                emptyTable: "لا توجد بيانات متاحة في الجدول",
                paginate: {
                    first: "الأول",
                    previous: "السابق",
                    next: "التالي",
                    last: "الأخير"
                },
                aria: {
                    sortAscending: ": تفعيل لترتيب العمود تصاعدياً",
                    sortDescending: ": تفعيل لترتيب العمود تنازلياً"
                }
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
                { width: '220px', targets: 5 } // زيادة عرض عمود الإجراءات للتصميم الجديد
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

        // تبديل حالة Google Drive
        $(document).on('click', '.toggle-google-drive-btn', function() {
            const sponsorId = $(this).data('sponsor-id');
            const isEnabled = $(this).data('enabled') === 1;
            self.toggleGoogleDrive(sponsorId, !isEnabled, $(this));
        });

        // تبديل حالة الحقل العادي
        $(document).on('change', '.field-switch', function() {
            const fieldId = $(this).data('field-id');
            const isActive = $(this).is(':checked');
            self.toggleField(fieldId, isActive);
        });

        // تبديل حالة الحقول المدمجة
        $(document).on('change', '.merged-fields-switch', function() {
            const mergedFields = $(this).data('merged-fields');
            const mergedType = $(this).data('merged-type');
            const isActive = $(this).is(':checked');
            self.toggleMergedFields(mergedFields, mergedType, isActive);
        });

        // البحث في الحقول
        $('#searchFields').on('keyup', function() {
            self.searchFields($(this).val());
        });

        // حفظ التغييرات
        $('#saveFieldsBtnFooter').on('click', function() {
            self.saveFieldsConfiguration();
        });

        // تبديل حالة الضغط
        $(document).on('change', '#compress_attachments_images_toggle', function() {
            self.compressAttachmentsEnabled = $(this).is(':checked');
            self.updateCompressToggleLabel();
        });

        // النقر على عنصر الحقل (لتفعيله/تعطيله)
        $(document).on('click', '.field-item', function(e) {
            if (!$(e.target).is('.field-switch, .merged-fields-switch')) {
                const checkbox = $(this).find('.field-switch, .merged-fields-switch');
                checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
            }
        });
    }

    /**
     * تبديل حالة الحقول المدمجة
     */
    toggleMergedFields(mergedFields, mergedType, isActive) {
        mergedFields.forEach(fieldDbColumn => {
            const field = this.fieldsData.find(f => f.db_column === fieldDbColumn);
            if (field) {
                field.active = isActive;
            }
        });

        const fieldItem = $(`.field-item[data-merged="${mergedType}"]`);
        if (isActive) {
            fieldItem.addClass('active');
        } else {
            fieldItem.removeClass('active');
        }

        this.updateActiveFieldsDisplay();
    }

    /**
     * فتح مودال إدارة الحقول
     */
    openFieldsModal(sponsorId, sponsorName) {
        this.currentSponsorId = sponsorId;
        $('#sponsor_name_display').text(sponsorName);

        console.log('🔓 ==== فتح المودال ====');
        console.log('🔓 معرف الجمعية:', sponsorId);
        console.log('🔓 اسم الجمعية:', sponsorName);

        $('#fieldsManagementModal').modal('show');
        this.loadSponsorFields(sponsorId);

        // التحقق من التاب النشط بعد فتح المودال
        setTimeout(() => {
            console.log('🔍 فحص التاب النشط بعد فتح المودال...');
            const activeTab = $('.tab-pane.active').attr('id');
            console.log('🔍 التاب النشط:', activeTab);

            if (activeTab === 'documents_tab' || $('#documents_tab').hasClass('active')) {
                console.log('📄 ==== التاب النشط هو الوثائق ====');
                console.log('📄 التحقق من دالة loadDocuments:', typeof loadDocuments);

                if (typeof loadDocuments === 'function') {
                    console.log('✅ دالة loadDocuments موجودة - تحميل الآن');
                    loadDocuments(sponsorId);
                } else {
                    console.error('❌ دالة loadDocuments غير موجودة!');
                    console.error('❌ تأكد من تحميل documents-management-new.js قبل sponsor-fields-management.js');
                }
            } else {
                console.log('ℹ️ التاب النشط ليس الوثائق، سيتم التحميل عند التبديل');
            }
        }, 100);
    }

    /**
     * تحديث حالة زر التبديل للضغط
     */
    updateCompressToggle() {
        $('#compress_attachments_images_toggle').prop('checked', this.compressAttachmentsEnabled);
        this.updateCompressToggleLabel();
    }

    /**
     * تحديث نص حالة زر التبديل للضغط
     */
    updateCompressToggleLabel() {
        const isChecked = $('#compress_attachments_images_toggle').is(':checked');
        $('#compress_enabled_text').toggle(isChecked);
        $('#compress_disabled_text').toggle(!isChecked);
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
                    self.compressAttachmentsEnabled = response.compress_attachments_images;
                    self.displayFields();
                    self.updateCompressToggle();
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
                // إخفاء الحقول المكررة (النسخ السفلية)
                if (this.shouldHideField(field)) {
                    return; // تخطي هذا الحقل
                }

                // معالجة دمج حقول أسماء المعيل الأربعة
                if (this.isMergedNameField(field)) {
                    if (field.db_column === 'field_data_first_name') {
                        // عرض حقل مدمج واحد للأسماء الأربعة
                        fieldsHtml += this.createMergedNameFieldItem(field, categories[category]);

                        if (this.areMergedFieldsActive('guardian_name')) {
                            activeFieldsHtml += this.createMergedActiveFieldItem('guardian_name');
                        }
                    } else if (field.db_column === 'field_father_id') {
                        // عرض حقل مدمج لمعلومات الأب المتوفي
                        fieldsHtml += this.createMergedDeadParentFieldItem(field, 'father');

                        if (this.areMergedFieldsActive('father')) {
                            activeFieldsHtml += this.createMergedActiveFieldItem('father');
                        }
                    } else if (field.db_column === 'field_mother_id') {
                        // عرض حقل مدمج لمعلومات الأم المتوفية
                        fieldsHtml += this.createMergedDeadParentFieldItem(field, 'mother');

                        if (this.areMergedFieldsActive('mother')) {
                            activeFieldsHtml += this.createMergedActiveFieldItem('mother');
                        }
                    }
                    // تخطي باقي الحقول المدمجة
                    return;
                }

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
     * التحقق من الحقول التي يجب إخفاؤها (النسخ المكررة السفلية)
     */
    shouldHideField(field) {
        const hiddenFields = [
            'field_re_guardian_name',       // اسم الوصي (مكرر)
            'field_re_guardian_phone',      // هاتف الوصي (مكرر)
            'field_re_guardian_id',         // رقم هوية الوصي (مكرر)
            'field_family_members_count'    // عدد أفراد الأسرة مع اليتيم
        ];

        return hiddenFields.includes(field.db_column);
    }

    /**
     * التحقق من حقول الأسماء المدمجة
     */
    isMergedNameField(field) {
        const mergedNameFields = [
            'field_data_first_name',
            'field_data_father_name',
            'field_data_grand_father_name',
            'field_data_family_name'
        ];

        const mergedFatherFields = [
            'field_father_id',
            'field_father_first_name',
            'field_father_death_date',
            'field_father_death_reason'
        ];

        const mergedMotherFields = [
            'field_mother_id',
            'field_mother_first_name',
            'field_mother_death_date',
            'field_mother_death_reason'
        ];

        return mergedNameFields.includes(field.db_column) ||
               mergedFatherFields.includes(field.db_column) ||
               mergedMotherFields.includes(field.db_column);
    }

    /**
     * التحقق من تفعيل حقول الأسماء المدمجة
     */
    areMergedFieldsActive(type) {
        let fieldsToCheck = [];

        if (type === 'guardian_name') {
            fieldsToCheck = [
                'field_data_first_name',
                'field_data_father_name',
                'field_data_grand_father_name',
                'field_data_family_name'
            ];
        } else if (type === 'father') {
            fieldsToCheck = [
                'field_father_id',
                'field_father_first_name',
                'field_father_death_date',
                'field_father_death_reason'
            ];
        } else if (type === 'mother') {
            fieldsToCheck = [
                'field_mother_id',
                'field_mother_first_name',
                'field_mother_death_date',
                'field_mother_death_reason'
            ];
        }

        return this.fieldsData.some(f =>
            fieldsToCheck.includes(f.db_column) && f.active
        );
    }

    /**
     * إنشاء عنصر الحقل المدمج للأسماء الأربعة
     */
    createMergedNameFieldItem(baseField, categoryFields) {
        const mergedNameFields = [
            'field_data_first_name',
            'field_data_father_name',
            'field_data_grand_father_name',
            'field_data_family_name'
        ];

        // الحصول على جميع الحقول الأربعة
        const nameFields = this.fieldsData.filter(f =>
            mergedNameFields.includes(f.db_column)
        );

        // التحقق من أي حقل مفعل
        const isAnyActive = nameFields.some(f => f.active);
        const checkedAttr = isAnyActive ? 'checked' : '';
        const activeClass = isAnyActive ? 'active' : '';

        return `
            <div class="field-item ${activeClass} merged-name-field" data-merged="guardian_name">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-gray-800">
                            أسماء المعيل (الأربعة)
                            <span class="badge badge-info badge-sm ms-2">حقل مدمج</span>
                        </div>
                        <div class="text-muted fs-7 mt-1">
                            <span class="badge badge-light-primary field-category-badge">${baseField.category}</span>
                            <small class="text-muted d-block mt-1">
                                يشمل: الاسم الأول، اسم الأب، اسم الجد، اسم العائلة
                            </small>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input merged-fields-switch"
                               type="checkbox"
                               id="merged_guardian_name_field"
                               data-merged-type="guardian_name"
                               data-merged-fields='${JSON.stringify(mergedNameFields)}'
                               ${checkedAttr}>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * إنشاء عنصر الحقل المدمج للوالدين المتوفين
     */
    createMergedDeadParentFieldItem(baseField, parentType) {
        let mergedFields = [];
        let displayName = '';
        let description = '';

        if (parentType === 'father') {
            mergedFields = [
                'field_father_id',
                'field_father_first_name',
                'field_father_death_date',
                'field_father_death_reason'
            ];
            displayName = 'معلومات الأب المتوفى';
            description = 'يشمل: رقم الهوية، الاسم، تاريخ الوفاة، سبب الوفاة';
        } else if (parentType === 'mother') {
            mergedFields = [
                'field_mother_id',
                'field_mother_first_name',
                'field_mother_death_date',
                'field_mother_death_reason'
            ];
            displayName = 'معلومات الأم المتوفية';
            description = 'يشمل: رقم الهوية، الاسم، تاريخ الوفاة، سبب الوفاة';
        }

        // الحصول على جميع الحقول
        const parentFields = this.fieldsData.filter(f =>
            mergedFields.includes(f.db_column)
        );

        // التحقق من أي حقل مفعل
        const isAnyActive = parentFields.some(f => f.active);
        const checkedAttr = isAnyActive ? 'checked' : '';
        const activeClass = isAnyActive ? 'active' : '';

        return `
            <div class="field-item ${activeClass} merged-parent-field" data-merged="${parentType}">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-gray-800">
                            ${displayName}
                            <span class="badge badge-info badge-sm ms-2">حقل مدمج</span>
                        </div>
                        <div class="text-muted fs-7 mt-1">
                            <span class="badge badge-light-primary field-category-badge">${baseField.category}</span>
                            <small class="text-muted d-block mt-1">
                                ${description}
                            </small>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input merged-fields-switch"
                               type="checkbox"
                               id="merged_${parentType}_field"
                               data-merged-type="${parentType}"
                               data-merged-fields='${JSON.stringify(mergedFields)}'
                               ${checkedAttr}>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * إنشاء عنصر الحقل المدمج النشط
     */
    createMergedActiveFieldItem(type) {
        let displayName = '';

        if (type === 'guardian_name') {
            displayName = 'أسماء المعيل (الأربعة)';
        } else if (type === 'father') {
            displayName = 'معلومات الأب المتوفى';
        } else if (type === 'mother') {
            displayName = 'معلومات الأم المتوفية';
        }

        return `
            <div class="field-item active mb-3" data-merged="${type}">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-gray-800">${displayName}</div>
                        <div class="text-muted fs-7">
                            <span class="badge badge-light-primary field-category-badge">
                                ${type === 'guardian_name' ? 'معلومات المعيل التفصيلية' : 'معلومات الوالدين المتوفين'}
                            </span>
                            <span class="badge badge-info ms-2">حقل مدمج</span>
                        </div>
                    </div>
                    <div>
                        <span class="badge badge-success">مفعل</span>
                    </div>
                </div>
            </div>
        `;
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

        // قائمة الحقول المدمجة
        const mergedGuardianNameFields = [
            'field_data_first_name',
            'field_data_father_name',
            'field_data_grand_father_name',
            'field_data_family_name'
        ];

        const mergedFatherFields = [
            'field_father_id',
            'field_father_first_name',
            'field_father_death_date',
            'field_father_death_reason'
        ];

        const mergedMotherFields = [
            'field_mother_id',
            'field_mother_first_name',
            'field_mother_death_date',
            'field_mother_death_reason'
        ];

        // قائمة الحقول المخفية
        const hiddenFields = [
            'field_re_guardian_name',
            'field_re_guardian_phone',
            'field_re_guardian_id',
            'field_family_members_count'
        ];

        const activeFields = this.fieldsData.filter(f =>
            f.active && !hiddenFields.includes(f.db_column)
        );

        if (activeFields.length === 0) {
            activeFieldsHtml = '<div class="text-center py-10"><p class="text-muted">لا توجد حقول مفعلة</p></div>';
        } else {
            let guardianNameAdded = false;
            let fatherAdded = false;
            let motherAdded = false;

            activeFields.forEach(field => {
                // التحقق من حقول أسماء المعيل
                if (mergedGuardianNameFields.includes(field.db_column)) {
                    if (!guardianNameAdded) {
                        activeFieldsHtml += this.createMergedActiveFieldItem('guardian_name');
                        guardianNameAdded = true;
                    }
                    return;
                }

                // التحقق من حقول الأب المتوفي
                if (mergedFatherFields.includes(field.db_column)) {
                    if (!fatherAdded) {
                        activeFieldsHtml += this.createMergedActiveFieldItem('father');
                        fatherAdded = true;
                    }
                    return;
                }

                // التحقق من حقول الأم المتوفية
                if (mergedMotherFields.includes(field.db_column)) {
                    if (!motherAdded) {
                        activeFieldsHtml += this.createMergedActiveFieldItem('mother');
                        motherAdded = true;
                    }
                    return;
                }

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
                fields: activeFieldsDbColumns,
                compress_attachments_images: $('#compress_attachments_images_toggle').is(':checked')
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

    /**
     * ربط أحداث التبويبات
     */
    attachTabEventListeners() {
        const self = this;

        // عند التبديل بين التبويبات - سماع لكل من button و a
        $('[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            const targetTab = $(e.target).attr('href') || $(e.target).attr('data-bs-target');

            console.log('🔄 ==== تبديل التاب ====');
            console.log('🔄 التاب الجديد:', targetTab);
            console.log('🔄 نوع العنصر:', e.target.tagName);

            if (targetTab === '#fields_tab') {
                console.log('📋 التبديل إلى تاب الحقول');
                self.currentTab = 'fields';
                $('#saveFieldsBtnFooter').show();
                $('#saveDocumentsBtnFooter').hide();
            } else if (targetTab === '#documents_tab') {
                console.log('📄 ==== التبديل إلى تاب الوثائق ====');
                self.currentTab = 'documents';
                $('#saveFieldsBtnFooter').hide();
                $('#saveDocumentsBtnFooter').hide();

                // استدعاء النظام الجديد
                if (self.currentSponsorId) {
                    console.log('✅ معرف الجمعية:', self.currentSponsorId);
                    console.log('🔍 فحص دالة loadDocuments:', typeof loadDocuments);

                    if (typeof loadDocuments === 'function') {
                        console.log('✅ دالة loadDocuments موجودة - استدعاء الآن');
                        loadDocuments(self.currentSponsorId);
                    } else {
                        console.error('❌ دالة loadDocuments غير موجودة!');
                        console.error('❌ تأكد من تحميل documents-management-new.js قبل sponsor-fields-management.js');
                        console.error('❌ window.loadDocuments:', typeof window.loadDocuments);
                    }
                } else {
                    console.error('❌ معرف الجمعية غير محدد!');
                    console.error('❌ self.currentSponsorId:', self.currentSponsorId);
                }
            }
        });

        // تم حذف جميع event listeners القديمة للوثائق
        // النظام الجديد يدير كل شيء في documents-management-new.js
    }

    // تم حذف جميع الوظائف القديمة للوثائق
    // النظام الجديد موجود في documents-management-new.js

    /**
     * تبديل حالة Google Drive للجمعية
     */
    toggleGoogleDrive(sponsorId, enable, buttonElement) {
        const self = this;
        const action = enable ? 'تفعيل' : 'إيقاف';

        Swal.fire({
            title: `هل أنت متأكد من ${action} رفع PDF إلى Google Drive؟`,
            text: enable ? 'سيتم رفع تقارير PDF تلقائياً إلى Google Drive' : 'سيتم إيقاف رفع التقارير إلى Google Drive',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، متأكد',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: enable ? '#50cd89' : '#f1416c'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/admin/sponsors/${sponsorId}/toggle-google-drive`,
                    method: 'POST',
                    data: {
                        enabled: enable ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم بنجاح!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });

                            // تحديث حالة الزر بالتصميم الجديد
                            buttonElement.data('enabled', enable ? 1 : 0);
                            buttonElement.attr('data-enabled', enable ? 1 : 0);

                            if (enable) {
                                // تفعيل: زر أخضر مع badge "مفعل"
                                buttonElement.removeClass('btn-light-danger').addClass('btn-success');
                                buttonElement.find('i').removeClass('fa-cloud-slash').addClass('fa-cloud-arrow-up');
                                buttonElement.find('.badge').removeClass('badge-light-secondary').addClass('badge-light-success').text('مفعل');
                            } else {
                                // تعطيل: زر رمادي مع badge "معطل"
                                buttonElement.removeClass('btn-success').addClass('btn-light-danger');
                                buttonElement.find('i').removeClass('fa-cloud-arrow-up').addClass('fa-cloud-slash');
                                buttonElement.find('.badge').removeClass('badge-light-success').addClass('badge-light-secondary').text('معطل');
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ!',
                                text: response.message || 'حدث خطأ أثناء التحديث'
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('خطأ في تبديل Google Drive:', xhr);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ!',
                            text: 'حدث خطأ أثناء الاتصال بالخادم'
                        });
                    }
                });
            }
        });
    }
}

// تهيئة المدير عند تحميل الصفحة
$(document).ready(function() {
    if ($('#sponsors_fields_table').length) {
        window.sponsorFieldsManager = new SponsorFieldsManager();
    }
});
