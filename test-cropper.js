// ملف اختبار أداة القص
// ضع هذا الكود في الكونسول لاختبار أداة القص مباشرة

// 1. فحص وجود أداة القص
console.log('=== فحص أداة القص ===');
console.log('showCropperModal:', typeof window.showCropperModal);
console.log('showCropper:', typeof window.showCropper);

// 2. فحص عناصر DOM
console.log('=== فحص عناصر DOM ===');
const elements = {
    modal: document.getElementById('cropperModal'),
    image: document.getElementById('cropperImage'),
    cropBtn: document.getElementById('cropperCropBtn'),
    loaderModal: document.getElementById('compressLoaderModal')
};

Object.entries(elements).forEach(([name, element]) => {
    console.log(`${name}:`, element ? '✅ موجود' : '❌ مفقود');
});

// 3. فحص Bootstrap
console.log('=== فحص Bootstrap ===');
console.log('Bootstrap:', typeof bootstrap !== 'undefined' ? '✅ متوفر' : '❌ مفقود');
if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    console.log('Bootstrap Modal:', '✅ متوفر');
} else {
    console.log('Bootstrap Modal:', '❌ مفقود');
}

// 4. فحص مكتبة Cropper.js
console.log('=== فحص مكتبة Cropper.js ===');
console.log('Cropper:', typeof Cropper !== 'undefined' ? '✅ متوفر' : '❌ مفقود');

// 5. اختبار إنشاء ملف وهمي واستدعاء أداة القص
function testCropper() {
    console.log('=== اختبار أداة القص ===');
    
    if (!window.showCropperModal) {
        console.error('❌ أداة القص غير متوفرة');
        return;
    }
    
    // إنشاء ملف اختبار (أحمر 100x100)
    const canvas = document.createElement('canvas');
    canvas.width = 100;
    canvas.height = 100;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'red';
    ctx.fillRect(0, 0, 100, 100);
    
    canvas.toBlob(function(blob) {
        const testFile = new File([blob], 'test-image.png', { type: 'image/png' });
        console.log('📸 اختبار ملف:', testFile);
        
        window.showCropperModal(testFile, function(croppedFile, error) {
            if (error) {
                console.error('❌ خطأ في أداة القص:', error);
            } else if (croppedFile) {
                console.log('✅ نجح اختبار أداة القص:', croppedFile);
            } else {
                console.warn('⚠️ تم إلغاء اختبار أداة القص');
            }
        });
    }, 'image/png');
}

// تشغيل الاختبار
console.log('=== بدء الاختبار ===');
console.log('لتشغيل اختبار أداة القص، اكتب: testCropper()');

// جعل دالة الاختبار متاحة عالمياً
window.testCropper = testCropper;
