@push('scriptsCode')
    <script>
        // Enable Bootstrap tabs
        const triggerTabList = [].slice.call(document.querySelectorAll('#formTabs button'));
        triggerTabList.forEach(function(triggerEl) {
            new bootstrap.Tab(triggerEl);
        });

        // ============================================
        // نظام Validation الشامل لصفحة الإضافة
        // ============================================

        $(document).ready(function() {
            // دالة لجمع وعرض جميع أخطاء الـ Validation
            function collectAndDisplayValidationErrors() {
                const errors = [];

                // التحقق من الحقول المطلوبة في البوابة الأولى
                const requiredFields = [
                    { name: 'data_section_id', label: 'القسم', selector: 'select[name="data_section_id"]' },
                    { name: 'data_id_number', label: 'رقم الهوية', selector: 'input[name="data_id_number"]' },
                    { name: 'file_id_number', label: 'رقم الملف', selector: 'input[name="file_id_number"]' },
                    { name: 'data_first_name', label: 'الاسم الأول', selector: 'input[name="data_first_name"]' },
                    { name: 'data_father_name', label: 'اسم الأب', selector: 'input[name="data_father_name"]' },
                    { name: 'data_grand_father_name', label: 'اسم الجد', selector: 'input[name="data_grand_father_name"]' },
                    { name: 'data_family_name', label: 'اسم العائلة', selector: 'input[name="data_family_name"]' },
                    { name: 'data_relationship', label: 'صلة القرابة', selector: 'select[name="data_relationship"]' },
                    { name: 'data_birth_date', label: 'تاريخ الميلاد', selector: 'input[name="data_birth_date"]' },
                    { name: 'data_gender', label: 'الجنس', selector: 'select[name="data_gender"]' },
                    { name: 'data_phone_number', label: 'رقم الهاتف', selector: 'input[name="data_phone_number"]' },
                    { name: 'data_marital_status', label: 'الحالة الاجتماعية', selector: 'select[name="data_marital_status"]' },
                    { name: 'data_displacement_status', label: 'حالة النزوح', selector: 'select[name="data_displacement_status"]' },
                    { name: 'data_current_address', label: 'العنوان الحالي', selector: 'input[name="data_current_address"]' },
                    { name: 'data_city', label: 'المدينة', selector: 'select[name="data_city"]' },
                    { name: 'data_province', label: 'المحافظة', selector: 'select[name="data_province"]' },
                    { name: 'data_health_status', label: 'الحالة الصحية', selector: 'select[name="data_health_status"]' },
                    { name: 'data_employment_status_breadwinner', label: 'الحالة الوظيفية المعيل', selector: 'select[name="data_employment_status_breadwinner"]' },
                    { name: 'data_housing_status', label: 'حالة السكن', selector: 'select[name="data_housing_status"]' },
                    { name: 'data_current_housing_type', label: 'نوع السكن الحالي', selector: 'select[name="data_current_housing_type"]' }
                ];

                requiredFields.forEach(function(field) {
                    const element = $(field.selector);
                    if (element.length > 0) {
                        const value = element.val();
                        if (!value || value.trim() === '') {
                            errors.push(field.label + ' مطلوب');
                            element.addClass('is-invalid');
                        } else {
                            element.removeClass('is-invalid');
                        }
                    }
                });

                // التحقق من صحة رقم الهوية (10 أرقام)
                const idNumber = $('input[name="data_id_number"]').val();
                if (idNumber && idNumber.length !== 9) {
                    errors.push('رقم الهوية يجب أن يتكون من 9 أرقام');
                    $('input[name="data_id_number"]').addClass('is-invalid');
                }

                // التحقق من صحة رقم الملف (6 أرقام)
                const fileId = $('input[name="file_id_number"]').val();
                if (fileId && fileId.length > 6) {
                    errors.push('رقم الملف يجب ألا يتجاوز 6 أرقام');
                    $('input[name="file_id_number"]').addClass('is-invalid');
                }

                // عرض الأخطاء إذا وجدت
                if (errors.length > 0) {
                    let errorHtml = '<div class="alert alert-danger" role="alert" style="border: 2px solid #dc3545; border-radius: 10px; background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%); box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2); animation: slideDown 0.4s ease-out;">';
                    errorHtml += '<h5 class="alert-heading mb-3"><i class="fas fa-exclamation-triangle me-2"></i>يرجى تصحيح الأخطاء التالية:</h5>';
                    errorHtml += '<ul class="mb-0">';
                    errors.forEach(function(error) {
                        errorHtml += '<li style="padding: 8px 0; font-size: 16px; color: #721c24; border-bottom: 1px dashed rgba(220, 53, 69, 0.2);">⚠️ ' + error + '</li>';
                    });
                    errorHtml += '</ul></div>';

                    // إزالة أي رسائل أخطاء قديمة
                    $('#validation-errors-container-create').remove();

                    // إضافة الرسالة الجديدة قبل أول بوابة
                    $('.tab-content').before('<div id="validation-errors-container-create">' + errorHtml + '</div>');

                    // التمرير إلى منطقة الأخطاء
                    $('html, body').animate({
                        scrollTop: $('#validation-errors-container-create').offset().top - 100
                    }, 500);

                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        html: 'يرجى تصحيح الأخطاء المعروضة في النموذج قبل الحفظ.<br><strong>عدد الأخطاء: ' + errors.length + '</strong>',
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#d33'
                    });

                    return false;
                } else {
                    $('#validation-errors-container-create').remove();
                    return true;
                }
            }

            // التحقق عند تقديم النموذج
            $('#main_form').on('submit', function(e) {
                if (!collectAndDisplayValidationErrors()) {
                    e.preventDefault();
                    return false;
                }
            });

            // إزالة علامة الخطأ عند تعديل الحقل
            $('input[required], select[required], input.is-invalid, select.is-invalid').on('change input', function() {
                if ($(this).val() && $(this).val().trim() !== '') {
                    $(this).removeClass('is-invalid');
                }
            });

            // التحقق الفوري عند تبديل البوابات
            $('.nav-link[data-bs-toggle="tab"]').on('click', function() {
                // إخفاء رسائل الأخطاء عند التنقل
                $('#validation-errors-container-create').fadeOut();
            });
        });
    </script>
@endpush

@push('scriptsCode')
    <script>
        // التعريفات الأساسية
        const fileInput = document.getElementById('document_file');
        const preview = document.getElementById('preview');
        const previewImg = preview.querySelector('img');
        const confirmBtn = document.getElementById('confirmUpload');
        const personSelector = document.getElementById('person_selector');
        const docTypeSelect = document.getElementById('document_type');
        const docs = new Map(); // لا تكرر تعريف هذا المتغير في أي مكان آخر

        // تحديث معالجة اختيار الملف
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                preview.classList.add('d-none');
                return;
            }

            if (!docTypeSelect.value || !personSelector.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'الرجاء اختيار نوع الوثيقة والشخص أولاً'
                });
                fileInput.value = '';
                return;
            }

            // عرض معاينة الصورة
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    preview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                previewImg.src = '/path/to/default/document/icon.png'; // استبدل بمسار أيقونة المستند الافتراضية
                preview.classList.remove('d-none');
            }
        });

        // تحديث معالجة تأكيد الرفع
        confirmBtn.addEventListener('click', function() {
            const file = fileInput.files[0];
            if (!file) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'الرجاء اختيار ملف'
                });
                return;
            }

            const selectedPerson = personSelector.value;
            if (!selectedPerson || !docTypeSelect.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'الرجاء اختيار الشخص ونوع الوثيقة'
                });
                return;
            }

            // الحصول على رقم الهوية
            let idNumber;
            if (selectedPerson === 'main') {
                idNumber = document.querySelector('input[name="data_id_number"]').value;
            } else if (selectedPerson === 'deceased_father') {
                idNumber = document.querySelector('input[name="father_id"]').value;
                if (!idNumber) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'الرجاء إدخال رقم هوية الأب المتوفى أولاً'
                    });
                    return;
                }
            } else if (selectedPerson === 'deceased_mother') {
                idNumber = document.querySelector('input[name="mother_id"]').value;
                if (!idNumber) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'الرجاء إدخال رقم هوية الأم المتوفية أولاً'
                    });
                    return;
                }
            } else {
                const familyIndex = selectedPerson.split('_')[1];
                idNumber = document.querySelector(`input[name="family_members[${familyIndex}][person_id]"]`).value;
            }

            // تحديث التحقق من رقم الهوية
            if (!idNumber) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'الرجاء إدخال رقم الهوية للشخص المحدد أولاً'
                });
                return;
            }

            // تحقق من اسم الملف الأصلي
            if (!file.name) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'اسم الملف غير صالح'
                });
                return;
            }

            const docType = docTypeSelect.value;
            const docTypeName = docTypeSelect.options[docTypeSelect.selectedIndex].text;
            const fileId = document.getElementById('document_id').value;
            const personName = personSelector.options[personSelector.selectedIndex].text;
            const docId = `doc_${Date.now()}`;

            // تحديث تركيبة اسم الملف ليتضمن رقم الهوية
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const newFileName = `${docType}_${fileId}_${idNumber}.${fileExtension}`;

            // تحديث HTML الوثيقة
            const docHTML = `
                <div class="document-card card h-100" id="${docId}">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <h6 class="mb-0 fw-bold text-primary">${docTypeName}</h6>
                        <button type="button" class="btn btn-danger" onclick="removeDocument('${docId}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center">
                        <div class="mb-3 w-100">
                            <img src="${previewImg.src}" class="img-fluid w-100" style="max-height: 150px; object-fit: contain;">
                        </div>
                        <div class="text-center w-100">
                            <p class="small text-muted mb-1">
                                <i class="fas fa-file-alt me-1"></i> ${docTypeName}
                            </p>
                            <p class="small text-muted">
                                <i class="fas fa-file-signature me-1"></i> ${newFileName}
                            </p>
                        </div>
                    </div>
                </div>
            `;

            // إضافة الوثيقة للقسم المناسب
            if (selectedPerson === 'deceased_father') {
                document.getElementById('father_docs').insertAdjacentHTML('beforeend', docHTML);
            } else if (selectedPerson === 'deceased_mother') {
                document.getElementById('mother_docs').insertAdjacentHTML('beforeend', docHTML);
            } else if (selectedPerson === 'main') {
                document.getElementById('main_person_docs').insertAdjacentHTML('beforeend', docHTML);
            } else {
                let familyContainer = document.querySelector(`#family_member_${selectedPerson}`);
                let docFlexContainer;

                if (!familyContainer) {
                    const sectionHTML = `
                <div id="family_member_${selectedPerson}" class="documents-section">
                    <h6 class="text-primary border-bottom pb-2">${personName}</h6>
                    <div class="documents-flex-container" id="docs_container_${selectedPerson}">
                    </div>
                </div>
            `;
                    document.getElementById('family_members_docs').insertAdjacentHTML('beforeend', sectionHTML);
                }

                docFlexContainer = document.querySelector(`#docs_container_${selectedPerson}`);
                if (!docFlexContainer) {
                    const containerHTML = `
                <div class="documents-flex-container" id="docs_container_${selectedPerson}"></div>
            `;
                    document.querySelector(`#family_member_${selectedPerson}`).insertAdjacentHTML('beforeend',
                        containerHTML);
                    docFlexContainer = document.querySelector(`#docs_container_${selectedPerson}`);
                }

                docFlexContainer.insertAdjacentHTML('beforeend', docHTML);
            }

            // تخزين معلومات الملف
            const modifiedFile = new File([file], newFileName, {
                type: file.type,
                lastModified: file.lastModified
            });

            docs.set(docId, {
                file: modifiedFile,
                type: docType,
                name: newFileName,
                personId: selectedPerson,
                personName: personName
            });

            // إعادة تعيين النموذج
            fileInput.value = '';
            preview.classList.add('d-none');
            docTypeSelect.value = '';
            personSelector.value = '';
        });

        // تحديث دالة حذف الوثيقة
        function removeDocument(docId) {
            if (!confirm('هل أنت متأكد من حذف هذه الوثيقة؟')) return;

            const element = document.getElementById(docId);
            if (element) {
                element.remove();
                docs.delete(docId);
            }
        }

        // إضافة الوثائق للنموذج عند الإرسال
        document.querySelector('form').addEventListener('submit', function(e) {
            let index = 0;
            docs.forEach((doc, docId) => {
                // ملف المرفق
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.name = `attachments[${index}][file]`;
                fileInput.style.display = 'none';
                const dt = new DataTransfer();
                dt.items.add(doc.file);
                fileInput.files = dt.files;
                this.appendChild(fileInput);

                // رقم الهوية
                const personIdInput = document.createElement('input');
                personIdInput.type = 'hidden';
                personIdInput.name = `attachments[${index}][person_identity_number]`;
                personIdInput.value = doc.personId;
                this.appendChild(personIdInput);

                // اسم الملف
                const fileNameInput = document.createElement('input');
                fileNameInput.type = 'hidden';
                fileNameInput.name = `attachments[${index}][stored_file_name]`;
                fileNameInput.value = doc.name;
                this.appendChild(fileNameInput);

                // نوع الوثيقة
                const fileTypeInput = document.createElement('input');
                fileTypeInput.type = 'hidden';
                fileTypeInput.name = `attachments[${index}][file_type]`;
                fileTypeInput.value = doc.type;
                this.appendChild(fileTypeInput);

                // رقم الملف العام
                const fileIdInput = document.createElement('input');
                fileIdInput.type = 'hidden';
                fileIdInput.name = `attachments[${index}][file_id_number]`;
                fileIdInput.value = document.getElementById('document_id').value;
                this.appendChild(fileIdInput);

                index++;
            });
        });

        let familyMemberCount = 1;

        document.getElementById('addFamilyMember').addEventListener('click', function() {
            const template = document.querySelector('.family-member-form').cloneNode(true);
            const parentForm = document.querySelector('.family-member-form');

            // إظهار النموذج المستنسخ
            template.style.display = 'block';

            // تحديث هيكل النموذج مع رقم الفرد
            template.className = 'family-member-form card shadow-sm mb-4';
            template.style.border = '2px solid #343a40';
            template.style.borderRadius = '8px';
            template.innerHTML = `
                        <div class="card-header bg-gradient-primary text-dark py-3 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 d-flex align-items-center">
                                <i class="fas fa-user-circle fs-4 me-2 text-primary"></i>
                                <span class="badge bg-success me-2" style="font-size: 1.1rem;">فرد الأسرة (${familyMemberCount})</span>
                            </h5>
                            <button type="button" class="btn btn-danger btn-sm delete-member">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="card-body bg-light">
                            <div class="row g-3">
                                ${template.querySelector('.row').innerHTML}
                            </div>
                        </div>
                    `;

            // تحديث الأسماء والقيم
            template.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.name) {
                    input.name = input.name.replace('[0]', `[${familyMemberCount}]`);

                    // إضافة required للحقول المطلوبة
                    if (input.name.includes('[first_name]') ||
                        input.name.includes('[last_name]') ||
                        input.name.includes('[person_birth_date]') ||
                        input.name.includes('[person_gender]')) {
                        input.required = true;
                    }

                    // التعامل مع رقم التسجيل
                    if (input.name.includes('[registration_id]')) {
                        input.value = document.querySelector('input[name="file_id_number"]').value;
                        input.readOnly = true;
                        input.classList.add('bg-secondary', 'bg-opacity-10');
                    }
                    // التعامل مع رقم الهوية
                    else if (input.name.includes('[person_id]')) {
                        input.setAttribute('inputmode', 'numeric');
                        input.setAttribute('pattern', '[0-9]*');
                        input.setAttribute('maxlength', '9');
                        input.setAttribute('oninput', "this.value = this.value.replace(/[^0-9]/g, '')");
                        input.value = '';
                    }
                    // حفظ القيم المحددة للأسماء
                    else if (input.name.includes('[second_name]') ||
                        input.name.includes('[third_name]') ||
                        input.name.includes('[last_name]')) {
                        input.value = parentForm.querySelector(
                            `[name="${input.name.replace(`[${familyMemberCount}]`, '[0]')}"]`).value;
                    } else {
                        // تفريغ باقي الحقول
                        if (input.type === 'text' || input.type === 'number' || input.type === 'date') {
                            input.value = '';
                        } else if (input.tagName === 'SELECT') {
                            input.selectedIndex = 0;
                        }
                    }
                }
            });

            // إضافة مستمع حدث للحذف
            template.querySelector('.delete-member').onclick = function() {
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: "سيتم حذف هذا الفرد من القائمة",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        template.style.opacity = '0';
                        template.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            template.remove();
                        }, 300);
                    }
                });
            };

            document.getElementById('familyMembersContainer').appendChild(template);

            // ربط السجل المدني على person_id للفرد الجديد
            const newPersonIdInput = template.querySelector('input[name$="[person_id]"]');
            if (newPersonIdInput) {
                delete newPersonIdInput.dataset.civilLookupAttached;
                const oldSpinner = newPersonIdInput.closest('.input-group') && newPersonIdInput.closest('.input-group').querySelector('.civil-lookup-status');
                if (oldSpinner) oldSpinner.remove();
                if (window.setupFamilyMemberLookup) {
                    window.setupFamilyMemberLookup(newPersonIdInput);
                }
            }

            familyMemberCount++;
        });
    </script>
@endpush

@push('scriptsCode')
    {{-- حساب العمر تلقائيا --}}
    <script>
        // حساب العمر تلقائياً عند تغيير تاريخ الميلاد
        document.addEventListener('input', function(e) {
            if (e.target.name.includes('[person_birth_date]')) {
                calculateAge(e.target);
            }
        });

        // استمع أيضاً لحدث change (عند الجلب من السجل المدني)
        document.addEventListener('change', function(e) {
            if (e.target.name && e.target.name.includes('[person_birth_date]')) {
                calculateAge(e.target);
            }
        });

        // دالة حساب العمر
        function calculateAge(inputElement) {
            const birthDate = new Date(inputElement.value);
            if (isNaN(birthDate.getTime())) return; // تحقق من صحة التاريخ

            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            const formIndex = inputElement.name.match(/\[(\d+)\]/)[1];
            const ageInput = document.querySelector(`input[name="family_members[${formIndex}][person_age]"]`);
            if (ageInput) {
                ageInput.value = age;
            }
        }
    </script>
@endpush

@push('scriptsCode')
    {{-- تحديث قائمة تعريف الاشخاص --}}
    <script>
        // تحديث تعريف قائمة الأشخاص
        function updatePersonsList() {
            const mainPerson = document.querySelector('option[value="main"]');
            const firstName = document.querySelector('input[name="data_first_name"]').value;
            const familyName = document.querySelector('input[name="data_family_name"]').value;

            // تحديث خيار صاحب الملف
            if (firstName && familyName) {
                mainPerson.textContent = `${firstName} ${familyName}`;
            } else {
                mainPerson.textContent = 'صاحب الملف';
            }

            // تحديث قائمة أفراد الأسرة
            const familyMembersOptions = document.getElementById('family_members_options');
            familyMembersOptions.innerHTML = ''; // مسح الخيارات القديمة

            document.querySelectorAll('.family-member-form').forEach((form, index) => {
                const firstName = form.querySelector('input[name*="[first_name]"]').value;
                const lastName = form.querySelector('input[name*="[last_name]"]').value;

                if (firstName && lastName) {
                    const option = document.createElement('option');
                    option.value = `family_${index}`;
                    option.textContent = `${firstName} ${lastName}`;
                    familyMembersOptions.appendChild(option);
                }
            });
        }

        // إضافة مستمعي الأحداث لتحديث القائمة
        document.querySelector('input[name="data_first_name"]').addEventListener('input', updatePersonsList);
        document.querySelector('input[name="data_family_name"]').addEventListener('input', updatePersonsList);

        // مراقبة التغييرات في نماذج أفراد الأسرة
        const familyMembersContainer = document.getElementById('familyMembersContainer');
        familyMembersContainer.addEventListener('input', function(e) {
            if (e.target.name && (e.target.name.includes('[first_name]') || e.target.name.includes(
                    '[last_name]'))) {
                updatePersonsList();
            }
        });

        // إضافة استدعاء التحديث بعد إضافة فرد جديد
        const originalAddFamilyMember = document.getElementById('addFamilyMember').onclick;
        document.getElementById('addFamilyMember').onclick = function() {
            if (originalAddFamilyMember) {
                originalAddFamilyMember.apply(this, arguments);
            }
            setTimeout(updatePersonsList, 100); // تأخير صغير للتأكد من إضافة العناصر
        };

        // تحديث القائمة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', updatePersonsList);
    </script>
@endpush

@push('scriptsCode')
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
@endpush

@push('scriptsCode')
    {{-- التحكم في إظهار وإخفاء نموذج ادخال الام --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleMotherButton = document.getElementById('toggleMotherInfo');
            const motherInfoSection = document.getElementById('motherInfoSection');

            toggleMotherButton.addEventListener('click', function() {
                const isHidden = motherInfoSection.style.display === 'none' || motherInfoSection.style
                    .display === '';

                // Animate the form
                motherInfoSection.style.transition = 'all 0.3s ease';
                motherInfoSection.style.display = 'block';
                motherInfoSection.style.opacity = '0';
                motherInfoSection.style.transform = 'translateY(-20px)';

                setTimeout(() => {
                    if (isHidden) {
                        motherInfoSection.style.opacity = '1';
                        motherInfoSection.style.transform = 'translateY(0)';
                        this.innerHTML =
                            '<i class="fas fa-minus me-2"></i>إخفاء بيانات الأم المتوفية';
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-danger');
                    } else {
                        motherInfoSection.style.opacity = '0';
                        motherInfoSection.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            motherInfoSection.style.display = 'none';
                        }, 300);
                        this.innerHTML =
                            '<i class="fas fa-plus me-2"></i>إضافة بيانات الأم المتوفية';
                        this.classList.remove('btn-danger');
                        this.classList.add('btn-primary');
                    }
                }, 10);
            });

            // ✅ تأكد من إظهار القسم قبل الإرسال
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                if (motherInfoSection && motherInfoSection.style.display === 'none') {
                    motherInfoSection.style.display = 'block';
                }
            });
        });
    </script>
@endpush


@push('scriptsCode')
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
                if (idNumber.length !== 9) {
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

@push('scriptsCode')
    {{-- تتبع الأخطاء على مستوى النظام --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // كائن تتبع الأخطاء
            window.ErrorTracker = {
                logs: [],
                startTime: new Date(),

                log: function(level, step, error, context = {}) {
                    const errorInfo = {
                        timestamp: new Date().toISOString(),
                        level: level,
                        step: step,
                        message: error?.message || error,
                        stack: error?.stack,
                        context: {
                            ...context,
                            url: window.location.href,
                            userAgent: navigator.userAgent,
                            timeFromStart: (new Date() - this.startTime) + 'ms'
                        }
                    };

                    this.logs.push(errorInfo);
                    console[level](`[${level.toUpperCase()}] ${step}:`, errorInfo);

                    if (level === 'error') {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في العملية',
                            text: error.message || 'حدث خطأ غير متوقع',
                            footer: `<a href="#" onclick="ErrorTracker.showDetails('${this.logs.length - 1}')">عرض التفاصيل</a>`
                        });
                    }
                },

                showDetails: function(logIndex) {
                    const error = this.logs[logIndex];
                    if (!error) return;

                    Swal.fire({
                        title: 'تفاصيل الخطأ',
                        html: `
                        <div dir="ltr" class="text-start">
                            <pre style="text-align: left;">
                            خطوة: ${error.step}
                            وقت: ${error.timestamp}
                            الرسالة: ${error.message}
                            المتصفح: ${error.context.userAgent}
                            المسار: ${error.stack || 'غير متوفر'}
                                                        </pre>
                                                    </div>
                    `,
                        width: '800px'
                    });
                },

                getFullLog: function() {
                    return this.logs;
                }
            };

            // تعريف المتغيرات
            const confirmBtn = document.getElementById('confirmUpload');
            const fileInput = document.getElementById('document_file');
            const personSelector = document.getElementById('person_selector');
            const docTypeSelect = document.getElementById('document_type');
            const docs = new Map();

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    try {
                        const file = fileInput.files[0];
                        const selectedPerson = personSelector.value;
                        const docType = docTypeSelect.value;


                        const docId = `doc_${Date.now()}`;
                        let idNumber = '';

                        try {
                            if (selectedPerson === 'main') {
                                idNumber = document.querySelector('input[name="data_id_number"]').value;
                            } else if (selectedPerson.startsWith('deceased_')) {
                                const parent = selectedPerson.split('_')[1];
                                idNumber = document.querySelector(`input[name="${parent}_id"]`)?.value ||
                                    '';
                            } else if (selectedPerson.startsWith('family_')) {
                                const index = selectedPerson.split('_')[1];
                                idNumber = document.querySelector(
                                    `input[name="family_members[${index}][person_id]"]`)?.value || '';
                            }
                        } catch (error) {
                            ErrorTracker.log('error', 'get_id_number', error, {
                                selectedPerson
                            });
                        }

                        try {
                            const docInfo = {
                                file: file,
                                personType: selectedPerson,
                                documentType: docType,
                                idNumber: idNumber
                            };

                            docs.set(docId, docInfo);
                        } catch (error) {
                            ErrorTracker.log('error', 'store_doc_info', error, {
                                docId,
                                selectedPerson
                            });
                        }

                        // إعادة تعيين الحقول
                        $('#person_selector').val('');
                        $('#document_type').val('');
                        $('#document_file').val('');
                        $('#preview').addClass('d-none');
                        $('#preview img').attr('src', '');
                        $('.is-invalid').removeClass('is-invalid');
                        $('.invalid-feedback').remove();

                    } catch (error) {
                        ErrorTracker.log('error', 'document_upload', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في رفع الملف',
                            text: error.message
                        });
                    }
                });
            }

            // إرسال النموذج
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    try {
                        ErrorTracker.log('info', 'form_submission_start', 'بدء إرسال النموذج');

                        docs.forEach((docInfo, docId) => {
                            try {
                                ErrorTracker.log('debug', 'process_doc', `معالجة المرفق: ${docId}`,
                                    docInfo);

                                const formData = new FormData();
                                formData.append(`documents[${docId}][file]`, docInfo.file);
                                formData.append(`documents[${docId}][type]`, docInfo.documentType ||
                                    '');
                                formData.append(`documents[${docId}][person_type]`, docInfo
                                    .personType || '');
                                formData.append(`documents[${docId}][identity_number]`, docInfo
                                    .idNumber || '');

                                for (let [key, value] of formData.entries()) {
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = key;
                                    input.value = typeof value === 'string' ? value : '';
                                    form.appendChild(input);
                                }

                            } catch (docError) {
                                ErrorTracker.log('error', 'process_doc_error', docError, {
                                    docId,
                                    docInfo
                                });
                            }
                        });

                        ErrorTracker.log('info', 'form_submission_complete', 'تم تجهيز النموذج للإرسال');

                    } catch (error) {
                        e.preventDefault();
                        ErrorTracker.log('error', 'form_submission_failed', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في إرسال النموذج',
                            text: error.message || 'حدث خطأ أثناء الإرسال'
                        });
                    }
                });
            }
        });
    </script>
@endpush


@push('scriptsCode')
    {{-- تحديث الحقول المخفية عند تغيير الشخص أو نوع الوثيقة --}}
    <script>
        // Always update hidden input when person_selector changes
        document.getElementById('person_selector').addEventListener('change', function() {
            document.getElementById('person_identity_number_hidden').value = this.value;
        });

        // Always update hidden input when document_type changes
        document.getElementById('document_type').addEventListener('change', function() {
            document.getElementById('file_type_hidden').value = this.value;
        });

        // On form submit, ensure hidden inputs are set to current select values
        document.querySelector('form').addEventListener('submit', function(e) {
            var personSelector = document.getElementById('person_selector');
            var hiddenInput = document.getElementById('person_identity_number_hidden');
            var docTypeSelect = document.getElementById('document_type');
            var fileTypeHidden = document.getElementById('file_type_hidden');
            if (personSelector && hiddenInput) {
                hiddenInput.value = personSelector.value;
            }
            if (docTypeSelect && fileTypeHidden) {
                fileTypeHidden.value = docTypeSelect.value;
            }
            // Prevent submit if not selected
            if (!hiddenInput.value) {
                e.preventDefault();
                alert('الرجاء اختيار الشخص للمرفق');
                var tab = document.querySelector('#attachments-tab');
                if (tab) new bootstrap.Tab(tab).show();
                personSelector.focus();
                return false;
            }
            if (!fileTypeHidden.value) {
                e.preventDefault();
                alert('الرجاء اختيار نوع الوثيقة');
                var tab = document.querySelector('#attachments-tab');
                if (tab) new bootstrap.Tab(tab).show();
                docTypeSelect.focus();
                return false;
            }
            // ...existing code...
        });
    </script>
@endpush

@push('scriptsCode')
    <script>
        // إرسال النموذج باستخدام Ajax
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('main_form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // تجهيز الحقول الديناميكية (المرفقات)
                    let index = 0;
                    docs.forEach((doc, docId) => {
                        // ملف المرفق
                        const fileInput = document.createElement('input');
                        fileInput.type = 'file';
                        fileInput.name = `attachments[${index}][file]`;
                        fileInput.style.display = 'none';
                        const dt = new DataTransfer();
                        dt.items.add(doc.file);
                        fileInput.files = dt.files;
                        form.appendChild(fileInput);

                        // رقم الهوية
                        const personIdInput = document.createElement('input');
                        personIdInput.type = 'hidden';
                        personIdInput.name = `attachments[${index}][person_identity_number]`;
                        personIdInput.value = doc.personId;
                        form.appendChild(personIdInput);

                        // اسم الملف
                        const fileNameInput = document.createElement('input');
                        fileNameInput.type = 'hidden';
                        fileNameInput.name = `attachments[${index}][stored_file_name]`;
                        fileNameInput.value = doc.name;
                        form.appendChild(fileNameInput);

                        // نوع الوثيقة
                        const fileTypeInput = document.createElement('input');
                        fileTypeInput.type = 'hidden';
                        fileTypeInput.name = `attachments[${index}][file_type]`;
                        fileTypeInput.value = doc.type;
                        form.appendChild(fileTypeInput);

                        // رقم الملف العام
                        const fileIdInput = document.createElement('input');
                        fileIdInput.type = 'hidden';
                        fileIdInput.name = `attachments[${index}][file_id_number]`;
                        fileIdInput.value = document.getElementById('document_id').value;
                        form.appendChild(fileIdInput);

                        index++;
                    });

                    // تجهيز البيانات للإرسال
                    const formData = new FormData(form);

                    // إزالة الحقول الديناميكية بعد التجهيز حتى لا تتكرر في الإرسال القادم
                    Array.from(form.querySelectorAll(
                        'input[type="file"][name^="attachments"], input[type="hidden"][name^="attachments"]'
                    )).forEach(el => el.remove());

                    // إرسال البيانات عبر Ajax
                    fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                            }
                        })
                        .then(async response => {
                            let data;
                            try {
                                data = await response.clone().json();
                            } catch {
                                data = await response.text();
                            }
                            // لا تعرض أي console.log هنا
                            return {
                                data,
                                status: response.status
                            };
                        })
                        .then(({
                            data,
                            status
                        }) => {
                            // تحقق من نوع الاستجابة
                            if (typeof data === 'object' && data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الحفظ',
                                    text: 'تم حفظ السجل بنجاح'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else if (status === 200 && typeof data === 'string' && data.indexOf(
                                    'success') !== -1) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الحفظ',
                                    text: 'تم حفظ السجل بنجاح'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'خطأ',
                                    text: (data && data.error) ? data.error :
                                        'حدث خطأ أثناء الحفظ'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: error.message || 'حدث خطأ أثناء الحفظ'
                            });
                        });
                });
            }
        });
    </script>
@endpush

@push('scriptsCode')
    <script>
        // تحديث أفراد الأسرة تلقائياً حسب اختيار القسم
        document.addEventListener('DOMContentLoaded', function() {
            const sectionSelect = document.querySelector('select[name="data_section_id"]');
            const addFamilyMemberBtn = document.getElementById('addFamilyMember');

            function fillFamilyMemberFields(template) {
                const sectionValue = sectionSelect.value;
                if (sectionValue === '1') {
                    // قسم الأيتام: خذ من الأب المتوفي
                    const fatherFirst = document.querySelector('input[name="father_first_name"]')?.value || '';
                    const fatherSecond = document.querySelector('input[name="father_second_name"]')?.value || '';
                    const fatherLast = document.querySelector('input[name="father_last_name"]')?.value || '';
                    template.querySelector('input[name*="[second_name]"]').value = fatherFirst;
                    template.querySelector('input[name*="[third_name]"]').value = fatherSecond;
                    template.querySelector('input[name*="[last_name]"]').value = fatherLast;
                } else {
                    // غير الأيتام: خذ من البيانات الأساسية
                    const dataFirst = document.querySelector('input[name="data_first_name"]')?.value || '';
                    const dataFather = document.querySelector('input[name="data_father_name"]')?.value || '';
                    const dataFamily = document.querySelector('input[name="data_family_name"]')?.value || '';
                    template.querySelector('input[name*="[second_name]"]').value = dataFirst;
                    template.querySelector('input[name*="[third_name]"]').value = dataFather;
                    template.querySelector('input[name*="[last_name]"]').value = dataFamily;
                }
            }

            // عند إضافة فرد جديد
            addFamilyMemberBtn.addEventListener('click', function() {
                setTimeout(() => {
                    const templates = document.querySelectorAll('.family-member-form');
                    const lastTemplate = templates[templates.length - 1];
                    fillFamilyMemberFields(lastTemplate);
                }, 50);
            });

            // عند تغيير القسم، حدث أفراد الأسرة الحاليين
            sectionSelect.addEventListener('change', function() {
                document.querySelectorAll('.family-member-form').forEach(template => {
                    fillFamilyMemberFields(template);
                });
            });
        });
    </script>
@endpush

@push('scriptsCode')
    <script>
        // بوابة عرض المعلومات المدخلة قبل الحفظ النهائي
        document.addEventListener('DOMContentLoaded', function() {
            // زر "التالي" في بوابة المرفقات (أو أي زر انتقال)
            const formTabs = document.getElementById('formTabs');
            const reviewTabId = 'review-tab';
            const reviewPaneId = 'review';

            // إضافة تبويب جديد لعرض المعلومات (نفس تصميم الجوال)
            if (!document.getElementById(reviewTabId)) {
                const reviewTab = document.createElement('li');
                reviewTab.className = 'nav-item';
                reviewTab.innerHTML = `
                <button class="nav-link py-3" id="${reviewTabId}" data-bs-toggle="tab" data-bs-target="#${reviewPaneId}"
                    type="button" role="tab" aria-controls="${reviewPaneId}" aria-selected="false">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-eye tab-icon mb-2"></i>
                        <span class="fs-4 fw-bold tab-label">عرض المعلومات المدخلة</span>
                    </div>
                </button>
            `;
                formTabs.appendChild(reviewTab);
            }

            // إضافة محتوى البوابة إذا لم يكن موجوداً
            if (!document.getElementById('review')) {
                const tabContent = document.getElementById('formTabsContent');
                const reviewPane = document.createElement('div');
                reviewPane.className = 'tab-pane fade';
                reviewPane.id = 'review';
                reviewPane.setAttribute('role', 'tabpanel');
                reviewPane.setAttribute('aria-labelledby', 'review-tab');
                reviewPane.innerHTML = `
                <div class="container py-4">
                    <h3 class="mb-4 text-primary fw-bold" style="letter-spacing:1px;">
                        <i class="fas fa-eye me-2"></i>مراجعة جميع المعلومات المدخلة
                    </h3>
                    <div id="reviewContent"></div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-success btn-lg save-record-btn" id="finalSaveBtn">
                            <i class="fas fa-save me-2"></i>
                            حفظ السجل نهائياً
                        </button>
                    </div>
                </div>
            `;
                tabContent.appendChild(reviewPane);
            }

            // عند الضغط على زر الحفظ في بوابة المراجعة، أرسل النموذج
            document.getElementById('review').addEventListener('click', function(e) {
                // لا شيء هنا (زر الحفظ سيعمل بشكل طبيعي)
            });

            // نقل زر الحفظ من بوابة المرفقات إلى بوابة المراجعة
            // (تم حذف زر الحفظ من المرفقات في ملف create.blade.php)
            // زر الحفظ الجديد هو الزر داخل بوابة المراجعة فقط

            // عند الضغط على زر الحفظ النهائي، أرسل النموذج
            document.getElementById('finalSaveBtn').addEventListener('click', function(e) {
                e.preventDefault();
                // إرسال النموذج الرئيسي
                document.getElementById('main_form').requestSubmit();
            });

            // عند الانتقال إلى بوابة المراجعة، اعرض البيانات
            document.getElementById(reviewTabId).addEventListener('click', function() {
                renderReviewContent();
            });

            // دالة لجمع وعرض جميع البيانات المدخلة
            function renderReviewContent() {
                const reviewContent = document.getElementById('reviewContent');
                if (!reviewContent) return;

                // اجمع البيانات من الحقول
                const getVal = name => document.querySelector(`[name="${name}"]`)?.value || '';
                const getSelText = name => {
                    const sel = document.querySelector(`[name="${name}"]`);
                    return sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : '';
                };

                // --- البيانات الأساسية ---
                let basicInfo = `
                <div class="card shadow-lg border-0 mb-4" style="border-radius:18px;">
                    <div class="card-header bg-primary text-white fw-bold fs-5 d-flex align-items-center" style="border-radius:18px 18px 0 0;">
                        <span style="font-size:1.25rem;">البيانات الأساسية</span>
                        <i class="fas fa-id-card-alt me-2 text-white"></i>
                    </div>
                    <div class="card-body bg-white">
                        <div class="row g-4">
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-layer-group me-1"></i>القسم:</span><br>${getSelText('data_section_id')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span><br>${getVal('data_id_number')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-user me-1"></i>الاسم الأول:</span><br>${getVal('data_first_name')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-user-friends me-1"></i>اسم الأب:</span><br>${getVal('data_father_name')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-users me-1"></i>اسم العائلة:</span><br>${getVal('data_family_name')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-calendar-alt me-1"></i>تاريخ الميلاد:</span><br>${getVal('data_birth_date')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-venus-mars me-1"></i>الجنس:</span><br>${getSelText('data_gender')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-phone me-1"></i>رقم الهاتف:</span><br>${getVal('data_phone_number')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-users me-1"></i>عدد أفراد الأسرة:</span><br>${getVal('data_number_of_individuals')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-heart me-1"></i>الحالة الاجتماعية:</span><br>${getSelText('data_marital_status')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-graduation-cap me-1"></i>المؤهل العلمي:</span><br>${getSelText('data_academic_qualification')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-exchange-alt me-1"></i>حالة النزوح:</span><br>${getSelText('data_displacement_status')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-map-marker-alt me-1"></i>العنوان الحالي:</span><br>${getVal('data_current_address')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-city me-1"></i>المدينة:</span><br>${getSelText('data_city')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-map me-1"></i>المحافظة:</span><br>${getSelText('data_province')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-heartbeat me-1"></i>الحالة الصحية:</span><br>${getSelText('data_health_status')}</div>
                        </div>
                    </div>
                </div>
            `;

                // --- أفراد الأسرة ---
                let familyHtml = '';
                document.querySelectorAll('.family-member-form').forEach((form, idx) => {
                    const getInputByName = n => form.querySelector(`[name$="[${n}]"]`)?.value || '';
                    const getSel = n => {
                        const sel = form.querySelector(`[name$="[${n}]"]`);
                        return sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : '';
                    };
                    familyHtml += `
                    <div class="card shadow border-0 mb-3" style="border-radius:14px;">
                        <div class="card-header bg-info text-white fw-bold d-flex align-items-center" style="border-radius:14px 14px 0 0;">
                            <span style="font-size:1.1rem;">فرد الأسرة #${idx + 1}</span>
                            <i class="fas fa-user-friends me-2 text-white"></i>
                        </div>
                        <div class="card-body bg-white">
                            <div class="row g-4">
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الأول:</span><br>${getInputByName('first_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الثاني:</span><br>${getInputByName('second_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الثالث:</span><br>${getInputByName('third_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-users me-1"></i>اسم العائلة:</span><br>${getInputByName('last_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span><br>${getInputByName('person_id')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-calendar-alt me-1"></i>تاريخ الميلاد:</span><br>${getInputByName('person_birth_date')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-hourglass-half me-1"></i>العمر:</span><br>${getInputByName('person_age')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-venus-mars me-1"></i>الجنس:</span><br>${getSel('person_gender')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-heartbeat me-1"></i>الحالة الصحية:</span><br>${getSel('person_health_status')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-hand-holding-heart me-1"></i>نوع الكفالة:</span><br>${getSel('person_type_of_guarantee')}</div>
                            </div>
                        </div>
                    </div>
                `;
                });

                // --- بيانات المتوفين ---
                let deceasedHtml = '';
                if (document.getElementById('deceased').style.display !== 'none') {
                    deceasedHtml = `
                    <div class="card shadow border-0 mb-4" style="border-radius:14px;">
                        <div class="card-header bg-danger text-white fw-bold d-flex align-items-center" style="border-radius:14px 14px 0 0;">
                            <span style="font-size:1.1rem;">بيانات المتوفين</span>
                            <i class="fas fa-user-times me-2 text-white"></i>
                        </div>
                        <div class="card-body bg-white">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 mb-2" style="background:#fff3f3;">
                                        <span class="fw-bold text-danger"><i class="fas fa-male me-1"></i>الأب المتوفى</span>
                                        <div class="mt-2">
                                            <span class="fw-bold"><i class="fas fa-user me-1"></i>الاسم:</span> ${getVal('father_first_name')} ${getVal('father_last_name')}<br>
                                            <span class="fw-bold"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span> ${getVal('father_id')}<br>
                                            <span class="fw-bold"><i class="fas fa-calendar-alt me-1"></i>تاريخ الوفاة:</span> ${getVal('father_death_date')}<br>
                                            <span class="fw-bold"><i class="fas fa-skull-crossbones me-1"></i>سبب الوفاة:</span> ${getSelText('father_death_reason')}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 mb-2" style="background:#fff3f3;">
                                        <span class="fw-bold text-danger"><i class="fas fa-female me-1"></i>الأم المتوفية</span>
                                        <div class="mt-2">
                                            <span class="fw-bold"><i class="fas fa-user me-1"></i>الاسم:</span> ${getVal('mother_first_name')} ${getVal('mother_last_name')}<br>
                                            <span class="fw-bold"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span> ${getVal('mother_id')}<br>
                                            <span class="fw-bold"><i class="fas fa-calendar-alt me-1"></i>تاريخ الوفاة:</span> ${getVal('mother_death_date')}<br>
                                            <span class="fw-bold"><i class="fas fa-skull-crossbones me-1"></i>سبب الوفاة:</span> ${getSelText('mother_death_reason')}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                }

                // --- المرفقات (صور) ---
                let attachmentsHtml = '';
                // صاحب الملف
                const mainDocs = document.querySelectorAll('#main_person_docs .document-card img');
                if (mainDocs.length) {
                    attachmentsHtml +=
                        `<div class="mb-3"><span class="fw-bold text-primary"><i class="fas fa-paperclip me-1"></i>مرفقات صاحب الملف:</span><div class="d-flex gap-2 flex-wrap">`;
                    mainDocs.forEach(img => {
                        attachmentsHtml +=
                            `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #0d6efd;background:#fff;">`;
                    });
                    attachmentsHtml += `</div></div>`;
                }
                // أفراد الأسرة
                document.querySelectorAll('#family_members_docs .documents-flex-container').forEach((container,
                    idx) => {
                    const imgs = container.querySelectorAll('img');
                    if (imgs.length) {
                        attachmentsHtml +=
                            `<div class="mb-3"><span class="fw-bold text-info"><i class="fas fa-paperclip me-1"></i>مرفقات فرد الأسرة #${idx + 1}:</span><div class="d-flex gap-2 flex-wrap">`;
                        imgs.forEach(img => {
                            attachmentsHtml +=
                                `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #17a2b8;background:#fff;">`;
                        });
                        attachmentsHtml += `</div></div>`;
                    }
                });
                // المتوفين
                const fatherDocs = document.querySelectorAll('#father_docs img');
                if (fatherDocs.length) {
                    attachmentsHtml +=
                        `<div class="mb-3"><span class="fw-bold text-danger"><i class="fas fa-paperclip me-1"></i>مرفقات الأب المتوفى:</span><div class="d-flex gap-2 flex-wrap">`;
                    fatherDocs.forEach(img => {
                        attachmentsHtml +=
                            `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #dc3545;background:#fff;">`;
                    });
                    attachmentsHtml += `</div></div>`;
                }
                const motherDocs = document.querySelectorAll('#mother_docs img');
                if (motherDocs.length) {
                    attachmentsHtml +=
                        `<div class="mb-3"><span class="fw-bold text-danger"><i class="fas fa-paperclip me-1"></i>مرفقات الأم المتوفية:</span><div class="d-flex gap-2 flex-wrap">`;
                    motherDocs.forEach(img => {
                        attachmentsHtml +=
                            `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #dc3545;background:#fff;">`;
                    });
                    attachmentsHtml += `</div></div>`;
                }

                reviewContent.innerHTML = `
                <div class="row">
                    <div class="col-12">
                        ${basicInfo}
                        ${familyHtml}
                        ${deceasedHtml}
                        <div class="card shadow border-0 mb-4" style="border-radius:14px;">
                            <div class="card-header bg-primary text-white fw-bold d-flex align-items-center" style="border-radius:14px 14px 0 0;">
                                <span style="font-size:1.1rem;">المرفقات</span>
                                <i class="fas fa-paperclip me-2 text-white"></i>

                            </div>
                            <div class="card-body bg-white">${attachmentsHtml || '<span class="text-muted">لا يوجد مرفقات</span>'}</div>
                        </div>
                    </div>
                </div>
            `;
            }

            // عند الضغط على زر الحفظ في بوابة المراجعة، أرسل النموذج
            document.getElementById('review').addEventListener('submit', function(e) {
                // سيتم إرسال النموذج بشكل طبيعي (لا تمنع الإرسال هنا)
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .tab-icon {
            font-size: 2rem;
            color: #0d6efd;
            background: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            margin-bottom: 0.2rem;
            border: none !important;
            transition: none !important;
            box-shadow: none !important;
        }
        .nav-tabs .nav-link.active .tab-icon,
        .nav-tabs .nav-link:focus .tab-icon,
        .nav-tabs .nav-link:hover .tab-icon {
            background: none !important;
            color: #0d6efd !important;
            border: none !important;
            transform: none !important;
            box-shadow: none !important;
        }
        @media (max-width: 576px) {
            .mobile-bottom-tabs {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 1050;
                background: rgba(245,245,245,0.95);
                box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
                margin-bottom: 0 !important;
                border-top: 1.5px solid #e5e7eb;
                border-radius: 22px 22px 0 0;
                padding: 0.2rem 0.5rem 0.3rem 0.5rem;
                display: flex !important;
                justify-content: space-between;
                gap: 0 !important;
            }
            .mobile-bottom-tabs .nav-item {
                flex: 1 1 0;
                display: flex;
                justify-content: center;
                align-items: stretch;
                position: relative;
            }
            .mobile-bottom-tabs .nav-link {
                padding: 0.4rem 0 !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                display: flex;
                flex-direction: column;
                align-items: center;
                border-radius: 18px !important;
                position: relative;
                height: 100%;
                min-width: 0;
            }
            .mobile-bottom-tabs .tab-label {
                display: none !important;
            }
            .tab-icon {
                font-size: 2.1rem !important;
                color: #232323 !important;
                background: rgba(200,200,200,0.18) !important;
                border-radius: 16px !important;
                padding: 0.55rem !important;
                margin-bottom: 0 !important;
                border: none !important;
                box-shadow: 0 1px 6px rgba(0,0,0,0.04) !important;
                transition: background 0.2s, color 0.2s, box-shadow 0.2s !important;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .nav-tabs .nav-link.active .tab-icon,
            .nav-tabs .nav-link:focus .tab-icon,
            .nav-tabs .nav-link:hover .tab-icon {
                color: #232323 !important;
                background: rgba(44,44,44,0.13) !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.10) !important;
            }
            .mobile-bottom-tabs .nav-item:not(:last-child)::after {
                content: "";
                position: absolute;
                top: 18%;
                right: 0;
                width: 1.5px;
                height: 64%;
                background: #e5e7eb;
                border-radius: 2px;
                opacity: 0.85;
                z-index: 2;
            }
            body {
                padding-bottom: 80px !important;
            }
        }
    </style>

    <script>
        // ============================================
        // إدارة الحسابات البنكية الديناميكية
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const bankAccountsContainer = document.getElementById('bankAccountsContainer');
            const addBankAccountBtn = document.getElementById('addBankAccountBtn');
            let bankAccountCount = 0;
            const maxBankAccounts = 10;
            const bankNames = @json($bank_name);

            function createBankAccountForm(index) {
                return `
                <div class="bank-account-form border rounded p-3 mb-3 position-relative"
                     data-index="${index}"
                     style="border:2px dashed #0d6efd !important; background-color: #d8d8d8;">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-bank-account-btn"
                            title="حذف الحساب" style="z-index: 10;"></button>
                    <h6 class="mb-3 text-primary"><i class="fas fa-university me-2"></i>حساب بنكي رقم ${index + 1}</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">رقم هوية صاحب الحساب <span class="text-muted">(اختياري)</span></label>
                            <div class="input-group" style="max-width:320px;">
                                <input type="text" name="bank_accounts[${index}][person_owner_identity_number]"
                                       class="form-control bank-owner-id-input" maxlength="9" inputmode="numeric" pattern="[0-9]*"
                                       placeholder="أدخل رقم الهوية للجلب التلقائي"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                <span class="bank-civil-lookup-status input-group-text" style="display: none;">
                                    <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">اسم صاحب الحساب <span class="text-muted">(اختياري)</span></label>
                            <input type="text" name="bank_accounts[${index}][re_guardian_name]"
                                   class="form-control bank-owner-name-input" maxlength="100"
                                   placeholder="أدخل اسم صاحب الحساب">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">اسم البنك <span class="text-muted">(اختياري)</span></label>
                            <select name="bank_accounts[${index}][bank_name]" class="form-select">
                                <option value="">اختر البنك</option>
                                ${bankNames.map(bank => `<option value="${bank.id}">${bank.description}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم هاتف صاحب الحساب <span class="text-muted">(اختياري)</span></label>
                            <input type="text" name="bank_accounts[${index}][re_phone_number]"
                                   class="form-control" maxlength="20" inputmode="numeric"
                                   placeholder="أدخل رقم الهاتف"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم حساب البنك بالدولار (IBAN) <span class="text-muted">(اختياري)</span></label>
                            <input type="text" name="bank_accounts[${index}][iban_usd]"
                                   class="form-control" maxlength="34"
                                   placeholder="مثال: PS00XXXX0000000000000000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رقم حساب البنك بالشيكل (IBAN) <span class="text-muted">(اختياري)</span></label>
                            <input type="text" name="bank_accounts[${index}][iban_shekel]"
                                   class="form-control" maxlength="34"
                                   placeholder="مثال: PS00XXXX0000000000000000000">
                        </div>
                    </div>
                </div>
                `;
            }

            function updateRemoveButtons() {
                document.querySelectorAll('.remove-bank-account-btn').forEach(btn => {
                    btn.onclick = function(e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'تأكيد الحذف',
                            text: 'هل أنت متأكد أنك تريد حذف معلومات الحساب البنكي؟',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'نعم، احذف',
                            cancelButtonText: 'إلغاء'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const form = btn.closest('.bank-account-form');
                                form.remove();
                                bankAccountCount--;

                                // إعادة ترقيم الحسابات المتبقية
                                document.querySelectorAll('.bank-account-form').forEach((form, idx) => {
                                    form.setAttribute('data-index', idx);
                                    const title = form.querySelector('h6');
                                    if (title) {
                                        title.innerHTML = `<i class="fas fa-university me-2"></i>حساب بنكي رقم ${idx + 1}`;
                                    }
                                });

                                if (bankAccountCount < maxBankAccounts) {
                                    addBankAccountBtn.disabled = false;
                                }
                                if (bankAccountCount === 0) {
                                    bankAccountsContainer.classList.add('d-none');
                                }

                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الحذف',
                                    text: 'تم حذف الحساب البنكي بنجاح',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        });
                    }
                });
            }

            addBankAccountBtn.addEventListener('click', function() {
                if (bankAccountCount < maxBankAccounts) {
                    if (bankAccountsContainer.classList.contains('d-none')) {
                        bankAccountsContainer.classList.remove('d-none');
                    }
                    bankAccountsContainer.insertAdjacentHTML('beforeend', createBankAccountForm(bankAccountCount));
                    bankAccountCount++;
                    updateRemoveButtons();

                    // ربط السجل المدني للحساب البنكي الجديد - بعد تأخير صغير للتأكد من الـ render
                    setTimeout(() => {
                        const newForm = bankAccountsContainer.lastElementChild;
                        const idInput = newForm ? newForm.querySelector('.bank-owner-id-input') : null;
                        console.log('🔧 Setting up bank owner lookup:', {
                            newForm: newForm,
                            idInput: idInput,
                            bankAccountCount: bankAccountCount - 1
                        });
                        if (idInput) {
                            setupBankOwnerCivilLookup(idInput);
                        } else {
                            console.error('❌ Could not find ID input in new form');
                        }

                        // التمرير للحساب الجديد
                        if (newForm) {
                            newForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 100);

                    if (bankAccountCount >= maxBankAccounts) {
                        addBankAccountBtn.disabled = true;
                        Swal.fire({
                            icon: 'info',
                            title: 'تنبيه',
                            text: 'لقد وصلت للحد الأقصى من الحسابات البنكية (10 حسابات)',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'لا يمكن إضافة أكثر من 10 حسابات بنكية',
                        confirmButtonText: 'حسناً'
                    });
                }
            });

            // دالة ربط السجل المدني لصاحب الحساب البنكي
            function setupBankOwnerCivilLookup(idInput) {
                if (!idInput || idInput.dataset.bankCivilLookupAttached === 'true') return;

                const form = idInput.closest('.bank-account-form');
                const nameInput = form.querySelector('.bank-owner-name-input');
                const statusSpinner = form.querySelector('.bank-civil-lookup-status');

                console.log('🏦 Bank Civil Lookup Setup:', {
                    idInput: idInput ? 'found' : 'NOT FOUND',
                    nameInput: nameInput ? 'found' : 'NOT FOUND',
                    statusSpinner: statusSpinner ? 'found' : 'NOT FOUND'
                });

                let debounceTimer;

                function doLookup() {
                    const idValue = idInput.value.trim();

                    console.log('🔍 Attempting lookup for:', idValue);

                    if (idValue.length < 9) {
                        if (statusSpinner) statusSpinner.style.display = 'none';
                        return;
                    }

                    if (statusSpinner) statusSpinner.style.display = 'inline-block';

                    fetch(`/api/civil-registry/search-by-id?search_text=${idValue}`)
                        .then(response => {
                            console.log('📡 API Response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('📊 API Data received:', data);

                            if (statusSpinner) statusSpinner.style.display = 'none';

                            // الـ API يرجع {success: true, data: [...], ...}
                            const records = data.data || data;

                            if (records && records.length > 0) {
                                const person = records[0];
                                const fullName = [
                                    person.CI_FIRST_ARB || person.first_name,
                                    person.CI_FATHER_ARB || person.second_name,
                                    person.CI_GFATHE_ARB || person.third_name,
                                    person.CI_FAMILY_ARB || person.last_name
                                ].filter(Boolean).join(' ');

                                console.log('✅ Full name constructed:', fullName);
                                console.log('📝 Name input element:', nameInput);

                                if (nameInput && fullName) {
                                    nameInput.value = fullName;
                                    console.log('✅ Name filled successfully!');
                                } else {
                                    console.error('❌ Failed to fill name:', {
                                        nameInput: nameInput,
                                        fullName: fullName
                                    });
                                }
                            } else {
                                console.warn('⚠️ No data found for ID:', idValue);
                            }
                        })
                        .catch(error => {
                            console.error('❌ خطأ في جلب بيانات السجل المدني:', error);
                            if (statusSpinner) statusSpinner.style.display = 'none';
                        });
                }

                idInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(doLookup, 500);
                });

                // إضافة change و blur للتأكد من التفعيل
                idInput.addEventListener('change', function() {
                    doLookup();
                });

                idInput.addEventListener('blur', function() {
                    if (idInput.value.trim().length >= 9) {
                        doLookup();
                    }
                });

                // تفعيل البحث التلقائي إذا كان الحقل ممتلئ مسبقاً
                if (idInput.value.trim().length >= 9) {
                    console.log('🚀 Auto-triggering lookup for pre-filled value');
                    doLookup();
                }

                idInput.dataset.bankCivilLookupAttached = 'true';
            }
        });
    </script>
@endpush
@include('admin.dashboard.records_management.editSectionJavascript.civilRegistryAutofill')
@push('scriptsCode')
<script>
(function () {
    // إدارة المتوفين الإضافيين في نموذج الإنشاء
    var createDeceasedCount = 0;
    var createDeathReasonsData = @json($death_reasons->map(fn($dr) => ['id' => $dr->id, 'desc' => $dr->description]));

    function buildCreateDeathReasonsOptions() {
        var opts = '<option value="">اختر</option>';
        createDeathReasonsData.forEach(function (dr) {
            opts += '<option value="' + dr.id + '">' + dr.desc + '</option>';
        });
        return opts;
    }

    function buildCreateAdditionalDeceasedForm(index) {
        return `
        <div class="additional-deceased-form border rounded p-3 mb-3 position-relative"
             data-index="${index}"
             style="border: 2px dashed #dc3545 !important; background:#fff8f8;">
            <button type="button"
                class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 remove-additional-deceased-create"
                title="حذف"><i class="fas fa-times"></i></button>
            <h6 class="text-danger fw-bold mb-3"><i class="fas fa-user-times me-2"></i>متوفي رقم ${index + 1}</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">رقم الهوية</label>
                    <div class="input-group">
                        <input type="text" name="additional_deceased[${index}][id_number]"
                               class="form-control additional-deceased-id-input" data-index="${index}"
                               inputmode="numeric" maxlength="9"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                               placeholder="رقم الهوية">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">صلة القرابة</label>
                    <select name="additional_deceased[${index}][relationship]" class="form-select">
                        <option value="">اختر</option>
                        <option value="father">أب</option><option value="mother">أم</option>
                        <option value="brother">أخ</option><option value="sister">أخت</option>
                        <option value="grandfather">جد</option><option value="grandmother">جدة</option>
                        <option value="uncle">عم</option><option value="aunt">عمة</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاريخ الوفاة</label>
                    <input type="date" name="additional_deceased[${index}][death_date]" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم الأول</label>
                    <input type="text" name="additional_deceased[${index}][first_name]" class="form-control" placeholder="الاسم الأول">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم الثاني</label>
                    <input type="text" name="additional_deceased[${index}][second_name]" class="form-control" placeholder="الاسم الثاني">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم الثالث</label>
                    <input type="text" name="additional_deceased[${index}][third_name]" class="form-control" placeholder="الاسم الثالث">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم العائلة</label>
                    <input type="text" name="additional_deceased[${index}][last_name]" class="form-control" placeholder="اسم العائلة">
                </div>
                <div class="col-md-6">
                    <label class="form-label">سبب الوفاة</label>
                    <select name="additional_deceased[${index}][death_reason]" class="form-select">
                        ${buildCreateDeathReasonsOptions()}
                    </select>
                </div>
            </div>
        </div>`;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var addBtn = document.getElementById('addAdditionalDeceasedBtnCreate');
        var container = document.getElementById('additionalDeceasedContainerCreate');
        var noMsg = document.getElementById('noAdditionalDeceasedMsgCreate');

        if (addBtn && container) {
            addBtn.addEventListener('click', function () {
                if (noMsg) noMsg.style.display = 'none';
                container.insertAdjacentHTML('beforeend', buildCreateAdditionalDeceasedForm(createDeceasedCount));
                var newForm = container.lastElementChild;
                var idInput = newForm.querySelector('.additional-deceased-id-input');
                if (idInput && window.setupAdditionalDeceasedLookup) {
                    window.setupAdditionalDeceasedLookup(idInput, createDeceasedCount);
                }
                createDeceasedCount++;
            });

            container.addEventListener('click', function (e) {
                var btn = e.target.closest('.remove-additional-deceased-create');
                if (btn) {
                    btn.closest('.additional-deceased-form').remove();
                    if (container.querySelectorAll('.additional-deceased-form').length === 0 && noMsg) {
                        noMsg.style.display = 'block';
                    }
                }
            });
        }
    });
})();
</script>
@endpush
