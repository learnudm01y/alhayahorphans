<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>بوابة رفع ملفات الإكسل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
        }

        .excel-gateway {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin: 20px auto;
            max-width: 800px;
        }

        .excel-drop-zone {
            border: 3px dashed #28a745;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            cursor: pointer;
        }

        .excel-drop-zone:hover,
        .excel-drop-zone.drag-over {
            border-color: #20c997;
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            transform: scale(1.02);
        }

        .processing-mode-toggle {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .mode-option {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mode-option.selected {
            border-color: #007bff;
            background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%);
        }

        .mode-option:hover {
            border-color: #6c757d;
            background: #f8f9fa;
        }

        .preview-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
        }

        .progress-container {
            display: none;
            margin: 20px 0;
        }

        .result-container {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }

        .success-result {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .error-result {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .file-info {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }

        .btn-excel {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-excel:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="excel-gateway">
            <!-- Header -->
            <div class="text-center mb-4">
                <h1 class="display-6 text-success">
                    <i class="fas fa-file-excel me-3"></i>
                    بوابة رفع ملفات الإكسل
                </h1>
                <p class="text-muted">رفع وإدارة ملفات Excel مع إمكانية الاستيراد إلى قاعدة البيانات</p>
            </div>

            <!-- Processing Mode Selection -->
            <div class="processing-mode-toggle">
                <h5 class="mb-3">
                    <i class="fas fa-cogs me-2"></i>
                    اختر طريقة المعالجة
                </h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mode-option" data-mode="file-only" id="fileModeOption">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="processing_mode" value="file-only" class="form-check-input me-3" id="fileOnlyMode" checked>
                                <label for="fileOnlyMode" class="form-check-label flex-grow-1">
                                    <h6 class="mb-1">
                                        <i class="fas fa-save text-primary me-2"></i>
                                        حفظ الملف فقط
                                    </h6>
                                    <small class="text-muted">
                                        حفظ ملف Excel في مجلد التخزين العادي مع تسجيل معلوماته في جدول enhanced_attachments
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mode-option" data-mode="import-data" id="importModeOption">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="processing_mode" value="import-data" class="form-check-input me-3" id="importDataMode">
                                <label for="importDataMode" class="form-check-label flex-grow-1">
                                    <h6 class="mb-1">
                                        <i class="fas fa-database text-success me-2"></i>
                                        استيراد البيانات
                                    </h6>
                                    <small class="text-muted">
                                        حفظ الملف + قراءة محتواه وإدخال البيانات إلى قاعدة البيانات
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Options (shows when import mode is selected) -->
            <div id="importOptions" class="card" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-sliders-h me-2"></i>
                        إعدادات الاستيراد
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">الجدول المستهدف:</label>
                            <select class="form-select" id="targetTable">
                                <option value="data" selected>data (الجدول الرئيسي)</option>
                                <option value="dead_people">dead_people (بيانات المتوفين)</option>
                                <option value="guardian_bank_accounts">guardian_bank_accounts (حسابات الأوصياء)</option>
                                <option value="re_people">re_people (إعادة الأشخاص)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الصف الأول يحتوي على:</label>
                            <select class="form-select" id="headerRow">
                                <option value="true" selected>أسماء الأعمدة (Headers)</option>
                                <option value="false">بيانات فعلية</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skipEmptyRows" checked>
                                <label class="form-check-label" for="skipEmptyRows">
                                    تخطي الصفوف الفارغة
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="validateData" checked>
                                <label class="form-check-label" for="validateData">
                                    التحقق من صحة البيانات
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Upload Area -->
            <div class="excel-drop-zone" id="excelDropZone">
                <i class="fas fa-cloud-upload-alt fa-4x text-success mb-3"></i>
                <h4>اسحب ملفات Excel هنا أو اضغط للاختيار</h4>
                <p class="text-muted">الصيغ المدعومة: .xlsx, .xls, .csv</p>
                <input type="file" id="excelFileInput" multiple accept=".xlsx,.xls,.csv" style="display: none;">
                <div class="mt-3">
                    <button type="button" class="btn btn-success btn-lg" onclick="document.getElementById('excelFileInput').click()">
                        <i class="fas fa-plus me-2"></i>اختيار ملفات
                    </button>
                </div>
            </div>

            <!-- Selected Files Preview -->
            <div id="selectedFilesContainer" style="display: none;">
                <h5 class="mt-4 mb-3">
                    <i class="fas fa-list me-2"></i>
                    الملفات المحددة
                </h5>
                <div id="selectedFilesList"></div>
            </div>

            <!-- Progress Bar -->
            <div class="progress-container" id="progressContainer">
                <h6>جاري المعالجة...</h6>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar"
                         style="width: 0%"
                         id="uploadProgress">
                        0%
                    </div>
                </div>
                <div id="progressDetails" class="small text-muted"></div>
            </div>

            <!-- Results -->
            <div class="result-container" id="resultContainer">
                <div id="resultContent"></div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center mt-4" id="actionButtons">
                <button type="button" class="btn btn-excel btn-lg me-3" id="uploadBtn" disabled>
                    <i class="fas fa-upload me-2"></i>
                    رفع ومعالجة الملفات
                </button>
                <button type="button" class="btn btn-secondary btn-lg" id="clearBtn">
                    <i class="fas fa-trash me-2"></i>
                    مسح الاختيار
                </button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Global variables
        let selectedFiles = [];
        let currentProcessingMode = 'file-only';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            updateModeSelection();
        });

        function initializeEventListeners() {
            // Mode selection
            document.querySelectorAll('input[name="processing_mode"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    currentProcessingMode = this.value;
                    updateModeSelection();
                });
            });

            // Mode option clicks
            document.querySelectorAll('.mode-option').forEach(option => {
                option.addEventListener('click', function() {
                    const mode = this.dataset.mode;
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    currentProcessingMode = mode;
                    updateModeSelection();
                });
            });

            // File input change
            document.getElementById('excelFileInput').addEventListener('change', handleFileSelection);

            // Drag and drop
            const dropZone = document.getElementById('excelDropZone');
            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('dragleave', handleDragLeave);
            dropZone.addEventListener('drop', handleDrop);
            dropZone.addEventListener('click', function() {
                document.getElementById('excelFileInput').click();
            });

            // Buttons
            document.getElementById('uploadBtn').addEventListener('click', startUpload);
            document.getElementById('clearBtn').addEventListener('click', clearSelection);
        }

        function updateModeSelection() {
            // Update visual selection
            document.querySelectorAll('.mode-option').forEach(option => {
                option.classList.remove('selected');
            });

            const selectedOption = document.querySelector(`.mode-option[data-mode="${currentProcessingMode}"]`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }

            // Show/hide import options
            const importOptions = document.getElementById('importOptions');
            if (currentProcessingMode === 'import-data') {
                importOptions.style.display = 'block';
            } else {
                importOptions.style.display = 'none';
            }

            console.log('Processing mode updated:', currentProcessingMode);
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');

            const files = Array.from(e.dataTransfer.files).filter(file =>
                file.name.match(/\.(xlsx|xls|csv)$/i)
            );

            if (files.length > 0) {
                addFilesToSelection(files);
            } else {
                showAlert('يرجى اختيار ملفات Excel صحيحة (.xlsx, .xls, .csv)', 'warning');
            }
        }

        function handleFileSelection(e) {
            const files = Array.from(e.target.files);
            addFilesToSelection(files);
        }

        function addFilesToSelection(files) {
            // Validate file types
            const validFiles = files.filter(file =>
                file.name.match(/\.(xlsx|xls|csv)$/i)
            );

            if (validFiles.length !== files.length) {
                showAlert('تم تجاهل بعض الملفات لأنها ليست ملفات Excel صحيحة', 'warning');
            }

            // Add to selection
            validFiles.forEach(file => {
                if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    selectedFiles.push(file);
                }
            });

            updateFilePreview();
            updateUploadButton();
        }

        function updateFilePreview() {
            const container = document.getElementById('selectedFilesContainer');
            const filesList = document.getElementById('selectedFilesList');

            if (selectedFiles.length === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            filesList.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info d-flex justify-content-between align-items-center';

                const fileSize = (file.size / 1024).toFixed(2);
                const fileType = file.name.split('.').pop().toUpperCase();

                fileInfo.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-excel text-success fa-2x me-3"></i>
                        <div>
                            <h6 class="mb-1">${file.name}</h6>
                            <small class="text-muted">
                                النوع: ${fileType} | الحجم: ${fileSize} KB |
                                المعالجة: ${currentProcessingMode === 'file-only' ? 'حفظ الملف فقط' : 'استيراد البيانات'}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                filesList.appendChild(fileInfo);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFilePreview();
            updateUploadButton();
        }

        function updateUploadButton() {
            const uploadBtn = document.getElementById('uploadBtn');
            uploadBtn.disabled = selectedFiles.length === 0;
        }

        function clearSelection() {
            selectedFiles = [];
            document.getElementById('excelFileInput').value = '';
            updateFilePreview();
            updateUploadButton();
            hideResults();
        }

        async function startUpload() {
            if (selectedFiles.length === 0) {
                showAlert('يرجى اختيار ملفات أولاً', 'warning');
                return;
            }

            showProgress();
            hideResults();

            try {
                const formData = new FormData();

                // Add files - try both array and single format for compatibility
                if (selectedFiles.length === 1) {
                    // Single file - try both formats
                    formData.append('files', selectedFiles[0]);  // Single format
                    // Note: We'll let the backend handle this
                } else {
                    // Multiple files - use array format
                    selectedFiles.forEach((file, index) => {
                        formData.append('files[]', file);
                    });
                }

                // Debug info
                console.log('Files to upload:', selectedFiles.length);
                console.log('Using format:', selectedFiles.length === 1 ? 'single (files)' : 'array (files[])');

                // Add processing options
                formData.append('processing_mode', currentProcessingMode);

                if (currentProcessingMode === 'import-data') {
                    formData.append('enable_excel_import', '1');
                    formData.append('target_table', document.getElementById('targetTable').value);
                    formData.append('header_row', document.getElementById('headerRow').value === 'true' ? '1' : '0');
                    formData.append('skip_empty_rows', document.getElementById('skipEmptyRows').checked ? '1' : '0');
                    formData.append('validate_data', document.getElementById('validateData').checked ? '1' : '0');
                } else {
                    formData.append('enable_excel_import', '0');
                }

                // Add CSRF token
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                // Start upload
                const response = await fetch('{{ route("admin.file.excel.upload") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                hideProgress();

                if (result.success) {
                    showSuccessResult(result);
                } else {
                    showErrorResult(result);
                }

            } catch (error) {
                hideProgress();
                showErrorResult({
                    success: false,
                    message: 'حدث خطأ أثناء رفع الملفات: ' + error.message
                });
                console.error('Upload error:', error);
            }
        }

        function showProgress() {
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('actionButtons').style.display = 'none';

            // Simulate progress (replace with real progress tracking)
            let progress = 0;
            const progressBar = document.getElementById('uploadProgress');
            const progressDetails = document.getElementById('progressDetails');

            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress >= 95) {
                    progress = 95;
                    clearInterval(interval);
                }

                progressBar.style.width = progress + '%';
                progressBar.textContent = Math.round(progress) + '%';

                if (progress < 30) {
                    progressDetails.textContent = 'جاري رفع الملفات...';
                } else if (progress < 70) {
                    progressDetails.textContent = 'جاري معالجة البيانات...';
                } else {
                    progressDetails.textContent = 'جاري الانتهاء من المعالجة...';
                }
            }, 500);
        }

        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
            document.getElementById('actionButtons').style.display = 'block';

            // Reset progress
            const progressBar = document.getElementById('uploadProgress');
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
        }

        function showSuccessResult(result) {
            const container = document.getElementById('resultContainer');
            const content = document.getElementById('resultContent');

            container.className = 'result-container success-result';
            container.style.display = 'block';

            let html = `
                <h5>
                    <i class="fas fa-check-circle me-2"></i>
                    تم رفع ومعالجة الملفات بنجاح!
                </h5>
                <p class="mb-3">${result.message}</p>
            `;

            if (result.files && result.files.length > 0) {
                html += '<h6>تفاصيل الملفات المعالجة:</h6><ul class="list-unstyled">';
                result.files.forEach(file => {
                    const statusIcon = file.status === 'created' ? 'fa-plus-circle text-success' : 'fa-sync-alt text-info';
                    const statusText = file.status === 'created' ? 'ملف جديد' : 'تم تحديث ملف موجود';

                    html += `
                        <li class="mb-3 p-3 border rounded">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas ${statusIcon} me-2"></i>
                                <strong>${file.original_name}</strong>
                                <span class="badge bg-info ms-2">${statusText}</span>
                            </div>
                            <small class="text-muted d-block">
                                المسار: ${file.file_path}<br>
                                الحجم: ${(file.file_size / 1024).toFixed(2)} KB<br>
                                معرف الملف: ${file.file_id}
                                ${file.import_result ? '<br>تم استيراد البيانات' : ''}
                            </small>
                            ${file.message ? `<div class="alert alert-info alert-sm mt-2 mb-0"><small><i class="fas fa-info-circle me-1"></i>${file.message}</small></div>` : ''}
                        </li>
                    `;
                });
                html += '</ul>';
            }

            if (result.import_summary) {
                html += `
                    <div class="alert alert-info mt-3">
                        <h6>ملخص الاستيراد:</h6>
                        <p class="mb-0">
                            تم استيراد ${result.import_summary.imported_rows} صف إلى جدول ${result.import_summary.target_table}
                        </p>
                    </div>
                `;
            }

            content.innerHTML = html;
        }

        function showErrorResult(result) {
            const container = document.getElementById('resultContainer');
            const content = document.getElementById('resultContent');

            container.className = 'result-container error-result';
            container.style.display = 'block';

            content.innerHTML = `
                <h5>
                    <i class="fas fa-exclamation-circle me-2"></i>
                    حدث خطأ أثناء المعالجة
                </h5>
                <p>${result.message || 'خطأ غير معروف'}</p>
                ${result.errors ? '<ul>' + result.errors.map(error => `<li>${error}</li>`).join('') + '</ul>' : ''}
            `;
        }

        function hideResults() {
            document.getElementById('resultContainer').style.display = 'none';
        }

        function showAlert(message, type = 'info') {
            // Create and show a temporary alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.querySelector('.excel-gateway').insertBefore(alertDiv, document.querySelector('.excel-gateway').firstChild);

            // Auto dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Close modal function for integration with main interface
        function closeExcelGateway() {
            if (window.parent && window.parent !== window) {
                // If opened in modal/iframe
                window.parent.postMessage('closeExcelGateway', '*');
            } else {
                // If opened in new window
                window.close();
            }
        }

        // Export functions for external access
        window.ExcelGateway = {
            getSelectedFiles: () => selectedFiles,
            getProcessingMode: () => currentProcessingMode,
            clearSelection: clearSelection,
            close: closeExcelGateway
        };

        console.log('Excel Gateway initialized successfully');
    </script>
</body>
</html>
