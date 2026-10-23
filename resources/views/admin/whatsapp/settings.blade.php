@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl" style="max-width: 900px;">
    <h4 class="mb-3">إعدادات الواتساب (الربط والتحكم)</h4>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('wa_error'))
        <div class="alert alert-danger">{{ session('wa_error') }}</div>
    @endif
    @if(session('wa_state'))
        <div class="alert alert-info">{{ session('wa_state') }}</div>
    @endif

    @if(session('wa_qr'))
        <div class="card mb-3">
            <div class="card-body text-center">
                <h6>امسح هذا الرمز برقم واتساب المرتبط بالجمعية</h6>
                <img src="{{ session('wa_qr') }}" alt="QR" style="max-width: 280px;" class="img-fluid border rounded">
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            الربط مع Evolution API
            @if($configured)
                <span class="badge bg-success float-start">الإعدادات مكتملة</span>
            @else
                <span class="badge bg-warning float-start">ناقصة</span>
            @endif
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.whatsapp.settings.update') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">عنوان خادم Evolution (API URL)</label>
                    <input type="text" dir="ltr" name="api_url" value="{{ old('api_url', $apiUrl) }}"
                           class="form-control" placeholder="http://localhost:8080">
                </div>

                <div class="mb-3">
                    <label class="form-label">اسم الـInstance</label>
                    <input type="text" dir="ltr" name="instance" value="{{ old('instance', $instance) }}"
                           class="form-control" placeholder="my-instance">
                </div>

                <div class="mb-3">
                    <label class="form-label">مفتاح API (apikey)</label>
                    <input type="password" dir="ltr" name="api_key" value=""
                           class="form-control"
                           placeholder="{{ $hasApiKey ? '••••••••  (محفوظ — اتركه فارغاً للإبقاء عليه)' : 'الصق المفتاح هنا' }}">
                    @if($hasApiKey)
                        <div class="form-text">يوجد مفتاح محفوظ. اترك الحقل فارغاً إذا لم ترد تغييره.</div>
                    @endif
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="auto_replies" value="1" id="sw-auto"
                           @checked(old('auto_replies', $autoReplies))>
                    <label class="form-check-label" for="sw-auto">تفعيل الردود التلقائية (البوت يعمل ويستجيب)</label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="ai_enabled" value="1" id="sw-ai"
                           @checked(old('ai_enabled', $aiEnabled))>
                    <label class="form-check-label" for="sw-ai">تفعيل الذكاء الاصطناعي (خيار: اسأل سؤالاً)</label>
                </div>

                <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">إدارة الاتصال بالرقم</div>
        <div class="card-body d-flex flex-wrap gap-2">
            <form method="post" action="{{ route('admin.whatsapp.settings.check') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">فحص حالة الاتصال</button>
            </form>

            <form method="post" action="{{ route('admin.whatsapp.settings.qr') }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary">عرض QR لربط الرقم</button>
            </form>

            <form method="post" action="{{ route('admin.whatsapp.settings.logout') }}"
                  onsubmit="return confirm('هل تريد فصل الرقم عن خادم Evolution؟');">
                @csrf
                <button type="submit" class="btn btn-outline-danger">فصل الرقم</button>
            </form>
        </div>
    </div>
</div>
@endsection
