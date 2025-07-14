<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار رفع الملفات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>اختبار رفع الملفات - نسخة مبسطة</h3>
                    </div>
                    <div class="card-body">
                        <input type="file" id="fileInput" multiple class="form-control mb-3">
                        <button id="uploadBtn" class="btn btn-primary">رفع الملفات</button>
                        <div id="output" class="mt-3 p-3 bg-light border rounded">
                            <p>اختر ملفات واضغط رفع...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log('🎯 بداية اختبار رفع الملفات');

        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            const uploadBtn = document.getElementById('uploadBtn');
            const output = document.getElementById('output');

            uploadBtn.addEventListener('click', async function() {
                const files = fileInput.files;

                if (files.length === 0) {
                    output.innerHTML = '<p class="text-warning">⚠️ يرجى اختيار ملفات أولاً</p>';
                    return;
                }

                output.innerHTML = '<p>🚀 بدء رفع الملفات...</p>';

                try {
                    const formData = new FormData();

                    // إضافة الملفات
                    for (let i = 0; i < files.length; i++) {
                        formData.append('files[]', files[i]);
                        console.log(`📁 ملف ${i + 1}: ${files[i].name}`);
                    }

                    // إضافة البيانات الأخرى
                    const folderName = 'test_upload_' + Date.now();
                    formData.append('folder_name', folderName);

                    console.log('📦 البيانات المرسلة:');
                    for (let [key, value] of formData.entries()) {
                        if (value instanceof File) {
                            console.log(`${key}: ${value.name} (${value.size} bytes)`);
                        } else {
                            console.log(`${key}: ${value}`);
                        }
                    }

                    // التحقق من CSRF token
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    console.log('🔐 CSRF Token:', csrfToken ? 'موجود' : 'غير موجود');

                    // إرسال الطلب
                    console.log('🌐 إرسال الطلب إلى: /api/files/process-folder-duplicates');

                    const response = await fetch('/api/files/process-folder-duplicates', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken || ''
                        }
                    });

                    console.log('📡 استجابة الخادم:', {
                        status: response.status,
                        statusText: response.statusText,
                        ok: response.ok
                    });

                    if (response.ok) {
                        const result = await response.json();
                        console.log('✅ نجح الرفع:', result);
                        output.innerHTML = `
                            <p class="text-success">✅ تم رفع الملفات بنجاح!</p>
                            <pre>${JSON.stringify(result, null, 2)}</pre>
                        `;
                    } else {
                        const errorText = await response.text();
                        console.error('❌ فشل الرفع:', errorText);
                        output.innerHTML = `
                            <p class="text-danger">❌ فشل في رفع الملفات</p>
                            <p>الحالة: ${response.status} ${response.statusText}</p>
                            <pre>${errorText}</pre>
                        `;
                    }

                } catch (error) {
                    console.error('❌ خطأ:', error);
                    output.innerHTML = `
                        <p class="text-danger">❌ خطأ في الاتصال</p>
                        <p>${error.message}</p>
                    `;
                }
            });
        });
    </script>
</body>
</html>
