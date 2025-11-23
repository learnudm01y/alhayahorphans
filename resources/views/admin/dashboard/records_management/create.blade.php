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
                            <input type="text"
                                class="form-control bg-secondary bg-opacity-25 border-0 text-center fs-3 fw-bold"
                                style="width: 180px; height: 55px; box-shadow: none;" value="{{ $file_id_number ?? '' }}"
                                readonly>
                        </div>
                    </div>

                    <!-- Add Tab Navigation (mobile-bottom-tabs style) -->
                    <ul class="nav nav-tabs nav-fill mb-4 mobile-bottom-tabs" id="formTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-3" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic"
                                type="button" role="tab" aria-controls="basic" aria-selected="true">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-user tab-icon mb-2"></i>
                                    <span class="fs-4 fw-bold tab-label">البيانات الأساسية</span>
                                </div>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="family-members-tab" data-bs-toggle="tab"
                                data-bs-target="#family-members" type="button" role="tab"
                                aria-controls="family-members" aria-selected="false">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-users tab-icon mb-2"></i>
                                    <span class="fs-4 fw-bold tab-label">أفراد الأسرة</span>
                                </div>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="deceased-tab" data-bs-toggle="tab" data-bs-target="#deceased"
                                type="button" role="tab" aria-controls="deceased" aria-selected="false">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-user-times tab-icon mb-2"></i>
                                    <span class="fs-4 fw-bold tab-label">الأفراد المتوفين</span>
                                </div>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="attachments-tab" data-bs-toggle="tab"
                                data-bs-target="#attachments" type="button" role="tab" aria-controls="attachments"
                                aria-selected="false">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-paperclip tab-icon mb-2"></i>
                                    <span class="fs-4 fw-bold tab-label">المرفقات</span>
                                </div>
                            </button>
                        </li>
                    </ul>
                    <style>
                    .tab-icon {
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
                        background: none !important;
                        color: #0d6efd !important;
                        border: none !important;
                        transform: none !important;
                        box-shadow: none !important;
                    }
                    @media (max-width: 576px) {
                        .mobile-bottom-tabs {
                            position: fixed;
                            bottom: 0;
                            left: 0;
                            right: 0;
                            z-index: 1050;
                            background: rgba(245,245,245,0.95);
                            box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
                            margin-bottom: 0 !important;
                            border-top: 1.5px solid #e5e7eb;
                            border-radius: 22px 22px 0 0;
                            padding: 0.2rem 0.5rem 0.3rem 0.5rem;
                            display: flex !important;
                            justify-content: space-between;
                            gap: 0 !important;
                        }
                        .mobile-bottom-tabs .nav-item {
                            flex: 1 1 0;
                            display: flex;
                            justify-content: center;
                            align-items: stretch;
                            position: relative;
                        }
                        .mobile-bottom-tabs .nav-link {
                            padding: 0.4rem 0 !important;
                            background: transparent !important;
                            border: none !important;
                            box-shadow: none !important;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            border-radius: 18px !important;
                            position: relative;
                            height: 100%;
                            min-width: 0;
                        }
                        .mobile-bottom-tabs .tab-label {
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
                        .mobile-bottom-tabs .nav-item:not(:last-child)::after {
                            content: "";
                            position: absolute;
                            top: 18%;
                            right: 0;
                            width: 1.5px;
                            height: 64%;
                            background: #e5e7eb;
                            border-radius: 2px;
                            opacity: 0.85;
                            z-index: 2;
                        }
                        body {
                            padding-bottom: 80px !important;
                        }
                    }
                    </style>
                    <div class="card-body bg-light">
                        <form action="{{ route('admin.records.management.store') }}" method="POST"
                            enctype="multipart/form-data" autocomplete="off" id="main_form">
                            @csrf
                            <input type="hidden" name="file_id_number" value="{{ $file_id_number ?? '' }}">
                            <input type="hidden" id="person_identity_number_hidden" name="person_identity_number" value="">
                            <input type="hidden" id="file_type_hidden" name="file_type" value="">
                            <!-- تأكد من وجود هذه الحقول المخفية -->
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
                                                    <option value="{{ $section->id }}">{{ $section->description }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">رقم الهوية <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="data_id_number" id="data_id_number"
                                                class="form-control" required inputmode="numeric" pattern="[0-9]*"
                                                maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
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
                                            <input type="number" name="data_phone_number" class="form-control">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">رقم هاتف بديل</label>
                                            <input type="number" name="data_alt_phone_number" class="form-control">
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
                                                    <option value="{{ $marital->id }}">{{ $marital->CI_PERSONAL_CD }}
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
                                                    <select name="data_province" class="form-select" required>
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
                                                    <label class="form-label">وصف الإحتياجات</label>
                                                    <textarea name="data_description_needs" class="form-control"></textarea>
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
                                            <label class="form-label">الحالة الوظيفية المعيل <span
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
                                            <label class="form-label">حالة السكن <span
                                                    class="text-danger">*</span></label>
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
                                                        <!-- نموذج إضافة فرد (مخفي - يستخدم كقالب فقط) -->
                                                        <div class="family-member-form border rounded p-3 mb-3" style="display: none;">
                                                            <div class="row g-3">
                                                                <input type="hidden" name="family_members[0][file_id]"
                                                                    value="{{ $file_id_number ?? '' }}">
                                                                <div class="col-md-6">
                                                                    <label class="form-label">رقم التسجيل <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text"
                                                                        name="family_members[0][registration_id]"
                                                                        class="form-control bg-secondary bg-opacity-10"
                                                                        readonly value="{{ $file_id_number ?? '' }}">
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
                                                                        class="form-control">
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
                                                                        class="form-control">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">رقم هوية اليتيم</label>
                                                                    <input type="text"
                                                                        name="family_members[0][person_id]"
                                                                        class="form-control" inputmode="numeric"
                                                                        pattern="[0-9]*" maxlength="10"
                                                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">تاريخ الميلاد <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="date"
                                                                        name="family_members[0][person_birth_date]"
                                                                        class="form-control">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">العمر</label>
                                                                    <input type="number"
                                                                        name="family_members[0][person_age]"
                                                                        class="form-control" readonly>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">الجنس <span
                                                                            class="text-danger">*</span></label>
                                                                    <select name="family_members[0][person_gender]"
                                                                        class="form-select">
                                                                        <option value="">اختر الجنس</option>
                                                                        <option value="1">ذكر</option>
                                                                        <option value="2">أنثى</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">الحالة الصحية</label>
                                                                    <select name="family_members[0][person_health_status]"
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
                                                                        name="family_members[0][person_type_of_guarantee]"
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
                                                        <input type="hidden" name="re_file_id"
                                                            value="{{ $file_id_number ?? '' }}">
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
                                                                inputmode="numeric" pattern="[0-9]*" maxlength="10"
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
                                                            <select name="father_death_reason" class="form-select">
                                                                <option value="">اختر سبب الوفاة</option>
                                                                @foreach ($death_reasons as $deathReason)
                                                                    <option value="{{ $deathReason->id }}">
                                                                        {{ $deathReason->description }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add Toggle Button -->
                                        <div class="col-12 mb-3">
                                            <button type="button" class="btn btn-primary w-100" id="toggleMotherInfo">
                                                <i class="fas fa-plus me-2"></i>إضافة بيانات الأم المتوفية
                                            </button>
                                        </div>

                                        <!-- Mother Information (Hidden by default) -->
                                        <div class="col-12" id="motherInfoSection" style="display: none;">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-gradient-primary text-dark py-3">
                                                    <h5 class="card-title mb-0 d-flex align-items-center">
                                                        <i class="fas fa-female fs-4 me-2"></i>
                                                        بيانات الأم المتوفية
                                                    </h5>
                                                </div>
                                                <div class="card-body bg-light">
                                                    <div class="row g-3">
                                                        <input type="hidden" name="re_file_id"
                                                            value="{{ $file_id_number ?? '' }}">
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
                                                                inputmode="numeric" pattern="[0-9]*" maxlength="10"
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
                                                            <select name="mother_death_reason" class="form-select">
                                                                <option value="">اختر سبب الوفاة</option>
                                                                @foreach ($death_reasons as $deathReason)
                                                                    <option value="{{ $deathReason->id }}">
                                                                        {{ $deathReason->description }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attachments Tab -->
                                 <div class="tab-pane fade" id="attachments" role="tabpanel" aria-labelledby="attachments-tab">
                                    <div class="row g-3">
                                        <div class="col-12 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">اختر الشخص <span class="text-danger">*</span></label>
                                                            <select id="person_selector" class="form-select" >
                                                                <option value="">اختر الشخص</option>
                                                                <optgroup label="صاحب الملف">
                                                                    <option value="main" data-id="{{ $file_id_number }}" data-id-number="{{ isset($data_id_number) ? $data_id_number : '' }}">
                                                                        <span class="main-person"></span>
                                                                    </option>
                                                                </optgroup>
                                                                <optgroup label="أفراد الأسرة" id="family_members_options">
                                                                    @foreach ($family_members ?? [] as $index => $member)
                                                                        <option value="family_{{ $index }}" data-id="{{ $file_id_number }}" data-id-number="{{ $member['person_id'] ?? '' }}">
                                                                            {{ $member['name'] ?? '' }}
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                                <optgroup label="الأفراد المتوفين">
                                                                    <option value="deceased_father" data-id-number="{{ isset($father_id_number) ? $father_id_number : '' }}">
                                                                        الأب المتوفى
                                                                    </option>
                                                                    <option value="deceased_mother" data-id-number="{{ isset($mother_id_number) ? $mother_id_number : '' }}">
                                                                        الأم المتوفية
                                                                    </option>
                                                                </optgroup>
                                                            </select>

                                                            <!-- حقل مخفي لإرسال رقم الهوية -->
                                                            <input type="hidden" name="person_identity_number" id="identity_number">
                                                        </div>

                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">نوع الوثيقة <span class="text-danger">*</span></label>
                                                            <select name="document_type" id="document_type" class="form-select">
                                                                <option value="">اختر نوع الوثيقة</option>
                                                                @foreach ($documentTypes as $documentType)
                                                                    <option value="{{ $documentType->pref }}">{{ $documentType->description }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">رقم الملف</label>
                                                            <input type="text" id="document_id" class="form-control bg-secondary bg-opacity-25"
                                                                value="{{ $file_id_number ?? '' }}" readonly>
                                                        </div>
                                                    </div>

                                                    <div class="text-center mt-3">
                                                        <div class="file-upload-wrapper">
                                                            <input type="file" name="document_file" id="document_file" class="form-control" accept="image/*">
                                                            <div id="preview" class="mt-3 d-none">
                                                                <img src="" alt="معاينة" class="img-fluid mb-2" style="max-height: 200px;">
                                                                <div class="mt-2">
                                                                    <button type="button" id="confirmUpload" class="btn btn-success">
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
                                    <!-- تم حذف زر الحفظ من هنا -->
                                </div>
                                <!-- نهاية بوابة المرفقات -->

                                <!-- بوابة عرض المعلومات المدخلة ستُضاف ديناميكياً من جافاسكريبت -->
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>



    @include('admin.dashboard.records_management.style')
    @include('admin.dashboard.records_management.javascript_create')
@endsection

