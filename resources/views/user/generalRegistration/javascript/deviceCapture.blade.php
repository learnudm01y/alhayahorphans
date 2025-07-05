@push('scriptsCodeUserRegistration')
<script>
    // تكوين خاص بفتح معرض الصور على الهواتف المحمولة
    document.addEventListener('DOMContentLoaded', function() {
        // كائن للتعامل مع الهواتف المحمولة وضبط خيارات التقاط الصور
        window.DeviceImageCapture = {
            // كشف نوع الجهاز
            detectDeviceType: function() {
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
                    window.innerWidth <= 768 ||
                    ('ontouchstart' in window) ||
                    (navigator.maxTouchPoints > 0);

                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                const isAndroid = /Android/i.test(navigator.userAgent);

                return {
                    isMobile: isMobile,
                    isIOS: isIOS,
                    isAndroid: isAndroid
                };
            },

            // تكوين عنصر إدخال الملفات (input file) بناءً على نوع الجهاز
            configureFileInputForDevice: function(fileInput, deviceInfo) {
                if (!fileInput) return;

                if (deviceInfo.isMobile) {
                    // للهواتف المحمولة: إزالة خاصية "capture" للسماح بالوصول إلى المعرض
                    fileInput.accept = 'image/*';
                    fileInput.removeAttribute('capture'); // أهم تغيير: إزالة خاصية capture لفتح المعرض

                    console.log('📱 تم تكوين حقل الملفات للجوال: فتح معرض الصور');
                } else {
                    // للحواسيب: قبول الصور والملفات PDF
                    fileInput.accept = 'image/*,.pdf';
                    fileInput.removeAttribute('capture');
                    console.log('💻 تم تكوين حقل الملفات للكمبيوتر: الصور و PDF');
                }

                // إعادة الإشارة إلى عنصر الإدخال
                return fileInput;
            },

            // تطبيق التكوين على جميع حقول إدخال الملفات في الصفحة
            setupAllFileInputs: function() {
                const deviceInfo = this.detectDeviceType();
                const fileInputs = document.querySelectorAll('input[type="file"]');

                fileInputs.forEach(input => {
                    this.configureFileInputForDevice(input, deviceInfo);
                });

                console.log(`🔍 تم تكوين ${fileInputs.length} حقل إدخال ملفات للجهاز الحالي`);

                // إعداد مراقب للعناصر الجديدة المضافة للصفحة
                this.observeNewFileInputs();
            },

            // مراقبة إضافة حقول إدخال ملفات جديدة للصفحة
            observeNewFileInputs: function() {
                const deviceInfo = this.detectDeviceType();
                const observer = new MutationObserver(mutations => {
                    mutations.forEach(mutation => {
                        if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                            for (let i = 0; i < mutation.addedNodes.length; i++) {
                                const node = mutation.addedNodes[i];
                                // التحقق مما إذا كان العنصر المضاف هو حقل إدخال ملفات
                                if (node.nodeType === 1 && node.tagName === 'INPUT' && node.type === 'file') {
                                    this.configureFileInputForDevice(node, deviceInfo);
                                }
                                // التحقق من العناصر الفرعية
                                if (node.nodeType === 1 && node.querySelectorAll) {
                                    const inputs = node.querySelectorAll('input[type="file"]');
                                    inputs.forEach(input => {
                                        this.configureFileInputForDevice(input, deviceInfo);
                                    });
                                }
                            }
                        }
                    });
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        };

        // بدء التكوين لجميع الحقول عند تحميل الصفحة
        window.DeviceImageCapture.setupAllFileInputs();

        // تكوين حقول إضافية عند التفاعل مع الصفحة
        document.addEventListener('click', function(e) {
            // تأجيل التنفيذ قليلاً للسماح بإضافة العناصر للـ DOM
            setTimeout(() => {
                const fileInputs = document.querySelectorAll('input[type="file"]');
                const deviceInfo = window.DeviceImageCapture.detectDeviceType();

                fileInputs.forEach(input => {
                    window.DeviceImageCapture.configureFileInputForDevice(input, deviceInfo);
                });
            }, 100);
        });
    });
</script>
@endpush
