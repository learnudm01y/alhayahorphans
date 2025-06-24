@push('scriptsCodeUserRegistration')
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
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في العملية',
                            text: error.message || 'حدث خطأ غير متوقع',
                            footer: `<a href="#" onclick="ErrorTracker.showDetails('${this.logs.length - 1}')">عرض التفاصيل</a>`
                        });
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
                                idNumber = document.querySelector(`input[name="${parent}_id"]`)?.value ||
                                    '';
                            } else if (selectedPerson.startsWith('family_')) {
                                const index = selectedPerson.split('_')[1];
                                idNumber = document.querySelector(
                                    `input[name="family_members[${index}][person_id]"]`)?.value || '';
                            }
                        } catch (error) {
                            ErrorTracker.log('error', 'get_id_number', error, {
                                selectedPerson
                            });
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
                            ErrorTracker.log('error', 'store_doc_info', error, {
                                docId,
                                selectedPerson
                            });
                        }

                        // إعادة تعيين الحقول
                        $('#person_selector').val('');
                        $('#document_type').val('');
                        $('#document_file').val('');
                        $('#preview').addClass('d-none');
                        $('#preview img').attr('src', '');
                        $('.is-invalid').removeClass('is-invalid');
                        $('.invalid-feedback').remove();

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

            // تحقق كلمة المرور قبل الانتقال إلى تبويب أفراد الأسرة
            $(document).ready(function() {
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

                // عند محاولة الانتقال إلى تبويب أفراد الأسرة أو المتوفين
                $('#family-members-tab, #deceased-tab').on('click', function(e) {
                    const pass = passwordInput.value;
                    const passConfirm = passwordConfirmInput.value;
                    const isValid = /^\d{4}$/.test(pass) && pass === passConfirm;
                    if (!isValid) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'تنبيه',
                            text: 'يجب أن تتكون كلمة المرور من 4 أرقام فقط وأن تتطابق مع التأكيد.'
                        });
                        passwordInput.classList.add('is-invalid');
                        passwordConfirmInput.classList.add('is-invalid');
                        // إعادة المستخدم إلى تبويب البيانات الأساسية
                        $('#basic-tab').tab('show');
                    } else {
                        passwordInput.classList.remove('is-invalid');
                        passwordConfirmInput.classList.remove('is-invalid');
                    }
                });
            });
        });
    </script>
@endpush
