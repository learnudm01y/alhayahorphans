@push('scriptsCode')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug: تأكيد تحميل JavaScript الخاص بـ Excel
        console.log('📊 Excel Management JavaScript loaded successfully');
        console.log('🔧 Current route:', window.location.pathname);
        console.log('🎯 Excel Mode: Active');

        // إعداد متغيرات عامة لـ Excel
        const searchInput = document.getElementById('kt_filemanager_search');
        const excelContentsModal = new bootstrap.Modal(document.getElementById('folderContentsModal'));
        let currentType = 'excel';
        let searchTimeout = null;

        // Debug: تأكيد إعداد المتغيرات
        console.log('📱 Excel Modal initialized:', !!excelContentsModal);

        // إعداد البحث المباشر للإكسل
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performExcelSearch(this.value);
                }, 500);
            });
        }

        // معالج النقر على مجلدات Excel
        document.addEventListener('click', function(e) {
            if (e.target.closest('.folder-view-btn') || e.target.closest('.folder-link')) {
                e.preventDefault();
                const folderName = e.target.closest('.folder-view-btn, .folder-link').getAttribute('data-folder');
                if (folderName) {
                    loadExcelFolderContents(folderName);
                }
            }

            // معالج معاينة Excel
            if (e.target.closest('.excel-preview')) {
                e.preventDefault();
                const excelSrc = e.target.closest('.excel-preview').getAttribute('data-src');
                const fileName = e.target.closest('.excel-preview').getAttribute('data-filename') || 'ملف Excel';
                showExcelModal(excelSrc, fileName);
            }
        });

        // دالة البحث في ملفات Excel
        function performExcelSearch(query) {
            if (query.length < 2) {
                location.reload();
                return;
            }

            showLoadingState();

            fetch(`{{ route('admin.folders.search') }}?search=${encodeURIComponent(query)}&type=excel`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateExcelTableContent(data.results);
                    } else {
                        showErrorMessage('حدث خطأ في البحث عن ملفات Excel');
                    }
                })
                .catch(error => {
                    console.error('Excel search error:', error);
                    showErrorMessage('حدث خطأ في الاتصال');
                })
                .finally(() => {
                    hideLoadingState();
                });
        }

        // تحميل محتويات مجلد Excel
        function loadExcelFolderContents(folderName) {
            console.log('📊 Loading Excel folder contents for:', folderName);

            const modalTitle = document.getElementById('modal-folder-name');
            const folderContents = document.getElementById('folder-contents');

            if (modalTitle) modalTitle.textContent = `مجلد Excel: ${folderName}`;
            if (folderContents) folderContents.innerHTML = '<div class="text-center py-5"><span class="spinner-border spinner-border-lg text-primary"></span><br><br>جاري تحميل ملفات Excel...</div>';

            excelContentsModal.show();

            fetch(`{{ route('admin.folders.contents') }}?folder=${encodeURIComponent(folderName)}&type=excel`)
                .then(response => response.json())
                .then(data => {
                    console.log('📊 Excel API Response:', data);
                    if (data.success) {
                        displayExcelContents(data.files, data.folder_name);
                    } else {
                        folderContents.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle fs-2x text-danger me-3"></i>حدث خطأ في جلب محتويات مجلد Excel</div>';
                    }
                })
                .catch(error => {
                    console.error('Excel folder contents error:', error);
                    folderContents.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle fs-2x text-danger me-3"></i>حدث خطأ في الاتصال</div>';
                });
        }

        // عرض محتويات مجلد Excel
        function displayExcelContents(files, folderName) {
            try {
                const folderContents = document.getElementById('folder-contents');

                // تشخيص البيانات الواردة
                console.log('📊 displayExcelContents called with:', {
                    filesCount: files ? files.length : 0,
                    folderName: folderName,
                    firstFile: files && files.length > 0 ? files[0] : null
                });

                if (!files || files.length === 0) {
                    folderContents.innerHTML = `
                        <div class="col-12 text-center">
                            <div class="d-flex flex-column align-items-center py-10">
                                <i class="fas fa-folder-open fs-4x text-muted mb-4"></i>
                                <h5 class="text-muted">مجلد Excel فارغ</h5>
                                <p class="text-muted">لا يحتوي هذا المجلد على أي ملفات Excel</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                let html = '';
                files.forEach(file => {
                    // تحديد نوع ملف Excel
                    const fileExtension = (file.file_extension || file.excel_type || 'xlsx').toLowerCase();
                    const isExcel = ['xls', 'xlsx', 'xlsm', 'csv'].includes(fileExtension);

                    console.log('Excel file analysis:', {
                        name: file.original_file_name,
                        extension: fileExtension,
                        isExcel: isExcel,
                        download_url: file.download_url
                    });

                    // إنشاء أيقونة Excel
                    const excelIcon = getExcelIcon(fileExtension);

                    // رابط التحميل
                    let downloadUrl = file.download_url || '';
                    if (downloadUrl && !downloadUrl.startsWith('http')) {
                        // إزالة storage/ المكررة من البداية
                        downloadUrl = downloadUrl.replace(/^\/?(storage\/)+/, '');
                        downloadUrl = downloadUrl.startsWith('/') ?
                            window.location.origin + '/storage/' + downloadUrl.substring(1) :
                            window.location.origin + '/storage/' + downloadUrl;
                    }

                    // تصميم كرت Excel محسن
                    html += `
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="card h-100 border-0 shadow-sm excel-card" style="transition: all 0.3s ease; border-radius: 15px; overflow: hidden;"
                                onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.15)';"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)';">

                                <div class="card-body p-0">
                                    <!-- منطقة أيقونة Excel -->
                                    <div class="d-flex align-items-center justify-content-center position-relative"
                                        style="height: 220px; background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%); border-radius: 15px; transition: all 0.3s ease;">
                                        <div class="text-center">
                                            ${excelIcon}
                                            <div class="mt-3">
                                                <span class="badge rounded-pill bg-success px-3 py-2" style="font-size: 12px;">
                                                    ${fileExtension.toUpperCase()}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- شارة Excel -->
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <span class="badge rounded-pill bg-success" style="padding: 6px 12px; font-size: 11px;">
                                                <i class="fas fa-file-excel me-1"></i>
                                                Excel
                                            </span>
                                        </div>

                                        <!-- زر معاينة مخفي -->
                                        <div class="position-absolute bottom-0 start-50 translate-middle-x mb-3" style="opacity: 0; transition: opacity 0.3s ease;">
                                            <button class="btn btn-success btn-sm rounded-pill px-3 py-1" style="backdrop-filter: blur(10px); background: rgba(40, 167, 69, 0.9) !important; box-shadow: 0 4px 15px rgba(0,0,0,0.2);"
                                                onclick="window.open('${downloadUrl}', '_blank')">
                                                <i class="fas fa-eye me-1"></i>
                                                فتح
                                            </button>
                                        </div>
                                    </div>

                                    <!-- معلومات الملف -->
                                    <div class="p-3">
                                        <h6 class="card-title mb-2 text-truncate fw-bold"
                                            title="${file.stored_file_name || file.original_file_name || file.file_name}"
                                            style="color: #2c3e50; font-size: 14px;">
                                            ${file.stored_file_name || file.original_file_name || file.file_name}
                                        </h6>

                                        <div class="text-muted small mb-3" style="line-height: 1.4;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span><i class="fas fa-hdd me-1 text-success"></i>الحجم:</span>
                                                <strong class="text-success">${file.formatted_size || 'غير محدد'}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span><i class="fas fa-calendar me-1 text-primary"></i>التاريخ:</span>
                                                <strong class="text-primary">${file.formatted_date || 'غير محدد'}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-excel me-1 text-info"></i>النوع:</span>
                                                <strong class="text-info">Excel ${fileExtension.toUpperCase()}</strong>
                                            </div>
                                        </div>

                                        <!-- أزرار العمليات -->
                                        <div class="d-flex justify-content-center gap-2">
                                            <button type="button"
                                                    class="btn btn-outline-success btn-sm flex-fill"
                                                    onclick="downloadExcelFile('${downloadUrl}', '${file.original_file_name || file.stored_file_name || file.file_name}')"
                                                    style="border-radius: 20px; font-weight: 600; transition: all 0.3s ease;"
                                                    onmouseover="this.style.backgroundColor='#198754'; this.style.color='white'; this.style.transform='translateY(-2px)';"
                                                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#198754'; this.style.transform='translateY(0)';">
                                                <i class="fas fa-download me-1"></i>
                                                تحميل
                                            </button>

                                            <button type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    onclick="previewExcelFile('${downloadUrl}', '${file.original_file_name || file.stored_file_name || file.file_name}')"
                                                    style="border-radius: 20px; font-weight: 600; transition: all 0.3s ease;"
                                                    onmouseover="this.style.backgroundColor='#0d6efd'; this.style.color='white'; this.style.transform='translateY(-2px)';"
                                                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#0d6efd'; this.style.transform='translateY(0)';">
                                                <i class="fas fa-eye me-1"></i>
                                                معاينة
                                            </button>

                                            <button type="button"
                                                    class="btn btn-outline-info btn-sm"
                                                    onclick="openExcelOnline('${downloadUrl}', '${file.original_file_name || file.stored_file_name || file.file_name}')"
                                                    style="border-radius: 20px; font-weight: 600; transition: all 0.3s ease;"
                                                    onmouseover="this.style.backgroundColor='#0dcaf0'; this.style.color='white'; this.style.transform='translateY(-2px)';"
                                                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#0dcaf0'; this.style.transform='translateY(0)';">
                                                <i class="fas fa-share me-1"></i>
                                                فتح أونلاين
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                // تحديث محتوى المودال
                if (folderContents) {
                    folderContents.innerHTML = html;
                } else {
                    console.error('folder-contents element not found');
                }

            } catch (error) {
                console.error('Error in displayExcelContents:', error);
                const folderContents = document.getElementById('folder-contents');
                if (folderContents) {
                    folderContents.innerHTML = '<p class="text-danger text-center py-5"><i class="fas fa-exclamation-triangle fs-3x text-danger mb-3"></i><br>حدث خطأ في تحميل ملفات Excel<br><small class="text-muted">يرجى المحاولة مرة أخرى</small></p>';
                }
            }
        }

        // دالة تحميل ملفات Excel
        function downloadExcelFile(fileUrl, fileName) {
            console.log('⬇️ Downloading Excel file:', fileName, 'from:', fileUrl);

            if (!fileUrl || fileUrl === '#') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'ملف غير متاح',
                        text: 'رابط تحميل ملف Excel غير متاح حالياً',
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
                return;
            }

            // إنشاء رابط تحميل مخفي
            const link = document.createElement('a');
            link.href = fileUrl;
            link.download = fileName;
            link.style.display = 'none';

            // إضافة الرابط للصفحة وتشغيله
            document.body.appendChild(link);
            link.click();

            // إزالة الرابط
            document.body.removeChild(link);

            // عرض رسالة نجاح
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'تم بدء التحميل',
                    text: `جاري تحميل ملف Excel: ${fileName}`,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                console.log('✅ Excel file download started:', fileName);
            }
        }

        // دالة معاينة ملف Excel
        function previewExcelFile(fileUrl, fileName) {
            console.log('👀 Previewing Excel file:', fileName, 'from:', fileUrl);

            if (!fileUrl || fileUrl === '#') {
                showErrorMessage('ملف Excel غير متاح للمعاينة');
                return;
            }

            // إنشاء مودال المعاينة
            const previewModalHtml = `
                <div class="modal fade" id="excelPreviewModal" tabindex="-1" aria-labelledby="excelPreviewModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="excelPreviewModalLabel">
                                    <i class="fas fa-file-excel me-2"></i>
                                    معاينة ملف Excel: ${fileName}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                            </div>
                            <div class="modal-body p-0" style="height: 80vh;">
                                <div class="d-flex flex-column h-100">
                                    <!-- خيارات المعاينة -->
                                    <div class="bg-light p-3 border-bottom">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <button class="btn btn-outline-primary w-100" onclick="loadOfficeOnlineViewer('${fileUrl}', '${fileName}')">
                                                    <i class="fab fa-microsoft me-2"></i>Microsoft Office Online
                                                </button>
                                            </div>
                                            <div class="col-md-4">
                                                <button class="btn btn-outline-success w-100" onclick="loadGoogleSheetsViewer('${fileUrl}', '${fileName}')">
                                                    <i class="fab fa-google me-2"></i>Google Sheets
                                                </button>
                                            </div>
                                            <div class="col-md-4">
                                                <button class="btn btn-outline-info w-100" onclick="loadSheetJSViewer('${fileUrl}', '${fileName}')">
                                                    <i class="fas fa-code me-2"></i>عارض مدمج
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- منطقة المعاينة -->
                                    <div class="flex-grow-1 position-relative">
                                        <div id="excelViewerContainer" class="h-100 w-100">
                                            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                                <div class="text-center">
                                                    <i class="fas fa-file-excel fs-3x mb-3"></i>
                                                    <h5>اختر طريقة المعاينة من الأعلى</h5>
                                                    <p>يمكنك اختيار إحدى الخدمات المتاحة لعرض محتويات ملف Excel</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-success" onclick="downloadExcelFile('${fileUrl}', '${fileName}')">
                                    <i class="fas fa-download me-2"></i>تحميل الملف
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // إزالة المودال السابق إن وجد
            const existingModal = document.getElementById('excelPreviewModal');
            if (existingModal) {
                existingModal.remove();
            }

            // إضافة المودال الجديد
            document.body.insertAdjacentHTML('beforeend', previewModalHtml);
            const previewModal = new bootstrap.Modal(document.getElementById('excelPreviewModal'));
            previewModal.show();
        }

        // دالة فتح ملف Excel في Microsoft Office Online
        function loadOfficeOnlineViewer(fileUrl, fileName) {
            const viewerContainer = document.getElementById('excelViewerContainer');
            const fullUrl = new URL(fileUrl, window.location.origin).href;
            const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(fullUrl)}`;

            viewerContainer.innerHTML = `
                <iframe src="${officeViewerUrl}"
                        class="w-100 h-100 border-0"
                        frameborder="0">
                </iframe>
            `;
        }

        // دالة فتح ملف Excel في Google Sheets
        function loadGoogleSheetsViewer(fileUrl, fileName) {
            const viewerContainer = document.getElementById('excelViewerContainer');
            const fullUrl = new URL(fileUrl, window.location.origin).href;
            const googleViewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(fullUrl)}&embedded=true`;

            viewerContainer.innerHTML = `
                <iframe src="${googleViewerUrl}"
                        class="w-100 h-100 border-0"
                        frameborder="0">
                </iframe>
            `;
        }

        // دالة عرض ملف Excel باستخدام SheetJS (عارض مدمج)
        function loadSheetJSViewer(fileUrl, fileName) {
            const viewerContainer = document.getElementById('excelViewerContainer');

            viewerContainer.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <h5>جاري تحميل وتحليل ملف Excel...</h5>
                        <p class="text-muted">📄 ${fileName}</p>
                    </div>
                </div>
            `;

            // التحقق من وجود مكتبة SheetJS أو تحميلها
            if (typeof XLSX === 'undefined') {
                console.log('📚 Loading SheetJS library...');
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
                script.onload = () => {
                    console.log('✅ SheetJS library loaded successfully');
                    processExcelFile(fileUrl, fileName, viewerContainer);
                };
                script.onerror = () => {
                    console.error('❌ Failed to load SheetJS library');
                    viewerContainer.innerHTML = `
                        <div class="d-flex align-items-center justify-content-center h-100 text-danger">
                            <div class="text-center">
                                <i class="fas fa-times-circle fs-3x mb-3"></i>
                                <h5>خطأ في تحميل المكتبة المطلوبة</h5>
                                <p>تعذر تحميل مكتبة معالجة ملفات Excel. تحقق من الاتصال بالإنترنت.</p>
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary btn-sm me-2" onclick="loadOfficeOnlineViewer('${fileUrl}', '${fileName}')">
                                        <i class="fab fa-microsoft me-1"></i>جرب Microsoft Office Online
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="loadGoogleSheetsViewer('${fileUrl}', '${fileName}')">
                                        <i class="ki-duotone ki-google me-1"></i>جرب Google Sheets
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                };
                document.head.appendChild(script);
            } else {
                console.log('✅ SheetJS library already loaded');
                processExcelFile(fileUrl, fileName, viewerContainer);
            }
        }

        // دالة معالجة ملف Excel
        function processExcelFile(fileUrl, fileName, container) {
            console.log('🔍 Processing Excel file:', fileName, 'URL:', fileUrl);

            fetch(fileUrl)
                .then(response => {
                    console.log('📡 Response status:', response.status);
                    console.log('📋 Response headers:', response.headers.get('content-type'));

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // التحقق من نوع المحتوى
                    const contentType = response.headers.get('content-type');
                    if (contentType && (contentType.includes('text/html') || contentType.includes('text/plain'))) {
                        throw new Error('الملف المسترجع ليس ملف Excel صالح (تم إرجاع HTML أو نص)');
                    }

                    return response.arrayBuffer();
                })
                .then(data => {
                    console.log('📊 Data received, size:', data.byteLength, 'bytes');

                    if (data.byteLength === 0) {
                        throw new Error('الملف فارغ أو غير موجود');
                    }

                    // إضافة معلومات إضافية لخيارات القراءة
                    const workbook = XLSX.read(data, {
                        type: 'array',
                        cellDates: true,
                        cellNF: false,
                        cellText: false
                    });

                    console.log('📈 Workbook loaded, sheets:', workbook.SheetNames);

                    if (!workbook.SheetNames || workbook.SheetNames.length === 0) {
                        throw new Error('لا توجد أوراق عمل في ملف Excel');
                    }

                    const sheetNames = workbook.SheetNames;

                    let tabsHtml = '<ul class="nav nav-tabs" id="excelTabs" role="tablist">';
                    let contentHtml = '<div class="tab-content" id="excelTabContent">';

                    sheetNames.forEach((sheetName, index) => {
                        const isActive = index === 0 ? 'active' : '';
                        tabsHtml += `
                            <li class="nav-item" role="presentation">
                                <button class="nav-link ${isActive}" id="tab-${index}" data-bs-toggle="tab"
                                        data-bs-target="#sheet-${index}" type="button" role="tab">
                                    📊 ${sheetName}
                                </button>
                            </li>
                        `;

                        try {
                            const worksheet = workbook.Sheets[sheetName];

                            // التحقق من وجود الورقة
                            if (!worksheet) {
                                console.warn('⚠️ Worksheet not found:', sheetName);
                                contentHtml += `
                                    <div class="tab-pane fade ${isActive ? 'show active' : ''}"
                                         id="sheet-${index}" role="tabpanel">
                                        <div class="p-3 text-center text-warning">
                                            <i class="ki-duotone ki-information fs-2x mb-3"></i>
                                            <h6>ورقة العمل "${sheetName}" فارغة أو تالفة</h6>
                                        </div>
                                    </div>
                                `;
                                return;
                            }

                            // التحقق من وجود بيانات في الورقة
                            const range = XLSX.utils.decode_range(worksheet['!ref'] || 'A1:A1');
                            const cellCount = (range.e.r - range.s.r + 1) * (range.e.c - range.s.c + 1);

                            if (cellCount === 1 && !worksheet['A1']) {
                                contentHtml += `
                                    <div class="tab-pane fade ${isActive ? 'show active' : ''}"
                                         id="sheet-${index}" role="tabpanel">
                                        <div class="p-3 text-center text-info">
                                            <i class="ki-duotone ki-file-sheet fs-2x mb-3"></i>
                                            <h6>ورقة العمل "${sheetName}" فارغة</h6>
                                            <p class="text-muted">لا توجد بيانات في هذه الورقة</p>
                                        </div>
                                    </div>
                                `;
                                return;
                            }

                            // تحويل الورقة إلى JSON أولاً ثم إلى HTML لتجنب مشاكل SheetJS
                            const jsonData = XLSX.utils.sheet_to_json(worksheet, {
                                header: 1,
                                defval: '',
                                raw: false
                            });

                            // إنشاء جدول HTML يدوياً من JSON
                            let tableHtml = '<table class="table table-striped table-hover table-sm table-bordered">';

                            if (jsonData.length > 0) {
                                // إضافة الصفوف
                                jsonData.forEach((row, rowIndex) => {
                                    if (row && row.length > 0) {
                                        const isHeaderRow = rowIndex === 0;
                                        const rowClass = isHeaderRow ? 'table-dark' : '';
                                        tableHtml += `<tr class="${rowClass}">`;

                                        row.forEach((cell, cellIndex) => {
                                            const cellValue = cell !== null && cell !== undefined ? String(cell) : '';
                                            const tag = isHeaderRow ? 'th' : 'td';
                                            tableHtml += `<${tag} class="text-center">${cellValue || ''}</${tag}>`;
                                        });

                                        tableHtml += '</tr>';
                                    }
                                });
                            } else {
                                tableHtml += '<tr><td class="text-center text-muted">لا توجد بيانات</td></tr>';
                            }

                            tableHtml += '</table>';

                            contentHtml += `
                                <div class="tab-pane fade ${isActive ? 'show active' : ''}"
                                     id="sheet-${index}" role="tabpanel">
                                    <div class="p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">📊 ورقة العمل: ${sheetName}</h6>
                                            <span class="badge bg-primary">${jsonData.length} صف</span>
                                        </div>
                                        <div style="max-height: 60vh; overflow: auto;">
                                            ${tableHtml}
                                        </div>
                                    </div>
                                </div>
                            `;

                            console.log(`✅ Sheet "${sheetName}" processed successfully with ${jsonData.length} rows`);

                        } catch (sheetError) {
                            console.error(`❌ Error processing sheet "${sheetName}":`, sheetError);
                            contentHtml += `
                                <div class="tab-pane fade ${isActive ? 'show active' : ''}"
                                     id="sheet-${index}" role="tabpanel">
                                    <div class="p-3 text-center text-danger">
                                        <i class="ki-duotone ki-cross-circle fs-2x mb-3"></i>
                                        <h6>خطأ في معالجة ورقة العمل "${sheetName}"</h6>
                                        <p class="text-muted">${sheetError.message}</p>
                                    </div>
                                </div>
                            `;
                        }
                    });

                    tabsHtml += '</ul>';
                    contentHtml += '</div>';

                    container.innerHTML = `
                        <div class="h-100 d-flex flex-column">
                            <div class="text-center mb-3">
                                <h5><i class="ki-duotone ki-file-sheet me-2"></i>محتويات ملف Excel</h5>
                                <p class="text-muted mb-0">📄 ${fileName} - ${sheetNames.length} ورقة عمل</p>
                            </div>
                            ${tabsHtml}
                            ${contentHtml}
                        </div>
                    `;

                    console.log('🎉 Excel file processed successfully!');
                })
                .catch(error => {
                    console.error('❌ Error processing Excel file:', error);

                    let errorMessage = 'تعذر معالجة الملف';
                    let errorDetails = '';

                    if (error.message.includes('Invalid HTML')) {
                        errorMessage = 'الملف المحدد ليس ملف Excel صالح';
                        errorDetails = 'يبدو أن الرابط يؤدي إلى صفحة HTML بدلاً من ملف Excel. تأكد من صحة رابط الملف.';
                    } else if (error.message.includes('HTTP error')) {
                        errorMessage = 'لا يمكن الوصول إلى الملف';
                        errorDetails = 'تأكد من أن الملف موجود ويمكن الوصول إليه.';
                    } else if (error.message.includes('فارغ')) {
                        errorMessage = 'الملف فارغ أو تالف';
                        errorDetails = 'الملف المحدد فارغ أو قد يكون تالفاً.';
                    } else {
                        errorDetails = error.message;
                    }

                    container.innerHTML = `
                        <div class="d-flex align-items-center justify-content-center h-100 text-danger">
                            <div class="text-center">
                                <i class="ki-duotone ki-cross-circle fs-3x mb-3"></i>
                                <h5>${errorMessage}</h5>
                                <p class="text-muted">${errorDetails}</p>
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary btn-sm me-2" onclick="loadOfficeOnlineViewer('${fileUrl}', '${fileName}')">
                                        <i class="ki-duotone ki-microsoft me-1"></i>جرب Microsoft Office Online
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="loadGoogleSheetsViewer('${fileUrl}', '${fileName}')">
                                        <i class="ki-duotone ki-google me-1"></i>جرب Google Sheets
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
        }

        // دالة فتح ملف Excel في خدمة خارجية
        function openExcelOnline(fileUrl, fileName) {
            console.log('🌐 Opening Excel file online:', fileName, 'from:', fileUrl);

            if (!fileUrl || fileUrl === '#') {
                showErrorMessage('ملف Excel غير متاح للفتح أونلاين');
                return;
            }

            // إنشاء مودال خيارات الفتح
            Swal.fire({
                title: 'اختر طريقة فتح الملف',
                text: `فتح ملف: ${fileName}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '📊 Microsoft Office Online',
                cancelButtonText: '📈 Google Sheets',
                showDenyButton: true,
                denyButtonText: 'إلغاء',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-success',
                    denyButton: 'btn btn-secondary'
                }
            }).then((result) => {
                const fullUrl = new URL(fileUrl, window.location.origin).href;

                if (result.isConfirmed) {
                    // Microsoft Office Online
                    const officeUrl = `https://view.officeapps.live.com/op/view.aspx?src=${encodeURIComponent(fullUrl)}`;
                    window.open(officeUrl, '_blank');
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    // Google Sheets/Drive
                    const googleUrl = `https://docs.google.com/gview?url=${encodeURIComponent(fullUrl)}`;
                    window.open(googleUrl, '_blank');
                }
            });
        }

        // دالة الحصول على أيقونة Excel
        function getExcelIcon(extension) {
            const ext = (extension || 'xlsx').toLowerCase();

            const icons = {
                'xlsx': '<i class="ki-duotone ki-file-sheet fs-3x text-success"><span class="path1"></span><span class="path2"></span></i>',
                'xls': '<i class="ki-duotone ki-file-sheet fs-3x text-success"><span class="path1"></span><span class="path2"></span></i>',
                'xlsm': '<i class="ki-duotone ki-file-sheet fs-3x text-warning"><span class="path1"></span><span class="path2"></span></i>',
                'csv': '<i class="ki-duotone ki-file-text fs-3x text-info"><span class="path1"></span><span class="path2"></span></i>',
                'default': '<i class="ki-duotone ki-file-sheet fs-3x text-success"><span class="path1"></span><span class="path2"></span></i>'
            };

            return icons[ext] || icons.default;
        }

        // دوال مساعدة للحالات والأخطاء
        function showLoadingState() {
            console.log('⏳ Showing loading state...');
            // يمكن إضافة منطق عرض التحميل هنا
        }

        function hideLoadingState() {
            console.log('✅ Hiding loading state...');
            // يمكن إضافة منطق إخفاء التحميل هنا
        }

        function showErrorMessage(message) {
            console.error('❌ Error:', message);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: message,
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        }

        function updateExcelTableContent(results) {
            console.log('🔄 تحديث محتويات جدول Excel مع نتائج البحث:', results);

            const container = document.getElementById('files_table_container');
            if (!container) {
                console.error('❌ Container غير موجود');
                return;
            }

            if (!results || results.data.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-file-excel fs-3x text-muted mb-3"></i>
                        <h4 class="text-muted">لم يتم العثور على ملفات Excel</h4>
                        <p class="text-muted">جرب كلمات بحث مختلفة</p>
                    </div>`;
                return;
            }

            let tableHtml = `
                <div class="table-responsive">
                    <table class="table table-row-dashed table-hover align-middle" id="kt_excel_manager_list">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-250px">اسم الملف</th>
                                <th class="min-w-150px">الحجم</th>
                                <th class="min-w-150px">رقم السجل</th>
                                <th class="min-w-150px">تاريخ الإنشاء</th>
                                <th class="text-end min-w-70px">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>`;

            results.data.forEach(file => {
                const fileSize = file.file_size ? (file.file_size / 1024).toFixed(1) + ' KB' : 'غير معروف';
                const recordNumber = file.record_number || 'غير محدد';
                const fileName = file.original_file_name || file.stored_file_name || file.file_name;

                // التأكد من وجود download_url وتنسيقه بشكل صحيح
                let downloadUrl = file.download_url || file.file_path || '';
                if (downloadUrl && !downloadUrl.startsWith('http')) {
                    // إزالة storage/ المكررة من البداية
                    downloadUrl = downloadUrl.replace(/^\/?(storage\/)+/, '');
                    downloadUrl = downloadUrl.startsWith('/') ?
                        window.location.origin + '/storage/' + downloadUrl.substring(1) :
                        window.location.origin + '/storage/' + downloadUrl;
                }

                console.log('📊 Excel file data:', {
                    name: fileName,
                    original_url: file.download_url,
                    processed_url: downloadUrl,
                    record_number: recordNumber
                });

                tableHtml += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-excel text-success fs-2 me-3"></i>
                                <div>
                                    <span class="text-gray-800 fw-bold">${fileName}</span>
                                    <div class="text-muted fs-7">${file.file_path || ''}</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-gray-400">${fileSize}</td>
                        <td class="text-gray-400">
                            <span class="badge badge-light-primary">${recordNumber}</span>
                        </td>
                        <td class="text-gray-400">${file.created_at || file.updated_at || ''}</td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-success excel-view-search-btn"
                                        data-excel-src="${downloadUrl}"
                                        data-title="${fileName}">
                                    <i class="fas fa-eye"></i> عرض
                                </button>
                                <button class="btn btn-sm btn-primary excel-download-btn"
                                        data-url="${downloadUrl}"
                                        data-filename="${fileName}">
                                    <i class="fas fa-download"></i> تحميل
                                </button>
                            </div>
                        </td>
                    </tr>`;
            });

            tableHtml += `</tbody></table></div>`;
            container.innerHTML = tableHtml;

            // إعادة ربط Event Listeners للعناصر الجديدة
            attachExcelEventListenersToSearchResults();

            console.log('✅ تم تحديث جدول Excel وربط الأحداث بنجاح');
        }

        // دالة ربط الأحداث بنتائج البحث Excel
        function attachExcelEventListenersToSearchResults() {
            console.log('🔗 ربط الأحداث بنتائج البحث Excel...');

            // معاينة ملفات Excel
            document.querySelectorAll('.excel-view-search-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const excelSrc = this.getAttribute('data-excel-src');
                    const title = this.getAttribute('data-title');
                    console.log('📊 عرض Excel من نتائج البحث:', excelSrc);
                    showExcelModal(excelSrc, title);
                });
            });

            // تحميل ملفات Excel
            document.querySelectorAll('.excel-download-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fileUrl = this.getAttribute('data-url');
                    const fileName = this.getAttribute('data-filename');
                    console.log('📥 تحميل Excel من نتائج البحث:', fileName);

                    // إنشاء رابط تحميل
                    const link = document.createElement('a');
                    link.href = fileUrl;
                    link.download = fileName;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            });

            console.log('✅ تم ربط جميع أحداث Excel بنجاح');
        }

        // دالة عرض Excel Modal
        function showExcelModal(excelSrc, fileName = 'ملف Excel') {
            console.log('📊 عرض Excel في Modal:', excelSrc);

            if (!excelSrc) {
                console.error('❌ مسار Excel غير صحيح');
                showErrorMessage('مسار الملف غير صحيح');
                return;
            }

            // استخدام دالة المعاينة الموجودة
            if (typeof previewExcelFile === 'function') {
                previewExcelFile(excelSrc, fileName);
            } else {
                // fallback - فتح في نافذة جديدة
                console.log('📊 فتح Excel في نافذة جديدة');
                window.open(excelSrc, '_blank');
            }
        }

        // دوال مساعدة للحالات والأخطاء
        function showLoadingState() {
            console.log('⏳ Showing loading state...');
            // يمكن إضافة منطق عرض التحميل هنا
        }

        function hideLoadingState() {
            console.log('✅ Hiding loading state...');
            // يمكن إضافة منطق إخفاء التحميل هنا
        }

        function showErrorMessage(message) {
            console.error('❌ Error:', message);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: message,
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        }

        // إتاحة الدوال عالمياً
        window.loadExcelFolderContents = loadExcelFolderContents;
        window.displayExcelContents = displayExcelContents;
        window.downloadExcelFile = downloadExcelFile;
        window.previewExcelFile = previewExcelFile;
        window.openExcelOnline = openExcelOnline;
        window.loadOfficeOnlineViewer = loadOfficeOnlineViewer;
        window.loadGoogleSheetsViewer = loadGoogleSheetsViewer;
        window.loadSheetJSViewer = loadSheetJSViewer;
        window.processExcelFile = processExcelFile;
        window.getExcelIcon = getExcelIcon;

        // تشخيص الدوال
        setTimeout(() => {
            console.log('🚀 Excel functions ready:', {
                loadExcelFolderContents: typeof window.loadExcelFolderContents,
                displayExcelContents: typeof window.displayExcelContents,
                downloadExcelFile: typeof window.downloadExcelFile,
                previewExcelFile: typeof window.previewExcelFile,
                openExcelOnline: typeof window.openExcelOnline,
                getExcelIcon: typeof window.getExcelIcon
            });
        }, 500);

        console.log('🎯 نظام إدارة ملفات Excel جاهز للاستخدام');
    });
</script>

<style>
/* تحسينات CSS مخصصة لملفات Excel */
.excel-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.excel-card:hover {
    transform: translateY(-8px) !important;
    box-shadow: 0 15px 35px rgba(40, 167, 69, 0.2) !important;
}

.excel-card .position-relative:hover .position-absolute {
    opacity: 1 !important;
}

.excel-card .btn-outline-success:hover,
.excel-card .btn-outline-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

/* تحسين عرض الجداول على الأجهزة المحمولة */
@media (max-width: 768px) {
    .excel-card .card-title {
        font-size: 12px;
    }

    .excel-card .small {
        font-size: 0.75rem;
    }
}

/* تحسين النوافذ المنبثقة لـ Excel */
.modal-xl {
    max-width: 95vw;
}

.modal-body {
    max-height: 80vh;
    overflow-y: auto;
}

/* تحسين أشرطة التقدم */
.spinner-border-lg {
    width: 3rem;
    height: 3rem;
}

/* تحسين الشارات */
.badge {
    font-size: 0.75em;
}

.badge-sm {
    font-size: 0.65em;
    padding: 0.25em 0.5em;
}
</style>
@endpush
