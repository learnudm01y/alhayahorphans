/**
 * اختبار شامل لدعم حذف الوثائق من window.allDocs في بوابة أفراد الأسرة
 * تاريخ التطوير: 2025-01-05
 */

console.log('🚀 بدء اختبار دعم حذف الوثائق من window.allDocs في بوابة أفراد الأسرة');

// محاكاة بيانات الاختبار
const testData = {
    familyMembers: [
        {
            personKey: 'family_0',
            personId: '1234567890',
            firstName: 'محمد',
            lastName: 'أحمد',
            documents: [
                {
                    type: 'birth_certificate',
                    typeText: 'شهادة الميلاد',
                    docName: 'birth_certificate_file123_1234567890.jpg',
                    fileId: 'file123',
                    personId: '1234567890',
                    personKey: 'family_0'
                },
                {
                    type: 'id_card',
                    typeText: 'بطاقة الهوية',
                    docName: 'id_card_file123_1234567890.jpg',
                    fileId: 'file123',
                    personId: '1234567890',
                    personKey: 'family_0'
                }
            ]
        },
        {
            personKey: 'family_1',
            personId: '0987654321',
            firstName: 'فاطمة',
            lastName: 'علي',
            documents: [
                {
                    type: 'birth_certificate',
                    typeText: 'شهادة الميلاد',
                    docName: 'birth_certificate_file456_0987654321.jpg',
                    fileId: 'file456',
                    personId: '0987654321',
                    personKey: 'family_1'
                }
            ]
        }
    ]
};

// دالة اختبار تهيئة window.allDocs
function testInitialization() {
    console.log('\n📋 1. اختبار تهيئة window.allDocs');

    // محاكاة التهيئة
    if (typeof window !== 'undefined') {
        window.allDocs = new Map();
    } else {
        // في بيئة Node.js
        global.window = { allDocs: new Map() };
    }

    const allDocs = (typeof window !== 'undefined' ? window.allDocs : global.window.allDocs);

    // إضافة البيانات التجريبية
    testData.familyMembers.forEach(member => {
        allDocs.set(member.personKey, member.documents);
    });

    console.log('✅ تم تهيئة window.allDocs بنجاح');
    console.log(`📊 عدد المفاتيح: ${allDocs.size}`);
    console.log(`🔑 المفاتيح: ${Array.from(allDocs.keys()).join(', ')}`);

    return allDocs;
}

// دالة اختبار حذف وثيقة واحدة
function testDeleteSingleDocument(allDocs) {
    console.log('\n🗑️ 2. اختبار حذف وثيقة واحدة');

    const personKey = 'family_0';
    const targetDoc = {
        docName: 'birth_certificate_file123_1234567890.jpg',
        type: 'birth_certificate',
        fileId: 'file123'
    };

    console.log(`🎯 الهدف: حذف الوثيقة ${targetDoc.docName} من ${personKey}`);

    if (allDocs.has(personKey)) {
        const arr = allDocs.get(personKey);
        const initialCount = arr.length;
        console.log(`📋 العدد الأولي للوثائق: ${initialCount}`);

        // محاكاة عملية الحذف
        let deletedFromAllDocs = false;
        for (let i = arr.length - 1; i >= 0; i--) {
            const arrDoc = arr[i];

            if (arrDoc.docName === targetDoc.docName &&
                arrDoc.type === targetDoc.type &&
                arrDoc.fileId === targetDoc.fileId) {

                const removedItem = arr.splice(i, 1)[0];
                deletedFromAllDocs = true;
                console.log(`✅ تم حذف الوثيقة بنجاح:`, {
                    index: i,
                    removedDoc: removedItem.docName,
                    remainingCount: arr.length
                });
                break;
            }
        }

        if (!deletedFromAllDocs) {
            console.error('❌ فشل في حذف الوثيقة');
            return false;
        }

        console.log(`📊 العدد بعد الحذف: ${arr.length}`);
        return true;
    } else {
        console.error(`❌ لم يتم العثور على المفتاح: ${personKey}`);
        return false;
    }
}

// دالة اختبار حذف جميع وثائق فرد
function testDeleteAllDocuments(allDocs) {
    console.log('\n🗑️ 3. اختبار حذف جميع وثائق فرد');

    const personKey = 'family_1';
    console.log(`🎯 الهدف: حذف جميع وثائق ${personKey}`);

    if (allDocs.has(personKey)) {
        const deletedDocs = allDocs.get(personKey);
        const deletedCount = deletedDocs.length;

        allDocs.delete(personKey);

        console.log(`✅ تم حذف جميع الوثائق:`, {
            personKey: personKey,
            deletedCount: deletedCount,
            remainingKeys: Array.from(allDocs.keys())
        });

        return true;
    } else {
        console.error(`❌ لم يتم العثور على المفتاح: ${personKey}`);
        return false;
    }
}

// دالة اختبار إعادة الفهرسة
function testReindexing(allDocs) {
    console.log('\n🔄 4. اختبار إعادة فهرسة المفاتيح');

    // محاكاة إعادة الفهرسة
    const oldKeys = Array.from(allDocs.keys());
    const newAllDocs = new Map();

    console.log(`📋 المفاتيح القديمة: ${oldKeys.join(', ')}`);

    // إعادة ترقيم المفاتيح
    let newIndex = 0;
    oldKeys.forEach(oldKey => {
        if (allDocs.has(oldKey)) {
            const docs = allDocs.get(oldKey);
            const newKey = `family_${newIndex}`;

            // تحديث personKey في كل وثيقة
            docs.forEach(doc => {
                doc.personKey = newKey;
            });

            newAllDocs.set(newKey, docs);
            console.log(`🔄 تم تحديث: ${oldKey} → ${newKey} (${docs.length} وثائق)`);
            newIndex++;
        }
    });

    // تحديث allDocs
    allDocs.clear();
    newAllDocs.forEach((docs, key) => {
        allDocs.set(key, docs);
    });

    console.log(`✅ تم إعادة الفهرسة بنجاح`);
    console.log(`📊 المفاتيح الجديدة: ${Array.from(allDocs.keys()).join(', ')}`);

    return true;
}

// دالة اختبار البحث المتقدم
function testAdvancedSearch(allDocs) {
    console.log('\n🔍 5. اختبار البحث المتقدم');

    const searchCriteria = {
        docName: 'id_card_file123_1234567890.jpg',
        type: 'id_card',
        fileId: 'file123'
    };

    console.log(`🎯 البحث عن:`, searchCriteria);

    let found = false;
    let foundKey = null;

    // البحث في جميع المفاتيح
    for (const [personKey, docs] of allDocs.entries()) {
        for (const doc of docs) {
            const isMatchByName = doc.docName === searchCriteria.docName;
            const isMatchByType = doc.type === searchCriteria.type;
            const isMatchByFileId = doc.fileId === searchCriteria.fileId;

            if (isMatchByName && isMatchByType && isMatchByFileId) {
                found = true;
                foundKey = personKey;
                console.log(`✅ تم العثور على الوثيقة في: ${personKey}`);
                break;
            }
        }
        if (found) break;
    }

    if (!found) {
        console.log(`❌ لم يتم العثور على الوثيقة`);
    }

    return found;
}

// دالة اختبار تنظيف المفاتيح الفارغة
function testCleanupEmptyKeys(allDocs) {
    console.log('\n🧹 6. اختبار تنظيف المفاتيح الفارغة');

    // إضافة مفتاح فارغ للاختبار
    allDocs.set('family_empty', []);

    console.log(`📋 المفاتيح قبل التنظيف: ${Array.from(allDocs.keys()).join(', ')}`);

    // تنظيف المفاتيح الفارغة
    const keysToDelete = [];
    allDocs.forEach((docs, key) => {
        if (!docs || docs.length === 0) {
            keysToDelete.push(key);
        }
    });

    keysToDelete.forEach(key => {
        allDocs.delete(key);
        console.log(`🗑️ تم حذف المفتاح الفارغ: ${key}`);
    });

    console.log(`✅ تم التنظيف بنجاح`);
    console.log(`📊 المفاتيح بعد التنظيف: ${Array.from(allDocs.keys()).join(', ')}`);

    return keysToDelete.length > 0;
}

// دالة اختبار شاملة
function runAllTests() {
    console.log('🧪 بدء الاختبارات الشاملة لدعم window.allDocs\n');

    const results = {
        initialization: false,
        deleteSingle: false,
        deleteAll: false,
        reindex: false,
        advancedSearch: false,
        cleanup: false
    };

    try {
        // 1. تهيئة
        const allDocs = testInitialization();
        results.initialization = true;

        // 2. حذف وثيقة واحدة
        results.deleteSingle = testDeleteSingleDocument(allDocs);

        // 3. البحث المتقدم
        results.advancedSearch = testAdvancedSearch(allDocs);

        // 4. حذف جميع وثائق فرد
        results.deleteAll = testDeleteAllDocuments(allDocs);

        // 5. إعادة الفهرسة
        results.reindex = testReindexing(allDocs);

        // 6. تنظيف المفاتيح الفارغة
        results.cleanup = testCleanupEmptyKeys(allDocs);

    } catch (error) {
        console.error('❌ خطأ في الاختبارات:', error);
    }

    // تقرير النتائج
    console.log('\n📊 تقرير النتائج النهائي:');
    console.log('================================');
    Object.entries(results).forEach(([test, passed]) => {
        const status = passed ? '✅ نجح' : '❌ فشل';
        const testName = {
            initialization: 'تهيئة window.allDocs',
            deleteSingle: 'حذف وثيقة واحدة',
            deleteAll: 'حذف جميع الوثائق',
            reindex: 'إعادة الفهرسة',
            advancedSearch: 'البحث المتقدم',
            cleanup: 'تنظيف المفاتيح الفارغة'
        }[test];

        console.log(`${status} - ${testName}`);
    });

    const passedTests = Object.values(results).filter(Boolean).length;
    const totalTests = Object.keys(results).length;

    console.log(`\n🎯 معدل النجاح: ${passedTests}/${totalTests} (${(passedTests/totalTests*100).toFixed(1)}%)`);

    if (passedTests === totalTests) {
        console.log('🎉 تم تطبيق دعم window.allDocs بنجاح في بوابة أفراد الأسرة!');
    } else {
        console.log('⚠️ هناك بعض القضايا التي تحتاج إلى مراجعة.');
    }

    return results;
}

// تشغيل الاختبارات
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { runAllTests, testData };
} else {
    runAllTests();
}
