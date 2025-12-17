<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار API الوثائق - Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h1 class="mb-4">اختبار API الوثائق - Debug</h1>

        <div class="card mb-4">
            <div class="card-body">
                <h5>1. جلب إعدادات الوثائق</h5>
                <div class="input-group mb-3">
                    <input type="number" id="sponsorId" class="form-control" placeholder="رقم الجمعية" value="5">
                    <button class="btn btn-primary" onclick="testGet()">جلب</button>
                </div>
                <pre id="getResult" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"></pre>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5>2. حفظ إعدادات تجريبية</h5>
                <button class="btn btn-success" onclick="testSave()">حفظ الوثائق 1 و 3 و 5</button>
                <pre id="saveResult" class="bg-light p-3 rounded mt-3"></pre>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5>3. التحقق من sponsor_field_settings</h5>
                <button class="btn btn-info" onclick="checkDatabase()">فحص قاعدة البيانات</button>
                <pre id="dbResult" class="bg-light p-3 rounded mt-3"></pre>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function testGet() {
            const sponsorId = $('#sponsorId').val();
            $('#getResult').text('جاري التحميل...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#getResult').text(JSON.stringify(response, null, 2));
                    console.log('Response:', response);
                },
                error: function(xhr) {
                    $('#getResult').text('خطأ: ' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                    console.error('Error:', xhr);
                }
            });
        }

        function testSave() {
            const sponsorId = $('#sponsorId').val();
            $('#saveResult').text('جاري الحفظ...');

            const testData = {
                documents: [
                    { document_type_id: 1, is_enabled: true },
                    { document_type_id: 2, is_enabled: false },
                    { document_type_id: 3, is_enabled: true },
                    { document_type_id: 4, is_enabled: false },
                    { document_type_id: 5, is_enabled: true }
                ]
            };

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                contentType: 'application/json',
                data: JSON.stringify(testData),
                success: function(response) {
                    $('#saveResult').text(JSON.stringify(response, null, 2));
                    console.log('Save response:', response);
                    // إعادة جلب البيانات للتحقق
                    setTimeout(testGet, 500);
                },
                error: function(xhr) {
                    $('#saveResult').text('خطأ: ' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                    console.error('Save error:', xhr);
                }
            });
        }

        function checkDatabase() {
            const sponsorId = $('#sponsorId').val();
            $('#dbResult').text('جاري الفحص...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/field-settings-check`,
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#dbResult').text(JSON.stringify(response, null, 2));
                },
                error: function(xhr) {
                    $('#dbResult').text('خطأ: ' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                }
            });
        }

        // Auto test on load
        $(document).ready(function() {
            testGet();
        });
    </script>
</body>
</html>
