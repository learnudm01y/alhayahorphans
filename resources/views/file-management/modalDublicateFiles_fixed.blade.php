<!-- Modal: عرض الملفات أو المجلدات المكررة أثناء الرفع -->
<div class="modal fade" id="duplicateFilesModal" tabindex="-1" aria-labelledby="duplicateFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title" id="duplicateFilesModalLabel">
          <i class="fas fa-clone text-warning me-2"></i>
          الملفات/المجلدات المكررة المكتشفة أثناء الرفع
        </h5>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-info btn-sm" id="showAllBtn" onclick="showAllDuplicateFiles()" title="عرض جميع الملفات المكررة">
            <i class="fas fa-list me-1"></i>
            <span id="showAllBtnText">عرض الكل</span>
          </button>
          <a href="{{ route('admin.duplicate.files.index') }}" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="fas fa-external-link-alt me-1"></i>
            إدارة متقدمة
          </a>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
      </div>
      <div class="modal-body">
        <!-- Statistics Cards -->
        <div class="row mb-3" id="duplicateStatsCards" style="display: none;">
          <div class="col-md-3">
            <div class="card border-0 bg-light">
              <div class="card-body text-center py-2">
                <small class="text-muted">إجمالي الملفات</small>
                <h6 class="mb-0 text-primary" id="modalTotalFiles">0</h6>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card border-0 bg-light">
              <div class="card-body text-center py-2">
                <small class="text-muted">الصور</small>
                <h6 class="mb-0 text-warning" id="modalTotalImages">0</h6>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card border-0 bg-light">
              <div class="card-body text-center py-2">
                <small class="text-muted">المستندات</small>
                <h6 class="mb-0 text-info" id="modalTotalDocuments">0</h6>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card border-0 bg-light">
              <div class="card-body text-center py-2">
                <small class="text-muted">المساحة</small>
                <h6 class="mb-0 text-success" id="modalTotalSize">0</h6>
              </div>
            </div>
          </div>
        </div>

        <div id="duplicateFilesList">
          <div class="text-center text-muted">جاري تحميل قائمة الملفات المكررة...</div>
        </div>

        <div class="alert alert-info mt-3" id="duplicateFilesNote" style="display:none;">
          يمكنك تحميل جميع الملفات المكررة كملف مضغوط أو مراجعتها بشكل فردي.
          <br><small class="text-muted">للحصول على إدارة متقدمة وعرض شامل، استخدم صفحة الإدارة المتقدمة.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
        <button type="button" id="deleteDuplicatesBtn" class="btn btn-danger">
          <i class="fas fa-trash-alt me-1"></i> حذف جميع الملفات المكررة
        </button>
        <a href="#" id="downloadDuplicatesLink" class="btn btn-warning" target="_blank" style="display: none;">
          <i class="fas fa-download me-1"></i> تحميل جميع الملفات المكررة
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// إعداد متغيرات الجلسة
let duplicateSessionId = null;

// دالة مساعدة لضمان إظهار أزرار Modal
function ensureButtonsVisible() {
    const downloadLink = document.getElementById('downloadDuplicatesLink');
    const deleteBtn = document.getElementById('deleteDuplicatesBtn');
    const note = document.getElementById('duplicateFilesNote');

    if (downloadLink) downloadLink.style.display = 'inline-block';
    if (deleteBtn) deleteBtn.style.display = 'inline-block';
    if (note) note.style.display = 'block';

    console.log('🔧 تم فرض إظهار جميع الأزرار');
}

// دالة لعرض المودال وجلب الملفات المكررة
function showDuplicateFilesModal(sessionId = null, showAll = false) {
    duplicateSessionId = sessionId;
    const listDiv = document.getElementById('duplicateFilesList');
    const downloadLink = document.getElementById('downloadDuplicatesLink');
    const deleteBtn = document.getElementById('deleteDuplicatesBtn');
    const note = document.getElementById('duplicateFilesNote');

    listDiv.innerHTML = '<div class="text-center text-muted">جاري تحميل قائمة الملفات المكررة...</div>';

    // إعداد رابط التنزيل فوراً
    if (downloadLink) {
        const baseUrl = window.location.protocol + '//' + window.location.host;
        const downloadUrl = sessionId ?
            `${baseUrl}/api/duplicate-files/download?session_id=${sessionId}` :
            `${baseUrl}/admin/duplicate-files/download-all`;
        downloadLink.href = downloadUrl;
        downloadLink.download = sessionId ?
            `duplicate_files_${sessionId}.zip` :
            `all_duplicate_files_${Date.now()}.zip`;
        downloadLink.style.display = 'inline-block';
        console.log('🔗 تم إعداد رابط التنزيل:', downloadUrl);
    }

    // إظهار الأزرار
    if (deleteBtn) deleteBtn.style.display = 'inline-block';
    if (note) note.style.display = 'block';

    // استدعاء دالة التأكيد
    ensureButtonsVisible();

    // تحديد URL حسب ما إذا كنا نريد عرض جميع الملفات أم جلسة معينة
    let fetchUrl;
    if (showAll || !sessionId) {
        // عرض جميع الملفات المكررة
        fetchUrl = '/admin/duplicate-files/paginated?per_page=100';
    } else {
        // عرض ملفات جلسة معينة
        fetchUrl = `/api/duplicate-files/summary?session_id=${sessionId}`;
    }

    fetch(fetchUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            console.log('Duplicate files response:', data); // للتشخيص

            let files = [];
            let totalCount = 0;
            let statistics = null;

            // معالجة البيانات حسب نوع الاستجابة
            if (showAll || !sessionId) {
                // استجابة من النظام الجديد (pagination)
                if (data.success && data.data && data.data.files) {
                    files = data.data.files;
                    totalCount = data.data.pagination ? data.data.pagination.total : files.length;
                    statistics = data.data.statistics;
                }
            } else {
                // استجابة من النظام القديم (session-based)
                if (data.success && data.data) {
                    if (data.data.files && data.data.files.length > 0) {
                        files = data.data.files;
                        totalCount = data.data.total_duplicates || files.length;
                    }
                }
            }

            if (files.length > 0) {
                let html = `<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    تم العثور على ${totalCount} ملف مكرر${files.length < totalCount ? ` (عرض ${files.length} من أصل ${totalCount})` : ''} وحفظها في التخزين المؤقت
                </div>`;

                // عرض الإحصائيات إذا كانت متاحة
                if (statistics) {
                    $('#modalTotalFiles').text(statistics.total_files || totalCount);
                    $('#modalTotalImages').text(statistics.total_images || 0);
                    $('#modalTotalDocuments').text(statistics.total_documents || 0);
                    $('#modalTotalSize').text(formatFileSize(statistics.total_size || 0));
                    $('#duplicateStatsCards').show();
                } else {
                    // إحصائيات بسيطة إذا لم تكن متاحة
                    $('#modalTotalFiles').text(totalCount);
                    $('#duplicateStatsCards').show();
                }

                // إضافة رابط للإدارة المتقدمة إذا كان هناك ملفات كثيرة
                if (totalCount > files.length) {
                    html += `<div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        يتم عرض ${files.length} ملف فقط. للاطلاع على جميع الـ ${totalCount} ملف مكرر،
                        <a href="/admin/duplicate-files" target="_blank" class="alert-link">
                            <i class="fas fa-external-link-alt me-1"></i>
                            انتقل إلى الإدارة المتقدمة
                        </a>
                    </div>`;
                }

                html += '<div class="table-responsive">';
                html += '<table class="table table-striped table-hover">';
                html += `<thead class="table-warning">
                    <tr>
                        <th style="width: 80px;">معاينة</th>
                        <th>اسم الملف المكرر</th>
                        <th>الملف الأصلي</th>
                        <th>المسار الكامل</th>
                        <th>النوع</th>
                        <th>حجم الملف</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>`;
                html += '<tbody>';

                files.forEach((file, index) => {
                    const fileSize = file.file_size ? formatFileSize(file.file_size) : 'غير محدد';
                    const createdAt = file.created_at ? new Date(file.created_at).toLocaleString('ar-SA') : 'غير محدد';
                    const originalName = file.original_name || 'غير محدد';
                    const tempFileName = file.temp_filename || file.duplicate_name || 'غير محدد';
                    const folderPath = file.folder_path || file.original_folder || 'غير محدد';
                    const fullPath = file.temp_path || 'غير محدد';
                    const mimeType = file.mime_type || 'غير معروف';
                    const isExpired = file.expires_at && new Date(file.expires_at) < new Date();
                    const isMissing = file.file_missing === true;

                    const rowClass = isMissing ? 'table-warning' : (isExpired ? 'table-danger' : '');
                    const statusText = isMissing ? 'ملف مفقود' : (isExpired ? 'منتهي الصلاحية' : 'نشط');
                    const statusClass = isMissing ? 'bg-warning text-dark' : (isExpired ? 'bg-danger' : 'bg-success');

                    // تحديد نوع الملف للأيقونة
                    const isImage = mimeType.startsWith('image/');
                    const fileIcon = getFileIcon(mimeType);
                    const fileTypeLabel = getFileTypeLabel(mimeType);

                    // إنشاء معاينة للملف
                    let previewHtml = '';
                    if (isImage && file.preview_url) {
                        previewHtml = `<img src="${file.preview_url}" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;" alt="معاينة" onclick="showImagePreview('${file.preview_url}', '${originalName}')">`;
                    } else if (isImage && file.temp_path) {
                        // محاولة عرض الصورة من المسار المباشر (إذا كان متاحاً)
                        previewHtml = `<img src="/storage/${file.temp_path}" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;" alt="معاينة" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" onclick="showImagePreview('/storage/${file.temp_path}', '${originalName}')">
                                      <div class="d-flex align-items-center justify-content-center bg-light border rounded" style="width: 60px; height: 60px; display: none;">
                                          <i class="${fileIcon} text-muted"></i>
                                      </div>`;
                    } else {
                        previewHtml = `<div class="d-flex align-items-center justify-content-center bg-light border rounded" style="width: 60px; height: 60px;">
                                          <i class="${fileIcon} text-muted"></i>
                                      </div>`;
                    }

                    const downloadBtn = !isMissing && !isExpired ?
                        `<a href="${file.download_url || '#'}" class="btn btn-sm btn-outline-primary me-1" download title="تحميل الملف">
                            <i class="fas fa-download"></i>
                        </a>` :
                        '<span class="text-muted small">غير متاح</span>';

                    html += `<tr class="${rowClass}">
                        <td>${previewHtml}</td>
                        <td>
                            <div>
                                <strong class="${isMissing ? 'text-warning' : (isExpired ? 'text-danger' : 'text-success')}">${tempFileName}</strong>
                                ${isMissing || isExpired ? `<br><span class="badge ${statusClass} mt-1">${statusText}</span>` : ''}
                            </div>
                        </td>
                        <td>
                            <small class="text-muted">${originalName}</small>
                        </td>
                        <td>
                            <div>
                                <small class="text-primary" title="${fullPath}">
                                    <i class="fas fa-folder me-1"></i>${folderPath}
                                </small>
                                <br>
                                <code class="small text-muted" style="font-size: 0.7rem;">${fullPath}</code>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">${fileTypeLabel}</span>
                            <br>
                            <small class="text-muted">${mimeType}</small>
                        </td>
                        <td>
                            <small>${fileSize}</small>
                        </td>
                        <td>
                            <small>${createdAt}</small>
                        </td>
                        <td>
                            <span class="badge ${statusClass}">${statusText}</span>
                        </td>
                        <td>
                            <div class="btn-group-vertical" role="group">
                                ${isImage ? `<button class="btn btn-sm btn-outline-info" onclick="showImagePreview('${file.preview_url || '/storage/' + file.temp_path}', '${originalName}')" title="معاينة">
                                    <i class="fas fa-eye"></i>
                                </button>` : ''}
                                ${downloadBtn}
                            </div>
                        </td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                html += `<div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    هذه الملفات محفوظة مؤقتاً وستنتهي صلاحيتها خلال 7 أيام
                </div>`;

                listDiv.innerHTML = html;

                // تحديث رابط التنزيل وتفعيل الرابط
                const finalDownloadLink = document.getElementById('downloadDuplicatesLink');
                if (finalDownloadLink) {
                    const baseUrl = window.location.protocol + '//' + window.location.host;
                    const downloadUrl = sessionId ?
                        `${baseUrl}/api/duplicate-files/download?session_id=${sessionId}` :
                        `${baseUrl}/admin/duplicate-files/download-all`;
                    finalDownloadLink.href = downloadUrl;
                    finalDownloadLink.download = sessionId ?
                        `duplicate_files_${sessionId}.zip` :
                        `all_duplicate_files_${Date.now()}.zip`;
                    finalDownloadLink.style.display = 'inline-block';
                    console.log('🔗 تم تحديث رابط التنزيل:', downloadUrl);
                }

                deleteBtn.style.display = 'inline-block';
                note.style.display = 'block';
            } else {
                // لا توجد ملفات مكررة
                let message;
                if (showAll || !sessionId) {
                    message = 'لا توجد ملفات مكررة في النظام حالياً.';
                } else {
                    message = 'لا توجد ملفات مكررة لهذه الجلسة.';
                }

                listDiv.innerHTML = `<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    ${message}
                    <br><br>
                    <div class="alert alert-primary mt-2">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>نصيحة:</strong> يمكنك استخدام
                        <button class="btn btn-sm btn-outline-info" onclick="showAllDuplicateFiles()">
                            <i class="fas fa-list me-1"></i>
                            عرض الكل
                        </button>
                        لعرض جميع الملفات المكررة، أو الانتقال إلى
                        <a href="/admin/duplicate-files" target="_blank" class="alert-link">
                            الإدارة المتقدمة
                        </a>
                    </div>
                </div>`;

                // إظهار رابط التحميل والحذف حتى لو لم توجد ملفات مكررة (لأغراض الاختبار)
                const finalDownloadLink = document.getElementById('downloadDuplicatesLink');
                if (finalDownloadLink) {
                    const baseUrl = window.location.protocol + '//' + window.location.host;
                    const downloadUrl = sessionId ?
                        `${baseUrl}/api/duplicate-files/download?session_id=${sessionId}` :
                        `${baseUrl}/admin/duplicate-files/download-all`;
                    finalDownloadLink.href = downloadUrl;
                    finalDownloadLink.download = sessionId ?
                        `duplicate_files_${sessionId}.zip` :
                        `all_duplicate_files_${Date.now()}.zip`;
                    finalDownloadLink.style.display = 'inline-block';
                    console.log('🔗 تم إعداد رابط التنزيل للاختبار:', downloadUrl);
                }

                deleteBtn.style.display = 'inline-block';
                note.style.display = 'block';
                ensureButtonsVisible(); // تأكيد إضافي
            }
        })
        .catch(error => {
            console.error('Error fetching duplicate files:', error);
            listDiv.innerHTML = `<div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                تعذر تحميل الملفات المكررة. يرجى المحاولة مرة أخرى.
                <br><br>
                <div class="alert alert-info mt-2">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>للاختبار:</strong> رغم الخطأ، يمكنك تجربة زر التحميل أدناه
                </div>
            </div>`;

            // إظهار رابط التحميل والحذف حتى في حالة الخطأ (لأغراض الاختبار)
            const sessionId = duplicateSessionId || 'test123';
            const finalDownloadLink = document.getElementById('downloadDuplicatesLink');
            if (finalDownloadLink) {
                const baseUrl = window.location.protocol + '//' + window.location.host;
                const downloadUrl = `${baseUrl}/api/duplicate-files/download?session_id=${sessionId}`;
                finalDownloadLink.href = downloadUrl;
                finalDownloadLink.download = `duplicate_files_${sessionId}.zip`;
                finalDownloadLink.style.display = 'inline-block';
                console.log('🔗 تم إعداد رابط التنزيل (حالة خطأ):', downloadUrl);
            }

            deleteBtn.style.display = 'inline-block';
            note.style.display = 'block';
        });

    // تأكيد إضافي لإظهار الأزرار بعد انتهاء جميع العمليات
    setTimeout(() => {
        const finalDownloadLink = document.getElementById('downloadDuplicatesLink');
        const finalDeleteBtn = document.getElementById('deleteDuplicatesBtn');
        const finalNote = document.getElementById('duplicateFilesNote');

        if (finalDownloadLink) {
            finalDownloadLink.style.display = 'inline-block';
            // التأكد من أن الرابط محدث
            if (duplicateSessionId && !finalDownloadLink.href.includes(duplicateSessionId)) {
                const baseUrl = window.location.protocol + '//' + window.location.host;
                const downloadUrl = `${baseUrl}/api/duplicate-files/download?session_id=${duplicateSessionId}`;
                finalDownloadLink.href = downloadUrl;
                finalDownloadLink.download = `duplicate_files_${duplicateSessionId}.zip`;
                console.log('🔗 تم تحديث رابط التنزيل نهائياً:', downloadUrl);
            }
        }
        if (finalDeleteBtn) finalDeleteBtn.style.display = 'inline-block';
        if (finalNote) finalNote.style.display = 'block';

        console.log('✅ تأكيد نهائي: تم إظهار جميع الأزرار');
    }, 2000);
}

// دالة لعرض جميع الملفات المكررة (بدون تحديد session)
function showAllDuplicateFiles() {
    console.log('🔄 عرض جميع الملفات المكررة...');
    showDuplicateFilesModal(null, true);
}

// دالة لتحديث عدد الملفات في الزر
async function updateFileCount() {
    try {
        const response = await fetch('/admin/duplicate-files/count', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            const totalCount = data.data.total_count;
            const activeCount = data.data.active_count;

            document.getElementById('showAllBtnText').textContent =
                `عرض الكل (${totalCount})`;

            console.log(`📊 إجمالي الملفات المكررة: ${totalCount} (نشط: ${activeCount})`);
        }
    } catch (error) {
        console.warn('تعذر جلب عدد الملفات:', error);
        document.getElementById('showAllBtnText').textContent = 'عرض الكل';
    }
}

// حذف جميع الملفات المكررة (من الجدول والمجلد)
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 تحميل Modal الملفات المكررة - النسخة المُصححة');

    // تحديث عدد الملفات في الزر
    updateFileCount();

    // ربط رابط التنزيل الجديد
    const downloadLink = document.getElementById('downloadDuplicatesLink');
    if (downloadLink) {
        console.log('✅ تم العثور على رابط التنزيل');

        // إضافة event listener لتتبع النقر
        downloadLink.addEventListener('click', function(e) {
            console.log('🖱️ تم النقر على رابط التنزيل');
            console.log('🌐 الرابط:', downloadLink.href);

            // التأكد من وجود href صحيح
            if (!downloadLink.href || downloadLink.href === '#' || downloadLink.href.endsWith('#')) {
                e.preventDefault();
                console.log('⚠️ الرابط غير صحيح، تجربة إعداده...');

                // محاولة إعداد الرابط
                let sessionId = window.duplicateSessionId || duplicateSessionId;
                if (!sessionId) {
                    sessionId = 'modal_session_' + Date.now();
                    console.log('⚠️ تم إنشاء session ID جديد:', sessionId);
                }

                const baseUrl = window.location.protocol + '//' + window.location.host;
                const downloadUrl = `${baseUrl}/api/duplicate-files/download?session_id=${sessionId}`;
                downloadLink.href = downloadUrl;
                downloadLink.download = `duplicate_files_${sessionId}.zip`;
                console.log('🔗 تم إعداد الرابط:', downloadUrl);

                // تنفيذ التنزيل
                window.location.href = downloadUrl;
            }

            // إظهار رسالة تأكيد
            const listDiv = document.getElementById('duplicateFilesList');
            if (listDiv) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success mt-3';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    تم تشغيل التنزيل! يتم الآن تحضير ملف ZIP للتنزيل...
                    <br><small>الرابط: ${downloadLink.href}</small>
                `;
                listDiv.insertBefore(alertDiv, listDiv.firstChild);

                // إزالة الرسالة بعد 10 ثواني
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 10000);
            }
        });

        console.log('✅ تم ربط رابط التنزيل بنجاح');
    } else {
        console.error('❌ لم يتم العثور على رابط التنزيل');
    }

    // تفعيل زر الحذف
    const deleteBtn = document.getElementById('deleteDuplicatesBtn');
    if (deleteBtn) {
        deleteBtn.onclick = function() {
            if (!duplicateSessionId) {
                alert('معرف الجلسة غير متوفر');
                return;
            }

            if (!confirm('هل أنت متأكد من حذف جميع الملفات المكررة نهائياً؟\nلن يمكن استرجاعها بعد الحذف.')) {
                return;
            }

            // إظهار حالة التحميل
            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحذف...';
            deleteBtn.disabled = true;

            fetch(`/api/duplicate-files/delete?session_id=${duplicateSessionId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                console.log('Delete response:', data); // للتشخيص

                if (data.success) {
                    document.getElementById('duplicateFilesList').innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ${data.message}
                            <br><small class="text-muted">تم حذف ${data.data.deleted_files} ملف و ${data.data.deleted_records} سجل من قاعدة البيانات</small>
                        </div>`;
                    document.getElementById('downloadDuplicatesLink').style.display = 'none';
                    deleteBtn.style.display = 'none';
                    document.getElementById('duplicateFilesNote').style.display = 'none';
                    document.getElementById('duplicateStatsCards').style.display = 'none';
                } else {
                    alert('تعذر حذف الملفات المكررة: ' + (data.message || 'خطأ غير معروف'));
                    deleteBtn.innerHTML = originalText;
                    deleteBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('تعذر الاتصال بالخادم لحذف الملفات المكررة.');
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            });
        };
    }
});

// دوال مساعدة للعرض المحسن
function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 بايت';

    const sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));

    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
}

function getFileIcon(mimeType) {
    if (!mimeType) return 'fas fa-file';

    const mime = mimeType.toLowerCase();
    if (mime.startsWith('image/')) return 'fas fa-image text-primary';
    if (mime.startsWith('video/')) return 'fas fa-video text-info';
    if (mime.startsWith('audio/')) return 'fas fa-music text-success';
    if (mime.includes('pdf')) return 'fas fa-file-pdf text-danger';
    if (mime.includes('word') || mime.includes('document')) return 'fas fa-file-word text-primary';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return 'fas fa-file-excel text-success';
    if (mime.includes('powerpoint') || mime.includes('presentation')) return 'fas fa-file-powerpoint text-warning';
    if (mime.includes('zip') || mime.includes('rar') || mime.includes('archive')) return 'fas fa-file-archive text-secondary';

    return 'fas fa-file text-muted';
}

function getFileTypeLabel(mimeType) {
    if (!mimeType) return 'غير معروف';

    const mime = mimeType.toLowerCase();
    if (mime.startsWith('image/')) return 'صورة';
    if (mime.startsWith('video/')) return 'فيديو';
    if (mime.startsWith('audio/')) return 'صوت';
    if (mime.includes('pdf')) return 'PDF';
    if (mime.includes('word') || mime.includes('document')) return 'وثيقة';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return 'جدول بيانات';
    if (mime.includes('powerpoint') || mime.includes('presentation')) return 'عرض تقديمي';
    if (mime.includes('zip') || mime.includes('rar') || mime.includes('archive')) return 'أرشيف';

    return 'ملف';
}

function showImagePreview(imageSrc, fileName) {
    // إنشاء modal بسيط لمعاينة الصورة
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">معاينة: ${fileName}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="${imageSrc}" class="img-fluid" alt="معاينة الصورة" style="max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <a href="${imageSrc}" class="btn btn-primary" download="${fileName}">
                        <i class="fas fa-download me-1"></i> تحميل
                    </a>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // إزالة Modal عند إغلاقه
    modal.addEventListener('hidden.bs.modal', function () {
        document.body.removeChild(modal);
    });
}
</script>
