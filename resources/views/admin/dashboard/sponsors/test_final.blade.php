<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>اختبار نظام الجمعيات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .alert { margin-top: 20px; }
        .log-box { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; margin-top: 20px; }
        .log-entry { margin-bottom: 5px; }
        .log-success { color: #00ff00; }
        .log-error { color: #ff4444; }
        .log-info { color: #00aaff; }
        .file-id-display { font-size: 24px; font-weight: bold; color: #007bff; padding: 15px; background: #e7f3ff; border-radius: 5px; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">🧪 اختبار نظام الجمعيات</h1>

        <div class="file-id-display">
            رقم الملف: <span id="fileIdValue">{{ str_pad($file_id, 6, '0', STR_PAD_LEFT) }}</span>
        </div>

        <form id="testForm">
            @csrf
            <input type="hidden" name="file_id" id="fileId" value="{{ $file_id }}">

            <div class="mb-3">
                <label class="form-label">اسم الجمعية <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="sponsor_name" value="جمعية اختبار {{ now()->format('His') }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">الاسم المختصر</label>
                <input type="text" class="form-control" name="sponsor_short_name" value="اختبار">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="text" class="form-control" name="sponsor_phone_number" value="0599123456">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-control" name="sponsor_email" value="test@test.com">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">العنوان</label>
                <textarea class="form-control" name="sponsor_address" rows="2">عنوان تجريبي للاختبار</textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">البنك</label>
                <select class="form-select" name="sponsor_bank_name_id">
                    <option value="">اختر البنك...</option>
                    @foreach ($banks as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->description }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">رقم الحساب البنكي</label>
                <input type="text" class="form-control" name="sponsor_account_bank_number" value="123456789">
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">
                <span class="indicator-label">🚀 اختبار الإرسال</span>
                <span class="indicator-progress" style="display:none;">
                    ⏳ جاري الإرسال... <span class="spinner-border spinner-border-sm"></span>
                </span>
            </button>
        </form>

        <div id="resultBox"></div>

        <div class="log-box" id="logBox">
            <div class="log-entry log-info">📋 جاهز للاختبار...</div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString('ar-SA');
            const color = type === 'success' ? 'log-success' : type === 'error' ? 'log-error' : 'log-info';
            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : '📌';

            $('#logBox').prepend(`<div class="log-entry ${color}">[${timestamp}] ${icon} ${message}</div>`);
        }

        $(document).ready(function() {
            log('📡 تم تحميل الصفحة بنجاح');
            log('🔢 رقم الملف: {{ $file_id }}');

            $('#testForm').on('submit', function(e) {
                e.preventDefault();

                log('🚀 بدء عملية الإرسال...', 'info');

                const formData = new FormData(this);
                log('📦 تم تجهيز البيانات:', 'info');

                for (let pair of formData.entries()) {
                    log(`   ${pair[0]}: ${pair[1]}`, 'info');
                }

                const submitBtn = $('#submitBtn');
                submitBtn.find('.indicator-label').hide();
                submitBtn.find('.indicator-progress').show();
                submitBtn.prop('disabled', true);

                $.ajax({
                    url: '/admin/sponsors',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        log('✅ نجح الإرسال!', 'success');
                        log('📨 الرد: ' + JSON.stringify(response), 'success');

                        $('#resultBox').html(`
                            <div class="alert alert-success mt-3">
                                <h4>✅ نجح الحفظ في قاعدة البيانات!</h4>
                                <p class="mb-0"><strong>الرسالة:</strong> ${response.message}</p>
                                ${response.data ? `<p class="mb-0"><strong>ID:</strong> ${response.data.id}</p>` : ''}
                                ${response.data ? `<p class="mb-0"><strong>file_id:</strong> ${response.data.file_id}</p>` : ''}
                            </div>
                        `);
                    },
                    error: function(xhr) {
                        log('❌ فشل الإرسال!', 'error');
                        log('📛 Status: ' + xhr.status, 'error');
                        log('📛 Response: ' + xhr.responseText, 'error');

                        let errorMsg = 'حدث خطأ غير معروف';

                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors) {
                                errorMsg = '<ul>';
                                $.each(xhr.responseJSON.errors, function(key, errors) {
                                    $.each(errors, function(i, error) {
                                        errorMsg += '<li>' + error + '</li>';
                                        log('⚠️ خطأ في ' + key + ': ' + error, 'error');
                                    });
                                });
                                errorMsg += '</ul>';
                            } else if (xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                                log('⚠️ ' + errorMsg, 'error');
                            }
                        }

                        $('#resultBox').html(`
                            <div class="alert alert-danger mt-3">
                                <h4>❌ فشل الحفظ!</h4>
                                <div>${errorMsg}</div>
                            </div>
                        `);
                    },
                    complete: function() {
                        submitBtn.find('.indicator-label').show();
                        submitBtn.find('.indicator-progress').hide();
                        submitBtn.prop('disabled', false);
                        log('🏁 انتهت العملية', 'info');
                    }
                });
            });
        });
    </script>
</body>
</html>
