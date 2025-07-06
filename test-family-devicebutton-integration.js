/**
 * اختبار تكامل DeviceImageSource الأصلي مع بوابة أفراد الأسرة
 * للتأكد من أن المودال الأصلي من deviceTypeOpenButton.blade.php يعمل
 */

console.log('🧪 [اختبار المودال الأصلي] بدء اختبار DeviceImageSource الأصلي مع أفراد الأسرة...');

// دالة اختبار شاملة للمودال الأصلي
function testOriginalDeviceModal() {
    console.log('🔍 [اختبار] فحص حالة DeviceImageSource الأصلي...');

    // 1. فحص وجود DeviceImageSource
    if (!window.DeviceImageSource) {
        console.error('❌ DeviceImageSource غير متوفر');
        return false;
    }

    console.log('✅ DeviceImageSource متوفر:', {
        enhance: !!window.DeviceImageSource.enhance,
        showModal: !!window.DeviceImageSource.showModal,
        detectDevice: !!window.DeviceImageSource.detectDevice,
        isActive: !!window.DeviceImageSource.isActive
    });

    // 2. فحص وجود المودال الأصلي
    const originalModal = document.getElementById('imageSourceModal');
    if (!originalModal) {
        console.error('❌ المودال الأصلي (imageSourceModal) غير موجود');
        return false;
    }

    console.log('✅ المودال الأصلي موجود:', {
        display: originalModal.style.display,
        visibility: originalModal.style.visibility,
        className: originalModal.className
    });

    // 3. التأكد من عدم وجود المودال البديل
    const simpleModal = document.getElementById('familyMobileModal');
    if (simpleModal) {
        console.log('🚫 إزالة المودال البديل إذا كان موجوداً');
        simpleModal.remove();
    }

    // 4. فحص نماذج أفراد الأسرة
    const familyForms = document.querySelectorAll('.family-member-form:not(.d-none)');
    console.log(`📊 عدد نماذج أفراد الأسرة: ${familyForms.length}`);

    if (familyForms.length === 0) {
        console.log('📝 إضافة فرد أسرة للاختبار...');
        if (window.addFamilyMember) {
            window.addFamilyMember();
            setTimeout(() => testOriginalDeviceModal(), 2000);
            return;
        }
    }

    // 5. اختبار الفرد الأول
    const firstForm = familyForms[0];
    const docSelect = firstForm.querySelector('.mainDocumentTypeSelect');
    const fileInput = firstForm.querySelector('.mainDocumentFileInput');

    if (!docSelect || !fileInput) {
        console.error('❌ لم يتم العثور على عناصر النموذج');
        return false;
    }

    // 6. التحقق من تطبيق التحسينات
    console.log('🔍 فحص تطبيق تحسينات DeviceImageSource:', {
        fileInputEnhanced: fileInput.dataset.deviceEnhanced === 'true',
        fileInputId: fileInput.id,
        hasCanUploadFunction: !!firstForm._familyCanUploadFunction
    });

    // 7. إضافة خيار اختبار
    if (!docSelect.querySelector('option[value="ORIGINAL_TEST"]')) {
        const testOption = document.createElement('option');
        testOption.value = 'ORIGINAL_TEST';
        testOption.textContent = 'اختبار المودال الأصلي';
        docSelect.appendChild(testOption);
    }

    // 8. محاكاة رقم هوية
    const idInput = firstForm.querySelector('input[name$="[person_id]"]');
    if (idInput && !idInput.value) {
        idInput.value = '1234567890';
        console.log('✅ تم إضافة رقم هوية للاختبار');
    }

    // 9. محاكاة اختيار نوع الوثيقة
    console.log('🎯 محاكاة اختيار نوع الوثيقة...');
    docSelect.value = 'ORIGINAL_TEST';

    const changeEvent = new Event('change', { bubbles: true });
    docSelect.dispatchEvent(changeEvent);

    // 10. التحقق من النتيجة
    setTimeout(() => {
        const modal = document.getElementById('imageSourceModal');

        if (modal && (modal.style.display === 'block' || modal.classList.contains('show'))) {
            console.log('🎉 نجح الاختبار! تم فتح المودال الأصلي من deviceTypeOpenButton');

            // فحص محتويات المودال
            const cameraBtn = modal.querySelector('#openCamera');
            const galleryBtn = modal.querySelector('#openGallery');
            const closeBtn = modal.querySelector('#closeModal');

            console.log('📋 محتويات المودال:', {
                cameraButton: !!cameraBtn,
                galleryButton: !!galleryBtn,
                closeButton: !!closeBtn,
                modalTitle: modal.querySelector('.modal-title')?.textContent
            });

            // إغلاق المودال
            if (closeBtn) {
                setTimeout(() => {
                    closeBtn.click();
                    console.log('🔄 تم إغلاق المودال');
                }, 1000);
            }

            return true;
        } else {
            console.log('⚠️ المودال الأصلي لم يظهر، فحص الأسباب...');

            // فحص تشخيصي
            const deviceInfo = window.DeviceImageSource?.detectDevice?.();
            if (deviceInfo) {
                console.log('📱 معلومات الجهاز:', deviceInfo);

                if (!deviceInfo.isMobile && !deviceInfo.isTablet) {
                    console.log('💻 جهاز مكتبي - المودال قد لا يظهر حسب التصميم');
                }
            }

            // محاولة فتح المودال مباشرة
            if (window.DeviceImageSource?.showModal) {
                console.log('🔧 محاولة فتح المودال مباشرة...');
                window.DeviceImageSource.showModal(function(file) {
                    console.log('✅ تم استلام ملف من المودال:', file?.name);
                });
            }

            return false;
        }
    }, 1000);
}

// دالة لفرض تطبيق التحسينات
function forceEnhanceDeviceImageSource() {
    console.log('🔧 [فرض التحسينات] تطبيق تحسينات DeviceImageSource...');

    if (window.DeviceImageSource && window.DeviceImageSource.enhance) {
        window.DeviceImageSource.enhance();

        setTimeout(() => {
            const familyInputs = document.querySelectorAll('.family-member-form input[type="file"]');
            const enhancedInputs = Array.from(familyInputs).filter(input => input.dataset.deviceEnhanced === 'true');

            console.log('📊 نتائج التحسين:', {
                totalInputs: familyInputs.length,
                enhancedInputs: enhancedInputs.length,
                successRate: familyInputs.length > 0 ? `${(enhancedInputs.length / familyInputs.length * 100).toFixed(1)}%` : '0%'
            });

            if (enhancedInputs.length === 0 && familyInputs.length > 0) {
                console.warn('⚠️ لم يتم تطبيق التحسينات على أي input، قد تحتاج لإعادة تحميل الصفحة');
            }
        }, 1000);
    } else {
        console.error('❌ DeviceImageSource.enhance غير متوفر');
    }
}

// دالة لمحاكاة جهاز محمول
function simulateMobileDevice() {
    console.log('📱 [محاكاة] تغيير حجم الشاشة لمحاكاة جهاز محمول...');

    // تغيير عرض النافذة
    if (window.innerWidth > 768) {
        // إضافة class لمحاكاة الجهاز المحمول
        document.body.style.width = '400px';
        document.body.style.maxWidth = '400px';

        // تحديث User Agent
        Object.defineProperty(navigator, 'userAgent', {
            value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
            configurable: true
        });

        console.log('✅ تم تطبيق محاكاة الجهاز المحمول');

        // إعادة كشف نوع الجهاز
        if (window.DeviceImageSource?.detectDevice) {
            const deviceInfo = window.DeviceImageSource.detectDevice();
            console.log('📱 نوع الجهاز المحدث:', deviceInfo);
        }
    }
}

// دالة اختبار مباشر للمودال
function testModalDirectly() {
    console.log('🎯 [اختبار مباشر] فتح المودال الأصلي مباشرة...');

    if (window.DeviceImageSource && window.DeviceImageSource.showModal) {
        window.DeviceImageSource.showModal(function(file) {
            if (file) {
                console.log('✅ [اختبار مباشر] تم استلام ملف:', file.name);
                alert(`تم اختبار المودال بنجاح!\nاسم الملف: ${file.name}`);
            } else {
                console.log('❌ [اختبار مباشر] لم يتم استلام ملف');
            }
        });
    } else {
        console.error('❌ [اختبار مباشر] DeviceImageSource.showModal غير متوفر');
        alert('فشل في فتح المودال - DeviceImageSource غير متوفر');
    }
}

// تشغيل الاختبار الأساسي
setTimeout(() => {
    console.log('🚀 بدء الاختبارات...');
    forceEnhanceDeviceImageSource();
    testOriginalDeviceModal();
}, 1000);

// إتاحة الدوال للاستخدام اليدوي
window.testOriginalDeviceModal = testOriginalDeviceModal;
window.forceEnhanceDeviceImageSource = forceEnhanceDeviceImageSource;
window.simulateMobileDevice = simulateMobileDevice;
window.testModalDirectly = testModalDirectly;

console.log('💡 الدوال المتاحة للاختبار:');
console.log('- testOriginalDeviceModal() - اختبار المودال الأصلي مع أفراد الأسرة');
console.log('- forceEnhanceDeviceImageSource() - فرض تطبيق التحسينات');
console.log('- simulateMobileDevice() - محاكاة جهاز محمول');
console.log('- testModalDirectly() - اختبار المودال مباشرة');

console.log('📋 [ملاحظة] المودال الأصلي يظهر عادة على الأجهزة المحمولة فقط');
