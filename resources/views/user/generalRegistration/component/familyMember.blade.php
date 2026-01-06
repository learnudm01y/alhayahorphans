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
                            reviewBtn.addEventListener('click', async function(e) {
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
                                const valid = await validateAllIds(e);
                                if (!valid) return; // إذا كان هناك خطأ لا تنتقل
                                // الانتقال إلى تبويب المراجعة
                                const reviewTab = document.getElementById('review-tab');
                                if (reviewTab) {
                                    reviewTab.click();
                                } else {
                                    console.log('تبويب المراجعة غير موجود');
                                }
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
    // Cache buster: 2025-01-06-v1.0.1
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

        // 🆕 دالة جلب بيانات فرد الأسرة من قاعدة البيانات المركزية
        window.fetchFamilyMemberData = function fetchFamilyMemberData(idNumber, memberIndex, formContainer = null) {
            console.log('🔍 [fetchFamilyMemberData] تم استدعاء الدالة:', {
                idNumber,
                memberIndex,
                hasFormContainer: !!formContainer,
                formContainerIndex: formContainer?.dataset?.memberIndex
            });

            const form = formContainer || document.querySelector(`.family-member-form[data-member-index="${memberIndex}"]`);
            if (!form) {
                console.error('❌ [fetchFamilyMemberData] لم يتم العثور على النموذج');
                console.log('📊 [fetchFamilyMemberData] جميع النماذج المتاحة:', 
                    Array.from(document.querySelectorAll('.family-member-form')).map(f => ({
                        index: f.dataset.memberIndex,
                        id: f.id,
                        isHidden: f.classList.contains('d-none')
                    }))
                );
                return;
            }

            console.log('✅ [fetchFamilyMemberData] تم العثور على النموذج:', {
                formIndex: form.dataset.memberIndex,
                formId: form.id
            });

            const firstNameInput = form.querySelector(`[name="family_members[${memberIndex}][first_name]"]`);
            const secondNameInput = form.querySelector(`[name="family_members[${memberIndex}][second_name]"]`);
            const thirdNameInput = form.querySelector(`[name="family_members[${memberIndex}][third_name]"]`);
            const lastNameInput = form.querySelector(`[name="family_members[${memberIndex}][last_name]"]`);
            const birthDateInput = form.querySelector(`[name="family_members[${memberIndex}][person_birth_date]"]`);
            const genderSelect = form.querySelector(`[name="family_members[${memberIndex}][person_gender]"]`);
            const statusSpan = form.querySelector(`.family-member-search-status[data-member-index="${memberIndex}"]`);

            console.log('🔍 [fetchFamilyMemberData] العناصر المستخرجة:', {
                hasFirstName: !!firstNameInput,
                firstNameName: firstNameInput?.name,
                hasSecondName: !!secondNameInput,
                secondNameName: secondNameInput?.name,
                hasThirdName: !!thirdNameInput,
                thirdNameName: thirdNameInput?.name,
                hasLastName: !!lastNameInput,
                lastNameName: lastNameInput?.name,
                hasBirthDate: !!birthDateInput,
                birthDateName: birthDateInput?.name,
                hasGender: !!genderSelect,
                genderName: genderSelect?.name,
                hasStatus: !!statusSpan
            });

            if (!idNumber || idNumber.length < 9) {
                console.warn('⚠️ [fetchFamilyMemberData] رقم الهوية قصير جداً:', idNumber);
                return;
            }

            // إظهار مؤشر التحميل
            if (statusSpan) statusSpan.style.display = 'flex';

            console.log('🌐 [fetchFamilyMemberData] إرسال طلب إلى API:', `/api/civil-registry/search-by-id?search_text=${idNumber}`);

            fetch(`/api/civil-registry/search-by-id?search_text=${encodeURIComponent(idNumber)}`)
                .then(response => {
                    console.log('📡 [fetchFamilyMemberData] استلام رد HTTP:', response.status, response.statusText);
                    return response.json();
                })
                .then(data => {
                    if (statusSpan) statusSpan.style.display = 'none';

                    console.log('📥 [fetchFamilyMemberData] استلام الرد من API:', data);

                    if (data.success && data.data && data.data.length > 0) {
                        const person = data.data[0];

                        console.log('✅ [fetchFamilyMemberData] تم العثور على الشخص:', person);

                        // توزيع الاسم على الحقول الأربعة
                        console.log('📝 [fetchFamilyMemberData] بيانات الاسم من API:', {
                            CI_FIRST_ARB: person.CI_FIRST_ARB,
                            CI_FATHER_ARB: person.CI_FATHER_ARB,
                            CI_GRAND_FATHER_ARB: person.CI_GRAND_FATHER_ARB,
                            CI_FAMILY_ARB: person.CI_FAMILY_ARB
                        });

                        if (firstNameInput && person.CI_FIRST_ARB) {
                            console.log('⏳ [fetchFamilyMemberData] قبل التعيين - firstNameInput.value:', firstNameInput.value);
                            firstNameInput.value = person.CI_FIRST_ARB;
                            firstNameInput.classList.add('is-valid');
                            console.log('✅ [fetchFamilyMemberData] بعد التعيين - firstNameInput.value:', firstNameInput.value);
                        } else {
                            console.warn('❌ [fetchFamilyMemberData] لم يتم تعيين الاسم الأول:', {
                                hasInput: !!firstNameInput,
                                hasData: !!person.CI_FIRST_ARB
                            });
                        }

                        if (secondNameInput && person.CI_FATHER_ARB) {
                            console.log('⏳ [fetchFamilyMemberData] قبل التعيين - secondNameInput.value:', secondNameInput.value);
                            secondNameInput.value = person.CI_FATHER_ARB;
                            secondNameInput.classList.add('is-valid');
                            console.log('✅ [fetchFamilyMemberData] بعد التعيين - secondNameInput.value:', secondNameInput.value);
                        } else {
                            console.warn('❌ [fetchFamilyMemberData] لم يتم تعيين الاسم الثاني:', {
                                hasInput: !!secondNameInput,
                                hasData: !!person.CI_FATHER_ARB
                            });
                        }

                        if (thirdNameInput && person.CI_GRAND_FATHER_ARB) {
                            console.log('⏳ [fetchFamilyMemberData] قبل التعيين - thirdNameInput.value:', thirdNameInput.value);
                            thirdNameInput.value = person.CI_GRAND_FATHER_ARB;
                            thirdNameInput.classList.add('is-valid');
                            console.log('✅ [fetchFamilyMemberData] بعد التعيين - thirdNameInput.value:', thirdNameInput.value);
                        } else {
                            console.warn('❌ [fetchFamilyMemberData] لم يتم تعيين الاسم الثالث:', {
                                hasInput: !!thirdNameInput,
                                hasData: !!person.CI_GRAND_FATHER_ARB
                            });
                        }

                        if (lastNameInput && person.CI_FAMILY_ARB) {
                            console.log('⏳ [fetchFamilyMemberData] قبل التعيين - lastNameInput.value:', lastNameInput.value);
                            lastNameInput.value = person.CI_FAMILY_ARB;
                            lastNameInput.classList.add('is-valid');
                            console.log('✅ [fetchFamilyMemberData] بعد التعيين - lastNameInput.value:', lastNameInput.value);
                        } else {
                            console.warn('❌ [fetchFamilyMemberData] لم يتم تعيين اسم العائلة:', {
                                hasInput: !!lastNameInput,
                                hasData: !!person.CI_FAMILY_ARB
                            });
                        }

                        // تاريخ الميلاد (إذا توفر)
                        if (birthDateInput && person.CI_BIRTH_DATE) {
                            // تحويل التاريخ إلى الصيغة المطلوبة YYYY-MM-DD
                            let birthDate = person.CI_BIRTH_DATE;
                            if (birthDate) {
                                // محاولة تحويل التاريخ
                                const dateObj = new Date(birthDate);
                                if (!isNaN(dateObj.getTime())) {
                                    const year = dateObj.getFullYear();
                                    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                                    const day = String(dateObj.getDate()).padStart(2, '0');
                                    birthDateInput.value = `${year}-${month}-${day}`;
                                    birthDateInput.classList.add('is-valid');
                                    console.log('✅ [fetchFamilyMemberData] تم تعيين تاريخ الميلاد:', birthDateInput.value);

                                    // حساب العمر تلقائياً
                                    const ageInput = form.querySelector(`[name="family_members[${memberIndex}][person_age]"]`);
                                    if (ageInput) {
                                        const today = new Date();
                                        let age = today.getFullYear() - year;
                                        const m = today.getMonth() - dateObj.getMonth();
                                        if (m < 0 || (m === 0 && today.getDate() < dateObj.getDate())) {
                                            age--;
                                        }
                                        ageInput.value = age;
                                        console.log('✅ [fetchFamilyMemberData] تم حساب العمر:', age);
                                    }

                                    // تفعيل حدث change لتحديث العمر
                                    birthDateInput.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            }
                        }

                        // الجنس (إذا توفر)
                        if (genderSelect && person.CI_SEX) {
                            // 1 = ذكر، 2 = أنثى
                            genderSelect.value = person.CI_SEX;
                            genderSelect.classList.add('is-valid');
                            console.log('✅ [fetchFamilyMemberData] تم تعيين الجنس:', person.CI_SEX);
                        }

                        console.log(`✅ تم جلب بيانات فرد الأسرة بنجاح:`, person);
                    } else {
                        // لم يتم العثور على الشخص
                        console.log(`ℹ️ لم يتم العثور على بيانات للهوية: ${idNumber}`);
                    }
                })
                .catch(error => {
                    if (statusSpan) statusSpan.style.display = 'none';
                    console.error('❌ [fetchFamilyMemberData] خطأ في جلب بيانات فرد الأسرة:', error);
                });
        };

        // 🆕 ربط أحداث الجلب التلقائي بحقول رقم الهوية
        window.attachFamilyMemberIdListeners = function attachFamilyMemberIdListeners() {
            console.log('🔧 [attachFamilyMemberIdListeners] بدء ربط المستمعات');
            const allInputs = document.querySelectorAll('.family-member-id-input');
            console.log('🔧 [attachFamilyMemberIdListeners] عدد الحقول الموجودة:', allInputs.length);

            allInputs.forEach((input, index) => {
                console.log(`🔧 [attachFamilyMemberIdListeners] معالجة الحقل ${index}:`, {
                    hasListener: !!input.dataset.fetchListenerAttached,
                    memberIndex: input.dataset.memberIndex
                });

                if (!input.dataset.fetchListenerAttached) {
                    input.dataset.fetchListenerAttached = 'true';
                    let debounceTimer;

                    input.addEventListener('input', function() {
                        clearTimeout(debounceTimer);
                        const idNumber = this.value.trim();
                        const memberIndex = this.dataset.memberIndex;
                        const formContainer = this.closest('.family-member-form');

                        console.log('⌨️ [input event] رقم الهوية:', idNumber, 'طول:', idNumber.length, 'memberIndex:', memberIndex, 'formContainer:', formContainer);

                        debounceTimer = setTimeout(() => {
                            if (idNumber.length >= 9) {
                                console.log('✅ [input event] استدعاء fetchFamilyMemberData بعد 500ms');
                                console.log('📦 [input event] المعاملات المرسلة:', {
                                    idNumber: idNumber,
                                    memberIndex: memberIndex,
                                    formContainer: formContainer,
                                    formDataMemberIndex: formContainer?.dataset?.memberIndex
                                });
                                window.fetchFamilyMemberData(idNumber, memberIndex, formContainer);
                            } else {
                                console.log('⏳ [input event] رقم الهوية قصير، انتظار المزيد');
                            }
                        }, 500);
                    });

                    input.addEventListener('blur', function() {
                        const idNumber = this.value.trim();
                        const memberIndex = this.dataset.memberIndex;
                        const formContainer = this.closest('.family-member-form');

                        console.log('👁️ [blur event] رقم الهوية:', idNumber, 'memberIndex:', memberIndex);
                        console.log('👁️ [blur event] formContainer.dataset.memberIndex:', formContainer?.dataset?.memberIndex);
                        console.log('👁️ [blur event] input.name:', this.name);

                        if (idNumber.length >= 9) {
                            console.log('✅ [blur event] استدعاء fetchFamilyMemberData');
                            window.fetchFamilyMemberData(idNumber, memberIndex, formContainer);
                        }
                    });

                    console.log(`✅ [attachFamilyMemberIdListeners] تم ربط المستمعات للحقل ${index}`);
                }
            });

            console.log('✅ [attachFamilyMemberIdListeners] اكتمل ربط المستمعات');
        };

        // تفعيل المستمعات عند تحميل الصفحة
        setTimeout(() => {
            window.attachFamilyMemberIdListeners();
        }, 500);

        // دالة إضافة فرد جديد - محدثة مع التحقق من الأخطاء
        window.addFamilyMember = function addFamilyMember() {
            try {
                console.log('🚀 بدء تنفيذ addFamilyMember');

                const container = document.getElementById('familyMembersContainer');
                if (!container) {
                    console.error('❌ لم يتم العثور على familyMembersContainer');
                    return;
                }

                let forms = container.querySelectorAll('.family-member-form:not(.d-none)');
                let template = document.getElementById('familyMemberTemplate');

                if (!template) {
                    console.error('❌ لم يتم العثور على familyMemberTemplate');
                    return;
                }

                let newIndex = forms.length;
                console.log(`📝 إنشاء نموذج جديد برقم: ${newIndex}`);

                let clone = template.cloneNode(true);
                clone.classList.remove('d-none');
                clone.removeAttribute('id');
                clone.setAttribute('data-member-index', newIndex);

                // تحديث أسماء الحقول والفهارس
                clone.querySelectorAll('[name]').forEach(function(input) {
                    input.name = input.name.replace(/family_members\[template\]/g, `family_members[${newIndex}]`);
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

                // 🔧 إزالة data-fetch-listener-attached من النسخة المستنسخة
                const clonedIdInput = clone.querySelector('.family-member-id-input');
                if (clonedIdInput) {
                    delete clonedIdInput.dataset.fetchListenerAttached;
                    clonedIdInput.dataset.memberIndex = newIndex;
                    clonedIdInput.classList.remove('is-valid', 'is-invalid');
                    console.log(`🔧 [addFamilyMember] تم تنظيف data-fetch-listener-attached للنموذج ${newIndex}`);
                }
                const clonedStatusSpan = clone.querySelector('.family-member-search-status');
                if (clonedStatusSpan) {
                    clonedStatusSpan.dataset.memberIndex = newIndex;
                }

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
                                window.reindexFamilyMembers();

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
                console.log(`✅ تم توليد نموذج ${newIndex}: data-upload-zone = family_${newIndex}`);

                // تأكيد أن النموذج أصبح مرئياً
                setTimeout(() => {
                    if (clone.classList.contains('d-none')) {
                        clone.classList.remove('d-none');
                        console.log('🔧 إزالة d-none من النموذج المُنشأ');
                    }
                }, 100);

                window.reindexFamilyMembers();

                // إعداد معالجات الرفع للنموذج الجديد
                if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
                    setTimeout(() => {
                        window.setupDocumentUploadHandlersForMember(clone, newIndex);
                    }, 200);
                }

                // 🆕 تفعيل مراقبة رقم الهوية للنموذج الجديد
                setTimeout(() => {
                    if (typeof window.validateIdAndToggleSelect === 'function') {
                        window.validateIdAndToggleSelect(clone);
                    }
                }, 300);

                // 🆕 تفعيل الجلب التلقائي للنموذج الجديد
                setTimeout(() => {
                    console.log(`🔧 [addFamilyMember] استدعاء attachFamilyMemberIdListeners للنموذج ${newIndex}`);
                    if (typeof window.attachFamilyMemberIdListeners === 'function') {
                        window.attachFamilyMemberIdListeners();
                        console.log(`✅ [addFamilyMember] تم تفعيل الجلب التلقائي للنموذج ${newIndex}`);
                    }
                }, 400);

                return true;
            } catch (error) {
                console.error('❌ خطأ في addFamilyMember:', error);
                return false;
            }
        };

        // دالة إعادة الفهرسة مع تحديث window.allDocs
        window.reindexFamilyMembers = function reindexFamilyMembers() {
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

                // 🆕 تحديث data-member-index لحقول الجلب التلقائي
                const idInput = form.querySelector('.family-member-id-input');
                if (idInput) idInput.dataset.memberIndex = idx;
                const statusSpan = form.querySelector('.family-member-search-status');
                if (statusSpan) statusSpan.dataset.memberIndex = idx;

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

                // 🆕 تطبيق تحسينات DeviceImageSource بعد إعداد المعالجات المخصصة
                setTimeout(() => {
                    if (window.DeviceImageSource && window.DeviceImageSource.enhance) {
                        console.log('🔧 [FamilyMember] إعادة تفعيل DeviceImageSource بعد إعادة الفهرسة');
                        try {
                            window.DeviceImageSource.enhance();
                        } catch (error) {
                            console.error('❌ [FamilyMember] خطأ في تطبيق DeviceImageSource:', error);
                        }
                    } else {
                        console.log('⚠️ [FamilyMember] DeviceImageSource غير متوفر');
                    }
                }, 500);
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

            // 🆕 إضافة تشخيص لنظام DeviceImageSource
            console.log('🔍 [FamilyMember] تفحص DeviceImageSource:', {
                deviceSourceAvailable: !!window.DeviceImageSource,
                enhanced: fileInput.dataset.deviceEnhanced,
                inputId: fileInput.id,
                formIndex: idx
            });

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
                        typeText: doc.typeText,
                        hasFile: !!doc.file,
                        hasProcessedFile: !!doc.processedFile,
                        fileName: doc.file?.name,
                        processedFileName: doc.processedFile?.name
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
                const isMobileUserAgent = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const isMobileScreen = window.innerWidth <= 768;
                const hasTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

                // كشف اللابتوب: شائع أن يكون جهاز ويندوز أو ماك مع شاشة بين 768 و 1400 بكسل وليس تابلت
                const isLaptopUserAgent = /Windows NT|Macintosh|Linux/i.test(navigator.userAgent) && !isMobileUserAgent;
                const isLaptopScreen = window.innerWidth > 768 && window.innerWidth <= 1400;
                const isLaptop = isLaptopUserAgent && isLaptopScreen && !hasTouch;

                const isMobile = isMobileUserAgent || isMobileScreen || hasTouch;

                let deviceType = 'desktop';
                if (isMobile) deviceType = 'mobile';
                else if (isLaptop) deviceType = 'laptop';

                console.log(`📱 [isMobileDevice] تحليل الجهاز:`, {
                    userAgent: isMobileUserAgent,
                    screenSize: isMobileScreen,
                    touchSupport: hasTouch,
                    isLaptopUserAgent,
                    isLaptopScreen,
                    isLaptop,
                    finalResult: deviceType,
                    screenWidth: window.innerWidth,
                    screenHeight: window.innerHeight
                });

                return deviceType;
            }

            // دالة إعادة تهيئة حالة الرفع - محدثة مع نظام كشف الجهاز
            function resetUploadState() {
                fileDialogOpen = false;

                // حماية إضافية: إذا لم يوجد fileInput أو parentNode، فقط أعد تعيين المتغيرات واخرج
                if (!fileInput || !fileInput.parentNode) {
                    console.warn(`⚠️ [resetUploadState] fileInput أو parentNode غير موجود، سيتم فقط إعادة تعيين المتغيرات`);
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
                        if (fileInput) fileInput.value = '';

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
                    allDocsContent: window.allDocs instanceof Map ? Array.from(window.allDocs.entries()) : 'غير صالح',
                    isMobile: window.innerWidth <= 768,
                    screenWidth: window.innerWidth,
                    screenHeight: window.innerHeight
                });

                preview.innerHTML = '';

                if (documents.length === 0) {
                    console.log(`📝 [renderDocuments] لا توجد مرفقات للعرض في المنطقة: ${idx}`);
                    preview.style.display = 'none';
                    return;
                }

                // إعداد الحاوية بشكل متجاوب
                const deviceType = isMobileDevice(); // 'mobile' | 'laptop' | 'desktop'
                const isMobile = deviceType === 'mobile' || deviceType === 'laptop';
                console.log(`📱 [renderDocuments] حالة الجهاز: ${deviceType}`);

                preview.style.display = 'block';
                preview.style.width = '100%';
                preview.style.overflow = 'visible';

                // تطبيق تحسينات الجوال أو اللابتوب
                if (isMobile) {
                    preview.style.padding = '10px';
                    preview.style.margin = '10px 0';
                    preview.classList.add('mobile-optimized');
                    console.log('📱 تم تطبيق تحسينات الجوال/اللابتوب على منطقة العرض');
                }

                const cardsWrapper = document.createElement('div');

                if (isMobile) {
                    cardsWrapper.className = 'd-flex flex-column gap-3';
                    cardsWrapper.style.cssText = `
                        margin-top: 15px;
                        width: 100%;
                        overflow-x: visible;
                        -webkit-overflow-scrolling: touch;
                        flex-direction: column;
                        gap: 15px;
                        padding: 0;
                    `;
                    console.log('📱 تم إعداد الحاوية للجوال/اللابتوب - عمودي');
                } else {
                    cardsWrapper.className = 'd-flex flex-wrap gap-3 justify-content-start';
                    cardsWrapper.style.cssText = `
                        margin-top: 15px;
                        width: 100%;
                        overflow-x: auto;
                        -webkit-overflow-scrolling: touch;
                    `;
                    console.log('💻 تم إعداد الحاوية للشاشة الكبيرة - أفقي');
                }

                documents.forEach((doc, docIdx) => {
                    // حماية: تجاهل أي بطاقة ناقصة البيانات الأساسية
                    let _fileForDisplay = doc.processedFile || doc.file;
                    const validFileName = _fileForDisplay && typeof _fileForDisplay.name === 'string' && _fileForDisplay.name.trim() !== '';
                    const validTypeText = doc.typeText && typeof doc.typeText === 'string' && doc.typeText.trim() !== '';
                    if (!validFileName || !validTypeText) {
                        console.warn(`⚠️ [renderDocuments] تجاهل بطاقة ناقصة:`, {doc, docIdx});
                        return; // لا تعرض البطاقة
                    }
                    console.log(`🎨 [renderDocuments] عرض المرفق ${docIdx + 1}/${documents.length}:`, {
                        docName: doc.docName,
                        typeText: doc.typeText,
                        personId: doc.personId,
                        fileId: doc.fileId,
                        personKey: doc.personKey,
                        hasFile: !!doc.file,
                        hasProcessedFile: !!doc.processedFile,
                        fileType: doc.file?.type,
                        processedFileType: doc.processedFile?.type,
                        fileName: doc.file?.name,
                        processedFileName: doc.processedFile?.name
                    });

                    const card = document.createElement('div');
                    card.className = 'attachment-card card border-0 shadow-sm';
                    if (doc.isSuccess) {
                        const successBadge = document.createElement('div');
                        successBadge.className = 'text-success fw-bold mb-2';
                        successBadge.style.fontSize = '1.1rem';
                        successBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i> تمت المعالجة بنجاح';
                        card.appendChild(successBadge);
                    }
                    // تطبيق تصميم متجاوب للجوال/اللابتوب
                    if (isMobile) {
                        card.style.cssText = `
                            width: 100%;
                            max-width: 100%;
                            border-radius: 12px;
                            overflow: hidden;
                            transition: all 0.3s ease;
                            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                            border: 1px solid #e3e6f0 !important;
                            margin-bottom: 15px;
                            display: block;
                            visibility: visible;
                            opacity: 1;
                            flex-shrink: 0;
                        `;
                        console.log(`📱 بطاقة ${docIdx + 1}: تم تطبيق تصميم الجوال/اللابتوب`);
                    } else {
                        card.style.cssText = `
                            width: 180px;
                            max-width: 180px;
                            min-width: 180px;
                            border-radius: 12px;
                            overflow: hidden;
                            transition: all 0.3s ease;
                            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                            border: 1px solid #e3e6f0 !important;
                            flex-shrink: 0;
                        `;
                        console.log(`💻 بطاقة ${docIdx + 1}: تم تطبيق تصميم الشاشة الكبيرة`);
                    }

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
                            <span class="text-truncate">${doc.typeText || 'وثيقة'}</span>
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

                        // التحقق من نوع الملف - إما processedFile أو file العادي
                        const fileToDisplay = doc.processedFile || doc.file;

                        try {
                            // إنشاء URL للصورة
                            if (fileToDisplay instanceof File || fileToDisplay instanceof Blob) {
                                img.src = URL.createObjectURL(fileToDisplay);
                                console.log(`🖼️ [renderDocuments] تم إنشاء URL للصورة:`, {
                                    fileName: fileToDisplay.name,
                                    size: fileToDisplay.size,
                                    type: fileToDisplay.type
                                });
                            } else {
                                console.warn(`⚠️ [renderDocuments] نوع ملف غير مدعوم للعرض:`, typeof fileToDisplay);
                                // استخدام أيقونة افتراضية في حالة عدم القدرة على عرض الصورة
                                img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDIwMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTIwIiBmaWxsPSIjZjhmOWZhIi8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNmM3NTdkIj7YtdmI2LHYqTwvdGV4dD4KPC9zdmc+';
                            }
                        } catch (error) {
                            console.error(`❌ [renderDocuments] خطأ في إنشاء URL للصورة:`, error);
                            img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDIwMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTIwIiBmaWxsPSIjZjhmOWZhIi8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNmM3NTdkIj7YrtmC2KMg2YHZiiDYp9mE2LXZiNix2Kk8L3RleHQ+Cjwvc3ZnPg==';
                        }

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

                        // تنظيف URL بعد التحميل
                        img.onload = function() {
                            if (img.src.startsWith('blob:')) {
                                setTimeout(() => {
                                    URL.revokeObjectURL(img.src);
                                }, 1000); // تأخير قصير لضمان عرض الصورة
                            }
                        };

                        // معالجة خطأ التحميل
                        img.onerror = function() {
                            console.error(`❌ [renderDocuments] فشل في تحميل الصورة`);
                            this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDIwMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTIwIiBmaWxsPSIjZjhmOWZhIi8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZGM5NTQ1Ij7YrtmC2KMg2YHZiiDYp9mE2LnYsdmCPC90ZXh0Pgo8L3N2Zz4=';
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


                    // اسم الملف مع حماية ضد القيم غير المعروفة
                    const fileName = document.createElement('div');
                    fileName.className = 'file-name text-muted small mb-2 text-truncate';
                    fileName.style.fontWeight = '500';
                    const fileForDisplay = doc.processedFile || doc.file;
                    let displayName = '';
                    if (fileForDisplay && fileForDisplay.name) {
                        displayName = fileForDisplay.name;
                    } else if (doc.docName && typeof doc.docName === 'string' && doc.docName.trim() !== '') {
                        displayName = doc.docName;
                    } else {
                        displayName = '';
                    }
                    fileName.textContent = displayName !== '' ? displayName : '—';
                    fileName.title = displayName !== '' ? displayName : 'لا يوجد اسم ملف';
                    cardBody.appendChild(fileName);

                    // نوع الوثيقة بشكل واضح
                    if (doc.typeText && typeof doc.typeText === 'string' && doc.typeText.trim() !== '') {
                        const typeTextDiv = document.createElement('div');
                        typeTextDiv.className = 'doc-type-text text-primary small mb-2';
                        typeTextDiv.textContent = doc.typeText;
                        cardBody.appendChild(typeTextDiv);
                    }

                    // معلومات إضافية
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'file-info small text-muted mb-3';
                    let fileSize = '—';
                    if (fileForDisplay && typeof fileForDisplay.size === 'number') {
                        fileSize = (fileForDisplay.size / 1024).toFixed(1) + ' KB';
                    }
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

                // تحسينات إضافية للجوال/اللابتوب
                if (deviceType === 'mobile' || deviceType === 'laptop') {
                    preview.style.padding = '10px';
                    preview.style.margin = '10px 0';
                    cardsWrapper.style.width = '100%';
                    cardsWrapper.style.padding = '0';

                    // التأكد من أن البطاقات مرئية
                    const cards = cardsWrapper.querySelectorAll('.attachment-card');
                    cards.forEach(card => {
                        card.style.display = 'block';
                        card.style.visibility = 'visible';
                        card.style.opacity = '1';
                    });

                    console.log(`📱 [renderDocuments] تم تطبيق تحسينات الجوال/اللابتوب - عدد البطاقات: ${cards.length}`);
                }

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

                // 🆕 حفظ دالة التحقق للاستخدام مع deviceTypeOpenButton
                form._familyCanUploadFunction = canUploadDocument;
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
                    // 🆕 التحقق من وجود نظام DeviceImageSource ونوع الجهاز
                    if (window.DeviceImageSource && typeof window.DeviceImageSource.detectDeviceType === 'function') {
                        const deviceInfo = window.DeviceImageSource.detectDeviceType();
                        console.log('📱 [docTypeSelect] نوع الجهاز المكتشف:', deviceInfo);

                        // للأجهزة المحمولة: التحقق من تفعيل المودال
                        if ((deviceInfo.isMobile || deviceInfo.isTablet) && window.showImageSourceModal) {
                            console.log('🎯 [docTypeSelect] جهاز محمول مكتشف، سيتم استخدام مودال الاختيار');
                            // السماح للنظام المحسن بالعمل
                            fileInput.click();
                        } else {
                            // للشاشات الكبيرة: استخدام النظام التقليدي
                            fileInput.click();
                        }
                    } else {
                        // إذا لم يكن النظام المحسن متوفراً
                        console.log('🔧 [docTypeSelect] استخدام النظام التقليدي لفتح حوار الملف');
                        fileInput.click();
                    }
                    console.log(`🎯 [docTypeSelect] تم فتح حوار اختيار الملف`);
                } catch (error) {
                    console.error(`❌ [docTypeSelect] خطأ في فتح حوار الملف:`, error);
                    resetUploadState();
                }
            });

            // دالة إعداد معالج الملف مع إعادة التهيئة
            function setupFileInputHandler(input) {
            // دالة إغلاق جميع المودالات/الكروبر/الباك دروب عند الفشل أو التداخل
            function forceCloseAllModals() {
                // إغلاق جميع المودالات المفتوحة والـ backdrop
                document.querySelectorAll('.modal.show, .modal-backdrop').forEach(el => {
                    el.classList.remove('show');
                    el.classList.add('fade');
                    el.style.display = 'none';
                    el.remove();
                });
                // إغلاق cropper modal إذا كان متاحًا
                if (window.closeCropperModal && typeof window.closeCropperModal === 'function') {
                    try { window.closeCropperModal(); } catch(e) {}
                }
                // إعادة تهيئة متغيرات الحالة
                fileDialogOpen = false;
            }
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
                            showProcessingState(e.target.result);
                            setTimeout(() => {
                                if (typeof window.showCropperModal === 'function') {
                                    // حماية: منع تكرار فتح cropper modal إذا كان هناك واحد مفتوح
                                    if (document.querySelector('.modal.show')) {
                                        console.warn('يوجد مودال مفتوح بالفعل، لن يتم فتح مودال جديد');
                                        return;
                                    }
                                    console.log('🔍 [fileInput] دالة المقص متوفرة، بدء عملية القص...');
                                    const existingBackdrops = document.querySelectorAll('.modal-backdrop');
                                    existingBackdrops.forEach(backdrop => backdrop.remove());
                                    // تعديل: الكولباك يأخذ croppedFile و cropperError
                                    window.showCropperModal(file, function(croppedFile, cropperError) {
                                        // حماية من undefined أو حالة غير معروفة
                                        if (!croppedFile || cropperError || typeof croppedFile !== 'object' || !croppedFile.size) {
                                            forceCloseAllModals();
                                            preview.innerHTML = '';
                                            docTypeSelect.value = '';
                                            resetUploadState();
                                            if (typeof Swal !== 'undefined') {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'فشل معالجة الصورة',
                                                    text: (cropperError && cropperError.message) ? cropperError.message : 'حدث خطأ أثناء قص الصورة أو تم الإلغاء. لم يتم إضافة الوثيقة.',
                                                    timer: 3500,
                                                    showConfirmButton: true
                                                });
                                            }
                                            return;
                                        }
                                        // تعيين اسم للملف المقصوص إذا لم يكن موجودًا أو غير صالح
                                        if (!croppedFile.name || croppedFile.name === 'blob' || croppedFile.name === 'undefined') {
                                            const ext = file.name.substring(file.name.lastIndexOf('.'));
                                            croppedFile.name = `${typeVal}_${fileId}_${personId}_cropped${ext}`;
                                        }
                                        // تحقق من نجاح القص: يجب أن يكون croppedFile صالحًا ولا يوجد cropperError
                                        if (croppedFile && !cropperError && fileId && personId && croppedFile.name && croppedFile.name.includes('_cropped') && croppedFile.name !== 'undefined') {
                                            const croppedDocName = `${typeVal}_${fileId}_${personId}${croppedFile.name.substring(croppedFile.name.lastIndexOf('.'))}`;
                                            // تحقق من عدم وجود تكرار لنفس docName
                                            if (!documents.some(d => d.docName === croppedDocName)) {
                                                const docObj = {
                                                    type: typeVal,
                                                    typeText: docTypeSelect.options[docTypeSelect.selectedIndex]?.text || 'وثيقة',
                                                    file: file,
                                                    processedFile: croppedFile,
                                                    docName: croppedDocName,
                                                    personId: personId,
                                                    fileId: fileId,
                                                    personKey: personKey,
                                                    isSuccess: true
                                                };
                                                documents.push(docObj);
                                                if (window.allDocs && window.allDocs instanceof Map) {
                                                    let docsArr = window.allDocs.get(personKey) || [];
                                                    if (!docsArr.some(d => d.docName === croppedDocName)) {
                                                        docsArr.push(docObj);
                                                        window.allDocs.set(personKey, docsArr);
                                                    }
                                                }
                                                syncWithAllDocs();
                                                renderDocuments();
                                            } else {
                                                console.warn('⚠️ [fileInput] محاولة إضافة وثيقة مكررة، تم الإلغاء');
                                            }
                                            docTypeSelect.value = '';
                                            resetUploadState();
                                            forceCloseAllModals();
                                            console.log(`✅ [fileInput] نجح القص وإنشاء كائن الوثيقة:`);
                                        } else {
                                            // فشل القص أو خطأ أو اسم غير صالح
                                            forceCloseAllModals();
                                            preview.innerHTML = '';
                                            docTypeSelect.value = '';
                                            resetUploadState();
                                            // رسالة فشل واضحة
                                            if (typeof Swal !== 'undefined') {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'فشل معالجة الصورة',
                                                    text: (cropperError && cropperError.message) ? cropperError.message : 'حدث خطأ أثناء قص الصورة أو تم الإلغاء. لم يتم إضافة الوثيقة.',
                                                    timer: 3500,
                                                    showConfirmButton: true
                                                });
                                            }
                                        }
                                    });
                                } else {
                                    console.error(`❌ [fileInput] دالة المقص غير متوفرة - window.showCropperModal`);
                                    forceCloseAllModals();
                                    console.log('🔧 [fileInput] محاولة المتابعة بدون قص...');
                                    // تحقق من عدم وجود تكرار لنفس docName
                                    if (!documents.some(d => d.docName === docName)) {
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
                                        documents.push(docObj);
                                        if (window.allDocs && window.allDocs instanceof Map) {
                                            let docsArr = window.allDocs.get(personKey) || [];
                                            if (!docsArr.some(d => d.docName === docName)) {
                                                docsArr.push(docObj);
                                                window.allDocs.set(personKey, docsArr);
                                            }
                                        }
                                        syncWithAllDocs();
                                        renderDocuments();
                                    } else {
                                        console.warn('⚠️ [fileInput] محاولة إضافة وثيقة مكررة بدون قص، تم الإلغاء');
                                    }
                                    docTypeSelect.value = '';
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

            // مستمع تغيير حجم الشاشة لإعادة ترتيب العرض
            const resizeHandler = function() {
                console.log(`📐 [resizeHandler] تغيير حجم الشاشة: ${window.innerWidth}x${window.innerHeight}`);
                if (documents.length > 0) {
                    setTimeout(() => {
                        renderDocuments();
                    }, 300);
                }
            };

            window.addEventListener('resize', resizeHandler);
            window.addEventListener('orientationchange', function() {
                setTimeout(resizeHandler, 500);
            });

            // التحقق من تطبيق التحسينات بعد إعداد المعالجات المخصصة
            setTimeout(() => {
                if (window.DeviceImageSource && !fileInput.dataset.deviceEnhanced) {
                    console.log('🔧 [FamilyMember] تطبيق تحسينات DeviceImageSource يدوياً على الفرد:', idx);
                    try {
                        window.DeviceImageSource.enhance();
                        // تأكيد التطبيق
                        if (fileInput && !fileInput.dataset.deviceEnhanced) {
                            fileInput.dataset.deviceEnhanced = 'true';
                        }
                    } catch (error) {
                        console.error('❌ [FamilyMember] خطأ في تطبيق DeviceImageSource على الفرد:', error);
                    }
                } else if (window.DeviceImageSource) {
                    console.log('ℹ️ [FamilyMember] DeviceImageSource مطبق مسبقاً على الفرد:', idx);
                } else {
                    console.log('⚠️ [FamilyMember] DeviceImageSource غير متوفر');
                }
            }, 1000);

        }; // إغلاق دالة window.setupDocumentUploadHandlersForMember

        // ربط زر إضافة فرد - تأكيد العمل
        const addFamilyMemberBtn = document.getElementById('addFamilyMember');
        if (addFamilyMemberBtn) {
            // إزالة أي مستمعات سابقة لتجنب التكرار
            addFamilyMemberBtn.replaceWith(addFamilyMemberBtn.cloneNode(true));
            const newBtn = document.getElementById('addFamilyMember');

            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('🖱️ تم الضغط على زر إضافة فرد');

                if (typeof window.addFamilyMember === 'function') {
                    const result = window.addFamilyMember();
                    if (result) {
                        console.log('✅ تم إضافة فرد الأسرة بنجاح');
                    } else {
                        console.error('❌ فشل في إضافة فرد الأسرة');
                    }
                } else {
                    console.error('❌ دالة addFamilyMember غير متوفرة');
                }
            });

            console.log('✅ تم ربط زر إضافة فرد بنجاح');
        } else {
            console.error('❌ لم يتم العثور على زر إضافة فرد');
        }

        // تحديث: 2025-01-07 - تم إصلاح جميع مشاكل الـ Syntax وعرض الصور
        console.log('✅ [familyMember] تم تحميل جميع المعالجات بنجاح');

        // إضافة cache buster للتأكد من تحديث CSS على الخادم
        const cacheBuster = Date.now();
        console.log(`🔄 [familyMember] Cache Buster: ${cacheBuster}`);

        // إضافة CSS للتأكد من العرض الصحيح على الجوال
        const mobileStyles = document.createElement('style');
        mobileStyles.id = `family-member-mobile-styles-${cacheBuster}`;
        mobileStyles.setAttribute('data-version', '2025-01-07-v2');


        // إزالة الأنماط القديمة إذا كانت موجودة
        const oldStyles = document.querySelectorAll('[id^="family-member-mobile-styles"]');
        oldStyles.forEach(style => style.remove());

        document.head.appendChild(mobileStyles);
        console.log(`📱 تم إضافة تحسينات CSS للجوال - إصدار محسن ${cacheBuster}`);

        // تشخيص إضافي لضمان عمل النظام
        setTimeout(() => {
            console.log('🔍 [familyMember] تشخيص النظام:', {
                allDocsAvailable: !!(window.allDocs && window.allDocs instanceof Map),
                allDocsSize: window.allDocs instanceof Map ? window.allDocs.size : 'غير متوفر',
                showCropperModalAvailable: typeof window.showCropperModal === 'function',
                deviceImageSourceAvailable: !!window.DeviceImageSource,
                deviceImageCaptureAvailable: !!window.DeviceImageCapture,
                showImageSourceModalAvailable: typeof window.showImageSourceModal === 'function',
                currentTimestamp: Date.now(),
                isMobileDevice: window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
            });

            // إجراء تشخيص إضافي للخادم الحقيقي
            const serverDiagnostics = function() {
                console.log('🏥 [تشخيص الخادم] بدء التشخيص المتقدم...');

                // فحص منطقة العرض
                const previews = document.querySelectorAll('.mainDocumentPreview');
                console.log(`📋 مناطق العرض الموجودة: ${previews.length}`);

                previews.forEach((preview, index) => {
                    const style = window.getComputedStyle(preview);
                    console.log(`منطقة ${index + 1}:`, {
                        display: style.display,
                        visibility: style.visibility,
                        opacity: style.opacity,
                        width: style.width,
                        height: style.height
                    });

                    // تطبيق إصلاح فوري إذا لزم الأمر
                    if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) < 0.1) {
                        console.log('🔧 تطبيق إصلاح فوري للمنطقة...');
                        preview.style.cssText = `
                            display: block !important;
                            visibility: visible !important;
                            opacity: 1 !important;
                            width: 100% !important;
                            position: relative !important;
                            margin: 10px 0 !important;
                            padding: 10px !important;
                            background: #f8f9fa !important;
                            border: 1px solid #28a745 !important;
                            border-radius: 8px !important;
                        `;
                    }
                });

                // فحص البطاقات
                const cards = document.querySelectorAll('.attachment-card');
                console.log(`🎴 البطاقات الموجودة: ${cards.length}`);

                cards.forEach((card, index) => {
                    const style = window.getComputedStyle(card);
                    const isVisible = style.display !== 'none' &&
                                     style.visibility !== 'hidden' &&
                                     parseFloat(style.opacity) > 0;

                    console.log(`بطاقة ${index + 1}:`, {
                        visible: isVisible,
                        width: style.width,
                        display: style.display
                    });

                    // إصلاح فوري للبطاقات المخفية
                    if (!isVisible) {
                        console.log('🔧 إصلاح البطاقة المخفية...');
                        card.style.cssText = `
                            display: block !important;
                            visibility: visible !important;
                            opacity: 1 !important;
                            width: 100% !important;
                            margin-bottom: 15px !important;
                            position: relative !important;
                        `;
                    }
                });

                // تنظيف modal backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length > 1) {
                    console.log(`🧹 تنظيف ${backdrops.length} backdrop زائد...`);
                    backdrops.forEach((backdrop, index) => {
                        if (index > 0) backdrop.remove();
                    });
                }

                console.log('✅ [تشخيص الخادم] اكتمل التشخيص المتقدم');
            };

            // تشغيل التشخيص
            serverDiagnostics();

            // إعادة التشغيل كل 10 ثوانٍ للتأكد
            setInterval(serverDiagnostics, 10000);
        }, 2000);

        // 🆕 دالة لتعطيل/تفعيل قائمة نوع الوثيقة بناءً على رقم الهوية
        window.setupIdValidationForDocumentSelect = function() {
            // مراقبة جميع نماذج أفراد الأسرة الموجودة والمستقبلية
            const container = document.getElementById('familyMembersContainer');
            if (!container) return;

            // استخدام MutationObserver لمراقبة إضافة نماذج جديدة
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && node.classList && node.classList.contains('family-member-form')) {
                            window.validateIdAndToggleSelect(node);
                        }
                    });
                });
            });

            observer.observe(container, {
                childList: true,
                subtree: false
            });

            // معالجة النماذج الموجودة حالياً
            document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                window.validateIdAndToggleSelect(form);
            });
        }

        // دالة التحقق من رقم الهوية وتعطيل/تفعيل القائمة
        window.validateIdAndToggleSelect = function(form) {
            const personIdInput = form.querySelector('input[name*="[person_id]"]');
            const docTypeSelect = form.querySelector('.mainDocumentTypeSelect');

            if (!personIdInput || !docTypeSelect) return;

            // دالة التحقق
            function checkAndToggle() {
                const idValue = personIdInput.value.trim();
                const isValid = /^\d{9}$/.test(idValue); // بالضبط 9 أرقام

                if (isValid) {
                    // تفعيل القائمة
                    docTypeSelect.disabled = false;
                    docTypeSelect.classList.remove('disabled');
                    docTypeSelect.style.opacity = '1';
                    docTypeSelect.style.cursor = 'pointer';
                } else {
                    // تعطيل القائمة
                    docTypeSelect.disabled = true;
                    docTypeSelect.classList.add('disabled');
                    docTypeSelect.style.opacity = '0.5';
                    docTypeSelect.style.cursor = 'not-allowed';
                    docTypeSelect.selectedIndex = 0; // إعادة تعيين الاختيار
                }
            }

            // التحقق الأولي
            checkAndToggle();

            // مراقبة التغييرات في حقل رقم الهوية
            personIdInput.addEventListener('input', checkAndToggle);
            personIdInput.addEventListener('change', checkAndToggle);

            // منع فتح القائمة إذا كانت معطلة
            docTypeSelect.addEventListener('mousedown', function(e) {
                if (this.disabled) {
                    e.preventDefault();
                    const idValue = personIdInput.value.trim();
                    let message = 'يجب إدخال رقم هوية صحيح (9 أرقام) قبل اختيار نوع الوثيقة';

                    if (!idValue) {
                        message = 'يجب إدخال رقم الهوية أولاً';
                    } else if (idValue.length < 9) {
                        message = `رقم الهوية يجب أن يكون 9 أرقام (تم إدخال ${idValue.length} فقط)`;
                    } else if (idValue.length > 9) {
                        message = `رقم الهوية يجب أن يكون 9 أرقام (تم إدخال ${idValue.length})`;
                    } else if (!/^\d+$/.test(idValue)) {
                        message = 'رقم الهوية يجب أن يحتوي على أرقام فقط';
                    }

                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: message,
                        confirmButtonText: 'حسناً'
                    });
                }
            });
        }

        // تفعيل المراقبة عند تحميل الصفحة
        setTimeout(function() {
            window.setupIdValidationForDocumentSelect();
        }, 1000);

    }); // نهاية DOMContentLoaded
        </script>
@endpush

