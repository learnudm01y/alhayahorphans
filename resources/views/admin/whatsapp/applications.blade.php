@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">طلبات التسجيل عبر واتساب</h4>
        <form method="get" action="{{ route('admin.whatsapp.index') }}" class="d-flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="بحث برقم الهاتف">
            <button type="submit" class="btn btn-outline-secondary btn-sm">بحث</button>
        </form>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>القناة (واتساب)</th>
                        <th>آخر نشاط</th>
                        <th>عدد الرسائل</th>
                        <th>رقم الملف</th>
                        <th>الاسم</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $row)
                        <tr>
                            <td>{{ $phones->firstItem() + $i }}</td>
                            <td dir="ltr">{{ $row['phone'] }}</td>
                            <td>{{ $row['last_at'] !== '' ? $row['last_at'] : '—' }}</td>
                            <td>{{ $row['messages_count'] }}</td>
                            <td>{{ $row['file_id'] ?? '—' }}</td>
                            <td>{{ $row['name'] ?? '—' }}</td>
                            <td>
                                @if($row['file_id'] !== null)
                                    <span class="badge bg-success">مسجّل</span>
                                @else
                                    <span class="badge bg-secondary">قيد المعالجة</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.whatsapp.show', $row['phone']) }}">التفاصيل</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">لا توجد طلبات بعد</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-3">
                {{ $phones->links('components.simple-pagination') }}
            </div>
        </div>
    </div>
</div>
@endsection
