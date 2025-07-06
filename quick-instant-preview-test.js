// اختبار سريع لمنظومة الإظهار المؤقت - أفراد الأسرة
// تاريخ: 2025-01-06

console.log('🚀 بدء اختبار منظومة الإظهار المؤقت...');

// اختبار 1: التحقق من window.allDocs
function testAllDocs() {
    console.log('📋 اختبار 1: فحص window.allDocs');

    if (typeof window.allDocs !== 'undefined') {
        if (window.allDocs instanceof Map) {
            console.log('✅ window.allDocs متاح ومهيأ كـ Map');
            console.log(`📊 عدد المفاتيح الحالية: ${window.allDocs.size}`);
            console.log(`🔑 المفاتيح: [${Array.from(window.allDocs.keys()).join(', ')}]`);
            return true;
        } else {
            console.log('⚠️ window.allDocs موجود لكنه ليس Map');
            console.log(`📄 النوع الحالي: ${typeof window.allDocs}`);
            return false;
        }
    } else {
        console.log('❌ window.allDocs غير متاح');
        return false;
    }
}

// اختبار 2: التحقق من دوال أفراد الأسرة
function testFamilyFunctions() {
    console.log('👨‍👩‍👧‍👦 اختبار 2: فحص دوال أفراد الأسرة');

    const functions = [
        'addFamilyMember',
        'reindexFamilyMembers',
        'setupDocumentUploadHandlersForMember'
    ];

    let allAvailable = true;

    functions.forEach(funcName => {
        if (typeof window[funcName] === 'function') {
            console.log(`✅ ${funcName}: متاحة`);
        } else {
            console.log(`❌ ${funcName}: غير متاحة`);
            allAvailable = false;
        }
    });

    return allAvailable;
}

// اختبار 3: التحقق من دالة المقص
function testCropperFunction() {
    console.log('✂️ اختبار 3: فحص دالة المقص');

    if (typeof window.showCropperModal === 'function') {
        console.log('✅ دالة المقص متاحة');
        return true;
    } else {
        console.log('❌ دالة المقص غير متاحة');
        console.log('ℹ️ سيتم استخدام نسخة محاكاة للاختبار');

        // إنشاء نسخة محاكاة للاختبار
        window.showCropperModal = function(file, callback) {
            console.log('🎭 استخدام نسخة محاكاة من المقص');
            setTimeout(() => {
                const croppedFile = new File([file], file.name.replace('.', '_cropped.'), {
                    type: file.type,
                    lastModified: Date.now()
                });
                callback(croppedFile);
            }, 1000);
        };

        return false;
    }
}

// اختبار 4: التحقق من عناصر الواجهة
function testUIElements() {
    console.log('🎨 اختبار 4: فحص عناصر الواجهة');

    const elements = [
        { id: 'familyMembersContainer', name: 'حاوي أفراد الأسرة' },
        { id: 'familyMemberTemplate', name: 'قالب فرد الأسرة' },
        { id: 'addFamilyMember', name: 'زر إضافة فرد' }
    ];

    let allFound = true;

    elements.forEach(element => {
        const el = document.getElementById(element.id);
        if (el) {
            console.log(`✅ ${element.name}: موجود`);
        } else {
            console.log(`❌ ${element.name}: غير موجود`);
            allFound = false;
        }
    });

    return allFound;
}

// اختبار 5: محاكاة الإظهار المؤقت
function testInstantPreview() {
    console.log('⚡ اختبار 5: محاكاة الإظهار المؤقت');

    try {
        // إنشاء وثيقة مؤقتة للاختبار
        const mockTempDoc = {
            type: 'identity',
            typeText: 'هوية شخصية',
            file: new File([''], 'test.jpg', { type: 'image/jpeg' }),
            processedFile: null,
            docName: 'identity_123_456.jpg',
            personId: '456',
            fileId: '123',
            personKey: 'family_test',
            isTemporary: true,
            timestamp: Date.now()
        };

        // إضافة إلى window.allDocs
        if (window.allDocs && window.allDocs instanceof Map) {
            let docsArr = window.allDocs.get('family_test') || [];
            docsArr.push(mockTempDoc);
            window.allDocs.set('family_test', docsArr);

            console.log('✅ تم إنشاء وثيقة مؤقتة للاختبار');
            console.log(`📋 الوثيقة: ${mockTempDoc.docName}`);
            console.log(`🔑 المفتاح: ${mockTempDoc.personKey}`);
            console.log(`⏰ مؤقتة: ${mockTempDoc.isTemporary}`);

            return true;
        } else {
            console.log('❌ فشل في إضافة الوثيقة المؤقتة');
            return false;
        }
    } catch (error) {
        console.log('❌ خطأ في اختبار الإظهار المؤقت:', error);
        return false;
    }
}

// اختبار 6: محاكاة عملية الحذف
function testTemporaryDeletion() {
    console.log('🗑️ اختبار 6: محاكاة حذف الوثائق المؤقتة');

    try {
        if (window.allDocs && window.allDocs instanceof Map && window.allDocs.has('family_test')) {
            let docsArr = window.allDocs.get('family_test');
            const tempIndex = docsArr.findIndex(doc => doc.isTemporary === true);

            if (tempIndex !== -1) {
                const deletedDoc = docsArr.splice(tempIndex, 1)[0];
                window.allDocs.set('family_test', docsArr);

                console.log('✅ تم حذف الوثيقة المؤقتة بنجاح');
                console.log(`📋 الوثيقة المحذوفة: ${deletedDoc.docName}`);

                return true;
            } else {
                console.log('⚠️ لم يتم العثور على وثيقة مؤقتة للحذف');
                return false;
            }
        } else {
            console.log('❌ لا توجد وثائق للحذف');
            return false;
        }
    } catch (error) {
        console.log('❌ خطأ في اختبار الحذف:', error);
        return false;
    }
}

// تشغيل جميع الاختبارات
function runAllTests() {
    console.log('🎯 بدء تشغيل جميع اختبارات منظومة الإظهار المؤقت...');
    console.log('='.repeat(60));

    const results = {
        allDocs: testAllDocs(),
        familyFunctions: testFamilyFunctions(),
        cropperFunction: testCropperFunction(),
        uiElements: testUIElements(),
        instantPreview: testInstantPreview(),
        temporaryDeletion: testTemporaryDeletion()
    };

    console.log('='.repeat(60));
    console.log('📊 ملخص النتائج:');

    let passedTests = 0;
    let totalTests = Object.keys(results).length;

    Object.keys(results).forEach(test => {
        const status = results[test] ? '✅ نجح' : '❌ فشل';
        console.log(`${test}: ${status}`);
        if (results[test]) passedTests++;
    });

    console.log('='.repeat(60));
    console.log(`🎉 النتيجة النهائية: ${passedTests}/${totalTests} اختبارات نجحت`);

    if (passedTests === totalTests) {
        console.log('🎊 تهانينا! جميع الاختبارات نجحت - المنظومة جاهزة!');
    } else if (passedTests >= totalTests * 0.8) {
        console.log('👍 معظم الاختبارات نجحت - المنظومة تعمل بشكل جيد');
    } else {
        console.log('⚠️ بعض الاختبارات فشلت - تحتاج لمراجعة');
    }

    return results;
}

// تشغيل الاختبارات تلقائياً عند التحميل
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(runAllTests, 1000);
    });
} else {
    setTimeout(runAllTests, 1000);
}

// تصدير الدوال للاستخدام اليدوي
window.instantPreviewTests = {
    runAll: runAllTests,
    testAllDocs,
    testFamilyFunctions,
    testCropperFunction,
    testUIElements,
    testInstantPreview,
    testTemporaryDeletion
};

console.log('ℹ️ للتشغيل اليدوي: استخدم window.instantPreviewTests.runAll()');
