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
                $editUrl = route('admin.records.management.edit', $row->id);
                $showUrl = route('admin.records.management.show', $row->id);
                return '<a href="'.$showUrl.'" class="btn btn-sm btn-info" style="margin-left:5px">عرض</a>' .
                       '<a href="'.$editUrl.'" class="btn btn-sm btn-primary">تعديل</a>';
            })
            ->addColumn('full_name', function($row) {
                return "{$row->data_first_name} {$row->data_father_name} {$row->data_grand_father_name} {$row->data_family_name}";
            })
            ->addColumn('section_name', function($row) {
                return optional($row->section)->description; // القسم من العلاقة
            })
            ->addColumn('request_status_name', function($row) {
                return optional($row->requestStatus)->description; // حالة الطلب من العلاقة
            })
            ->addColumn('relationship_name', function($row) {
                return optional($row->categoryOfRelation)->attribute; // صلة القرابة من العلاقة الجديدة
            })
            ->addColumn('health_status_name', function($row) {
                return optional($row->healthStatus)->description; // الحالة الصحية من العلاقة
            })
            ->addColumn('marital_status_name', function($row) {
                return optional($row->maritalStatus)->description; // الحالة الاجتماعية من العلاقة
            })
            ->addColumn('academic_qualification_name', function($row) {
                return optional($row->academicQualification)->description; // المؤهل العلمي من العلاقة
            })
            ->addColumn('city_name', function($row) {
                return optional($row->city)->city; // المدينة من العلاقة
            })
            ->addColumn('employment_status_name', function($row) {
                return optional($row->employmentStatusBreadwinner)->description; // حالة عمل العائل من العلاقة
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
            Column::make('file_id_number')->title('رقم الملف'),
            Column::make('section_name')->title('القسم'),
            Column::make('request_status_name')->title('حالة الطلب'),
            Column::make('data_id_number')->title('رقم الهوية'),
            Column::make('full_name')->title('الاسم الكامل'),
            Column::make('data_birth_date')->title('تاريخ الميلاد'),
            Column::make('relationship_name')->title('صلة القرابة'),
            Column::make('health_status_name')->title('الحالة الصحية'),
            Column::make('data_phone_number')->title('رقم الجوال'),
            Column::make('data_phone_number')->title('رقم جوال إضافي'),
            Column::make('marital_status_name')->title('الحالة الاجتماعية'),
            Column::make('academic_qualification_name')->title('المؤهل العلمي'),
            Column::make('city_name')->title('المدينة'),
            Column::make('employment_status_name')->title('حالة عمل المعيل'),
            Column::make('data_description_needs')->title('وصف الاحتياج'),
            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(200)
                  ->addClass('text-center')
                  ->title('إجراء'),
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
