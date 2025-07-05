// اختبار عرض الصور الفوري في بوابة أفراد الأسرة
// استخدم هذا الكود في كونسول المتصفح للتشخيص

console.log('🧪 بدء اختبار العرض الفوري للصور في بوابة أفراد الأسرة...');

// 1. فحص البيانات المحفوظة
function checkAllDocsData() {
    console.log('\n📊 فحص بيانات window.allDocs:');
    
    if (!window.allDocs || !(window.allDocs instanceof Map)) {
        console.log('❌ window.allDocs غير موجود أو ليس Map');
        return false;
    }
    
    console.log('✅ window.allDocs موجود. عدد البوابات:', window.allDocs.size);
    
    window.allDocs.forEach((tasks, personKey) => {
        console.log(`\n📁 بوابة "${personKey}":`);
        console.log(`   عدد المهام: ${tasks.length}`);
        
        tasks.forEach((task, index) => {
            console.log(`   المهمة ${index + 1}:`, {
                id: task.id,
                status: task.status,
                fileName: task.originalFile ? task.originalFile.name : 'لا يوجد',
                hasOriginalFile: !!task.originalFile,
                hasProcessedFile: !!task.processedFile,
                isProcessed: task.isProcessed,
                isImage: task.originalFile ? task.originalFile.type.startsWith('image/') : false
            });
        });
    });
    
    return true;
}

// 2. فحص عرض الصور في الواجهة
function checkImageDisplay() {
    console.log('\n🖼️ فحص عرض الصور في الواجهة:');
    
    const familyZones = document.querySelectorAll('[data-upload-zone*="family_"]');
    console.log('عدد بوابات أفراد الأسرة الموجودة:', familyZones.length);
    
    familyZones.forEach((zone, index) => {
        const personKey = zone.getAttribute('data-upload-zone');
        const previewDiv = zone.querySelector('.mainDocumentPreview');
        const cards = previewDiv ? previewDiv.querySelectorAll('.card') : [];
        const images = previewDiv ? previewDiv.querySelectorAll('img') : [];
        
        console.log(`\n📋 بوابة ${index + 1} (${personKey}):`);
        console.log(`   عدد البطاقات: ${cards.length}`);
        console.log(`   عدد الصور: ${images.length}`);
        
        images.forEach((img, imgIndex) => {
            console.log(`   الصورة ${imgIndex + 1}:`, {
                src: img.src ? 'موجود' : 'مفقود',
                display: getComputedStyle(img).display,
                opacity: getComputedStyle(img).opacity,
                width: img.style.maxWidth || 'auto',
                height: img.style.maxHeight || 'auto'
            });
        });
    });
}

// 3. فحص مطابقة البيانات مع العرض
function checkDataToDisplayMatching() {
    console.log('\n🔍 فحص مطابقة البيانات مع العرض:');
    
    if (!window.allDocs) {
        console.log('❌ لا توجد بيانات للمقارنة');
        return;
    }
    
    window.allDocs.forEach((tasks, personKey) => {
        const imageTasks = tasks.filter(task => 
            task.originalFile && task.originalFile.type.startsWith('image/')
        );
        
        if (imageTasks.length === 0) {
            console.log(`⚪ بوابة "${personKey}": لا توجد صور`);
            return;
        }
        
        const zone = document.querySelector(`[data-upload-zone="${personKey}"]`);
        if (!zone) {
            console.log(`❌ بوابة "${personKey}": المنطقة غير موجودة في الصفحة`);
            return;
        }
        
        const previewDiv = zone.querySelector('.mainDocumentPreview');
        const displayedImages = previewDiv ? previewDiv.querySelectorAll('img').length : 0;
        
        console.log(`📊 بوابة "${personKey}":`);
        console.log(`   صور في البيانات: ${imageTasks.length}`);
        console.log(`   صور معروضة: ${displayedImages}`);
        console.log(`   المطابقة: ${imageTasks.length === displayedImages ? '✅' : '❌'}`);
        
        // تفاصيل كل صورة
        imageTasks.forEach((task, index) => {
            console.log(`   صورة ${index + 1}:`, {
                اسم_الملف: task.originalFile.name,
                الحالة: task.status,
                معالج: task.isProcessed ? 'نعم' : 'لا',
                يجب_عرضها: task.originalFile ? 'نعم' : 'لا'
            });
        });
    });
}

// 4. محاكاة رفع صورة تجريبية
function simulateImageUpload() {
    console.log('\n🧪 محاكاة رفع صورة تجريبية...');
    
    // البحث عن أول بوابة أفراد أسرة
    const familyZone = document.querySelector('[data-upload-zone*="family_"]');
    if (!familyZone) {
        console.log('❌ لا توجد بوابة أفراد أسرة للاختبار');
        console.log('💡 قم بإضافة فرد أسرة أولاً');
        return;
    }
    
    const personKey = familyZone.getAttribute('data-upload-zone');
    console.log('✅ تم العثور على بوابة للاختبار:', personKey);
    
    // إنشاء صورة تجريبية
    const canvas = document.createElement('canvas');
    canvas.width = 100;
    canvas.height = 100;
    const ctx = canvas.getContext('2d');
    
    // رسم صورة ملونة
    ctx.fillStyle = '#FF6B6B';
    ctx.fillRect(0, 0, 100, 100);
    ctx.fillStyle = '#4ECDC4';
    ctx.fillRect(25, 25, 50, 50);
    ctx.fillStyle = '#FFE66D';
    ctx.arc(50, 50, 15, 0, 2 * Math.PI);
    ctx.fill();
    
    canvas.toBlob(function(blob) {
        if (!blob) {
            console.log('❌ فشل في إنشاء صورة تجريبية');
            return;
        }
        
        const testFile = new File([blob], 'test-family-image.png', { type: 'image/png' });
        console.log('✅ تم إنشاء صورة تجريبية:', testFile.name, testFile.size, 'bytes');
        
        // محاكاة إضافة المهمة
        if (window.addAttachmentTask) {
            const taskId = window.addAttachmentTask(personKey, testFile, 'test_doc', '1234567890', 'TEST123');
            
            if (taskId) {
                console.log('✅ تم إضافة المهمة التجريبية:', taskId);
                console.log('🔍 تحقق من العرض في الواجهة الآن...');
                
                // انتظار قصير ثم فحص النتيجة
                setTimeout(() => {
                    console.log('\n📊 نتيجة الاختبار:');
                    checkImageDisplay();
                }, 1000);
            } else {
                console.log('❌ فشل في إضافة المهمة التجريبية');
            }
        } else {
            console.log('❌ دالة addAttachmentTask غير متوفرة');
        }
    }, 'image/png');
}

// 5. تشخيص شامل
function runFullDiagnostic() {
    console.log('🚀 بدء التشخيص الشامل للعرض الفوري...\n');
    
    const results = {
        allDocsExists: checkAllDocsData(),
        imageDisplayCheck: true,
        dataMatching: true
    };
    
    try {
        checkImageDisplay();
        checkDataToDisplayMatching();
    } catch (error) {
        console.error('❌ خطأ في التشخيص:', error);
        results.imageDisplayCheck = false;
        results.dataMatching = false;
    }
    
    console.log('\n📋 ملخص التشخيص:');
    console.log('بيانات allDocs:', results.allDocsExists ? '✅' : '❌');
    console.log('عرض الصور:', results.imageDisplayCheck ? '✅' : '❌');
    console.log('مطابقة البيانات:', results.dataMatching ? '✅' : '❌');
    
    if (Object.values(results).every(r => r)) {
        console.log('\n🎉 جميع الفحوصات نجحت!');
        console.log('💡 لاختبار رفع صورة تجريبية، اكتب: simulateImageUpload()');
    } else {
        console.log('\n⚠️ توجد مشاكل تحتاج إلى حل');
    }
    
    return results;
}

// تصدير الدوال للاستخدام
window.familyImageTest = {
    checkAllDocsData,
    checkImageDisplay,
    checkDataToDisplayMatching,
    simulateImageUpload,
    runFullDiagnostic
};

// تشغيل التشخيص التلقائي
runFullDiagnostic();

console.log('\n💡 الدوال المتاحة:');
console.log('- familyImageTest.runFullDiagnostic() - تشخيص شامل');
console.log('- familyImageTest.simulateImageUpload() - اختبار رفع صورة تجريبية');
console.log('- familyImageTest.checkImageDisplay() - فحص عرض الصور');
console.log('- familyImageTest.checkDataToDisplayMatching() - فحص مطابقة البيانات');
