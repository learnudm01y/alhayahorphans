<!-- إضافة Font Awesome للأيقونات -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Modal HTML -->
<div id="imageSourceModal" class="image-source-modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-camera-retro modal-title-icon"></i>
                اختر مصدر الصورة
            </h3>
            <button class="modal-close" id="closeModal">
                <i class="fas fa-times close-icon"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="source-buttons">
                <button class="source-btn camera-btn" id="openCamera">
                    <div class="btn-icon">
                        <i class="fas fa-camera btn-main-icon"></i>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">التقاط صورة</span>
                        <span class="btn-subtitle">فتح الكاميرا</span>
                    </div>
                </button>

                <button class="source-btn gallery-btn" id="openGallery">
                    <div class="btn-icon">
                        <i class="fas fa-images btn-main-icon"></i>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">معرض الصور</span>
                        <span class="btn-subtitle">اختر من الصور المحفوظة</span>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden File Inputs -->
<input type="file" id="cameraInput" accept="image/*" style="display: none;">
<input type="file" id="galleryInput" accept="image/*" style="display: none;">

<style>
/* تصميم Modal متجاوب وجميل */
.image-source-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: none;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease-out;
}

.modal-content {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 90%;
    max-width: 500px;
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    border-radius: 20px 20px 0 0;
    box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.modal-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-title-icon {
    font-size: 1.4rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.5));
}

.modal-close {
    background: rgba(220, 53, 69, 0.8);
    border: 2px solid rgba(220, 53, 69, 0.9);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    backdrop-filter: blur(10px);
}

.modal-close:hover {
    background: rgba(220, 53, 69, 1);
    border-color: rgba(220, 53, 69, 1);
    transform: scale(1.15) rotate(90deg);
    box-shadow: 0 6px 20px rgba(220, 53, 69, 0.6);
}

.close-icon {
    font-size: 1.2rem;
    font-weight: 900;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
    filter: drop-shadow(0 0 4px rgba(255, 255, 255, 0.8));
    color: white;
}

.modal-body {
    padding: 25px 20px 30px;
}

.source-buttons {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.source-btn {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.source-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.camera-btn:hover {
    border-color: #28a745;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.gallery-btn:hover {
    border-color: #007bff;
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    color: white;
}

.btn-icon {
    font-size: 2.5rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(145deg, #f8f9fa, #e9ecef);
    transition: all 0.3s ease;
    box-shadow:
        inset 2px 2px 5px rgba(0, 0, 0, 0.1),
        inset -2px -2px 5px rgba(255, 255, 255, 0.8),
        0 4px 12px rgba(0, 0, 0, 0.15);
    border: 3px solid rgba(255, 255, 255, 0.8);
}

.btn-main-icon {
    font-weight: 900;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    filter: drop-shadow(0 0 6px rgba(0, 0, 0, 0.3));
    color: #495057;
}

.camera-btn:hover .btn-icon {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow:
        0 8px 20px rgba(40, 167, 69, 0.4),
        inset 0 0 15px rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.camera-btn:hover .btn-main-icon {
    color: white;
    text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5);
    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.8));
}

.gallery-btn:hover .btn-icon {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow:
        0 8px 20px rgba(0, 123, 255, 0.4),
        inset 0 0 15px rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.gallery-btn:hover .btn-main-icon {
    color: white;
    text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5);
    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.8));
}

/* أنيميشن */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        transform: translateX(-50%) translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
}

@keyframes slideDown {
    from {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
    to {
        transform: translateX(-50%) translateY(100%);
        opacity: 0;
    }
}

/* تصميم متجاوب للشاشات الكبيرة */
@media (min-width: 768px) {
    .modal-content {
        bottom: 33.33vh;
        border-radius: 20px;
        width: 80%;
        max-width: 600px;
    }

    .source-buttons {
        flex-direction: row;
        gap: 20px;
    }

    .source-btn {
        flex: 1;
        flex-direction: column;
        text-align: center;
        padding: 30px 20px;
        gap: 15px;
    }

    .btn-content {
        text-align: center;
    }

    .btn-icon {
        font-size: 3rem;
        width: 80px;
        height: 80px;
        border-width: 4px;
    }

    .btn-main-icon {
        font-size: 3.2rem;
    }

    .modal-title-icon {
        font-size: 1.6rem;
    }

    .modal-close {
        width: 45px;
        height: 45px;
        border-width: 3px;
        background: rgba(220, 53, 69, 0.85);
        border-color: rgba(220, 53, 69, 0.95);
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.5);
    }

    .modal-close:hover {
        background: rgba(220, 53, 69, 1);
        border-color: rgba(220, 53, 69, 1);
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.7);
    }

    .close-icon {
        font-size: 1.4rem;
    }
}

/* تأثيرات إضافية */
.source-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s ease;
}

.source-btn:hover::before {
    left: 100%;
}

/* حالة التحميل */
.source-btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

.source-btn.loading .btn-icon {
    animation: pulse 1.5s infinite;
    box-shadow:
        0 0 20px rgba(0, 123, 255, 0.6),
        inset 0 0 15px rgba(255, 255, 255, 0.3);
}

.source-btn.loading .btn-main-icon {
    color: #007bff;
    text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.3);
    filter: drop-shadow(0 0 12px rgba(0, 123, 255, 0.8));
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow:
            0 0 20px rgba(0, 123, 255, 0.6),
            inset 0 0 15px rgba(255, 255, 255, 0.3);
    }
    50% {
        transform: scale(1.1);
        box-shadow:
            0 0 30px rgba(0, 123, 255, 0.8),
            inset 0 0 20px rgba(255, 255, 255, 0.4);
    }
}

/* تأثيرات إضافية للأيقونات */
.btn-main-icon {
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.source-btn:active .btn-icon {
    transform: scale(0.95);
}

.source-btn:active .btn-main-icon {
    transform: scale(0.9);
}

/* تحسين الرؤية في الوضع المظلم */
@media (prefers-color-scheme: dark) {
    .btn-main-icon {
        color: #212529;
        text-shadow: 1px 1px 3px rgba(255, 255, 255, 0.3);
    }

    .modal-title-icon {
        filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.7));
    }

    .close-icon {
        filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.9));
        color: white;
    }

    .modal-close {
        background: rgba(220, 53, 69, 0.9);
        border-color: rgba(220, 53, 69, 1);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.6);
    }

    .modal-close:hover {
        background: rgba(220, 53, 69, 1);
        box-shadow: 0 6px 20px rgba(220, 53, 69, 0.8);
    }
}
</style>

<script>
        /**
         * نظام الكشف عن الجهاز وتوجيه مستعرض الصور - عام لجميع البوابات
         */
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 [DeviceDetection] تم بدء تشغيل نظام كشف الجهاز وتوجيه مستعرض الصور');

            // متغيرات Modal
            let currentFileInput = null;
            let currentCallback = null;

            // دالة كشف نوع الجهاز المحسنة
            function detectDeviceType() {
                const userAgent = navigator.userAgent.toLowerCase();
                const isMobile = /android|webos|iphone|ipod|blackberry|iemobile|opera mini/i.test(userAgent);
                const isTablet = /ipad|tablet|(android(?!.*mobile))/i.test(userAgent);
                const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
                const isSmallScreen = window.innerWidth <= 768;
                const hasCamera = navigator.mediaDevices && navigator.mediaDevices.getUserMedia;

                return {
                    isMobile: isMobile || (isTouchDevice && isSmallScreen && !isTablet),
                    isTablet: isTablet,
                    isDesktop: !isMobile && !isTablet,
                    isTouchDevice: isTouchDevice,
                    hasCamera: hasCamera,
                    screenWidth: window.innerWidth,
                    userAgent: userAgent
                };
            }

            // دالة تكوين input الكاميرا حسب نوع الجهاز
            function configureCameraInput(deviceInfo) {
                const cameraInput = document.getElementById('cameraInput');

                // إزالة الإعدادات السابقة
                cameraInput.removeAttribute('capture');

                if (deviceInfo.isMobile) {
                    // للجوال: إعطاء خيارات متعددة للكاميرا
                    cameraInput.setAttribute('capture', 'environment');
                    console.log('📱 [CameraConfig] تم تكوين الكاميرا للجوال: environment camera');
                } else if (deviceInfo.isTablet) {
                    // للتابلت: كاميرا أمامية أو خلفية
                    cameraInput.setAttribute('capture', 'user');
                    console.log('📱 [CameraConfig] تم تكوين الكاميرا للتابلت: user camera');
                } else {
                    // للكمبيوتر: إضافة capture للوصول للكاميرا
                    cameraInput.setAttribute('capture', 'user');
                    console.log('💻 [CameraConfig] تم تكوين الكاميرا للكمبيوتر: user camera');
                }
            }

            // دالة فحص توفر الكاميرا
            async function checkCameraAvailability() {
                try {
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        console.warn('⚠️ [Camera] getUserMedia غير مدعوم في هذا المتصفح');
                        return false;
                    }

                    // فحص الأجهزة المتاحة
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(device => device.kind === 'videoinput');

                    console.log(`📹 [Camera] تم العثور على ${videoDevices.length} كاميرا متاحة`);

                    if (videoDevices.length > 0) {
                        videoDevices.forEach((device, index) => {
                            console.log(`📹 [Camera${index + 1}] ${device.label || `كاميرا ${index + 1}`}`);
                        });
                        return true;
                    }

                    return false;
                } catch (error) {
                    console.warn('⚠️ [Camera] خطأ في فحص الكاميرات:', error);
                    return false;
                }
            }

            // دالة تحسين زر الكاميرا حسب الجهاز
            function enhanceCameraButton(deviceInfo) {
                const cameraBtn = document.getElementById('openCamera');
                const cameraTitle = cameraBtn.querySelector('.btn-title');
                const cameraSubtitle = cameraBtn.querySelector('.btn-subtitle');

                if (deviceInfo.isMobile) {
                    cameraTitle.textContent = 'التقاط صورة';
                    cameraSubtitle.textContent = 'فتح كاميرا الجوال';
                } else if (deviceInfo.isTablet) {
                    cameraTitle.textContent = 'التقاط صورة';
                    cameraSubtitle.textContent = 'فتح كاميرا التابلت';
                } else {
                    cameraTitle.textContent = 'التقاط صورة';
                    cameraSubtitle.textContent = 'فتح كاميرا الكمبيوتر';
                }
            }

            // دالة إظهار Modal
            function showImageSourceModal(fileInput, callback) {
                currentFileInput = fileInput;
                currentCallback = callback;

                const modal = document.getElementById('imageSourceModal');
                const deviceInfo = detectDeviceType();

                // تحسين واجهة الأزرار حسب نوع الجهاز
                enhanceCameraButton(deviceInfo);

                modal.style.display = 'block';

                // تأثير الظهور
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);

                console.log(`📱 [Modal] تم إظهار نافذة اختيار مصدر الصورة للجهاز: ${deviceInfo.isMobile ? 'جوال' : deviceInfo.isTablet ? 'تابلت' : 'كمبيوتر'}`);
            }

            // دالة إخفاء Modal
            function hideImageSourceModal() {
                const modal = document.getElementById('imageSourceModal');
                const modalContent = modal.querySelector('.modal-content');

                modalContent.style.animation = 'slideDown 0.3s ease-out forwards';

                setTimeout(() => {
                    modal.style.display = 'none';
                    modalContent.style.animation = '';
                    modal.classList.remove('show');
                }, 300);

                console.log('❌ [Modal] تم إخفاء نافذة اختيار مصدر الصورة');
            }

            // دالة نقل الملف إلى input الأصلي مع دعم المقص
            function transferFileToOriginalInput(file, sourceType) {
                if (!file || !currentFileInput) {
                    console.error('❌ [FileTransfer] لا يوجد ملف أو input أصلي');
                    return false;
                }

                try {
                    console.log(`🔄 [FileTransfer] بدء معالجة ملف ${sourceType}: ${file.name}`);

                    // التحقق من أن الملف صورة وفتح المقص
                    if (file.type.startsWith('image/')) {
                        console.log('🖼️ [FileTransfer] الملف صورة، فتح أداة المقص...');

                        // فحص توفر أداة المقص
                        if (typeof window.showCropperModal === 'function') {
                            window.showCropperModal(file, function(croppedFile) {
                                if (croppedFile) {
                                    console.log('✅ [FileTransfer] تم قص الصورة بنجاح:', croppedFile.name);
                                    // نقل الملف المقصوص إلى input الأصلي
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(croppedFile);
                                    currentFileInput.files = dataTransfer.files;

                                    // تشغيل callback إذا كان موجود
                                    if (typeof currentCallback === 'function') {
                                        currentCallback(croppedFile);
                                    }

                                    // إطلاق حدث change على input الأصلي
                                    const changeEvent = new Event('change', { bubbles: true });
                                    currentFileInput.dispatchEvent(changeEvent);

                                    // إطلاق حدث input للتأكد من تحديث UI
                                    const inputEvent = new Event('input', { bubbles: true });
                                    currentFileInput.dispatchEvent(inputEvent);

                                    console.log(`✅ [FileTransfer] تم نقل ملف ${sourceType} مقصوص: ${croppedFile.name}`);
                                } else {
                                    console.log('❌ [FileTransfer] تم إلغاء قص الصورة');
                                }
                            });
                        } else {
                            console.warn('⚠️ [FileTransfer] أداة المقص غير متوفرة، نقل الصورة مباشرة');
                            // نقل الملف مباشرة بدون قص
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            currentFileInput.files = dataTransfer.files;

                            if (typeof currentCallback === 'function') {
                                currentCallback(file);
                            }

                            const changeEvent = new Event('change', { bubbles: true });
                            currentFileInput.dispatchEvent(changeEvent);

                            const inputEvent = new Event('input', { bubbles: true });
                            currentFileInput.dispatchEvent(inputEvent);

                            console.log(`✅ [FileTransfer] تم نقل ملف ${sourceType}: ${file.name}`);
                        }
                    } else {
                        // ملف غير صورة، نقل مباشر
                        console.log('📄 [FileTransfer] الملف ليس صورة، نقل مباشر');
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        currentFileInput.files = dataTransfer.files;

                        if (typeof currentCallback === 'function') {
                            currentCallback(file);
                        }

                        const changeEvent = new Event('change', { bubbles: true });
                        currentFileInput.dispatchEvent(changeEvent);

                        const inputEvent = new Event('input', { bubbles: true });
                        currentFileInput.dispatchEvent(inputEvent);

                        console.log(`✅ [FileTransfer] تم نقل ملف ${sourceType}: ${file.name}`);
                    }

                    return true;
                } catch (error) {
                    console.error('❌ [FileTransfer] خطأ في نقل الملف:', error);
                    return false;
                }
            }

            // تحديث دالة تكوين input file
            function configureFileInputForDevice(fileInput, deviceInfo) {
                if (!fileInput) return;

                // التحقق من أن input لم يتم تكوينه مسبقاً
                if (fileInput.getAttribute('data-device-configured') === 'true') {
                    return;
                }

                // إزالة الإعدادات السابقة
                fileInput.removeAttribute('capture');
                fileInput.removeAttribute('accept');

                // تطبيق إعدادات الصور فقط لجميع الأجهزة
                fileInput.setAttribute('accept', 'image/*');

                // إزالة event listeners السابقة إذا كانت موجودة
                const newInput = fileInput.cloneNode(true);
                fileInput.parentNode.replaceChild(newInput, fileInput);

                // إضافة حدث النقر لإظهار Modal
                newInput.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log(`🔄 [InputClick] تم النقر على input: ${this.id || this.className}`);
                    showImageSourceModal(this);
                });

                newInput.setAttribute('data-device-configured', 'true');
                newInput.setAttribute('data-device-type', deviceInfo.isMobile ? 'mobile' :
                    deviceInfo.isTablet ? 'tablet' : 'desktop');

                console.log(`✅ [ConfigureInput] تم تكوين input مع Modal للصور فقط: ${newInput.id || newInput.className}`);

                return newInput;
            }

            // دالة تطبيق التكوين على جميع inputs في الصفحة
            function configureAllFileInputs() {
                const deviceInfo = detectDeviceType();
                console.log(`🔍 [ConfigureAll] معلومات الجهاز:`, deviceInfo);

                const fileInputSelectors = [
                    '#mainDocumentFileInput_main',
                    '.mainDocumentFileInput',
                    '#mainDocumentFileInput_father',
                    '#mainDocumentFileInput_mother',
                    '[id^="mainDocumentFileInput_"]',
                    'input[type="file"]:not(#cameraInput):not(#galleryInput)'
                ];

                let configuredCount = 0;

                fileInputSelectors.forEach(selector => {
                    const inputs = document.querySelectorAll(selector);
                    inputs.forEach(input => {
                        if (!input.getAttribute('data-device-configured')) {
                            configureFileInputForDevice(input, deviceInfo);
                            configuredCount++;
                        }
                    });
                });

                console.log(`✅ [ConfigureAll] تم تكوين ${configuredCount} input file`);
                return configuredCount;
            }

            // إعداد أحداث Modal محسنة
            function setupModalEvents() {
                const modal = document.getElementById('imageSourceModal');
                const closeBtn = document.getElementById('closeModal');
                const cameraBtn = document.getElementById('openCamera');
                const galleryBtn = document.getElementById('openGallery');
                const cameraInput = document.getElementById('cameraInput');
                const galleryInput = document.getElementById('galleryInput');
                const overlay = modal.querySelector('.modal-overlay');

                // إغلاق Modal
                [closeBtn, overlay].forEach(element => {
                    element.addEventListener('click', hideImageSourceModal);
                });

                // زر الكاميرا محسن
                cameraBtn.addEventListener('click', async function() {
                    console.log('📸 [Camera] تم النقر على زر الكاميرا');
                    cameraBtn.classList.add('loading');

                    try {
                        const deviceInfo = detectDeviceType();

                        // تكوين الكاميرا حسب نوع الجهاز
                        configureCameraInput(deviceInfo);

                        // إغلاق Modal فوراً قبل فتح الكاميرا
                        hideImageSourceModal();

                        // فحص توفر الكاميرا
                        const cameraAvailable = await checkCameraAvailability();

                        if (!cameraAvailable && deviceInfo.isDesktop) {
                            console.warn('⚠️ [Camera] لم يتم العثور على كاميرا، سيتم فتح منتقي الملفات');
                        }

                        setTimeout(() => {
                            cameraInput.click();
                            cameraBtn.classList.remove('loading');
                        }, 100);

                    } catch (error) {
                        console.error('❌ [Camera] خطأ في تفعيل الكاميرا:', error);
                        cameraBtn.classList.remove('loading');

                        // في حالة الخطأ، افتح منتقي الملفات العادي
                        setTimeout(() => {
                            cameraInput.click();
                        }, 100);
                    }
                });

                // زر معرض الصور
                galleryBtn.addEventListener('click', function() {
                    console.log('🖼️ [Gallery] تم النقر على زر معرض الصور');
                    galleryBtn.classList.add('loading');

                    // إغلاق Modal فوراً قبل فتح معرض الصور
                    hideImageSourceModal();

                    setTimeout(() => {
                        galleryInput.click();
                        galleryBtn.classList.remove('loading');
                    }, 100);
                });

                // معالجة اختيار الملف من الكاميرا
                cameraInput.addEventListener('change', function(e) {
                    console.log('📸 [CameraInput] تم تغيير ملف الكاميرا');

                    if (e.target.files && e.target.files.length > 0) {
                        const file = e.target.files[0];
                        const deviceInfo = detectDeviceType();

                        console.log(`📸 [CameraInput] تم اختيار ملف من ${deviceInfo.isMobile ? 'الجوال' : deviceInfo.isTablet ? 'التابلت' : 'الكمبيوتر'}: ${file.name}, الحجم: ${file.size} bytes`);

                        // التحقق من أن الملف صورة
                        if (file.type.startsWith('image/')) {
                            // استدعاء transferFileToOriginalInput التي ستفتح المقص تلقائياً
                            transferFileToOriginalInput(file, 'كاميرا');
                        } else {
                            console.error('❌ [CameraInput] الملف المختار ليس صورة');
                            alert('يرجى اختيار ملف صورة صحيح');
                        }
                    }

                    // تنظيف input
                    e.target.value = '';
                });

                // معالجة اختيار الملف من معرض الصور
                galleryInput.addEventListener('change', function(e) {
                    console.log('🖼️ [GalleryInput] تم تغيير ملف المعرض');

                    if (e.target.files && e.target.files.length > 0) {
                        const file = e.target.files[0];
                        console.log(`🖼️ [GalleryInput] تم اختيار ملف: ${file.name}, الحجم: ${file.size} bytes`);

                        // التحقق من أن الملف صورة
                        if (file.type.startsWith('image/')) {
                            // استدعاء transferFileToOriginalInput التي ستفتح المقص تلقائياً
                            transferFileToOriginalInput(file, 'معرض الصور');
                        } else {
                            console.error('❌ [GalleryInput] الملف المختار ليس صورة');
                            alert('يرجى اختيار ملف صورة صحيح');
                        }
                    }

                    // تنظيف input
                    e.target.value = '';
                });

                // إغلاق بمفتاح Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && modal.style.display === 'block') {
                        hideImageSourceModal();
                    }
                });

                console.log('🎛️ [ModalEvents] تم إعداد جميع أحداث Modal مع دعم الكاميرا والمقص');
            }

            // مراقبة إضافة عناصر جديدة (للبوابات الديناميكية)
            function setupDynamicFileInputMonitoring() {
                const observer = new MutationObserver(function(mutations) {
                    let newInputsFound = false;

                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === Node.ELEMENT_NODE) {
                                    const newFileInputs = node.querySelectorAll(
                                        'input[type="file"]:not(#cameraInput):not(#galleryInput)');

                                    if (newFileInputs.length > 0) {
                                        newInputsFound = true;
                                        const deviceInfo = detectDeviceType();

                                        newFileInputs.forEach(input => {
                                            if (!input.getAttribute('data-device-configured')) {
                                                configureFileInputForDevice(input, deviceInfo);
                                                console.log(
                                                    `🆕 [DynamicMonitor] تم تكوين input جديد:`,
                                                    input.id || input.className);
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    });
                });

                const containersToWatch = [
                    document.getElementById('familyMembersContainer'),
                    document.getElementById('main_form'),
                    document.body
                ].filter(container => container !== null);

                containersToWatch.forEach(container => {
                    observer.observe(container, {
                        childList: true,
                        subtree: true
                    });
                });

                console.log(`👁️ [DynamicMonitor] تم تفعيل مراقبة العناصر الديناميكية`);
                return observer;
            }

            // إعادة التكوين عند تغيير حجم الشاشة
            function setupResponsiveReconfiguration() {
                let resizeTimeout;

                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(function() {
                        console.log(
                            `📐 [Responsive] تغيير حجم الشاشة إلى: ${window.innerWidth}x${window.innerHeight}`
                        );

                        document.querySelectorAll('input[type="file"][data-device-configured]:not(#cameraInput):not(#galleryInput)')
                            .forEach(input => {
                                input.removeAttribute('data-device-configured');
                            });

                        const reConfiguredCount = configureAllFileInputs();
                        console.log(
                            `🔄 [Responsive] تم إعادة تكوين ${reConfiguredCount} input بعد تغيير الحجم`
                        );
                    }, 300);
                });
            }

            // إعادة التكوين عند تدوير الجهاز
            function setupOrientationChangeHandling() {
                window.addEventListener('orientationchange', function() {
                    setTimeout(function() {
                        console.log(`🔄 [Orientation] تغيير اتجاه الجهاز`);

                        document.querySelectorAll('input[type="file"][data-device-configured]:not(#cameraInput):not(#galleryInput)')
                            .forEach(input => {
                                input.removeAttribute('data-device-configured');
                            });

                        const reConfiguredCount = configureAllFileInputs();
                        console.log(
                            `✅ [Orientation] تم إعادة تكوين ${reConfiguredCount} input بعد تدوير الجهاز`
                        );
                    }, 500);
                });
            }

            // دالة تنظيف المتغيرات
            function resetModalState() {
                currentFileInput = null;
                currentCallback = null;
                console.log('🧹 [Reset] تم تنظيف حالة Modal');
            }

            // تشغيل النظام
            console.log('🎯 [Init] بدء تهيئة نظام كشف الجهاز...');

            // انتظار تحميل أداة المقص
            function waitForCropper() {
                if (typeof window.showCropperModal === 'function') {
                    console.log('✅ [Init] أداة المقص متوفرة ومتصلة');
                } else {
                    console.warn('⚠️ [Init] أداة المقص غير متوفرة، سيتم المحاولة مرة أخرى...');
                    setTimeout(waitForCropper, 500);
                }
            }
            waitForCropper();

            // فحص توفر الكاميرا عند التحميل
            checkCameraAvailability().then(available => {
                if (available) {
                    console.log('✅ [Init] الكاميرا متاحة ومدعومة');
                } else {
                    console.log('⚠️ [Init] الكاميرا غير متاحة أو غير مدعومة');
                }
            });

            setupModalEvents();
            const initialConfiguredCount = configureAllFileInputs();
            setupDynamicFileInputMonitoring();
            setupResponsiveReconfiguration();
            setupOrientationChangeHandling();

            // جعل الدوال متاحة عالمياً
            window.DeviceImageCapture = {
                detectDeviceType,
                configureFileInputForDevice,
                configureAllFileInputs,
                showImageSourceModal,
                hideImageSourceModal,
                transferFileToOriginalInput,
                resetModalState,
                checkCameraAvailability,
                configureCameraInput,
                enhanceCameraButton,
                reconfigure: function() {
                    document.querySelectorAll('input[type="file"][data-device-configured]:not(#cameraInput):not(#galleryInput)').forEach(
                        input => {
                            input.removeAttribute('data-device-configured');
                        });
                    resetModalState();
                    return configureAllFileInputs();
                }
            };

            console.log(`🎉 [SystemReport] تم تشغيل النظام بنجاح! تم تكوين ${initialConfiguredCount} input file مع دعم كاميرا محسن`);
        });
</script>
