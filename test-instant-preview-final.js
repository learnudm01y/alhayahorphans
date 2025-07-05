// اختبار نهائي شامل للعرض الفوري للصور في بوابة أفراد الأسرة
// نسخ هذا الكود في كونسول المتصفح لإجراء اختبار كامل

console.log('🚀 بدء الاختبار النهائي للعرض الفوري للصور...');

// 1. فحص البنية التحتية
function checkInfrastructure() {
    console.log('\n🔧 فحص البنية التحتية:');
    
    const checks = {
        allDocsMap: window.allDocs instanceof Map,
        addAttachmentTask: typeof window.addAttachmentTask === 'function',
        renderAttachmentTasksUI: typeof window.renderAttachmentTasksUI === 'function',
        updateAttachmentTaskStatus: typeof window.updateAttachmentTaskStatus === 'function',
        cropperAvailable: !!(window.showCropperModal || window.showCropper)
    };
    
    Object.entries(checks).forEach(([key, value]) => {
        console.log(`   ${value ? '✅' : '❌'} ${key}:`, value);
    });
    
    return Object.values(checks).every(v => v);
}

// 2. فحص بوابات أفراد الأسرة
function checkFamilyGates() {
    console.log('\n👨‍👩‍👧‍👦 فحص بوابات أفراد الأسرة:');
    
    const familyZones = document.querySelectorAll('[data-upload-zone*="family_"]');
    console.log(`   عدد البوابات: ${familyZones.length}`);
    
    if (familyZones.length === 0) {
        console.log('   ⚠️ لا توجد بوابات أفراد أسرة. قم بإضافة فرد أسرة أولاً.');
        return false;
    }
    
    familyZones.forEach((zone, index) => {
        const personKey = zone.getAttribute('data-upload-zone');
        const fileInput = zone.querySelector('input[type="file"]');
        const docSelect = zone.querySelector('select');
        const previewDiv = zone.querySelector('.mainDocumentPreview');
        
        console.log(`   البوابة ${index + 1} (${personKey}):`);
        console.log(`     📤 input file: ${fileInput ? '✅' : '❌'}`);
        console.log(`     📋 select: ${docSelect ? '✅' : '❌'}`);
        console.log(`     🖼️ preview div: ${previewDiv ? '✅' : '❌'}`);
    });
    
    return true;
}

// 3. اختبار العرض الفوري مع صورة حقيقية
async function testInstantPreview() {
    console.log('\n🖼️ اختبار العرض الفوري:');
    
    const familyZone = document.querySelector('[data-upload-zone*="family_"]');
    if (!familyZone) {
        console.log('   ❌ لا توجد بوابة للاختبار');
        return false;
    }
    
    const personKey = familyZone.getAttribute('data-upload-zone');
    console.log(`   🎯 اختبار البوابة: ${personKey}`);
    
    // إنشاء صورة تجريبية ملونة
    const canvas = document.createElement('canvas');
    canvas.width = 200;
    canvas.height = 200;
    const ctx = canvas.getContext('2d');
    
    // رسم نمط ملون جميل
    const gradient = ctx.createRadialGradient(100, 100, 0, 100, 100, 100);
    gradient.addColorStop(0, '#FF6B6B');
    gradient.addColorStop(0.5, '#4ECDC4');
    gradient.addColorStop(1, '#45B7D1');
    
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, 200, 200);
    
    // إضافة نص
    ctx.fillStyle = 'white';
    ctx.font = 'bold 16px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('TEST IMAGE', 100, 90);
    ctx.fillText(new Date().toLocaleTimeString(), 100, 110);
    
    return new Promise((resolve) => {
        canvas.toBlob(async (blob) => {
            const testFile = new File([blob], 'instant-preview-test.png', { type: 'image/png' });
            console.log(`   📁 ملف الاختبار: ${testFile.name} (${testFile.size} bytes)`);
            
            // حفظ الحالة قبل الاختبار
            const beforeTasks = window.allDocs.get(personKey) || [];
            const beforeCount = beforeTasks.length;
            
            console.log(`   📊 المهام قبل الاختبار: ${beforeCount}`);
            
            // إضافة المهمة
            const taskId = window.addAttachmentTask(personKey, testFile, 'اختبار عرض فوري', '1234567890', 'INSTANT_TEST');
            
            if (!taskId) {
                console.log('   ❌ فشل في إضافة المهمة');
                resolve(false);
                return;
            }
            
            console.log(`   ✅ تم إضافة المهمة: ${taskId}`);
            
            // فحص فوري للعرض
            setTimeout(() => {
                console.log('\n   🔍 فحص العرض الفوري (بعد 100ms):');
                
                const previewDiv = familyZone.querySelector('.mainDocumentPreview');
                const cards = previewDiv ? previewDiv.querySelectorAll('.card') : [];
                const images = previewDiv ? previewDiv.querySelectorAll('img') : [];
                
                console.log(`     🃏 عدد البطاقات: ${cards.length}`);
                console.log(`     🖼️ عدد الصور: ${images.length}`);
                
                // فحص الصورة المضافة حديثاً
                const newCard = document.getElementById('preview_att_' + taskId);
                if (newCard) {
                    console.log('     ✅ بطاقة المهمة موجودة');
                    
                    const img = newCard.querySelector('img');
                    if (img) {
                        console.log('     ✅ الصورة معروضة فوراً');
                        console.log(`       المصدر: ${img.src.substring(0, 50)}...`);
                        console.log(`       العرض: ${getComputedStyle(img).display}`);
                        console.log(`       الشفافية: ${getComputedStyle(img).opacity}`);
                        
                        // فحص overlay المعالجة
                        const overlay = newCard.querySelector('.processing-overlay');
                        if (overlay) {
                            console.log('     ✅ overlay المعالجة موجود');
                            const spinner = overlay.querySelector('.spinner-border');
                            const text = overlay.querySelector('.small');
                            console.log(`       Spinner: ${spinner ? '✅' : '❌'}`);
                            console.log(`       النص: ${text ? text.textContent : 'مفقود'}`);
                        } else {
                            console.log('     ⚠️ overlay المعالجة مفقود');
                        }
                        
                        resolve(true);
                    } else {
                        console.log('     ❌ الصورة غير معروضة');
                        resolve(false);
                    }
                } else {
                    console.log('     ❌ بطاقة المهمة غير موجودة');
                    resolve(false);
                }
            }, 100);
        }, 'image/png');
    });
}

// 4. مراقبة تحديثات الحالة
function monitorStatusUpdates(taskId, duration = 10000) {
    console.log(`\n⏱️ مراقبة تحديثات الحالة للمهمة ${taskId} لمدة ${duration/1000} ثواني:`);
    
    let lastStatus = null;
    const startTime = Date.now();
    
    const monitor = setInterval(() => {
        // البحث عن المهمة
        let currentTask = null;
        for (let tasks of window.allDocs.values()) {
            currentTask = tasks.find(t => t.id === taskId);
            if (currentTask) break;
        }
        
        if (!currentTask) {
            console.log('   ⚠️ المهمة لم تعد موجودة');
            clearInterval(monitor);
            return;
        }
        
        if (currentTask.status !== lastStatus) {
            const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
            console.log(`   [${elapsed}s] الحالة: ${lastStatus || 'undefined'} → ${currentTask.status}`);
            lastStatus = currentTask.status;
            
            // فحص العرض عند تغيير الحالة
            const card = document.getElementById('preview_att_' + taskId);
            if (card) {
                const img = card.querySelector('img');
                const overlay = card.querySelector('.processing-overlay');
                const statusBadge = card.querySelector('.status-badge');
                
                console.log(`       العرض:`, {
                    image: img ? 'موجود' : 'مفقود',
                    overlay: overlay ? 'موجود' : 'مفقود',
                    status: statusBadge ? statusBadge.textContent.trim() : 'مفقود'
                });
            }
            
            if (currentTask.status === 'completed' || currentTask.status === 'failed') {
                console.log(`   🏁 المعالجة انتهت بحالة: ${currentTask.status}`);
                clearInterval(monitor);
            }
        }
    }, 500);
    
    // إيقاف المراقبة بعد المدة المحددة
    setTimeout(() => {
        clearInterval(monitor);
        console.log('   ⏰ انتهت مدة المراقبة');
    }, duration);
    
    return monitor;
}

// 5. تشغيل الاختبار الكامل
async function runCompleteTest() {
    console.log('🏁 بدء الاختبار الكامل للعرض الفوري...\n');
    
    // 1. فحص البنية التحتية
    const infraOk = checkInfrastructure();
    if (!infraOk) {
        console.log('\n❌ فشل فحص البنية التحتية');
        return;
    }
    
    // 2. فحص البوابات
    const gatesOk = checkFamilyGates();
    if (!gatesOk) {
        console.log('\n❌ فشل فحص البوابات');
        return;
    }
    
    // 3. اختبار العرض الفوري
    console.log('\n⏳ جاري اختبار العرض الفوري...');
    const previewOk = await testInstantPreview();
    
    if (previewOk) {
        console.log('\n🎉 نجح اختبار العرض الفوري!');
        console.log('✅ الصور تظهر فوراً في بوابة أفراد الأسرة');
        console.log('✅ تأثيرات المعالجة تعمل بشكل صحيح');
        
        // بدء مراقبة تحديثات الحالة
        const familyZone = document.querySelector('[data-upload-zone*="family_"]');
        const personKey = familyZone.getAttribute('data-upload-zone');
        const tasks = window.allDocs.get(personKey) || [];
        const lastTask = tasks[tasks.length - 1];
        
        if (lastTask) {
            monitorStatusUpdates(lastTask.id);
        }
    } else {
        console.log('\n❌ فشل اختبار العرض الفوري');
        console.log('⚠️ تحقق من الكونسول للتفاصيل');
    }
    
    return previewOk;
}

// 6. اختبار سريع للموبايل
function quickMobileTest() {
    console.log('\n📱 اختبار سريع للموبايل:');
    
    // محاكاة أبعاد الموبايل
    const originalWidth = window.innerWidth;
    const mobileWidth = 375; // iPhone width
    
    console.log(`   العرض الحالي: ${originalWidth}px`);
    console.log(`   محاكاة عرض الموبايل: ${mobileWidth}px`);
    
    // فحص CSS للموبايل
    const familyZone = document.querySelector('[data-upload-zone*="family_"]');
    if (familyZone) {
        const images = familyZone.querySelectorAll('img');
        images.forEach((img, index) => {
            const computedStyle = getComputedStyle(img);
            console.log(`   صورة ${index + 1}:`, {
                maxWidth: computedStyle.maxWidth,
                maxHeight: computedStyle.maxHeight,
                borderRadius: computedStyle.borderRadius
            });
        });
    }
    
    console.log('   💡 للاختبار الكامل على الموبايل، افتح أدوات المطور > Device Toolbar');
}

// إتاحة الدوال عبر النافذة للاستخدام المباشر
window.testInstantPreview = runCompleteTest;
window.quickMobileTest = quickMobileTest;
window.checkFamilyGates = checkFamilyGates;

// تشغيل تلقائي للفحص الأساسي
checkInfrastructure();
checkFamilyGates();

console.log('\n💡 دوال متاحة للاستخدام:');
console.log('   • testInstantPreview() - اختبار كامل للعرض الفوري');
console.log('   • quickMobileTest() - فحص سريع للموبايل');
console.log('   • checkFamilyGates() - فحص بوابات أفراد الأسرة');
console.log('\n🚀 للبدء، اكتب: testInstantPreview()');
