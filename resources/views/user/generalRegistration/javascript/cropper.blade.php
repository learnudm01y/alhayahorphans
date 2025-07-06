<style>
    /* إزاحة المودال للأعلى لإظهار جميع التفاصيل */
    #cropperModal {
        transform: translateY(-10%) !important; /* إزاحة للأعلى */
    }

    /* تحسين إضافي للموبايل */
    @media (max-width: 767.98px) {
        #cropperModal {
            transform: translateY(-5%) !important; /* تقليل الإزاحة */
        }

        /* Custom styles for the cropper modal */
        .modal-fullscreen-sm-down .modal-content {
            border-radius: 0;
            margin: 0;
            height: 95vh !important; /* زيادة الارتفاع لاستغلال الشاشة */
        }

        /* إصلاح الكلاس الصحيح لجسم المودال */
        .cropper-modal-body {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            padding: 0 !important;
            overflow: hidden;
            min-height: 400px;
            min-width: 300px;
        }

        /* Desktop layout: Side-by-side */
        @media (min-width: 768px) {
            .cropper-modal-body {
                flex-direction: row;
            }
            .controls-panel-container {
                order: 2;
                border-right: 1px solid #dee2e6;
                border-left: none;
            }
        }

        .crop-area {
            flex-grow: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            overflow: hidden;
            padding: 5px;
            min-width: 300px;
            min-height: 300px;
            width: 100%;
            height: 100%;
        }

        .crop-area img {
            display: block;
            min-width: 250px;
            min-height: 250px;
            max-width: 95%;
            max-height: 95%;
            object-fit: contain;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        @media (min-width: 992px) {
            .modal-dialog {
                max-width: 1200px;
                width: 90vw;
                height: 80vh;
            }
            .modal-content {
                height: 100%;
            }
            .cropper-modal-body {
                flex-direction: row;
                min-height: 600px;
                min-width: 800px;
                height: 100%;
            }
            .crop-area {
                min-width: 600px;
                min-height: 500px;
                max-width: none;
                max-height: none;
            }
            .crop-area img {
                min-width: 400px;
                min-height: 300px;
            }
            .controls-panel-container {
                min-width: 160px;
                max-width: 200px;
            }
        }

        /* تحسين خاص للأجهزة المحمولة - إصلاح شامل */
        @media (max-width: 767.98px) {
            #cropperModal {
                transform: translateY(-5%) !important; /* تقليل الإزاحة */
            }

            .modal-fullscreen-sm-down .modal-content {
                height: 95vh !important; /* زيادة الارتفاع لاستغلال الشاشة */
                margin: 0 !important;
            }

            .crop-area {
                min-height: 65vh !important; /* زيادة كبيرة في ارتفاع منطقة الصورة */
                max-height: 70vh !important;
                padding: 5px !important; /* تقليل الـ padding */
                justify-content: center;
                align-items: center;
                flex-shrink: 1;
                background-color: #f0f0f0 !important; /* لون خلفية أفضل */
            }

            .crop-area img {
                min-width: 95% !important; /* تكبير العرض */
                min-height: 95% !important; /* تكبير الارتفاع */
                max-width: 98% !important;
                max-height: 98% !important; /* زيادة الحد الأقصى */
                margin: 0;
                object-position: center center;
                object-fit: contain;
            }

            .controls-panel-container {
                flex-direction: column !important;
                justify-content: flex-start !important;
                align-items: stretch !important;
                flex-wrap: nowrap !important;
                padding: 0.8rem !important; /* تقليل الـ padding */
                border-top: 3px solid #dee2e6;
                min-width: 0;
                max-width: 100vw;
                background-color: #ffffff !important; /* خلفية بيضاء */
                min-height: 20vh !important; /* تقليل الارتفاع */
                max-height: 25vh !important;
                overflow-y: auto !important;
                position: relative !important;
            }

            .controls-grid {
                grid-template-columns: repeat(4, 1fr) !important; /* تغيير إلى 4 أعمدة */
                width: 100% !important;
                gap: 0.8rem !important; /* زيادة المسافة */
                margin-bottom: 0.8rem !important;
                flex-shrink: 0;
                justify-items: center;
            }

            .controls-grid button {
                padding: 12px 10px !important; /* زيادة الـ padding */
                min-width: 50px !important; /* زيادة العرض */
                min-height: 50px !important; /* زيادة الارتفاع */
                font-size: 18px !important; /* زيادة حجم الخط */
                border-width: 2px !important;
                border-radius: 8px !important;
                background-color: #f8f9fa !important;
                border-color: #6c757d !important;
            }

            /* إعادة ترتيب الأزرار للموبايل */
            .controls-grid .move-up { grid-column: 2; grid-row: 1; }
            .controls-grid .move-left { grid-column: 1; grid-row: 2; }
            .controls-grid .move-right { grid-column: 3; grid-row: 2; }
            .controls-grid .move-down { grid-column: 2; grid-row: 2; }

            /* أزرار التكبير والدوران في صف منفصل */
            .controls-grid .zoom-in { grid-column: 1; grid-row: 3; }
            .controls-grid .zoom-out { grid-column: 2; grid-row: 3; }
            .controls-grid .rotate-right { grid-column: 3; grid-row: 3; }

            .controls-panel-container .action-buttons {
                flex-direction: row !important;
                width: 100% !important;
                justify-content: space-between !important;
                margin-top: 0.8rem !important;
                gap: 0.8rem !important;
                padding: 0.8rem 0 !important;
                border-top: 2px solid #dee2e6 !important;
                border-left: none !important;
                min-height: 60px !important;
                flex-shrink: 0 !important;
            }

            .controls-panel-container .action-buttons button {
                width: 48% !important;
                padding: 14px 10px !important; /* زيادة الـ padding */
                font-size: 16px !important; /* زيادة الخط */
                min-height: 50px !important; /* زيادة الارتفاع */
                font-weight: bold !important;
                border-radius: 8px !important;
                display: block !important;
                position: relative !important;
                z-index: 10 !important;
                flex: 1 !important;
                box-shadow: 0 2px 6px rgba(0,0,0,0.15) !important; /* إضافة ظل */
            }
        }

        /* تحسين زر الحفظ */
        #cropperCropBtn {
            background-color: #28a745 !important;
            color: white !important;
            border-color: #28a745 !important;
        }

        #cropperCropBtn:hover {
            background-color: #218838 !important;
            border-color: #1e7e34 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(40,167,69,0.3) !important;
        }

        /* تحسين زر الإلغاء */
        .btn-danger {
            background-color: #dc3545 !important;
            color: white !important;
            border-color: #dc3545 !important;
        }

        .btn-danger:hover {
            background-color: #c82333 !important;
            border-color: #bd2130 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(220,53,69,0.3) !important;
        }

        @media (max-width: 767.98px) {
            .cropper-modal-body {
                flex-direction: column;
                min-width: 0;
                min-height: 0;
                height: 100%; /* ضمان الارتفاع الكامل */
            }

            .controls-panel-container {
                flex-direction: column !important; /* تغيير إلى عمودي */
                justify-content: flex-start !important; /* بداية من الأعلى */
                align-items: stretch !important; /* امتداد كامل */
                flex-wrap: nowrap !important; /* منع التفاف */
                padding: 1rem !important;
                border-top: 3px solid #dee2e6;
                min-width: 0;
                max-width: 100vw;
                background-color: #f8f9fa;
                min-height: 25vh !important; /* تقليل الارتفاع */
                max-height: 30vh !important; /* تقليل الحد الأقصى */
                overflow-y: auto !important; /* إضافة scroll عند الحاجة */
                position: relative !important; /* تموضع نسبي */
            }

            .controls-grid {
                grid-template-columns: repeat(auto-fit, minmax(45px, 1fr));
                width: 100% !important; /* عرض كامل */
                gap: 0.5rem;
                margin-bottom: 0.5rem !important; /* تقليل المسافة */
                flex-shrink: 0; /* منع الانكماش */
            }

            .controls-grid button {
                padding: 8px 6px !important; /* تقليل أكثر */
                min-width: 40px !important; /* تقليل العرض */
                min-height: 40px !important; /* تقليل الارتفاع */
                font-size: 14px !important;
            }

            .controls-panel-container .action-buttons {
                flex-direction: row !important; /* أفقي حتى للموبايل */
                width: 100% !important; /* عرض كامل */
                justify-content: space-between !important;
                margin-top: 0.5rem !important; /* تقليل المسافة */
                gap: 0.5rem !important; /* تقليل الفجوة */
                padding: 0.5rem 0 !important; /* تقليل padding */
                border-top: 2px solid #dee2e6 !important;
                border-left: none !important; /* إزالة الحد الأيسر */
                min-height: 50px !important; /* تقليل الارتفاع */
                flex-shrink: 0 !important; /* منع الانكماش */
            }

            .controls-panel-container .action-buttons button {
                width: 48% !important; /* عرض 48% لكل زر */
                padding: 10px 8px !important; /* تقليل padding */
                font-size: 14px !important; /* تقليل الخط */
                min-height: 40px !important; /* تقليل الارتفاع */
                font-weight: bold !important;
                border-radius: 6px !important;
                display: block !important;
                position: relative !important;
                z-index: 10 !important; /* ضمان الظهور */
                flex: 1 !important; /* نمو متساوي */
            }

            /* تقليل مساحة منطقة الصورة أكثر */
            .crop-area {
                min-height: 50vh !important; /* زيادة ارتفاع الصورة */
                max-height: 55vh !important; /* زيادة الحد الأقصى */
                padding: 8px;
                justify-content: center;
                align-items: center;
                flex-shrink: 1; /* السماح بالانكماش */
            }

            /* التأكد من أن المودال يأخذ الارتفاع الكامل */
            .modal-fullscreen-sm-down .modal-content {
                height: 85vh !important; /* زيادة الارتفاع */
                display: flex !important;
                flex-direction: column !important;
            }

            .modal-fullscreen-sm-down .cropper-modal-body {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
                height: 100% !important;
            }
        }

        /* Ensure Cropper.js points are visible and appropriately sized */
        .cropper-point {
            background-color: #fff !important; /* White background */
            border: 2px solid #337ab7 !important; /* زيادة سمك الحدود */
            opacity: 0.9 !important; /* زيادة الوضوح */
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.3) !important; /* تحسين الظل */
            width: 12px !important; /* تكبير العرض */
            height: 12px !important; /* تكبير الارتفاع */
            border-radius: 50% !important; /* جعل النقاط دائرية */
        }

        /* تكبير النقاط أكثر على الأجهزة المحمولة */
        @media (max-width: 767.98px) {
            .cropper-point {
                width: 16px !important; /* حجم أكبر للموبايل */
                height: 16px !important; /* حجم أكبر للموبايل */
                border: 3px solid #337ab7 !important; /* حدود أسمك للموبايل */
            }
        }

        /* تحسين النقاط على الشاشات الكبيرة */
        @media (min-width: 992px) {
            .cropper-point {
                width: 14px !important; /* حجم متوسط للشاشات الكبيرة */
                height: 14px !important; /* حجم متوسط للشاشات الكبيرة */
            }
        }

        .cropper-view-box {
            outline: 3px solid #337ab7 !important; /* تكبير سمك الحدود */
            outline-color: rgba(51, 122, 183, 0.85) !important; /* زيادة الوضوح */
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.5) !important; /* إضافة ظل أبيض */
        }
        .cropper-line {
            background-color: #337ab7 !important; /* Blue lines */
            opacity: 0.8 !important; /* زيادة الوضوح */
        }

        /* تحسين الخطوط الوسطية */
        .cropper-line.cropper-line-h {
            height: 2px !important; /* تكبير سمك الخطوط الأفقية */
        }
        .cropper-line.cropper-line-v {
            width: 2px !important; /* تكبير سمك الخطوط العمودية */
        }

        /* تحسين منطقة القص بالكامل */
        .cropper-crop-box {
            box-shadow: 0 0 10px rgba(51, 122, 183, 0.3) !important; /* إضافة ظل للمنطقة */
        }

        /* تحسين النقاط عند التمرير عليها */
        .cropper-point:hover {
            background-color: #337ab7 !important;
            border-color: #fff !important;
            transform: scale(1.2) !important; /* تكبير عند التمرير */
            transition: all 0.2s ease !important;
        }
    }

    /* Loader styles */
    .modal-content {
        border-radius: 0.5rem;
    }

    .spinner-border {
        width: 3rem;
        height: 3rem;
    }

    /* تحسين عام للخطوط */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 0.875rem;
        line-height: 1.5;
        color: #333;
    }

    h5 {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    /* تحسين الأزرار العامة */
    .btn {
        border-radius: 0.375rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: background-color 0.3s, transform 0.3s;
    }

    .btn:hover {
        transform: translateY(-2px);
    }

    .btn:active {
        transform: translateY(0);
    }

    /* تحسين الأزرار الرئيسية */
    .btn-primary {
        background-color: #007bff;
        border-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0056b3;
        border-color: #004085;
    }

    .btn-primary:active {
        background-color: #004085;
        border-color: #003057;
    }

    /* تحسين أزرار الحفظ والإلغاء - الأهم */
    .controls-panel-container .action-buttons {
        display: flex;
        flex-direction: row !important; /* جانب بعض بدلاً من عمودي */
        gap: 0.5rem !important; /* تقليل المسافة */
        width: 100%;
        margin-top: 1rem;
        padding: 0.5rem 0; /* تقليل padding */
        border-top: 2px solid #f0f0f0; /* خط فاصل */
        flex-shrink: 0; /* منع الانكماش */
        min-height: 60px; /* تقليل الارتفاع */
        justify-content: space-between; /* توزيع متساوي */
    }

    .action-buttons button {
        padding: 8px 12px !important; /* تقليل كبير في padding */
        font-size: 14px !important; /* تقليل حجم الخط */
        font-weight: bold !important;
        border-radius: 6px !important; /* تقليل الحواف */
        min-height: 38px !important; /* تقليل الارتفاع */
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important; /* تقليل الظل */
        transition: all 0.3s ease !important;
        border: 2px solid transparent !important;
        white-space: nowrap !important; /* منع التفاف النص */
        overflow: visible !important; /* ضمان الرؤية */
        display: block !important; /* عرض كامل */
        width: 48% !important; /* عرض 48% لكل زر */
        flex: 1 !important; /* نمو متساوي */
    }

    /* تحسين زر الحفظ */
    #cropperCropBtn {
        background-color: #28a745 !important;
        color: white !important;
        border-color: #28a745 !important;
    }

    #cropperCropBtn:hover {
        background-color: #218838 !important;
        border-color: #1e7e34 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 12px rgba(40,167,69,0.3) !important;
    }

    /* تحسين زر الإلغاء */
    .btn-danger {
        background-color: #dc3545 !important;
        color: white !important;
        border-color: #dc3545 !important;
    }

    .btn-danger:hover {
        background-color: #c82333 !important;
        border-color: #bd2130 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 12px rgba(220,53,69,0.3) !important;
    }

    @media (max-width: 767.98px) {
        .cropper-modal-body {
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            height: 100%; /* ضمان الارتفاع الكامل */
        }

        .controls-panel-container {
            flex-direction: column !important; /* تغيير إلى عمودي */
            justify-content: flex-start !important; /* بداية من الأعلى */
            align-items: stretch !important; /* امتداد كامل */
            flex-wrap: nowrap !important; /* منع التفاف */
            padding: 1rem !important;
            border-top: 3px solid #dee2e6;
            min-width: 0;
            max-width: 100vw;
            background-color: #f8f9fa;
            min-height: 25vh !important; /* تقليل الارتفاع */
            max-height: 30vh !important; /* تقليل الحد الأقصى */
            overflow-y: auto !important; /* إضافة scroll عند الحاجة */
            position: relative !important; /* تموضع نسبي */
        }

        .controls-grid {
            grid-template-columns: repeat(auto-fit, minmax(45px, 1fr));
            width: 100% !important; /* عرض كامل */
            gap: 0.5rem;
            margin-bottom: 0.5rem !important; /* تقليل المسافة */
            flex-shrink: 0; /* منع الانكماش */
        }

        .controls-grid button {
            padding: 8px 6px !important; /* تقليل أكثر */
            min-width: 40px !important; /* تقليل العرض */
            min-height: 40px !important; /* تقليل الارتفاع */
            font-size: 14px !important;
        }

        .controls-panel-container .action-buttons {
            flex-direction: row !important; /* أفقي حتى للموبايل */
            width: 100% !important; /* عرض كامل */
            justify-content: space-between !important;
            margin-top: 0.5rem !important; /* تقليل المسافة */
            gap: 0.5rem !important; /* تقليل الفجوة */
            padding: 0.5rem 0 !important; /* تقليل padding */
            border-top: 2px solid #dee2e6 !important;
            border-left: none !important; /* إزالة الحد الأيسر */
            min-height: 50px !important; /* تقليل الارتفاع */
            flex-shrink: 0 !important; /* منع الانكماش */
        }

        .controls-panel-container .action-buttons button {
            width: 48% !important; /* عرض 48% لكل زر */
            padding: 10px 8px !important; /* تقليل padding */
            font-size: 14px !important; /* تقليل الخط */
            min-height: 40px !important; /* تقليل الارتفاع */
            font-weight: bold !important;
            border-radius: 6px !important;
            display: block !important;
            position: relative !important;
            z-index: 10 !important; /* ضمان الظهور */
            flex: 1 !important; /* نمو متساوي */
        }

        /* تقليل مساحة منطقة الصورة أكثر */
        .crop-area {
            min-height: 50vh !important; /* زيادة ارتفاع الصورة */
            max-height: 55vh !important; /* زيادة الحد الأقصى */
            padding: 8px;
            justify-content: center;
            align-items: center;
            flex-shrink: 1; /* السماح بالانكماش */
        }

        /* التأكد من أن المودال يأخذ الارتفاع الكامل */
        .modal-fullscreen-sm-down .modal-content {
            height: 85vh !important; /* زيادة الارتفاع */
            display: flex !important;
            flex-direction: column !important;
        }

        .modal-fullscreen-sm-down .cropper-modal-body {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            height: 100% !important;
        }
    }

    /* Ensure Cropper.js points are visible and appropriately sized */
    .cropper-point {
        background-color: #fff !important; /* White background */
        border: 2px solid #337ab7 !important; /* زيادة سمك الحدود */
        opacity: 0.9 !important; /* زيادة الوضوح */
        box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.3) !important; /* تحسين الظل */
        width: 12px !important; /* تكبير العرض */
        height: 12px !important; /* تكبير الارتفاع */
        border-radius: 50% !important; /* جعل النقاط دائرية */
    }

    /* تكبير النقاط أكثر على الأجهزة المحمولة */
    @media (max-width: 767.98px) {
        .cropper-point {
            width: 16px !important; /* حجم أكبر للموبايل */
            height: 16px !important; /* حجم أكبر للموبايل */
            border: 3px solid #337ab7 !important; /* حدود أسمك للموبايل */
        }
    }

    /* تحسين النقاط على الشاشات الكبيرة */
    @media (min-width: 992px) {
        .cropper-point {
            width: 14px !important; /* حجم متوسط للشاشات الكبيرة */
            height: 14px !important; /* حجم متوسط للشاشات الكبيرة */
        }
    }

    .cropper-view-box {
        outline: 3px solid #337ab7 !important; /* تكبير سمك الحدود */
        outline-color: rgba(51, 122, 183, 0.85) !important; /* زيادة الوضوح */
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.5) !important; /* إضافة ظل أبيض */
    }
    .cropper-line {
        background-color: #337ab7 !important; /* Blue lines */
        opacity: 0.8 !important; /* زيادة الوضوح */
    }

    /* تحسين الخطوط الوسطية */
    .cropper-line.cropper-line-h {
        height: 2px !important; /* تكبير سمك الخطوط الأفقية */
    }
    .cropper-line.cropper-line-v {
        width: 2px !important; /* تكبير سمك الخطوط العمودية */
    }

    /* تحسين منطقة القص بالكامل */
    .cropper-crop-box {
        box-shadow: 0 0 10px rgba(51, 122, 183, 0.3) !important; /* إضافة ظل للمنطقة */
    }

    /* تحسين النقاط عند التمرير عليها */
    .cropper-point:hover {
        background-color: #337ab7 !important;
        border-color: #fff !important;
        transform: scale(1.2) !important; /* تكبير عند التمرير */
        transition: all 0.2s ease !important;
    }
</style>

<!-- Loader Modal -->
<div class="modal fade" id="compressLoaderModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <div class="spinner-border text-primary mb-3" role="status">
          <span class="visually-hidden">جاري إدراج الوثيقة...</span>
        </div>
        <div id="compressLoaderTitle" class="fw-bold">جاري إدراج الوثيقة</div>
      </div>
    </div>
  </div>
</div>

<!-- Cropper Modal Markup -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content d-flex flex-column vh-100">
      <div class="modal-header">
        <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="cropper-modal-body flex-grow-1 d-flex flex-column flex-md-row p-0 overflow-hidden">

        <!-- Crop Area -->
        <div class="crop-area flex-grow-1 d-flex justify-content-center align-items-center bg-light p-2">
          <img id="cropperImage" src="" alt="Image to crop" style="max-width: 100%; height: auto;" />
        </div>

        <!-- Controls Panel -->
        <div class="controls-panel-container bg-white d-flex flex-column justify-content-between p-3">
          <div class="controls-grid d-grid gap-2" style="grid-template-columns: repeat(3, 1fr);">
            <button id="cropperMoveUp" class="btn btn-outline-secondary" title="↑" disabled><i class="fas fa-arrow-up"></i></button>
            <button id="cropperMoveLeft" class="btn btn-outline-secondary" title="←" disabled><i class="fas fa-arrow-left"></i></button>
            <span class="move-center"></span>
            <button id="cropperMoveRight" class="btn btn-outline-secondary" title="→" disabled><i class="fas fa-arrow-right"></i></button>
            <button id="cropperMoveDown" class="btn btn-outline-secondary" title="↓" disabled><i class="fas fa-arrow-down"></i></button>
            <button id="cropperZoomIn" class="btn btn-outline-secondary" title="＋" disabled><i class="fas fa-search-plus"></i></button>
            <button id="cropperZoomOut" class="btn btn-outline-secondary" title="－" disabled><i class="fas fa-search-minus"></i></button>
            <button id="cropperRotateRight" class="btn btn-outline-secondary" title="↻" disabled><i class="fas fa-sync-alt"></i></button>
          </div>
          <div class="action-buttons d-flex flex-column gap-2 mt-3">
            <button id="cropperCropBtn" class="btn btn-success" disabled>قص وحفظ</button>
            <button class="btn btn-danger" data-bs-dismiss="modal">إلغاء</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- إضافة مكتبة browser-image-compression -->
<script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>

<script>
window.showCropperModal = function(file, callback) {
  // فحص شامل للملف المُمرر
  console.log('[showCropperModal] بدء فحص الملف المُمرر:', file);

  // التحقق من وجود الملف
  if (!file) {
    console.error('[showCropperModal] لم يتم تمرير ملف!');
    if (callback) callback(null, 'لم يتم تمرير ملف');
    return;
  }

  // التحقق من أن الملف هو File أو Blob
  if (!(file instanceof File) && !(file instanceof Blob)) {
    console.error('[showCropperModal] الملف المُمرر ليس من نوع File أو Blob:', typeof file, file);
    if (callback) callback(null, 'نوع الملف غير صالح');
    return;
  }

  // التحقق من نوع الملف
  if (!file.type || !file.type.startsWith('image/')) {
    console.error('[showCropperModal] الملف ليس صورة! نوع الملف:', file.type);
    if (callback) callback(null, 'الملف ليس صورة');
    return;
  }

  // التحقق من حجم الملف
  if (file.size === 0) {
    console.error('[showCropperModal] الملف فارغ! حجم الملف:', file.size);
    if (callback) callback(null, 'الملف فارغ');
    return;
  }

  // التحقق من حجم الملف (حد أقصى 50MB)
  const maxSize = 50 * 1024 * 1024; // 50MB
  if (file.size > maxSize) {
    console.error('[showCropperModal] الملف كبير جداً! حجم الملف:', file.size, 'الحد الأقصى:', maxSize);
    if (callback) callback(null, 'حجم الملف كبير جداً');
    return;
  }

  console.log('[showCropperModal] ✅ الملف صالح - الاسم:', file.name, 'النوع:', file.type, 'الحجم:', file.size, 'bytes');

  const modalEl = document.getElementById('cropperModal');
  const imgEl = document.getElementById('cropperImage');
  const cropBtn = document.getElementById('cropperCropBtn');
  const loaderModalEl = document.getElementById('compressLoaderModal');

  // التحقق من وجود العناصر المطلوبة
  if (!modalEl) {
    console.error('[showCropperModal] عنصر المودال غير موجود!');
    if (callback) callback(null, 'عنصر المودال مفقود');
    return;
  }

  if (!imgEl) {
    console.error('[showCropperModal] عنصر الصورة غير موجود!');
    if (callback) callback(null, 'عنصر الصورة مفقود');
    return;
  }

  if (!cropBtn) {
    console.error('[showCropperModal] زر القص غير موجود!');
    if (callback) callback(null, 'زر القص مفقود');
    return;
  }

  console.log('[showCropperModal] ✅ جميع العناصر موجودة، بدء تحميل الصورة...');

  const loaderModal = bootstrap.Modal.getOrCreateInstance(loaderModalEl);
  let cropper;
  let cropperReady = false;
  let timeoutTimer = null;
  let objectUrl = null;

  function isMobileChrome() {
    const ua = navigator.userAgent;
    return /Android|iPhone|iPad|iPod/i.test(ua) && /Chrome/i.test(ua);
  }

  function disableAllControls() {
    cropBtn.disabled = true;
    ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = true;
    });
  }

  function enableAllControls() {
    cropBtn.disabled = false;
    ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = false;
    });
  }

  // ⭐ إضافة دالة enableCropperControls المفقودة
  function enableCropperControls() {
    console.log('[enableCropperControls] تفعيل أزرار التحكم في أداة القص');

    if (!cropper) {
      console.warn('[enableCropperControls] أداة القص غير متوفرة');
      return;
    }

    // تفعيل أزرار الحركة
    const moveUpBtn = document.getElementById('cropperMoveUp');
    const moveDownBtn = document.getElementById('cropperMoveDown');
    const moveLeftBtn = document.getElementById('cropperMoveLeft');
    const moveRightBtn = document.getElementById('cropperMoveRight');

    if (moveUpBtn) {
      moveUpBtn.onclick = () => cropper.move(0, -10);
    }
    if (moveDownBtn) {
      moveDownBtn.onclick = () => cropper.move(0, 10);
    }
    if (moveLeftBtn) {
      moveLeftBtn.onclick = () => cropper.move(-10, 0);
    }
    if (moveRightBtn) {
      moveRightBtn.onclick = () => cropper.move(10, 0);
    }

    // تفعيل أزرار التكبير والتصغير
    const zoomInBtn = document.getElementById('cropperZoomIn');
    const zoomOutBtn = document.getElementById('cropperZoomOut');

    if (zoomInBtn) {
      zoomInBtn.onclick = () => cropper.zoom(0.1);
    }
    if (zoomOutBtn) {
      zoomOutBtn.onclick = () => cropper.zoom(-0.1);
    }

    // تفعيل زر الدوران
    const rotateBtn = document.getElementById('cropperRotateRight');
    if (rotateBtn) {
      rotateBtn.onclick = () => cropper.rotate(90);
    }

    console.log('[enableCropperControls] تم تفعيل جميع أزرار التحكم');
  }

  disableAllControls();
  if (imgEl.cropperInstance) {
    imgEl.cropperInstance.destroy();
    imgEl.cropperInstance = null;
  }
  cropBtn.onclick = null;

  // تحرير الموارد عند إغلاق المودال
  function cleanup() {
    if (imgEl.cropperInstance) {
      imgEl.cropperInstance.destroy();
      imgEl.cropperInstance = null;
    }
    cropper = null;
    cropBtn.onclick = null;
    if (objectUrl) {
      try { URL.revokeObjectURL(objectUrl); } catch {}
      objectUrl = null;
    }
    if (timeoutTimer) {
      clearTimeout(timeoutTimer);
      timeoutTimer = null;
    }
  }
  modalEl.addEventListener('hidden.bs.modal', function cleanupListener() {
    cleanup();
    modalEl.removeEventListener('hidden.bs.modal', cleanupListener);
  });

  // timeout صارم (5 دقائق)
  timeoutTimer = setTimeout(() => {
    cleanup();
    loaderModal.hide();
    Swal.fire({ icon: 'error', title: 'انتهى الوقت', text: 'لم يتم استكمال قص الصورة خلال الوقت المحدد (5 دقائق).' });
    callback(null);
  }, 300000);

  // تحميل الصورة مع تشخيص محسن
  console.log('[showCropperModal] بدء قراءة الملف باستخدام FileReader...');
  const reader = new FileReader();

  reader.onerror = function(error) {
    console.error('[showCropperModal] خطأ في قراءة الملف:', error);
    cleanup();
    loaderModal.hide();
    if (callback) callback(null, 'خطأ في قراءة الملف');
  };

  reader.onloadstart = function() {
    console.log('[showCropperModal] بدء قراءة الملف...');
  };

  reader.onprogress = function(e) {
    if (e.lengthComputable) {
      const percentLoaded = Math.round((e.loaded / e.total) * 100);
      console.log('[showCropperModal] تقدم قراءة الملف:', percentLoaded + '%');
    }
  };

  reader.onload = function(e) {
    console.log('[showCropperModal] تم قراءة الملف بنجاح، بدء تحميل الصورة...');

    if (!e.target.result) {
      console.error('[showCropperModal] لم يتم الحصول على بيانات من الملف!');
      cleanup();
      loaderModal.hide();
      if (callback) callback(null, 'لا توجد بيانات في الملف');
      return;
    }

    if (objectUrl) {
      try { URL.revokeObjectURL(objectUrl); } catch {}
    }
    objectUrl = e.target.result;

    // تعيين مصدر الصورة مع تحسين العرض الأولي
    imgEl.src = objectUrl;

    // ⭐ تحسين العرض الأولي للصورة
    imgEl.style.width = 'auto';
    imgEl.style.height = 'auto';
    imgEl.style.maxWidth = '100%';
    imgEl.style.maxHeight = '100%';

    cropperReady = false;
    let tryCount = 0;
    const maxTries = 15;

    console.log('[showCropperModal] تم تعيين مصدر الصورة، انتظار تحميل الصورة...');

    // مستمع تحميل الصورة محسن
    imgEl.onload = function() {
      console.log('[showCropperModal] تم تحميل الصورة بنجاح! الأبعاد:', imgEl.naturalWidth + 'x' + imgEl.naturalHeight);

      // ⭐ تحسين جذري لحجم الصورة عند التحميل - لحل مشكلة صغر الحجم
      const containerWidth = imgEl.parentElement.clientWidth || 800;
      const containerHeight = imgEl.parentElement.clientHeight || 600;
      const imageAspectRatio = imgEl.naturalWidth / imgEl.naturalHeight;
      const containerAspectRatio = containerWidth / containerHeight;

      let displayWidth, displayHeight;

      // تكبير الصورة لتملأ المساحة المتاحة بنسبة أكبر
      if (imageAspectRatio > containerAspectRatio) {
        // الصورة أعرض من الحاوية - استخدم 95% من العرض
        displayWidth = containerWidth * 0.95;
        displayHeight = displayWidth / imageAspectRatio;

        // إذا كان الارتفاع أصغر من 80% من الحاوية، زد الحجم
        if (displayHeight < containerHeight * 0.8) {
          displayHeight = containerHeight * 0.85;
          displayWidth = displayHeight * imageAspectRatio;
        }
      } else {
        // الصورة أطول من الحاوية - استخدم 90% من الارتفاع
        displayHeight = containerHeight * 0.9;
        displayWidth = displayHeight * imageAspectRatio;

        // إذا كان العرض أصغر من 80% من الحاوية، زد الحجم
        if (displayWidth < containerWidth * 0.8) {
          displayWidth = containerWidth * 0.85;
          displayHeight = displayWidth / imageAspectRatio;
        }
      }

      // ⭐ ضمان حد أدنى للحجم حتى للصور الصغيرة
      const minSize = Math.min(containerWidth, containerHeight) * 0.7;
      if (displayWidth < minSize || displayHeight < minSize) {
        if (displayWidth < displayHeight) {
          displayWidth = minSize;
          displayHeight = displayWidth / imageAspectRatio;
        } else {
          displayHeight = minSize;
          displayWidth = displayHeight * imageAspectRatio;
        }
      }

      // تطبيق الحجم المحسوب
      imgEl.style.width = displayWidth + 'px';
      imgEl.style.height = displayHeight + 'px';

      console.log('[showCropperModal] تم تحسين حجم الصورة بقوة:', displayWidth + 'x' + displayHeight);
      console.log('[showCropperModal] حجم الحاوية:', containerWidth + 'x' + containerHeight);
    };

    imgEl.onerror = function(imgError) {
      console.error('[showCropperModal] خطأ في تحميل الصورة:', imgError);
      cleanup();
      loaderModal.hide();
      if (callback) callback(null, 'خطأ في تحميل الصورة');
    };

    function initCropper(force = false) {
      console.log('[showCropperModal] محاولة تهيئة أداة القص - المحاولة:', tryCount + 1, 'إجبار:', force);
      console.log('[showCropperModal] أبعاد الصورة الطبيعية:', imgEl.naturalWidth + 'x' + imgEl.naturalHeight);
      console.log('[showCropperModal] أبعاد الصورة المعروضة:', imgEl.width + 'x' + imgEl.height);

      // تحسين حجم الصورة لتكون محدودة داخل المنطقة المُحددة
      const deviceInfo = detectDeviceType ? detectDeviceType() : {};
      const containerWidth = imgEl.parentElement.clientWidth || window.innerWidth;
      const containerHeight = imgEl.parentElement.clientHeight || window.innerHeight;

      if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
        imgEl.style.maxHeight = '70vh !important'; /* تقييد الارتفاع للموبايل */
        imgEl.style.maxWidth = '90vw !important'; /* تقييد العرض */
        imgEl.style.minWidth = '85vw !important'; /* حد أدنى محدود */
        imgEl.style.minHeight = '65vh !important'; /* حد أدنى محدود */
        imgEl.style.width = '90% !important'; /* عرض محدود */
        imgEl.style.height = '90% !important'; /* ارتفاع محدود */
        imgEl.style.objectPosition = 'center center';
        console.log('[showCropperModal] تطبيق أسلوب موبايل كروم مع تقييد المنطقة');
      } else if (deviceInfo.isMobile || deviceInfo.isTablet) {
        imgEl.style.maxHeight = '70vh !important'; /* تقييد للموبايل */
        imgEl.style.maxWidth = '90vw !important'; /* تقييد العرض */
        imgEl.style.minWidth = '85vw !important'; /* حد أدنى محدود */
        imgEl.style.minHeight = '65vh !important'; /* حد أدنى محدود */
        imgEl.style.width = '90% !important'; /* عرض محدود */
        imgEl.style.height = '90% !important'; /* ارتفاع محدود */
        imgEl.style.objectPosition = 'center center';
        console.log('[showCropperModal] تطبيق أسلوب الأجهزة المحمولة مع تقييد المنطقة');
      } else {
        // للكمبيوتر - تقييد صارم للمنطقة
        imgEl.style.maxHeight = '65vh !important'; /* تقييد كبير للكمبيوتر */
        imgEl.style.maxWidth = '70vw !important'; /* تقييد العرض */
        imgEl.style.minWidth = '55vw !important'; /* حد أدنى محدود */
        imgEl.style.minHeight = '55vh !important'; /* حد أدنى محدود */
        imgEl.style.width = '85% !important'; /* عرض محدود */
        imgEl.style.height = '85% !important'; /* ارتفاع محدود */
        imgEl.style.objectPosition = 'center center';
        console.log('[showCropperModal] تطبيق أسلوب الكمبيوتر مع تقييد صارم للمنطقة');
      }

      if ((imgEl.naturalWidth > 0 && imgEl.naturalHeight > 0) || force) {
        console.log('[showCropperModal] الصورة جاهزة، بدء تهيئة Cropper.js بحدود مقيدة...');

        if (imgEl.cropperInstance) {
          console.log('[showCropperModal] تدمير أداة القص الموجودة...');
          imgEl.cropperInstance.destroy();
          imgEl.cropperInstance = null;
        }

        try {
          cropper = new Cropper(imgEl, {
            aspectRatio: NaN,
            viewMode: 1, /* تغيير إلى viewMode 1 لتقييد المنطقة */
            responsive: true,
            autoCropArea: 0.75, /* تقليل إلى 75% لضمان عدم تجاوز الحدود */
            movable: true,
            zoomable: true,
            rotatable: true,
            scalable: false, /* منع التكبير خارج الحدود */
            background: true,
            guides: true,
            center: true,
            dragMode: 'crop', /* تغيير إلى crop لتقييد الحركة */
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            minContainerWidth: containerWidth * 0.7, /* تقليل الحد الأدنى */
            minContainerHeight: containerHeight * 0.6, /* تقليل الحد الأدنى */
            minCanvasWidth: 0,
            minCanvasHeight: 0,
            minCropBoxWidth: 60, /* حد أدنى صغير لصندوق القص */
            minCropBoxHeight: 60, /* حد أدنى صغير لصندوق القص */
            ready() {
              console.log('[showCropperModal] ✅ تم تهيئة أداة القص مع تقييد المنطقة!');
              cropperReady = true;
              enableAllControls();
              enableCropperControls();

              // تحديد منطقة القص بدقة داخل الحدود المُحددة
              setTimeout(() => {
                const containerData = cropper.getContainerData();
                const canvasData = cropper.getCanvasData();

                console.log('[showCropperModal] بيانات الحاوية:', containerData);
                console.log('[showCropperModal] بيانات Canvas:', canvasData);

                // تحديد Canvas بحجم مقيد داخل المنطقة المُحددة
                try {
                  const restrictedCanvasWidth = containerData.width * 0.75; /* 75% من عرض الحاوية */
                  const restrictedCanvasHeight = containerData.height * 0.75; /* 75% من ارتفاع الحاوية */

                  const imageAspectRatio = imgEl.naturalWidth / imgEl.naturalHeight;
                  let newCanvasWidth, newCanvasHeight;

                  // حساب الحجم المقيد للصورة
                  if (imageAspectRatio > (restrictedCanvasWidth / restrictedCanvasHeight)) {
                    newCanvasWidth = restrictedCanvasWidth;
                    newCanvasHeight = restrictedCanvasWidth / imageAspectRatio;
                  } else {
                    newCanvasHeight = restrictedCanvasHeight;
                    newCanvasWidth = restrictedCanvasHeight * imageAspectRatio;
                  }

                  // توسيط الصورة في المنطقة المُحددة
                  const leftPosition = (containerData.width - newCanvasWidth) / 2;
                  const topPosition = (containerData.height - newCanvasHeight) / 2;

                  cropper.setCanvasData({
                    left: Math.max(0, leftPosition),
                    top: Math.max(0, topPosition),
                    width: newCanvasWidth,
                    height: newCanvasHeight
                  });

                  console.log('[showCropperModal] تم تحديد Canvas داخل المنطقة المقيدة:', newCanvasWidth + 'x' + newCanvasHeight);

                  // تحديد منطقة القص بحجم مقيد داخل الصورة
                  setTimeout(() => {
                    const updatedCanvasData = cropper.getCanvasData();

                    // حساب حجم منطقة القص المقيدة
                    const cropBoxWidth = updatedCanvasData.width * 0.70; /* 70% من حجم Canvas */
                    const cropBoxHeight = updatedCanvasData.height * 0.70; /* 70% من حجم Canvas */
                    const cropBoxLeft = updatedCanvasData.left + (updatedCanvasData.width * 0.15); /* توسيط */
                    const cropBoxTop = updatedCanvasData.top + (updatedCanvasData.height * 0.15); /* توسيط */

                    cropper.setCropBoxData({
                      width: cropBoxWidth,
                      height: cropBoxHeight,
                      left: cropBoxLeft,
                      top: cropBoxTop
                    });

                    console.log('[showCropperModal] تم تحديد منطقة القص المقيدة داخل الصورة');

                    // إضافة قيود إضافية لمنع تجاوز الحدود
                    cropper.setData({
                      x: Math.max(0, cropBoxLeft - updatedCanvasData.left),
                      y: Math.max(0, cropBoxTop - updatedCanvasData.top),
                      width: cropBoxWidth,
                      height: cropBoxHeight,
                      rotate: 0,
                      scaleX: 1,
                      scaleY: 1
                    });

                  }, 200);

                } catch(e) {
                  console.warn('[showCropperModal] فشل في تحديد المنطقة المقيدة:', e);

                  // طريقة احتياطية مع تقييد أكبر
                  try {
                    const fallbackWidth = canvasData.width * 0.60; /* تقليل أكبر */
                    const fallbackHeight = canvasData.height * 0.60; /* تقليل أكبر */
                    const fallbackLeft = canvasData.left + (canvasData.width * 0.20); /* توسيط */
                    const fallbackTop = canvasData.top + (canvasData.height * 0.20); /* توسيط */

                    cropper.setCropBoxData({
                      width: fallbackWidth,
                      height: fallbackHeight,
                      left: fallbackLeft,
                      top: fallbackTop
                    });

                    console.log('[showCropperModal] تم استخدام الطريقة الاحتياطية مع تقييد أكبر');
                  } catch(e2) {
                    console.warn('[showCropperModal] فشل في الطريقة الاحتياطية أيضاً:', e2);
                  }
                }
              }, 300);

              // منع التكبير المفرط الذي يتجاوز الحدود
              setTimeout(() => {
                try {
                  const currentData = cropper.getData();
                  const containerData = cropper.getContainerData();

                  // إذا كانت الصورة أصغر من 60% من الحاوية، قم بتكبيرها قليلاً فقط
                  if (currentData.width < containerData.width * 0.6 || currentData.height < containerData.height * 0.6) {
                    const limitedZoomRatio = Math.min(
                      (containerData.width * 0.7) / currentData.width,
                      (containerData.height * 0.7) / currentData.height
                    );

                    if (limitedZoomRatio > 1 && limitedZoomRatio <= 1.2) {
                      cropper.zoomTo(limitedZoomRatio);
                      console.log('[showCropperModal] تم تطبيق زوم محدود:', limitedZoomRatio);
                    }
                  }
                } catch(e) {
                  console.warn('[showCropperModal] فشل في تطبيق الزوم المحدود:', e);
                }
              }, 500);
            },
            error(err) {
              console.error('[showCropperModal] خطأ في تهيئة أداة القص:', err);
              cleanup();
              loaderModal.hide();
              if (callback) callback(null, 'خطأ في تهيئة أداة القص');
            }
          });
          imgEl.cropperInstance = cropper;
          console.log('[showCropperModal] تم إنشاء أداة القص وربطها بالصورة مع تقييد المنطقة');
        } catch (cropperError) {
          console.error('[showCropperModal] استثناء في إنشاء أداة القص:', cropperError);
          cleanup();
          loaderModal.hide();
          if (callback) callback(null, 'استثناء في أداة القص');
        }
      } else if (tryCount < maxTries) {
        tryCount++;
        console.log('[showCropperModal] الصورة غير جاهزة، إعادة المحاولة بعد', 120 * tryCount, 'مللي ثانية');
        setTimeout(initCropper, 120 * tryCount);
      } else {
        console.error('[showCropperModal] فشل في تحميل الصورة بعد', maxTries, 'محاولة');
        cleanup();
        loaderModal.hide();
        if (callback) callback(null, 'فشل في تحميل الصورة');
      }
    }
    setTimeout(initCropper, 60);

    cropBtn.onclick = async function() {
      // عند الضغط على زر القص، أظهر SweetAlert مع progress bar
      let swalProgress = 0;
      let swalInstance = null;
      let swalClosed = false;
      Swal.fire({
        title: 'جاري معالجة الصورة...',
        html: '<div id="swal-progress-text">0%</div><div class="progress mt-2" style="height: 18px;"><div id="swal-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%">0%</div></div>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          swalInstance = Swal.getPopup();
        }
      });

      function updateSwalProgress(percent) {
        swalProgress = Math.max(0, Math.min(100, Math.round(percent)));
        const text = document.getElementById('swal-progress-text');
        const bar = document.getElementById('swal-progress-bar');
        if (text) text.textContent = swalProgress + '%';
        if (bar) {
          bar.style.width = swalProgress + '%';
          bar.textContent = swalProgress + '%';
        }
      }

      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      if (!cropperReady || !cropper || !cropper.getCroppedCanvas) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'لم يتم تهيئة أداة القص بعد. يرجى الانتظار أو إعادة المحاولة.' });
        cleanup();
        callback(null);
        return;
      }
      if (!imgEl || !imgEl.src || imgEl.naturalWidth === 0 || imgEl.naturalHeight === 0) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'لم يتم تحميل الصورة بشكل صحيح. يرجى إعادة المحاولة أو اختيار صورة أخرى.' });
        cleanup();
        callback(null);
        return;
      }
      const data = cropper.getData(true);
      let canvas;
      try {
        canvas = cropper.getCroppedCanvas({
          width: Math.round(data.width),
          height: Math.round(data.height),
          imageSmoothingQuality: 'high'
        });
      } catch (err) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر قص الصورة. يرجى التأكد من أن الصورة ظاهرة بشكل صحيح ثم أعد المحاولة.' });
        cleanup();
        callback(null);
        return;
      }
      if (!canvas || canvas.width === 0 || canvas.height === 0) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر قص الصورة. يرجى التأكد من أن الصورة ظاهرة بشكل صحيح ثم أعد المحاولة.' });
        cleanup();
        callback(null);
        return;
      }
      try {
        let imageBitmap;
        if (canvas.transferToImageBitmap) {
          imageBitmap = canvas.transferToImageBitmap();
        } else {
          imageBitmap = await createImageBitmap(canvas);
        }
        const originalName = file.name;
        const ext = originalName.substring(originalName.lastIndexOf('.'));
        const base = originalName.replace(ext, '');
        const uniqueSuffix = '_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
        let compressOptions = {
          maxSizeMB: isMobileChrome() ? 0.08 : 0.1,
          maxWidthOrHeight: isMobileChrome() ? 900 : Math.max(canvas.width, canvas.height),
          initialQuality: isMobileChrome() ? 0.5 : 0.7,
          fileType: file.type
        };
        const worker = new Worker('/js/imageProcessorWorker.js');
        worker.postMessage({ imageBitmap, options: compressOptions }, [imageBitmap]);
        worker.onmessage = function(ev) {
          if (ev.data.type === 'progress') {
            updateSwalProgress(ev.data.percent);
          } else if (ev.data.type === 'done') {
            const compressedBlob = ev.data.blob;
            const fileType = ev.data.fileType || file.type;
            const newFile = new File([compressedBlob], base + uniqueSuffix + '_cropped' + ext, { type: fileType });
            cropBtn.blur && cropBtn.blur();
            setTimeout(() => {
              if (!swalClosed) Swal.close();
              swalClosed = true;
              cleanup();
              callback(newFile);
            }, 0);
            setTimeout(() => { if (!swalClosed) Swal.close(); swalClosed = true; }, 700);
            worker.terminate();
          } else if (ev.data.type === 'error') {
            console.error('Worker error:', ev.data.message);
            if (!swalClosed) Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر ضغط الصورة: ' + (ev.data.message || '') });
            cleanup();
            callback(null);
            worker.terminate();
          }
        };
        worker.onerror = function(err) {
          console.error('Worker onerror:', err);
          if (!swalClosed) Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ في معالجة الصورة (العامل).' });
          cleanup();
          callback(null);
          worker.terminate();
        };
      } catch (err) {
        if (!swalClosed) Swal.fire({ icon: 'error', title: 'خطأ', text: 'فشل معالجة الصورة: ' + (err && err.message ? err.message : err) });
        cleanup();
        callback(null);
      }
    };
  };
  reader.readAsDataURL(file);

  const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  bsModal.show();

  modalEl.addEventListener('shown.bs.modal', function onShown() {
    let shownTry = 0;
    function tryInitOnModal() {
      if ((!imgEl.cropperInstance || !imgEl.cropperInstance.getData) && imgEl.naturalWidth > 0) {
        if (imgEl.cropperInstance) {
          imgEl.cropperInstance.destroy();
          imgEl.cropperInstance = null;
        }

        // تطبيق نفس التحسينات المقيدة عند إظهار المودال
        const deviceInfo = window.DeviceImageSource ? window.DeviceImageSource.detectDevice() : {};

        if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
          imgEl.style.maxHeight = '65vh'; /* تقييد للموبايل */
          imgEl.style.maxWidth = '90vw'; /* تقييد العرض */
          imgEl.style.minWidth = '85vw'; /* تقييد الحد الأدنى */
          imgEl.style.minHeight = '60vh'; /* تقييد الحد الأدنى */
          imgEl.style.objectPosition = 'center center';
        } else if (deviceInfo.isMobile || deviceInfo.isTablet) {
          imgEl.style.maxHeight = '65vh'; /* تقييد للموبايل */
          imgEl.style.maxWidth = '90vw'; /* تقييد العرض */
          imgEl.style.minWidth = '85vw'; /* تقييد الحد الأدنى */
          imgEl.style.minHeight = '60vh'; /* تقييد الحد الأدنى */
          imgEl.style.objectPosition = 'center center';
        } else {
          imgEl.style.maxHeight = '500px'; /* تقييد أكبر للكمبيوتر */
          imgEl.style.maxWidth = '650px'; /* تقييد أكبر للكمبيوتر */
          imgEl.style.minWidth = '400px'; /* حد أدنى محدود */
          imgEl.style.minHeight = '300px'; /* حد أدنى محدود */
          imgEl.style.objectPosition = 'center center';
        }

        cropper = new Cropper(imgEl, {
          aspectRatio: NaN,
          viewMode: 1, /* تقييد المنطقة */
          responsive: true,
          autoCropArea: 0.70, /* تقليل إلى 70% */
          movable: true,
          zoomable: true,
          rotatable: true,
          scalable: false, /* منع التكبير خارج الحدود */
          background: true,
          guides: true,
          center: true,
          dragMode: 'crop', /* تقييد الحركة */
          cropBoxMovable: true,
          cropBoxResizable: true,
          toggleDragModeOnDblclick: false,
          minContainerWidth: 160, /* تقليل للموبايل */
          minContainerHeight: 160, /* تقليل للموبايل */
          minCanvasWidth: 0,
          minCanvasHeight: 0,
          minCropBoxWidth: 30, /* حد أدنى صغير */
          minCropBoxHeight: 30, /* حد أدنى صغير */
          ready() {
            cropperReady = true;
            enableAllControls();
            enableCropperControls();

            // تحديد الصورة بحجم مقيد في المودال
            setTimeout(() => {
              const containerData = cropper.getContainerData();
              const canvasData = cropper.getCanvasData();

              // تحديد الصورة بحجم مقيد
              try {
                const restrictedWidth = canvasData.width * 0.70; /* تقليل إلى 70% */
                const restrictedHeight = canvasData.height * 0.70; /* تقليل إلى 70% */
                const restrictedLeft = canvasData.left + (canvasData.width * 0.15); /* توسيط */
                const restrictedTop = canvasData.top + (canvasData.height * 0.15); /* توسيط */

                cropper.setCropBoxData({
                  width: restrictedWidth,
                  height: restrictedHeight,
                  left: restrictedLeft,
                  top: restrictedTop
                });

                console.log('[showCropperModal] تم تحديد الصورة بحجم مقيد في المودال');
              } catch(e) {
                console.warn('[showCropperModal] فشل في تحديد الصورة بحجم مقيد في المودال:', e);
              }

              // توسيط الصورة
              if (canvasData.width < containerData.width) {
                const imageCenterPosition = (containerData.width - canvasData.width) / 2;
                try {
                  cropper.setCanvasData({
                    left: Math.max(0, imageCenterPosition),
                    top: canvasData.top,
                    width: canvasData.width,
                    height: canvasData.height
                  });
                  console.log('[showCropperModal] تم توسيط الصورة في المودال');
                } catch(e) {
                  console.warn('[showCropperModal] فشل في توسيط الصورة في المودال:', e);
                }
              }
            }, 300);

            // معالجة خاصة للأجهزة المحمولة مع تقييد أكبر
            if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
              try {
                setTimeout(() => {
                  const mobileCanvasData = cropper.getCanvasData();

                  // تحديد الصورة بحجم مقيد للموبايل
                  const mobileRestrictedWidth = mobileCanvasData.width * 0.65; /* تقييد أكبر للموبايل */
                  const mobileRestrictedHeight = mobileCanvasData.height * 0.65; /* تقييد أكبر للموبايل */
                  const mobileRestrictedLeft = mobileCanvasData.left + (mobileCanvasData.width * 0.175); /* توسيط */
                  const mobileRestrictedTop = mobileCanvasData.top + (mobileCanvasData.height * 0.175); /* توسيط */

                  cropper.setCropBoxData({
                    width: mobileRestrictedWidth,
                    height: mobileRestrictedHeight,
                    left: mobileRestrictedLeft,
                    top: mobileRestrictedTop
                  });
                  console.log('[showCropperModal] تم تطبيق تحديد مقيد للصورة على موبايل كروم في المودال');
                }, 400);
              } catch(e){
                console.warn('[showCropperModal] فشل في تطبيق إعدادات موبايل كروم في المودال:', e);
              }
            }
          }
        });
        imgEl.cropperInstance = cropper;
      } else if (shownTry < 8 && imgEl.naturalWidth === 0) {
        shownTry++;
        setTimeout(tryInitOnModal, 120 * shownTry);
      }
    }
    setTimeout(tryInitOnModal, 120);
    modalEl.removeEventListener('shown.bs.modal', onShown);
  });

  // ⭐ إضافة دالة كشف نوع الجهاز إذا لم تكن متوفرة
  function detectDeviceType() {
    if (window.DeviceImageSource && window.DeviceImageSource.detectDevice) {
      return window.DeviceImageSource.detectDevice();
    }

    const userAgent = navigator.userAgent.toLowerCase();
    const isMobile = /android|webos|iphone|ipod|blackberry|iemobile|opera mini/i.test(userAgent);
    const isTablet = /ipad|tablet|(android(?!.*mobile))/i.test(userAgent);
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    const isSmallScreen = window.innerWidth <= 768;

    return {
      isMobile: isMobile || (isTouchDevice && isSmallScreen && !isTablet),
      isTablet: isTablet,
      isDesktop: !isMobile && !isTablet,
      isTouchDevice: isTouchDevice,
      hasCamera: navigator.mediaDevices && navigator.mediaDevices.getUserMedia,
      screenWidth: window.innerWidth,
      userAgent: userAgent
    };
  }
};

// FileUploadHandler: Robust AJAX file upload for all upload zones
document.addEventListener('DOMContentLoaded', function() {
  // Helper: Find all upload zones
  const uploadZones = document.querySelectorAll('[data-upload-role="zone"]');
  uploadZones.forEach(function(zone) {
    const fileInput = zone.querySelector('[data-file-role="input"]');
    const selectBtn = zone.querySelector('[data-file-role="select"]');
    const preview = zone.querySelector('[data-file-role="preview"]');
    const hiddenFileId = zone.querySelector('[data-file-role="file_id"]');
    const hiddenTempName = zone.querySelector('[data-file-role="temp_file_name"]');
    const hiddenDocType = zone.querySelector('[data-file-role="document_type_value"]');

    // When select button is clicked, trigger file input
    if (selectBtn && fileInput) {
      selectBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.click();
      });
    }

    // On file input change
    if (fileInput) {
      fileInput.addEventListener('change', function(e) {
        const file = fileInput.files[0];
        if (!file) return;
        // Optionally: validate file type/size here
        // Show cropper modal if image, else upload directly
        if (file.type.startsWith('image/')) {
          window.showCropperModal(file, function(croppedFile) {
            if (croppedFile) {
              uploadFileAJAX(croppedFile, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
            } else {
              // User cancelled or error
              fileInput.value = '';
            }
          });
        } else {
          uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
        }
      });
    }
  });

  // AJAX upload logic
  function uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType) {
    // Show loader
    const loaderModalEl = document.getElementById('compressLoaderModal');
    const loaderModal = loaderModalEl ? bootstrap.Modal.getOrCreateInstance(loaderModalEl) : null;
    loaderModal && loaderModal.show();

    const formData = new FormData();
    formData.append('file', file);
    // Optionally: add CSRF token if needed
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) formData.append('_token', csrf.getAttribute('content'));
    // Optionally: add document_type_value if available
    if (hiddenDocType && hiddenDocType.value) {
      formData.append('document_type_value', hiddenDocType.value);
    }

    fetch('/ajax/file-upload', {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json',
      },
    })
    .then(response => response.json())
    .then(data => {
      loaderModal && loaderModal.hide();
      if (data.success && data.file_id && data.temp_file_name) {
        // Update hidden fields
        if (hiddenFileId) hiddenFileId.value = data.file_id;
        if (hiddenTempName) hiddenTempName.value = data.temp_file_name;
        // Show preview (if image)
        if (preview && file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = '';
          };
          reader.readAsDataURL(file);
        } else if (preview) {
          preview.textContent = file.name;
          preview.style.display = '';
        }
        // Optionally: show success message
      } else {
        Swal.fire({ icon: 'error', title: 'خطأ', text: data.message || 'فشل رفع الملف. حاول مرة أخرى.' });
        // Reset hidden fields
        if (hiddenFileId) hiddenFileId.value = '';
        if (hiddenTempName) hiddenTempName.value = '';
        if (preview) preview.style.display = 'none';
      }
    })
    .catch(err => {
      loaderModal && loaderModal.hide();
      Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ أثناء رفع الملف. حاول مرة أخرى.' });
      if (hiddenFileId) hiddenFileId.value = '';
      if (hiddenTempName) hiddenTempName.value = '';
      if (preview) preview.style.display = 'none';
    });
  }
});
</script>

<!-- دالة مختصرة لسهولة الوصول -->
<script>
window.showCropper = window.showCropperModal;

// فحص تشخيصي لتأكيد توفر أداة القص
console.log('[Cropper] ✅ تم تحميل أداة القص بنجاح');
console.log('[Cropper] window.showCropperModal:', typeof window.showCropperModal);
console.log('[Cropper] window.showCropper:', typeof window.showCropper);

// إرسال إشارة عالمية بأن أداة القص جاهزة
window.cropperReady = true;
if (window.dispatchEvent) {
  window.dispatchEvent(new CustomEvent('cropperReady', {
    detail: {
      showCropperModal: window.showCropperModal,
      showCropper: window.showCropper
    }
  }));
}
</script>



<!-- Loader Modal -->
<div class="modal fade" id="compressLoaderModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <div class="spinner-border text-primary mb-3" role="status">
          <span class="visually-hidden">جاري إدراج الوثيقة...</span>
        </div>
        <div id="compressLoaderTitle" class="fw-bold">جاري إدراج الوثيقة</div>
      </div>
    </div>
  </div>
</div>

<!-- Cropper Modal Markup -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content d-flex flex-column vh-100">
      <div class="modal-header">
        <h5 class="modal-title" id="cropperModalLabel">قص الصورة</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="cropper-modal-body flex-grow-1 d-flex flex-column flex-md-row p-0 overflow-hidden">

        <!-- Crop Area -->
        <div class="crop-area flex-grow-1 d-flex justify-content-center align-items-center bg-light p-2">
          <img id="cropperImage" src="" alt="Image to crop" style="max-width: 100%; height: auto;" />
        </div>

        <!-- Controls Panel -->
        <div class="controls-panel-container bg-white d-flex flex-column justify-content-between p-3">
          <div class="controls-grid d-grid gap-2" style="grid-template-columns: repeat(3, 1fr);">
            <button id="cropperMoveUp" class="btn btn-outline-secondary" title="↑" disabled><i class="fas fa-arrow-up"></i></button>
            <button id="cropperMoveLeft" class="btn btn-outline-secondary" title="←" disabled><i class="fas fa-arrow-left"></i></button>
            <span class="move-center"></span>
            <button id="cropperMoveRight" class="btn btn-outline-secondary" title="→" disabled><i class="fas fa-arrow-right"></i></button>
            <button id="cropperMoveDown" class="btn btn-outline-secondary" title="↓" disabled><i class="fas fa-arrow-down"></i></button>
            <button id="cropperZoomIn" class="btn btn-outline-secondary" title="＋" disabled><i class="fas fa-search-plus"></i></button>
            <button id="cropperZoomOut" class="btn btn-outline-secondary" title="－" disabled><i class="fas fa-search-minus"></i></button>
            <button id="cropperRotateRight" class="btn btn-outline-secondary" title="↻" disabled><i class="fas fa-sync-alt"></i></button>
          </div>
          <div class="action-buttons d-flex flex-column gap-2 mt-3">
            <button id="cropperCropBtn" class="btn btn-success" disabled>قص وحفظ</button>
            <button class="btn btn-danger" data-bs-dismiss="modal">إلغاء</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- إضافة مكتبة browser-image-compression -->
<script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>

<script>
window.showCropperModal = function(file, callback) {
  // فحص شامل للملف المُمرر
  console.log('[showCropperModal] بدء فحص الملف المُمرر:', file);

  // التحقق من وجود الملف
  if (!file) {
    console.error('[showCropperModal] لم يتم تمرير ملف!');
    if (callback) callback(null, 'لم يتم تمرير ملف');
    return;
  }

  // التحقق من أن الملف هو File أو Blob
  if (!(file instanceof File) && !(file instanceof Blob)) {
    console.error('[showCropperModal] الملف المُمرر ليس من نوع File أو Blob:', typeof file, file);
    if (callback) callback(null, 'نوع الملف غير صالح');
    return;
  }

  // التحقق من نوع الملف
  if (!file.type || !file.type.startsWith('image/')) {
    console.error('[showCropperModal] الملف ليس صورة! نوع الملف:', file.type);
    if (callback) callback(null, 'الملف ليس صورة');
    return;
  }

  // التحقق من حجم الملف
  if (file.size === 0) {
    console.error('[showCropperModal] الملف فارغ! حجم الملف:', file.size);
    if (callback) callback(null, 'الملف فارغ');
    return;
  }

  // التحقق من حجم الملف (حد أقصى 50MB)
  const maxSize = 50 * 1024 * 1024; // 50MB
  if (file.size > maxSize) {
    console.error('[showCropperModal] الملف كبير جداً! حجم الملف:', file.size, 'الحد الأقصى:', maxSize);
    if (callback) callback(null, 'حجم الملف كبير جداً');
    return;
  }

  console.log('[showCropperModal] ✅ الملف صالح - الاسم:', file.name, 'النوع:', file.type, 'الحجم:', file.size, 'bytes');

  const modalEl = document.getElementById('cropperModal');
  const imgEl = document.getElementById('cropperImage');
  const cropBtn = document.getElementById('cropperCropBtn');
  const loaderModalEl = document.getElementById('compressLoaderModal');

  // التحقق من وجود العناصر المطلوبة
  if (!modalEl) {
    console.error('[showCropperModal] عنصر المودال غير موجود!');
    if (callback) callback(null, 'عنصر المودال مفقود');
    return;
  }

  if (!imgEl) {
    console.error('[showCropperModal] عنصر الصورة غير موجود!');
    if (callback) callback(null, 'عنصر الصورة مفقود');
    return;
  }

  if (!cropBtn) {
    console.error('[showCropperModal] زر القص غير موجود!');
    if (callback) callback(null, 'زر القص مفقود');
    return;
  }

  console.log('[showCropperModal] ✅ جميع العناصر موجودة، بدء تحميل الصورة...');

  const loaderModal = bootstrap.Modal.getOrCreateInstance(loaderModalEl);
  let cropper;
  let cropperReady = false;
  let timeoutTimer = null;
  let objectUrl = null;

  function isMobileChrome() {
    const ua = navigator.userAgent;
    return /Android|iPhone|iPad|iPod/i.test(ua) && /Chrome/i.test(ua);
  }

  function disableAllControls() {
    cropBtn.disabled = true;
    ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = true;
    });
  }

  function enableAllControls() {
    cropBtn.disabled = false;
    ['MoveUp','MoveDown','MoveLeft','MoveRight','ZoomIn','ZoomOut','RotateRight'].forEach(action => {
      const btn = document.getElementById('cropper' + action);
      if (btn) btn.disabled = false;
    });
  }

  // ⭐ إضافة دالة enableCropperControls المفقودة
  function enableCropperControls() {
    console.log('[enableCropperControls] تفعيل أزرار التحكم في أداة القص');

    if (!cropper) {
      console.warn('[enableCropperControls] أداة القص غير متوفرة');
      return;
    }

    // تفعيل أزرار الحركة
    const moveUpBtn = document.getElementById('cropperMoveUp');
    const moveDownBtn = document.getElementById('cropperMoveDown');
    const moveLeftBtn = document.getElementById('cropperMoveLeft');
    const moveRightBtn = document.getElementById('cropperMoveRight');

    if (moveUpBtn) {
      moveUpBtn.onclick = () => cropper.move(0, -10);
    }
    if (moveDownBtn) {
      moveDownBtn.onclick = () => cropper.move(0, 10);
    }
    if (moveLeftBtn) {
      moveLeftBtn.onclick = () => cropper.move(-10, 0);
    }
    if (moveRightBtn) {
      moveRightBtn.onclick = () => cropper.move(10, 0);
    }

    // تفعيل أزرار التكبير والتصغير
    const zoomInBtn = document.getElementById('cropperZoomIn');
    const zoomOutBtn = document.getElementById('cropperZoomOut');

    if (zoomInBtn) {
      zoomInBtn.onclick = () => cropper.zoom(0.1);
    }
    if (zoomOutBtn) {
      zoomOutBtn.onclick = () => cropper.zoom(-0.1);
    }

    // تفعيل زر الدوران
    const rotateBtn = document.getElementById('cropperRotateRight');
    if (rotateBtn) {
      rotateBtn.onclick = () => cropper.rotate(90);
    }

    console.log('[enableCropperControls] تم تفعيل جميع أزرار التحكم');
  }

  disableAllControls();
  if (imgEl.cropperInstance) {
    imgEl.cropperInstance.destroy();
    imgEl.cropperInstance = null;
  }
  cropBtn.onclick = null;

  // تحرير الموارد عند إغلاق المودال
  function cleanup() {
    if (imgEl.cropperInstance) {
      imgEl.cropperInstance.destroy();
      imgEl.cropperInstance = null;
    }
    cropper = null;
    cropBtn.onclick = null;
    if (objectUrl) {
      try { URL.revokeObjectURL(objectUrl); } catch {}
      objectUrl = null;
    }
    if (timeoutTimer) {
      clearTimeout(timeoutTimer);
      timeoutTimer = null;
    }
  }
  modalEl.addEventListener('hidden.bs.modal', function cleanupListener() {
    cleanup();
    modalEl.removeEventListener('hidden.bs.modal', cleanupListener);
  });

  // timeout صارم (5 دقائق)
  timeoutTimer = setTimeout(() => {
    cleanup();
    loaderModal.hide();
    Swal.fire({ icon: 'error', title: 'انتهى الوقت', text: 'لم يتم استكمال قص الصورة خلال الوقت المحدد (5 دقائق).' });
    callback(null);
  }, 300000);

  // تحميل الصورة مع تشخيص محسن
  console.log('[showCropperModal] بدء قراءة الملف باستخدام FileReader...');
  const reader = new FileReader();

  reader.onerror = function(error) {
    console.error('[showCropperModal] خطأ في قراءة الملف:', error);
    cleanup();
    loaderModal.hide();
    if (callback) callback(null, 'خطأ في قراءة الملف');
  };

  reader.onloadstart = function() {
    console.log('[showCropperModal] بدء قراءة الملف...');
  };

  reader.onprogress = function(e) {
    if (e.lengthComputable) {
      const percentLoaded = Math.round((e.loaded / e.total) * 100);
      console.log('[showCropperModal] تقدم قراءة الملف:', percentLoaded + '%');
    }
  };

  reader.onload = function(e) {
    console.log('[showCropperModal] تم قراءة الملف بنجاح، بدء تحميل الصورة...');

    if (!e.target.result) {
      console.error('[showCropperModal] لم يتم الحصول على بيانات من الملف!');
      cleanup();
      loaderModal.hide();
      if (callback) callback(null, 'لا توجد بيانات في الملف');
      return;
    }

    if (objectUrl) {
      try { URL.revokeObjectURL(objectUrl); } catch {}
    }
    objectUrl = e.target.result;

    // تعيين مصدر الصورة مع تحسين العرض الأولي
    imgEl.src = objectUrl;

    // ⭐ تحسين العرض الأولي للصورة
    imgEl.style.width = 'auto';
    imgEl.style.height = 'auto';
    imgEl.style.maxWidth = '100%';
    imgEl.style.maxHeight = '100%';

    cropperReady = false;
    let tryCount = 0;
    const maxTries = 15;

    console.log('[showCropperModal] تم تعيين مصدر الصورة، انتظار تحميل الصورة...');

    // مستمع تحميل الصورة محسن
    imgEl.onload = function() {
      console.log('[showCropperModal] تم تحميل الصورة بنجاح! الأبعاد:', imgEl.naturalWidth + 'x' + imgEl.naturalHeight);

      // ⭐ تحسين جذري لحجم الصورة عند التحميل - لحل مشكلة صغر الحجم
      const containerWidth = imgEl.parentElement.clientWidth || 800;
      const containerHeight = imgEl.parentElement.clientHeight || 600;
      const imageAspectRatio = imgEl.naturalWidth / imgEl.naturalHeight;
      const containerAspectRatio = containerWidth / containerHeight;

      let displayWidth, displayHeight;

      // تكبير الصورة لتملأ المساحة المتاحة بنسبة أكبر
      if (imageAspectRatio > containerAspectRatio) {
        // الصورة أعرض من الحاوية - استخدم 95% من العرض
        displayWidth = containerWidth * 0.95;
        displayHeight = displayWidth / imageAspectRatio;

        // إذا كان الارتفاع أصغر من 80% من الحاوية، زد الحجم
        if (displayHeight < containerHeight * 0.8) {
          displayHeight = containerHeight * 0.85;
          displayWidth = displayHeight * imageAspectRatio;
        }
      } else {
        // الصورة أطول من الحاوية - استخدم 90% من الارتفاع
        displayHeight = containerHeight * 0.9;
        displayWidth = displayHeight * imageAspectRatio;

        // إذا كان العرض أصغر من 80% من الحاوية، زد الحجم
        if (displayWidth < containerWidth * 0.8) {
          displayWidth = containerWidth * 0.85;
          displayHeight = displayWidth / imageAspectRatio;
        }
      }

      // ⭐ ضمان حد أدنى للحجم حتى للصور الصغيرة
      const minSize = Math.min(containerWidth, containerHeight) * 0.7;
      if (displayWidth < minSize || displayHeight < minSize) {
        if (displayWidth < displayHeight) {
          displayWidth = minSize;
          displayHeight = displayWidth / imageAspectRatio;
        } else {
          displayHeight = minSize;
          displayWidth = displayHeight * imageAspectRatio;
        }
      }

      // تطبيق الحجم المحسوب
      imgEl.style.width = displayWidth + 'px';
      imgEl.style.height = displayHeight + 'px';

      console.log('[showCropperModal] تم تحسين حجم الصورة بقوة:', displayWidth + 'x' + displayHeight);
      console.log('[showCropperModal] حجم الحاوية:', containerWidth + 'x' + containerHeight);
    };

    imgEl.onerror = function(imgError) {
      console.error('[showCropperModal] خطأ في تحميل الصورة:', imgError);
      cleanup();
      loaderModal.hide();
      if (callback) callback(null, 'خطأ في تحميل الصورة');
    };

    function initCropper(force = false) {
      console.log('[showCropperModal] محاولة تهيئة أداة القص - المحاولة:', tryCount + 1, 'إجبار:', force);
      console.log('[showCropperModal] أبعاد الصورة الطبيعية:', imgEl.naturalWidth + 'x' + imgEl.naturalHeight);
      console.log('[showCropperModal] أبعاد الصورة المعروضة:', imgEl.width + 'x' + imgEl.height);

      // تحسين حجم الصورة لتكون محدودة داخل المنطقة المُحددة
      const deviceInfo = detectDeviceType ? detectDeviceType() : {};
      const containerWidth = imgEl.parentElement.clientWidth || window.innerWidth;
      const containerHeight = imgEl.parentElement.clientHeight || window.innerHeight;

      if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
        imgEl.style.maxHeight = '70vh !important'; /* تقييد الارتفاع للموبايل */
        imgEl.style.maxWidth = '90vw !important'; /* تقييد العرض */
        imgEl.style.minWidth = '85vw !important'; /* حد أدنى محدود */
        imgEl.style.minHeight = '65vh !important'; /* حد أدنى محدود */
        imgEl.style.width = '90% !important'; /* عرض محدود */
        imgEl.style.height = '90% !important'; /* ارتفاع محدود */
        imgEl.style.objectPosition = 'center center';
        console.log('[showCropperModal] تطبيق أسلوب موبايل كروم مع تقييد المنطقة');
      } else if (deviceInfo.isMobile || deviceInfo.isTablet) {
        imgEl.style.maxHeight = '70vh !important'; /* تقييد للموبايل */
        imgEl.style.maxWidth = '90vw !important'; /* تقييد العرض */
        imgEl.style.minWidth = '85vw !important'; /* حد أدنى محدود */
        imgEl.style.minHeight = '65vh !important'; /* حد أدنى محدود */
        imgEl.style.width = '90% !important'; /* عرض محدود */
        imgEl.style.height = '90% !important'; /* ارتفاع محدود */
        imgEl.style.objectPosition = 'center center';
        console.log('[showCropperModal] تطبيق أسلوب الأجهزة المحمولة مع تقييد المنطقة');
      } else {
        // للكمبيوتر - تقييد صارم للمنطقة
        imgEl.style.maxHeight = '65vh !important'; /* تقييد كبير للكمبيوتر */
        imgEl.style.maxWidth = '70vw !important'; /* تقييد العرض */
        imgEl.style.minWidth = '55vw !important'; /* حد أدنى محدود */
        imgEl.style.minHeight = '55vh !important'; /* حد أدنى محدود */
        imgEl.style.width = '85% !important'; /* عرض محدود */
        imgEl.style.height = '85% !important'; /* ارتفاع محدود */
        imgEl.style.objectPosition = 'center center';
        console.log('[showCropperModal] تطبيق أسلوب الكمبيوتر مع تقييد صارم للمنطقة');
      }

      if ((imgEl.naturalWidth > 0 && imgEl.naturalHeight > 0) || force) {
        console.log('[showCropperModal] الصورة جاهزة، بدء تهيئة Cropper.js بحدود مقيدة...');

        if (imgEl.cropperInstance) {
          console.log('[showCropperModal] تدمير أداة القص الموجودة...');
          imgEl.cropperInstance.destroy();
          imgEl.cropperInstance = null;
        }

        try {
          cropper = new Cropper(imgEl, {
            aspectRatio: NaN,
            viewMode: 1, /* تغيير إلى viewMode 1 لتقييد المنطقة */
            responsive: true,
            autoCropArea: 0.75, /* تقليل إلى 75% لضمان عدم تجاوز الحدود */
            movable: true,
            zoomable: true,
            rotatable: true,
            scalable: false, /* منع التكبير خارج الحدود */
            background: true,
            guides: true,
            center: true,
            dragMode: 'crop', /* تغيير إلى crop لتقييد الحركة */
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            minContainerWidth: containerWidth * 0.7, /* تقليل الحد الأدنى */
            minContainerHeight: containerHeight * 0.6, /* تقليل الحد الأدنى */
            minCanvasWidth: 0,
            minCanvasHeight: 0,
            minCropBoxWidth: 60, /* حد أدنى صغير لصندوق القص */
            minCropBoxHeight: 60, /* حد أدنى صغير لصندوق القص */
            ready() {
              console.log('[showCropperModal] ✅ تم تهيئة أداة القص مع تقييد المنطقة!');
              cropperReady = true;
              enableAllControls();
              enableCropperControls();

              // تحديد منطقة القص بدقة داخل الحدود المُحددة
              setTimeout(() => {
                const containerData = cropper.getContainerData();
                const canvasData = cropper.getCanvasData();

                console.log('[showCropperModal] بيانات الحاوية:', containerData);
                console.log('[showCropperModal] بيانات Canvas:', canvasData);

                // تحديد Canvas بحجم مقيد داخل المنطقة المُحددة
                try {
                  const restrictedCanvasWidth = containerData.width * 0.75; /* 75% من عرض الحاوية */
                  const restrictedCanvasHeight = containerData.height * 0.75; /* 75% من ارتفاع الحاوية */

                  const imageAspectRatio = imgEl.naturalWidth / imgEl.naturalHeight;
                  let newCanvasWidth, newCanvasHeight;

                  // حساب الحجم المقيد للصورة
                  if (imageAspectRatio > (restrictedCanvasWidth / restrictedCanvasHeight)) {
                    newCanvasWidth = restrictedCanvasWidth;
                    newCanvasHeight = restrictedCanvasWidth / imageAspectRatio;
                  } else {
                    newCanvasHeight = restrictedCanvasHeight;
                    newCanvasWidth = restrictedCanvasHeight * imageAspectRatio;
                  }

                  // توسيط الصورة في المنطقة المُحددة
                  const leftPosition = (containerData.width - newCanvasWidth) / 2;
                  const topPosition = (containerData.height - newCanvasHeight) / 2;

                  cropper.setCanvasData({
                    left: Math.max(0, leftPosition),
                    top: Math.max(0, topPosition),
                    width: newCanvasWidth,
                    height: newCanvasHeight
                  });

                  console.log('[showCropperModal] تم تحديد Canvas داخل المنطقة المقيدة:', newCanvasWidth + 'x' + newCanvasHeight);

                  // تحديد منطقة القص بحجم مقيد داخل الصورة
                  setTimeout(() => {
                    const updatedCanvasData = cropper.getCanvasData();

                    // حساب حجم منطقة القص المقيدة
                    const cropBoxWidth = updatedCanvasData.width * 0.70; /* 70% من حجم Canvas */
                    const cropBoxHeight = updatedCanvasData.height * 0.70; /* 70% من حجم Canvas */
                    const cropBoxLeft = updatedCanvasData.left + (updatedCanvasData.width * 0.15); /* توسيط */
                    const cropBoxTop = updatedCanvasData.top + (updatedCanvasData.height * 0.15); /* توسيط */

                    cropper.setCropBoxData({
                      width: cropBoxWidth,
                      height: cropBoxHeight,
                      left: cropBoxLeft,
                      top: cropBoxTop
                    });

                    console.log('[showCropperModal] تم تحديد منطقة القص المقيدة داخل الصورة');

                    // إضافة قيود إضافية لمنع تجاوز الحدود
                    cropper.setData({
                      x: Math.max(0, cropBoxLeft - updatedCanvasData.left),
                      y: Math.max(0, cropBoxTop - updatedCanvasData.top),
                      width: cropBoxWidth,
                      height: cropBoxHeight,
                      rotate: 0,
                      scaleX: 1,
                      scaleY: 1
                    });

                  }, 200);

                } catch(e) {
                  console.warn('[showCropperModal] فشل في تحديد المنطقة المقيدة:', e);

                  // طريقة احتياطية مع تقييد أكبر
                  try {
                    const fallbackWidth = canvasData.width * 0.60; /* تقليل أكبر */
                    const fallbackHeight = canvasData.height * 0.60; /* تقليل أكبر */
                    const fallbackLeft = canvasData.left + (canvasData.width * 0.20); /* توسيط */
                    const fallbackTop = canvasData.top + (canvasData.height * 0.20); /* توسيط */

                    cropper.setCropBoxData({
                      width: fallbackWidth,
                      height: fallbackHeight,
                      left: fallbackLeft,
                      top: fallbackTop
                    });

                    console.log('[showCropperModal] تم استخدام الطريقة الاحتياطية مع تقييد أكبر');
                  } catch(e2) {
                    console.warn('[showCropperModal] فشل في الطريقة الاحتياطية أيضاً:', e2);
                  }
                }
              }, 300);

              // منع التكبير المفرط الذي يتجاوز الحدود
              setTimeout(() => {
                try {
                  const currentData = cropper.getData();
                  const containerData = cropper.getContainerData();

                  // إذا كانت الصورة أصغر من 60% من الحاوية، قم بتكبيرها قليلاً فقط
                  if (currentData.width < containerData.width * 0.6 || currentData.height < containerData.height * 0.6) {
                    const limitedZoomRatio = Math.min(
                      (containerData.width * 0.7) / currentData.width,
                      (containerData.height * 0.7) / currentData.height
                    );

                    if (limitedZoomRatio > 1 && limitedZoomRatio <= 1.2) {
                      cropper.zoomTo(limitedZoomRatio);
                      console.log('[showCropperModal] تم تطبيق زوم محدود:', limitedZoomRatio);
                    }
                  }
                } catch(e) {
                  console.warn('[showCropperModal] فشل في تطبيق الزوم المحدود:', e);
                }
              }, 500);
            },
            error(err) {
              console.error('[showCropperModal] خطأ في تهيئة أداة القص:', err);
              cleanup();
              loaderModal.hide();
              if (callback) callback(null, 'خطأ في تهيئة أداة القص');
            }
          });
          imgEl.cropperInstance = cropper;
          console.log('[showCropperModal] تم إنشاء أداة القص وربطها بالصورة مع تقييد المنطقة');
        } catch (cropperError) {
          console.error('[showCropperModal] استثناء في إنشاء أداة القص:', cropperError);
          cleanup();
          loaderModal.hide();
          if (callback) callback(null, 'استثناء في أداة القص');
        }
      } else if (tryCount < maxTries) {
        tryCount++;
        console.log('[showCropperModal] الصورة غير جاهزة، إعادة المحاولة بعد', 120 * tryCount, 'مللي ثانية');
        setTimeout(initCropper, 120 * tryCount);
      } else {
        console.error('[showCropperModal] فشل في تحميل الصورة بعد', maxTries, 'محاولة');
        cleanup();
        loaderModal.hide();
        if (callback) callback(null, 'فشل في تحميل الصورة');
      }
    }
    setTimeout(initCropper, 60);

    cropBtn.onclick = async function() {
      // عند الضغط على زر القص، أظهر SweetAlert مع progress bar
      let swalProgress = 0;
      let swalInstance = null;
      let swalClosed = false;
      Swal.fire({
        title: 'جاري معالجة الصورة...',
        html: '<div id="swal-progress-text">0%</div><div class="progress mt-2" style="height: 18px;"><div id="swal-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%">0%</div></div>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          swalInstance = Swal.getPopup();
        }
      });

      function updateSwalProgress(percent) {
        swalProgress = Math.max(0, Math.min(100, Math.round(percent)));
        const text = document.getElementById('swal-progress-text');
        const bar = document.getElementById('swal-progress-bar');
        if (text) text.textContent = swalProgress + '%';
        if (bar) {
          bar.style.width = swalProgress + '%';
          bar.textContent = swalProgress + '%';
        }
      }

      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      if (!cropperReady || !cropper || !cropper.getCroppedCanvas) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'لم يتم تهيئة أداة القص بعد. يرجى الانتظار أو إعادة المحاولة.' });
        cleanup();
        callback(null);
        return;
      }
      if (!imgEl || !imgEl.src || imgEl.naturalWidth === 0 || imgEl.naturalHeight === 0) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'لم يتم تحميل الصورة بشكل صحيح. يرجى إعادة المحاولة أو اختيار صورة أخرى.' });
        cleanup();
        callback(null);
        return;
      }
      const data = cropper.getData(true);
      let canvas;
      try {
        canvas = cropper.getCroppedCanvas({
          width: Math.round(data.width),
          height: Math.round(data.height),
          imageSmoothingQuality: 'high'
        });
      } catch (err) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر قص الصورة. يرجى التأكد من أن الصورة ظاهرة بشكل صحيح ثم أعد المحاولة.' });
        cleanup();
        callback(null);
        return;
      }
      if (!canvas || canvas.width === 0 || canvas.height === 0) {
        Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر قص الصورة. يرجى التأكد من أن الصورة ظاهرة بشكل صحيح ثم أعد المحاولة.' });
        cleanup();
        callback(null);
        return;
      }
      try {
        let imageBitmap;
        if (canvas.transferToImageBitmap) {
          imageBitmap = canvas.transferToImageBitmap();
        } else {
          imageBitmap = await createImageBitmap(canvas);
        }
        const originalName = file.name;
        const ext = originalName.substring(originalName.lastIndexOf('.'));
        const base = originalName.replace(ext, '');
        const uniqueSuffix = '_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
        let compressOptions = {
          maxSizeMB: isMobileChrome() ? 0.08 : 0.1,
          maxWidthOrHeight: isMobileChrome() ? 900 : Math.max(canvas.width, canvas.height),
          initialQuality: isMobileChrome() ? 0.5 : 0.7,
          fileType: file.type
        };
        const worker = new Worker('/js/imageProcessorWorker.js');
        worker.postMessage({ imageBitmap, options: compressOptions }, [imageBitmap]);
        worker.onmessage = function(ev) {
          if (ev.data.type === 'progress') {
            updateSwalProgress(ev.data.percent);
          } else if (ev.data.type === 'done') {
            const compressedBlob = ev.data.blob;
            const fileType = ev.data.fileType || file.type;
            const newFile = new File([compressedBlob], base + uniqueSuffix + '_cropped' + ext, { type: fileType });
            cropBtn.blur && cropBtn.blur();
            setTimeout(() => {
              if (!swalClosed) Swal.close();
              swalClosed = true;
              cleanup();
              callback(newFile);
            }, 0);
            setTimeout(() => { if (!swalClosed) Swal.close(); swalClosed = true; }, 700);
            worker.terminate();
          } else if (ev.data.type === 'error') {
            console.error('Worker error:', ev.data.message);
            if (!swalClosed) Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر ضغط الصورة: ' + (ev.data.message || '') });
            cleanup();
            callback(null);
            worker.terminate();
          }
        };
        worker.onerror = function(err) {
          console.error('Worker onerror:', err);
          if (!swalClosed) Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ في معالجة الصورة (العامل).' });
          cleanup();
          callback(null);
          worker.terminate();
        };
      } catch (err) {
        if (!swalClosed) Swal.fire({ icon: 'error', title: 'خطأ', text: 'فشل معالجة الصورة: ' + (err && err.message ? err.message : err) });
        cleanup();
        callback(null);
      }
    };
  };
  reader.readAsDataURL(file);

  const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  bsModal.show();

  modalEl.addEventListener('shown.bs.modal', function onShown() {
    let shownTry = 0;
    function tryInitOnModal() {
      if ((!imgEl.cropperInstance || !imgEl.cropperInstance.getData) && imgEl.naturalWidth > 0) {
        if (imgEl.cropperInstance) {
          imgEl.cropperInstance.destroy();
          imgEl.cropperInstance = null;
        }

        // تطبيق نفس التحسينات المقيدة عند إظهار المودال
        const deviceInfo = window.DeviceImageSource ? window.DeviceImageSource.detectDevice() : {};

        if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
          imgEl.style.maxHeight = '65vh'; /* تقييد للموبايل */
          imgEl.style.maxWidth = '90vw'; /* تقييد العرض */
          imgEl.style.minWidth = '85vw'; /* تقييد الحد الأدنى */
          imgEl.style.minHeight = '60vh'; /* تقييد الحد الأدنى */
          imgEl.style.objectPosition = 'center center';
        } else if (deviceInfo.isMobile || deviceInfo.isTablet) {
          imgEl.style.maxHeight = '65vh'; /* تقييد للموبايل */
          imgEl.style.maxWidth = '90vw'; /* تقييد العرض */
          imgEl.style.minWidth = '85vw'; /* تقييد الحد الأدنى */
          imgEl.style.minHeight = '60vh'; /* تقييد الحد الأدنى */
          imgEl.style.objectPosition = 'center center';
        } else {
          imgEl.style.maxHeight = '500px'; /* تقييد أكبر للكمبيوتر */
          imgEl.style.maxWidth = '650px'; /* تقييد أكبر للكمبيوتر */
          imgEl.style.minWidth = '400px'; /* حد أدنى محدود */
          imgEl.style.minHeight = '300px'; /* حد أدنى محدود */
          imgEl.style.objectPosition = 'center center';
        }

        cropper = new Cropper(imgEl, {
          aspectRatio: NaN,
          viewMode: 1, /* تقييد المنطقة */
          responsive: true,
          autoCropArea: 0.70, /* تقليل إلى 70% */
          movable: true,
          zoomable: true,
          rotatable: true,
          scalable: false, /* منع التكبير خارج الحدود */
          background: true,
          guides: true,
          center: true,
          dragMode: 'crop', /* تقييد الحركة */
          cropBoxMovable: true,
          cropBoxResizable: true,
          toggleDragModeOnDblclick: false,
          minContainerWidth: 160, /* تقليل للموبايل */
          minContainerHeight: 160, /* تقليل للموبايل */
          minCanvasWidth: 0,
          minCanvasHeight: 0,
          minCropBoxWidth: 30, /* حد أدنى صغير */
          minCropBoxHeight: 30, /* حد أدنى صغير */
          ready() {
            cropperReady = true;
            enableAllControls();
            enableCropperControls();

            // تحديد الصورة بحجم مقيد في المودال
            setTimeout(() => {
              const containerData = cropper.getContainerData();
              const canvasData = cropper.getCanvasData();

              // تحديد الصورة بحجم مقيد
              try {
                const restrictedWidth = canvasData.width * 0.70; /* تقليل إلى 70% */
                const restrictedHeight = canvasData.height * 0.70; /* تقليل إلى 70% */
                const restrictedLeft = canvasData.left + (canvasData.width * 0.15); /* توسيط */
                const restrictedTop = canvasData.top + (canvasData.height * 0.15); /* توسيط */

                cropper.setCropBoxData({
                  width: restrictedWidth,
                  height: restrictedHeight,
                  left: restrictedLeft,
                  top: restrictedTop
                });

                console.log('[showCropperModal] تم تحديد الصورة بحجم مقيد في المودال');
              } catch(e) {
                console.warn('[showCropperModal] فشل في تحديد الصورة بحجم مقيد في المودال:', e);
              }

              // توسيط الصورة
              if (canvasData.width < containerData.width) {
                const imageCenterPosition = (containerData.width - canvasData.width) / 2;
                try {
                  cropper.setCanvasData({
                    left: Math.max(0, imageCenterPosition),
                    top: canvasData.top,
                    width: canvasData.width,
                    height: canvasData.height
                  });
                  console.log('[showCropperModal] تم توسيط الصورة في المودال');
                } catch(e) {
                  console.warn('[showCropperModal] فشل في توسيط الصورة في المودال:', e);
                }
              }
            }, 300);

            // معالجة خاصة للأجهزة المحمولة مع تقييد أكبر
            if (isMobileChrome() && imgEl.naturalHeight > imgEl.naturalWidth * 2) {
              try {
                setTimeout(() => {
                  const mobileCanvasData = cropper.getCanvasData();

                  // تحديد الصورة بحجم مقيد للموبايل
                  const mobileRestrictedWidth = mobileCanvasData.width * 0.65; /* تقييد أكبر للموبايل */
                  const mobileRestrictedHeight = mobileCanvasData.height * 0.65; /* تقييد أكبر للموبايل */
                  const mobileRestrictedLeft = mobileCanvasData.left + (mobileCanvasData.width * 0.175); /* توسيط */
                  const mobileRestrictedTop = mobileCanvasData.top + (mobileCanvasData.height * 0.175); /* توسيط */

                  cropper.setCropBoxData({
                    width: mobileRestrictedWidth,
                    height: mobileRestrictedHeight,
                    left: mobileRestrictedLeft,
                    top: mobileRestrictedTop
                  });
                  console.log('[showCropperModal] تم تطبيق تحديد مقيد للصورة على موبايل كروم في المودال');
                }, 400);
              } catch(e){
                console.warn('[showCropperModal] فشل في تطبيق إعدادات موبايل كروم في المودال:', e);
              }
            }
          }
        });
        imgEl.cropperInstance = cropper;
      } else if (shownTry < 8 && imgEl.naturalWidth === 0) {
        shownTry++;
        setTimeout(tryInitOnModal, 120 * shownTry);
      }
    }
    setTimeout(tryInitOnModal, 120);
    modalEl.removeEventListener('shown.bs.modal', onShown);
  });

  // ⭐ إضافة دالة كشف نوع الجهاز إذا لم تكن متوفرة
  function detectDeviceType() {
    if (window.DeviceImageSource && window.DeviceImageSource.detectDevice) {
      return window.DeviceImageSource.detectDevice();
    }

    const userAgent = navigator.userAgent.toLowerCase();
    const isMobile = /android|webos|iphone|ipod|blackberry|iemobile|opera mini/i.test(userAgent);
    const isTablet = /ipad|tablet|(android(?!.*mobile))/i.test(userAgent);
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    const isSmallScreen = window.innerWidth <= 768;

    return {
      isMobile: isMobile || (isTouchDevice && isSmallScreen && !isTablet),
      isTablet: isTablet,
      isDesktop: !isMobile && !isTablet,
      isTouchDevice: isTouchDevice,
      hasCamera: navigator.mediaDevices && navigator.mediaDevices.getUserMedia,
      screenWidth: window.innerWidth,
      userAgent: userAgent
    };
  }
};

// FileUploadHandler: Robust AJAX file upload for all upload zones
document.addEventListener('DOMContentLoaded', function() {
  // Helper: Find all upload zones
  const uploadZones = document.querySelectorAll('[data-upload-role="zone"]');
  uploadZones.forEach(function(zone) {
    const fileInput = zone.querySelector('[data-file-role="input"]');
    const selectBtn = zone.querySelector('[data-file-role="select"]');
    const preview = zone.querySelector('[data-file-role="preview"]');
    const hiddenFileId = zone.querySelector('[data-file-role="file_id"]');
    const hiddenTempName = zone.querySelector('[data-file-role="temp_file_name"]');
    const hiddenDocType = zone.querySelector('[data-file-role="document_type_value"]');

    // When select button is clicked, trigger file input
    if (selectBtn && fileInput) {
      selectBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.click();
      });
    }

    // On file input change
    if (fileInput) {
      fileInput.addEventListener('change', function(e) {
        const file = fileInput.files[0];
        if (!file) return;
        // Optionally: validate file type/size here
        // Show cropper modal if image, else upload directly
        if (file.type.startsWith('image/')) {
          window.showCropperModal(file, function(croppedFile) {
            if (croppedFile) {
              uploadFileAJAX(croppedFile, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
            } else {
              // User cancelled or error
              fileInput.value = '';
            }
          });
        } else {
          uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType);
        }
      });
    }
  });

  // AJAX upload logic
  function uploadFileAJAX(file, zone, preview, hiddenFileId, hiddenTempName, hiddenDocType) {
    // Show loader
    const loaderModalEl = document.getElementById('compressLoaderModal');
    const loaderModal = loaderModalEl ? bootstrap.Modal.getOrCreateInstance(loaderModalEl) : null;
    loaderModal && loaderModal.show();

    const formData = new FormData();
    formData.append('file', file);
    // Optionally: add CSRF token if needed
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) formData.append('_token', csrf.getAttribute('content'));
    // Optionally: add document_type_value if available
    if (hiddenDocType && hiddenDocType.value) {
      formData.append('document_type_value', hiddenDocType.value);
    }

    fetch('/ajax/file-upload', {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json',
      },
    })
    .then(response => response.json())
    .then(data => {
      loaderModal && loaderModal.hide();
      if (data.success && data.file_id && data.temp_file_name) {
        // Update hidden fields
        if (hiddenFileId) hiddenFileId.value = data.file_id;
        if (hiddenTempName) hiddenTempName.value = data.temp_file_name;
        // Show preview (if image)
        if (preview && file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = '';
          };
          reader.readAsDataURL(file);
        } else if (preview) {
          preview.textContent = file.name;
          preview.style.display = '';
        }
        // Optionally: show success message
      } else {
        Swal.fire({ icon: 'error', title: 'خطأ', text: data.message || 'فشل رفع الملف. حاول مرة أخرى.' });
        // Reset hidden fields
        if (hiddenFileId) hiddenFileId.value = '';
        if (hiddenTempName) hiddenTempName.value = '';
        if (preview) preview.style.display = 'none';
      }
    })
    .catch(err => {
      loaderModal && loaderModal.hide();
      Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ أثناء رفع الملف. حاول مرة أخرى.' });
      if (hiddenFileId) hiddenFileId.value = '';
      if (hiddenTempName) hiddenTempName.value = '';
      if (preview) preview.style.display = 'none';
    });
  }
});
</script>

<!-- دالة مختصرة لسهولة الوصول -->
<script>
window.showCropper = window.showCropperModal;

// فحص تشخيصي لتأكيد توفر أداة القص
console.log('[Cropper] ✅ تم تحميل أداة القص بنجاح');
console.log('[Cropper] window.showCropperModal:', typeof window.showCropperModal);
console.log('[Cropper] window.showCropper:', typeof window.showCropper);

// إرسال إشارة عالمية بأن أداة القص جاهزة
window.cropperReady = true;
if (window.dispatchEvent) {
  window.dispatchEvent(new CustomEvent('cropperReady', {
    detail: {
      showCropperModal: window.showCropperModal,
      showCropper: window.showCropper
    }
  }));
}
</script>


