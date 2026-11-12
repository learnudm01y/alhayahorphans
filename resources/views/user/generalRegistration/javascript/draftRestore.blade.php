@php
    // صفحة الرفض تُسترجع بوميض old() من الجلسة، فلا تُزعج المسودة المحلية هناك.
    $hasOldFlash = session()->has('_old_input')
        || (string) old('data_id_number', '') !== ''
        || (string) old('data_first_name', '') !== '';
@endphp

<script>
    (function () {
        const OLD_FLASH_PRESENT = @json($hasOldFlash);
        const BANNER_ID = 'registrationDraftBanner';

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);

            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
                + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function waitForZone(personKey, timeoutMs) {
            return new Promise(function (resolve) {
                const startedAt = Date.now();
                (function poll() {
                    if (document.querySelector('[data-upload-zone="' + personKey + '"]')) {
                        resolve(true);

                        return;
                    }
                    if (Date.now() - startedAt > timeoutMs) {
                        resolve(false);

                        return;
                    }
                    setTimeout(poll, 120);
                })();
            });
        }

        function currentSectionId() {
            const select = document.querySelector('select[name="data_section_id"]');

            return select ? String(select.value || '') : '';
        }

        function applyFields(fields, basicOnly) {
            const allowed = Object.keys(window.RegDraftAutosave.buildState().fields);
            allowed.forEach(function (name) {
                if (name === 'data_section_id' || (basicOnly && !name.startsWith('data_') && !/^mother_(id|first_name|second_name|third_name|last_name|is_alive)$/.test(name))) {
                    return;
                }
                window.RegRows.fill(document, '[name="' + name + '"]', (fields || {})[name]);
            });
        }

        function taskExists(personKey, doc) {
            if (!window.allDocs || !(window.allDocs instanceof Map)) {
                return false;
            }
            const tasks = window.allDocs.get(personKey) || [];

            return tasks.some(function (task) {
                const file = task.processedFile || task.originalFile;

                return task.docType === doc.docType
                    && String(task.personId || '') === String(doc.personId || '')
                    && (!file || (file.name === doc.name && file.size === doc.size));
            });
        }

        function buildTask(doc, file) {
            return {
                id: doc.taskId,
                personKey: doc.personKey,
                originalFile: file,
                processedFile: file,
                docType: doc.docType,
                docTypeName: doc.docTypeName || doc.docType,
                fileType: 'image',
                personId: String(doc.personId || ''),
                fileIdNumber: window.RegDraft.pageKey(),
                status: 'completed',
                errorMessage: null,
                timestamp: Date.now(),
                timer: null,
                createdAt: Date.now(),
                updatedAt: Date.now(),
                isProcessed: true,
                uploadStatus: 'uploading',
                uploadProgress: 0,
                // لا تُنسخ الصورة إلى المخزن مرة أخرى: هي نسخة منه أصلاً
                draftMirrored: true
            };
        }

        function uploadRestoredTask(task, personKey) {
            if (typeof window.uploadFileInChunks !== 'function') {
                // بلا دالة الرفع يبقى الملف فعلياً في المهمة، فيُرسل مع الإرسال مباشرة
                task.uploadStatus = null;

                return;
            }
            const fileIdInput = document.querySelector('input[name="file_id_number"]');
            const currentFileId = fileIdInput && fileIdInput.value
                ? fileIdInput.value
                : window.RegDraft.pageKey();

            window.uploadFileInChunks(task.processedFile, currentFileId, function (progress) {
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
        }

        function addRestoredTask(task) {
            if (!window.allDocs) {
                window.allDocs = new Map();
            }
            if (!(window.allDocs instanceof Map)) {
                return false;
            }
            if (!window.allDocs.has(task.personKey)) {
                window.allDocs.set(task.personKey, []);
            }
            window.allDocs.get(task.personKey).push(task);

            return true;
        }

        async function restoreDocs(state, rowMapping) {
            let missing = 0;
            const docs = (state.docs || []).filter(function (doc) {
                return doc && doc.personKey && doc.taskId;
            });
            for (const source of docs) {
                if (source.personKey.indexOf('template') !== -1) {
                    continue;
                }
                const dynamic = /^(family_|additional_deceased_)/.test(source.personKey);
                const personKey = dynamic ? rowMapping[source.personKey] : source.personKey;
                if (!personKey) {
                    missing++;
                    continue;
                }
                const doc = Object.assign({}, source, {
                    personKey: personKey,
                    personId: source.personId === source.personKey ? personKey : source.personId
                });
                if (taskExists(doc.personKey, doc)) {
                    continue;
                }
                const zoneReady = await waitForZone(doc.personKey, 4000);
                if (!zoneReady) {
                    missing++;

                    continue;
                }
                const record = await window.RegDraft.getDoc(state.draftKey, doc.taskId);
                if (!record) {
                    missing++;

                    continue;
                }
                const task = buildTask(doc, record.file);
                if (!addRestoredTask(task)) {
                    missing++;

                    continue;
                }
                // تهيئة قائمة نوع الوثيقة مع الحدث حتى لا تُعاد معالجة الصورة في أداة القص
                const docSelect = document.getElementById('mainDocumentTypeSelect_' + docSelectSuffix(doc.personKey));
                if (docSelect && doc.docType) {
                    docSelect.value = doc.docType;
                }
                window.renderAttachmentTasksUI(doc.personKey);
                uploadRestoredTask(task, doc.personKey);
            }

            return missing;
        }

        function docSelectSuffix(personKey) {
            if (personKey === 'main') {
                return 'main';
            }
            if (personKey === 'deceased_father') {
                return 'father';
            }
            if (personKey === 'deceased_mother') {
                return 'mother';
            }
            // بطاقات الأفراد: منطقتها family_0 لكن معرف قائمتها mainDocumentTypeSelect_0
            if (personKey.indexOf('family_') === 0) {
                return personKey.slice('family_'.length);
            }
            if (personKey.indexOf('additional_deceased_') === 0) {
                return 'additional_' + personKey.slice('additional_deceased_'.length);
            }

            return personKey;
        }

        async function applyState(state, options) {
            const settings = options || {};
            if (!settings.basicOnly) window.RegDraft.useDraft(state.draftKey);
            applyFields(state.fields, settings.basicOnly);
            const rowMapping = {};
            if (!settings.basicOnly) {
                Object.assign(rowMapping, window.RegRows.applyFamilyMembers(state.familyMembers || []));
                Object.assign(rowMapping, window.RegRows.applyAdditionalDeceased(state.additionalDeceased || []));
                if (state.showDeceasedMother) {
                    window.RegRows.showDeceasedMother();
                }
            }
            const missing = settings.skipDocs ? 0 : await restoreDocs(state, rowMapping);
            if (state.storageWarning === 'quota') {
                notify('لا توجد مساحة كافية في هذا المتصفح لحفظ كل المرفقات: '
                    + 'البيانات محفوظة، وقد تحتاج إلى إعادة إرفاق بعض الصور قبل الإرسال.');
            } else if (missing > 0) {
                notify('لم نتمكن من استرجاع ' + missing + ' مرفقاً من هذا الجهاز. '
                    + 'بقية البيانات محفوظة، وأعِد إرفاق ما نقص ثم أرسل الطلب.');
            }

            return missing;
        }

        function confirmSectionSwitch() {
            const message = 'قسم هذه المسودة غير متاح في نموذج التسجيل الحالي. يمكنك نقل البيانات الأساسية فقط إلى نموذج جديد، مع إبقاء المسودة الأصلية ومرفقاتها محفوظة.';
            if (typeof Swal === 'undefined' || !Swal.fire) {
                return Promise.resolve(window.confirm(message) ? 'current' : 'cancel');
            }
            return Swal.fire({
                icon: 'question',
                title: 'القسم مختلف',
                text: message,
                showCancelButton: true,
                confirmButtonText: 'نقل البيانات الأساسية',
                cancelButtonText: 'إلغاء'
            }).then(function (result) {
                return result && result.isConfirmed ? 'current' : 'cancel';
            });
        }

        function notify(text) {
            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    icon: 'info',
                    title: 'استرجاع المسودة',
                    text: text,
                    confirmButtonText: 'حسنًا'
                });

                return;
            }
            window.alert(text);
        }

        function removeBanner() {
            const banner = document.getElementById(BANNER_ID);
            if (banner) {
                banner.remove();
            }
        }

        function buildBanner(state) {
            window.RegDraftRestore.awaitingChoice = true;
            const summary = [
                (state.familyMembers || []).length + ' فرد',
                (state.docs || []).length + ' مرفق'
            ];

            const banner = document.createElement('div');
            banner.id = BANNER_ID;
            banner.setAttribute('role', 'status');
            banner.className = 'alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0';
            banner.style.cssText = 'position:sticky;top:0;z-index:1041;';
            banner.innerHTML = '<div class="fw-bold text-end">'
                + 'تم العثور على مسودة محفوظة في هذا الجهاز'
                + '<div class="fw-normal small mt-1">'
                + 'آخر حفظ: ' + formatTime(state.savedAt) + ' — ' + summary.join(' — ')
                + '</div></div>';

            const actions = document.createElement('div');
            actions.className = 'd-flex flex-wrap gap-2';

            const continueButton = document.createElement('button');
            continueButton.type = 'button';
            continueButton.className = 'btn btn-success btn-sm';
            continueButton.id = 'draftContinueBtn';
            continueButton.textContent = 'إكمال المسودة';

            const restartButton = document.createElement('button');
            restartButton.type = 'button';
            restartButton.className = 'btn btn-outline-danger btn-sm';
            restartButton.id = 'draftRestartBtn';
            restartButton.textContent = 'بدء نموذج جديد';

            actions.appendChild(continueButton);
            actions.appendChild(restartButton);

            if (window.RegDraft.list().length > 1) {
                const listButton = document.createElement('button');
                listButton.type = 'button';
                listButton.className = 'btn btn-outline-secondary btn-sm';
                listButton.id = 'draftListBtn';
                listButton.textContent = 'مسوداتي';
                actions.appendChild(listButton);
            }

            banner.appendChild(actions);

            const form = document.getElementById('main_form');
            if (form && form.parentNode) {
                form.parentNode.insertBefore(banner, form);
            }

            continueButton.addEventListener('click', function () {
                actions.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
                const sectionDiffers = state.sectionId && currentSectionId()
                    && state.sectionId !== currentSectionId();
                const choice = sectionDiffers ? confirmSectionSwitch() : Promise.resolve('draft');
                choice.then(async function (answer) {
                    if (answer === 'cancel') return false;
                    if (answer === 'current') {
                        const bytes = crypto.getRandomValues(new Uint8Array(6));
                        window.RegDraft.useDraft('TEMP-' + Array.from(bytes, function (byte) { return (byte % 36).toString(36); }).join('').toUpperCase());
                    }
                    await applyState(state, { basicOnly: answer === 'current', skipDocs: answer === 'current' });
                    return true;
                }).then(function (restored) {
                    if (!restored) return;
                    window.RegDraftRestore.awaitingChoice = false;
                    removeBanner();
                }).catch(function () {
                    notify('تعذر إكمال استرجاع المسودة. بياناتها ما زالت محفوظة؛ يرجى إعادة المحاولة.');
                }).finally(function () {
                    actions.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
                });
            });

            restartButton.addEventListener('click', function () {
                window.RegDraft.forget(state.draftKey).then(function () {
                    window.location.reload();
                });
            });

            if (actions.querySelector('#draftListBtn')) {
                actions.querySelector('#draftListBtn').addEventListener('click', openDraftPicker);
            }
        }

        async function openDraftPicker() {
            const summaries = window.RegDraft.list();
            if (summaries.length === 0) {
                notify('لا توجد مسودات محفوظة في هذا المتصفح.');

                return;
            }
            if (typeof Swal === 'undefined' || !Swal.fire) {
                const state = window.RegDraft.load(summaries[0].draftKey);
                if (state) {
                    removeBanner();
                    buildBanner(state);
                }

                return;
            }
            const inputOptions = {};
            summaries.forEach(function (summary) {
                inputOptions[summary.draftKey] = formatTime(summary.savedAt)
                    + ' — ' + summary.counts.familyMembers + ' فرد، '
                    + summary.counts.docs + ' مرفق';
            });
            const result = await Swal.fire({
                icon: 'info',
                title: 'مسوداتي في هذا الجهاز',
                input: 'select',
                inputOptions: inputOptions,
                inputPlaceholder: 'اختر مسودة',
                showCancelButton: true,
                confirmButtonText: 'استرجاع',
                cancelButtonText: 'إلغاء',
                customClass: { htmlContainer: 'text-start' }
            });
            if (!result || !result.isConfirmed || !result.value) {
                return;
            }
            const state = window.RegDraft.load(result.value);
            if (!state) {
                notify('لم تعد هذه المسودة متوفرة في هذا المتصفح.');

                return;
            }
            removeBanner();
            buildBanner(state);
        }

        window.RegDraftRestore = {
            applyState: applyState,
            currentSectionId: currentSectionId,
            awaitingChoice: false
        };

        window.addEventListener('load', async function () {
            if (OLD_FLASH_PRESENT || !window.RegDraft.available()) {
                return;
            }
            await window.RegDraft.purgeExpired();
            const key = window.RegDraft.pageKey();
            if (!key) {
                return;
            }
            const latest = window.RegDraft.list()[0];
            const state = window.RegDraft.load(key) || (latest && window.RegDraft.load(latest.draftKey));
            if (!state) {
                return;
            }
            buildBanner(state);
        });
    })();
</script>
