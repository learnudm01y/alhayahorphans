<!-- إضافة Font Awesome للأيقونات -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Modal HTML -->
<div id="imageSourceModal" class="image-source-modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title text-white">
                <i class="fas fa-camera-retro modal-title-icon"></i>
                اختر مصدر الصورة
            </h3>
            <button class="modal-close" id="closeModal">
                <i class="fas fa-times close-icon" style="color: black; font-size: 1.4rem;"></i>
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

<!-- Hidden File Inputs للتكامل مع النظام الموجود -->
<input type="file" id="deviceCameraInput" accept="image/*" style="display: none;">
<input type="file" id="deviceGalleryInput" accept="image/*" style="display: none;">

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
 * نظام تحسين اختيار مصدر الصورة - يتكامل مع النظام الموجود
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 [DeviceImageSource] تم بدء تشغيل نظام تحسين اختيار مصدر الصورة');

    // متغيرات لتتبع التفاعل
    let isModalActive = false;
    let originalFileInput = null;
    let pendingCallback = null;

    // دالة كشف نوع الجهاز
    function detectDeviceType() {
        const userAgent = navigator.userAgent.toLowerCase();
        const isMobile = /android|webos|iphone|ipod|blackberry|iemobile|opera mini/i.test(userAgent);
        const isTablet = /ipad|tablet|(android(?!.*mobile))/i.test(userAgent);
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        const isSmallScreen = window.innerWidth <= 768;

        return {
            isMobile: isMobile || (isTouchDevice && isSmallScreen && !isTablet),
            isTablet: isTablet,
            isDesktop: !isMobile && !isTablet,
            isTouchDevice: isTouchDevice,
            hasCamera: navigator.mediaDevices && navigator.mediaDevices.getUserMedia,
            screenWidth: window.innerWidth,
            userAgent: userAgent
        };
    }

    // دالة تكوين input الكاميرا حسب نوع الجهاز
    function configureCameraInput(deviceInfo) {
        const cameraInput = document.getElementById('deviceCameraInput');

        cameraInput.removeAttribute('capture');

        if (deviceInfo.isMobile) {
            cameraInput.setAttribute('capture', 'environment');
            console.log('📱 [CameraConfig] تم تكوين الكاميرا للجوال');
        } else if (deviceInfo.isTablet) {
            cameraInput.setAttribute('capture', 'user');
            console.log('📱 [CameraConfig] تم تكوين الكاميرا للتابلت');
        } else {
            cameraInput.setAttribute('capture', 'user');
            console.log('💻 [CameraConfig] تم تكوين الكاميرا للكمبيوتر');
        }
    }

    // دالة تحسين زر الكاميرا حسب الجهاز
    function enhanceCameraButton(deviceInfo) {
        const cameraTitle = document.querySelector('#openCamera .btn-title');
        const cameraSubtitle = document.querySelector('#openCamera .btn-subtitle');

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
    function showImageSourceModal(callback) {
        pendingCallback = callback;
        isModalActive = true;

        const modal = document.getElementById('imageSourceModal');
        const deviceInfo = detectDeviceType();

        enhanceCameraButton(deviceInfo);
        configureCameraInput(deviceInfo);

        modal.style.display = 'block';

        setTimeout(() => {
            modal.classList.add('show');
        }, 10);

        console.log(`📱 [Modal] تم إظهار مودال اختيار مصدر الصورة للجهاز: ${deviceInfo.isMobile ? 'جوال' : deviceInfo.isTablet ? 'تابلت' : 'كمبيوتر'}`);
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
            isModalActive = false;
            originalFileInput = null;
            pendingCallback = null;
        }, 300);

        console.log('❌ [Modal] تم إخفاء مودال اختيار مصدر الصورة');
    }

    // دالة نقل الملف للنظام الموجود مع التكامل الصحيح
    function transferFileToSystem(file, source) {
        console.log(`🔄 [Transfer] نقل ملف من ${source}: ${file.name}`);

        // أولاً: إخفاء مودال اختيار المصدر
        hideImageSourceModal();

        // ثانياً: إذا كان هناك callback (من نظام documentUpload)، استخدمه
        if (pendingCallback && typeof pendingCallback === 'function') {
            console.log('✅ [Transfer] استدعاء callback للنظام الموجود (documentUpload)');
            // تأخير قصير للتأكد من إخفاء المودال قبل فتح القص
            setTimeout(() => {
                pendingCallback(file);
            }, 100);
        }
        // ثالثاً: إذا كان هناك input أصلي، انقل الملف إليه
        else if (originalFileInput) {
            console.log('✅ [Transfer] نقل إلى input الأصلي');
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            originalFileInput.files = dataTransfer.files;

            // إطلاق حدث change
            const changeEvent = new Event('change', { bubbles: true });
            originalFileInput.dispatchEvent(changeEvent);
        }
        // رابعاً: إذا لم يكن هناك callback ولا input، فتح القص مباشرة
        else {
            console.log('🔄 [Transfer] فتح القص مباشرة');
            if (typeof window.showCropperModal === 'function') {
                setTimeout(() => {
                    window.showCropperModal(file, function(croppedFile) {
                        if (croppedFile) {
                            console.log('✅ [Transfer] تم قص الصورة:', croppedFile.name);
                            // يمكن إضافة معالجة إضافية هنا حسب الحاجة
                        }
                    });
                }, 100);
            } else {
                console.warn('⚠️ [Transfer] أداة القص غير متوفرة');
            }
        }
    }

    // إعداد أحداث Modal
    function setupModalEvents() {
        const modal = document.getElementById('imageSourceModal');
        const closeBtn = document.getElementById('closeModal');
        const cameraBtn = document.getElementById('openCamera');
        const galleryBtn = document.getElementById('openGallery');
        const cameraInput = document.getElementById('deviceCameraInput');
        const galleryInput = document.getElementById('deviceGalleryInput');
        const overlay = modal.querySelector('.modal-overlay');

        // إغلاق Modal
        [closeBtn, overlay].forEach(element => {
            element.addEventListener('click', hideImageSourceModal);
        });

        // زر الكاميرا
        cameraBtn.addEventListener('click', function() {
            console.log('📸 [Camera] تم النقر على زر الكاميرا');
            cameraBtn.classList.add('loading');

            setTimeout(() => {
                cameraInput.click();
                cameraBtn.classList.remove('loading');
            }, 100);
        });

        // زر معرض الصور
        galleryBtn.addEventListener('click', function() {
            console.log('🖼️ [Gallery] تم النقر على زر معرض الصور');
            galleryBtn.classList.add('loading');

            setTimeout(() => {
                galleryInput.click();
                galleryBtn.classList.remove('loading');
            }, 100);
        });

        // معالجة اختيار الملف من الكاميرا
        cameraInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files.length > 0) {
                const file = e.target.files[0];
                console.log(`📸 [CameraInput] تم اختيار ملف: ${file.name}`);

                if (file.type.startsWith('image/')) {
                    transferFileToSystem(file, 'كاميرا');
                } else {
                    console.error('❌ [CameraInput] الملف ليس صورة');
                    alert('يرجى اختيار ملف صورة صحيح');
                }
            }
            e.target.value = '';
        });

        // معالجة اختيار الملف من معرض الصور
        galleryInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files.length > 0) {
                const file = e.target.files[0];
                console.log(`🖼️ [GalleryInput] تم اختيار ملف: ${file.name}`);

                if (file.type.startsWith('image/')) {
                    transferFileToSystem(file, 'معرض الصور');
                } else {
                    console.error('❌ [GalleryInput] الملف ليس صورة');
                    alert('يرجى اختيار ملف صورة صحيح');
                }
            }
            e.target.value = '';
        });

        // إغلاق بمفتاح Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isModalActive) {
                hideImageSourceModal();
            }
        });

        console.log('🎛️ [ModalEvents] تم إعداد جميع أحداث Modal');
    }

    // دالة تحسين تجربة رفع الملفات - فقط للأجهزة المحمولة
    function enhanceFileInputs() {
        const deviceInfo = detectDeviceType();

        // فقط للأجهزة المحمولة والتابلت
        if (!deviceInfo.isMobile && !deviceInfo.isTablet) {
            console.log('💻 [Enhancement] جهاز كمبيوتر - لا حاجة لتحسين رفع الملفات');
            return;
        }

        console.log('📱 [Enhancement] جهاز محمول/لوحي - تطبيق تحسينات رفع الملفات');

        // البحث عن جميع inputs الخاصة بالصور في النظام الموجود
        const selectors = [
            'input[type="file"][accept*="image"]',
            'input[type="file"].mainDocumentFileInput',
            'input[type="file"]#mainDocumentFileInput_main',
            'input[type="file"]#mainDocumentFileInput_father',
            'input[type="file"]#mainDocumentFileInput_mother',
            'input[type="file"][id^="mainDocumentFileInput_"]'
        ];

        const imageFileInputs = document.querySelectorAll(selectors.join(','));

        imageFileInputs.forEach((input, index) => {
            // تجنب معالجة inputs المودال الخاص بنا
            if (input.id === 'deviceCameraInput' || input.id === 'deviceGalleryInput') {
                return;
            }

            // إزالة المعالجات السابقة إذا كانت موجودة
            if (input.dataset.deviceEnhanced === 'true') {
                return;
            }

            console.log(`🔧 [Enhancement] تحسين input رقم ${index + 1}:`, input.id || input.className);

            // استبدال معالج النقر للأجهزة المحمولة فقط
            const originalClick = input.onclick;

            input.addEventListener('click', function(e) {
                // للأجهزة المحمولة: إظهار مودال الاختيار
                if (deviceInfo.isMobile || deviceInfo.isTablet) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log('🎯 [Enhancement] تم النقر على input محسن (جهاز محمول)، إظهار مودال الاختيار');

                    originalFileInput = input;
                    showImageSourceModal(function(selectedFile) {
                        console.log('📋 [Enhancement] استلام ملف من المودال:', selectedFile.name);

                        // نقل الملف إلى input الأصلي
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(selectedFile);
                        input.files = dataTransfer.files;

                        // تشغيل المعالج الأصلي إذا كان موجوداً
                        if (originalClick) {
                            try {
                                originalClick.call(input, e);
                            } catch (err) {
                                console.warn('تحذير: خطأ في تشغيل المعالج الأصلي:', err);
                            }
                        }

                        // إطلاق حدث change
                        const changeEvent = new Event('change', { bubbles: true });
                        input.dispatchEvent(changeEvent);

                        // إطلاق حدث input للتأكد من تحديث UI
                        const inputEvent = new Event('input', { bubbles: true });
                        input.dispatchEvent(inputEvent);
                    });
                }
                // للكمبيوتر: العمل العادي (لا تدخل)
            });

            // وضع علامة أن هذا input تم تحسينه
            input.dataset.deviceEnhanced = 'true';
        });

        // مراقبة إضافة inputs جديدة
        const observer = new MutationObserver(function(mutations) {
            let needsUpdate = false;

            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.querySelectorAll) {
                        const newInputs = node.querySelectorAll(selectors.join(','));
                        if (newInputs.length > 0) {
                            needsUpdate = true;
                        }
                    }
                });
            });

            if (needsUpdate) {
                console.log('🆕 [Enhancement] inputs جديدة تمت إضافتها، تطبيق التحسينات');
                setTimeout(() => enhanceFileInputs(), 100);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // تهيئة النظام
    function initializeSystem() {
        console.log('🎯 [Init] بدء تهيئة نظام تحسين اختيار مصدر الصورة');

        setupModalEvents();

        // انتظار تحميل أداة القص
        let cropperCheckCount = 0;
        const maxCropperChecks = 50;

        function waitForCropper() {
            if (window.showCropperModal || window.showCropper) {
                console.log('✅ [Init] أداة القص متوفرة');
                // بدء تحسين inputs بعد التأكد من توفر أداة القص
                setTimeout(() => {
                    enhanceFileInputs();
                }, 500);
                return true;
            }

            if (cropperCheckCount < maxCropperChecks) {
                cropperCheckCount++;
                setTimeout(waitForCropper, 100);
                return false;
            }

            console.warn('⚠️ [Init] أداة القص غير متوفرة بعد انتظار 5 ثوان، سيتم المتابعة بدونها');
            // المتابعة حتى لو لم تكن أداة القص متوفرة
            setTimeout(() => {
                enhanceFileInputs();
            }, 500);
            return false;
        }

        waitForCropper();

        console.log('✅ [Init] تم تهيئة النظام بنجاح');
    }

    // بدء التشغيل
    initializeSystem();

    // جعل الدوال متاحة عالمياً للاستخدام المباشر إذا لزم الأمر
    window.DeviceImageSource = {
        showModal: showImageSourceModal,
        hideModal: hideImageSourceModal,
        detectDevice: detectDeviceType,
        isActive: () => isModalActive,
        enhance: enhanceFileInputs
    };

    console.log('🎉 [DeviceImageSource] تم تشغيل النظام بنجاح');
});
</script>
