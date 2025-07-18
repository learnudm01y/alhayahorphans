{{-- @extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        اختبار سرعة الإنترنت - LibreSpeed
                    </h1>
                    <p class="mb-0">قياس سرعة الاتصال بالإنترنت بدقة وشمولية</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Speed Test Interface -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-lg">
                <div class="card-body">
                    <!-- LibreSpeed Interface Container -->
                    <div id="speedtest-container" style="min-height: 600px;">
                        <iframe
                             src="{{ asset('speedtest/index-laravel.html') }}"
                             width="100%"
                             height="600"
                             frameborder="0"
                            style="border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Controls and Information -->
    <div class="row mt-4">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات الاختبار
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li><i class="fas fa-download text-primary me-2"></i>اختبار سرعة التحميل</li>
                        <li><i class="fas fa-upload text-success me-2"></i>اختبار سرعة الرفع</li>
                        <li><i class="fas fa-clock text-warning me-2"></i>قياس الـ Ping</li>
                        <li><i class="fas fa-wave-square text-danger me-2"></i>قياس الـ Jitter</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-server me-2"></i>
                        حالة الخادم
                    </h5>
                </div>
                <div class="card-body">
                    <div id="server-status">
                        <div class="d-flex align-items-center mb-2">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            <span>فحص حالة الخادم...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12 mb-3">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>
                        أدوات إضافية
                    </h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="refreshSpeedTest()">
                        <i class="fas fa-refresh me-2"></i>إعادة تحميل الاختبار
                    </button>
                    <button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="openInNewTab()">
                        <i class="fas fa-external-link-alt me-2"></i>فتح في نافذة جديدة
                    </button>
                    <button class="btn btn-outline-success btn-sm w-100" onclick="checkServerStatus()">
                        <i class="fas fa-check-circle me-2"></i>فحص حالة الخادم
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Settings Panel -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-sliders-h me-2"></i>
                        الإعدادات المتقدمة
                        <button class="btn btn-sm btn-outline-secondary float-end" type="button" data-bs-toggle="collapse" data-bs-target="#advancedSettings">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </h5>
                </div>
                <div class="collapse" id="advancedSettings">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>معلومات تقنية:</h6>
                                <ul class="list-unstyled small">
                                    <li><strong>نوع الاختبار:</strong> LibreSpeed (مفتوح المصدر)</li>
                                    <li><strong>الخادم:</strong> محلي (Laravel)</li>
                                    <li><strong>البروتوكول:</strong> HTTP/HTTPS</li>
                                    <li><strong>دقة القياس:</strong> عالية</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>متطلبات النظام:</h6>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-check text-success me-1"></i>متصفح حديث</li>
                                    <li><i class="fas fa-check text-success me-1"></i>JavaScript مفعل</li>
                                    <li><i class="fas fa-check text-success me-1"></i>اتصال مستقر بالإنترنت</li>
                                    <li><i class="fas fa-check text-success me-1"></i>لا يتطلب Flash أو إضافات</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

#speedtest-container iframe {
    transition: opacity 0.3s ease-in-out;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* تحسينات للأجهزة المحمولة */
@media (max-width: 768px) {
    #speedtest-container iframe {
        height: 500px;
    }

    .card-header h1 {
        font-size: 1.5rem;
    }

    .btn {
        font-size: 0.9rem;
    }
}

/* تأثيرات الأزرار */
.btn {
    transition: all 0.3s ease;
}

    .btn:hover {
    transform: translateY(-1px);
    box-shadow    : 0 4px 8px rgba(0,0,0,0.15);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // فحص حالة الخادم عند تحميل الصفحة
    checkServerStatus();

    // فحص دوري كل 30 ثانية
    setInterval(checkServerStatus, 30000);
});

function refreshSpeedTest() {
    const iframe = document.querySelector('#speedtest-container iframe');
    if (ifr    ame) {
        iframe.style.opacity = '0.5';
        iframe.src = iframe.src;

        iframe.onload = function() {
            iframe.style.opacity = '1';
        };
    }
}

function openInNewTab() {
    window.open('{{ asset("speedtest/index-laravel.html") }}', '_blank');
}

f        unction checkServerStatus() {
    const statusDiv = document.getElementById('server-status');

    fetch('{{ route("admin.speedtest.stats") }}', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-toke    n"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'active') {
            statusDiv.innerHTML = `
                <div class="d-flex align-items-center text-success mb-2">
                    <i class="fas fa-check-circle me-2"></i>
                    <span><strong>الخادم نشط ويعمل</strong></span>
                </div>
                <small class="text-muted">
                    آخر فحص: ${new Date().toLocaleTimeString('ar-EG')}
                </small>
            `;
        } else {
            statusDiv.innerHTML = `
                <div class="d-flex align-items-center text-warning mb-2">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span>حالة الخادم غير مؤكدة</span>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('خطأ في فحص حالة الخادم:', error);
        statusDiv.innerHTML = `
            <div class="d-flex align-items-center text-danger mb-2">
                <i class="fas fa-times-circle me-2"></i>
                <span>فشل في الاتصال بالخادم</span>
            </div>
            <small class="text-muted">
                يرجى التحقق من الاتصال بالإنترنت
            </small>
        `;
    });
}

// إضافة معالج أخطاء لـ iframe
document.querySelector('#speedtest-container iframe').addEventListener('error', function() {
    this.style.border = '2px solid #dc3545';
    const container = document.getElementById('speedtest-container');
    container.innerHTML = `
        <div class="alert alert-danger text-center">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>خطأ في تحميل اختبار السرعة</h4>
            <p>فشل في تحميل واجهة LibreSpeed. يرجى التحقق من:</p>
            <ul class="list-unstyled">
                <li>• اتصال الإنترنت</li>
                <li>• إعدادات الخادم</li>
                <li>• صحة ملفات LibreSpeed</li>
            </ul>
            <button class="btn btn-primary mt-3" onclick="location.reload()">
                <i class="fas fa-refresh me-2"></i>إعادة المحاولة
            </button>
        </div>
    `;
});
</script>
@endsection --}}
