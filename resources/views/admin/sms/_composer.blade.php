{{-- يستخدم داخل كل تبويب: @include('admin.sms._composer', ['id' => 'single', 'phone' => true]) --}}
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">اسم المرسل</label>
        <select class="form-select" id="{{ $id }}-sender">
            @foreach($senders as $s) <option value="{{ $s }}">{{ $s }}</option> @endforeach
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label">قالب جاهز</label>
        <select class="form-select tpl-select" data-target="{{ $id }}-message">
            <option value="">— بدون قالب —</option>
            @foreach($templates as $t) <option value="{{ $t->body }}">{{ $t->title }}</option> @endforeach
        </select>
    </div>

    <div class="col-12">
        <label class="form-label">نص الرسالة</label>
        <div class="row g-3 align-items-start">
            <div class="{{ ($phone ?? false) ? 'col-md-7' : 'col-12' }}">
                <textarea id="{{ $id }}-message" class="form-control sms-text" rows="7" maxlength="2000"></textarea>
                <small class="text-muted" id="{{ $id }}-counter"></small>
            </div>
            @if($phone ?? false)
                <div class="col-md-5">
                    @include('admin.sms._phone', ['id' => $id, 'title' => $phoneTitle ?? 'رسالة جديدة'])
                </div>
            @endif
        </div>
    </div>
</div>
