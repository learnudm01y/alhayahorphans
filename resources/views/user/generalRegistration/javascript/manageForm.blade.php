@push('scriptsCodeUserRegistration')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    {{-- نظام كشف الجهاز وتوجيه مستعرض الصور - مأخوذ من  --}}
        @include('user.generalRegistration.javascript.deviceTypeOpenButton')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.querySelector('input[name="user_password"]');
            const screenshotBtn = document.getElementById('screenshotPasswordBtn');
            if (screenshotBtn && passwordInput) {
                screenshotBtn.addEventListener('click', function() {
                    html2canvas(passwordInput.closest('.position-relative')).then(function(canvas) {
                        const link = document.createElement('a');
                        link.download = 'password_screenshot.png';
                        link.href = canvas.toDataURL();
                        link.click();
                    });
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.querySelector('input[name="user_password"]');
            const passwordConfirmInput = document.querySelector('input[name="user_password_confirmation"]');
            const screenshotBtn = document.getElementById('screenshotPasswordBtn');
            const pulseHint = document.querySelector('.screenshot-hint');
            let pulseActive = false;

            function checkPasswordMatch() {
                const pass = passwordInput.value;
                const passConfirm = passwordConfirmInput.value;
                if (/^\d{4}$/.test(pass) && pass === passConfirm) {
                    if (!pulseActive) {
                        screenshotBtn.classList.add('pulse-active');
                        if (pulseHint) pulseHint.classList.remove('d-none');
                        pulseActive = true;
                    }
                } else {
                    screenshotBtn.classList.remove('pulse-active');
                    if (pulseHint) pulseHint.classList.add('d-none');
                    pulseActive = false;
                }
            }

            if (passwordInput && passwordConfirmInput && screenshotBtn) {
                passwordInput.addEventListener('input', checkPasswordMatch);
                passwordConfirmInput.addEventListener('input', checkPasswordMatch);
                // عند الضغط على الكاميرا أخفِ النبض والتلميح
                screenshotBtn.addEventListener('click', function() {
                    screenshotBtn.classList.remove('pulse-active');
                    if (pulseHint) pulseHint.classList.add('d-none');
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // دعم جميع البوابات التي تحتوي على نفس معرفات رفع الملفات
            const docTypeSelects = document.querySelectorAll('#mainDocumentTypeSelect');
            const fileInputs = document.querySelectorAll('#mainDocumentFileInput');
            const previews = document.querySelectorAll('#mainDocumentPreview');

            docTypeSelects.forEach(function(docTypeSelect, idx) {
                const fileInput = fileInputs[idx];
                const preview = previews[idx];
                let documents = [];
                let fileDialogOpen = false;

                function canUploadDocument(triggeredBy) {
                    const parent = docTypeSelect.closest('.row, .card, form') || document;
                    // ابحث عن رقم الهوية المناسب حسب البوابة
                    let idInput =
                        parent.querySelector('input[name^="family_members["][name$="[person_id]"]') ||
                        parent.querySelector('input[name="member_id"]') ||
                        parent.querySelector('input[name="data_id_number"]') ||
                        parent.querySelector('input[name="father_id"]') ||
                        parent.querySelector('input[name="mother_id"]') ||
                        document.getElementById('data_id_number') ||
                        document.querySelector('input[name="data_id_number"]') ||
                        document.querySelector('input[name="father_id"]') ||
                        document.querySelector('input[name="mother_id"]') ||
                        document.querySelector('input[name="member_id"]') ||
                        document.querySelector('input[name^="family_members["][name$="[person_id]"]');
                    // تحقق من أن رقم الهوية للأب أو الأم في بوابة المتوفين موجود
                    const isDeceasedFather = !!parent.querySelector('input[name="father_id"]');
                    const isDeceasedMother = !!parent.querySelector('input[name="mother_id"]');
                    if (isDeceasedFather && !parent.querySelector('input[name="father_id"]').value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال رقم هوية الأب المتوفى أولاً قبل رفع أي وثيقة.'
                        });
                        return false;
                    }
                    if (isDeceasedMother && !parent.querySelector('input[name="mother_id"]').value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال رقم هوية الأم المتوفية أولاً قبل رفع أي وثيقة.'
                        });
                        return false;
                    }
                    if (triggeredBy === 'mousedown') {
                        if (!idInput || !idInput.value) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'تنبيه',
                                text: 'يرجى إدخال رقم الهوية أولاً قبل رفع أي وثيقة.'
                            });
                            return false;
                        }
                        return true;
                    }
                    if (!docTypeSelect.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى اختيار نوع الوثيقة أولاً.'
                        });
                        return false;
                    }
                    if (!idInput || !idInput.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال رقم الهوية أولاً قبل رفع أي وثيقة.'
                        });
                        return false;
                    }
                    return true;
                }

                function renderDocuments() {
                    preview.innerHTML = '';
                    const cardsWrapper = document.createElement('div');
                    cardsWrapper.className = 'd-flex flex-wrap gap-2';
                    documents.forEach((doc, idx) => {
                        const card = document.createElement('div');
                        card.className = 'card mb-2';
                        card.style.width = '160px';
                        card.style.verticalAlign = 'top';
                        const cardBody = document.createElement('div');
                        cardBody.className = 'card-body p-2 text-center';
                        const docType = document.createElement('div');
                        docType.className = 'fw-bold mb-1';
                        docType.textContent = doc.typeText;
                        cardBody.appendChild(docType);
                        const docNameDiv = document.createElement('div');
                        docNameDiv.className = 'small text-muted mb-1';
                        docNameDiv.textContent = doc.docName;
                        cardBody.appendChild(docNameDiv);
                        if (doc.file.type.startsWith('image/')) {
                            const img = document.createElement('img');
                            img.src = URL.createObjectURL(doc.file);
                            img.style.maxWidth = '100px';
                            img.style.maxHeight = '100px';
                            img.className = 'rounded border mb-1';
                            img.onload = function() {
                                URL.revokeObjectURL(img.src);
                            };
                            cardBody.appendChild(img);
                        } else {
                            const span = document.createElement('span');
                            span.textContent = doc.file.name;
                            cardBody.appendChild(span);
                        }
                        // زر حذف الوثيقة
                        const delBtn = document.createElement('button');
                        delBtn.type = 'button';
                        delBtn.className = 'btn btn-danger btn-sm mt-2';
                        delBtn.textContent = 'حذف';
                        delBtn.onclick = function() {
                            Swal.fire({
                                title: 'تأكيد الحذف',
                                text: 'هل أنت متأكد أنك تريد حذف هذه الوثيقة؟',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'نعم، احذف',
                                cancelButtonText: 'إلغاء'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    card.style.transition =
                                        'filter 0.7s, opacity 0.7s, transform 0.7s, box-shadow 0.7s';
                                    card.style.filter =
                                        'blur(7px) grayscale(0.7) contrast(1.5) drop-shadow(0 0 12px #b3e5fc)';
                                    card.style.opacity = '0';
                                    card.style.transform =
                                        'scale(0.85) rotateZ(8deg) translateX(100px) skewY(6deg)';
                                    card.style.boxShadow =
                                        '0 0 60px 0 #00bcd44d, 0 0 0 2px #fff8';
                                    setTimeout(function() {
                                        // حذف من مصفوفة documents المحلية
                                        documents.splice(idx, 1);
                                        // حذف من window.allDocs أيضاً بشكل صحيح
                                        if (window.allDocs && window
                                            .allDocs instanceof Map) {
                                            const personKey = doc.personId ||
                                                doc.personKey || 'main';
                                            if (window.allDocs.has(personKey)) {
                                                const arr = window.allDocs.get(
                                                    personKey);
                                                // حذف كل العناصر المطابقة لنفس docName وtype وfileId
                                                for (let i = arr.length -
                                                        1; i >= 0; i--) {
                                                    if (
                                                        arr[i].docName === doc
                                                        .docName &&
                                                        arr[i].type === doc
                                                        .type &&
                                                        arr[i].fileId === doc
                                                        .fileId
                                                    ) {
                                                        arr.splice(i, 1);
                                                    }
                                                }
                                                if (arr.length === 0) window
                                                    .allDocs.delete(personKey);
                                            }
                                        }
                                        renderDocuments();
                                        // تحديث واجهة عرض المعلومات إذا كانت الدالة موجودة
                                        if (typeof window
                                            .renderReviewContent === 'function'
                                        ) {
                                            window.renderReviewContent();
                                        }
                                    }, 700);
                                }
                            });
                        };
                        cardBody.appendChild(delBtn);
                        card.appendChild(cardBody);
                        cardsWrapper.appendChild(card);
                    });
                    preview.appendChild(cardsWrapper);
                }

                docTypeSelect.addEventListener('mousedown', function(e) {
                    if (!canUploadDocument('mousedown') || fileDialogOpen) {
                        e.preventDefault();
                    }
                });

                docTypeSelect.addEventListener('change', function() {
                    if (!canUploadDocument('change')) {
                        this.value = '';
                        return;
                    }
                    if (fileDialogOpen) return;
                    fileDialogOpen = true;
                    fileInput.value = '';
                    const resetDialog = () => {
                        fileDialogOpen = false;
                        fileInput.removeEventListener('blur', resetDialog);
                    };
                    fileInput.addEventListener('blur', resetDialog);
                    fileInput.click();
                });

                fileInput.addEventListener('click', function() {});

                fileInput.addEventListener('change', function() {
                    if (!canUploadDocument('file')) {
                        fileInput.value = '';
                        fileDialogOpen = false;
                        return;
                    }
                    fileDialogOpen = false;
                    if (this.files && this.files[0]) {
                        const parent = docTypeSelect.closest('.row, .card, form') || document;
                        let idInput =
                            parent.querySelector(
                                'input[name^="family_members["][name$="[person_id]"]') ||
                            parent.querySelector('input[name="member_id"]') ||
                            parent.querySelector('input[name="data_id_number"]') ||
                            parent.querySelector('input[name="father_id"]') ||
                            parent.querySelector('input[name="mother_id"]') ||
                            document.getElementById('data_id_number') ||
                            document.querySelector('input[name="data_id_number"]') ||
                            document.querySelector('input[name="father_id"]') ||
                            document.querySelector('input[name="mother_id"]') ||
                            document.querySelector('input[name="member_id"]') ||
                            document.querySelector(
                                'input[name^="family_members["][name$="[person_id]"]');
                        const filesArr = Array.from(this.files);
                        const typeText = docTypeSelect.options[docTypeSelect.selectedIndex].text;
                        const typeVal = docTypeSelect.value;
                        window.lastSelectedDocType = typeVal;
                        const fileIdInput = parent.querySelector('input[name="file_id_number"]') ||
                            document.querySelector('input[name="file_id_number"]');
                        const fileId = fileIdInput ? fileIdInput.value : '';
                        const personId = idInput ? idInput.value : '';
                        if (!fileId || fileId === 'undefined') return;
                        const personKey = personId || (parent.querySelector('[data-upload-zone]')
                            ?.getAttribute('data-upload-zone') || 'main');
                        // طباعة جميع المفاتيح في allDocs بعد كل عملية رفع
                        if (window.allDocs && window.allDocs instanceof Map) {
                            const allKeys = Array.from(window.allDocs.keys());
                            console.log('[DEBUG] جميع مفاتيح allDocs الحالية:', allKeys);
                        }
                        console.log('[DEBUG] personKey عند رفع الملف:', personKey, {
                            personId,
                            dataUploadZone: parent.querySelector('[data-upload-zone]')
                                ?.getAttribute('data-upload-zone')
                        });
                        filesArr.forEach(file => {
                            // تأكد من تمرير نوع الوثيقة الصحيح (pref)
                            const docName =
                                `${typeVal}_${fileId}_${personId}${file.name.substring(file.name.lastIndexOf('.'))}`;
                            if (file.type.startsWith('image/')) {
                                if (typeof window.showCropperModal === 'function') {
                                    window.showCropperModal(file, function(croppedFile) {
                                        if (!croppedFile || !fileId || !personId)
                                            return;
                                        if (croppedFile.name && croppedFile.name
                                            .includes('_cropped') && croppedFile
                                            .name !== 'undefined') {
                                            const docObj = {
                                                type: typeVal,
                                                typeText: typeText,
                                                file: croppedFile,
                                                processedFile: croppedFile, // تعيين processedFile
                                                docName: docName,
                                                personId: personId,
                                                fileId: fileId
                                            };
                                            let docsArr = window.allDocs.get(
                                                personKey) || [];
                                            docsArr.push(docObj);
                                            window.allDocs.set(personKey, docsArr);
                                            documents.push(docObj);
                                            renderDocuments();
                                            // عرض حي لكل عملية إضافة
                                            console.log('🟡 إضافة ملف مقصوص:',
                                                docObj);
                                            console.log(
                                                '🟢 محتوى allDocs بعد إضافة صورة مقصوصة:',
                                                window.allDocs);
                                        }
                                        docTypeSelect.value = '';
                                    });
                                }
                            } else {
                                if (!fileId || !personId) return;
                                const docObj = {
                                    type: typeVal,
                                    typeText: typeText,
                                    file: file,
                                    processedFile: file, // تعيين processedFile
                                    docName: docName,
                                    personId: personId,
                                    fileId: fileId
                                };
                                let docsArr = window.allDocs.get(personKey) || [];
                                docsArr.push(docObj);
                                window.allDocs.set(personKey, docsArr);
                                documents.push(docObj);
                                // عرض حي لكل عملية إضافة
                                console.log('🟡 إضافة ملف:', docObj);
                                renderDocuments();
                                docTypeSelect.value = '';
                            }
                        });
                        // عرض حي لجميع العمليات بعد كل رفع
                        console.log('🟢 جميع المرفقات الحالية:', Array.from(window.allDocs
                            .entries()));
                    }
                });
            });
        });
    </script>

    {{-- cropper modal --}}
    @include('user.generalRegistration.javascript.cropper')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // دعم جميع بوابات أفراد الأسرة بشكل ديناميكي
            // إزالة تعريف setupDocumentUploadHandlersForMember من هنا لأنه موجود في familyMember.blade.php

            // تفعيل رفع الملفات لكل فرد حالي عند تحميل الصفحة
            document.querySelectorAll('.family-member-form').forEach((form, idx) => {
                // حل مبتكر: اجعل كل نموذج يأخذ data-upload-zone فريد إذا لم يكن موجوداً
                let dataUploadZone = form.getAttribute('data-upload-zone');
                if (!dataUploadZone) {
                    // إذا كان هناك نموذج آخر بنفس data-upload-zone، أعطه index جديد
                    let usedZones = Array.from(document.querySelectorAll('.family-member-form'))
                        .map(f => f.getAttribute('data-upload-zone'))
                        .filter(Boolean);
                    let newZone = `family_${idx}`;
                    let tryIdx = idx;
                    while (usedZones.includes(newZone)) {
                        tryIdx++;
                        newZone = `family_${tryIdx}`;
                    }
                    form.setAttribute('data-upload-zone', newZone);
                }

                // استخدام الدالة المركزية فقط
                if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
                    window.setupDocumentUploadHandlersForMember(form, idx);
                }
            });

            // عند إضافة فرد جديد، اربط الأحداث له فقط
            const addFamilyMemberBtn = document.getElementById('addFamilyMember');
            if (addFamilyMemberBtn) {
                addFamilyMemberBtn.addEventListener('click', function() {
                    setTimeout(() => {
                        const forms = document.querySelectorAll('.family-member-form');
                        // حل مبتكر: أعط كل نموذج جديد data-upload-zone فريد
                        let usedZones = Array.from(forms).map(f => f.getAttribute(
                            'data-upload-zone')).filter(Boolean);
                        forms.forEach((form, idx) => {
                            let dataUploadZone = form.getAttribute('data-upload-zone');
                            if (!dataUploadZone) {
                                let newZone = `family_${idx}`;
                                let tryIdx = idx;
                                while (usedZones.includes(newZone)) {
                                    tryIdx++;
                                    newZone = `family_${tryIdx}`;
                                }
                                form.setAttribute('data-upload-zone', newZone);
                                usedZones.push(newZone);
                            }

                            // استخدام الدالة المركزية فقط
                            if (typeof window.setupDocumentUploadHandlersForMember ===
                                'function') {
                                window.setupDocumentUploadHandlersForMember(form, idx);
                            }
                        });
                    }, 100);
                });
            }
        });
    </script>

    <script>
        // منع ظهور خطأ "input غير قابل للتركيز" عند التحقق من الحقول المطلوبة في تبويبات مخفية
        // وتفعيل التبويب تلقائياً عند وجود خطأ في أحد حقوله

        document.addEventListener('DOMContentLoaded', function() {
            // دالة مساعدة: تفعيل تبويب حسب id
            function activateTab(tabId) {
                const tabBtn = document.getElementById(tabId);
                if (tabBtn) tabBtn.click();
            }

            // دالة عامة للتحقق من الحقول المطلوبة في تبويب معين
            function validateTabFields(tabSelector, requiredFields) {
                let firstInvalid = null;
                requiredFields.forEach(field => {
                    const el = document.querySelector(`${tabSelector} [name="${field.name}"]`);
                    if (el && !el.value) {
                        firstInvalid = el;
                    }
                });
                return firstInvalid;
            }

            // مثال: عند الضغط على زر "التالي" في بوابة المتوفين
            const deceasedNextBtn = document.getElementById('goToFamilyTabBtn');
            if (deceasedNextBtn) {
                deceasedNextBtn.addEventListener('click', function(e) {
                    // تحقق من الأب
                    const fatherFields = [{
                            name: 'father_first_name'
                        },
                        {
                            name: 'father_last_name'
                        },
                        {
                            name: 'father_id'
                        },
                        {
                            name: 'father_death_date'
                        },
                        {
                            name: 'father_death_reason'
                        },
                    ];
                    let invalid = validateTabFields('#deceased', fatherFields);
                    if (invalid) {
                        // فعّل تبويب المتوفين
                        activateTab('deceased-tab');
                        setTimeout(() => invalid.focus(), 200);
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى تعبئة جميع الحقول المطلوبة للأب.'
                        });
                        return;
                    }
                    // تحقق من الأم إذا كانت ظاهرة
                    const motherSection = document.getElementById('motherInfoSection');
                    if (motherSection && motherSection.style.display !== 'none') {
                        const motherFields = [{
                                name: 'mother_first_name'
                            },
                            {
                                name: 'mother_last_name'
                            },
                            {
                                name: 'mother_id'
                            },
                            {
                                name: 'mother_death_date'
                            },
                            {
                                name: 'mother_death_reason'
                            },
                        ];
                        invalid = validateTabFields('#motherInfoSection', motherFields);
                        if (invalid) {
                            activateTab('deceased-tab');
                            setTimeout(() => invalid.focus(), 200);
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'تنبيه',
                                text: 'يرجى تعبئة جميع الحقول المطلوبة للأم.'
                            });
                            return;
                        }
                    }
                });
            }

            // مثال: عند التحقق من أفراد الأسرة
            const familyNextBtn = document.getElementById('goToReviewTabBtn');
            if (familyNextBtn) {
                familyNextBtn.addEventListener('click', function(e) {
                    let invalid = null;
                    document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                        if (invalid) return;
                        const requiredFields = [{
                            name: 'first_name'
                        }, ];
                        requiredFields.forEach(field => {
                            const el = form.querySelector(`[name*="[${field.name}]"]`);
                            if (el && !el.value) invalid = el;
                        });
                    });
                    if (invalid) {
                        activateTab('family-members-tab');
                        setTimeout(() => invalid.focus(), 200);
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى تعبئة جميع الحقول المطلوبة لأفراد الأسرة.'
                        });
                        return;
                    }
                });
            }

            // يمكن تكرار نفس المنطق لأي تبويب آخر

            // --- منطق التحقق من رفع الوثائق الإلزامية عبر window.allDocs ---
            // دالة: التحقق من رفع جميع الوثائق الإلزامية لشخص أو بوابة معينة
            function checkRequiredDocs(personKey, requiredDocTypes) {
                if (!window.allDocs || !(window.allDocs instanceof Map)) return requiredDocTypes;
                // ابحث عن جميع المرفقات في كل المناطق (وليس فقط personKey)
                let allDocsArr = [];
                window.allDocs.forEach((arr, key) => {
                    if (key === personKey || key === String(personKey)) {
                        allDocsArr = allDocsArr.concat(arr);
                    }
                });
                // إذا لم يوجد شيء، جرب fallback: جميع المرفقات في كل المناطق (حل أخير)
                if (allDocsArr.length === 0) {
                    window.allDocs.forEach((arr) => {
                        allDocsArr = allDocsArr.concat(arr);
                    });
                }
                // فقط المرفقات المعتمدة: processedFile موجود ونوعه ليس فارغاً
                const uploadedTypes = allDocsArr
                    .filter(doc => doc.processedFile && (doc.type || doc.docType))
                    .map(doc => (doc.type || doc.docType || '').toString().trim().toLowerCase());
                return requiredDocTypes.filter(type => {
                    const normType = (type || '').toString().trim().toLowerCase();
                    return !uploadedTypes.some(upType => upType === normType);
                });
            }

            // دالة: منع الانتقال بين التبويبات إذا لم تُرفع جميع الوثائق الإلزامية
            function preventTabSwitchIfDocsMissing(tabBtnId, personKey, requiredDocTypes, tabNameAr) {
                const tabBtn = document.getElementById(tabBtnId);
                if (!tabBtn) return;
                tabBtn.addEventListener('click', function(e) {
                    const missing = checkRequiredDocs(personKey, requiredDocTypes);
                    if (missing.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            html: 'يجب رفع جميع الوثائق الإلزامية التالية قبل الانتقال من بوابة <b>' +
                                tabNameAr +
                                '</b>:<br><ul style="text-align:right;direction:rtl;">' + missing
                                .map(m => '<li>' + m + '</li>').join('') + '</ul>'
                        });
                        // طباعة الناقص في الكونسول للتشخيص
                        console.warn('[منع الانتقال] الوثائق الناقصة:', missing, 'لـ', personKey);
                    }
                });
            }

            // مثال: منع الانتقال من تبويب البيانات الأساسية إلا بعد رفع الوثائق المطلوبة
            // (يفترض أن أنواع الوثائق الإلزامية معروفة مسبقاً)
            const basicRequiredDocs = window.basicRequiredDocs || [];
            if (basicRequiredDocs.length > 0) {
                preventTabSwitchIfDocsMissing('basic-tab-next', 'main', basicRequiredDocs, 'البيانات الأساسية');
            }

            // مثال: منع الانتقال من تبويب المتوفين (الأب/الأم) إلا بعد رفع الوثائق المطلوبة
            // (يفترض أن لكل متوفى personKey خاص به، مثل father_id أو mother_id)
            const deceasedFatherRequiredDocs = window.deceasedFatherRequiredDocs || [];
            const deceasedMotherRequiredDocs = window.deceasedMotherRequiredDocs || [];
            const fatherIdInput = document.querySelector('input[name="father_id"]');
            const motherIdInput = document.querySelector('input[name="mother_id"]');
            if (deceasedFatherRequiredDocs.length > 0 && fatherIdInput && fatherIdInput.value) {
                preventTabSwitchIfDocsMissing('deceased-tab-next', fatherIdInput.value, deceasedFatherRequiredDocs,
                    'المتوفى (الأب)');
            }
            if (deceasedMotherRequiredDocs.length > 0 && motherIdInput && motherIdInput.value) {
                preventTabSwitchIfDocsMissing('deceased-tab-next', motherIdInput.value, deceasedMotherRequiredDocs,
                    'المتوفى (الأم)');
            }

            // مثال: منع الانتقال من تبويب أفراد الأسرة إلا بعد رفع الوثائق المطلوبة لكل فرد
            // (يفترض أن لكل فرد personKey خاص به)
            const familyRequiredDocs = window.familyRequiredDocs || [];
            document.querySelectorAll('.family-member-form').forEach(function(form) {
                const personIdInput = form.querySelector(
                    'input[name^="family_members["][name$="[person_id]"]');
                if (personIdInput && personIdInput.value && familyRequiredDocs.length > 0) {
                    preventTabSwitchIfDocsMissing('family-members-tab-next', personIdInput.value,
                        familyRequiredDocs, 'أفراد الأسرة');
                }
            });

            // ملاحظة: يجب ضبط معرفات الأزرار/التبويبات (مثل basic-tab-next, deceased-tab-next, family-members-tab-next) حسب المشروع الفعلي
            // ويمكنك تكرار نفس المنطق لأي تبويب آخر حسب الحاجة

        });
    </script>
@endpush

{{-- cropper modal --}}
@include('user.generalRegistration.javascript.cropper')


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // دعم جميع بوابات أفراد الأسرة بشكل ديناميكي
        // إزالة تعريف setupDocumentUploadHandlersForMember من هنا لأنه موجود في familyMember.blade.php

        // تفعيل رفع الملفات لكل فرد حالي عند تحميل الصفحة
        document.querySelectorAll('.family-member-form').forEach((form, idx) => {
            // حل مبتكر: اجعل كل نموذج يأخذ data-upload-zone فريد إذا لم يكن موجوداً
            let dataUploadZone = form.getAttribute('data-upload-zone');
            if (!dataUploadZone) {
                // إذا كان هناك نموذج آخر بنفس data-upload-zone، أعطه index جديد
                let usedZones = Array.from(document.querySelectorAll('.family-member-form'))
                    .map(f => f.getAttribute('data-upload-zone'))
                    .filter(Boolean);
                let newZone = `family_${idx}`;
                let tryIdx = idx;
                while (usedZones.includes(newZone)) {
                    tryIdx++;
                    newZone = `family_${tryIdx}`;
                }
                form.setAttribute('data-upload-zone', newZone);
            }

            // استخدام الدالة المركزية فقط
            if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
                window.setupDocumentUploadHandlersForMember(form, idx);
            }
        });

        // عند إضافة فرد جديد، اربط الأحداث له فقط
        const addFamilyMemberBtn = document.getElementById('addFamilyMember');
        if (addFamilyMemberBtn) {
            addFamilyMemberBtn.addEventListener('click', function() {
                setTimeout(() => {
                    const forms = document.querySelectorAll('.family-member-form');
                    // حل مبتكر: أعط كل نموذج جديد data-upload-zone فريد
                    let usedZones = Array.from(forms).map(f => f.getAttribute(
                        'data-upload-zone')).filter(Boolean);
                    forms.forEach((form, idx) => {
                        let dataUploadZone = form.getAttribute('data-upload-zone');
                        if (!dataUploadZone) {
                            let newZone = `family_${idx}`;
                            let tryIdx = idx;
                            while (usedZones.includes(newZone)) {
                                tryIdx++;
                                newZone = `family_${tryIdx}`;
                            }
                            form.setAttribute('data-upload-zone', newZone);
                            usedZones.push(newZone);
                        }

                        // استخدام الدالة المركزية فقط
                        if (typeof window.setupDocumentUploadHandlersForMember ===
                            'function') {
                            window.setupDocumentUploadHandlersForMember(form, idx);
                        }
                    });
                }, 100);
            });
        }
    });
</script>
@push('scriptsCodeUserRegistration')
    <script>
        // منع ظهور خطأ "input غير قابل للتركيز" عند التحقق من الحقول المطلوبة في تبويبات مخفية
        // وتفعيل التبويب تلقائياً عند وجود خطأ في أحد حقوله

        document.addEventListener('DOMContentLoaded', function() {
            // دالة مساعدة: تفعيل تبويب حسب id
            function activateTab(tabId) {
                const tabBtn = document.getElementById(tabId);
                if (tabBtn) tabBtn.click();
            }

            // دالة عامة للتحقق من الحقول المطلوبة في تبويب معين
            function validateTabFields(tabSelector, requiredFields) {
                let firstInvalid = null;
                requiredFields.forEach(field => {
                    const el = document.querySelector(`${tabSelector} [name="${field.name}"]`);
                    if (el && !el.value) {
                        firstInvalid = el;
                    }
                });
                return firstInvalid;
            }

            // مثال: عند الضغط على زر "التالي" في بوابة المتوفين
            const deceasedNextBtn = document.getElementById('goToFamilyTabBtn');
            if (deceasedNextBtn) {
                deceasedNextBtn.addEventListener('click', function(e) {
                    // تحقق من الأب
                    const fatherFields = [{
                            name: 'father_first_name'
                        },
                        {
                            name: 'father_last_name'
                        },
                        {
                            name: 'father_id'
                        },
                        {
                            name: 'father_death_date'
                        },
                        {
                            name: 'father_death_reason'
                        },
                    ];
                    let invalid = validateTabFields('#deceased', fatherFields);
                    if (invalid) {
                        // فعّل تبويب المتوفين
                        activateTab('deceased-tab');
                        setTimeout(() => invalid.focus(), 200);
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى تعبئة جميع الحقول المطلوبة للأب.'
                        });
                        return;
                    }
                    // تحقق من الأم إذا كانت ظاهرة
                    const motherSection = document.getElementById('motherInfoSection');
                    if (motherSection && motherSection.style.display !== 'none') {
                        const motherFields = [{
                                name: 'mother_first_name'
                            },
                            {
                                name: 'mother_last_name'
                            },
                            {
                                name: 'mother_id'
                            },
                            {
                                name: 'mother_death_date'
                            },
                            {
                                name: 'mother_death_reason'
                            },
                        ];
                        invalid = validateTabFields('#motherInfoSection', motherFields);
                        if (invalid) {
                            activateTab('deceased-tab');
                            setTimeout(() => invalid.focus(), 200);
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'تنبيه',
                                text: 'يرجى تعبئة جميع الحقول المطلوبة للأم.'
                            });
                            return;
                        }
                    }
                });
            }

            // مثال: عند التحقق من أفراد الأسرة
            const familyNextBtn = document.getElementById('goToReviewTabBtn');
            if (familyNextBtn) {
                familyNextBtn.addEventListener('click', function(e) {
                    let invalid = null;
                    document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form) {
                        if (invalid) return;
                        const requiredFields = [{
                            name: 'first_name'
                        }, ];
                        requiredFields.forEach(field => {
                            const el = form.querySelector(`[name*="[${field.name}]"]`);
                            if (el && !el.value) invalid = el;
                        });
                    });
                    if (invalid) {
                        activateTab('family-members-tab');
                        setTimeout(() => invalid.focus(), 200);
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى تعبئة جميع الحقول المطلوبة لأفراد الأسرة.'
                        });
                        return;
                    }
                });
            }

            // يمكن تكرار نفس المنطق لأي تبويب آخر

            // --- منطق التحقق من رفع الوثائق الإلزامية عبر window.allDocs ---
            // دالة: التحقق من رفع جميع الوثائق الإلزامية لشخص أو بوابة معينة
            function checkRequiredDocs(personKey, requiredDocTypes) {
                if (!window.allDocs || !(window.allDocs instanceof Map)) return requiredDocTypes;
                // ابحث عن جميع المرفقات في كل المناطق (وليس فقط personKey)
                let allDocsArr = [];
                window.allDocs.forEach((arr, key) => {
                    if (key === personKey || key === String(personKey)) {
                        allDocsArr = allDocsArr.concat(arr);
                    }
                });
                // إذا لم يوجد شيء، جرب fallback: جميع المرفقات في كل المناطق (حل أخير)
                if (allDocsArr.length === 0) {
                    window.allDocs.forEach((arr) => {
                        allDocsArr = allDocsArr.concat(arr);
                    });
                }
                // فقط المرفقات المعتمدة: processedFile موجود ونوعه ليس فارغاً
                const uploadedTypes = allDocsArr
                    .filter(doc => doc.processedFile && (doc.type || doc.docType))
                    .map(doc => (doc.type || doc.docType || '').toString().trim().toLowerCase());
                return requiredDocTypes.filter(type => {
                    const normType = (type || '').toString().trim().toLowerCase();
                    return !uploadedTypes.some(upType => upType === normType);
                });
            }

            // دالة: منع الانتقال بين التبويبات إذا لم تُرفع جميع الوثائق الإلزامية
            function preventTabSwitchIfDocsMissing(tabBtnId, personKey, requiredDocTypes, tabNameAr) {
                const tabBtn = document.getElementById(tabBtnId);
                if (!tabBtn) return;
                tabBtn.addEventListener('click', function(e) {
                    const missing = checkRequiredDocs(personKey, requiredDocTypes);
                    if (missing.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            html: 'يجب رفع جميع الوثائق الإلزامية التالية قبل الانتقال من بوابة <b>' +
                                tabNameAr +
                                '</b>:<br><ul style="text-align:right;direction:rtl;">' + missing
                                .map(m => '<li>' + m + '</li>').join('') + '</ul>'
                        });
                        // طباعة الناقص في الكونسول للتشخيص
                        console.warn('[منع الانتقال] الوثائق الناقصة:', missing, 'لـ', personKey);
                    }
                });
            }

            // مثال: منع الانتقال من تبويب البيانات الأساسية إلا بعد رفع الوثائق المطلوبة
            // (يفترض أن أنواع الوثائق الإلزامية معروفة مسبقاً)
            const basicRequiredDocs = window.basicRequiredDocs || [];
            if (basicRequiredDocs.length > 0) {
                preventTabSwitchIfDocsMissing('basic-tab-next', 'main', basicRequiredDocs, 'البيانات الأساسية');
            }

            // مثال: منع الانتقال من تبويب المتوفين (الأب/الأم) إلا بعد رفع الوثائق المطلوبة
            // (يفترض أن لكل متوفى personKey خاص به، مثل father_id أو mother_id)
            const deceasedFatherRequiredDocs = window.deceasedFatherRequiredDocs || [];
            const deceasedMotherRequiredDocs = window.deceasedMotherRequiredDocs || [];
            const fatherIdInput = document.querySelector('input[name="father_id"]');
            const motherIdInput = document.querySelector('input[name="mother_id"]');
            if (deceasedFatherRequiredDocs.length > 0 && fatherIdInput && fatherIdInput.value) {
                preventTabSwitchIfDocsMissing('deceased-tab-next', fatherIdInput.value, deceasedFatherRequiredDocs,
                    'المتوفى (الأب)');
            }
            if (deceasedMotherRequiredDocs.length > 0 && motherIdInput && motherIdInput.value) {
                preventTabSwitchIfDocsMissing('deceased-tab-next', motherIdInput.value, deceasedMotherRequiredDocs,
                    'المتوفى (الأم)');
            }

            // مثال: منع الانتقال من تبويب أفراد الأسرة إلا بعد رفع الوثائق المطلوبة لكل فرد
            // (يفترض أن لكل فرد personKey خاص به)
            const familyRequiredDocs = window.familyRequiredDocs || [];
            document.querySelectorAll('.family-member-form').forEach(function(form) {
                const personIdInput = form.querySelector(
                    'input[name^="family_members["][name$="[person_id]"]');
                if (personIdInput && personIdInput.value && familyRequiredDocs.length > 0) {
                    preventTabSwitchIfDocsMissing('family-members-tab-next', personIdInput.value,
                        familyRequiredDocs, 'أفراد الأسرة');
                }
            });

            // ملاحظة: يجب ضبط معرفات الأزرار/التبويبات (مثل basic-tab-next, deceased-tab-next, family-members-tab-next) حسب المشروع الفعلي
            // ويمكنك تكرار نفس المنطق لأي تبويب آخر حسب الحاجة

        });
    </script>

    <script>
        document.addEventListener('invalid', function(e) {
            // إذا كان الحقل غير ظاهر أو غير قابل للتركيز
            const input = e.target;
            if ((input.offsetParent === null || input.disabled) && input.name) {
                // تفعيل التبويب المناسب تلقائياً
                if (input.name === 'mother_id' || input.closest('#motherInfoSection')) {
                    const tabBtn = document.getElementById('deceased-tab');
                    if (tabBtn) tabBtn.click();
                } else if (input.name === 'father_id' || input.closest('#deceased')) {
                    const tabBtn = document.getElementById('deceased-tab');
                    if (tabBtn) tabBtn.click();
                } else if (input.name.startsWith('family_members') || input.closest('.family-member-form')) {
                    const tabBtn = document.getElementById('family-members-tab');
                    if (tabBtn) tabBtn.click();
                }
                setTimeout(() => {
                    input.focus && input.focus();
                }, 200);
            }
        }, true);
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let passwordScreenshotTaken = false;
            const passwordInput = document.querySelector('input[name="user_password"]');
            const passwordConfirmInput = document.querySelector('input[name="user_password_confirmation"]');
            if (passwordInput && passwordConfirmInput) {
                passwordConfirmInput.addEventListener('blur', function() {
                    if (passwordScreenshotTaken) return;
                    const pass = passwordInput.value;
                    const passConfirm = passwordConfirmInput.value;
                    if (/^\d{4}$/.test(pass) && pass === passConfirm) {
                        passwordScreenshotTaken = true;
                        setTimeout(function() {
                            html2canvas(passwordInput.parentElement).then(function(canvas) {
                                const link = document.createElement('a');
                                link.download = 'password_screenshot.png';
                                link.href = canvas.toDataURL();
                                link.click();
                            });
                        }, 200);
                    }
                });
            }

            const saveBtn = document.getElementById('saveToGoogleBtn');
            const idInput = document.getElementById('data_id_number') || document.querySelector(
                'input[name="data_id_number"]');
            const passInput = document.querySelector('input[name="user_password"]');
            if (saveBtn && idInput && passInput) {
                saveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!idInput.value) {
                        idInput.focus();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال رقم الهوية أولاً قبل حفظ كلمة المرور في جوجل.'
                        });
                        return;
                    }
                    if (!passInput.value) {
                        passInput.focus();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال كلمة المرور أولاً قبل الحفظ.'
                        });
                        return;
                    }
                    if (window.PasswordCredential) {
                        Swal.fire({
                            title: 'تأكيد الحفظ',
                            text: 'سيتم حفظ رقم الهوية كاسم مستخدم وكلمة المرور في مدير كلمات المرور في جوجل. هل تريد المتابعة؟',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'نعم، احفظ',
                            cancelButtonText: 'إلغاء'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const cred = new window.PasswordCredential({
                                    id: idInput.value,
                                    password: passInput.value,
                                    name: idInput.value
                                });
                                navigator.credentials.store(cred).then(function() {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'تم الحفظ',
                                        text: 'تم حفظ كلمة المرور في مدير كلمات المرور في المتصفح (جوجل).'
                                    });
                                });
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'غير مدعوم',
                            text: 'هذه الميزة مدعومة فقط في بعض المتصفحات مثل جوجل كروم.'
                        });
                    }
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // دعم جميع بوابات رفع الملفات (بما فيها المتوفين)
            const docTypeSelects = document.querySelectorAll('.mainDocumentTypeSelect');
            docTypeSelects.forEach(function(docTypeSelect) {
                docTypeSelect.addEventListener('change', function() {
                    // ابحث عن input[type="file"] القريب بعد الـ select مباشرة (DOM traversal)
                    let fileInput = null;
                    let next = docTypeSelect.nextElementSibling;
                    while (next) {
                        if (next.classList && next.classList.contains('mainDocumentFileInput')) {
                            fileInput = next;
                            break;
                        }
                        // إذا كان العنصر عبارة عن div أو مجموعة، ابحث داخله
                        if (next.querySelector) {
                            let innerInput = next.querySelector('.mainDocumentFileInput');
                            if (innerInput) {
                                fileInput = innerInput;
                                break;
                            }
                        }
                        next = next.nextElementSibling;
                    }
                    if (!fileInput) return;
                    if (docTypeSelect.value) {
                        fileInput.style.display = '';
                        fileInput.value = '';
                        fileInput.click();
                    } else {
                        fileInput.style.display = 'none';
                    }
                });
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة مسافة أسفل تبويب عرض المعلومات المدخلة
            const reviewPane = document.getElementById('review');
            if (reviewPane && !reviewPane.querySelector('#didding')) {
                const diddingDiv = document.createElement('div');
                diddingDiv.id = 'didding';
                diddingDiv.style.paddingBottom = '80px';
                reviewPane.appendChild(diddingDiv);
            }
        });
    </script>

    <script>
        // معالجة مشكلة required مع الحقول المخفية أو غير القابلة للتركيز عند إرسال أي نموذج
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    let invalid = false;
                    form.querySelectorAll('[required]').forEach(function(input) {
                        // إذا كان الحقل غير ظاهر أو غير قابل للتركيز
                        const style = window.getComputedStyle(input);
                        if ((style.display === 'none' || input.offsetParent === null ||
                                input.disabled) && input.required) {
                            input.removeAttribute('required');
                            input.setAttribute('data-temp-required', '1');
                            invalid = true;
                        }
                    });
                    // بعد الإرسال، أعد required للحقول التي أزلناها مؤقتاً
                    if (invalid) {
                        setTimeout(function() {
                            form.querySelectorAll('[data-temp-required]').forEach(function(
                                input) {
                                input.setAttribute('required', 'required');
                                input.removeAttribute('data-temp-required');
                            });
                        }, 100);
                    }
                }, true);
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mainForm = document.getElementById('main_form');
            if (!mainForm) return;
            mainForm.addEventListener('submit', function(e) {
                // منع الإرسال الافتراضي
                e.preventDefault();
                // جمع جميع البيانات في FormData
                const formData = new FormData(mainForm);

                // حذف أي بيانات أفراد أسرة قديمة من FormData
                Array.from(formData.keys()).forEach(key => {
                    if (key.startsWith('family_members')) {
                        formData.delete(key);
                    }
                });

                // جمع بيانات أفراد الأسرة من النماذج الظاهرة فقط
                document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form, idx) {
                    form.querySelectorAll('[name]').forEach(function(input) {
                        const name = input.name;
                        const value = input.value;
                        if (name.startsWith('family_members[')) {
                            formData.append(name, value);
                        }
                    });
                });

                // جمع بيانات الحسابات البنكية من النماذج الديناميكية (من baseTap.blade.php)
                document.querySelectorAll('.bank-account-form').forEach(function(form, idx) {
                    form.querySelectorAll('[name]').forEach(function(input) {
                        const name = input.name;
                        const value = input.value;
                        if (name.startsWith('bank_accounts[')) {
                            formData.append(name, value);
                        }
                    });
                });

                // حذف أي مرفقات قديمة من FormData
                Array.from(formData.keys()).forEach(key => {
                    if (key.startsWith('attachments')) {
                        formData.delete(key);
                    }
                });

                // جمع جميع المرفقات من window.allDocs بشكل ديناميكي لأي شخص أو بوابة
                let attachIndex = 0;
                let attachmentsDebug = [];
                if (window.allDocs && window.allDocs instanceof Map) {
                    window.allDocs.forEach((docsArr, personKey) => {
                        docsArr.forEach(doc => {
                            // استخدم doc.type أو doc.docType
                            const docTypeVal = doc.type || doc.docType;
                            if (!docTypeVal || docTypeVal === 'undefined') {
                                console.error('❌ مرفق بدون نوع وثيقة (type):', doc);
                            }
                            if (doc && typeof doc === 'object' && doc.processedFile) {
                                formData.append(`attachments[${attachIndex}][file]`, doc
                                    .processedFile);
                                formData.append(
                                    `attachments[${attachIndex}][person_identity_number]`,
                                    doc.personId);
                                formData.append(`attachments[${attachIndex}][file_type]`,
                                    docTypeVal);
                                formData.append(
                                    `attachments[${attachIndex}][stored_file_name]`, doc
                                    .processedFile.name);
                                formData.append(
                                    `attachments[${attachIndex}][file_id_number]`, doc
                                    .fileId || '');
                                attachmentsDebug.push({
                                    idx: attachIndex,
                                    name: doc.processedFile.name,
                                    type: docTypeVal,
                                    personId: doc.personId,
                                    fileId: doc.fileId,
                                    isImage: doc.processedFile.type && doc
                                        .processedFile.type.startsWith('image/')
                                });
                                console.log('🟡 سيتم إرسال هذا المرفق:', {
                                    idx: attachIndex,
                                    name: doc.processedFile.name,
                                    type: docTypeVal,
                                    personId: doc.personId,
                                    fileId: doc.fileId,
                                    isImage: doc.processedFile.type && doc
                                        .processedFile.type.startsWith('image/')
                                });
                                attachIndex++;
                            }
                        });
                    });
                }
                // طباعة جميع المرفقات قبل الإرسال النهائي
                console.log('🟢 جميع المرفقات المرسلة فعلياً (window.allDocs):', Array.from(window.allDocs
                    .entries()));


                // تم تعطيل التحقق من وجود مرفق واحد على الأقل قبل الحفظ نهائيًا بناءً على طلب التعديلات.

                // طباعة جميع المرفقات التي ستُرسل (للتأكد من محتوى FormData)
                console.log('🟠 جميع المرفقات التي ستُرسل (attachmentsDebug):', attachmentsDebug);

                // طباعة جميع المفاتيح في FormData (للتأكد النهائي)
                for (let pair of formData.entries()) {
                    if (pair[0].startsWith('attachments')) {
                        console.log('🟣 FormData attachment:', pair[0], pair[1]);
                    }
                }

                // إرسال البيانات عبر AJAX
                fetch(mainForm.action, {
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
                        return {
                            data,
                            status: response.status
                        };
                    })
                    .then(({ data, status }) => {
                        // إذا كانت الاستجابة JSON وبها success=true
                        if (typeof data === 'object' && data && data.success) {
                            // إذا كان هناك توجيه من السيرفر
                            // if (data.redirect) {
                            //     // window.location.href = data.redirect;
                            //     // return;
                            // }
                            if (data && data.success && data.redirect) {
                                // معالجة الرابط إذا كان يبدأ بـ http أو https أو //
                                let redirectUrl = data.redirect;
                                if (redirectUrl.startsWith('http://') || redirectUrl.startsWith('https://') || redirectUrl.startsWith('//')) {
                                    window.location.href = redirectUrl;
                                } else {
                                    // إذا كان الرابط نسبي، أضفه إلى أصل الموقع
                                    window.location.href = window.location.origin + (redirectUrl.startsWith('/') ? redirectUrl : '/' + redirectUrl);
                                }
                                return;
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحفظ',
                                text: 'تم حفظ السجل بنجاح'
                            });
                            // setTimeout(() => window.location.reload(), 1500);
                        }
                        // إذا كانت الاستجابة نصية والكود 200، اعتبرها نجاح (حل مشكلة Laravel redirect)
                        else if (status === 200 && typeof data === 'string') {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحفظ',
                                text: 'تم حفظ السجل بنجاح'
                            });
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            // اطبع الاستجابة في الـ console لتسهيل التشخيص
                            console.error('استجابة غير متوقعة من السيرفر:', data);
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: (data && data.error) ? data.error : 'حدث خطأ أثناء الحفظ'
                            });
                        }
                    })
                    .catch(err => {
                        console.error('خطأ أثناء الاتصال أو المعالجة:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: 'حدث خطأ أثناء الحفظ'
                        });
                    });
            });
        });
    </script>

    <script>
        // دالة: هل تم رفع وثيقة إلزامية معينة (حسب النوع)
        function isRequiredDocUploaded(requiredType) {
            if (!window.allDocs || !(window.allDocs instanceof Map)) return false;
            let found = false;
            const normType = (requiredType || '').toString().trim().toLowerCase();
            window.allDocs.forEach(arr => {
                arr.forEach(doc => {
                    const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                    if (
                        docType === normType &&
                        (doc.processedFile === true || doc.processedFile === 1 || ((doc
                                .processedFile instanceof File || doc.processedFile instanceof Blob) &&
                            doc.status === 'completed'))
                    ) {
                        found = true;
                    }
                });
            });
            return found;
        }

        // دالة: جلب تفاصيل أول وثيقة مطابقة لنوع معين (مع processedFile)
        function getUploadedDocByType(requiredType) {
            if (!window.allDocs || !(window.allDocs instanceof Map)) return null;
            let result = null;
            const normType = (requiredType || '').toString().trim().toLowerCase();
            window.allDocs.forEach(arr => {
                arr.forEach(doc => {
                    const docType = (doc.type || doc.docType || '').toString().trim().toLowerCase();
                    if (
                        docType === normType &&
                        (doc.processedFile === true || doc.processedFile === 1 || ((doc
                                .processedFile instanceof File || doc.processedFile instanceof Blob) &&
                            doc.status === 'completed'))
                    ) {
                        result = doc;
                    }
                });
            });
            return result;
        }

        // --- تمييز الوثائق المرفوعة في قوائم select ---
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.mainDocumentTypeSelect').forEach(function(select) {
                function updateSelectOptionsHighlight() {
                    for (let i = 0; i < select.options.length; i++) {
                        const opt = select.options[i];
                        const typeVal = opt.value;
                        if (!typeVal) continue;
                        if (isRequiredDocUploaded(typeVal)) {
                            opt.classList.add('uploaded-doc-option');
                            opt.style.backgroundColor = '#e0ffe0';
                            opt.style.fontWeight = 'bold';
                        } else {
                            opt.classList.remove('uploaded-doc-option');
                            opt.style.backgroundColor = '';
                            opt.style.fontWeight = '';
                        }
                    }
                }
                select.addEventListener('focus', updateSelectOptionsHighlight);
                select.addEventListener('mousedown', updateSelectOptionsHighlight);
                select.addEventListener('change', updateSelectOptionsHighlight);
                updateSelectOptionsHighlight();
                window.addEventListener('allDocsUpdated', updateSelectOptionsHighlight);
            });
        });

        // --- منع الانتقال بين التبويبات إلا بعد رفع جميع الوثائق المطلوبة ---
        document.addEventListener('DOMContentLoaded', function() {
            window.canSwitchTab = function(requiredDocTypes) {
                if (!requiredDocTypes || !requiredDocTypes.length) return true;
                return requiredDocTypes.every(type => isRequiredDocUploaded(type));
            };

            // مثال: ربط أزرار "التالي" بمنع الانتقال إذا لم تُرفع جميع الوثائق المطلوبة
            // يجب ضبط معرفات الأزرار وقوائم الوثائق المطلوبة حسب كل تبويب
            const tabNextButtons = [{
                    btnId: 'basic-tab-next',
                    requiredDocs: window.basicRequiredDocs || [],
                    tabName: 'البيانات الأساسية'
                },
                {
                    btnId: 'deceased-tab-next',
                    requiredDocs: (window.deceasedFatherRequiredDocs || []).concat(window
                        .deceasedMotherRequiredDocs || []),
                    tabName: 'المتوفين'
                },
                {
                    btnId: 'family-members-tab-next',
                    requiredDocs: window.familyRequiredDocs || [],
                    tabName: 'أفراد الأسرة'
                },
            ];
            tabNextButtons.forEach(function(tab) {
                const btn = document.getElementById(tab.btnId);
                if (btn && tab.requiredDocs.length > 0) {
                    btn.addEventListener('click', function(e) {
                        if (!window.canSwitchTab(tab.requiredDocs)) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'تنبيه',
                                html: 'يجب رفع جميع الوثائق الإلزامية التالية قبل الانتقال من بوابة <b>' +
                                    tab.tabName +
                                    '</b>:<br><ul style="text-align:right;direction:rtl;">' +
                                    tab.requiredDocs.map(m => '<li>' + m + '</li>').join(
                                        '') + '</ul>'
                            });
                        }
                    });
                }
            });
        });
    </script>
@endpush
