// ملف اختبار المقص في جميع البوابات
// استخدم هذا الكود في كونسول المتصفح لاختبار المقص

console.log('🧪 بدء اختبار المقص في جميع البوابات...');

// 1. فحص توفر أداة القص
function testCropperAvailability() {
    console.log('\n📋 فحص توفر أداة القص:');
    console.log('window.showCropperModal:', typeof window.showCropperModal);
    console.log('window.showCropper:', typeof window.showCropper);
    console.log('window.cropperReady:', window.cropperReady);
    
    if (window.showCropperModal || window.showCropper) {
        console.log('✅ أداة القص متوفرة');
        return true;
    } else {
        console.log('❌ أداة القص غير متوفرة');
        return false;
    }
}

// 2. فحص عناصر المقص في الصفحة
function testCropperElements() {
    console.log('\n📋 فحص عناصر المقص:');
    
    const modalEl = document.getElementById('cropperModal');
    const imgEl = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    
    console.log('cropperModal:', modalEl ? '✅ موجود' : '❌ مفقود');
    console.log('cropperImage:', imgEl ? '✅ موجود' : '❌ مفقود');
    console.log('cropperCropBtn:', cropBtn ? '✅ موجود' : '❌ مفقود');
    
    return modalEl && imgEl && cropBtn;
}

// 3. فحص مناطق الرفع
function testUploadZones() {
    console.log('\n📋 فحص مناطق الرفع:');
    
    const allZones = document.querySelectorAll('[data-upload-zone]');
    console.log('إجمالي مناطق الرفع:', allZones.length);
    
    allZones.forEach(zone => {
        const personKey = zone.getAttribute('data-upload-zone');
        const fileInput = zone.querySelector('input[type="file"]');
        const docSelect = zone.querySelector('select');
        
        console.log(`منطقة "${personKey}":`, {
            fileInput: fileInput ? '✅' : '❌',
            docSelect: docSelect ? '✅' : '❌',
            initialized: zone.dataset.initialized === 'true' ? '✅' : '❌'
        });
    });
    
    return allZones.length;
}

// 4. فحص نظام إدارة المرفقات
function testAttachmentSystem() {
    console.log('\n📋 فحص نظام إدارة المرفقات:');
    
    console.log('window.allDocs:', window.allDocs instanceof Map ? '✅ Map' : '❌ غير متوفر');
    
    if (window.allDocs instanceof Map) {
        console.log('عدد المناطق في allDocs:', window.allDocs.size);
        window.allDocs.forEach((tasks, personKey) => {
            console.log(`منطقة "${personKey}": ${tasks.length} مهمة`);
        });
    }
}

// 5. اختبار المقص بملف تجريبي
function testCropperWithDummyFile() {
    console.log('\n🧪 اختبار المقص بملف تجريبي...');
    
    if (!window.showCropperModal && !window.showCropper) {
        console.log('❌ أداة القص غير متوفرة');
        return;
    }
    
    // إنشاء canvas صغير كصورة تجريبية
    const canvas = document.createElement('canvas');
    canvas.width = 100;
    canvas.height = 100;
    const ctx = canvas.getContext('2d');
    
    // رسم مربع ملون
    ctx.fillStyle = '#FF6B6B';
    ctx.fillRect(0, 0, 100, 100);
    ctx.fillStyle = '#4ECDC4';
    ctx.fillRect(20, 20, 60, 60);
    ctx.fillStyle = '#45B7D1';
    ctx.fillRect(40, 40, 20, 20);
    
    canvas.toBlob(function(blob) {
        if (!blob) {
            console.log('❌ فشل في إنشاء blob تجريبي');
            return;
        }
        
        const testFile = new File([blob], 'test-image.png', { type: 'image/png' });
        console.log('✅ تم إنشاء ملف تجريبي:', testFile.name, testFile.size, 'bytes');
        
        const showCropper = window.showCropperModal || window.showCropper;
        
        showCropper(testFile, function(croppedFile, error) {
            if (error) {
                console.log('❌ خطأ في المقص:', error);
            } else if (croppedFile) {
                console.log('✅ تم قص الصورة التجريبية بنجاح:', croppedFile.name || 'cropped', croppedFile.size, 'bytes');
            } else {
                console.log('⚠️ تم إلغاء المقص أو لم يتم إرجاع ملف');
            }
        });
        
        console.log('🎯 تم فتح المقص - يرجى قص الصورة أو إلغاء العملية');
    }, 'image/png');
}

// 6. اختبار تلقائي شامل
function runFullTest() {
    console.log('🚀 بدء الاختبار التلقائي الشامل...');
    
    const results = {
        cropperAvailable: testCropperAvailability(),
        cropperElements: testCropperElements(),
        uploadZones: testUploadZones() > 0,
        attachmentSystem: window.allDocs instanceof Map
    };
    
    testAttachmentSystem();
    
    console.log('\n📊 ملخص النتائج:');
    Object.entries(results).forEach(([test, result]) => {
        console.log(`${result ? '✅' : '❌'} ${test}`);
    });
    
    const allPassed = Object.values(results).every(r => r);
    
    if (allPassed) {
        console.log('\n🎉 جميع الاختبارات نجحت! يمكنك الآن اختبار المقص يدوياً.');
        console.log('💡 لاختبار المقص بملف تجريبي، اكتب: testCropperWithDummyFile()');
    } else {
        console.log('\n⚠️ بعض الاختبارات فشلت. يرجى مراجعة المشاكل أعلاه.');
    }
    
    return results;
}

// 7. إعادة تهيئة مناطق الرفع
function reinitializeUploadZones() {
    console.log('🔄 إعادة تهيئة مناطق الرفع...');
    
    if (typeof window.initializeAllUploadZones === 'function') {
        window.initializeAllUploadZones();
        console.log('✅ تم استدعاء initializeAllUploadZones()');
    } else {
        console.log('❌ دالة initializeAllUploadZones غير متوفرة');
    }
}

// 8. مراقبة أحداث المقص
function monitorCropperEvents() {
    console.log('👂 بدء مراقبة أحداث المقص...');
    
    window.addEventListener('cropperReady', function(event) {
        console.log('🎉 تم استلام حدث cropperReady:', event.detail);
    });
    
    console.log('✅ تم تفعيل مراقبة أحداث المقص');
}

// تصدير الدوال للاستخدام في الكونسول
window.cropperTest = {
    testCropperAvailability,
    testCropperElements,
    testUploadZones,
    testAttachmentSystem,
    testCropperWithDummyFile,
    runFullTest,
    reinitializeUploadZones,
    monitorCropperEvents
};

// تشغيل الاختبار التلقائي عند تحميل الملف
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runFullTest);
} else {
    runFullTest();
}

console.log('\n💡 الدوال المتاحة:');
console.log('- cropperTest.runFullTest() - اختبار شامل');
console.log('- cropperTest.testCropperWithDummyFile() - اختبار المقص بملف تجريبي');
console.log('- cropperTest.reinitializeUploadZones() - إعادة تهيئة مناطق الرفع');
console.log('- cropperTest.monitorCropperEvents() - مراقبة أحداث المقص');
