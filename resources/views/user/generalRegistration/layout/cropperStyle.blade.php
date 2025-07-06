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
    }

    #cropperModal .modal-content {
        height: 100vh;
        border-radius: 0;
        border: none;
        display: flex;
        flex-direction: column;
    }

    /* Header المودال */
    #cropperModal .modal-header {
        flex-shrink: 0;
        padding: 0.5rem 1rem;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
        min-height: 50px;
    }

    #cropperModal .modal-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
    }

    /* جسم المودال */
    .cropper-modal-body {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0;
        background-color: #f5f5f5;
        min-height: 0;
        height: calc(100vh - 50px);
    }

    /* منطقة الصورة */
    .crop-area {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #ffffff;
        border: 3px solid #007bff;
        border-radius: 10px;
        margin: 8px;
        padding: 10px;
        position: relative;
        overflow: hidden;
        min-height: 300px;
        max-height: calc(100vh - 280px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .crop-area::before {
        content: "منطقة قص الصورة";
        position: absolute;
        top: 5px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #007bff;
        color: white;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: bold;
        z-index: 10;
    }

    .crop-area img {
        display: block;
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 5px;
    }

    /* منطقة الأزرار */
    .controls-panel-container {
        flex-shrink: 0;
        background-color: #ffffff;
        border-top: 3px solid #dee2e6;
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 15px;
        min-height: 220px;
        max-height: 250px;
        box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
        position: relative;
        z-index: 50;
    }

    /* قسم التحكم */
    .controls-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .controls-title {
        font-size: 14px;
        font-weight: bold;
        color: #333;
        margin: 0 0 10px 0;
        text-align: center;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 5px;
    }

    /* أزرار الحركة */
    .movement-controls {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        margin-bottom: 10px;
    }

    .movement-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        width: 100%;
    }

    .movement-center {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* أزرار التكبير والدوران */
    .action-controls {
        display: flex;
        justify-content: space-around;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* أنماط الأزرار الموحدة */
    .control-btn {
        border: 2px solid #6c757d;
        background-color: #f8f9fa;
        color: #495057;
        border-radius: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .control-btn:not(:disabled):hover {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
        transform: scale(1.05);
    }

    .control-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* أزرار الحركة */
    .movement-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 16px;
    }

    /* أزرار التكبير */
    .zoom-btn {
        min-width: 80px;
        height: 35px;
        padding: 5px 10px;
        font-size: 12px;
        gap: 5px;
        flex-direction: column;
    }

    .zoom-btn i {
        font-size: 14px;
    }

    .zoom-btn span {
        font-size: 10px;
        font-weight: bold;
    }

    /* زر الدوران */
    .rotate-btn {
        min-width: 80px;
        height: 35px;
        padding: 5px 10px;
        font-size: 12px;
        gap: 5px;
        flex-direction: column;
    }

    .rotate-btn i {
        font-size: 14px;
    }

    .rotate-btn span {
        font-size: 10px;
        font-weight: bold;
    }

    /* أزرار الحفظ والإلغاء */
    .action-buttons {
        display: flex !important;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 0;
        border-top: 2px solid #e9ecef;
        background-color: #ffffff;
        position: relative;
        z-index: 100;
        min-height: 60px;
        align-items: center;
    }

    .action-buttons button {
        flex: 1;
        height: 50px;
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
        min-width: 140px;
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
        .cropper-modal-body {
            flex-direction: row;
            gap: 15px;
            padding: 15px;
        }

        .crop-area {
            flex: 1;
            margin: 0;
            min-width: 600px;
            min-height: 500px;
            max-height: none;
        }

        .controls-panel-container {
            width: 280px;
            flex-shrink: 0;
            border-top: none;
            border-left: 3px solid #dee2e6;
            min-height: auto;
            max-height: none;
            justify-content: space-between;
        }

        .movement-controls {
            margin-bottom: 20px;
        }

        .movement-btn {
            width: 50px;
            height: 50px;
            font-size: 18px;
        }

        .zoom-btn,
        .rotate-btn {
            min-width: 90px;
            height: 40px;
            font-size: 14px;
        }

        .action-buttons {
            flex-direction: column;
            border-top: 2px solid #e9ecef;
            padding-top: 15px;
            min-height: auto;
        }

        .action-buttons button {
            flex: none;
            height: 55px;
            font-size: 16px;
            min-width: auto;
            margin-bottom: 10px;
        }
    }

    /* تحسينات للأجهزة المحمولة */
    @media (max-width: 991.98px) {
        .crop-area {
            min-height: calc(100vh - 320px);
            max-height: calc(100vh - 320px);
            margin: 5px;
            padding: 8px;
        }

        .controls-panel-container {
            min-height: 240px;
            max-height: 260px;
            padding: 10px 15px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #ffffff;
            border-top: 3px solid #007bff;
            z-index: 1000;
        }

        .controls-title {
            font-size: 12px;
            margin-bottom: 8px;
        }

        .movement-controls {
            margin-bottom: 8px;
        }

        .movement-btn {
            width: 35px;
            height: 35px;
            font-size: 14px;
        }

        .action-controls {
            gap: 5px;
            margin-bottom: 10px;
        }

        .zoom-btn,
        .rotate-btn {
            min-width: 70px;
            height: 30px;
            font-size: 10px;
        }

        .zoom-btn i,
        .rotate-btn i {
            font-size: 12px;
        }

        .zoom-btn span,
        .rotate-btn span {
            font-size: 9px;
        }

        .action-buttons {
            padding: 8px 0;
            border-top: 2px solid #e9ecef;
            background-color: #ffffff;
            min-height: 60px;
        }

        .action-buttons button {
            height: 45px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 8px;
            min-width: 120px;
        }

        .cropper-modal-body {
            padding-bottom: 260px;
        }
    }

    /* تحسين نقاط Cropper.js */
    .cropper-point {
        width: 15px !important;
        height: 15px !important;
        background-color: #fff !important;
        border: 3px solid #007bff !important;
        border-radius: 50% !important;
        opacity: 1 !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
    }

    .cropper-line {
        background-color: #007bff !important;
        opacity: 0.8 !important;
    }

    .cropper-line.cropper-line-h {
        height: 2px !important;
    }

    .cropper-line.cropper-line-v {
        width: 2px !important;
    }

    .cropper-view-box {
        outline: 2px solid #007bff !important;
        outline-offset: -2px !important;
    }

    .cropper-crop-box {
        box-shadow: 0 0 20px rgba(0,123,255,0.3) !important;
    }

    @media (max-width: 767.98px) {
        .cropper-point {
            width: 20px !important;
            height: 20px !important;
            border: 4px solid #007bff !important;
        }

        .cropper-line.cropper-line-h {
            height: 3px !important;
        }

        .cropper-line.cropper-line-v {
            width: 3px !important;
        }

        .cropper-view-box {
            outline: 3px solid #007bff !important;
        }
    }

    /* إخفاء العناصر غير الضرورية */
    .cropper-bg {
        background-image: none !important;
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

    @media (max-width: 767.98px) {
        .action-buttons {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #ffffff !important;
            padding: 15px !important;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.2) !important;
            z-index: 9999 !important;
        }
    }
</style>
