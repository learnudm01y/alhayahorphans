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
                    <div class="card-body bg-light">
                        <form action="{{ route('admin.records.management.store') }}" method="POST"
                            enctype="multipart/form-data" autocomplete="off">
                            @csrf

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
                                    <input type="text" name="data_id_number" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                            <input type="text" name="data_first_name" class="form-control" required>
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
                                    <label class="form-label">الحالة الاجتماعية<span class="text-danger">*</span></label>
                                    <select name="data_marital_status" class="form-select">
                                        <option value="">اختر الحالة</option>
                                        @foreach ($marital_status as $marital)
                                            <option value="{{ $marital->id }}">{{ $marital->description }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">المؤهل العلمي </label>
                                    <select name="data_academic_qualification" class="form-select">
                                        <option value="">اختر المؤهل</option>
                                        @foreach ($academic_qualification as $qualification)
                                            <option value="{{ $qualification->id }}">{{ $qualification->description }}
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
                                            <label class="form-label">العنوان قبل النزوح</label>
                                            <input type="text" name="data_address_before_displacement"
                                                class="form-control">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">العنوان الحالي <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="data_current_address" class="form-control">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">المدينة <span class="text-danger">*</span></label>
                                            <select name="data_city" class="form-select">
                                                <option value="">اختر المدينة</option>
                                                @foreach ($city as $city_item)
                                                    <option value="{{ $city_item->id }}">{{ $city_item->city }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">المحافظة <span class="text-danger">*</span></label>
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
                                            <input type="number" name="data_number_of_individuals_with_chronic_diseases"
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
                                    <label class="form-label">حالة عمل المعيل <span class="text-danger">*</span></label>
                                    <select name="data_employment_status_breadwinner" class="form-select">
                                        <option value="">اختر الحالة</option>
                                       @foreach ($employment_status_breadwinner as $employment_status_item)
                                            <option value="{{ $employment_status_item->id }}">
                                                {{ $employment_status_item->description }}</option>

                                       @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">حالةالسكن <span class="text-danger">*</span></label>
                                    <select name="data_employment_status_breadwinner" class="form-select">
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
                                <div class="col-md-4">
                                    <label class="form-label">صورة الهوية</label>
                                    <input type="file" name="data_id_image" class="form-control" accept="image/*">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">صورة حجة الولاية</label>
                                    <input type="file" name="data_guardianship_argument_image" class="form-control"
                                        accept="image/*">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">المستخدم المدخل للبيانات</label>
                                    <input type="text" name="data_user_insert_data"
                                        class="form-control bg-secondary bg-opacity-25"
                                        value="{{ auth()->user()->name }}" readonly>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success px-5 py-2 fw-bold">
                                    <i class="fas fa-save me-2"></i>حفظ البيانات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
