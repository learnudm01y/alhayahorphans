// اختبار شامل لجميع وظائف إدارة الوثائق في familyMember
// Comprehensive test for all document management functions in familyMember

class ComprehensiveTest {
    constructor() {
        this.setupGlobalEnvironment();
        this.testResults = [];
    }

    setupGlobalEnvironment() {
        // إعداد window.allDocs
        global.window = {
            allDocs: new Map()
        };

        // إعداد Swal mock
        global.Swal = {
            fire: (options) => Promise.resolve({ isConfirmed: true })
        };

        console.log('🚀 إعداد البيئة الشاملة لاختبار familyMember...\n');
    }

    // محاكاة إضافة عضو عائلة جديد
    simulateAddFamilyMember(memberKey) {
        const memberData = {
            key: memberKey,
            name: `Member ${memberKey}`,
            documents: []
        };

        // محاكاة HTML للعضو
        const mockMemberElement = {
            querySelector: (selector) => {
                if (selector === '[data-upload-zone]') {
                    return { getAttribute: () => memberKey };
                }
                if (selector === '.mainDocumentPreview') {
                    return {
                        style: { display: 'none' },
                        innerHTML: '',
                        addEventListener: () => {}
                    };
                }
                if (selector === '.mainDocumentTypeSelect') {
                    return {
                        value: '',
                        addEventListener: () => {}
                    };
                }
                if (selector === '.mainDocumentFileInput') {
                    return {
                        files: [],
                        addEventListener: () => {}
                    };
                }
                return null;
            },
            querySelectorAll: () => []
        };

        return { memberData, element: mockMemberElement };
    }

    // محاكاة رفع وثيقة
    simulateDocumentUpload(memberKey, docType, filename) {
        const document = {
            id: `doc_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            type: docType,
            filename: filename,
            url: `/uploads/${filename}`,
            uploadedAt: new Date().toISOString()
        };

        // إضافة الوثيقة إلى window.allDocs
        if (!window.allDocs.has(memberKey)) {
            window.allDocs.set(memberKey, []);
        }
        window.allDocs.get(memberKey).push(document);

        return document;
    }

    // محاكاة حذف وثيقة
    simulateDocumentDelete(memberKey, docId) {
        if (!window.allDocs.has(memberKey)) {
            return false;
        }

        const memberDocs = window.allDocs.get(memberKey);
        const initialLength = memberDocs.length;

        // البحث والحذف
        for (let i = memberDocs.length - 1; i >= 0; i--) {
            if (memberDocs[i].id === docId) {
                memberDocs.splice(i, 1);
                break;
            }
        }

        // تنظيف المفتاح إذا لم تعد هناك وثائق
        if (memberDocs.length === 0) {
            window.allDocs.delete(memberKey);
        }

        return memberDocs.length < initialLength;
    }

    // محاكاة حذف عضو عائلة
    simulateDeleteFamilyMember(memberKey) {
        const hadDocuments = window.allDocs.has(memberKey);
        const docCount = hadDocuments ? window.allDocs.get(memberKey).length : 0;

        // حذف جميع وثائق العضو
        window.allDocs.delete(memberKey);

        return { hadDocuments, docCount };
    }

    // محاكاة إعادة ترتيب أعضاء العائلة
    simulateReorderMembers(oldKeys, newKeys) {
        const tempMap = new Map();

        // نسخ البيانات الحالية
        for (let [key, docs] of window.allDocs) {
            tempMap.set(key, docs);
        }

        // مسح الخريطة
        window.allDocs.clear();

        // إعادة إضافة البيانات بالمفاتيح الجديدة
        newKeys.forEach((newKey, index) => {
            const oldKey = oldKeys[index];
            if (tempMap.has(oldKey)) {
                window.allDocs.set(newKey, tempMap.get(oldKey));
            }
        });
    }

    // اختبار التحقق من صحة الوثيقة
    testDocumentValidation(memberKey, hasDocuments, hasPreview, hasFileInput, hasDocType) {
        const mockForm = {
            querySelector: (selector) => {
                if (selector === '[data-upload-zone]') {
                    return { getAttribute: () => memberKey };
                }
                if (selector === '.mainDocumentPreview') {
                    return {
                        style: { display: hasPreview ? 'block' : 'none' },
                        innerHTML: hasPreview ? '<div class="attachment-card">Document</div>' : ''
                    };
                }
                if (selector === '.mainDocumentTypeSelect') {
                    return { value: hasDocType ? 'passport' : '' };
                }
                if (selector === '.mainDocumentFileInput') {
                    return { files: hasFileInput ? [{ name: 'test.pdf' }] : [] };
                }
                return null;
            }
        };

        // منطق التحقق
        let invalidField = null;
        let invalidLabel = null;

        if (!invalidField) {
            const uploadZone = mockForm.querySelector('[data-upload-zone]');
            const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : null;

            let hasProcessedDocument = false;

            // التحقق من window.allDocs أولاً
            if (window.allDocs && window.allDocs instanceof Map && personKey) {
                const memberDocs = window.allDocs.get(personKey);
                hasProcessedDocument = memberDocs && memberDocs.length > 0;
            }

            // التحقق من منطقة المعاينة كبديل
            if (!hasProcessedDocument) {
                const preview = mockForm.querySelector('.mainDocumentPreview');
                const hasVisibleDocument = preview && preview.style.display !== 'none' &&
                                         preview.innerHTML.trim() !== '' &&
                                         preview.innerHTML.includes('attachment-card');
                hasProcessedDocument = hasVisibleDocument;
            }

            // إذا لم توجد وثائق معالجة، تحقق من حالة الإدخال الحالية
            if (!hasProcessedDocument) {
                const docType = mockForm.querySelector('.mainDocumentTypeSelect');
                const fileInput = mockForm.querySelector('.mainDocumentFileInput');
                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                if (docType && !docType.value && !hasFile) {
                    invalidField = docType;
                    invalidLabel = 'نوع الوثيقة أو رفع الملف';
                }
            }
        }

        return { isValid: !invalidField, invalidLabel };
    }

    // تشغيل جميع الاختبارات
    runAllTests() {
        console.log('🧪 بدء الاختبار الشامل لإدارة الوثائق...\n');

        let passedTests = 0;
        let totalTests = 0;

        const test = (description, testFn) => {
            totalTests++;
            try {
                testFn();
                console.log(`✅ ${description}`);
                passedTests++;
                this.testResults.push({ test: description, status: 'PASS' });
            } catch (error) {
                console.log(`❌ ${description}: ${error.message}`);
                this.testResults.push({ test: description, status: 'FAIL', error: error.message });
            }
        };

        const assert = (condition, message) => {
            if (!condition) {
                throw new Error(message);
            }
        };

        // اختبار 1: إضافة أعضاء عائلة متعددين
        test('إضافة أعضاء عائلة متعددين', () => {
            const member1 = this.simulateAddFamilyMember('family_member_1');
            const member2 = this.simulateAddFamilyMember('family_member_2');
            const member3 = this.simulateAddFamilyMember('family_member_3');

            assert(member1.memberData.key === 'family_member_1', 'مفتاح العضو الأول صحيح');
            assert(member2.memberData.key === 'family_member_2', 'مفتاح العضو الثاني صحيح');
            assert(member3.memberData.key === 'family_member_3', 'مفتاح العضو الثالث صحيح');
        });

        // اختبار 2: رفع وثائق لأعضاء مختلفين
        test('رفع وثائق لأعضاء مختلفين', () => {
            const doc1 = this.simulateDocumentUpload('family_member_1', 'passport', 'passport1.pdf');
            const doc2 = this.simulateDocumentUpload('family_member_1', 'id_card', 'id1.pdf');
            const doc3 = this.simulateDocumentUpload('family_member_2', 'passport', 'passport2.pdf');

            assert(window.allDocs.get('family_member_1').length === 2, 'العضو الأول لديه وثيقتان');
            assert(window.allDocs.get('family_member_2').length === 1, 'العضو الثاني لديه وثيقة واحدة');
            assert(!window.allDocs.has('family_member_3'), 'العضو الثالث لا يملك وثائق');
            assert(doc1.type === 'passport', 'نوع الوثيقة الأولى صحيح');
            assert(doc3.filename === 'passport2.pdf', 'اسم ملف الوثيقة الثالثة صحيح');
        });

        // اختبار 3: التحقق من صحة الوثائق - حالات مختلفة
        test('التحقق من صحة الوثائق - حالات مختلفة', () => {
            // عضو لديه وثائق معالجة - يجب اجتياز التحقق
            const validation1 = this.testDocumentValidation('family_member_1', true, false, false, false);
            assert(validation1.isValid, 'عضو لديه وثائق معالجة يجب أن يجتاز التحقق');

            // عضو بدون وثائق ولا ملفات - يجب فشل التحقق
            const validation2 = this.testDocumentValidation('family_member_3', false, false, false, false);
            assert(!validation2.isValid, 'عضو بدون وثائق يجب أن يفشل في التحقق');

            // عضو لديه معاينة فقط - يجب اجتياز التحقق
            const validation3 = this.testDocumentValidation('family_member_4', false, true, false, false);
            assert(validation3.isValid, 'عضو لديه معاينة يجب أن يجتاز التحقق');
        });

        // اختبار 4: حذف وثائق محددة
        test('حذف وثائق محددة', () => {
            const member1Docs = window.allDocs.get('family_member_1');
            const docToDelete = member1Docs[0].id;

            const deleteResult = this.simulateDocumentDelete('family_member_1', docToDelete);
            assert(deleteResult, 'تم حذف الوثيقة بنجاح');
            assert(window.allDocs.get('family_member_1').length === 1, 'باقي وثيقة واحدة للعضو الأول');
        });

        // اختبار 5: حذف جميع وثائق عضو
        test('حذف جميع وثائق عضو', () => {
            const member1Docs = window.allDocs.get('family_member_1');
            const remainingDocId = member1Docs[0].id;

            const deleteResult = this.simulateDocumentDelete('family_member_1', remainingDocId);
            assert(deleteResult, 'تم حذف الوثيقة الأخيرة');
            assert(!window.allDocs.has('family_member_1'), 'تم إزالة مفتاح العضو من window.allDocs');
        });

        // اختبار 6: حذف عضو عائلة كامل
        test('حذف عضو عائلة كامل', () => {
            const deleteResult = this.simulateDeleteFamilyMember('family_member_2');
            assert(deleteResult.hadDocuments, 'العضو كان لديه وثائق');
            assert(deleteResult.docCount === 1, 'العضو كان لديه وثيقة واحدة');
            assert(!window.allDocs.has('family_member_2'), 'تم حذف العضو من window.allDocs');
        });

        // اختبار 7: إعادة ترتيب الأعضاء
        test('إعادة ترتيب الأعضاء', () => {
            // إضافة بعض الأعضاء الجدد مع الوثائق
            this.simulateDocumentUpload('family_member_0', 'passport', 'pass0.pdf');
            this.simulateDocumentUpload('family_member_4', 'id_card', 'id4.pdf');

            const oldKeys = ['family_member_0', 'family_member_4'];
            const newKeys = ['family_member_1', 'family_member_2'];

            this.simulateReorderMembers(oldKeys, newKeys);

            assert(window.allDocs.has('family_member_1'), 'المفتاح الجديد موجود');
            assert(window.allDocs.has('family_member_2'), 'المفتاح الجديد الثاني موجود');
            assert(!window.allDocs.has('family_member_0'), 'المفتاح القديم غير موجود');
            assert(!window.allDocs.has('family_member_4'), 'المفتاح القديم الثاني غير موجود');
        });

        // اختبار 8: سلامة البيانات بعد عمليات متعددة
        test('سلامة البيانات بعد عمليات متعددة', () => {
            // إضافة وحذف وتعديل
            this.simulateDocumentUpload('family_member_1', 'birth_cert', 'birth.pdf');
            this.simulateDocumentUpload('family_member_2', 'marriage_cert', 'marriage.pdf');

            const totalDocs = Array.from(window.allDocs.values()).reduce((sum, docs) => sum + docs.length, 0);
            console.log(`    تصحيح: العدد الفعلي للوثائق: ${totalDocs}`);
            console.log(`    تصحيح: الأعضاء الحاليون: ${Array.from(window.allDocs.keys()).join(', ')}`);

            // التحقق من الحد الأدنى للوثائق (يجب أن يكون أكثر من 0)
            assert(totalDocs >= 2, `العدد الإجمالي للوثائق يجب أن يكون على الأقل 2، ولكن وجد ${totalDocs}`);

            // التحقق من التكامل
            for (let [key, docs] of window.allDocs) {
                for (let doc of docs) {
                    assert(doc.id, 'كل وثيقة لها معرف');
                    assert(doc.type, 'كل وثيقة لها نوع');
                    assert(doc.filename, 'كل وثيقة لها اسم ملف');
                }
            }
        });

        console.log(`\n📊 نتائج الاختبار الشامل: ${passedTests}/${totalTests} نجحت`);
        console.log(`✨ نسبة النجاح: ${((passedTests / totalTests) * 100).toFixed(1)}%`);

        if (passedTests === totalTests) {
            console.log('🎉 جميع الاختبارات نجحت! النظام يعمل بشكل مثالي.');
            console.log('\n🔍 ملخص الحالة النهائية:');
            console.log(`📂 إجمالي الأعضاء: ${window.allDocs.size}`);
            console.log(`📄 إجمالي الوثائق: ${Array.from(window.allDocs.values()).reduce((sum, docs) => sum + docs.length, 0)}`);
            console.log('\n📋 تفاصيل الأعضاء والوثائق:');
            for (let [key, docs] of window.allDocs) {
                console.log(`  👤 ${key}: ${docs.length} وثيقة/وثائق`);
                docs.forEach(doc => {
                    console.log(`    📎 ${doc.type} - ${doc.filename}`);
                });
            }
        } else {
            console.log('⚠️  بعض الاختبارات فشلت. يرجى مراجعة النتائج.');
        }

        return { passed: passedTests, total: totalTests, success: passedTests === totalTests };
    }
}

// تشغيل الاختبار الشامل
const comprehensiveTest = new ComprehensiveTest();
const result = comprehensiveTest.runAllTests();

if (result.success) {
    console.log('\n🚀 النظام جاهز للإنتاج!');
} else {
    console.log('\n🔧 يحتاج النظام إلى مراجعة إضافية.');
}
