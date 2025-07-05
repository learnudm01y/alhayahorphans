<div class="tab-pane fade" id="family-members" role="tabpanel" aria-labelledby="family-members-tab">
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">بيانات أفراد الأسرة</h5>
                    </div>
                    <div id="familyMembersContainer">
                        <!-- نموذج إضافة فرد (مخفي كقالب فقط) -->
                        <div class="family-member-form border rounded p-3 mb-3 d-none" data-member-index="template" id="familyMemberTemplate" data-upload-zone="template">
                            <div class="row g-3">
                                @include('user.generalRegistration.component.familyMemberFields', ['idx' => 'template', 'file_id_number' => $file_id_number ?? '', 'health_status' => $health_status, 'documentTypes' => $documentTypes])
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="addFamilyMember">
                        <i class="fas fa-plus me-2"></i>إضافة فرد
                    </button>
                </div> <!-- نهاية card-body -->
                <div class="mt-4 text-end">
                    <button type="button" class="btn btn-success px-5 py-2 fs-5" id="goToReviewTabBtn">
                        التالي <i class="fas fa-arrow-left ms-2"></i>
                    </button>
                </div>
                <div id="didding" style="padding-bottom: 80px;"></div>

                <!-- سكريبتات التحقق من البيانات -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const reviewBtn = document.getElementById('goToReviewTabBtn');
                        if (reviewBtn) {
                            reviewBtn.addEventListener('click', function(e) {
                                let invalidField = null;
                                let invalidLabel = '';
                                // تحقق فقط من النماذج الظاهرة
                                document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                                    if (invalidField) return;
                                    const requiredFields = [
                                        { selector: 'input[name$="[first_name]"]', label: 'الاسم الأول' },
                                        { selector: 'input[name$="[last_name]"]', label: 'اسم العائلة' },
                                        { selector: 'input[name$="[person_id]"]', label: 'رقم هوية اليتيم' },
                                        { selector: 'input[name$="[person_birth_date]"]', label: 'تاريخ الميلاد' },
                                        { selector: 'select[name$="[person_gender]"]', label: 'الجنس' },
                                    ];
                                    for (const field of requiredFields) {
                                        const el = form.querySelector(field.selector);
                                        if (el && !el.value) {
                                            invalidField = el;
                                            invalidLabel = field.label;
                                            break;
                                        }
                                    }
                                    // تحقق من رفع ملف أو اختيار نوع الوثيقة
                                    if (!invalidField) {
                                        // التحقق من وجود وثائق معالجة للعضو
                                        const uploadZone = form.querySelector('[data-upload-zone]');
                                        const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : null;

                                        let hasProcessedDocument = false;

                                        // التحقق من window.allDocs أولاً
                                        if (window.allDocs && window.allDocs instanceof Map && personKey) {
                                            const memberDocs = window.allDocs.get(personKey);
                                            hasProcessedDocument = memberDocs && memberDocs.length > 0;
                                        }

                                        // التحقق من منطقة المعاينة كبديل
                                        if (!hasProcessedDocument) {
                                            const preview = form.querySelector('.mainDocumentPreview');
                                            const hasVisibleDocument = preview && preview.style.display !== 'none' &&
                                                                     preview.innerHTML.trim() !== '' &&
                                                                     preview.innerHTML.includes('attachment-card');
                                            hasProcessedDocument = hasVisibleDocument;
                                        }

                                        // إذا لم توجد وثائق معالجة، تحقق من حالة الإدخال الحالية
                                        if (!hasProcessedDocument) {
                                            const docType = form.querySelector('.mainDocumentTypeSelect');
                                            const fileInput = form.querySelector('.mainDocumentFileInput');
                                            const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                                            if (docType && !docType.value && !hasFile) {
                                                invalidField = docType;
                                                invalidLabel = 'نوع الوثيقة أو رفع الملف';
                                            }
                                        }
                                    }
                                });
                                if (invalidField) {
                                    e.preventDefault();
                                    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'تنبيه',
                                        text: `يرجى إدخال ${invalidLabel} لكل فرد من أفراد الأسرة قبل المتابعة!`,
                                        confirmButtonText: 'حسنًا'
                                    });
                                    if (invalidField.focus) invalidField.focus();
                                    return;
                                }
                                // ...existing code for tab navigation...
                                document.getElementById('review-tab')?.click();
                            });
                        }
                    });
                </script>

                <!-- سكريبتات منع التنقل -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // منع التنقل بين التبويبات إلا بعد تحقق شروط validation لأفراد الأسرة
                        const navLinks = document.querySelectorAll('#formTabs .nav-link');
                        navLinks.forEach(function(link) {
                            link.addEventListener('click', function(e) {
                                // إذا لم تكن في تبويب أفراد الأسرة، لا تتحقق
                                const activeTab = document.querySelector('.tab-pane.active');
                                if (!activeTab || activeTab.id !== 'family-members') return;
                                // تحقق من جميع أفراد الأسرة
                                let invalidField = null;
                                let invalidLabel = '';
                                document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                                    if (invalidField) return;
                                    const requiredFields = [{
                                            selector: 'input[name$="[first_name]"]',
                                            label: 'الاسم الأول'
                                        },
                                        {
                                            selector: 'input[name$="[last_name]"]',
                                            label: 'اسم العائلة'
                                        },
                                        {
                                            selector: 'input[name$="[person_id]"]',
                                            label: 'رقم هوية اليتيم'
                                        },
                                        {
                                            selector: 'input[name$="[person_birth_date]"]',
                                            label: 'تاريخ الميلاد'
                                        },
                                        {
                                            selector: 'select[name$="[person_gender]"]',
                                            label: 'الجنس'
                                        },
                                    ];
                                    for (const field of requiredFields) {
                                        const el = form.querySelector(field.selector);
                                        if (el && !el.value) {
                                            invalidField = el;
                                            invalidLabel = field.label;
                                            break;
                                        }
                                    }
                                    if (!invalidField) {
                                        // التحقق من وجود وثائق معالجة للعضو
                                        const uploadZone = form.querySelector('[data-upload-zone]');
                                        const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : null;

                                        let hasProcessedDocument = false;

                                        // التحقق من window.allDocs أولاً
                                        if (window.allDocs && window.allDocs instanceof Map && personKey) {
                                            const memberDocs = window.allDocs.get(personKey);
                                            hasProcessedDocument = memberDocs && memberDocs.length > 0;
                                        }

                                        // التحقق من منطقة المعاينة كبديل
                                        if (!hasProcessedDocument) {
                                            const preview = form.querySelector('.mainDocumentPreview');
                                            const hasVisibleDocument = preview && preview.style.display !== 'none' &&
                                                                     preview.innerHTML.trim() !== '' &&
                                                                     preview.innerHTML.includes('attachment-card');
                                            hasProcessedDocument = hasVisibleDocument;
                                        }

                                        // إذا لم توجد وثائق معالجة، تحقق من حالة الإدخال الحالية
                                        if (!hasProcessedDocument) {
                                            const docType = form.querySelector('.mainDocumentTypeSelect');
                                            const fileInput = form.querySelector('.mainDocumentFileInput');
                                            const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                                            if (docType && !docType.value && !hasFile) {
                                                invalidField = docType;
                                                invalidLabel = 'نوع الوثيقة أو رفع الملف';
                                            }
                                        }
                                    }
                                });
                                if (invalidField) {
                                    e.preventDefault();
                                    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'تنبيه',
                                        text: `يرجى إدخال ${invalidLabel} لكل فرد من أفراد الأسرة قبل المتابعة!`,
                                        confirmButtonText: 'حسنًا'
                                    });
                                    if (invalidField.focus) invalidField.focus();
                                    return false;
                                }
                            });
                        });
                    });
                </script>

                <!-- سكريبت عام لمنع التنقل -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // منع التنقل بين التبويبات إلا بعد تحقق شروط validation لكل بوابة
                        const navLinks = document.querySelectorAll('#formTabs .nav-link');
                        navLinks.forEach(function(link) {
                            link.addEventListener('click', function(e) {
                                const activeTab = document.querySelector('.tab-pane.active');
                                if (!activeTab) return;
                                let invalidField = null;
                                let invalidLabel = '';
                                // بوابة أفراد الأسرة
                                if (activeTab.id === 'family-members') {
                                    document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                                        if (invalidField) return;
                                        const requiredFields = [{
                                                selector: 'input[name$="[first_name]"]',
                                                label: 'الاسم الأول'
                                            },
                                            {
                                                selector: 'input[name$="[last_name]"]',
                                                label: 'اسم العائلة'
                                            },
                                            {
                                                selector: 'input[name$="[person_id]"]',
                                                label: 'رقم هوية اليتيم'
                                            },
                                            {
                                                selector: 'input[name$="[person_birth_date]"]',
                                                label: 'تاريخ الميلاد'
                                            },
                                            {
                                                selector: 'select[name$="[person_gender]"]',
                                                label: 'الجنس'
                                            },
                                        ];
                                        for (const field of requiredFields) {
                                            const el = form.querySelector(field.selector);
                                            if (el && !el.value) {
                                                invalidField = el;
                                                invalidLabel = field.label;
                                                break;
                                            }
                                        }
                                        if (!invalidField) {
                                            // التحقق من وجود وثائق معالجة للعضو
                                            const uploadZone = form.querySelector('[data-upload-zone]');
                                            const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : null;

                                            let hasProcessedDocument = false;

                                            // التحقق من window.allDocs أولاً
                                            if (window.allDocs && window.allDocs instanceof Map && personKey) {
                                                const memberDocs = window.allDocs.get(personKey);
                                                hasProcessedDocument = memberDocs && memberDocs.length > 0;
                                            }

                                            // التحقق من منطقة المعاينة كبديل
                                            if (!hasProcessedDocument) {
                                                const preview = form.querySelector('.mainDocumentPreview');
                                                const hasVisibleDocument = preview && preview.style.display !== 'none' &&
                                                                         preview.innerHTML.trim() !== '' &&
                                                                         preview.innerHTML.includes('attachment-card');
                                                hasProcessedDocument = hasVisibleDocument;
                                            }

                                            // إذا لم توجد وثائق معالجة، تحقق من حالة الإدخال الحالية
                                            if (!hasProcessedDocument) {
                                                const docType = form.querySelector('.mainDocumentTypeSelect');
                                                const fileInput = form.querySelector('.mainDocumentFileInput');
                                                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                                                if (docType && !docType.value && !hasFile) {
                                                    invalidField = docType;
                                                    invalidLabel = 'نوع الوثيقة أو رفع الملف';
                                                }
                                            }
                                        }
                                    });
                                }
                                if (invalidField) {
                                    e.preventDefault();
                                    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'تنبيه',
                                        text: `يرجى إدخال ${invalidLabel} قبل المتابعة!`,
                                        confirmButtonText: 'حسنًا'
                                    });
                                    if (invalidField.focus) invalidField.focus();
                                    return false;
                                }
                            });
                        });
                    });
                </script>
            </div>
        </div>
    </div>
</div>

@push('scriptsCodeUserRegistration')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // تهيئة window.allDocs مع دعم بوابة أفراد الأسرة
        setTimeout(function() {
            if (typeof window.allDocs !== 'object' || !window.allDocs) {
                window.allDocs = new Map();
                console.log(`🔧 [familyMember] تم إنشاء window.allDocs جديد`);
            }

            // التحقق من صحة structure
            if (!(window.allDocs instanceof Map)) {
                console.warn(`⚠️ [familyMember] window.allDocs ليس Map، تحويل...`);
                const tempMap = new Map();
                if (typeof window.allDocs === 'object') {
                    Object.keys(window.allDocs).forEach(key => {
                        tempMap.set(key, window.allDocs[key]);
                    });
                }
                window.allDocs = tempMap;
            }

            console.log(`✅ [familyMember] تم تهيئة window.allDocs:`, {
                type: typeof window.allDocs,
                isMap: window.allDocs instanceof Map,
                keysCount: window.allDocs instanceof Map ? window.allDocs.size : 'غير صالح',
                keys: window.allDocs instanceof Map ? Array.from(window.allDocs.keys()) : 'غير صالح'
            });
        }, 0);

        // دالة إضافة فرد جديد
        function addFamilyMember() {
            const container = document.getElementById('familyMembersContainer');
            let forms = container.querySelectorAll('.family-member-form:not(.d-none)');
            let template = document.getElementById('familyMemberTemplate');
            let newIndex = forms.length;
            let clone = template.cloneNode(true);
            clone.classList.remove('d-none');
            clone.removeAttribute('id');
            clone.setAttribute('data-member-index', newIndex);

            // تحديث أسماء الحقول والفهارس
            clone.querySelectorAll('[name]').forEach(function(input) {
                input.name = input.name.replace(/family_members\[\d+\]/g, `family_members[${newIndex}]`);
                if (input.name.endsWith('[file_id]')) {
                    let fileIdInput = template.querySelector('[name$="[file_id]"]');
                    if (fileIdInput) input.value = fileIdInput.value;
                } else if (input.name.endsWith('[registration_id]')) {
                    let regIdInput = template.querySelector('[name$="[registration_id]"]');
                    if (regIdInput) {
                        input.value = regIdInput.value;
                        input.readOnly = true;
                        input.classList.add('bg-secondary', 'bg-opacity-10');
                    }
                } else if (input.type === 'text' || input.type === 'number' || input.type === 'date') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });

            // تحديث data-upload-zone
            const uploadZone = clone.querySelector('[data-upload-zone]');
            if (uploadZone) {
                uploadZone.setAttribute('data-upload-zone', `family_${newIndex}`);
            }

            // تحديث معرفات العناصر
            const docType = clone.querySelector('.mainDocumentTypeSelect');
            if (docType) docType.id = `mainDocumentTypeSelect_${newIndex}`;
            const fileInput = clone.querySelector('.mainDocumentFileInput');
            if (fileInput) fileInput.id = `mainDocumentFileInput_${newIndex}`;
            const preview = clone.querySelector('.mainDocumentPreview');
            if (preview) preview.id = `mainDocumentPreview_${newIndex}`;
            const names = clone.querySelector('.mainDocumentNames');
            if (names) names.id = `mainDocumentNames_${newIndex}`;

            // مسح المحتوى السابق
            clone.querySelectorAll('.mainDocumentPreview, .mainDocumentNames').forEach(div => div.innerHTML = '');

            // تهيئة window.allDocs
            if (window.allDocs && window.allDocs instanceof Map) {
                window.allDocs.set(`family_${newIndex}`, []);
            }

            // إضافة header وزر حذف
            let cardHeader = clone.querySelector('.card-header');
            if (!cardHeader) {
                cardHeader = document.createElement('div');
                cardHeader.className = 'card-header bg-gradient-primary text-dark py-3 d-flex justify-content-between align-items-center';
                cardHeader.innerHTML = `
                    <h5 class="card-title mb-0 d-flex align-items-center">
                        <i class="fas fa-user fs-4 me-2"></i>
                        بيانات فرد الأسرة
                    </h5>
                    <button type="button" class="btn btn-danger btn-sm delete-member">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                clone.insertBefore(cardHeader, clone.firstChild);
            }

            cardHeader.querySelector('.delete-member').onclick = function(e) {
                e.preventDefault();

                // الحصول على personKey للفرد المراد حذفه
                const uploadZone = clone.querySelector('[data-upload-zone]');
                const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : `family_${newIndex}`;

                console.log(`🗑️ [delete-member] محاولة حذف فرد العائلة:`, {
                    memberIndex: newIndex,
                    personKey: personKey,
                    allDocsKeys: window.allDocs instanceof Map ? Array.from(window.allDocs.keys()) : 'غير صالح'
                });

                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'سيتم حذف هذا الفرد من القائمة مع جميع وثائقه',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log(`✅ [delete-member] تأكيد حذف فرد العائلة`);

                        // حذف جميع وثائق هذا الفرد من window.allDocs
                        if (window.allDocs && window.allDocs instanceof Map) {
                            if (window.allDocs.has(personKey)) {
                                const deletedDocs = window.allDocs.get(personKey);
                                window.allDocs.delete(personKey);
                                console.log(`🗑️ [delete-member] تم حذف جميع وثائق الفرد من allDocs:`, {
                                    personKey: personKey,
                                    deletedDocsCount: deletedDocs ? deletedDocs.length : 0,
                                    remainingKeys: Array.from(window.allDocs.keys())
                                });
                            } else {
                                console.log(`ℹ️ [delete-member] لم يتم العثور على وثائق للفرد في allDocs:`, {
                                    personKey: personKey,
                                    availableKeys: Array.from(window.allDocs.keys())
                                });
                            }
                        }

                        // تأثير الحذف البصري
                        clone.style.opacity = '0';
                        clone.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            clone.remove();
                            reindexFamilyMembers();

                            // رسالة نجاح
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف',
                                text: 'تم حذف فرد العائلة وجميع وثائقه بنجاح',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }, 300);
                    }
                });
            };

            container.appendChild(clone);
            console.log(`تم توليد نموذج ${newIndex}: data-upload-zone = family_${newIndex}`);
            reindexFamilyMembers();
        }

        // دالة إعادة الفهرسة مع تحديث window.allDocs
        function reindexFamilyMembers() {
            const forms = document.querySelectorAll('#familyMembersContainer .family-member-form:not(.d-none)');

            console.log(`🔄 [reindexFamilyMembers] بدء إعادة الفهرسة:`, {
                formsCount: forms.length,
                allDocsKeys: window.allDocs instanceof Map ? Array.from(window.allDocs.keys()) : 'غير صالح'
            });

            // إنشاء خريطة مؤقتة لتحديث window.allDocs
            const newAllDocs = new Map();

            forms.forEach(function(form, idx) {
                const oldUploadZone = form.querySelector('[data-upload-zone]');
                const oldPersonKey = oldUploadZone ? oldUploadZone.getAttribute('data-upload-zone') : null;
                const newPersonKey = `family_${idx}`;

                console.log(`🔄 [reindexFamilyMembers] معالجة النموذج ${idx}:`, {
                    oldPersonKey: oldPersonKey,
                    newPersonKey: newPersonKey
                });

                form.setAttribute('data-member-index', idx);

                // تحديث أسماء الحقول
                form.querySelectorAll('[name]').forEach(function(input) {
                    input.name = input.name.replace(/family_members\[\d+\]/g, `family_members[${idx}]`);
                });

                // تحديث data-upload-zone
                if (oldUploadZone) {
                    oldUploadZone.setAttribute('data-upload-zone', newPersonKey);
                }

                // تحديث معرفات العناصر
                const docType = form.querySelector('.mainDocumentTypeSelect');
                if (docType) docType.id = `mainDocumentTypeSelect_${idx}`;
                const fileInput = form.querySelector('.mainDocumentFileInput');
                if (fileInput) fileInput.id = `mainDocumentFileInput_${idx}`;
                const preview = form.querySelector('.mainDocumentPreview');
                if (preview) preview.id = `mainDocumentPreview_${idx}`;
                const names = form.querySelector('.mainDocumentNames');
                if (names) names.id = `mainDocumentNames_${idx}`;

                // تحديث window.allDocs
                if (window.allDocs && window.allDocs instanceof Map && oldPersonKey && oldPersonKey !== newPersonKey) {
                    if (window.allDocs.has(oldPersonKey)) {
                        const docs = window.allDocs.get(oldPersonKey);

                        // تحديث personKey في كل وثيقة
                        if (docs && docs.length > 0) {
                            docs.forEach(doc => {
                                doc.personKey = newPersonKey;
                            });
                            newAllDocs.set(newPersonKey, docs);

                            console.log(`🔄 [reindexFamilyMembers] تم تحديث مفتاح الوثائق:`, {
                                from: oldPersonKey,
                                to: newPersonKey,
                                docsCount: docs.length
                            });
                        }
                    }
                } else if (window.allDocs && window.allDocs instanceof Map && oldPersonKey === newPersonKey) {
                    // إذا كان المفتاح لم يتغير، انسخ الوثائق كما هي
                    if (window.allDocs.has(oldPersonKey)) {
                        newAllDocs.set(newPersonKey, window.allDocs.get(oldPersonKey));
                    }
                }
            });

            // تحديث window.allDocs بالخريطة الجديدة
            if (window.allDocs && window.allDocs instanceof Map) {
                window.allDocs.clear();
                newAllDocs.forEach((docs, key) => {
                    window.allDocs.set(key, docs);
                });

                console.log(`✅ [reindexFamilyMembers] تم تحديث window.allDocs:`, {
                    newKeys: Array.from(window.allDocs.keys()),
                    totalDocs: Array.from(window.allDocs.values()).reduce((sum, docs) => sum + docs.length, 0)
                });
            }

            // إعادة ربط حساب العمر
            setTimeout(function() {
                document.querySelectorAll('.family-member-form:not(.d-none) input[name$="[person_birth_date]"]').forEach(function(input) {
                    // تم حذف الربط اليدوي هنا لأن سكريبت ageCalculating.blade.php يربط الحدث بشكل عام
                    // input.oninput = function() {
                    //     window.calculateAge(this);
                    // };
                });
            }, 100);

            // إعادة تفعيل معالجات رفع الملفات
            setTimeout(function() {
                if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
                    document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form, idx) {
                        window.setupDocumentUploadHandlersForMember(form, idx);
                    });
                }
            }, 150);
        }

        // دالة setupDocumentUploadHandlersForMember المركزية مع إصلاح مشكلة التعطيل
        window.setupDocumentUploadHandlersForMember = function(form, idx) {
            const fileInput = form.querySelector('.mainDocumentFileInput');
            const preview = form.querySelector('.mainDocumentPreview');
            const docTypeSelect = form.querySelector('.mainDocumentTypeSelect');

            if (!fileInput || !preview || !docTypeSelect) return;

            // منع التكرار
            if (form._familyUploadHandlersInitialized) return;
            form._familyUploadHandlersInitialized = true;

            let documents = [];
            let fileDialogOpen = false;

            // تحميل الوثائق الموجودة من window.allDocs
            const uploadZone = form.querySelector('[data-upload-zone]');
            const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : `family_${idx}`;
            if (window.allDocs && window.allDocs instanceof Map && window.allDocs.has(personKey)) {
                documents = [...window.allDocs.get(personKey)];
                console.log(`📋 [setupDocumentUploadHandlersForMember] تم تحميل الوثائق الموجودة:`, {
                    personKey: personKey,
                    documentsCount: documents.length,
                    documents: documents.map(doc => ({
                        docName: doc.docName,
                        type: doc.type,
                        typeText: doc.typeText
                    }))
                });

                // عرض الوثائق الموجودة
                setTimeout(() => renderDocuments(), 100);
            } else {
                console.log(`📝 [setupDocumentUploadHandlersForMember] بدء بوثائق فارغة للمنطقة: ${personKey}`);
            }

            // دالة مزامنة المصفوفة المحلية مع window.allDocs
            function syncWithAllDocs() {
                const personKey = form.querySelector('[data-upload-zone]')?.getAttribute('data-upload-zone') || `family_${idx}`;

                if (window.allDocs && window.allDocs instanceof Map) {
                    // تحديث window.allDocs بالوثائق المحلية
                    if (documents.length > 0) {
                        window.allDocs.set(personKey, [...documents]);
                        console.log(`🔄 [syncWithAllDocs] تم تحديث window.allDocs:`, {
                            personKey: personKey,
                            documentsCount: documents.length
                        });
                    } else {
                        // حذف المفتاح إذا لم تعد هناك وثائق
                        if (window.allDocs.has(personKey)) {
                            window.allDocs.delete(personKey);
                            console.log(`🗑️ [syncWithAllDocs] تم حذف المفتاح الفارغ: ${personKey}`);
                        }
                    }
                } else {
                    console.error(`❌ [syncWithAllDocs] window.allDocs غير صالح`);
                }
            }

            // دالة كشف نوع الجهاز
            function isMobileDevice() {
                return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
                    window.innerWidth <= 768 ||
                    ('ontouchstart' in window) ||
                    (navigator.maxTouchPoints > 0);
            }

            // دالة إعادة تهيئة حالة الرفع - محدثة مع نظام كشف الجهاز
            function resetUploadState() {
                fileDialogOpen = false;

                // التحقق من وجود fileInput والعقدة الأب قبل المحاولة
                if (!fileInput || !fileInput.parentNode) {
                    console.log(`⚠️ [resetUploadState] fileInput أو parentNode غير موجود، إنهاء العملية`);
                    return;
                }

                fileInput.value = '';

                // إزالة جميع المستمعات المؤقتة
                const newFileInput = fileInput.cloneNode(true);

                try {
                    fileInput.parentNode.replaceChild(newFileInput, fileInput);

                    // تحديث المرجع إلى العنصر الجديد
                    const updatedFileInput = form.querySelector('.mainDocumentFileInput');
                    if (updatedFileInput) {
                        // تطبيق تكوين الجهاز على input الجديد
                        if (window.DeviceImageCapture && typeof window.DeviceImageCapture.configureFileInputForDevice === 'function') {
                            const deviceInfo = window.DeviceImageCapture.detectDeviceType();
                            window.DeviceImageCapture.configureFileInputForDevice(updatedFileInput, deviceInfo);
                            console.log(`🔧 [resetUploadState] تم تطبيق تكوين الجهاز على input الجديد`);
                        }

                        // إعادة ربط المستمع الأساسي
                        setTimeout(() => {
                            setupFileInputHandler(updatedFileInput);
                        }, 100);
                    }
                } catch (error) {
                    console.error(`❌ [resetUploadState] خطأ في استبدال fileInput:`, error);

                    // إعادة تهيئة بديلة - فقط مسح القيمة وإعادة ربط المستمعات
                    try {
                        fileInput.value = '';

                        if (window.DeviceImageCapture && typeof window.DeviceImageCapture.configureFileInputForDevice === 'function') {
                            const deviceInfo = window.DeviceImageCapture.detectDeviceType();
                            window.DeviceImageCapture.configureFileInputForDevice(fileInput, deviceInfo);
                        }

                        console.log(`🔧 [resetUploadState] تم استخدام إعادة التهيئة البديلة`);
                    } catch (fallbackError) {
                        console.error(`❌ [resetUploadState] فشل في إعادة التهيئة البديلة:`, fallbackError);
                    }
                }

                console.log(`🔄 [resetUploadState] تم إعادة تهيئة حالة الرفع للمنطقة: ${idx}`);
            }

            // دالة التحقق من إمكانية الرفع
            function canUploadDocument(triggeredBy) {
                let idInput = form.querySelector('input[name^="family_members["][name$="[person_id]"]');

                if (triggeredBy === 'mousedown') {
                    if (!idInput || !idInput.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال رقم الهوية أولاً قبل رفع أي وثيقة.'
                        });
                        return false;
                    }
                    return true;
                }

                if (!docTypeSelect.value) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'يرجى اختيار نوع الوثيقة أولاً.'
                    });
                    return false;
                }

                if (!idInput || !idInput.value) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'يرجى إدخال رقم الهوية أولاً قبل رفع أي وثيقة.'
                    });
                    return false;
                }

                return true;
            }

            // دالة عرض حالة المعالجة مع spinner واحد فقط
            function showProcessingState(imageUrl) {
                preview.innerHTML = `
                    <div class="processing-container position-relative">
                        <div class="image-wrapper" style="position: relative; display: inline-block; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <img src="${imageUrl}" class="img-fluid processing-blur" style="max-width: 200px; max-height: 200px; filter: blur(2px); opacity: 0.8; transition: all 0.3s ease;">
                            <div class="processing-overlay position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(1px);">
                                <div class="spinner-border text-primary mb-3" role="status" style="width: 2.5rem; height: 2.5rem;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="processing-text text-primary fw-bold mb-2" style="font-size: 1.1rem;">
                                    <i class="fas fa-magic me-2" style="color: #6f42c1;"></i>
                                    جاري المعالجة...
                                </div>
                                <small class="text-muted text-center">سيتم فتح أداة القص تلقائياً</small>
                                <div class="progress mt-3" style="width: 80%; height: 4px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-gradient" role="progressbar" style="width: 100%; background: linear-gradient(45deg, #6f42c1, #007bff);"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <style>
                        .processing-container {
                            text-align: center;
                            padding: 20px;
                            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                            border-radius: 15px;
                            border: 2px solid #dee2e6;
                            margin: 10px 0;
                            animation: fadeInUp 0.4s ease-out;
                        }
                        @keyframes fadeInUp {
                            from { opacity: 0; transform: translateY(20px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                    </style>
                `;
                preview.style.display = 'block';
            }

            // دالة عرض المرفقات مع آلية حذف محسنة مطابقة للبوابات الأخرى
            function renderDocuments() {
                console.log(`🔍 [renderDocuments] بدء عرض المرفقات للمنطقة: ${idx}`, {
                    documentsCount: documents.length,
                    allDocsContent: window.allDocs instanceof Map ? Array.from(window.allDocs.entries()) : 'غير صالح'
                });

                preview.innerHTML = '';

                if (documents.length === 0) {
                    console.log(`📝 [renderDocuments] لا توجد مرفقات للعرض في المنطقة: ${idx}`);
                    preview.style.display = 'none';
                    return;
                }

                const cardsWrapper = document.createElement('div');
                cardsWrapper.className = 'd-flex flex-wrap gap-3 justify-content-start';
                cardsWrapper.style.marginTop = '15px';

                documents.forEach((doc, docIdx) => {
                    console.log(`🎨 [renderDocuments] عرض المرفق ${docIdx + 1}/${documents.length}:`, {
                        docName: doc.docName,
                        typeText: doc.typeText,
                        personId: doc.personId,
                        fileId: doc.fileId,
                        personKey: doc.personKey
                    });

                    const card = document.createElement('div');
                    card.className = 'attachment-card card border-0 shadow-sm';
                    card.style.cssText = `
                        width: 180px;
                        border-radius: 12px;
                        overflow: hidden;
                        transition: all 0.3s ease;
                        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                        border: 1px solid #e3e6f0 !important;
                    `;

                    // تأثير hover
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-5px)';
                        this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                    });

                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                    });

                    // Header للبطاقة
                    const cardHeader = document.createElement('div');
                    cardHeader.className = 'card-header bg-gradient-primary text-white p-2';
                    cardHeader.style.cssText = `
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        border: none;
                        font-size: 0.85rem;
                        font-weight: 600;
                    `;
                    cardHeader.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between">
                            <i class="fas fa-file-image me-1"></i>
                            <span class="text-truncate">${doc.typeText}</span>
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                    `;

                    const cardBody = document.createElement('div');
                    cardBody.className = 'card-body p-3 text-center';

                    // عرض الصورة أو أيقونة الملف
                    if (doc.file && doc.file.type && doc.file.type.startsWith('image/')) {
                        const imageContainer = document.createElement('div');
                        imageContainer.className = 'image-container position-relative';
                        imageContainer.style.cssText = `
                            border-radius: 8px;
                            overflow: hidden;
                            margin-bottom: 10px;
                            background: #f8f9fa;
                        `;

                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(doc.file);
                        img.style.cssText = `
                            width: 100%;
                            height: 120px;
                            object-fit: cover;
                            border-radius: 8px;
                            transition: transform 0.3s ease;
                        `;
                        img.className = 'document-image';

                        // تأثير zoom على الصورة
                        img.addEventListener('mouseenter', function() {
                            this.style.transform = 'scale(1.05)';
                        });

                        img.addEventListener('mouseleave', function() {
                            this.style.transform = 'scale(1)';
                        });

                        img.onload = function() {
                            URL.revokeObjectURL(img.src);
                        };

                        // شارة "معالج" على الصورة
                        const processBadge = document.createElement('div');
                        processBadge.className = 'position-absolute top-0 end-0 m-1';
                        processBadge.innerHTML = `
                            <span class="badge bg-success rounded-pill">
                                <i class="fas fa-check-circle me-1"></i>معالج
                            </span>
                        `;

                        imageContainer.appendChild(img);
                        imageContainer.appendChild(processBadge);
                        cardBody.appendChild(imageContainer);
                    } else if (doc.file && doc.file.name) {
                        // عرض أيقونة للملفات غير الصور
                        const fileIcon = document.createElement('div');
                        fileIcon.className = 'file-icon-container mb-3';
                        fileIcon.innerHTML = `
                            <div class="file-icon d-flex align-items-center justify-content-center" style="
                                width: 80px;
                                height: 80px;
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                border-radius: 12px;
                                margin: 0 auto;
                                color: white;
                                font-size: 2rem;
                            ">
                                <i class="fas fa-file-alt"></i>
                            </div>
                        `;
                        cardBody.appendChild(fileIcon);
                    }

                    // اسم الملف
                    const fileName = document.createElement('div');
                    fileName.className = 'file-name text-muted small mb-2 text-truncate';
                    fileName.style.fontWeight = '500';
                    fileName.textContent = doc.file.name || doc.docName;
                    fileName.title = doc.file.name || doc.docName; // tooltip
                    cardBody.appendChild(fileName);

                    // معلومات إضافية
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'file-info small text-muted mb-3';
                    const fileSize = doc.file.size ? (doc.file.size / 1024).toFixed(1) + ' KB' : 'غير معروف';
                    fileInfo.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <span><i class="fas fa-weight-hanging me-1"></i>${fileSize}</span>
                            <span><i class="fas fa-clock me-1"></i>الآن</span>
                        </div>
                    `;
                    cardBody.appendChild(fileInfo);

                    // زر حذف محسّن مطابق للبوابات الأخرى
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'btn btn-outline-danger btn-sm w-100';
                    deleteBtn.style.cssText = `
                        border-radius: 8px;
                        font-weight: 500;
                        transition: all 0.3s ease;
                    `;
                    deleteBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i>حذف';

                    deleteBtn.addEventListener('mouseenter', function() {
                        this.className = 'btn btn-danger btn-sm w-100';
                        this.innerHTML = '<i class="fas fa-trash-alt me-1"></i>تأكيد الحذف';
                    });

                    deleteBtn.addEventListener('mouseleave', function() {
                        this.className = 'btn btn-outline-danger btn-sm w-100';
                        this.innerHTML = '<i class="fas fa-trash-alt me-1"></i>حذف';
                    });

                    deleteBtn.onclick = function() {
                        console.log(`🗑️ [deleteBtn] محاولة حذف المرفق:`, {
                            docIdx: docIdx,
                            docName: doc.docName,
                            type: doc.type,
                            fileId: doc.fileId,
                            personId: doc.personId,
                            personKey: doc.personKey,
                            currentDocuments: documents.length,
                            allDocsKeys: window.allDocs instanceof Map ? Array.from(window.allDocs.keys()) : 'غير صالح'
                        });

                        Swal.fire({
                            title: 'تأكيد الحذف',
                            text: 'هل أنت متأكد من حذف هذه الوثيقة؟',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i>نعم، احذف',
                            cancelButtonText: '<i class="fas fa-times me-1"></i>إلغاء',
                            backdrop: true,
                            customClass: {
                                popup: 'animated fadeInDown'
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                console.log(`✅ [deleteBtn] تأكيد الحذف للمرفق:`, {
                                    docIdx: docIdx,
                                    docName: doc.docName,
                                    beforeDeleteCount: documents.length
                                });

                                // تأثير انحلال جميل
                                card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                                card.style.transform = 'scale(0.8) rotateZ(5deg)';
                                card.style.opacity = '0';
                                card.style.filter = 'blur(3px)';

                                setTimeout(function() {
                                    console.log(`🔄 [deleteBtn] بدء عملية الحذف الفعلية للمرفق:`, {
                                        docIdx: docIdx,
                                        docName: doc.docName,
                                        personKey: doc.personKey || `family_${idx}`
                                    });

                                    // حذف من مصفوفة documents المحلية أولاً
                                    const deletedDoc = documents.splice(docIdx, 1)[0];
                                    console.log(`📤 [deleteBtn] تم الحذف من documents المحلية:`, {
                                        deletedDoc: deletedDoc,
                                        remainingCount: documents.length
                                    });

                                    // حذف من window.allDocs مع تحسين آلية البحث والحذف
                                    if (window.allDocs && window.allDocs instanceof Map) {
                                        // تحديد personKey بأولويات متعددة
                                        const possibleKeys = [
                                            doc.personKey,
                                            `family_${idx}`,
                                            doc.personId,
                                            form.querySelector('[data-upload-zone]')?.getAttribute('data-upload-zone')
                                        ].filter(key => key); // إزالة القيم الفارغة

                                        console.log(`🔍 [deleteBtn] البحث في window.allDocs عن مفاتيح محتملة:`, {
                                            possibleKeys: possibleKeys,
                                            targetDoc: {
                                                docName: doc.docName,
                                                type: doc.type,
                                                fileId: doc.fileId,
                                                personId: doc.personId
                                            },
                                            allDocsKeys: Array.from(window.allDocs.keys())
                                        });

                                        let deletedFromAllDocs = false;
                                        let foundPersonKey = null;

                                        // البحث في جميع المفاتيح المحتملة
                                        for (const personKey of possibleKeys) {
                                            if (window.allDocs.has(personKey)) {
                                                const arr = window.allDocs.get(personKey);
                                                console.log(`📋 [deleteBtn] فحص مصفوفة في allDocs:`, {
                                                    personKey: personKey,
                                                    arrayLength: arr.length,
                                                    arrayContent: arr.map(item => ({
                                                        docName: item.docName || 'غير محدد',
                                                        type: item.type || 'غير محدد',
                                                        fileId: item.fileId || 'غير محدد'
                                                    }))
                                                });

                                                // البحث والحذف مع معايير متعددة للمطابقة
                                                for (let i = arr.length - 1; i >= 0; i--) {
                                                    const arrDoc = arr[i];

                                                    // معايير مطابقة متعددة
                                                    const isMatchByName = arrDoc.docName === doc.docName;
                                                    const isMatchByTypeAndFile = arrDoc.type === doc.type &&
                                                                                arrDoc.fileId === doc.fileId;
                                                    const isMatchByPersonId = arrDoc.personId === doc.personId &&
                                                                             arrDoc.type === doc.type;

                                                    console.log(`🔍 [deleteBtn] فحص العنصر ${i} في ${personKey}:`, {
                                                        arrDoc: {
                                                            docName: arrDoc.docName,
                                                            type: arrDoc.type,
                                                            fileId: arrDoc.fileId,
                                                            personId: arrDoc.personId
                                                        },
                                                        targetDoc: {
                                                            docName: doc.docName,
                                                            type: doc.type,
                                                            fileId: doc.fileId,
                                                            personId: doc.personId
                                                        },
                                                        matches: {
                                                            byName: isMatchByName,
                                                            byTypeAndFile: isMatchByTypeAndFile,
                                                            byPersonId: isMatchByPersonId
                                                        }
                                                    });

                                                    // إذا تطابق أي من المعايير
                                                    if (isMatchByName || isMatchByTypeAndFile || isMatchByPersonId) {
                                                        const removedItem = arr.splice(i, 1)[0];
                                                        deletedFromAllDocs = true;
                                                        foundPersonKey = personKey;

                                                        console.log(`✅ [deleteBtn] تم حذف العنصر من allDocs:`, {
                                                            personKey: personKey,
                                                            index: i,
                                                            removedItem: {
                                                                docName: removedItem.docName,
                                                                type: removedItem.type,
                                                                fileId: removedItem.fileId
                                                            },
                                                            remainingArrayLength: arr.length
                                                        });
                                                        break;
                                                    }
                                                }

                                                // إذا تم العثور على الوثيقة وحذفها، اخرج من الحلقة
                                                if (deletedFromAllDocs) break;
                                            }
                                        }

                                        // تنظيف المفتاح الفارغ
                                        if (deletedFromAllDocs && foundPersonKey) {
                                            const arr = window.allDocs.get(foundPersonKey);
                                            if (arr && arr.length === 0) {
                                                window.allDocs.delete(foundPersonKey);
                                                console.log(`🗑️ [deleteBtn] تم حذف المفتاح الفارغ من allDocs: ${foundPersonKey}`);
                                            }
                                        }

                                        // تسجيل تحذير إذا لم يتم العثور على الوثيقة
                                        if (!deletedFromAllDocs) {
                                            console.warn(`⚠️ [deleteBtn] لم يتم العثور على الوثيقة في allDocs للحذف:`, {
                                                searchedKeys: possibleKeys,
                                                targetDocument: {
                                                    docName: doc.docName,
                                                    type: doc.type,
                                                    fileId: doc.fileId,
                                                    personId: doc.personId
                                                },
                                                allDocsContent: Array.from(window.allDocs.entries()).map(([key, arr]) => ({
                                                    key: key,
                                                    documents: arr.map(item => ({
                                                        docName: item.docName,
                                                        type: item.type,
                                                        fileId: item.fileId
                                                    }))
                                                }))
                                            });
                                        }
                                    } else {
                                        console.error(`❌ [deleteBtn] window.allDocs غير صالح:`, {
                                            type: typeof window.allDocs,
                                            isMap: window.allDocs instanceof Map,
                                            value: window.allDocs
                                        });
                                    }

                                    // إعادة العرض مع المزامنة
                                    console.log(`🎨 [deleteBtn] إعادة عرض المرفقات بعد الحذف...`);
                                    renderDocuments();
                                    syncWithAllDocs(); // مزامنة مع window.allDocs

                                    // رسالة نجاح
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'تم الحذف',
                                        text: 'تم حذف الوثيقة بنجاح',
                                        timer: 2000,
                                        showConfirmButton: false,
                                        customClass: {
                                            popup: 'animated fadeInUp'
                                        }
                                    });

                                    console.log(`✅ [deleteBtn] اكتملت عملية الحذف بنجاح للمرفق:`, {
                                        docName: doc.docName,
                                        finalDocumentsCount: documents.length,
                                        finalAllDocsState: window.allDocs instanceof Map ? Array.from(window.allDocs.entries()) : 'غير صالح'
                                    });
                                }, 600);
                            } else {
                                console.log(`❌ [deleteBtn] تم إلغاء عملية الحذف للمرفق: ${doc.docName}`);
                            }
                        });
                    };

                    cardBody.appendChild(deleteBtn);
                    card.appendChild(cardHeader);
                    card.appendChild(cardBody);
                    cardsWrapper.appendChild(card);
                });

                preview.appendChild(cardsWrapper);
                preview.style.display = 'block';

                console.log(`✅ [renderDocuments] اكتمل عرض ${documents.length} مرفق للمنطقة: ${idx}`);
            }

            // معالج mousedown للتحقق المبكر مع إعادة التهيئة
            docTypeSelect.addEventListener('mousedown', function(e) {
                if (!canUploadDocument('mousedown')) {
                    e.preventDefault();
                    resetUploadState(); // إعادة تهيئة حتى لو فشل التحقق
                    return false;
                }

                if (fileDialogOpen) {
                    e.preventDefault();
                    resetUploadState(); // إعادة تهيئة إذا كان الحوار مفتوحاً
                    return false;
                }
            });

            // معالج تغيير نوع الوثيقة مع إعادة التهيئة المحسنة
            docTypeSelect.addEventListener('change', function() {
                console.log(`📋 [docTypeSelect] تغيير نوع الوثيقة إلى: ${this.value}`);

                if (!canUploadDocument('change')) {
                    this.value = '';
                    resetUploadState();
                    return;
                }

                if (fileDialogOpen) {
                    console.log(`⚠️ [docTypeSelect] حوار الملفات مفتوح بالفعل، إعادة تهيئة...`);
                    resetUploadState();
                    return;
                }

                fileDialogOpen = true;
                fileInput.value = '';

                // التأكد من تطبيق تكوين الجهاز قبل فتح الحوار
                if (window.DeviceImageCapture && typeof window.DeviceImageCapture.configureFileInputForDevice === 'function') {
                    const deviceInfo = window.DeviceImageCapture.detectDeviceType();
                    window.DeviceImageCapture.configureFileInputForDevice(fileInput, deviceInfo);
                    console.log(`🔧 [docTypeSelect] تم تطبيق تكوين الجهاز قبل فتح الحوار`);
                }

                // تحديد نوع الملفات المقبولة حسب الجهاز
                if (isMobileDevice()) {
                    // للجوال: توجيه إلى معرض الصور فقط
                    fileInput.accept = 'image/*';
                    fileInput.capture = 'environment'; // استخدام الكاميرا الخلفية
                    console.log(`📱 [docTypeSelect] تم تفعيل وضع الجوال - معرض الصور والكاميرا`);
                } else {
                    // للكمبيوتر: الصور والـ PDF
                    fileInput.accept = 'image/*,.pdf';
                    fileInput.removeAttribute('capture');
                    console.log(`💻 [docTypeSelect] تم تفعيل وضع الكمبيوتر - الصور والـ PDF`);
                }

                // مستمع مؤقت لإعادة التهيئة عند إلغاء اختيار الملف
                const resetOnCancel = () => {
                    setTimeout(() => {
                        if (!fileInput.files || fileInput.files.length === 0) {
                            console.log(`❌ [docTypeSelect] تم إلغاء اختيار الملف، إعادة تهيئة...`);
                            resetUploadState();
                        }
                    }, 500);
                };

                // ربط المستمعات المؤقتة
                fileInput.addEventListener('change', resetOnCancel, { once: true });
                fileInput.addEventListener('cancel', resetOnCancel, { once: true });
                window.addEventListener('focus', resetOnCancel, { once: true });

                // فتح حوار اختيار الملف
                try {
                    fileInput.click();
                    console.log(`🎯 [docTypeSelect] تم فتح حوار اختيار الملف`);
                } catch (error) {
                    console.error(`❌ [docTypeSelect] خطأ في فتح حوار الملف:`, error);
                    resetUploadState();
                }
            });

            // دالة إعداد معالج الملف مع إعادة التهيئة
            function setupFileInputHandler(input) {
                // التحقق من صحة العنصر
                if (!input || !input.parentNode) {
                    console.warn(`⚠️ [setupFileInputHandler] عنصر الإدخال غير صالح، إنهاء العملية`);
                    return;
                }

                input.addEventListener('change', function() {
                    console.log(`📁 [fileInput] تغيير الملف، عدد الملفات: ${this.files ? this.files.length : 0}`);

                    if (!canUploadDocument('file')) {
                        console.log(`❌ [fileInput] فشل التحقق من إمكانية الرفع`);
                        resetUploadState();
                        return;
                    }

                    if (!this.files || this.files.length === 0) {
                        console.log(`📝 [fileInput] لا توجد ملفات محددة، إعادة تهيئة...`);
                        resetUploadState();
                        return;
                    }

                    const file = this.files[0];
                    console.log(`✅ [fileInput] تم اختيار ملف: ${file.name} (${file.type})`);

                    // إنهاء حالة فتح الحوار
                    fileDialogOpen = false;

                    let idInput = form.querySelector('input[name^="family_members["][name$="[person_id]"]');
                    const typeText = docTypeSelect.options[docTypeSelect.selectedIndex].text;
                    const typeVal = docTypeSelect.value;

                    const fileIdInput = document.querySelector('input[name="file_id_number"]');
                    const fileId = fileIdInput ? fileIdInput.value : '';
                    const personId = idInput ? idInput.value : '';

                    if (!fileId || fileId === 'undefined') {
                        console.error(`❌ [fileInput] رقم الملف غير صالح: ${fileId}`);
                        resetUploadState();
                        return;
                    }

                    const personKey = form.querySelector('[data-upload-zone]')?.getAttribute('data-upload-zone') || `family_${idx}`;
                    const docName = `${typeVal}_${fileId}_${personId}${file.name.substring(file.name.lastIndexOf('.'))}`;

                    console.log(`📤 [fileInput] بدء معالجة الملف:`, {
                        fileName: file.name,
                        fileType: file.type,
                        typeVal: typeVal,
                        typeText: typeText,
                        personId: personId,
                        fileId: fileId,
                        personKey: personKey,
                        docName: docName
                    });

                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            // إظهار حالة المعالجة
                            showProcessingState(e.target.result);

                            // فتح المقص بعد تأخير
                            setTimeout(() => {
                                if (typeof window.showCropperModal === 'function') {
                                    window.showCropperModal(file, function(croppedFile) {
                                        if (!croppedFile || !fileId || !personId) {
                                            console.log(`❌ [fileInput] فشل القص أو الإلغاء`);
                                            preview.innerHTML = '';
                                            docTypeSelect.value = '';
                                            resetUploadState();
                                            return;
                                        }

                                        if (croppedFile.name && croppedFile.name.includes('_cropped') && croppedFile.name !== 'undefined') {
                                            const docObj = {
                                                type: typeVal,
                                                typeText: typeText,
                                                file: croppedFile,
                                                processedFile: croppedFile,
                                                docName: docName,
                                                personId: personId,
                                                fileId: fileId,
                                                personKey: personKey
                                            };

                                            console.log(`✅ [fileInput] نجح القص وإنشاء كائن الوثيقة:`, docObj);

                                            // إضافة إلى window.allDocs
                                            if (window.allDocs && window.allDocs instanceof Map) {
                                                let docsArr = window.allDocs.get(personKey) || [];
                                                docsArr.push(docObj);
                                                window.allDocs.set(personKey, docsArr);
                                                console.log(`📋 [fileInput] تم إضافة الوثيقة إلى allDocs`);
                                            }

                                            // إضافة إلى documents المحلية
                                            documents.push(docObj);

                                            // مزامنة مع window.allDocs
                                            syncWithAllDocs();

                                            // إظهار رسالة النجاح
                                            preview.innerHTML = `
                                                <div class="success-container text-center p-4" style="
                                                    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                                                    border-radius: 15px;
                                                    border: 2px solid #28a745;
                                                    animation: successPulse 0.6s ease-in-out;
                                                ">
                                                    <div class="success-icon mb-3">
                                                        <i class="fas fa-check-circle text-success" style="font-size: 3rem; animation: bounceIn 0.8s ease;"></i>
                                                    </div>
                                                    <div class="text-success fw-bold mb-2" style="font-size: 1.2rem;">تمت المعالجة بنجاح!</div>
                                                    <small class="text-muted">جاري عرض النتيجة النهائية...</small>
                                                </div>
                                                <style>
                                                    @keyframes successPulse {
                                                        0% { transform: scale(0.8); opacity: 0; }
                                                        50% { transform: scale(1.05); }
                                                        100% { transform: scale(1); opacity: 1; }
                                                    }
                                                    @keyframes bounceIn {
                                                        0% { transform: scale(0.3); opacity: 0; }
                                                        50% { transform: scale(1.05); }
                                                        70% { transform: scale(0.9); }
                                                        100% { transform: scale(1); opacity: 1; }
                                                    }
                                                </style>
                                            `;

                                            // عرض النتيجة النهائية
                                            setTimeout(() => {
                                                renderDocuments();
                                                // إعادة تهيئة بعد النجاح
                                                docTypeSelect.value = '';
                                                resetUploadState();
                                            }, 1500);
                                        }
                                    });
                                } else {
                                    console.error(`❌ [fileInput] دالة المقص غير متوفرة`);
                                    resetUploadState();
                                }
                            }, 800);
                        };

                        reader.onerror = function() {
                            console.error(`❌ [fileInput] خطأ في قراءة الملف`);
                            resetUploadState();
                        };

                        reader.readAsDataURL(file);
                    } else {
                        // معالجة الملفات غير الصور
                        console.log(`📄 [fileInput] معالجة ملف غير صورة: ${file.name}`);

                        setTimeout(() => {
                            const docObj = {
                                type: typeVal,
                                typeText: typeText,
                                file: file,
                                processedFile: file,
                                docName: docName,
                                personId: personId,
                                fileId: fileId,
                                personKey: personKey
                            };

                            // إضافة إلى window.allDocs
                            if (window.allDocs && window.allDocs instanceof Map) {
                                let docsArr = window.allDocs.get(personKey) || [];
                                docsArr.push(docObj);
                                window.allDocs.set(personKey, docsArr);
                            }

                            // إضافة إلى documents المحلية
                            documents.push(docObj);

                            // مزامنة مع window.allDocs
                            syncWithAllDocs();

                            renderDocuments();

                            console.log(`✅ [fileInput] تم إضافة ملف غير صورة بنجاح`);

                            // إعادة تهيئة بعد النجاح
                            docTypeSelect.value = '';
                            resetUploadState();
                        }, 500);
                    }
                });

                // مستمع إضافي للإلغاء مع التحقق من صحة العنصر
                input.addEventListener('cancel', function() {
                    console.log(`❌ [fileInput] تم إلغاء اختيار الملف`);
                    // التحقق من وجود النموذج قبل محاولة إعادة التهيئة
                    if (form && form.parentNode) {
                        resetUploadState();
                    }
                });
            }

            // إعداد معالج الملف الأولي
            setupFileInputHandler(fileInput);
        };

        // ربط زر إضافة فرد
        const addFamilyMemberBtn = document.getElementById('addFamilyMember');
        if (addFamilyMemberBtn) {
            addFamilyMemberBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addFamilyMember();
            });
        }

        // جعل الدوال متاحة عالمياً
        window.addFamilyMember = addFamilyMember;
    });

    // تم حذف تعريف الدالة هنا لتجنب التعارض مع ملف ageCalculating.blade.php
    // window.calculateAge = function(inputElement) {
    //     const birthDate = new Date(inputElement.value);
    //     if (isNaN(birthDate.getTime())) return;

    //     const today = new Date();
    //     let age = today.getFullYear() - birthDate.getFullYear();
    //     const monthDiff = today.getMonth() - birthDate.getMonth();

    //     if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    //         age--;
    //     }

    //     const match = inputElement.name.match(/family_members\[(\d+)\]/);
    //     if (!match) return;

    //     const formIndex = match[1];
    //     const form = inputElement.closest('.family-member-form');
    //     if (!form) return;

    //     const ageInput = form.querySelector(`input[name="family_members[${formIndex}][person_age]"]`);
    //     if (ageInput) {
    //         ageInput.value = age;
    //     }
    // };
    </script>
@endpush
