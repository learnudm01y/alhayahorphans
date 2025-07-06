// تطبيق تحليل Syntax للكود JavaScript من familyMember.blade.php
// Cache buster: 2025-01-06-v1.0.1

document.addEventListener('DOMContentLoaded', function() {
    // تهيئة window.allDocs مع دعم بوابة أفراد الأسرة
    setTimeout(function() {
        if (typeof window.allDocs !== 'object' || !window.allDocs) {
            window.allDocs = new Map();
            console.log(`🔧 [familyMember] تم إنشاء window.allDocs جديد`);
        }

        // التحقق من صحة structure
        if (!(window.allDocs instanceof Map)) {
            console.warn(`⚠️ [familyMember] window.allDocs ليس Map، تحويل...`);
            const tempMap = new Map();
            if (typeof window.allDocs === 'object') {
                Object.keys(window.allDocs).forEach(key => {
                    tempMap.set(key, window.allDocs[key]);
                });
            }
            window.allDocs = tempMap;
        }

        console.log(`✅ [familyMember] تم تهيئة window.allDocs:`, {
            type: typeof window.allDocs,
            isMap: window.allDocs instanceof Map,
            keysCount: window.allDocs instanceof Map ? window.allDocs.size : 'غير صالح',
            keys: window.allDocs instanceof Map ? Array.from(window.allDocs.keys()) : 'غير صالح'
        });
    }, 0);

    // دالة إضافة فرد جديد
    window.addFamilyMember = function addFamilyMember() {
        const container = document.getElementById('familyMembersContainer');
        let forms = container.querySelectorAll('.family-member-form:not(.d-none)');
        let template = document.getElementById('familyMemberTemplate');
        let newIndex = forms.length;
        let clone = template.cloneNode(true);
        clone.classList.remove('d-none');
        clone.removeAttribute('id');
        clone.setAttribute('data-member-index', newIndex);

        // تحديث أسماء الحقول والفهارس
        clone.querySelectorAll('[name]').forEach(function(input) {
            input.name = input.name.replace(/family_members\[\d+\]/g, `family_members[${newIndex}]`);
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

        // تحديث معرفات العناصر
        const docType = clone.querySelector('.mainDocumentTypeSelect');
        if (docType) docType.id = `mainDocumentTypeSelect_${newIndex}`;
        const fileInput = clone.querySelector('.mainDocumentFileInput');
        if (fileInput) fileInput.id = `mainDocumentFileInput_${newIndex}`;
        const preview = clone.querySelector('.mainDocumentPreview');
        if (preview) preview.id = `mainDocumentPreview_${newIndex}`;
        const names = clone.querySelector('.mainDocumentNames');
        if (names) names.id = `mainDocumentNames_${newIndex}`;

        // مسح المحتوى السابق
        clone.querySelectorAll('.mainDocumentPreview, .mainDocumentNames').forEach(div => div.innerHTML = '');

        // تهيئة window.allDocs
        if (window.allDocs && window.allDocs instanceof Map) {
            window.allDocs.set(`family_${newIndex}`, []);
        }

        // إضافة header وزر حذف
        let cardHeader = clone.querySelector('.card-header');
        if (!cardHeader) {
            cardHeader = document.createElement('div');
            cardHeader.className = 'card-header bg-gradient-primary text-dark py-3 d-flex justify-content-between align-items-center';
            cardHeader.innerHTML = `
                <h5 class="card-title mb-0 d-flex align-items-center">
                    <i class="fas fa-user fs-4 me-2"></i>
                    بيانات فرد الأسرة
                </h5>
                <button type="button" class="btn btn-danger btn-sm delete-member">
                    <i class="fas fa-times"></i>
                </button>
            `;
            clone.insertBefore(cardHeader, clone.firstChild);
        }

        cardHeader.querySelector('.delete-member').onclick = function(e) {
            e.preventDefault();

            // الحصول على personKey للفرد المراد حذفه
            const uploadZone = clone.querySelector('[data-upload-zone]');
            const personKey = uploadZone ? uploadZone.getAttribute('data-upload-zone') : `family_${newIndex}`;

            console.log(`🗑️ [delete-member] محاولة حذف فرد العائلة:`, {
                memberIndex: newIndex,
                personKey: personKey,
                allDocsKeys: window.allDocs instanceof Map ? Array.from(window.allDocs.keys()) : 'غير صالح'
            });

            // مجرد لأغراض الاختبار - إزالة Swal.fire لأغراض syntax check
            // Swal.fire({...}).then((result) => {
            if (true) { // لأغراض الاختبار
                console.log(`✅ [delete-member] تأكيد حذف فرد العائلة`);

                // حذف جميع وثائق هذا الفرد من window.allDocs
                if (window.allDocs && window.allDocs instanceof Map) {
                    if (window.allDocs.has(personKey)) {
                        const deletedDocs = window.allDocs.get(personKey);
                        window.allDocs.delete(personKey);
                        console.log(`🗑️ [delete-member] تم حذف جميع وثائق الفرد من allDocs:`, {
                            personKey: personKey,
                            deletedDocsCount: deletedDocs ? deletedDocs.length : 0,
                            remainingKeys: Array.from(window.allDocs.keys())
                        });
                    } else {
                        console.log(`ℹ️ [delete-member] لم يتم العثور على وثائق للفرد في allDocs:`, {
                            personKey: personKey,
                            availableKeys: Array.from(window.allDocs.keys())
                        });
                    }
                }

                // تأثير الحذف البصري
                clone.style.opacity = '0';
                clone.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    clone.remove();
                    window.reindexFamilyMembers();
                }, 300);
            }
            // });
        };

        container.appendChild(clone);
        console.log(`تم توليد نموذج ${newIndex}: data-upload-zone = family_${newIndex}`);
        window.reindexFamilyMembers();
    };

    // دالة إعادة الفهرسة مع تحديث window.allDocs
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

            // تحديث معرفات العناصر
            const docType = form.querySelector('.mainDocumentTypeSelect');
            if (docType) docType.id = `mainDocumentTypeSelect_${idx}`;
            const fileInput = form.querySelector('.mainDocumentFileInput');
            if (fileInput) fileInput.id = `mainDocumentFileInput_${idx}`;
            const preview = form.querySelector('.mainDocumentPreview');
            if (preview) preview.id = `mainDocumentPreview_${idx}`;
            const names = form.querySelector('.mainDocumentNames');
            if (names) names.id = `mainDocumentNames_${idx}`;

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

        // إعادة تفعيل معالجات رفع الملفات
        setTimeout(function() {
            if (typeof window.setupDocumentUploadHandlersForMember === 'function') {
                document.querySelectorAll('.family-member-form:not(.d-none)').forEach(function(form, idx) {
                    window.setupDocumentUploadHandlersForMember(form, idx);
                });
            }

            // تطبيق تحسينات DeviceImageSource بعد إعداد المعالجات المخصصة
            setTimeout(() => {
                if (window.DeviceImageSource && window.DeviceImageSource.enhance) {
                    console.log('🔧 [FamilyMember] إعادة تفعيل DeviceImageSource بعد إعادة الفهرسة');
                    window.DeviceImageSource.enhance();
                }
            }, 500);
        }, 150);
    };

    // دالة setupDocumentUploadHandlersForMember - نسخة مبسطة للاختبار
    window.setupDocumentUploadHandlersForMember = function(form, idx) {
        console.log(`🔧 [setupDocumentUploadHandlersForMember] إعداد معالجات للنموذج ${idx}`);
        // تم حذف التفاصيل الكاملة لأغراض syntax check فقط
    };

    console.log('✅ [familyMember] تم تحميل جميع المعالجات بنجاح');
});
