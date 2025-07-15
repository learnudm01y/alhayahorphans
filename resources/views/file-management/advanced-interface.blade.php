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
                            <button type="button" class="btn btn-primary btn-lg" id="selectFilesBtn">
                                <i class="fas fa-file-plus me-2"></i>اختيار ملفات
                            </button>
                            <button type="button" class="btn btn-success btn-lg" id="startUploadBtn" style="display: none;">
                                <i class="fas fa-upload me-2"></i>بدء الرفع
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
                        const response = await fetch(`/admin/file/duplicate-summary?session_id=${sessionId}`);
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
                const selectFolderBtn = document.getElementById('selectFolderBtn');
                const selectFolderWithImagesBtn = document.getElementById('selectFolderWithImagesBtn');
                const generateRecordBtn = document.getElementById('generateRecordBtn');
                const clearAllBtn = document.getElementById('clearAllBtn');
                const startUploadBtn = document.getElementById('startUploadBtn');

                // Drag and drop events
                dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
                dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
                dropZone.addEventListener('drop', this.handleDrop.bind(this));

                // File and folder selection
                selectBtn.addEventListener('click', () => fileInput.click());
                selectFolderBtn.addEventListener('click', () => folderInput.click());
                selectFolderWithImagesBtn.addEventListener('click', () => imagesWithFolderInput.click());

                fileInput.addEventListener('change', this.handleFileSelect.bind(this));
                folderInput.addEventListener('change', this.handleFolderSelect.bind(this));
                imagesWithFolderInput.addEventListener('change', this.handleImagesWithFolderSelect.bind(this));

                // Record number generation
                generateRecordBtn.addEventListener('click', this.generateRecordNumber.bind(this));

                // Clear all files
                clearAllBtn.addEventListener('click', this.clearAllFiles.bind(this));

                // Start upload manually
                startUploadBtn.addEventListener('click', this.startUploads.bind(this));

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
            }

            clearAllFiles() {
                this.files.clear();
                document.getElementById('fileInput').value = '';
                document.getElementById('folderInput').value = '';
                document.getElementById('imagesWithFolderInput').value = '';
                document.getElementById('filePreviewsContainer').innerHTML = `
                    <div class="col-12 text-center text-muted py-5">
                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                        <p>لا توجد ملفات محددة بعد</p>
                    </div>
                `;
                document.getElementById('startUploadBtn').style.display = 'none';
                document.getElementById('uploadProgressSection').style.display = 'none';
                this.updateFileCounts();

                console.log('🧹 تم مسح جميع الملفات');
            }

            async generateRecordNumber() {
                try {
                    const response = await fetch('/api/files/generate-record-number', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const result = await response.json();
                        document.getElementById('recordNumber').value = result.record_number;

                        // عرض رقم الملف في Console
                        console.log('🔢 رقم الملف المولد:', result.record_number);
                        console.log('📄 تفاصيل إضافية:', {
                            recordNumber: result.record_number,
                            timestamp: new Date().toISOString(),
                            source: 'file_id_number من قاعدة البيانات'
                        });

                        this.showAlert(`تم إنشاء رقم الملف: ${result.record_number}`, 'success');
                    } else {
                        throw new Error('Failed to generate record number');
                    }
                } catch (error) {
                    console.error('❌ خطأ في توليد رقم الملف:', error);
                    this.showAlert('فشل في إنشاء رقم الملف', 'danger');
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

                    // جميع المجلدات في المسار
                    pathParts.slice(0, -1).forEach((folderName, index) => {
                        analysis.folders.add(folderName);

                        // تحديد نوع المجلد
                        if (/^\d{8,10}$/.test(folderName)) {
                            analysis.identityFolders.add(folderName);
                        } else if (index === 0) {
                            // المجلد الأول في المسار (المجلد الأب)
                            analysis.parentFolders.add(folderName);
                        } else {
                            // مجلد متوسط أو غير صالح
                            analysis.invalidFolders.add(folderName);
                        }
                    });

                    // بناء هيكل التسلسل الهرمي للمجلدات
                    let currentLevel = analysis.folderHierarchy;
                    pathParts.slice(0, -1).forEach(folderName => {
                        if (!currentLevel[folderName]) {
                            currentLevel[folderName] = {
                                files: [],
                                subfolders: {},
                                isIdentityFolder: /^\d{8,10}$/.test(folderName),
                                isParentFolder: false
                            };
                        }
                        currentLevel = currentLevel[folderName].subfolders;
                    });

                    // إضافة الملف إلى المجلد المناسب
                    let targetLevel = analysis.folderHierarchy;
                    pathParts.slice(0, -1).forEach(folderName => {
                        targetLevel = targetLevel[folderName];
                        if (pathParts.slice(0, -1)[pathParts.slice(0, -1).length - 1] === folderName) {
                            targetLevel.files.push({
                                name: file.name,
                                size: file.size,
                                type: file.type,
                                fullPath: file.webkitRelativePath
                            });
                        }
                        targetLevel = targetLevel.subfolders;
                    });

                    // تحليل نوع الملف
                    const ext = file.name.split('.').pop().toLowerCase();
                    analysis.fileTypes[ext] = (analysis.fileTypes[ext] || 0) + 1;

                    // بناء هيكل المجلد التقليدي (للتوافق مع الكود الموجود)
                    if (!analysis.structure[directFolder]) {
                        analysis.structure[directFolder] = [];
                    }
                    analysis.structure[directFolder].push({
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        fullPath: file.webkitRelativePath
                    });
                });

                // تحويل Sets إلى Arrays
                analysis.folders = Array.from(analysis.folders);
                analysis.identityFolders = Array.from(analysis.identityFolders);
                analysis.parentFolders = Array.from(analysis.parentFolders);
                analysis.invalidFolders = Array.from(analysis.invalidFolders);

                // تحديد المجلدات الأب
                Object.keys(analysis.folderHierarchy).forEach(rootFolder => {
                    analysis.folderHierarchy[rootFolder].isParentFolder = true;
                });

                return analysis;
            }

            processFiles(files) {
                if (!this.validateInputs()) return;

                files.forEach(file => {
                    const fileId = this.generateFileId();
                    const fileData = {
                        id: fileId,
                        file: file,
                        status: 'pending',
                        progress: 0,
                        type: this.detectFileType(file),
                        preview: null,
                        processing: {
                            compression: false,
                            cloudSync: false,
                            ocr: false
                        }
                    };

                    this.files.set(fileId, fileData);
                    this.createFilePreview(fileData);
                });

                this.updateFileCounts();
                this.startUploads();
            }

            processFolderFiles(files, type) {
                if (!this.validateInputs()) return;

                console.log(`🔄 بدء معالجة ${type === 'folder' ? 'المجلد' : 'مجلد الصور'}...`);

                // تحليل هيكل المجلدات لتحديد الملفات الصالحة
                const folderAnalysis = this.analyzeFolderStructure(files);

                // فلترة الملفات للاحتفاظ فقط بالملفات في مجلدات الهوية الصحيحة
                const validFiles = files.filter(file => {
                    const pathParts = file.webkitRelativePath.split('/').filter(part => part.trim() !== '');

                    // البحث عن مجلد هوية في المسار
                    const hasIdentityFolder = pathParts.some(part => /^\d{8,10}$/.test(part));

                    if (hasIdentityFolder) {
                        return true;
                    }

                    // إذا لم نجد مجلد هوية، سجل تحذير
                    console.log(`⚠️ تجاهل الملف: ${file.webkitRelativePath} - لا يوجد في مجلد هوية صحيح`);
                    return false;
                });

                console.log(`📊 فلترة الملفات: ${files.length} إجمالي → ${validFiles.length} صالح`);

                if (validFiles.length === 0) {
                    console.log('⚠️ لا توجد ملفات صالحة للرفع. تأكد من وجود مجلدات بأسماء أرقام هوية صحيحة (8-10 أرقام).');
                    return;
                }

                // معالجة الملفات الصالحة محلياً أولاً للمعاينة
                validFiles.forEach(file => {
                    const fileId = this.generateFileId();
                    const pathParts = file.webkitRelativePath.split('/').filter(part => part.trim() !== '');

                    // العثور على مجلد الهوية في المسار
                    const identityFolder = pathParts.find(part => /^\d{8,10}$/.test(part));

                    const fileData = {
                        id: fileId,
                        file: file,
                        status: 'pending',
                        progress: 0,
                        type: this.detectFileType(file),
                        preview: null,
                        folderPath: file.webkitRelativePath.split('/').slice(0, -1).join('/'),
                        relativePath: file.webkitRelativePath,
                        identityFolder: identityFolder,
                        source: type === 'folder' ? 'folder-upload' : 'images-folder',
                        processing: {
                            compression: document.getElementById('compressImages').checked,
                            cloudSync: document.getElementById('cloudSync').checked,
                            ocr: false
                        }
                    };

                    this.files.set(fileId, fileData);
                    this.createFilePreview(fileData);
                });

                this.updateFileCounts();
                this.toggleExcelOptions();

                console.log(`✅ تمت معالجة ${validFiles.length} ملف صالح من ${type === 'folder' ? 'المجلد' : 'مجلد الصور'}`);
                console.log('📊 حالة النظام:', {
                    totalFiles: this.files.size,
                    identityFolders: folderAnalysis.identityFolders,
                    parentFolders: folderAnalysis.parentFolders,
                    folderStructure: this.getFolderStructure()
                });

                // إظهار زر الرفع
                document.getElementById('startUploadBtn').style.display = 'inline-block';

                // حفظ الملفات المعالجة للرفع اللاحق
                this.processedFiles = validFiles;
                this.currentUploadType = type;

                console.log(`✨ تم تحضير ${validFiles.length} ملف للرفع. اضغط على زر "بدء الرفع" لتنفيذ العملية.`);
            }

            getFolderStructure() {
                const structure = {};
                this.files.forEach(fileData => {
                    if (fileData.folderPath) {
                        if (!structure[fileData.folderPath]) {
                            structure[fileData.folderPath] = [];
                        }
                        structure[fileData.folderPath].push(fileData);
                    }
                });
                return structure;
            }

            async loadAnalytics() {
                try {
                    const response = await fetch('/api/files/analytics', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        document.getElementById('totalFilesCount').innerText = data.total_files;
                        document.getElementById('totalStorageSize').innerText = (data.total_storage_size / 1024 / 1024).toFixed(1) + ' MB';
                        document.getElementById('todayFilesCount').innerText = data.today_files;
                        document.getElementById('compressionRatio').innerText = data.compression_ratio + '%';
                    } else {
                        throw new Error('Failed to load analytics data');
                    }
                } catch (error) {
                    console.error('❌ خطأ في تحميل بيانات التحليلات:', error);
                }
            }

            validateInputs() {
                // Add your validation logic here
                return true;
            }

            generateFileId() {
                return 'file_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
            }

            detectFileType(file) {
                const ext = file.name.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) return 'image';
                if (['pdf'].includes(ext)) return 'pdf';
                if (['xlsx', 'xls', 'csv'].includes(ext)) return 'excel';
                if (['zip', 'rar', '7z'].includes(ext)) return 'archive';
                return 'unknown';
            }

            createFilePreview(fileData) {
                const container = document.getElementById('filePreviewsContainer');

                // إزالة رسالة "لا توجد ملفات" إذا كانت موجودة
                const emptyMessage = container.querySelector('.text-center.text-muted');
                if (emptyMessage) {
                    emptyMessage.remove();
                }

                const fileElement = document.createElement('div');
                fileElement.className = 'col-md-4 mb-4';

                // تحديد نوع الملف وأيقونته
                let iconClass = 'fas fa-file';
                if (fileData.type === 'image') iconClass = 'fas fa-image';
                else if (fileData.type === 'pdf') iconClass = 'fas fa-file-pdf';
                else if (fileData.type === 'excel') iconClass = 'fas fa-file-excel';

                fileElement.innerHTML = `
                    <div class="file-preview" id="preview_${fileData.id}">
                        <div class="position-relative">
                            <div class="d-flex align-items-center justify-content-center bg-light rounded-top" style="height: 150px;">
                                <i class="${iconClass} fa-3x text-secondary"></i>
                            </div>
                            <div class="processing-overlay" style="display: none;" id="overlay_${fileData.id}">
                                <div class="spinner-border text-light" role="status">
                                    <span class="visually-hidden">جاري المعالجة...</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 bg-light rounded-bottom">
                            <h6 class="mb-1" id="fileName_${fileData.id}" title="${fileData.file.name}">
                                ${fileData.file.name.length > 20 ? fileData.file.name.substring(0, 20) + '...' : fileData.file.name}
                            </h6>
                            <small class="text-muted d-block mb-2">📁 ${fileData.folderPath || 'مجلد رئيسي'}</small>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-secondary" id="fileType_${fileData.id}">${fileData.type}</span>
                                    <span class="badge bg-info text-dark" id="fileStatus_${fileData.id}">معلق</span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="actionsMenu_${fileData.id}" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="actionsMenu_${fileData.id}">
                                        <li><a class="dropdown-item" href="#" onclick="app.downloadFile('${fileData.id}')"><i class="fas fa-download me-2"></i> تحميل</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="app.deleteFile('${fileData.id}')"><i class="fas fa-trash me-2"></i> حذف</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(fileElement);
            }

            updateFileCounts() {
                // تحديث عدادات الواجهة
                const totalFiles = this.files.size;
                document.getElementById('totalFiles').textContent = totalFiles;

                // تحديث إحصائيات بسيطة
                const completedFiles = Array.from(this.files.values()).filter(f => f.status === 'completed').length;
                const processingFiles = Array.from(this.files.values()).filter(f => f.status === 'processing').length;
                const failedFiles = Array.from(this.files.values()).filter(f => f.status === 'failed').length;

                document.getElementById('completedFiles').textContent = completedFiles;
                document.getElementById('processingFiles').textContent = processingFiles;
                document.getElementById('failedFiles').textContent = failedFiles;

                // إظهار قسم التقدم إذا كان هناك ملفات
                if (totalFiles > 0) {
                    document.getElementById('uploadProgressSection').style.display = 'block';
                    const progress = totalFiles > 0 ? (completedFiles / totalFiles) * 100 : 0;
                    document.getElementById('overallProgress').style.width = `${progress}%`;
                }
            }

            async uploadFolderFile(files, uploadType) {
                console.log(`🚀 بدء رفع ${uploadType}:`, {
                    filesCount: files.length,
                    timestamp: new Date().toISOString()
                });

                const formData = new FormData();

                // إضافة جميع الملفات
                files.forEach((file, index) => {
                    formData.append(`files[${index}]`, file);
                    formData.append(`paths[${index}]`, file.webkitRelativePath || file.name);
                });

                // إضافة معلومات إضافية
                formData.append('upload_type', uploadType);
                formData.append('person_id', document.getElementById('personId').value || '');
                formData.append('compress_images', document.getElementById('compressImages').checked);
                formData.append('auto_organize', document.getElementById('autoOrganize').checked);
                formData.append('cloud_sync', document.getElementById('cloudSync').checked);

                // إضافة ملف Excel إذا كان موجود
                const excelFile = document.getElementById('excelFileInput').files[0];
                if (excelFile) {
                    formData.append('excel_file', excelFile);
                    formData.append('enable_excel_import', document.getElementById('enableExcelImport').checked);
                    formData.append('target_table', document.getElementById('targetTable').value);

                    console.log('📊 ملف Excel مرفق:', {
                        name: excelFile.name,
                        size: excelFile.size,
                        importEnabled: document.getElementById('enableExcelImport').checked
                    });
                }

                try {
                    console.log('📤 إرسال البيانات إلى الخادم...');

                    const response = await fetch('/admin/files/process-folder-upload', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (response.ok) {
                        console.log('✅ نجح رفع المجلد:', result);

                        // فحص وعرض الملفات المكررة
                        if (result.duplicate_files && result.duplicate_files.total_duplicates > 0) {
                            console.log('🔍 تم اكتشاف ملفات مكررة:', result.duplicate_files);

                            // حفظ session_id للملفات المكررة
                            if (result.duplicate_files.session_id) {
                                window.CURRENT_DUPLICATE_SESSION_ID = result.duplicate_files.session_id;
                                sessionStorage.setItem('duplicate_files_session_id', result.duplicate_files.session_id);

                                // إظهار زر عرض الملفات المكررة
                                const duplicateBtn = document.getElementById('showDuplicateFilesBtn');
                                if (duplicateBtn) {
                                    duplicateBtn.style.display = 'inline-block';
                                    duplicateBtn.innerHTML = `<i class="fas fa-clone me-2"></i>عرض الملفات المكررة (${result.duplicate_files.total_duplicates})`;
                                    duplicateBtn.classList.add('btn-warning');
                                    duplicateBtn.classList.remove('btn-secondary');
                                }

                                // عرض تنبيه للمستخدم
                                this.showAlert(`تم اكتشاف ${result.duplicate_files.total_duplicates} ملف مكرر. تم حفظهم في مجلد مؤقت.`, 'warning');
                            }
                        }

                        // عرض تفاصيل التحقق من الملفات المكررة
                        if (result.warnings && result.warnings.length > 0) {
                            console.log('⚠️ تحذيرات الملفات المكررة:', result.warnings);
                            result.warnings.forEach(warning => {
                                if (warning.type === 'duplicate_file') {
                                    console.log(`🔄 ملف مكرر: ${warning.file_name} في المجلد ${warning.folder}`);
                                    console.log(`📁 مسار التخزين المؤقت: ${warning.duplicate_temp_path}`);
                                }
                            });
                        }

                        // عرض ربط أسماء المجلدات برقم الملف في Console
                        if (result.identity_mapping && Object.keys(result.identity_mapping).length > 0) {
                            console.log('🔗 ربط أسماء المجلدات برقم الملف:');
                            Object.entries(result.identity_mapping).forEach(([identity, fileId]) => {
                                console.log(`📁 المجلد: ${identity} ← رقم الملف: ${fileId}`);
                            });
                        }

                        // عرض المجلدات المعتمدة والمرفوضة والمتجاهلة
                        if (result.validated_folders && Object.keys(result.validated_folders).length > 0) {
                            console.log('✅ مجلدات الهوية المعتمدة:');
                            Object.entries(result.validated_folders).forEach(([folderName, data]) => {
                                console.log(`📁 ${folderName} → file_id: ${data.file_id_number} (${data.matched_by})`);
                            });
                        }

                        if (result.ignored_parent_folders && result.ignored_parent_folders.length > 0) {
                            console.log('🏷️ المجلدات الأب المتجاهلة (غير مجلدات هوية):', result.ignored_parent_folders);
                            result.ignored_parent_folders.forEach(folder => {
                                console.log(`📂 ${folder} - تم تجاهله لأنه ليس مجلد هوية صحيح`);
                            });
                        }

                        if (result.rejected_folders && result.rejected_folders.length > 0) {
                            console.log('❌ مجلدات الهوية المرفوضة:', result.rejected_folders);
                            result.rejected_folders.forEach(folder => {
                                console.log(`📁 ${folder} - غير موجود في النظام`);
                            });
                        }

                        // عرض إحصائيات عملية الرفع
                        if (result.statistics) {
                            console.log('📊 إحصائيات العملية:', {
                                total_folders: result.statistics.total_folders,
                                identity_folders: result.statistics.identity_folders,
                                valid_folders: result.statistics.valid_folders,
                                rejected_identity_folders: result.statistics.rejected_identity_folders,
                                ignored_parent_folders: result.statistics.ignored_parent_folders,
                                processed_files: result.statistics.processed_files,
                                duplicate_files: result.statistics.duplicate_files_count || 0,
                                files_with_errors: result.statistics.files_with_errors,
                                files_with_warnings: result.statistics.files_with_warnings
                            });
                        }

                        this.showAlert(`تم رفع ${result.processed_files || files.length} ملف بنجاح!`, 'success');

                        // تحديث الواجهة
                        this.updateFileCounts();
                        this.loadAnalytics();

                        return result;
                    } else {
                        // معالجة أخطاء التحقق من صحة المجلدات
                        if (response.status === 422) {
                            let errorMessage = result.message;

                            if (result.rejected_folders && result.rejected_folders.length > 0) {
                                console.error('❌ مجلدات هوية مرفوضة:', result.rejected_folders);
                                errorMessage += `: ${result.rejected_folders.join(', ')}`;
                            }

                            if (result.ignored_parent_folders && result.ignored_parent_folders.length > 0) {
                                console.log('ℹ️ مجلدات أب تم تجاهلها (عادي):', result.ignored_parent_folders);
                                errorMessage += `\nملاحظة: تم تجاهل المجلدات الأب التالية بشكل طبيعي: ${result.ignored_parent_folders.join(', ')}`;
                            }

                            this.showAlert(errorMessage, 'danger');
                        } else {
                            throw new Error(result.message || 'فشل في رفع المجلد');
                        }
                    }
                } catch (error) {
                    console.error('❌ خطأ في رفع المجلد:', error);
                    this.showAlert(`فشل في رفع المجلد: ${error.message}`, 'danger');
                    throw error;
                }
            }

            async startUploads() {
                // التحقق من وجود ملفات معالجة للرفع (من المجلدات)
                if (this.processedFiles && this.processedFiles.length > 0) {
                    console.log('🚀 بدء رفع الملفات المعالجة من المجلد...');
                    try {
                        const result = await this.uploadFolderFile(this.processedFiles, this.currentUploadType);
                        console.log('🎉 اكتملت عملية الرفع والتحويل بنجاح!', result);
                        this.showAlert('تم رفع ملفات المجلد بنجاح!', 'success');

                        // مسح الملفات المعالجة بعد الرفع
                        this.processedFiles = null;
                        this.currentUploadType = null;
                    } catch (error) {
                        console.error('❌ فشلت عملية الرفع:', error);
                        this.showAlert('فشلت عملية رفع ملفات المجلد: ' + error.message, 'error');
                    }
                    return;
                }

                // معالجة الملفات العادية (غير المجلدات)
                if (this.activeUploads >= this.maxConcurrentUploads) {
                    this.showAlert('يوجد عمليات رفع نشطة. يرجى الانتظار حتى تكتمل.', 'warning');
                    return;
                }

                const pendingFiles = Array.from(this.files.values()).filter(f => f.status === 'pending');
                if (pendingFiles.length === 0) {
                    this.showAlert('لا توجد ملفات جديدة للرفع.', 'info');
                    return;
                }

                this.showAlert('بدء رفع الملفات...', 'info');
                this.updateFileCounts();

                for (const fileData of pendingFiles) {
                    if (this.activeUploads >= this.maxConcurrentUploads) break;

                    this.activeUploads++;
                    fileData.status = 'processing';
                    this.updateFileStatus(fileData.id, 'processing');

                    try {
                        // Simulate file upload
                        await this.simulateFileUpload(fileData);
                        fileData.status = 'completed';
                        this.updateFileStatus(fileData.id, 'completed');
                    } catch (error) {
                        fileData.status = 'failed';
                        this.updateFileStatus(fileData.id, 'failed');
                        console.error('❌ خطأ في رفع الملف:', fileData.file.name, error);
                    } finally {
                        this.activeUploads--;
                    }
                }

                this.updateFileCounts();
                this.showAlert('تمت معالجة الدفعة الحالية.', 'success');
            }

            async simulateFileUpload(fileData) {
                return new Promise((resolve, reject) => {
                    setTimeout(() => {
                        // Randomly succeed or fail the upload
                        Math.random() > 0.2 ? resolve() : reject(new Error('Upload failed'));
                    }, 2000);
                });
            }

            updateFileStatus(fileId, status) {
                const fileElement = document.getElementById(`preview_${fileId}`);
                const statusLabel = document.getElementById(`fileStatus_${fileId}`);
                const overlay = document.getElementById(`overlay_${fileId}`);

                if (status === 'processing') {
                    statusLabel.innerText = 'قيد المعالجة';
                    overlay.style.display = 'flex';
                } else if (status === 'completed') {
                    statusLabel.innerText = 'مكتملة';
                    overlay.style.display = 'none';
                } else if (status === 'failed') {
                    statusLabel.innerText = 'فاشلة';
                    overlay.style.display = 'none';
                }
            }

            showAlert(message, type = 'info') {
                const alertBox = document.createElement('div');
                alertBox.className = `alert alert-${type} alert-dismissible fade show`;
                alertBox.role = 'alert';
                alertBox.innerHTML = `
                    <i class="fas fa-info-circle me-2"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.container-fluid').prepend(alertBox);

                // إزالة التنبيه تلقائياً بعد 5 ثوان
                setTimeout(() => {
                    if (alertBox && alertBox.parentNode) {
                        alertBox.parentNode.removeChild(alertBox);
                    }
                }, 5000);
            }

            handleFilterChange(e) {
                const filter = e.target.value;
                const files = document.querySelectorAll('#filePreviewsContainer .file-preview');

                files.forEach(file => {
                    const fileType = file.querySelector('[id^="fileType_"]').innerText.toLowerCase();
                    if (filter === 'all' || fileType === filter) {
                        file.closest('.col-md-4').style.display = 'block';
                    } else {
                        file.closest('.col-md-4').style.display = 'none';
                    }
                });
            }

            downloadFile(fileId) {
                const fileData = this.files.get(fileId);
                if (!fileData) return;

                const url = URL.createObjectURL(fileData.file);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileData.file.name;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                this.showAlert(`جاري تحميل ${fileData.file.name}...`, 'info');
            }

            deleteFile(fileId) {
                this.files.delete(fileId);
                document.getElementById(`preview_${fileId}`).closest('.col-md-4').remove();
                this.showAlert('تم حذف الملف بنجاح.', 'success');
            }
        }

        const app = new AdvancedFileManager();

        // تفعيل بوابة Excel
        document.getElementById('excel-gateway-btn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('excelGatewayModal'));
            modal.show();
        });
    </script>
</body>
</html>

<!-- Excel Gateway Modal -->
<div class="modal fade" id="excelGatewayModal" tabindex="-1" aria-labelledby="excelGatewayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="excelGatewayModalLabel">
                    <i class="fas fa-table me-2"></i>بوابة Excel المتخصصة
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe src="{{ route('admin.file.excel.gateway') }}"
                        width="100%"
                        height="600"
                        frameborder="0"
                        id="excelGatewayFrame">
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
