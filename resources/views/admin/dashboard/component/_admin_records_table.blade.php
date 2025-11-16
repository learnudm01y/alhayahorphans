<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle text-center mb-2">
        <thead style="background-color: white !important;">
            <tr style="background-color: white !important;">
                <th style="background-color: white !important; color: #333 !important;">#</th>
                <th style="background-color: white !important; color: #333 !important;">رقم السجل</th>
                <th style="background-color: white !important; color: #333 !important;">الاسم</th>
                <th style="background-color: white !important; color: #333 !important;">خيارات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $rec)
                <tr>
                    <td>{{ ($records->firstItem() ?? 0) + $loop->index }}</td>
                    <td>
                        <span class="fw-bold">{{ $rec->file_id_number }}</span>
                    </td>
                    <td>{{ $rec->data_first_name }} {{ $rec->data_family_name }}</td>
                    <td>
                        <a href="{{ route('admin.records.management.show', $rec->id) }}" class="btn btn-outline-info btn-sm" target="_blank" title="عرض التفاصيل">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('admin.records.management.edit', $rec->id) }}" class="btn btn-outline-primary btn-sm" target="_blank" title="تعديل السجل">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="admin-records-pagination mt-3">
        <style>
            .admin-records-pagination .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 0.25rem;
                margin: 0;
                padding: 0.5rem 0;
                flex-wrap: wrap;
            }
            .admin-records-pagination .page-item {
                margin: 0;
            }
            .admin-records-pagination .page-link {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
                color: #495057;
                background-color: #fff;
                padding: 0.5rem 0.75rem;
                font-size: 0.95rem;
                font-weight: 500;
                transition: all 0.2s ease;
                min-width: 42px;
                text-align: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .admin-records-pagination .page-link:hover {
                background-color: #f8f9fa;
                border-color: #0d6efd;
                color: #0d6efd;
                transform: translateY(-1px);
                box-shadow: 0 2px 6px rgba(13,110,253,0.15);
                text-decoration: none;
            }
            .admin-records-pagination .page-item.active .page-link {
                background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
                border-color: #0d6efd;
                color: #fff;
                font-weight: 600;
                box-shadow: 0 3px 8px rgba(13,110,253,0.3);
                transform: scale(1.05);
            }
            .admin-records-pagination .page-item.disabled .page-link {
                background-color: #f8f9fa;
                border-color: #dee2e6;
                color: #adb5bd;
                cursor: not-allowed;
                opacity: 0.6;
                box-shadow: none;
            }
            .admin-records-pagination .page-item.disabled .page-link:hover {
                background-color: #f8f9fa;
                border-color: #dee2e6;
                color: #adb5bd;
                transform: none;
            }
            .admin-records-pagination .page-link[data-page]:not(.disabled) {
                cursor: pointer;
            }
            .admin-pagination-info {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 1rem;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 0.5rem;
                margin-top: 0.75rem;
                border: 1px solid #dee2e6;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .admin-pagination-info .info-text {
                font-size: 0.9rem;
                color: #495057;
                font-weight: 500;
            }
            .admin-pagination-info .badge {
                font-size: 0.85rem;
                padding: 0.4rem 0.8rem;
                font-weight: 600;
            }
            @media (max-width: 576px) {
                .admin-records-pagination .page-link {
                    padding: 0.4rem 0.6rem;
                    font-size: 0.85rem;
                    min-width: 36px;
                }
                .admin-pagination-info {
                    justify-content: center;
                    text-align: center;
                }
            }
        </style>
        <nav aria-label="سجلات الموظف">
            <ul class="pagination mb-0">
                {{-- زر الصفحة السابقة --}}
                @if ($records->onFirstPage())
                    <li class="page-item disabled">
                        <span class="page-link" aria-label="السابق">
                            <i class="bi bi-chevron-double-right"></i>
                        </span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="{{ $records->currentPage() - 1 }}" aria-label="السابق" title="الصفحة السابقة">
                            <i class="bi bi-chevron-double-right"></i>
                        </a>
                    </li>
                @endif

                {{-- عرض أرقام الصفحات بذكاء --}}
                @php
                    $current = $records->currentPage();
                    $last = $records->lastPage();
                    $delta = 2; // عدد الصفحات التي تظهر قبل وبعد الصفحة الحالية
                @endphp

                {{-- الصفحة الأولى دائماً --}}
                @if($current > $delta + 2)
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="1">1</a>
                    </li>
                    @if($current > $delta + 3)
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    @endif
                @endif

                {{-- الصفحات المحيطة بالصفحة الحالية --}}
                @for ($i = max(1, $current - $delta); $i <= min($last, $current + $delta); $i++)
                    @if ($i == $current)
                        <li class="page-item active" aria-current="page">
                            <span class="page-link">{{ $i }}</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="#" data-page="{{ $i }}">{{ $i }}</a>
                        </li>
                    @endif
                @endfor

                {{-- الصفحة الأخيرة دائماً --}}
                @if($current < $last - $delta - 1)
                    @if($current < $last - $delta - 2)
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    @endif
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="{{ $last }}">{{ $last }}</a>
                    </li>
                @endif

                {{-- زر الصفحة التالية --}}
                @if ($records->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="{{ $records->currentPage() + 1 }}" aria-label="التالي" title="الصفحة التالية">
                            <i class="bi bi-chevron-double-left"></i>
                        </a>
                    </li>
                @else
                    <li class="page-item disabled">
                        <span class="page-link" aria-label="التالي">
                            <i class="bi bi-chevron-double-left"></i>
                        </span>
                    </li>
                @endif
            </ul>
        </nav>
        <div class="admin-pagination-info">
            <span class="info-text">
                <i class="bi bi-info-circle me-1"></i>
                عرض <strong>{{ $records->firstItem() ?? 0 }}</strong> إلى <strong>{{ $records->lastItem() ?? 0 }}</strong>
            </span>
            <span class="info-text">
                من إجمالي <span class="badge bg-primary">{{ $records->total() }}</span> سجل
            </span>
            <span class="info-text">
                صفحة <span class="badge bg-info">{{ $records->currentPage() }}</span> من <span class="badge bg-secondary">{{ $records->lastPage() }}</span>
            </span>
        </div>
    </div>
</div>
