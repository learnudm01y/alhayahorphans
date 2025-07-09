@push('scriptsCodeUserRegistration')
    {{-- تعريف documentTypes من الباكند لضمان توفره لجميع السكريبتات --}}
    <script>
        window.documentTypes = window.documentTypes || @json($documentTypes ?? []);
    </script>
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
                    // إذا كان الخطأ بسبب إلغاء القص أو عدم حفظ الصورة
                    if (errorInfo.message === 'لم يتم قص الصورة أو تم إلغاء العملية') {
                      if (typeof window.hideProcessingAlert === 'function') window.hideProcessingAlert();
                        Swal.fire({
                            icon: 'info',
                            title: 'لم يتم حفظ الصورة',
                            text: 'لم يتم حفظ الصورة لأنك لم تضغط على زر "حفظ التعديلات".',
                            confirmButtonText: 'نعم'
                        });
                    } else {

                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في العملية',
                            text: error.message || ' انتهى الوقت المحدد لتنفيذ عملية قص الصورة ',
                            confirmButtonText: 'نعم'
                        });
                    }
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

            // مراقبة تحديث حالة المرفقات في window.allDocs وتحديث processedFile تلقائياً إذا كان هناك ملف processedFile من نوع File أو Blob
            function patchAllDocsProcessedFile() {
                // حماية من التكرار المتداخل (منع الاستدعاء الذاتي/التكرار)
                if (patchAllDocsProcessedFile.isPatching) return;
                patchAllDocsProcessedFile.isPatching = true;
                try {
                    if (window.allDocs && window.allDocs instanceof Map) {
                        let imageProcessed = false;
                        window.allDocs.forEach(arr => {
                            arr.forEach(doc => {
                                // لا تغيّر processedFile أبداً إلى true أو أي قيمة غير File/Blob
                                if ((doc.processedFile instanceof File || doc.processedFile instanceof Blob) && doc.status === 'completed') {
                                    imageProcessed = true;
                                    console.log('[patchAllDocsProcessedFile] processedFile هو ملف صالح (File/Blob) للوثيقة:', doc);
                                } else if (doc.status === 'completed' && (doc.processedFile === true || doc.processedFile === 1 || doc.processedFile == null)) {
                                    // محاولة تعيين processedFile إلى قيمة غير صالحة، تجاهل مع تحذير
                                    console.warn('[patchAllDocsProcessedFile] محاولة تعيين processedFile إلى قيمة غير صالحة! سيتم تجاهلها ولن يتم تغيير الملف الحالي.', doc);
                                }
                            });
                        });
                        // تم تعطيل إعادة تهيئة القوائم المنسدلة (select) بعد رفع الملفات نهائياً
                        // لا تقم بأي إعادة تعيين أو تغيير selectedIndex هنا إطلاقاً
                        // هذا يحل مشكلة حجب المستخدم عن رفع ملفات متعددة أو من نفس النوع
                        // إذا كان هناك حاجة لإعادة التهيئة، يجب أن تكون فقط بناءً على تفاعل المستخدم وليس تلقائياً بعد الرفع
                        // console.log('[patchAllDocsProcessedFile] تم تجاوز إعادة تهيئة القوائم المنسدلة بعد رفع الملفات.');
                    }
                } finally {
                    patchAllDocsProcessedFile.isPatching = false;
                }
            }

            // دالة تنفذ بعد اكتمال معالجة أي مرفق (صورة)
            function handleAfterAttachmentCompleted(doc) {
                // لا تقم بأي تعديل على allDocs أو processedFile أو استدعاء patchAllDocsProcessedFile هنا إطلاقاً!
                // فقط عمليات جانبية أو إشعارات أو لوجات
                console.log('[handleAfterAttachmentCompleted] تم تنفيذ العملية المطلوبة بعد اكتمال معالجة المرفق (بدون أي تعديل على allDocs):', doc);
            }
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
                                idNumber = document.querySelector(`input[name="${parent}_id"]`)?.value || '';
                            } else if (selectedPerson.startsWith('family_')) {
                                const index = selectedPerson.split('_')[1];
                                idNumber = document.querySelector(`input[name="family_members[${index}][person_id]"]`)?.value || '';
                            }
                        } catch (error) {
                            ErrorTracker.log('error', 'get_id_number', error, { selectedPerson });
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
                            ErrorTracker.log('error', 'store_doc_info', error, { docId, selectedPerson });
                        }

                        // تحديث window.allDocs فوراً بعد رفع الوثيقة
                        if (window.allDocs && window.allDocs instanceof Map) {
                            // منطق إضافة الوثيقة الجديدة إلى allDocs
                            // دعم المتوفين: إذا كان الشخص متوفى (deceased_...)، استخدم رقم الهوية كـ key إضافي
                            let arr = window.allDocs.get(selectedPerson) || [];
                            let docObj = {
                                type: docType,
                                docType: docType, // توحيد الحقل
                                processedFile: true,
                                file: file,
                                personId: idNumber,
                                personKey: idNumber, // إضافة personKey لسهولة التحقق
                                status: 'completed'
                            };
                            // طباعة تشخيصية بعد الإضافة
                            console.log('تمت إضافة وثيقة إلى allDocs:', {selectedPerson, docObj, allDocs: Array.from(window.allDocs.entries())});
                            arr.push(docObj);
                            window.allDocs.set(selectedPerson, arr);
                            // إذا كان selectedPerson يبدأ بـ deceased_ أو يوجد idNumber، أضف أيضاً entry برقم الهوية
                            if (selectedPerson.startsWith('deceased_') && idNumber) {
                                let arrById = window.allDocs.get(idNumber) || [];
                                arrById.push(docObj);
                                window.allDocs.set(idNumber, arrById);
                            }
                        }
                        // تحديث حالة processedFile مباشرة
                        if (typeof patchAllDocsProcessedFile === 'function') {
                            patchAllDocsProcessedFile();
                        }

                        // إعادة تهيئة القائمة المنسدلة لنوع الوثيقة بعد كل رفع (إرجاعها للوضع الافتراضي فقط)
                        // إعادة تهيئة القائمة المنسدلة الخاصة برفع الملفات: إضافة/تفعيل خيار (اختر نوع الوثيقة) وجعله هو الظاهر
                        if (docTypeSelect) {
                            // ابحث عن خيار فارغ أو "0" أو نص "اختر نوع الوثيقة"
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
                            // إذا لم يوجد خيار افتراضي، أضفه في الأعلى
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


                        // إعادة تهيئة جميع القوائم المنسدلة لنوع الوثيقة في الصفحة بعد كل رفع
                        const allDocTypeSelects = document.querySelectorAll('select[id="document_type"], select.mainDocumentTypeSelect');
                        allDocTypeSelects.forEach(function(select) {
                            if (select.options.length > 0) {
                                let defaultIndex = 0;
                                for (let i = 0; i < select.options.length; i++) {
                                    if (select.options[i].value === '' || select.options[i].value === '0') {
                                        defaultIndex = i;
                                        break;
                                    }
                                }
                                if (select.selectedIndex !== defaultIndex) {
                                    select.selectedIndex = defaultIndex;
                                    if (typeof $ !== 'undefined') {
                                        $(select).trigger('change');
                                    } else if (typeof Event === 'function') {
                                        select.dispatchEvent(new Event('change'));
                                    }
                                }
                            } else {
                                select.selectedIndex = 0;
                            }
                        });
                        if (fileInput) {
                        // إعادة ضبط حقل رفع الملفات بشكل مضمون عبر استبداله بعنصر جديد دائماً
                        const newFileInput = fileInput.cloneNode(true);
                        fileInput.parentNode.replaceChild(newFileInput, fileInput);
                        // إعادة ربط الأحداث الخاصة بالمعاينة
                        $(newFileInput).off('change.keepPreview').on('change.keepPreview', function(e) {
                            if (window.lastPreviewImage) {
                                setTimeout(function() {
                                    $('#preview').removeClass('d-none');
                                    $('#preview img').attr('src', window.lastPreviewImage);
                                }, 50);
                            }
                        });
                        // تحديث المتغير fileInput ليشير إلى العنصر الجديد (إذا كان هناك متغير عام)
                        window.fileInput = newFileInput;
                        }

                        // إبقاء صورة المعاينة ظاهرة دائماً بعد رفع صورة ناجحة (لا يتم إخفاؤها أو مسحها عند تغيير القوائم)
                        // إذا تم اختيار صورة جديدة، لا يتم إخفاء صورة المعاينة السابقة إلا بعد رفعها بنجاح
                        if (file && file.type && file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                // فقط إذا تم رفع الصورة (وليس فقط اختيارها)، يتم تحديث المعاينة
                                window.lastPreviewImage = e.target.result;
                                $('#preview').removeClass('d-none');
                                $('#preview img').attr('src', e.target.result);
                                // طباعة تشخيصية
                                console.log('تم تحديث صورة المعاينة بعد رفع الصورة:', window.lastPreviewImage);
                            };
                            reader.readAsDataURL(file);
                        } else if (window.lastPreviewImage) {
                            // إذا لم يتم اختيار صورة جديدة، أبقِ الصورة السابقة
                            $('#preview').removeClass('d-none');
                            $('#preview img').attr('src', window.lastPreviewImage);
                        }


                        $('.is-invalid').removeClass('is-invalid');
                        $('.invalid-feedback').remove();

                        // منع إخفاء صورة المعاينة عند تغيير نوع الوثيقة أو الشخص
                        $(docTypeSelect).off('change.keepPreview').on('change.keepPreview', function() {
                            if (window.lastPreviewImage) {
                                $('#preview').removeClass('d-none');
                                $('#preview img').attr('src', window.lastPreviewImage);
                            }
                        });
                        $(personSelector).off('change.keepPreview').on('change.keepPreview', function() {
                            if (window.lastPreviewImage) {
                                $('#preview').removeClass('d-none');
                                $('#preview img').attr('src', window.lastPreviewImage);
                            }
                        });
                        // منع إخفاء صورة المعاينة عند تغيير ملف جديد (input[type=file])
                        $(fileInput).off('change.keepPreview').on('change.keepPreview', function(e) {
                            // إذا تم اختيار صورة جديدة ولم يتم رفعها بعد، لا تخفي الصورة السابقة
                            if (window.lastPreviewImage) {
                                setTimeout(function() {
                                    $('#preview').removeClass('d-none');
                                    $('#preview img').attr('src', window.lastPreviewImage);
                                }, 50);
                            }
                        });

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

            // تعريف دوال التحقق في النطاق الأعلى حتى تكون متاحة للجميع
            function validateBasicTab() {
                // ...existing code for validateBasicTab...
                function validateBasicTab() {
                    const password = document.querySelector('[name="user_password"]')?.value || '';
                    const passwordConfirm = document.querySelector('[name="user_password_confirmation"]')?.value || '';
                    if (
                        password.length === 4 &&
                        passwordConfirm.length === 4 &&
                        password !== passwordConfirm
                    ) {
                        Swal.fire({
                            icon: 'error',
                            title: 'تنبيه',
                            text: 'كلمة المرور وتأكيد كلمة المرور غير متطابقتين!'
                        });
                        return false;
                    }
                    // ...rest of the code...
                }
                const requiredFields = [
                    'data_section_id', 'data_id_number', 'user_password', 'user_password_confirmation',
                    'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name',
                    'data_relationship', 'data_birth_date', 'data_gender', 'data_phone_number',
                    'data_marital_status', 'data_displacement_status', 'data_current_address',
                    'data_city', 'data_province', 'data_health_status', 'data_employment_status_breadwinner',
                    'data_housing_status', 'data_current_housing_type','data_number_of_individuals'
                ];
                let missingFields = [];
                for (const name of requiredFields) {
                    const el = document.querySelector(`[name="${name}"]`);
                    if (el && !el.value) missingFields.push(name);
                }
                // تقليل تكرار السجلات: لا تسجل إلا إذا تغيرت الحقول الناقصة
                if (!window._lastMissingFields || JSON.stringify(window._lastMissingFields) !== JSON.stringify(missingFields)) {
                    if (missingFields.length > 0) {
                        console.warn('الحقول الناقصة:', missingFields);
                    }
                    window._lastMissingFields = missingFields;
                }
                if (missingFields.length > 0) {
                    return false;
                }
                let valid = true;
                let missingDocs = [];
                if (window.documentTypes) {
                    if (window.allDocs && window.allDocs instanceof Map) {
                        console.log('جميع الوثائق في allDocs:', Array.from(window.allDocs.entries()));
                    }
                    window.documentTypes.filter(dt => dt.basic_enabled && dt.basic_required).forEach(dt => {
                        let found = false;
                        // توحيد prefType مرة واحدة
                        const prefType = (dt.pref || '').toString().trim().toLowerCase();
                        if (window.allDocs && window.allDocs instanceof Map) {
                            window.allDocs.forEach(arr => {
                                arr.forEach(doc => {
                                    // توحيد التسمية مع تجاهل حالة الأحرف
                                    const docType = (doc.type || doc.docType || doc.documentType || '').toString().trim().toLowerCase();
                                    if (
                                        docType === prefType &&
                                        (
                                            doc.processedFile === true ||
                                            doc.processedFile === 1 ||
                                            ((doc.processedFile instanceof File || doc.processedFile instanceof Blob) && doc.status === 'completed')
                                        )
                                    ) {
                                        found = true;
                                    }
                                });
                            });
                        }
                        if (!found) {
                            valid = false;
                            // أضف الوصف أو pref الأصلي للعرض، لكن المقارنة دائمًا موحدة
                            missingDocs.push(dt.description || dt.pref);
                        }
                    });
                }
                if (missingDocs.length > 0) {
                    console.warn('الوثائق الإجبارية غير المرفوعة:', missingDocs);
                }
                return valid;
            }

            function validateDeceasedTab() {
                // ...existing code for validateDeceasedTab...
                let valid = true;
                let missingFields = [];
                const fatherFields = [
                    'father_first_name', 'father_last_name', 'father_id', 'father_death_date', 'father_death_reason'
                ];
                fatherFields.forEach(name => {
                    const el = document.querySelector(`[name="${name}"]`);
                    if (el && !el.value) missingFields.push(name);
                });
                const motherSection = document.getElementById('motherInfoSection');
                if (motherSection && motherSection.style.display !== 'none') {
                    const motherFields = [
                        'mother_first_name', 'mother_last_name', 'mother_id', 'mother_death_date', 'mother_death_reason'
                    ];
                    motherFields.forEach(name => {
                        const el = motherSection.querySelector(`[name="${name}"]`);
                        if (el && !el.value) missingFields.push(name);
                    });
                }
                if (missingFields.length > 0) {
                    valid = false;
                    console.warn('الحقول الإجبارية الناقصة (المتوفين):', missingFields);
                }
                let deceasedMissingDocs = [];
                if (window.documentTypes && window.allDocs && window.allDocs instanceof Map) {
                    const fatherIdInput = document.querySelector('input[name="father_id"]');
                    const fatherId = fatherIdInput ? fatherIdInput.value : null;
                    if (fatherId) {
                        window.documentTypes.filter(dt => dt.deceased_enabled && dt.deceased_required).forEach(dt => {
                            let found = false;
                            window.allDocs.forEach(arr => {
                                arr.forEach(doc => {
                                    const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                    if (
                                        docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                        (
                                            doc.processedFile === true ||
                                            doc.processedFile === 1 ||
                                            ((doc.processedFile instanceof File || doc.processedFile instanceof Blob) && doc.status === 'completed')
                                        ) &&
                                        (doc.personId === fatherId || doc.personKey === fatherId)
                                    ) {
                                        found = true;
                                    }
                                });
                            });
                            if (!found) {
                                valid = false;
                                deceasedMissingDocs.push(dt.pref + ' (الأب)');
                            }
                        });
                    }
                    const motherIdInput = motherSection ? motherSection.querySelector('input[name="mother_id"]') : null;
                    const motherId = motherIdInput ? motherIdInput.value : null;
                    if (motherSection && motherSection.style.display !== 'none' && motherId) {
                        window.documentTypes.filter(dt => dt.deceased_enabled && dt.deceased_required).forEach(dt => {
                            let found = false;
                            window.allDocs.forEach(arr => {
                                arr.forEach(doc => {
                                    const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                    if (
                                        docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                        (
                                            doc.processedFile === true ||
                                            doc.processedFile === 1 ||
                                            ((doc.processedFile instanceof File || doc.processedFile instanceof Blob) && doc.status === 'completed')
                                        ) &&
                                        (doc.personId === motherId || doc.personKey === motherId)
                                    ) {
                                        found = true;
                                    }
                                });
                            });
                            if (!found) {
                                valid = false;
                                deceasedMissingDocs.push(dt.pref + ' (الأم)');
                            }
                        });
                    }
                }
                if (deceasedMissingDocs.length > 0) {
                    console.warn('الوثائق الإجبارية غير المرفوعة (المتوفين):', deceasedMissingDocs);
                }
                return valid;
            }

            function validateFamilyTab() {
                // ...existing code for validateFamilyTab...
                let valid = true;
                document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                    const requiredFields = [
                        'first_name', 'last_name', 'person_id', 'person_birth_date', 'person_gender'
                    ];
                    for (const field of requiredFields) {
                        const el = form.querySelector(`[name*="[${field}]"]`);
                        if (el && !el.value) valid = false;
                    }
                    if (window.documentTypes) {
                        window.documentTypes.filter(dt => dt.family_enabled && dt.family_required).forEach(dt => {
                            let found = false;
                            if (window.allDocs && window.allDocs instanceof Map) {
                                window.allDocs.forEach(arr => {
                                    arr.forEach(doc => {
                                        const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                        if (
                                            docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                            doc.processedFile
                                        ) {
                                            found = true;
                                        }
                                    });
                                });
                            }
                            if (!found) valid = false;
                        });
                    }
                });
                return valid;
            }

            function updateTabStates() {
                // تعريف التبويبات حسب الترتيب
                const tabs = [
                    { id: 'basic', validate: validateBasicTab },
                    { id: 'deceased', validate: validateDeceasedTab },
                    { id: 'family-members', validate: validateFamilyTab },
                    { id: 'final', validate: function() { return true; } } // تبويب المعلومات المدخلة
                ];
                // القسم المختار
                const sectionInput = document.querySelector('[name="data_section_id"]');
                const sectionValue = sectionInput ? sectionInput.value : '';
                // إظهار/إخفاء تبويب المتوفين والأسرة حسب القسم
                let enableDeceased = true;
                let enableFamily = true;
                if (sectionValue !== 'orphans') {
                    // إذا لم يكن القسم أيتام، قد لا تظهر بعض التبويبات
                    enableDeceased = document.getElementById('deceased')?.style.display !== 'none';
                    enableFamily = document.getElementById('family-members')?.style.display !== 'none';
                }

                // منطق التفعيل التسلسلي: كل تبويب لا يُفعل إلا إذا اكتملت البوابة السابقة مباشرة
                // بوابة البيانات الأساسية دائمًا مفعلة
                let prevCompleted = true;
                for (let i = 0; i < tabs.length; i++) {
                    const tab = tabs[i];
                    const navBtn = document.querySelector(`#formTabs button[data-bs-target="#${tab.id}"]`);
                    if (!navBtn) continue;
                    // تحقق منطق إظهار التبويب
                    if ((tab.id === 'deceased' && !enableDeceased) || (tab.id === 'family-members' && !enableFamily)) {
                        navBtn.classList.add('d-none');
                        continue;
                    } else {
                        navBtn.classList.remove('d-none');
                    }
                    // بوابة البيانات الأساسية دائمًا مفعلة
                    if (tab.id === 'basic') {
                        navBtn.disabled = false;
                        navBtn.classList.remove('disabled');
                        navBtn.style.color = '#000';
                        navBtn.style.backgroundColor = '';
                        prevCompleted = validateBasicTab();
                        continue;
                    }
                    // بوابة المعلومات المدخلة (final) لا تُفعل إلا إذا اكتملت جميع البوابات السابقة
                    if (tab.id === 'final') {
                        // إذا جميع البوابات السابقة مكتملة (أي جميع validate ترجع true)
                        let allCompleted = true;
                        for (let j = 0; j < tabs.length - 1; j++) {
                            // فقط التبويبات الظاهرة
                            const t = tabs[j];
                            const btn = document.querySelector(`#formTabs button[data-bs-target="#${t.id}"]`);
                            if (btn && !btn.classList.contains('d-none')) {
                                if (!t.validate()) {
                                    allCompleted = false;
                                    break;
                                }
                            }
                        }
                        if (allCompleted) {
                            navBtn.disabled = false;
                            navBtn.classList.remove('disabled');
                            navBtn.style.color = '#000';
                            navBtn.style.backgroundColor = '';
                        } else {
                            navBtn.disabled = true;
                            navBtn.classList.add('disabled');
                            navBtn.style.color = '#aaa';
                            navBtn.style.backgroundColor = '#eee';
                        }
                        continue;
                    }
                    // التبويبات الأخرى: تُفعل فقط إذا اكتملت البوابة السابقة مباشرة (prevCompleted)
                    if (prevCompleted) {
                        navBtn.disabled = false;
                        navBtn.classList.remove('disabled');
                        navBtn.style.color = '#000';
                        navBtn.style.backgroundColor = '';
                        prevCompleted = tab.validate();
                    } else {
                        navBtn.disabled = true;
                        navBtn.classList.add('disabled');
                        navBtn.style.color = '#aaa';
                        navBtn.style.backgroundColor = '#eee';
                        prevCompleted = false;
                    }
                }
            }

            // استدعاء تحديث التبويبات عند تحميل الصفحة وأي تغيير

            // إضافة مراقبة الحدث invalid على مستوى المستند لإبراز الحقل وتوجيه المستخدم
            document.addEventListener('invalid', function(e) {
                e.preventDefault();
                if (typeof highlightInvalidField === 'function') {
                    highlightInvalidField(e.target);
                } else {
                    // نسخة احتياطية إذا لم يتم تحميل الدالة
                    var input = e.target;
                    input.style.border = '2px solid red';
                    setTimeout(() => { input.focus && input.focus(); }, 200);
                }
            }, true);

            $(document).ready(function() {
                updateTabStates();
                // عند تغيير أي حقل أو مرفق
                $(document).on('input change', 'input, select, textarea', function() {
                    patchAllDocsProcessedFile();
                    updateTabStates();
                });
                // عند تغيير المرفقات (يفترض أن window.allDocs تتغير)
                if (window.allDocs && window.allDocs instanceof Map) {
                    const origSet = window.allDocs.set;
                    window.allDocs.set = function() {
                        const res = origSet.apply(this, arguments);
                        patchAllDocsProcessedFile();
                        updateTabStates();
                        return res;
                    };
                }

                // تحديد حقول كلمة المرور
                const passwordInput = document.querySelector('input[name="user_password"]');
                const passwordConfirmInput = document.querySelector('input[name="user_password_confirmation"]');

                // منع إدخال غير الأرقام وتحديد الطول بـ 4 فقط
                [passwordInput, passwordConfirmInput].forEach(function(input) {
                    if (!input) return;
                    input.setAttribute('maxlength', '4');
                    input.setAttribute('inputmode', 'numeric');
                    input.setAttribute('pattern', '\\d{4}');
                    input.addEventListener('input', function(e) {
                        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
                    });
                });

                // منع الخروج من أي بوابة دون تعبئة البيانات والوثائق الإجبارية باستخدام حدث show.bs.tab (الأكثر أمانًا)
                $('#formTabs button[data-bs-toggle="tab"]').on('show.bs.tab', function(e) {
                    // لا تجعل الدالة async حتى لا يتجاهل Bootstrap المنع
                    updateTabStates(); // تحديث حالة التبويبات عند محاولة الانتقال
                    // تحقق من تكرار رقم الهوية في النموذج أو قاعدة البيانات
                    if (typeof validateAllIds === 'function') {
                        // السماح دائماً بالانتقال إلى البوابة الرئيسية بدون تحقق
                        const target = $(e.target).attr('data-bs-target');
                        if (target === '#basic') {
                            return true;
                        }
                        // استخدم then/catch بدلاً من await
                        // فقط أوقف الانتقال إذا كان هناك خطأ فعلي
                        if (e.target._pendingValidation) return false;
                        e.target._pendingValidation = true;
                        validateAllIds().then(function(valid) {
                            e.target._pendingValidation = false;
                            if (!valid) {
                                e.preventDefault && e.preventDefault();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'تنبيه',
                                    text: 'لا يمكنك المتابعة بسبب تكرار رقم الهوية في النموذج أو وجوده في قاعدة البيانات.'
                                });
                            } else {
                                // فعّل التبويب يدوياً فقط إذا كان التحقق ناجحاً
                                var tab = new bootstrap.Tab(e.target);
                                tab.show();
                            }
                        }).catch(function() {
                            e.target._pendingValidation = false;
                        });
                        // لا تمنع الانتقال إلا إذا كان هناك خطأ فعلي (سيتم منعه داخل then)
                        // return false;
                    }
                    const target = $(e.target).attr('data-bs-target');
                    const currentActive = document.querySelector('#formTabs .nav-link.active');
                    const currentTarget = currentActive ? currentActive.getAttribute('data-bs-target') : null;

                    const password = document.querySelector('[name="user_password"]')?.value || '';
                    const passwordConfirm = document.querySelector('[name="user_password_confirmation"]')?.value || '';
                    if (
                        password.length === 4 &&
                        passwordConfirm.length === 4 &&
                        password !== passwordConfirm
                    ) {
                        e.preventDefault && e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'تنبيه',
                            text: 'كلمة المرور وتأكيد كلمة المرور غير متطابقتين!'
                        });
                        return false;
                    }

                    function validateBasicTab() {
                        const requiredFields = [
                            'data_section_id', 'data_id_number', 'user_password', 'user_password_confirmation',
                            'data_first_name', 'data_father_name', 'data_grand_father_name', 'data_family_name',
                            'data_relationship', 'data_birth_date', 'data_gender', 'data_phone_number',
                            'data_marital_status', 'data_displacement_status', 'data_current_address',
                            'data_city', 'data_province', 'data_health_status', 'data_employment_status_breadwinner',
                            'data_housing_status', 'data_current_housing_type'
                        ];
                        let missingFields = [];
                        for (const name of requiredFields) {
                            const el = document.querySelector(`[name="${name}"]`);
                            if (el && !el.value) missingFields.push(name);
                        }
                        if (missingFields.length > 0) {
                            console.warn('الحقول الناقصة:', missingFields);
                            return false;
                        }
                        let valid = true;
                        let missingDocs = [];
                        if (window.documentTypes) {
                            // تحقق من window.allDocs (المصفوفة المركزية)
                            window.documentTypes.filter(dt => dt.basic_enabled && dt.basic_required).forEach(dt => {
                        let found = false;
                        if (window.allDocs && window.allDocs instanceof Map) {
                            window.allDocs.forEach(arr => {
                                arr.forEach(doc => {
                                    const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                    const prefType = (dt.pref || '').toString().trim().toLowerCase();
                                    if (
                                        docType === prefType &&
                                        doc.processedFile
                                    ) {
                                        found = true;
                                    }
                                });
                            });
                        }
                        if (!found) {
                            valid = false;
                            missingDocs.push(dt.pref);
                        }
                            });
                        }
                        if (missingDocs.length > 0) {
                            console.warn('الوثائق الإجبارية غير المرفوعة:', missingDocs);
                        }
                        return valid;
                    }
                    function validateDeceasedTab() {
                        let valid = true;
                        let missingFields = [];
                        // تحقق من الحقول الإجبارية للأب
                        const fatherFields = [
                            'father_first_name', 'father_last_name', 'father_id', 'father_death_date', 'father_death_reason'
                        ];
                        fatherFields.forEach(name => {
                            const el = document.querySelector(`[name="${name}"]`);
                            if (el && !el.value) missingFields.push(name);
                        });
                        // تحقق من الحقول الإجبارية للأم إذا كانت ظاهرة
                        const motherSection = document.getElementById('motherInfoSection');
                        if (motherSection && motherSection.style.display !== 'none') {
                            const motherFields = [
                                'mother_first_name', 'mother_last_name', 'mother_id', 'mother_death_date', 'mother_death_reason'
                            ];
                            motherFields.forEach(name => {
                                const el = motherSection.querySelector(`[name="${name}"]`);
                                if (el && !el.value) missingFields.push(name);
                            });
                        }
                        if (missingFields.length > 0) {
                            valid = false;
                            console.warn('الحقول الإجبارية الناقصة (المتوفين):', missingFields);
                        }
                        // تحقق من الوثائق الإجبارية (من window.allDocs) لكل متوفى (أب/أم)
                        let deceasedMissingDocs = [];
                        if (window.documentTypes && window.allDocs && window.allDocs instanceof Map) {
                            // تحقق للأب
                            const fatherIdInput = document.querySelector('input[name="father_id"]');
                            const fatherId = fatherIdInput ? fatherIdInput.value : null;
                            if (fatherId) {
                                window.documentTypes.filter(dt => dt.deceased_enabled && dt.deceased_required).forEach(dt => {
                                    let found = false;
                                    window.allDocs.forEach(arr => {
                                        arr.forEach(doc => {
                                            const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                            if (
                                                docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                                doc.processedFile &&
                                                (doc.personId === fatherId || doc.personKey === fatherId)
                                            ) {
                                                found = true;
                                            }
                                        });
                                    });
                                    if (!found) {
                                        valid = false;
                                        deceasedMissingDocs.push(dt.pref + ' (الأب)');
                                    }
                                });
                            }
                            // تحقق للأم إذا كانت ظاهرة
                            const motherIdInput = motherSection ? motherSection.querySelector('input[name="mother_id"]') : null;
                            const motherId = motherIdInput ? motherIdInput.value : null;
                            if (motherSection && motherSection.style.display !== 'none' && motherId) {
                                window.documentTypes.filter(dt => dt.deceased_enabled && dt.deceased_required).forEach(dt => {
                                    let found = false;
                                    window.allDocs.forEach(arr => {
                                        arr.forEach(doc => {
                                            const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                            if (
                                                docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                                doc.processedFile &&
                                                (doc.personId === motherId || doc.personKey === motherId)
                                            ) {
                                                found = true;
                                            }
                                        });
                                    });
                                    if (!found) {
                                        valid = false;
                                        deceasedMissingDocs.push(dt.pref + ' (الأم)');
                                    }
                                });
                            }
                        }
                        if (deceasedMissingDocs.length > 0) {
                            console.warn('الوثائق الإجبارية غير المرفوعة (المتوفين):', deceasedMissingDocs);
                        }
                        return valid;
                    }
                    function validateFamilyTab() {
                        let valid = true;
                        document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                            const requiredFields = [
                                'first_name', 'last_name', 'person_id', 'person_birth_date', 'person_gender'
                            ];
                            for (const field of requiredFields) {
                                const el = form.querySelector(`[name*="[${field}]"]`);
                                if (el && !el.value) valid = false;
                            }
                            if (window.documentTypes) {
                                window.documentTypes.filter(dt => dt.family_enabled && dt.family_required).forEach(dt => {
                                    let found = false;
                                    if (window.allDocs && window.allDocs instanceof Map) {
                                        window.allDocs.forEach(arr => {
                                            arr.forEach(doc => {
                                                const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                                if (
                                                    docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                                    doc.processedFile
                                                ) {
                                                    found = true;
                                                }
                                            });
                                        });
                                    }
                                    if (!found) valid = false;
                                });
                            }
                        });
                        return valid;
                    }

                    // منع الخروج من "البيانات الأساسية" لأي تبويب آخر إلا بعد الإكمال
                    // منع تخطي بوابة البيانات الأساسية إذا كانت معروضة
                    const basicTab = document.getElementById('basic');
                    if (basicTab && basicTab.style.display !== 'none' && currentTarget === '#basic' && target !== '#basic') {
                        let missingFields = [];
                        let missingDocs = [];
                        const fieldLabels = {
                            data_section_id: 'القسم',
                            data_id_number: 'رقم الهوية',
                            user_password: 'كلمة المرور',
                            user_password_confirmation: 'تأكيد كلمة المرور',
                            data_first_name: 'الاسم الأول',
                            data_father_name: 'اسم الأب',
                            data_grand_father_name: 'اسم الجد',
                            data_family_name: 'اسم العائلة',
                            data_relationship: 'صلة القرابة',
                            data_birth_date: 'تاريخ الميلاد',
                            data_gender: 'الجنس',
                            data_phone_number: 'رقم الجوال',
                            data_marital_status: 'الحالة الاجتماعية',
                            data_displacement_status: 'حالة النزوح',
                            data_current_address: 'العنوان الحالي',
                            data_city: 'المدينة',
                            data_province: 'المحافظة',
                            data_health_status: 'الحالة الصحية',
                            data_employment_status_breadwinner: 'حالة العمل (معيل)',
                            data_housing_status: 'حالة السكن',
                            data_current_housing_type: 'نوع السكن الحالي'
                        };
                        const requiredFields = Object.keys(fieldLabels);
                        for (const name of requiredFields) {
                            const el = document.querySelector(`[name="${name}"]`);
                            if (el && !el.value) {
                                missingFields.push(fieldLabels[name] || name);
                                if (el) el.style.border = '2px solid red';
                            } else if (el) {
                                el.style.border = '';
                            }
                        }
                        if (window.documentTypes) {
                            window.documentTypes.filter(dt => dt.basic_enabled && dt.basic_required).forEach(dt => {
                                let found = false;
                                if (window.allDocs && window.allDocs instanceof Map) {
                                    window.allDocs.forEach(arr => {
                                        arr.forEach(doc => {
                                            const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                            if (
                                                docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                                doc.processedFile
                                            ) {
                                                found = true;
                                            }
                                        });
                                    });
                                }
                                if (!found) missingDocs.push(dt.description || dt.pref);
                            });
                        }
                        if (missingFields.length > 0 || missingDocs.length > 0) {
                            e.preventDefault();
                            let html = '';
                            if (missingFields.length > 0) {
                                html += '<b>الحقول الناقصة:</b><ul style="text-align:right;direction:rtl;">' + missingFields.map(f => '<li>' + f + '</li>').join('') + '</ul>';
                            }
                            if (missingDocs.length > 0) {
                                html += '<b>الوثائق الإجبارية غير المرفوعة:</b><ul style="text-align:right;direction:rtl;">' + missingDocs.map(f => '<li>' + f + '</li>').join('') + '</ul>';
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'تنبيه',
                                html: html
                            });
                            console.warn('الحقول الناقصة:', missingFields);
                            console.warn('الوثائق الإجبارية غير المرفوعة:', missingDocs);
                            return false;
                        }
                    }
                    // منع الخروج من "المتوفين" لأي تبويب آخر إلا بعد الإكمال
                    // منع تخطي بوابة المتوفين إذا كانت معروضة
                    const deceasedTab = document.getElementById('deceased');
                    if (deceasedTab && deceasedTab.style.display !== 'none' && currentTarget === '#deceased' && target !== '#deceased') {
                        let missingFields = [];
                        let deceasedMissingDocs = [];
                        // تعريف labels لحقول المتوفين
                        const deceasedFieldLabels = {
                            father_first_name: 'اسم الأب (الأول)',
                            father_last_name: 'اسم الأب (العائلة)',
                            father_id: 'رقم هوية الأب',
                            father_death_date: 'تاريخ وفاة الأب',
                            father_death_reason: 'سبب وفاة الأب',
                            mother_first_name: 'اسم الأم (الأول)',
                            mother_last_name: 'اسم الأم (العائلة)',
                            mother_id: 'رقم هوية الأم',
                            mother_death_date: 'تاريخ وفاة الأم',
                            mother_death_reason: 'سبب وفاة الأم'
                        };
                        // الأب
                        const fatherFields = [
                            'father_first_name', 'father_last_name', 'father_id', 'father_death_date', 'father_death_reason'
                        ];
                        fatherFields.forEach(name => {
                            const el = document.querySelector(`[name="${name}"]`);
                            if (el && !el.value) missingFields.push(deceasedFieldLabels[name] || name);
                        });
                        // الأم
                        const motherSection = document.getElementById('motherInfoSection');
                        if (motherSection && motherSection.style.display !== 'none') {
                            const motherFields = [
                                'mother_first_name', 'mother_last_name', 'mother_id', 'mother_death_date', 'mother_death_reason'
                            ];
                            motherFields.forEach(name => {
                                const el = motherSection.querySelector(`[name="${name}"]`);
                                if (el && !el.value) missingFields.push(deceasedFieldLabels[name] || name);
                            });
                        }
                        // الوثائق
                        if (window.documentTypes && window.allDocs && window.allDocs instanceof Map) {
                            // الأب
                            const fatherIdInput = document.querySelector('input[name="father_id"]');
                            const fatherId = fatherIdInput ? fatherIdInput.value : null;
                            if (fatherId) {
                                window.documentTypes.filter(dt => dt.deceased_enabled && dt.deceased_required).forEach(dt => {
                                    let found = false;
                                    window.allDocs.forEach(arr => {
                                        arr.forEach(doc => {
                                            const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                            if (
                                                docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                                doc.processedFile &&
                                                (doc.personId === fatherId || doc.personKey === fatherId)
                                            ) {
                                                found = true;
                                            }
                                        });
                                    });
                                    if (!found) deceasedMissingDocs.push((dt.description || dt.pref) + ' (الأب)');
                                });
                            }
                            // الأم
                            const motherIdInput = motherSection ? motherSection.querySelector('input[name="mother_id"]') : null;
                            const motherId = motherIdInput ? motherIdInput.value : null;
                            if (motherSection && motherSection.style.display !== 'none' && motherId) {
                                window.documentTypes.filter(dt => dt.deceased_enabled && dt.deceased_required).forEach(dt => {
                                    let found = false;
                                    window.allDocs.forEach(arr => {
                                        arr.forEach(doc => {
                                            const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                            if (
                                                docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                                doc.processedFile &&
                                                (doc.personId === motherId || doc.personKey === motherId)
                                            ) {
                                                found = true;
                                            }
                                        });
                                    });
                                    if (!found) deceasedMissingDocs.push((dt.description || dt.pref) + ' (الأم)');
                                });
                            }
                        }
                        if (missingFields.length > 0 || deceasedMissingDocs.length > 0) {
                            e.preventDefault();
                            let html = '';
                            if (missingFields.length > 0) {
                                html += '<b>الحقول الناقصة:</b><ul style="text-align:right;direction:rtl;">' + missingFields.map(f => '<li>' + f + '</li>').join('') + '</ul>';
                            }
                            if (deceasedMissingDocs.length > 0) {
                                html += '<b>الوثائق الإجبارية غير المرفوعة:</b><ul style="text-align:right;direction:rtl;">' + deceasedMissingDocs.map(f => '<li>' + f + '</li>').join('') + '</ul>';
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'تنبيه',
                                html: html
                            });
                            console.warn('الحقول الناقصة (المتوفين):', missingFields);
                            console.warn('الوثائق الإجبارية غير المرفوعة (المتوفين):', deceasedMissingDocs);
                            return false;
                        }
                    }
                    // منع الخروج من "أفراد الأسرة" لأي تبويب آخر إلا بعد الإكمال
                    // منع تخطي بوابة الأسرة إذا كان هناك حقول مولدة أو إذا كان القسم أيتام ولم تتم إضافة فرد واحد على الأقل
                    const familyTab = document.getElementById('family-members');
                    const sectionInput = document.querySelector('[name="data_section_id"]');
                    const sectionValue = sectionInput ? sectionInput.value : '';
                    if (familyTab && familyTab.style.display !== 'none' && currentTarget === '#family-members' && target !== '#family-members') {
                        let missingFields = [];
                        let missingDocs = [];
                        const familyFieldLabels = {
                            first_name: 'الاسم الأول',
                            last_name: 'اسم العائلة',
                            person_id: 'رقم هوية الفرد',
                            person_birth_date: 'تاريخ الميلاد',
                            person_gender: 'الجنس'
                        };
                        const familyForms = document.querySelectorAll('.family-member-form:not(.d-none)');
                        // شرط الأيتام: لا يمكن تخطي البوابة إلا إذا أضيف فرد واحد على الأقل
                        if (sectionValue === 'orphans' && familyForms.length === 0) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'تنبيه',
                                html: '<b>يجب إضافة فرد واحد على الأقل في بوابة أفراد الأسرة (للقسم: الأيتام)</b>'
                            });
                            return false;
                        }
                        // إذا كان هناك حقول مولدة (نموذج واحد على الأقل)
                        if (familyForms.length > 0) {
                            familyForms.forEach(function(form) {
                                const requiredFields = [
                                    'first_name', 'last_name', 'person_id', 'person_birth_date', 'person_gender'
                                ];
                                let personIdInput = form.querySelector('input[name*="[person_id]"]');
                                let personId = personIdInput ? personIdInput.value : null;
                                for (const field of requiredFields) {
                                    const el = form.querySelector(`[name*="[${field}]"]`);
                                    if (el && !el.value) {
                                        missingFields.push(familyFieldLabels[field] || field);
                                        if (el) el.style.border = '2px solid red';
                                    } else if (el) {
                                        el.style.border = '';
                                    }
                                }
                                if (window.documentTypes && personId) {
                                    window.documentTypes.filter(dt => dt.family_enabled && dt.family_required).forEach(dt => {
                                        let found = false;
                                        if (window.allDocs && window.allDocs instanceof Map) {
                                            window.allDocs.forEach(arr => {
                                                arr.forEach(doc => {
                                                    const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                                                    if (
                                                        docType === (dt.pref || '').toString().trim().toLowerCase() &&
                                                        doc.processedFile &&
                                                        (doc.personId === personId || doc.personKey === personId)
                                                    ) {
                                                        found = true;
                                                    }
                                                });
                                            });
                                        }
                                        if (!found) missingDocs.push(dt.description || dt.pref);
                                    });
                                }
                            });
                            if (missingFields.length > 0 || missingDocs.length > 0) {
                                e.preventDefault();
                                let html = '';
                                if (missingFields.length > 0) {
                                    html += '<b>الحقول الناقصة:</b><ul style="text-align:right;direction:rtl;">' + missingFields.map(f => '<li>' + f + '</li>').join('') + '</ul>';
                                }
                                if (missingDocs.length > 0) {
                                    html += '<b>الوثائق الإجبارية غير المرفوعة:</b><ul style="text-align:right;direction:rtl;">' + missingDocs.map(f => '<li>' + f + '</li>').join('') + '</ul>';
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'تنبيه',
                                    html: html
                                });
                                console.warn('الحقول الناقصة (أفراد الأسرة):', missingFields);
                                console.warn('الوثائق الإجبارية غير المرفوعة (أفراد الأسرة):', missingDocs);
                                return false;
                            }
                        }
                    }
                });
            });
        });
    </script>
<script src="/js/highlightInvalidField.js"></script>
@endpush
