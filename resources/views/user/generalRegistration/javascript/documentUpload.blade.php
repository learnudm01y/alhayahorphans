@push('scriptsCodeUserRegistration')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Enhanced Attachment State Engine ---
    const allDocs = new Map();
    window.allDocs = allDocs;

    function generateTaskId() {
        return 'att_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
    }

    function addAttachmentTask(personKey, file, docType, personId, fileIdNumber) {
        const id = generateTaskId();
        const task = {
            id,
            personKey,
            originalFile: file,
            processedFile: null,
            docType,
            personId,
            fileIdNumber,
            status: 'pending',
            errorMessage: null,
            timestamp: Date.now()
        };
        if (!allDocs.has(personKey)) allDocs.set(personKey, []);
        allDocs.get(personKey).push(task);
        console.log('[Attachment] Task added', task);
        processAttachment(id);
        renderAttachmentTasksUI(personKey);
        return id;
    }

    function updateAttachmentTaskStatus(id, status, processedFile = null, errorMessage = null) {
        for (let [personKey, arr] of allDocs.entries()) {
            const task = arr.find(t => t.id === id);
            if (task) {
                task.status = status;
                if (processedFile !== undefined) task.processedFile = processedFile;
                if (errorMessage !== undefined) task.errorMessage = errorMessage;
                task.updatedAt = Date.now();
                console.log(`[Attachment] Task ${id} status updated:`, status, errorMessage);
                renderAttachmentTasksUI(personKey);
                return;
            }
        }
    }

    async function processAttachment(id) {
        let task;
        for (let arr of allDocs.values()) {
            task = arr.find(t => t.id === id);
            if (task) break;
        }
        if (!task) return;
        if (task.status !== 'pending') return;
        updateAttachmentTaskStatus(id, 'processing');
        if (task.originalFile.type && task.originalFile.type.startsWith('image/')) {
            try {
                const croppedFile = await showCropperModalPromise(task.originalFile, 60000);
                if (!croppedFile) {
                    updateAttachmentTaskStatus(id, 'failed', null, 'لم يتم قص الصورة أو حدث خطأ.');
                } else {
                    updateAttachmentTaskStatus(id, 'completed', croppedFile, null);
                }
            } catch (err) {
                updateAttachmentTaskStatus(id, 'failed', null, err && err.message ? err.message : 'خطأ غير معروف أثناء معالجة الصورة.');
            }
        } else {
            updateAttachmentTaskStatus(id, 'completed', task.originalFile, null);
        }
    }

    function showCropperModalPromise(file, timeoutMs = 60000) {
        return new Promise((resolve, reject) => {
            let finished = false;
            let timer = setTimeout(() => {
                if (!finished) {
                    finished = true;
                    reject(new Error('Timeout: لم يتم استكمال قص الصورة خلال الوقت المحدد.'));
                }
            }, timeoutMs);
            if (typeof window.showCropperModal !== 'function') {
                clearTimeout(timer);
                return reject(new Error('Cropper غير متوفر.'));
            }
            window.showCropperModal(file, function(croppedFile) {
                if (finished) return;
                finished = true;
                clearTimeout(timer);
                if (!croppedFile) return reject(new Error('لم يتم قص الصورة أو تم الإلغاء.'));
                resolve(croppedFile);
            });
        });
    }

    function removeAttachmentTask(personKey, taskId) {
        if (!allDocs.has(personKey)) return;
        const arr = allDocs.get(personKey);
        const idx = arr.findIndex(t => t.id === taskId);
        if (idx !== -1) {
            const task = arr[idx];
            if (task.processedFile && task.processedFile.previewUrl) {
                try { URL.revokeObjectURL(task.processedFile.previewUrl); } catch {}
            }
            arr.splice(idx, 1);
            if (arr.length === 0) allDocs.delete(personKey);
            renderAttachmentTasksUI(personKey);
        }
    }

    // حذف جميع المهام المرتبطة ببوابة معينة (مثلاً عند حذف فرد أسرة)
    function removeAllAttachmentTasksForPersonKey(personKey) {
        if (window.allDocs && window.allDocs.has(personKey)) {
            window.allDocs.delete(personKey);
        }
    }

    // تحديث renderAttachmentTasksUI: لا تعرض المرفقات إلا إذا كانت البوابة موجودة فعلياً في الصفحة
    function renderAttachmentTasksUI(personKey) {
        // تحقق أن البوابة موجودة فعلياً في الصفحة
        let previewDiv = null;
        if (personKey === 'main') previewDiv = document.getElementById('mainDocumentPreview');
        else if (personKey === 'deceased_father') previewDiv = document.querySelector('[data-upload-zone="deceased_father"] .mainDocumentPreview, #mainDocumentPreview');
        else if (personKey === 'deceased_mother') previewDiv = document.querySelector('[data-upload-zone="deceased_mother"] .mainDocumentPreview, #mainDocumentPreview');
        else if (personKey.startsWith('family_')) previewDiv = document.querySelector(`[data-upload-zone="${personKey}"] .mainDocumentPreview, #mainDocumentPreview_${personKey.replace('family_','')}`);
        // إذا لم توجد البوابة في الصفحة، لا تعرض شيئاً واحذف المرفقات من allDocs
        if (!previewDiv) {
            removeAllAttachmentTasksForPersonKey(personKey);
            return;
        }
        previewDiv.innerHTML = '';
        const arr = allDocs.get(personKey) || [];
        arr.forEach(task => {
            const card = document.createElement('div');
            card.className = 'card mb-2';
            card.style.width = '170px';
            card.style.display = 'inline-block';
            card.style.marginRight = '8px';
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body p-2 text-center';
            const docType = document.createElement('div');
            docType.className = 'fw-bold mb-1';
            docType.textContent = task.docType || '';
            cardBody.appendChild(docType);
            const docNameDiv = document.createElement('div');
            docNameDiv.className = 'small text-muted mb-1';
            docNameDiv.textContent = task.processedFile ? (task.processedFile.name || '') : (task.originalFile.name || '');
            cardBody.appendChild(docNameDiv);
            if (task.processedFile && task.processedFile.type && task.processedFile.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(task.processedFile);
                img.style.maxWidth = '100px';
                img.style.maxHeight = '100px';
                img.className = 'rounded border mb-1';
                img.onload = function() { URL.revokeObjectURL(img.src); };
                cardBody.appendChild(img);
            } else if (task.originalFile && task.originalFile.type && task.originalFile.type.startsWith('image/') && !task.processedFile) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(task.originalFile);
                img.style.maxWidth = '100px';
                img.style.maxHeight = '100px';
                img.className = 'rounded border mb-1';
                img.onload = function() { URL.revokeObjectURL(img.src); };
                cardBody.appendChild(img);
            }
            const statusDiv = document.createElement('div');
            statusDiv.className = 'mt-1';
            if (task.status === 'pending') {
                statusDiv.innerHTML = '<span class="badge bg-secondary">قيد الانتظار</span>';
            } else if (task.status === 'processing') {
                statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span> <span class="text-primary">جاري المعالجة...</span>';
            } else if (task.status === 'completed') {
                statusDiv.innerHTML = '<span class="badge bg-success">تمت المعالجة</span>';
            } else if (task.status === 'failed') {
                statusDiv.innerHTML = `<span class="badge bg-danger">فشل</span><br><span class="text-danger small">${task.errorMessage || 'خطأ غير معروف'}</span>`;
            }
            cardBody.appendChild(statusDiv);
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn btn-danger btn-sm mt-2';
            delBtn.textContent = 'حذف';
            delBtn.onclick = function() {
                Swal.fire({
                    title: 'تأكيد الحذف',
                    text: 'هل أنت متأكد أنك تريد حذف هذا المرفق؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        removeAttachmentTask(personKey, task.id);
                    }
                });
            };
            cardBody.appendChild(delBtn);
            card.appendChild(cardBody);
            previewDiv.appendChild(card);
        });
    }

    function initUploadZone(zone) {
        const personKey = zone.getAttribute('data-upload-zone');
        const fileInput = zone.querySelector('input[type="file"]');
        const docTypeSelect = zone.querySelector('select');
        if (!fileInput || !docTypeSelect) return;
        fileInput.addEventListener('change', function(e) {
            if (!docTypeSelect.value) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يجب اختيار نوع الوثيقة أولاً قبل رفع الملف.' });
                fileInput.value = '';
                return;
            }
            let personId = '';
            if (personKey === 'main') {
                personId = document.getElementById('data_id_number')?.value || '';
            } else if (personKey === 'deceased_father') {
                personId = document.querySelector('input[name="father_id"]')?.value || '';
            } else if (personKey === 'deceased_mother') {
                personId = document.querySelector('input[name="mother_id"]')?.value || '';
            } else if (personKey.startsWith('family_')) {
                const form = zone.closest('.family-member-form');
                personId = form ? form.querySelector('input[name$="[person_id]"]')?.value || '' : '';
                if (!personId) {
                    Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى إدخال رقم هوية فرد الأسرة أولاً قبل رفع الملف.' });
                    fileInput.value = '';
                    return;
                }
            }
            if (!personId) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى إدخال رقم الهوية أولاً قبل رفع الملف.' });
                fileInput.value = '';
                return;
            }
            Array.from(fileInput.files).forEach(file => {
                addAttachmentTask(personKey, file, docTypeSelect.value, personId, document.querySelector('input[name="file_id_number"]')?.value || '');
            });
        });
        docTypeSelect.addEventListener('change', function() {
            if (!allDocs.has(personKey)) return;
            allDocs.get(personKey).forEach(task => { task.docType = docTypeSelect.value; });
            renderAttachmentTasksUI(personKey);
        });
    }

    document.querySelectorAll('[data-upload-zone]').forEach(initUploadZone);

    // مراقبة حذف أفراد الأسرة من DOM، وحذف مرفقاتهم تلقائياً
    const familyContainer = document.getElementById('familyMembersContainer');
    if (familyContainer) {
        const observer = new MutationObserver(function(mutations) {
            // اجمع جميع personKey الحاليين في الصفحة
            const currentKeys = Array.from(familyContainer.querySelectorAll('[data-upload-zone]'))
                .map(zone => zone.getAttribute('data-upload-zone'));
            // احذف أي مرفقات في allDocs لم يعد لها بوابة في الصفحة
            if (window.allDocs) {
                Array.from(window.allDocs.keys()).forEach(personKey => {
                    if (personKey.startsWith('family_') && !currentKeys.includes(personKey)) {
                        removeAllAttachmentTasksForPersonKey(personKey);
                    }
                });
            }
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.querySelector && node.querySelector('[data-upload-zone]')) {
                        node.querySelectorAll('[data-upload-zone]').forEach(initUploadZone);
                    }
                });
            });
        });
        observer.observe(familyContainer, { childList: true, subtree: true });
    }

    // ملاحظة مهمة: إذا كان لديك إرسال AJAX (fetch) في ملف آخر مثل manageForm.blade.php،
    // يجب أن يتم التحقق من وجود المرفقات فقط في ذلك الملف وليس هنا.
    // إذا كان الإرسال يتم عبر AJAX، أزل أو علق التحقق من المرفقات هنا نهائياً.

    const form = document.getElementById('main_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // علق أو احذف التحقق التالي إذا كان لديك إرسال AJAX:
            /*
            const validPersonKeys = new Set();
            document.querySelectorAll('[data-upload-zone]').forEach(zone => {
                validPersonKeys.add(zone.getAttribute('data-upload-zone'));
            });
            let completedCount = 0;
            allDocs.forEach((tasksArr, personKey) => {
                if (!validPersonKeys.has(personKey)) return;
                tasksArr.forEach(task => {
                    if (task.status === 'completed' && task.processedFile) completedCount++;
                });
            });
            if (completedCount === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى رفع مرفق واحد على الأقل قبل الحفظ.'
                });
                return false;
            }
            */
            Array.from(form.querySelectorAll('input[type="file"]')).forEach(input => input.remove());
            let index = 0;
            allDocs.forEach((tasksArr, personKey) => {
                tasksArr.forEach(task => {
                    if (task.status !== 'completed' || !task.processedFile) return;
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = `attachments[${index}][file]`;
                    fileInput.style.display = 'none';
                    const dt = new DataTransfer();
                    dt.items.add(task.processedFile);
                    fileInput.files = dt.files;
                    form.appendChild(fileInput);

                    const personIdInput = document.createElement('input');
                    personIdInput.type = 'hidden';
                    personIdInput.name = `attachments[${index}][person_identity_number]`;
                    personIdInput.value = task.personId;
                    form.appendChild(personIdInput);

                    const fileNameInput = document.createElement('input');
                    fileNameInput.type = 'hidden';
                    fileNameInput.name = `attachments[${index}][stored_file_name]`;
                    fileNameInput.value = task.processedFile.name;
                    form.appendChild(fileNameInput);

                    const fileTypeInput = document.createElement('input');
                    fileTypeInput.type = 'hidden';
                    fileTypeInput.name = `attachments[${index}][file_type]`;
                    fileTypeInput.value = task.docType;
                    form.appendChild(fileTypeInput);

                    const fileIdInput = document.createElement('input');
                    fileIdInput.type = 'hidden';
                    fileIdInput.name = `attachments[${index}][file_id_number]`;
                    fileIdInput.value = task.fileIdNumber;
                    form.appendChild(fileIdInput);

                    index++;
                });
            });

            // ملاحظة: إذا كان لديك إرسال AJAX في ملفات أخرى (مثل manageForm.blade.php)،
            // لا داعي لهذا التحقق هنا، لأن التحقق الفعلي يتم في manageForm.blade.php.
            // إذا كنت تعتمد على الإرسال AJAX فقط، يمكنك تعطيل التحقق هنا نهائياً:
            return true;
        });
    }

    document.querySelectorAll('.family-member-form input[name$="[person_id]"]').forEach(function(input) {
        input.addEventListener('input', function(e) {
            const form = input.closest('.family-member-form');
            const match = input.name.match(/family_members\[(\d+)\]/);
            if (!match) return;
            const index = match[1];
            const personKey = `family_${index}`;
            if (window.allDocs && window.allDocs.has(personKey)) {
                window.allDocs.get(personKey).forEach(task => {
                    task.personId = input.value;
                });
                renderAttachmentTasksUI(personKey);
                console.log('🟢 تم تحديث رقم هوية فرد الأسرة في جميع مهامه:', personKey, input.value, window.allDocs.get(personKey));
            }
        });
    });
});
</script>
@endpush
