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
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-light btn-sm" id="refreshBtn">
                                <i class="fas fa-sync-alt me-1"></i>
                                تحديث
                            </button>
                            <button class="btn btn-danger btn-sm" id="bulkDeleteBtn" style="display: none;">
                                <i class="fas fa-trash-alt me-1"></i>
                                حذف المحدد
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-files-o fa-2x"></i>
                    </div>
                    <h5 class="card-title text-muted mb-1">إجمالي الملفات</h5>
                    <h3 class="text-primary mb-0" id="totalFiles">-</h3>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-images fa-2x"></i>
                    </div>
                    <h5 class="card-title text-muted mb-1">الصور</h5>
                    <h3 class="text-warning mb-0" id="totalImages">-</h3>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                    <h5 class="card-title text-muted mb-1">المستندات</h5>
                    <h3 class="text-info mb-0" id="totalDocuments">-</h3>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-hdd fa-2x"></i>
                    </div>
                    <h5 class="card-title text-muted mb-1">المساحة المستخدمة</h5>
                    <h3 class="text-success mb-0" id="totalSize">-</h3>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filePreviewModalLabel">معاينة الملف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body text-center" id="filePreviewContent">
                <!-- محتوى المعاينة -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <a href="#" class="btn btn-primary" id="downloadFromPreview" target="_blank">
                    <i class="fas fa-download me-1"></i>
                    تحميل
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

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}
</style>
@endpush

@push('scripts')
<script>
class DuplicateFilesManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 25;
        this.searchTerm = '';
        this.fileType = '';
        this.status = '';
        this.selectedFiles = new Set();

        this.init();
        this.loadFiles();
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

        // Select all checkbox
        $('#selectAll').on('change', (e) => this.toggleSelectAll(e.target.checked));

        // Bulk delete
        $('#bulkDeleteBtn').on('click', () => this.bulkDelete());
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

            const response = await fetch(`{{ route('admin.duplicate.files.paginated') }}?${params}`, {
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
                        `<img src="${file.preview_url || '/storage/' + file.temp_path}" class="preview-thumbnail" alt="معاينة" onclick="duplicateFilesManager.previewFile(${file.id})">` :
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
            paginationLinks.append(`
                <li class="page-item">
                    <a class="page-link" href="#" onclick="duplicateFilesManager.goToPage(${pagination.current_page - 1})" aria-label="السابق">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `);
        }

        // Page numbers
        for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.last_page, pagination.current_page + 2); i++) {
            const isActive = i === pagination.current_page;
            paginationLinks.append(`
                <li class="page-item ${isActive ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="duplicateFilesManager.goToPage(${i})">${i}</a>
                </li>
            `);
        }

        // Next button
        if (pagination.current_page < pagination.last_page) {
            paginationLinks.append(`
                <li class="page-item">
                    <a class="page-link" href="#" onclick="duplicateFilesManager.goToPage(${pagination.current_page + 1})" aria-label="التالي">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `);
        }
    }

    updateStatistics(statistics) {
        $('#totalFiles').text(statistics.total_files || 0);
        $('#totalImages').text(statistics.total_images || 0);
        $('#totalDocuments').text(statistics.total_documents || 0);
        $('#totalSize').text(this.formatFileSize(statistics.total_size || 0));
    }

    // File operations
    async previewFile(fileId) {
        try {
            const response = await fetch(`{{ route('admin.duplicate.files.preview', '') }}/${fileId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                const file = data.data;
                $('#filePreviewModalLabel').text(`معاينة: ${file.original_name}`);
                $('#downloadFromPreview').attr('href', `{{ route('admin.duplicate.files.download', '') }}/${fileId}`);

                if (this.isImageFile(file.mime_type)) {
                    $('#filePreviewContent').html(`
                        <img src="${file.preview_url || '/storage/' + file.temp_path}" class="img-fluid" alt="معاينة الصورة">
                    `);
                } else {
                    $('#filePreviewContent').html(`
                        <div class="text-center">
                            <i class="${this.getFileIcon(file.mime_type)} fa-4x text-muted mb-3"></i>
                            <h5>${file.original_name}</h5>
                            <p class="text-muted">${file.mime_type}</p>
                            <p>الحجم: ${this.formatFileSize(file.file_size)}</p>
                        </div>
                    `);
                }

                $('#filePreviewModal').modal('show');
            }
        } catch (error) {
            console.error('Error previewing file:', error);
            this.showError('حدث خطأ أثناء معاينة الملف');
        }
    }

    async showFileDetails(fileId) {
        try {
            const response = await fetch(`{{ route('admin.duplicate.files.view', '') }}/${fileId}`, {
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
        window.open(`{{ route('admin.duplicate.files.download', '') }}/${fileId}`, '_blank');
    }

    async deleteFile(fileId) {
        if (!confirm('هل أنت متأكد من حذف هذا الملف؟ لن يمكن استرجاعه بعد الحذف.')) {
            return;
        }

        try {
            const response = await fetch(`{{ route('admin.duplicate.files.delete', '') }}/${fileId}`, {
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
            const response = await fetch(`{{ route('admin.duplicate.files.bulk.delete') }}`, {
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

    // Navigation and filtering
    goToPage(page) {
        this.currentPage = page;
        this.loadFiles();
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
