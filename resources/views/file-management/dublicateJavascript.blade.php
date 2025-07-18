@push('scriptsCode')
    <!-- Custom Scripts -->
    <script>
        // إضافة تأثيرات تفاعلية
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (alert && alert.parentNode) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });

            // تحسين responsive للـ sidebar
            const sidebarToggle = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.admin-sidebar');

            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }

            // إضافة smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });

        // دالة لعرض رسائل Toast
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();

            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-info-circle me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            // إزالة Toast بعد إخفائه
            toast.addEventListener('hidden.bs.toast', function() {
                toast.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1055';
            document.body.appendChild(container);
            return container;
        }
    </script>
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

                // Delete all files
                $('#deleteAllBtn').on('click', () => this.deleteAllFiles());

                // Download all files as ZIP
                $('#downloadAllBtn').on('click', () => this.downloadAllFiles());

                // Download selected files as ZIP
                $('#downloadSelectedBtn').on('click', () => this.downloadSelectedFiles());

                // Pagination event delegation
                $(document).on('click', '#paginationLinks .pagination-btn', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const $btn = $(e.currentTarget);

                    // تجاهل الأزرار المعطلة
                    if ($btn.is(':disabled') || $btn.parent().hasClass('active')) {
                        return false;
                    }

                    const page = parseInt($btn.data('page'));

                    if (page && !isNaN(page) && page > 0) {
                        this.goToPage(page);
                    }

                    return false;
                });
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
                            <button type="button" class="page-link pagination-btn" data-page="${pagination.current_page - 1}" aria-label="السابق">
                                <span aria-hidden="true">&laquo;</span>
                            </button>
                        </li>
                    `);
                }

                // Page numbers
                for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.last_page, pagination.current_page + 2); i++) {
                    const isActive = i === pagination.current_page;
                    paginationLinks.append(`
                        <li class="page-item ${isActive ? 'active' : ''}">
                            <button type="button" class="page-link pagination-btn" data-page="${i}" ${isActive ? 'disabled' : ''}>${i}</button>
                        </li>
                    `);
                }

                // Next button
                if (pagination.current_page < pagination.last_page) {
                    paginationLinks.append(`
                        <li class="page-item">
                            <button type="button" class="page-link pagination-btn" data-page="${pagination.current_page + 1}" aria-label="التالي">
                                <span aria-hidden="true">&raquo;</span>
                            </button>
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
                $('#downloadSelectedBtn').toggle(hasSelection);

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
                        $('#downloadSelectedBtn').hide();
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

            async deleteAllFiles() {
                if (!confirm('هل أنت متأكد من حذف جميع الملفات المكررة؟ هذا الإجراء لا يمكن التراجع عنه!')) {
                    return;
                }

                if (!confirm('تأكيد نهائي: سيتم حذف جميع الملفات المكررة نهائياً. هل تريد المتابعة؟')) {
                    return;
                }

                try {
                    this.showLoading();

                    const response = await fetch(`{{ route('admin.duplicate.files.bulk.delete') }}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify({
                            delete_all: true
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showSuccess(`تم حذف ${data.data.deleted_count} ملف بنجاح`);
                        this.selectedFiles.clear();
                        $('#bulkDeleteBtn').hide();
                        $('#downloadSelectedBtn').hide();
                        $('#selectAll').prop('checked', false);
                        this.loadFiles(); // Reload the current page
                    } else {
                        throw new Error(data.message || 'فشل في حذف الملفات');
                    }
                } catch (error) {
                    console.error('Error deleting all files:', error);
                    this.showError('حدث خطأ أثناء حذف جميع الملفات: ' + error.message);
                } finally {
                    this.hideLoading();
                }
            }

            async downloadAllFiles() {
                if (!confirm('هل تريد تنزيل جميع الملفات المكررة في ملف مضغوط؟')) {
                    return;
                }

                try {
                    this.showLoading();

                    // Create download link and trigger download
                    const downloadUrl = `{{ route('admin.duplicate.files.download.all') }}`;
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.download = `duplicate_files_all_${new Date().toISOString().split('T')[0]}.zip`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    this.showSuccess('تم بدء تنزيل جميع الملفات');
                } catch (error) {
                    console.error('Error downloading all files:', error);
                    this.showError('حدث خطأ أثناء تنزيل الملفات: ' + error.message);
                } finally {
                    this.hideLoading();
                }
            }

            async downloadSelectedFiles() {
                if (this.selectedFiles.size === 0) {
                    this.showError('يرجى تحديد ملف واحد على الأقل للتنزيل');
                    return;
                }

                try {
                    this.showLoading();

                    const response = await fetch(`{{ route('admin.duplicate.files.download.selected') }}`, {
                        method: 'POST',
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

                    if (response.ok) {
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `duplicate_files_selected_${new Date().toISOString().split('T')[0]}.zip`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        window.URL.revokeObjectURL(url);

                        this.showSuccess(`تم تنزيل ${this.selectedFiles.size} ملف بنجاح`);
                    } else {
                        throw new Error('فشل في تنزيل الملفات');
                    }
                } catch (error) {
                    console.error('Error downloading selected files:', error);
                    this.showError('حدث خطأ أثناء تنزيل الملفات المحددة: ' + error.message);
                } finally {
                    this.hideLoading();
                }
            }

            // Navigation and filtering
            goToPage(page) {
                // التأكد من أن الصفحة صحيحة
                if (page < 1 || isNaN(page)) {
                    return;
                }

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
                // Use toast notifications if available
                if (typeof showToast === 'function') {
                    showToast(message, 'success');
                } else {
                    alert(message);
                }

                // Also log to console for debugging
                console.log('Success: ' + message);
            }

            showError(message) {
                // Use toast notifications if available
                if (typeof showToast === 'function') {
                    showToast(message, 'danger');
                } else {
                    alert(message);
                }

                // Also log to console for debugging
                console.error('Error: ' + message);
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
                if (window.duplicateFilesManager) {
                    duplicateFilesManager.updateSelectedFiles();
                }
            });

            // منع التصفح عند الضغط على روابط فارغة
            $(document).on('click', 'a[href="#"], a[href="javascript:void(0)"]', function(e) {
                e.preventDefault();
                return false;
            });

            // Add keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    if (window.duplicateFilesManager) {
                        const selectAllCheckbox = document.getElementById('selectAll');
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = true;
                            duplicateFilesManager.toggleSelectAll(true);
                        }
                    }
                }

                if (e.key === 'Delete' && window.duplicateFilesManager) {
                    if (duplicateFilesManager.selectedFiles.size > 0) {
                        duplicateFilesManager.bulkDelete();
                    }
                }
            });
        });
    </script>
@endpush
