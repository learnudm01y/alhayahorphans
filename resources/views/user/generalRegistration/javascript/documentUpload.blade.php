@push('scriptsCodeUserRegistration')
    <!-- تحسينات CSS للمعاينة السلسة -->
    <style>
        /* تأثيرات سلسة للصور أثناء المعالجة */
        .attachment-preview-container {
            position: relative;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .attachment-preview-container img {
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .attachment-preview-container.processing img {
            opacity: 0.7;
            filter: blur(1px);
        }

        .attachment-preview-container.completed img {
            opacity: 1;
            filter: none;
        }

        /* overlay للمعالجة */
        .processing-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s ease;
        }

        .processing-overlay.fade-out {
            opacity: 0;
        }

        /* تحسينات للموبايل */
        @media (max-width: 768px) {
            .attachment-preview-container img {
                max-width: 80px;
                max-height: 80px;
            }

            .processing-overlay .spinner-border {
                width: 1.2rem;
                height: 1.2rem;
            }

            .processing-overlay .small {
                font-size: 0.6rem;
            }
        }

        /* تأثير النجاح */
        .attachment-card.success-flash {
            animation: successFlash 0.6s ease;
        }

        @keyframes successFlash {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); background-color: #d4edda; }
            100% { transform: scale(1); background-color: white; }
        }

        /* تحسين شارات الحالة */
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 500;
        }

        .status-badge.pending {
            background-color: #cce7ff;
            color: #0066cc;
        }

        .status-badge.processing {
            background-color: #fff3cd;
            color: #856404;
            animation: pulse 1.5s infinite;
        }

        .status-badge.completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.failed {
            background-color: #f8d7da;
            color: #721c24;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // فحص تشخيصي لحالة أداة القص
    console.log('[documentUpload] فحص حالة أداة القص...');
    console.log('[documentUpload] window.showCropperModal:', typeof window.showCropperModal);
    console.log('[documentUpload] window.showCropper:', typeof window.showCropper);
    console.log('[documentUpload] window.cropperReady:', window.cropperReady);

    // متغير عام لتتبع حالة المعالجة
    window.isProcessingAttachment = false;
    let processingAlert = null;

    // دالة لعرض رسالة الانتظار
    function showProcessingAlert() {
        if (processingAlert) return; // تجنب العرض المزدوج

        processingAlert = Swal.fire({
            title: 'جاري المعالجة...',
            html: 'الرجاء الانتظار حتى تكتمل معالجة الملف',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            },
            // لا نغلق تلقائياً - سيتم إغلاقها من خلال hideProcessingAlert
        });

        window.isProcessingAttachment = true;
        console.log('[Processing] ⏳ بدء عملية المعالجة، تم عرض تنبيه الانتظار');
    }

    // دالة لإخفاء رسالة الانتظار
    function hideProcessingAlert() {
        if (processingAlert) {
            Swal.close();
            processingAlert = null;
        }
        window.isProcessingAttachment = false;
        console.log('[Processing] ✅ انتهت عملية المعالجة، تم إغلاق تنبيه الانتظار');
    }

    // الاستماع لإشارة جاهزية أداة القص
    window.addEventListener('cropperReady', function(event) {
        console.log('[documentUpload] ✅ تم استلام إشارة جاهزية أداة القص:', event.detail);

        // إعادة تهيئة مناطق الرفع للتأكد من ربطها بأداة القص
        setTimeout(() => {
            console.log('[documentUpload] إعادة تهيئة مناطق الرفع بعد جاهزية أداة القص...');
            initializeAllUploadZones();
        }, 100);
    });

    // انتظار تحميل أداة القص إذا لم تكن متوفرة
    if (!window.showCropperModal && !window.showCropper && !window.cropperReady) {
        console.warn('[documentUpload] أداة القص غير متوفرة عند تحميل الصفحة، انتظار التحميل...');
        let checkCount = 0;
        const checkCropper = () => {
            if (window.showCropperModal || window.showCropper || window.cropperReady) {
                console.log('[documentUpload] ✅ تم تحميل أداة القص بنجاح');
                // إعادة تهيئة مناطق الرفع
                setTimeout(() => {
                    initializeAllUploadZones();
                }, 100);
            } else if (checkCount < 50) { // انتظار 10 ثواني
                checkCount++;
                setTimeout(checkCropper, 200);
            } else {
                console.error('[documentUpload] ❌ فشل في تحميل أداة القص بعد 10 ثواني');
            }
        };
        checkCropper();
    } else {
        console.log('[documentUpload] ✅ أداة القص متوفرة ومجهزة');
    }

    // --- Enhanced Attachment State Engine ---
    const allDocs = new Map();
    window.allDocs = allDocs;

    function generateTaskId() {
        return 'att_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
    }

    function addAttachmentTask(personKey, file, docType, personId, fileIdNumber) {
        // التحقق الصارم من صحة البيانات المدخلة
        if (!personKey || personKey === 'template' || personKey.includes('template')) {
            console.warn('[addAttachmentTask] محاولة إضافة مرفق بـ personKey غير صالح:', {personKey, personId, file, docType, fileIdNumber});
            return null;
        }

        // اسمح بـ personId فارغ لبوابات معينة (البيانات الأساسية والمتوفين)
        const allowEmptyPersonId = ['main', 'deceased_father', 'deceased_mother'].includes(personKey);

        // إذا كان personId فارغًا وهذه بوابة تسمح بذلك، استخدم قيمة افتراضية
        if ((!personId || personId.trim() === '') && allowEmptyPersonId) {
            personId = 'default_' + personKey;
            console.log('[addAttachmentTask] استخدام قيمة افتراضية لـ personId:', personId, 'للبوابة:', personKey);
        }
        // لا تسمح بـ personId فارغ للبوابات الأخرى
        else if (!personId || personId === 'template' || personId.trim() === '') {
            console.warn('[addAttachmentTask] محاولة إضافة مرفق بـ personId غير صالح:', {personKey, personId, file, docType, fileIdNumber});
            return null;
        }

        if (!file || !docType || docType === '' || docType === 'undefined') {
            console.warn('[addAttachmentTask] محاولة إضافة مرفق بدون ملف أو نوع وثيقة صالح:', {personKey, personId, file, docType, fileIdNumber});
            return null;
        }

        // التأكد من أن منطقة الرفع موجودة فعلياً في الصفحة
        const uploadZone = document.querySelector(`[data-upload-zone="${personKey}"]`);
        if (!uploadZone) {
            console.warn('[addAttachmentTask] منطقة الرفع غير موجودة في الصفحة:', {personKey, personId, file, docType});
            return null;
        }

        const id = generateTaskId();
        const isImage = file.type && file.type.startsWith('image/');
        const task = {
            id,
            personKey,
            originalFile: file,
            // ⭐ CRITICAL FIX: تعيين الملف فوراً للعرض الفوري
            processedFile: file,
            docType: docType,
            personId: personId.toString().trim(),
            fileIdNumber: fileIdNumber || '',
            status: 'pending', // دائماً pending في البداية للعرض الفوري
            errorMessage: null,
            timestamp: Date.now(),
            timer: null,
            createdAt: Date.now(),
            updatedAt: Date.now(),
            // علامة للتمييز بين الملف الأصلي والمعالج
            isProcessed: false // دائماً false في البداية
        };

        // إدارة محسنة للتكرار - امنع المهام المكررة بنفس الملف ونوع الوثيقة للشخص نفسه
        if (!allDocs.has(personKey)) allDocs.set(personKey, []);

        const tasks = allDocs.get(personKey);
        const duplicateTask = tasks.find(t =>
            t.originalFile.name === file.name &&
            t.docType === docType &&
            t.personId === task.personId
        );

        if (duplicateTask) {
            if (duplicateTask.status === 'failed') {
                // استبدال المهمة الفاشلة بمهمة جديدة
                console.log('[addAttachmentTask] استبدال مهمة فاشلة بمهمة جديدة:', duplicateTask.id, '→', id);
                removeAttachmentTask(personKey, duplicateTask.id);
            } else if (duplicateTask.status === 'completed') {
                // السماح بإعادة رفع ملف مكتمل (استبدال)
                console.log('[addAttachmentTask] استبدال مهمة مكتملة بمهمة جديدة:', duplicateTask.id, '→', id);
                removeAttachmentTask(personKey, duplicateTask.id);
            } else {
                // رفض المهمة المكررة إذا كانت قيد التنفيذ
                console.warn('[addAttachmentTask] مهمة مكررة قيد التنفيذ، لن تتم الإضافة:', {
                    existing: duplicateTask.id,
                    newTask: id,
                    status: duplicateTask.status
                });
                return duplicateTask.id; // إرجاع معرف المهمة الموجودة
            }
        }

        // إضافة المهمة الجديدة
        tasks.push(task);

        console.log('🟡 إضافة مرفق جديد:', {
            id,
            personKey,
            personId: task.personId,
            fileName: file.name,
            docType,
            isImage,
            processedFile: task.processedFile ? 'موجود' : 'null',
            status: task.status
        });

        // ⭐ CRITICAL: عرض فوري للصورة قبل أي معالجة
        console.log('📸 عرض فوري للصورة قبل المعالجة:', {
            fileName: file.name,
            personKey: personKey,
            isImage: isImage,
            hasProcessedFile: !!task.processedFile,
            taskStatus: task.status
        });

        // تحديث الواجهة فوراً - هذا سيعرض الصورة قبل modal القص
        renderAttachmentTasksUI(personKey);

        // انتظار قصير للتأكد من عرض الصورة قبل بدء المعالجة
        setTimeout(() => {
            console.log('🔄 بدء معالجة المرفق بعد العرض الفوري:', id);

            // للملفات غير الصور، أكمل فوراً
            if (!isImage) {
                updateAttachmentTaskStatus(id, 'completed', task.originalFile, null);
            } else {
                // للصور، ابدأ المعالجة
                processAttachment(id);
            }
        }, 100); // انتظار 100ms لضمان عرض الصورة

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
                const previousStatus = task.status; // حفظ الحالة السابقة
                task.status = status;
                // لا تعيّن processedFile إلا إذا كان فعلاً من نوع File
                if (processedFile !== undefined) {
                    if (status === 'completed') {
                        if (processedFile instanceof File || processedFile instanceof Blob) {
                            task.processedFile = processedFile;
                            task.isProcessed = true; // علامة أن الملف تم معالجته
                        } else if (!processedFile && task.originalFile instanceof File) {
                            // fallback: استخدم الملف الأصلي إذا لم يتم تمرير processedFile
                            task.processedFile = task.originalFile;
                            task.isProcessed = false; // لم يتم معالجة الملف فعلياً
                        } else {
                            // إذا لم يكن هناك ملف صالح، لا تغيّر processedFile أبداً
                            console.error('[updateAttachmentTaskStatus] محاولة تعيين processedFile غير صالحة عند الاكتمال. سيتم تجاهلها ولن يتم تغيير الملف الحالي.', {id, status, processedFile, task});
                        }
                    } else {
                        // في الحالات الأخرى (processing/pending/failed) لا تفرض أي حماية على processedFile
                        if (processedFile instanceof File || processedFile instanceof Blob) {
                            task.processedFile = processedFile;
                        } // لا تعيّن null أو true أو أي قيمة أخرى أبداً
                    }
                }
                if (errorMessage !== undefined) task.errorMessage = errorMessage;

                task.updatedAt = Date.now();
                console.log(`[updateAttachmentTaskStatus] ✅ تحديث حالة المهمة ${id}:`, {
                    من: previousStatus,
                    إلى: status,
                    ملف_معالج: processedFile ? 'موجود' : 'لا يوجد',
                    معالج: task.isProcessed,
                    رسالة_خطأ: errorMessage
                });

                // تحديث فوري للواجهة مع تأثيرات انتقالية سلسة
                requestAnimationFrame(() => {
                    renderAttachmentTasksUI(personKey);

                    // ⭐ تأثيرات خاصة عند اكتمال المعالجة
                    if (status === 'completed') {
                        setTimeout(() => {
                            const taskCard = document.getElementById('preview_att_' + id);
                            if (taskCard) {
                                console.log(`[updateAttachmentTaskStatus] 🎉 إضافة تأثيرات النجاح للمهمة ${id}`);

                                // إزالة تأثيرات المعالجة
                                const imgContainer = taskCard.querySelector('.attachment-preview-container');
                                if (imgContainer) {
                                    imgContainer.classList.remove('pending', 'processing');
                                    imgContainer.classList.add('completed');

                                    // تحسين الصورة (إزالة التشويش)
                                    const img = imgContainer.querySelector('img');
                                    if (img) {
                                        img.style.opacity = '1';
                                        img.style.filter = 'none';
                                    }
                                }

                                // إزالة overlay مع تأثير fade
                                const overlay = taskCard.querySelector('.processing-overlay');
                                if (overlay) {
                                    overlay.classList.add('fade-out');
                                    setTimeout(() => {
                                        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                                    }, 300);
                                }

                                // إضافة تأثير نجاح واضح للبطاقة
                                taskCard.classList.add('success-flash');
                                setTimeout(() => {
                                    taskCard.classList.remove('success-flash');
                                }, 600);

                                // تحديث شارة الحالة لتأكيد النجاح
                                const statusBadge = taskCard.querySelector('.status-badge');
                                if (statusBadge) {
                                    statusBadge.innerHTML = '✅ تم بنجاح';
                                    statusBadge.className = 'status-badge completed';
                                }
                            }
                        }, 100);
                    }
                });

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
        let personKey;
        for (let [pk, arr] of allDocs.entries()) {
            task = arr.find(t => t.id === id);
            if (task) {
                personKey = pk;
                break;
            }
        }

        if (!task) {
            console.error('[processAttachment] لم يتم العثور على المهمة:', id);
            return;
        }

        if (task.status !== 'pending') {
            console.log('[processAttachment] حالة المهمة ليست pending، لن تتم المعالجة:', id, task.status);
            return;
        }

        console.log(`[processAttachment] 🚀 بدء معالجة المهمة ${id}:`, {
            fileName: task.originalFile.name,
            fileType: task.originalFile.type,
            isImage: task.originalFile.type.startsWith('image/')
        });

        // التحقق من وجود منطقة الرفع قبل المعالجة
        const uploadZone = document.querySelector(`[data-upload-zone="${task.personKey}"]`);
        if (!uploadZone) {
            console.error('[processAttachment] منطقة الرفع غير موجودة، المهمة ستفشل:', task.personKey);
            updateAttachmentTaskStatus(id, 'failed', null, 'منطقة الرفع غير موجودة في الصفحة');
            return;
        }

        // تحديث الحالة إلى "processing"
        updateAttachmentTaskStatus(id, 'processing');

        if (task.originalFile.type && task.originalFile.type.startsWith('image/')) {
            console.log('[processAttachment] 📸 بدء معالجة الصورة:', id, task.originalFile.name);

            // التحقق السريع من توفر أداة القص
            const showCropper = window.showCropperModal || window.showCropper;
            if (!showCropper) {
                console.error('[processAttachment] أداة القص غير متوفرة');
                updateAttachmentTaskStatus(id, 'failed', null, 'أداة قص الصور غير متوفرة. يرجى تحديث الصفحة.');
                return;
            }

            // إعداد مؤقت محسن مع تتبع أفضل
            const timeoutMs = 120000; // دقيقتان
            task.timer = setTimeout(() => {
                console.error('[processAttachment] انتهى الوقت المحدد لمعالجة الصورة:', id, task.originalFile.name);
                updateAttachmentTaskStatus(id, 'failed', null, 'انتهى الوقت المحدد لمعالجة الصورة (دقيقتان). يرجى المحاولة مرة أخرى.');
                hideProcessingAlert(); // إغلاق التنبيه عند انتهاء المهلة

                if (window.ErrorTracker && window.ErrorTracker.log) {
                    window.ErrorTracker.log('error', 'image-processing-timeout', 'Image processing timed out after 2 minutes', {
                        taskId: id,
                        personKey: task.personKey,
                        fileName: task.originalFile.name
                    });
                }
            }, timeoutMs);

            showCropperModalPromise(task.originalFile, timeoutMs)
                .then(croppedFile => {
                    // تنظيف المؤقت عند النجاح
                    if (task.timer) {
                        clearTimeout(task.timer);
                        task.timer = null;
                    }

                    // إخفاء تنبيه الانتظار
                    hideProcessingAlert();

                    if (croppedFile && (croppedFile instanceof File || croppedFile instanceof Blob)) {
                        console.log('[processAttachment] تم قص الصورة بنجاح:', id, croppedFile.name || 'cropped-image');
                        updateAttachmentTaskStatus(id, 'completed', croppedFile, null);
                    } else {
                        console.warn('[processAttachment] فشل في قص الصورة أو تم الإلغاء:', id);
                        updateAttachmentTaskStatus(id, 'failed', null, 'فشل في قص الصورة أو تم إلغاء العملية');
                    }
                })
                .catch(error => {
                    // تنظيف المؤقت عند حدوث خطأ
                    if (task.timer) {
                        clearTimeout(task.timer);
                        task.timer = null;
                    }

                    // إخفاء تنبيه الانتظار حتى في حالة الخطأ
                    hideProcessingAlert();

                    console.error('[processAttachment] خطأ أثناء معالجة الصورة:', id, error);
                    const errorMessage = error && error.message ? error.message : 'خطأ غير معروف أثناء معالجة الصورة';
                    updateAttachmentTaskStatus(id, 'failed', null, errorMessage);

                    if (window.ErrorTracker && window.ErrorTracker.log) {
                        window.ErrorTracker.log('error', 'image-processing-error', errorMessage, {
                            taskId: id,
                            personKey: task.personKey,
                            fileName: task.originalFile.name,
                            error: error
                        });
                    }
                });
        } else {
            // الملفات غير الصورة (PDF، إلخ)
            if (task.originalFile instanceof File) {
                console.log('[processAttachment] معالجة ملف غير صورة:', id, task.originalFile.name);
                updateAttachmentTaskStatus(id, 'completed', task.originalFile, null);
            } else {
                console.error('[processAttachment] الملف الأصلي غير صالح:', id, task.originalFile);
                updateAttachmentTaskStatus(id, 'failed', null, 'الملف الأصلي غير صالح');
            }
        }
    }

    function showCropperModalPromise(file, timeoutMs = 120000) {
        return new Promise((resolve, reject) => {
            let finished = false;
            let cropperStarted = false;

            console.log('[showCropperModalPromise] بدء معالجة ملف:', file.name, 'الحجم:', file.size);

            // البحث عن دالة أداة القص مع انتظار قصير
            function findCropperFunction() {
                let showCropper = window.showCropperModal || window.showCropper;

                if (!showCropper) {
                    // البحث في جميع الدوال المتاحة
                    for (let key in window) {
                        if (typeof window[key] === 'function' && /cropper/i.test(key)) {
                            showCropper = window[key];
                            console.log('[showCropperModalPromise] تم العثور على دالة القص:', key);
                            break;
                        }
                    }
                }

                return showCropper;
            }

            function startCropperProcess() {
                if (finished) return;

                const showCropper = findCropperFunction();

                if (!showCropper) {
                    finished = true;
                    const errorMsg = 'أداة قص الصور غير متوفرة';
                    console.error('[showCropperModalPromise]', errorMsg);

                    // إظهار رسالة خطأ للمستخدم
                    hideProcessingAlert(); // إخفاء تنبيه الانتظار

                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في أداة القص',
                        text: 'أداة قص الصور غير متوفرة. يرجى تحديث الصفحة والمحاولة مرة أخرى.',
                        confirmButtonText: 'حسناً'
                    });

                    if (window.ErrorTracker && window.ErrorTracker.log) {
                        window.ErrorTracker.log('error', 'cropper-missing', errorMsg, { file: file.name });
                    }
                    reject(new Error('أداة قص الصور غير متوفرة. يرجى تحديث الصفحة.'));
                    return;
                }

                console.log('[showCropperModalPromise] بدء عملية القص للملف:', file.name);

                // مؤقت رئيسي لعملية القص
                let mainTimer = setTimeout(() => {
                    if (!finished) {
                        finished = true;
                        const errorMsg = `انتهت مهلة معالجة الصورة (${timeoutMs/1000} ثانية)`;
                        console.error('[showCropperModalPromise]', errorMsg);

                        hideProcessingAlert(); // إخفاء تنبيه الانتظار عند انتهاء المهلة

                        Swal.fire({
                            icon: 'warning',
                            title: 'انتهت المهلة الزمنية',
                            text: 'انتهت مهلة معالجة الصورة. يرجى المحاولة مرة أخرى.',
                            confirmButtonText: 'حسناً'
                        });

                        if (window.ErrorTracker && window.ErrorTracker.log) {
                            window.ErrorTracker.log('error', 'cropper-timeout', errorMsg, { file: file.name });
                        }
                        reject(new Error('انتهت مهلة معالجة الصورة. يرجى المحاولة مرة أخرى.'));
                    }
                }, timeoutMs);

                try {
                    console.log('[showCropperModalPromise] 🎯 استدعاء أداة القص للملف:', file.name);
                    console.log('[showCropperModalPromise] 📊 تفاصيل الملف:', {
                        name: file.name,
                        type: file.type,
                        size: file.size
                    });

                    // ⭐ CRITICAL: التأكد من تمرير الملف بشكل صحيح
                    showCropper(file, function(croppedFile, error) {
                        // ⭐ أضف هنا: إظهار رسالة الانتظار بعد الضغط على زر "قص وحفظ"
                        showProcessingAlert();

                        cropperStarted = true;

                        if (finished) {
                            console.log('[showCropperModalPromise] تم تجاهل النتيجة - العملية منتهية مسبقاً');
                            return;
                        }

                        finished = true;
                        clearTimeout(mainTimer);

                        if (error) {
                            console.error('[showCropperModalPromise] خطأ من أداة القص:', error);

                            // إخفاء تنبيه الانتظار عند حدوث خطأ
                            hideProcessingAlert();

                            if (window.ErrorTracker && window.ErrorTracker.log) {
                                window.ErrorTracker.log('error', 'cropper-callback-error', error, { file: file.name });
                            }
                            reject(error instanceof Error ? error : new Error(String(error)));
                            return;
                        }

                        if (!croppedFile) {
                            console.warn('[showCropperModalPromise] لم يتم إرجاع ملف مقصوص');

                            // إخفاء تنبيه الانتظار عند الإلغاء
                            hideProcessingAlert();

                            reject(new Error('لم يتم قص الصورة أو تم إلغاء العملية'));
                            return;
                        }

                        console.log('[showCropperModalPromise] ✅ تم القص بنجاح:', {
                            originalName: file.name,
                            croppedSize: croppedFile.size,
                            croppedType: croppedFile.type || 'unknown'
                        });

                        // تحويل Blob إلى File إذا لزم الأمر
                        let resultFile = croppedFile;
                        if (!(croppedFile instanceof File) && croppedFile instanceof Blob) {
                            try {
                                resultFile = new File([croppedFile], file.name, {
                                    type: croppedFile.type || file.type
                                });
                            } catch (e) {
                                console.warn('[showCropperModalPromise] فشل في تحويل Blob إلى File، سيتم استخدام Blob');
                                resultFile = croppedFile;
                            }
                        }

                        console.log('[showCropperModalPromise] تم قص الصورة بنجاح:', resultFile.name || 'unnamed');

                        // إخفاء تنبيه الانتظار بعد النجاح
                        hideProcessingAlert();

                        resolve(resultFile);
                    });

                    cropperStarted = true;

                } catch (error) {
                    clearTimeout(mainTimer);
                    finished = true;
                    console.error('[showCropperModalPromise] استثناء أثناء استدعاء أداة القص:', error);

                    // إخفاء تنبيه الانتظار عند حدوث خطأ
                    hideProcessingAlert();

                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في فتح أداة القص',
                        text: 'حدث خطأ أثناء فتح أداة قص الصور. يرجى المحاولة مرة أخرى.',
                        confirmButtonText: 'حسناً'
                    });

                    if (window.ErrorTracker && window.ErrorTracker.log) {
                        window.ErrorTracker.log('error', 'cropper-exception', error, { file: file.name });
                    }
                    reject(new Error('حدث خطأ أثناء فتح أداة قص الصور'));
                }
            }

            // التحقق الفوري من وجود أداة القص، إذا لم توجد انتظار قصير
            const showCropper = findCropperFunction();
            if (showCropper) {
                startCropperProcess();
            } else {
                console.warn('[showCropperModalPromise] أداة القص غير متوفرة فوراً، انتظار قصير...');
                // انتظار قصير (ثانية واحدة فقط) ثم المحاولة
                setTimeout(() => {
                    if (!finished) {
                        startCropperProcess();
                    }
                }, 1000);
            }
        });
    }

    function removeAttachmentTask(personKey, taskId) {
        if (!allDocs.has(personKey)) return;
        const arr = allDocs.get(personKey);
        const idx = arr.findIndex(t => t.id === taskId);
        if (idx !== -1) {
            const task = arr[idx];
            // تنظيف المؤقتات
            if (task.timer) {
                clearTimeout(task.timer);
                task.timer = null;
                console.log('[removeAttachmentTask] تم إلغاء المؤقت للمهمة:', taskId);
            }
            // تنظيف URLs
            if (task.processedFile && task.processedFile.previewUrl) {
                try { URL.revokeObjectURL(task.processedFile.previewUrl); } catch {}
            }
            arr.splice(idx, 1);
            if (arr.length === 0) allDocs.delete(personKey);
            renderAttachmentTasksUI(personKey);
            console.log('[removeAttachmentTask] تم حذف المهمة بنجاح:', taskId);
        }
    }

    // حذف جميع المهام المرتبطة ببوابة معينة (مثلاً عند حذف فرد أسرة)
    function removeAllAttachmentTasksForPersonKey(personKey) {
        if (window.allDocs && window.allDocs.has(personKey)) {
            const tasks = window.allDocs.get(personKey);
            // تنظيف جميع المؤقتات
            tasks.forEach(task => {
                if (task.timer) {
                    clearTimeout(task.timer);
                    task.timer = null;
                }
                // تنظيف URLs
                if (task.processedFile && task.processedFile.previewUrl) {
                    try { URL.revokeObjectURL(task.processedFile.previewUrl); } catch {}
                }
            });
            window.allDocs.delete(personKey);
            console.log('[removeAllAttachmentTasksForPersonKey] تم حذف جميع المهام للشخص:', personKey);
        }
    }

    // تحسين renderAttachmentTasksUI للعرض الفوري المحسن
    function renderAttachmentTasksUI(personKey) {
        console.log(`[renderAttachmentTasksUI] 🎨 تحديث واجهة البوابة: ${personKey}`);

        // تحقق أن البوابة موجودة فعلياً في الصفحة
        let previewDiv = null;
        const zone = document.querySelector(`[data-upload-zone="${personKey}"]`);
        if (zone) {
            previewDiv = zone.querySelector('.mainDocumentPreview');
        }

        // إذا لم توجد البوابة في الصفحة، لا تعرض شيئاً واحذف المرفقات من allDocs
        if (!previewDiv) {
            console.warn(`[renderAttachmentTasksUI] بوابة ${personKey} غير موجودة في الصفحة`);
            removeAllAttachmentTasksForPersonKey(personKey);
            return;
        }

        previewDiv.innerHTML = '';
        const arr = allDocs.get(personKey) || [];

        console.log(`[renderAttachmentTasksUI] عدد المهام للعرض: ${arr.length}`);

        arr.forEach((task, index) => {
            console.log(`[renderAttachmentTasksUI] معالجة مهمة ${index + 1}/${arr.length}:`, {
                id: task.id,
                status: task.status,
                fileName: task.originalFile ? task.originalFile.name : 'مجهول',
                isImage: task.originalFile ? task.originalFile.type.startsWith('image/') : false
            });

            const card = document.createElement('div');
            card.className = 'card mb-2 attachment-card';
            card.id = 'preview_att_' + task.id; // معرف فريد لكل بطاقة
            card.style.width = '170px';
            card.style.display = 'inline-block';
            card.style.marginRight = '8px';

            const cardBody = document.createElement('div');
            cardBody.className = 'card-body p-2 text-center';

            // عنوان نوع الوثيقة
            const docType = document.createElement('div');
            docType.className = 'fw-bold mb-1';
            docType.textContent = task.docType || '';
            cardBody.appendChild(docType);

            // اسم الملف
            const docNameDiv = document.createElement('div');
            docNameDiv.className = 'small text-muted mb-1';
            docNameDiv.textContent = task.processedFile ? (task.processedFile.name || '') : (task.originalFile.name || '');
            cardBody.appendChild(docNameDiv);

            // ⭐ منطق العرض الفوري المحسن
            let previewFile = null;
            let showProcessingOverlay = false;

            // أولوية العرض: دائماً اعرض ملف صالح
            if (task.originalFile && task.originalFile.type && task.originalFile.type.startsWith('image/')) {
                previewFile = task.originalFile; // العرض الفوري للملف الأصلي
                console.log(`[renderAttachmentTasksUI] ✅ استخدام الملف الأصلي للعرض الفوري: ${task.originalFile.name}`);

                // استبدال بالملف المعالج إذا اكتمل القص فعلياً
                if (task.status === 'completed' &&
                    task.isProcessed &&
                    task.processedFile &&
                    task.processedFile !== task.originalFile &&
                    task.processedFile.type &&
                    task.processedFile.type.startsWith('image/')) {
                    previewFile = task.processedFile;
                    console.log(`[renderAttachmentTasksUI] 🔄 استبدال بالملف المعالج: ${task.processedFile.name}`);
                }

                // إظهار overlay المعالجة أثناء pending/processing فقط
                if (task.status === 'pending' || task.status === 'processing') {
                    showProcessingOverlay = true;
                    console.log(`[renderAttachmentTasksUI] 🔄 إظهار overlay للحالة: ${task.status}`);
                }
            }

            if (previewFile) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'position-relative mb-1 attachment-preview-container';
                imgContainer.style.display = 'inline-block';

                const img = document.createElement('img');
                const objectUrl = URL.createObjectURL(previewFile);
                img.src = objectUrl;
                img.style.maxWidth = '100px';
                img.style.maxHeight = '100px';
                img.className = 'rounded border';

                // إضافة تأثير المعالجة إذا لزم الأمر
                if (showProcessingOverlay) {
                    // تطبيق تأثير شفافية على الصورة أثناء المعالجة
                    img.style.opacity = '0.7';
                    img.style.filter = 'blur(1px)';

                    // إضافة spinner فوق الصورة
                    const overlay = document.createElement('div');
                    overlay.className = 'position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center processing-overlay';
                    overlay.style.background = 'rgba(0,0,0,0.3)';
                    overlay.style.borderRadius = '8px';

                    const spinner = document.createElement('div');
                    spinner.className = 'spinner-border spinner-border-sm text-light';
                    spinner.style.width = '1.5rem';
                    spinner.style.height = '1.5rem';

                    const processingText = document.createElement('div');
                    processingText.className = 'small text-light mt-1 text-center';
                    processingText.style.fontSize = '0.7rem';
                    processingText.textContent = task.status === 'pending' ? 'انتظار...' : 'معالجة...';

                    overlay.appendChild(spinner);
                    overlay.appendChild(processingText);
                    imgContainer.appendChild(img);
                    imgContainer.appendChild(overlay);
                } else {
                    imgContainer.appendChild(img);
                }

                cardBody.appendChild(imgContainer);
                card.dataset.objectUrl = objectUrl;
            }

            const statusDiv = document.createElement('div');
            statusDiv.className = 'mt-1';

            // تحسين عرض الحالات مع رسائل واضحة ومناسبة للموبايل
            switch (task.status) {
                case 'pending':
                    statusDiv.innerHTML = '<span class="status-badge pending">🔄 تحضير...</span>';
                    break;
                case 'processing':
                    statusDiv.innerHTML = '<span class="status-badge processing">⚙️ جاري القص...</span>';
                    break;
                case 'completed':
                    statusDiv.innerHTML = '<span class="status-badge completed">✅ تم بنجاح</span>';
                    // إضافة تأثير نجاح للبطاقة
                    card.classList.add('attachment-card');
                    setTimeout(() => {
                        card.classList.add('success-flash');
                    }, 100);
                    break;
                case 'failed':
                    const errorMsg = task.errorMessage || 'خطأ غير معروف';
                    // تجنب إظهار "timeout" كرسالة خطأ غامضة
                    const displayMsg = errorMsg.includes('timeout') || errorMsg.includes('انتهى')
                        ? 'انتهت المهلة'
                        : errorMsg.length > 25 ? errorMsg.substring(0, 25) + '...' : errorMsg;
                    statusDiv.innerHTML = `<span class="status-badge failed">❌ فشل</span><br><span class="text-danger small" style="font-size: 0.65rem;">${displayMsg}</span>`;
                    break;
                default:
                    statusDiv.innerHTML = '<span class="status-badge">❓ غير معروف</span>';
                    console.warn('[renderAttachmentTasksUI] حالة مهمة غير معروفة:', task.status, task);
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
        if (!personKey || personKey === 'template' || personKey.includes('template')) {
            console.warn('[initUploadZone] تجاهل تهيئة منطقة رفع بقيمة template أو فارغة:', {zone, personKey});
            return;
        }
        const fileInput = zone.querySelector('input[type="file"]');
        const docTypeSelect = zone.querySelector('select');
        if (!fileInput || !docTypeSelect) {
            console.error('[initUploadZone] عناصر الرفع مفقودة في المنطقة:', personKey, {fileInput, docTypeSelect});
            return;
        }
        // التأكد من أن المنطقة لم يتم تهيئتها مسبقاً
        if (zone.dataset.initialized === 'true') {
            console.log('[initUploadZone] المنطقة مهيأة مسبقاً:', personKey);
            return;
        }

        // وضع علامة التهيئة
        zone.dataset.initialized = 'true';

        console.log(`[initUploadZone] تهيئة منطقة رفع الملفات: personKey=${personKey}`, {zone, fileInput, docTypeSelect});

        // دعم رفع ملفات متعددة
        fileInput.setAttribute('multiple', 'multiple');

        // إزالة مستمعي الأحداث السابقين لتجنب التكرار
        const newFileInput = fileInput.cloneNode(true);
        fileInput.parentNode.replaceChild(newFileInput, fileInput);

        newFileInput.addEventListener('change', function(e) {
            console.log('🟠 محاولة رفع ملف، قيمة نوع الوثيقة:', docTypeSelect.value, 'في المنطقة:', personKey);

            // منع المستخدم من الرفع إذا كانت هناك معالجة جارية
            if (window.isProcessingAttachment) {
                Swal.fire({
                    icon: 'warning',
                    title: 'جاري معالجة ملف آخر',
                    text: 'الرجاء الانتظار حتى تنتهي معالجة الملف الحالي قبل رفع ملف جديد.',
                    confirmButtonText: 'حسناً'
                });
                newFileInput.value = '';
                return;
            }

            if (!docTypeSelect.value || docTypeSelect.value === 'undefined' || docTypeSelect.value === '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يجب اختيار نوع الوثيقة أولاً قبل رفع الملف.',
                    confirmButtonText: 'حسناً'
                });
                newFileInput.value = '';
                return;
            }

            // التحقق من وجود أداة القص قبل المعالجة
            function ensureCropperAvailable() {
                return new Promise((resolve, reject) => {
                    if (window.showCropperModal || window.showCropper) {
                        console.log('✅ أداة القص متوفرة للمنطقة:', personKey);
                        resolve(true);
                        return;
                    }

                    console.warn('⚠️ أداة القص غير متوفرة، انتظار التحميل للمنطقة:', personKey);
                    let attempts = 0;
                    const maxAttempts = 50; // 10 ثواني

                    const checkInterval = setInterval(() => {
                        attempts++;
                        if (window.showCropperModal || window.showCropper) {
                            console.log('✅ تم تحميل أداة القص بنجاح للمنطقة:', personKey);
                            clearInterval(checkInterval);
                            resolve(true);
                        } else if (attempts >= maxAttempts) {
                            console.error('❌ فشل في تحميل أداة القص للمنطقة:', personKey);
                            clearInterval(checkInterval);
                            reject(new Error('فشل في تحميل أداة قص الصور'));
                        }
                    }, 200);
                });
            }

            // التأكد من توفر أداة القص قبل المتابعة
            ensureCropperAvailable()
                .then(() => {
                    let personId = '';

                    // استخراج رقم الهوية حسب نوع المنطقة
                    if (personKey === 'main') {
                        // البيانات الأساسية
                        const idInput = document.getElementById('data_id_number');
                        personId = idInput ? idInput.value.trim() : '';
                        // لا تمنع الرفع إذا كان personId فارغاً هنا
                        // استخدم قيمة افتراضية إذا كان فارغًا
                        if (!personId) {
                            personId = 'default_main';
                            console.log('[initUploadZone] استخدام قيمة افتراضية للبيانات الأساسية:', personId);
                        }
                    } else if (personKey === 'deceased_father') {
                        const fatherIdInput = document.querySelector('input[name="father_id"]');
                        personId = fatherIdInput ? fatherIdInput.value.trim() : '';
                        // استخدم قيمة افتراضية إذا كان فارغًا
                        if (!personId) {
                            personId = 'default_father';
                            console.log('[initUploadZone] استخدام قيمة افتراضية للأب المتوفى:', personId);
                        }
                    } else if (personKey === 'deceased_mother') {
                        const motherIdInput = document.querySelector('input[name="mother_id"]');
                        personId = motherIdInput ? motherIdInput.value.trim() : '';
                        // استخدم قيمة افتراضية إذا كان فارغًا
                        if (!personId) {
                            personId = 'default_mother';
                            console.log('[initUploadZone] استخدام قيمة افتراضية للأم المتوفية:', personId);
                        }
                    } else if (personKey.startsWith('family_')) {
                        // أفراد الأسرة: يجب وجود رقم هوية
                        const form = zone.closest('.family-member-form');
                        if (form) {
                            const personIdInput = form.querySelector('input[name$="[person_id]"]');
                            personId = personIdInput ? personIdInput.value.trim() : '';

                            if (!personId) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'تنبيه',
                                    text: 'يرجى إدخال رقم هوية فرد الأسرة أولاً قبل رفع الملف.',
                                    confirmButtonText: 'حسناً'
                                });
                                newFileInput.value = '';
                                return;
                            }

                            // التحقق من صحة رقم الهوية (9-10 أرقام)
                            if (!/^[0-9]{9,10}$/.test(personId)) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'تنبيه',
                                    text: 'رقم الهوية يجب أن يكون مكوناً من 9-10 أرقام فقط.',
                                    confirmButtonText: 'حسناً'
                                });
                                newFileInput.value = '';
                                return;
                            }
                        } else {
                            console.error('[initUploadZone] لم يتم العثور على نموذج فرد الأسرة للمنطقة:', personKey);
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: 'حدث خطأ في النظام. يرجى تحديث الصفحة والمحاولة مرة أخرى.',
                                confirmButtonText: 'حسناً'
                            });
                            newFileInput.value = '';
                            return;
                        }
                    } else {
                        // أنواع أخرى من المفاتيح
                        console.warn('[initUploadZone] نوع personKey غير معروف:', personKey);
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'نوع منطقة الرفع غير معروف. يرجى التواصل مع الدعم الفني.',
                            confirmButtonText: 'حسناً'
                        });
                        newFileInput.value = '';
                        return;
                    }
                    // لا تمنع الرفع للبوابات الرئيسية والمتوفين حتى لو كان personId فارغاً
                    // دعم رفع ملفات متعددة بشكل تراكمي
                    const docTypeValue = docTypeSelect.value;
                    const fileIdNumber = document.querySelector('input[name="file_id_number"]')?.value || '';

                    // طباعة تفاصيل كاملة للمساعدة في التشخيص
                    console.log('📋 تفاصيل الرفع:', {
                        personKey: personKey,
                        personId: personId,
                        docTypeValue: docTypeValue,
                        fileIdNumber: fileIdNumber,
                        filesCount: newFileInput.files.length
                    });

                    Array.from(newFileInput.files).forEach(file => {
                        console.log('🟢 رفع ملف جديد:', file.name, 'نوع الوثيقة (pref):', docTypeValue, 'في المنطقة:', personKey);
                        addAttachmentTask(personKey, file, docTypeValue, personId, fileIdNumber);
                    });

                    // طباعة محتويات allDocs للتأكد من إضافة المرفقات
                    console.log('📊 محتويات allDocs بعد الإضافة:', Array.from(window.allDocs.entries()));

                    setTimeout(() => { newFileInput.value = ''; }, 10);
                })
                .catch(error => {
                    console.error('❌ خطأ في التأكد من توفر أداة القص:', error);
                    hideProcessingAlert(); // تأكد من إخفاء التنبيه في حالة الخطأ
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: 'أداة قص الصور غير متوفرة. يرجى تحديث الصفحة والمحاولة مرة أخرى.',
                        confirmButtonText: 'حسناً'
                    });
                    newFileInput.value = '';
                });
        });

        // تعطيل القائمة المنسدلة أثناء المعالجة
        docTypeSelect.addEventListener('mousedown', function(e) {
            if (window.isProcessingAttachment) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'جاري معالجة ملف',
                    text: 'الرجاء الانتظار حتى تنتهي معالجة الملف الحالي.',
                    confirmButtonText: 'حسناً'
                });
                return false;
            }
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

    // تهيئة جميع مناطق الرفع الموجودة عند تحميل الصفحة
    function initializeAllUploadZones() {
        console.log('[initializeAllUploadZones] بدء تهيئة جميع مناطق الرفع...');

        // تهيئة المناطق الرئيسية أولاً
        document.querySelectorAll('[data-upload-zone]:not([data-upload-zone*="family_"])').forEach(zone => {
            console.log('[initializeAllUploadZones] تهيئة منطقة رئيسية:', zone.getAttribute('data-upload-zone'));
            initUploadZone(zone);
        });

        // ثم تهيئة مناطق أفراد الأسرة
        document.querySelectorAll('[data-upload-zone*="family_"]').forEach(zone => {
            const personKey = zone.getAttribute('data-upload-zone');
            if (personKey && !personKey.includes('template')) {
                console.log('[initializeAllUploadZones] تهيئة منطقة أفراد الأسرة:', personKey);
                initUploadZone(zone);
            }
        });

        console.log('[initializeAllUploadZones] تمت تهيئة جميع مناطق الرفع');
    }

    // تهيئة فورية
    initializeAllUploadZones();

    // تهيئة إضافية بعد تحميل كامل للصفحة للتأكد
    setTimeout(initializeAllUploadZones, 1000);

    // مراقبة محسنة لحذف وإضافة أفراد الأسرة
    const familyContainer = document.getElementById('familyMembersContainer');
    if (familyContainer) {
        const observer = new MutationObserver(function(mutations) {
            let shouldCleanup = false;
            let shouldInitialize = false;

            // حفظ مرفقات البوابات الأساسية والمتوفين قبل أي تغييرات
            preserveMainAndDeceasedAttachments();

            // جمع جميع personKey الحاليين في الصفحة
            const currentKeys = Array.from(document.querySelectorAll('[data-upload-zone]'))
                .map(zone => zone.getAttribute('data-upload-zone'))
                .filter(key => key && key !== 'template');

            mutations.forEach(function(mutation) {
                // التعامل مع العقد المحذوفة
                mutation.removedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.querySelector) {
                        const removedZones = node.querySelectorAll('[data-upload-zone]');
                        if (removedZones.length > 0) {
                            shouldCleanup = true;
                        }
                    }
                });

                // التعامل مع العقد المضافة
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.querySelector) {
                        const addedZones = node.querySelectorAll('[data-upload-zone]');
                        if (addedZones.length > 0) {
                            shouldInitialize = true;
                        }
                    }
                });
            });

            // تنظيف المرفقات للمناطق المحذوفة
            if (shouldCleanup && window.allDocs) {
                Array.from(window.allDocs.keys()).forEach(personKey => {
                    if (personKey.startsWith('family_') && !currentKeys.includes(personKey)) {
                        console.log('[Observer] حذف مرفقات منطقة محذوفة:', personKey);
                        removeAllAttachmentTasksForPersonKey(personKey);
                    }
                });
            }

            // تهيئة مناطق الرفع الجديدة
            if (shouldInitialize) {
                setTimeout(() => {
                    document.querySelectorAll('[data-upload-zone]').forEach(zone => {
                        const personKey = zone.getAttribute('data-upload-zone');
                        if (personKey && !personKey.includes('template') && zone.dataset.initialized !== 'true') {
                            console.log('[Observer] تهيئة منطقة رفع جديدة:', personKey);
                            initUploadZone(zone);
                        }
                    });

                    // استعادة المرفقات المحمية بعد التحديثات
                    setTimeout(() => {
                        window.restoreMainAndDeceasedAttachments();
                    }, 100);
                }, 100);
            }
        });

        observer.observe(familyContainer, {
            childList: true,
            subtree: true,
            attributes: false,
            characterData: false
        });

        console.log('[documentUpload] تم تهيئة مراقب أفراد الأسرة مع حماية المرفقات');
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

            // طباعة تشخيصية قبل الإرسال
            console.log('📦 بيانات المرفقات قبل الإرسال:', Array.from(window.allDocs.entries()));

            let index = 0;
            let attachmentsLog = []; // للتشخيص

            allDocs.forEach((tasksArr, personKey) => {
                console.log(`⏳ معالجة مرفقات البوابة: ${personKey}, العدد: ${tasksArr.length}`);

                tasksArr.forEach(task => {
                    if (task.status !== 'completed' || !task.processedFile) {
                        console.log(`⚠️ تجاهل مرفق غير مكتمل:`, task);
                        return;
                    }

                    // إضافة معلومات المرفق للتشخيص
                    attachmentsLog.push({
                        index: index,
                        personKey: personKey,
                        personId: task.personId,
                        fileName: task.processedFile.name,
                        docType: task.docType,
                        fileIdNumber: task.fileIdNumber || '',
                        status: task.status
                    });

                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = `attachments[${index}][file]`;
                    fileInput.style.display = 'none';
                    const dt = new DataTransfer();
                    if (task.processedFile instanceof File) {
                        dt.items.add(task.processedFile);
                        console.log(`✅ إضافة ملف للإرسال: ${task.processedFile.name}`);
                    } else {
                        console.warn('⚠️ تم تجاهل عنصر غير صالح في DataTransfer:', task.processedFile);
                    }
                    fileInput.files = dt.files;
                    form.appendChild(fileInput);

                    const personIdInput = document.createElement('input');
                    personIdInput.type = 'hidden';
                    personIdInput.name = `attachments[${index}][person_identity_number]`;
                    personIdInput.value = task.personId || `default_${personKey}`;
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

                    // إضافة حقل إضافي يحدد البوابة الأصلية للمرفق
                    const personKeyInput = document.createElement('input');
                    personKeyInput.type = 'hidden';
                    personKeyInput.name = `attachments[${index}][person_key]`;
                    personKeyInput.value = personKey;
                    form.appendChild(personKeyInput);

                    index++;
                });
            });

            // طباعة ملخص المرفقات التي سيتم إرسالها للتأكد
            console.log('📤 ملخص المرفقات التي سيتم إرسالها:', attachmentsLog);
            console.log('📊 إجمالي عدد المرفقات المرسلة:', index);

            // إنشاء مدخل إضافي في النموذج للتشخيص
            const debugInfo = document.createElement('input');
            debugInfo.type = 'hidden';
            debugInfo.name = 'attachments_debug_info';
            debugInfo.value = JSON.stringify(attachmentsLog);
            form.appendChild(debugInfo);

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

// إضافة دالة خاصة للتأكد من حفظ مرفقات البوابات الأساسية والمتوفين
function preserveMainAndDeceasedAttachments() {
    if (!window.allDocs) return;

    // نسخ البيانات الحالية بطريقة عميقة (deep copy)
    const currentState = {};
    const protectedKeys = ['main', 'deceased_father', 'deceased_mother', 'default_main', 'default_father', 'default_mother'];

    window.allDocs.forEach((value, key) => {
        // حفظ المرفقات للبوابات الأساسية والمتوفين وأي مفتاح غير خاص بأفراد الأسرة
        if (protectedKeys.includes(key) || key.startsWith('default_') || (!key.startsWith('family_'))) {
            // عمل نسخة عميقة من المصفوفة مع الاحتفاظ بالخصائص المهمة
            const deepCopy = value.map(task => ({
                id: task.id,
                personKey: task.personKey,
                docType: task.docType,
                personId: task.personId,
                fileIdNumber: task.fileIdNumber || '',
                status: task.status,
                // نسخ الملفات ومراجع البيانات المهمة
                originalFile: task.originalFile,
                processedFile: task.processedFile,
                isProcessed: task.isProcessed
            }));

            currentState[key] = deepCopy;
            console.log(`🛡️ حفظ ${deepCopy.length} مرفق للبوابة: ${key}`);
        }
    });

    if (Object.keys(currentState).length > 0) {
        // تخزين النسخة في متغير عام مع طابع زمني
        window._preservedAttachments = currentState;
        window._preservedAttachmentsTimestamp = Date.now();
        console.log('🔒 تم حفظ نسخة من مرفقات البوابات الرئيسية:', Object.keys(currentState).join(', '));
    }
}

// تحسين دالة استرجاع المرفقات لتعمل بشكل أكثر ذكاءً
window.restoreMainAndDeceasedAttachments = function(forceRestore = false) {
    if (!window.allDocs || !window._preservedAttachments) return false;

    // التحقق من المرفقات الحالية وما إذا كانت مفقودة
    const currentKeys = Array.from(window.allDocs.keys());
    const preservedKeys = Object.keys(window._preservedAttachments);
    let needsRestore = forceRestore;

    // التحقق مما إذا كانت البوابات الرئيسية مفقودة
    preservedKeys.forEach(key => {
        if (!currentKeys.includes(key) && window._preservedAttachments[key].length > 0) {
            needsRestore = true;
            console.log(`⚠️ اكتشاف فقدان البوابة: ${key} (${window._preservedAttachments[key].length} مرفق)`);
        }
    });

    if (!needsRestore) {
        // فحص إضافي: هل تم تقليل عدد المرفقات في أي بوابة؟
        preservedKeys.forEach(key => {
            if (currentKeys.includes(key)) {
                const currentCount = window.allDocs.get(key).length;
                const preservedCount = window._preservedAttachments[key].length;

                if (currentCount < preservedCount) {
                    console.log(`⚠️ اكتشاف نقص في المرفقات: ${key} (${currentCount}/${preservedCount})`);
                    needsRestore = true;
                }
            }
        });
    }

    if (needsRestore) {
        let restoredCount = 0;

        preservedKeys.forEach(key => {
            const preservedDocs = window._preservedAttachments[key];

            if (!preservedDocs || !preservedDocs.length) return;

            // إذا لم تكن البوابة موجودة، أنشئها
            if (!window.allDocs.has(key)) {
                window.allDocs.set(key, [...preservedDocs]);
                restoredCount += preservedDocs.length;
                console.log(`🔄 استعادة البوابة المفقودة بالكامل: ${key} (${preservedDocs.length} مرفق)`);
            } else {
                // إذا كانت البوابة موجودة، أضف المرفقات المفقودة فقط
                const currentDocs = window.allDocs.get(key);
                const currentIds = new Set(currentDocs.map(doc => doc.id));

                let addedCount = 0;
                preservedDocs.forEach(doc => {
                    if (!currentIds.has(doc.id)) {
                        currentDocs.push(doc);
                        addedCount++;
                        restoredCount++;
                    }
                });

                if (addedCount > 0) {
                    console.log(`🔄 استعادة ${addedCount} مرفق مفقود للبوابة: ${key}`);
                }
            }
        });

        if (restoredCount > 0) {
            console.log(`✅ تمت استعادة ${restoredCount} مرفق للبوابات الرئيسية`);

            // تحديث الواجهة لعرض المرفقات المستعادة
            preservedKeys.forEach(key => {
                if (window.allDocs.has(key)) {
                    // تأكد من وجود البوابة في الصفحة قبل تحديث الواجهة
                    const zone = document.querySelector(`[data-upload-zone="${key}"]`);
                    if (zone) {
                        renderAttachmentTasksUI(key);
                    }
                }
            });

            return true;
        }
    }

    return false;
};

// تعزيز مراقبة الانتقال بين التبويبات لاستعادة المرفقات
document.addEventListener('DOMContentLoaded', function() {
    // مراقبة الانتقال بين التبويبات
    document.querySelectorAll('.nav-link[data-bs-toggle="tab"]').forEach(tabLink => {
        tabLink.addEventListener('shown.bs.tab', function(e) {
            const targetId = e.target.getAttribute('data-bs-target');

            // الحفظ والاسترجاع عند التنقل بين التبويبات
            preserveMainAndDeceasedAttachments();
            setTimeout(() => {
                window.restoreMainAndDeceasedAttachments();
            }, 200);

            // استرجاع إضافي عند الدخول إلى تبويبات محددة
            if (targetId === '#basic' || targetId === '#deceased' || targetId === '#family-members') {
                setTimeout(() => {
                    if (window.restoreMainAndDeceasedAttachments(true)) {
                        console.log(`🔎 استرجاع استباقي للمرفقات عند الدخول إلى التبويب: ${targetId}`);
                    }
                }, 500);
            }

            // استعادة المرفقات بعد تحديث التبويب
            setTimeout(() => {
                if (targetId === '#review') {
                    console.log('📋 التبويب: المراجعة - فحص المرفقات قبل الإرسال');
                    window.restoreMainAndDeceasedAttachments(true);
                }
            }, 300);
        });
    });

    // حفظ المرفقات دوريًا
    setInterval(preserveMainAndDeceasedAttachments, 10000); // كل 10 ثوانِ

    // استرجاع المرفقات عند تحميل الصفحة
    setTimeout(() => {
        preserveMainAndDeceasedAttachments();
    }, 1000);
});

// تعزيز مراقبة أفراد الأسرة - استبدال الكود الموجود
const familyContainer = document.getElementById('familyMembersContainer');
if (familyContainer) {
    const observer = new MutationObserver(function(mutations) {
        let shouldCleanup = false;
        let shouldInitialize = false;

        // حفظ مرفقات البوابات الأساسية والمتوفين قبل أي تغييرات
        preserveMainAndDeceasedAttachments();

        // جمع جميع personKey الحاليين في الصفحة
        const currentKeys = Array.from(document.querySelectorAll('[data-upload-zone]'))
            .map(zone => zone.getAttribute('data-upload-zone'))
            .filter(key => key && key !== 'template');

        mutations.forEach(function(mutation) {
            // التعامل مع العقد المحذوفة
            mutation.removedNodes.forEach(function(node) {
                if (node.nodeType === 1 && node.querySelector) {
                    const removedZones = node.querySelectorAll('[data-upload-zone]');
                    if (removedZones.length > 0) {
                        shouldCleanup = true;
                    }
                }
            });

            // التعامل مع العقد المضافة
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1 && node.querySelector) {
                    const addedZones = node.querySelectorAll('[data-upload-zone]');
                    if (addedZones.length > 0) {
                        shouldInitialize = true;
                    }
                }
            });
        });

        // تنظيف المرفقات للمناطق المحذوفة
        if (shouldCleanup && window.allDocs) {
            Array.from(window.allDocs.keys()).forEach(personKey => {
                if (personKey.startsWith('family_') && !currentKeys.includes(personKey)) {
                    console.log('[Observer] حذف مرفقات منطقة محذوفة:', personKey);
                    removeAllAttachmentTasksForPersonKey(personKey);
                }
            });
        }

        // تهيئة مناطق الرفع الجديدة
        if (shouldInitialize) {
            setTimeout(() => {
                document.querySelectorAll('[data-upload-zone]').forEach(zone => {
                    const personKey = zone.getAttribute('data-upload-zone');
                    if (personKey && !personKey.includes('template') && zone.dataset.initialized !== 'true') {
                        console.log('[Observer] تهيئة منطقة رفع جديدة:', personKey);
                        initUploadZone(zone);
                    }
                });

                // استعادة المرفقات المحمية بعد التحديثات
                setTimeout(() => {
                    window.restoreMainAndDeceasedAttachments();
                }, 100);
            }, 100);
        }
    });

    observer.observe(familyContainer, {
        childList: true,
        subtree: true,
        attributes: false,
        characterData: false
    });

    console.log('[documentUpload] تم تهيئة مراقب أفراد الأسرة مع حماية المرفقات');
}


</script>
@endpush
