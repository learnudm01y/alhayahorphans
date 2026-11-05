@extends('admin.dashboard.toolbars.index')

@section('content')
    <div class="w-100 px-3 py-4">
        <div class="card shadow border-0 rounded-lg animate__animated animate__fadeIn">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between" style="border-top-right-radius: .5rem; border-top-left-radius: .5rem;">
                <h5 class="mb-0 text-white"><i class="fas fa-exchange-alt me-2 text-white"></i> طلبات نقل الأفراد بين المعيلين</h5>
                <a href="{{ route('admin.manage.user.requests.index') }}" class="btn btn-light btn-sm">
                    <i class="fas fa-list"></i> إدارة التسجيلات
                </a>
            </div>

            <div class="card-body bg-white border-bottom p-3">
                <form method="GET" action="{{ route('admin.member-transfer-requests.index') }}" class="row align-items-end g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="pending" @selected($status === 'pending')>معلّقة</option>
                            <option value="approved" @selected($status === 'approved')>موافَق عليها</option>
                            <option value="rejected" @selected($status === 'rejected')>مرفوضة</option>
                            <option value="failed" @selected($status === 'failed')>تعذّر تنفيذها</option>
                            <option value="cancelled" @selected($status === 'cancelled')>ملغاة</option>
                            <option value="all" @selected($status === 'all')>الكل</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">بحث برقم الهوية أو رقم الملف</label>
                        <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="مثال: 1023456789 أو 500123">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> تطبيق</button>
                        <a href="{{ route('admin.member-transfer-requests.index') }}" class="btn btn-secondary"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </form>
            </div>

            <div class="card-body bg-light p-4">
                @if(!empty($tableMissing))
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        جدول طلبات النقل غير موجود في هذه الاستضافة بعد. نفّذ أمر الترحيل على السيرفر:
                        <code>php artisan migrate --path=database/migrations/2026_11_05_000000_create_member_transfer_requests_table.php</code>
                    </div>
                @elseif($requests->isEmpty())
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> لا توجد طلبات في هذا الوضع.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped align-middle mb-3 text-right" dir="rtl">
                            <thead>
                            <tr>
                                <th>تاريخ الطلب</th>
                                <th>فرد الأسرة</th>
                                <th>من ملف (المصدر)</th>
                                <th>إلى ملف (الهدف)</th>
                                <th>حالة الطلب</th>
                                <th>ملاحظة</th>
                                <th style="width: 190px;">إجراء</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($requests as $row)
                                @php
                                    $snapshot = is_array($row->member_snapshot) ? $row->member_snapshot : [];
                                    $memberName = trim(collect([
                                        $snapshot['first_name'] ?? null,
                                        $snapshot['second_name'] ?? null,
                                        $snapshot['third_name'] ?? null,
                                        $snapshot['last_name'] ?? null,
                                    ])->filter()->implode(' '));
                                    $target = $guardians->get((int) $row->file_id_number);
                                    $source = $row->source_file_id_number ? $guardians->get((int) $row->source_file_id_number) : null;
                                    $memberStillInSource = $liveMembers
                                        ->has(ltrim(trim((string) $row->source_file_id_number), '0') . '|' . ltrim(trim((string) $row->identity_number), '0'));
                                    $canAct = $row->status === \App\Models\MemberTransferRequest::STATUS_PENDING;
                                @endphp
                                <tr>
                                    <td>{{ $row->created_at ? $row->created_at->format('Y-m-d') : '—' }}</td>
                                    <td>
                                        <div class="fw-bold">{{ $memberName !== '' ? $memberName : 'بدون اسم مُرسل' }}</div>
                                        <small class="text-muted">الهوية: {{ $row->identity_number }}</small>
                                    </td>
                                    <td>
                                        <div>{{ $source ? $source->display_name : '—' }}</div>
                                        <small class="text-muted">ملف {{ str_pad((string) $row->source_file_id_number, 6, '0', STR_PAD_LEFT) }}</small>
                                    </td>
                                    <td>
                                        <div>{{ $target ? $target->display_name : '—' }}</div>
                                        <small class="text-muted">
                                            ملف {{ str_pad((string) $row->file_id_number, 6, '0', STR_PAD_LEFT) }}
                                            @if($row->data_id)
                                                · <a href="{{ route('admin.records.management.show', $row->data_id) }}">عرض السجل</a>
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        @switch($row->status)
                                            @case('pending')
                                                <span class="badge badge-warning">معلّق</span>
                                                @break
                                            @case('approved')
                                                <span class="badge badge-success">منقول</span>
                                                @break
                                            @case('rejected')
                                                <span class="badge badge-danger">مرفوض</span>
                                                @break
                                            @case('failed')
                                                <span class="badge badge-dark">تعذّر التنفيذ</span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge badge-secondary">ملغى</span>
                                                @break
                                            @default
                                                <span class="badge badge-secondary">{{ $row->status }}</span>
                                        @endswitch
                                        @if($canAct && !$memberStillInSource)
                                            <div><small class="text-danger">الفرد لم يعد في ملف المصدر</small></div>
                                        @endif
                                    </td>
                                    <td><small>{{ $row->reason ?? '—' }}</small></td>
                                    <td>
                                        @if($canAct)
                                            <button type="button" class="btn btn-sm btn-success approve-transfer"
                                                    data-id="{{ $row->id }}"
                                                    data-member="{{ $memberName !== '' ? $memberName : $row->identity_number }}"
                                                    data-from="{{ str_pad((string) $row->source_file_id_number, 6, '0', STR_PAD_LEFT) }}"
                                                    data-to="{{ str_pad((string) $row->file_id_number, 6, '0', STR_PAD_LEFT) }}">
                                                <i class="fas fa-check"></i> موافقة ونقل
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger reject-transfer"
                                                    data-id="{{ $row->id }}"
                                                    data-member="{{ $memberName !== '' ? $memberName : $row->identity_number }}">
                                                <i class="fas fa-times"></i> رفض
                                            </button>
                                        @else
                                            <span class="text-muted small">لا إجراء</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $requests->links('pagination::bootstrap-4') }}
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scriptsCode')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        .card { border-radius: .75rem; }
        .table { direction: rtl; }
        .table thead th {
            background: #f8fafc;
            color: #1a237e;
            font-weight: 600;
            border-bottom: 2px solid #e3e6f0;
            text-align: right !important;
        }
    </style>
    <script>
        toastr.options = { "positionClass": "toast-top-left", "closeButton": true, "progressBar": true };

        var csrfToken = '{{ csrf_token() }}';
        var approveUrl = '{{ route("admin.member-transfer-requests.approve", ":id") }}';
        var rejectUrl = '{{ route("admin.member-transfer-requests.reject", ":id") }}';

        function postJson(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload || {})
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, status: response.status, body: body };
                });
            });
        }

        $(document).on('click', '.approve-transfer', function () {
            var button = $(this);
            var id = button.data('id');

            Swal.fire({
                title: 'تأكيد النقل',
                text: 'سيُنقل الرابط فقط: الفرد «' + button.data('member') + '» من ملف ' + button.data('from') + ' إلى ملف ' + button.data('to') + '. لا تُعدَّل أي بيانات أخرى.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، انقل',
                cancelButtonText: 'إلغاء'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                button.prop('disabled', true);
                postJson(approveUrl.replace(':id', id), {}).then(function (res) {
                    if (res.ok && res.body.success) {
                        toastr.success(res.body.message);
                        setTimeout(function () { window.location.reload(); }, 900);
                        return;
                    }
                    button.prop('disabled', false);
                    toastr.error(res.body.message || 'تعذّر تنفيذ النقل.');
                }).catch(function () {
                    button.prop('disabled', false);
                    toastr.error('تعذّر الاتصال بالخادم.');
                });
            });
        });

        $(document).on('click', '.reject-transfer', function () {
            var button = $(this);
            var id = button.data('id');

            Swal.fire({
                title: 'رفض طلب النقل',
                input: 'text',
                inputPlaceholder: 'سبب الرفض (اختياري)',
                text: 'سيبقى الفرد «' + button.data('member') + '» في ملف معيله الحالي.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'رفض',
                cancelButtonText: 'إلغاء'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                button.prop('disabled', true);
                postJson(rejectUrl.replace(':id', id), { reason: result.value || null }).then(function (res) {
                    if (res.ok && res.body.success) {
                        toastr.success(res.body.message);
                        setTimeout(function () { window.location.reload(); }, 900);
                        return;
                    }
                    button.prop('disabled', false);
                    toastr.error(res.body.message || 'تعذّر رفض الطلب.');
                }).catch(function () {
                    button.prop('disabled', false);
                    toastr.error('تعذّر الاتصال بالخادم.');
                });
            });
        });
    </script>
@endpush
