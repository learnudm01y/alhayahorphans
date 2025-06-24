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
                                <!-- Basic Info Tab -->
                                <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
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
