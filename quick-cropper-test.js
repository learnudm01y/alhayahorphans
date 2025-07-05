/**
 * اختبار سريع لإصلاح modal القص
 * التحقق من عدم وجود تداخل في عرض الصورة
 */

console.log('🧪 اختبار إصلاح modal القص - نسخة مُبسطة');

// فحص modal القص السريع
function quickCropperTest() {
    console.log('🔍 فحص modal القص السريع...');
    
    // فحص العناصر الأساسية
    const modalEl = document.getElementById('cropperModal');
    const imgEl = document.getElementById('cropperImage');
    const cropBtn = document.getElementById('cropperCropBtn');
    
    console.log('Modal Element:', modalEl ? '✅ موجود' : '❌ مفقود');
    console.log('Image Element:', imgEl ? '✅ موجود' : '❌ مفقود');
    console.log('Crop Button:', cropBtn ? '✅ موجود' : '❌ مفقود');
    
    if (imgEl) {
        console.log('Image CSS Classes:', imgEl.className);
        console.log('Image Style:', imgEl.getAttribute('style'));
        console.log('Image Current Source:', imgEl.src ? imgEl.src.substring(0, 50) + '...' : 'لا يوجد');
    }
    
    // فحص دالة showCropperModal
    console.log('showCropperModal Function:', typeof window.showCropperModal);
    
    if (window.showCropperModal) {
        console.log('✅ دالة القص متوفرة وجاهزة للاستخدام');
    } else {
        console.log('❌ دالة القص غير متوفرة');
    }
}

// فحص التداخلات المحتملة في CSS
function checkCSSConflicts() {
    console.log('🎨 فحص التداخلات في CSS...');
    
    const imgEl = document.getElementById('cropperImage');
    if (imgEl) {
        const computedStyle = window.getComputedStyle(imgEl);
        console.log('CSS Display:', computedStyle.display);
        console.log('CSS Visibility:', computedStyle.visibility);
        console.log('CSS Opacity:', computedStyle.opacity);
        console.log('CSS Max-Width:', computedStyle.maxWidth);
        console.log('CSS Max-Height:', computedStyle.maxHeight);
        console.log('CSS Position:', computedStyle.position);
        console.log('CSS Z-Index:', computedStyle.zIndex);
    }
}

// تشغيل الاختبارات
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 بدء اختبار modal القص السريع...');
    quickCropperTest();
    checkCSSConflicts();
    
    console.log('💡 لاختبار عملي، ارفع صورة في بوابة أفراد الأسرة');
});

// إضافة الدالة للنطاق العام
window.quickCropperTest = quickCropperTest;
window.checkCSSConflicts = checkCSSConflicts;
