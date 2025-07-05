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

                    <!-- Add Tab Navigation (mobile-bottom-tabs style) -->
                    @include('user.generalRegistration.component.navBar')

                    <div class="card-body bg-light">

                        <!-- تأكد من وجود هذه الحقول المخفية -->
                        <div class="tab-content" id="formTabsContent">
                            <!-- Instructions Tab: بوابة تعليمات الإدخال معزولة بالكامل -->
                            <div class="tab-pane fade show active" id="instructions" role="tabpanel"
                                aria-labelledby="instructions-tab">
                                <div class="alert alert-info mt-4" dir="rtl">
                                    <div class="mb-3" style="text-align: right;">
                                        <span class="text-black" style="font-sأize: 1.1rem;">
                                            <i class="fas fa-info-circle"></i>ملاحظة/ إذا كنت قد سجلت مسبقا لدينا بإمكانك
                                            تسجيل الدخول
                                        </span>
                                        <button type="button" class="btn btn-primary ms-3" id="showLoginFormBtn">
                                            <i class="fas fa-sign-in-alt fa-flip-horizontal me-1"></i>تسجيل الدخول
                                        </button>
                                    </div>
                                    <!-- نموذج تسجيل الدخول (مخفي افتراضياً) -->
                                    <div id="loginFormContainer" class="card p-4 my-3 shadow-sm border border-primary"
                                        style="max-width: 400px; margin: 0 auto; display: none;">
                                        <form id="loginForm" method="POST" action="{{ route('user.login') }}"
                                            autocomplete="off">
                                            @csrf
                                            <div class="mb-3">
                                                <label for="login_email" class="form-label">رقم الهوية</label>
                                                <input type="text" class="form-control" id="login_email"
                                                    name="login_email" maxlength="20" required pattern="[0-9]+">
                                            </div>
                                            <div class="mb-3">
                                                <label for="login_password" class="form-label">كلمة المرور (4 أرقام)</label>
                                                <input type="password" class="form-control" id="login_password"
                                                    name="login_password" maxlength="4" minlength="4" required
                                                    pattern="\d{4}">
                                            </div>
                                            <button type="submit" class="btn btn-success w-100">دخول</button>
                                        </form>
                                    </div>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const showLoginFormBtn = document.getElementById('showLoginFormBtn');
                                            const loginFormContainer = document.getElementById('loginFormContainer');
                                            if (showLoginFormBtn && loginFormContainer) {
                                                showLoginFormBtn.addEventListener('click', function() {
                                                    loginFormContainer.style.display = loginFormContainer.style.display === 'none' ?
                                                        'block' : 'none';
                                                });
                                            }
                                        });
                                    </script>
                                    <form action="{{ route('store.generalRegistration') }}" method="POST"
                                        enctype="multipart/form-data" autocomplete="off" id="main_form">
                                        @csrf
                                        <input type="hidden" name="file_id_number" value="{{ $file_id_number ?? '' }}">
                                        <h4 class="mb-3"><i class="fas fa-info-circle"></i> تعليمات إدخال البيانات
                                        </h4>
                                        <ul class="fs-5">
                                            <li>يرجى تعبئة جميع الحقول الإلزامية بعناية لضمان قبول الطلب.</li>
                                            <li>لرفع الملفات (مثل الصور أو المستندات)، استخدم زر <b>إضافة مرفق</b> في
                                                بوابة المرفقات فقط، وتأكد من أن حجم الملف وصيغته مطابقة للتعليمات.
                                            </li>
                                            <li>لحساب العمر تلقائيًا، أدخل تاريخ الميلاد بشكل صحيح وسيتم حساب العمر
                                                تلقائيًا في الحقل المخصص.</li>
                                            <li>في حال ظهور رسالة خطأ باللون الأحمر، يرجى مراجعة البيانات المدخلة
                                                وتصحيحها.</li>
                                            <li>استخدم خاصية الإكمال التلقائي عند إدخال بيانات مثل اسم المدينة أو
                                                المحافظة لتسهيل وتسريع الإدخال.</li>
                                            <li>يمكنك مراجعة البيانات المدخلة في بوابة "عرض المعلومات المدخلة" قبل
                                                الحفظ النهائي.</li>
                                            <li>لحفظ كلمة المرور في متصفحك، استخدم زر الحفظ بجانب حقل كلمة المرور
                                                (في حال توفره).</li>
                                        </ul>
                                        <div class="mt-3 text-muted">
                                            <i class="fas fa-exclamation-triangle"></i> جميع المعلومات ستُعامل بسرية
                                            تامة.
                                        </div>
                                </div>
                                <div class="mt-4 text-end">
                                    <button type="button" class="btn btn-success px-5 py-2 fs-5" id="goToBasicTabBtn">
                                        التالي <i class="fas fa-arrow-left ms-2"></i>
                                    </button>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const goToBasicTabBtn = document.getElementById('goToBasicTabBtn');
                                        if (goToBasicTabBtn) {
                                            goToBasicTabBtn.addEventListener('click', function() {
                                                const basicTab = document.getElementById('basic-tab');
                                                if (basicTab) {
                                                    basicTab.click();
                                                }
                                            });
                                        }
                                    });
                                </script>
                                <div id="didding" style="padding-bottom: 80px;"></div>
                            </div>
                            <!-- نهاية بوابة تعليمات الإدخال -->

                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                                @include('user.generalRegistration.component.baseTap')
                            </div>
                            <!-- Family Members Tab -->
                            @include('user.generalRegistration.component.familyMember')


                            <!-- Deceased Tab -->
                         
                            @include('user.generalRegistration.component.deceased')
                            <!-- بوابة عرض المعلومات المدخلة ستُضاف ديناميكياً من جافاسكريبت -->
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('user.generalRegistration.style')
    @include('user.generalRegistration.javascript')

@endsection
