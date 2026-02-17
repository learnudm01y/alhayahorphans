<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار الصور</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="p-5">
    <div class="container">
        <h1 class="mb-4">اختبار عرض صور التصميم</h1>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        صورة الرأس
                    </div>
                    <div class="card-body">
                        <img src="/storage/report_designs/m2Y1PEU48oxEntKNucYJVEqUH2zVjJPtOVoKK1lk.png"
                             class="img-fluid"
                             onerror="this.src='https://via.placeholder.com/300x200?text=Error+Loading+Image'; this.classList.add('border-danger')">
                        <p class="mt-2">
                            <small class="text-muted">m2Y1PEU48oxEntKNucYJVEqUH2zVjJPtOVoKK1lk.png</small>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        الصورة الرئيسية
                    </div>
                    <div class="card-body">
                        <img src="/storage/report_designs/C72tBtj7v034Ru8s0pGNeqEf2Eb1UI0WqZZXBhwZ.png"
                             class="img-fluid"
                             onerror="this.src='https://via.placeholder.com/300x200?text=Error+Loading+Image'; this.classList.add('border-danger')">
                        <p class="mt-2">
                            <small class="text-muted">C72tBtj7v034Ru8s0pGNeqEf2Eb1UI0WqZZXBhwZ.png</small>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        صورة التذييل
                    </div>
                    <div class="card-body">
                        <img src="/storage/report_designs/xv1VUTiAATndo71ClLC6Uh0lL2SH8hmI1S6nT3hr.png"
                             class="img-fluid"
                             onerror="this.src='https://via.placeholder.com/300x200?text=Error+Loading+Image'; this.classList.add('border-danger')">
                        <p class="mt-2">
                            <small class="text-muted">xv1VUTiAATndo71ClLC6Uh0lL2SH8hmI1S6nT3hr.png</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i>
            <strong>ملاحظة:</strong> إذا ظهرت الصور بشكل صحيح، فهذا يعني أن المسارات تعمل بشكل صحيح.
        </div>

        <div class="mt-4">
            <button class="btn btn-primary" onclick="testAjax()">
                <i class="bi bi-cloud-download"></i> اختبار جلب التصميم عبر AJAX
            </button>
            <div id="ajaxResult" class="mt-3"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function testAjax() {
            $('#ajaxResult').html('<div class="spinner-border"></div> جاري التحميل...');

            $.ajax({
                url: '/admin/sponsors/1/report-design',
                method: 'GET',
                success: function(response) {
                    console.log('Response:', response);

                    if (response.design) {
                        let html = '<div class="alert alert-success">✓ تم جلب التصميم بنجاح!</div>';
                        html += '<pre class="bg-light p-3">' + JSON.stringify(response.design, null, 2) + '</pre>';

                        html += '<div class="row mt-3">';
                        if (response.design.header_image) {
                            html += '<div class="col-md-4"><img src="/storage/' + response.design.header_image + '" class="img-fluid" onerror="this.classList.add(\'border\', \'border-danger\', \'border-3\')"></div>';
                        }
                        if (response.design.main_image) {
                            html += '<div class="col-md-4"><img src="/storage/' + response.design.main_image + '" class="img-fluid" onerror="this.classList.add(\'border\', \'border-danger\', \'border-3\')"></div>';
                        }
                        if (response.design.footer_image) {
                            html += '<div class="col-md-4"><img src="/storage/' + response.design.footer_image + '" class="img-fluid" onerror="this.classList.add(\'border\', \'border-danger\', \'border-3\')"></div>';
                        }
                        html += '</div>';

                        $('#ajaxResult').html(html);
                    } else {
                        $('#ajaxResult').html('<div class="alert alert-warning">لا يوجد تصميم محفوظ</div>');
                    }
                },
                error: function(xhr) {
                    $('#ajaxResult').html('<div class="alert alert-danger">خطأ: ' + (xhr.responseJSON?.message || 'فشل الطلب') + '</div>');
                }
            });
        }
    </script>
</body>
</html>
