<script>
    document.addEventListener('DOMContentLoaded', function() {
        // العناصر الأساسية
        const formTabs = document.getElementById('formTabs');
        const formTabsContent = document.getElementById('formTabsContent');
        const reviewTabId = 'review-tab';
        const reviewPaneId = 'review';
        const mainForm = document.getElementById('main_form');

        // 1. إضافة تبويب "عرض المعلومات المدخلة" إذا لم يكن موجوداً
        if (!document.getElementById(reviewTabId)) {
            const reviewTabLi = document.createElement('li');
            reviewTabLi.className = 'nav-item';
            reviewTabLi.innerHTML = `
                <button class="nav-link py-3" id="${reviewTabId}" data-bs-toggle="tab" data-bs-target="#${reviewPaneId}"
                    type="button" role="tab" aria-controls="${reviewPaneId}" aria-selected="false" title="عرض المعلومات المدخلة">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-eye tab-icon mb-2"></i>
                        <span class="fs-4 fw-bold tab-label">عرض المعلومات المدخلة</span>
                    </div>
                </button>
            `;
            formTabs.appendChild(reviewTabLi);
        }

        // 2. إضافة محتوى التبويب (Pane) إذا لم يكن موجوداً
        if (!document.getElementById(reviewPaneId)) {
            const reviewPane = document.createElement('div');
            reviewPane.className = 'tab-pane fade';
            reviewPane.id = reviewPaneId;
            reviewPane.setAttribute('role', 'tabpanel');
            reviewPane.setAttribute('aria-labelledby', reviewTabId);
            reviewPane.innerHTML = `
                <div class="container py-4">
                    <h3 class="mb-4 text-primary fw-bold" style="letter-spacing:1px;">
                        <i class="fas fa-eye me-2"></i>مراجعة جميع المعلومات المدخلة
                    </h3>
                    <div id="reviewContent"></div>
                    <div class="d-grid gap-2 mt-4">

                    </div>
                </div>
            `;
            formTabsContent.appendChild(reviewPane);
        }

        // 3. عند الضغط على زر "حفظ نهائي" داخل تبويب المراجعة، نرسل النموذج
        // نستخدم requestSubmit() إن كان مدعوماً، وإلا fallback إلى submit()
        function submitMainForm() {
            if (typeof mainForm.requestSubmit === 'function') {
                mainForm.requestSubmit();
            } else {
                mainForm.submit();
            }
        }
        // تأكد من وجود العنصر قبل إضافة المستمع
        const finalSaveBtn = document.getElementById('finalSaveBtn');
        if (finalSaveBtn) {
            finalSaveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                submitMainForm();
            });
        }

        // 4. عند النقر على تبويب "عرض المعلومات المدخلة"، نفذ renderReviewContent
        const reviewTabBtn = document.getElementById(reviewTabId);
        if (reviewTabBtn) {
            reviewTabBtn.addEventListener('click', function() {
                renderReviewContent();
            });
        }

        // 5. دالة لجمع وعرض جميع البيانات في div#reviewContent
        function renderReviewContent() {
            const reviewContent = document.getElementById('reviewContent');
            if (!reviewContent) return;

            // دوال مساعدة لجلب القيم
            const getVal = name => {
                const el = mainForm.querySelector(`[name="${name}"]`);
                if (!el) return '';
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) return el.value.trim();
                    // إذا مجموعة راديو، نبحث المجموعة
                    const group = mainForm.querySelectorAll(`[name="${name}"]`);
                    for (const g of group) {
                        if (g.checked) return g.value.trim();
                    }
                    return '';
                }
                return el.value || '';
            };
            const getSelText = name => {
                const sel = mainForm.querySelector(`[name="${name}"]`);
                if (!sel) return '';
                if (sel.tagName.toLowerCase() !== 'select') return sel.value || '';
                const idx = sel.selectedIndex;
                if (idx >= 0) {
                    return sel.options[idx].text;
                }
                return '';
            };
            // دالة لإرجاع رقم الملف مع الأصفار السابقة
            function padFileIdNumber(fileId, length = 6) {
                fileId = String(fileId || '');
                return fileId.padStart(length, '0');
            }

            // --- البيانات الأساسية ---
            const basicInfo = `
                <div class="card shadow-lg border-0 mb-4" style="border-radius:18px;">
                    <div class="card-header bg-primary text-white fw-bold fs-5 d-flex align-items-center" style="border-radius:18px 18px 0 0;">
                        <span style="font-size:1.25rem;">البيانات الأساسية</span>
                        <i class="fas fa-id-card-alt me-2 text-white"></i>
                    </div>
                    <div class="card-body bg-white">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-layer-group me-1"></i>القسم:</span><br>${getSelText('data_section_id')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-id-badge me-1"></i>رقم الملف:</span><br>${padFileIdNumber(getVal('file_id_number'))}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span><br>${getVal('data_id_number')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-user me-1"></i>الاسم الأول:</span><br>${getVal('data_first_name')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-user-friends me-1"></i>اسم الأب:</span><br>${getVal('data_father_name')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-users me-1"></i>اسم العائلة:</span><br>${getVal('data_family_name')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-calendar-alt me-1"></i>تاريخ الميلاد:</span><br>${getVal('data_birth_date')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-venus-mars me-1"></i>الجنس:</span><br>${getSelText('data_gender')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-phone me-1"></i>رقم الهاتف:</span><br>${getVal('data_phone_number')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-users me-1"></i>عدد أفراد الأسرة:</span><br>${getVal('data_number_of_individuals')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-heart me-1"></i>الحالة الاجتماعية:</span><br>${getSelText('data_marital_status')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-graduation-cap me-1"></i>المؤهل العلمي:</span><br>${getSelText('data_academic_qualification')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-exchange-alt me-1"></i>حالة النزوح:</span><br>${getSelText('data_displacement_status')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-map-marker-alt me-1"></i>العنوان الحالي:</span><br>${getVal('data_current_address')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-city me-1"></i>المدينة:</span><br>${getSelText('data_city')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-map me-1"></i>المحافظة:</span><br>${getSelText('data_province')}
                            </div>
                            <div class="col-md-4">
                                <span class="fw-bold text-primary"><i class="fas fa-heartbeat me-1"></i>الحالة الصحية:</span><br>${getSelText('data_health_status')}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // --- أفراد الأسرة ---
            let familyHtml = '';
            // نفرض أن لكل فرد خانة حاوية ذات الصنف .family-member-form
            const familyForms = document.querySelectorAll('.family-member-form');
            familyForms.forEach((formEl, idx) => {
                // دوال مساعدة لجلب القيم داخل كل بطاقة فرد
                const getInput = name => {
                    // نبحث name ينتهي بالمفتاح داخل كل formEl: مثلاً name="family_members[0][first_name]"
                    const el = formEl.querySelector(`[name$="[${name}]"]`);
                    return el ? el.value || '' : '';
                };
                const getSelectText = name => {
                    const sel = formEl.querySelector(`[name$="[${name}]"]`);
                    if (!sel) return '';
                    if (sel.tagName.toLowerCase() !== 'select') return sel.value || '';
                    const idxOpt = sel.selectedIndex;
                    if (idxOpt >= 0) return sel.options[idxOpt].text;
                    return '';
                };

                familyHtml += `
                    <div class="card shadow border-0 mb-3" style="border-radius:14px;">
                        <div class="card-header bg-info text-white fw-bold d-flex align-items-center" style="border-radius:14px 14px 0 0;">
                            <span style="font-size:1.1rem;">فرد الأسرة #${idx + 1}</span>
                            <i class="fas fa-user-friends me-2 text-white"></i>
                        </div>
                        <div class="card-body bg-white">
                            <div class="row g-4">
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الأول:</span><br>${getInput('first_name')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الثاني:</span><br>${getInput('second_name')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-user me-1"></i>الاسم الثالث:</span><br>${getInput('third_name')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-users me-1"></i>اسم العائلة:</span><br>${getInput('last_name')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-id-badge me-1"></i>رقم الهوية:</span><br>${getInput('person_id')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-calendar-alt me-1"></i>تاريخ الميلاد:</span><br>${getInput('person_birth_date')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-hourglass-half me-1"></i>العمر:</span><br>${getInput('person_age')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-venus-mars me-1"></i>الجنس:</span><br>${getSelectText('person_gender')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-heartbeat me-1"></i>الحالة الصحية:</span><br>${getSelectText('person_health_status')}
                                </div>
                                <div class="col-md-3">
                                    <span class="fw-bold text-info"><i class="fas fa-hand-holding-heart me-1"></i>نوع الكفالة:</span><br>${getSelectText('person_type_of_guarantee')}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            // --- بيانات المتوفين ---
            let deceasedHtml = '';
            const deceasedSection = document.getElementById('deceased');
            // نتأكد من وجود العنصر وأنه ظاهر (يمكن تعديل المنطق حسب طريقة إظهار/إخفاء القسم)
            if (deceasedSection && deceasedSection.style.display !== 'none') {
                // استخدام getVal/getSelText من النموذج الرئيسي
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

            // --- المرفقات ---
            let attachmentsHtml = '';
            // صاحب الملف: صور داخل عنصر له id="main_person_docs" ونتوقع أن كل صورة داخل .document-card img
            const mainDocs = document.querySelectorAll('#main_person_docs .document-card img');
            if (mainDocs.length) {
                attachmentsHtml += `<div class="mb-3"><span class="fw-bold text-primary"><i class="fas fa-paperclip me-1"></i>مرفقات صاحب الملف:</span><div class="d-flex gap-2 flex-wrap">`;
                mainDocs.forEach(img => {
                    attachmentsHtml += `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #0d6efd;background:#fff;">`;
                });
                attachmentsHtml += `</div></div>`;
            }
            // أفراد الأسرة: داخل حاويات معرفها #family_members_docs .documents-flex_container
            document.querySelectorAll('#family_members_docs .documents-flex-container').forEach((container, idx) => {
                const imgs = container.querySelectorAll('img');
                if (imgs.length) {
                    attachmentsHtml += `<div class="mb-3"><span class="fw-bold text-info"><i class="fas fa-paperclip me-1"></i>مرفقات فرد الأسرة #${idx + 1}:</span><div class="d-flex gap-2 flex-wrap">`;
                    imgs.forEach(img => {
                        attachmentsHtml += `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #17a2b8;background:#fff;">`;
                    });
                    attachmentsHtml += `</div></div>`;
                }
            });
            // متوفى الأب: داخل #father_docs img
            const fatherDocs = document.querySelectorAll('#father_docs img');
            if (fatherDocs.length) {
                attachmentsHtml += `<div class="mb-3"><span class="fw-bold text-danger"><i class="fas fa-paperclip me-1"></i>مرفقات الأب المتوفى:</span><div class="d-flex gap-2 flex-wrap">`;
                fatherDocs.forEach(img => {
                    attachmentsHtml += `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #dc3545;background:#fff;">`;
                });
                attachmentsHtml += `</div></div>`;
            }
            // متوفى الأم: داخل #mother_docs img
            const motherDocs = document.querySelectorAll('#mother_docs img');
            if (motherDocs.length) {
                attachmentsHtml += `<div class="mb-3"><span class="fw-bold text-danger"><i class="fas fa-paperclip me-1"></i>مرفقات الأم المتوفية:</span><div class="d-flex gap-2 flex-wrap">`;
                motherDocs.forEach(img => {
                    attachmentsHtml += `<img src="${img.src}" style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #dc3545;background:#fff;">`;
                });
                attachmentsHtml += `</div></div>`;
            }

            // دمج الأقسام في المحتوى النهائي
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
                            <div class="card-body bg-white">
                                ${attachmentsHtml || '<span class="text-muted">لا يوجد مرفقات</span>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    });
</script>
<style>
/* توحيد شكل جميع الأيقونات في التبويبات */
.tab-icon {
    /* الافتراضي للديسكتوب: أيقونة بسيطة فقط */
    font-size: 2rem;
    color: #0d6efd;
    background: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    margin-bottom: 0.2rem;
    border: none !important;
    transition: none !important;
    box-shadow: none !important;
}
.nav-tabs .nav-link.active .tab-icon,
.nav-tabs .nav-link:focus .tab-icon,
.nav-tabs .nav-link:hover .tab-icon {
    /* لا تأثيرات ولا تغيير لون أو خلفية في الديسكتوب */
    background: none !important;
    color: #0d6efd !important;
    border: none !important;
    transform: none !important;
    box-shadow: none !important;
}
@media (max-width: 576px) {
    .tab-label {
        display: none !important;
    }
    .tab-icon {
        font-size: 2.1rem !important;
        color: #232323 !important;
        background: rgba(200,200,200,0.18) !important;
        border-radius: 16px !important;
        padding: 0.55rem !important;
        margin-bottom: 0 !important;
        border: none !important;
        box-shadow: 0 1px 6px rgba(0,0,0,0.04) !important;
        transition: background 0.2s, color 0.2s, box-shadow 0.2s !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .nav-tabs .nav-link.active .tab-icon,
    .nav-tabs .nav-link:focus .tab-icon,
    .nav-tabs .nav-link:hover .tab-icon {
        color: #232323 !important;
        background: rgba(44,44,44,0.13) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.10) !important;
    }
}
/* ...existing code... */
</style>
}
/* ...existing code... */
</style>
