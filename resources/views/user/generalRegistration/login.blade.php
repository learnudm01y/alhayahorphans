
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول للبوابة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <meta name="color-scheme" content="light dark">
    <style>
        body {
            background: linear-gradient(120deg, #f8fafc 60%, #e3eafc 100%);
            min-height: 100vh;
            font-family: 'Cairo', Arial, Tahoma, sans-serif;
            overflow-x: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 500px;
            width: 90vw;
            margin: 0 auto;
            border-radius: 22px;
            box-shadow: 0 8px 32px rgba(25, 118, 210, 0.13), 0 1.5px 8px rgba(0,0,0,0.07);
            background: rgba(255,255,255,0.98);
            padding: 2.7rem 2.5rem 2.2rem 2.5rem;
            position: relative;
            animation: cardFadeIn 1.1s cubic-bezier(.68,-0.55,.27,1.55);
        }
        @keyframes cardFadeIn {
            0% { opacity: 0; transform: translateY(60px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .login-logo {
            height: 64px;
            margin-bottom: 18px;
            animation: logoBounce 1.2s cubic-bezier(.68,-0.55,.27,1.55);
        }
        @keyframes logoBounce {
            0% { transform: scale(0.7) rotate(-10deg); opacity: 0; }
            60% { transform: scale(1.15) rotate(8deg); opacity: 1; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }
        .login-title {
            font-weight: bold;
            font-size: 2.1rem;
            color: #1976d2;
            letter-spacing: 1px;
            margin-bottom: 0.2rem;
            animation: fadeInUp 1.2s 0.2s both;
        }
        .login-desc {
            color: #607d8b;
            font-size: 1.13rem;
            margin-bottom: 1.5rem;
            animation: fadeInUp 1.2s 0.35s both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .form-label { color: #222; font-weight: 500; }
        .form-control,
        input.form-control,
        input.form-control-lg,
        input[type="text"],
        input[type="password"] {
            border-radius: 12px !important;
            font-size: 1.13rem !important;
            background: #fff !important;
            color: #222 !important;
            border: 1.5px solid #e3eafc !important;
            box-shadow: none !important;
            outline: none !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
        }
        /* إزالة لون الخلفية الافتراضي من Bootstrap لجميع الحقول */
        .form-control,
        .form-control-lg,
        input.form-control,
        input.form-control-lg,
        input[type="text"],
        input[type="password"] {
            background-color: #fff !important;
            background: #fff !important;
            color: #222 !important;
        }
        /* إزالة تأثيرات الـ autofill من المتصفح */
        input:-webkit-autofill,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px #fff inset !important;
            box-shadow: 0 0 0 1000px #fff inset !important;
            background-color: #fff !important;
            color: #222 !important;
            -webkit-text-fill-color: #222 !important;
            caret-color: #1976d2 !important;
            transition: background-color 5000s ease-in-out 0s !important;
        }
        .form-control:disabled, .form-control[readonly] {
            background: #f8fafc !important;
            color: #b0b8c1 !important;
        }
        .form-control::placeholder {
            color: #b0b8c1 !important;
            opacity: 1;
        }
        .form-control:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 2px #1976d233;
        }
        .btn-login {
            background: linear-gradient(90deg, #1976d2 60%, #42a5f5 100%);
            color: #fff;
            font-size: 1.2rem;
            border-radius: 12px;
            font-weight: bold;
            padding: 0.75rem 0;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(25, 118, 210, 0.08);
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%);
            box-shadow: 0 4px 16px rgba(25, 118, 210, 0.13);
        }
        .login-footer {
            margin-top: 1.5rem;
            color: #888;
            font-size: 0.98rem;
            animation: fadeInUp 1.2s 0.6s both;
        }
        .register-link {
            display: block;
            margin-top: 1.2rem;
            text-align: center;
            font-size: 1.08rem;
            color: #1976d2;
            font-weight: bold;
            text-decoration: none;
            transition: color 0.2s;
            animation: fadeInUp 1.2s 0.5s both;
        }
        .register-link:hover {
            color: #0d47a1;
            text-decoration: underline;
        }
        .animated-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .bubble {
            position: absolute;
            border-radius: 50%;
            opacity: 0.13;
            background: linear-gradient(120deg, #1976d2 60%, #42a5f5 100%);
            animation: floatBubble 8s infinite ease-in-out;
        }
        @keyframes floatBubble {
            0% { transform: translateY(0) scale(1); opacity: 0.13; }
            50% { transform: translateY(-60px) scale(1.12); opacity: 0.22; }
            100% { transform: translateY(0) scale(1); opacity: 0.13; }
        }
        @media (max-width: 576px) {
            .login-card { padding: 1.2rem 0.5rem; margin: 2vh auto; }
            .login-title { font-size: 1.3rem; }
        }
        @media (max-width: 350px) {
            .login-card { padding: 0.5rem 0.1rem; }
        }
    </style>
</head>
<body>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // عرض رسالة نجاح إذا تم التحويل من صفحة الحفظ
        document.addEventListener('DOMContentLoaded', function() {
            // ابحث عن باراميتر success في الرابط
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                setTimeout(function() {
                    toastr.options = {
                        "closeButton": true,
                        "progressBar": true,
                        "positionClass": "toast-top-center",
                        "timeOut": "3500",
                        "toastClass": "custom-toastr-success toast-success"
                    };
                    toastr.success('تم حفظ السجل بنجاح! يمكنك الآن تسجيل الدخول.');
                }, 400);
            }
        });
    </script>
    <style>
    /* تخصيص لون خلفية Toastr للرسائل الناجحة */
    .custom-toastr-success {
        background-color: #388e3c !important; /* أخضر غامق واضح */
        color: #fff !important;
        border-radius: 10px !important;
        font-size: 0.65rem !important;
        box-shadow: 0 4px 18px #388e3c33;
        margin-top: 5rem !important;
    }
    </style>
    <div class="bubbles">
        <div class="bubble" style="left: 10vw; width: 60px; height: 60px; background: #1976d2; animation-duration: 13s;"></div>
        <div class="bubble" style="left: 30vw; width: 38px; height: 38px; background: #42a5f5; animation-duration: 11s; animation-delay: 2s;"></div>
        <div class="bubble" style="left: 60vw; width: 80px; height: 80px; background: #1976d2; animation-duration: 15s; animation-delay: 1.5s;"></div>
        <div class="bubble" style="left: 80vw; width: 50px; height: 50px; background: #42a5f5; animation-duration: 12s; animation-delay: 3s;"></div>
        <div class="bubble" style="left: 90vw; width: 30px; height: 30px; background: #1976d2; animation-duration: 10s; animation-delay: 4s;"></div>
    </div>
    <!-- Animated Shapes -->
    <div class="animated-shape" style="top: -30px; left: -30px;"></div>
    <div class="animated-shape shape2"></div>
    <div class="animated-shape shape3"></div>
    <div class="login-card">
        <div class="text-center mb-4">
            <img src="{{ asset('uploads/avatar.jpg') }}" alt="Logo" class="login-logo">
            <div class="login-title">تسجيل الدخول للبوابة</div>
            <div class="login-desc">يرجى إدخال رقم هوية المكفول ورقم الملف الداخلي</div>
        </div>
        <form id="loginForm" method="POST" action="{{ route('user.login') }}" autocomplete="off">
            @csrf
            <div class="mb-3">
                <label for="login_email" class="form-label">رقم هوية المكفول</label>
                <input type="text" class="form-control form-control-lg" id="login_email" name="login_email" required autofocus placeholder="أدخل رقم هوية الشخص المكفول">
            </div>
            <div class="mb-3">
                <label for="login_password" class="form-label">رقم الملف الداخلي</label>
                <input type="password" class="form-control form-control-lg" id="login_password" name="login_password" required placeholder="أدخل رقم الملف الداخلي">
            </div>
            <button type="submit" class="btn btn-login w-100">دخول</button>
        </form>
        <a href="/users/generalRegistration" class="register-link">ليس لديك حساب؟ سجل الآن</a>
        <div class="login-footer text-center">
            <svg width="22" height="22" fill="#1976d2" style="vertical-align:middle; margin-left:4px;" viewBox="0 0 16 16"><path d="M8 1a4 4 0 0 1 4 4v2.09c.58.206 1 .762 1 1.41v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4c0-.648.42-1.204 1-1.41V5a4 4 0 0 1 4-4zm0 1a3 3 0 0 0-3 3v2h6V5a3 3 0 0 0-3-3zm-4 7v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1z"/></svg>
            جميع المعلومات ستُعامل بسرية تامة
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animate bubbles on load
        document.querySelectorAll('.bubble').forEach(function(bubble, i) {
            bubble.style.top = (80 + Math.random() * 10) + 'vh';
        });
    </script>
</body>
</html>
