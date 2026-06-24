<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ============================
        // ErrorTracker: تتبع الأخطاء
        // ============================
        window.ErrorTracker = {
            logs: [],
            startTime: new Date(),

            log: function (level, step, error, context = {}) {
                const now = new Date();
                const errorInfo = {
                    timestamp: now.toISOString(),
                    level: level,
                    step: step,
                    message: error?.message || String(error),
                    stack: error?.stack || null,
                    context: {
                        ...context,
                        url: window.location.href,
                        userAgent: navigator.userAgent,
                        timeFromStart: (now - this.startTime) + 'ms'
                    }
                };
                this.logs.push(errorInfo);
                if (console[level]) {
                    console[level](`[${level.toUpperCase()}] ${step}:`, errorInfo);
                } else {
                    console.log(`[${level.toUpperCase()}] ${step}:`, errorInfo);
                }
                if (level === 'error') {
                    try {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في العملية',
                            text: error?.message || 'حدث خطأ غير متوقع',
                            footer: `<a href="#" onclick="ErrorTracker.showDetails(${this.logs.length - 1}); return false;">عرض التفاصيل</a>`
                        });
                    } catch (swalError) {
                        console.error('Error showing Swal:', swalError);
                    }
                }
            },

            showDetails: function (logIndex) {
                const error = this.logs[logIndex];
                if (!error) return;
                try {
                    Swal.fire({
                        title: 'تفاصيل الخطأ',
                        html: `
                            <div dir="ltr" class="text-start">
                                <pre style="text-align: left;">
                                خطوة: ${error.step}
                                وقت: ${error.timestamp}
                                الرسالة: ${error.message}
                                المتصفح: ${error.context.userAgent}
                                المسار (Stack): ${error.stack || 'غير متوفر'}
                                المزيد من المعلومات: ${JSON.stringify(error.context, null, 2)}
                                                            </pre>
                            </div>
                        `,
                        width: '800px'
                    });
                } catch (swalError) {
                    console.error('Error showing details in Swal:', swalError);
                }
            },

            getFullLog: function () {
                return this.logs;
            }
        };

        // ============================
        // إشعار المستخدم عند محاولة رفع ملف بدون تحديد بيانات
        // ============================
        const confirmBtn = document.getElementById('confirmUpload');
        const fileInput = document.getElementById('document_file');
        const personSelector = document.getElementById('person_selector');
        const docTypeSelect = document.getElementById('document_type');

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function (event) {
                event.preventDefault();
                try {
                    // تم حل المشكلة: لا تتحقق هنا من الملف إذا كان قد أضيف فعلاً إلى docs
                    if (typeof window.docs === 'object' && window.docs instanceof Map && window.docs.size > 0) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تمت إضافة المرفق',
                            text: 'سيتم إرسال الملف عند إرسال النموذج'
                        });
                        return;
                    }

                    // إذا لم يكن في docs، تحقق من input
                    if (!fileInput?.files?.length) {
                        Swal.fire({ icon: 'warning', title: 'لم يتم اختيار ملف', text: 'يرجى اختيار ملف قبل التأكيد' });
                        fileInput?.focus();
                        return;
                    }

                    const selectedPerson = personSelector?.value || '';
                    const docType = docTypeSelect?.value || '';

                    if (!selectedPerson || !docType) {
                        Swal.fire({ icon: 'warning', title: 'بيانات ناقصة', text: 'يرجى اختيار الشخص ونوع الوثيقة' });
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'تمت إضافة المرفق',
                        text: 'سيتم إرسال الملف عند إرسال النموذج'
                    });
                } catch (error) {
                    ErrorTracker.log('error', 'document_upload', error);
                }
            });
        }

        // ============================
        // إرسال النموذج بالطريقة العادية
        // ============================
        const form = (document.getElementById('main_form') || document.querySelector('form'));
        if (form) {
            form.addEventListener('submit', function () {
                ErrorTracker.log('info', 'form_submit', 'تم إرسال النموذج بالطريقة التقليدية');
            });
        }
    });
</script>
