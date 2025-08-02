@push('scriptsCode')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug: تأكيد تحميل JavaScript
        console.log('📄 Folder Management JavaScript loaded successfully');
        console.log('🔧 Current route:', window.location.pathname);

        // Ensure global functions are available immediately
        let globalFunctionReady = false;

        // إعداد متغيرات عامة
        const searchInput = document.getElementById('kt_filemanager_search');
        const folderContentsModal = new bootstrap.Modal(document.getElementById('folderContentsModal'));
        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        let currentType = '{{ request("type", "images") }}';
        let searchTimeout = null;

        // Debug: تأكيد إعداد المتغيرات
        console.log('🎯 Current type:', currentType);
        console.log('📱 Modals initialized:', {
            folderContentsModal: !!folderContentsModal,
            imageModal: !!imageModal
        });

        // إعداد البحث المباشر
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performSearch(this.value);
                }, 500);
            });
        }

        // معالج النقر على المجلدات
        document.addEventListener('click', function(e) {
            if (e.target.closest('.folder-view-btn') || e.target.closest('.folder-link')) {
                e.preventDefault();
                const folderName = e.target.closest('.folder-view-btn, .folder-link').getAttribute('data-folder');
                if (folderName) {
                    loadFolderContents(folderName);
                }
            }

            // معالج عرض الصور
            if (e.target.closest('.image-preview')) {
                e.preventDefault();
                const imageSrc = e.target.closest('.image-preview').getAttribute('data-src') ||
                            e.target.closest('.image-preview').getAttribute('src');
                const imageTitle = e.target.closest('.image-preview').getAttribute('alt') || 'صورة';
                showImageModal(imageSrc, imageTitle);
            }

            // معالج معاينة PDF
            if (e.target.closest('.pdf-preview')) {
                e.preventDefault();
                const pdfSrc = e.target.closest('.pdf-preview').getAttribute('data-src');
                showPdfModal(pdfSrc);
            }

            // معالج معاينة Excel
            if (e.target.closest('.excel-preview')) {
                e.preventDefault();
                const excelSrc = e.target.closest('.excel-preview').getAttribute('data-src');
                showExcelModal(excelSrc);
            }
        });

        // إضافة event listener لزر تحميل المجلد كاملاً
        document.addEventListener('click', function(e) {
            if (e.target.id === 'downloadFolderBtn' || e.target.closest('#downloadFolderBtn')) {
                e.preventDefault();
                const folderName = document.getElementById('modal-folder-name').textContent;
                if (folderName && folderName.trim() !== '') {
                    downloadEntireFolder(folderName.trim());
                } else {
                    console.error('❌ اسم المجلد غير محدد');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'خطأ',
                            text: 'لم يتم تحديد اسم المجلد',
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    }
                }
            }
        });

        // دالة البحث
        function performSearch(query) {
            if (query.length < 2) {
                location.reload();
                return;
            }

            console.log('🔍 Performing search for:', query);
            showLoadingState();

            const searchUrl = `{{ route('admin.folders.search') }}?search=${encodeURIComponent(query)}&type=${currentType}`;
            console.log('🌐 Search URL:', searchUrl);

            fetch(searchUrl)
                .then(response => {
                    console.log('📡 Search response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('📊 Search response data:', data);
                    if (data.success) {
                        updateTableContent(data.results);
                    } else {
                        console.error('❌ Search failed:', data.message);
                        showErrorMessage('حدث خطأ في البحث: ' + (data.message || 'غير محدد'));
                    }
                })
                .catch(error => {
                    console.error('🚨 Search error:', error);
                    showErrorMessage('حدث خطأ في الاتصال: ' + error.message);
                })
                .finally(() => {
                    hideLoadingState();
                });
        }

        // تحميل محتويات المجلد
        function loadFolderContents(folderName) {
            console.log('📂 Loading folder contents for:', folderName);

            const modalTitle = document.getElementById('modal-folder-name');
            const folderContents = document.getElementById('folder-contents');

            if (modalTitle) modalTitle.textContent = folderName;
            if (folderContents) folderContents.innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-lg"></span><br>جاري التحميل...</div>';

            folderContentsModal.show();

            // إضافة المزيد من معاملات التشخيص
            const requestUrl = `{{ route('admin.folders.contents') }}?folder=${encodeURIComponent(folderName)}&type=${currentType}&debug=1`;
            console.log('🔍 Request URL:', requestUrl);

            fetch(requestUrl)
                .then(response => {
                    console.log('📡 Response status:', response.status);
                    console.log('📡 Response headers:', response.headers);
                    return response.json();
                })
                .then(data => {
                    console.log('📊 Full response data:', data);

                    if (data.success) {
                        console.log('✅ Success response:', {
                            filesCount: data.files ? data.files.length : 0,
                            folderName: data.folder_name,
                            files: data.files
                        });
                        displayFolderContents(data.files, data.folder_name);
                    } else {
                        console.error('❌ Server returned error:', data.message || 'Unknown error');
                        folderContents.innerHTML = `<div class="alert alert-danger">
                            <h5>خطأ في جلب محتويات المجلد</h5>
                            <p>${data.message || 'حدث خطأ غير معروف'}</p>
                            <small>المجلد: ${folderName}</small>
                        </div>`;
                    }
                })
                .catch(error => {
                    console.error('🚨 Fetch error:', error);
                    folderContents.innerHTML = `<div class="alert alert-danger">
                        <h5>خطأ في الاتصال</h5>
                        <p>تعذر الاتصال بالخادم</p>
                        <small>Error: ${error.message}</small>
                    </div>`;
                });
        }

        // Function to check if image exists
        function checkImageExists(url) {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve(true);
                img.onerror = () => resolve(false);
                img.src = url;
            });
        }

        // عرض محتويات المجلد
        function displayFolderContents(files, folderName) {
            try {
                const folderContents = document.getElementById('folder-contents');

                // تشخيص البيانات الواردة
                console.log('📊 displayFolderContents called with:', {
                    filesCount: files ? files.length : 0,
                    folderName: folderName,
                    firstFile: files && files.length > 0 ? files[0] : null
                });

            // فحص download_url في الملفات
            if (files && files.length > 0) {
                const filesWithUrl = files.filter(f => f.download_url && f.download_url.trim() !== '');
                const filesWithoutUrl = files.filter(f => !f.download_url || f.download_url.trim() === '');

                console.log('🔗 URL Analysis:', {
                    totalFiles: files.length,
                    filesWithUrl: filesWithUrl.length,
                    filesWithoutUrl: filesWithoutUrl.length
                });

                if (filesWithoutUrl.length > 0) {
                    console.warn('⚠️ Files without download_url:', filesWithoutUrl.map(f => f.stored_file_name || f.original_file_name));
                }
            }

            if (!files || files.length === 0) {
                folderContents.innerHTML = `
                    <div class="col-12 text-center">
                        <div class="d-flex flex-column align-items-center py-10">
                            <i class="fas fa-folder-open fs-4x text-muted mb-4"></i>
                            <h5 class="text-muted">المجلد فارغ</h5>
                            <p class="text-muted">لا يحتوي هذا المجلد على أي ملفات</p>
                        </div>
                    </div>
                `;
                return;
            }

            let html = '';
            files.forEach(file => {
                // Enhanced image detection
                const fileExtension = (file.file_extension || file.extension || file.file_path?.split('.').pop() || '').toLowerCase();
                const mimeType = file.mime_type || '';
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension) ||
                            mimeType.includes('image/') ||
                            file.is_image === true;

                console.log('File analysis:', {
                    name: file.original_file_name,
                    extension: fileExtension,
                    mime: mimeType,
                    isImage: isImage,
                    download_url: file.download_url
                });

                const fileIcon = getFileIcon(file);

                // Build image URL with enhanced validation
                let imageUrl = file.download_url || '';

                // استخدام العرض الآمن للصور
                if (imageUrl && !imageUrl.startsWith('http')) {
                    // استخراج اسم الملف من المسار
                    const filename = imageUrl.replace(/^\/?(storage\/)+/, '').split('/').pop();
                    imageUrl = `{{ route('admin.file.show', '') }}/${filename}`;
                }

                // Create preview HTML with enhanced design and no black overlay
                const previewHtml = isImage && imageUrl ?
                    `<div class="image-container" data-image-url="${imageUrl}" style="position: relative; height: 220px; overflow: hidden; border-radius: 15px; cursor: pointer; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); box-shadow: 0 8px 25px rgba(0,0,0,0.1); transition: all 0.3s ease; border: 2px solid transparent;">
                        <img src="${imageUrl}"
                            class="w-100 h-100"
                            style="object-fit: cover; transition: transform 0.3s ease;"
                            alt="${file.original_file_name || file.stored_file_name || file.file_name}"
                            loading="lazy"
                            data-src="${imageUrl}"
                            onload="console.log('✅ Image loaded successfully:', this.src); this.parentElement.style.border='2px solid #28a745'; this.parentElement.style.boxShadow='0 8px 30px rgba(40, 167, 69, 0.2)';"
                            onerror="console.error('❌ Image failed to load:', this.src);
                                    this.style.display='none';
                                    this.parentElement.innerHTML='<div class=&quot;d-flex flex-column align-items-center justify-content-center h-100 text-muted&quot; style=&quot;background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);&quot;><i class=&quot;fas fa-image fs-3x text-danger&quot;></i><div class=&quot;mt-2 fw-bold&quot;>صورة غير متاحة</div><small class=&quot;text-muted&quot;>تعذر تحميل الصورة</small></div>';
                                    this.parentElement.style.border='2px solid #dc3545'; this.parentElement.style.boxShadow='0 8px 30px rgba(220, 53, 69, 0.2)';">

                        <!-- شارة نوع الملف -->
                        <div class="position-absolute top-0 end-0 m-2">
                            <span class="badge rounded-pill" style="background: linear-gradient(45deg, #28a745, #20c997); padding: 6px 12px; font-size: 11px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                <i class="fas fa-image me-1"></i>
                                صورة
                            </span>
                        </div>

                        <!-- زر عرض مخفي يظهر عند الـ hover -->
                        <div class="position-absolute bottom-0 start-50 translate-middle-x mb-3" style="opacity: 0; transition: opacity 0.3s ease;">
                            <button class="btn btn-light btn-sm rounded-pill px-3 py-1" style="backdrop-filter: blur(10px); background: rgba(255,255,255,0.9) !important; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                                <i class="fas fa-eye me-1"></i>
                                عرض
                            </button>
                        </div>
                    </div>` :
                    `<div class="d-flex align-items-center justify-content-center position-relative"
                        style="height: 220px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 15px; border: 2px dashed #dee2e6; transition: all 0.3s ease; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                        <div class="text-center">
                            ${fileIcon}
                            <div class="mt-3">
                                <span class="badge rounded-pill bg-primary px-3 py-2" style="font-size: 12px;">
                                    ${file.file_type || 'ملف'}
                                </span>
                            </div>
                        </div>

                        <!-- شارة نوع الملف -->
                        <div class="position-absolute top-0 end-0 m-2">
                            <span class="badge rounded-pill bg-primary" style="padding: 6px 12px; font-size: 11px;">
                                ${file.file_extension?.toUpperCase() || 'FILE'}
                            </span>
                        </div>
                    </div>`;

                html += `
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card h-100 border-0 shadow-sm" style="transition: all 0.3s ease; border-radius: 15px; overflow: hidden;"
                            onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.15)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)';">

                            <div class="card-body p-0">
                                <!-- منطقة المعاينة -->
                                <div class="position-relative"
                                    onmouseover="const btn = this.querySelector('.hover-btn'); if(btn) btn.style.opacity='1';"
                                    onmouseout="const btn = this.querySelector('.hover-btn'); if(btn) btn.style.opacity='0';">
                                    ${previewHtml}
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
                                            <span><i class="fas fa-hdd me-1"></i>الحجم:</span>
                                            <strong class="text-primary">${file.formatted_size || 'غير محدد'}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span><i class="fas fa-calendar me-1"></i>التاريخ:</span>
                                            <strong class="text-secondary">${file.formatted_date || 'غير محدد'}</strong>
                                        </div>
                                        ${file.mime_type ? `
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file me-1"></i>النوع:</span>
                                                <strong class="text-info">${file.mime_type}</strong>
                                            </div>
                                        ` : ''}
                                    </div>

                                    <!-- أزرار العمليات -->
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button"
                                                class="btn btn-outline-primary btn-sm flex-fill"
                                                onclick="downloadFile('${imageUrl || file.download_url || '#'}', '${file.original_file_name || file.stored_file_name || file.file_name}')"
                                                style="border-radius: 20px; font-weight: 600; transition: all 0.3s ease;"
                                                onmouseover="this.style.backgroundColor='#0d6efd'; this.style.color='white'; this.style.transform='translateY(-2px)';"
                                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#0d6efd'; this.style.transform='translateY(0)';">
                                            <i class="fas fa-download me-1"></i>
                                            تحميل
                                        </button>

                                        ${file.mime_type && file.mime_type.includes('image') ? `
                                            <button type="button"
                                                    class="btn btn-outline-success btn-sm"
                                                    onclick="openImageModal('${imageUrl}', '${file.original_file_name || file.stored_file_name || file.file_name}')"
                                                    style="border-radius: 20px; font-weight: 600; transition: all 0.3s ease;"
                                                    onmouseover="this.style.backgroundColor='#198754'; this.style.color='white'; this.style.transform='translateY(-2px)';"
                                                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#198754'; this.style.transform='translateY(0)';">
                                                <i class="fas fa-eye me-1"></i>
                                                معاينة
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Update modal content
            if (folderContents) {
                folderContents.innerHTML = html;
            } else {
                console.error('folder-contents not found');
            }

            } catch (error) {
                console.error('Error in displayFolderContents:', error);
                const folderContents = document.getElementById('folder-contents');
                if (folderContents) {
                    folderContents.innerHTML = '<p class="text-danger text-center py-5"><i class="fas fa-exclamation-triangle fs-3x text-danger mb-3"></i><br>حدث خطأ في تحميل الملفات<br><small class="text-muted">يرجى المحاولة مرة أخرى</small></p>';
                }
            }
        }

        // دالة تحميل الملفات المحسنة
        function downloadFile(fileUrl, fileName) {
            console.log('⬇️ Downloading file:', fileName, 'from:', fileUrl);

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
                    text: `جاري تحميل الملف: ${fileName}`,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                console.log('✅ File download started:', fileName);
            }
        }

        // دالة تحميل المجلد كاملاً كملف ZIP
        function downloadEntireFolder(folderName) {
            console.log('📁⬇️ Starting ZIP download for folder:', folderName);

            // عرض تأكيد للمستخدم
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'تحميل المجلد كملف ZIP',
                    text: `هل تريد تحميل جميع ملفات المجلد "${folderName}" كملف ZIP مضغوط؟`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '📦 نعم، حمّل كـ ZIP',
                    cancelButtonText: 'إلغاء',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return performZipDownload(folderName);
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        // لا نحتاج لعمل شيء هنا لأن التحميل سيبدأ تلقائياً
                        console.log('✅ ZIP download initiated');
                    }
                });
            } else {
                // تحميل مباشر بدون SweetAlert
                if (confirm(`هل تريد تحميل جميع ملفات المجلد "${folderName}" كملف ZIP؟`)) {
                    performZipDownload(folderName);
                }
            }
        }

        // تنفيذ تحميل المجلد كـ ZIP
        async function performZipDownload(folderName) {
            try {
                console.log('� Starting ZIP creation for folder:', folderName);

                // إنشاء رابط التحميل
                const downloadUrl = `{{ route('admin.folders.download.zip') }}?folder=${encodeURIComponent(folderName)}`;
                console.log('� ZIP download URL:', downloadUrl);

                // إنشاء رابط تحميل مخفي
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `folder_${folderName}_${new Date().toISOString().slice(0,10)}.zip`;
                link.style.display = 'none';

                // إضافة الرابط للصفحة وتشغيله
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // عرض رسالة نجاح
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'تم بدء التحميل!',
                        text: `جاري تحضير وتحميل ملف ZIP للمجلد "${folderName}"`,
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false,
                        timerProgressBar: true
                    });
                }

                console.log('✅ ZIP download link created and clicked');
                return { success: true };

            } catch (error) {
                console.error('❌ Error initiating ZIP download:', error);

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'خطأ في التحميل',
                        text: 'حدث خطأ أثناء تحضير ملف ZIP: ' + error.message,
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                } else {
                    alert('خطأ في تحميل المجلد: ' + error.message);
                }

                throw error;
            }
        }

        // فتح صورة في مودال للعرض المحسن
        function openImageModal(imageSrc, imageTitle = 'معاينة الصورة') {
            console.log('🖼️ Opening enhanced image modal:', imageSrc);

            // إنشاء مودال ديناميكي للصورة
            const modalId = 'enhancedImageModal';
            let existingModal = document.getElementById(modalId);

            // إزالة المودال السابق إن وجد
            if (existingModal) {
                existingModal.remove();
            }

            // إنشاء مودال جديد
            const modalHtml = `
                <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true" data-bs-backdrop="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl">
                        <div class="modal-content bg-dark">
                            <div class="modal-header border-0 pb-2">
                                <h5 class="modal-title text-white" id="${modalId}Label">
                                    <i class="fas fa-image me-2"></i>
                                    ${imageTitle}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                            </div>
                            <div class="modal-body text-center p-0">
                                <div class="position-relative">
                                    <img src="${imageSrc}"
                                         class="img-fluid rounded"
                                         alt="${imageTitle}"
                                         style="max-height: 80vh; width: auto; object-fit: contain;"
                                         onload="console.log('✅ Enhanced modal image loaded successfully');"
                                         onerror="console.error('❌ Enhanced modal image failed to load'); this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMThweCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPuKdjCDYtdmI2LHYqSDYutmK2LEg2YXYqtin2K3YqTwvdGV4dD48L3N2Zz4=';">
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-2">
                                <a href="${imageSrc}" download="${imageTitle}" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i>
                                    تحميل الصورة
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // إضافة المودال إلى الصفحة
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // عرض المودال
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();

            // إزالة المودال عند الإغلاق
            document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Make functions globally accessible
        window.downloadFile = downloadFile;
        window.downloadEntireFolder = downloadEntireFolder;
        window.openImageModal = openImageModal;        // عرض الصورة في نافذة منبثقة
        function showImageModal(imageSrc, imageTitle = 'صورة') {
            console.log('Opening image modal:', imageSrc); // Debug log

            const modalImage = document.getElementById('modalImage');
            const modalLabel = document.getElementById('imageModalLabel');
            const downloadBtn = document.getElementById('downloadImage');

            if (modalImage) {
                modalImage.src = imageSrc;
                modalImage.onload = () => console.log('Modal image loaded successfully');
                modalImage.onerror = () => {
                    console.error('Modal image failed to load:', imageSrc);
                    modalImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDQwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSI0MDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjVGNUY1Ii8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LXNpemU9IjE4IiBmaWxsPSIjOTk5Ij7YtdmI2LHYqSDYutmK2LEg2YXYqtmI2YHYsdipPC90ZXh0Pgo8L3N2Zz4=';
                };
            }

            if (modalLabel) modalLabel.textContent = imageTitle;

            if (downloadBtn) {
                downloadBtn.onclick = () => {
                    const link = document.createElement('a');
                    link.href = imageSrc;
                    link.download = imageTitle;
                    link.click();
                };
            }

            // Check if modal exists and show it
            if (typeof imageModal !== 'undefined' && imageModal) {
                imageModal.show();
            } else {
                console.error('Image modal not found');
                // Fallback - open in new window
                window.open(imageSrc, '_blank');
            }
        }

        // Make showImageModal globally accessible
        window.showImageModal = showImageModal;
        window.loadFolderContents = loadFolderContents;
        window.displayFolderContents = displayFolderContents;

        // Debug: Test function availability
        window.testFunctions = function() {
            console.log('🧪 Testing functions:');
            console.log('showImageModal:', typeof window.showImageModal);
            console.log('loadFolderContents:', typeof window.loadFolderContents);
            console.log('displayFolderContents:', typeof window.displayFolderContents);
            console.log('downloadFile:', typeof window.downloadFile);
            console.log('downloadEntireFolder:', typeof window.downloadEntireFolder);
            console.log('openImageModal:', typeof window.openImageModal);
        };

        // Auto-run test
        setTimeout(() => {
            console.log('🚀 Auto-testing functions...');
            window.testFunctions();

            // Additional verification
            console.log('✅ Global functions status:', {
                downloadFile: typeof window.downloadFile === 'function',
                downloadEntireFolder: typeof window.downloadEntireFolder === 'function',
                openImageModal: typeof window.openImageModal === 'function',
                showImageModal: typeof window.showImageModal === 'function',
                loadFolderContents: typeof window.loadFolderContents === 'function'
            });
        }, 1000);

        console.log('🎯 نظام إدارة المجلدات المتطور جاهز للاستخدام مع جميع الدوال العامة متاحة');

        // Mark global functions as ready
        globalFunctionReady = true;
        window.globalFunctionReady = true;

        // Final verification
        console.log('🔍 Final function verification:', {
            downloadFile: typeof window.downloadFile,
            downloadEntireFolder: typeof window.downloadEntireFolder,
            openImageModal: typeof window.openImageModal,
            showImageModal: typeof window.showImageModal,
            loadFolderContents: typeof window.loadFolderContents,
            ready: globalFunctionReady
        });

        // دالة الحصول على أيقونة الملف
        function getFileIcon(file) {
            const ext = (file.file_extension || file.file_path?.split('.').pop() || '').toLowerCase();
            const mimeType = file.mime_type || '';

            const icons = {
                pdf: '<i class="fas fa-file-pdf fs-3x text-danger"></i>',
                doc: '<i class="fas fa-file-word fs-3x text-primary"></i>',
                docx: '<i class="fas fa-file-word fs-3x text-primary"></i>',
                xls: '<i class="fas fa-file-excel fs-3x text-success"></i>',
                xlsx: '<i class="fas fa-file-excel fs-3x text-success"></i>',
                csv: '<i class="fas fa-file-csv fs-3x text-warning"></i>',
                txt: '<i class="fas fa-file-alt fs-3x text-secondary"></i>',
                zip: '<i class="fas fa-file-archive fs-3x text-info"></i>',
                rar: '<i class="fas fa-file-archive fs-3x text-info"></i>',
                default: '<i class="fas fa-file fs-3x text-muted"></i>'
            };

            // تحقق من نوع MIME أولاً
            if (mimeType.includes('image')) {
                return '<i class="fas fa-image fs-3x text-success"></i>';
            } else if (mimeType.includes('pdf')) {
                return icons.pdf;
            } else if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) {
                return icons.xlsx;
            } else if (mimeType.includes('word')) {
                return icons.docx;
            }

            return icons[ext] || icons.default;
        }

        // دالة تحديث محتويات الجدول مع نتائج البحث
        function updateTableContent(results) {
            console.log('🔄 Updating table content with search results:', results);

            const container = document.getElementById('files_table_container');
            if (!container) {
                console.error('❌ Container غير موجود');
                return;
            }

            if (!results || !results.data || results.data.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-search fs-3x text-muted mb-3"></i>
                        <h4 class="text-muted">لم يتم العثور على نتائج</h4>
                        <p class="text-muted">جرب كلمات بحث مختلفة أو تحقق من الإملاء</p>
                        <button class="btn btn-light btn-sm" onclick="location.reload()">
                            <i class="fas fa-refresh me-2"></i>إعادة تحميل الصفحة
                        </button>
                    </div>`;
                return;
            }

            // إظهار عدد النتائج
            const resultCount = results.data.length;
            const totalCount = results.total || resultCount;

            let tableHtml = `
                <div class="alert alert-info d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle me-3"></i>
                    <div>
                        <strong>نتائج البحث:</strong> تم العثور على ${resultCount} نتيجة
                        ${totalCount > resultCount ? ` من أصل ${totalCount}` : ''}
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed table-hover align-middle" id="kt_file_manager_list">
                        <thead style="background-color: #ffffff !important;">
                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-250px">اسم الملف</th>
                                <th class="min-w-100px">الحجم</th>
                                <th class="min-w-120px">رقم السجل</th>
                                <th class="min-w-100px">النوع</th>
                                <th class="min-w-120px">التاريخ</th>
                                <th class="text-end min-w-100px">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>`;

            results.data.forEach(file => {
                const fileSize = file.formatted_size || (file.file_size ? formatFileSize(file.file_size) : 'غير معروف');
                const recordNumber = file.record_number || file.extracted_folder_name || 'غير محدد';
                const fileName = file.original_file_name || file.stored_file_name || file.file_name || 'ملف غير معروف';
                const personName = file.person_name || 'غير مسجل';
                const fileDate = file.formatted_date || file.created_at || file.updated_at || 'غير محدد';

                // تحديد نوع الملف وأيقونته
                const fileExtension = (file.file_extension || file.extension || '').toLowerCase();
                const mimeType = file.mime_type || '';
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension) ||
                              mimeType.includes('image/');
                const isPdf = fileExtension === 'pdf' || mimeType.includes('pdf');

                let fileTypeIcon = 'fas fa-file';
                let fileTypeColor = 'text-secondary';
                let fileTypeLabel = 'ملف';

                if (isImage) {
                    fileTypeIcon = 'fas fa-image';
                    fileTypeColor = 'text-success';
                    fileTypeLabel = 'صورة';
                } else if (isPdf) {
                    fileTypeIcon = 'fas fa-file-pdf';
                    fileTypeColor = 'text-danger';
                    fileTypeLabel = 'PDF';
                } else if (['xlsx', 'xls', 'csv'].includes(fileExtension)) {
                    fileTypeIcon = 'fas fa-file-excel';
                    fileTypeColor = 'text-warning';
                    fileTypeLabel = 'Excel';
                }

                // معالجة رابط التحميل
                let downloadUrl = file.download_url;
                if (!downloadUrl && fileName) {
                    downloadUrl = `{{ route('admin.file.show', '') }}/${fileName}`;
                }

                console.log('🔍 Processing search result:', {
                    fileName: fileName,
                    recordNumber: recordNumber,
                    downloadUrl: downloadUrl,
                    isImage: isImage,
                    fileType: fileTypeLabel
                });

                tableHtml += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="${fileTypeIcon} ${fileTypeColor} fs-2 me-3"></i>
                                <div>
                                    <span class="text-gray-800 fw-bold d-block">${fileName}</span>
                                    <small class="text-muted">${personName}</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-gray-600">${fileSize}</td>
                        <td>
                            <span class="badge badge-light-primary">${recordNumber}</span>
                        </td>
                        <td>
                            <span class="badge badge-light-${fileTypeColor.replace('text-', '')}">${fileTypeLabel}</span>
                        </td>
                        <td class="text-gray-600">${fileDate}</td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                ${isImage ? `
                                    <button class="btn btn-sm btn-success image-preview-btn"
                                            data-src="${downloadUrl}"
                                            data-title="${fileName}"
                                            title="معاينة الصورة">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-primary download-file-btn"
                                        data-url="${downloadUrl}"
                                        data-filename="${fileName}"
                                        title="تحميل الملف">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
            });

            tableHtml += `</tbody></table></div>`;

            // إضافة pagination إذا كان متاحاً
            if (results.last_page && results.last_page > 1) {
                tableHtml += `
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            عرض ${results.from || 1} إلى ${results.to || resultCount} من ${results.total || resultCount} نتيجة
                        </div>
                        <div class="text-muted">
                            صفحة ${results.current_page || 1} من ${results.last_page || 1}
                        </div>
                    </div>`;
            }

            container.innerHTML = tableHtml;

            // إعادة ربط Event Listeners للعناصر الجديدة
            attachEventListenersToSearchResults();

            console.log('✅ تم تحديث الجدول وربط الأحداث بنجاح');
        }

        // دالة ربط الأحداث بنتائج البحث
        function attachEventListenersToSearchResults() {
            console.log('🔗 ربط الأحداث بنتائج البحث...');

            // معاينة الصور
            document.querySelectorAll('.image-preview-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const imageSrc = this.getAttribute('data-src');
                    const imageTitle = this.getAttribute('data-title');

                    console.log('👁️ معاينة صورة من نتائج البحث:', {
                        imageSrc: imageSrc,
                        imageTitle: imageTitle
                    });

                    if (!imageSrc || imageSrc === 'undefined' || imageSrc === '') {
                        console.error('❌ مسار الصورة غير صحيح:', imageSrc);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: 'مسار الصورة غير متاح',
                                timer: 3000
                            });
                        }
                        return;
                    }

                    showImageModal(imageSrc, imageTitle);
                });
            });

            // تحميل الملفات
            document.querySelectorAll('.download-file-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fileUrl = this.getAttribute('data-url');
                    const fileName = this.getAttribute('data-filename');

                    console.log('📥 تحميل ملف من نتائج البحث:', {
                        fileUrl: fileUrl,
                        fileName: fileName
                    });

                    if (!fileUrl || fileUrl === 'undefined' || fileUrl === '') {
                        console.error('❌ رابط التحميل غير صحيح:', fileUrl);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: 'رابط التحميل غير متاح',
                                timer: 3000
                            });
                        }
                        return;
                    }

                    // إنشاء رابط تحميل ديناميكي
                    const link = document.createElement('a');
                    link.href = fileUrl;
                    link.download = fileName;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            });

            console.log('✅ تم ربط جميع الأحداث بنجاح');
        }

        // دوال مساعدة
        // دوال مساعدة
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function showLoadingState() {
            console.log('⏳ عرض حالة التحميل...');
            // يمكن إضافة منطق عرض التحميل هنا
        }

        function hideLoadingState() {
            console.log('✅ إخفاء حالة التحميل...');
            // يمكن إضافة منطق إخفاء التحميل هنا
        }

        function showErrorMessage(message) {
            console.error('❌ خطأ:', message);
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

    });

</script>

<style>
/* تحسينات CSS مخصصة */
.image-preview {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border-radius: 8px;
}

.image-preview:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    cursor: pointer;
}

.card-flush .card-body {
    transition: background-color 0.2s ease-in-out;
}

.card-flush:hover .card-body {
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}

.folder-link {
    transition: color 0.2s ease-in-out;
}

.folder-link:hover {
    color: var(--bs-primary) !important;
    text-decoration: none;
}

/* تحسين عرض الجداول على الأجهزة المحمولة */
@media (max-width: 768px) {
    .table-responsive {
        border: none;
    }

    .table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
    }
}

/* تحسين النوافذ المنبثقة */
.modal-xl {
    max-width: 95vw;
}

.modal-body {
    max-height: 80vh;
    overflow-y: auto;
}

/* تحسين أشرطة التقدم والمحمل */
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

/* تحسين الأزرار */
.btn-sm {
    font-size: 0.8rem;
}

/* تحسين عرض الصور */
.image-container:hover .image-overlay {
    opacity: 1 !important;
}

.image-container:hover img {
    transform: scale(1.05);
}

.image-preview {
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}

.file-preview-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.file-preview-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* تحسين البحث */
#kt_filemanager_search {
    transition: box-shadow 0.2s ease-in-out;
}

#kt_filemanager_search:focus {
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}
</style>
@endpush
