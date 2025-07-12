<!-- Modal: عرض الملفات أو المجلدات المكررة أثناء الرفع -->
<div class="modal fade" id="duplicateFilesModal" tabindex="-1" aria-labelledby="duplicateFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title" id="duplicateFilesModalLabel">
          <i class="fas fa-clone text-warning me-2"></i>
          الملفات/المجلدات المكررة المكتشفة أثناء الرفع
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body">
        <div id="duplicateFilesList">
          <div class="text-center text-muted">جاري تحميل قائمة الملفات المكررة...</div>
        </div>
        <div class="alert alert-info mt-3" id="duplicateFilesNote" style="display:none;">
          يمكنك تحميل جميع الملفات المكررة كملف مضغوط أو مراجعتها بشكل فردي.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
        <button type="button" id="deleteDuplicatesBtn" class="btn btn-danger" style="display:none;">
          <i class="fas fa-trash-alt me-1"></i> حذف جميع الملفات المكررة
        </button>
        <a href="#" id="downloadDuplicatesBtn" class="btn btn-warning" style="display:none;">
          <i class="fas fa-download me-1"></i> تحميل جميع الملفات المكررة
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// إعداد متغيرات الجلسة
let duplicateSessionId = null;

// دالة لعرض المودال وجلب الملفات المكررة
function showDuplicateFilesModal(sessionId) {
    duplicateSessionId = sessionId;
    const listDiv = document.getElementById('duplicateFilesList');
    const downloadBtn = document.getElementById('downloadDuplicatesBtn');
    const deleteBtn = document.getElementById('deleteDuplicatesBtn');
    const note = document.getElementById('duplicateFilesNote');
    listDiv.innerHTML = '<div class="text-center text-muted">جاري تحميل قائمة الملفات المكررة...</div>';
    downloadBtn.style.display = 'none';
    deleteBtn.style.display = 'none';
    note.style.display = 'none';

    fetch(`/admin/file/duplicate-summary?session_id=${sessionId}`)
        .then(res => res.json())
        .then(data => {
            console.log('Duplicate files response:', data); // للتشخيص

            if (data.success && data.data && data.data.files && data.data.files.length > 0) {
                const files = data.data.files;
                let html = `<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    تم العثور على ${data.data.total_duplicates} ملف مكرر وحفظها في التخزين المؤقت
                </div>`;

                html += '<div class="table-responsive">';
                html += '<table class="table table-striped table-hover">';
                html += `<thead class="table-warning">
                    <tr>
                        <th>اسم الملف المكرر</th>
                        <th>الملف الأصلي</th>
                        <th>المجلد</th>
                        <th>حجم الملف</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>`;
                html += '<tbody>';

                files.forEach((file, index) => {
                    const fileSize = file.file_size ? (file.file_size / 1024).toFixed(2) + ' KB' : 'غير محدد';
                    const createdAt = file.created_at ? new Date(file.created_at).toLocaleString('ar-SA') : 'غير محدد';
                    const originalName = file.original_name || 'غير محدد';
                    const tempFileName = file.temp_filename || file.duplicate_name || 'غير محدد';
                    const folderPath = file.folder_path || file.original_folder || 'غير محدد';
                    const isMissing = file.file_missing === true;

                    const rowClass = isMissing ? 'table-warning' : '';
                    const statusBadge = isMissing ? '<span class="badge bg-warning text-dark ms-1">ملف مفقود</span>' : '';
                    const downloadBtn = !isMissing && file.download_url ?
                        `<a href="${file.download_url}" class="btn btn-sm btn-outline-primary" download title="تحميل الملف">
                            <i class="fas fa-download"></i>
                        </a>` :
                        '<span class="text-muted small">غير متاح</span>';

                    html += `<tr class="${rowClass}">
                        <td>
                            <i class="fas fa-file ${isMissing ? 'text-warning' : 'text-success'} me-2"></i>
                            <strong>${tempFileName}</strong>
                            ${statusBadge}
                        </td>
                        <td>
                            <small class="text-muted">${originalName}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary">${folderPath}</span>
                        </td>
                        <td>
                            <small>${fileSize}</small>
                        </td>
                        <td>
                            <small>${createdAt}</small>
                        </td>
                        <td>
                            ${downloadBtn}
                        </td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                html += `<div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    هذه الملفات محفوظة مؤقتاً وستنتهي صلاحيتها خلال 7 أيام
                </div>`;

                listDiv.innerHTML = html;
                downloadBtn.href = `/admin/file/download-duplicates?session_id=${sessionId}`;
                downloadBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                note.style.display = 'block';
            } else {
                const message = data.message || 'لا توجد ملفات مكررة حالياً.';
                listDiv.innerHTML = `<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    ${message}
                </div>`;
                downloadBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                note.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching duplicate files:', error);
            listDiv.innerHTML = `<div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                تعذر تحميل الملفات المكررة. يرجى المحاولة مرة أخرى.
            </div>`;
        });
}

// حذف جميع الملفات المكررة (من الجدول والمجلد)
document.addEventListener('DOMContentLoaded', function() {
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

            fetch(`/admin/file/delete-duplicates?session_id=${duplicateSessionId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
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
                    document.getElementById('downloadDuplicatesBtn').style.display = 'none';
                    deleteBtn.style.display = 'none';
                    document.getElementById('duplicateFilesNote').style.display = 'none';
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
</script>
