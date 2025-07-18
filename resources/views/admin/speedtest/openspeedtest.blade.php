@extends('admin.dashboard.toolbars.index')

@section('title', 'اختبار سرعة الإنترنت - OpenSpeedTest')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-white">
                        <i class="fas fa-tachometer-alt"></i>
                        اختبار سرعة الإنترنت - OpenSpeedTest
                    </h3>
                    <div class="card-tools">

                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="row">
                        <div class="col-12">
                            <div id="openspeedtest-container" style="height: 600px; width: 100%;">
                                <!-- سيتم تحميل واجهة OpenSpeedTest هنا -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- تضمين ملفات OpenSpeedTest -->
<link href="{{ asset('openspeedtest/assets/css/app.css') }}" rel="stylesheet" type="text/css">

<!-- تحميل إصلاح مسارات OpenSpeedTest قبل السكريبت الرئيسي -->
<script src="{{ asset('js/openspeedtest-fix.js') }}"></script>

<script>
    // تكوين OpenSpeedTest للعمل مع Laravel Backend
    window.SpeedTestConfig = {
        hostname: '{{ request()->getHost() }}',
        protocol: '{{ request()->isSecure() ? "https" : "http" }}',
        port: '{{ request()->getPort() }}',
        backend: {
            download: '{{ route("admin.openspeedtest.download") }}',
            upload: '{{ route("admin.openspeedtest.upload") }}',
            getip: '{{ route("admin.openspeedtest.getip") }}',
            status: '{{ route("admin.openspeedtest.status") }}'
        }
    };

    console.log('🚀 OpenSpeedTest Configuration:', window.SpeedTestConfig);
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 تهيئة OpenSpeedTest...');

    // تحميل واجهة OpenSpeedTest
    loadOpenSpeedTest();

    function loadOpenSpeedTest() {
        const container = document.getElementById('openspeedtest-container');

        // إنشاء iframe لتحميل OpenSpeedTest
        const iframe = document.createElement('iframe');
        iframe.src = '{{ asset("openspeedtest/index.html") }}';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.frameBorder = '0';
        iframe.allowFullscreen = true;

        container.appendChild(iframe);

        console.log('✅ تم تحميل OpenSpeedTest بنجاح');
    }
});
</script>

<style>
.card {
    box-shadow: 0 4px 8px rgba(0,0,0,0.10);
    border-radius: 10px;
    border: none;
}

.card-header {
    background: linear-gradient(90deg, #232526 0%, #4c6ef5 100%);
    color: #fff;
    border-radius: 10px 10px 0 0;
    padding: 16px 22px;
    text-align: right;
    border-bottom: 3px solid #ffb400;
    box-shadow: 0 2px 8px rgba(76,110,245,0.10);
    position: relative;
    z-index: 1;
}

.card-header .card-title {
    font-weight: 700;
    font-size: 1.5rem;
    color: #ffb400;
    letter-spacing: 0.5px;
    text-shadow: 1px 1px 2px #23252633;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header .card-tools button {
    color: #fff;
    background: #ffb400;
    border-radius: 50%;
    border: none;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.card-header .card-tools button:hover {
    background: #232526;
    color: #ffb400;
}

.card-body {
    padding: 0;
}

#openspeedtest-container {
    background: #fff;
    border-radius: 10px;
    margin: 20px auto;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    max-width: 900px;
}

@media (max-width: 992px) {
    #openspeedtest-container {
        height: 400px;
        margin: 10px auto;
        max-width: 100%;
    }
    .card-header .card-title {
        font-size: 1.1rem;
    }
}

@media (max-width: 576px) {
    #openspeedtest-container {
        height: 250px;
        margin: 5px auto;
        max-width: 100%;
    }
    .card-header {
        font-size: 1rem;
        padding: 8px 10px;
        text-align: center;
    }
    .card-header .card-title {
        font-size: 0.95rem;
    }
}
</style>

<!-- تحميل app-2.5.4.js في نهاية الصفحة بعد جميع العناصر -->
<script src="{{ asset('openspeedtest/assets/js/app-2.5.4.js') }}"></script>
@endsection
