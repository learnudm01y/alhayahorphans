<script>
    // resources/js/records_management.js
    document.addEventListener('DOMContentLoaded', function() {
        console.log('RecordsManagement JS initialized');

        const attachmentsByPerson = new Map();
        const personSelector = document.getElementById('person_selector');
        const docTypeSelect = document.getElementById('document_type');
        const fileInput = document.getElementById('document_file');
        const preview = document.getElementById('preview');
        const previewList = document.getElementById('previewList');
        const confirmBtn = document.getElementById('confirmUpload');
        const mainForm = document.getElementById('main_form');
        const identityHidden = document.getElementById('identity_number');
        const fileIdHidden = document.getElementById('file_id_hidden');
        const documentIdField = document.getElementById('document_id');
        const selectedFilesByPerson = {};

        // ==========================
        // Helpers
        function getSelectedPersonKey() {
            return personSelector ? personSelector.value : '';
        }

        function getIdNumberForPerson() {
            if (!personSelector) return '';
            const opt = personSelector.options[personSelector.selectedIndex];
            return opt ? (opt.getAttribute('data-id-number') || '') : '';
        }

        function getFileIdNumberForPerson() {
            if (!personSelector) return '';
            const opt = personSelector.options[personSelector.selectedIndex];
            return opt ? (opt.getAttribute('data-id') || '') : '';
        }

        function padFileIdNumber(val) {
            return val.toString().padStart(6, '0');
        }

        function logInfo(msg, data = {}) {
            console.log('[Info]', msg, data);
        }

        function logError(msg, data = {}) {
            console.error('[Error]', msg, data);
        }

        // ==========================
        // Event: Change person
        if (personSelector) {
            personSelector.addEventListener('change', function() {
                const personKey = getSelectedPersonKey();
                const idNum = getIdNumberForPerson();
                const fileIdRaw = getFileIdNumberForPerson();
                if (identityHidden) identityHidden.value = idNum;
                if (fileIdHidden) fileIdHidden.value = fileIdRaw;
                if (documentIdField) documentIdField.value = fileIdRaw;

                if (preview) preview.classList.add('d-none');
                if (previewList) previewList.innerHTML = '';
                if (confirmBtn) confirmBtn.classList.add('d-none');
                if (fileInput) fileInput.value = '';
                if (docTypeSelect) docTypeSelect.value = '';
                logInfo('Person selected changed', {
                    personKey,
                    idNum,
                    fileIdRaw
                });
            });
        }

        // ==========================
        // Event: File input change
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const personKey = getSelectedPersonKey();
                if (!selectedFilesByPerson[personKey]) {
                    selectedFilesByPerson[personKey] = [];
                }
                if (fileInput.files && fileInput.files.length > 0) {
                    if (previewList) previewList.innerHTML = '';
                    Array.from(fileInput.files).forEach((file) => {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            const container = document.createElement('div');
                            container.style.position = 'relative';
                            container.style.width = '80px';
                            container.style.height = '80px';
                            container.style.border = '1px solid #ddd';
                            container.style.borderRadius = '4px';
                            container.style.overflow = 'hidden';
                            container.style.display = 'flex';
                            container.style.alignItems = 'center';
                            container.style.justifyContent = 'center';
                            container.style.backgroundColor = '#f8f9fa';
                            if (file.type.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = event.target.result;
                                img.style.maxWidth = '100%';
                                img.style.maxHeight = '100%';
                                container.appendChild(img);
                            } else {
                                const span = document.createElement('span');
                                span.textContent = file.name;
                                span.style.fontSize = '10px';
                                span.style.textAlign = 'center';
                                span.style.padding = '2px';
                                container.appendChild(span);
                            }
                            if (previewList) previewList.appendChild(container);
                        };
                        reader.readAsDataURL(file);
                        selectedFilesByPerson[personKey].push({
                            file,
                            file_type: docTypeSelect?.value || '',
                            person_identity_number: getIdNumberForPerson(),
                            file_id_number: getFileIdNumberForPerson()
                        });
                    });
                    if (preview) preview.classList.remove('d-none');
                    if (confirmBtn) confirmBtn.classList.remove('d-none');
                    logInfo('Files selected for preview', {
                        count: fileInput.files.length
                    });
                } else {
                    if (preview) preview.classList.add('d-none');
                    if (previewList) previewList.innerHTML = '';
                    if (confirmBtn) confirmBtn.classList.add('d-none');
                }
            });
        }

        // ==========================
        // Event: Confirm upload
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function(event) {
                event.preventDefault();
                const personKey = getSelectedPersonKey();
                const idNumRaw = getIdNumberForPerson();
                const fileIdRaw = getFileIdNumberForPerson();
                const docType = docTypeSelect ? docTypeSelect.value : '';
                if (!personKey) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'اختر الشخص أولاً'
                    });
                    return;
                }
                if (!idNumRaw || !fileIdRaw) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'رقم الهوية أو رقم الملف غير متوفر'
                    });
                    return;
                }
                if (!docType) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'اختر نوع الوثيقة'
                    });
                    return;
                }
                if (!fileInput.files || fileInput.files.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'اختر ملف/ملفات للرفع'
                    });
                    return;
                }

                // تحقق أن كل ملف هو instance من File وليس object فارغ
                for (let i = 0; i < fileInput.files.length; i++) {
                    if (!(fileInput.files[i] instanceof File) || !fileInput.files[i].name) {
                        Swal.fire({
                            icon: 'error',
                            title: 'ملف غير صالح',
                            text: 'يرجى اختيار ملف صالح للرفع'
                        });
                        return;
                    }
                }

                const fileIdPadded = padFileIdNumber(fileIdRaw);
                if (!attachmentsByPerson.has(personKey)) {
                    attachmentsByPerson.set(personKey, {
                        personIdentityNumber: idNumRaw,
                        fileIdNumber: fileIdPadded,
                        documents: []
                    });
                }
                const personEntry = attachmentsByPerson.get(personKey);
                Array.from(fileInput.files).forEach((file) => {
                    const ext = file.name.split('.').pop().toLowerCase();
                    const newFileName = `${docType}_${fileIdPadded}_${idNumRaw}.${ext}`;
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        const docObj = {
                            file: new File([file], newFileName, {
                                type: file.type,
                                lastModified: file.lastModified
                            }),
                            fileType: docType,
                            storedFileName: newFileName,
                            previewDataUrl: evt.target.result
                        };
                        personEntry.documents.push(docObj);
                        addDocToUI(personKey, docObj, idNumRaw);
                        logInfo('Added document to attachmentsByPerson', {
                            personKey,
                            newFileName
                        });
                    };
                    reader.readAsDataURL(file);
                });
                fileInput.value = '';
                if (preview) preview.classList.add('d-none');
                if (previewList) previewList.innerHTML = '';
                if (confirmBtn) confirmBtn.classList.add('d-none');
                if (docTypeSelect) docTypeSelect.value = '';
            });
        }

        // ==========================
        // Add Document to UI
        function addDocToUI(personKey, docObj, personNameOrId) {
            try {
                const {
                    fileType,
                    storedFileName,
                    previewDataUrl
                } = docObj;
                const personName = personSelector.options[personSelector.selectedIndex]?.text || personKey;
                const previewHtml = previewDataUrl ?
                    `<img src="${previewDataUrl}" class="img-fluid" style="max-height:100px;">` :
                    `<span class="text-muted">${storedFileName}</span>`;
                const cardHtml = `
                    <div class="document-card card" data-person-key="${personKey}" data-stored-file-name="${storedFileName}" style="width: 150px;">
                        <div class="card-header d-flex justify-content-between align-items-center p-2">
                            <h6 class="mb-0 small">${fileType}</h6>
                            <button type="button" class="btn btn-sm btn-danger delete-temp-attachment" data-person-key="${personKey}" data-stored-file-name="${storedFileName}">حذف</button>
                        </div>
                        <div class="card-body text-center p-2">
                            ${previewHtml}
                            <p class="small text-muted mb-0 mt-1">${personName}</p>
                        </div>
                    </div>`;

                if (personKey === 'main') {
                    document.getElementById('main_person_docs')?.insertAdjacentHTML('beforeend', cardHtml);
                } else if (personKey === 'deceased_father') {
                    // 🆕 إنشاء قسم الأب المتوفى إذا لم يكن موجوداً
                    let fatherSection = document.getElementById('father_docs');
                    if (!fatherSection) {
                        // البحث عن حاوية المتوفين
                        let deceasedContainer = null;
                        document.querySelectorAll('.card-body h5').forEach(h5 => {
                            if (h5.textContent.includes('وثائق الأفراد المتوفين')) {
                                deceasedContainer = h5.closest('.card-body')?.querySelector('.row');
                            }
                        });

                        if (!deceasedContainer) {
                            console.warn('لم يتم العثور على حاوية المتوفين');
                            return;
                        }
                        const fatherSectionHtml = `
                        <div class="col-md-6">
                            <div class="deceased-docs-section">
                                <div class="bg-primary bg-opacity-10 border border-primary rounded-top px-3 py-2 mb-2 text-primary fw-bold text-center">
                                    الأب المتوفى
                                </div>
                                <div id="father_docs" class="documents-flex-container d-flex flex-nowrap gap-3 py-2"></div>
                            </div>
                        </div>`;
                        deceasedContainer.insertAdjacentHTML('beforeend', fatherSectionHtml);
                        fatherSection = document.getElementById('father_docs');
                    }
                    fatherSection?.insertAdjacentHTML('beforeend', cardHtml);
                    // 🆕 إزالة رسالة "لا توجد مرفقات" إذا كانت موجودة
                    const fatherNoDocsMsg = fatherSection?.querySelector('.text-muted');
                    if (fatherNoDocsMsg && fatherNoDocsMsg.textContent.includes('لا توجد مرفقات')) {
                        fatherNoDocsMsg.remove();
                    }
                } else if (personKey === 'deceased_mother') {
                    // 🆕 إنشاء قسم الأم المتوفية إذا لم يكن موجوداً
                    let motherSection = document.getElementById('mother_docs');
                    if (!motherSection) {
                        // البحث عن حاوية المتوفين
                        let deceasedContainer = null;
                        document.querySelectorAll('.card-body h5').forEach(h5 => {
                            if (h5.textContent.includes('وثائق الأفراد المتوفين')) {
                                deceasedContainer = h5.closest('.card-body')?.querySelector('.row');
                            }
                        });

                        if (!deceasedContainer) {
                            console.warn('لم يتم العثور على حاوية المتوفين');
                            return;
                        }
                        const motherSectionHtml = `
                        <div class="col-md-6">
                            <div class="deceased-docs-section">
                                <div class="bg-primary bg-opacity-10 border border-primary rounded-top px-3 py-2 mb-2 text-primary fw-bold text-center">
                                    الأم المتوفية
                                </div>
                                <div id="mother_docs" class="documents-flex-container d-flex flex-nowrap gap-3 py-2"></div>
                            </div>
                        </div>`;
                        deceasedContainer.insertAdjacentHTML('beforeend', motherSectionHtml);
                        motherSection = document.getElementById('mother_docs');
                    }
                    motherSection?.insertAdjacentHTML('beforeend', cardHtml);
                    // 🆕 إزالة رسالة "لا توجد مرفقات" إذا كانت موجودة
                    const motherNoDocsMsg = motherSection?.querySelector('.text-muted');
                    if (motherNoDocsMsg && motherNoDocsMsg.textContent.includes('لا توجد مرفقات')) {
                        motherNoDocsMsg.remove();
                    }
                } else if (personKey.startsWith('family_')) {
                    const safeKey = personKey.replace(/[^a-zA-Z0-9_-]/g, '_');
                    let section = document.getElementById(`family_member_${safeKey}`);
                    if (!section) {
                        const wrapper = document.getElementById('family_members_docs');
                        if (!wrapper) return;
                        const sectionHtml = `
                        <div id="family_member_${safeKey}" class="mb-3">
                            <div class="bg-primary bg-opacity-10 border border-primary rounded-top px-3 py-2 mb-2 text-primary fw-bold text-center">
                                ${personName}
                            </div>
                            <div id="docs_container_${safeKey}" class="d-flex flex-nowrap overflow-auto gap-3 py-2"></div>
                        </div>`;
                        wrapper.insertAdjacentHTML('beforeend', sectionHtml);
                        section = document.getElementById(`family_member_${safeKey}`);
                    }
                    const container = document.getElementById(`docs_container_${safeKey}`);
                    container?.insertAdjacentHTML('beforeend', cardHtml);
                }
                const btn = document.querySelector(
                    `.delete-temp-attachment[data-person-key="${personKey}"][data-stored-file-name="${storedFileName}"]`
                );
                btn?.addEventListener('click', function() {
                    const pKey = this.getAttribute('data-person-key');
                    const sFileName = this.getAttribute('data-stored-file-name');
                    if (attachmentsByPerson.has(pKey)) {
                        const entry = attachmentsByPerson.get(pKey);
                        entry.documents = entry.documents.filter(d => d.storedFileName !== sFileName);
                        if (entry.documents.length === 0) {
                            attachmentsByPerson.delete(pKey);
                        }
                    }
                    this.closest('.document-card')?.remove();
                    logInfo('Removed document from attachmentsByPerson', {
                        personKey: pKey,
                        storedFileName: sFileName
                    });
                });
            } catch (err) {
                logError('Error in addDocToUI', {
                    error: err
                });
            }
        }

        // ==========================
        // Clear / Display Validation Errors
        function clearValidationErrors() {
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
            const generalAlert = document.getElementById('general-validation-alert');
            if (generalAlert) generalAlert.remove();
            const attachmentsAlert = document.querySelector('.alert-validation-attachments');
            if (attachmentsAlert) attachmentsAlert.remove();
        }

        function displayValidationErrors(errors) {
            clearValidationErrors();
            for (const field in errors) {
                const msgs = errors[field];
                if (field.startsWith('attachmentsByPerson') || field.startsWith('attachmentsData')) {
                    const tabEl = document.querySelector('#attachments-tab');
                    if (tabEl) {
                        const tab = new bootstrap.Tab(tabEl);
                        tab.show();
                    }
                    const container = document.getElementById('attachments');
                    if (container) {
                        let alertEl = container.querySelector('.alert-validation-attachments');
                        if (!alertEl) {
                            alertEl = document.createElement('div');
                            alertEl.className = 'alert alert-danger alert-validation-attachments';
                            container.prepend(alertEl);
                        }
                        const ul = document.createElement('ul');
                        msgs.forEach(msg => {
                            const li = document.createElement('li');
                            li.textContent = msg;
                            ul.appendChild(li);
                        });
                        alertEl.innerHTML = '<strong>خطأ في المرفقات:</strong>';
                        alertEl.appendChild(ul);
                    }
                } else {
                    const el = document.querySelector(`[name="${field}"]`);
                    if (el) {
                        el.classList.add('is-invalid');
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = msgs.join(' ');
                        el.insertAdjacentElement('afterend', feedback);
                    } else {
                        let generalAlert = document.getElementById('general-validation-alert');
                        if (!generalAlert) {
                            generalAlert = document.createElement('div');
                            generalAlert.id = 'general-validation-alert';
                            generalAlert.className = 'alert alert-danger';
                            mainForm.prepend(generalAlert);
                        }
                        generalAlert.textContent = msgs.join(' ');
                    }
                }
            }
        }

        // ==========================
        // Main Form Submission
        if (mainForm) {
            mainForm.addEventListener('submit', function(event) {
                event.preventDefault();
                (async function() {
                    clearValidationErrors();

                    const submitBtn = mainForm.querySelector('[type="submit"]');
                    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML =
                            '<span class="spinner-border spinner-border-sm"></span> جاري الحفظ...';
                    }

                    try {
                        const formData = new FormData(mainForm);
                        let pIndex = 0;

                        for (const [personKey, entry] of attachmentsByPerson.entries()) {
                            formData.append(
                                `attachmentsByPerson[${pIndex}][person_identity_number]`,
                                entry.personIdentityNumber);
                            formData.append(`attachmentsByPerson[${pIndex}][file_id_number]`,
                                entry.fileIdNumber);
                            formData.append(`attachmentsByPerson[${pIndex}][person_key]`,
                                personKey);

                            entry.documents.forEach((docObj, j) => {
                                // ✅ إضافة الملف فقط لو docObj.file هو نسخة من File
                                if (docObj.file instanceof File) {
                                    console.log(`[INFO] الملف الحالي: `, {
                                        name: docObj.file.name,
                                        type: docObj.file.type,
                                        size: docObj.file.size,
                                        isFileInstance: docObj.file instanceof File,
                                    });
                                    formData.append(
                                        `attachmentsByPerson[${pIndex}][documents][${j}][file]`,
                                        docObj.file
                                    );
                                } else {
                                    console.warn(`تم تجاوز الملف لأن docObj.file ليست File، بل:`, docObj.file);
                                }

                                // ✅ القيم النصية يتم إرسالها على كل حال
                                formData.append(
                                    `attachmentsByPerson[${pIndex}][documents][${j}][file_type]`,
                                    docObj.fileType ?? ''
                                );
                                formData.append(
                                    `attachmentsByPerson[${pIndex}][documents][${j}][stored_file_name]`,
                                    docObj.storedFileName ?? ''
                                );
                            });
                            pIndex++;
                        }

                        const actionUrl = mainForm.getAttribute('action') || window.location
                            .href;
                        const methodInput = mainForm.querySelector('input[name="_method"]');
                        const fetchMethod = methodInput ? 'POST' : (mainForm.getAttribute(
                            'method')?.toUpperCase() || 'POST');
                        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
                        const headers = csrfTokenMeta ? {
                            'X-CSRF-TOKEN': csrfTokenMeta.getAttribute('content'),
                        } : {};

                        const response = await fetch(actionUrl, {
                            method: fetchMethod,
                            headers,
                            body: formData,
                        });

                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnHtml;
                        }

                        if (!response.ok) {
                            if (response.status === 422) {
                                const data = await response.json();
                                displayValidationErrors(data.errors || {});
                                return;
                            }
                            let errText = '';
                            try {
                                errText = await response.text();
                            } catch (_) {}

                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: `خطأ على الخادم (${response.status})${errText ? ': ' + errText : ''}`
                            });
                            logError('Error submitting form', {
                                status: response.status,
                                error: errText,
                            });
                            return;
                        }

                        const resultData = await response.json().catch(() => null);
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحفظ',
                            text: resultData?.message || 'تم حفظ البيانات بنجاح',
                        });
                        logInfo('Form submission success', {
                            resultData
                        });

                    } catch (err) {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnHtml;
                        }
                        logError('Error submitting form', {
                            error: err
                        });
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: err.message || 'حدث خطأ أثناء الحفظ',
                        });
                    }
                })();
            });
        }

        // ==========================
        // Deleting Old Attachments
        document.querySelectorAll('.delete-attachment').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const attId = this.getAttribute('data-id');
                if (!mainForm) return;
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: "سيتم حذف هذا المرفق!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // حذف من قاعدة البيانات عبر AJAX
                        fetch('/admin/records-management/delete-attachment/' + attId, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // فقط عند موافق أضف إلى مصفوفة الحذف واحذف من الواجهة
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'attachments_to_delete[]';
                                input.value = attId;
                                mainForm.appendChild(input);
                                const card = btn.closest('.document-card');
                                card?.remove();
                                logInfo('Marked existing attachment for deletion', {
                                    attachmentId: attId
                                });
                                Swal.fire('تم الحذف!', 'تم حذف المرفق بنجاح.', 'success');
                            } else {
                                Swal.fire('خطأ!', data.message || 'حدث خطأ أثناء الحذف من قاعدة البيانات.', 'error');
                            }
                        })
                        .catch(() => {
                            Swal.fire('خطأ!', 'حدث خطأ أثناء الحذف من قاعدة البيانات.', 'error');
                        });
                    }
                });
            });
        });

        // عند تجهيز بيانات أفراد الأسرة للإرسال، تأكد من أن كل عنصر له file_id (رقم الملف)
        // إذا لم يوجد، استخدم رقم الملف الرئيسي من النموذج
        function ensureFamilyMembersFileId(formData) {
            // جلب رقم الملف الرئيسي من النموذج
            var mainFileId = '';
            var fileIdInput = document.querySelector('[name="file_id_number"]');
            if (fileIdInput) mainFileId = fileIdInput.value;

            // جلب أفراد الأسرة من الذاكرة أو من النموذج (حسب التطبيق)
            // إذا كنت تجمعهم من JS:
            if (window.familyMembers && Array.isArray(window.familyMembers)) {
                window.familyMembers.forEach(function(member, idx) {
                    if (!member.file_id && mainFileId) {
                        member.file_id = mainFileId;
                    }
                    // أضفهم إلى formData إذا كنت ترسلهم يدويًا
                    Object.keys(member).forEach(function(key) {
                        formData.append(`family_members[${idx}][${key}]`, member[key]);
                    });
                });
            }
            // إذا كنت تعتمد على عناصر input ديناميكية:
            // تأكد أن كل input[name^="family_members"][name$="[file_id]"] له قيمة، وإن لم يكن أضف input مخفي بقيمة رقم الملف الرئيسي
            var memberInputs = document.querySelectorAll('input[name^="family_members"][name$="[file_id]"]');
            memberInputs.forEach(function(input) {
                if (!input.value && mainFileId) {
                    input.value = mainFileId;
                }
            });
        }

        // عند تجهيز attachmentsByPerson للإرسال، تأكد أن كل document يحمل file_id_number صحيح (رقم ملف الشخص)
        // إذا لم يوجد، استخدم رقم الملف الرئيسي من النموذج
        function ensureAttachmentsByPersonFileId(attachmentsByPersonMap) {
            var mainFileId = '';
            var fileIdInput = document.querySelector('[name="file_id_number"]');
            if (fileIdInput) mainFileId = fileIdInput.value;

            attachmentsByPersonMap.forEach(function(person, personKey) {
                // إذا لم يوجد fileIdNumber، جرب جلبه من data-id-number أو استخدم الرئيسي
                if (!person.fileIdNumber) {
                    // ابحث عن input أو عنصر يحمل data-id-number لهذا الشخص
                    var selector = `[data-person-key="${personKey}"][data-id-number]`;
                    var el = document.querySelector(selector);
                    if (el && el.getAttribute('data-id-number')) {
                        person.fileIdNumber = el.getAttribute('data-id-number');
                    } else if (mainFileId) {
                        person.fileIdNumber = mainFileId;
                    }
                }
                // تأكد أن كل document يحمل file_id_number الصحيح
                if (Array.isArray(person.documents)) {
                    person.documents.forEach(function(doc) {
                        if (!doc.fileIdNumber && person.fileIdNumber) {
                            doc.fileIdNumber = person.fileIdNumber;
                        }
                    });
                }
            });
        }

        // معالجة رقم الهوية ورقم الملف لأفراد الأسرة في attachmentsByPerson قبل الإرسال
        function ensureAttachmentsByPersonIdentityAndFileId(attachmentsByPersonMap) {
            var mainFileId = '';
            var fileIdInput = document.querySelector('[name="file_id_number"]');
            if (fileIdInput) mainFileId = fileIdInput.value;

            attachmentsByPersonMap.forEach(function(person, personKey) {
                // رقم الهوية: إذا لم يوجد، جرب جلبه من عنصر يحمل data-person-key وdata-identity أو من بيانات أفراد الأسرة
                if (!person.personIdentityNumber) {
                    // ابحث عن عنصر يحمل data-person-key وdata-identity
                    var el = document.querySelector(`[data-person-key="${personKey}"][data-identity]`);
                    if (el && el.getAttribute('data-identity')) {
                        person.personIdentityNumber = el.getAttribute('data-identity');
                    } else if (window.familyMembers && Array.isArray(window.familyMembers)) {
                        // جلب رقم الهوية من بيانات أفراد الأسرة إذا تطابق الشخص
                        var found = window.familyMembers.find(function(m) {
                            return m.person_key === personKey || m.id == personKey;
                        });
                        if (found && found.person_identity_number) {
                            person.personIdentityNumber = found.person_identity_number;
                        }
                    }
                }
                // رقم الملف: إذا لم يوجد، جرب جلبه من data-id-number أو استخدم الرئيسي
                if (!person.fileIdNumber) {
                    var el2 = document.querySelector(`[data-person-key="${personKey}"][data-id-number]`);
                    if (el2 && el2.getAttribute('data-id-number')) {
                        person.fileIdNumber = el2.getAttribute('data-id-number');
                    } else if (mainFileId) {
                        person.fileIdNumber = mainFileId;
                    }
                }
                // تأكد أن كل document يحمل file_id_number الصحيح
                if (Array.isArray(person.documents)) {
                    person.documents.forEach(function(doc) {
                        if (!doc.fileIdNumber && person.fileIdNumber) {
                            doc.fileIdNumber = person.fileIdNumber;
                        }
                    });
                }
            });
        }

        // عند تجهيز FormData للإرسال:
        function sendFormWithFiles(formSelector, url, method = 'POST') {
            const form = document.querySelector(formSelector);
            if (!form) return;

            const formData = new FormData(form);

            // معالجة رقم الهوية ورقم الملف لأفراد الأسرة في attachmentsByPerson
            if (window.attachmentsByPerson && typeof window.attachmentsByPerson.forEach === 'function') {
                ensureAttachmentsByPersonIdentityAndFileId(window.attachmentsByPerson);
                let pIndex = 0;
                window.attachmentsByPerson.forEach(function(person) {
                    formData.append(`attachmentsByPerson[${pIndex}][person_identity_number]`, person.personIdentityNumber || '');
                    formData.append(`attachmentsByPerson[${pIndex}][file_id_number]`, person.fileIdNumber || '');
                    if (Array.isArray(person.documents)) {
                        person.documents.forEach(function(doc, dIndex) {
                            if (doc.file instanceof File) {
                                formData.append(`attachmentsByPerson[${pIndex}][documents][${dIndex}][file]`, doc.file, doc.storedFileName);
                            }
                            formData.append(`attachmentsByPerson[${pIndex}][documents][${dIndex}][file_type]`, doc.fileType);
                            formData.append(`attachmentsByPerson[${pIndex}][documents][${dIndex}][stored_file_name]`, doc.storedFileName);
                            formData.append(`attachmentsByPerson[${pIndex}][documents][${dIndex}][file_id_number]`, doc.fileIdNumber || '');
                        });
                    }
                    pIndex++;
                });
            }

            // ...أكمل تجهيز بقية البيانات...

            fetch(url, {
                method: method,
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
            .then(response => response.json())
            .then(data => {
                // ... التعامل مع النجاح ...
            })
            .catch(error => {
                // ... التعامل مع الخطأ ...
            });
        }

        // مثال على الاستخدام عند الضغط على زر الحفظ:
        document.addEventListener('DOMContentLoaded', function() {
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    sendFormWithFiles('form', form.action, 'POST');
                });
            }
        });

        // إصلاح اختفاء رقم الملف عند اختيار قسم أفراد الأسرة في بوابة المرفقات
        document.addEventListener('DOMContentLoaded', function() {
            // ...existing code...

            // عند تغيير قسم المرفقات إلى "أفراد الأسرة"، أعد تعيين قيمة حقل رقم الملف إذا اختفت
            var sectionSelect = document.getElementById('attachments_section_select');
            var documentIdInput = document.getElementById('document_id');
            var mainFileIdInput = document.querySelector('[name="file_id_number"]');

            if (sectionSelect && documentIdInput && mainFileIdInput) {
                sectionSelect.addEventListener('change', function() {
                    // تحقق إذا كان القسم المختار هو أفراد الأسرة (حسب القيمة لديك)
                    // مثال: value == 'family_members' أو رقم القسم الخاص بأفراد الأسرة
                    if (this.value === 'family_members' || this.value === '2') {
                        // إذا كان الحقل فارغ أو تغير، أعد تعيينه
                        if (!documentIdInput.value || documentIdInput.value !== mainFileIdInput.value) {
                            documentIdInput.value = mainFileIdInput.value;
                        }
                    }
                });
            }
        });

        // راصد أخطاء لعرض أي خطأ يحدث عند تغيير أفراد الأسرة أو رقم الملف
        document.addEventListener('DOMContentLoaded', function() {
            // ...existing code...

            // راصد عند تغيير اختيار فرد الأسرة
            var familyMemberSelect = document.getElementById('family_member_select'); // غيّر id حسب تطبيقك
            var documentIdInput = document.getElementById('document_id');
            if (familyMemberSelect && documentIdInput) {
                familyMemberSelect.addEventListener('change', function() {
                    try {
                        var selectedOption = familyMemberSelect.options[familyMemberSelect.selectedIndex];
                        var memberFileId = selectedOption.getAttribute('data-id') || '';
                        documentIdInput.value = memberFileId;
                        // سجل في الكونسول للمراقبة
                        console.log('[DEBUG] Selected family member:', selectedOption.text, 'File ID:', memberFileId);
                    } catch (e) {
                        console.error('[ERROR] عند تغيير فرد الأسرة:', e);
                        alert('حدث خطأ عند تغيير فرد الأسرة: ' + e.message);
                    }
                });
            }

            // راصد عند تغيير نوع الوثيقة
            var documentTypeSelect = document.getElementById('document_type');
            if (documentTypeSelect && documentIdInput) {
                documentTypeSelect.addEventListener('change', function() {
                    try {
                        // لا تغير document_id هنا، فقط راقب
                        console.log('[DEBUG] Document type changed. Current file ID:', documentIdInput.value);
                    } catch (e) {
                        console.error('[ERROR] عند تغيير نوع الوثيقة:', e);
                        alert('حدث خطأ عند تغيير نوع الوثيقة: ' + e.message);
                    }
                });
            }

            // راصد عام لأي خطأ جافاسكريبت في الصفحة
            window.addEventListener('error', function(event) {
                console.error('[GLOBAL JS ERROR]', event.message, event.filename, event.lineno);
                alert('حدث خطأ في الصفحة: ' + event.message);
            });
        });
    });
</script>
