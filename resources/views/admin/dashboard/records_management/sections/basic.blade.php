<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">القسم <span class="text-danger">*</span></label>
        <select name="data_section_id" class="form-select">
            <option value="">اختر القسم</option>
            @foreach ($generalSection as $section)
                <option value="{{ $section->id }}" {{ (isset($data) && $data->data_section_id == $section->id) ? 'selected' : '' }}>{{ $section->description }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="text" name="data_id_number" id="data_id_number" class="form-control" required inputmode="numeric" pattern="[0-9]*" maxlength="9" oninput="this.value = this.value.replace(/[^0-9]/g, '');" value="{{ $data->data_id_number ?? '' }}" placeholder="أدخل رقم الهوية للجلب التلقائي">
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">رقم الملف <span class="text-danger">*</span></label>
        <input type="text" name="file_id_number" id="file_id_number" class="form-control" required inputmode="numeric" pattern="[0-9]*" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '');" value="{{ isset($data->file_id_number) ? str_pad($data->file_id_number, 6, '0', STR_PAD_LEFT) : '' }}">
    </div>
    <div class="col-12">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                <input type="text" name="data_first_name" class="form-control" required value="{{ $data->data_first_name ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">اسم الأب <span class="text-danger">*</span></label>
                <input type="text" name="data_father_name" class="form-control" value="{{ $data->data_father_name ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">اسم الجد<span class="text-danger">*</span></label>
                <input type="text" name="data_grand_father_name" class="form-control" value="{{ $data->data_grand_father_name ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">اسم العائلة<span class="text-danger">*</span></label>
                <input type="text" name="data_family_name" class="form-control" value="{{ $data->data_family_name ?? '' }}">
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">صلة القرابة <span class="text-danger">*</span></label>
        <select name="data_relationship" class="form-select">
            <option value="">اختر صلة القرابة</option>
            @foreach ($category_of_relationship as $category)
                <option value="{{ $category->id }}" {{ (isset($data) && $data->data_relationship == $category->id) ? 'selected' : '' }}>{{ $category->attribute }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">تاريخ الميلاد<span class="text-danger">*</span></label>
        <input type="date" name="data_birth_date" class="form-control" value="{{ $data->data_birth_date ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">الجنس <span class="text-danger">*</span></label>
        <select name="data_gender" class="form-select">
            <option value="">اختر الجنس</option>
            <option value="1" {{ (isset($data) && $data->data_gender == 1) ? 'selected' : '' }}>ذكر</option>
            <option value="2" {{ (isset($data) && $data->data_gender == 2) ? 'selected' : '' }}>أنثى</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">رقم الهاتف<span class="text-danger">*</span></label>
        <input type="number" name="data_phone_number" class="form-control" value="{{ $data->data_phone_number ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">رقم هاتف بديل</label>
        <input type="number" name="data_alt_phone_number" class="form-control" value="{{ $data->data_alt_phone_number ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">عدد افراد الاسرة</label>
        <input type="number" name="data_number_of_individuals" class="form-control" min="0" value="{{ $data->data_number_of_individuals ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">الحالة الاجتماعية<span class="text-danger">*</span></label>
        <select name="data_marital_status" class="form-select">
            <option value="">اختر الحالة</option>
            @foreach ($ci_personal_cd as $status)
                <option value="{{ $status->id }}" {{ (isset($data) && $data->data_marital_status == $status->id) ? 'selected' : '' }}>{{ $status->CI_PERSONAL_CD }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">المؤهل العلمي </label>
        <select name="data_academic_qualification" class="form-select">
            <option value="">اختر المؤهل</option>
            @foreach ($academic_qualification as $qualification)
                <option value="{{ $qualification->id }}" {{ (isset($data) && $data->data_academic_qualification == $qualification->id) ? 'selected' : '' }}>{{ $qualification->description }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">حالة النزوح <span class="text-danger">*</span></label>
        <select name="data_displacement_status" class="form-select">
            <option value="">اختر الحالة</option>
            @foreach ($displacement_status as $displacement_status_item)
                <option value="{{ $displacement_status_item->id }}" {{ (isset($data) && $data->data_displacement_status == $displacement_status_item->id) ? 'selected' : '' }}>{{ $displacement_status_item->description }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">العنوان قبل النزوح</label>
                <input type="text" name="data_address_before_displacement" class="form-control" value="{{ $data->data_address_before_displacement ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">العنوان الحالي <span class="text-danger">*</span></label>
                <input type="text" name="data_current_address" class="form-control" value="{{ $data->data_current_address ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">المدينة <span class="text-danger">*</span></label>
                <select name="data_city" class="form-select">
                    <option value="">اختر المدينة</option>
                    @foreach ($city as $city_item)
                        <option value="{{ $city_item->id }}" {{ (isset($data) && $data->data_city == $city_item->id) ? 'selected' : '' }}>{{ $city_item->city }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">المحافظة <span class="text-danger">*</span></label>
                <select name="data_province" class="form-select" required>
                    <option value="">اختر المحافظة</option>
                    @foreach ($province as $province_item)
                        <option value="{{ $province_item->id }}" {{ (isset($data) && $data->data_province == $province_item->id) ? 'selected' : '' }}>{{ $province_item->description }}</option>
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
                        <option value="{{ $health_status_item->id }}" {{ (isset($data) && $data->data_health_status == $health_status_item->id) ? 'selected' : '' }}>{{ $health_status_item->description }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">وصف الإحتياجات</label>
                <textarea name="data_description_needs" class="form-control">{{ $data->data_description_needs ?? '' }}</textarea>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">عدد الذكور</label>
                <input type="number" name="data_number_mail" class="form-control" min="0" value="{{ $data->data_number_mail ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">عدد الإناث</label>
                <input type="number" name="data_number_female" class="form-control" min="0" value="{{ $data->data_number_female ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">عدد الأفراد المصابين بأمراض مزمنة</label>
                <input type="number" name="data_number_of_individuals_with_chronic_diseases" class="form-control" min="0" value="{{ $data->data_number_of_individuals_with_chronic_diseases ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">عدد ذوي الاحتياجات الخاصة</label>
                <input type="number" name="data_number_of_people_with_special_needs" class="form-control" min="0" value="{{ $data->data_number_of_people_with_special_needs ?? '' }}">
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">الحالة الوظيفية المعيل <span class="text-danger">*</span></label>
        <select name="data_employment_status_breadwinner" class="form-select">
            <option value="">اختر الحالة</option>
            @foreach ($employment_status_breadwinner as $employment_status_item)
                <option value="{{ $employment_status_item->id }}" {{ (isset($data) && $data->data_employment_status_breadwinner == $employment_status_item->id) ? 'selected' : '' }}>{{ $employment_status_item->description }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">حالة السكن <span class="text-danger">*</span></label>
        <select name="data_housing_status" class="form-select">
            <option value="">اختر الحالة</option>
            @foreach ($housing_status as $HousingStatusItem)
                <option value="{{ $HousingStatusItem->id }}" {{ (isset($data) && $data->data_housing_status == $HousingStatusItem->id) ? 'selected' : '' }}>{{ $HousingStatusItem->description }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">نوع السكن الحالي <span class="text-danger">*</span></label>
        <select name="data_current_housing_type" class="form-select">
            <option value="">اختر الحالة</option>
            @foreach ($TypeOfAccommodation as $TypeOfAccommodationItem)
                <option value="{{ $TypeOfAccommodationItem->id }}" {{ (isset($data) && $data->data_current_housing_type == $TypeOfAccommodationItem->id) ? 'selected' : '' }}>{{ $TypeOfAccommodationItem->description }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">المستخدم المدخل للبيانات</label>
        <input type="text" name="data_user_insert_data" class="form-control bg-secondary bg-opacity-25" value="{{ $data->data_user_insert_data ?? (auth()->user()->name ?? '') }}" readonly>
    </div>
</div>

{{-- 🏦 قسم الحسابات البنكية --}}
<div class="mt-5 mb-4">
    <h4 class="mb-3 text-primary"><i class="fas fa-university me-2"></i>المعلومات البنكية</h4>
    <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
        <span>يمكنك إضافة حسابات بنكية للمعيل (حد أقصى 10 حسابات). جميع الحقول اختيارية.</span>
        <button type="button" class="btn btn-primary btn-sm" id="addEditBankAccountBtn">
            <i class="fas fa-plus me-1"></i> إضافة حساب بنكي
        </button>
    </div>
    <div id="editBankAccountsContainer" class="d-none">
        {{-- سيتم إضافة النماذج هنا ديناميكيا --}}
    </div>
</div>

{{-- إزالة أي أزرار حفظ/إلغاء إضافية من هذا القسم، الأزرار الرئيسية موجودة في form_sections.blade.php فقط --}}
