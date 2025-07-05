// تجربة منطق التحقق المحسن من الوثائق
// Test the enhanced document validation logic

class ValidationTestEnv {
    constructor() {
        this.setupMockDOM();
        this.setupGlobalVariables();
    }

    setupMockDOM() {
        // إعداد DOM مقلد
        global.document = {
            querySelector: (selector) => this.mockElementQueries[selector] || null,
            querySelectorAll: (selector) => this.mockElementQueries[selector] || [],
            createElement: (tag) => ({ tagName: tag, innerHTML: '', style: {} })
        };

        this.mockElementQueries = {};
    }

    setupGlobalVariables() {
        global.window = {
            allDocs: new Map()
        };
    }

    // إعداد عضو عائلة للاختبار
    setupFamilyMember(personKey, hasProcessedDocs = false, hasPreviewCard = false, hasFileInput = false, hasDocType = false) {
        const form = {
            querySelector: (selector) => {
                if (selector === '[data-upload-zone]') {
                    return { getAttribute: () => personKey };
                }
                if (selector === '.mainDocumentPreview') {
                    return {
                        style: { display: hasPreviewCard ? 'block' : 'none' },
                        innerHTML: hasPreviewCard ? '<div class="attachment-card">Document</div>' : '',
                        trim: function() { return this.innerHTML; }
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

        // إعداد window.allDocs إذا كانت هناك وثائق معالجة
        if (hasProcessedDocs) {
            window.allDocs.set(personKey, [
                {
                    id: 'doc1',
                    type: 'passport',
                    filename: 'passport.pdf',
                    url: '/uploads/passport.pdf'
                }
            ]);
        }

        return form;
    }

    // تشغيل منطق التحقق
    runValidationLogic(form) {
        let invalidField = null;
        let invalidLabel = null;

        // منطق التحقق المحسن (منسوخ من الكود الأساسي)
        if (!invalidField) {
            // التحقق من وجود وثائق معالجة للعضو
            const uploadZone = form.querySelector('[data-upload-zone]');
            const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : null;

            let hasProcessedDocument = false;

            // التحقق من window.allDocs أولاً
            if (window.allDocs && window.allDocs instanceof Map && personKey) {
                const memberDocs = window.allDocs.get(personKey);
                hasProcessedDocument = memberDocs && memberDocs.length > 0;
            }

            // التحقق من منطقة المعاينة كبديل
            if (!hasProcessedDocument) {
                const preview = form.querySelector('.mainDocumentPreview');
                const hasVisibleDocument = preview && preview.style.display !== 'none' &&
                                         preview.innerHTML.trim() !== '' &&
                                         preview.innerHTML.includes('attachment-card');
                hasProcessedDocument = hasVisibleDocument;
            }

            // إذا لم توجد وثائق معالجة، تحقق من حالة الإدخال الحالية
            if (!hasProcessedDocument) {
                const docType = form.querySelector('.mainDocumentTypeSelect');
                const fileInput = form.querySelector('.mainDocumentFileInput');
                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                if (docType && !docType.value && !hasFile) {
                    invalidField = docType;
                    invalidLabel = 'نوع الوثيقة أو رفع الملف';
                }
            }
        }

        return { invalidField, invalidLabel };
    }
}

// اختبارات التحقق
function runValidationTests() {
    const testEnv = new ValidationTestEnv();
    let passedTests = 0;
    let totalTests = 0;

    function test(description, testFn) {
        totalTests++;
        try {
            testFn();
            console.log(`✅ ${description}`);
            passedTests++;
        } catch (error) {
            console.log(`❌ ${description}: ${error.message}`);
        }
    }

    function assert(condition, message) {
        if (!condition) {
            throw new Error(message);
        }
    }

    console.log('🧪 بدء اختبارات منطق التحقق المحسن...\n');

    // اختبار 1: عضو لديه وثائق معالجة في window.allDocs
    test('عضو لديه وثائق معالجة في window.allDocs - يجب اجتياز التحقق', () => {
        const form = testEnv.setupFamilyMember('member1', true, false, false, false);
        const result = testEnv.runValidationLogic(form);
        assert(!result.invalidField, 'يجب عدم وجود حقل غير صالح');
    });

    // اختبار 2: عضو لديه وثيقة في منطقة المعاينة
    test('عضو لديه وثيقة في منطقة المعاينة - يجب اجتياز التحقق', () => {
        testEnv.setupGlobalVariables(); // إعادة تعيين
        const form = testEnv.setupFamilyMember('member2', false, true, false, false);
        const result = testEnv.runValidationLogic(form);
        assert(!result.invalidField, 'يجب عدم وجود حقل غير صالح');
    });

    // اختبار 3: عضو لديه ملف في الإدخال ونوع وثيقة
    test('عضو لديه ملف في الإدخال ونوع وثيقة - يجب اجتياز التحقق', () => {
        testEnv.setupGlobalVariables(); // إعادة تعيين
        const form = testEnv.setupFamilyMember('member3', false, false, true, true);
        const result = testEnv.runValidationLogic(form);
        assert(!result.invalidField, 'يجب عدم وجود حقل غير صالح');
    });

    // اختبار 4: عضو لديه ملف فقط بدون نوع وثيقة
    test('عضو لديه ملف فقط بدون نوع وثيقة - يجب اجتياز التحقق', () => {
        testEnv.setupGlobalVariables(); // إعادة تعيين
        const form = testEnv.setupFamilyMember('member4', false, false, true, false);
        const result = testEnv.runValidationLogic(form);
        assert(!result.invalidField, 'يجب عدم وجود حقل غير صالح عند وجود ملف');
    });

    // اختبار 5: عضو بدون أي وثائق أو ملفات
    test('عضو بدون أي وثائق أو ملفات - يجب فشل التحقق', () => {
        testEnv.setupGlobalVariables(); // إعادة تعيين
        const form = testEnv.setupFamilyMember('member5', false, false, false, false);
        const result = testEnv.runValidationLogic(form);
        assert(result.invalidField, 'يجب وجود حقل غير صالح');
        assert(result.invalidLabel === 'نوع الوثيقة أو رفع الملف', 'رسالة الخطأ يجب أن تكون صحيحة');
    });

    // اختبار 6: عضو لديه نوع وثيقة فقط بدون ملف
    test('عضو لديه نوع وثيقة فقط بدون ملف - يجب اجتياز التحقق', () => {
        testEnv.setupGlobalVariables(); // إعادة تعيين
        const form = testEnv.setupFamilyMember('member6', false, false, false, true);
        const result = testEnv.runValidationLogic(form);
        assert(!result.invalidField, 'يجب عدم وجود حقل غير صالح عند وجود نوع وثيقة');
    });

    // اختبار 7: عضو لديه وثائق في allDocs ومعاينة (حالة مضاعفة)
    test('عضو لديه وثائق في allDocs ومعاينة - يجب اجتياز التحقق', () => {
        const form = testEnv.setupFamilyMember('member7', true, true, false, false);
        const result = testEnv.runValidationLogic(form);
        assert(!result.invalidField, 'يجب عدم وجود حقل غير صالح');
    });

    // اختبار 8: معاينة فارغة (بدون attachment-card)
    test('معاينة فارغة (بدون attachment-card) - يجب فشل التحقق', () => {
        testEnv.setupGlobalVariables(); // إعادة تعيين
        const form = testEnv.setupFamilyMember('member8', false, false, false, false);
        // تخصيص المعاينة لتكون فارغة
        const originalQuery = form.querySelector;
        form.querySelector = function(selector) {
            if (selector === '.mainDocumentPreview') {
                return {
                    style: { display: 'block' },
                    innerHTML: '',
                    trim: function() { return this.innerHTML; }
                };
            }
            return originalQuery.call(this, selector);
        };

        const result = testEnv.runValidationLogic(form);
        assert(result.invalidField, 'يجب وجود حقل غير صالح للمعاينة الفارغة');
    });

    console.log(`\n📊 نتائج الاختبار: ${passedTests}/${totalTests} نجحت`);
    console.log(`✨ نسبة النجاح: ${((passedTests / totalTests) * 100).toFixed(1)}%`);

    if (passedTests === totalTests) {
        console.log('🎉 جميع الاختبارات نجحت! منطق التحقق يعمل بشكل صحيح.');
    } else {
        console.log('⚠️  بعض الاختبارات فشلت. يرجى مراجعة المنطق.');
    }
}

// تشغيل الاختبارات
runValidationTests();
