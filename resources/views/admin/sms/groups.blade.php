@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">مجموعات الأرقام</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sms.index') }}" class="btn btn-outline-secondary btn-sm">إرسال الرسائل</a>
            <a href="{{ route('admin.sms.templates.index') }}" class="btn btn-outline-secondary btn-sm">القوالب</a>
            <a href="{{ route('admin.sms.logs') }}" class="btn btn-outline-secondary btn-sm">السجل</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0" id="grp-form-title">إضافة مجموعة جديدة</h6></div>
        <div class="card-body">
            <form id="grp-form" method="POST">
                @csrf
                <input type="hidden" id="grp-id" value="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">اسم المجموعة</label>
                        <input type="text" name="name" id="grp-name" class="form-control" required maxlength="120">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">الأرقام (رقم في كل سطر أو بفواصل)</label>
                        <textarea name="numbers" id="grp-numbers" class="form-control" rows="6" required dir="ltr"></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="grp-submit">حفظ</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="grp-cancel">إلغاء التعديل</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>#</th><th>الاسم</th><th>عدد الأرقام</th><th class="text-end">إجراءات</th></tr>
                </thead>
                <tbody>
                    @forelse($groups as $g)
                        <tr data-id="{{ $g->id }}" data-name="{{ $g->name }}" data-numbers="{{ $g->numbers }}">
                            <td>{{ $g->id }}</td>
                            <td>{{ $g->name }}</td>
                            <td>
                                {{ collect(preg_split('/[\s,;،]+/u', $g->numbers, -1, PREG_SPLIT_NO_EMPTY))->filter()->count() }}
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary js-edit">تعديل</button>
                                <form method="POST" class="d-inline js-del" action="{{ route('admin.sms.groups.index') }}/{{ $g->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">لا توجد مجموعات</td></tr>
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
    const BASE = "{{ route('admin.sms.groups.index') }}";
    const $ = s => document.querySelector(s);

    $('#grp-cancel').onclick = () => resetForm();

    document.querySelectorAll('tr[data-id]').forEach(tr => {
        const id = tr.dataset.id;
        tr.querySelector('.js-edit').onclick = () => {
            $('#grp-id').value = id;
            $('#grp-name').value = tr.dataset.name;
            $('#grp-numbers').value = tr.dataset.numbers;
            $('#grp-form').action = BASE + '/' + id;
            $('#grp-submit').textContent = 'تحديث';
            $('#grp-form-title').textContent = 'تعديل المجموعة #' + id;
            $('#grp-cancel').classList.remove('d-none');
            $('#grp-name').focus();
        };
    });

    function resetForm() {
        $('#grp-form').reset();
        $('#grp-id').value = '';
        $('#grp-form').action = BASE;
        $('#grp-submit').textContent = 'حفظ';
        $('#grp-form-title').textContent = 'إضافة مجموعة جديدة';
        $('#grp-cancel').classList.add('d-none');
    }

    $('#grp-form').addEventListener('submit', async e => {
        e.preventDefault();
        const updating = !!$('#grp-id').value;
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
        if (typeof Swal === 'undefined') { if (confirm('حذف هذه المجموعة؟')) f.submit(); return; }
        const r = await Swal.fire({ icon: 'warning', title: 'حذف المجموعة', text: 'سيتم حذف المجموعة نهائيًا.', showCancelButton: true, confirmButtonText: 'حذف', cancelButtonText: 'إلغاء' });
        if (r.isConfirmed) f.submit();
    }));

    resetForm();
});
</script>
@endpush
