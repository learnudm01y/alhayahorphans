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
                            <button class="nav-link py-3" id="family-members-tab" data-bs-toggle="tab" data-bs-target="#family-members"
                                type="button" role="tab" aria-controls="family-members" aria-selected="false">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <span class="fs-4 fw-bold">أفراد الأسرة</span>
                                </div>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-3" id="attachments-tab" data-bs-toggle="tab" data-bs-target="#attachments"
                                type="button" role="tab" aria-controls="attachments" aria-selected="false">
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
                                <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
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
                                            <input type="text" name="data_id_number" id="data_id_number" class="form-control" required>
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
                                            <label class="form-label">المستخدم المدخل للبيانات</label>
                                            <input type="text" name="data_user_insert_data"
                                                class="form-control bg-secondary bg-opacity-25"
                                                value="{{ auth()->user()->name }}" readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- Family Members Tab -->
                                <div class="tab-pane fade" id="family-members" role="tabpanel" aria-labelledby="family-members-tab">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h5 class="card-title mb-0">بيانات أفراد الأسرة</h5>
                                                        <button type="button" class="btn btn-primary" id="addFamilyMember">
                                                            <i class="fas fa-plus me-2"></i>إضافة فرد
                                                        </button>
                                                    </div>
                                                    <div id="familyMembersContainer">
                                                        <!-- نموذج إضافة فرد -->
                                                        <div class="family-member-form border rounded p-3 mb-3">
                                                            <div class="row g-3">
                                                                <input type="hidden" name="family_members[0][file_id]" value="{{ $file_id_number ?? '' }}">
                                                                <div class="col-md-6">
                                                                    <label class="form-label">رقم التسجيل <span class="text-danger">*</span></label>
                                                                    <input type="text" name="family_members[0][registration_id]" class="form-control" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">حالة الكفالة</label>
                                                                    <select name="family_members[0][sponsorship_status]" class="form-select">
                                                                        <option value="">اختر الحالة</option>
                                                                        @foreach ($sponsorship_status as $status)
                                                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">الاسم الأول <span class="text-danger">*</span></label>
                                                                    <input type="text" name="family_members[0][first_name]" class="form-control" required>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">الاسم الثاني</label>
                                                                    <input type="text" name="family_members[0][second_name]" class="form-control">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">الاسم الثالث</label>
                                                                    <input type="text" name="family_members[0][third_name]" class="form-control">
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <label class="form-label">اسم العائلة <span class="text-danger">*</span></label>
                                                                    <input type="text" name="family_members[0][last_name]" class="form-control" required>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">رقم هوية اليتيم</label>
                                                                    <input type="text" name="family_members[0][orphan_id]" class="form-control">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                                                                    <input type="date" name="family_members[0][orphan_birth_date]" class="form-control" required>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">العمر</label>
                                                                    <input type="number" name="family_members[0][orphan_age]" class="form-control" readonly>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">الجنس <span class="text-danger">*</span></label>
                                                                    <select name="family_members[0][orphan_gender]" class="form-select" required>
                                                                        <option value="">اختر الجنس</option>
                                                                        <option value="1">ذكر</option>
                                                                        <option value="2">أنثى</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">الحالة الصحية</label>
                                                                    <select name="family_members[0][orphan_health_status]" class="form-select">
                                                                        <option value="">اختر الحالة</option>
                                                                        @foreach ($health_status as $status)
                                                                            <option value="{{ $status->id }}">{{ $status->description }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">نوع الكفالة</label>
                                                                    <select name="family_members[0][orphan_type_of_guarantee]" class="form-select">
                                                                        <option value="">اختر النوع</option>
                                                                        @foreach ($guarantee_types as $type)
                                                                            <option value="{{ $type->id }}">{{ $type->description }}</option>
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

                                <!-- Attachments Tab -->
                                <div class="tab-pane fade" id="attachments" role="tabpanel" aria-labelledby="attachments-tab">
                                    <div class="row g-3">
                                        <div class="col-12 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">نوع الوثيقة <span class="text-danger">*</span></label>
                                                            <select name="document_type" id="document_type" class="form-select" required>
                                                                <option value="">اختر نوع الوثيقة</option>
                                                                @foreach ($documentTypes as $documentType)
                                                                    <option value="{{ $documentType->pref }}">{{ $documentType->description }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">رقم الوثيقة</label>
                                                            <input type="text" id="document_id" name="document_id" class="form-control bg-secondary bg-opacity-25"
                                                                value="{{ $file_id_number ?? '' }}" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="text-center mt-3">
                                                        <input type="file" name="document_file" id="document_file"
                                                            class="form-control" accept="image/*" style="display: none;">
                                                        <label for="document_file" class="btn btn-outline-primary mb-2">
                                                            <i class="fas fa-file-upload me-2"></i>اختيار ملف
                                                        </label>
                                                        <div id="preview" class="mt-2 d-none">
                                                            <img src="" alt="معاينة" class="img-fluid mb-2" style="max-height: 200px;">
                                                            <button type="button" id="confirmUpload" class="btn btn-success">
                                                                <i class="fas fa-check me-2"></i>تأكيد الرفع
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-3">الوثائق المرفقة</h5>
                                                    <div id="uploaded_documents" class="row g-3">
                                                        <!-- الوثائق المرفقة ستظهر هنا -->
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
        triggerTabList.forEach(function (triggerEl) {
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
            background-color: rgba(0,0,0,0.05);
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .family-member-form {
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        .family-member-form:hover {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
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
        const docsContainer = document.getElementById('uploaded_documents');
        const docTypeSelect = document.getElementById('document_type');
        const docs = new Map();

        // معالجة اختيار الملف
        fileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (!file) return;

            if (!docTypeSelect.value) {
                alert('الرجاء اختيار نوع الوثيقة أولاً');
                this.value = '';
                return;
            }

            // عرض معاينة الصورة
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });

        // معالجة تأكيد الرفع
        confirmBtn.addEventListener('click', function() {
            const file = fileInput.files[0];
            if (!file) return;

            const docType = docTypeSelect.value;
            const docTypeName = docTypeSelect.options[docTypeSelect.selectedIndex].text;
            const fileId = document.getElementById('document_id').value;

            // تغيير ترتيب اسم الملف: نوع_الوثيقة_رقم_الوثيقة.الامتداد
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const newFileName = `${docType}_${fileId}.${fileExtension}`;

            // إنشاء ملف جديد بالاسم المعدل
            const modifiedFile = new File([file], newFileName, {
                type: file.type,
                lastModified: file.lastModified,
            });

            const docId = `${fileId}-${docType}-${Date.now()}`;

            // إضافة الوثيقة للعرض
            const docHTML = `
                <div class="col-md-4 mb-3" id="doc-${docId}">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">${docTypeName}</h6>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeDocument('${docId}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <img src="${previewImg.src}" class="card-img-top" style="height: 200px; object-fit: contain;">
                        <div class="card-body">
                            <p class="small text-muted mb-1">رقم الملف: ${fileId}</p>
                            <p class="small text-muted mb-1">نوع الوثيقة: ${docTypeName}</p>
                            <p class="small text-muted">اسم الملف: ${newFileName}</p>
                        </div>
                    </div>
                </div>
            `;

            docsContainer.insertAdjacentHTML('afterbegin', docHTML);

            // تخزين الوثيقة
            docs.set(docId, {
                file: modifiedFile,
                type: docType,
                name: newFileName,
                typeName: docTypeName
            });

            // إعادة تعيين النموذج
            fileInput.value = '';
            preview.classList.add('d-none');
            docTypeSelect.value = '';
        });

        // حذف وثيقة
        function removeDocument(docId) {
            if (!confirm('هل أنت متأكد من حذف هذه الوثيقة؟')) return;

            const element = document.getElementById(`doc-${docId}`);
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

            // تحديث الانديكس في الاسماء
            template.querySelectorAll('input, select').forEach(input => {
                if (input.name) {
                    input.name = input.name.replace('[0]', `[${familyMemberCount}]`);
                }
            });

            // إضافة زر الحذف
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-2';
            deleteButton.innerHTML = '<i class="fas fa-times"></i>';
            deleteButton.onclick = function() {
                if(confirm('هل أنت متأكد من حذف هذا الفرد؟')) {
                    template.remove();
                }
            };

            template.style.position = 'relative';
            template.appendChild(deleteButton);

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
@endsection
