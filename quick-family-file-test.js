// اختبار سريع للتحقق من عناصر أفراد الأسرة في DOM
console.log('🔍 [اختبار DOM] بدء فحص عناصر أفراد الأسرة...');

// التحقق من وجود العناصر المطلوبة
function checkFamilyMemberElements() {
    console.log('📋 فحص العناصر الأساسية...');

    // 1. التحقق من حاوية أفراد الأسرة
    const container = document.getElementById('familyMembersContainer');
    console.log('حاوية أفراد الأسرة:', {
        exists: !!container,
        element: container
    });

    // 2. التحقق من قالب فرد الأسرة
    const template = document.getElementById('familyMemberTemplate');
    console.log('قالب فرد الأسرة:', {
        exists: !!template,
        element: template,
        hasContent: template ? template.innerHTML.length > 0 : false
    });

    // 3. التحقق من زر إضافة فرد
    const addBtn = document.getElementById('addFamilyMember');
    console.log('زر إضافة فرد:', {
        exists: !!addBtn,
        element: addBtn
    });

    // 4. التحقق من الدوال العامة
    console.log('الدوال المطلوبة:', {
        addFamilyMember: typeof window.addFamilyMember,
        reindexFamilyMembers: typeof window.reindexFamilyMembers,
        setupDocumentUploadHandlersForMember: typeof window.setupDocumentUploadHandlersForMember
    });

    // 5. التحقق من window.allDocs
    console.log('نظام إدارة الوثائق:', {
        allDocsExists: typeof window.allDocs !== 'undefined',
        allDocsType: typeof window.allDocs,
        isMap: window.allDocs instanceof Map,
        size: window.allDocs instanceof Map ? window.allDocs.size : 'N/A'
    });

    return {
        container: !!container,
        template: !!template,
        addBtn: !!addBtn,
        functions: {
            add: typeof window.addFamilyMember === 'function',
            reindex: typeof window.reindexFamilyMembers === 'function',
            upload: typeof window.setupDocumentUploadHandlersForMember === 'function'
        }
    };
}

// التحقق من أخطاء JavaScript
function checkJavaScriptErrors() {
    console.log('🔍 فحص أخطاء JavaScript...');

    let errorCount = 0;
    const originalError = console.error;

    console.error = function(...args) {
        errorCount++;
        console.log(`❌ خطأ JavaScript رقم ${errorCount}:`, ...args);
        originalError.apply(console, args);
    };

    // اختبار بعض العمليات
    try {
        // اختبار دالة إضافة فرد
        if (typeof window.addFamilyMember === 'function') {
            console.log('✅ دالة addFamilyMember متوفرة');
        } else {
            console.error('❌ دالة addFamilyMember غير متوفرة');
        }

        // اختبار Map
        const testMap = new Map();
        testMap.set('test', []);
        console.log('✅ Map يعمل بشكل صحيح');

    } catch (error) {
        console.error('❌ خطأ في الاختبار:', error);
    }

    console.error = originalError;
    return errorCount;
}

// محاولة تشغيل دالة إضافة فرد
function testAddFamilyMember() {
    console.log('🧪 اختبار دالة إضافة فرد...');

    const result = checkFamilyMemberElements();

    if (!result.container) {
        console.error('❌ حاوية أفراد الأسرة غير موجودة - تأكد من أن العنصر familyMembersContainer موجود في HTML');
        return false;
    }

    if (!result.template) {
        console.error('❌ قالب فرد الأسرة غير موجود - تأكد من أن العنصر familyMemberTemplate موجود في HTML');
        return false;
    }

    if (!result.functions.add) {
        console.error('❌ دالة addFamilyMember غير متوفرة - تأكد من تحميل السكريبت بشكل صحيح');
        return false;
    }

    try {
        console.log('🚀 محاولة تشغيل addFamilyMember...');
        window.addFamilyMember();
        console.log('✅ تم تشغيل addFamilyMember بنجاح');
        return true;
    } catch (error) {
        console.error('❌ خطأ في تشغيل addFamilyMember:', error);
        return false;
    }
}

// تشغيل الاختبار الكامل
function runFullTest() {
    console.log('🚀 تشغيل الاختبار الكامل...');
    console.log('================================');

    const elementsCheck = checkFamilyMemberElements();
    const errorCount = checkJavaScriptErrors();
    const addTestResult = testAddFamilyMember();

    console.log('================================');
    console.log('📊 نتائج الاختبار:');
    console.log('- العناصر الأساسية:', elementsCheck.container && elementsCheck.template ? '✅' : '❌');
    console.log('- الدوال المطلوبة:', elementsCheck.functions.add && elementsCheck.functions.reindex ? '✅' : '❌');
    console.log('- أخطاء JavaScript:', errorCount === 0 ? '✅ لا توجد أخطاء' : `❌ ${errorCount} أخطاء`);
    console.log('- اختبار إضافة فرد:', addTestResult ? '✅' : '❌');

    if (elementsCheck.container && elementsCheck.template && elementsCheck.functions.add) {
        console.log('🎉 النظام جاهز للعمل!');
    } else {
        console.log('⚠️ هناك مشاكل تحتاج إلى إصلاح');
    }
}

// تشغيل الاختبار عند تحميل الصفحة
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runFullTest);
} else {
    runFullTest();
}

// إتاحة الدالة للتشغيل اليدوي
window.testFamilyMemberSystem = runFullTest;
