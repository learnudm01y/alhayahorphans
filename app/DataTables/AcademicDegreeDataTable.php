<?php

namespace App\DataTables;

use App\Models\AcademicDegree;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class AcademicDegreeDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('actions', function ($row) {
                return view('admin.dashboard.category_management.partials.actions', compact('row'))->render();
            })
            ->rawColumns(['actions'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(AcademicDegree $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('academicdegree-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->orderBy(0, 'desc')
            ->selectStyleSingle()
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'lengthMenu' => [[10, 25, 50, -1], [10, 25, 50, 'الكل']],
                'language' => [
                    'url' => '//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json',
                    'lengthMenu' => '_MENU_',
                    'search' => 'بحث',
                    'zeroRecords' => 'لا توجد نتائج',
                    'info' => 'عرض _START_ إلى _END_ من _TOTAL_ سجل',
                    'infoEmpty' => 'عرض 0 إلى 0 من 0 سجل',
                    'infoFiltered' => '(تصفية من _MAX_ سجل)',
                    'paginate' => [
                        'first' => 'الأول',
                        'last' => 'الأخير',
                        'next' => 'التالي',
                        'previous' => 'السابق'
                    ]
                ],
                'drawCallback' => 'function() {
                    $(".dataTables_paginate > .pagination").addClass("pagination-rounded justify-content-end");
                }',
                'initComplete' => 'function(settings, json) {
                    $("#academicdegree-table").css("text-align", "right");
                    $("#academicdegree-table").css("direction", "rtl");
                }',
                'select' => false, // منع التحديد/التفاعل
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')
                ->title('الرقم')
                ->addClass('text-center'),
            Column::make('description')
                ->title('الدرجة العلمية')
                ->addClass('text-center'),
            Column::computed('actions')
                ->title('الإجراءات')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'AcademicDegree_' . date('YmdHis');
    }
}
