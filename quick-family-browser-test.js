// اختبار سريع للتحقق من سلامة JavaScript في المتصفح
// يمكن تشغيل هذا الكود في console المتصفح للتحقق من الأخطاء

console.log('🔍 بدء اختبار سلامة JavaScript في المتصفح...');

// 1. التحقق من وجود الأخطاء في window
function checkWindowErrors() {
    console.log('🔍 فحص الأخطاء في window...');

    // التحقق من وجود window.allDocs
    if (typeof window.allDocs !== 'undefined') {
        console.log('✅ window.allDocs موجود:', typeof window.allDocs);
        if (window.allDocs instanceof Map) {
            console.log('✅ window.allDocs هو Map صالح');
            console.log('📊 عدد المفاتيح:', window.allDocs.size);
            console.log('📋 المفاتيح:', Array.from(window.allDocs.keys()));
        } else {
            console.warn('⚠️ window.allDocs ليس Map');
        }
    } else {
        console.warn('⚠️ window.allDocs غير موجود');
    }

    // التحقق من وجود الدوال المطلوبة
    const requiredFunctions = [
        'setupDocumentUploadHandlersForMember',
        'DeviceImageSource',
        'showCropperModal'
    ];

    requiredFunctions.forEach(funcName => {
        if (typeof window[funcName] !== 'undefined') {
            console.log(`✅ window.${funcName} موجود:`, typeof window[funcName]);
        } else {
            console.warn(`⚠️ window.${funcName} غير موجود`);
        }
    });
}

// 2. التحقق من عناصر familyMember
function checkFamilyMemberElements() {
    console.log('🔍 فحص عناصر أفراد الأسرة...');

    const container = document.getElementById('familyMembersContainer');
    if (container) {
        console.log('✅ حاوية أفراد الأسرة موجودة');

        const memberForms = container.querySelectorAll('.family-member-form:not(.d-none)');
        console.log(`📊 عدد النماذج النشطة: ${memberForms.length}`);

        memberForms.forEach((form, idx) => {
            const uploadZone = form.querySelector('[data-upload-zone]');
            const fileInput = form.querySelector('.mainDocumentFileInput');
            const preview = form.querySelector('.mainDocumentPreview');
            const docSelect = form.querySelector('.mainDocumentTypeSelect');

            console.log(`📋 النموذج ${idx}:`, {
                hasUploadZone: !!uploadZone,
                uploadZoneKey: uploadZone ? uploadZone.getAttribute('data-upload-zone') : 'غير موجود',
                hasFileInput: !!fileInput,
                hasPreview: !!preview,
                hasDocSelect: !!docSelect,
                previewDisplay: preview ? preview.style.display : 'غير موجود'
            });
        });
    } else {
        console.warn('⚠️ حاوية أفراد الأسرة غير موجودة');
    }
}

// 3. التحقق من أخطاء JavaScript المخفية
function checkHiddenErrors() {
    console.log('🔍 فحص الأخطاء المخفية...');

    // التحقق من console errors
    const originalError = console.error;
    let errorCount = 0;

    console.error = function(...args) {
        errorCount++;
        console.log(`❌ خطأ رقم ${errorCount}:`, ...args);
        originalError.apply(console, args);
    };

    // اختبار تنفيذ كود بسيط
    try {
        const testMap = new Map();
        testMap.set('test', []);
        console.log('✅ Map يعمل بشكل صحيح');
    } catch (error) {
        console.error('❌ خطأ في Map:', error);
    }

    try {
        const testFunc = () => console.log('Arrow function works');
        testFunc();
        console.log('✅ Arrow functions تعمل بشكل صحيح');
    } catch (error) {
        console.error('❌ خطأ في Arrow functions:', error);
    }

    // إرجاع console.error للوضع العادي
    console.error = originalError;

    console.log(`📊 إجمالي الأخطاء المكتشفة: ${errorCount}`);
}

// 4. اختبار التفاعل مع نماذج أفراد الأسرة
function testFamilyMemberInteraction() {
    console.log('🔍 اختبار التفاعل مع نماذج أفراد الأسرة...');

    try {
        // محاولة تشغيل setupDocumentUploadHandlersForMember
        if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
            const mockForm = document.createElement('div');
            mockForm.innerHTML = `
                <input type="file" class="mainDocumentFileInput">
                <div class="mainDocumentPreview"></div>
                <select class="mainDocumentTypeSelect"></select>
                <div data-upload-zone="test_zone"></div>
            `;

            window.setupDocumentUploadHandlersForMember(mockForm, 999);
            console.log('✅ setupDocumentUploadHandlersForMember يعمل بشكل صحيح');
        } else {
            console.warn('⚠️ setupDocumentUploadHandlersForMember غير متوفر للاختبار');
        }
    } catch (error) {
        console.error('❌ خطأ في اختبار setupDocumentUploadHandlersForMember:', error);
    }
}

// 5. تشغيل جميع الاختبارات
function runBrowserTest() {
    console.log('🚀 تشغيل اختبار شامل في المتصفح...');
    console.log('=====================================');

    checkWindowErrors();
    console.log('-------------------------------------');

    checkFamilyMemberElements();
    console.log('-------------------------------------');

    checkHiddenErrors();
    console.log('-------------------------------------');

    testFamilyMemberInteraction();
    console.log('-------------------------------------');

    console.log('🎉 اكتمل الاختبار في المتصفح!');
    console.log('💡 إذا لم تظهر أخطاء أعلاه، فإن JavaScript يعمل بشكل صحيح');
    console.log('💡 إذا كان هناك أخطاء، فتحقق من:');
    console.log('   1. تحديث الصفحة مع إفراغ الكاش (Ctrl+F5)');
    console.log('   2. إغلاق وإعادة فتح المتصفح');
    console.log('   3. فحص Network tab للتأكد من تحميل الملفات الصحيحة');
}

// تشغيل الاختبار فورا
runBrowserTest();

// إتاحة الدالة للتشغيل اليدوي
window.runFamilyMemberTest = runBrowserTest;
