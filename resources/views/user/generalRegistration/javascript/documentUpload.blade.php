@push('scriptsCodeUserRegistration')
    <script>
        // نظام موحد لجمع وتجهيز جميع المرفقات من جميع البوابات
        // يدعم: البيانات الأساسية، الأفراد المتوفين، أفراد الأسرة

        document.addEventListener('DOMContentLoaded', function() {
            // Map لحفظ كل الملفات المرفوعة: المفتاح = معرف فريد (مثلاً: main, deceased_father, family_0 ...)
            // والقيمة: مصفوفة من الملفات (يدعم أكثر من ملف لكل شخص)
            const allDocs = new Map();
            window.allDocs = allDocs; // اجعلها متاحة عالمياً

            // دالة تجهيز منطقة رفع ملفات واحدة
            function initUploadZone(zone) {
                const personKey = zone.getAttribute('data-upload-zone');
                const fileInput = zone.querySelector('input[type="file"]');
                const docTypeSelect = zone.querySelector('select');
                if (!fileInput || !docTypeSelect) return;
                // عند اختيار ملف
                fileInput.addEventListener('change', function(e) {
                    if (!docTypeSelect.value) {
                        Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يجب اختيار نوع الوثيقة أولاً قبل رفع الملف.' });
                        fileInput.value = '';
                        return;
                    }
                    let personId = '';
                    if (personKey === 'main') {
                        personId = document.getElementById('data_id_number')?.value || '';
                    } else if (personKey === 'deceased_father') {
                        personId = document.querySelector('input[name="father_id"]')?.value || '';
                    } else if (personKey === 'deceased_mother') {
                        personId = document.querySelector('input[name="mother_id"]')?.value || '';
                    } else if (personKey.startsWith('family_')) {
                        const form = zone.closest('.family-member-form');
                        personId = form ? form.querySelector('input[name$="[person_id]"]')?.value || '' : '';
                        console.log('🟢 رقم هوية فرد الأسرة المرسل:', personId, 'من الفورم:', form);
                        if (!personId) {
                            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى إدخال رقم هوية فرد الأسرة أولاً قبل رفع الملف.' });
                            fileInput.value = '';
                            return;
                        }
                    }
                    if (!personId) {
                        Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى إدخال رقم الهوية أولاً قبل رفع الملف.' });
                        fileInput.value = '';
                        return;
                    }
                    Array.from(fileInput.files).forEach(file => {
                        const doc = {
                            file: file,
                            name: file.name,
                            type: docTypeSelect.value,
                            personId: personId,
                            file_id_number: document.getElementById('document_id')?.value || '',
                        };
                        addDocument(personKey, doc);
                    });
                    if (personKey.startsWith('family_')) {
                        console.log('🟠 رفع ملف فرد أسرة:', { personKey, personId, zone, fileInput, docType: docTypeSelect.value, file: fileInput.files[0] });
                    }
                });
                // عند تغيير نوع الوثيقة
                docTypeSelect.addEventListener('change', function() {
                    if (!allDocs.has(personKey)) return;
                    allDocs.get(personKey).forEach(doc => { doc.type = docTypeSelect.value; });
                });
            }

            // تجهيز جميع المناطق الحالية عند تحميل الصفحة
            document.querySelectorAll('[data-upload-zone]').forEach(initUploadZone);

            // مراقبة إضافة أفراد جدد ديناميكياً
            const familyContainer = document.getElementById('familyMembersContainer');
            if (familyContainer) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && node.querySelector && node.querySelector('[data-upload-zone]')) {
                                node.querySelectorAll('[data-upload-zone]').forEach(initUploadZone);
                            }
                        });
                    });
                });
                observer.observe(familyContainer, { childList: true });
            }

            // دالة مساعدة لإضافة ملف لمجموعة شخص معين
            function addDocument(personKey, doc) {
                if (!allDocs.has(personKey)) allDocs.set(personKey, []);
                allDocs.get(personKey).push(doc);
            }

            // دالة مساعدة لحذف ملف من مجموعة شخص معين
            function removeDocument(personKey, fileName) {
                if (!allDocs.has(personKey)) return;
                allDocs.set(personKey, allDocs.get(personKey).filter(doc => doc.name !== fileName));
            }

            // عند حذف ملف (مثلاً زر حذف بجانب كل ملف)
            document.querySelectorAll('[data-remove-doc-btn]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const personKey = btn.getAttribute('data-person-key');
                    const fileName = btn.getAttribute('data-file-name');
                    removeDocument(personKey, fileName);
                    // احذف العنصر من الواجهة إذا لزم
                });
            });

            // عند إرسال النموذج الرئيسي
            const form = document.getElementById('main_form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Debug: طباعة كل المرفقات في الكونسول قبل الإرسال
                    console.log('🟢 جميع المرفقات المجمعة للإرسال:', Array.from(allDocs.entries()));
                    // تحقق أولاً: لا يوجد ملف بدون نوع وثيقة
                    let missingType = false;
                    allDocs.forEach((docsArr, personKey) => {
                        docsArr.forEach(doc => {
                            if (!doc.type) missingType = true;
                        });
                    });
                    if (missingType) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يجب اختيار نوع الوثيقة لكل ملف قبل الإرسال.'
                        });
                        return false;
                    }
                    // امسح جميع حقول الملفات الأصلية من الفورم (لمنع إرسال ملفات بدون نوع)
                    Array.from(form.querySelectorAll('input[type="file"]')).forEach(input => input.remove());
                    // قبل الإرسال: أضف جميع الملفات من allDocs إلى النموذج
                    let index = 0;
                    allDocs.forEach((docsArr, personKey) => {
                        docsArr.forEach(doc => {
                            // Debug: طباعة تفاصيل كل ملف
                            console.log(`🟡 إضافة ملف للإرسال:`, doc);
                            // ملف المرفق
                            const fileInput = document.createElement('input');
                            fileInput.type = 'file';
                            fileInput.name = `attachments[${index}][file]`;
                            fileInput.style.display = 'none';
                            const dt = new DataTransfer();
                            dt.items.add(doc.file);
                            fileInput.files = dt.files;
                            form.appendChild(fileInput);

                            // رقم الهوية
                            const personIdInput = document.createElement('input');
                            personIdInput.type = 'hidden';
                            personIdInput.name = `attachments[${index}][person_identity_number]`;
                            personIdInput.value = doc.personId;
                            form.appendChild(personIdInput);

                            // اسم الملف
                            const fileNameInput = document.createElement('input');
                            fileNameInput.type = 'hidden';
                            fileNameInput.name = `attachments[${index}][stored_file_name]`;
                            fileNameInput.value = doc.name;
                            form.appendChild(fileNameInput);

                            // نوع الوثيقة
                            const fileTypeInput = document.createElement('input');
                            fileTypeInput.type = 'hidden';
                            fileTypeInput.name = `attachments[${index}][file_type]`;
                            fileTypeInput.value = doc.type;
                            form.appendChild(fileTypeInput);

                            // رقم الملف العام
                            const fileIdInput = document.createElement('input');
                            fileIdInput.type = 'hidden';
                            fileIdInput.name = `attachments[${index}][file_id_number]`;
                            fileIdInput.value = doc.file_id_number;
                            form.appendChild(fileIdInput);

                            index++;
                        });
                    });
                });
            }

            // تحديث رقم الهوية في جميع ملفات فرد الأسرة عند تغييره
            document.querySelectorAll('.family-member-form input[name$="[person_id]"]').forEach(function(input) {
                input.addEventListener('input', function(e) {
                    const form = input.closest('.family-member-form');
                    // استخراج فهرس الفرد من اسم الحقل (مثلاً: family_members[2][person_id])
                    const match = input.name.match(/family_members\[(\d+)\]/);
                    if (!match) return;
                    const index = match[1];
                    const personKey = `family_${index}`;
                    if (window.allDocs && window.allDocs.has(personKey)) {
                        window.allDocs.get(personKey).forEach(doc => {
                            doc.personId = input.value;
                        });
                        // Debug: طباعة تحديث رقم الهوية في جميع ملفات هذا الفرد
                        console.log('🟢 تم تحديث رقم هوية فرد الأسرة في جميع مرفقاته:', personKey, input.value, window.allDocs.get(personKey));
                    }
                });
            });
        });
    </script>
@endpush
