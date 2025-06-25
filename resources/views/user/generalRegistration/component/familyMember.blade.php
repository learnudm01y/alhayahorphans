<div class="tab-pane fade" id="family-members" role="tabpanel" aria-labelledby="family-members-tab">
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">بيانات أفراد الأسرة</h5>

                    </div>
                    <div id="familyMembersContainer">
                        <!-- نموذج إضافة فرد -->
                        <div class="family-member-form border rounded p-3 mb-3" data-member-index="0">
                            <div class="row g-3">
                                <input type="hidden" name="family_members[0][file_id]"
                                    value="{{ $file_id_number ?? '' }}">
                                <div class="col-md-6">
                                    <label class="form-label">رقم التسجيل <span class="text-danger">*</span></label>
                                    <input type="text" name="family_members[0][registration_id]"
                                        class="form-control bg-secondary bg-opacity-10" readonly
                                        value="{{ $file_id_number ?? '' }}">
                                </div>
                                  <div class="col-md-4">
                                    <label class="form-label">رقم هوية اليتيم</label>
                                    <input type="text" name="family_members[0][person_id]" class="form-control"
                                        inputmode="numeric" pattern="[0-9]*" maxlength="10"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                    <input type="text" name="family_members[0][first_name]" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الاسم الثاني <span class="text-primary"
                                            style="color:#6c757d !important;">(اختياري)</span>
                                    </label>
                                    <input type="text" name="family_members[0][second_name]" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الاسم الثالث <span class="text-primary"
                                            style="color:#6c757d !important;">(اختياري)</span>
                                    </label>
                                    <input type="text" name="family_members[0][third_name]" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                                    <input type="text" name="family_members[0][last_name]" class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                                    <input type="date" name="family_members[0][person_birth_date]"
                                        class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">العمر</label>
                                    <input type="number" name="family_members[0][person_age]" class="form-control"
                                        readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الجنس <span class="text-danger">*</span></label>
                                    <select name="family_members[0][person_gender]" class="form-select">
                                        <option value="">اختر الجنس</option>
                                        <option value="1">ذكر</option>
                                        <option value="2">أنثى</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الحالة الصحية</label>
                                    <select name="family_members[0][person_health_status]" class="form-select">
                                        <option value="">اختر الحالة</option>
                                        @foreach ($health_status as $status)
                                            <option value="{{ $status->id }}">
                                                {{ $status->description }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-primary">ملاحظة <span class="text-primary"
                                            style="color:#6c757d !important;">(اختياري)</span></label>
                                    <textarea name="family_members[0][person_note]" cols="30" rows="4"
                                        class="form-control rounded shadow-sm border-primary bg-light" placeholder="أدخل ملاحظتك هنا..."
                                        style="resize: vertical; min-height: 80px;"></textarea>
                                </div>
                                <div class="col-md-4 mt-3">
                                    <!-- ملاحظة توضيحية لرفع الملفات -->
                                    <div class="alert alert-primary py-2 mb-2" style="font-size: 0.97rem;">
                                        يرجى اختيار نوع الوثيقة أولاً، وسوف يتم تحويلك لرفع الصورة المطلوبة.
                                    </div>
                                    <label class="form-label fw-bold"> رفع الملفات <span
                                            class="text-danger">*</span></label>
                                    <div class="upload-zone" data-upload-zone="family_0">
                                        <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_0">
                                            <option value="">اختر نوع الوثيقة</option>
                                            @foreach ($documentTypes as $documentType)
                                                <option value="{{ $documentType->pref }}">
                                                    {{ $documentType->description }}</option>
                                            @endforeach
                                        </select>
                                        <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_0"
                                            accept="image/*,.pdf"
                                            style="display:none !important; visibility:hidden !important; width:0; height:0; pointer-events:none; opacity:0; position:absolute; left:-9999px;">
                                        <div class="mainDocumentPreview mt-2" id="mainDocumentPreview_0"></div>
                                        <div class="mainDocumentNames mt-2" id="mainDocumentNames_0"></div>
                                    </div>
                                </div>
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
                                // تحقق من جميع أفراد الأسرة
                                let invalidField = null;
                                let invalidLabel = '';
                                document.querySelectorAll('.family-member-form').forEach(function(form) {
                                    if (invalidField) return; // توقف عند أول خطأ فقط
                                    // الحقول المطلوبة لكل فرد
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
                                    // تحقق من رفع ملف أو اختيار نوع الوثيقة
                                    if (!invalidField) {
                                        const docType = form.querySelector('.mainDocumentTypeSelect');
                                        const fileInput = form.querySelector('.mainDocumentFileInput');
                                        const hasFile = fileInput && fileInput.files && fileInput.files.length >
                                            0;
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
                                document.querySelectorAll('.family-member-form').forEach(function(form) {
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
                                    document.querySelectorAll('.family-member-form').forEach(function(form) {
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
    // تعريف دالة نسخ نموذج فرد الأسرة وإضافته
    function addFamilyMember() {
        const container = document.getElementById('familyMembersContainer');
        const forms = container.querySelectorAll('.family-member-form');
        const lastIndex = forms.length - 1;
        const lastForm = forms[lastIndex];
        if (!lastForm) return;
        // نسخ النموذج
        const clone = lastForm.cloneNode(true);
        // تحديث الفهارس في الأسماء
        const newIndex = lastIndex + 1;
        clone.setAttribute('data-member-index', newIndex);
        // تحديث data-upload-zone في منطقة رفع الملفات للفرد الجديد
        const uploadZone = clone.querySelector('[data-upload-zone]');
        if (uploadZone) {
            uploadZone.setAttribute('data-upload-zone', `family_${newIndex}`);
        }
        clone.querySelectorAll('[name]').forEach(function(input) {
            input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
            // إذا كان الحقل هو رقم الملف العام، انسخ قيمته من النموذج الأصلي
            if (input.name.endsWith('[file_id]')) {
                const originalFileId = lastForm.querySelector('[name$="[file_id]"]');
                if (originalFileId) input.value = originalFileId.value;
            // إذا كان الحقل هو رقم التسجيل، انسخ قيمته من النموذج الأصلي
            } else if (input.name.endsWith('[registration_id]')) {
                const originalRegId = lastForm.querySelector('[name$="[registration_id]"]');
                if (originalRegId) input.value = originalRegId.value;
                input.readOnly = true;
                input.classList.add('bg-secondary', 'bg-opacity-10');
            } else if (input.type === 'text' || input.type === 'number' || input.type === 'date') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            }
        });
        // تفريغ معاينة وأسماء الملفات
        clone.querySelectorAll('.mainDocumentPreview, .mainDocumentNames').forEach(div => div.innerHTML = '');
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
            // إذا كان موجوداً، تأكد من وجود زر الحذف
            if (!cardHeader.querySelector('.delete-member')) {
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-danger btn-sm delete-member';
                delBtn.innerHTML = '<i class="fas fa-times"></i>';
                cardHeader.appendChild(delBtn);
            }
        }
        // إضافة مستمع حذف مع SweetAlert
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
                    }, 300);
                }
            });
        };
        // إضافة النموذج الجديد
        container.appendChild(clone);
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
        const forms = document.querySelectorAll('#familyMembersContainer .family-member-form');
        forms.forEach(function(form, idx) {
            form.setAttribute('data-member-index', idx);
            // تحديث أسماء الحقول
            form.querySelectorAll('[name]').forEach(function(input) {
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
    }
    // استدعاء الدالة بعد إضافة فرد جديد
    if (window.addFamilyMember) {
        const originalAdd = window.addFamilyMember;
        window.addFamilyMember = function() {
            originalAdd();
            reindexFamilyMembers();
        };
    }
    // استدعاء الدالة بعد حذف فرد (زر الحذف)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-member')) {
            setTimeout(reindexFamilyMembers, 350); // بعد الحذف
        }
    });
});
</script>
@endpush
            </div>
        </div>
    </div>
</div>
