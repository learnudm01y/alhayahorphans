/**
 * اختبار تكامل نظام أفراد الأسرة مع نظام اختيار مصدر الصورة
 * يفحص حل مشكلة initUploadZone وعمل مودال الكاميرا/المعرض
 */

console.log('🧪 [FamilyDeviceIntegration] بدء اختبار التكامل...');

// 1. فحص وجود دالة initUploadZone
function testInitUploadZone() {
    console.log('\n📋 اختبار دالة initUploadZone:');

    if (typeof window.initUploadZone === 'function') {
        console.log('✅ دالة initUploadZone متوفرة');

        // اختبار استدعاء الدالة مع منطقة اختبارية
        const testZones = document.querySelectorAll('[data-upload-zone*="family_"]');
        if (testZones.length > 0) {
            console.log(`🔍 تم العثور على ${testZones.length} منطقة رفع لأفراد الأسرة`);

            testZones.forEach((zone, idx) => {
                const personKey = zone.getAttribute('data-upload-zone');
                console.log(`   منطقة ${idx + 1}: ${personKey}`);

                try {
                    window.initUploadZone(zone);
                    console.log(`   ✅ تم تهيئة ${personKey} بنجاح`);
                } catch (error) {
                    console.error(`   ❌ خطأ في تهيئة ${personKey}:`, error.message);
                }
            });
        } else {
            console.log('⚠️ لم يتم العثور على مناطق رفع لأفراد الأسرة');
        }
    } else {
        console.error('❌ دالة initUploadZone غير متوفرة');
    }
}

// 2. فحص نظام DeviceImageSource
function testDeviceImageSource() {
    console.log('\n📱 اختبار نظام DeviceImageSource:');

    if (window.DeviceImageSource) {
        console.log('✅ نظام DeviceImageSource متوفر');

        // فحص الوظائف المتوفرة
        const functions = ['showModal', 'hideModal', 'detectDevice', 'enhance'];
        functions.forEach(func => {
            if (typeof window.DeviceImageSource[func] === 'function') {
                console.log(`   ✅ دالة ${func} متوفرة`);
            } else {
                console.error(`   ❌ دالة ${func} غير متوفرة`);
            }
        });

        // فحص نوع الجهاز
        if (typeof window.DeviceImageSource.detectDevice === 'function') {
            const deviceInfo = window.DeviceImageSource.detectDevice();
            console.log('📱 معلومات الجهاز:', {
                isMobile: deviceInfo.isMobile,
                isTablet: deviceInfo.isTablet,
                isDesktop: !deviceInfo.isMobile && !deviceInfo.isTablet
            });
        }

        // فحص وجود مودال اختيار المصدر
        const modal = document.getElementById('imageSourceModal');
        if (modal) {
            console.log('✅ مودال اختيار مصدر الصورة موجود في الصفحة');
        } else {
            console.error('❌ مودال اختيار مصدر الصورة مفقود');
        }

    } else {
        console.error('❌ نظام DeviceImageSource غير متوفر');
    }
}

// 3. فحص ربط الأحداث في نماذج أفراد الأسرة
function testFamilyMemberEvents() {
    console.log('\n👥 اختبار أحداث نماذج أفراد الأسرة:');

    const familyForms = document.querySelectorAll('.family-member-form:not(.d-none)');
    console.log(`🔍 تم العثور على ${familyForms.length} نموذج فرد أسرة نشط`);

    familyForms.forEach((form, idx) => {
        const docTypeSelect = form.querySelector('.mainDocumentTypeSelect');
        const fileInput = form.querySelector('.mainDocumentFileInput');
        const uploadZone = form.querySelector('[data-upload-zone]');

        const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : 'غير محدد';

        console.log(`   نموذج ${idx + 1} (${personKey}):`);
        console.log(`      اختيار نوع الوثيقة: ${docTypeSelect ? '✅' : '❌'}`);
        console.log(`      حقل رفع الملف: ${fileInput ? '✅' : '❌'}`);
        console.log(`      منطقة الرفع: ${uploadZone ? '✅' : '❌'}`);

        // فحص وجود دالة التحقق المخصصة
        if (form._familyCanUploadFunction) {
            console.log(`      دالة التحقق المخصصة: ✅`);
        } else {
            console.log(`      دالة التحقق المخصصة: ❌`);
        }

        // فحص تهيئة منطقة الرفع
        if (uploadZone && uploadZone.dataset.initialized === 'true') {
            console.log(`      حالة التهيئة: ✅ مهيأة`);
        } else {
            console.log(`      حالة التهيئة: ⚠️ غير مهيأة`);
        }
    });
}

// 4. اختبار إضافة فرد جديد
function testAddFamilyMember() {
    console.log('\n➕ اختبار إضافة فرد جديد:');

    const addBtn = document.getElementById('addFamilyMember');
    if (addBtn) {
        console.log('✅ زر إضافة فرد الأسرة موجود');

        if (typeof window.addFamilyMember === 'function') {
            console.log('✅ دالة addFamilyMember متوفرة');

            // محاكاة إضافة فرد (اختياري)
            console.log('💡 يمكن محاكاة إضافة فرد بتنفيذ: window.addFamilyMember()');
        } else {
            console.error('❌ دالة addFamilyMember غير متوفرة');
        }
    } else {
        console.error('❌ زر إضافة فرد الأسرة مفقود');
    }
}

// 5. اختبار محاكي لمودال اختيار المصدر
function testDeviceModalSimulation() {
    console.log('\n🎯 اختبار محاكي لمودال اختيار المصدر:');

    if (window.DeviceImageSource && window.DeviceImageSource.showModal) {
        console.log('✅ يمكن محاكاة إظهار مودال اختيار المصدر');

        // إنشاء ملف اختبار
        function createTestFile() {
            const canvas = document.createElement('canvas');
            canvas.width = 100;
            canvas.height = 100;
            const ctx = canvas.getContext('2d');

            // رسم مربع ملون
            ctx.fillStyle = '#ff6b6b';
            ctx.fillRect(0, 0, 100, 100);
            ctx.fillStyle = 'white';
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('TEST', 50, 55);

            return new Promise((resolve) => {
                canvas.toBlob((blob) => {
                    const file = new File([blob], 'test-image.png', {
                        type: 'image/png',
                        lastModified: Date.now()
                    });
                    resolve(file);
                }, 'image/png', 0.8);
            });
        }

        console.log('💡 يمكن اختبار المودال بتنفيذ:');
        console.log('   window.DeviceImageSource.showModal(function(file) { console.log("تم اختيار:", file?.name); });');

        // حفظ دالة الاختبار في النطاق العام
        window.testDeviceModal = function() {
            window.DeviceImageSource.showModal(function(file) {
                if (file) {
                    console.log('✅ تم اختيار ملف اختبار:', file.name);
                } else {
                    console.log('❌ تم إلغاء اختيار الملف');
                }
            });
        };

    } else {
        console.error('❌ دالة showModal غير متوفرة');
    }
}

// تشغيل جميع الاختبارات
function runAllTests() {
    console.log('🚀 بدء تشغيل جميع اختبارات التكامل...\n');

    testInitUploadZone();
    testDeviceImageSource();
    testFamilyMemberEvents();
    testAddFamilyMember();
    testDeviceModalSimulation();

    console.log('\n🎉 انتهت جميع الاختبارات!');
    console.log('\n📝 ملاحظات:');
    console.log('   - إذا كانت دالة initUploadZone متوفرة، فقد تم حل مشكلة "initUploadZone is not defined"');
    console.log('   - إذا كان نظام DeviceImageSource متوفر، فيمكن اختبار مودال الكاميرا/المعرض');
    console.log('   - استخدم window.testDeviceModal() لاختبار مودال اختيار المصدر');
}

// تشغيل الاختبارات عند تحميل DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runAllTests);
} else {
    runAllTests();
}

// إتاحة دالة الاختبار في النطاق العام
window.testFamilyDeviceIntegration = runAllTests;

console.log('💡 يمكن إعادة تشغيل الاختبارات بتنفيذ: window.testFamilyDeviceIntegration()');
