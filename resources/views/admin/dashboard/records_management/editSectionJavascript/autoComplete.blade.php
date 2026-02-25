<script>
    // تحديث أفراد الأسرة تلقائياً حسب اختيار القسم
    document.addEventListener('DOMContentLoaded', function() {
        const sectionSelect = document.querySelector('select[name="data_section_id"]');
        const addFamilyMemberBtn = document.getElementById('addFamilyMember');

        // --- إضافة فرد أسرة جديد عند الضغط على الزر ---
        if (addFamilyMemberBtn && !window.familyMemberBtnHandlerAdded) {
            addFamilyMemberBtn.addEventListener('click', function() {
                const container = document.getElementById('familyMembersContainer');
                if (!container) return;
                const forms = container.querySelectorAll('.family-member-form');
                let newForm;
                let newIndex;
                const sponsorshipStatuses = @json($sponsorship_status);
                const healthStatuses = @json($health_status);
                const guaranteeTypes = @json($guarantee_types);
                if (forms.length === 0) {
                    // لا يوجد أي card: انسخ أول card من الصفحة مباشرة (وليس من HTML ثابت أو AJAX)
                    // ابحث عن أول select موجود لكل قائمة منسدلة وانسخ خياراته (وليس فقط innerHTML)
                    function getSelectOptionsHtml(selector) {
                        var select = document.querySelector(selector);
                        return select ? select.outerHTML.replace(/^<select[^>]*>|<\/select>$/g, '') :
                        '';
                    }
                    // استخدم innerHTML فقط للخيارات وليس للعنصر select نفسه
                    function getOptionsOnly(selector) {
                        var select = document.querySelector(selector);
                        return select ? select.innerHTML : '';
                    }
                    const sponsorshipOptions = getOptionsOnly(
                        'select[name^="family_members"][name$="[sponsorship_status]"]');
                    const healthOptions = getOptionsOnly(
                        'select[name^="family_members"][name$="[person_health_status]"]');
                    const guaranteeOptions = getOptionsOnly(
                        'select[name^="family_members"][name$="[person_type_of_guarantee]"]');
                    const genderOptions = getOptionsOnly(
                        'select[name^="family_members"][name$="[person_gender]"]');
                    const html = `
                    <div class="family-member-form rounded p-3 mb-4 position-relative" style="border: 2px solid #343a40; border-radius: 8px;">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-bottom: 1px solid #eee; min-height: 48px;">
                            <span class="fw-bold d-flex align-items-center" style="font-size: 1.1rem;">
                                <i class="fas fa-user-circle fs-5 me-2 text-primary"></i>
                                <span class="badge bg-success me-2" style="font-size: 1rem;">فرد الأسرة (1)</span>
                            </span>
                            <button type="button" class="btn btn-light btn-sm delete-family-member-x custom-x-btn" title="حذف">
                                <span aria-hidden="true" style="font-size:1.2rem;">&times;</span>
                            </button>
                        </div>
                        <div class="row g-3">
                            <input type="hidden" name="family_members[0][file_id]" value="">
                            <div class="col-md-6">
                                <label class="form-label">رقم التسجيل <span class="text-danger">*</span></label>
                                <input type="text" name="family_members[0][registration_id]" class="form-control bg-secondary bg-opacity-10 registration-id-input" readonly value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">حالة الكفالة</label>
                                <select name="family_members[0][sponsorship_status]" class="form-select">
                                    <option value="">اختر الحالة</option>
                                    ${sponsorshipStatuses.map(status => `<option value="${status.id}">${status.description}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">رقم هوية اليتيم</label>
                                <div class="input-group" style="max-width:320px;">
                                    <input type="text" name="family_members[0][person_id]" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="9" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="أدخل رقم الهوية للجلب التلقائي">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                <input type="text" name="family_members[0][first_name]" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الاسم الثاني</label>
                                <input type="text" name="family_members[0][second_name]" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الاسم الثالث</label>
                                <input type="text" name="family_members[0][third_name]" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                                <input type="text" name="family_members[0][last_name]" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                                <input type="date" name="family_members[0][person_birth_date]" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">العمر</label>
                                <input type="number" name="family_members[0][person_age]" class="form-control" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الجنس</label>
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
                                    ${healthStatuses.map(status => `<option value="${status.id}">${status.description}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">نوع الكفالة</label>
                                <select name="family_members[0][person_type_of_guarantee]" class="form-select">
                                    <option value="">اختر النوع</option>
                                    ${guaranteeTypes.map(type => `<option value="${type.id}">${type.description}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">ملاحظة</label>
                                <textarea name="family_members[0][person_note]" class="form-control" rows="3" placeholder="أدخل ملاحظة..." style="resize:vertical;"></textarea>
                            </div>
                        </div>
                    </div>
                    `;

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    newForm = tempDiv.firstElementChild;
                    newIndex = 0;
                } else {
                    // يوجد card: انسخ آخر card
                    newForm = forms[forms.length - 1].cloneNode(true);
                    newIndex = forms.length;

                    // تحديث border للكارت المستنسخ
                    newForm.style.border = '2px solid #343a40';
                    newForm.style.borderRadius = '8px';

                    // تحديث رقم الفرد في العنوان
                    const cardHeaderSpan = newForm.querySelector('.card-header span.fw-bold');
                    if (cardHeaderSpan) {
                        cardHeaderSpan.innerHTML = `
                            <i class="fas fa-user-circle fs-5 me-2 text-primary"></i>
                            <span class="badge bg-success me-2" style="font-size: 1rem;">فرد الأسرة (${newIndex + 1})</span>
                        `;
                    }
                }
                // تحديث أسماء الحقول والمعرفات
                newForm.querySelectorAll('[name]').forEach(function(input) {
                    input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
                    if (input.id) input.id = input.id.replace(/\d+/, newIndex);
                    if (input.type === 'text' || input.type === 'number' || input.type ===
                        'date') {
                        input.value = '';
                    } else if (input.tagName.toLowerCase() === 'textarea') {
                        input.value = '';
                    } else if (input.tagName.toLowerCase() === 'select') {
                        input.selectedIndex = 0;
                    }
                });
                // إزالة ربط السجل المدني القديم من النسخة المكررة كي يُعاد ربطها
                newForm.querySelectorAll('[data-civil-lookup-attached]').forEach(function(el) {
                    delete el.dataset.civilLookupAttached;
                    var oldSpinner = el.closest && el.closest('.input-group') && el.closest('.input-group').querySelector('.civil-lookup-status');
                    if (oldSpinner) oldSpinner.remove();
                });
                container.appendChild(newForm);
                // ربط السجل المدني على person_id للفرد الجديد فوراً
                var newPersonIdInput = newForm.querySelector('input[name$="[person_id]"]');
                if (newPersonIdInput && window.setupFamilyMemberLookup) {
                    window.setupFamilyMemberLookup(newPersonIdInput);
                }
            });
            window.familyMemberBtnHandlerAdded = true;
        }

        function fillFamilyMemberFields(template) {
            if (!template) return;
            const sectionValue = sectionSelect ? sectionSelect.value : '';
            if (sectionValue === '1') {
                const fatherFirstInput = document.querySelector('input[name="father_first_name"]');
                const fatherSecondInput = document.querySelector('input[name="father_second_name"]');
                const fatherLastInput = document.querySelector('input[name="father_last_name"]');
                const fatherFirst = fatherFirstInput ? fatherFirstInput.value : '';
                const fatherSecond = fatherSecondInput ? fatherSecondInput.value : '';
                const fatherLast = fatherLastInput ? fatherLastInput.value : '';
                const secondNameInput = template.querySelector('input[name*="[second_name]"]');
                const thirdNameInput = template.querySelector('input[name*="[third_name]"]');
                const lastNameInput = template.querySelector('input[name*="[last_name]"]');
                if (secondNameInput) secondNameInput.value = fatherFirst;
                if (thirdNameInput) thirdNameInput.value = fatherSecond;
                if (lastNameInput) lastNameInput.value = fatherLast;
            } else {
                const dataFirstInput = document.querySelector('input[name="data_first_name"]');
                const dataFatherInput = document.querySelector('input[name="data_father_name"]');
                const dataFamilyInput = document.querySelector('input[name="data_family_name"]');
                const dataFirst = dataFirstInput ? dataFirstInput.value : '';
                const dataFather = dataFatherInput ? dataFatherInput.value : '';
                const dataFamily = dataFamilyInput ? dataFamilyInput.value : '';
                const secondNameInput = template.querySelector('input[name*="[second_name]"]');
                const thirdNameInput = template.querySelector('input[name*="[third_name]"]');
                const lastNameInput = template.querySelector('input[name*="[last_name]"]');
                if (secondNameInput) secondNameInput.value = dataFirst;
                if (thirdNameInput) thirdNameInput.value = dataFather;
                if (lastNameInput) lastNameInput.value = dataFamily;
            }
        }

        // عند إضافة فرد جديد
        if (addFamilyMemberBtn) {
            addFamilyMemberBtn.addEventListener('click', function() {
                setTimeout(() => {
                    const templates = document.querySelectorAll('.family-member-form');
                    if (!templates || templates.length === 0) return;
                    const lastTemplate = templates[templates.length - 1];
                    if (lastTemplate) fillFamilyMemberFields(lastTemplate);
                    if (typeof updatePersonsList === 'function') updatePersonsList();
                }, 50);
            });
        }

        // عند تغيير القسم، حدث أفراد الأسرة الحاليين
        if (sectionSelect) {
            sectionSelect.addEventListener('change', function() {
                document.querySelectorAll('.family-member-form').forEach(template => {
                    if (template) fillFamilyMemberFields(template);
                });
            });
        }

        // إصلاح نهائي: لا تستخدم document.getElementById.onclick أو أي كود مباشر في HTML attributes
        // إذا كان لديك في أي مكان في الصفحة:
        // <button id="addFamilyMember" onclick="...">
        // احذف خاصية onclick من الـ HTML، واجعل كل الأحداث عبر addEventListener فقط.
    });

    // إرسال جميع البيانات إلى الخادم عند إرسال النموذج
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('main_form');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm"></span> جارٍ الإرسال...';
            }

            const formData = new FormData(form);

            // حل جذري: تحقق أن كل formEl ليس null قبل استخدام querySelectorAll
            document.querySelectorAll('.family-member-form').forEach(function(formEl, idx) {
                if (!formEl) return;
                formEl.querySelectorAll('[name]').forEach(function(input) {
                    let name = input.name.replace(/^family_members\[\d+\]/,
                        `family_members[${idx}]`);
                    formData.set(name, input.value);
                });
            });

            if (typeof docs !== 'undefined') {
                docs.forEach((docInfo, docId) => {
                    formData.append(`document_file[]`, docInfo.file);
                    formData.append(`file_type[]`, docInfo.type);
                    formData.append(`person_identity_number[]`, docInfo.personId);
                });
            }

            fetch(form.action, {
                    method: form.method || 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(async response => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                    let data = {};
                    try {
                        data = await response.json();
                    } catch {}
                    if (!response.ok || !data.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: (data.message || 'فشل الإرسال')
                        });
                        return;
                    }
                    Swal.fire({
                            icon: 'success',
                            title: 'تم الحفظ',
                            text: data.message || 'تم إرسال البيانات بنجاح'
                        })
                        .then(() => window.location.reload());
                })
                .catch(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: 'حدث خطأ أثناء الإرسال'
                    });
                });
        });
    });

    // إصلاح نهائي للمشكلة:
    // 1. تأكد أنه لا يوجد أي عنصر في HTML يستخدم onclick="..." أو أي خاصية inline JS.
    // 2. كل الأحداث يجب أن تكون عبر addEventListener فقط كما هو في الكود أدناه.
    // 3. إذا كان لديك كود مثل document.getElementById('addFamilyMember').onclick = ... في أي مكان آخر، احذفه.
</script>
