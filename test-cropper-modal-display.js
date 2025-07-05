/**
 * اختبار مفصل لـ modal القص في بوابة أفراد الأسرة
 * التحقق من عرض الصورة الفعلية أثناء القص
 */

console.log('🧪 بدء اختبار modal القص المُفصل...');

// إنشاء ملف صورة تجريبي
function createTestImageFile() {
    // إنشاء canvas صغير مع صورة تجريبية
    const canvas = document.createElement('canvas');
    canvas.width = 400;
    canvas.height = 300;
    
    const ctx = canvas.getContext('2d');
    
    // خلفية زرقاء
    ctx.fillStyle = '#4A90E2';
    ctx.fillRect(0, 0, 400, 300);
    
    // نص تجريبي
    ctx.fillStyle = '#FFFFFF';
    ctx.font = '24px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('اختبار القص', 200, 120);
    ctx.fillText('بوابة أفراد الأسرة', 200, 160);
    ctx.fillText(new Date().toLocaleTimeString('ar'), 200, 200);
    
    // تحويل إلى blob ثم إلى file
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

// فحص modal القص
async function testCropperModal() {
    console.log('🔍 فحص توفر عناصر modal القص...');
    
    // فحص العناصر الأساسية
    const modalEl = document.getElementById('cropperModal');
    const imgEl = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    const loaderModalEl = document.getElementById('compressLoaderModal');
    
    console.log('✅ نتائج فحص العناصر:');
    console.log('  - Modal:', modalEl ? '✅ موجود' : '❌ مفقود');
    console.log('  - Image Element:', imgEl ? '✅ موجود' : '❌ مفقود');
    console.log('  - Crop Button:', cropBtn ? '✅ موجود' : '❌ مفقود');
    console.log('  - Loader Modal:', loaderModalEl ? '✅ موجود' : '❌ مفقود');
    
    if (!modalEl || !imgEl || !cropBtn) {
        console.error('❌ العناصر الأساسية لـ modal القص مفقودة!');
        return false;
    }
    
    // فحص أزرار التحكم
    const controlButtons = [
        'cropperMoveUp', 'cropperMoveDown', 'cropperMoveLeft', 'cropperMoveRight',
        'cropperZoomIn', 'cropperZoomOut', 'cropperRotateRight'
    ];
    
    console.log('🎮 فحص أزرار التحكم:');
    controlButtons.forEach(id => {
        const btn = document.getElementById(id);
        console.log(`  - ${id}:`, btn ? '✅ موجود' : '❌ مفقود');
    });
    
    // فحص دالة showCropperModal
    console.log('📱 فحص دالة showCropperModal:');
    console.log('  - window.showCropperModal:', typeof window.showCropperModal);
    console.log('  - window.showCropper:', typeof window.showCropper);
    console.log('  - window.cropperReady:', window.cropperReady);
    
    if (!window.showCropperModal && !window.showCropper) {
        console.error('❌ دالة showCropperModal غير متوفرة!');
        return false;
    }
    
    return true;
}

// اختبار عملي لفتح modal القص
async function testModalOpening() {
    console.log('🚀 بدء اختبار فتح modal القص...');
    
    try {
        // إنشاء ملف تجريبي
        const testFile = await createTestImageFile();
        console.log('✅ تم إنشاء ملف تجريبي:', testFile.name, testFile.size, 'bytes');
        
        // الحصول على دالة القص
        const showCropper = window.showCropperModal || window.showCropper;
        
        if (!showCropper) {
            console.error('❌ دالة القص غير متوفرة!');
            return;
        }
        
        // فتح modal القص مع مراقبة العملية
        console.log('📂 فتح modal القص...');
        
        // مراقبة حالة الصورة
        const imgEl = document.getElementById('cropperImage');
        
        // إضافة مستمعات للأحداث
        const originalOnload = imgEl.onload;
        const originalOnerror = imgEl.onerror;
        
        imgEl.onload = function() {
            console.log('🖼️ تم تحميل الصورة في modal القص!');
            console.log('  - مصدر الصورة:', imgEl.src.substring(0, 50) + '...');
            console.log('  - الأبعاد الطبيعية:', imgEl.naturalWidth + 'x' + imgEl.naturalHeight);
            console.log('  - الأبعاد المعروضة:', imgEl.width + 'x' + imgEl.height);
            console.log('  - مرئية:', imgEl.style.display !== 'none');
            
            if (originalOnload) originalOnload.call(this);
        };
        
        imgEl.onerror = function(err) {
            console.error('❌ خطأ في تحميل الصورة في modal القص:', err);
            
            if (originalOnerror) originalOnerror.call(this, err);
        };
        
        // مراقبة modal
        const modalEl = document.getElementById('cropperModal');
        modalEl.addEventListener('shown.bs.modal', function() {
            console.log('👁️ تم عرض modal القص');
            
            // فحص حالة الصورة بعد عرض modal
            setTimeout(() => {
                console.log('🔍 فحص حالة الصورة بعد 2 ثانية:');
                console.log('  - مصدر الصورة:', imgEl.src ? imgEl.src.substring(0, 50) + '...' : 'لا يوجد');
                console.log('  - محملة:', imgEl.complete);
                console.log('  - الأبعاد الطبيعية:', imgEl.naturalWidth + 'x' + imgEl.naturalHeight);
                console.log('  - مرئية:', imgEl.offsetWidth > 0 && imgEl.offsetHeight > 0);
                
                // فحص cropper instance
                if (imgEl.cropperInstance) {
                    console.log('✅ تم إنشاء cropper instance');
                    try {
                        const data = imgEl.cropperInstance.getData();
                        console.log('  - بيانات القص:', data);
                    } catch (e) {
                        console.warn('⚠️ لم يتم تهيئة cropper بالكامل بعد');
                    }
                } else {
                    console.warn('⚠️ cropper instance غير موجود');
                }
            }, 2000);
        });
        
        // استدعاء دالة القص
        showCropper(testFile, function(result) {
            if (result) {
                console.log('✅ تم قص الصورة بنجاح:', result.name, result.size, 'bytes');
            } else {
                console.log('⚠️ تم إلغاء القص أو حدث خطأ');
            }
        });
        
    } catch (error) {
        console.error('❌ خطأ في اختبار modal القص:', error);
    }
}

// تشغيل الاختبارات
async function runTests() {
    console.log('🧪 بدء اختبارات modal القص الشاملة...');
    
    const basicCheck = await testCropperModal();
    
    if (basicCheck) {
        console.log('✅ الفحص الأساسي نجح، بدء الاختبار العملي...');
        
        // انتظار قليل للتأكد من تحميل كل شيء
        setTimeout(testModalOpening, 1000);
    } else {
        console.error('❌ فشل الفحص الأساسي، لا يمكن المتابعة');
    }
}

// تشغيل الاختبارات عند تحميل الصفحة
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runTests);
} else {
    runTests();
}

// إضافة دالة لاختبار يدوي
window.testCropperModal = testModalOpening;

console.log('📋 تم تحميل اختبار modal القص');
console.log('💡 لتشغيل اختبار يدوي، استخدم: testCropperModal()');
