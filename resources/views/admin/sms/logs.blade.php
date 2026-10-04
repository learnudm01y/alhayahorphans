@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">سجل الرسائل</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sms.index') }}" class="btn btn-outline-secondary btn-sm">إرسال الرسائل</a>
            <a href="{{ route('admin.sms.templates.index') }}" class="btn btn-outline-secondary btn-sm">القوالب</a>
            <a href="{{ route('admin.sms.groups.index') }}" class="btn btn-outline-secondary btn-sm">المجموعات</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الوقت</th>
                        <th>المستخدم</th>
                        <th>النوع</th>
                        <th>المرسل</th>
                        <th>الرقم/المستلمون</th>
                        <th>الحالة</th>
                        <th>الرسالة</th>
                        <th>مرجع المزود</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $l)
                        <tr>
                            <td>{{ $l->id }}</td>
                            <td>{{ optional($l->created_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $l->user?->name ?? '—' }}</td>
                            <td>{{ $l->type === 'excel' ? 'Excel' : ($l->type === 'bulk' ? 'جماعي' : 'فردي') }}</td>
                            <td>{{ $l->sender }}</td>
                            <td>
                                {{ $l->recipients > 1 ? $l->recipients.' رقم' : $l->mobile }}
                            </td>
                            <td>
                                @if($l->status === 'sent' || $l->status === 'accepted')
                                    <span class="badge bg-success">مقبول</span>
                                @elseif($l->status === 'failed')
                                    <span class="badge bg-danger">فشل</span>
                                @else
                                    <span class="badge bg-secondary">{{ $l->status }}</span>
                                @endif
                                @if($l->error) <div class="small text-danger">{{ \Illuminate\Support\Str::limit($l->error, 80) }}</div> @endif
                            </td>
                            <td style="max-width:320px">{{ \Illuminate\Support\Str::limit($l->message, 90) }}</td>
                            <td dir="ltr">{{ $l->request_id ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">لا توجد سجلات بعد</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-3">
                {{ $logs->links('components.simple-pagination') }}
            </div>
        </div>
    </div>
</div>
@endsection
