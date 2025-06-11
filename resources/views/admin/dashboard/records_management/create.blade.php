@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="container-fluid py-4" style="max-width:100vw;">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card shadow-lg border-0">
                    <div
                        class="card-header bg-gradient-primary text-dark fw-bold fs-4 text-center rounded-top p-4 d-flex align-items-center justify-content-between flex-wrap">
                        <span style="margin-top: 1.5rem; display: inline-block;">
                            <i class="fas fa-user-plus me-2"></i>
                            إضافة سجل جديد
                        </span>
                        <div class="d-flex align-items-center" style="margin-top: 1.5rem;">
                            <label class="form-label mb-0 me-2 fs-5" style="color: #222;">رقم الملف:</label>
                            <input type="text" name="file_id_number"
                                class="form-control bg-secondary bg-opacity-25 border-0 text-center fs-3 fw-bold"
                                style="width: 180px; height: 55px; box-shadow: none;" value="{{ $file_id_number ?? '' }}"
                                readonly>
                        </div>
                    </div>

                    <!-- Add Tab Navigation -->
                    <ul class="nav nav-tabs nav-fill mb-4" id="formTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-3" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic"
                                type="button" role="tab" aria-controls="basic" aria-selected="true">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-user fa-2x mb-2"></i>
                                    <span class="fs-4 fw-bold">البيانات الأساسية</span>
                                </div>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="family-members-tab" data-bs-toggle="tab"
                                data-bs-target="#family-members" type="button" role="tab"
                                aria-controls="family-members" aria-selected="false">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <span class="fs-4 fw-bold">أفراد الأسرة</span>
                                </div>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="deceased-tab" data-bs-toggle="tab" data-bs-target="#deceased"
                                type="button" role="tab" aria-controls="deceased" aria-selected="false">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-user-times fa-2x mb-2"></i>
                                    <span class="fs-4 fw-bold">الأفراد المتوفين</span>
                                </div>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="attachments-tab" data-bs-toggle="tab"
                                data-bs-target="#attachments" type="button" role="tab" aria-controls="attachments"
                                aria-selected="false">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-paperclip fa-2x mb-2"></i>
                                    <span class="fs-4 fw-bold">المرفقات</span>
                                </div>
                            </button>
                        </li>
                    </ul>

                    <div class="card-body bg-light">
                        <form action="{{ route('admin.records.management.store') }}" method="POST"
                            enctype="multipart/form-data" autocomplete="off">
                            @csrf
                            <div class="tab-content" id="formTabsContent">
                                <!-- Basic Info Tab -->
                                <div class="tab-pane fade show active" id="basic" role="tabpanel"
                                    aria-labelledby="basic-tab">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">القسم <span class="text-danger">*</span></label>
                                            <select name="data_section_id" class="form-select">
                                                <option value="">اختر القسم</option>
                                                @foreach ($generalSection as $section)
                                                    <option value="{{ $section->id }}">{{ $section->description }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                                            <input type="text" name="data_id_number" id="data_id_number"
                                                class="form-control" required
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                maxlength="10"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                        </div>
                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label">الاسم الأول <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="data_first_name" class="form-control"
                                                        required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">اسم الأب <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="data_father_name" class="form-control">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">اسم الجد<span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="data_grand_father_name"
                                                        class="form-control">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">اسم العائلة<span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="data_family_name" class="form-control">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">صلة القرابة <span
                                                    class="text-danger">*</span></label>
                                            <select name="data_relationship" class="form-select">
                                                <option value="">اختر صلة القرابة</option>
                                                @foreach ($category_of_relationship as $category)
                                                    <option value="{{ $category->id }}">{{ $category->attribute }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">تاريخ الميلاد<span
                                                    class="text-danger">*</span></label>
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
                                            <input type="tel" name="data_phone_number" class="form-control">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">رقم هاتف بديل</label>
                                            <input type="tel" name="data_alt_phone_number" class="form-control">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">عدد افراد الاسرة</label>
                                            <input type="number" name="data_number_of_individuals" class="form-control"
                                                min="0">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">الحالة الاجتماعية<span
                                                    class="text-danger">*</span></label>
                                            <select name="data_marital_status" class="form-select">
                                                <option value="">اختر الحالة</option>
                                                @foreach ($marital_status as $marital)
                                                    <option value="{{ $marital->id }}">{{ $marital->description }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">المؤهل العلمي </label>
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
                                            <label class="form-label">حالة النزوح <span
                                                    class="text-danger">*</span></label>
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
                                                    <label class="form-label">العنوان قبل النزوح</label>
                                                    <input type="text" name="data_address_before_displacement"
                                                        class="form-control">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">العنوان الحالي <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="data_current_address"
                                                        class="form-control">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">المدينة <span
                                                            class="text-danger">*</span></label>
                                                    <select name="data_city" class="form-select">
                                                        <option value="">اختر المدينة</option>
                                                        @foreach ($city as $city_item)
                                                            <option value="{{ $city_item->id }}">{{ $city_item->city }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">المحافظة <span
                                                            class="text-danger">*</span></label>
                                                    <select name="province" class="form-select">
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
                                                    <label class="form-label">الحالة الصحية <span
                                                            class="text-danger">*</span></label>
                                                    <select name="data_health_status" class="form-select">
                                                        <option value="">اختر الحالة</option>
                                                        @foreach ($health_status as $health_status_item)
                                                            <option value="{{ $health_status_item->id }}">
                                                                {{ $health_status_item->description }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label">وصف الحالة الصحية</label>
                                                    <textarea name="data_description_health_status" class="form-control"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label">عدد الذكور</label>
                                                    <input type="number" name="data_number_mail" class="form-control"
                                                        min="0">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">عدد الإناث</label>
                                                    <input type="number" name="data_number_female" class="form-control"
                                                        min="0">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">عدد الأفراد المصابين بأمراض مزمنة</label>
                                                    <input type="number"
                                                        name="data_number_of_individuals_with_chronic_diseases"
                                                        class="form-control" min="0">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">عدد ذوي الاحتياجات الخاصة</label>
                                                    <input type="number" name="data_number_of_people_with_special_needs"
                                                        class="form-control" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">الحالة الوظيفية  المعيل <span
                                                    class="text-danger">*</span></label>
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
                                            <label class="form-label">نوع السكن الحالي <span
                                                    class="text-danger">*</span></label>
                                            <select name="data_current_housing_type" class="form-select">
                                                <option value="">اختر الحالة</option>
                                                @foreach ($TypeOfAccommodation as $TypeOfAccommodationItem)
                                                    <option value="{{ $TypeOfAccommodationItem->id }}">
                                                        {{ $TypeOfAccommodationItem->description }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">المستخدم المدخل للبيانات</label>
                                            <input type="text" name="data_user_insert_data"
                                                class="form-control bg-secondary bg-opacity-25"
                                                value="{{ auth()->user()->name }}" readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- Family Members Tab -->
                                <div class="tab-pane fade" id="family-members" role="tabpanel"
                                    aria-labelledby="family-members-tab">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h5 class="card-title mb-0">بيانات أفراد الأسرة</h5>
                                                        <button type="button" class="btn btn-primary"
                                                            id="addFamilyMember">
                                                            <i class="fas fa-plus me-2"></i>إضافة فرد
                                                        </button>
                                                    </div>
                                                    <div id="familyMembersContainer">
                                                        <!-- نموذج إضافة فرد -->
                                                        <div class="family-member-form border rounded p-3 mb-3">
                                                            <div class="row g-3">
                                                                <input type="hidden" name="family_members[0][file_id]"
                                                                    value="{{ $file_id_number ?? '' }}">
                                                                <div class="col-md-6">
                                                                    <label class="form-label">رقم التسجيل <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text" name="family_members[0][registration_id]"
                                                                        class="form-control bg-secondary bg-opacity-10" readonly
                                                                        value="{{ $file_id_number ?? '' }}">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">حالة الكفالة</label>
                                                                    <select name="family_members[0][sponsorship_status]"
                                                                        class="form-select">
                                                                        <option value="">اختر الحالة</option>
                                                                        @foreach ($sponsorship_status as $status)
                                                                            <option value="{{ $status->id }}">
                                                                                {{ $status->description }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">الاسم الأول <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text"
                                                                        name="family_members[0][first_name]"
                                                                        class="form-control" required>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">الاسم الثاني</label>
                                                                    <input type="text"
                                                                        name="family_members[0][second_name]"
                                                                        class="form-control">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">الاسم الثالث</label>
                                                                    <input type="text"
                                                                        name="family_members[0][third_name]"
                                                                        class="form-control">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">اسم العائلة <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text"
                                                                        name="family_members[0][last_name]"
                                                                        class="form-control" required>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">رقم هوية اليتيم</label>
                                                                    <input type="text" name="family_members[0][orphan_id]"
                                                                        class="form-control"
                                                                        inputmode="numeric"
                                                                        pattern="[0-9]*"
                                                                        maxlength="10"
                                                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">تاريخ الميلاد <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="date"
                                                                        name="family_members[0][orphan_birth_date]"
                                                                        class="form-control" required>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">العمر</label>
                                                                    <input type="number"
                                                                        name="family_members[0][orphan_age]"
                                                                        class="form-control" readonly>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">الجنس <span
                                                                            class="text-danger">*</span></label>
                                                                    <select name="family_members[0][orphan_gender]"
                                                                        class="form-select" required>
                                                                        <option value="">اختر الجنس</option>
                                                                        <option value="1">ذكر</option>
                                                                        <option value="2">أنثى</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">الحالة الصحية</label>
                                                                    <select name="family_members[0][orphan_health_status]"
                                                                        class="form-select">
                                                                        <option value="">اختر الحالة</option>
                                                                        @foreach ($health_status as $status)
                                                                            <option value="{{ $status->id }}">
                                                                                {{ $status->description }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">نوع الكفالة</label>
                                                                    <select
                                                                        name="family_members[0][orphan_type_of_guarantee]"
                                                                        class="form-select">
                                                                        <option value="">اختر النوع</option>
                                                                        @foreach ($guarantee_types as $type)
                                                                            <option value="{{ $type->id }}">
                                                                                {{ $type->description }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Deceased Tab -->
                                <div class="tab-pane fade" id="deceased" role="tabpanel"
                                    aria-labelledby="deceased-tab">
                                    <div class="row g-3">
                                        <!-- Father Information -->
                                        <div class="col-12">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-gradient-primary text-dark py-3">
                                                    <h5 class="card-title mb-0 d-flex align-items-center">
                                                        <i class="fas fa-male fs-4 me-2"></i>
                                                        بيانات الأب المتوفى
                                                    </h5>
                                                </div>
                                                <div class="card-body bg-light">
                                                    <div class="row g-3">
                                                        <div class="col-md-3">
                                                            <label class="form-label">الاسم الأول <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="father_first_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">الاسم الثاني</label>
                                                            <input type="text" name="father_second_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">الاسم الثالث</label>
                                                            <input type="text" name="father_third_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">اسم العائلة <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="father_last_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">رقم الهوية <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="father_id" class="form-control"
                                                                inputmode="numeric"
                                                                pattern="[0-9]*"
                                                                maxlength="10"
                                                                oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">تاريخ الوفاة <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="date" name="father_death_date"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">سبب الوفاة <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="father_death_reason"
                                                                class="form-control">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mother Information -->
                                        <div class="col-12">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-gradient-primary text-dark py-3">
                                                    <h5 class="card-title mb-0 d-flex align-items-center">
                                                        <i class="fas fa-female fs-4 me-2"></i>
                                                        بيانات الأم المتوفية
                                                    </h5>
                                                </div>
                                                <div class="card-body bg-light">
                                                    <div class="row g-3">
                                                        <div class="col-md-3">
                                                            <label class="form-label">الاسم الأول <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="mother_first_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">الاسم الثاني</label>
                                                            <input type="text" name="mother_second_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">الاسم الثالث</label>
                                                            <input type="text" name="mother_third_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">اسم العائلة <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="mother_last_name"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">رقم الهوية <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="mother_id" class="form-control"
                                                                inputmode="numeric"
                                                                pattern="[0-9]*"
                                                                maxlength="10"
                                                                oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">تاريخ الوفاة <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="date" name="mother_death_date"
                                                                class="form-control">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">سبب الوفاة <span
                                                                    class="text-danger">*</span></label>
                                                            <input type="text" name="mother_death_reason"
                                                                class="form-control">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attachments Tab -->
                                <div class="tab-pane fade" id="attachments" role="tabpanel"
                                    aria-labelledby="attachments-tab">
                                    <div class="row g-3">
                                        <div class="col-12 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">اختر الشخص <span
                                                                    class="text-danger">*</span></label>
                                                            <select id="person_selector" class="form-select" required>
                                                                <option value="">اختر الشخص</option>
                                                                <optgroup label="صاحب الملف">
                                                                    <option value="main"
                                                                        data-id="{{ $file_id_number }}">
                                                                        <span class="main-person"></span>
                                                                    </option>
                                                                </optgroup>
                                                                <optgroup label="أفراد الأسرة"
                                                                    id="family_members_options">
                                                                    <!-- سيتم إضافة الخيارات ديناميكياً -->
                                                                </optgroup>
                                                                <optgroup label="الأفراد المتوفين">
                                                                    <option value="deceased_father">الأب المتوفى</option>
                                                                    <option value="deceased_mother">الأم المتوفية</option>
                                                                </optgroup>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">نوع الوثيقة <span
                                                                    class="text-danger">*</span></label>
                                                            <select name="document_type" id="document_type"
                                                                class="form-select" required>
                                                                <option value="">اختر نوع الوثيقة</option>
                                                                @foreach ($documentTypes as $documentType)
                                                                    <option value="{{ $documentType->pref }}">
                                                                        {{ $documentType->description }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">رقم الوثيقة</label>
                                                            <input type="text" id="document_id"
                                                                class="form-control bg-secondary bg-opacity-25"
                                                                value="{{ $file_id_number ?? '' }}" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="text-center mt-3">
                                                        <div class="file-upload-wrapper">
                                                            <input type="file" name="document_file" id="document_file"
                                                                class="form-control" accept="image/*">
                                                            <div id="preview" class="mt-3 d-none">
                                                                <img src="" alt="معاينة" class="img-fluid mb-2"
                                                                    style="max-height: 200px;">
                                                                <div class="mt-2">
                                                                    <button type="button" id="confirmUpload"
                                                                        class="btn btn-success">
                                                                        <i class="fas fa-check me-2"></i>تأكيد الرفع
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-3">الوثائق المرفقة</h5>
                                                    <div id="documents_container">
                                                        <!-- قسم وثائق صاحب الملف -->
                                                        <div class="card mb-4">
                                                            <div class="card-body">
                                                                <h5 class="card-title border-bottom pb-2">وثائق صاحب الملف
                                                                </h5>
                                                                <div id="main_person_docs"
                                                                    class="d-flex flex-nowrap overflow-auto gap-3 py-2">
                                                                    <!-- وثائق صاحب الملف ستظهر هنا -->
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- قسم وثائق أفراد الأسرة -->
                                                        <div class="card mb-5">
                                                            <div class="card-body">
                                                                <h5 class="card-title border-bottom pb-2">وثائق أفراد
                                                                    الأسرة</h5>
                                                                <div id="family_members_docs">
                                                                    <!-- سيتم إضافة أقسام الوثائق ديناميكياً لكل فرد -->
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- قسم وثائق المتوفين -->
                                                        <div class="card mb-4">
                                                            <div class="card-body">
                                                                <h5 class="card-title border-bottom pb-2 mb-4">
                                                                    <i class="fas fa-user-times me-2"></i>وثائق الأفراد
                                                                    المتوفين
                                                                </h5>
                                                                <div class="row g-4">
                                                                    <div class="col-md-6">
                                                                        <div class="deceased-docs-section">
                                                                            <h6 class="text-primary mb-3">وثائق الأب
                                                                                المتوفى</h6>
                                                                            <div id="father_docs"
                                                                                class="documents-flex-container">
                                                                                <!-- وثائق الأب المتوفى ستظهر هنا -->
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="deceased-docs-section">
                                                                            <h6 class="text-primary mb-3">وثائق الأم
                                                                                المتوفية</h6>
                                                                            <div id="mother_docs"
                                                                                class="documents-flex-container">
                                                                                <!-- وثائق الأم المتوفية ستظهر هنا -->
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scriptsCode')
        <script>
            // Enable Bootstrap tabs
            const triggerTabList = [].slice.call(document.querySelectorAll('#formTabs button'));
            triggerTabList.forEach(function(triggerEl) {
                new bootstrap.Tab(triggerEl);
            });
        </script>
    @endpush

    @push('styles')
        <style>
            .nav-tabs .nav-link {
                border: none;
                color: #666;
                transition: all 0.3s;
                position: relative;
            }

            .nav-tabs .nav-link:hover {
                background-color: rgba(0, 0, 0, 0.05);
            }

            .nav-tabs .nav-link.active {
                color: #0d6efd;
                background-color: #fff;
                border-bottom: 3px solid #0d6efd;
            }

            .nav-tabs .nav-link i {
                transition: all 0.3s;
            }

            .nav-tabs .nav-link:hover i,
            .nav-tabs .nav-link.active i {
                transform: scale(1.2);
            }

            .preview-item .card {
                height: 100%;
            }

            .preview-item .card-img-top {
                height: 200px;
                object-fit: cover;
            }

            #loading {
                padding: 2rem;
            }

            .uploaded-document {
                transition: all 0.3s ease;
            }

            .uploaded-document:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }

            .family-member-form {
                background-color: #f8f9fa;
                transition: all 0.3s ease;
            }

            .family-member-form:hover {
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            }

            .card-title {
                color: #0d6efd;
                font-weight: bold;
            }

            .person-documents {
                background-color: #f8f9fa;
                border-radius: 0.5rem;
                padding: 1rem;
            }

            .person-documents:not(:last-child) {
                border-bottom: 1px solid #dee2e6;
            }

            .family-member-documents .card {
                height: 100%;
                min-height: 300px;
            }

            .family-member-documents .card-body {
                display: flex;
                flex-direction: column;
            }

            .family-member-documents .card img {
                max-height: 150px;
                object-fit: contain;
                margin-bottom: 1rem;
            }

            #family_members_docs .d-flex {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                gap: 1rem !important;
                padding: 0.5rem 0 !important;
            }

            #family_members_docs .card {
                min-width: 250px !important;
                flex: 0 0 auto !important;
            }

            /* تنسيق شريط التمرير */
            #family_members_docs .d-flex::-webkit-scrollbar {
                height: 8px;
            }

            #family_members_docs .d-flex::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            #family_members_docs .d-flex::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            #family_members_docs .d-flex::-webkit-scrollbar-thumb:hover {
                background: #555;
            }

            /* تنسيق حاويات الوثائق */
            .documents-flex-container {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                gap: 1rem !important;
                padding: 0.5rem 0 !important;
                scroll-behavior: smooth;
            }

            .document-card {
                min-width: 250px !important;
                flex: 0 0 auto !important;
                margin-bottom: 0 !important;
            }

            /* تحسينات على بطاقات الوثائق */
            .document-card {
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
                background: #fff;
            }

            .document-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
            }

            .document-card .card-header {
                background: rgba(13, 110, 253, 0.1);
                border-bottom: 1px solid rgba(13, 110, 253, 0.2);
                padding: 0.75rem 1.25rem;
            }

            .document-card .card-header h6 {
                font-size: 1rem;
                color: #0d6efd;
                margin: 0;
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .document-card .btn-danger {
                margin-right: 0.5rem;
                padding: 0.375rem 0.75rem;
                border-radius: 6px;
            }

            .document-card .card-header .btn-danger:hover {
                background-color: #dc3545;
                transform: scale(1.05);
                box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
            }

            /* تنظيم المحتوى داخل البطاقة */
            .document-card .card-body {
                padding: 1.25rem;
            }

            .document-card .card-body img {
                padding: 0.5rem;
                border: 1px solid rgba(13, 110, 253, 0.1);
                border-radius: 8px;
                background: white;
            }

            .document-card .text-center {
                margin-top: 1rem;
            }

            .document-card .text-muted {
                color: #6c757d !important;
                margin-bottom: 0.5rem;
            }

            /* تحسين المظهر العام للبطاقة */
            .document-card {
                border: none;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                border-radius: 10px;
                overflow: hidden;
                margin: 0.5rem;
            }

            /* تنسيق زر اختيار الملف */
            #document_file {
                border: 2px dashed #0d6efd;
                border-radius: 10px;
                padding: 2rem;
                background: rgba(13, 110, 253, 0.05);
                cursor: pointer;
                transition: all 0.3s ease;
            }

            #document_file:hover {
                background: rgba(13, 110, 253, 0.1);
                border-color: #0a58ca;
            }

            .upload-btn {
                border: 2px solid #0d6efd;
                font-weight: bold;
                padding: 1rem;
                transition: all 0.3s ease;
            }

            .upload-btn:hover {
                background: #0d6efd;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
            }

            .upload-btn i {
                font-size: 1.2rem;
            }

            .file-upload-wrapper {
                position: relative;
                padding: 1rem;
                background: #f8f9fa;
                border-radius: 10px;
            }

            #preview {
                background: white;
                padding: 1rem;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            #preview img {
                max-width: 100%;
                height: auto;
                border-radius: 4px;
            }

            #confirmUpload {
                padding: 0.5rem 2rem;
                font-weight: bold;
            }

            /* تنسيقات خاصة بقسم المتوفين */
            #deceased .card-title {
                position: relative;
                padding-bottom: 0.5rem;
            }

            #deceased .card-title i {
                font-size: 1.25rem;
            }

            #deceased .border {
                border-color: rgba(13, 110, 253, 0.2) !important;
                transition: all 0.3s ease;
            }

            #deceased .border:hover {
                border-color: rgba(13, 110, 253, 0.4) !important;
                box-shadow: 0 0 15px rgba(13, 110, 253, 0.1);
            }

            /* تنسيقات قسم وثائق المتوفين */
            .deceased-docs-section {
                background: rgba(13, 110, 253, 0.03);
                border-radius: 12px;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
                box-shadow: 0 0 15px rgba(13, 110, 253, 0.05);
            }

            .deceased-docs-section h6 {
                border-bottom: 2px solid rgba(13, 110, 253, 0.2);
                padding-bottom: 0.75rem;
                margin-bottom: 1.5rem;
            }

            #father_docs,
            #mother_docs {
                min-height: 120px;
                border: 1px dashed rgba(13, 110, 253, 0.3);
                border-radius: 8px;
                padding: 1rem;
                margin-top: 1rem;
            }

            /* تحسينات على بطاقات المعلومات */
            .deceased-person-card {
                border: none;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
            }

            .deceased-person-card:hover {
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
                transform: translateY(-5px);
            }

            .deceased-person-card .card-header {
                border-bottom: 2px solid rgba(13, 110, 253, 0.1);
            }

            .deceased-person-card .card-title {
                color: #2c3e50;
                font-size: 1.25rem;
            }

            .deceased-person-card .card-title i {
                color: #0d6efd;
            }

            .deceased-person-card hr {
                opacity: 0.1;
            }

            .deceased-person-card .form-control {
                border-color: rgba(0, 0, 0, 0.1);
            }

            .deceased-person-card .form-control:focus {
                border-color: #0d6efd;
                box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            }

            .deceased-person-card textarea {
                resize: none;
            }

            .family-member-form {
                transition: all 0.3s ease;
            }

            .family-member-form .card-header {
                position: relative;
            }

            .family-member-form .delete-member {
                padding: 0.25rem 0.5rem;
                transition: all 0.3s ease;
                background-color: #dc3545;
                border-color: #dc3545;
                color: white;
            }

            .family-member-form .delete-member:hover {
                background-color: #bb2d3b;
                border-color: #b02a37;
                transform: scale(1.1);
            }

            .family-member-form .card-body {
                padding: 1.5rem;
            }

            .family-member-form .form-control:focus,
            .family-member-form .form-select:focus {
                border-color: rgba(13, 110, 253, 0.4);
                box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            }
        </style>
    @endpush

    @push('scriptsCode')
        <script>
            // التعريفات الأساسية
            const fileInput = document.getElementById('document_file');
            const preview = document.getElementById('preview');
            const previewImg = preview.querySelector('img');
            const confirmBtn = document.getElementById('confirmUpload');
            const personSelector = document.getElementById('person_selector');
            const docTypeSelect = document.getElementById('document_type');
            const docs = new Map();

            // تحديث معالجة اختيار الملف
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) {
                    preview.classList.add('d-none');
                    return;
                }

                if (!docTypeSelect.value || !personSelector.value) {
                    alert('الرجاء اختيار نوع الوثيقة والشخص أولاً');
                    fileInput.value = '';
                    return;
                }

                // عرض معاينة الصورة
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        previewImg.src = event.target.result;
                        preview.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewImg.src = '/path/to/default/document/icon.png'; // استبدل بمسار أيقونة المستند الافتراضية
                    preview.classList.remove('d-none');
                }
            });

            // تحديث معالجة تأكيد الرفع
            confirmBtn.addEventListener('click', function() {
                const file = fileInput.files[0];
                if (!file) {
                    alert('الرجاء اختيار ملف');
                    return;
                }

                const selectedPerson = personSelector.value;
                if (!selectedPerson || !docTypeSelect.value) {
                    alert('الرجاء اختيار الشخص ونوع الوثيقة');
                    return;
                }

                // الحصول على رقم الهوية
                let idNumber;
                if (selectedPerson === 'main') {
                    idNumber = document.querySelector('input[name="data_id_number"]').value;
                } else if (selectedPerson === 'deceased_father') {
                    idNumber = document.querySelector('input[name="father_id"]').value || 'father';
                } else if (selectedPerson === 'deceased_mother') {
                    idNumber = document.querySelector('input[name="mother_id"]').value || 'mother';
                } else {
                    const familyIndex = selectedPerson.split('_')[1];
                    idNumber = document.querySelector(`input[name="family_members[${familyIndex}][orphan_id]"]`).value;
                }

                // تحديث التحقق من رقم الهوية
                if (!idNumber && !['deceased_father', 'deceased_mother'].includes(selectedPerson)) {
                    alert('الرجاء إدخال رقم الهوية للشخص المحدد أولاً');
                    return;
                }

                const docType = docTypeSelect.value;
                const docTypeName = docTypeSelect.options[docTypeSelect.selectedIndex].text;
                const fileId = document.getElementById('document_id').value;
                const personName = personSelector.options[personSelector.selectedIndex].text;
                const docId = `doc_${Date.now()}`; // إنشاء معرف فريد للوثيقة

                // تحديد اسم الملف الجديد
                const fileExtension = file.name.split('.').pop().toLowerCase();
                const newFileName = `${docType}_${fileId}_${idNumber}.${fileExtension}`;

                // تحديث HTML الوثيقة
                const docHTML = `
    <div class="document-card card h-100" id="${docId}">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <h6 class="mb-0 fw-bold text-primary">${docTypeName}</h6>
            <button type="button" class="btn btn-danger" onclick="removeDocument('${docId}')">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="card-body d-flex flex-column align-items-center">
            <div class="mb-3 w-100">
                <img src="${previewImg.src}" class="img-fluid w-100" style="max-height: 150px; object-fit: contain;">
            </div>
            <div class="text-center w-100">
                <p class="small text-muted mb-1">
                    <i class="fas fa-file-alt me-1"></i> ${docTypeName}
                </p>
                <p class="small text-muted">
                    <i class="fas fa-file-signature me-1"></i> ${newFileName}
                </p>
            </div>
        </div>
    </div>
`;

                // إضافة الوثيقة للقسم المناسب
                if (selectedPerson === 'deceased_father') {
                    document.getElementById('father_docs').insertAdjacentHTML('beforeend', docHTML);
                } else if (selectedPerson === 'deceased_mother') {
                    document.getElementById('mother_docs').insertAdjacentHTML('beforeend', docHTML);
                } else if (selectedPerson === 'main') {
                    document.getElementById('main_person_docs').insertAdjacentHTML('beforeend', docHTML);
                } else {
                    let familyContainer = document.querySelector(`#family_member_${selectedPerson}`);
                    let docFlexContainer;

                    if (!familyContainer) {
                        const sectionHTML = `
                <div id="family_member_${selectedPerson}" class="documents-section">
                    <h6 class="text-primary border-bottom pb-2">${personName}</h6>
                    <div class="documents-flex-container" id="docs_container_${selectedPerson}">
                    </div>
                </div>
            `;
                        document.getElementById('family_members_docs').insertAdjacentHTML('beforeend', sectionHTML);
                    }

                    docFlexContainer = document.querySelector(`#docs_container_${selectedPerson}`);
                    if (!docFlexContainer) {
                        const containerHTML = `
                <div class="documents-flex-container" id="docs_container_${selectedPerson}"></div>
            `;
                        document.querySelector(`#family_member_${selectedPerson}`).insertAdjacentHTML('beforeend',
                            containerHTML);
                        docFlexContainer = document.querySelector(`#docs_container_${selectedPerson}`);
                    }

                    docFlexContainer.insertAdjacentHTML('beforeend', docHTML);
                }

                // تخزين معلومات الملف
                const modifiedFile = new File([file], newFileName, {
                    type: file.type,
                    lastModified: file.lastModified
                });

                docs.set(docId, {
                    file: modifiedFile,
                    type: docType,
                    name: newFileName,
                    personId: selectedPerson,
                    personName: personName
                });

                // إعادة تعيين النموذج
                fileInput.value = '';
                preview.classList.add('d-none');
                docTypeSelect.value = '';
                personSelector.value = '';
            });

            // تحديث دالة حذف الوثيقة
            function removeDocument(docId) {
                if (!confirm('هل أنت متأكد من حذف هذه الوثيقة؟')) return;

                const element = document.getElementById(docId);
                if (element) {
                    element.remove();
                    docs.delete(docId);
                }
            }

            // إضافة الوثائق للنموذج عند الإرسال
            document.querySelector('form').addEventListener('submit', function(e) {
                docs.forEach((doc, docId) => {
                    const fileField = document.createElement('input');
                    fileField.type = 'file';
                    fileField.name = `documents[${docId}]`;
                    fileField.style.display = 'none';

                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(doc.file);
                    fileField.files = dataTransfer.files;

                    this.appendChild(fileField);
                });
            });

            let familyMemberCount = 1;

            document.getElementById('addFamilyMember').addEventListener('click', function() {
                const template = document.querySelector('.family-member-form').cloneNode(true);
                const parentForm = document.querySelector('.family-member-form');

                // تحديث هيكل النموذج
                template.className = 'family-member-form card border-0 shadow-sm mb-4';
                template.innerHTML = `
        <div class="card-header bg-gradient-primary text-dark py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 d-flex align-items-center">
                <i class="fas fa-user fs-4 me-2"></i>
                بيانات فرد الأسرة
            </h5>
            <button type="button" class="btn btn-danger btn-sm delete-member">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body bg-light">
            <div class="row g-3">
                ${template.querySelector('.row').innerHTML}
            </div>
        </div>
    `;

                // تحديث الأسماء والقيم
                template.querySelectorAll('input, select').forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace('[0]', `[${familyMemberCount}]`);

                        // التعامل مع رقم التسجيل
                        if (input.name.includes('[registration_id]')) {
                            input.value = document.querySelector('input[name="file_id_number"]').value;
                            input.readOnly = true;
                            input.classList.add('bg-secondary', 'bg-opacity-10');
                        }
                        // التعامل مع رقم الهوية
                        else if (input.name.includes('[orphan_id]')) {
                            input.setAttribute('inputmode', 'numeric');
                            input.setAttribute('pattern', '[0-9]*');
                            input.setAttribute('maxlength', '10');
                            input.setAttribute('oninput', "this.value = this.value.replace(/[^0-9]/g, '')");
                            input.value = '';
                        }
                        // حفظ القيم المحددة للأسماء
                        else if (input.name.includes('[second_name]') ||
                            input.name.includes('[third_name]') ||
                            input.name.includes('[last_name]')) {
                            input.value = parentForm.querySelector(`[name="${input.name.replace(`[${familyMemberCount}]`, '[0]')}"]`).value;
                        } else {
                            // تفريغ باقي الحقول
                            if (input.type === 'text' || input.type === 'number' || input.type === 'date') {
                                input.value = '';
                            } else if (input.tagName === 'SELECT') {
                                input.selectedIndex = 0;
                            }
                        }
                    }
                });

                // إضافة مستمع حدث للحذف
                template.querySelector('.delete-member').onclick = function() {
                    Swal.fire({
                        title: 'هل أنت متأكد؟',
                        text: "سيتم حذف هذا الفرد من القائمة",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'نعم، احذف',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            template.style.opacity = '0';
                            template.style.transform = 'scale(0.9)';
                            setTimeout(() => {
                                template.remove();
                            }, 300);
                        }
                    });
                };

                document.getElementById('familyMembersContainer').appendChild(template);
                familyMemberCount++;
            });
        </script>
    @endpush

    @push('scriptsCode')
        <script>
            // حساب العمر تلقائياً عند تغيير تاريخ الميلاد
            document.addEventListener('input', function(e) {
                if (e.target.name.includes('[orphan_birth_date]')) {
                    calculateAge(e.target);
                }
            });

            // دالة حساب العمر
            function calculateAge(inputElement) {
                const birthDate = new Date(inputElement.value);
                if (isNaN(birthDate.getTime())) return; // تحقق من صحة التاريخ

                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();

                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }

                const formIndex = inputElement.name.match(/\[(\d+)\]/)[1];
                const ageInput = document.querySelector(`input[name="family_members[${formIndex}][orphan_age]"]`);
                if (ageInput) {
                    ageInput.value = age;
                }
            }
        </script>
    @endpush

    @push('scriptsCode')
        <script>
            // تحديث تعريف قائمة الأشخاص
            function updatePersonsList() {
                const mainPerson = document.querySelector('option[value="main"]');
                const firstName = document.querySelector('input[name="data_first_name"]').value;
                const familyName = document.querySelector('input[name="data_family_name"]').value;

                // تحديث خيار صاحب الملف
                if (firstName && familyName) {
                    mainPerson.textContent = `${firstName} ${familyName}`;
                } else {
                    mainPerson.textContent = 'صاحب الملف';
                }

                // تحديث قائمة أفراد الأسرة
                const familyMembersOptions = document.getElementById('family_members_options');
                familyMembersOptions.innerHTML = ''; // مسح الخيارات القديمة

                document.querySelectorAll('.family-member-form').forEach((form, index) => {
                    const firstName = form.querySelector('input[name*="[first_name]"]').value;
                    const lastName = form.querySelector('input[name*="[last_name]"]').value;

                    if (firstName && lastName) {
                        const option = document.createElement('option');
                        option.value = `family_${index}`;
                        option.textContent = `${firstName} ${lastName}`;
                        familyMembersOptions.appendChild(option);
                    }
                });
            }

            // إضافة مستمعي الأحداث لتحديث القائمة
            document.querySelector('input[name="data_first_name"]').addEventListener('input', updatePersonsList);
            document.querySelector('input[name="data_family_name"]').addEventListener('input', updatePersonsList);

            // مراقبة التغييرات في نماذج أفراد الأسرة
            const familyMembersContainer = document.getElementById('familyMembersContainer');
            familyMembersContainer.addEventListener('input', function(e) {
                if (e.target.name && (e.target.name.includes('[first_name]') || e.target.name.includes(
                        '[last_name]'))) {
                    updatePersonsList();
                }
            });

            // إضافة استدعاء التحديث بعد إضافة فرد جديد
            const originalAddFamilyMember = document.getElementById('addFamilyMember').onclick;
            document.getElementById('addFamilyMember').onclick = function() {
                if (originalAddFamilyMember) {
                    originalAddFamilyMember.apply(this, arguments);
                }
                setTimeout(updatePersonsList, 100); // تأخير صغير للتأكد من إضافة العناصر
            };

            // تحديث القائمة عند تحميل الصفحة
            document.addEventListener('DOMContentLoaded', updatePersonsList);
        </script>
    @endpush
@endsection
