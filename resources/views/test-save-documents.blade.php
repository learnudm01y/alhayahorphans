<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار حفظ الوثائق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>اختبار حفظ إعدادات الوثائق</h2>

        <div class="card mt-4">
            <div class="card-body">
                <h5>الخطوة 1: اختبار الجلب</h5>
                <button class="btn btn-primary" onclick="testGet()">جلب الإعدادات الحالية</button>
                <pre id="getResult" class="mt-3 bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h5>الخطوة 2: اختبار الحفظ</h5>
                <div class="mb-3">
                    <label>اختر الوثائق المفعلة:</label>
                    <div id="documentsList"></div>
                </div>
                <button class="btn btn-success" onclick="testSave()">حفظ الإعدادات</button>
                <pre id="saveResult" class="mt-3 bg-light p-3"></pre>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h5>الخطوة 3: التحقق من قاعدة البيانات</h5>
                <button class="btn btn-info" onclick="checkDatabase()">فحص قاعدة البيانات</button>
                <pre id="dbResult" class="mt-3 bg-light p-3"></pre>
            </div>
        </div>
    </div>

    <script>
        const sponsorId = 5; // جمعية الهلال الأحمر
        let allDocuments = [];

        function testGet() {
            $('#getResult').html('جاري الجلب...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'GET',
                success: function(response) {
                    $('#getResult').html(JSON.stringify(response, null, 2));

                    if (response.success && response.data) {
                        allDocuments = response.data;
                        displayDocuments();
                    }
                },
                error: function(xhr) {
                    $('#getResult').html('خطأ: ' + JSON.stringify(xhr.responseJSON, null, 2));
                }
            });
        }

        function displayDocuments() {
            let html = '';
            allDocuments.forEach(doc => {
                const checked = doc.is_enabled ? 'checked' : '';
                html += `
                    <div class="form-check">
                        <input class="form-check-input doc-checkbox" type="checkbox" value="${doc.id}"
                               id="doc${doc.id}" ${checked}>
                        <label class="form-check-label" for="doc${doc.id}">
                            ${doc.id} - ${doc.description} (${doc.pref})
                        </label>
                    </div>
                `;
            });
            $('#documentsList').html(html);
        }

        function testSave() {
            $('#saveResult').html('جاري الحفظ...');

            // جمع الوثائق المحددة
            const documents = [];
            $('.doc-checkbox').each(function() {
                documents.push({
                    document_type_id: parseInt($(this).val()),
                    is_enabled: $(this).is(':checked')
                });
            });

            console.log('Sending data:', documents);

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    documents: documents
                }),
                success: function(response) {
                    $('#saveResult').html(JSON.stringify(response, null, 2));

                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'نجح!',
                            text: response.message
                        });

                        // إعادة جلب البيانات للتحقق
                        setTimeout(() => {
                            testGet();
                            checkDatabase();
                        }, 500);
                    }
                },
                error: function(xhr) {
                    $('#saveResult').html('خطأ: ' + JSON.stringify(xhr.responseJSON, null, 2));
                    console.error('Save error:', xhr);
                }
            });
        }

        function checkDatabase() {
            $('#dbResult').html('جاري الفحص...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/check-field-settings`,
                method: 'GET',
                success: function(response) {
                    $('#dbResult').html(JSON.stringify(response, null, 2));
                },
                error: function(xhr) {
                    $('#dbResult').html('خطأ: ' + JSON.stringify(xhr.responseJSON, null, 2));
                }
            });
        }

        // جلب البيانات تلقائياً عند التحميل
        $(document).ready(function() {
            testGet();
        });
    </script>
</body>
</html>
