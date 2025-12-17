<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار زر الحفظ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>🔍 اختبار زر حفظ الوثائق</h2>
        <p>افتح Console (F12) لرؤية النتائج</p>

        <hr>

        <h4>الخطوة 1: محاكاة البطاقات</h4>
        <div id="documents_container">
            <div class="document-card" data-doc-id="1">
                <input class="document-enabled-toggle" type="checkbox" checked>
                <span>وثيقة 1</span>
            </div>
            <div class="document-card" data-doc-id="2">
                <input class="document-enabled-toggle" type="checkbox">
                <span>وثيقة 2</span>
            </div>
            <div class="document-card" data-doc-id="3">
                <input class="document-enabled-toggle" type="checkbox" checked>
                <span>وثيقة 3</span>
            </div>
        </div>

        <hr>

        <h4>الخطوة 2: اختبار الزر</h4>
        <button type="button" class="btn btn-success" id="saveDocumentsBtnFooter">
            حفظ إعدادات الوثائق
        </button>

        <hr>

        <h4>النتيجة:</h4>
        <pre id="result" style="background: #f5f5f5; padding: 15px; min-height: 200px;"></pre>
    </div>

    <script>
        const sponsorId = 5;
        let output = '';

        function log(msg) {
            output = `[${new Date().toLocaleTimeString()}] ${msg}\n` + output;
            $('#result').text(output);
            console.log(msg);
        }

        // محاكاة نفس الكود الموجود في sponsor-fields-management.js
        $('#saveDocumentsBtnFooter').on('click', function() {
            log('🔔 تم النقر على الزر!');

            // جمع البيانات
            const documents = [];
            $('.document-card').each(function() {
                const docId = $(this).data('doc-id');
                const isEnabled = $(this).find('.document-enabled-toggle').is(':checked');

                documents.push({
                    document_type_id: parseInt(docId),
                    is_enabled: isEnabled
                });
            });

            log(`📊 عدد الوثائق: ${documents.length}`);
            log(`📋 البيانات: ${JSON.stringify(documents)}`);

            // إرسال AJAX
            log('📤 جاري الإرسال...');

            $.ajax({
                url: `/admin/sponsors/${sponsorId}/documents`,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify({
                    documents: documents
                }),
                dataType: 'json',
                success: function(response) {
                    log('✅ نجح الحفظ!');
                    log(`📊 النتيجة: ${JSON.stringify(response, null, 2)}`);

                    Swal.fire({
                        icon: 'success',
                        title: 'نجح!',
                        text: response.message
                    });
                },
                error: function(xhr) {
                    log('❌ فشل الحفظ!');
                    log(`📊 الخطأ: ${JSON.stringify(xhr.responseJSON || xhr.responseText, null, 2)}`);

                    Swal.fire({
                        icon: 'error',
                        title: 'فشل!',
                        text: 'حدث خطأ'
                    });
                }
            });
        });

        log('✅ تم تحميل الصفحة - اضغط على الزر للاختبار');
    </script>
</body>
</html>
