// اختبار سريع للعرض الفوري والحالات المحدّثة
// نسخ هذا في كونسول المتصفح بعد فتح صفحة التسجيل

console.log('🧪 اختبار العرض الفوري المحدّث...');

function quickImageTest() {
    console.log('\n📸 اختبار العرض الفوري للصور:');
    
    // البحث عن بوابة أفراد أسرة
    const familyZone = document.querySelector('[data-upload-zone*="family_"]');
    if (!familyZone) {
        console.log('❌ لا توجد بوابة أفراد أسرة. أضف فرد أسرة أولاً.');
        return false;
    }
    
    const personKey = familyZone.getAttribute('data-upload-zone');
    console.log(`✅ تم العثور على بوابة: ${personKey}`);
    
    // إنشاء صورة اختبار ملونة
    const canvas = document.createElement('canvas');
    canvas.width = 150;
    canvas.height = 150;
    const ctx = canvas.getContext('2d');
    
    // رسم نمط جميل
    const gradient = ctx.createLinearGradient(0, 0, 150, 150);
    gradient.addColorStop(0, '#FF6B6B');
    gradient.addColorStop(0.5, '#4ECDC4');
    gradient.addColorStop(1, '#45B7D1');
    
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, 150, 150);
    
    // إضافة دائرة بيضاء في المنتصف
    ctx.fillStyle = 'white';
    ctx.beginPath();
    ctx.arc(75, 75, 30, 0, 2 * Math.PI);
    ctx.fill();
    
    // إضافة نص
    ctx.fillStyle = 'black';
    ctx.font = 'bold 12px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('INSTANT', 75, 70);
    ctx.fillText('TEST', 75, 85);
    
    canvas.toBlob((blob) => {
        const testFile = new File([blob], 'instant-test.png', { type: 'image/png' });
        console.log(`📁 إنشاء ملف اختبار: ${testFile.name} (${testFile.size} bytes)`);
        
        // تسجيل الحالة قبل الإضافة
        const beforeTasks = window.allDocs.get(personKey) || [];
        console.log(`📊 المهام قبل الاختبار: ${beforeTasks.length}`);
        
        // إضافة المهمة
        console.log('🚀 إضافة مهمة جديدة...');
        const taskId = window.addAttachmentTask(personKey, testFile, 'اختبار العرض الفوري', '1234567890', 'INSTANT_TEST');
        
        if (taskId) {
            console.log(`✅ تم إضافة المهمة: ${taskId}`);
            
            // فحص فوري
            setTimeout(() => {
                console.log('\n🔍 فحص العرض بعد 50ms:');
                checkTaskDisplay(taskId, personKey);
            }, 50);
            
            // فحص بعد ثانية
            setTimeout(() => {
                console.log('\n🔍 فحص الحالة بعد 1 ثانية:');
                checkTaskDisplay(taskId, personKey);
            }, 1000);
            
            // فحص بعد 3 ثوان (يجب أن تكتمل المعالجة)
            setTimeout(() => {
                console.log('\n🔍 فحص الحالة النهائية بعد 3 ثوان:');
                checkTaskDisplay(taskId, personKey);
            }, 3000);
            
        } else {
            console.log('❌ فشل في إضافة المهمة');
        }
    }, 'image/png');
}

function checkTaskDisplay(taskId, personKey) {
    // فحص البيانات
    const tasks = window.allDocs.get(personKey) || [];
    const task = tasks.find(t => t.id === taskId);
    
    if (!task) {
        console.log('❌ المهمة غير موجودة في البيانات');
        return;
    }
    
    console.log('📊 حالة المهمة في البيانات:', {
        id: task.id,
        status: task.status,
        isProcessed: task.isProcessed,
        hasOriginalFile: !!task.originalFile,
        hasProcessedFile: !!task.processedFile,
        errorMessage: task.errorMessage
    });
    
    // فحص العرض
    const card = document.getElementById('preview_att_' + taskId);
    if (card) {
        console.log('✅ بطاقة المهمة موجودة في الواجهة');
        
        const img = card.querySelector('img');
        const overlay = card.querySelector('.processing-overlay');
        const statusBadge = card.querySelector('.status-badge');
        
        console.log('🖼️ عناصر العرض:', {
            image: img ? '✅ موجود' : '❌ مفقود',
            imageSrc: img ? (img.src ? 'محدد' : 'غير محدد') : 'لا يوجد',
            imageOpacity: img ? getComputedStyle(img).opacity : 'لا يوجد',
            overlay: overlay ? '✅ موجود' : '❌ مفقود',
            statusBadge: statusBadge ? statusBadge.textContent.trim() : '❌ مفقود'
        });
        
        if (img && img.src) {
            console.log('✅ الصورة معروضة بنجاح');
        } else {
            console.log('❌ الصورة غير معروضة');
        }
        
    } else {
        console.log('❌ بطاقة المهمة غير موجودة في الواجهة');
    }
}

function checkCurrentState() {
    console.log('\n📋 فحص الحالة الحالية:');
    
    // فحص allDocs
    if (window.allDocs instanceof Map) {
        console.log(`✅ window.allDocs موجود (${window.allDocs.size} بوابات)`);
        
        window.allDocs.forEach((tasks, personKey) => {
            console.log(`   بوابة ${personKey}: ${tasks.length} مهام`);
            tasks.forEach((task, index) => {
                console.log(`     ${index + 1}. ${task.originalFile.name} - ${task.status}`);
            });
        });
    } else {
        console.log('❌ window.allDocs غير موجود أو ليس Map');
    }
    
    // فحص الدوال المطلوبة
    const functions = [
        'addAttachmentTask',
        'renderAttachmentTasksUI', 
        'updateAttachmentTaskStatus',
        'processAttachment'
    ];
    
    console.log('\n🔧 فحص الدوال:');
    functions.forEach(funcName => {
        const exists = typeof window[funcName] === 'function';
        console.log(`   ${exists ? '✅' : '❌'} ${funcName}`);
    });
    
    // فحص بوابات أفراد الأسرة
    const familyZones = document.querySelectorAll('[data-upload-zone*="family_"]');
    console.log(`\n👨‍👩‍👧‍👦 بوابات أفراد الأسرة: ${familyZones.length}`);
}

// تشغيل فحص أولي
checkCurrentState();

// إتاحة الدالة للاستخدام
window.quickImageTest = quickImageTest;

console.log('\n💡 للاختبار، اكتب: quickImageTest()');
console.log('📝 تأكد من إضافة فرد أسرة أولاً إذا لم يكن موجوداً');
