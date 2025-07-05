@push('scriptsCodeUserRegistration')
    <script>
        // إرسال النموذج باستخدام Ajax
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('main_form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // تجهيز الحقول الديناميكية (المرفقات)
                    let index = 0;
                    
                    // التحقق من وجود window.allDocs وتحويلها إلى تنسيق مناسب للإرسال
                    if (window.allDocs && window.allDocs instanceof Map) {
                        const allAttachments = [];
                        window.allDocs.forEach((tasks, personKey) => {
                            tasks.forEach(task => {
                                if (task.status === 'completed' && task.processedFile) {
                                    allAttachments.push({
                                        file: task.processedFile,
                                        type: task.docType,
                                        personId: task.personId,
                                        name: task.originalFile.name
                                    });
                                }
                            });
                        });
                        
                        // معالجة كل مرفق وإضافته للنموذج
                        allAttachments.forEach((doc) => {
                            // التحقق من صحة الملف
                            if (!(doc.file instanceof File) && !(doc.file instanceof Blob)) {
                                console.error('[formSubmission] عنصر مرفق غير صالح (سيتم تجاهله):', doc, doc.file);
                                return; // تخطى هذا العنصر
                            }
                            
                            // إنشاء input للملف
                            const fileInput = document.createElement('input');
                            fileInput.type = 'file';
                            fileInput.name = `attachments[${index}][file]`;
                            fileInput.style.display = 'none';
                            const dt = new DataTransfer();
                            dt.items.add(doc.file);
                            fileInput.files = dt.files;
                            form.appendChild(fileInput);
                            
                            // سجل تشخيصي لكل مرفق صالح
                            console.log('[formSubmission] إضافة مرفق صالح:', {
                                name: doc.name,
                                type: doc.type,
                                file: doc.file,
                                personId: doc.personId
                            });

                            // رقم الهوية
                            const personIdInput = document.createElement('input');
                            personIdInput.type = 'hidden';
                            personIdInput.name = `attachments[${index}][person_identity_number]`;
                            personIdInput.value = doc.personId || '';
                            form.appendChild(personIdInput);

                            // اسم الملف
                            const fileNameInput = document.createElement('input');
                            fileNameInput.type = 'hidden';
                            fileNameInput.name = `attachments[${index}][stored_file_name]`;
                            fileNameInput.value = doc.name || doc.file.name || '';
                            form.appendChild(fileNameInput);

                            // نوع الوثيقة
                            const fileTypeInput = document.createElement('input');
                            fileTypeInput.type = 'hidden';
                            fileTypeInput.name = `attachments[${index}][file_type]`;
                            fileTypeInput.value = doc.type || '';
                            form.appendChild(fileTypeInput);

                            // رقم الملف العام
                            const fileIdInput = document.createElement('input');
                            fileIdInput.type = 'hidden';
                            fileIdInput.name = `attachments[${index}][file_id_number]`;
                            const documentIdElement = document.getElementById('document_id');
                            fileIdInput.value = documentIdElement ? documentIdElement.value : '';
                            form.appendChild(fileIdInput);

                            index++;
                        });
                    }

                    // تجهيز البيانات للإرسال
                    const formData = new FormData(form);

                    // تشخيص: سجل جميع المرفقات قبل الإرسال
                    if (window.allDocs && window.allDocs instanceof Map) {
                        const allCompletedAttachments = [];
                        window.allDocs.forEach((tasks) => {
                            tasks.forEach(task => {
                                if (task.status === 'completed' && task.processedFile) {
                                    allCompletedAttachments.push({
                                        name: task.originalFile ? task.originalFile.name : 'Unknown',
                                        type: task.docType || 'Unknown',
                                        file: task.processedFile,
                                        isFile: task.processedFile instanceof File,
                                        isBlob: task.processedFile instanceof Blob,
                                        personId: task.personId || 'Unknown'
                                    });
                                }
                            });
                        });
                        console.log('[formSubmission] جميع المرفقات المرسلة:', allCompletedAttachments);
                    }

                    // إزالة الحقول الديناميكية بعد التجهيز حتى لا تتكرر في الإرسال القادم
                    Array.from(form.querySelectorAll(
                        'input[type="file"][name^="attachments"], input[type="hidden"][name^="attachments"]'
                    )).forEach(el => el.remove());

                    // إرسال البيانات عبر Ajax
                    fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                            }
                        })
                        .then(async response => {
                            let data;
                            try {
                                data = await response.clone().json();
                            } catch {
                                data = await response.text();
                            }
                            return {
                                data,
                                status: response.status
                            };
                        })
                        .then(({
                            data,
                            status
                        }) => {
                            // تحقق من نوع الاستجابة
                            if (typeof data === 'object' && data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الحفظ',
                                    text: 'تم حفظ السجل بنجاح'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else if (status === 200 && typeof data === 'string' && data.indexOf('success') !== -1) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم الحفظ',
                                    text: 'تم حفظ السجل بنجاح'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                // عرض رسالة خطأ مفصلة
                                let errorMessage = 'حدث خطأ أثناء الحفظ';
                                if (data && data.error) {
                                    errorMessage = data.error;
                                } else if (data && data.message) {
                                    errorMessage = data.message;
                                } else if (typeof data === 'string' && data.length > 0) {
                                    errorMessage = data;
                                }
                                
                                Swal.fire({
                                    icon: 'error',
                                    title: 'خطأ',
                                    text: errorMessage,
                                    footer: `حالة الاستجابة: ${status}`
                                });
                            }
                        })
                        .catch(error => {
                            console.error('[formSubmission] خطأ في إرسال النموذج:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ في الاتصال',
                                text: error.message || 'حدث خطأ أثناء إرسال البيانات إلى الخادم'
                            });
                        });
                });
            }
        });
    </script>
@endpush
