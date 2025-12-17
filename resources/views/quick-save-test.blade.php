<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار سريع - الحفظ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>اختبار سريع</h2>
        <button class="btn btn-primary" onclick="quickTest()">اختبار الحفظ الآن</button>
        <pre id="result" class="mt-3 bg-light p-3"></pre>
    </div>

    <script>
        function quickTest() {
            const sponsorId = 5;
            const documents = [
                {document_type_id: 1, is_enabled: true},
                {document_type_id: 2, is_enabled: false},
                {document_type_id: 3, is_enabled: true},
                {document_type_id: 4, is_enabled: false},
                {document_type_id: 5, is_enabled: true}
            ];

            console.log('Sending:', documents);
            $('#result').html('جاري الإرسال...\n' + JSON.stringify(documents, null, 2));

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({documents: documents}),
                dataType: 'json',
                success: function(response) {
                    $('#result').append('\n\n✅ النتيجة:\n' + JSON.stringify(response, null, 2));

                    // التحقق
                    $.get(`/admin/sponsors/${sponsorId}/check-field-settings`, function(check) {
                        $('#result').append('\n\n📊 فحص قاعدة البيانات:\n' + JSON.stringify(check, null, 2));
                    });
                },
                error: function(xhr) {
                    $('#result').append('\n\n❌ خطأ:\n' + JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2));
                }
            });
        }
    </script>
</body>
</html>
