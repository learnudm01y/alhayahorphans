    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">القسم الوصي (المعيل) <span class="text-danger">*</span></label>
            <select name="data_section_id" class="form-select" readonly disabled>
                @php
                    $activeSection = $generalSection->firstWhere('status', 1);
                @endphp
                @if($activeSection)
                    <option value="{{ $activeSection->id }}" selected>{{ $activeSection->description }}</option>
                @else
                    <option value="">لا يوجد قسم مفعل</option>
                @endif
            </select>
            <input type="hidden" name="data_section_id" value="{{ $activeSection->id ?? '' }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">رقم الهوية الوصي (المعيل) <span class="text-danger">*</span></label>
            <input type="text" name="data_id_number" id="data_id_number" class="form-control" inputmode="numeric" minlength="9" maxlength="10" pattern="[0-9]{9,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>

        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الاسم الأول الوصي (المعيل) <span class="text-danger">*</span></label>
                    <input type="text" name="data_first_name" class="form-control" maxlength="30">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم الأب الوصي (المعيل) <span class="text-danger">*</span></label>
                    <input type="text" name="data_father_name" class="form-control" maxlength="30">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم الجد الوصي (المعيل) <span class="text-danger">*</span></label>
                    <input type="text" name="data_grand_father_name" class="form-control" maxlength="30">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم العائلة الوصي (المعيل) <span class="text-danger">*</span></label>
                    <input type="text" name="data_family_name" class="form-control" maxlength="30">
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">صلة القرابة الوصي (المعيل) <span class="text-danger">*</span></label>
            <select name="data_relationship" id="data_relationship" class="form-select">
                <option value="">اختر صلة القرابة</option>
                @foreach ($category_of_relationship->where('attribute', '!=', 'Unknown') as $category)
                    <option value="{{ $category->id }}">{{ $category->attribute }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">تاريخ الميلاد الوصي (المعيل) <span class="text-danger">*</span></label>
            <input type="date" name="data_birth_date" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">الجنس الوصي (المعيل) <span class="text-danger">*</span></label>
            <select name="data_gender" class="form-select">
                <option value="">اختر الجنس</option>
                <option value="1">ذكر</option>
                <option value="2">أنثى</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">رقم الهاتف الوصي (المعيل) <span class="text-danger">*</span></label>
            <input type="number" name="data_phone_number" class="form-control" maxlength="10"
                oninput="if(this.value.length>10)this.value=this.value.slice(0,10);"
                placeholder="مثال: 0599905588">
            <small class="text-muted">أدخل رقم الجوال بهذا الشكل: 0599905588</small>
        </div>
        <div class="col-md-4">
            <label class="form-label">رقم هاتف بديل الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
            <input type="number" name="data_alt_phone_number" class="form-control" maxlength="10"
                oninput="if(this.value.length>10)this.value=this.value.slice(0,10);"
                placeholder="مثال: 0599905588">
            <small class="text-muted">أدخل رقم الجوال بهذا الشكل: 0599905588</small>
        </div>
        <div class="col-md-4">
            <label class="form-label">عدد افراد الاسرة الوصي (المعيل)</label>
            <input type="number" name="data_number_of_individuals" class="form-control" min="0" max="9999" maxlength="4" oninput="if(this.value.length>4)this.value=this.value.slice(0,4);" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">الحالة الاجتماعية الوصي (المعيل) <span class="text-danger">*</span></label>
            <select name="data_marital_status" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($ci_personal_cd as $status)
                    <option value="{{ $status->id }}">{{ $status->CI_PERSONAL_CD }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">المؤهل العلمي الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
            <select name="data_academic_qualification" class="form-select">
                <option value="">اختر المؤهل</option>
                @foreach ($academic_qualification->where('description', '!=', 'Unknown') as $qualification)
                    <option value="{{ $qualification->id }}">
                        {{ $qualification->description }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">حالة النزوح الوصي (المعيل) <span class="text-danger">*</span></label>
            <select name="data_displacement_status" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($displacement_status->where('description', '!=', 'Unknown') as $displacement_status_item)
                    <option value="{{ $displacement_status_item->id }}">
                        {{ $displacement_status_item->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">العنوان قبل النزوح الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="data_address_before_displacement" class="form-control" maxlength="30">
                </div>
                <div class="col-md-3">
                    <label class="form-label">العنوان الحالي الوصي (المعيل) <span class="text-danger">*</span></label>
                    <input type="text" name="data_current_address" class="form-control" maxlength="30">
                </div>
                <div class="col-md-3">
                    <label class="form-label">المدينة الوصي (المعيل) <span class="text-danger">*</span></label>
                    <select name="data_city" class="form-select">
                        <option value="">اختر المدينة</option>
                        @foreach ($city->where('city', '!=', 'Unknown') as $city_item)
                            <option value="{{ $city_item->id }}">{{ $city_item->city }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المحافظة الوصي (المعيل) <span class="text-danger">*</span></label>
                    <select name="data_province" class="form-select">
                        <option value="">اختر المحافظة</option>
                        @foreach ($province->where('description', '!=', 'Unknown') as $province_item)
                            <option value="{{ $province_item->id }}">
                                {{ $province_item->description }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">الحالة الصحية الوصي (المعيل) <span class="text-danger">*</span></label>
                    <select name="data_health_status" class="form-select">
                        <option value="">اختر الحالة</option>
                        @foreach ($health_status->where('description', '!=', 'Unknown') as $health_status_item)
                            <option value="{{ $health_status_item->id }}">
                                {{ $health_status_item->description }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">وصف الإحتياجات الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
                    <textarea name="data_description_needs" class="form-control"></textarea>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">عدد الذكور الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_mail" class="form-control" min="0" max="9999" maxlength="4" oninput="if(this.value.length>4)this.value=this.value.slice(0,4);">
                </div>
                <div class="col-md-3">
                    <label class="form-label">عدد الإناث الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_female" class="form-control" min="0" max="9999" maxlength="4" oninput="if(this.value.length>4)this.value=this.value.slice(0,4);">
                </div>
                <div class="col-md-3">
                    <label class="form-label">عدد الأفراد المصابين بأمراض مزمنة الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_of_individuals_with_chronic_diseases"
                        class="form-control" min="0" max="9999" maxlength="4" oninput="if(this.value.length>4)this.value=this.value.slice(0,4);">
                </div>
                <div class="col-md-3">
                    <label class="form-label">عدد ذوي الاحتياجات الخاصة الوصي (المعيل) <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_of_people_with_special_needs" class="form-control"
                        min="0" max="9999" maxlength="4" oninput="if(this.value.length>4)this.value=this.value.slice(0,4);">
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">الحالة الوظيفية المعيل <span class="text-danger">*</span></label>
            <select name="data_employment_status_breadwinner" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($employment_status_breadwinner->where('description', '!=', 'Unknown') as $employment_status_item)
                    <option value="{{ $employment_status_item->id }}">
                        {{ $employment_status_item->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">حالة السكن الوصي (المعيل) <span class="text-danger">*</span></label>
            <select name="data_housing_status" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($HousingStatus->where('description', '!=', 'Unknown') as $HousingStatusItem)
                    <option value="{{ $HousingStatusItem->id }}">
                        {{ $HousingStatusItem->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">نوع السكن الحالي الوصي (المعيل) <span class="text-danger">*</span></label>
            <select name="data_current_housing_type" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($TypeOfAccommodation->where('description', '!=', 'Unknown') as $TypeOfAccommodationItem)
                    <option value="{{ $TypeOfAccommodationItem->id }}">
                        {{ $TypeOfAccommodationItem->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mt-3">
            <!-- ملاحظة و زر إضافة حساب بنكي جديد -->
            <div class="alert alert-info py-2 mb-2 d-flex align-items-center justify-content-between" style="font-size: 0.97rem;">
                <span>إذا كنت تمتلك حساب بنك قم بإضافة المعلومات المطلوبة. يمكنك إضافة حتى 10 حسابات بنكية.</span>
                <button type="button" class="btn btn-outline-primary btn-sm ms-2" id="addBankAccountBtn" style="border: 2px solid #0d6efd; border-radius: 8px; box-shadow: 0 0 0 2px #e7f1ff;">
                    إضافة حساب بنكي
                </button>
            </div>
            <!-- منطقة الحسابات البنكية الديناميكية (مخفية في البداية) -->
            <div id="bankAccountsContainer" class="d-none">
                <!-- سيتم توليد النماذج البنكية هنا عبر الجافاسكريبت -->
            </div>

            <!-- قسم بيانات الأم (يظهر/يختفي حسب صلة القرابة) -->
            <div id="motherSection" class="mt-4 d-none" style="border: 2px solid #6f42c1; border-radius: 10px; padding: 15px; background: #f8f0ff;">
                <h6 class="text-purple fw-bold mb-3"><i class="fas fa-female me-2"></i>بيانات الأم</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">رقم هوية الأم <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="mother_id" id="mother_id" class="form-control" inputmode="numeric" maxlength="9" pattern="[0-9]{9}" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            <span class="input-group-text" id="motherLookupStatus" style="display:none;">
                                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                            </span>
                        </div>
                        <small class="text-muted">سيتم جلب البيانات تلقائياً من السجل المدني</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">اسم الأم الأول <span class="text-danger">*</span></label>
                        <input type="text" name="mother_first_name" id="mother_first_name" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">اسم الأب (اسم ثاني الأم) <span class="text-danger">*</span></label>
                        <input type="text" name="mother_second_name" id="mother_second_name" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">اسم الجد (اسم ثالث الأم) <span class="text-danger">*</span></label>
                        <input type="text" name="mother_third_name" id="mother_third_name" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">اسم عائلة الأم <span class="text-danger">*</span></label>
                        <input type="text" name="mother_last_name" id="mother_last_name" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">هل الأم على قيد الحياة؟ <span class="text-danger">*</span></label>
                        <select name="mother_is_alive" id="mother_is_alive" class="form-select">
                            <option value="">اختر</option>
                            <option value="1">نعم</option>
                            <option value="0">لا</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="mother_from_civil_registry" id="mother_from_civil_registry" value="0">
            </div>
            <!-- ملاحظة توضيحية لرفع الملفات -->
            <div class="alert alert-primary py-2 mb-2 mt-3" style="font-size: 0.97rem;">
                يرجى اختيار نوع الوثيقة أولاً، وسوف يتم تحويلك لرفع الصورة
                المطلوبة.
            </div>
            <!-- منطقة رفع الملفات للبيانات الأساسية -->
            <div data-upload-zone="main" data-person-id="{{ $data_id_number ?? '' }}">
                <label class="form-label fw-bold"> رفع الملفات <span class="text-danger">*</span></label>
                <div class="alert alert-warning py-2 mb-2" style="font-size: 0.9rem;">
                    <i class="fas fa-star text-danger me-1"></i>
                    <strong>الوثائق المميزة بعلامة <span class="text-danger">★</span> إجبارية ويجب إدخالها</strong>
                </div>
                <select class="form-select mainDocumentTypeSelect" id="mainDocumentTypeSelect_main">
                    <option value="">اختر نوع الوثيقة</option>
                    @foreach ($documentTypes->where('basic_enabled', 1)->where('description', '!=', 'Unknown') as $documentType)
                        <option value="{{ $documentType->pref }}" {{ $documentType->basic_required ? 'data-required=true' : '' }}>
                            {{ $documentType->basic_required ? '★ ' : '' }}{{ $documentType->description }}{{ $documentType->basic_required ? ' (إجباري)' : '' }}
                        </option>
                    @endforeach
                </select>
                <input type="file" class="mainDocumentFileInput" id="mainDocumentFileInput_main" tabindex="-1" aria-hidden="true"
                style="
                position: absolute !important;
                width:1px !important;
                height:1px !important;
                opacity:0 !important;
                pointer-events:none !important;
                      ">
                <div class="mainDocumentPreview" id="mainDocumentPreview_main"></div>
                <div class="mainDocumentNames" id="mainDocumentNames_main"></div>
            </div>
        </div>
    </div>
<!-- زر التالي مع تباعد مناسب -->
<div class="mt-5 mb-5 text-end">
    <button type="button" class="btn btn-success px-5 py-2 fs-5" id="goToNextTabBtn" style="margin-top: 10rem; margin-bottom: 5rem;">
        التالي <i class="fas fa-arrow-left ms-2"></i>
    </button>
</div>

@include('user.generalRegistration.javascript.baseTapJavascript')

<!-- SweetAlert2 CDN -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nextBtn = document.getElementById('goToNextTabBtn');
        if (nextBtn) {
            nextBtn.addEventListener('click', async function(e) {
                // جميع الحقول المطلوبة (input/select/textarea) التي عليها نجمة
                // تحقق من تطابق كلمة المرور
                        const password = document.querySelector('[name="user_password"]')?.value || '';
                        const passwordConfirm = document.querySelector('[name="user_password_confirmation"]')?.value || '';
                        if (password && passwordConfirm && password !== passwordConfirm) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'تنبيه',
                                text: 'كلمة المرور وتأكيد كلمة المرور غير متطابقتين!'
                            });
                            return;
                        }
                const requiredFields = [
                    {
                        name: 'data_section_id',
                        label: 'القسم'
                    },
                    {
                        name: 'data_id_number',
                        label: 'رقم الهوية'
                    },
                    {
                        name: 'user_password',
                        label: 'كلمة المرور'
                    },
                    {
                        name: 'user_password_confirmation',
                        label: 'تأكيد كلمة المرور'
                    },
                    {
                        name: 'data_first_name',
                        label: 'الاسم الأول'
                    },
                    {
                        name: 'data_father_name',
                        label: 'اسم الأب'
                    },
                    {
                        name: 'data_grand_father_name',
                        label: 'اسم الجد'
                    },
                    {
                        name: 'data_family_name',
                        label: 'اسم العائلة'
                    },
                    {
                        name: 'data_relationship',
                        label: 'صلة القرابة'
                    },
                    {
                        name: 'data_birth_date',
                        label: 'تاريخ الميلاد'
                    },
                    {
                        name: 'data_gender',
                        label: 'الجنس'
                    },
                    {
                        name: 'data_phone_number',
                        label: 'رقم الهاتف'
                    },
                    {
                        name: 'data_marital_status',
                        label: 'الحالة الاجتماعية'
                    },
                    {
                        name: 'data_displacement_status',
                        label: 'حالة النزوح'
                    },
                    {
                        name: 'data_current_address',
                        label: 'العنوان الحالي'
                    },
                    {
                        name: 'data_city',
                        label: 'المدينة'
                    },
                    {
                        name: 'data_province',
                        label: 'المحافظة'
                    },
                    {
                        name: 'data_health_status',
                        label: 'الحالة الصحية'
                    },
                    {
                        name: 'data_employment_status_breadwinner',
                        label: 'الحالة الوظيفية المعيل'
                    },
                    {
                        name: 'data_housing_status',
                        label: 'حالة السكن'
                    },
                    {
                        name: 'data_current_housing_type',
                        label: 'نوع السكن الحالي'
                    },
                    {
                        name: 'data_number_of_individuals',
                        label: 'عدد افراد الاسرة'
                    },
                ];
                let firstInvalid = null;
                for (const field of requiredFields) {
                    const el = document.querySelector(`[name="${field.name}"]`);
                    if (el) {
                        if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
                            firstInvalid = field;
                            break;
                        } else if ((el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName ===
                                'TEXTAREA') && !el.value) {
                            firstInvalid = field;
                            break;
                        }
                    }
                }
                if (firstInvalid) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: `يرجى إدخال ${firstInvalid.label} قبل المتابعة!`,
                        confirmButtonText: 'حسنًا'
                    });
                    return;
                }

                // تحقق من بيانات الأم إذا كان القسم مرئياً
                const motherSection = document.getElementById('motherSection');
                if (motherSection && !motherSection.classList.contains('d-none')) {
                    const motherRequiredFields = [
                        { name: 'mother_id', label: 'رقم هوية الأم' },
                        { name: 'mother_first_name', label: 'اسم الأم الأول' },
                        { name: 'mother_second_name', label: 'اسم الأب (اسم ثاني الأم)' },
                        { name: 'mother_third_name', label: 'اسم الجد (اسم ثالث الأم)' },
                        { name: 'mother_last_name', label: 'اسم عائلة الأم' },
                        { name: 'mother_is_alive', label: 'حالة الأم' },
                    ];
                    let motherInvalid = null;
                    for (const field of motherRequiredFields) {
                        const el = document.querySelector(`[name="${field.name}"]`);
                        if (el && !el.value) {
                            motherInvalid = field;
                            break;
                        }
                    }
                    if (motherInvalid) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: `يرجى إدخال ${motherInvalid.label} قبل المتابعة!`,
                            confirmButtonText: 'حسنًا'
                        });
                        return;
                    }
                }

                // تحقق من أرقام الهوية (تكرار وقاعدة بيانات) قبل أي انتقال
                const valid = await validateAllIds(e);
                if (!valid) return;
                // منطق الانتقال حسب القسم
                const sectionSelect = document.querySelector('select[name="data_section_id"]');
                const selectedValue = sectionSelect ? sectionSelect.value : '';
                const orphansSectionId = '1'; // عدل هذا الرقم حسب قاعدة البيانات لديك إذا لزم الأمر
                if (selectedValue === orphansSectionId) {
                    const deceasedTab = document.getElementById('deceased-tab');
                    if (deceasedTab) deceasedTab.click();
                } else {
                    const familyTab = document.getElementById('family-members-tab');
                    if (familyTab) familyTab.click();
                }
            });
        }
        // زر إظهار الحقول البنكية
        const showBankFieldsBtn = document.getElementById('showBankFieldsBtn');
        const bankFields = document.getElementById('bankFields');
        if (showBankFieldsBtn && bankFields) {
            showBankFieldsBtn.addEventListener('click', function() {
                bankFields.classList.remove('d-none');
                showBankFieldsBtn.disabled = true;
                showBankFieldsBtn.innerText = 'تم عرض الحقول';
            });
        }
        // منطقة الحسابات البنكية الديناميكية
        const bankAccountsContainer = document.getElementById('bankAccountsContainer');
        const addBankAccountBtn = document.getElementById('addBankAccountBtn');
        let bankAccountCount = 0;
        const maxBankAccounts = 10;
        const bankNames = @json($bank_name);

        function createBankAccountForm(index) {
            return `
            <div class="bank-account-form border rounded p-2 mb-3 position-relative"
                 data-index="${index}"
                 style="border:2px dashed #000 !important;">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-bank-account-btn" title="حذف الحساب"></button>
                  <div class="mb-2">
                    <label class="form-label">اسم البنك <span class="text-primary">(اختياري)</span></label>
                    <select name="bank_accounts[${index}][bank_name]" class="form-select">
                        <option value="">اختر البنك</option>
                        ${bankNames.map(bank => `<option value="${bank.id}">${bank.description}</option>`).join('')}
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">رقم هوية صاحب الحساب <span class="text-primary">(اختياري)</span></label>
                    <div class="input-group">
                        <input type="text" name="bank_accounts[${index}][person_owner_identity_number]" class="form-control bank-owner-id-input" maxlength="20" data-index="${index}" placeholder="أدخل رقم الهوية لجلب الاسم تلقائياً">
                        <span class="input-group-text bank-owner-search-status" data-index="${index}" style="display:none;">
                            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                        </span>
                    </div>
                    <small class="text-muted">سيتم جلب الاسم تلقائياً من قاعدة البيانات المركزية</small>
                </div>
                <div class="mb-2">
                    <label class="form-label">اسم صاحب حساب البنك <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_accounts[${index}][re_guardian_name]" class="form-control bank-owner-name-input" data-index="${index}" maxlength="100" placeholder="سيتم جلب الاسم تلقائياً أو يمكنك إدخاله يدوياً">
                </div>
                <div class="mb-2">
                    <label class="form-label">رقم هاتف صاحب الحساب <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_accounts[${index}][re_phone_number]" class="form-control" maxlength="20">
                </div>
                <div class="mb-2">
                    <label class="form-label">رقم حساب البنك الدولاري (IBAN) <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_accounts[${index}][iban_usd]" class="form-control" maxlength="34">
                </div>
                <div class="mb-2">
                    <label class="form-label">رقم حساب البنك الشيكل (IBAN) <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_accounts[${index}][iban_shekel]" class="form-control" maxlength="34">
                </div>

            </div>
            `;
        }

        function updateRemoveButtons() {
            document.querySelectorAll('.remove-bank-account-btn').forEach(btn => {
                btn.onclick = function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'تأكيد الحذف',
                        text: 'هل أنت متأكد أنك تريد حذف معلومات الحساب البنكي؟',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'نعم، احذف',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            btn.closest('.bank-account-form').remove();
                            bankAccountCount--;
                            if (bankAccountCount < maxBankAccounts) addBankAccountBtn.disabled = false;
                            if (bankAccountCount === 0) bankAccountsContainer.classList.add('d-none');
                        }
                    });
                }
            });
        }

        // دالة جلب اسم صاحب الحساب من قاعدة البيانات المركزية
        function fetchBankOwnerName(idNumber, formIndex) {
            const nameInput = document.querySelector(`.bank-owner-name-input[data-index="${formIndex}"]`);
            const statusSpan = document.querySelector(`.bank-owner-search-status[data-index="${formIndex}"]`);

            if (!idNumber || idNumber.length < 5) {
                return;
            }

            // إظهار مؤشر التحميل
            if (statusSpan) statusSpan.style.display = 'flex';

            fetch(`/api/civil-registry/search-by-id?search_text=${encodeURIComponent(idNumber)}`)
                .then(response => response.json())
                .then(data => {
                    if (statusSpan) statusSpan.style.display = 'none';

                    if (data.success && data.data && data.data.length > 0) {
                        const person = data.data[0];
                        // تجميع الاسم الكامل من الحقول المختلفة
                        const fullName = [
                            person.CI_FIRST_ARB || '',
                            person.CI_FATHER_ARB || '',
                            person.CI_GRAND_FATHER_ARB || '',
                            person.CI_FAMILY_ARB || ''
                        ].filter(n => n).join(' ');

                        if (fullName && nameInput) {
                            nameInput.value = fullName;
                            nameInput.classList.add('is-valid');
                            nameInput.classList.remove('is-invalid');
                        }
                    } else {
                        // لم يتم العثور على الشخص - السماح بالإدخال اليدوي
                        if (nameInput) {
                            nameInput.classList.remove('is-valid');
                            nameInput.placeholder = 'لم يتم العثور على الاسم - يرجى إدخاله يدوياً';
                        }
                    }
                })
                .catch(error => {
                    if (statusSpan) statusSpan.style.display = 'none';
                    console.error('خطأ في جلب بيانات صاحب الحساب:', error);
                    if (nameInput) {
                        nameInput.placeholder = 'يرجى إدخال الاسم يدوياً';
                    }
                });
        }

        // ربط حدث الإدخال بحقول رقم الهوية
        function attachBankOwnerIdListener() {
            document.querySelectorAll('.bank-owner-id-input').forEach(input => {
                if (!input.dataset.listenerAttached) {
                    input.dataset.listenerAttached = 'true';
                    let debounceTimer;
                    input.addEventListener('input', function() {
                        clearTimeout(debounceTimer);
                        const idNumber = this.value.trim();
                        const formIndex = this.dataset.index;

                        debounceTimer = setTimeout(() => {
                            fetchBankOwnerName(idNumber, formIndex);
                        }, 500); // انتظار 500 مللي ثانية بعد توقف الكتابة
                    });

                    // جلب الاسم عند فقدان التركيز أيضاً
                    input.addEventListener('blur', function() {
                        const idNumber = this.value.trim();
                        const formIndex = this.dataset.index;
                        if (idNumber.length >= 5) {
                            fetchBankOwnerName(idNumber, formIndex);
                        }
                    });
                }
            });
        }

        addBankAccountBtn.addEventListener('click', function() {
            if (bankAccountCount < maxBankAccounts) {
                if (bankAccountsContainer.classList.contains('d-none')) {
                    bankAccountsContainer.classList.remove('d-none');
                }
                bankAccountsContainer.insertAdjacentHTML('beforeend', createBankAccountForm(bankAccountCount));
                bankAccountCount++;
                updateRemoveButtons();
                attachBankOwnerIdListener(); // ربط مستمع الجلب التلقائي
                if (bankAccountCount >= maxBankAccounts) addBankAccountBtn.disabled = true;
            }
        });
        // لا تضف نموذج افتراضي عند التحميل، بل انتظر الضغط على الزر
    }); // نهاية document.addEventListener('DOMContentLoaded', ...)

    // إذا كان الإرسال AJAX، أضف الكود التالي لجمع الحقول البنكية قبل الإرسال:
    // document.addEventListener('DOMContentLoaded', function() {
    //     const mainForm = document.getElementById('main_form');
    //     if (!mainForm) return;
    //     mainForm.addEventListener('submit', function(e) {
    //         // ...existing code لجمع بيانات أفراد الأسرة والمرفقات...

    //         // حذف أي بيانات حسابات بنكية قديمة من FormData
    //         const formData = new FormData(mainForm);
    //         Array.from(formData.keys()).forEach(key => {
    //             if (key.startsWith('bank_accounts')) {
    //                 formData.delete(key);
    //             }
    //         });

    //         // جمع بيانات الحسابات البنكية الديناميكية
    //         document.querySelectorAll('.bank-account-form').forEach(function(form, idx) {
    //             form.querySelectorAll('[name]').forEach(function(input) {
    //                 const name = input.name;
    //                 const value = input.value;
    //                 if (name.startsWith('bank_accounts[')) {
    //                     formData.append(name, value);
    //                 }
    //             });
    //         });

    //         // طباعة بيانات الحسابات البنكية في الواجهة قبل الإرسال
    //         let bankAccountsDebug = [];
    //         document.querySelectorAll('.bank-account-form').forEach(function(form, idx) {
    //             let obj = {};
    //             form.querySelectorAll('[name]').forEach(function(input) {
    //                 obj[input.name] = input.value;
    //             });
    //             bankAccountsDebug.push(obj);
    //         });
    //         console.log('🚀 بيانات الحسابات البنكية المرسلة:', bankAccountsDebug);

    //         // لا ترسل النموذج هنا إذا كان هناك إرسال AJAX في manageForm.blade.php
    //         // فقط أضف البيانات إلى FormData، ودع الإرسال يتم من مكان واحد فقط
    //         // e.preventDefault(); // احذف أو علق هذا السطر إذا كان الإرسال سيتم من manageForm.blade.php

    //     });
    // });
    // عند الإرسال AJAX، أو عند الإرسال العادي، عالج مشكلة required مع الحقول المخفية أو غير القابلة للتركيز
    // الحل: إزالة required مؤقتاً من الحقول غير الظاهرة قبل الإرسال ثم إرجاعها بعد الإرسال
    document.addEventListener('DOMContentLoaded', function() {
        const mainForm = document.getElementById('main_form');
        if (!mainForm) return;
        mainForm.addEventListener('submit', function(e) {
            // إزالة required مؤقتاً من الحقول غير الظاهرة أو غير القابلة للتركيز
            mainForm.querySelectorAll('[required]').forEach(function(input) {
                const style = window.getComputedStyle(input);
                if ((style.display === 'none' || input.offsetParent === null || input.disabled) && input.required) {
                    input.removeAttribute('required');
                    input.setAttribute('data-temp-required', '1');
                }
            });
            // بعد الإرسال، أعد required للحقول التي أزلناها مؤقتاً
            setTimeout(function() {
                mainForm.querySelectorAll('[data-temp-required]').forEach(function(input) {
                    input.setAttribute('required', 'required');
                    input.removeAttribute('data-temp-required');
                });
            }, 100);
        }, true);

        // التحقق الفوري من رقم الهوية في البوابة الأساسية
        const mainIdInput = document.getElementById('data_id_number');
        if (mainIdInput) {
            mainIdInput.addEventListener('blur', function() {
                const value = this.value.trim();

                if (value === '') {
                    this.style.border = '2px solid red';
                    return;
                }

                if (!/^\d+$/.test(value)) {
                    this.style.border = '2px solid red';
                    Swal.fire({
                        icon: 'error',
                        title: 'رقم الهوية غير صحيح',
                        text: 'يجب أن يحتوي رقم الهوية على أرقام فقط',
                        confirmButtonText: 'حسناً'
                    });
                    return;
                }

                if (value.length !== 9) {
                    this.style.border = '2px solid red';
                    Swal.fire({
                        icon: 'error',
                        title: 'رقم الهوية غير مكتمل',
                        text: 'رقم الهوية يجب أن يكون 9 أرقام بالضبط. أنت أدخلت ' + value.length + ' رقم فقط',
                        confirmButtonText: 'حسناً'
                    });
                    return;
                }

                // رقم الهوية صحيح
                this.style.border = '2px solid green';

                // 🆕 التحقق من وجود المعيل في قاعدة البيانات
                checkExistingGuardian(value);
            });

            // إزالة التنسيق عند بدء الكتابة
            mainIdInput.addEventListener('input', function() {
                if (this.style.border) {
                    this.style.border = '';
                }
            });
        }
    });

    /**
     * جلب بيانات المعيل من السجل المدني وملء الحقول تلقائياً
     */
    async function checkExistingGuardian(identityNumber) {
        if (!identityNumber || identityNumber.length !== 9) return;

        try {
            const response = await fetch('{{ route("lookup.guardian") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ identity_number: identityNumber })
            });

            const result = await response.json();

            if (result.success && result.data && result.source === 'civil_registry') {
                fillFieldsFromCivilRegistry(result.data);
            }
        } catch (error) {
            console.error('خطأ في التحقق من المعيل:', error);
            Swal.close();
        }
    }

    /**
     * ملء الحقول من السجل المدني
     */
    function fillFieldsFromCivilRegistry(personData) {
        const fields = {
            'data_id_number': personData.data_id_number || personData.id_number,
            'data_first_name': personData.data_first_name || personData.first_name,
            'data_father_name': personData.data_father_name || personData.father_name,
            'data_grand_father_name': personData.data_grand_father_name || personData.grand_father_name,
            'data_family_name': personData.data_family_name || personData.family_name,
            'data_birth_date': personData.data_birth_date || personData.birth_date,
            'data_gender': personData.data_gender || personData.gender,
            'data_marital_status': personData.data_marital_status || personData.marital_status,
            'data_city': personData.data_city || personData.city,
            'data_current_address': personData.data_current_address || personData.street
        };

        Object.keys(fields).forEach(fieldName => {
            const field = document.querySelector('[name="' + fieldName + '"]');
            if (field && fields[fieldName]) {
                if (fieldName === 'data_birth_date') {
                    try {
                        let dateValue = fields[fieldName];
                        if (dateValue) {
                            const dateObj = new Date(dateValue);
                            if (!isNaN(dateObj.getTime())) {
                                const year = dateObj.getFullYear();
                                const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                                const day = String(dateObj.getDate()).padStart(2, '0');
                                field.value = `${year}-${month}-${day}`;
                            } else {
                                field.value = dateValue;
                            }
                        }
                    } catch (e) {}
                } else {
                    field.value = fields[fieldName];
                }
                field.dispatchEvent(new Event('change', { bubbles: true }));
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });

    }

    // =====================================================
    // 🆕 قسم بيانات الأم حسب صلة القرابة
    // =====================================================
    document.addEventListener('DOMContentLoaded', function() {
        const relationshipSelect = document.getElementById('data_relationship');
        const motherSection = document.getElementById('motherSection');
        const motherIdInput = document.getElementById('mother_id');

        if (relationshipSelect) {
            relationshipSelect.addEventListener('change', function() {
                const selectedValue = this.value;
                // إذا كانت صلة القرابة = 1 (الأم)، لا تظهر قسم الأم
                if (selectedValue === '1' || selectedValue === '') {
                    motherSection.classList.add('d-none');
                    // مسح حقول الأم
                    clearMotherFields();
                } else {
                    motherSection.classList.remove('d-none');
                }
            });
        }

        // البحث عن الأم في السجل المدني عند إدخال رقم الهوية
        if (motherIdInput) {
            let motherDebounceTimer;
            motherIdInput.addEventListener('input', function() {
                clearTimeout(motherDebounceTimer);
                const value = this.value.trim();
                if (value.length >= 9) {
                    motherDebounceTimer = setTimeout(() => {
                        lookupMother(value);
                    }, 500);
                }
            });

            motherIdInput.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value.length >= 9) {
                    lookupMother(value);
                }
            });
        }
    });

    function clearMotherFields() {
        const fields = ['mother_id', 'mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name', 'mother_is_alive'];
        fields.forEach(fieldName => {
            const field = document.getElementById(fieldName) || document.querySelector(`[name="${fieldName}"]`);
            if (field) field.value = '';
        });
    }

    async function lookupMother(idNumber) {
        if (!idNumber || idNumber.length < 9) return;

        const statusEl = document.getElementById('motherLookupStatus');
        if (statusEl) statusEl.style.display = 'flex';

        try {
            const response = await fetch('{{ route("lookup.mother") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ identity_number: idNumber })
            });

            const result = await response.json();

            if (statusEl) statusEl.style.display = 'none';

            if (result.success && result.data) {
                const data = result.data;
                setMotherField('mother_first_name', data.mother_first_name);
                setMotherField('mother_second_name', data.mother_second_name);
                setMotherField('mother_third_name', data.mother_third_name);
                setMotherField('mother_last_name', data.mother_last_name);

                // تحديد حالة الحياة تلقائياً من السجل المدني
                const isAliveSelect = document.getElementById('mother_is_alive');
                if (isAliveSelect) {
                    isAliveSelect.value = data.is_alive ? '1' : '0';
                }

                document.getElementById('mother_from_civil_registry').value = '1';

                Swal.fire({
                    icon: 'success',
                    title: 'تم جلب بيانات الأم',
                    text: result.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'لم يتم العثور على بيانات',
                    text: result.message || 'يرجى إدخال بيانات الأم يدوياً',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        } catch (error) {
            if (statusEl) statusEl.style.display = 'none';
            console.error('خطأ في البحث عن الأم:', error);
        }
    }

    function setMotherField(name, value) {
        const field = document.getElementById(name) || document.querySelector(`[name="${name}"]`);
        if (field && value) {
            field.value = value;
            field.style.backgroundColor = '#e8f5e9';
        }
    }

    // =====================================================
    // 🆕 التحقق من ربط الشخص بمعيل آخر
    // =====================================================
    async function checkPersonLinked(identityNumber, currentFileId) {
        if (!identityNumber || identityNumber.length < 9) return false;

        try {
            const response = await fetch('{{ route("check.person.linked") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    identity_number: identityNumber,
                    current_file_id: currentFileId
                })
            });

            const result = await response.json();

            if (result.is_linked) {
                Swal.fire({
                    icon: 'error',
                    title: 'الشخص مرتبط بمعيل آخر',
                    text: result.message,
                    confirmButtonText: 'حسناً'
                });
                return true;
            }

            return false;
        } catch (error) {
            console.error('خطأ في التحقق من ربط الشخص:', error);
            return false;
        }
    }</script>
