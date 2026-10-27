@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl" style="max-width: 900px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">إعدادات الرسائل النصية</h4>
        <a href="{{ route('admin.sms.index') }}" class="btn btn-outline-secondary btn-sm">← العودة للإرسال</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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
        <div class="card-header">عام</div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.sms.settings.update') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">المرسل الافتراضي</label>
                    @if($senders)
                        <select name="default_sender" class="form-select">
                            <option value="">— من ملف .env (NSMS_SENDER): {{ $envSender ?: 'غير محدد' }} —</option>
                            @foreach($senders as $s)
                                <option value="{{ $s }}" @selected(old('default_sender', $defaultSender) === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            يُختار هذا المرسل تلقائيًا في كل صناديق الإرسال (فردي/جماعي/Excel ومودال الكفالات).
                        </div>
                    @else
                        <input type="text" class="form-control" dir="ltr" value="{{ $defaultSender }}" disabled>
                        <div class="form-text text-warning">
                            تعذر جلب أسماء المرسلين من الخدمة. تأكد من قيمة <code>NSMS_TOKEN</code> في ملف <code>.env</code>.
                        </div>
                    @endif
                    @error('default_sender') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_bulk" value="1" id="sw-confirm"
                           @checked(old('confirm_bulk', $confirmBulk))>
                    <label class="form-check-label" for="sw-confirm">
                        طلب تأكيد قبل الإرسال الجماعي أو إرسال ملف Excel
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
            </form>
        </div>
    </div>
</div>
@endsection
