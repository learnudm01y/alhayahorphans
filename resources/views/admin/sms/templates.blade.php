@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">قوالب الرسائل</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sms.index') }}" class="btn btn-outline-secondary btn-sm">إرسال الرسائل</a>
            <a href="{{ route('admin.sms.groups.index') }}" class="btn btn-outline-secondary btn-sm">المجموعات</a>
            <a href="{{ route('admin.sms.logs') }}" class="btn btn-outline-secondary btn-sm">السجل</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0" id="tpl-form-title">إضافة قالب جديد</h6></div>
        <div class="card-body">
            <form id="tpl-form" method="POST">
                @csrf
                <input type="hidden" id="tpl-id" value="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">عنوان القالب</label>
                        <input type="text" name="title" id="tpl-title" class="form-control" required maxlength="120">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">نص الرسالة</label>
                        <textarea name="body" id="tpl-body" class="form-control" rows="4" required maxlength="2000"></textarea>
                        <small class="text-muted">استخدم <code>{العمود}</code> لربط المتغيرات بأعمدة ملف Excel.</small>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="tpl-active" class="form-check-input" value="1" checked>
                            <label class="form-check-label" for="tpl-active">فعّال (يظهر في شاشة الإرسال)</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="tpl-submit">حفظ</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="tpl-cancel">إلغاء التعديل</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>#</th><th>العنوان</th><th>النص</th><th>الحالة</th><th class="text-end">إجراءات</th></tr>
                </thead>
                <tbody>
                    @forelse($templates as $t)
                        <tr data-id="{{ $t->id }}" data-title="{{ $t->title }}" data-body="{{ $t->body }}" data-active="{{ $t->is_active ? 1 : 0 }}">
                            <td>{{ $t->id }}</td>
                            <td>{{ $t->title }}</td>
                            <td style="max-width:420px">{{ \Illuminate\Support\Str::limit($t->body, 120) }}</td>
                            <td>
                                @if($t->is_active) <span class="badge bg-success">فعّال</span>
                                @else <span class="badge bg-secondary">معطّل</span> @endif
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary js-edit">تعديل</button>
                                <form method="POST" class="d-inline js-del" action="{{ route('admin.sms.templates.index') }}/{{ $t->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">لا توجد قوالب</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scriptsCode')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const BASE = "{{ route('admin.sms.templates.index') }}";
    const $ = s => document.querySelector(s);

    $('#tpl-cancel').onclick = () => resetForm();    document.querySelectorAll('tr[data-id]').forEach(tr => {
        tr.querySelector('.js-edit').onclick = () => {
            $('#tpl-id').value = tr.dataset.id;
            $('#tpl-title').value = tr.dataset.title;
            $('#tpl-body').value = tr.dataset.body;
            $('#tpl-active').checked = tr.dataset.active === '1';
            $('#tpl-form').action = BASE + '/' + tr.dataset.id;
            $('#tpl-submit').textContent = 'تحديث';
            $('#tpl-form-title').textContent = 'تعديل القالب #' + tr.dataset.id;
            $('#tpl-cancel').classList.remove('d-none');
            $('#tpl-title').focus();
        };
    });

    function resetForm() {
        $('#tpl-form').reset();
        $('#tpl-id').value = '';
        $('#tpl-form').action = BASE;
        $('#tpl-submit').textContent = 'حفظ';
        $('#tpl-form-title').textContent = 'إضافة قالب جديد';
        $('#tpl-cancel').classList.add('d-none');
        $('#tpl-active').checked = true;
    }

    $('#tpl-form').addEventListener('submit', async e => {
        e.preventDefault();
        const updating = !!$('#tpl-id').value;
        const fd = new FormData(e.target);
        fd.set('_method', updating ? 'PUT' : 'POST');
        const res = await fetch(e.target.action, {
            method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: fd,
        });
        const d = await res.json().catch(() => ({}));
        if (res.ok) { location.reload(); }
        else { Swal?.fire({ icon: 'error', title: 'تعذر الحفظ', text: d.message || (d.errors && Object.values(d.errors)[0][0]) || '' }); }
    });

    document.querySelectorAll('.js-del').forEach(f => f.addEventListener('submit', async e => {
        e.preventDefault();
        if (typeof Swal === 'undefined') { if (confirm('حذف هذا القالب؟')) f.submit(); return; }
        const r = await Swal.fire({ icon: 'warning', title: 'حذف القالب', text: 'سيتم حذف القالب نهائيًا.', showCancelButton: true, confirmButtonText: 'حذف', cancelButtonText: 'إلغاء' });
        if (r.isConfirmed) f.submit();
    }));

    resetForm();
});
</script>
@endpush
