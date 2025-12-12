<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار Google Drive API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .btn-test {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .result-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border-right: 5px solid #667eea;
        }
        .success-box {
            border-right-color: #28a745;
        }
        .error-box {
            border-right-color: #dc3545;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loading.active {
            display: block;
        }
        .file-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        .file-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateX(-5px);
        }
        .badge-custom {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="text-center mb-4">
            <h1 class="text-white mb-2">
                <i class="fab fa-google-drive"></i>
                اختبار Google Drive API
            </h1>
            <p class="text-white">اختبار شامل لجميع وظائف Google Drive</p>
        </div>

        <!-- تنبيه مهم -->
        <div class="alert alert-warning" role="alert">
            <h5><i class="fas fa-exclamation-triangle"></i> ملاحظة مهمة</h5>
            <p><strong>Service Account لا يملك مساحة تخزين خاصة!</strong></p>
            <p>لرفع الملفات، يجب عليك:</p>
            <ol>
                <li>إنشاء مجلد في Google Drive من حسابك الشخصي</li>
                <li>انسخ الـ <strong>Folder ID</strong> من رابط المجلد</li>
                <li>شارك المجلد مع Service Account Email: <code id="serviceEmail"></code></li>
                <li>أعطه صلاحيات <strong>Editor</strong></li>
                <li>استخدم الـ Folder ID عند رفع الملفات</li>
            </ol>
        </div>

        <!-- اختبار الاتصال -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plug"></i>
                    اختبار الاتصال
                </h5>
            </div>
            <div class="card-body">
                <p>اختبر الاتصال بـ Google Drive API والحصول على قائمة الملفات</p>
                <button class="btn btn-primary btn-test" onclick="testConnection()">
                    <i class="fas fa-check-circle"></i>
                    اختبار الاتصال
                </button>
                <div id="connectionResult"></div>
            </div>
        </div>

        <!-- رفع ملف -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cloud-upload-alt"></i>
                    رفع ملف
                </h5>
            </div>
            <div class="card-body">
                <p>اختبر رفع ملف إلى Google Drive (يجب تحديد Folder ID لمجلد مشترك)</p>
                <form id="uploadForm">
                    <div class="mb-3">
                        <label class="form-label">Folder ID (مطلوب)</label>
                        <input type="text" class="form-control" id="uploadFolderId" placeholder="أدخل Folder ID للمجلد المشترك" required>
                        <small class="text-muted">يجب أن يكون المجلد مشترك مع Service Account</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">اختر ملف</label>
                        <input type="file" class="form-control" id="fileInput" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-test">
                        <i class="fas fa-upload"></i>
                        رفع الملف
                    </button>
                </form>
                <div id="uploadResult"></div>
            </div>
        </div>

        <!-- سرد الملفات -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i>
                    سرد الملفات
                </h5>
            </div>
            <div class="card-body">
                <p>عرض قائمة الملفات الموجودة في Google Drive</p>
                <div class="row">
                    <div class="col-md-6">
                        <input type="number" class="form-control mb-3" id="pageSize" placeholder="عدد الملفات (افتراضي: 20)" value="20">
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-info btn-test w-100" onclick="listFiles()">
                            <i class="fas fa-folder-open"></i>
                            عرض الملفات
                        </button>
                    </div>
                </div>
                <div id="listResult"></div>
            </div>
        </div>

        <!-- إنشاء مجلد -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-folder-plus"></i>
                    إنشاء مجلد
                </h5>
            </div>
            <div class="card-body">
                <p>إنشاء مجلد جديد في Google Drive</p>
                <form id="folderForm">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="folderName" placeholder="اسم المجلد" required>
                    </div>
                    <button type="submit" class="btn btn-warning btn-test">
                        <i class="fas fa-plus-circle"></i>
                        إنشاء المجلد
                    </button>
                </form>
                <div id="folderResult"></div>
            </div>
        </div>

        <!-- البحث عن ملفات -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-search"></i>
                    البحث عن ملفات
                </h5>
            </div>
            <div class="card-body">
                <p>البحث عن ملفات بالاسم</p>
                <form id="searchForm">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="searchQuery" placeholder="ابحث عن ملف..." required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            بحث
                        </button>
                    </div>
                </form>
                <div id="searchResult"></div>
            </div>
        </div>

        <!-- حذف ملف -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trash-alt"></i>
                    حذف ملف
                </h5>
            </div>
            <div class="card-body">
                <p>حذف ملف من Google Drive باستخدام File ID</p>
                <form id="deleteForm">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="deleteFileId" placeholder="File ID" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-test">
                        <i class="fas fa-trash"></i>
                        حذف الملف
                    </button>
                </form>
                <div id="deleteResult"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // عرض Service Account Email
        $(document).ready(function() {
            $('#serviceEmail').text('alhayaorphanssystem-125@alhayahorphans.iam.gserviceaccount.com');
        });

        // اختبار الاتصال
        function testConnection() {
            showLoading('connectionResult');
            $.ajax({
                url: '{{ route("google.drive.test.connection") }}',
                method: 'POST',
                success: function(response) {
                    if (response.success) {
                        const sharedDrives = response.data.shared_drives || [];
                        const files = response.data.files || [];

                        $('#connectionResult').html(
                            `<div class="result-box success-box">
                                <h5 class="text-success"><i class="fas fa-check-circle"></i> ${response.message}</h5>

                                <div class="mb-3">
                                    <h6><i class="fas fa-folder-open"></i> Shared Drives المتاحة: ${response.data.shared_drives_count}</h6>
                                    ${sharedDrives.length > 0 ? `
                                        <div class="files-list">
                                            ${sharedDrives.map(drive => `
                                                <div class="file-item">
                                                    <i class="fas fa-hdd text-primary"></i>
                                                    <strong>${drive.name}</strong>
                                                    <span class="badge bg-success badge-custom">${drive.id}</span>
                                                    <button class="btn btn-sm btn-primary" onclick="$('#uploadFolderId').val('${drive.id}')">
                                                        استخدام للرفع
                                                    </button>
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : '<p class="text-muted">لا توجد Shared Drives متاحة</p>'}
                                </div>

                                <div>
                                    <p><strong>عدد الملفات العادية:</strong> ${response.data.files_count}</p>
                                    ${files.length > 0 ?
                                        `<h6 class="mt-3">أول 5 ملفات:</h6>
                                        <div class="files-list">
                                            ${files.map(file => `
                                                <div class="file-item">
                                                    <i class="fas fa-file"></i>
                                                    <strong>${file.name}</strong>
                                                    <span class="badge bg-info badge-custom">${file.id}</span>
                                                </div>
                                            `).join('')}
                                        </div>` : ''
                                    }
                                </div>
                            </div>`
                        );
                    }
                },
                error: function(xhr) {
                    showError('connectionResult', xhr);
                }
            });
        }

        // رفع ملف
        $('#uploadForm').submit(function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('file', $('#fileInput')[0].files[0]);
            formData.append('folder_id', $('#uploadFolderId').val());

            showLoading('uploadResult');
            $.ajax({
                url: '{{ route("google.drive.test.upload") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#uploadResult').html(
                            `<div class="result-box success-box">
                                <h5 class="text-success"><i class="fas fa-check-circle"></i> ${response.message}</h5>
                                <p><strong>اسم الملف:</strong> ${response.data.file_name}</p>
                                <p><strong>File ID:</strong> <code>${response.data.file_id}</code></p>
                                <a href="${response.data.web_view_link}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                    <i class="fas fa-external-link-alt"></i> عرض في Google Drive
                                </a>
                            </div>`
                        );
                        $('#uploadForm')[0].reset();
                    }
                },
                error: function(xhr) {
                    showError('uploadResult', xhr);
                }
            });
        });

        // سرد الملفات
        function listFiles() {
            const pageSize = $('#pageSize').val() || 20;
            showLoading('listResult');
            $.ajax({
                url: '{{ route("google.drive.test.list") }}',
                method: 'GET',
                data: { page_size: pageSize },
                success: function(response) {
                    if (response.success && response.data.files) {
                        $('#listResult').html(
                            `<div class="result-box success-box">
                                <h5 class="text-success"><i class="fas fa-check-circle"></i> تم جلب ${response.data.files.length} ملف</h5>
                                <div class="files-list mt-3">
                                    ${response.data.files.map(file => `
                                        <div class="file-item">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <i class="fas fa-file"></i>
                                                    <strong>${file.name}</strong>
                                                </div>
                                                <div class="col-md-4">
                                                    <span class="badge bg-info badge-custom">${file.id}</span>
                                                </div>
                                                <div class="col-md-2 text-end">
                                                    <button class="btn btn-sm btn-danger" onclick="quickDelete('${file.id}', '${file.name}')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>`
                        );
                    }
                },
                error: function(xhr) {
                    showError('listResult', xhr);
                }
            });
        }

        // إنشاء مجلد
        $('#folderForm').submit(function(e) {
            e.preventDefault();
            const folderName = $('#folderName').val();
            showLoading('folderResult');
            $.ajax({
                url: '{{ route("google.drive.test.folder") }}',
                method: 'POST',
                data: { folder_name: folderName },
                success: function(response) {
                    if (response.success) {
                        $('#folderResult').html(
                            `<div class="result-box success-box">
                                <h5 class="text-success"><i class="fas fa-check-circle"></i> ${response.message}</h5>
                                <p><strong>اسم المجلد:</strong> ${response.data.folder_name}</p>
                                <p><strong>Folder ID:</strong> <code>${response.data.folder_id}</code></p>
                            </div>`
                        );
                        $('#folderForm')[0].reset();
                    }
                },
                error: function(xhr) {
                    showError('folderResult', xhr);
                }
            });
        });

        // البحث
        $('#searchForm').submit(function(e) {
            e.preventDefault();
            const query = $('#searchQuery').val();
            showLoading('searchResult');
            $.ajax({
                url: '{{ route("google.drive.test.search") }}',
                method: 'GET',
                data: { query: query },
                success: function(response) {
                    if (response.success && response.data.files) {
                        $('#searchResult').html(
                            `<div class="result-box success-box">
                                <h5 class="text-success"><i class="fas fa-check-circle"></i> وجد ${response.data.files.length} نتيجة</h5>
                                <div class="files-list mt-3">
                                    ${response.data.files.map(file => `
                                        <div class="file-item">
                                            <i class="fas fa-file"></i>
                                            <strong>${file.name}</strong>
                                            <span class="badge bg-info badge-custom">${file.id}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>`
                        );
                    }
                },
                error: function(xhr) {
                    showError('searchResult', xhr);
                }
            });
        });

        // حذف ملف
        $('#deleteForm').submit(function(e) {
            e.preventDefault();
            const fileId = $('#deleteFileId').val();
            if (confirm('هل أنت متأكد من حذف هذا الملف؟')) {
                showLoading('deleteResult');
                $.ajax({
                    url: '{{ route("google.drive.test.delete") }}',
                    method: 'DELETE',
                    data: { file_id: fileId },
                    success: function(response) {
                        if (response.success) {
                            $('#deleteResult').html(
                                `<div class="result-box success-box">
                                    <h5 class="text-success"><i class="fas fa-check-circle"></i> ${response.message}</h5>
                                </div>`
                            );
                            $('#deleteForm')[0].reset();
                        }
                    },
                    error: function(xhr) {
                        showError('deleteResult', xhr);
                    }
                });
            }
        });

        // حذف سريع
        function quickDelete(fileId, fileName) {
            if (confirm(`هل تريد حذف الملف: ${fileName}؟`)) {
                $.ajax({
                    url: '{{ route("google.drive.test.delete") }}',
                    method: 'DELETE',
                    data: { file_id: fileId },
                    success: function(response) {
                        if (response.success) {
                            alert('تم حذف الملف بنجاح!');
                            listFiles();
                        }
                    },
                    error: function(xhr) {
                        alert('فشل حذف الملف: ' + (xhr.responseJSON?.error || 'خطأ غير معروف'));
                    }
                });
            }
        }

        // عرض Loading
        function showLoading(elementId) {
            $(`#${elementId}`).html(
                `<div class="loading active">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                    <p class="mt-2">جاري المعالجة...</p>
                </div>`
            );
        }

        // عرض خطأ
        function showError(elementId, xhr) {
            const error = xhr.responseJSON || {};
            $(`#${elementId}`).html(
                `<div class="result-box error-box">
                    <h5 class="text-danger"><i class="fas fa-exclamation-circle"></i> ${error.message || 'حدث خطأ'}</h5>
                    ${error.error ? `<p><strong>التفاصيل:</strong> ${error.error}</p>` : ''}
                    <pre>${JSON.stringify(error, null, 2)}</pre>
                </div>`
            );
        }
    </script>
</body>
</html>
