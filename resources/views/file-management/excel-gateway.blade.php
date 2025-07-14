<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة Excel المتخصصة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .gateway-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="gateway-card p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                        <h2 class="text-primary">بوابة Excel المتخصصة</h2>
                        <p class="text-muted">رفع ومعالجة ملفات Excel مع إعدادات متقدمة</p>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>قيد التطوير:</strong> هذه الصفحة في طور الإنشاء. يرجى استخدام واجهة إدارة الملفات الرئيسية حالياً.
                    </div>

                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-upload fa-2x text-primary mb-3"></i>
                                    <h5>رفع ملفات Excel</h5>
                                    <p class="small text-muted">رفع ملفات Excel للمعالجة</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-database fa-2x text-success mb-3"></i>
                                    <h5>استيراد البيانات</h5>
                                    <p class="small text-muted">استيراد البيانات إلى قاعدة البيانات</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-chart-bar fa-2x text-warning mb-3"></i>
                                    <h5>تقارير ومعاينة</h5>
                                    <p class="small text-muted">معاينة وتحليل البيانات</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-primary me-2" onclick="window.parent.location.href='/file-management/advanced-interface'">
                            <i class="fas fa-arrow-left me-2"></i>العودة إلى إدارة الملفات
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.close()">
                            <i class="fas fa-times me-2"></i>إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
