<style>
    /* =====================================================
       تصميم بسيط لمودال قص الصور
       =====================================================*/

    /* إخفاء أي مودال مكرر */
    #cropperModal ~ #cropperModal {
        display: none !important;
    }

    /* ضمان ظهور المودال فوق كل العناصر */
    #cropperModal.show {
        z-index: 999999 !important;
    }

    #cropperModal.show ~ .modal-backdrop {
        z-index: 999998 !important;
    }

    /* المودال الأساسي */
    #cropperModal .modal-dialog {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #cropperModal .modal-content {
        width: 100%;
        height: 100%;
        max-width: 100vw;
        max-height: 100vh;
        display: flex;
        flex-direction: column;
        padding: 0;
        margin: 0;
        border: none;
        border-radius: 0;
        background: #fff;
    }

    /* الشريط العلوي - بسيط */
    #cropperModal .modal-header {
        padding: 10px 15px;
        background: #1a1a1a;
        border-bottom: 1px solid #333;
        min-height: 45px;
        flex-shrink: 0;
    }

    #cropperModal .modal-header .modal-title {
        color: #fff;
        font-size: 14px;
        font-weight: 500;
        margin: 0;
    }

    /* منطقة الصورة */
    .cropper-modal-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #f0f0f0;
        padding: 0;
        min-height: 0;
        overflow: hidden;
    }

    .crop-area {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #e5e5e5;
        margin: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        min-height: 0;
        overflow: hidden;
    }

    .crop-area img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    /* الأزرار */
    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 10px;
        padding: 12px 15px;
        background: #fff;
        border-top: 1px solid #ddd;
        flex-shrink: 0;
    }

    .action-buttons button {
        padding: 10px 25px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    #cropperCropBtn {
        background: #28a745;
        color: #fff;
    }

    #cropperCropBtn:hover:not(:disabled) {
        background: #218838;
    }

    #cropperCropBtn:disabled {
        background: #999;
        cursor: not-allowed;
    }

    #cropperCancelBtn {
        background: #dc3545;
        color: #fff;
    }

    #cropperCancelBtn:hover {
        background: #c82333;
    }

    /* خطوط ونقاط القص */
    .cropper-line {
        background: #333 !important;
    }

    .cropper-point {
        background: #fff !important;
        border: 2px solid #333 !important;
        border-radius: 50% !important;
        width: 12px !important;
        height: 12px !important;
    }

    /* =====================================================
       الجوال
       =====================================================*/
    @media (max-width: 767px) {
        .cropper-modal-body {
            padding-bottom: 65px;
        }

        .crop-area {
            margin: 5px;
        }

        .action-buttons {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px;
            padding-bottom: calc(10px + env(safe-area-inset-bottom, 0px));
            z-index: 99999;
        }

        .action-buttons button {
            flex: 1;
            padding: 12px;
        }

        .cropper-point {
            width: 16px !important;
            height: 16px !important;
        }
    }

    /* =====================================================
       الشاشات الكبيرة
       =====================================================*/
    @media (min-width: 768px) {
        #cropperModal .modal-content {
            bottom: 0.33vh;
            border-radius: 20px;
            width: 80%;
            max-width: 600px;
        }
    }
</style>

