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
                                        const docType = form.querySelector('.mainDocumentTypeSelect');
                                        const fileInput = form.querySelector('.mainDocumentFileInput');
                                        const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
                                        if (docType && !docType.value && !hasFile) {
                                            invalidField = docType;
                                            invalidLabel = 'نوع الوثيقة أو رفع الملف';
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
                                        const docType = form.querySelector('.mainDocumentTypeSelect');
                                        const fileInput = form.querySelector('.mainDocumentFileInput');
                                        const hasFile = fileInput && fileInput.files && fileInput.files
                                            .length > 0;
                                        if (docType && !docType.value && !hasFile) {
                                            invalidField = docType;
                                            invalidLabel = 'نوع الوثيقة أو رفع الملف';
                                        }
                                    }
                                });
                                if (invalidField) {
                                    e.preventDefault();
                                    if (typeof e.stopImmediatePropagation === 'function') e
                                        .stopImmediatePropagation();
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
                                            const docType = form.querySelector(
                                                '.mainDocumentTypeSelect');
                                            const fileInput = form.querySelector(
                                                '.mainDocumentFileInput');
                                            const hasFile = fileInput && fileInput.files && fileInput
                                                .files.length > 0;
                                            if (docType && !docType.value && !hasFile) {
                                                invalidField = docType;
                                                invalidLabel = 'نوع الوثيقة أو رفع الملف';
                                            }
                                        }
                                    });
                                }
                                if (invalidField) {
                                    e.preventDefault();
                                    if (typeof e.stopImmediatePropagation === 'function') e
                                        .stopImmediatePropagation();
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
@push('scriptsCodeUserRegistration')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // عند تحميل الصفحة، تأكد من تعريف window.allDocs وتهيئة النموذج الأول فقط، ثم فعّل reindexFamilyMembers لمرة واحدة
    setTimeout(function() {
        if (typeof window.allDocs !== 'object' || !window.allDocs) {
            window.allDocs = {};
        }
        // لا تهيئ window.allDocs['family_0'] هنا إطلاقًا، فقط عند إضافة النماذج
    }, 0);
    // عند أول إضافة، انسخ النموذج المخفي وأظهره
    function addFamilyMember() {
        const container = document.getElementById('familyMembersContainer');
        let forms = container.querySelectorAll('.family-member-form:not(.d-none)');
        let template = document.getElementById('familyMemberTemplate');
        let newIndex = forms.length;
        let clone = template.cloneNode(true);
        clone.classList.remove('d-none');
        clone.removeAttribute('id');
        clone.setAttribute('data-member-index', newIndex);

        // تحديث أسماء الحقول والفهارس لكل عنصر يحمل name
        clone.querySelectorAll('[name]').forEach(function(input) {
            // تحديث كل الفهارس لأي حقل باسم family_members[رقم] في الاسم كله
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
        // تحديث id العناصر الخاصة بالملفات والمعاينة
        const docType = clone.querySelector('.mainDocumentTypeSelect');
        if (docType) docType.id = `mainDocumentTypeSelect_${newIndex}`;
        const fileInput = clone.querySelector('.mainDocumentFileInput');
        if (fileInput) fileInput.id = `mainDocumentFileInput_${newIndex}`;
        const preview = clone.querySelector('.mainDocumentPreview');
        if (preview) preview.id = `mainDocumentPreview_${newIndex}`;
        const names = clone.querySelector('.mainDocumentNames');
        if (names) names.id = `mainDocumentNames_${newIndex}`;

        // مسح معاينة المرفقات وأسماء الملفات
        clone.querySelectorAll('.mainDocumentPreview, .mainDocumentNames').forEach(div => div.innerHTML = '');

        // إعادة تهيئة window.allDocs لهذا النموذج فقط
        if (window.allDocs && typeof window.allDocs === 'object') {
            window.allDocs[`family_${newIndex}`] = [];
        }

        // إضافة card-header وزر حذف
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
        } else {
            if (!cardHeader.querySelector('.delete-member')) {
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-danger btn-sm delete-member';
                delBtn.innerHTML = '<i class="fas fa-times"></i>';
                cardHeader.appendChild(delBtn);
            }
        }
        cardHeader.querySelector('.delete-member').onclick = function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'سيتم حذف هذا الفرد من القائمة',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    clone.style.opacity = '0';
                    clone.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        clone.remove();
                        reindexFamilyMembers();
                    }, 300);
                }
            });
        };
        container.appendChild(clone);

        // طباعة معلومات النموذج الجديد في الـ console
        const uploadZoneKey = clone.querySelector('[data-upload-zone]')?.getAttribute('data-upload-zone') || 'N/A';
        const fieldNames = Array.from(clone.querySelectorAll('[name]')).map(i => i.name);
        console.log(`تم توليد نموذج ${newIndex}: data-upload-zone = ${uploadZoneKey}, أسماء الحقول:`, fieldNames);

        // بعد الإضافة، فعّل إعادة الفهرسة مباشرة
        reindexFamilyMembers();
    }
    // ربط الزر بالدالة
    const addFamilyMemberBtn = document.getElementById('addFamilyMember');
    if (addFamilyMemberBtn) {
        addFamilyMemberBtn.onclick = null;
        addFamilyMemberBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addFamilyMember();
        });
    }
    // اجعل الدالة متاحة عالمياً إذا احتجت
    window.addFamilyMember = addFamilyMember;
    // دالة لإعادة ترتيب فهارس النماذج وأسماء الحقول بعد أي حذف أو إضافة
    function reindexFamilyMembers() {
        const forms = document.querySelectorAll('#familyMembersContainer .family-member-form:not(.d-none)');
        forms.forEach(function(form, idx) {
            form.setAttribute('data-member-index', idx);
            form.querySelectorAll('[name]').forEach(function(input) {
                // تحديث كل الفهارس لأي حقل باسم family_members[رقم] في الاسم كله
                input.name = input.name.replace(/family_members\[\d+\]/g, `family_members[${idx}]`);
            });
            // تحديث data-upload-zone
            const uploadZone = form.querySelector('[data-upload-zone]');
            if (uploadZone) {
                uploadZone.setAttribute('data-upload-zone', `family_${idx}`);
            }
            // تحديث id العناصر الخاصة بالملفات والمعاينة (اختياري)
            const docType = form.querySelector('.mainDocumentTypeSelect');
            if (docType) docType.id = `mainDocumentTypeSelect_${idx}`;
            const fileInput = form.querySelector('.mainDocumentFileInput');
            if (fileInput) fileInput.id = `mainDocumentFileInput_${idx}`;
            const preview = form.querySelector('.mainDocumentPreview');
            if (preview) preview.id = `mainDocumentPreview_${idx}`;
            const names = form.querySelector('.mainDocumentNames');
            if (names) names.id = `mainDocumentNames_${idx}`;
        });
        // Debug: طباعة أسماء الحقول ومفتاح data-upload-zone بعد كل إعادة فهرسة
        forms.forEach(function(form, idx) {
            const uploadZone = form.querySelector('[data-upload-zone]');
            const zoneKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : 'N/A';
            console.log(`نموذج ${idx}: data-upload-zone = ${zoneKey}, أسماء الحقول:`, Array.from(form.querySelectorAll('[name]')).map(i => i.name));
        });
        // إعادة ربط حدث حساب العمر على جميع حقول تاريخ الميلاد بعد كل إعادة فهرسة
        setTimeout(function() {
            document.querySelectorAll('.family-member-form:not(.d-none) input[name$="[person_birth_date]"]').forEach(function(input) {
                input.oninput = function() {
                    window.calculateAge(this);
                };
            });
        }, 100);
        // إعادة تفعيل أحداث رفع المرفقات لكل نموذج ظاهر بعد كل إعادة فهرسة
        setTimeout(function() {
            if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
                document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form, idx) {
                    window.setupDocumentUploadHandlersForMember(form, idx);
                });
            }
        }, 150);
    }
    // لا يتم توليد أي نموذج تلقائيًا عند التحميل، فقط عند الضغط على زر إضافة فرد
    // استدعاء الدالة بعد حذف فرد (زر الحذف)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-member')) {
            setTimeout(reindexFamilyMembers, 350); // بعد الحذف
        }
    });
});
// تعريف دالة حساب العمر في النطاق العام
window.calculateAge = function(inputElement) {
    const birthDate = new Date(inputElement.value);
    if (isNaN(birthDate.getTime())) return;
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    // استخراج الفهرس الصحيح من اسم الحقل (أكثر أمانًا)
    const match = inputElement.name.match(/family_members\[(\d+)\]/);
    if (!match) return;
    const formIndex = match[1];
    // ابحث عن الحقل داخل نفس النموذج فقط
    const form = inputElement.closest('.family-member-form');
    if (!form) return;
    const ageInput = form.querySelector(`input[name="family_members[${formIndex}][person_age]"]`);
    if (ageInput) {
        ageInput.value = age;
    }
};
</script>
@endpush
            </div>
        </div>
    </div>
</div>
