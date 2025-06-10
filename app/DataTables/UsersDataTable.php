<?php

namespace App\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class UsersDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('role', function($user) {
                $badgeClass = 'badge bg-secondary';
                if ($user->role === 'admin') {
                    $badgeClass = 'badge bg-success';
                } elseif ($user->role === 'user') {
                    $badgeClass = 'badge bg-primary';
                }
                return '<span class="'.$badgeClass.'">'.e($user->role).'</span>';
            })
            ->addColumn('action', function($user) {
                return view('admin.dashboard.authorization.partials.actions', compact('user'))->render();
            })
            ->rawColumns(['role', 'action'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(User $model): QueryBuilder
    {
        return $model->newQuery()->where('role', 'user')
        ->orderBy('id', 'DESC');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
        // يجب أن يتطابق الـ id مع الـ id في الـ view
        ->setTableId('users-table')
        ->columns($this->getColumns())
        // استخدم minifiedAjax() بدون أي تعليق أو تعديل
        ->minifiedAjax()
        ->orderBy(1)
        ->selectStyleSingle()
        ->parameters([
            'paging' => true,
            'searching' => true,
            'info' => true,
            'responsive' => true,
            'autoWidth' => false,
            'processing' => true,
            'serverSide' => true,
            'stateSave' => true,
            'dom' => 'lrtip',
        ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')->title('المعرف')->searchable(false)->orderable(true)->addClass('text-end'),
            Column::make('name')->title('الاسم')->orderable(true)->addClass('text-end'),
            Column::make('email')->title('البريد الإلكتروني')->orderable(true)->addClass('text-end'),
            Column::make('phone')->title('رقم الجوال')->orderable(false)->addClass('text-end'),
            Column::make('role')->title('الدور')->orderable(false)->addClass('text-end'),
            Column::computed('action')
                ->title('العمليات')
                ->exportable(false)
                ->printable(false)
                ->width(300)
                ->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Users_' . date('YmdHis');
    }
}
