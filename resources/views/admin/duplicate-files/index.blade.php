@extends('layouts.admin')

@section('title', 'إدارة الملفات المكررة')

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">
                                <i class="fas fa-clone me-2"></i>
                                إدارة الملفات المكررة
                            </h4>
                            <small class="opacity-75">عرض وإدارة جميع الملفات المكررة المحفوظة في النظام</small>
                        </div>
                    </div>
                    <div class="d-flex gap-2 -2">
                        <button class="btn btn-primary btn-sm" id="downloadAllBtn">
                            <i class="fas fa-download me-1"></i>
                            تنزيل الكل
                        </button>
                        <button class="btn btn-info btn-sm" id="downloadSelectedBtn" style="display: none;">
                            <i class="fas fa-download me-1"></i>
                            تنزيل المحدد
                        </button>
                        <button class="btn btn-outline- btn-sm text-white" id="refreshBtn">
                            <i class="fas fa-sync-alt me-1 text-white"></i>
                            تحديث
                        </button>
                        <button class="btn btn-warning btn-sm" id="bulkDeleteBtn" style="display: none;">
                            <i class="fas fa-trash-alt me-1"></i>
                            حذف المحدد
                        </button>
                        <button class="btn btn-danger btn-sm" id="deleteAllBtn">
                            <i class="fas fa-trash-alt me-1"></i>
                            حذف الكل
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- Real Database Statistics Card -->
        <div class="col-12 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="fas fa-database me-2"></i>
                                الإحصائيات الحقيقية من قاعدة البيانات
                            </h5>
                            <small class="opacity-75">العدد الفعلي للملفات المكررة المحفوظة في النظام</small>
                        </div>
                        <button class="btn btn-outline-light btn-sm" id="refreshRealStatsBtn">
                            <i class="fas fa-sync-alt me-1"></i>
                            تحديث
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-lg-2 col-md-4 mb-3">
                            <div class="border-end">
                                <h3 class="text-success mb-1" id="realTotalFiles">-</h3>
                                <small class="text-muted">إجمالي الملفات</small>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-3">
                            <div class="border-end">
                                <h3 class="text-primary mb-1" id="realActiveFiles">-</h3>
                                <small class="text-muted">الملفات النشطة</small>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-3">
                            <div class="border-end">
                                <h3 class="text-warning mb-1" id="realExpiredFiles">-</h3>
                                <small class="text-muted">الملفات المنتهية</small>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-3">
                            <div class="border-end">
                                <h3 class="text-info mb-1" id="realTotalImages">-</h3>
                                <small class="text-muted">الصور</small>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-3">
                            <div class="border-end">
                                <h3 class="text-secondary mb-1" id="realTotalDocuments">-</h3>
                                <small class="text-muted">المستندات</small>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-4 mb-3">
                            <h3 class="text-dark mb-1" id="realTotalSize">-</h3>
                            <small class="text-muted">المساحة المستخدمة</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">البحث في أسماء الملفات</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="searchInput" placeholder="اكتب اسم الملف...">
                                <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">نوع الملف</label>
                            <select class="form-select" id="fileTypeFilter">
                                <option value="">جميع الأنواع</option>
                                <option value="image">الصور</option>
                                <option value="document">المستندات</option>
                                <option value="video">الفيديو</option>
                                <option value="audio">الصوت</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">حالة الملف</label>
                            <select class="form-select" id="statusFilter">
                                <option value="">جميع الحالات</option>
                                <option value="active">نشط</option>
                                <option value="expired">منتهي الصلاحية</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">عدد النتائج</label>
                            <select class="form-select" id="perPageSelect">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Files Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <!-- Loading State -->
                    <div id="loadingState" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="text-muted mt-3">جاري تحميل الملفات...</p>
                    </div>

                    <!-- Table Container -->
                    <div id="tableContainer" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th style="width: 80px;">معاينة</th>
                                        <th>اسم الملف</th>
                                        <th>الملف الأصلي</th>
                                        <th>المسار</th>
                                        <th>النوع</th>
                                        <th>الحجم</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>الحالة</th>
                                        <th style="width: 150px;">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="filesTableBody">
                                    <!-- يتم ملؤها ديناميكياً -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div id="emptyState" class="text-center py-5" style="display: none;">
                        <div class="text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3"></i>
                            <h5>لا توجد ملفات مكررة</h5>
                            <p>لم يتم العثور على أي ملفات مكررة في النظام.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted" id="paginationInfo">
                    <!-- معلومات التقسيم -->
                </div>
                <nav aria-label="صفحات الملفات المكررة">
                    <ul class="pagination mb-0" id="paginationLinks">
                        <!-- روابط التقسيم -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- File Preview Modal -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title d-flex align-items-center" id="filePreviewModalLabel">
                    <i class="fas fa-eye me-2 text-primary"></i>
                    معاينة الملف
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body p-4" id="filePreviewContent" style="min-height: 400px;">
                <!-- محتوى المعاينة -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                    <p class="text-muted mt-3">جاري تحميل المعاينة...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <div class="me-auto">
                    <small class="text-muted" id="filePreviewInfo">
                        <!-- معلومات إضافية عن الملف -->
                    </small>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    إغلاق
                </button>
                <a href="#" class="btn btn-primary" id="downloadFromPreview" target="_blank">
                    <i class="fas fa-download me-1"></i>
                    تحميل الملف
                </a>
            </div>
        </div>
    </div>
</div>

<!-- File Details Modal -->
<div class="modal fade" id="fileDetailsModal" tabindex="-1" aria-labelledby="fileDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fileDetailsModalLabel">تفاصيل الملف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body" id="fileDetailsContent">
                <!-- تفاصيل الملف -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
        <link rel="stylesheet" href="{{ asset('css/duplicate-files-enhanced.css') }}">
        <style>
        .preview-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .file-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .file-size {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .file-path {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            margin: 0 0.125rem;
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }

        .bg-gradient-success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* تحسينات المعاينة */
        .image-preview-container img {
            transition: transform 0.2s ease;
            cursor: zoom-in;
        }

        .image-preview-container img:hover {
            transform: scale(1.02);
        }

        .video-preview-container video {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .audio-preview-container audio {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .pdf-preview-container iframe {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .file-preview-container {
            padding: 2rem;
        }

        .modal-xl .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }

        /* تحسين عرض المعلومات */
        .preview-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .preview-info-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
        }
        </style>
@endpush

@push('scriptsCode')
<!-- تضمين مُصلح مسارات الملفات -->
<script src="{{ asset('js/file-path-fixer.js') }}"></script>

<script>
        class DuplicateFilesManager {
            constructor() {
                console.log('🚀 إنشاء DuplicateFilesManager');

                this.currentPage = 1;
                this.perPage = 25;
                this.searchTerm = '';
                this.fileType = '';
                this.status = '';
                this.selectedFiles = new Set();

                this.init();
                this.loadFiles();

                console.log('📊 تحميل الإحصائيات الحقيقية...');
                this.loadRealStatistics();
            }

            init() {
                // Initialize event listeners
                this.bindEvents();

                // Set CSRF token for all AJAX requests
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
            }

            bindEvents() {
                // Search
                $('#searchBtn').on('click', () => this.search());
                $('#searchInput').on('keypress', (e) => {
                    if (e.which === 13) this.search();
                });

                // Filters
                $('#fileTypeFilter, #statusFilter').on('change', () => this.applyFilters());
                $('#perPageSelect').on('change', () => this.changePerPage());

                // Refresh
                $('#refreshBtn').on('click', () => this.refresh());

                // Refresh real statistics
                $('#refreshRealStatsBtn').on('click', () => this.loadRealStatistics());

                // Select all checkbox
                $('#selectAll').on('change', (e) => this.toggleSelectAll(e.target.checked));

                // Bulk delete
                $('#bulkDeleteBtn').on('click', () => this.bulkDelete());

                // Delete all
                $('#deleteAllBtn').on('click', () => this.deleteAll());
            }

            async loadFiles() {
                try {
                    this.showLoading();

                    const params = new URLSearchParams({
                        page: this.currentPage,
                        per_page: this.perPage,
                        search: this.searchTerm,
                        file_type: this.fileType,
                        status: this.status
                    });

                    const response = await fetch(`{{ route('public.duplicate.files.paginated') }}?${params}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        // إصلاح مسارات الملفات قبل العرض
                        if (window.FilePathFixer && data.data.files) {
                            data.data.files = window.FilePathFixer.fixFilePathsInArray(data.data.files);
                        }

                        this.renderFiles(data.data.files);
                        this.renderPagination(data.data.pagination);
                        this.updateStatistics(data.data.statistics);
                        this.hideLoading();
                    } else {
                        throw new Error(data.message || 'فشل في تحميل الملفات');
                    }
                } catch (error) {
                    console.error('Error loading files:', error);
                    this.showError('حدث خطأ أثناء تحميل الملفات: ' + error.message);
                    this.hideLoading();
                }
            }

            renderFiles(files) {
                const tbody = $('#filesTableBody');
                tbody.empty();

                if (files.length === 0) {
                    this.showEmptyState();
                    return;
                }

                files.forEach(file => {
                    const row = this.createFileRow(file);
                    tbody.append(row);
                });

                $('#tableContainer').show();
                $('#emptyState').hide();

                // إصلاح مسارات الصور في DOM بعد إضافة العناصر
                setTimeout(() => {
                    if (window.FilePathFixer) {
                        window.FilePathFixer.fixImagePathsInDOM('#filesTableBody');
                    }
                }, 100);
            }

            createFileRow(file) {
                const isImage = this.isImageFile(file.mime_type);
                const fileIcon = this.getFileIcon(file.mime_type);
                const fileSize = this.formatFileSize(file.file_size);
                const createdAt = new Date(file.created_at).toLocaleString('ar-SA');
                const isExpired = new Date(file.expires_at) < new Date();

                const statusClass = isExpired ? 'bg-danger' : 'bg-success';
                const statusText = isExpired ? 'منتهي الصلاحية' : 'نشط';

                return $(`
                    <tr data-file-id="${file.id}" class="${isExpired ? 'table-warning' : ''}">
                        <td>
                            <input type="checkbox" class="form-check-input file-checkbox" value="${file.id}">
                        </td>
                        <td>
                            ${isImage ?
                                `<img src="${file.preview_url || this.getValidImagePath(file)}" class="preview-thumbnail" alt="معاينة" onclick="duplicateFilesManager.previewFile(${file.id})">` :
                                `<div class="file-icon" onclick="duplicateFilesManager.showFileDetails(${file.id})">
                                    <i class="${fileIcon} fa-lg text-muted"></i>
                                </div>`
                            }
                        </td>
                        <td>
                            <div>
                                <strong>${file.duplicate_name || file.original_name}</strong>
                                <br>
                                <small class="text-muted">${file.mime_type}</small>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted">${file.original_name}</small>
                        </td>
                        <td>
                            <span class="file-path" title="${file.temp_path}">
                                ${file.original_folder || 'غير محدد'}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-secondary">${this.getFileTypeLabel(file.mime_type)}</span>
                        </td>
                        <td>
                            <span class="file-size">${fileSize}</span>
                        </td>
                        <td>
                            <small>${createdAt}</small>
                        </td>
                        <td>
                            <span class="badge status-badge ${statusClass}">${statusText}</span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                ${isImage ?
                                    `<button class="btn btn-outline-info btn-sm" onclick="duplicateFilesManager.previewFile(${file.id})" title="معاينة">
                                        <i class="fas fa-eye"></i>
                                    </button>` :
                                    `<button class="btn btn-outline-info btn-sm" onclick="duplicateFilesManager.showFileDetails(${file.id})" title="تفاصيل">
                                        <i class="fas fa-info"></i>
                                    </button>`
                                }
                                <button class="btn btn-outline-primary btn-sm" onclick="duplicateFilesManager.downloadFile(${file.id})" title="تحميل">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="duplicateFilesManager.deleteFile(${file.id})" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `);
            }

            renderPagination(pagination) {
                const paginationInfo = $('#paginationInfo');
                const paginationLinks = $('#paginationLinks');

                // Update info
                paginationInfo.text(`عرض ${pagination.from} إلى ${pagination.to} من أصل ${pagination.total} ملف`);

                // Build pagination links
                paginationLinks.empty();

                // Previous button
                if (pagination.current_page > 1) {
                    const prevBtn = $(`
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" data-page="${pagination.current_page - 1}" aria-label="السابق">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    `);
                    paginationLinks.append(prevBtn);
                }

                // Page numbers
                for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.last_page, pagination.current_page + 2); i++) {
                    const isActive = i === pagination.current_page;
                    const pageBtn = $(`
                        <li class="page-item ${isActive ? 'active' : ''}">
                            <a class="page-link" href="javascript:void(0)" data-page="${i}">${i}</a>
                        </li>
                    `);
                    paginationLinks.append(pageBtn);
                }

                // Next button
                if (pagination.current_page < pagination.last_page) {
                    const nextBtn = $(`
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" data-page="${pagination.current_page + 1}" aria-label="التالي">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    `);
                    paginationLinks.append(nextBtn);
                }

                // Add click event handlers
                paginationLinks.find('a.page-link').on('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const page = parseInt($(e.currentTarget).data('page'));
                    if (page && page !== pagination.current_page) {
                        this.goToPage(page);
                    }
                    return false;
                });
            }

            updateStatistics(statistics) {
                $('#totalFiles').text(statistics.total_files || 0);
                $('#totalImages').text(statistics.total_images || 0);
                $('#totalDocuments').text(statistics.total_documents || 0);
                $('#totalSize').text(this.formatFileSize(statistics.total_size || 0));
            }

            async loadRealStatistics() {
                try {
                    console.log('🔄 تحميل الإحصائيات الحقيقية من قاعدة البيانات...');

                    // إظهار loading state
                    $('#realTotalFiles, #realActiveFiles, #realExpiredFiles, #realTotalImages, #realTotalDocuments, #realTotalSize').text('...');

                    const response = await fetch(`{{ route('public.duplicate.files.real.statistics') }}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    console.log('📡 استجابة الخادم:', response.status, response.statusText);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('✅ البيانات المستلمة:', data);

                    if (data.success) {
                        console.log('📊 تحديث الإحصائيات الحقيقية:', data.data);
                        this.updateRealStatistics(data.data);
                    } else {
                        throw new Error(data.message || 'فشل في تحميل الإحصائيات الحقيقية');
                    }
                } catch (error) {
                    console.error('❌ خطأ في تحميل الإحصائيات الحقيقية:', error);
                    this.showError('حدث خطأ أثناء تحميل الإحصائيات الحقيقية: ' + error.message);

                    // إظهار خطأ في العناصر
                    $('#realTotalFiles, #realActiveFiles, #realExpiredFiles, #realTotalImages, #realTotalDocuments, #realTotalSize').text('خطأ');
                }
            }

            updateRealStatistics(statistics) {
                console.log('🔄 تحديث عناصر الإحصائيات الحقيقية:', statistics);

                $('#realTotalFiles').text(statistics.total_files || 0);
                $('#realActiveFiles').text(statistics.active_files || 0);
                $('#realExpiredFiles').text(statistics.expired_files || 0);
                $('#realTotalImages').text(statistics.total_images || 0);
                $('#realTotalDocuments').text(statistics.total_documents || 0);
                $('#realTotalSize').text(this.formatFileSize(statistics.total_size || 0));

                console.log('✅ تم تحديث جميع عناصر الإحصائيات الحقيقية');
            }

            // File operations
            async previewFile(fileId) {
                try {
                    console.log('🔍 فتح معاينة الملف:', fileId);

                    const response = await fetch(`{{ route('public.duplicate.files.preview', '') }}/${fileId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        let file = data.data;

                        console.log('📁 بيانات الملف:', file);

                        $('#filePreviewModalLabel').text(`معاينة: ${file.original_name}`);
                        $('#downloadFromPreview').attr('href', `{{ route('public.duplicate.files.download', '') }}/${fileId}`);

                        // عرض المحتوى حسب نوع الملف
                        this.renderPreviewContent(file);

                        $('#filePreviewModal').modal('show');
                    } else {
                        throw new Error(data.message || 'فشل في تحميل الملف للمعاينة');
                    }
                } catch (error) {
                    console.error('Error previewing file:', error);
                    this.showError('حدث خطأ أثناء معاينة الملف: ' + error.message);
                }
            }

            renderPreviewContent(file) {
                const mimeType = file.mime_type;
                let content = '';

                // تحديث معلومات الملف في الـ footer
                const createdAt = new Date(file.created_at).toLocaleDateString('ar-SA');
                $('#filePreviewInfo').html(`
                    <i class="fas fa-calendar me-1"></i>
                    تاريخ الإنشاء: ${createdAt} |
                    <i class="fas fa-hdd me-1"></i>
                    الحجم: ${this.formatFileSize(file.file_size)} |
                    <i class="fas fa-tag me-1"></i>
                    النوع: ${this.getFileTypeLabel(mimeType)}
                `);

                if (this.isImageFile(mimeType)) {
                    // معاينة الصور
                    const imageSrc = file.preview_url || this.getValidImagePath(file);
                    content = `
                        <div class="image-preview-container text-center">
                            <div class="mb-3">
                                <img src="${imageSrc}" class="img-fluid rounded shadow" alt="معاينة الصورة"
                                     style="max-height: 500px; max-width: 100%;"
                                     onclick="this.style.transform = this.style.transform ? '' : 'scale(1.5)'">
                            </div>
                            <div class="preview-info-grid">
                                <div class="preview-info-item">
                                    <i class="fas fa-expand-arrows-alt text-primary mb-2"></i>
                                    <div><strong>الأبعاد</strong></div>
                                    <div class="text-muted">${file.width || 'غير محدد'} × ${file.height || 'غير محدد'}</div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-palette text-info mb-2"></i>
                                    <div><strong>نوع الصورة</strong></div>
                                    <div class="text-muted">${mimeType.split('/')[1].toUpperCase()}</div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-weight-hanging text-warning mb-2"></i>
                                    <div><strong>الحجم</strong></div>
                                    <div class="text-muted">${this.formatFileSize(file.file_size)}</div>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-mouse-pointer me-2"></i>
                                انقر على الصورة للتكبير/التصغير
                            </div>
                        </div>
                    `;
                } else if (mimeType && mimeType.startsWith('video/')) {
                    // معاينة الفيديو
                    const videoSrc = this.getValidImagePath(file);
                    content = `
                        <div class="video-preview-container">
                            <div class="text-center mb-3">
                                <h5 class="text-primary">
                                    <i class="fas fa-play-circle me-2"></i>
                                    معاينة الفيديو
                                </h5>
                            </div>
                            <video controls class="w-100 rounded shadow" style="max-height: 400px;">
                                <source src="${videoSrc}" type="${mimeType}">
                                متصفحك لا يدعم عرض الفيديو.
                            </video>
                            <div class="preview-info-grid mt-3">
                                <div class="preview-info-item">
                                    <i class="fas fa-video text-danger mb-2"></i>
                                    <div><strong>نوع الفيديو</strong></div>
                                    <div class="text-muted">${mimeType.split('/')[1].toUpperCase()}</div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-clock text-primary mb-2"></i>
                                    <div><strong>المدة</strong></div>
                                    <div class="text-muted">سيظهر عند التشغيل</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (mimeType && mimeType.startsWith('audio/')) {
                    // معاينة الصوت
                    const audioSrc = this.getValidImagePath(file);
                    content = `
                        <div class="audio-preview-container text-center">
                            <div class="mb-4">
                                <i class="fas fa-music fa-5x text-primary mb-3"></i>
                                <h4>${file.original_name}</h4>
                                <p class="text-muted">ملف صوتي</p>
                            </div>
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <audio controls class="w-100 mb-3">
                                        <source src="${audioSrc}" type="${mimeType}">
                                        متصفحك لا يدعم تشغيل الصوت.
                                    </audio>
                                </div>
                            </div>
                            <div class="preview-info-grid">
                                <div class="preview-info-item">
                                    <i class="fas fa-file-audio text-success mb-2"></i>
                                    <div><strong>نوع الملف</strong></div>
                                    <div class="text-muted">${mimeType.split('/')[1].toUpperCase()}</div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-volume-up text-info mb-2"></i>
                                    <div><strong>جودة الصوت</strong></div>
                                    <div class="text-muted">حسب الملف الأصلي</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (mimeType && mimeType.includes('pdf')) {
                    // معاينة PDF
                    const pdfSrc = this.getValidImagePath(file);
                    content = `
                        <div class="pdf-preview-container">
                            <div class="text-center mb-3">
                                <h5 class="text-danger">
                                    <i class="fas fa-file-pdf me-2"></i>
                                    مستند PDF
                                </h5>
                                <p class="text-muted">${file.original_name}</p>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                        <a href="${pdfSrc}" target="_blank" class="btn btn-outline-primary">
                                            <i class="fas fa-external-link-alt me-2"></i>
                                            فتح في نافذة جديدة
                                        </a>
                                        <button class="btn btn-outline-secondary" onclick="document.getElementById('pdfFrame').style.display = document.getElementById('pdfFrame').style.display === 'none' ? 'block' : 'none'">
                                            <i class="fas fa-eye me-2"></i>
                                            إظهار/إخفاء المعاينة
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <iframe id="pdfFrame" src="${pdfSrc}" width="100%" height="500px" class="border rounded shadow"></iframe>
                            <div class="preview-info-grid mt-3">
                                <div class="preview-info-item">
                                    <i class="fas fa-file-pdf text-danger mb-2"></i>
                                    <div><strong>نوع المستند</strong></div>
                                    <div class="text-muted">PDF</div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-download text-success mb-2"></i>
                                    <div><strong>التحميل</strong></div>
                                    <div class="text-muted">متاح للتحميل</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (mimeType && (mimeType.includes('text/') || mimeType.includes('javascript') || mimeType.includes('json'))) {
                    // معاينة الملفات النصية
                    content = `
                        <div class="text-preview-container text-center">
                            <div class="mb-4">
                                <i class="fas fa-file-code fa-5x text-info mb-3"></i>
                                <h4>${file.original_name}</h4>
                                <p class="text-muted">ملف نصي</p>
                            </div>
                            <div class="preview-info-grid">
                                <div class="preview-info-item">
                                    <i class="fas fa-code text-primary mb-2"></i>
                                    <div><strong>نوع الملف</strong></div>
                                    <div class="text-muted">${mimeType}</div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-edit text-warning mb-2"></i>
                                    <div><strong>قابل للتحرير</strong></div>
                                    <div class="text-muted">في محرر النصوص</div>
                                </div>
                            </div>
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle me-2"></i>
                                لعرض محتوى الملف النصي، يرجى تحميله وفتحه في محرر النصوص المناسب
                            </div>
                        </div>
                    `;
                } else {
                    // معاينة افتراضية للملفات الأخرى
                    const icon = this.getFileIcon(mimeType);
                    const typeLabel = this.getFileTypeLabel(mimeType);
                    content = `
                        <div class="file-preview-container text-center">
                            <div class="mb-4">
                                <i class="${icon} fa-5x text-secondary mb-3"></i>
                                <h4>${file.original_name}</h4>
                                <p class="text-muted">ملف ${typeLabel}</p>
                            </div>
                            <div class="preview-info-grid">
                                <div class="preview-info-item">
                                    <i class="fas fa-tag text-primary mb-2"></i>
                                    <div><strong>نوع الملف</strong></div>
                                    <div class="text-muted">${typeLabel}</div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-code text-info mb-2"></i>
                                    <div><strong>MIME Type</strong></div>
                                    <div class="text-muted"><code class="small">${mimeType}</code></div>
                                </div>
                                <div class="preview-info-item">
                                    <i class="fas fa-weight-hanging text-warning mb-2"></i>
                                    <div><strong>الحجم</strong></div>
                                    <div class="text-muted">${this.formatFileSize(file.file_size)}</div>
                                </div>
                            </div>
                            <div class="alert alert-primary mt-4">
                                <i class="fas fa-download me-2"></i>
                                <strong>للمعاينة الكاملة:</strong> قم بتحميل الملف وفتحه في التطبيق المناسب
                            </div>
                        </div>
                    `;
                }

                $('#filePreviewContent').html(content);
            }

            async showFileDetails(fileId) {
                try {
                    const response = await fetch(`{{ route('public.duplicate.files.view', '') }}/${fileId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        const file = data.data;
                        const createdAt = new Date(file.created_at).toLocaleString('ar-SA');
                        const expiresAt = new Date(file.expires_at).toLocaleString('ar-SA');
                        const isExpired = new Date(file.expires_at) < new Date();

                        $('#fileDetailsModalLabel').text(`تفاصيل: ${file.original_name}`);
                        $('#fileDetailsContent').html(`
                            <div class="row">
                                <div class="col-sm-4"><strong>الاسم الأصلي:</strong></div>
                                <div class="col-sm-8">${file.original_name}</div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>الاسم المكرر:</strong></div>
                                <div class="col-sm-8">${file.duplicate_name}</div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>نوع الملف:</strong></div>
                                <div class="col-sm-8">${file.mime_type}</div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>الحجم:</strong></div>
                                <div class="col-sm-8">${this.formatFileSize(file.file_size)}</div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>المسار المؤقت:</strong></div>
                                <div class="col-sm-8"><code>${file.temp_path}</code></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>المجلد الأصلي:</strong></div>
                                <div class="col-sm-8">${file.original_folder || 'غير محدد'}</div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>معرف الجلسة:</strong></div>
                                <div class="col-sm-8"><code>${file.session_id}</code></div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>تاريخ الإنشاء:</strong></div>
                                <div class="col-sm-8">${createdAt}</div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-4"><strong>تاريخ انتهاء الصلاحية:</strong></div>
                                <div class="col-sm-8">
                                    ${expiresAt}
                                    ${isExpired ? '<span class="badge bg-danger ms-2">منتهي الصلاحية</span>' : '<span class="badge bg-success ms-2">نشط</span>'}
                                </div>
                            </div>
                        `);

                        $('#fileDetailsModal').modal('show');
                    }
                } catch (error) {
                    console.error('Error showing file details:', error);
                    this.showError('حدث خطأ أثناء تحميل تفاصيل الملف');
                }
            }

            downloadFile(fileId) {
                window.open(`{{ route('public.duplicate.files.download', '') }}/${fileId}`, '_blank');
            }

            async deleteFile(fileId) {
                if (!confirm('هل أنت متأكد من حذف هذا الملف؟ لن يمكن استرجاعه بعد الحذف.')) {
                    return;
                }

                try {
                    const response = await fetch(`{{ route('public.duplicate.files.delete', '') }}/${fileId}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showSuccess('تم حذف الملف بنجاح');
                        this.loadFiles(); // Reload the current page
                    } else {
                        throw new Error(data.message || 'فشل في حذف الملف');
                    }
                } catch (error) {
                    console.error('Error deleting file:', error);
                    this.showError('حدث خطأ أثناء حذف الملف: ' + error.message);
                }
            }

            // Selection and bulk operations
            toggleSelectAll(checked) {
                $('.file-checkbox').prop('checked', checked);
                this.updateSelectedFiles();
            }

            updateSelectedFiles() {
                this.selectedFiles.clear();
                $('.file-checkbox:checked').each((index, checkbox) => {
                    this.selectedFiles.add(parseInt(checkbox.value));
                });

                const hasSelection = this.selectedFiles.size > 0;
                $('#bulkDeleteBtn').toggle(hasSelection);

                // Update select all checkbox state
                const totalCheckboxes = $('.file-checkbox').length;
                const checkedCheckboxes = $('.file-checkbox:checked').length;

                if (checkedCheckboxes === 0) {
                    $('#selectAll').prop('indeterminate', false).prop('checked', false);
                } else if (checkedCheckboxes === totalCheckboxes) {
                    $('#selectAll').prop('indeterminate', false).prop('checked', true);
                } else {
                    $('#selectAll').prop('indeterminate', true);
                }
            }

            async bulkDelete() {
                if (this.selectedFiles.size === 0) {
                    this.showError('يرجى تحديد ملف واحد على الأقل للحذف');
                    return;
                }

                if (!confirm(`هل أنت متأكد من حذف ${this.selectedFiles.size} ملف؟ لن يمكن استرجاعها بعد الحذف.`)) {
                    return;
                }

                try {
                    const response = await fetch(`{{ route('public.duplicate.files.bulk.delete') }}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify({
                            file_ids: Array.from(this.selectedFiles)
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showSuccess(`تم حذف ${data.data.deleted_count} ملف بنجاح`);
                        this.selectedFiles.clear();
                        $('#bulkDeleteBtn').hide();
                        $('#selectAll').prop('checked', false);
                        this.loadFiles(); // Reload the current page
                    } else {
                        throw new Error(data.message || 'فشل في حذف الملفات');
                    }
                } catch (error) {
                    console.error('Error bulk deleting files:', error);
                    this.showError('حدث خطأ أثناء حذف الملفات: ' + error.message);
                }
            }

            async deleteAll() {
                // تأكيد إضافي لحذف جميع الملفات
                if (!confirm('هل أنت متأكد من حذف جميع الملفات المكررة؟ هذا الإجراء لا يمكن التراجع عنه!')) {
                    return;
                }

                if (!confirm('تحذير أخير: سيتم حذف جميع الملفات المكررة نهائياً. هل تريد المتابعة؟')) {
                    return;
                }

                try {
                    // إظهار loading state
                    $('#deleteAllBtn').html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>جاري الحذف...').prop('disabled', true);

                    const response = await fetch(`{{ route('public.duplicate.files.delete.all') }}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        let successMessage = `تم حذف جميع الملفات المكررة بنجاح (${data.data.deleted_count} ملف)`;

                        // Add directory deletion info if available
                        if (data.data.directory_deleted) {
                            successMessage += ' وتم حذف المجلد الرئيسي';
                        }

                        if (data.data.temp_directory_cleaned) {
                            successMessage += ' وتم تنظيف المجلد المؤقت';
                        }

                        // Add success rate if there were failures
                        if (data.data.failed_count > 0) {
                            successMessage += ` (معدل النجاح: ${data.data.success_rate}%)`;
                        }

                        this.showSuccess(successMessage);
                        this.selectedFiles.clear();
                        $('#bulkDeleteBtn').hide();
                        $('#selectAll').prop('checked', false);
                        this.loadFiles(); // Reload files
                        this.loadRealStatistics(); // Reload statistics
                    } else {
                        throw new Error(data.message || 'فشل في حذف جميع الملفات');
                    }
                } catch (error) {
                    console.error('Error deleting all files:', error);
                    this.showError('حدث خطأ أثناء حذف جميع الملفات: ' + error.message);
                } finally {
                    // إعادة تعيين الزر
                    $('#deleteAllBtn').html('<i class="fas fa-trash-alt me-1"></i>حذف الكل').prop('disabled', false);
                }
            }

            // Navigation and filtering
            goToPage(page) {
                console.log(`📄 الانتقال إلى الصفحة: ${page}`);

                // التأكد من أن رقم الصفحة صالح
                if (!page || page < 1) {
                    console.warn('⚠️ رقم صفحة غير صالح:', page);
                    return;
                }

                this.currentPage = page;
                this.loadFiles();

                // منع التمرير إلى أعلى الصفحة
                return false;
            }

            search() {
                this.searchTerm = $('#searchInput').val().trim();
                this.currentPage = 1;
                this.loadFiles();
            }

            applyFilters() {
                this.fileType = $('#fileTypeFilter').val();
                this.status = $('#statusFilter').val();
                this.currentPage = 1;
                this.loadFiles();
            }

            changePerPage() {
                this.perPage = parseInt($('#perPageSelect').val());
                this.currentPage = 1;
                this.loadFiles();
            }

            refresh() {
                this.currentPage = 1;
                this.selectedFiles.clear();
                $('#bulkDeleteBtn').hide();
                $('#selectAll').prop('checked', false);
                this.loadFiles();
                this.loadRealStatistics();
            }

            // UI helper methods
            showLoading() {
                $('#loadingState').show();
                $('#tableContainer').hide();
                $('#emptyState').hide();
            }

            hideLoading() {
                $('#loadingState').hide();
            }

            showEmptyState() {
                $('#emptyState').show();
                $('#tableContainer').hide();
            }

            showSuccess(message) {
                // You can replace this with your preferred notification system
                alert(message);
            }

            showError(message) {
                // You can replace this with your preferred notification system
                alert(message);
            }

            // Utility methods
            isImageFile(mimeType) {
                return mimeType && mimeType.startsWith('image/');
            }

            getValidImagePath(file) {
                // إذا كان هناك preview_url استخدمه
                if (file.preview_url) {
                    return file.preview_url;
                }

                // إذا كان temp_path يحتوي على مسار Windows مطلق، استخدم route للعرض
                if (file.temp_path && (file.temp_path.includes('I:\\') || file.temp_path.includes('C:\\') || file.temp_path.includes('unit test'))) {
                    return `/public-api/duplicate-files/serve/${file.id}`;
                }

                // إذا كان temp_path يبدأ بـ storage/ أو temp/
                if (file.temp_path && (file.temp_path.startsWith('storage/') || file.temp_path.startsWith('temp/'))) {
                    return `/storage/${file.temp_path.replace(/^storage\//, '')}`;
                }

                // إذا كان temp_path نسبي، أضف /storage/
                if (file.temp_path && !file.temp_path.startsWith('/')) {
                    return `/storage/${file.temp_path}`;
                }

                // استخدام route آمن كحل أخير
                return `/public-api/duplicate-files/serve/${file.id}`;
            }

            getFileIcon(mimeType) {
                if (!mimeType) return 'fas fa-file';

                if (mimeType.startsWith('image/')) return 'fas fa-image';
                if (mimeType.startsWith('video/')) return 'fas fa-video';
                if (mimeType.startsWith('audio/')) return 'fas fa-music';
                if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
                if (mimeType.includes('word') || mimeType.includes('document')) return 'fas fa-file-word';
                if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel';
                if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fas fa-file-powerpoint';
                if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('archive')) return 'fas fa-file-archive';

                return 'fas fa-file';
            }

            getFileTypeLabel(mimeType) {
                if (!mimeType) return 'غير معروف';

                if (mimeType.startsWith('image/')) return 'صورة';
                if (mimeType.startsWith('video/')) return 'فيديو';
                if (mimeType.startsWith('audio/')) return 'صوت';
                if (mimeType.includes('pdf')) return 'PDF';
                if (mimeType.includes('word') || mimeType.includes('document')) return 'وثيقة';
                if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'جدول بيانات';
                if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'عرض تقديمي';
                if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('archive')) return 'أرشيف';

                return 'ملف';
            }

            formatFileSize(bytes) {
                if (!bytes || bytes === 0) return '0 بايت';

                const sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
                const i = Math.floor(Math.log(bytes) / Math.log(1024));

                return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
            }
        }

        // Initialize when document is ready
        $(document).ready(function() {
            console.log('🚀 تحميل صفحة إدارة الملفات المكررة');

            // Initialize the manager
            window.duplicateFilesManager = new DuplicateFilesManager();

            // Add event listener for checkbox changes
            $(document).on('change', '.file-checkbox', function() {
                duplicateFilesManager.updateSelectedFiles();
            });
        });
</script>
@endpush
@endsection
