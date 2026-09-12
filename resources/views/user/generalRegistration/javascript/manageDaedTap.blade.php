@push('scriptsCodeUserRegistration')
    {{-- التحكم في بوابة المتوفين --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sectionSelect = document.querySelector('select[name="data_section_id"]');
            const formTabs = document.getElementById('formTabs');
            const deceasedTab = document.querySelector('#deceased-tab').parentElement;
            const basicTab = document.querySelector('#basic-tab').parentElement;
            const familyMembersTab = document.querySelector('#family-members-tab').parentElement;
            const deceasedPane = document.querySelector('#deceased');

            // دالة للتحكم في ظهور ونقل بوابة المتوفين
            function handleDeceasedTab() {
                const ispersonsSection = sectionSelect.value === '1'; // افترض أن قيمة 1 تمثل قسم الأيتام

                // إخفاء/إظهار البوابة
                deceasedTab.style.display = ispersonsSection ? '' : 'none';
                deceasedPane.style.display = ispersonsSection ? '' : 'none';

                if (ispersonsSection) {
                    // نقل بوابة المتوفين بعد البيانات الأساسية
                    deceasedTab.remove();
                    basicTab.after(deceasedTab);

                    // تفعيل البوابة الأساسية إذا كانت بوابة المتوفين نشطة
                    if (deceasedPane.classList.contains('active')) {
                        const basicTabButton = document.querySelector('#basic-tab');
                        new bootstrap.Tab(basicTabButton).show();
                    }

                    // إضافة مستمع لتعبئة بيانات الأب في نموذج أفراد الأسرة
                    const fatherInputs = {
                        'father_first_name': '[second_name]', // الاسم الأول للأب -> الاسم الثاني للابن
                        'father_second_name': '[third_name]', // الاسم الثاني للأب -> الاسم الثالث للابن
                        'father_last_name': '[last_name]' // اسم عائلة الأب -> اسم عائلة الابن
                    };

                    Object.keys(fatherInputs).forEach(fatherField => {
                        const fatherInput = document.querySelector(`input[name="${fatherField}"]`);
                        if (fatherInput) {
                            fatherInput.addEventListener('input', function() {
                                const familyMembers = document.querySelectorAll(
                                    '.family-member-form');
                                familyMembers.forEach((form, index) => {
                                    const targetField = form.querySelector(
                                        `input[name="family_members[${index}]${fatherInputs[fatherField]}"]`
                                    );
                                    if (targetField) {
                                        targetField.value = this.value;
                                    }
                                });
                            });
                        }
                    });
                } else {
                    // إعادة بوابة المتوفين إلى موقعها الأصلي
                    deceasedTab.remove();
                    familyMembersTab.after(deceasedTab);
                }
            }

            // استدعاء الدالة عند تغيير القسم
            sectionSelect.addEventListener('change', handleDeceasedTab);

            // تنفيذ الدالة عند تحميل الصفحة
            handleDeceasedTab();

            // تحديث النماذج الجديدة عند إضافة فرد جديد
            const addFamilyMemberElement = document.getElementById('addFamilyMember');
            if (addFamilyMemberElement) {
                const originalAddFamilyMember = addFamilyMemberElement.onclick;
                addFamilyMemberElement.onclick = function() {
                    if (originalAddFamilyMember) {
                        originalAddFamilyMember.apply(this, arguments);
                    }

                if (sectionSelect.value === '1') {
                    const lastForm = document.querySelector('.family-member-form:last-child');
                    const formIndex = lastForm.querySelector('input[name*="[file_id]"]')
                        .name.match(/\[(\d+)\]/)[1];

                    // نسخ بيانات الأب إلى النموذج الجديد
                    const fatherData = {
                        'father_first_name': '[second_name]', // الاسم الأول للأب -> الاسم الثاني للابن
                        'father_second_name': '[third_name]', // الاسم الثاني للأب -> الاسم الثالث للابن
                        'father_last_name': '[last_name]' // اسم عائلة الأب -> اسم عائلة الابن
                    };

                    Object.keys(fatherData).forEach(fatherField => {
                        const fatherValue = document.querySelector(`input[name="${fatherField}"]`)
                            ?.value;
                        if (fatherValue) {
                            const targetField = lastForm.querySelector(
                                `input[name="family_members[${formIndex}]${fatherData[fatherField]}"]`
                            );
                            if (targetField) {
                                targetField.value = fatherValue;
                            }
                        }
                    });
                }
            };
            } else {
                console.warn('⚠️ [manageDaedTap] عنصر addFamilyMember غير موجود');
            }
        });
    </script>
    {{-- التحكم في إظهار وإخفاء نموذج ادخال الام + التعبئة التلقائية عند اختيار "ام متوفية" --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleMotherButton = document.getElementById('toggleMotherInfo');
            const motherInfoSection = document.getElementById('motherInfoSection');
            let isMotherSectionVisible = false;

            // دالة إظهار قسم الأم المتوفية
            function showMotherSection() {
                if (isMotherSectionVisible) return;
                motherInfoSection.style.display = 'block';
                motherInfoSection.style.opacity = '0';
                motherInfoSection.style.transform = 'translateY(-20px)';
                motherInfoSection.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    motherInfoSection.style.opacity = '1';
                    motherInfoSection.style.transform = 'translateY(0)';
                }, 10);
                toggleMotherButton.innerHTML = '<i class="fas fa-minus me-2"></i>إخفاء بيانات الأم المتوفية';
                toggleMotherButton.classList.remove('btn-primary');
                toggleMotherButton.classList.add('btn-danger');
                isMotherSectionVisible = true;
            }

            // دالة إخفاء قسم الأم المتوفية
            function hideMotherSection() {
                if (!isMotherSectionVisible) return;
                motherInfoSection.style.transition = 'all 0.3s ease';
                motherInfoSection.style.opacity = '0';
                motherInfoSection.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    motherInfoSection.style.display = 'none';
                }, 300);
                toggleMotherButton.innerHTML = '<i class="fas fa-plus me-2"></i>إضافة بيانات الأم المتوفية';
                toggleMotherButton.classList.remove('btn-danger');
                toggleMotherButton.classList.add('btn-primary');
                isMotherSectionVisible = false;
            }

            // زر التبديل اليدوي
            toggleMotherButton.addEventListener('click', function(e) {
                e.preventDefault();
                if (!isMotherSectionVisible) {
                    showMotherSection();
                } else {
                    hideMotherSection();
                }
            });

            // 🆕 عند تغيير حالة الأم (حية / متوفية) في البوابة الأساسية
            const motherIsAliveSelect = document.getElementById('mother_is_alive');
            if (motherIsAliveSelect) {
                motherIsAliveSelect.addEventListener('change', function() {
                    const isDeceased = this.value === '0';

                    if (isDeceased) {
                        // فتح قسم الأم المتوفية تلقائياً
                        showMotherSection();

                        // نسخ بيانات الأم الحية إلى حقول الأم المتوفية
                        const livingMotherId = document.getElementById('mother_id');
                        const livingMotherFirstName = document.getElementById('mother_first_name');
                        const livingMotherSecondName = document.getElementById('mother_second_name');
                        const livingMotherThirdName = document.getElementById('mother_third_name');
                        const livingMotherLastName = document.getElementById('mother_last_name');

                        const deceasedMotherId = motherInfoSection.querySelector('[name="deceased_mother_id"]');
                        const deceasedMotherFirstName = motherInfoSection.querySelector('[name="deceased_mother_first_name"]');
                        const deceasedMotherSecondName = motherInfoSection.querySelector('[name="deceased_mother_second_name"]');
                        const deceasedMotherThirdName = motherInfoSection.querySelector('[name="deceased_mother_third_name"]');
                        const deceasedMotherLastName = motherInfoSection.querySelector('[name="deceased_mother_last_name"]');

                        // نسخ البيانات إذا كانت موجودة والحقول فارغة
                        if (livingMotherId && deceasedMotherId && !deceasedMotherId.value) {
                            deceasedMotherId.value = livingMotherId.value;
                        }
                        if (livingMotherFirstName && deceasedMotherFirstName && !deceasedMotherFirstName.value) {
                            deceasedMotherFirstName.value = livingMotherFirstName.value;
                        }
                        if (livingMotherSecondName && deceasedMotherSecondName && !deceasedMotherSecondName.value) {
                            deceasedMotherSecondName.value = livingMotherSecondName.value;
                        }
                        if (livingMotherThirdName && deceasedMotherThirdName && !deceasedMotherThirdName.value) {
                            deceasedMotherThirdName.value = livingMotherThirdName.value;
                        }
                        if (livingMotherLastName && deceasedMotherLastName && !deceasedMotherLastName.value) {
                            deceasedMotherLastName.value = livingMotherLastName.value;
                        }

                        // التركيز على حقل تاريخ الوفاة
                        setTimeout(() => {
                            const deathDateInput = motherInfoSection.querySelector('[name="mother_death_date"]');
                            if (deathDateInput) deathDateInput.focus();
                        }, 400);

                    } else if (this.value === '1') {
                        // إخفاء قسم الأم المتوفية عند التحول إلى "حية"
                        hideMotherSection();
                    }
                });
            }

            // تم إزالة فرض إظهار بيانات الأم المتوفية قبل الإرسال لكي لا يضطر المستخدم لإدخالها إذا لم يرغب بذلك
        });
    </script>
    {{-- عملية التحقق من إدخال بيانت الاب والاب المتوفين --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من الحقول الإجبارية للأب المتوفى
            const fatherRequiredFields = {
                'father_first_name': 'الاسم الأول للأب',
                'father_last_name': 'اسم عائلة الأب',
                'father_id': 'رقم هوية الأب',
                'father_death_date': 'تاريخ وفاة الأب',
                'father_death_reason': 'سبب وفاة الأب'
            };

            // التحقق من الحقول الإجبارية للأم المتوفية
            const motherRequiredFields = {
                'deceased_mother_first_name': 'الاسم الأول للأم',
                'deceased_mother_last_name': 'اسم عائلة الأم',
                'deceased_mother_id': 'رقم هوية الأم',
                'mother_death_date': 'تاريخ وفاة الأم',
                'mother_death_reason': 'سبب وفاة الأم'
            };

            // دالة للتحقق من تاريخ الوفاة
            function validateDeathDate(deathDateInput) {
                const deathDate = new Date(deathDateInput.value);
                const today = new Date();

                if (deathDate > today) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في التاريخ',
                        text: 'لا يمكن أن يكون تاريخ الوفاة في المستقبل'
                    });
                    deathDateInput.value = '';
                    return false;
                }
                return true;
            }

            // إضافة مستمعي الأحداث لتواريخ الوفاة
            document.querySelector('input[name="father_death_date"]').addEventListener('change', function() {
                validateDeathDate(this);
            });

            document.querySelector('input[name="mother_death_date"]').addEventListener('change', function() {
                validateDeathDate(this);
            });

            // التحقق من البيانات قبل الإرسال
            const mainFormEl = document.getElementById('main_form') || document.querySelector('form');
            mainFormEl.addEventListener('submit', function(e) {
                const section = document.querySelector('select[name="data_section_id"]').value;

                if (section === '1') { // قسم الأيتام
                    // التحقق من بيانات الأب - فقط إذا أدخل المستخدم رقم هوية الأب
                    const fatherIdInput = this.querySelector('[name="father_id"]');
                    const fatherIdValue = fatherIdInput ? fatherIdInput.value.trim() : '';

                    if (fatherIdValue) {
                        // إذا أدخل رقم هوية الأب، تحقق من باقي الحقول الإجبارية
                        for (const [field, label] of Object.entries(fatherRequiredFields)) {
                            const input = this.querySelector(`[name="${field}"]`);
                            if (!input || !input.value || !input.value.trim()) {
                                e.preventDefault();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'حقل مطلوب',
                                    text: `الرجاء إدخال ${label}`
                                });
                                // تفعيل تبويب المتوفين
                                const deceasedTabEl = document.querySelector('#deceased-tab');
                                if (deceasedTabEl) bootstrap.Tab.getInstance(deceasedTabEl)?.show();
                                if (input) input.focus();
                                return;
                            }
                        }
                    }

                    // التحقق من بيانات الأم إذا كانت مضافة وأدخل المستخدم رقم هويتها
                    const motherSection = document.getElementById('motherInfoSection');
                    if (motherSection && motherSection.style.display !== 'none') {
                        const motherIdInput = this.querySelector('[name="deceased_mother_id"]');
                        const motherIdValue = motherIdInput ? motherIdInput.value.trim() : '';

                        if (motherIdValue) {
                            for (const [field, label] of Object.entries(motherRequiredFields)) {
                                const input = this.querySelector(`[name="${field}"]`);
                                if (!input || !input.value || !input.value.trim()) {
                                    e.preventDefault();
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'حقل مطلوب',
                                        text: `الرجاء إدخال ${label}`
                                    });
                                    // تفعيل تبويب المتوفين
                                    const deceasedTabEl = document.querySelector('#deceased-tab');
                                    if (deceasedTabEl) bootstrap.Tab.getInstance(deceasedTabEl)?.show();
                                    if (input) input.focus();
                                    return;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
