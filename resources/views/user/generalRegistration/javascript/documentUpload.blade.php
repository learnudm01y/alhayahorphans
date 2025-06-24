@push('scriptsCodeUserRegistration')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                    previewImg.src =
                        '/path/to/default/document/icon.png'; // استبدل بمسار أيقونة المستند الافتراضية
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
                    idNumber = document.querySelector(
                        `input[name="family_members[${familyIndex}][person_id]"]`).value;
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
                        document.getElementById('family_members_docs').insertAdjacentHTML('beforeend',
                            sectionHTML);
                    }

                    docFlexContainer = document.querySelector(`#docs_container_${selectedPerson}`);
                    if (!docFlexContainer) {
                        const containerHTML = `
                    <div class="documents-flex-container" id="docs_container_${selectedPerson}"></div>
                `;
                        document.querySelector(`#family_member_${selectedPerson}`).insertAdjacentHTML(
                            'beforeend',
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

            // دالة إضافة فرد جديد
            function addFamilyMemberHandler() {
                const template = document.querySelector('.family-member-form').cloneNode(true);
                const parentForm = document.querySelector('.family-member-form');

                // تحديث هيكل النموذج
                template.className = 'family-member-form card border-0 shadow-sm mb-4';
                template.innerHTML = `
                            <div class="card-header bg-gradient-primary text-dark py-3 d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0 d-flex align-items-center">
                                    <i class="fas fa-user fs-4 me-2"></i>
                                    بيانات فرد الأسرة
                                </h5>
                                <button type="button" class="btn btn-danger btn-sm delete-member">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="card-body bg-light">
                                <div class="row g-3">
                                    ${parentForm.querySelector('.row').innerHTML}
                                </div>
                            </div>
                        `;

                // تحديث الأسماء والقيم
                template.querySelectorAll('input, select').forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace('[0]', `[${familyMemberCount}]`);

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
                            input.setAttribute('maxlength', '10');
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

                // تفريغ عناصر رفع الملفات عند الاستنساخ
                template.querySelectorAll('.mainDocumentTypeSelect').forEach(sel => sel.value = '');
                template.querySelectorAll('.mainDocumentFileInput').forEach(inp => inp.value = '');
                // الأهم: تفريغ معاينة وأسماء الملفات دائماً
                template.querySelectorAll('.mainDocumentPreview').forEach(div => div.innerHTML = '');
                template.querySelectorAll('.mainDocumentNames').forEach(div => div.innerHTML = '');

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
                familyMemberCount++;
            }

            // اجعل الدالة متاحة عالمياً
            window.addFamilyMemberHandler = addFamilyMemberHandler;

            // اربط الزر بالدالة باستخدام addEventListener فقط (مرة واحدة)
            const addFamilyMemberBtn = document.getElementById('addFamilyMember');
            if (addFamilyMemberBtn) {
                // أزل أي مستمع onclick سابق
                addFamilyMemberBtn.onclick = null;
                addFamilyMemberBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    addFamilyMemberHandler();
                    setTimeout(updatePersonsList, 100); // تحديث القائمة بعد الإضافة
                });
            }

            // تعريف المتغير مرة واحدة فقط هنا
            if (typeof familyMembersContainer === 'undefined') {
                const familyMembersContainer = document.getElementById('familyMembersContainer');
            }

            // مراقبة التغييرات في نماذج أفراد الأسرة
            if (familyMembersContainer) {
                familyMembersContainer.addEventListener('input', function(e) {
                    if (e.target.name && (e.target.name.includes('[first_name]') || e.target.name.includes(
                            '[last_name]'))) {
                        updatePersonsList();
                    }
                });

                // مراقبة إضافة أفراد الأسرة بطريقة حديثة (MutationObserver بدلاً من DOMNodeInserted)
                const observer = new MutationObserver(function(mutationsList) {
                    for (const mutation of mutationsList) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(node => {
                                if (node.classList && node.classList.contains(
                                        'family-member-form')) {
                                    // يمكنك هنا استدعاء أي دالة تريدها عند إضافة فرد جديد
                                    // مثال: fillFamilyMemberFields(node);
                                }
                            });
                        }
                    }
                });
                observer.observe(familyMembersContainer, {
                    childList: true
                });
            }

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

            // تحديث القائمة عند تحميل الصفحة
            document.addEventListener('DOMContentLoaded', updatePersonsList);

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
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'الرجاء اختيار الشخص للمرفق'
                    });
                    var tab = document.querySelector('#attachments-tab');
                    if (tab) new bootstrap.Tab(tab).show();
                    personSelector.focus();
                    return false;
                }
                if (!fileTypeHidden.value) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'الرجاء اختيار نوع الوثيقة'
                    });
                    var tab = document.querySelector('#attachments-tab');
                    if (tab) new bootstrap.Tab(tab).show();
                    docTypeSelect.focus();
                    return false;
                }
                // ...existing code...
            });
        });

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

        // تحديث القائمة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', updatePersonsList);

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
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'الرجاء اختيار الشخص للمرفق'
                });
                var tab = document.querySelector('#attachments-tab');
                if (tab) new bootstrap.Tab(tab).show();
                personSelector.focus();
                return false;
            }
            if (!fileTypeHidden.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'الرجاء اختيار نوع الوثيقة'
                });
                var tab = document.querySelector('#attachments-tab');
                if (tab) new bootstrap.Tab(tab).show();
                docTypeSelect.focus();
                return false;
            }
            // ...existing code...
        });
    </script>
@endpush
