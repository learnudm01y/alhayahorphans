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
                        <form action="{{ route('store.generalRegistration') }}" method="POST" enctype="multipart/form-data"
                            autocomplete="off" id="main_form">
                            @csrf
                            <input type="hidden" name="file_id_number" value="{{ $file_id_number ?? '' }}">
                            <input type="hidden" id="person_identity_number_hidden" name="person_identity_number"
                                value="">
                            <input type="hidden" id="file_type_hidden" name="file_type" value="">
                            <!-- تأكد من وجود هذه الحقول المخفية -->
                            <div class="tab-content" id="formTabsContent">
                                <!-- Instructions Tab: بوابة تعليمات الإدخال معزولة بالكامل -->
                                <div class="tab-pane fade show active" id="instructions" role="tabpanel"
                                    aria-labelledby="instructions-tab">
                                    <div class="alert alert-info mt-4" dir="rtl">
                                        <div class="mb-3" style="text-align: right;">
                                            <span class="text-black" style="font-sأize: 1.1rem;">
                                                <i class="fas fa-info-circle"></i>ملاحظة/ إذا كنت قد سجلت مسبقا لدينا بإمكانك تسجيل الدخول
                                            </span>
                                            <button type="button" class="btn btn-primary ms-3" id="showLoginFormBtn">
                                                <i class="fas fa-sign-in-alt fa-flip-horizontal me-1"></i>تسجيل الدخول
                                            </button>
                                        </div>
                                        <!-- نموذج تسجيل الدخول (مخفي افتراضياً) -->
                                        <div id="loginFormContainer" class="card p-4 my-3 shadow-sm border border-primary" style="max-width: 400px; margin: 0 auto; display: none;">
                                            <form id="loginForm" method="POST" action="{{ route('login') }}" autocomplete="off">
                                                @csrf
                                                <div class="mb-3">
                                                    <label for="login_id_number" class="form-label">رقم الهوية</label>
                                                    <input type="text" class="form-control" id="login_id_number" name="login_id_number" maxlength="20" required pattern="[0-9]+">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="login_password" class="form-label">كلمة المرور (4 أرقام)</label>
                                                    <input type="password" class="form-control" id="login_password" name="login_password" maxlength="4" minlength="4" required pattern="\d{4}">
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
                                                        loginFormContainer.style.display = loginFormContainer.style.display === 'none' ? 'block' : 'none';
                                                    });
                                                }
                                            });
                                        </script>
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
                                <!-- Attachments Tab -->
                               @include('user.generalRegistration.component.attachment')
                                <!-- نهاية بوابة المرفقات -->

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

@push('scriptsCodeUserRegistration')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.querySelector('input[name="user_password"]');
            const screenshotBtn = document.getElementById('screenshotPasswordBtn');
            if (screenshotBtn && passwordInput) {
                screenshotBtn.addEventListener('click', function() {
                    html2canvas(passwordInput.closest('.position-relative')).then(function(canvas) {
                        const link = document.createElement('a');
                        link.download = 'password_screenshot.png';
                        link.href = canvas.toDataURL();
                        link.click();
                    });
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.querySelector('input[name="user_password"]');
            const passwordConfirmInput = document.querySelector('input[name="user_password_confirmation"]');
            const screenshotBtn = document.getElementById('screenshotPasswordBtn');
            const pulseHint = document.querySelector('.screenshot-hint');
            let pulseActive = false;

            function checkPasswordMatch() {
                const pass = passwordInput.value;
                const passConfirm = passwordConfirmInput.value;
                if (/^\d{4}$/.test(pass) && pass === passConfirm) {
                    if (!pulseActive) {
                        screenshotBtn.classList.add('pulse-active');
                        if (pulseHint) pulseHint.classList.remove('d-none');
                        pulseActive = true;
                    }
                } else {
                    screenshotBtn.classList.remove('pulse-active');
                    if (pulseHint) pulseHint.classList.add('d-none');
                    pulseActive = false;
                }
            }

            if (passwordInput && passwordConfirmInput && screenshotBtn) {
                passwordInput.addEventListener('input', checkPasswordMatch);
                passwordConfirmInput.addEventListener('input', checkPasswordMatch);
                // عند الضغط على الكاميرا أخفِ النبض والتلميح
                screenshotBtn.addEventListener('click', function() {
                    screenshotBtn.classList.remove('pulse-active');
                    if (pulseHint) pulseHint.classList.add('d-none');
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const saveBtn = document.getElementById('saveToGoogleBtn');
            const idInput = document.getElementById('data_id_number') || document.querySelector(
                'input[name="data_id_number"]');
            const passInput = document.querySelector('input[name="user_password"]');
            if (saveBtn && idInput && passInput) {
                saveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!idInput.value) {
                        idInput.focus();
                        // استبدال التنبيه التقليدي بـ Swal.fire
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال رقم الهوية أولاً قبل حفظ كلمة المرور في جوجل.'
                        });
                        return;
                    }
                    if (!passInput.value) {
                        passInput.focus();
                        // استبدال التنبيه التقليدي بـ Swal.fire
                        Swal.fire({
                            icon: 'warning',
                            title: 'تنبيه',
                            text: 'يرجى إدخال كلمة المرور أولاً قبل الحفظ.'
                        });
                        return;
                    }
                    if (window.PasswordCredential) {
                        if (confirm(
                                'سيتم حفظ رقم الهوية كاسم مستخدم وكلمة المرور في مدير كلمات المرور في جوجل. هل تريد المتابعة؟'
                            )) {
                            const cred = new window.PasswordCredential({
                                id: idInput.value,
                                password: passInput.value,
                                name: idInput.value
                            });
                            navigator.credentials.store(cred).then(function() {
                                // استبدال التنبيه التقليدي بـ Swal.fire
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم',
                                    text: 'تم حفظ كلمة المرور في مدير كلمات المرور في المتصفح (جوجل).'
                                });
                            });
                        }
                    } else {
                        // استبدال التنبيه التقليدي بـ Swal.fire
                        Swal.fire({
                            icon: 'info',
                            title: 'معلومات',
                            text: 'هذه الميزة مدعومة فقط في بعض المتصفحات مثل جوجل كروم.'
                        });
                    }
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // دعم جميع بوابات رفع الملفات (بما فيها المتوفين)
            const docTypeSelects = document.querySelectorAll('.mainDocumentTypeSelect');
            docTypeSelects.forEach(function(docTypeSelect) {
                docTypeSelect.addEventListener('change', function() {
                    // ابحث عن input[type="file"] القريب بعد الـ select مباشرة (DOM traversal)
                    let fileInput = null;
                    let next = docTypeSelect.nextElementSibling;
                    while (next) {
                        if (next.classList && next.classList.contains('mainDocumentFileInput')) {
                            fileInput = next;
                            break;
                        }
                        // إذا كان العنصر عبارة عن div أو مجموعة، ابحث داخله
                        if (next.querySelector) {
                            let innerInput = next.querySelector('.mainDocumentFileInput');
                            if (innerInput) {
                                fileInput = innerInput;
                                break;
                            }
                        }
                        next = next.nextElementSibling;
                    }
                    if (!fileInput) return;
                    if (docTypeSelect.value) {
                        fileInput.style.display = '';
                        fileInput.value = '';
                        fileInput.click();
                    } else {
                        fileInput.style.display = 'none';
                    }
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة مسافة أسفل تبويب عرض المعلومات المدخلة
            const reviewPane = document.getElementById('review');
            if (reviewPane && !reviewPane.querySelector('#didding')) {
                const diddingDiv = document.createElement('div');
                diddingDiv.id = 'didding';
                diddingDiv.style.paddingBottom = '80px';
                reviewPane.appendChild(diddingDiv);
            }
        });
    </script>
@endpush

<style>
    .screenshot-pulse-btn {
        overflow: visible;
    }

    .pulse-circle {
        position: absolute;
        left: 50%;
        top: 50%;
        width: 38px;
        height: 38px;
        background: rgba(13, 110, 253, 0.15);
        border-radius: 50%;
        transform: translate(-50%, -50%) scale(1);
        z-index: 0;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
    }

    .screenshot-pulse-btn.pulse-active .pulse-circle {
        animation: pulse-blue 1.2s infinite;
        opacity: 1;
    }

    @keyframes pulse-blue {
        0% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 0.7;
        }

        70% {
            transform: translate(-50%, -50%) scale(1.5);
            opacity: 0.2;
        }

        100% {
            transform: translate(-50%, -50%) scale(2);
            opacity: 0;
        }
    }
</style>
