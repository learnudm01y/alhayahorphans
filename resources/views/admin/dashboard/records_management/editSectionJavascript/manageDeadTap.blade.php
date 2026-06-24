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
        const addFamilyMemberBtn = document.getElementById('addFamilyMember');
        if (addFamilyMemberBtn) {
            const originalAddFamilyMember = addFamilyMemberBtn.onclick;
            addFamilyMemberBtn.onclick = function() {
                if (originalAddFamilyMember) {
                    originalAddFamilyMember.apply(this, arguments);
                }

                if (sectionSelect.value === '1') {
                    const lastForm = document.querySelector('.family-member-form:last-child');
                    if (!lastForm) return;
                    const fileIdInput = lastForm.querySelector('input[name*="[file_id]"]');
                    if (!fileIdInput || !fileIdInput.name) return;
                    const match = fileIdInput.name.match(/\[(\d+)\]/);
                    if (!match) return;
                    const formIndex = match[1];

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
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleMotherButton = document.getElementById('toggleMotherInfo');
        const motherInfoSection = document.getElementById('motherInfoSection');

        if (!toggleMotherButton || !motherInfoSection) return;

        // إعداد الأنيميشن عبر CSS
        motherInfoSection.style.transition = 'all 0.35s cubic-bezier(.4,2,.6,1)';
        motherInfoSection.style.overflow = 'hidden';

        // عند تحميل الصفحة: إظهار أو إخفاء القسم حسب البيانات
        var hasMotherData = false;
        ['mother_first_name', 'mother_last_name', 'mother_id', 'mother_death_date', 'mother_death_reason'].forEach(function(name) {
            var input = document.querySelector('[name="' + name + '"]');
            if (input && input.value && input.value.trim() !== '') {
                hasMotherData = true;
            }
        });
        if (hasMotherData) {
            motherInfoSection.style.display = 'block';
            motherInfoSection.style.maxHeight = motherInfoSection.scrollHeight + 'px';
            toggleMotherButton.innerHTML = '<i class="fas fa-minus me-2"></i>إخفاء بيانات الأم المتوفية';
            toggleMotherButton.classList.remove('btn-primary');
            toggleMotherButton.classList.add('btn-danger');
        } else {
            motherInfoSection.style.display = 'none';
            motherInfoSection.style.maxHeight = '0';
            toggleMotherButton.innerHTML = '<i class="fas fa-plus me-2"></i>إضافة بيانات الأم المتوفية';
            toggleMotherButton.classList.remove('btn-danger');
            toggleMotherButton.classList.add('btn-primary');
        }

        // ربط الزر مع الأنيميشن
        toggleMotherButton.onclick = function() {
            if (motherInfoSection.style.display === 'none' || motherInfoSection.style.maxHeight === '0px' || motherInfoSection.style.maxHeight === '') {
                motherInfoSection.style.display = 'block';
                // لإعادة الحساب الصحيح للارتفاع
                setTimeout(function() {
                    motherInfoSection.style.maxHeight = motherInfoSection.scrollHeight + 'px';
                }, 10);
                toggleMotherButton.innerHTML = '<i class="fas fa-minus me-2"></i>إخفاء بيانات الأم المتوفية';
                toggleMotherButton.classList.remove('btn-primary');
                toggleMotherButton.classList.add('btn-danger');
            } else {
                motherInfoSection.style.maxHeight = '0';
                // بعد انتهاء الأنيميشن أخفِ العنصر فعلياً
                setTimeout(function() {
                    motherInfoSection.style.display = 'none';
                }, 350);
                toggleMotherButton.innerHTML = '<i class="fas fa-plus me-2"></i>إضافة بيانات الأم المتوفية';
                toggleMotherButton.classList.remove('btn-danger');
                toggleMotherButton.classList.add('btn-primary');
            }
        };

        // تأكد من إظهار القسم قبل الإرسال (لضمان إرسال بيانات الأم إذا كانت ظاهرة)
        var form = (document.getElementById('main_form') || document.querySelector('form'));
        if (form) {
            form.addEventListener('submit', function() {
                if (motherInfoSection && (motherInfoSection.style.display === 'none' || motherInfoSection.style.maxHeight === '0px')) {
                    motherInfoSection.style.display = 'block';
                    motherInfoSection.style.maxHeight = motherInfoSection.scrollHeight + 'px';
                }
            });
        }
    });
</script>
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
            if (idNumber.length > 0 && (idNumber.length < 9 || idNumber.length > 10)) {
                input.setCustomValidity('يجب أن يتكون رقم الهوية من 9 أو 10 أرقام');
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
        (document.getElementById('main_form') || document.querySelector('form')).addEventListener('submit', function(e) {
            const section = document.querySelector('select[name="data_section_id"]').value;

            if (section === '1') { // قسم الأيتام
                // التحقق من بيانات الأب
                for (const [field, label] of Object.entries(fatherRequiredFields)) {
                    const input = this.querySelector(`[name="${field}"]`);
                    if (!input || (!input.value || !input.value.trim())) {
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
                if (motherSection && motherSection.style.display !== 'none') {
                    for (const [field, label] of Object.entries(motherRequiredFields)) {
                        const input = this.querySelector(`[name="${field}"]`);
                        if (!input || (!input.value || !input.value.trim())) {
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // معرف زر تبويب "الأفراد المتوفين" وزر toggle الأم
        const deceasedTabBtn = document.getElementById('deceased-tab');
        const toggleBtnId = 'toggleMotherInfo';
        if (!deceasedTabBtn) {
            console.warn('زر تبويب المتوفين (deceased-tab) غير موجود');
            return;
        }

        // استماع لحدث إظهار التبويب (Bootstrap 5)
        deceasedTabBtn.addEventListener('shown.bs.tab', function(event) {
            // عند فتح التبويب يدوياً، ننفذ نقرة واحدة على زر toggle الأم
            const toggleBtn = document.getElementById(toggleBtnId);
            if (!toggleBtn) {
                console.warn(`زر ${toggleBtnId} غير موجود داخل تبويب المتوفين`);
                return;
            }
            // نضغط مرة واحدة: لفتح القسم أو إغلاقه حسب الحالة الحالية
            // إذا القسم مغلق، الضغط سيفتحه؛ وإذا مفتوح والتصميم يتيح الإغلاق بالنقرة، سينفذه أيضاً.
            // إذا تريد التأكد فتحه دائماً، يمكن التحقق أولاً من حالة القسم ثم الضغط فقط عند الإغلاق.
            // مثال: فتح القسم إذا كان مغلق:
            const targetSelector = toggleBtn.getAttribute('data-bs-target') || toggleBtn.getAttribute('href');
            if (targetSelector) {
                const sectionEl = document.querySelector(targetSelector);
                if (sectionEl) {
                    const isCollapsed = !sectionEl.classList.contains('show');
                    if (isCollapsed) {
                        // نضغط لفتح القسم
                        if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                            try {
                                new bootstrap.Collapse(sectionEl, { toggle: true });
                            } catch (err) {
                                toggleBtn.click();
                            }
                        } else {
                            toggleBtn.click();
                        }
                    }
                    // إذا كان القسم بالفعل مفتوحاً، ولا تريد نقرة لإغلاقه، فلا تفعل شيئاً.
                } else {
                    // إن لم نجد القسم، نجرب نقرة عادية
                    toggleBtn.click();
                }
            } else {
                // إذا الزر لا يستخدم data-bs-target أو href، نجرب نقرة مباشرة
                toggleBtn.click();
            }
        });
    });
</script>
