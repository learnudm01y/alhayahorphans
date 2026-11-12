@php
    // الالتقاط التلقائي: لا يكتب شيئاً على السيرفر، ولا يخزّن أي كلمة مرور ولا أرقام الملفات ولا الحقول الخفية.
    // لا يبدأ إلا بعد تفاعل حقيقي من المستخدم (isTrusted)، حتى لا تستبدل صفحة فارغة مسودة سابقة.
@endphp
<script>
    (function () {
        const STATIC_FIELD_NAMES = [
            'data_section_id', 'data_id_number', 'data_first_name', 'data_father_name',
            'data_grand_father_name', 'data_family_name', 'data_relationship', 'data_birth_date',
            'data_gender', 'data_phone_number', 'data_alt_phone_number', 'data_marital_status',
            'data_displacement_status', 'data_address_before_displacement', 'data_current_address',
            'data_city', 'data_province', 'data_province_visible', 'data_health_status',
            'data_description_needs', 'mother_id', 'mother_first_name', 'mother_second_name',
            'mother_third_name', 'mother_last_name', 'mother_is_alive', 'father_id',
            'father_first_name', 'father_second_name', 'father_third_name', 'father_last_name',
            'father_death_date', 'father_death_reason', 'deceased_mother_id',
            'deceased_mother_first_name', 'deceased_mother_second_name', 'deceased_mother_third_name',
            'deceased_mother_last_name', 'mother_death_date', 'mother_death_reason'
        ];

        const MEMBER_KEYS = ['person_id', 'first_name', 'second_name', 'third_name', 'last_name',
            'person_birth_date', 'person_gender', 'person_health_status', 'person_note'];

        const DECEASED_KEYS = ['id_number', 'first_name', 'second_name', 'third_name', 'last_name',
            'relationship', 'death_date', 'death_reason'];

        // حماية مزدوجة: حتى لو أضيف حقل جديد لن تُخزّن كلمات المرور ولا أرقام الملفات
        const NEVER_STORED = ['_token', 'file_id_number', 'original_file_id_number', 're_file_id',
            'mother_from_civil_registry', 'user_password', 'user_password_confirmation'];

        let dirty = false;
        let saveTimer = null;
        let mirrorTimer = null;
        let lastWarning = '';
        const mirroredFiles = new Map();
        const failedMirrors = new Set();

        function readValue(field) {
            if (!field || field.type === 'file') {
                return '';
            }
            if (field.type === 'checkbox') {
                return field.checked ? '1' : '0';
            }

            return field.value === undefined ? '' : String(field.value);
        }

        function collectFields() {
            const fields = {};
            STATIC_FIELD_NAMES.forEach(function (name) {
                if (NEVER_STORED.indexOf(name) !== -1 || name.toLowerCase().indexOf('password') !== -1) {
                    return;
                }
                const field = document.querySelector('[name="' + name + '"]');
                if (field) {
                    fields[name] = readValue(field);
                }
            });

            return fields;
        }

        function memberForms() {
            if (typeof window.getActiveFamilyMemberForms === 'function') {
                return window.getActiveFamilyMemberForms();
            }

            return Array.prototype.slice.call(document.querySelectorAll(
                '#familyMembersContainer .family-member-form'
            )).filter(function (form) {
                return form.getAttribute('data-member-index') !== 'template';
            });
        }

        function collectMembers() {
            return memberForms().map(function (card) {
                const member = { rowIndex: card.getAttribute('data-member-index') };
                MEMBER_KEYS.forEach(function (key) {
                    const field = card.querySelector('[name$="[' + key + ']"]');
                    member[key] = readValue(field);
                });

                return member;
            });
        }

        function collectDeceased() {
            return Array.prototype.slice.call(
                document.querySelectorAll('.additional-deceased-form')
            ).map(function (row) {
                const person = { rowIndex: row.getAttribute('data-index') };
                DECEASED_KEYS.forEach(function (key) {
                    const field = row.querySelector('[name$="[' + key + ']"]');
                    person[key] = readValue(field);
                });

                return person;
            });
        }

        function collectDocs(familyMembers, additionalDeceased) {
            const docs = [];
            if (!window.allDocs || !(window.allDocs instanceof Map)) {
                return docs;
            }
            // تبقى مفاتيح الوثائق بفهرس البطاقة الأصلي؛ RegRows يعيد خريطة فهارس الاسترجاع.
            const rowKeys = new Set(familyMembers.map(function (member) {
                return 'family_' + member.rowIndex;
            }).concat(additionalDeceased.map(function (person) {
                return 'additional_deceased_' + person.rowIndex;
            })));
            window.allDocs.forEach(function (tasks, personKey) {
                // حذف المتوفي لا يمسح allDocs في المكوّن، فلا نحتفظ بمرفقات بطاقة محذوفة.
                if (/^(family_|additional_deceased_)/.test(personKey) && !rowKeys.has(personKey)) {
                    return;
                }
                (tasks || []).forEach(function (task) {
                    const file = task.processedFile || task.originalFile;
                    if (!file || typeof file.size !== 'number') {
                        return;
                    }
                    docs.push({
                        taskId: task.id,
                        personKey: personKey,
                        docType: task.docType || task.type || '',
                        docTypeName: task.docTypeName || task.docType || '',
                        personId: task.personId || '',
                        name: file.name || 'image.jpg',
                        type: file.type || 'image/jpeg',
                        size: file.size || 0
                    });
                });
            });

            return docs;
        }

        function motherSectionVisible() {
            const section = document.getElementById('motherInfoSection');

            return !!section && section.style.display !== 'none';
        }

        function buildState() {
            const draftKey = window.RegDraft.pageKey();
            const fields = collectFields();
            const familyMembers = collectMembers();
            const additionalDeceased = collectDeceased();

            return {
                draftKey: draftKey,
                sectionId: fields.data_section_id || '',
                fields: fields,
                familyMembers: familyMembers,
                additionalDeceased: additionalDeceased,
                showDeceasedMother: motherSectionVisible(),
                docs: collectDocs(familyMembers, additionalDeceased),
                storageWarning: lastWarning || (failedMirrors.size > 0 ? 'unavailable' : '')
            };
        }

        const STORAGE_BANNER_ID = 'registrationStorageBanner';

        // فشل حفظ الصور مستقل عن نجاح حفظ النص، وputDoc(false) لا يحدد سبب الفشل.
        function showStorageBanner() {
            const kind = lastWarning || (failedMirrors.size > 0 ? 'attachments' : '');
            let banner = document.getElementById(STORAGE_BANNER_ID);
            if (!kind) {
                if (banner) banner.remove();

                return;
            }
            if (!banner) {
                banner = document.createElement('div');
                banner.id = STORAGE_BANNER_ID;
                banner.setAttribute('role', 'alert');
                banner.className = 'alert alert-warning rounded-0 mb-0 text-center fw-bold';
                banner.style.cssText = 'position:sticky;top:0;z-index:1041;';
                const form = document.getElementById('main_form');
                if (form && form.parentNode) {
                    form.parentNode.insertBefore(banner, form);
                }
            }
            banner.textContent = kind === 'quota'
                ? 'امتلأت مساحة هذا المتصفح: لم تُحفظ آخر التغييرات في المسودة. أبقِ هذه الصفحة مفتوحة وأرسل الطلب الآن.'
                : kind === 'attachments'
                    ? 'تعذر حفظ بعض المرفقات في هذا المتصفح. أبقِ الصفحة مفتوحة حتى ينجح الحفظ، أو أرسل الطلب قبل إغلاقها.'
                    : 'لا يمكن حفظ المسودة في هذا المتصفح: أكمل الطلب دون إغلاق هذه الصفحة وأرسله قبل أي انقطاع.';
        }

        function saveNow(reason) {
            if (window.registrationSavedSuccessfully) return;
            if (window.RegDraftRestore && window.RegDraftRestore.awaitingChoice) return;
            if (!window.RegDraft.available()) {
                lastWarning = 'unavailable';
                showStorageBanner();

                return;
            }
            if (!dirty) {
                return;
            }
            const draftKey = window.RegDraft.pageKey();
            if (!draftKey) {
                return;
            }
            lastWarning = '';
            const result = window.RegDraft.save(buildState());
            if (!result.ok) {
                lastWarning = result.code === 'quota' ? 'quota' : 'unavailable';
            }
            showStorageBanner();
            window.RegDraft.requestPersistence();
        }

        function scheduleSave() {
            if (window.registrationSavedSuccessfully) return;
            dirty = true;
            if (saveTimer) {
                clearTimeout(saveTimer);
            }
            saveTimer = setTimeout(function () {
                saveTimer = null;
                saveNow('field');
            }, 1500);
        }

        // نسخة كل صورة في IndexedDB: تتم بعد أي تحديث للواجهة، والحذف يتتبع اختفاء المهمة
        function mirrorBlobs() {
            if (window.registrationSavedSuccessfully) return;
            if (window.RegDraftRestore && window.RegDraftRestore.awaitingChoice) return;
            const draftKey = window.RegDraft.pageKey();
            if (!draftKey || !window.allDocs || !(window.allDocs instanceof Map)) {
                return;
            }
            const alive = new Set(collectDocs(collectMembers(), collectDeceased()).map(function (doc) {
                return doc.taskId;
            }));
            const writes = [];
            const previousFailures = failedMirrors.size;
            window.allDocs.forEach(function (tasks) {
                (tasks || []).forEach(function (task) {
                    const file = task.processedFile || task.originalFile;
                    if (!alive.has(task.id) || !file || typeof Blob === 'undefined' || !(file instanceof Blob)) {
                        return;
                    }
                    if (task.draftMirrored && !mirroredFiles.has(task.id)) {
                        mirroredFiles.set(task.id, file);
                    }
                    if (mirroredFiles.get(task.id) === file) {
                        failedMirrors.delete(task.id);

                        return;
                    }
                    writes.push(window.RegDraft.putDoc(draftKey, task.id, file).catch(function () {
                        return false;
                    }).then(function (saved) {
                        if ((task.processedFile || task.originalFile) !== file) return;
                        task.draftMirrored = saved;
                        if (saved) {
                            mirroredFiles.set(task.id, file);
                            failedMirrors.delete(task.id);
                        } else {
                            failedMirrors.add(task.id);
                        }
                        showStorageBanner();
                    }));
                });
            });
            mirroredFiles.forEach(function (file, taskId) {
                if (!alive.has(taskId)) {
                    mirroredFiles.delete(taskId);
                    writes.push(window.RegDraft.dropDoc(draftKey, taskId));
                }
            });
            failedMirrors.forEach(function (taskId) {
                if (!alive.has(taskId)) failedMirrors.delete(taskId);
            });
            showStorageBanner();
            if (writes.length > 0 || previousFailures !== failedMirrors.size) {
                Promise.all(writes).then(scheduleSave, scheduleSave);
            }
        }

        function scheduleMirror() {
            if (window.registrationSavedSuccessfully) return;
            if (mirrorTimer) {
                clearTimeout(mirrorTimer);
            }
            mirrorTimer = setTimeout(function () {
                mirrorTimer = null;
                mirrorBlobs();
            }, 400);
        }

        // بعد انقطاع الإنترنت: إعادة جدولة ما فشل رفعه قبل أن يعود الاتصال
        function requeueFailedUploads() {
            if (!window.allDocs || !(window.allDocs instanceof Map)
                || typeof window.uploadFileInChunks !== 'function') {
                return 0;
            }
            let requeued = 0;
            window.allDocs.forEach(function (tasks, personKey) {
                (tasks || []).forEach(function (task) {
                    if (task.uploadStatus !== 'error' || !task.processedFile) {
                        return;
                    }
                    task.uploadStatus = 'uploading';
                    task.uploadProgress = 0;
                    task.errorMessage = null;
                    requeued++;
                    const fileIdInput = document.querySelector('input[name="file_id_number"]');
                    window.uploadFileInChunks(task.processedFile, fileIdInput && fileIdInput.value
                        ? fileIdInput.value : window.RegDraft.pageKey(), function (progress) {
                            task.uploadProgress = progress;
                            window.renderAttachmentTasksUI(personKey);
                        }).then(function (tempPath) {
                            task.uploadStatus = 'done';
                            task.tempPath = tempPath;
                            window.renderAttachmentTasksUI(personKey);
                        }).catch(function () {
                            task.uploadStatus = 'error';
                            window.renderAttachmentTasksUI(personKey);
                        });
                });
            });

            return requeued;
        }

        const OFFLINE_ID = 'registrationOfflineBanner';

        function renderOfflineBanner() {
            if (navigator.onLine) {
                const gone = document.getElementById(OFFLINE_ID);
                if (gone) {
                    gone.remove();
                }

                return;
            }
            if (document.getElementById(OFFLINE_ID)) {
                return;
            }
            const banner = document.createElement('div');
            banner.id = OFFLINE_ID;
            banner.setAttribute('role', 'status');
            banner.className = 'alert alert-warning rounded-0 mb-0 text-center fw-bold';
            banner.style.cssText = 'position:sticky;top:0;z-index:1040;';
            banner.textContent = 'لا يوجد اتصال بالإنترنت: بياناتك ومرفقاتك تُحفظ في هذا الجهاز، '
                + 'وسيكتمل الرفع تلقائياً عند عودة الاتصال.';
            const form = document.getElementById('main_form');
            if (form && form.parentNode) {
                form.parentNode.insertBefore(banner, form);
            }
        }

        window.RegDraftAutosave = {
            buildState: buildState,
            saveNow: function () {
                dirty = true;
                saveNow('manual');
            },
            scheduleSave: scheduleSave,
            scheduleMirror: scheduleMirror,
            requeueFailedUploads: requeueFailedUploads,
            isDirty: function () {
                return dirty;
            }
        };

        document.addEventListener('input', function (event) {
            if (!event.isTrusted) {
                return;
            }
            const name = event.target && event.target.name;
            if (!name || NEVER_STORED.indexOf(name) !== -1) {
                return;
            }
            scheduleSave();
        }, true);

        document.addEventListener('change', function (event) {
            if (!event.isTrusted) {
                return;
            }
            const name = event.target && event.target.name;
            if (!name || NEVER_STORED.indexOf(name) !== -1) {
                return;
            }
            scheduleSave();
        }, true);

        document.addEventListener('click', function (event) {
            // الضغط على أزرار الإضافة/الحذف يغيّر عدد البطاقات فيجب الحفظ
            const button = event.target && event.target.closest ? event.target.closest('button') : null;
            if (!event.isTrusted || !button) {
                return;
            }
            if (button.id === 'addFamilyMember' || button.id === 'addAnotherDeceasedBtn'
                || button.id === 'toggleMotherInfo' || button.classList.contains('remove-family-member')
                || button.classList.contains('remove-deceased-btn') || button.classList.contains('delete-member')
                || button.classList.contains('remove-additional-deceased-btn')) {
                setTimeout(scheduleMirror, 600);
                scheduleSave();
            }
        }, true);

        window.addEventListener('load', function () {
            renderOfflineBanner();
            if (typeof window.renderAttachmentTasksUI === 'function') {
                const originalRender = window.renderAttachmentTasksUI;
                window.renderAttachmentTasksUI = function () {
                    const result = originalRender.apply(this, arguments);
                    scheduleMirror();

                    return result;
                };
            }
            // الضمانة الأخيرة: المستندات قد تُضاف دون أي حدث حقيقي بعدها
            setInterval(scheduleMirror, 10000);
        });

        window.addEventListener('online', function () {
            renderOfflineBanner();
            const requeued = requeueFailedUploads();
            if (requeued > 0) {
                saveNow('online');
            }
        });

        window.addEventListener('offline', renderOfflineBanner);

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                if (saveTimer) {
                    clearTimeout(saveTimer);
                    saveTimer = null;
                }
                saveNow('hidden');
            }
        });

        window.addEventListener('pagehide', function () {
            if (saveTimer) {
                clearTimeout(saveTimer);
                saveTimer = null;
            }
            saveNow('pagehide');
        });

        document.addEventListener('submit', function (event) {
            if (event.target && event.target.id === 'main_form') {
                saveNow('before-submit');
            }
        }, true);
    })();
</script>
