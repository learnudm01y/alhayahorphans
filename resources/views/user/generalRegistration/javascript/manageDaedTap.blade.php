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
            const originalAddFamilyMember = document.getElementById('addFamilyMember').onclick;
            document.getElementById('addFamilyMember').onclick = function() {
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
        });
    </script>
    {{-- التحكم في إظهار وإخفاء نموذج ادخال الام --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleMotherButton = document.getElementById('toggleMotherInfo');
            const motherInfoSection = document.getElementById('motherInfoSection');
            let isMotherSectionVisible = false;

            toggleMotherButton.addEventListener('click', function(e) {
                e.preventDefault(); // منع أي سلوك افتراضي
                if (!isMotherSectionVisible) {
                    // إظهار القسم مع التحريك
                    motherInfoSection.style.display = 'block';
                    motherInfoSection.style.opacity = '0';
                    motherInfoSection.style.transform = 'translateY(-20px)';
                    motherInfoSection.style.transition = 'all 0.3s ease';

                    setTimeout(() => {
                        motherInfoSection.style.opacity = '1';
                        motherInfoSection.style.transform = 'translateY(0)';
                    }, 10);

                    this.innerHTML = '<i class="fas fa-minus me-2"></i>إخفاء بيانات الأم المتوفية';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-danger');
                    isMotherSectionVisible = true;
                } else {
                    // إخفاء القسم مع التحريك
                    motherInfoSection.style.transition = 'all 0.3s ease';
                    motherInfoSection.style.opacity = '0';
                    motherInfoSection.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        motherInfoSection.style.display = 'none';
                    }, 300);

                    this.innerHTML = '<i class="fas fa-plus me-2"></i>إضافة بيانات الأم المتوفية';
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-primary');
                    isMotherSectionVisible = false;
                }
            });

            // ✅ تأكد من إظهار القسم قبل الإرسال
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                if (motherInfoSection && !isMotherSectionVisible) {
                    motherInfoSection.style.display = 'block';
                    motherInfoSection.style.opacity = '1';
                    motherInfoSection.style.transform = 'translateY(0)';
                    isMotherSectionVisible = true;
                }
            });
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
                'mother_first_name': 'الاسم الأول للأم',
                'mother_last_name': 'اسم عائلة الأم',
                'mother_id': 'رقم هوية الأم',
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

            // دالة للتحقق من رقم الهوية
            function validateIdNumber(input) {
                const idNumber = input.value;
                if (idNumber.length !== 10) {
                    input.setCustomValidity('يجب أن يتكون رقم الهوية من 10 أرقام');
                    return false;
                }
                input.setCustomValidity('');
                return true;
            }

            // إضافة مستمعي الأحداث لأرقام الهوية
            document.querySelector('input[name="father_id"]').addEventListener('input', function() {
                validateIdNumber(this);
            });

            document.querySelector('input[name="mother_id"]').addEventListener('input', function() {
                validateIdNumber(this);
            });

            // التحقق من البيانات قبل الإرسال
            document.querySelector('form').addEventListener('submit', function(e) {
                const section = document.querySelector('select[name="data_section_id"]').value;

                if (section === '1') { // قسم الأيتام
                    // التحقق من بيانات الأب
                    for (const [field, label] of Object.entries(fatherRequiredFields)) {
                        const input = this.querySelector(`[name="${field}"]`);
                        if (!input.value) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'حقل مطلوب',
                                text: `الرجاء إدخال ${label}`
                            });
                            // تفعيل تبويب المتوفين
                            bootstrap.Tab.getInstance(document.querySelector('#deceased-tab')).show();
                            input.focus();
                            return;
                        }
                    }

                    // التحقق من بيانات الأم إذا كانت مضافة
                    const motherSection = document.getElementById('motherInfoSection');
                    if (motherSection.style.display !== 'none') {
                        for (const [field, label] of Object.entries(motherRequiredFields)) {
                            const input = this.querySelector(`[name="${field}"]`);
                            if (!input.value) {
                                e.preventDefault();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'حقل مطلوب',
                                    text: `الرجاء إدخال ${label}`
                                });
                                // تفعيل تبويب المتوفين
                                bootstrap.Tab.getInstance(document.querySelector('#deceased-tab')).show();
                                input.focus();
                                return;
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
