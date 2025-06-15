<?php

namespace App\DataTables;

use App\Models\Data;
use App\Models\RecordsManagemente;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class RecordsManagementeDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function($row) {
                // $editUrl = route('records_management.edit', $row->id);
                // $deleteUrl = route('records_management.destroy', $row->id);
                // return '
                //     <a href="'.$editUrl.'" class="btn btn-sm btn-primary">تعديل</a>
                //     <form action="'.$deleteUrl.'" method="POST" style="display:inline;">
                //         '.csrf_field().'
                //         '.method_field('DELETE').'
                //         <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'هل أنت متأكد من الحذف؟\')">حذف</button>
                //     </form>
                // ';
            })
            // ->rawColumns(['action'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Data $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('recordsmanagemente-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    //->dom('Bfrtip')
                    ->orderBy(0, 'desc')
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print'),
                        // Button::make('reset'),
                        // Button::make('reload')
                    ])
                    ->parameters([
                        'language' => [
                            'emptyTable' => 'لا توجد بيانات متاحة في الجدول',
                            'zeroRecords' => 'لم يتم العثور على سجلات مطابقة',
                            // يمكنك تخصيص رسائل أخرى هنا إذا رغبت
                        ],
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('file_id_number'),
            Column::make('data_section_id'),
            Column::make('data_request_status'),
            Column::make('data_id_number'),
            Column::make('data_first_name'),
            Column::make('data_father_name'),
            Column::make('data_grand_father_name'),
            Column::make('data_family_name'),
            Column::make('data_birth_date'),
            Column::make('data_relationship'),
            Column::make('data_health_status'),
            Column::make('data_phone_number'),
            Column::make('data_phone_number'),
            Column::make('data_marital_status'),
            Column::make('data_academic_qualification'),
            Column::make('data_city'),
            Column::make('data_employment_status_breadwinner'),
            Column::make('data_description_needs'),
            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(120)
                  ->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'RecordsManagemente_' . date('YmdHis');
    }
}
