@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">تفاصيل طلب واتساب — <span dir="ltr">{{ $phone }}</span></h4>
        <a href="{{ route('admin.whatsapp.index') }}" class="btn btn-outline-secondary btn-sm">قائمة الطلبات</a>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">ملخص القناة</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-1"><strong>رقم الهاتف:</strong> <span dir="ltr">{{ $phone }}</span></li>
                        <li class="mb-1"><strong>عدد الرسائل:</strong> {{ $messagesCount }}</li>
                        <li class="mb-1"><strong>أول نشاط:</strong> {{ $firstAt ?? '—' }}</li>
                        <li><strong>آخر نشاط:</strong> {{ $lastAt ?? '—' }}</li>
                    </ul>
                </div>
            </div>

            @if($file !== null)
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">الملف المسجّل</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>رقم الملف:</strong> {{ $file['id'] }}</p>
                        <p class="mb-3"><strong>الاسم:</strong> {{ $file['name'] ?? '—' }}</p>
                        @if($file['data_id'] !== null)
                            <a class="btn btn-primary btn-sm"
                               target="_blank"
                               href="{{ route('admin.records.management.show', $file['data_id']) }}">فتح الملف</a>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">المرفقات ({{ $attachments->count() }})</h6>
                    </div>
                    <div class="card-body">
                        @forelse($attachments as $attachment)
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <div class="fw-bold" dir="ltr" style="text-align: right;">{{ $attachment->stored_file_name }}</div>
                                    <small class="text-muted">
                                        {{ $attachment->file_type }}
                                        @if($attachment->file_size)
                                            — {{ number_format($attachment->file_size / 1024, 1) }} KB
                                        @endif
                                    </small>
                                </div>
                                <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ asset($attachment->file_path) }}">عرض</a>
                            </div>
                        @empty
                            <p class="text-muted mb-0">لا توجد مرفقات مخزّنة لهذا الملف.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="card mb-3">
                    <div class="card-body text-muted mb-0">
                        لم يُسجَّل ملف بعد من هذا الرقم (الجلسة قيد المعالجة أو أُلغيت).
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">المحادثة (آخر {{ $logs->total() }} رسالة)</h6>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    @forelse($conversation as $log)
                        <div class="mb-2 p-2 rounded {{ $log->direction === 'inbound' ? 'bg-light' : 'bg-primary bg-opacity-10' }}">
                            <div class="d-flex justify-content-between">
                                <span class="badge {{ $log->direction === 'inbound' ? 'bg-secondary' : 'bg-primary' }}">
                                    {{ $log->direction === 'inbound' ? 'وارد' : 'صادر' }}
                                </span>
                                <small class="text-muted">
                                    {{ optional($log->created_at)->format('Y-m-d H:i:s') }} — {{ $log->type }}
                                </small>
                            </div>
                            @php($lineText = \Illuminate\Support\Str::limit((string) ($log->text ?? ''), 400))
                            <div class="mt-1" style="white-space: pre-wrap;">{{ $lineText !== '' ? $lineText : '—' }}</div>
                            @if($log->status === 'failed')
                                <div class="small text-danger">فشل: {{ $log->error }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">لا توجد رسائل لهذا الرقم.</p>
                    @endforelse
                </div>
                <div class="card-footer d-flex justify-content-center">
                    {{ $logs->links('components.simple-pagination') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
