<style>
    /* إعدادات المودال الأساسية */
    #cropperModal {
        z-index: 1060;
    }

    #cropperModal .modal-dialog {
        margin: 0;
        width: 100vw;
        height: 100vh;
        max-width: 100vw;
        max-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #cropperModal .modal-content {
        height: 95vh;
        max-height: 95vh;
        display: flex;
        flex-direction: column;
        padding: 0;
        border-radius: 12px;
        border: none;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        background: #fff;
        overflow: hidden;
    }

    #cropperModal .modal-header {
        flex-shrink: 0;
        padding: 0.5rem 1rem;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
        min-height: 50px;
    }

    /* الجسم الرئيسي للمودال */
    .cropper-modal-body {
        flex: 1 1 0;
        display: flex;
        flex-direction: row;
        gap: 0;
        padding: 0;
        height: 100%;
        min-height: 0;
        align-items: stretch;
    }

    /* منطقة القص */
    .crop-area {
        flex: 1 1 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #fff;
        border: 3px solid #007bff;
        border-radius: 10px;
        margin: 0;
        padding: 0;
        position: relative;
        overflow: hidden;
        min-width: 0;
        min-height: 0;
        height: 100%;
        max-height: 100%;
        align-self: stretch;
    }

    .crop-area img {
        display: block;
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 5px;
        margin: auto;
        /* ضمان التوسيط الكامل */
        position: relative;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
    }

    /* منطقة التحكم الجانبية */
    .controls-panel-container {
        width: 260px;
        flex-shrink: 0;
        background-color: #fff;
        border-left: 3px solid #dee2e6;
        padding: 18px 10px 10px 10px;
        display: flex;
        flex-direction: column;
        gap: 15px;
        min-height: 100%;
        max-height: 100%;
        box-shadow: none;
        position: relative;
        z-index: 50;
    }

    /* أزرار الحفظ والإلغاء */
    .action-buttons {
        display: flex !important;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 0 0 0;
        border-top: 2px solid #e9ecef;
        background-color: #fff;
        position: relative;
        z-index: 100;
        min-height: 60px;
        align-items: center;
    }

    .action-buttons button {
        flex: 1;
        height: 48px;
        font-size: 15px;
        font-weight: bold;
        border-radius: 8px;
        border: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        min-width: 120px;
    }

    #cropperCropBtn {
        background-color: #28a745 !important;
        color: white !important;
        box-shadow: 0 3px 8px rgba(40,167,69,0.3);
        border: 2px solid #28a745 !important;
    }

    #cropperCropBtn:not(:disabled):hover {
        background-color: #218838 !important;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40,167,69,0.4);
    }

    .btn-danger {
        background-color: #dc3545 !important;
        color: white !important;
        box-shadow: 0 3px 8px rgba(220,53,69,0.3);
        border: 2px solid #dc3545 !important;
    }

    .btn-danger:hover {
        background-color: #c82333 !important;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(220,53,69,0.4);
    }

    /* تحسينات للشاشات الكبيرة */
    @media (min-width: 992px) {
        #cropperModal .modal-dialog {
            margin-top: 200px !important; /* زيادة الازاحة من الأعلى */
            margin-bottom: 0px !important;
        }
        #cropperModal .modal-content {
            height: 95vh;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            padding: 0;
        }
        .cropper-modal-body {
            flex: 1 1 0;
            display: flex;
            flex-direction: row;
            gap: 0;
            padding: 0;
            height: 100%;
            min-height: 0;
            align-items: stretch;
        }
        .crop-area {
            flex: 1 1 0;
            margin: 0;
            padding: 0;
            min-width: 0;
            min-height: 0;
            height: 100%;
            max-height: 100%;
            align-self: stretch;
            display: flex;
            align-items: center;
            justify-content: center;
            /* إزالة min-width/min-height/max-height من الشاشات الكبيرة */
        }
        .crop-area img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            margin: auto;
        }
        .controls-panel-container {
            width: 280px;
            flex-shrink: 0;
            border-top: none;
            border-left: 3px solid #dee2e6;
            min-height: 100%;
            max-height: 100%;
            justify-content: space-between;
            padding: 18px 10px 10px 10px;
        }
        .action-buttons {
            flex-direction: row;
            border-top: 2px solid #e9ecef;
            padding: 15px 0 0 0;
            min-height: 60px;
            position: relative;
            background: #fff;
            justify-content: center;
            align-items: center;
        }
        .action-buttons button {
            flex: 1;
            height: 48px;
            font-size: 15px;
            min-width: 120px;
            margin: 0 8px;
        }
    }

    /* تحسينات للأجهزة المحمولة */
    @media (max-width: 991.98px) {
        #cropperModal .modal-content {
            height: 98vh;
            border-radius: 8px;
        }
        .cropper-modal-body {
            flex-direction: column;
            gap: 0;
            padding: 0;
            height: 100%;
            align-items: stretch;
        }
        .crop-area {
            min-height: 0 !important;
            max-height: none !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            align-self: stretch;
        }
        .crop-area img {
            max-height: 100% !important;
        }
        .controls-panel-container {
            width: 100%;
            min-height: 90px;
            max-height: 140px;
            border-left: none;
            border-top: 3px solid #dee2e6;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.04);
            padding: 8px 4px 4px 4px;
            flex-direction: row;
            gap: 10px;
        }
        .action-buttons {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #fff !important;
            padding: 10px 4px !important;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.13) !important;
            z-index: 9999 !important;
            border-radius: 0 !important;
            min-height: 50px;
        }
        .action-buttons button {
            height: 38px;
            font-size: 13px;
            min-width: 80px;
        }
        .cropper-modal-body {
            padding-bottom: 60px !important;
        }
    }

    /* شاشات الجوال الصغيرة جداً */
    @media (max-width: 575.98px) {
        #cropperModal .modal-content {
            height: 99vh;
            border-radius: 0;
        }
        .crop-area {
            margin: 0 !important;
            padding: 0 !important;
            min-height: 0 !important;
            max-height: none !important;
            height: 100% !important;
        }
        .controls-panel-container {
            min-height: 60px;
            max-height: 90px;
            padding: 4px 1px 1px 1px;
        }
        .action-buttons {
            padding: 6px 2px !important;
            min-height: 40px;
        }
        .action-buttons button {
            height: 32px;
            font-size: 11px;
            min-width: 60px;
        }
        .crop-area::before {
            font-size: 9px;
            padding: 1px 6px;
        }
    }

    /* شاشات الكمبيوتر الكبيرة */
    @media (min-width: 1200px) {
        #cropperModal .modal-content {
            height: 90vh;
            max-width: 1100px;
            margin: auto;
        }
        .crop-area {
            min-width: 400px;
            min-height: 350px;
            max-height: 70vh;
        }
        .controls-panel-container {
            width: 300px;
            min-width: 220px;
            max-width: 350px;
        }
    }
    /* تحسين عام للخطوط */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Loader styles */
    .spinner-border {
        width: 3rem;
        height: 3rem;
    }

    /* ضمان ظهور الأزرار */
    .action-buttons button {
        visibility: visible !important;
        opacity: 1 !important;
        display: flex !important;
    }

    /* ضمان ظهور أزرار الحفظ والإلغاء دائماً فوق كل شيء في الجوال */
    @media (max-width: 767.98px) {
        .action-buttons {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #fff !important;
            padding: 10px 4px !important;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.2) !important;
            z-index: 99999 !important;
            border-radius: 0 !important;
            min-height: 50px !important;
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        .cropper-modal-body {
            padding-bottom: 70px !important;
        }
        #cropperModal .modal-content {
            height: 88vh !important; /* تصغير الطول من الأسفل للجوال */
            max-height: 88vh !important;
        }
        #cropperModal .modal-dialog {
            margin-top: 0px !important; /* تقليل الهامش من الأعلى للجوال */
            margin-bottom: 5px !important; /* تقليل الهامش من الأعلى للجوال */
        }
    }

    /* إصلاح إضافي: ضمان أن الأزرار لا تُغطى بأي عنصر آخر */
    .action-buttons button {
        visibility: visible !important;
        opacity: 1 !important;
        display: flex !important;
        z-index: 100000 !important;
    }

    /* خطوط الشبكة (Cropper grid lines) */
    .cropper-line {
        background-color: #007bff !important;
        opacity: 0.9 !important;
        /* زيادة عرض الخطوط */
    }
    .cropper-line.cropper-line-h {
        height: 5px !important; /* عريض جداً */
    }
    .cropper-line.cropper-line-v {
        width: 5px !important; /* عريض جداً */
    }

    /* نقاط الشبكة (Cropper points) */
    .cropper-point {
        width: 16px !important;
        height: 16px !important;
        background-color: #fff !important;
        border: 3px solid #007bff !important;
        border-radius: 50% !important;
        opacity: 1 !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
    }

    /* تكبير النقاط أكثر على الشاشات الصغيرة */
    @media (max-width: 991.98px) {
        .cropper-point {
            width: 20px !important;
            height: 20px !important;
            border-width: 4px !important;
        }
        .cropper-line.cropper-line-h {
            height: 7px !important;
        }
        .cropper-line.cropper-line-v {
            width: 7px !important;
        }
    }

    /* تحسين خاص للآيباد/تابلت فقط (دون التأثير على الجوال أو الكمبيوتر) */
    @media (min-width: 768px) and (max-width: 991.98px) {
        #cropperModal .modal-dialog {
            margin-top: 300px !important; /* إزاحة معتدلة من الأعلى */
            margin-bottom: 20px !important;
            align-items: flex-start !important;
        }
        #cropperModal .modal-content {
            height: 90vh !important;
            max-height: 90vh !important;
            border-radius: 16px !important;
        }
        .cropper-modal-body {
            flex-direction: column !important;
            height: 100% !important;
            min-height: 0 !important;
            align-items: stretch !important;
            padding: 0 !important;
            gap: 0 !important;
        }
        .crop-area {
            flex: 1 1 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            min-width: 0 !important;
            min-height: 0 !important;
            height: 100% !important;
            max-height: 100% !important;
            align-self: stretch !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .crop-area img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            margin: auto !important;
        }
        .action-buttons {
            position: static !important;
            padding: 10px 0 0 0 !important;
            min-height: 50px !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            z-index: 100 !important;
            background: #fff !important;
        }
        .cropper-modal-body {
            padding-bottom: 0 !important;
        }
    }

    @media (max-width: 767.98px) {
        #cropperModal .modal-dialog {
            margin-top: 0px !important; /* تخفيف التباعد من الأعلى للجوال */
            margin-bottom: 28px !important;
            height: 100vh !important;
            max-height: 100vh !important;
            align-items: flex-start !important;
        }
        #cropperModal .modal-content {
            height: 90vh !important;
            max-height: 90vh !important;
            border-radius: 10px !important;
            margin: 0 auto !important;
        }
        .cropper-modal-body {
            padding-bottom: 48px !important;
            height: 100% !important;
            min-height: 0 !important;
        }
        .action-buttons {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #fff !important;
            padding: 10px 4px 32px 4px !important;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.2) !important;
            z-index: 99999 !important;
            border-radius: 0 !important;
            min-height: 50px !important;
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    }
</style>

