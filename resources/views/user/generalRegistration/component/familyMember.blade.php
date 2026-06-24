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
                        console.log('🔍 [fetchFamilyMemberData] جميع الحقول المتاحة:', Object.keys(person));
                        console.log('📅 [fetchFamilyMemberData] بيانات تاريخ الميلاد والجنس:', {
                            CI_BIRTH_DT: person.CI_BIRTH_DT,
                            CI_SEX_CD: person.CI_SEX_CD,
                            CI_BIRTH_DATE: person.CI_BIRTH_DATE,
                            CI_SEX: person.CI_SEX,
                            CI_GENDER: person.CI_GENDER
                        });

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
                        console.log('📅 [fetchFamilyMemberData] محاولة تعيين تاريخ الميلاد:', {
                            hasBirthDateInput: !!birthDateInput,
                            birthDateValue: person.CI_BIRTH_DT
                        });

                        if (birthDateInput && person.CI_BIRTH_DT) {
                            // تحويل التاريخ إلى الصيغة المطلوبة YYYY-MM-DD
                            let birthDate = person.CI_BIRTH_DT;
                            console.log('📅 [fetchFamilyMemberData] تاريخ الميلاد الخام:', birthDate);

                            if (birthDate) {
                                // محاولة تحويل التاريخ
                                const dateObj = new Date(birthDate);
                                console.log('📅 [fetchFamilyMemberData] كائن التاريخ:', dateObj, 'صالح؟', !isNaN(dateObj.getTime()));

                                if (!isNaN(dateObj.getTime())) {
                                    const year = dateObj.getFullYear();
                                    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                                    const day = String(dateObj.getDate()).padStart(2, '0');
                                    const formattedDate = `${year}-${month}-${day}`;

                                    console.log('📅 [fetchFamilyMemberData] قبل التعيين - birthDateInput.value:', birthDateInput.value);
                                    birthDateInput.value = formattedDate;
                                    birthDateInput.classList.add('is-valid');
                                    console.log('✅ [fetchFamilyMemberData] بعد التعيين - birthDateInput.value:', birthDateInput.value);

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
                                } else {
                                    console.error('❌ [fetchFamilyMemberData] تاريخ الميلاد غير صالح:', birthDate);
                                }
                            }
                        } else {
                            console.warn('⚠️ [fetchFamilyMemberData] لم يتم تعيين تاريخ الميلاد:', {
                                hasBirthDateInput: !!birthDateInput,
                                hasBirthDateData: !!person.CI_BIRTH_DT
                            });
                        }

                        // الجنس (إذا توفر)
                        console.log('⚧ [fetchFamilyMemberData] محاولة تعيين الجنس:', {
                            hasGenderSelect: !!genderSelect,
                            genderValue: person.CI_SEX_CD
                        });

                        if (genderSelect && person.CI_SEX_CD) {
                            console.log('⚧ [fetchFamilyMemberData] قبل التعيين - genderSelect.value:', genderSelect.value);
                            // 1 = ذكر، 2 = أنثى
                            genderSelect.value = person.CI_SEX_CD;
                            genderSelect.classList.add('is-valid');
                            console.log('✅ [fetchFamilyMemberData] بعد التعيين - genderSelect.value:', genderSelect.value, 'person.CI_SEX_CD:', person.CI_SEX_CD);
                        } else {
                            console.warn('⚠️ [fetchFamilyMemberData] لم يتم تعيين الجنس:', {
                                hasGenderSelect: !!genderSelect,
                                hasGenderData: !!person.CI_SEX_CD
                            });
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
        // دالة setupDocumentUploadHandlersForMember المركزية (تم إفراغها لأن documentUpload.blade.php يعالج كل شيء الآن)
        window.setupDocumentUploadHandlersForMember = function(form, idx) {};

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

