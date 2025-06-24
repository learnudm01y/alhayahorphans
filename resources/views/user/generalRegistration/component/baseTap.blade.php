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
                                            <label class="form-label">رقم الهوية <span class="text-danger">*</span></label>
                                            <input type="text" name="data_id_number" id="data_id_number"
                                                class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="10"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                        </div>
                                        <div class="col-md-8">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-12 mb-2">

                                                    <span class="text-primary fw-bold" style="font-size: 1rem;">تتكون كلمة المرور من 4 أرقام فقط، وتكون متطابقة مثلا (1234) ,(1234).</span><br>
                                                </div>
                                                <div class="col-12 d-flex flex-row gap-3">
                                                    <div class="flex-fill position-relative">
                                                        <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                                                        <input type="text" name="user_password" class="form-control" autocomplete="new-password" maxlength="4" inputmode="numeric" pattern="\d{4}" required>
                                                        <div class="d-flex align-items-center mt-1">
                                                            <span class=" fw-bold" style="font-size: 0.95rem; color: #5d5d5d;">ننصحك بأخذ لقطة شاشة أو حفظ كلمة المرور في كلمات مرور جوجل حتى لا تفقدها.</span>
                                                            <button type="button" class="btn btn-outline-primary btn-sm ms-2 position-relative screenshot-pulse-btn" id="screenshotPasswordBtn" title="التقاط لقطة شاشة لكلمة المرور">
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
                                                        <input type="text" name="user_password_confirmation" class="form-control" autocomplete="new-password" maxlength="4" inputmode="numeric" pattern="\d{4}" required>
                                                        <button type="button" class="btn btn-success btn-sm ms-2 mt-2" id="saveToGoogleBtn" title="حفظ كلمة المرور في جوجل" style="white-space: nowrap; font-size: 0.92rem; padding: 0.35rem 0.7rem; min-width: 90px;">
                                                            <i class="fab fa-google me-1"></i> حفظ في جوجل
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label">الاسم الأول <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="data_first_name" class="form-control">
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
                                        <div class="col-md-4 mt-3">
                                            <!-- تم إلغاء جميع alert هنا -->
                                            <label class="form-label fw-bold"> رفع الملفات  <span class="text-danger">*</span></label>
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
                                <div class="mt-4 text-end">
                                    <button type="button" class="btn btn-success px-5 py-2 fs-5" id="goToNextTabBtn">
                                        التالي <i class="fas fa-arrow-left ms-2"></i>
                                    </button>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const nextBtn = document.getElementById('goToNextTabBtn');
                                        if (nextBtn) {
                                            nextBtn.addEventListener('click', function() {
                                                const sectionSelect = document.querySelector('select[name="data_section_id"]');
                                                const selectedValue = sectionSelect ? sectionSelect.value : '';
                                                // عدل رقم قسم الأيتام حسب قاعدة البيانات لديك
                                                const orphansSectionId = '1'; // مثال: 3 هو رقم قسم الأيتام
                                                if (selectedValue === orphansSectionId) {
                                                    const deceasedTab = document.getElementById('deceased-tab');
                                                    if (deceasedTab) deceasedTab.click();
                                                } else {
                                                    const familyTab = document.getElementById('family-members-tab');
                                                    if (familyTab) familyTab.click();
                                                }
                                            });
                                        }
                                    });
                                </script>
