// اختبار إصلاح مشكلة initUploadZone is not defined
// نسخة متطورة مع فحص شامل لجميع السيناريوهات

console.log('🧪 [Test] بدء اختبار إصلاح مشكلة initUploadZone...');

// 1. فحص تعريف الدالة في window
console.log('🔍 [Test] فحص تعريف window.initUploadZone:', {
    isDefined: typeof window.initUploadZone === 'function',
    isFunction: window.initUploadZone instanceof Function,
    globalExists: 'initUploadZone' in window
});

// 2. فحص توقيت التحميل
document.addEventListener('DOMContentLoaded', function() {
    console.log('📅 [Test] DOMContentLoaded - فحص حالة الدوال:');

    // فحص الدوال الأساسية
    const functionsToCheck = [
        'window.initUploadZone',
        'window.setupDocumentUploadHandlersForMember',
        'window.addFamilyMember',
        'window.DeviceImageSource'
    ];

    functionsToCheck.forEach(funcName => {
        const func = funcName.split('.').reduce((obj, prop) => obj && obj[prop], window);
        console.log(`  ${funcName}: ${typeof func === 'function' ? '✅ متوفرة' : '❌ غير متوفرة'}`);
    });

    // 3. اختبار إضافة فرد أسرة ومراقبة initUploadZone
    setTimeout(() => {
        console.log('🎯 [Test] محاولة إضافة فرد أسرة واختبار initUploadZone...');

        // محاكاة إضافة فرد أسرة
        const familyContainer = document.getElementById('familyMembersContainer');
        if (familyContainer && typeof window.addFamilyMember === 'function') {
            console.log('🆕 [Test] إضافة فرد أسرة للاختبار...');

            // مراقبة تغييرات DOM
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && node.querySelector) {
                            const uploadZones = node.querySelectorAll('[data-upload-zone]');
                            uploadZones.forEach(zone => {
                                const personKey = zone.getAttribute('data-upload-zone');
                                if (personKey && !personKey.includes('template')) {
                                    console.log('🔍 [Test] منطقة رفع جديدة مكتشفة:', personKey);

                                    // اختبار استدعاء initUploadZone
                                    try {
                                        if (typeof window.initUploadZone === 'function') {
                                            console.log('✅ [Test] استدعاء window.initUploadZone...');
                                            window.initUploadZone(zone);
                                            console.log('✅ [Test] نجح استدعاء initUploadZone للمنطقة:', personKey);
                                        } else {
                                            console.error('❌ [Test] window.initUploadZone غير متوفرة!');
                                        }
                                    } catch (error) {
                                        console.error('❌ [Test] خطأ في استدعاء initUploadZone:', error);
                                    }
                                }
                            });
                        }
                    });
                });
            });

            observer.observe(familyContainer, {
                childList: true,
                subtree: true
            });

            // إضافة فرد أسرة فعلياً
            try {
                window.addFamilyMember();
                console.log('✅ [Test] تم إضافة فرد أسرة بنجاح');
            } catch (error) {
                console.error('❌ [Test] خطأ في إضافة فرد أسرة:', error);
            }

            // إيقاف المراقب بعد 5 ثواني
            setTimeout(() => {
                observer.disconnect();
                console.log('🛑 [Test] تم إيقاف مراقب الاختبار');
            }, 5000);

        } else {
            console.error('❌ [Test] لا يمكن إجراء اختبار إضافة فرد أسرة - العناصر المطلوبة غير متوفرة');
        }
    }, 2000);

    // 4. اختبار مراقبات documentUpload.blade.php
    setTimeout(() => {
        console.log('🔍 [Test] فحص مراقبات documentUpload...');

        // محاكاة سيناريو يستدعي فيه مراقب documentUpload دالة initUploadZone
        const testZone = document.createElement('div');
        testZone.setAttribute('data-upload-zone', 'test_family_0');
        testZone.classList.add('test-upload-zone');

        // إضافة النموذج المحتوي
        const testForm = document.createElement('div');
        testForm.classList.add('family-member-form');
        testForm.setAttribute('data-member-index', '0');
        testForm.appendChild(testZone);

        // محاكاة استدعاء مثل الذي يحدث في documentUpload.blade.php
        try {
            console.log('🧪 [Test] محاكاة استدعاء مراقب documentUpload...');

            if (typeof window.initUploadZone === 'function') {
                window.initUploadZone(testZone);
                console.log('✅ [Test] نجح استدعاء initUploadZone من مراقب محاكى');
            } else {
                console.error('❌ [Test] فشل - window.initUploadZone غير متوفرة في المراقب');
            }
        } catch (error) {
            console.error('❌ [Test] خطأ في استدعاء initUploadZone من مراقب محاكى:', error);
        }

        // تنظيف عناصر الاختبار
        testForm.remove();
    }, 3000);

    // 5. اختبار النماذج الموجودة
    setTimeout(() => {
        console.log('🔍 [Test] فحص النماذج الموجودة...');

        const existingForms = document.querySelectorAll('.family-member-form:not(.d-none)');
        console.log(`📊 [Test] عدد النماذج الموجودة: ${existingForms.length}`);

        existingForms.forEach((form, index) => {
            const uploadZone = form.querySelector('[data-upload-zone]');
            if (uploadZone) {
                const personKey = uploadZone.getAttribute('data-upload-zone');
                console.log(`🔍 [Test] نموذج ${index}: personKey=${personKey}, initialized=${uploadZone.dataset.initialized}`);

                // اختبار إعادة تهيئة
                if (personKey && !personKey.includes('template')) {
                    try {
                        if (typeof window.initUploadZone === 'function') {
                            window.initUploadZone(uploadZone);
                            console.log(`✅ [Test] نجحت إعادة تهيئة النموذج ${index}`);
                        }
                    } catch (error) {
                        console.error(`❌ [Test] فشلت إعادة تهيئة النموذج ${index}:`, error);
                    }
                }
            }
        });
    }, 4000);
});

// 6. فحص دوري للحالة
setInterval(() => {
    const currentTime = new Date().toLocaleTimeString();
    const initUploadZoneStatus = typeof window.initUploadZone === 'function' ? '✅' : '❌';
    console.log(`⏰ [Test] ${currentTime} - حالة window.initUploadZone: ${initUploadZoneStatus}`);
}, 10000);

console.log('🚀 [Test] تم تهيئة اختبار initUploadZone - مراقبة النتائج...');
