@extends('user.generalRegistration.index')
@section('contentGeneralRegistration')
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

                    <!-- Tab Navigation -->
                    @include('user.generalRegistration.component.navBar')

                    <div class="card-body bg-light">

                        <form action="{{ route('store.generalRegistration') }}" method="POST"
                            enctype="multipart/form-data" autocomplete="off" id="main_form" novalidate>
                            @csrf
                            <input type="hidden" name="file_id_number" value="{{ $file_id_number ?? '' }}">

                        <div class="tab-content" id="formTabsContent">

                            <!-- Basic Info Tab (Active by default) -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                                @include('user.generalRegistration.component.baseTap')
                            </div>

                            <!-- Family Members Tab -->
                            @include('user.generalRegistration.component.familyMember')

                            <!-- Deceased Tab -->
                            @include('user.generalRegistration.component.deceased')

                            <!-- Attachments & Review Tab -->
                            <div class="tab-pane fade" id="attachments" role="tabpanel" aria-labelledby="attachments-tab">
                                <div class="card shadow-sm border-0 mb-4" dir="rtl">
                                    <div class="card-header bg-gradient-primary text-dark fw-bold">
                                        <i class="fas fa-paperclip me-2"></i>المرفقات والمراجعة
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            سيتم إضافة المرفقات من داخل تبويبات البيانات. يرجى مراجعة جميع البيانات قبل الحفظ.
                                        </div>

                                        <!-- ملخص البيانات المدخلة -->
                                        <div id="reviewSummary" class="mt-3">
                                            <h5 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>ملخص البيانات</h5>
                                            <div class="row" id="reviewContent">
                                                <!-- سيتم ملؤها ديناميكياً -->
                                            </div>
                                        </div>

                                        <div class="mt-4 text-center">
                                            <button type="button" class="btn btn-success px-5 py-3 fs-5" id="submitFormBtn">
                                                <i class="fas fa-save me-2"></i>حفظ التسجيل
                                            </button>
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
    @include('user.generalRegistration.style')
    @include('user.generalRegistration.javascript')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submitFormBtn = document.getElementById('submitFormBtn');
            if (submitFormBtn) {
                submitFormBtn.addEventListener('click', function() {
                    const mainForm = document.getElementById('main_form');
                    if (mainForm) mainForm.requestSubmit();
                });
            }

            const citySelect = document.querySelector('select[name="data_city"]');
            const hiddenProvinceInput = document.querySelector('input[name="data_province"]');
            const visibleProvinceSelect = document.querySelector('select[name="data_province_visible"]');
            const cityProvinceMap = @json($city->mapWithKeys(function ($item) { return [$item->id => $item->province_id ?? null]; })->all());

            function syncProvinceFromCity() {
                if (!citySelect || !hiddenProvinceInput) return;
                const selectedCityId = citySelect.value;
                const mappedProvince = cityProvinceMap[selectedCityId] ?? '';
                hiddenProvinceInput.value = mappedProvince;
                if (visibleProvinceSelect) {
                    visibleProvinceSelect.value = mappedProvince || '';
                }
            }

            if (citySelect) {
                citySelect.addEventListener('change', syncProvinceFromCity);
                syncProvinceFromCity();
            }

            function syncFamilySummaryHiddenValues() {
                const familyForms = document.querySelectorAll('#familyMembersContainer .family-member-form:not(.d-none)');
                let maleCount = 0;
                let femaleCount = 0;
                let chronicCount = 0;
                let specialNeedsCount = 0;

                familyForms.forEach(function(form) {
                    const genderSelect = form.querySelector('select[name$="[person_gender]"]');
                    const healthSelect = form.querySelector('select[name$="[person_health_status]"]');
                    const genderValue = genderSelect ? genderSelect.value : '';
                    if (genderValue === '1') maleCount++;
                    if (genderValue === '2') femaleCount++;

                    if (healthSelect) {
                        const selectedOption = healthSelect.options[healthSelect.selectedIndex];
                        const selectedText = selectedOption ? selectedOption.textContent || '' : '';
                        if (selectedText.includes('مزمن')) chronicCount++;
                        if (selectedText.includes('معاق') || selectedText.includes('احتياج') || selectedText.includes('إعاقة')) specialNeedsCount++;
                    }
                });

                const maleInput = document.querySelector('[name="data_number_mail"]');
                const femaleInput = document.querySelector('[name="data_number_female"]');
                const chronicInput = document.querySelector('[name="data_number_of_individuals_with_chronic_diseases"]');
                const specialInput = document.querySelector('[name="data_number_of_people_with_special_needs"]');
                const totalInput = document.querySelector('[name="data_number_of_individuals"]');

                if (maleInput) maleInput.value = maleCount;
                if (femaleInput) femaleInput.value = femaleCount;
                if (chronicInput) chronicInput.value = chronicCount;
                if (specialInput) specialInput.value = specialNeedsCount;
                if (totalInput) totalInput.value = maleCount + femaleCount;
            }

            document.addEventListener('change', function(event) {
                const isFamilyGender = event.target && event.target.matches('select[name$="[person_gender]"]');
                const isFamilyHealth = event.target && event.target.matches('select[name$="[person_health_status]"]');
                if (isFamilyGender || isFamilyHealth) {
                    syncFamilySummaryHiddenValues();
                }
            });

            const familyMemberObserver = new MutationObserver(function() {
                syncFamilySummaryHiddenValues();
            });

            const familyMembersContainer = document.getElementById('familyMembersContainer');
            if (familyMembersContainer) {
                familyMemberObserver.observe(familyMembersContainer, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['value', 'selected', 'style', 'class']
                });
            }

            syncFamilySummaryHiddenValues();
        });
    </script>

@endsection
