@push('scriptsCodeUserRegistration')
    <script>
        // بوابة عرض المعلومات المدخلة قبل الحفظ النهائي
        document.addEventListener('DOMContentLoaded', function() {
            // زر "التالي" في بوابة المرفقات (أو أي زر انتقال)
            const formTabs = document.getElementById('formTabs');
            const reviewTabId = 'review-tab';
            const reviewPaneId = 'review';

            // إضافة تبويب جديد لعرض المعلومات (نفس تصميم الجوال)
            if (!document.getElementById(reviewTabId)) {
                const reviewTab = document.createElement('li');
                reviewTab.className = 'nav-item';
                reviewTab.innerHTML = `
                <button class="nav-link py-3" id="${reviewTabId}" data-bs-toggle="tab" data-bs-target="#${reviewPaneId}"
                    type="button" role="tab" aria-controls="${reviewPaneId}" aria-selected="false">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-eye tab-icon mb-2"></i>
                        <span class="fs-4 fw-bold tab-label">عرض المعلومات المدخلة</span>
                    </div>
                </button>
            `;
                formTabs.appendChild(reviewTab);
            }

            // إضافة محتوى البوابة إذا لم يكن موجوداً
            if (!document.getElementById('review')) {
                const tabContent = document.getElementById('formTabsContent');
                const reviewPane = document.createElement('div');
                reviewPane.className = 'tab-pane fade';
                reviewPane.id = 'review';
                reviewPane.setAttribute('role', 'tabpanel');
                reviewPane.setAttribute('aria-labelledby', 'review-tab');
                reviewPane.innerHTML = `
                <div class="container py-4">
                    <h3 class="mb-4 text-primary fw-bold" style="letter-spacing:1px;">
                        <i class="fas fa-eye me-2"></i>مراجعة جميع المعلومات المدخلة
                    </h3>
                    <div id="reviewContent"></div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-success btn-lg save-record-btn" id="finalSaveBtn">
                            <i class="fas fa-save me-2"></i>
                            حفظ السجل نهائياً
                        </button>
                    </div>
                </div>
            `;
                tabContent.appendChild(reviewPane);
            }

            // عند الضغط على زر الحفظ في بوابة المراجعة، أرسل النموذج
            document.getElementById('review').addEventListener('click', function(e) {
                // لا شيء هنا (زر الحفظ سيعمل بشكل طبيعي)
            });

            // نقل زر الحفظ من بوابة المرفقات إلى بوابة المراجعة
            // (تم حذف زر الحفظ من المرفقات في ملف create.blade.php)
            // زر الحفظ الجديد هو الزر داخل بوابة المراجعة فقط

            // عند الضغط على زر الحفظ النهائي، أرسل النموذج
            document.getElementById('finalSaveBtn').addEventListener('click', function(e) {
                e.preventDefault();
                // إرسال النموذج الرئيسي
                document.getElementById('main_form').requestSubmit();
            });

            // عند الانتقال إلى بوابة المراجعة، اعرض البيانات
            document.getElementById(reviewTabId).addEventListener('click', function() {
                renderReviewContent();
            });

            // دالة لجمع وعرض جميع البيانات المدخلة
            function renderReviewContent() {
                const reviewContent = document.getElementById('reviewContent');
                if (!reviewContent) return;
                // Debug: طباعة جميع المرفقات في الذاكرة
                console.log('window.allDocs عند عرض المراجعة:', window.allDocs);

                // اجمع البيانات من الحقول
                const getVal = name => document.querySelector(`[name="${name}"]`)?.value || '';
                const getSelText = name => {
                    const sel = document.querySelector(`[name="${name}"]`);
                    return sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : '';
                };

                // --- البيانات الأساسية ---
                let basicInfo = `
                <div class="card shadow-lg border-0 mb-4" style="border-radius:18px;">
                    <div class="card-header bg-primary text-white fw-bold fs-5 d-flex align-items-center" style="border-radius:18px 18px 0 0;">
                        <span style="font-size:1.25rem;">البيانات الأساسية</span>
                        <i class="fas fa-id-card-alt me-2 text-white"></i>
                    </div>
                    <div class="card-body bg-white">
                        <div class="row g-4">
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-layer-group me-1"></i>القسم:</span><br>${getSelText('data_section_id')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span><br>${getVal('data_id_number')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-user me-1"></i>الاسم الأول:</span><br>${getVal('data_first_name')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-user-friends me-1"></i>اسم الأب:</span><br>${getVal('data_father_name')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-users me-1"></i>اسم العائلة:</span><br>${getVal('data_family_name')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-calendar-alt me-1"></i>تاريخ الميلاد:</span><br>${getVal('data_birth_date')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-venus-mars me-1"></i>الجنس:</span><br>${getSelText('data_gender')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-phone me-1"></i>رقم الهاتف:</span><br>${getVal('data_phone_number')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-users me-1"></i>عدد أفراد الأسرة:</span><br>${getVal('data_number_of_individuals')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-heart me-1"></i>الحالة الاجتماعية:</span><br>${getSelText('data_marital_status')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-graduation-cap me-1"></i>المؤهل العلمي:</span><br>${getSelText('data_academic_qualification')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-exchange-alt me-1"></i>حالة النزوح:</span><br>${getSelText('data_displacement_status')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-map-marker-alt me-1"></i>العنوان الحالي:</span><br>${getVal('data_current_address')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-city me-1"></i>المدينة:</span><br>${getSelText('data_city')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-map me-1"></i>المحافظة:</span><br>${getSelText('data_province')}</div>
                            <div class="col-md-4"><span class="fw-bold text-primary"><i class="fas fa-heartbeat me-1"></i>الحالة الصحية:</span><br>${getSelText('data_health_status')}</div>
                        </div>
                    </div>
                </div>
            `;

                // --- أفراد الأسرة ---
                let familyHtml = '';
                // تجاهل النماذج المخفية أو القالب
                document.querySelectorAll('.family-member-form').forEach((form, idx) => {
                    if (form.classList.contains('d-none') || form.id === 'familyMemberTemplate') return;
                    const getInputByName = n => form.querySelector(`[name$="[${n}]"]`)?.value || '';
                    const getSel = n => {
                        const sel = form.querySelector(`[name$="[${n}]"]`);
                        return sel && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : '';
                    };
                    familyHtml += `
                    <div class="card shadow border-0 mb-3" style="border-radius:14px;">
                        <div class="card-header bg-info text-white fw-bold d-flex align-items-center" style="border-radius:14px 14px 0 0;">
                            <span style="font-size:1.1rem;">فرد الأسرة #${idx + 1}</span>
                            <i class="fas fa-user-friends me-2 text-white"></i>
                        </div>
                        <div class="card-body bg-white">
                            <div class="row g-4">
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الأول:</span><br>${getInputByName('first_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الثاني:</span><br>${getInputByName('second_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الثالث:</span><br>${getInputByName('third_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-users me-1"></i>اسم العائلة:</span><br>${getInputByName('last_name')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span><br>${getInputByName('person_id')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-calendar-alt me-1"></i>تاريخ الميلاد:</span><br>${getInputByName('person_birth_date')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-hourglass-half me-1"></i>العمر:</span><br>${getInputByName('person_age')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-venus-mars me-1"></i>الجنس:</span><br>${getSel('person_gender')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-heartbeat me-1"></i>الحالة الصحية:</span><br>${getSel('person_health_status')}</div>
                                <div class="col-md-3"><span class="fw-bold text-info"><i class="fas fa-hand-holding-heart me-1"></i>نوع الكفالة:</span><br>${getSel('person_type_of_guarantee')}</div>
                            </div>
                        </div>
                    </div>
                `;
                });

                // --- بيانات المتوفين ---
                let deceasedHtml = '';
                if (document.getElementById('deceased').style.display !== 'none') {
                    deceasedHtml = `
                    <div class="card shadow border-0 mb-4" style="border-radius:14px;">
                        <div class="card-header bg-danger text-white fw-bold d-flex align-items-center" style="border-radius:14px 14px 0 0;">
                            <span style="font-size:1.1rem;">بيانات المتوفين</span>
                            <i class="fas fa-user-times me-2 text-white"></i>
                        </div>
                        <div class="card-body bg-white">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 mb-2" style="background:#fff3f3;">
                                        <span class="fw-bold text-danger"><i class="fas fa-male me-1"></i>الأب المتوفى</span>
                                        <div class="mt-2">
                                            <span class="fw-bold"><i class="fas fa-user me-1"></i>الاسم:</span> ${getVal('father_first_name')} ${getVal('father_last_name')}<br>
                                            <span class="fw-bold"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span> ${getVal('father_id')}<br>
                                            <span class="fw-bold"><i class="fas fa-calendar-alt me-1"></i>تاريخ الوفاة:</span> ${getVal('father_death_date')}<br>
                                            <span class="fw-bold"><i class="fas fa-skull-crossbones me-1"></i>سبب الوفاة:</span> ${getSelText('father_death_reason')}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 mb-2" style="background:#fff3f3;">
                                        <span class="fw-bold text-danger"><i class="fas fa-female me-1"></i>الأم المتوفية</span>
                                        <div class="mt-2">
                                            <span class="fw-bold"><i class="fas fa-user me-1"></i>الاسم:</span> ${getVal('mother_first_name')} ${getVal('mother_last_name')}<br>
                                            <span class="fw-bold"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span> ${getVal('mother_id')}<br>
                                            <span class="fw-bold"><i class="fas fa-calendar-alt me-1"></i>تاريخ الوفاة:</span> ${getVal('mother_death_date')}<br>
                                            <span class="fw-bold"><i class="fas fa-skull-crossbones me-1"></i>سبب الوفاة:</span> ${getSelText('mother_death_reason')}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                }

                // --- المرفقات (من Map البرمجية) ---
let attachmentsHtml = '';
                if (window.allDocs && typeof window.allDocs.keys === 'function') {
                    // عرض جميع المرفقات لكل بوابة (main, deceased_father, deceased_mother, family_X, وأخرى)
                    const labelMap = {
                        'main': 'مرفقات صاحب الطلب',
                        'deceased_father': 'مرفقات الأب المتوفى',
                        'deceased_mother': 'مرفقات الأم المتوفاة'
                    };
                    Array.from(window.allDocs.keys()).forEach(personKey => {
                        if (!window.allDocs.has(personKey)) return;
                        const docsArr = window.allDocs.get(personKey);
                        if (!docsArr || !docsArr.length) return;

                        let personLabel = labelMap[personKey] || '';
                        if (!personLabel && personKey.startsWith('family_')) {
                            // استخراج رقم فرد الأسرة
                            const idx = parseInt(personKey.replace('family_', '')) + 1;
                            personLabel = `مرفقات فرد الأسرة #${idx}`;
                        }
                        if (!personLabel) {
                            personLabel = 'مرفقات أخرى';
                        }

                        attachmentsHtml += `<div class="mb-3"><div class="fw-bold text-primary mb-2" style="font-size:1.08rem;">${personLabel}</div>`;
                        attachmentsHtml += `<div class="d-flex flex-wrap gap-3">`;

                        docsArr.forEach(doc => {
                            // استخراج نوع الوثيقة
                            const docType = doc.docType || doc.typeText || doc.type || '';
                            // استخراج اسم الملف
                            const fileName = doc.name || doc.docName || (doc.originalFile && doc.originalFile.name) || (doc.processedFile && doc.processedFile.name) || '';
                            // استخراج الملف نفسه
                            const fileObj = doc.file || doc.originalFile || doc.processedFile || null;
                            // نوع الملف
                            const fileType = fileObj && fileObj.type ? fileObj.type : '';

                            attachmentsHtml += `<div class="card shadow-sm border-0" style="width:170px;min-height:180px;border-radius:14px;overflow:hidden;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;">`;
                            attachmentsHtml += `<div class="bg-light text-dark fw-bold py-2 px-2 w-100 text-center" style="font-size:0.97rem;border-bottom:1px solid #eee;">${docType}</div>`;
                            attachmentsHtml += `<div class="p-2 w-100 d-flex flex-column align-items-center justify-content-center" style="min-height:120px;">`;
                            if (fileObj && fileType.startsWith('image/')) {
                                const url = URL.createObjectURL(fileObj);
                                attachmentsHtml += `<img src="${url}" style="max-width:110px;max-height:110px;border-radius:10px;border:2px solid #0d6efd;background:#fff;box-shadow:0 2px 8px #0001;">`;
                                attachmentsHtml += `<div class="mt-2 text-truncate" style="max-width:120px;font-size:0.93rem;">${fileName}</div>`;
                            } else if (fileObj) {
                                attachmentsHtml += `<span class="text-secondary" style="font-size:0.98rem;"><i class="fas fa-file-alt me-1"></i>${fileName}</span>`;
                            } else {
                                attachmentsHtml += `<span class="text-danger" style="font-size:0.95rem;">لا يوجد ملف</span>`;
                            }
                            attachmentsHtml += `</div></div>`;
                        });

                        attachmentsHtml += `</div></div>`;
                    });
                }

                reviewContent.innerHTML = `
                <div class="row">
                    <div class="col-12">
                        ${basicInfo}
                        ${familyHtml}
                        ${deceasedHtml}
                        <div class="card shadow border-0 mb-4" style="border-radius:14px;">
                            <div class="card-header bg-primary text-white fw-bold d-flex align-items-center" style="border-radius:14px 14px 0 0;">
                                <span style="font-size:1.1rem;">المرفقات</span>
                                <i class="fas fa-paperclip me-2 text-white"></i>
                            </div>
                            <div class="card-body bg-white">${attachmentsHtml || '<span class="text-muted">لا يوجد مرفقات</span>'}</div>
                        </div>
                    </div>
                </div>
            `;
            }

            // عند الضغط على زر الحفظ في بوابة المراجعة، أرسل النموذج
            document.getElementById('review').addEventListener('submit', function(e) {
                // سيتم إرسال النموذج بشكل طبيعي (لا تمنع الإرسال هنا)
            });
        });
        // ربط زر الحفظ النهائي بالتحقق من أرقام الهوية
        document.addEventListener('DOMContentLoaded', function() {
            const finalSaveBtn = document.getElementById('finalSaveBtn');
            if (finalSaveBtn) {
                finalSaveBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    // استدعاء دالة التحقق الموحدة
                    const valid = await validateAllIds(e);
                    if (!valid) return; // لا ترسل النموذج إذا كان هناك خطأ
                    // إذا كان كل شيء صحيح أرسل النموذج
                    document.getElementById('main_form').requestSubmit();
                });
            }
        });
        
        // === ضع الكود الجديد هنا ===
        async function validateAllIds(e) {
            // تحقق من التكرار في النموذج
            const isUnique = checkDuplicateIdsInForm();
            if (!isUnique) {
                if (e) e.preventDefault();
                return false;
            }
            // تحقق من قاعدة البيانات
            const dbOk = await checkIdNumbersInDatabase();
            if (!dbOk) {
                if (e) e.preventDefault();
                return false;
            }
            return true;
        }

    </script>
@endpush
