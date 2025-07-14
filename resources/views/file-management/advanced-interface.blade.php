<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نظام إدارة الملفات المتقدم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .file-preview-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s ease;
            background: white;
            height: 200px;
        }

        .file-preview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-color: #007bff;
        }

        .file-preview-content {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .file-preview-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .file-preview-icon {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }

        .file-preview-info {
            padding: 10px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .file-name {
            font-size: 0.85rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .file-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .btn-pulse {
            animation: pulse 2s infinite;
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }
            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
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
        }

        .cloud-sync-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
        }

        .cloud-sync-status.synced { background: #d4edda; color: #155724; }
        .cloud-sync-status.pending { background: #fff3cd; color: #856404; }
        .cloud-sync-status.failed { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-0">
                    <i class="fas fa-cloud-upload-alt text-primary me-2"></i>
                    نظام إدارة الملفات المتقدم
                </h1>
                <p class="text-muted">رفع وإدارة ملفات متعددة الأنواع مع التكامل السحابي</p>
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

                <div class="col-md-6">
                    <label for="personId" class="form-label fw-bold">
                        <i class="fas fa-id-card text-secondary"></i> رقم الهوية (اختياري)
                    </label>
                    <input type="text" class="form-control" id="personId" placeholder="رقم الهوية للربط مع الشخص">
                </div>
            </div>

            <!-- Processing Options and Controls Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <label class="form-label fw-bold">
                        <i class="fas fa-cogs text-success"></i> خيارات المعالجة
                    </label>
                    <div class="d-flex gap-4 flex-wrap">
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

                <div class="col-md-4">
                    <label class="form-label fw-bold">
                        <i class="fas fa-upload text-primary"></i> عمليات الرفع
                    </label>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success flex-fill" id="selectFilesBtn">
                                <i class="fas fa-file-plus"></i> اختيار ملفات
                            </button>
                            <button type="button" class="btn btn-info flex-fill" id="selectFolderBtn">
                                <i class="fas fa-folder-plus"></i> اختيار مجلد
                            </button>
                        </div>
                        <button type="button" class="btn btn-warning w-100" id="selectFolderWithImagesBtn">
                            <i class="fas fa-images"></i> مجلد + صور متعددة
                        </button>
                        <!-- زر الرفع الأساسي -->
                        <button type="button" class="btn btn-success w-100 mt-2" id="startUploadBtnMain" style="display: none;">
                            <i class="fas fa-database me-2"></i>إرسال إلى قاعدة البيانات
                        </button>
                    </div>
                        <button type="button" class="btn btn-info" id="clearAllBtn">
                            <i class="fas fa-trash"></i> مسح
                        </button>
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
                                        <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="document.getElementById('excelFileInput').click()">
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

            <!-- Duplicate File Detection Options -->
            <div class="row mb-4" id="duplicateDetectionSection">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-clone"></i> خيارات كشف الملفات المكررة
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="enableDuplicateDetection" checked>
                                        <label class="form-check-label fw-bold" for="enableDuplicateDetection">
                                            <i class="fas fa-search"></i> تفعيل كشف الملفات المكررة
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle text-info"></i>
                                        يتم مقارنة أسماء الملفات مع الملفات الموجودة في النظام
                                    </div>
                                </div>

                                <div class="col-md-6" id="duplicateHandlingOptions">
                                    <label for="duplicateHandling" class="form-label fw-bold">
                                        <i class="fas fa-cogs"></i> التعامل مع الملفات المكررة
                                    </label>
                                    <select class="form-select" id="duplicateHandling">
                                        <option value="store_temp">حفظ في التخزين المؤقت (افتراضي)</option>
                                        <option value="skip">تخطي الملفات المكررة</option>
                                        <option value="replace">استبدال الملفات الموجودة</option>
                                    </select>

                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-warning btn-sm" id="viewDuplicatesBtn" style="display: none;">
                                            <i class="fas fa-eye"></i> عرض الملفات المكررة
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" id="downloadDuplicatesBtn" style="display: none;">
                                            <i class="fas fa-download"></i> تحميل المكررات
                                        </button>
                                    </div>
                                    <div id="duplicateStatus" class="small text-muted mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Type Filters -->
            <div class="row">
                <div class="col-12">
                    <label class="form-label fw-bold">
                        <i class="fas fa-filter text-info"></i> فلترة أنواع الملفات
                    </label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="fileFilter" id="filterAll" value="all" checked>
                        <label class="btn btn-outline-primary" for="filterAll">
                            <i class="fas fa-th"></i> جميع الملفات
                        </label>

                        <input type="radio" class="btn-check" name="fileFilter" id="filterImages" value="image">
                        <label class="btn btn-outline-success" for="filterImages">
                            <i class="fas fa-image"></i> صور
                        </label>

                        <input type="radio" class="btn-check" name="fileFilter" id="filterPDF" value="pdf">
                        <label class="btn btn-outline-danger" for="filterPDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </label>

                        <input type="radio" class="btn-check" name="fileFilter" id="filterExcel" value="excel">
                        <label class="btn btn-outline-info" for="filterExcel">
                            <i class="fas fa-file-excel"></i> Excel
                        </label>

                        <input type="radio" class="btn-check" name="fileFilter" id="filterArchive" value="archive">
                        <label class="btn btn-outline-warning" for="filterArchive">
                            <i class="fas fa-file-archive"></i> مضغوط
                        </label>

                        <!-- زر بوابة Excel المتخصصة -->
                        <button type="button" class="btn btn-success" id="excel-gateway-btn" title="بوابة Excel المتخصصة">
                            <i class="fas fa-table"></i> بوابة Excel
                        </button>
        </div>

        <!-- Enhanced File Drop Zone -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="file-drop-zone" id="fileDropZone">
                    <div class="drop-zone-content text-center">
                        <i class="fas fa-cloud-upload-alt fa-5x text-primary mb-4"></i>
                        <h3 class="text-primary">اسحب وأفلت الملفات هنا</h3>
                        <p class="text-muted mb-4">أو انقر على الزر أدناه لاختيار الملفات</p>

                        <!-- File Upload Actions -->
                        <div class="d-flex justify-content-center gap-3 mb-3">
                            <button type="button" class="btn btn-primary btn-lg" id="selectFilesDropBtn">
                                <i class="fas fa-file-plus me-2"></i>اختيار ملفات
                            </button>
                            <button type="button" class="btn btn-success btn-lg" id="startUploadBtn" style="display: none;">
                                <i class="fas fa-upload me-2"></i>بدء الرفع إلى قاعدة البيانات
                            </button>
                            <button type="button" class="btn btn-warning btn-lg" id="pauseUploadBtn" style="display: none;">
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
                        <!-- مدخل الصور مع المجلدات -->
                        <input type="file" id="imagesWithFolderInput" webkitdirectory directory multiple class="d-none" accept="image/*">
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
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated"
                                 id="overallProgress" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="h5 mb-0" id="totalFiles">0</div>
                                <small class="text-muted">إجمالي الملفات</small>
                            </div>
                            <div class="col-3">
                                <div class="h5 mb-0 text-success" id="completedFiles">0</div>
                                <small class="text-muted">مكتملة</small>
                            </div>
                            <div class="col-3">
                                <div class="h5 mb-0 text-warning" id="processingFiles">0</div>
                                <small class="text-muted">قيد المعالجة</small>
                            </div>
                            <div class="col-3">
                                <div class="h5 mb-0 text-danger" id="failedFiles">0</div>
                                <small class="text-muted">فاشلة</small>
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-images me-2"></i>معاينة الملفات
                        </h5>
                        <div class="file-type-filter">
                            <button class="btn btn-sm btn-primary active" data-filter="all">الكل</button>
                            <button class="btn btn-sm btn-outline-primary" data-filter="image">صور</button>
                            <button class="btn btn-sm btn-outline-primary" data-filter="pdf">PDF</button>
                            <button class="btn btn-sm btn-outline-success" data-filter="excel" id="excelFilterBtn">
                                <i class="fas fa-file-excel me-1"></i>Excel
                            </button>
                            <button class="btn btn-sm btn-outline-primary" data-filter="word">Word</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row" id="filePreviewsContainer">
                            <div class="col-12 text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <p>لا توجد ملفات محددة بعد</p>
                            </div>
                        </div>
                        <!-- زر عرض الملفات المكررة -->
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-warning" id="showDuplicateFilesBtn">
                                <i class="fas fa-clone me-2"></i>عرض الملفات المكررة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Dashboard -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="analytics-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">إجمالي الملفات</h6>
                            <h3 class="mb-0" id="totalFilesCount">0</h3>
                        </div>
                        <i class="fas fa-file fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">حجم التخزين</h6>
                            <h3 class="mb-0" id="totalStorageSize">0 MB</h3>
                        </div>
                        <i class="fas fa-hdd fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333;">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">ملفات اليوم</h6>
                            <h3 class="mb-0" id="todayFilesCount">0</h3>
                        </div>
                        <i class="fas fa-calendar-day fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">نسبة الضغط</h6>
                            <h3 class="mb-0" id="compressionRatio">0%</h3>
                        </div>
                        <i class="fas fa-compress fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ربط زر عرض الملفات المكررة بالمودال
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('showDuplicateFilesBtn');
            if (btn) {
                btn.onclick = function() {
                    // مثال: جلب sessionId من السيرفر أو من sessionStorage أو من آخر عملية رفع
                    // هنا يجب استبدال 'CURRENT_DUPLICATE_SESSION_ID' بالمعرف الفعلي للجلسة
                    const sessionId = window.CURRENT_DUPLICATE_SESSION_ID || sessionStorage.getItem('duplicate_files_session_id');
                    if (sessionId) {
                        showDuplicateFilesModal(sessionId);
                        const modal = new bootstrap.Modal(document.getElementById('duplicateFilesModal'));
                        modal.show();
                    } else {
                        alert('لا توجد جلسة ملفات مكررة حالياً.');
                    }
                };
            }
        });

        class AdvancedFileManager {
            constructor() {
                this.files = new Map();
                this.currentBatch = null;
                this.uploadQueue = [];
                this.maxConcurrentUploads = 3;
                this.activeUploads = 0;
                this.uploadInProgress = false; // منع التكرار

                // متغيرات للتعامل مع ملفات المجلدات
                this.processedFiles = null;
                this.currentUploadType = null;

                this.initializeEventListeners();
                this.loadAnalytics();
                this.checkForExistingDuplicates();
            }

            // فحص وجود ملفات مكررة موجودة مسبقاً
            async checkForExistingDuplicates() {
                const sessionId = sessionStorage.getItem('duplicate_files_session_id');
                if (sessionId) {
                    try {
                        const response = await fetch(`/api/duplicate-files/summary?session_id=${sessionId}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (response.ok) {
                            const result = await response.json();
                            if (result.success && result.data && result.data.files.length > 0) {
                                window.CURRENT_DUPLICATE_SESSION_ID = sessionId;
                                const duplicateBtn = document.getElementById('showDuplicateFilesBtn');
                                if (duplicateBtn) {
                                    duplicateBtn.style.display = 'inline-block';
                                    duplicateBtn.innerHTML = `<i class="fas fa-clone me-2"></i>عرض الملفات المكررة (${result.data.total_duplicates})`;
                                    duplicateBtn.classList.add('btn-warning');
                                    duplicateBtn.classList.remove('btn-secondary');
                                }
                                console.log('🔍 تم العثور على ملفات مكررة موجودة مسبقاً:', result.data);
                            }
                        }
                    } catch (error) {
                        console.log('لا توجد ملفات مكررة سابقة');
                    }
                }
            }

            initializeEventListeners() {
                const dropZone = document.getElementById('fileDropZone');
                const fileInput = document.getElementById('fileInput');
                const folderInput = document.getElementById('folderInput');
                const imagesWithFolderInput = document.getElementById('imagesWithFolderInput');
                const selectBtn = document.getElementById('selectFilesBtn');
                const selectDropBtn = document.getElementById('selectFilesDropBtn');
                const selectFolderBtn = document.getElementById('selectFolderBtn');
                const selectFolderWithImagesBtn = document.getElementById('selectFolderWithImagesBtn');
                const generateRecordBtn = document.getElementById('generateRecordBtn');
                const clearAllBtn = document.getElementById('clearAllBtn');
                const startUploadBtn = document.getElementById('startUploadBtn');

                // Check if elements exist before adding listeners
                if (!fileInput || !folderInput || !imagesWithFolderInput) {
                    console.error('❌ عناصر input مفقودة في DOM');
                    return;
                }

                // Drag and drop events
                if (dropZone) {
                    dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
                    dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
                    dropZone.addEventListener('drop', this.handleDrop.bind(this));
                }

                // File and folder selection
                if (selectBtn) {
                    selectBtn.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر اختيار ملفات');
                        fileInput.click();
                    });
                }

                if (selectDropBtn) {
                    selectDropBtn.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر اختيار ملفات (المنطقة)');
                        fileInput.click();
                    });
                }

                if (selectFolderBtn) {
                    selectFolderBtn.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر اختيار مجلد');
                        folderInput.click();
                    });
                }

                if (selectFolderWithImagesBtn) {
                    selectFolderWithImagesBtn.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر مجلد + صور');
                        imagesWithFolderInput.click();
                    });
                }

                fileInput.addEventListener('change', this.handleFileSelect.bind(this));
                folderInput.addEventListener('change', this.handleFolderSelect.bind(this));
                imagesWithFolderInput.addEventListener('change', this.handleImagesWithFolderSelect.bind(this));

                // Record number generation
                if (generateRecordBtn) {
                    generateRecordBtn.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر إنشاء رقم ملف');
                        this.generateRecordNumber();
                    });
                }

                // Clear all files
                if (clearAllBtn) {
                    clearAllBtn.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر مسح الكل');
                        this.clearAllFiles();
                    });
                }

                // Start upload manually
                if (startUploadBtn) {
                    startUploadBtn.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر بدء الرفع');
                        this.startUploads();
                    });
                }

                // زر الرفع الأساسي الجديد
                const startUploadBtnMain = document.getElementById('startUploadBtnMain');
                if (startUploadBtnMain) {
                    startUploadBtnMain.addEventListener('click', () => {
                        console.log('🖱️ تم النقر على زر إرسال إلى قاعدة البيانات');
                        this.startUploads();
                    });
                }

                // Excel import options visibility
                fileInput.addEventListener('change', this.toggleExcelOptions.bind(this));

                // Enable/disable Excel import target table
                document.getElementById('enableExcelImport').addEventListener('change', (e) => {
                    document.getElementById('excelTargetOptions').style.display =
                        e.target.checked ? 'block' : 'none';
                });

                // Excel file selection for folder uploads
                document.getElementById('excelFileInput').addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    const statusDiv = document.getElementById('excelFileStatus');

                    if (file) {
                        statusDiv.innerHTML = `<i class="fas fa-file-excel text-success"></i> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                        console.log('📊 ملف Excel محدد:', {
                            name: file.name,
                            size: file.size,
                            type: file.type
                        });
                    } else {
                        statusDiv.innerHTML = '';
                    }
                });

                // File filter radio buttons
                document.querySelectorAll('input[name="fileFilter"]').forEach(radio => {
                    radio.addEventListener('change', this.handleFilterChange.bind(this));
                });

                // Duplicate detection options
                document.getElementById('enableDuplicateDetection').addEventListener('change', (e) => {
                    document.getElementById('duplicateHandlingOptions').style.display =
                        e.target.checked ? 'block' : 'none';
                });

                document.getElementById('duplicateHandling').addEventListener('change', (e) => {
                    console.log('🔧 تم تغيير إعداد التعامل مع المكررات إلى:', e.target.value);
                });
            }

            // مسح جميع الملفات
            clearAllFiles() {
                console.log('🗑️ مسح جميع الملفات...');

                try {
                    const fileCount = this.files.size;
                    this.files.clear();

                    // تحديث العرض
                    this.updateFileDisplay();

                    // إخفاء زر الرفع
                    this.hideUploadSection();

                    // إعادة تعيين المدخلات
                    const inputs = ['fileInput', 'folderInput', 'imagesWithFolderInput'];
                    inputs.forEach(inputId => {
                        const input = document.getElementById(inputId);
                        if (input) input.value = '';
                    });

                    // تحديث الإحصائيات
                    this.loadAnalytics();

                    this.showAlert(`تم مسح ${fileCount} ملف`, 'info');
                    console.log('✅ تم مسح جميع الملفات');

                } catch (error) {
                    console.error('❌ خطأ في مسح الملفات:', error);
                    this.showAlert('خطأ في مسح الملفات', 'danger');
                }
            }

            // إنشاء رقم ملف جديد
            generateRecordNumber() {
                console.log('🔢 إنشاء رقم ملف جديد...');

                try {
                    const timestamp = Date.now();
                    const random = Math.floor(Math.random() * 1000);
                    const recordNumber = `ASO${timestamp}${random}`;

                    const recordInput = document.getElementById('recordNumber');
                    if (recordInput) {
                        recordInput.value = recordNumber;
                        this.showAlert('تم إنشاء رقم ملف جديد', 'success');
                    }

                    console.log('✅ رقم الملف الجديد:', recordNumber);
                    return recordNumber;

                } catch (error) {
                    console.error('❌ خطأ في إنشاء رقم الملف:', error);
                    this.showAlert('خطأ في إنشاء رقم الملف', 'danger');
                }
            }

            toggleExcelOptions() {
                const files = Array.from(document.getElementById('fileInput').files);
                const hasExcelFiles = files.some(file => {
                    const ext = file.name.split('.').pop().toLowerCase();
                    return ['xlsx', 'xls', 'csv', 'zip', 'rar', '7z'].includes(ext);
                });

                document.getElementById('excelImportSection').style.display =
                    hasExcelFiles ? 'block' : 'none';
            }

            handleDragOver(e) {
                e.preventDefault();
                e.currentTarget.classList.add('drag-over');
            }

            handleDragLeave(e) {
                e.currentTarget.classList.remove('drag-over');
            }

            handleDrop(e) {
                e.preventDefault();
                e.currentTarget.classList.remove('drag-over');

                const files = Array.from(e.dataTransfer.files);
                this.processFiles(files);
            }

            handleFileSelect(e) {
                const files = Array.from(e.target.files);
                this.processFiles(files);
                e.target.value = ''; // Reset input
            }

            handleFolderSelect(e) {
                const files = Array.from(e.target.files);
                console.log('📁 تم اختيار مجلد يحتوي على:', files.length, 'ملف');

                // تحليل المجلد وعرض التفاصيل
                const folderAnalysis = this.analyzeFolderStructure(files);
                console.log('📊 تحليل المجلد:', folderAnalysis);

                // عرض هيكل المجلدات
                console.log('🏗️ هيكل المجلدات:', folderAnalysis.folderHierarchy);

                // عرض تفاصيل المجلدات الأب
                if (folderAnalysis.parentFolders.length > 0) {
                    console.log('📂 المجلدات الأب المكتشفة:', folderAnalysis.parentFolders);
                    folderAnalysis.parentFolders.forEach(parent => {
                        console.log(`📁 مجلد أب: ${parent} - سيتم تجاهله وتحليل محتوياته`);
                    });
                }

                // عرض تفاصيل إضافية حول مجلدات الهوية
                if (folderAnalysis.identityFolders.length > 0) {
                    console.log('🆔 مجلدات الهوية المكتشفة:', folderAnalysis.identityFolders);
                    folderAnalysis.identityFolders.forEach(identity => {
                        console.log(`📋 ${identity}: سيتم التحقق من وجوده في النظام`);
                    });
                } else {
                    console.log('⚠️ لم يتم العثور على مجلدات بأسماء أرقام هوية');
                    console.log('💡 المجلدات الموجودة:', folderAnalysis.folders);
                    console.log('💡 تنبيه: يجب أن تحتوي على مجلدات فرعية بأسماء أرقام هوية صحيحة');
                }

                // عرض المجلدات غير الصالحة
                if (folderAnalysis.invalidFolders.length > 0) {
                    console.log('❌ مجلدات غير صالحة (ستُتجاهل):', folderAnalysis.invalidFolders);
                }

                this.processFolderFiles(files, 'folder');
                e.target.value = ''; // Reset input
            }

            handleImagesWithFolderSelect(e) {
                const files = Array.from(e.target.files);
                console.log('🖼️ تم اختيار مجلد صور يحتوي على:', files.length, 'صورة');

                // تحليل المجلد وعرض التفاصيل
                const folderAnalysis = this.analyzeFolderStructure(files);
                console.log('📊 تحليل مجلد الصور:', folderAnalysis);

                // عرض هيكل المجلدات
                console.log('🏗️ هيكل مجلدات الصور:', folderAnalysis.folderHierarchy);

                // عرض تفاصيل المجلدات الأب
                if (folderAnalysis.parentFolders.length > 0) {
                    console.log('📂 المجلدات الأب المكتشفة:', folderAnalysis.parentFolders);
                    folderAnalysis.parentFolders.forEach(parent => {
                        console.log(`📁 مجلد أب: ${parent} - سيتم تجاهله وتحليل محتوياته`);
                    });
                }

                // عرض تفاصيل إضافية حول مجلدات الهوية
                if (folderAnalysis.identityFolders.length > 0) {
                    console.log('🆔 مجلدات الهوية المكتشفة:', folderAnalysis.identityFolders);
                    folderAnalysis.identityFolders.forEach(identity => {
                        console.log(`📋 ${identity}: سيتم التحقق من وجوده في النظام`);
                    });
                } else {
                    console.log('⚠️ لم يتم العثور على مجلدات بأسماء أرقام هوية');
                    console.log('💡 المجلدات الموجودة:', folderAnalysis.folders);
                    console.log('💡 تنبيه: يجب أن تحتوي على مجلدات فرعية بأسماء أرقام هوية صحيحة');
                }

                // عرض المجلدات غير الصالحة
                if (folderAnalysis.invalidFolders.length > 0) {
                    console.log('❌ مجلدات غير صالحة (ستُتجاهل):', folderAnalysis.invalidFolders);
                }

                this.processFolderFiles(files, 'images');
                e.target.value = ''; // Reset input
            }

            // Analyze folder structure method
            analyzeFolderStructure(files) {
                const analysis = {
                    totalFiles: files.length,
                    folders: new Set(),
                    identityFolders: new Set(),
                    parentFolders: new Set(),
                    invalidFolders: new Set(),
                    fileTypes: {},
                    structure: {},
                    folderHierarchy: {}
                };

                files.forEach(file => {
                    // تحليل المسار الكامل
                    const pathParts = file.webkitRelativePath.split('/').filter(part => part.trim() !== '');

                    if (pathParts.length === 0) return;

                    // المجلد المباشر للملف (المجلد الأخير في المسار)
                    const directFolder = pathParts[pathParts.length - 2] || 'root';

                    // إضافة جميع أجزاء المسار إلى قائمة المجلدات
                    pathParts.slice(0, -1).forEach(folderName => {
                        analysis.folders.add(folderName);

                        // فحص إذا كان اسم المجلد يشبه رقم هوية
                        if (/^\d{8,10}$/.test(folderName)) {
                            analysis.identityFolders.add(folderName);
                        } else if (folderName !== pathParts[pathParts.length - 2]) {
                            // إذا لم يكن رقم هوية وليس المجلد المباشر، فهو مجلد أب
                            analysis.parentFolders.add(folderName);
                        } else {
                            // مجلد غير صالح
                            analysis.invalidFolders.add(folderName);
                        }
                    });

                    // بناء هيكل المجلدات الهرمي
                    let currentLevel = analysis.folderHierarchy;
                    pathParts.slice(0, -1).forEach(folderName => {
                        if (!currentLevel[folderName]) {
                            currentLevel[folderName] = {
                                files: [],
                                subfolders: {}
                            };
                        }
                        currentLevel = currentLevel[folderName].subfolders;
                    });

                    // إضافة الملف إلى المجلد المناسب
                    let targetLevel = analysis.folderHierarchy;
                    pathParts.slice(0, -1).forEach(folderName => {
                        if (targetLevel[folderName]) {
                            if (pathParts.slice(0, -1)[pathParts.slice(0, -1).length - 1] === folderName) {
                                targetLevel[folderName].files.push({
                                    name: file.name,
                                    size: file.size,
                                    type: file.type,
                                    fullPath: file.webkitRelativePath
                                });
                            }
                            targetLevel = targetLevel[folderName].subfolders;
                        }
                    });

                    // تحليل نوع الملف
                    const ext = file.name.split('.').pop().toLowerCase();
                    analysis.fileTypes[ext] = (analysis.fileTypes[ext] || 0) + 1;

                    // بناء هيكل المجلد التقليدي (للتوافق مع الكود الموجود)
                    if (!analysis.structure[directFolder]) {
                        analysis.structure[directFolder] = [];
                    }
                    analysis.structure[directFolder].push(file);
                });

                // تحويل Sets إلى arrays للسهولة
                analysis.folders = Array.from(analysis.folders);
                analysis.identityFolders = Array.from(analysis.identityFolders);
                analysis.parentFolders = Array.from(analysis.parentFolders);
                analysis.invalidFolders = Array.from(analysis.invalidFolders);

                return analysis;
            }

            // Process files method
            processFiles(files) {
                console.log('📁 معالجة الملفات:', files.length);

                files.forEach((file, index) => {
                    const fileId = `file_${Date.now()}_${index}`;
                    this.files.set(fileId, file);
                    console.log(`📄 تم إضافة ملف: ${file.name}`);
                });

                this.updateFileDisplay();
                this.showUploadSection();

                console.log('✅ تم تحضير الملفات للرفع');
            }

            // إظهار قسم الرفع
            showUploadSection() {
                const startUploadBtn = document.getElementById('startUploadBtn');
                const startUploadBtnMain = document.getElementById('startUploadBtnMain');

                if (startUploadBtn) {
                    startUploadBtn.style.display = 'inline-block';
                    console.log('👁️ تم إظهار زر بدء الرفع');
                }

                if (startUploadBtnMain) {
                    startUploadBtnMain.style.display = 'block';
                    console.log('👁️ تم إظهار زر إرسال إلى قاعدة البيانات');
                }
            }

            // إخفاء قسم الرفع
            hideUploadSection() {
                const startUploadBtn = document.getElementById('startUploadBtn');
                const startUploadBtnMain = document.getElementById('startUploadBtnMain');

                if (startUploadBtn) {
                    startUploadBtn.style.display = 'none';
                }

                if (startUploadBtnMain) {
                    startUploadBtnMain.style.display = 'none';
                }
            }

            // Start uploads method
            async startUploads() {
                if (this.uploadInProgress) {
                    console.log('⚠️ عملية رفع جارية بالفعل - تم تجاهل الطلب المكرر');
                    this.showAlert('عملية رفع جارية بالفعل، يرجى الانتظار', 'warning');
                    return;
                }

                if (this.files.size === 0) {
                    this.showAlert('لا توجد ملفات للرفع', 'warning');
                    return;
                }

                this.uploadInProgress = true; // تعيين حالة الرفع
                console.log('🚀 بدء عملية الرفع...');

                const uploadProgressSection = document.getElementById('uploadProgressSection');
                if (uploadProgressSection) {
                    uploadProgressSection.style.display = 'block';
                }

                const enableDuplicateDetection = document.getElementById('enableDuplicateDetection')?.checked || false;
                const duplicateHandling = document.getElementById('duplicateHandling')?.value || 'keep_both';

                try {
                    // تحضير بيانات الرفع
                    const formData = new FormData();

                    // إضافة الملفات
                    let fileIndex = 0;
                    this.files.forEach((file, fileId) => {
                        formData.append('files[]', file);
                        console.log(`📁 إضافة ملف ${fileIndex + 1}:`, file.name, 'حجم:', file.size);
                        fileIndex++;
                    });

                    // معلومات إضافية مطلوبة
                    const folderName = 'uploaded_files_' + Date.now();
                    formData.append('folder_name', folderName);
                    formData.append('total_files', this.files.size);
                    formData.append('enable_duplicate_detection', enableDuplicateDetection);
                    formData.append('duplicate_handling', duplicateHandling);

                    // طباعة محتويات FormData للتأكد
                    console.log('📦 محتويات FormData:');
                    for (let [key, value] of formData.entries()) {
                        if (value instanceof File) {
                            console.log(`${key}: ${value.name} (${value.size} bytes)`);
                        } else {
                            console.log(`${key}: ${value}`);
                        }
                    }

                    // عرض تفاصيل الرفع في Console
                    console.log('📦 تفاصيل بيانات الرفع:', {
                        totalFiles: this.files.size,
                        folderName: folderName,
                        enableDuplicateDetection,
                        duplicateHandling,
                        endpoint: '/api/files/process-folder-duplicates'
                    });

                    // استخدام route Laravel للرفع
                    console.log('🌐 إرسال الطلب إلى:', '/api/duplicate-files/process');

                    // التحقق من CSRF token
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    console.log('🔐 CSRF Token:', csrfToken ? 'موجود' : 'غير موجود');

                    const response = await fetch('/api/duplicate-files/process', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    console.log('📡 استجابة الخادم:', {
                        status: response.status,
                        statusText: response.statusText,
                        ok: response.ok,
                        headers: Object.fromEntries(response.headers.entries())
                    });

                    if (response.ok) {
                        const result = await response.json();
                        console.log('✅ تم رفع الملفات بنجاح:', result);

                        if (result.success) {
                            this.showAlert(result.message || 'تم رفع الملفات بنجاح', 'success');

                            // التعامل مع الملفات المكررة
                            if (result.data && result.data.duplicates_found > 0) {
                                console.log('🔍 تم العثور على ملفات مكررة:', result.data);

                                // حفظ session ID للملفات المكررة
                                if (result.data.session_id) {
                                    sessionStorage.setItem('duplicate_files_session_id', result.data.session_id);
                                    window.CURRENT_DUPLICATE_SESSION_ID = result.data.session_id;

                                    // إظهار زر الملفات المكررة
                                    const duplicateBtn = document.getElementById('showDuplicateFilesBtn');
                                    if (duplicateBtn) {
                                        duplicateBtn.style.display = 'inline-block';
                                        duplicateBtn.innerHTML = `<i class="fas fa-clone me-2"></i>عرض الملفات المكررة (${result.data.duplicates_found})`;
                                        duplicateBtn.classList.add('btn-warning');
                                        duplicateBtn.classList.remove('btn-secondary');
                                    }

                                    this.showAlert(`تم العثور على ${result.data.duplicates_found} ملف مكرر`, 'warning');
                                }
                            }

                            // this.clearAllFiles();
                            this.loadAnalytics();
                        } else {
                            this.showAlert(result.message || 'فشل في رفع الملفات', 'danger');
                        }
                    } else {
                        const errorText = await response.text();
                        console.error('❌ فشل في رفع الملفات:', {
                            status: response.status,
                            statusText: response.statusText,
                            responseText: errorText
                        });
                        this.showAlert(`فشل في رفع الملفات: ${response.status} ${response.statusText}`, 'danger');
                    }
                } catch (error) {
                    console.error('❌ خطأ في عملية الرفع:', {
                        name: error.name,
                        message: error.message,
                        stack: error.stack
                    });
                    this.showAlert('خطأ في عملية الرفع: ' + error.message, 'danger');
                } finally {
                    this.uploadInProgress = false; // إعادة تعيين حالة الرفع
                    if (uploadProgressSection) {
                        uploadProgressSection.style.display = 'none';
                    }
                }
            }

            // معالجة المجلدات والملفات
            processFolderFiles(files, type) {
                console.log(`📁 معالجة ${files.length} ملف من نوع: ${type}`);

                try {
                    // إضافة الملفات للخريطة
                    Array.from(files).forEach((file, index) => {
                        const fileId = `${type}_${Date.now()}_${index}`;
                        this.files.set(fileId, file);
                        console.log(`📄 تم إضافة ملف: ${file.name}`);
                    });

                    // تحديث العرض
                    this.updateFileDisplay();

                    // عرض رسالة نجاح
                    this.showAlert(`تم إضافة ${files.length} ملف بنجاح`, 'success');

                    console.log('✅ تمت معالجة الملفات بنجاح');

                } catch (error) {
                    console.error('❌ خطأ في معالجة الملفات:', error);
                    this.showAlert('خطأ في معالجة الملفات', 'danger');
                }
            }

            // تحديث عرض الملفات
            updateFileDisplay() {
                // تحديث منطقة المعاينة
                this.updateFilePreviewsContainer();
                // تحديث قائمة الملفات (إن وجدت)
                const filesList = document.getElementById('filesList');
                if (filesList) {
                    filesList.innerHTML = '';
                    this.files.forEach((file, fileId) => {
                        const row = document.createElement('tr');
                        row.className = 'file-row';
                        row.dataset.fileId = fileId;
                        row.dataset.fileType = file.type || 'unknown';
                        row.innerHTML = `
                            <td>${file.name}</td>
                            <td>${this.formatFileSize(file.size)}</td>
                            <td>${file.type || 'غير محدد'}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="window.fileManagerApp.removeFile('${fileId}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                        filesList.appendChild(row);
                    });
                }
                // تحديث الإحصائيات
                this.loadAnalytics();
                // تحسين أزرار الرفع
                this.enhanceUploadButtons();
                // إظهار زر بدء الرفع دائماً عند وجود ملفات
                this.showUploadSection();
            }

            // تحديث منطقة معاينة الملفات
            updateFilePreviewsContainer() {
                const container = document.getElementById('filePreviewsContainer');
                if (!container) return;

                if (this.files.size === 0) {
                    container.innerHTML = `
                        <div class="col-12 text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-3x mb-3"></i>
                            <p>لا توجد ملفات محددة بعد</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = '';

                this.files.forEach((file, fileId) => {
                    const col = document.createElement('div');
                    col.className = 'col-md-3 col-sm-6 mb-3';

                    const fileType = this.getFileTypeCategory(file);
                    const icon = this.getFileIcon(file);

                    col.innerHTML = `
                        <div class="file-preview-card" data-file-id="${fileId}" data-file-type="${fileType}">
                            <div class="file-preview-content">
                                ${this.isImageFile(file) ?
                                    `<img src="${URL.createObjectURL(file)}" alt="${file.name}" class="file-preview-image">` :
                                    `<div class="file-preview-icon"><i class="${icon} fa-3x"></i></div>`
                                }
                                <div class="file-preview-info">
                                    <h6 class="file-name" title="${file.name}">${this.truncateFileName(file.name)}</h6>
                                    <small class="text-muted">${this.formatFileSize(file.size)}</small>
                                    <div class="file-actions mt-2">
                                        <button class="btn btn-sm btn-danger" onclick="window.fileManagerApp.removeFile('${fileId}')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="window.fileManagerApp.previewFile('${fileId}')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    container.appendChild(col);
                });

                console.log(`🖼️ تم عرض ${this.files.size} ملف في منطقة المعاينة`);
            }

            // تحديد فئة نوع الملف
            getFileTypeCategory(file) {
                const ext = file.name.split('.').pop().toLowerCase();

                if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) {
                    return 'image';
                } else if (['pdf'].includes(ext)) {
                    return 'pdf';
                } else if (['xlsx', 'xls', 'csv'].includes(ext)) {
                    return 'excel';
                } else if (['doc', 'docx'].includes(ext)) {
                    return 'word';
                } else if (['zip', 'rar', '7z'].includes(ext)) {
                    return 'archive';
                } else {
                    return 'other';
                }
            }

            // الحصول على أيقونة الملف
            getFileIcon(file) {
                const type = this.getFileTypeCategory(file);

                switch (type) {
                    case 'image': return 'fas fa-image text-success';
                    case 'pdf': return 'fas fa-file-pdf text-danger';
                    case 'excel': return 'fas fa-file-excel text-success';
                    case 'word': return 'fas fa-file-word text-primary';
                    case 'archive': return 'fas fa-file-archive text-warning';
                    default: return 'fas fa-file text-secondary';
                }
            }

            // فحص إذا كان الملف صورة
            isImageFile(file) {
                return file.type && file.type.startsWith('image/');
            }

            // اختصار اسم الملف
            truncateFileName(fileName) {
                if (fileName.length <= 20) return fileName;
                return fileName.substring(0, 17) + '...';
            }

            // معاينة الملف
            previewFile(fileId) {
                const file = this.files.get(fileId);
                if (!file) return;

                console.log('👁️ معاينة الملف:', file.name);
                // يمكن إضافة منطق معاينة مفصل هنا
                this.showAlert(`معاينة الملف: ${file.name}`, 'info');
            }

            // إزالة ملف
            removeFile(fileId) {
                if (this.files.has(fileId)) {
                    const file = this.files.get(fileId);
                    this.files.delete(fileId);
                    console.log(`🗑️ تم حذف الملف: ${file.name}`);
                    this.updateFileDisplay();
                    this.showAlert('تم حذف الملف', 'info');
                }
            }

            // عرض التنبيهات
            showAlert(message, type = 'info') {
                console.log(`🚨 ${type.toUpperCase()}: ${message}`);

                // البحث عن منطقة الإشعارات أو إنشاؤها
                let alertsContainer = document.getElementById('alertsContainer');
                if (!alertsContainer) {
                    alertsContainer = document.createElement('div');
                    alertsContainer.id = 'alertsContainer';
                    alertsContainer.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 9999;
                        width: 300px;
                    `;
                    document.body.appendChild(alertsContainer);
                }

                // إنشاء الإشعار
                const alert = document.createElement('div');
                alert.className = `alert alert-${type} alert-dismissible fade show`;
                alert.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;

                alertsContainer.appendChild(alert);

                // إزالة الإشعار تلقائياً بعد 5 ثوان
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 5000);
            }

            // تنسيق حجم الملف
            formatFileSize(bytes) {
                if (!bytes) return '0 Bytes';

                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));

                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // معالج تغيير فلتر الملفات
            handleFilterChange(event) {
                console.log('🔍 تم تغيير فلتر الملفات:', event.target.value);

                const filter = event.target.value;
                const fileRows = document.querySelectorAll('#filesList .file-row');

                fileRows.forEach(row => {
                    const fileType = row.dataset.fileType || 'unknown';
                    let shouldShow = true;

                    switch (filter) {
                        case 'images':
                            shouldShow = fileType.startsWith('image/');
                            break;
                        case 'documents':
                            shouldShow = fileType.includes('pdf') ||
                                        fileType.includes('doc') ||
                                        fileType.includes('text');
                            break;
                        case 'archives':
                            shouldShow = fileType.includes('zip') ||
                                        fileType.includes('rar');
                            break;
                        case 'all':
                        default:
                            shouldShow = true;
                            break;
                    }

                    row.style.display = shouldShow ? 'table-row' : 'none';
                });

                // تحديث العداد
                const visibleFiles = document.querySelectorAll('#filesList .file-row:not([style*="display: none"])').length;
                console.log(`📊 عدد الملفات المرئية: ${visibleFiles}`);
            }

            // إضافة دالة loadAnalytics المفقودة
            loadAnalytics() {
                console.log('📊 تحميل التحليلات...');

                try {
                    const totalFiles = this.files ? this.files.size : 0;
                    const totalSize = this.files ? Array.from(this.files.values())
                        .reduce((sum, file) => sum + (file.size || 0), 0) : 0;

                    // تحديث العدادات في لوحة التحكم
                    const totalFilesCount = document.getElementById('totalFilesCount');
                    if (totalFilesCount) {
                        totalFilesCount.textContent = totalFiles;
                    }

                    const totalStorageSize = document.getElementById('totalStorageSize');
                    if (totalStorageSize) {
                        totalStorageSize.textContent = this.formatFileSize(totalSize);
                    }

                    // تحديث عدادات أخرى إن وجدت
                    const fileCountElement = document.querySelector('.file-count');
                    if (fileCountElement) {
                        fileCountElement.textContent = totalFiles;
                    }

                    const totalSizeElement = document.querySelector('.total-size');
                    if (totalSizeElement) {
                        totalSizeElement.textContent = this.formatFileSize(totalSize);
                    }

                    console.log(`📈 إحصائيات: ${totalFiles} ملف، الحجم: ${this.formatFileSize(totalSize)}`);

                } catch (error) {
                    console.error('❌ خطأ في تحميل التحليلات:', error);
                }
            }

            // دالة validateInputs المفقودة
            validateInputs() {
                console.log('✅ التحقق من صحة المدخلات...');

                try {
                    const hasFiles = this.files ? this.files.size > 0 : false;

                    if (!hasFiles) {
                        this.showAlert('يرجى اختيار ملفات للرفع', 'warning');
                        return false;
                    }

                    console.log('✅ جميع المدخلات صحيحة');
                    return true;

                } catch (error) {
                    console.error('❌ خطأ في التحقق من المدخلات:', error);
                    return false;
                }
            }

            // تحسين إظهار أزرار الرفع مع تأثيرات بصرية
            enhanceUploadButtons() {
                const startUploadBtnMain = document.getElementById('startUploadBtnMain');

                if (startUploadBtnMain && this.files.size > 0) {
                    // إضافة تأثير بصري للفت الانتباه
                    startUploadBtnMain.classList.add('btn-pulse');
                    startUploadBtnMain.innerHTML = `
                        <i class="fas fa-database me-2"></i>
                        إرسال ${this.files.size} ملف إلى قاعدة البيانات
                        <span class="badge bg-light text-dark ms-2">${this.files.size}</span>
                    `;
                } else if (startUploadBtnMain) {
                    startUploadBtnMain.classList.remove('btn-pulse');
                    startUploadBtnMain.innerHTML = `
                        <i class="fas fa-database me-2"></i>إرسال إلى قاعدة البيانات
                    `;
                }
            }
        }

        // Visual Feedback System
        function addVisualFeedback() {
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                // Add hover effect
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                    this.style.transition = 'transform 0.2s';
                });

                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });

                // Add click effect
                button.addEventListener('mousedown', function() {
                    this.style.transform = 'scale(0.95)';
                    console.log(`🖱️ Button clicked: ${this.id || this.className}`);
                });

                button.addEventListener('mouseup', function() {
                    this.style.transform = 'scale(1.05)';
                });
            });
        }

        // Enhanced error handling
        window.addEventListener('error', function(e) {
            console.error('🚨 JavaScript Error:', e.error);
            console.error('📍 في الملف:', e.filename);
            console.error('📍 السطر:', e.lineno);
        });

        // Initialize the application when DOM is ready
        let app;
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 تهيئة تطبيق إدارة الملفات...');

            // First, check if all required elements exist
            const requiredElements = [
                'fileInput', 'folderInput', 'imagesWithFolderInput',
                'selectFilesBtn', 'selectFilesDropBtn', 'selectFolderBtn',
                'selectFolderWithImagesBtn', 'generateRecordBtn', 'clearAllBtn',
                'startUploadBtn', 'startUploadBtnMain'
            ];

            const missingElements = [];
            requiredElements.forEach(id => {
                if (!document.getElementById(id)) {
                    missingElements.push(id);
                }
            });

            if (missingElements.length > 0) {
                console.warn('⚠️ العناصر المفقودة:', missingElements);
            }

            try {
                app = new AdvancedFileManager();
                console.log('✅ تم تهيئة التطبيق بنجاح');

                // Make app globally available for debugging
                window.fileManagerApp = app;

                // Add additional click listeners as backup
                addBackupEventListeners();

            } catch (error) {
                console.error('❌ خطأ في تهيئة التطبيق:', error);
            }
        });

        // Backup event listeners in case the main ones fail
        function addBackupEventListeners() {
            console.log('🔧 إضافة event listeners احتياطية...');

            // Backup for select files button
            const selectFilesBtn = document.getElementById('selectFilesBtn');
            if (selectFilesBtn) {
                selectFilesBtn.onclick = function() {
                    console.log('🖱️ [احتياطي] زر اختيار ملفات');
                    const fileInput = document.getElementById('fileInput');
                    if (fileInput) fileInput.click();
                };
            }

            // Backup for select files drop button
            const selectFilesDropBtn = document.getElementById('selectFilesDropBtn');
            if (selectFilesDropBtn) {
                selectFilesDropBtn.onclick = function() {
                    console.log('🖱️ [احتياطي] زر اختيار ملفات (منطقة السحب)');
                    const fileInput = document.getElementById('fileInput');
                    if (fileInput) fileInput.click();
                };
            }

            // Backup for select folder button
            const selectFolderBtn = document.getElementById('selectFolderBtn');
            if (selectFolderBtn) {
                selectFolderBtn.onclick = function() {
                    console.log('🖱️ [احتياطي] زر اختيار مجلد');
                    const folderInput = document.getElementById('folderInput');
                    if (folderInput) folderInput.click();
                };
            }

            // Backup for select folder with images button
            const selectFolderWithImagesBtn = document.getElementById('selectFolderWithImagesBtn');
            if (selectFolderWithImagesBtn) {
                selectFolderWithImagesBtn.onclick = function() {
                    console.log('🖱️ [احتياطي] زر مجلد + صور');
                    const imagesWithFolderInput = document.getElementById('imagesWithFolderInput');
                    if (imagesWithFolderInput) imagesWithFolderInput.click();
                };
            }

            // Backup for generate record button
            const generateRecordBtn = document.getElementById('generateRecordBtn');
            if (generateRecordBtn) {
                generateRecordBtn.onclick = function() {
                    console.log('🖱️ [احتياطي] زر إنشاء رقم ملف');
                    if (window.fileManagerApp && window.fileManagerApp.generateRecordNumber) {
                        window.fileManagerApp.generateRecordNumber();
                    }
                };
            }

            // Backup for clear all button
            const clearAllBtn = document.getElementById('clearAllBtn');
            if (clearAllBtn) {
                clearAllBtn.onclick = function() {
                    console.log('🖱️ [احتياطي] زر مسح الكل');
                    if (window.fileManagerApp && window.fileManagerApp.clearAllFiles) {
                        window.fileManagerApp.clearAllFiles();
                    }
                };
            }

            // Backup for start upload button
            const startUploadBtn = document.getElementById('startUploadBtn');
            if (startUploadBtn) {
                startUploadBtn.onclick = function() {
                    console.log('🖱️ [احتياطي] زر بدء الرفع');
                    if (window.fileManagerApp && window.fileManagerApp.startUploads) {
                        window.fileManagerApp.startUploads();
                    }
                };
            }

            // Backup for main upload button
            const startUploadBtnMain = document.getElementById('startUploadBtnMain');
            if (startUploadBtnMain) {
                startUploadBtnMain.onclick = function() {
                    console.log('🖱️ [احتياطي] زر إرسال إلى قاعدة البيانات');
                    if (window.fileManagerApp && window.fileManagerApp.startUploads) {
                        window.fileManagerApp.startUploads();
                    }
                };
            }

            console.log('✅ تم إضافة event listeners احتياطية');
        }

        // DOM Diagnostic Tool
        function diagnoseDOMElements() {
            console.log('🔍 تشخيص عناصر DOM...');

            const elements = {
                'fileInput': document.getElementById('fileInput'),
                'folderInput': document.getElementById('folderInput'),
                'imagesWithFolderInput': document.getElementById('imagesWithFolderInput'),
                'selectFilesBtn': document.getElementById('selectFilesBtn'),
                'selectFilesDropBtn': document.getElementById('selectFilesDropBtn'),
                'selectFolderBtn': document.getElementById('selectFolderBtn'),
                'selectFolderWithImagesBtn': document.getElementById('selectFolderWithImagesBtn'),
                'generateRecordBtn': document.getElementById('generateRecordBtn'),
                'clearAllBtn': document.getElementById('clearAllBtn'),
                'startUploadBtn': document.getElementById('startUploadBtn'),
                'startUploadBtnMain': document.getElementById('startUploadBtnMain')
            };

            console.table(Object.entries(elements).map(([id, element]) => ({
                Element: id,
                Exists: !!element,
                Type: element?.tagName,
                Classes: element?.className || 'N/A',
                Text: element?.textContent?.slice(0, 30) || 'N/A'
            })));

            // Test click functionality
            Object.entries(elements).forEach(([id, element]) => {
                if (element && element.tagName === 'BUTTON') {
                    console.log(`🧪 اختبار الزر: ${id}`);
                    element.addEventListener('click', function() {
                        console.log(`✅ ${id} clicked successfully!`);
                    }, { once: false });
                }
            });
        }

        // Run diagnostic after everything loads
        window.addEventListener('load', function() {
            setTimeout(() => {
                diagnoseDOMElements();
                console.log('🎯 يمكنك اختبار الأزرار الآن');
            }, 500);
        });
    </script>
</body>
</html>
