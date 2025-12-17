<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار مباشر للحفظ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>اختبار مباشر - حفظ إعدادات الوثائق</h2>

        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">اختبار 1: إرسال JSON</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-primary" onclick="testJSON()">إرسال كـ JSON</button>
                <pre id="jsonResult" class="mt-3 bg-light p-3"></pre>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">اختبار 2: إرسال Form Data</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-success" onclick="testFormData()">إرسال كـ Form Data</button>
                <pre id="formResult" class="mt-3 bg-light p-3"></pre>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">اختبار 3: التحقق من قاعدة البيانات</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-info" onclick="checkDB()">فحص قاعدة البيانات</button>
                <pre id="dbResult" class="mt-3 bg-light p-3"></pre>
            </div>
        </div>
    </div>

    <script>
        const sponsorId = 5;

        function testJSON() {
            $('#jsonResult').html('جاري الإرسال...');

            const data = {
                documents: [
                    {document_type_id: 1, is_enabled: true},
                    {document_type_id: 2, is_enabled: false},
                    {document_type_id: 3, is_enabled: true}
                ]
            };

            console.log('Sending JSON:', data);

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify(data),
                dataType: 'json',
                success: function(response) {
                    $('#jsonResult').html('✅ نجح!\n\n' + JSON.stringify(response, null, 2));
                    console.log('JSON Success:', response);
                },
                error: function(xhr) {
                    $('#jsonResult').html('❌ فشل!\n\n' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                    console.error('JSON Error:', xhr);
                }
            });
        }

        function testFormData() {
            $('#formResult').html('جاري الإرسال...');

            const data = {
                'documents[0][document_type_id]': 1,
                'documents[0][is_enabled]': true,
                'documents[1][document_type_id]': 2,
                'documents[1][is_enabled]': false,
                'documents[2][document_type_id]': 3,
                'documents[2][is_enabled]': true
            };

            console.log('Sending Form Data:', data);

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: data,
                success: function(response) {
                    $('#formResult').html('✅ نجح!\n\n' + JSON.stringify(response, null, 2));
                    console.log('Form Data Success:', response);
                },
                error: function(xhr) {
                    $('#formResult').html('❌ فشل!\n\n' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                    console.error('Form Data Error:', xhr);
                }
            });
        }

        function checkDB() {
            $('#dbResult').html('جاري الفحص...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/check-field-settings`,
                method: 'GET',
                success: function(response) {
                    $('#dbResult').html(JSON.stringify(response, null, 2));
                },
                error: function(xhr) {
                    $('#dbResult').html('❌ فشل الفحص\n\n' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                }
            });
        }
    </script>
</body>
</html>
