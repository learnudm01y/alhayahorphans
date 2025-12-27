<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شكراً لتعاونكم - جمعية الحياة</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Amiri', serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            margin-bottom: 40px;
            animation: bounceIn 1s ease-out;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        .logo img {
            max-width: 200px;
            height: auto;
        }

        .message {
            font-size: 48px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
            line-height: 1.6;
            letter-spacing: 2px;
        }

        .sub-message {
            font-size: 36px;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .success-icon {
            width: 120px;
            height: 120px;
            margin: 30px auto;
            position: relative;
            animation: scaleIn 0.5s ease-out;
        }

        .success-circle {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }

        .success-checkmark {
            color: white;
            font-size: 64px;
            font-weight: bold;
            animation: checkPop 0.6s ease-out 0.3s both;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes checkPop {
            0% {
                transform: scale(0) rotate(0deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.2) rotate(10deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .footer {
            margin-top: 40px;
            font-size: 18px;
            color: #718096;
        }

        @media (max-width: 768px) {
            .container {
                padding: 40px 20px;
            }

            .message {
                font-size: 32px;
            }

            .sub-message {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="{{ asset('uploads/logo01.png') }}" alt="جمعية الحياة">
        </div>

        <div class="success-icon">
            <div class="success-circle">
                <div class="success-checkmark">✓</div>
            </div>
        </div>

        <h1 class="message">شكراً لحسن تعاونكم</h1>
        <h2 class="sub-message">جمعية الحياة</h2>

        <div class="footer">
            <p>تم استلام طلبكم بنجاح</p>
            <p>سيتم التواصل معكم في أقرب وقت</p>
        </div>
    </div>
</body>
</html>
