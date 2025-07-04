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
        // منع إضافة أي مهمة إذا كان personKey أو personId فارغ أو يحمل template
        if (!personKey || personKey === 'template' || !personId || personId === 'template') {
            console.warn('[addAttachmentTask] محاولة إضافة مرفق بقيم غير صالحة:', {personKey, personId, file, docType, fileIdNumber});
            return null;
        }
        if (!file || !docType) {
            console.warn('[addAttachmentTask] محاولة إضافة مرفق بدون ملف أو نوع وثيقة:', {personKey, personId, file, docType, fileIdNumber});
            return null;
        }
        const id = generateTaskId();
        const isImage = file.type && file.type.startsWith('image/');
        const task = {
            id,
            personKey,
            originalFile: file,
            processedFile: isImage ? null : file,
            docType: docType,
            personId,
            fileIdNumber,
            status: isImage ? 'pending' : 'completed',
            errorMessage: null,
            timestamp: Date.now(),
            timer: null
        };
        // لا تضف نفس المهمة مرتين لنفس الشخص أو لنفس رقم الهوية
        if (!allDocs.has(personKey)) allDocs.set(personKey, []);
        // لا تضف نفس المهمة إذا كان هناك مهمة بنفس الملف ونوع الوثيقة قيد التنفيذ أو بانتظار القص أو حتى فشلت لنفس الشخص
        const existingTask = allDocs.get(personKey).find(t => t.originalFile.name === file.name && t.docType === docType && t.personId === personId);
        if (existingTask) {
            // إذا كانت المهمة السابقة فشلت أو انتهت، احذفها وأضف الجديدة
            if (existingTask.status === 'failed' || existingTask.status === 'completed') {
                removeAttachmentTask(personKey, existingTask.id);
                allDocs.get(personKey).push(task);
            } else {
                // هناك مهمة قيد التنفيذ أو بانتظار القص لنفس الملف ولنفس الشخص ولنفس نوع الوثيقة، تجاهل الإضافة
                console.warn('[addAttachmentTask] مهمة مكررة قيد التنفيذ أو بانتظار القص، لن تتم الإضافة:', {personKey, personId, file, docType, fileIdNumber});
                return null;
            }
        } else {
            allDocs.get(personKey).push(task);
        }
        if (personId && personId !== personKey) {
            if (!allDocs.has(personId)) allDocs.set(personId, []);
            const alreadyExistsById = allDocs.get(personId).some(t => t.originalFile === file && t.docType === docType);
            if (!alreadyExistsById) {
                allDocs.get(personId).push(task);
            }
        }
        // عرض حي لكل عملية إضافة
        console.log('🟡 إضافة مرفق:', task);
        processAttachment(id);
        renderAttachmentTasksUI(personKey);
        // عرض حي لجميع المرفقات بعد كل إضافة
        console.log('🟢 جميع المرفقات الحالية:', Array.from(window.allDocs.entries()));
        // Diagnostic marker for workflow verification
        console.log('🟢');
        return id;
    }

    function updateAttachmentTaskStatus(id, status, processedFile = null, errorMessage = null) {
        // عند اكتمال مهمة بنجاح (قص صورة)، احذف أي مهمة سابقة (فشلت أو قيد التنفيذ) لنفس الملف ولنفس الشخص ولنفس نوع الوثيقة
        if (status === 'completed') {
            for (let [personKey, arr] of allDocs.entries()) {
                const task = arr.find(t => t.id === id);
                if (task) {
                    // ابحث عن أي مهمة أخرى (غير الحالية) بنفس الملف/الشخص/النوع (سواء كانت failed أو pending أو processing)
                    const duplicateTasks = arr.filter(t => t.id !== id && t.originalFile.name === task.originalFile.name && t.docType === task.docType && t.personId === task.personId);
                    duplicateTasks.forEach(dupTask => {
                        removeAttachmentTask(personKey, dupTask.id);
                    });
                }
            }
        }
        for (let [personKey, arr] of allDocs.entries()) {
            const task = arr.find(t => t.id === id);
            if (task) {
                task.status = status;
                // لا تعيّن processedFile إلا إذا كان فعلاً من نوع File
                if (processedFile !== undefined) {
                    if (status === 'completed') {
                        if (processedFile instanceof File) {
                            task.processedFile = processedFile;
                        } else if (!processedFile && task.originalFile instanceof File) {
                            // fallback: استخدم الملف الأصلي إذا لم يتم تمرير processedFile
                            task.processedFile = task.originalFile;
                        } else {
                            // إذا لم يكن هناك ملف صالح، لا تغيّر processedFile أبداً
                            console.error('[updateAttachmentTaskStatus] محاولة تعيين processedFile غير صالحة عند الاكتمال. سيتم تجاهلها ولن يتم تغيير الملف الحالي.', {id, status, processedFile, task});
                        }
                    } else {
                        // في الحالات الأخرى (processing/pending/failed) لا تفرض أي حماية على processedFile
                        if (processedFile instanceof File) {
                            task.processedFile = processedFile;
                        } // لا تعيّن null أو true أو أي قيمة أخرى أبداً
                    }
                }
                if (errorMessage !== undefined) task.errorMessage = errorMessage;
                // إذا كانت صورة، حدث صورة المعاينة العامة
                if (task.status === 'completed' && task.originalFile && task.originalFile.type && task.originalFile.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        window.lastPreviewImage = e.target.result;
                        // تحديث جميع المعاينات في الصفحة
                        document.querySelectorAll('#preview img').forEach(img => {
                            img.src = window.lastPreviewImage;
                            img.parentElement.classList.remove('d-none');
                        });
                    };
                    reader.readAsDataURL(task.originalFile);
                }
                // --- إعادة تهيئة القائمة المنسدلة فقط (دون حذف أي خيار) ---
                if (task.status === 'completed') {
                    // تم إلغاء أي تهيئة تلقائية للقائمة المنسدلة بعد رفع الملفات
                    // لا تغيّر selectedIndex ولا تفرض أي تغيير على select
                    console.log('[updateAttachmentTaskStatus] تم رفع الملف بنجاح بدون تهيئة تلقائية للقائمة المنسدلة:', task.docType);
                    // إعادة تهيئة القائمة المنسدلة الخاصة برفع الملفات بعد رفع الملف بنجاح
                    const zone = document.querySelector(`[data-upload-zone="${personKey}"]`);
                    if (zone) {
                        const docTypeSelect = zone.querySelector('select');
                        if (docTypeSelect) {
                            let foundDefault = false;
                            for (let i = 0; i < docTypeSelect.options.length; i++) {
                                let val = docTypeSelect.options[i].value;
                                let txt = docTypeSelect.options[i].text.trim();
                                if (val === '' || val === '0' || txt === 'اختر نوع الوثيقة') {
                                    docTypeSelect.selectedIndex = i;
                                    foundDefault = true;
                                    break;
                                }
                            }
                            if (!foundDefault) {
                                let option = document.createElement('option');
                                option.value = '';
                                option.text = 'اختر نوع الوثيقة';
                                docTypeSelect.insertBefore(option, docTypeSelect.options[0]);
                                docTypeSelect.selectedIndex = 0;
                            }
                            // إطلاق حدث التغيير
                            if (typeof $ !== 'undefined') {
                                $(docTypeSelect).trigger('change');
                            } else if (typeof Event === 'function') {
                                docTypeSelect.dispatchEvent(new Event('change'));
                            }
                        }
                    }
                }
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
        if (!task) {
            console.log('[processAttachment] لم يتم العثور على المهمة', id);
            return;
        }
        if (task.status !== 'pending') {
            console.log('[processAttachment] حالة المهمة ليست pending، لن تتم المعالجة', id, task.status);
            return;
        }
        updateAttachmentTaskStatus(id, 'processing');
        if (task.originalFile.type && task.originalFile.type.startsWith('image/')) {
            // اربط timeout خاص بهذه المهمة
            task.timer = setTimeout(() => {
                console.log('[processAttachment] انتهى الوقت لهذه الصورة، المهمة ستفشل', id);
                updateAttachmentTaskStatus(id, 'failed', null, 'انتهى الوقت لهذه الصورة.');
            }, 300000); // 5 دقائق
            try {
                console.log('[processAttachment] سيتم استدعاء showCropperModalPromise للصورة', id, task.originalFile);
                const croppedFile = await showCropperModalPromise(task.originalFile, 300000);
                clearTimeout(task.timer); // ألغِ المؤقت عند النجاح
                if (croppedFile && croppedFile instanceof File) {
                    console.log('[processAttachment] تم قص الصورة بنجاح', id, croppedFile);
                    updateAttachmentTaskStatus(id, 'completed', croppedFile, null);
                } else {
                    console.log('[processAttachment] لم يتم قص الصورة أو حدث خطأ', id, croppedFile);
                    updateAttachmentTaskStatus(id, 'failed', null, 'لم يتم قص الصورة أو حدث خطأ.');
                }
            } catch (err) {
                clearTimeout(task.timer); // ألغِ المؤقت عند الفشل
                console.log('[processAttachment] حدث خطأ أثناء معالجة الصورة', id, err);
                updateAttachmentTaskStatus(id, 'failed', null, err && err.message ? err.message : 'خطأ غير معروف أثناء معالجة الصورة.');
            }
        } else {
            if (task.originalFile instanceof File) {
                console.log('[processAttachment] الملف ليس صورة، سيتم تعيينه مباشرة كمرفق نهائي', id, task.originalFile);
                updateAttachmentTaskStatus(id, 'completed', task.originalFile, null);
            } else {
                console.log('[processAttachment] الملف الأصلي غير صالح، المهمة ستفشل', id, task.originalFile);
                updateAttachmentTaskStatus(id, 'failed', null, 'الملف الأصلي غير صالح.');
            }
        }
    }

    function showCropperModalPromise(file, timeoutMs = 300000) {
        return new Promise((resolve, reject) => {
            let finished = false;
            let cropperStarted = false;
            let cropperResolved = false;
            // Diagnostic logging
            console.log('[showCropperModalPromise] Invoked for file:', file);
            // Fail fast if cropper modal is not opened within 2 seconds
            let fastFailTimer = setTimeout(() => {
                if (!cropperStarted) {
                    finished = true;
                    if (window.ErrorTracker && window.ErrorTracker.log) window.ErrorTracker.log('error', 'cropper-fast-fail', 'Cropper modal not invoked within 2s', { file });
                    console.error('[showCropperModalPromise] ❌ لم يتم استدعاء نافذة القص (cropper) خلال ثانيتين!');
                    reject(new Error('تعذر فتح أداة قص الصور. يرجى تحديث الصفحة أو التواصل مع الدعم.'));
                }
            }, 2000);
            // Main timeout for cropper operation
            let timer = setTimeout(() => {
                if (!finished) {
                    finished = true;
                    if (window.ErrorTracker && window.ErrorTracker.log) window.ErrorTracker.log('error', 'cropper-timeout', 'Cropper callback not called within timeout', { file });
                    console.error('[showCropperModalPromise] ⏰ Timeout: لم يتم فتح نافذة القص (cropper) خلال الوقت المحدد!');
                    reject(new Error('انتهت مهلة معالجة الصورة. يرجى المحاولة مجدداً.'));
                }
            }, timeoutMs);

            // --- Robust cropper modal invocation for all upload zones ---
            // Try to always use the global cropper modal function, fallback to window
            let showCropper = window.showCropperModal || window.showCropper || null;
            if (!showCropper) {
                // Try to find cropper modal function in other scripts
                for (let k in window) {
                    if (typeof window[k] === 'function' && /cropper/i.test(k)) {
                        showCropper = window[k];
                        break;
                    }
                }
            }
            if (!showCropper) {
                clearTimeout(timer);
                clearTimeout(fastFailTimer);
                if (window.ErrorTracker && window.ErrorTracker.log) window.ErrorTracker.log('error', 'cropper-missing', 'No cropper modal function found', { file });
                console.error('[showCropperModalPromise] ❌ الدالة window.showCropperModal غير معرفة! تأكد من تضمين كود المودال لجميع مناطق الرفع، خاصة أفراد الأسرة.');
                reject(new Error('لم يتم تحميل أداة قص الصور بشكل صحيح. يرجى تحديث الصفحة أو التواصل مع الدعم.'));
                return;
            }
            try {
                console.log('[showCropperModalPromise] سيتم فتح نافذة القص (cropper) لهذا الملف:', file);
                showCropper(file, function(croppedFile, error) {
                    cropperStarted = true;
                    clearTimeout(fastFailTimer);
                    if (finished) return;
                    finished = true;
                    clearTimeout(timer);
                    if (error) {
                        if (window.ErrorTracker && window.ErrorTracker.log) window.ErrorTracker.log('error', 'cropper-error', error, { file });
                        console.warn('[showCropperModalPromise] cropper callback error:', error);
                        reject(error instanceof Error ? error : new Error(error));
                        return;
                    }
                    if (!croppedFile) {
                        if (window.ErrorTracker && window.ErrorTracker.log) window.ErrorTracker.log('error', 'cropper-blob-missing', 'No blob returned from cropper', { file });
                        console.warn('[showCropperModalPromise] لم يتم قص الصورة أو تم الإلغاء');
                        reject(new Error('لم يتم قص الصورة أو تم الإلغاء.'));
                        return;
                    }
                    cropperResolved = true;
                    // Convert blob to File if needed
                    let resultFile = croppedFile;
                    if (!(croppedFile instanceof File) && croppedFile instanceof Blob) {
                        try {
                            resultFile = new File([croppedFile], file.name, { type: croppedFile.type || file.type });
                        } catch (e) {
                            // fallback: use blob
                            resultFile = croppedFile;
                        }
                    }
                    console.log('[showCropperModalPromise] تم قص الصورة وإرجاع ملف جديد', resultFile);
                    resolve(resultFile);
                });
                cropperStarted = true;
                clearTimeout(fastFailTimer);
            } catch (err) {
                clearTimeout(timer);
                clearTimeout(fastFailTimer);
                finished = true;
                if (window.ErrorTracker && window.ErrorTracker.log) window.ErrorTracker.log('error', 'cropper-exception', err, { file });
                console.error('[showCropperModalPromise] استثناء أثناء محاولة فتح نافذة القص:', err);
                reject(new Error('حدث خطأ أثناء فتح أداة قص الصور.'));
            }
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
        // استخدم دومًا الفئة مع data-upload-zone
        const zone = document.querySelector(`[data-upload-zone="${personKey}"]`);
        if (zone) {
            previewDiv = zone.querySelector('.mainDocumentPreview');
        }
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
            card.id = 'preview_att_' + task.id; // معرف فريد لكل بطاقة
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

            // دائماً اعرض صورة المعاينة إذا كان لدينا ملف صورة أصلي أو نهائي
            let previewFile = null;
            if (task.processedFile && task.processedFile.type && task.processedFile.type.startsWith('image/')) {
                previewFile = task.processedFile;
            } else if (task.originalFile && task.originalFile.type && task.originalFile.type.startsWith('image/')) {
                previewFile = task.originalFile;
            }
            if (previewFile) {
                const img = document.createElement('img');
                const objectUrl = URL.createObjectURL(previewFile);
                img.src = objectUrl;
                img.style.maxWidth = '100px';
                img.style.maxHeight = '100px';
                img.className = 'rounded border mb-1';
                // لا تستدعِ revokeObjectURL هنا
                cardBody.appendChild(img);
                card.dataset.objectUrl = objectUrl;
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
                        // ألغِ objectUrl عند حذف الكارد فقط
                        if (card.dataset.objectUrl) {
                            try { URL.revokeObjectURL(card.dataset.objectUrl); } catch {}
                        }
                        removeAttachmentTask(personKey, task.id);
                    }
                });
            };
            cardBody.appendChild(delBtn);
            card.appendChild(cardBody);
            previewDiv.appendChild(card);
        });

        // إخفاء جميع عناصر input[type="file"] الخاصة بالرفع
        setTimeout(() => {
            document.querySelectorAll('[data-upload-zone] input[type="file"]').forEach(input => {
                input.style.display = 'none';
                input.style.visibility = 'hidden';
                input.style.width = '0';
                input.style.height = '0';
                input.style.pointerEvents = 'none';
                input.style.opacity = '0';
                input.style.position = 'absolute';
                input.style.left = '-9999px';
            });
        }, 0);
    }

    function initUploadZone(zone) {
        const personKey = zone.getAttribute('data-upload-zone');
        if (!personKey || personKey === 'template') {
            console.warn('[initUploadZone] تجاهل تهيئة منطقة رفع بقيمة template أو فارغة:', {zone, personKey});
            return;
        }
        const fileInput = zone.querySelector('input[type="file"]');
        const docTypeSelect = zone.querySelector('select');
        if (!fileInput || !docTypeSelect) return;
        // Log for debugging upload zone initialization
        console.log(`[initUploadZone] تهيئة منطقة رفع الملفات: personKey=${personKey}`, {zone, fileInput, docTypeSelect});
        // دعم رفع ملفات متعددة
        fileInput.setAttribute('multiple', 'multiple');
        fileInput.addEventListener('change', function(e) {
            console.log('🟠 محاولة رفع ملف، قيمة نوع الوثيقة (docTypeSelect.value):', docTypeSelect.value);
            if (!docTypeSelect.value || docTypeSelect.value === 'undefined') {
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
            // دعم رفع ملفات متعددة بشكل تراكمي
            let docsArr = window.allDocs.get(personKey) || [];
            const docTypeValue = docTypeSelect.value;
            Array.from(fileInput.files).forEach(file => {
                // تأكد من تمرير نوع الوثيقة الصحيح
                console.log('🟢 رفع ملف جديد:', file.name, 'نوع الوثيقة (pref):', docTypeValue);
                addAttachmentTask(personKey, file, docTypeValue, personId, document.querySelector('input[name="file_id_number"]')?.value || '');
            });
            // إعادة تعيين قيمة input بعد معالجة جميع الملفات
            setTimeout(() => { fileInput.value = ''; }, 10);
        });
        // لا تعيد تعيين select إلا بعد رفع الملفات فعليًا (يمكنك التعليق على السطر التالي إذا أردت إبقاء الاختيار)
        // docTypeSelect.value = '';
        docTypeSelect.addEventListener('change', function() {
            if (!allDocs.has(personKey)) return;
            // تحديث نوع الوثيقة فقط للمهام التي لم تكتمل بعد (pending/processing)
            allDocs.get(personKey).forEach(task => {
                if (task.status === 'pending' || task.status === 'processing') {
                    task.docType = docTypeSelect.value;
                }
            });
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
            // إلغاء التحقق من وجود مرفق واحد على الأقل نهائياً
            // return true;
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
                    if (task.processedFile instanceof File) {
                        dt.items.add(task.processedFile);
                    } else {
                        console.warn('تم تجاهل عنصر غير صالح في DataTransfer (documentUpload):', task.processedFile);
                    }
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
            // لا تظهر أي رسالة تحقق هنا
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
