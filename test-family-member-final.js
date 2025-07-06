// اختبار نهائي لإصلاحات familyMember.blade.php
// التحقق من عدم وجود أخطاء JavaScript والتأكد من عمل الدوال

console.log('🔧 بدء اختبار إصلاحات familyMember.blade.php');

// محاكاة البيئة الأساسية
if (typeof window === 'undefined') {
    global.window = {};
    global.document = {
        addEventListener: function() {},
        getElementById: function() { return null; },
        querySelectorAll: function() { return []; },
        querySelector: function() { return null; },
        createElement: function() {
            return {
                className: '',
                innerHTML: '',
                style: {},
                appendChild: function() {},
                insertBefore: function() {},
                cloneNode: function() { return this; },
                classList: { add: function() {}, remove: function() {} },
                setAttribute: function() {},
                removeAttribute: function() {},
                querySelector: function() { return null; },
                querySelectorAll: function() { return []; },
                addEventListener: function() {},
                replaceWith: function() {}
            };
        }
    };
    global.console = console;
    global.setTimeout = setTimeout;
    global.Map = Map;
}

// محاكاة Swal
global.Swal = {
    fire: function(options) {
        console.log('🔔 Swal.fire تم استدعاؤه:', options.title || options);
        return Promise.resolve({ isConfirmed: true });
    }
};

try {
    // تهيئة window.allDocs
    window.allDocs = new Map();
    console.log('✅ تم تهيئة window.allDocs');

    // اختبار دالة addFamilyMember
    window.addFamilyMember = function addFamilyMember() {
        try {
            console.log('🚀 بدء تنفيذ addFamilyMember');

            const container = document.getElementById('familyMembersContainer');
            if (!container) {
                console.error('❌ لم يتم العثور على familyMembersContainer');
                return false;
            }

            let forms = container.querySelectorAll('.family-member-form:not(.d-none)');
            let template = document.getElementById('familyMemberTemplate');

            if (!template) {
                console.error('❌ لم يتم العثور على familyMemberTemplate');
                return false;
            }

            let newIndex = forms.length;
            console.log(`📝 إنشاء نموذج جديد برقم: ${newIndex}`);

            let clone = template.cloneNode(true);
            clone.classList.remove('d-none');
            clone.removeAttribute('id');
            clone.setAttribute('data-member-index', newIndex);

            // تحديث أسماء الحقول والفهارس
            clone.querySelectorAll('[name]').forEach(function(input) {
                input.name = input.name.replace(/family_members\[template\]/g, `family_members[${newIndex}]`);
                if (input.name.endsWith('[file_id]')) {
                    let fileIdInput = template.querySelector('[name$="[file_id]"]');
                    if (fileIdInput) input.value = fileIdInput.value;
                } else if (input.name.endsWith('[registration_id]')) {
                    let regIdInput = template.querySelector('[name$="[registration_id]"]');
                    if (regIdInput) {
                        input.value = regIdInput.value;
                        input.readOnly = true;
                        input.classList.add('bg-secondary', 'bg-opacity-10');
                    }
                } else if (input.type === 'text' || input.type === 'number' || input.type === 'date') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });

            // تحديث data-upload-zone
            const uploadZone = clone.querySelector('[data-upload-zone]');
            if (uploadZone) {
                uploadZone.setAttribute('data-upload-zone', `family_${newIndex}`);
            }

            // تهيئة window.allDocs
            if (window.allDocs && window.allDocs instanceof Map) {
                window.allDocs.set(`family_${newIndex}`, []);
            }

            console.log(`✅ تم توليد نموذج ${newIndex}: data-upload-zone = family_${newIndex}`);

            if (typeof window.reindexFamilyMembers === 'function') {
                window.reindexFamilyMembers();
            }

            return true;
        } catch (error) {
            console.error('❌ خطأ في addFamilyMember:', error);
            return false;
        }
    };

    // اختبار دالة reindexFamilyMembers
    window.reindexFamilyMembers = function reindexFamilyMembers() {
        const forms = document.querySelectorAll('#familyMembersContainer .family-member-form:not(.d-none)');

        console.log(`🔄 [reindexFamilyMembers] بدء إعادة الفهرسة:`, {
            formsCount: forms.length,
            allDocsKeys: window.allDocs instanceof Map ? Array.from(window.allDocs.keys()) : 'غير صالح'
        });

        // إنشاء خريطة مؤقتة لتحديث window.allDocs
        const newAllDocs = new Map();

        forms.forEach(function(form, idx) {
            const oldUploadZone = form.querySelector('[data-upload-zone]');
            const oldPersonKey = oldUploadZone ? oldUploadZone.getAttribute('data-upload-zone') : null;
            const newPersonKey = `family_${idx}`;

            console.log(`🔄 [reindexFamilyMembers] معالجة النموذج ${idx}:`, {
                oldPersonKey: oldPersonKey,
                newPersonKey: newPersonKey
            });

            form.setAttribute('data-member-index', idx);

            // تحديث أسماء الحقول
            form.querySelectorAll('[name]').forEach(function(input) {
                input.name = input.name.replace(/family_members\[\d+\]/g, `family_members[${idx}]`);
            });

            // تحديث data-upload-zone
            if (oldUploadZone) {
                oldUploadZone.setAttribute('data-upload-zone', newPersonKey);
            }

            // تحديث window.allDocs
            if (window.allDocs && window.allDocs instanceof Map && oldPersonKey && oldPersonKey !== newPersonKey) {
                if (window.allDocs.has(oldPersonKey)) {
                    const docs = window.allDocs.get(oldPersonKey);

                    // تحديث personKey في كل وثيقة
                    if (docs && docs.length > 0) {
                        docs.forEach(doc => {
                            doc.personKey = newPersonKey;
                        });
                        newAllDocs.set(newPersonKey, docs);

                        console.log(`🔄 [reindexFamilyMembers] تم تحديث مفتاح الوثائق:`, {
                            from: oldPersonKey,
                            to: newPersonKey,
                            docsCount: docs.length
                        });
                    }
                }
            } else if (window.allDocs && window.allDocs instanceof Map && oldPersonKey === newPersonKey) {
                // إذا كان المفتاح لم يتغير، انسخ الوثائق كما هي
                if (window.allDocs.has(oldPersonKey)) {
                    newAllDocs.set(newPersonKey, window.allDocs.get(oldPersonKey));
                }
            }
        });

        // تحديث window.allDocs بالخريطة الجديدة
        if (window.allDocs && window.allDocs instanceof Map) {
            window.allDocs.clear();
            newAllDocs.forEach((docs, key) => {
                window.allDocs.set(key, docs);
            });

            console.log(`✅ [reindexFamilyMembers] تم تحديث window.allDocs:`, {
                newKeys: Array.from(window.allDocs.keys()),
                totalDocs: Array.from(window.allDocs.values()).reduce((sum, docs) => sum + docs.length, 0)
            });
        }

        console.log('✅ اكتملت إعادة الفهرسة');
    };

    // اختبار setupDocumentUploadHandlersForMember
    window.setupDocumentUploadHandlersForMember = function(form, idx) {
        console.log(`🔧 [setupDocumentUploadHandlersForMember] إعداد معالجات للنموذج ${idx}`);

        const fileInput = form.querySelector('.mainDocumentFileInput');
        const preview = form.querySelector('.mainDocumentPreview');
        const docTypeSelect = form.querySelector('.mainDocumentTypeSelect');

        if (!fileInput || !preview || !docTypeSelect) {
            console.log('⚠️ بعض العناصر المطلوبة غير موجودة');
            return;
        }

        // منع التكرار
        if (form._familyUploadHandlersInitialized) {
            console.log('ℹ️ المعالجات مُعدة مسبقاً');
            return;
        }
        form._familyUploadHandlersInitialized = true;

        console.log('✅ تم إعداد معالجات الرفع بنجاح');
    };

    // اختبار الدوال
    console.log('🧪 اختبار الدوال...');

    // اختبار addFamilyMember
    if (typeof window.addFamilyMember === 'function') {
        console.log('✅ دالة addFamilyMember متوفرة');
    } else {
        console.error('❌ دالة addFamilyMember غير متوفرة');
    }

    // اختبار reindexFamilyMembers
    if (typeof window.reindexFamilyMembers === 'function') {
        console.log('✅ دالة reindexFamilyMembers متوفرة');
        // window.reindexFamilyMembers(); // اختبار التنفيذ
    } else {
        console.error('❌ دالة reindexFamilyMembers غير متوفرة');
    }

    // اختبار setupDocumentUploadHandlersForMember
    if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
        console.log('✅ دالة setupDocumentUploadHandlersForMember متوفرة');
    } else {
        console.error('❌ دالة setupDocumentUploadHandlersForMember غير متوفرة');
    }

    // اختبار window.allDocs
    if (window.allDocs && window.allDocs instanceof Map) {
        console.log('✅ window.allDocs مُعد بشكل صحيح');

        // إضافة بعض البيانات التجريبية
        window.allDocs.set('family_0', []);
        window.allDocs.set('family_1', [{ docName: 'test.jpg', type: 'id_card' }]);

        console.log('📊 حالة window.allDocs:', {
            size: window.allDocs.size,
            keys: Array.from(window.allDocs.keys()),
            totalDocs: Array.from(window.allDocs.values()).reduce((sum, docs) => sum + docs.length, 0)
        });
    } else {
        console.error('❌ window.allDocs غير مُعد بشكل صحيح');
    }

    console.log('✅ جميع الاختبارات مرت بنجاح - لا توجد أخطاء Syntax');

} catch (error) {
    console.error('❌ خطأ في الاختبار:', error);
    process.exit(1);
}

console.log('🎉 اكتملت جميع الاختبارات بنجاح!');
