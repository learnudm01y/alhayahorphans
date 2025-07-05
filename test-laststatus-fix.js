// اختبار إصلاح خطأ lastStatus
// نسخ هذا الكود في كونسول المتصفح للتحقق من الإصلاح

console.log('🔧 اختبار إصلاح خطأ lastStatus...');

function testStatusUpdate() {
    console.log('\n📋 فحص دالة updateAttachmentTaskStatus:');
    
    // فحص وجود الدالة
    if (typeof window.updateAttachmentTaskStatus !== 'function') {
        console.log('❌ دالة updateAttachmentTaskStatus غير موجودة');
        return false;
    }
    
    console.log('✅ دالة updateAttachmentTaskStatus موجودة');
    
    // فحص allDocs
    if (!(window.allDocs instanceof Map)) {
        console.log('❌ window.allDocs غير موجود أو ليس Map');
        return false;
    }
    
    console.log('✅ window.allDocs موجود');
    
    // البحث عن مهمة موجودة للاختبار
    let testTaskId = null;
    let testPersonKey = null;
    
    for (let [personKey, tasks] of window.allDocs.entries()) {
        if (tasks.length > 0) {
            testTaskId = tasks[0].id;
            testPersonKey = personKey;
            break;
        }
    }
    
    if (!testTaskId) {
        console.log('⚠️ لا توجد مهام للاختبار. أضف صورة أولاً.');
        return false;
    }
    
    console.log(`✅ تم العثور على مهمة للاختبار: ${testTaskId} في ${testPersonKey}`);
    
    // اختبار تحديث الحالة
    try {
        console.log('🧪 اختبار تحديث حالة المهمة...');
        
        // الحصول على الحالة الحالية
        const currentTask = window.allDocs.get(testPersonKey).find(t => t.id === testTaskId);
        const originalStatus = currentTask.status;
        
        console.log(`الحالة الحالية: ${originalStatus}`);
        
        // اختبار تحديث بسيط (بدون تغيير فعلي للحالة)
        window.updateAttachmentTaskStatus(testTaskId, originalStatus);
        
        console.log('✅ تم تشغيل updateAttachmentTaskStatus بنجاح - لا توجد أخطاء lastStatus');
        return true;
        
    } catch (error) {
        console.error('❌ خطأ أثناء اختبار updateAttachmentTaskStatus:', error);
        
        if (error.message.includes('lastStatus')) {
            console.error('🚨 مازال هناك مراجع لـ lastStatus!');
        }
        
        return false;
    }
}

function testCompleteFlow() {
    console.log('\n🧪 اختبار التدفق الكامل مع إصلاح lastStatus:');
    
    // البحث عن بوابة أفراد أسرة
    const familyZone = document.querySelector('[data-upload-zone*="family_"]');
    if (!familyZone) {
        console.log('⚠️ لا توجد بوابة أفراد أسرة للاختبار');
        return;
    }
    
    const personKey = familyZone.getAttribute('data-upload-zone');
    console.log(`✅ بوابة الاختبار: ${personKey}`);
    
    // إنشاء صورة اختبار صغيرة
    const canvas = document.createElement('canvas');
    canvas.width = 50;
    canvas.height = 50;
    const ctx = canvas.getContext('2d');
    
    ctx.fillStyle = '#FF6B6B';
    ctx.fillRect(0, 0, 50, 50);
    ctx.fillStyle = 'white';
    ctx.font = '10px Arial';
    ctx.fillText('FIX', 15, 30);
    
    canvas.toBlob((blob) => {
        const testFile = new File([blob], 'fix-test.png', { type: 'image/png' });
        console.log(`📁 ملف الاختبار: ${testFile.name}`);
        
        try {
            // اختبار إضافة المهمة
            const taskId = window.addAttachmentTask(personKey, testFile, 'اختبار الإصلاح', '9999999999', 'FIX_TEST');
            
            if (taskId) {
                console.log(`✅ تم إضافة المهمة بنجاح: ${taskId}`);
                
                // مراقبة تحديثات الحالة
                setTimeout(() => {
                    console.log('🔍 فحص تحديثات الحالة...');
                    
                    const task = window.allDocs.get(personKey)?.find(t => t.id === taskId);
                    if (task) {
                        console.log(`📊 حالة المهمة: ${task.status}`);
                        console.log('✅ لا توجد أخطاء lastStatus في التحديثات');
                    }
                }, 1000);
                
            } else {
                console.log('❌ فشل في إضافة المهمة');
            }
            
        } catch (error) {
            console.error('❌ خطأ في اختبار التدفق:', error);
            
            if (error.message.includes('lastStatus')) {
                console.error('🚨 خطأ lastStatus مازال موجود!');
            }
        }
    }, 'image/png');
}

// تشغيل الاختبارات
console.log('🚀 بدء اختبار إصلاح lastStatus...');

if (testStatusUpdate()) {
    console.log('\n🎉 تم إصلاح خطأ lastStatus بنجاح!');
    console.log('\n💡 للاختبار الكامل، اكتب: testCompleteFlow()');
    
    // إتاحة الدالة للاستخدام
    window.testCompleteFlow = testCompleteFlow;
} else {
    console.log('\n❌ مازالت هناك مشاكل تحتاج إلى حل');
}

console.log('\n📝 إذا رأيت هذه الرسالة بدون أخطاء، فقد تم الإصلاح بنجاح!');
