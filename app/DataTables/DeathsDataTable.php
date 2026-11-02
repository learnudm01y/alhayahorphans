<?php

namespace App\DataTables;

use App\Models\DeathRecord;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Services\DataTable;

class DeathsDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('civil_matched', fn ($row) =>
                $row->civil_matched
                    ? '<span class="badge bg-success">مدموج</span>'
                    : '<span class="badge bg-secondary">غير مدموج</span>'
            )
            ->addColumn('full_name', fn ($row) => $row->name ?: '—')
            ->addColumn('action', function ($row) {
                $deleteUrl = route('civil-registry.deaths.destroy', $row->id);
                return '
                    <form action="' . $deleteUrl . '" method="POST" style="display:inline;" onsubmit="return confirm(\'هل أنت متأكد من حذف هذا السجل؟\');">
                        ' . csrf_field() . '
                        ' . method_field('DELETE') . '
                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> حذف</button>
                    </form>
                ';
            })
            ->rawColumns(['civil_matched', 'action'])
            ->setRowId('id');
    }

    /**
     * يعرض المتوفين فقط — السجلات ذات الحالة "متوفى".
     * (السجلات ذات الحالة "حي" إن وُجدت لا تظهر في هذه الشاشة)
     */
    public function query(): QueryBuilder
    {
        return DeathRecord::query()
            ->where('status', 'متوفى')
            ->select([
                'deaths_list.id',
                'deaths_list.identity_number',
                'deaths_list.name',
                'deaths_list.status',
                'deaths_list.civil_matched',
                'deaths_list.source_file',
                'deaths_list.created_at',
            ])
            ->orderByDesc('deaths_list.id');
    }

    public function html(): \Yajra\DataTables\Html\Builder
    {
        return $this->builder()
            ->setTableId('deaths-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->processing(true)
            ->serverSide(true)
            ->orderBy(0, 'desc')
            ->pageLength(10)
            ->responsive(true)
            ->autoWidth(false)
            ->parameters([
                'searching'   => true,
                'searchDelay' => 500,
                'stateSave'   => true,
                'lengthMenu'  => [10, 25, 50, 100],
                'select'      => false,
            ])
            ->buttons(['excel', 'csv', 'pdf', 'print', 'reset', 'reload']);
    }

    protected function getColumns(): array
    {
        return [
            \Yajra\DataTables\Html\Column::make('identity_number')->title('رقم الهوية')->searchable(true)->orderable(true)->className('text-center'),
            \Yajra\DataTables\Html\Column::make('full_name')->title('الاسم')->searchable(false)->orderable(false)->className('text-center'),
            \Yajra\DataTables\Html\Column::make('status')->title('الحالة')->searchable(false)->orderable(false)->className('text-center'),
            \Yajra\DataTables\Html\Column::make('civil_matched')->title('الدمج مع السجل المدني')->searchable(false)->orderable(false)->className('text-center'),
            \Yajra\DataTables\Html\Column::make('source_file')->title('الملف المصدر')->searchable(false)->orderable(false)->className('text-center'),
            \Yajra\DataTables\Html\Column::make('created_at')->title('تاريخ الإضافة')->searchable(false)->orderable(false)->className('text-center'),
            \Yajra\DataTables\Html\Column::computed('action')
                ->title('الإجراءات')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Deaths_' . date('YmdHis');
    }
}
