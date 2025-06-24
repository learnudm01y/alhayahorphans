@push('scriptsCodeUserRegistration')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
@endpush
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
            const saveBtn = document.getElementById('saveToGoogleBtn');
            const idInput = document.getElementById('data_id_number') || document.querySelector(
                'input[name="data_id_number"]');
            const passInput = document.querySelector('input[name="user_password"]');
            if (saveBtn && idInput && passInput) {
                saveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!idInput.value) {
                        idInput.focus();
                        // استبدال التنبيه التقليدي بـ Swal.fire
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال رقم الهوية أولاً قبل حفظ كلمة المرور في جوجل.'
                        });
                        return;
                    }
                    if (!passInput.value) {
                        passInput.focus();
                        // استبدال التنبيه التقليدي بـ Swal.fire
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال كلمة المرور أولاً قبل الحفظ.'
                        });
                        return;
                    }
                    if (window.PasswordCredential) {
                        if (confirm(
                                'سيتم حفظ رقم الهوية كاسم مستخدم وكلمة المرور في مدير كلمات المرور في جوجل. هل تريد المتابعة؟'
                                )) {
                            const cred = new window.PasswordCredential({
                                id: idInput.value,
                                password: passInput.value,
                                name: idInput.value
                            });
                            navigator.credentials.store(cred).then(function() {
                                // استبدال التنبيه التقليدي بـ Swal.fire
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم',
                                    text: 'تم حفظ كلمة المرور في مدير كلمات المرور في المتصفح (جوجل).'
                                });
                            });
                        }
                    } else {
                        // استبدال التنبيه التقليدي بـ Swal.fire
                        Swal.fire({
                            icon: 'info',
                            title: 'معلومات',
                            text: 'هذه الميزة مدعومة فقط في بعض المتصفحات مثل جوجل كروم.'
                        });
                    }
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
                                    // تأثير زجاج يتكسر مع تلاشي لليمين
                                    card.style.transition =
                                        'filter 0.7s, opacity 0.7s, transform 0.7s, box-shadow 0.7s';
                                    card.style.filter =
                                        'blur(7px) grayscale(0.7) contrast(1.5) drop-shadow(0 0 12px #b3e5fc)';
                                    card.style.opacity = '0';
                                    card.style.transform =
                                        'scale(0.85) rotateZ(8deg) translateX(100px) skewY(6deg)';
                                    card.style.boxShadow =
                                        '0 0 60px 0 #00bcd44d, 0 0 0 2px #fff8';
                                    // إضافة تأثير "شروخ" عبر overlay SVG
                                    if (!card.querySelector('.glass-crack')) {
                                        const crack = document.createElement('div');
                                        crack.className = 'glass-crack';
                                        crack.style.position = 'absolute';
                                        crack.style.top = 0;
                                        crack.style.left = 0;
                                        crack.style.width = '100%';
                                        crack.style.height = '100%';
                                        crack.style.pointerEvents = 'none';
                                        crack.style.zIndex = 10;
                                        crack.innerHTML = `<svg width="100%" height="100%" viewBox="0 0 160 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <polyline points="10,10 80,80 150,10" stroke="#b3e5fc" stroke-width="2"/>
                                        <polyline points="30,60 80,80 130,60" stroke="#b3e5fc" stroke-width="1.5"/>
                                        <polyline points="80,80 80,180" stroke="#b3e5fc" stroke-width="1.2"/>
                                        <polyline points="40,120 80,80 120,120" stroke="#b3e5fc" stroke-width="1.2"/>
                                        </svg>`;
                                        card.style.position = 'relative';
                                        card.appendChild(crack);
                                    }
                                    setTimeout(function() {
                                        documents.splice(idx, 1);
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
                        // دمج رقم هوية الفرد في اسم الوثيقة
                        const personId = idInput ? idInput.value : '';
                        const docName =
                            `${typeVal}_${fileId}_${personId}${file.name.substring(file.name.lastIndexOf('.'))}`;
                        if (file.type.startsWith('image/')) {
                            if (typeof window.showCropperModal === 'function') {
                                window.showCropperModal(file, function(croppedFile) {
                                    documents.push({
                                        type: typeVal,
                                        typeText: typeText,
                                        file: croppedFile,
                                        docName: docName
                                    });
                                    renderDocuments();
                                    docTypeSelect.value = '';
                                });
                            }
                        } else {
                            documents.push({
                                type: typeVal,
                                typeText: typeText,
                                file: file,
                                docName: docName
                            });
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
</style>
<script>
    // تحسين دالة showCropperModal: أزرار تحكم كاملة، صورة داخل هاوبة، خطوط شبكة واضحة
    window.showCropperModal = function(file, callback) {
        const modalEl = document.getElementById('cropperModal');
        const cropperImage = document.getElementById('cropperImage');
        const cropBtn = document.getElementById('cropperCropBtn');
        let cropper = null;

        // إزالة أي حدث سابق لمنع التكرار
        cropBtn.onclick = null;

        // إضافة أزرار تحكم متقدمة
        let controls = document.getElementById('cropperControls');
        if (!controls) {
            controls = document.createElement('div');
            controls.id = 'cropperControls';
            controls.className = 'd-flex flex-wrap gap-2 justify-content-center mb-2';
            controls.innerHTML = `
                <button type="button" class="btn btn-outline-primary" id="cropperZoomIn"><i class="bi bi-zoom-in"></i> تكبير</button>
                <button type="button" class="btn btn-outline-primary" id="cropperZoomOut"><i class="bi bi-zoom-out"></i> تصغير</button>
                <button type="button" class="btn btn-outline-secondary" id="cropperRotateLeft"><i class="bi bi-arrow-counterclockwise"></i> تدوير يسار</button>
                <button type="button" class="btn btn-outline-secondary" id="cropperRotateRight"><i class="bi bi-arrow-clockwise"></i> تدوير يمين</button>
                <button type="button" class="btn btn-outline-warning" id="cropperReset"><i class="bi bi-arrow-repeat"></i> إعادة ضبط</button>
            `;
            modalEl.querySelector('.modal-body').prepend(controls);
        }

        // قراءة الصورة
        const reader = new FileReader();
        reader.onload = function(e) {
            cropperImage.src = e.target.result;
            cropperImage.onload = function() {
                if (cropper) cropper.destroy();
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
                    ready() {
                        // تكبير خطوط الشبكة والنقاط
                        const style = document.createElement('style');
                        style.id = 'cropperCustomGridStyle';
                        style.innerHTML = `
                            .cropper-point {
                                width: 18px !important;
                                height: 18px !important;
                                background: #0d6efd !important;
                                border: 2px solid #fff !important;
                            }
                            .cropper-line {
                                background-color: #0d6efd !important;
                                opacity: 0.8 !important;
                                height: 3px !important;
                            }
                            .cropper-center {
                                background: #ffc107 !important;
                                width: 12px !important;
                                height: 12px !important;
                            }
                            .cropper-view-box {
                                border: 3px solid #0d6efd !important;
                            }
                        `;
                        document.head.appendChild(style);
                    }
                });
            };
        };
        reader.readAsDataURL(file);

        // إظهار المودال
        let bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();

        // أزرار التحكم
        controls.querySelector('#cropperZoomIn').onclick = () => cropper && cropper.zoom(0.1);
        controls.querySelector('#cropperZoomOut').onclick = () => cropper && cropper.zoom(-0.1);
        controls.querySelector('#cropperRotateLeft').onclick = () => cropper && cropper.rotate(-45);
        controls.querySelector('#cropperRotateRight').onclick = () => cropper && cropper.rotate(45);
        controls.querySelector('#cropperReset').onclick = () => cropper && cropper.reset();

        // زر القص
        cropBtn.onclick = function() {
            if (!cropper || !cropperImage) return;
            const canvas = cropper.getCroppedCanvas({
                maxWidth: 2048,
                maxHeight: 2048,
                imageSmoothingQuality: 'high'
            });
            if (!canvas) return;
            canvas.toBlob(function(blob) {
                if (!blob) return;
                const croppedFile = new File([blob], file.name, {
                    type: file.type
                });
                bsModal.hide();
                cropper.destroy();
                cropper = null;
                // إزالة خطوط الشبكة المخصصة
                const style = document.getElementById('cropperCustomGridStyle');
                if (style) style.remove();
                // إزالة الصورة من هاوبة
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
                    let idInput = form.querySelector(
                        'input[name^="family_members["][name$="[person_id]"]');
                    const file = this.files[0];
                    const typeText = docTypeSelect.options[docTypeSelect.selectedIndex].text;
                    const typeVal = docTypeSelect.value;
                    const fileIdInput = form.querySelector('input[name="file_id_number"]') || document
                        .querySelector('input[name="file_id_number"]');
                    const fileId = fileIdInput ? fileIdInput.value : '';
                    const personId = idInput ? idInput.value : '';
                    const docName =
                        `${typeVal}_${fileId}_${personId}${file.name.substring(file.name.lastIndexOf('.'))}`;
                    if (file.type.startsWith('image/')) {
                        if (typeof window.showCropperModal === 'function') {
                            window.showCropperModal(file, function(croppedFile) {
                                documents.push({
                                    type: typeVal,
                                    typeText: typeText,
                                    file: croppedFile,
                                    docName: docName
                                });
                                renderDocuments();
                                docTypeSelect.value = '';
                            });
                        }
                    } else {
                        documents.push({
                            type: typeVal,
                            typeText: typeText,
                            file: file,
                            docName: docName
                        });
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
