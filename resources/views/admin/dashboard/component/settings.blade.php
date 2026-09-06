<div class="card mb-5 mb-xl-10">
    <!--begin::Card header-->
    <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse"
        data-bs-target="#kt_account_profile_details" aria-expanded="true" aria-controls="kt_account_profile_details">
        <!--begin::Card title-->
        <div class="card-title m-0">
            <h3 class="fw-bold m-0"> المعلومات الشخصية </h3>
        </div>
        <!--end::Card title-->
    </div>
    <!--begin::Card header-->
    <!--begin::Content-->
    <div id="kt_account_settings_profile_details" class="collapse show">
        <!--begin::Form-->
        <form id="kt_account_profile_details_form" class="form fv-plugins-bootstrap5 fv-plugins-framework"
            novalidate="novalidate" enctype="multipart/form-data">
            <!--begin::Card body-->
            <div class="card-body border-top p-9">
                <!--begin::Input group-->
                <div class="row mb-6">
                    <!--begin::Label-->
                    <label class="col-lg-4 col-form-label fw-semibold fs-6"> الصورة الشخصية </label>
                    <!--end::Label-->
                    <!--begin::Col-->
                    <div class="col-lg-8">
                        <!--begin::Image input-->
                        <div class="image-input image-input-outline" id="user-avatar-input" data-kt-image-input="true"
                            style="background-image: url('{{ asset('admin/assets/media/svg/avatars/blank.svg') }}')">

                            <!--begin::Preview existing avatar-->
                            <div class="image-input-wrapper w-125px h-125px" id="avatar-preview"
                                style="background-image: url('{{ asset($user->avatar_url ?? Auth::user()->avatar) }}')">
                            </div>
                            <!--end::Preview existing avatar-->

                            <!--begin::Label-->
                            <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                data-kt-image-input-action="change" data-bs-toggle="tooltip" title="تغيير الصورة">
                                <i class="ki-duotone ki-pencil fs-7">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                <!--begin::Inputs-->
                                <input type="file" name="avatar" id="avatar-input" accept="image/*,.heic,.heif">
                                <input type="hidden" name="avatar_remove">
                                <!--end::Inputs-->
                            </label>
                            <!--end::Label-->

                            <!--begin::Cancel-->
                            <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                data-kt-image-input-action="cancel" data-bs-toggle="tooltip" title="إلغاء التغيير">
                                <i class="ki-duotone ki-cross fs-2">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </span>
                            <!--end::Cancel-->

                            <!--begin::Remove-->
                            <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow"
                                data-kt-image-input-action="remove" data-bs-toggle="tooltip" title="حذف الصورة"
                                id="avatar-remove-btn">
                                <i class="ki-duotone ki-cross fs-2">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </span>
                            <!--end::Remove-->
                        </div>
                        <!--end::Image input-->

                        <!--begin::Hint-->
                        <div class="form-text">أنواع الصور المسموح بها: png, jpg, jpeg.</div>
                        <!--end::Hint-->
                    </div>
                    <!--end::Col-->
                </div>
                <!--end::Input group-->
                <!--begin::Input group-->
                <div class="row mb-6">
                    <!--begin::Label-->
                    <label class="col-lg-4 col-form-label required fw-semibold fs-6"> الإسم كاملاً </label>
                    <!--end::Label-->
                    <!--begin::Col-->
                    <div class="col-lg-8">
                        <!--begin::Row-->
                        <div class="row">
                            <!--begin::Col-->
                            <div class="col-lg-8 fv-row fv-plugins-icon-container">
                                <input type="text" name="name"
                                    class="form-control form-control-lg form-control-solid" placeholder="First name"
                                    value="{{ $user->name }}">
                            </div>
                        </div>
                        <!--end::Row-->
                    </div>
                    <!--end::Col-->
                </div>
                <!--end::Input group-->
                <!--begin::Input group-->
                <div class="row mb-6">
                    <!--begin::Label-->
                    <label class="col-lg-4 col-form-label required fw-semibold fs-6"> رقم الهاتف </label>
                    <!--end::Label-->
                    <!--begin::Col-->
                    <div class="col-lg-8 fv-row fv-plugins-icon-container">
                        <input type="tel" name="phone" class="form-control form-control-lg form-control-solid"
                            placeholder="رقم الهاتف" value="{{ $user->phone }}">
                    </div>
                    <!--end::Col-->
                </div>
                <!--end::Input group-->
                <!--begin::Input group-->
                <div class="row mb-6">
                    <!--begin::Label-->
                    <label class="col-lg-4 col-form-label required fw-semibold fs-6"> رقم الهاتف البديل </label>
                    <!--end::Label-->
                    <!--begin::Col-->
                    <div class="col-lg-8 fv-row fv-plugins-icon-container">
                        <input type="tel" name="alt_phone" class="form-control form-control-lg form-control-solid"
                            placeholder="رقم الهاتف البديل" value="{{ $user->alt_phone }}">
                    </div>
                    <!--end::Col-->
                </div>
                <!--end::Input group-->
                <!--begin::Input group-->
                <div class="row mb-6">
                    <!--begin::Label-->
                    <label class="col-lg-4 col-form-label fw-semibold fs-6"> الدولة </label>
                    <!--end::Label-->
                    <!--begin::Col-->
                    <select name="country_code" aria-label="Select a Country" data-control="select2"
                        data-placeholder="حدد الدولة..." class="form-select form-select-solid form-select-lg">
                        <option value="">حدد الدولة...</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->code }}"
                                data-kt-flag="{{ asset('admin/assets/media/flags/' . $country->flag) }}"
                                {{ old('country_code', $user->country_code ?? '') == $country->code ? 'selected' : '' }}>
                                {{ $country->ci_birth_cd }} <!-- عرض اسم الدولة من عمود ci_birth_cd -->
                            </option>
                        @endforeach
                    </select>
                    <!--end::Col-->
                </div>
                <!--end::Input group-->
            </div>
            <!--end::Card body-->
            <!--begin::Actions-->
            <div class="card-footer d-flex justify-content-end py-6 px-9">
                <button type="button" class="btn btn-primary" id="kt_account_profile_details_submit"> حفظ التغييرات
                </button>

            </div>
            <!--end::Actions-->
            <input type="hidden">
        </form>
        <!--end::Form-->
    </div>
    <!--end::Content-->
</div>

<div class="card mb-5 mb-xl-10">
    <!--begin::Card header-->
    <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse"
        data-bs-target="#kt_account_signin_method">
        <div class="card-title m-0">
            <h3 class="fw-bold m-0"> إدارة إعدادات الدخول </h3>
        </div>
    </div>
    <!--end::Card header-->
    <!--begin::Content-->
    <div id="kt_account_settings_signin_method" class="collapse show">
        <!--begin::Card body-->
        <div class="card-body border-top p-9">
            <!--begin::Email Address-->
            <div class="d-flex flex-wrap align-items-center">
                <!--begin::Label-->
                <div id="kt_signin_email">
                    <div class="fs-6 fw-bold mb-1"> البريد الإلكتروني </div>
                    <div class="fw-semibold text-gray-600">{{ Auth::user()->email }}</div>
                </div>
                <!--end::Label-->
                <!--begin::Edit-->
                <div id="kt_signin_email_edit" class="flex-row-fluid d-none">
                    <!--begin::Form-->
                    <form id="kt_signin_change_email" class="form fv-plugins-bootstrap5 fv-plugins-framework"
                        novalidate="novalidate">
                        <div class="row mb-6">
                            <div class="col-lg-6 mb-4 mb-lg-0">
                                <div class="fv-row mb-0 fv-plugins-icon-container">
                                    <label for="emailaddress" class="form-label fs-6 fw-bold mb-3"> أدخل بريد إلكتروني
                                        جديد </label>
                                    <input type="email" class="form-control form-control-lg form-control-solid"
                                        id="emailaddress" placeholder="Email Address" name="emailaddress"
                                        value="{{ Auth::user()->email }}">
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="fv-row mb-0 fv-plugins-icon-container">
                                    <label for="confirmemailpassword" class="form-label fs-6 fw-bold mb-3"> تأكيد
                                        بإستخدام كلمة المرور </label>
                                    <input type="password" class="form-control form-control-lg form-control-solid"
                                        name="confirmemailpassword" id="confirmemailpassword">
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex">
                            <button id="kt_signin_submit" type="button" class="btn btn-primary me-2 px-6"> تحديث
                                البريد </button>
                            <button id="kt_signin_cancel" type="button"
                                class="btn btn-color-gray-400 btn-active-light-primary px-6"> إلغاء </button>
                        </div>
                    </form>
                    <!--end::Form-->
                </div>
                <!--end::Edit-->
                <!--begin::Action-->
                <div id="kt_signin_email_button" class="ms-auto">
                    <button class="btn btn-light btn-active-light-primary"> تغيير البريد الإلكتروني </button>
                </div>
                <!--end::Action-->
            </div>
            <!--end::Email Address-->
            <!--begin::Separator-->
            <div class="separator separator-dashed my-6"></div>
            <!--end::Separator-->
            <!--begin::Password-->
            <div class="d-flex flex-wrap align-items-center mb-10">
                <!--begin::Label-->
                <div id="kt_signin_password">
                    <div class="fs-6 fw-bold mb-1"> كلمة المرور </div>
                    <div class="fw-semibold text-gray-600">************</div>
                </div>
                <!--end::Label-->
                <!--begin::Edit-->
                <div id="kt_signin_password_edit" class="flex-row-fluid d-none">
                    <!--begin::Form-->
                    <form id="kt_signin_change_password" class="form fv-plugins-bootstrap5 fv-plugins-framework"
                        novalidate="novalidate">
                        <div class="row mb-1">
                            <div class="col-lg-4">
                                <div class="fv-row mb-0 fv-plugins-icon-container">
                                    <label for="currentpassword" class="form-label fs-6 fw-bold mb-3"> كلمة المرور
                                        الحالية </label>
                                    <input type="password" class="form-control form-control-lg form-control-solid"
                                        name="currentpassword" id="currentpassword">
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="fv-row mb-0 fv-plugins-icon-container">
                                    <label for="newpassword" class="form-label fs-6 fw-bold mb-3"> كلمة المرور الجديدة
                                    </label>
                                    <input type="password" class="form-control form-control-lg form-control-solid"
                                        name="newpassword" id="newpassword">
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="fv-row mb-0 fv-plugins-icon-container">
                                    <label for="confirmpassword" class="form-label fs-6 fw-bold mb-3"> تأكيد كلمة
                                        المرور الجديدة </label>
                                    <input type="password" class="form-control form-control-lg form-control-solid"
                                        name="newpassword_confirmation" id="confirmpassword">
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text mb-5"> يجب أن تتكون كلمة المرور من 8 أحرف على الأقل وتحتوي على رموز
                        </div>
                        <div class="d-flex">
                            <button id="kt_password_submit" type="button" class="btn btn-primary me-2 px-6"> تحديث
                                كلمة المرور </button>
                            <button id="kt_password_cancel" type="button"
                                class="btn btn-color-gray-400 btn-active-light-primary px-6"> إلغاء </button>
                        </div>
                    </form>
                    <!--end::Form-->
                </div>
                <!--end::Edit-->
                <!--begin::Action-->
                <div id="kt_signin_password_button" class="ms-auto">
                    <button class="btn btn-light btn-active-light-primary"> إعادة تعيين كلمة المرور </button>
                </div>
                <!--end::Action-->
            </div>
            <!--end::Password-->
        </div>
        <!--end::Card body-->
    </div>
    <!--end::Content-->
</div>

