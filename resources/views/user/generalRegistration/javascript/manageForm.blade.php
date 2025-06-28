@push('scriptsCodeUserRegistration')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
                // مصفوفة محلية للمرفقات لهذه البوابة (الأب أو الأم)
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
                                        // حذف من مصفوفة documents المحلية فقط العنصر المطلوب
                                        documents.splice(idx, 1);
                                        // حذف من window.allDocs فقط العنصر المطابق
                                        if (window.allDocs && window.allDocs instanceof Map) {
                                            const personKey = doc.personId || doc.personKey || 'main';
                                            if (window.allDocs.has(personKey)) {
                                                let arr = window.allDocs.get(personKey);
                                                arr = arr.filter(item => !(item.docName === doc.docName && item.type === doc.type && item.fileId === doc.fileId));
                                                if (arr.length > 0) {
                                                    window.allDocs.set(personKey, arr);
                                                } else {
                                                    window.allDocs.delete(personKey);
                                                }
                                            }
                                        }
                                        renderDocuments();
                                        // تحديث واجهة عرض المعلومات إذا كانت الدالة موجودة
                                        if (typeof window.renderReviewContent === 'function') {
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
                    // السماح فقط برفع الصور jpg, jpeg, png
                    if (docTypeSelect.value && fileInput) {
                        fileInput.setAttribute('accept', 'image/jpeg,image/png');
                        fileInput.setAttribute('capture', 'environment'); // توجيه المستخدم مباشرة للاستديو/الكاميرا
                    }
                    fileDialogOpen = true;
                    fileInput.value = '';
                    const resetDialog = () => {
                        fileDialogOpen = false;
                        fileInput.removeEventListener('blur', resetDialog);
                    };
                    fileInput.addEventListener('blur', resetDialog);
                    fileInput.click();
                });

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
                            parent.querySelector('input[name="father_id"]') ||
                            parent.querySelector('input[name="mother_id"]') ||
                            parent.querySelector('input[name^="family_members["][name$="[person_id]"]') ||
                            parent.querySelector('input[name="member_id"]') ||
                            parent.querySelector('input[name="data_id_number"]') ||
                            document.getElementById('data_id_number') ||
                            document.querySelector('input[name="data_id_number"]') ||
                            document.querySelector('input[name="father_id"]') ||
                            document.querySelector('input[name="mother_id"]') ||
                            document.querySelector('input[name="member_id"]') ||
                            document.querySelector('input[name^="family_members["][name$="[person_id]"]');
                        const file = this.files[0];
                        const typeText = docTypeSelect.options[docTypeSelect.selectedIndex].text;
                        const typeVal = docTypeSelect.value;
                        const fileIdInput = parent.querySelector('input[name="file_id_number"]') ||
                            document.querySelector('input[name="file_id_number"]');
                        const fileId = fileIdInput ? fileIdInput.value : '';
                        const personId = idInput ? idInput.value : '';
                        if (!fileId || fileId === 'undefined') return;
                        const docName = `${typeVal}_${fileId}_${personId}${file.name.substring(file.name.lastIndexOf('.'))}`;
                        const personKey = personId || (parent.querySelector('[data-upload-zone]')?.getAttribute('data-upload-zone') || 'main');
                        // تحقق من عدم تكرار نفس الملف
                        let docsArr = window.allDocs.get(personKey) || [];
                        const alreadyExists = docsArr.some(item => item.docName === docName && item.type === typeVal && item.fileId === fileId);
                        if (alreadyExists) {
                            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'تم رفع هذا الملف مسبقاً.' });
                            docTypeSelect.value = '';
                            return;
                        }
                        if (file.type.startsWith('image/')) {
                            if (typeof window.showCropperModal === 'function') {
                                window.showCropperModal(file, function(croppedFile) {
                                    if (!croppedFile || !fileId || !personId) return;
                                    if (croppedFile.name && croppedFile.name.includes('_cropped') && croppedFile.name !== 'undefined') {
                                        // احذف أي صورة أصلية أو مكررة لنفس الشخص ولنفس النوع بنفس الاسم
                                        docsArr = docsArr.filter(doc => {
                                            if (!doc.file) return true;
                                            if (doc.file.type.startsWith('image/')) {
                                                if (!doc.file.name.includes('_cropped')) return false;
                                                if (doc.file.name === croppedFile.name) return false;
                                            }
                                            return true;
                                        });
                                        // أضف الصورة المقصوصة
                                        const docObj = {
                                            type: typeVal,
                                            typeText: typeText,
                                            file: croppedFile,
                                            docName: docName,
                                            personId: personId,
                                            fileId: fileId
                                        };
                                        docsArr.push(docObj);
                                        window.allDocs.set(personKey, docsArr);
                                        documents.push(docObj);
                                        // تعريف دالة العرض مرة واحدة في الأعلى
                                        if (!window.attachmentsDisplayList) window.attachmentsDisplayList = [];
                                        window.addAttachmentDisplayEntry = function(docObj, parent) {
                                            let personName = '';
                                            let label = docObj.typeText;
                                            if (parent && parent.classList && parent.classList.contains('family-member-form')) {
                                                const firstName = parent.querySelector('[name$="[first_name]"]')?.value || '';
                                                const lastName = parent.querySelector('[name$="[last_name]"]')?.value || '';
                                                personName = `${firstName} ${lastName}`.trim();
                                            } else if (parent && parent.closest && parent.closest('#basic')) {
                                                const firstName = document.querySelector('[name="data_first_name"]')?.value || '';
                                                const lastName = document.querySelector('[name="data_family_name"]')?.value || '';
                                                personName = `${firstName} ${lastName}`.trim();
                                            } else if (parent && parent.closest && parent.closest('#deceased')) {
                                                const firstName = document.querySelector('[name="father_first_name"]')?.value || '';
                                                const lastName = document.querySelector('[name="father_last_name"]')?.value || '';
                                                personName = `${firstName} ${lastName}`.trim();
                                            } else if (
                                                (parent && parent.closest && parent.closest('#motherInfoSection')) ||
                                                (parent && parent.id === 'motherInfoSection') ||
                                                (document.querySelector('#motherInfoSection') && document.querySelector('#motherInfoSection').contains(parent))
                                            ) {
                                                // الأم المتوفية
                                                const firstName = document.querySelector('[name="mother_first_name"]')?.value || '';
                                                const lastName = document.querySelector('[name="mother_last_name"]')?.value || '';
                                                personName = `${firstName} ${lastName}`.trim();
                                                label = 'مرفق الأم المتوفية:';
                                            }
                                            window.attachmentsDisplayList.push({
                                                personName,
                                                label,
                                                doc: docObj.file,
                                                docName: docObj.docName
                                            });
                                        }
                                        // بعد إضافة docObj إلى window.allDocs و/أو documents، أضف إلى مصفوفة العرض
                                        window.addAttachmentDisplayEntry(docObj, parent);
                                        renderDocuments();
                                    }
                                    docTypeSelect.value = '';
                                });
                            }
                        } else {
                            // فقط أضف الملفات غير الصور إذا بياناتها مكتملة
                            if (!fileId || !personId) return;
                            const docObj = {
                                type: typeVal,
                                typeText: typeText,
                                file: file,
                                docName: docName,
                                personId: personId,
                                fileId: fileId
                            };
                            docsArr.push(docObj);
                            window.allDocs.set(personKey, docsArr);
                            documents.push(docObj);
                            // تعريف دالة العرض مرة واحدة في الأعلى
                            if (!window.attachmentsDisplayList) window.attachmentsDisplayList = [];
                            window.addAttachmentDisplayEntry = function(docObj, parent) {
                                let personName = '';
                                let label = docObj.typeText;
                                if (parent && parent.classList && parent.classList.contains('family-member-form')) {
                                    const firstName = parent.querySelector('[name$="[first_name]"]')?.value || '';
                                    const lastName = parent.querySelector('[name$="[last_name]"]')?.value || '';
                                    personName = `${firstName} ${lastName}`.trim();
                                } else if (parent && parent.closest && parent.closest('#basic')) {
                                    const firstName = document.querySelector('[name="data_first_name"]')?.value || '';
                                    const lastName = document.querySelector('[name="data_family_name"]')?.value || '';
                                    personName = `${firstName} ${lastName}`.trim();
                                } else if (parent && parent.closest && parent.closest('#deceased')) {
                                    const firstName = document.querySelector('[name="father_first_name"]')?.value || '';
                                    const lastName = document.querySelector('[name="father_last_name"]')?.value || '';
                                    personName = `${firstName} ${lastName}`.trim();
                                } else if (
                                    (parent && parent.closest && parent.closest('#motherInfoSection')) ||
                                    (parent && parent.id === 'motherInfoSection') ||
                                    (document.querySelector('#motherInfoSection') && document.querySelector('#motherInfoSection').contains(parent))
                                ) {
                                    // الأم المتوفية
                                    const firstName = document.querySelector('[name="mother_first_name"]')?.value || '';
                                    const lastName = document.querySelector('[name="mother_last_name"]')?.value || '';
                                    personName = `${firstName} ${lastName}`.trim();
                                    label = 'مرفق الأم المتوفية:';
                                }
                                window.attachmentsDisplayList.push({
                                    personName,
                                    label,
                                    doc: docObj.file,
                                    docName: docObj.docName
                                });
                            }
                            // بعد إضافة docObj إلى window.allDocs و/أو documents، أضف إلى مصفوفة العرض
                            window.addAttachmentDisplayEntry(docObj, parent);
                            renderDocuments();
                            docTypeSelect.value = '';
                        }
                    }
                });
            });
        });
    </script>
@endpush

{{-- cropper modal --}}
@include('user.generalRegistration.javascript.cropper')


<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet" />
<script>
window.showCropperModal = function(file, callback) {
    const modalEl = document.getElementById('cropperModal');
    const cropperImage = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    let cropper = null;
    // إزالة أي حدث سابق لمنع التكرار
    cropBtn.onclick = null;
    // إزالة أي cropper سابق
    if (cropperImage.cropperInstance) {
        cropperImage.cropperInstance.destroy();
        cropperImage.cropperInstance = null;
    }
    // قراءة الصورة
    const reader = new FileReader();
    reader.onload = function(e) {
        cropperImage.src = e.target.result;
        cropperImage.onload = function() {
            if (cropperImage.cropperInstance) {
                cropperImage.cropperInstance.destroy();
            }
            cropper = new Cropper(cropperImage, {
                aspectRatio: NaN,
                viewMode: 1,
                responsive: true,
                autoCropArea: 1,
                movable: true,
                zoomable: true,
                rotatable: true,
                scalable: true,
                background: false,
                minContainerWidth: 320,
                minContainerHeight: 320,
                guides: true,
                center: true,
                highlight: true,
                dragMode: 'move',
                cropBoxResizable: true,
                cropBoxMovable: true,
            });
            cropperImage.cropperInstance = cropper;
        };
    };
    reader.readAsDataURL(file);
    // إظهار المودال
    let bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
    // إضافة أزرار التحكم مع الأيقونات
    let controls = modalEl.querySelector('.cropper-controls');
    if (!controls) {
        controls = document.createElement('div');
        controls.className = 'cropper-controls';
        controls.innerHTML = `
            <button type="button" title="تحريك للأعلى" id="cropperMoveUp"><i class="fas fa-arrow-up"></i></button>
            <button type="button" title="تحريك لليسار" id="cropperMoveLeft"><i class="fas fa-arrow-left"></i></button>
            <button type="button" title="تحريك لليمين" id="cropperMoveRight"><i class="fas fa-arrow-right"></i></button>
            <button type="button" title="تحريك للأسفل" id="cropperMoveDown"><i class="fas fa-arrow-down"></i></button>
            <button type="button" title="تكبير" id="cropperZoomIn"><i class="fas fa-search-plus"></i></button>
            <button type="button" title="تصغير" id="cropperZoomOut"><i class="fas fa-search-minus"></i></button>
            <button type="button" title="إعادة تعيين" id="cropperReset"><i class="fas fa-sync-alt"></i></button>
        `;
        // أضفها أعلى الصورة
        const modalBody = modalEl.querySelector('.modal-body');
        modalBody.insertBefore(controls, modalBody.firstChild);
    }
    // وضع الصورة داخل حاوية وسطية
    let container = modalEl.querySelector('.cropper-center-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'cropper-center-container';
        const img = cropperImage;
        container.appendChild(img);
        // أضف الحاوية مكان الصورة الأصلية
        const modalBody = modalEl.querySelector('.modal-body');
        modalBody.appendChild(container);
    } else {
        if (!container.contains(cropperImage)) {
            container.innerHTML = '';
            container.appendChild(cropperImage);
        }
    }
    // تفعيل أزرار التحكم
    setTimeout(() => {
        if (!cropper) return;
        controls.querySelector('#cropperMoveUp').onclick = () => cropper.move(0, -10);
        controls.querySelector('#cropperMoveDown').onclick = () => cropper.move(0, 10);
        controls.querySelector('#cropperMoveLeft').onclick = () => cropper.move(-10, 0);
        controls.querySelector('#cropperMoveRight').onclick = () => cropper.move(10, 0);
        controls.querySelector('#cropperZoomIn').onclick = () => cropper.zoom(0.1);
        controls.querySelector('#cropperZoomOut').onclick = () => cropper.zoom(-0.1);
        controls.querySelector('#cropperReset').onclick = () => cropper.reset();
    }, 500);
    // زر القص
    cropBtn.onclick = function() {
        if (!cropperImage.cropperInstance) return;
        // الحصول على بيانات cropBox الفعلية
        const cropData = cropperImage.cropperInstance.getData(true);
        const canvas = cropperImage.cropperInstance.getCroppedCanvas({
            width: Math.round(cropData.width),
            height: Math.round(cropData.height),
            imageSmoothingQuality: 'high'
        });
        if (!canvas) return;
        canvas.toBlob(function(blob) {
            if (!blob) return;
            // اسم جديد للملف المقصوص
            const originalName = file.name;
            const dotIdx = originalName.lastIndexOf('.');
            const base = dotIdx !== -1 ? originalName.substring(0, dotIdx) : originalName;
            const ext = dotIdx !== -1 ? originalName.substring(dotIdx) : '';
            const croppedName = base + '_cropped' + ext;
            const croppedFile = new File([blob], croppedName, {
                type: file.type
            });
            // فحص blob الناتج
            console.log('croppedFile', croppedFile);
            bsModal.hide();
            cropperImage.cropperInstance.destroy();
            cropperImage.cropperInstance = null;
            callback(croppedFile);
        }, file.type);
    };
}
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // دعم جميع بوابات أفراد الأسرة بشكل ديناميكي
        function setupDocumentUploadHandlersForMember(form, memberIndex) {
            const docTypeSelect = form.querySelector('.mainDocumentTypeSelect');
            const fileInput = form.querySelector('.mainDocumentFileInput');
            const preview = form.querySelector('.mainDocumentPreview');
            let documents = [];
            let fileDialogOpen = false;

            function canUploadDocument(triggeredBy) {
                let idInput = form.querySelector('input[name^="family_members["][name$="[person_id]"]');
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
                        // عرض الصورة المقصوصة فقط
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
                                    // حذف من مصفوفة documents المحلية فقط العنصر المطلوب
                                    documents.splice(idx, 1);
                                    // حذف من window.allDocs فقط العنصر المطابق
                                    if (window.allDocs && window.allDocs instanceof Map) {
                                        const personKey = doc.personId || doc.personKey || 'main';
                                        if (window.allDocs.has(personKey)) {
                                            let arr = window.allDocs.get(personKey);
                                            arr = arr.filter(item => !(item.docName === doc.docName && item.type === doc.type && item.fileId === doc.fileId));
                                            if (arr.length > 0) {
                                                window.allDocs.set(personKey, arr);
                                            } else {
                                                window.allDocs.delete(personKey);
                                            }
                                        }
                                    }
                                    renderDocuments();
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
                // السماح فقط برفع الصور jpg, jpeg, png
                if (docTypeSelect.value && fileInput) {
                    fileInput.setAttribute('accept', 'image/jpeg,image/png');
                    fileInput.setAttribute('capture', 'environment'); // توجيه المستخدم مباشرة للاستديو/الكاميرا
                }
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
                    let idInput = form.querySelector('input[name^="family_members["][name$="[person_id]"]');
                    const file = this.files[0];
                    const typeText = docTypeSelect.options[docTypeSelect.selectedIndex].text;
                    const typeVal = docTypeSelect.value;
                    // جلب رقم الملف العام بشكل آمن
                    const fileIdInputEl = document.querySelector('input[name="file_id_number"]');
                    const fileId = (fileIdInputEl && fileIdInputEl.value && fileIdInputEl.value !== 'undefined') ? fileIdInputEl.value : '';
                    if (!fileId || fileId === 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'رقم الملف العام غير متوفر أو غير صالح، لا يمكن رفع المرفق.' });
                        return;
                    }
                    const personId = idInput ? idInput.value : '';
                    const docName = `${typeVal}_${fileId}_${personId}${file.name.substring(file.name.lastIndexOf('.'))}`;
                    const personKey = personId || (form.querySelector('[data-upload-zone]')?.getAttribute('data-upload-zone') || 'main');
                    if (file.type.startsWith('image/')) {
                        if (typeof window.showCropperModal === 'function') {
                            window.showCropperModal(file, function(croppedFile) {
                                if (!croppedFile || !fileId || !personId) return;
                                // تحقق من اسم الصورة المقصوصة
                                if (croppedFile.name && croppedFile.name.includes('_cropped') && croppedFile.name !== 'undefined') {
                                    // احذف أي صورة أصلية أو مكررة لنفس الشخص ولنفس النوع
                                    let docsArr = window.allDocs.get(personKey) || [];
                                    docsArr = docsArr.filter(doc => {
                                        if (!doc.file) return true;
                                        // احذف أي صورة أصلية أو صورة مقصوصة بنفس الاسم
                                        if (doc.file.type.startsWith('image/')) {
                                            if (!doc.file.name.includes('_cropped')) return false;
                                            if (doc.file.name === croppedFile.name) return false;
                                        }
                                        return true;
                                    });
                                    window.allDocs.set(personKey, docsArr);
                                    // أضف الصورة المقصوصة
                                    const docObj = {
                                        type: typeVal,
                                        typeText: typeText,
                                        file: croppedFile,
                                        docName: docName,
                                        personId: personId,
                                        fileId: fileId
                                    };
                                    docsArr.push(docObj);
                                    window.allDocs.set(personKey, docsArr);
                                    documents.push(docObj);
                                    // تعريف دالة العرض مرة واحدة في الأعلى
                                    if (!window.attachmentsDisplayList) window.attachmentsDisplayList = [];
                                    window.addAttachmentDisplayEntry = function(docObj, parent) {
                                        let personName = '';
                                        let label = docObj.typeText;
                                        if (parent && parent.classList && parent.classList.contains('family-member-form')) {
                                            const firstName = parent.querySelector('[name$="[first_name]"]')?.value || '';
                                            const lastName = parent.querySelector('[name$="[last_name]"]')?.value || '';
                                            personName = `${firstName} ${lastName}`.trim();
                                        } else if (parent && parent.closest && parent.closest('#basic')) {
                                            const firstName = document.querySelector('[name="data_first_name"]')?.value || '';
                                            const lastName = document.querySelector('[name="data_family_name"]')?.value || '';
                                            personName = `${firstName} ${lastName}`.trim();
                                        } else if (parent && parent.closest && parent.closest('#deceased')) {
                                            const firstName = document.querySelector('[name="father_first_name"]')?.value || '';
                                            const lastName = document.querySelector('[name="father_last_name"]')?.value || '';
                                            personName = `${firstName} ${lastName}`.trim();
                                        } else if (
                                            (parent && parent.closest && parent.closest('#motherInfoSection')) ||
                                            (parent && parent.id === 'motherInfoSection') ||
                                            (document.querySelector('#motherInfoSection') && document.querySelector('#motherInfoSection').contains(parent))
                                        ) {
                                            // الأم المتوفية
                                            const firstName = document.querySelector('[name="mother_first_name"]')?.value || '';
                                            const lastName = document.querySelector('[name="mother_last_name"]')?.value || '';
                                            personName = `${firstName} ${lastName}`.trim();
                                            label = 'مرفق الأم المتوفية:';
                                        }
                                        window.attachmentsDisplayList.push({
                                            personName,
                                            label,
                                            doc: docObj.file,
                                            docName: docObj.docName
                                        });
                                    }
                                    // بعد إضافة docObj إلى window.allDocs و/أو documents، أضف إلى مصفوفة العرض
                                    window.addAttachmentDisplayEntry(docObj, parent);
                                    renderDocuments();
                                }
                                docTypeSelect.value = '';
                            });
                        }
                    } else {
                        // فقط أضف الملفات غير الصور إذا بياناتها مكتملة
                        if (!fileId || !personId) return;
                        const docObj = {
                            type: typeVal,
                            typeText: typeText,
                            file: file,
                            docName: docName,
                            personId: personId,
                            fileId: fileId
                        };
                        docsArr.push(docObj);
                        window.allDocs.set(personKey, docsArr);
                        documents.push(docObj);
                        // تعريف دالة العرض مرة واحدة في الأعلى
                        if (!window.attachmentsDisplayList) window.attachmentsDisplayList = [];
                        window.addAttachmentDisplayEntry = function(docObj, parent) {
                            let personName = '';
                            let label = docObj.typeText;
                            if (parent && parent.classList && parent.classList.contains('family-member-form')) {
                                const firstName = parent.querySelector('[name$="[first_name]"]')?.value || '';
                                const lastName = parent.querySelector('[name$="[last_name]"]')?.value || '';
                                personName = `${firstName} ${lastName}`.trim();
                            } else if (parent && parent.closest && parent.closest('#basic')) {
                                const firstName = document.querySelector('[name="data_first_name"]')?.value || '';
                                const lastName = document.querySelector('[name="data_family_name"]')?.value || '';
                                personName = `${firstName} ${lastName}`.trim();
                            } else if (parent && parent.closest && parent.closest('#deceased')) {
                                const firstName = document.querySelector('[name="father_first_name"]')?.value || '';
                                const lastName = document.querySelector('[name="father_last_name"]')?.value || '';
                                personName = `${firstName} ${lastName}`.trim();
                            } else if (
                                (parent && parent.closest && parent.closest('#motherInfoSection')) ||
                                (parent && parent.id === 'motherInfoSection') ||
                                (document.querySelector('#motherInfoSection') && document.querySelector('#motherInfoSection').contains(parent))
                            ) {
                                // الأم المتوفية
                                const firstName = document.querySelector('[name="mother_first_name"]')?.value || '';
                                const lastName = document.querySelector('[name="mother_last_name"]')?.value || '';
                                personName = `${firstName} ${lastName}`.trim();
                                label = 'مرفق الأم المتوفية:';
                            }
                            window.attachmentsDisplayList.push({
                                personName,
                                label,
                                doc: docObj.file,
                                docName: docObj.docName
                            });
                        }
                        // بعد إضافة docObj إلى window.allDocs و/أو documents، أضف إلى مصفوفة العرض
                        window.addAttachmentDisplayEntry(docObj, parent);
                        renderDocuments();
                        docTypeSelect.value = '';
                    }
                }
            });
        }

        // تفعيل رفع الملفات لكل فرد حالي عند تحميل الصفحة
        document.querySelectorAll('.family-member-form').forEach((form, idx) => {
            setupDocumentUploadHandlersForMember(form, idx);
        });

        // عند إضافة فرد جديد، اربط الأحداث له فقط
        const addFamilyMemberBtn = document.getElementById('addFamilyMember');
        if (addFamilyMemberBtn) {
            addFamilyMemberBtn.addEventListener('click', function() {
                setTimeout(() => {
                    const forms = document.querySelectorAll('.family-member-form');
                    const lastForm = forms[forms.length - 1];
                    setupDocumentUploadHandlersForMember(lastForm, forms.length - 1);
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
            const fatherFields = [
                { name: 'father_first_name' },
                { name: 'father_last_name' },
                { name: 'father_id' },
                { name: 'father_death_date' },
                { name: 'father_death_reason' },
            ];
            let invalid = validateTabFields('#deceased', fatherFields);
            if (invalid) {
                // فعّل تبويب المتوفين
                activateTab('deceased-tab');
                setTimeout(() => invalid.focus(), 200);
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى تعبئة جميع الحقول المطلوبة للأب.' });
                return;
            }
            // تحقق من الأم إذا كانت ظاهرة
            const motherSection = document.getElementById('motherInfoSection');
            if (motherSection && motherSection.style.display !== 'none') {
                const motherFields = [
                    { name: 'mother_first_name' },
                    { name: 'mother_last_name' },
                    { name: 'mother_id' },
                    { name: 'mother_death_date' },
                    { name: 'mother_death_reason' },
                ];
                invalid = validateTabFields('#motherInfoSection', motherFields);
                if (invalid) {
                    activateTab('deceased-tab');
                    setTimeout(() => invalid.focus(), 200);
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى تعبئة جميع الحقول المطلوبة للأم.' });
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
                const requiredFields = [
                    { name: 'first_name' },
                    { name: 'last_name' },
                    { name: 'person_birth_date' },
                    { name: 'person_gender' },
                ];
                requiredFields.forEach(field => {
                    const el = form.querySelector(`[name*="[${field.name}]"]`);
                    if (el && !el.value) invalid = el;
                });
            });
            if (invalid) {
                activateTab('family-members-tab');
                setTimeout(() => invalid.focus(), 200);
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى تعبئة جميع الحقول المطلوبة لأفراد الأسرة.' });
                return;
            }
        });
    }

    // يمكن تكرار نفس المنطق لأي تبويب آخر
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let invalid = false;
            form.querySelectorAll('[required]').forEach(function(input) {
                const style = window.getComputedStyle(input);
                if ((style.display === 'none' || input.offsetParent === null || input.disabled) && input.required) {
                    input.removeAttribute('required');
                    input.setAttribute('data-temp-required', '1');
                    invalid = true;
                }
            });
            if (invalid) {
                setTimeout(function() {
                    form.querySelectorAll('[data-temp-required]').forEach(function(input) {
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
        setTimeout(() => { input.focus && input.focus(); }, 200);
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
                        }, 200); // تأخير بسيط لضمان تحديث الحقول
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
                    if ((style.display === 'none' || input.offsetParent === null || input.disabled) && input.required) {
                        input.removeAttribute('required');
                        input.setAttribute('data-temp-required', '1');
                        invalid = true;
                    }
                });
                // بعد الإرسال، أعد required للحقول التي أزلناها مؤقتاً
                if (invalid) {
                    setTimeout(function() {
                        form.querySelectorAll('[data-temp-required]').forEach(function(input) {
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

            // حذف أي مرفقات قديمة من FormData
            Array.from(formData.keys()).forEach(key => {
                if (key.startsWith('attachments')) {
                    formData.delete(key);
                }
            });

            // جمع جميع المرفقات من window.allDocs بشكل ديناميكي لأي شخص أو بوابة
            let attachIndex = 0;
            if (window.allDocs && window.allDocs instanceof Map) {
                // أضف فقط الصور المقصوصة أو الملفات غير صور
                window.allDocs.forEach((docsArr, personKey) => {
                    docsArr.forEach(doc => {
                        if (doc.file && doc.file.type && doc.file.type.startsWith('image/')) {
                            if (doc.file.name && doc.file.name.includes('_cropped')) {
                                formData.append(`attachments[${attachIndex}][file]`, doc.file);
                                formData.append(`attachments[${attachIndex}][person_identity_number]`, doc.personId);
                                formData.append(`attachments[${attachIndex}][file_type]`, doc.type);
                                formData.append(`attachments[${attachIndex}][stored_file_name]`, doc.file.name);
                                formData.append(`attachments[${attachIndex}][file_id_number]`, doc.fileId || '');
                                attachIndex++;
                                console.log('🟢 إضافة صورة مقصوصة للإرسال:', doc.file.name);
                            }
                        } else if (doc.file) {
                            formData.append(`attachments[${attachIndex}][file]`, doc.file);
                            formData.append(`attachments[${attachIndex}][person_identity_number]`, doc.personId);
                            formData.append(`attachments[${attachIndex}][file_type]`, doc.type);
                            formData.append(`attachments[${attachIndex}][stored_file_name]`, doc.file.name);
                            formData.append(`attachments[${attachIndex}][file_id_number]`, doc.fileId || '');
                            attachIndex++;
                            console.log('🟢 إضافة ملف غير صورة للإرسال:', doc.file.name);
                        }
                    });
                });
            }

            // إرسال البيانات عبر AJAX
            fetch(mainForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'تم الحفظ', text: 'تم حفظ السجل بنجاح' });
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: data.error || 'حدث خطأ أثناء الحفظ' });
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ أثناء الحفظ' });
            });
        });
    });
</script>
@endpush
