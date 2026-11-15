@extends('admin.dashboard.toolbars.index')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-6">
        <h1 class="h3 fw-bold">
            <i class="fas fa-trash-restore me-2 text-primary"></i>
            سلة فحص الوجوه
        </h1>
        <div class="d-flex gap-2">
            <a href="{{ route('attachment-audit.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-right me-2"></i>رجوع لفحص المرفقات
            </a>
            @if ($items->total() > 0)
            <button class="btn btn-danger" id="face-trash-empty">
                <i class="fas fa-trash me-2"></i>حذف كل ما في السلة ({{ $items->total() }})
            </button>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="alert alert-warning py-2">
                الحذف النهائي هنا لا يسترجع. الملفات تبقى قابلة للاسترجاع حتى تاريخ انتهائها، وكل حذف يقتصر على قاعدة البيانات وملفات السلة المحلية فقط — لا يمس Google Drive إطلاقاً.
            </div>

            @if ($rows->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="fas fa-trash-restore fa-3x mb-3 d-block"></i>
                    السلة فارغة — لا توجد صور محذوفة من فحص الوجوه.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-bordered table-striped text-center align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>معاينة</th>
                                <th>المرفق</th>
                                <th>رقم الهوية</th>
                                <th>اسم الملف الأصلي</th>
                                <th>الحجم</th>
                                <th>حُذف في</th>
                                <th>ينتهي الاسترجاع</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                            <tr id="face-trash-row-{{ $row['id'] }}">
                                <td style="width: 110px;">
                                    @if ($row['file_size'] !== null)
                                    <img src="{{ $row['preview_url'] }}" alt="معاينة" loading="lazy"
                                         style="max-height: 80px; max-width: 100px; object-fit: contain;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                    <i class="fas fa-file-image fa-2x text-muted" style="display:none;"></i>
                                    @else
                                    <i class="fas fa-file-image fa-2x text-muted"></i>
                                    @endif
                                </td>
                                <td>#{{ $row['attachment_id'] }}</td>
                                <td dir="ltr">{{ $row['person'] }}</td>
                                <td class="text-start" dir="ltr">{{ $row['original_name'] }}</td>
                                <td>{{ $row['file_size'] !== null ? number_format($row['file_size'] / 1024, 1) . ' KB' : '—' }}</td>
                                <td>{{ $row['trashed_at'] }}</td>
                                <td>
                                    {{ $row['restore_deadline_at'] }}
                                    @if ($row['expired'])
                                        <br><span class="badge bg-danger">انتهت المدة</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary face-trash-restore-btn"
                                            data-restore-url="{{ $row['restore_url'] }}"
                                            @if ($row['expired']) disabled @endif>
                                        <i class="fas fa-undo me-1"></i>استرجاع
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

@push('scriptsCode')
<script>
    $(document).ready(function() {
        $('#face-trash-empty').on('click', function() {
            Swal.fire({
                title: 'حذف كل ما في السلة نهائياً؟',
                text: 'سيُحذف {{ $items->total() }} عنصر نهائياً من قاعدة البيانات وملفات السلة المحلية، ولا يمكن التراجع.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف الكل',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#d33'
            }).then(function(result) {
                if (!result.isConfirmed) return;

                $.post("{{ route('attachment-audit.face-trash.empty') }}")
                    .done(function(response) {
                        Swal.fire('تم الإفراغ', response.message || 'أُفرغت السلة.', 'success')
                            .then(function() { window.location.reload(); });
                    })
                    .fail(function(xhr) {
                        Swal.fire('تعذر الإفراغ', xhr.responseJSON?.message || 'حدث خطأ غير متوقع', 'error');
                    });
            });
        });

        $('.face-trash-restore-btn').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true);
            $.post($btn.data('restore-url'))
                .done(function(response) {
                    Swal.fire('تم الاسترجاع', response.message || 'استُعيد المرفق إلى مكانه.', 'success')
                        .then(function() { window.location.reload(); });
                })
                .fail(function(xhr) {
                    Swal.fire('تعذر الاسترجاع', xhr.responseJSON?.message || 'حدث خطأ غير متوقع', 'error');
                    $btn.prop('disabled', false);
                });
        });
    });
</script>
@endpush
@endsection
