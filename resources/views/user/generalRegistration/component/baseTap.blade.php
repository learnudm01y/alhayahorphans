<div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">القسم <span class="text-danger">*</span></label>
            <select name="data_section_id" class="form-select">
                <option value="">اختر القسم</option>
                @foreach ($generalSection as $section)
                    <option value="{{ $section->id }}">{{ $section->description }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
            <input type="text" name="data_id_number" id="data_id_number" class="form-control" inputmode="numeric"
                pattern="[0-9]*" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div class="col-md-8">
            <div class="row g-2 align-items-end">
                <div class="col-md-12 mb-2">

                    <span class="text-primary fw-bold" style="font-size: 1rem;">تتكون كلمة المرور من 4 أرقام فقط، وتكون
                        متطابقة مثلا (1234) ,(1234).</span><br>
                </div>
                <div class="col-12 d-flex flex-row gap-3">
                    <div class="flex-fill position-relative">
                        <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                        <input type="text" name="user_password" class="form-control" autocomplete="new-password"
                            maxlength="4" inputmode="numeric" pattern="\d{4}" required>
                        <div class="d-flex align-items-center mt-1">
                            <span class=" fw-bold" style="font-size: 0.95rem; color: #5d5d5d;">ننصحك بأخذ لقطة شاشة أو
                                حفظ كلمة المرور في كلمات مرور جوجل حتى لا تفقدها.</span>
                            <button type="button"
                                class="btn btn-outline-primary btn-sm ms-2 position-relative screenshot-pulse-btn"
                                id="screenshotPasswordBtn" title="التقاط لقطة شاشة لكلمة المرور">
                                <span class="pulse-circle"></span>
                                <i class="fas fa-camera"></i>
                            </button>

                        </div>
                        <div class="screenshot-hint text-primary fw-bold mt-1 d-none" style="font-size:0.95rem;">
                            <i class="fas fa-hand-pointer"></i> اضغط على أيقونة الكاميرا لتصوير كلمة المرور
                        </div>
                    </div>
                    <div class="flex-fill">
                        <label class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                        <input type="text" name="user_password_confirmation" class="form-control"
                            autocomplete="new-password" maxlength="4" inputmode="numeric" pattern="\d{4}" required>
                        <button type="button" class="btn btn-success btn-sm ms-2 mt-2" id="saveToGoogleBtn"
                            title="حفظ كلمة المرور في جوجل"
                            style="white-space: nowrap; font-size: 0.92rem; padding: 0.35rem 0.7rem; min-width: 90px;">
                            <i class="fab fa-google me-1"></i> حفظ في جوجل
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                    <input type="text" name="data_first_name" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم الأب <span class="text-danger">*</span></label>
                    <input type="text" name="data_father_name" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم الجد<span class="text-danger">*</span></label>
                    <input type="text" name="data_grand_father_name" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">اسم العائلة<span class="text-danger">*</span></label>
                    <input type="text" name="data_family_name" class="form-control">
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">صلة القرابة <span class="text-danger">*</span></label>
            <select name="data_relationship" class="form-select">
                <option value="">اختر صلة القرابة</option>
                @foreach ($category_of_relationship as $category)
                    <option value="{{ $category->id }}">{{ $category->attribute }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">تاريخ الميلاد<span class="text-danger">*</span></label>
            <input type="date" name="data_birth_date" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">الجنس <span class="text-danger">*</span></label>
            <select name="data_gender" class="form-select">
                <option value="">اختر الجنس</option>
                <option value="1">ذكر</option>
                <option value="2">أنثى</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">رقم الهاتف<span class="text-danger">*</span></label>
            <input type="number" name="data_phone_number" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">رقم هاتف بديل <span class="text-primary">(اختياري)</span></label>
            <input type="number" name="data_alt_phone_number" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">عدد افراد الاسرة <span class="text-primary">(اختياري)</span></label>
            <input type="number" name="data_number_of_individuals" class="form-control" min="0">
        </div>
        <div class="col-md-4">
            <label class="form-label">الحالة الاجتماعية<span class="text-danger">*</span></label>
            <select name="data_marital_status" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($marital_status as $marital)
                    <option value="{{ $marital->id }}">{{ $marital->description }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">المؤهل العلمي <span class="text-primary">(اختياري)</span></label>
            <select name="data_academic_qualification" class="form-select">
                <option value="">اختر المؤهل</option>
                @foreach ($academic_qualification as $qualification)
                    <option value="{{ $qualification->id }}">
                        {{ $qualification->description }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">حالة النزوح <span class="text-danger">*</span></label>
            <select name="data_displacement_status" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($displacement_status as $displacement_status_item)
                    <option value="{{ $displacement_status_item->id }}">
                        {{ $displacement_status_item->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">العنوان قبل النزوح <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="data_address_before_displacement" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">العنوان الحالي <span class="text-danger">*</span></label>
                    <input type="text" name="data_current_address" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">المدينة <span class="text-danger">*</span></label>
                    <select name="data_city" class="form-select">
                        <option value="">اختر المدينة</option>
                        @foreach ($city as $city_item)
                            <option value="{{ $city_item->id }}">{{ $city_item->city }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المحافظة <span class="text-danger">*</span></label>
                    <select name="data_province" class="form-select">
                        <option value="">اختر المحافظة</option>
                        @foreach ($province as $province_item)
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
                    <label class="form-label">الحالة الصحية <span class="text-danger">*</span></label>
                    <select name="data_health_status" class="form-select">
                        <option value="">اختر الحالة</option>
                        @foreach ($health_status as $health_status_item)
                            <option value="{{ $health_status_item->id }}">
                                {{ $health_status_item->description }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">وصف الإحتياجات <span class="text-primary">(اختياري)</span></label>
                    <textarea name="data_description_needs" class="form-control"></textarea>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">عدد الذكور <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_mail" class="form-control" min="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">عدد الإناث <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_female" class="form-control" min="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">عدد الأفراد المصابين بأمراض مزمنة <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_of_individuals_with_chronic_diseases"
                        class="form-control" min="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">عدد ذوي الاحتياجات الخاصة <span class="text-primary">(اختياري)</span></label>
                    <input type="number" name="data_number_of_people_with_special_needs" class="form-control"
                        min="0">
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">الحالة الوظيفية المعيل <span class="text-danger">*</span></label>
            <select name="data_employment_status_breadwinner" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($employment_status_breadwinner as $employment_status_item)
                    <option value="{{ $employment_status_item->id }}">
                        {{ $employment_status_item->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">حالة السكن <span class="text-danger">*</span></label>
            <select name="data_housing_status" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($HousingStatus as $HousingStatusItem)
                    <option value="{{ $HousingStatusItem->id }}">
                        {{ $HousingStatusItem->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">نوع السكن الحالي <span class="text-danger">*</span></label>
            <select name="data_current_housing_type" class="form-select">
                <option value="">اختر الحالة</option>
                @foreach ($TypeOfAccommodation as $TypeOfAccommodationItem)
                    <option value="{{ $TypeOfAccommodationItem->id }}">
                        {{ $TypeOfAccommodationItem->description }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mt-3">
            <!-- ملاحظة و زر إظهار الحقول البنكية -->
            <div class="alert alert-info py-2 mb-2 d-flex align-items-center justify-content-between" style="font-size: 0.97rem;">
                <span>إذا كنت تمتلك حساب بنك قم بإضافة المعلومات المطلوبة</span>
                <button type="button" class="btn btn-outline-primary btn-sm ms-2" id="showBankFieldsBtn">إظهار الحقول البنكية</button>
            </div>
            <!-- الحقول البنكية المخفية -->
            <div id="bankFields" class="row g-3 d-none">
                <div class="col-md-12">
                    <label class="form-label">اسم صاحب حساب البنك <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_account_holder_name" class="form-control" maxlength="100">
                </div>
                <div class="col-md-12">
                    <label class="form-label">رقم الجوال المربوط بحساب البنك <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_phone_number" class="form-control" maxlength="20">
                </div>
                <div class="col-md-12">
                    <label class="form-label">رقم حساب البنك الدولاري (IBAN) <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_account_usd" class="form-control" maxlength="34">
                </div>
                <div class="col-md-12">
                    <label class="form-label">رقم حساب البنك الشيكل (IBAN) <span class="text-primary">(اختياري)</span></label>
                    <input type="text" name="bank_account_ils" class="form-control" maxlength="34">
                </div>
                <div class="col-md-12">
                    <label class="form-label">اسم البنك <span class="text-primary">(اختياري)</span></label>
                    <select name="bank_name_id" class="form-select">
                        <option value="">اختر البنك</option>
                        @foreach ($bank_name as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->description }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
              <!-- ملاحظة توضيحية لرفع الملفات -->
            <div class="alert alert-primary py-2 mb-2 mt-3" style="font-size: 0.97rem;">
                يرجى اختيار نوع الوثيقة أولاً، وسوف يتم تحويلك لرفع الصورة
                المطلوبة.
            </div>
            <label class="form-label fw-bold"> رفع الملفات <span class="text-danger">*</span></label>
            <select class="form-select" id="mainDocumentTypeSelect">
                <option value="">اختر نوع الوثيقة</option>
                @foreach ($documentTypes as $documentType)
                    <option value="{{ $documentType->pref }}">{{ $documentType->description }}</option>
                @endforeach
            </select>
            <input type="file" id="mainDocumentFileInput" accept="image/*,.pdf" style="display:none;">
            <div id="mainDocumentPreview" class="mt-2"></div>
            <div id="mainDocumentNames" class="mt-2"></div>
        </div>

    </div>
</div>

@include('user.generalRegistration.javascript.baseTapJavascript')

<!-- SweetAlert2 CDN -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nextBtn = document.getElementById('goToNextTabBtn');
        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                // جميع الحقول المطلوبة (input/select/textarea) التي عليها نجمة
                const requiredFields = [{
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
                const sectionSelect = document.querySelector('select[name="data_section_id"]');
                const selectedValue = sectionSelect ? sectionSelect.value : '';
                const orphansSectionId = '1';
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
    });
</script>
<div class="mt-4 text-end">
    <button type="button" class="btn btn-success px-5 py-2 fs-5" id="goToNextTabBtn">
        التالي <i class="fas fa-arrow-left ms-2"></i>
    </button>
</div>
<div id="didding" style="padding-bottom: 80px;"></div>
