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
    <div class="admin-records-pagination">
        <nav>
            <ul class="pagination justify-content-center mb-0">
                {{-- Previous Page Link --}}
                @if ($records->onFirstPage())
                    <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="#" data-page="{{ $records->currentPage() - 1 }}">&laquo;</a></li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($records->getUrlRange(1, $records->lastPage()) as $page => $url)
                    @if ($page == $records->currentPage())
                        <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="#" data-page="{{ $page }}">{{ $page }}</a></li>
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($records->hasMorePages())
                    <li class="page-item"><a class="page-link" href="#" data-page="{{ $records->currentPage() + 1 }}">&raquo;</a></li>
                @else
                    <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                @endif
            </ul>
        </nav>
    </div>
    <div class="text-muted small">عرض {{ $records->count() }} من {{ $records->total() }} سجل.</div>
</div>
