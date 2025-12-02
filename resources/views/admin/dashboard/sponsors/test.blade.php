<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار إضافة جمعية</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; display: none; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>اختبار إضافة جمعية</h1>

        <form id="testForm">
            <div class="form-group">
                <label>رقم الملف (file_id) *</label>
                <input type="number" name="file_id" id="file_id" value="{{ $file_id ?? 1 }}" required>
            </div>

            <div class="form-group">
                <label>اسم الجمعية *</label>
                <input type="text" name="sponsor_name" value="جمعية الخير الاختبارية" required>
            </div>

            <div class="form-group">
                <label>الاسم المختصر</label>
                <input type="text" name="sponsor_short_name" value="جمعية الخير">
            </div>

            <div class="form-group">
                <label>رقم الهاتف</label>
                <input type="text" name="sponsor_phone_number" value="+970599123456">
            </div>

            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="sponsor_email" value="test@example.com">
            </div>

            <div class="form-group">
                <label>العنوان</label>
                <textarea name="sponsor_address" rows="3">غزة - فلسطين</textarea>
            </div>

            <div class="form-group">
                <label>رمز الدولة</label>
                <input type="text" name="country_code" value="PS" maxlength="10">
            </div>

            <button type="submit">إرسال البيانات</button>
        </form>

        <div id="result" class="result"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set CSRF token
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $('#testForm').on('submit', function(e) {
                e.preventDefault();

                const formData = $(this).serialize();
                console.log('=== Sending Data ===');
                console.log('Form Data:', formData);

                $('#result').hide();

                $.ajax({
                    url: '/admin/sponsors',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        console.log('Success:', response);

                        $('#result')
                            .removeClass('error')
                            .addClass('success')
                            .html('<strong>نجح!</strong><br>' + response.message + '<br><pre>' + JSON.stringify(response, null, 2) + '</pre>')
                            .show();
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr);

                        let errorHtml = '<strong>خطأ!</strong><br>';

                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors) {
                                errorHtml += '<ul>';
                                $.each(xhr.responseJSON.errors, function(key, value) {
                                    errorHtml += '<li>' + value.join('<br>') + '</li>';
                                });
                                errorHtml += '</ul>';
                            } else if (xhr.responseJSON.message) {
                                errorHtml += xhr.responseJSON.message;
                            }
                            errorHtml += '<br><pre>' + JSON.stringify(xhr.responseJSON, null, 2) + '</pre>';
                        } else {
                            errorHtml += 'حدث خطأ غير متوقع<br><pre>' + xhr.responseText + '</pre>';
                        }

                        $('#result')
                            .removeClass('success')
                            .addClass('error')
                            .html(errorHtml)
                            .show();
                    }
                });
            });
        });
    </script>
</body>
</html>
