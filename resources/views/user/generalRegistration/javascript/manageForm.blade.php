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
                                        documents.splice(idx, 1);
                                        if (window.allDocs && window.allDocs instanceof Map) {
                                            const personKey = doc.personId || doc.personKey || 'main';
                                            if (window.allDocs.has(personKey)) {
                                                const arr = window.allDocs.get(personKey);
                                                for (let i = arr.length - 1; i >= 0; i--) {
                                                    if (
                                                        arr[i].docName === doc.docName &&
                                                        arr[i].type === doc.type &&
                                                        arr[i].personId === doc.personId &&
                                                        arr[i].fileId === doc.fileId
                                                    ) {
                                                        console.log('🗑️ حذف مرفق من window.allDocs:', arr[i]);
                                                        arr.splice(i, 1);
                                                    }
                                                }
                                                if (arr.length === 0) window.allDocs.delete(personKey);
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
                        // البحث عن رقم الهوية للفرد (أولوية أفراد الأسرة)
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
                        const file = this.files[0];
                        const typeText = docTypeSelect.options[docTypeSelect.selectedIndex].text;
                        const typeVal = docTypeSelect.value;
                        const fileIdInput = parent.querySelector('input[name="file_id_number"]') ||
                            document.querySelector('input[name="file_id_number"]');
                        const fileId = fileIdInput ? fileIdInput.value : '';
                        const personId = idInput ? idInput.value : '';
                        // لا تضف أو ترسل أي ملف إذا كان رقم الملف العام غير معرف أو فارغ
                        if (!fileId || fileId === 'undefined') return;
                        const docName = `${typeVal}_${fileId}_${personId}${file.name.substring(file.name.lastIndexOf('.'))}`;
                        const personKey = personId || (parent.querySelector('[data-upload-zone]')?.getAttribute('data-upload-zone') || 'main');
                        if (file.type.startsWith('image/')) {
                            if (typeof window.showCropperModal === 'function') {
                                window.showCropperModal(file, function(croppedFile) {
                                    if (!croppedFile || !fileId || !personId) return;
                                    // فقط بعد القص يتم التخزين
                                    const docObj = {
                                        type: typeVal,
                                        typeText: typeText,
                                        file: croppedFile,
                                        docName: docName,
                                        personId: personId,
                                        fileId: fileId
                                    };
                                    documents.push(docObj);
                                    if (!window.allDocs.has(personKey)) window.allDocs.set(personKey, []);
                                    window.allDocs.get(personKey).push(docObj);
                                    console.log('🟡 إضافة ملف للإرسال:', docObj);
                                    renderDocuments();
                                    docTypeSelect.value = '';
                                });
                            }
                        } else {
                            // لا يتم تخزين أي ملف صورة أصلية أبداً
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
                            documents.push(docObj);
                            if (!window.allDocs.has(personKey)) window.allDocs.set(personKey, []);
                            window.allDocs.get(personKey).push(docObj);
                            console.log('🟡 إضافة ملف للإرسال:', docObj);
                            renderDocuments();
                            docTypeSelect.value = '';
                        }
                    }
                });
            });
        });
    </script>
@endpush
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
    <div class="modal-dialog cropper-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- أزرار التحكم ستضاف دينامياً من دالة showCropperModal -->
                <img id="cropperImage" src="" alt="Image to crop" style="width: 100%; display: block;">
            </div>
            <div class="modal-footer d-flex flex-wrap justify-content-between">
                <button type="button" class="btn btn-primary" id="cropperCropBtn">قص وحفظ</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
            </div>
        </div>
    </div>
</div>
<style>
    @media (min-width: 768px) {
        .cropper-modal-dialog {
            max-width: 600px;
            width: 60vw;
        }
    }
    @media (max-width: 767.98px) {
        .cropper-modal-dialog {
            max-width: 98vw;
            min-width: 90vw;
            width: 98vw;
            margin: 0 auto;
        }
    }
    /* مركز الحاوية الخاصة بالصورة */
    .cropper-center-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 350px;
        background: #f8f9fa;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        margin-bottom: 60px; /* مباعدة إضافية من الأسفل */
        position: relative;
    }
    /* تكبير نقاط التحديد */
    .cropper-point {
        width: 18px !important;
        height: 18px !important;
        background: #2196f3 !important;
        border: 2px solid #fff !important;
        box-shadow: 0 0 6px #2196f3cc;
    }
    /* أزرار التحكم */
    .cropper-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
        margin-bottom: 1rem;
    }
    .cropper-controls button {
        min-width: 44px;
        min-height: 44px;
        font-size: 1.3rem;
        border-radius: 50%;
        border: none;
        background: #f1f3f4;
        color: #333;
        transition: background 0.2s;
        box-shadow: 0 1px 4px #0001;
    }
    .cropper-controls button:hover {
        background: #e3f2fd;
        color: #1976d2;
    }
</style>
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
            <button type="button" title="تدوير يمين" id="cropperRotateRight"><i class="fas fa-undo"></i></button>
            <button type="button" title="تدوير يسار" id="cropperRotateLeft"><i class="fas fa-redo"></i></button>
            <button type="button" title="قلب أفقي" id="cropperFlipH"><i class="fas fa-arrows-alt-h"></i></button>
            <button type="button" title="قلب عمودي" id="cropperFlipV"><i class="fas fa-arrows-alt-v"></i></button>
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
        controls.querySelector('#cropperRotateRight').onclick = () => cropper.rotate(45);
        controls.querySelector('#cropperRotateLeft').onclick = () => cropper.rotate(-45);
        controls.querySelector('#cropperFlipH').onclick = () => cropper.scaleX(cropper.getData().scaleX === 1 ? -1 : 1);
        controls.querySelector('#cropperFlipV').onclick = () => cropper.scaleY(cropper.getData().scaleY === 1 ? -1 : 1);
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
                                    // حذف من مصفوفة documents المحلية
                                    documents.splice(idx, 1);
                                    // حذف من window.allDocs أيضاً
                                    if (window.allDocs && window.allDocs instanceof Map) {
                                        const personKey = doc.personId || doc.personKey || 'main';
                                        if (window.allDocs.has(personKey)) {
                                            const arr = window.allDocs.get(personKey);
                                            const foundIdx = arr.findIndex(d => d.docName === doc.docName && d.type === doc.type && d.personId === doc.personId);
                                            if (foundIdx !== -1) {
                                                arr.splice(foundIdx, 1);
                                                if (arr.length === 0) window.allDocs.delete(personKey);
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
                                const docObj = {
                                    type: typeVal,
                                    typeText: typeText,
                                    file: croppedFile,
                                    docName: docName,
                                    personId: personId,
                                    fileId: fileId
                                };
                                documents.push(docObj);
                                if (!window.allDocs.has(personKey)) window.allDocs.set(personKey, []);
                                window.allDocs.get(personKey).push(docObj);
                                console.log('🟡 إضافة ملف للإرسال:', docObj);
                                renderDocuments();
                                docTypeSelect.value = '';
                            });
                        }
                    } else {
                        if (!fileId || !personId) return;
                        const docObj = {
                            type: typeVal,
                            typeText: typeText,
                            file: file,
                            docName: docName,
                            personId: personId,
                            fileId: fileId
                        };
                        documents.push(docObj);
                        if (!window.allDocs.has(personKey)) window.allDocs.set(personKey, []);
                        window.allDocs.get(personKey).push(docObj);
                        console.log('🟡 إضافة ملف للإرسال:', docObj);
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
                    // مثال: family_members[0][first_name]
                    const name = input.name;
                    const value = input.value;
                    if (name.startsWith('family_members[')) {
                        formData.append(name, value);
                    }
                });
            });

            // جمع جميع الملفات المقصوصة من جميع مناطق رفع الملفات
            document.querySelectorAll('.mainDocumentPreview').forEach(function(preview, idx) {
                // ابحث عن مصفوفة documents في كل منطقة
                if (window.documents && Array.isArray(window.documents)) {
                    window.documents.forEach(function(doc, docIdx) {
                        if (doc.file) {
                            formData.append(`attachments[${docIdx}][file]`, doc.file, doc.docName || doc.file.name);
                            formData.append(`attachments[${docIdx}][file_type]`, doc.type);
                            formData.append(`attachments[${docIdx}][person_identity_number]`, preview.closest('[data-upload-zone]')?.getAttribute('data-upload-zone') || 'main');
                            formData.append(`attachments[${docIdx}][file_id_number]`, document.querySelector('input[name="file_id_number"]').value || '');
                            formData.append(`attachments[${docIdx}][stored_file_name]`, doc.docName || doc.file.name);
                        }
                    });
                }
            });
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
