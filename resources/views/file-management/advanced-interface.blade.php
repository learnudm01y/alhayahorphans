@extends('admin.dashboard.toolbars.index')
@section('content')
    <!-- إضافة CSRF token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- إضافة animate.css للحصول على انيميشن أفضل -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- إضافة النظام الجديد لقياس سرعة الإنترنت -->
    <script src="{{ asset('js/real-speed-test.js') }}"></script>
    <style>
        .file-drop-zone {
            border: 3px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .file-drop-zone.drag-over {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            transform: scale(1.02);
        }

        .processing-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .file-preview {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
        }

        .file-preview:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .progress-ring {
            width: 60px;
            height: 60px;
        }

        .progress-ring circle {
            stroke: #007bff;
            stroke-width: 4;
            fill: transparent;
            stroke-dasharray: 188.4;
            stroke-dashoffset: 188.4;
            transition: stroke-dashoffset 0.3s;
        }

        .smart-upload-controls {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .file-type-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .file-type-filter .btn {
            border-radius: 20px;
            padding: 8px 16px;
        }

        .analytics-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .small-breakdown {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .file-types-breakdown {
            font-size: 0.75rem;
        }

        .small-breakdown hr {
            margin: 0.25rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Responsive Design Improvements */
        @media (max-width: 768px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }

            .smart-upload-controls {
                padding: 15px;
                margin-bottom: 15px;
            }

            .analytics-card {
                padding: 15px;
                margin-bottom: 15px;
            }

            .analytics-card h3 {
                font-size: 1.5rem;
            }

            .analytics-card h6 {
                font-size: 0.9rem;
            }

            .small-breakdown {
                font-size: 0.65rem;
            }

            .file-types-breakdown {
                font-size: 0.65rem;
            }

            .btn-group-responsive .btn {
                flex: 1 1 auto;
                min-width: 100px;
                margin-bottom: 5px;
                font-size: 0.85rem;
            }

            .d-flex.gap-2 {
                flex-direction: column;
                gap: 0.5rem !important;
            }

            .file-type-filter {
                justify-content: center;
                flex-wrap: wrap;
                gap: 8px;
            }

            .card-header .row {
                align-items: stretch !important;
            }
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 5px;
                padding-right: 5px;
            }

            .analytics-card {
                padding: 12px;
            }

            .analytics-card h3 {
                font-size: 1.25rem;
            }

            .analytics-card .fa-2x {
                font-size: 1.5em;
            }

            .small-breakdown {
                font-size: 0.6rem;
            }

            .file-types-breakdown {
                font-size: 0.6rem;
            }

            .analytics-card h6 {
                font-size: 0.8rem;
                margin-bottom: 0.5rem;
            }

            .card-header {
                padding: 10px 15px;
            }

            .card-body {
                padding: 15px;
            }

            .btn-lg {
                padding: 8px 16px;
                font-size: 1rem;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 0.8rem;
            }

            /* تحسين عرض النصوص في الجوال */
            h1.h3 {
                font-size: 1.5rem;
            }

            .form-label {
                font-size: 0.9rem;
            }

            .form-check-label {
                font-size: 0.85rem;
            }
        }

        /* تحسينات إضافية للتوافق مع الأنظمة الخارجية */
        .file-management-container {
            /* ورث الخصائص من النظام الخارجي */
            font-family: inherit;
            color: inherit;
            direction: inherit;
        }

        .file-management-container .btn {
            /* تأكد من توافق الأزرار */
            border-radius: inherit;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .file-management-container .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .file-management-container .btn-lg {
            padding: 12px 24px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .file-management-container .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        /* تحسينات الأزرار المخصصة */
        .header-buttons .btn {
            margin-bottom: 8px;
            min-width: 120px;
        }

        @media (max-width: 768px) {
            .header-buttons .btn {
                min-width: 80px;
                font-size: 0.9rem;
            }
        }

        .file-management-container .card {
            /* تأكد من توافق البطاقات */
            border-radius: inherit;
            box-shadow: inherit;
        }

        /* Override للخصائص المهمة فقط */
        .analytics-card {
            border-radius: 15px !important;
        }

        .smart-upload-controls {
            border-radius: 10px !important;
        }

        .cloud-sync-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
        }

        .cloud-sync-status.synced {
            background: #d4edda;
            color: #155724;
        }

        .cloud-sync-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .cloud-sync-status.failed {
            background: #f8d7da;
            color: #721c24;
        }

        /* تحسينات أزرار card header */
        .card-header .btn {
            border-radius: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-header .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        /* تحسينات شريط التقدم */
        .progress {
            background: linear-gradient(90deg, #0D47A1 0%, #1565C0 100%);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
        }

        .progress-bar {
            background: linear-gradient(45deg, #0D47A1, #1565C0, #0277BD, #01579B);
            background-size: 400% 100%;
            animation: rainbow-flow 3s infinite linear;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(13, 71, 161, 0.6);
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
            animation: slide-shine 2s infinite;
        }

        @keyframes rainbow-flow {
            0% { background-position: 400% 0; }
            100% { background-position: -400% 0; }
        }

        @keyframes slide-shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* تحسينات العدادات */
        .h5 {
            transition: all 0.3s ease;
        }

        .h5:hover {
            transform: scale(1.05);
        }

        /* تحسين حالة الملفات */
        .badge {
            transition: all 0.2s ease;
        }

        .file-preview {
            transition: all 0.3s ease;
        }

        .file-preview.processing {
            animation: pulse-gentle 2s infinite;
        }

        @keyframes pulse-gentle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
            20%, 40%, 60%, 80% { transform: translateX(3px); }
        }

        @keyframes fadeIn {
            from { opacity: 0.5; }
            to { opacity: 1; }
        }

        /* Animation styles for progress section transitions */
        #uploadProgressSection {
            transition: opacity 0.5s ease, transform 0.3s ease;
        }

        .badge {
            transition: all 0.3s ease;
        }

        /* Pulse animation for completed files */
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 15px 5px rgba(40, 167, 69, 0.3);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        /* Processing animation for files being processed */
        @keyframes pulse-processing {
            0% {
                background: linear-gradient(45deg, #007bff, #0056b3) !important;
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7);
            }
            50% {
                background: linear-gradient(45deg, #0056b3, #007bff) !important;
                box-shadow: 0 0 10px 3px rgba(0, 123, 255, 0.4);
            }
            100% {
                background: linear-gradient(45deg, #007bff, #0056b3) !important;
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
            }
        }

        .card-header .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }

        .card-header .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
            border: none;
        }

        .card-header .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e91e63 100%);
            border: none;
        }

        /* إجبار الأزرار على الظهور في أقصى اليسار */
        .card-header .d-flex {
            justify-content: flex-start !important;
            text-align: start !important;
        }

        .card-header .d-flex.gap-2 {
            direction: ltr !important;
            justify-content: flex-start !important;
        }

        /* في الاتجاه العربي، نريد الأزرار في أقصى اليسار */
        [dir="rtl"] .card-header .d-flex {
            justify-content: flex-end !important;
            direction: rtl !important;
        }

        [dir="rtl"] .card-header .d-flex.gap-2 {
            justify-content: flex-end !important;
            direction: rtl !important;
        }

        /* تحسينات التجاوب للأزرار */
        @media (max-width: 768px) {
            .card-header .btn {
                min-width: 60px;
                font-size: 0.8rem;
                padding: 6px 12px;
            }
        }

        @media (max-width: 576px) {
            .card-header .btn {
                min-width: 50px;
                font-size: 0.75rem;
                padding: 4px 8px;
            }
        }

        /* كارت قياس سرعة الإنترنت - التصميم المحسن بالأبيض */
        .speed-test-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 24px;
            padding: 20px 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .speed-test-card.download-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .speed-test-card.upload-card {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .speed-test-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
        }

        .speed-test-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse-enhanced 5s infinite;
        }

        @keyframes pulse-enhanced {
            0% { transform: scale(0.8); opacity: 0.6; }
            50% { transform: scale(1.2); opacity: 0.2; }
            100% { transform: scale(0.8); opacity: 0.6; }
        }

        .speed-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            z-index: 10;
            position: relative;
        }

        .speed-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .speed-card-title i {
            font-size: 1.3rem;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .speed-card-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-grow: 1;
            z-index: 10;
            position: relative;
        }

        .speed-gauge-container {
            position: relative;
            width: 90px;
            height: 90px;
            margin-right: 18px;
        }

        .speed-gauge {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: conic-gradient(
                from 0deg,
                #ff6b6b 0deg 72deg,
                #ffa726 72deg 144deg,
                #42a5f5 144deg 216deg,
                #66bb6a 216deg 288deg,
                #4fc3f7 288deg 360deg
            );
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .speed-gauge::before {
            content: '';
            position: absolute;
            width: 55px;
            height: 55px;
            background: radial-gradient(circle, #ffffff 0%, #f8f9fa 100%);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: inset 0 3px 8px rgba(0, 0, 0, 0.1);
        }

        .speed-gauge-needle {
            position: absolute;
            width: 3px;
            height: 32px;
            background: linear-gradient(to top, #2c3e50 0%, #34495e 100%);
            top: 50%;
            left: 50%;
            transform-origin: bottom center;
            transform: translate(-50%, -100%) rotate(0deg);
            border-radius: 2px;
            transition: transform 1s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 10;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .speed-gauge-needle::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            border-radius: 50%;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .speed-display {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 15;
            background: rgba(255, 255, 255, 0.95);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #2c3e50;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .speed-value {
            font-size: 0.8rem;
            line-height: 1;
            color: #2c3e50;
            font-weight: 800;
        }

        .speed-unit {
            font-size: 0.5rem;
            opacity: 0.8;
            color: #7f8c8d;
            margin-top: 2px;
        }

        .speed-info {
            flex-grow: 1;
            padding-left: 40px;
            color: #2c3e50;
        }

        .speed-status {
            font-size: 0.9rem;
            margin-bottom: 10px;
            opacity: 1;
            font-weight: 700;
            color: #2c3e50;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .speed-test-btn {
            background: rgba(255, 255, 255, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.5);
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s ease;
            backdrop-filter: blur(15px);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .speed-test-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            border-color: rgba(255, 255, 255, 0.7);
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        .speed-test-btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .speed-test-btn i {
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .speed-details {
            font-size: 0.8rem;
            opacity: 1;
            margin-top: 8px;
            color: #2c3e50;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }

        .speed-progress {
            height: 6px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .speed-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
            border-radius: 3px;
            transition: width 0.8s ease;
            width: 0%;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        /* التجاوب مع الأجهزة المحسن */
        @media (max-width: 768px) {
            .speed-test-card {
                padding: 16px 20px;
                min-height: 120px;
            }

            .speed-gauge-container {
                width: 80px;
                height: 80px;
                margin-right: 12px;
            }

            .speed-gauge {
                width: 80px;
                height: 80px;
            }

            .speed-gauge::before {
                width: 60px;
                height: 60px;
            }

            .speed-display {
                width: 50px;
                height: 50px;
            }

            .speed-value {
                font-size: 0.9rem;
            }

            .speed-card-title {
                font-size: 1rem;
                color: #ffffff;
            }

            .speed-card-title i {
                color: #ffffff;
            }

            .speed-status {
                color: #ffffff;
            }

            .speed-details {
                color: #ffffff;
            }

            .speed-test-btn {
                color: #ffffff;
            }

            .speed-test-btn i {
                color: #ffffff;
            }
        }

        @media (max-width: 576px) {
            .speed-test-card {
                padding: 14px 18px;
                min-height: 110px;
            }

            .speed-gauge-container {
                width: 70px;
                height: 70px;
                margin-right: 10px;
            }

            .speed-gauge {
                width: 70px;
                height: 70px;
            }

            .speed-gauge::before {
                width: 40px;
                height: 40px;
            }

            .speed-display {
                width: 30px;
                height: 30px;
            }

            .speed-value {
                font-size: 0.6rem;
            }

            .speed-card-title {
                font-size: 0.9rem;
                color: #ffffff;
            }

            .speed-card-title i {
                color: #ffffff;
            }

            .speed-status {
                color: #2c3e50;
                font-size: 0.7rem;
            }

            .speed-details {
                color: #2c3e50;
                font-size: 0.6rem;
            }

            .speed-test-btn {
                color: #ffffff;
                font-size: 0.65rem;
                padding: 4px 8px;
            }

            .speed-test-btn i {
                color: #ffffff;
            }

            .speed-info {
                padding-left: 30px;
                color: #2c3e50;
            }
        }

        /* SweetAlert تحسينات مخصصة للملفات المكررة */
        .duplicate-alert-popup {
            border-radius: 15px !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
        }

        .duplicate-alert-title {
            color: #e74c3c !important;
            font-weight: bold !important;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1) !important;
        }

        .duplicate-alert-html {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        }

        .duplicate-alert-content .btn {
            transition: all 0.3s ease !important;
            font-weight: 600 !important;
            border: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
        }

        .duplicate-alert-content .btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 16px rgba(0,0,0,0.25) !important;
        }

        .duplicate-alert-content .btn-warning:hover {
            background: linear-gradient(45deg, #f39c12, #e67e22) !important;
        }

        .duplicate-alert-content .btn-success:hover {
            background: linear-gradient(45deg, #27ae60, #2ecc71) !important;
        }

        .duplicate-alert-content .alert {
            background: linear-gradient(135deg, #fff3cd 0%, #fef9e7 100%) !important;
            border: 1px solid #ffc107 !important;
        }

        .duplicate-alert-content .badge {
            font-size: 14px !important;
            padding: 8px 12px !important;
            border-radius: 20px !important;
        }

        /* انيميشن CSS للـ SweetAlert */
        .swal2-popup.duplicate-alert-popup {
            animation: duplicateAlertSlideIn 0.5s ease-out;
        }

        @keyframes duplicateAlertSlideIn {
            0% {
                transform: translateY(-50px) scale(0.9);
                opacity: 0;
            }
            100% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        /* تحسينات الأيقونة في SweetAlert */
        .swal2-icon.swal2-warning .swal2-icon-content {
            font-size: 3.5rem !important;
            font-weight: bold !important;
        }

        .swal2-icon.swal2-error .swal2-icon-content {
            font-size: 3.5rem !important;
            font-weight: bold !important;
        }

        /* انيميشن خاص للملفات المكررة */
        @keyframes pulse-duplicate {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
            }
            50% {
                transform: scale(1.02);
                box-shadow: 0 4px 16px rgba(255, 193, 7, 0.6);
            }
        }

        .duplicate-file {
            position: relative;
            overflow: hidden;
        }

        .duplicate-file::before {
            content: '⚠️';
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 1.2rem;
            background: rgba(255, 193, 7, 0.9);
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            animation: bounce 2s infinite;
        }

        .duplicate-file:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4) !important;
        }

        /* تحسين انيميشن pulse للملفات المكررة */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
        }

        /* انيميشن shake للفشل */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
            20%, 40%, 60%, 80% { transform: translateX(2px); }
        }

        /* انيميشن bounce للنجاح */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* انيميشن fadeIn */
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        /* انيميشن pulse-processing للمعالجة */
        @keyframes pulse-processing {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
       <!-- تحسينات للأجهزة المحمولة -->
    <style>
                @media (max-width: 992px) {
                    .speed-test-card {
                        width: 100% !important;
                        max-width: 280px;
                        margin-bottom: 10px;
                    }

                    .d-flex.gap-2.flex-wrap {
                        justify-content: center !important;
                    }
                }

                @media (max-width: 768px) {
                    .speed-test-card {
                        width: 100% !important;
                        max-width: none;
                        margin-bottom: 10px;
                    }

                    .speed-card-content {
                        flex-direction: column;
                        text-align: center;
                    }

                    .speed-gauge-container {
                        margin-right: 0;
                        margin-bottom: 10px;
                        align-self: center;
                    }

                    .speed-info {
                        padding-left: 0;
                    }
                }
    </style>
    <div class="container-fluid py-4 file-management-container">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-lg-6 col-md-6 col-12">
                <h1 class="h3 mb-0">
                    <i class="fas fa-cloud-upload-alt text-primary me-2"></i>
                    نظام إدارة الملفات المتقدم
                </h1>
                <p class="text-muted">رفع وإدارة ملفات متعددة الأنواع مع التكامل السحابي</p>
            </div>
            <div class="col-lg-6 col-md-6 col-12">
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                </div>
            </div>

        </div>

        <!-- Analytics Dashboard - Top Position -->
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3">
                <div class="analytics-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <h6 class="mb-2">إجمالي الملفات</h6>
                            <h3 class="mb-2" id="totalFilesCount">0</h3>
                            <div class="small-breakdown">
                                <div class="d-flex justify-content-between">
                                    <span>الأساسية:</span>
                                    <span id="attachmentsCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>المحسنة:</span>
                                    <span id="enhancedCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>المكررة:</span>
                                    <span id="duplicatesCount">0</span>
                                </div>
                                <hr class="my-1" style="border-color: rgba(255,255,255,0.3);">
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>الإجمالي:</span>
                                    <span id="grandTotalFiles">0</span>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-file fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3">
                <div class="analytics-card"
                    style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <h6 class="mb-2">حجم التخزين</h6>
                            <h3 class="mb-2" id="totalStorageSize">0 MB</h3>
                            <div class="small-breakdown">
                                <div class="d-flex justify-content-between">
                                    <span>الأساسية:</span>
                                    <span id="attachmentsSize">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>المحسنة:</span>
                                    <span id="enhancedSize">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>المكررة:</span>
                                    <span id="duplicatesSize">0</span>
                                </div>
                                <hr class="my-1" style="border-color: rgba(255,255,255,0.3);">
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>الإجمالي:</span>
                                    <span id="grandTotalSize">0</span>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-hdd fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3">
                <div class="analytics-card"
                    style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <h6 class="mb-2">ملفات اليوم</h6>
                            <h3 class="mb-2" id="todayFilesCount">0</h3>
                            <div class="small-breakdown">
                                <div class="d-flex justify-content-between">
                                    <span>الأساسية:</span>
                                    <span id="todayAttachments">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>المحسنة:</span>
                                    <span id="todayEnhanced">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>المكررة:</span>
                                    <span id="todayDuplicates">0</span>
                                </div>
                                <hr class="my-1" style="border-color: rgba(0,0,0,0.2);">
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>الإجمالي:</span>
                                    <span id="grandTotalToday">0</span>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-calendar-day fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-3">
                <div class="analytics-card"
                    style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <h6 class="mb-2">أنواع الملفات المحسنة</h6>
                            <div class="file-types-breakdown">
                                <div class="d-flex justify-content-between">
                                    <span>📷 صور:</span>
                                    <span id="imagesCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>📄 PDF:</span>
                                    <span id="pdfCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>📊 Excel:</span>
                                    <span id="excelCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>📝 Word:</span>
                                    <span id="wordCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>🗜️ أرشيف:</span>
                                    <span id="archiveCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>📎 أخرى:</span>
                                    <span id="otherCount">0</span>
                                </div>
                            </div>
                        </div>
                        <i class="fas fa-chart-pie fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Smart Upload Controls -->
        <div class="smart-upload-controls">
            <!-- Record Number (Hidden) and Person ID Row -->
            <div class="row mb-4">
                <div class="col-md-6" style="display: none;">
                    <label for="recordNumber" class="form-label fw-bold">
                        <i class="fas fa-hashtag text-primary"></i> رقم الملف
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="recordNumber" placeholder="سيتم إنشاؤه تلقائياً">
                        <button class="btn btn-primary" type="button" id="generateRecordBtn">
                            <i class="fas fa-magic"></i> إنشاء
                        </button>
                    </div>
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> يتم التوليد التلقائي حسب آخر رقم في قاعدة البيانات
                    </div>
                </div>
            </div>

            <!-- Processing Options and Controls Row -->
            <div class="row mb-4">
                <div class="col-lg-6 col-md-8 mb-3">
                    <label class="form-label fw-bold">
                        <i class="fas fa-cogs text-success"></i> خيارات المعالجة
                    </label>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="compressImages" checked>
                            <label class="form-check-label" for="compressImages">
                                <i class="fas fa-compress-arrows-alt"></i> ضغط الصور
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoOrganize" checked>
                            <label class="form-check-label" for="autoOrganize">
                                <i class="fas fa-folder-tree"></i> تنظيم تلقائي
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cloudSync">
                            <label class="form-check-label" for="cloudSync">
                                <i class="fas fa-cloud"></i> مزامنة سحابية
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-4">
                    <label class="form-label fw-bold">
                        <i class="fas fa-tools text-warning"></i> أدوات إضافية
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-warning btn-sm shadow-sm" id="showDuplicateFilesBtn">
                            <i class="fas fa-clone me-1"></i>
                            <span class="d-none d-sm-inline">الملفات المكررة</span>
                            <span class="d-sm-none">مكررة</span>
                        </button>
                        <button type="button" class="btn btn-success btn-sm shadow-sm" id="excel-gateway-btn" title="بوابة Excel المتخصصة">
                            <i class="fas fa-table me-1"></i>
                            <span class="d-none d-sm-inline">بوابة Excel</span>
                            <span class="d-sm-none">Excel</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Excel Import Options (Conditional) -->
        <div class="row mb-3" id="excelImportSection" style="display: none;">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-file-excel"></i> خيارات استيراد ملفات Excel
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="enableExcelImport">
                                    <label class="form-check-label fw-bold" for="enableExcelImport">
                                        <i class="fas fa-database"></i> استيراد البيانات إلى قاعدة البيانات
                                    </label>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    اختياري: يمكن رفع ملفات Excel للتخزين فقط دون استيراد البيانات
                                </div>
                            </div>

                            <div class="col-md-6" id="excelTargetOptions" style="display: none;">
                                <label for="targetTable" class="form-label fw-bold">
                                    <i class="fas fa-table"></i> الجدول المستهدف
                                </label>
                                <select class="form-select" id="targetTable">
                                    <option value="data">جدول البيانات الرئيسي</option>
                                    <option value="dead_people">سجلات المتوفين</option>
                                    <option value="guardian_bank_accounts">حسابات الأوصياء المصرفية</option>
                                    <option value="re_people">سجلات الهويات المعاد إصدارها</option>
                                </select>

                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-success btn-sm me-2"
                                        onclick="document.getElementById('excelFileInput').click()">
                                        <i class="fas fa-file-excel"></i> اختيار ملف Excel
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" id="previewExcelBtn">
                                        <i class="fas fa-eye"></i> معاينة البيانات
                                    </button>
                                </div>
                                <div id="excelFileStatus" class="small text-muted mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- File Type Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <label class="form-label fw-bold">
                    <i class="fas fa-filter text-info"></i> فلترة أنواع الملفات
                </label>
                <div class="btn-group-responsive w-100" role="group">
                    <div class="row g-2">
                        <div class="col-lg-2 col-md-4 col-6">
                            <input type="radio" class="btn-check" name="fileFilter" id="filterAll" value="all" checked>
                            <label class="btn btn-outline-primary w-100 shadow-sm" for="filterAll">
                                <i class="fas fa-th"></i> <span class="d-none d-sm-inline">جميع الملفات</span><span class="d-sm-none">الكل</span>
                            </label>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <input type="radio" class="btn-check" name="fileFilter" id="filterImages" value="image">
                            <label class="btn btn-outline-success w-100 shadow-sm" for="filterImages">
                                <i class="fas fa-image"></i> <span class="d-none d-sm-inline">صور</span><span class="d-sm-none">صور</span>
                            </label>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <input type="radio" class="btn-check" name="fileFilter" id="filterPDF" value="pdf">
                            <label class="btn btn-outline-danger w-100 shadow-sm" for="filterPDF">
                                <i class="fas fa-file-pdf"></i> <span class="d-none d-sm-inline">PDF</span><span class="d-sm-none">PDF</span>
                            </label>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <input type="radio" class="btn-check" name="fileFilter" id="filterExcel" value="excel">
                            <label class="btn btn-outline-info w-100 shadow-sm" for="filterExcel">
                                <i class="fas fa-file-excel"></i> <span class="d-none d-sm-inline">Excel</span><span class="d-sm-none">Excel</span>
                            </label>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <input type="radio" class="btn-check" name="fileFilter" id="filterArchive" value="archive">
                            <label class="btn btn-outline-warning w-100 shadow-sm" for="filterArchive">
                                <i class="fas fa-file-archive"></i> <span class="d-none d-sm-inline">مضغوط</span><span class="d-sm-none">ZIP</span>
                            </label>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <input type="radio" class="btn-check" name="fileFilter" id="filterOther" value="other">
                            <label class="btn btn-outline-secondary w-100 shadow-sm" for="filterOther">
                                <i class="fas fa-file"></i> <span class="d-none d-sm-inline">أخرى</span><span class="d-sm-none">أخرى</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <!-- Enhanced File Drop Zone -->
                <div class="row mt-4" style="display: none;">
                    <div class="col-12">
                        <div class="file-drop-zone" id="fileDropZone">
                            <div class="drop-zone-content text-center">
                                <i class="fas fa-cloud-upload-alt fa-5x text-primary mb-4"></i>
                                <h3 class="text-primary">اسحب وأفلت الملفات هنا</h3>
                                <p class="text-muted mb-4">أو انقر على الزر أدناه لاختيار الملفات</p>

                                <!-- File Upload Actions -->
                                <div class="d-flex justify-content-center gap-3 mb-3">
                                    {{-- <button type="button" class="btn btn-success btn-lg" id="startUploadBtn" style="display: none;">
                                <i class="fas fa-upload me-2"></i>بدء الرفع
                            </button> --}}
                                    <button type="button" class="btn btn-warning btn-lg" id="pauseUploadBtn"
                                        style="display: none;">
                                        <i class="fas fa-pause me-2"></i>إيقاف مؤقت
                                    </button>
                                </div>

                                <div class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    يدعم: الصور (JPG, PNG, GIF)، المستندات (PDF, Word)، Excel، والملفات المضغوطة (ZIP, RAR)
                                    <br>
                                    الحد الأقصى للملف: 1 جيجابايت
                                </div>

                                <!-- File Input Elements -->
                                <input type="file" id="fileInput" multiple class="d-none" accept="*/*">
                                <!-- مدخل المجلدات -->
                                <input type="file" id="folderInput" webkitdirectory directory multiple class="d-none">
                                <!-- مدخل ملف Excel للمجلدات -->
                                <input type="file" id="excelFileInput" class="d-none" accept=".xlsx,.xls,.csv">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div class="row mt-4" id="uploadProgressSection" style="display: none;">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tasks me-2"></i>تقدم الرفع
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3" style="height: 12px; border-radius: 8px; overflow: hidden;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-gradient"
                                        id="overallProgress" role="progressbar" style="width: 0%; transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                                </div>
                                <div class="text-center mb-3">
                                    <small class="text-muted fw-bold" id="progressText" style="transition: all 0.3s ease;">جاري التحضير...</small>
                                </div>
                                <div class="row text-center">
                                    <div class="col-2">
                                        <div class="h5 mb-0" id="totalFiles">0</div>
                                        <small class="text-muted">إجمالي الملفات</small>
                                    </div>
                                    <div class="col-2">
                                        <div class="h5 mb-0 text-success" id="completedFiles">0</div>
                                        <small class="text-muted">مكتملة</small>
                                    </div>
                                    <div class="col-2">
                                        <div class="h5 mb-0 text-warning" id="processingFiles">0</div>
                                        <small class="text-muted">قيد المعالجة</small>
                                    </div>
                                    <div class="col-2">
                                        <div class="h5 mb-0 text-danger" id="failedFiles">0</div>
                                        <small class="text-muted">فاشلة</small>
                                    </div>
                                    <div class="col-2">
                                        <div class="h5 mb-0 text-warning" id="duplicateFiles" style="color: #ff8c00 !important;">0</div>
                                        <small class="text-muted">مكررة</small>
                                    </div>
                                    <div class="col-2">
                                        <div class="h5 mb-0 text-info" id="pendingFiles">0</div>
                                        <small class="text-muted">في الانتظار</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- File Previews -->
                <div class="row mt-4" id="filePreviewsSection">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pt-8">
                                    <div class="col-md-6 col-12">
                                        <h5 class="card-title mb-0 text-end">
                                            <i class="fas fa-images me-2"></i>معاينة الملفات
                                        </h5>
                                    </div>
                                    <div class="col-md-6 col-12 mb-2 mb-md-0">
                                        <div class="d-flex flex-wrap gap-2 justify-content-start">
                                            <button type="button" class="btn btn-success btn-sm" id="startUploadBtn"
                                                style="display: none;">
                                                <i class="fas fa-upload me-1"></i><span class="d-none d-sm-inline">بدء الرفع</span><span class="d-sm-none">رفع</span>
                                            </button>
                                            <button type="button" class="btn btn-info btn-sm" id="selectFolderBtn2">
                                                <i class="fas fa-folder-plus me-1"></i><span class="d-none d-sm-inline">اختيار مجلد</span><span class="d-sm-none">مجلد</span>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" id="clearAllBtn2">
                                                <i class="fas fa-trash me-1"></i><span class="d-none d-sm-inline">مسح</span><span class="d-sm-none">مسح</span>
                                            </button>
                                        </div>
                                    </div>
                            </div>
                            <div class="card-body">
                                <div class="row" id="filePreviewsContainer">
                                    <div class="col-12 text-center text-muted py-5">
                                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                                        <p>لا توجد ملفات محددة بعد</p>
                                    </div>
                                </div>
                            </div>
                        </div>                </div>
            </div>

            <!-- File Preview Modal -->
            <div class="modal fade" id="filePreviewModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="previewModalTitle">معاينة الملف</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="previewModalBody">
                            <!-- Preview content will be loaded here -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                            <button type="button" class="btn btn-primary" id="downloadFileBtn">
                                <i class="fas fa-download me-2"></i>تحميل
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @include('file-management.modalDublicateFiles')

            <!-- Duplicate Files Modal for Folders -->
            <div class="modal fade" id="duplicateFilesModal" tabindex="-1" aria-labelledby="duplicateFilesModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="duplicateFilesModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                الملفات المكررة المكتشفة
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Content will be loaded dynamically -->
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">جاري التحميل...</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Excel Gateway Modal -->
            <div class="modal fade" id="excelGatewayModal" tabindex="-1" aria-labelledby="excelGatewayModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="excelGatewayModalLabel">
                                <i class="fas fa-table me-2"></i>بوابة Excel المتخصصة
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <iframe src="{{ route('admin.file.excel.gateway') }}" width="100%" height="600"
                                frameborder="0" id="excelGatewayFrame">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>

            <!-- نصيحة حول رفع المجلدات -->
            <div class="alert alert-info mt-2" style="font-size: 0.95em;">
                <i class="fas fa-info-circle"></i>
                عند رفع مجلد رئيسي يحتوي على عدة مجلدات فرعية، سيتم تجاهل المجلد الأب تلقائياً وسيتم معالجة المجلدات الفرعية التي تحمل أرقام هوية فقط. تأكد أن أسماء المجلدات الفرعية تطابق أرقام الهوية أو أرقام الملفات في النظام.
            </div>

            <!-- معلومات الأداء والتوافق -->
            <div class="alert alert-success mt-2 d-block d-md-none" style="font-size: 0.9em;">
                <i class="fas fa-mobile-alt"></i>
                <strong>نصائح للجوال:</strong> تم تحسين هذه الصفحة للعمل على الأجهزة المحمولة. يمكنك التمرير أفقياً لعرض المزيد من الخيارات، والنقر مطولاً على الأزرار للحصول على تفاصيل إضافية.
            </div>
            @include('file-management.indexFileManegerjavascript')
@endsection
