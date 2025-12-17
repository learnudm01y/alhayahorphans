<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار API الوثائق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h1 class="mb-4">اختبار API الوثائق</h1>

        <div class="card mb-4">
            <div class="card-body">
                <h5>اختبار جلب إعدادات الوثائق</h5>
                <div class="input-group mb-3">
                    <input type="number" id="sponsorId" class="form-control" placeholder="رقم الجمعية" value="1">
                    <button class="btn btn-primary" onclick="testGetDocuments()">جلب الإعدادات</button>
                </div>
                <pre id="result" class="bg-light p-3 rounded"></pre>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5>اختبار حفظ إعدادات الوثائق</h5>
                <button class="btn btn-success" onclick="testSaveDocuments()">حفظ إعدادات تجريبية</button>
                <pre id="saveResult" class="bg-light p-3 rounded mt-3"></pre>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function testGetDocuments() {
            const sponsorId = $('#sponsorId').val();
            $('#result').text('جاري التحميل...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#result').text(JSON.stringify(response, null, 2));
                },
                error: function(xhr) {
                    $('#result').text('خطأ: ' + JSON.stringify(xhr.responseJSON, null, 2));
                }
            });
        }

        function testSaveDocuments() {
            const sponsorId = $('#sponsorId').val();
            $('#saveResult').text('جاري الحفظ...');

            const testData = {
                documents: [
                    {
                        document_type_id: 1,
                        is_enabled: true,
                        basic_enabled: false,
                        family_enabled: false,
                        deceased_enabled: true,
                        is_required: false,
                        notes: ''
                    },
                    {
                        document_type_id: 3,
                        is_enabled: true,
                        basic_enabled: true,
                        family_enabled: true,
                        deceased_enabled: false,
                        is_required: true,
                        notes: 'إلزامي'
                    }
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
                },
                error: function(xhr) {
                    $('#saveResult').text('خطأ: ' + JSON.stringify(xhr.responseJSON, null, 2));
                }
            });
        }
    </script>
</body>
</html>
