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
                $deleteUrl = route('admin.records.management.delete', $row->id);
                return '
                <div class="dropdown">
                  <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    الإجراءات
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" style="min-width: 140px;">
                    <li>
                      <a class="dropdown-item" style="color:#222;" href="'.$showUrl.'">
                        <i class="bi bi-eye text-info"></i> عرض
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" style="color:#222;" href="'.$editUrl.'">
                        <i class="bi bi-pencil-square text-primary"></i> تعديل
                      </a>
                    </li>
                    <li>
                      <form action="'.$deleteUrl.'" method="POST" style="display:inline;" onsubmit="return confirm(\'هل أنت متأكد من حذف السجل؟\');">
                        '.csrf_field().method_field('DELETE').'
                        <button type="submit" class="dropdown-item text-danger" style="width:100%;text-align:right;">
                          <i class="bi bi-trash text-danger"></i> حذف
                        </button>
                      </form>
                    </li>
                  </ul>
                </div>
                ';
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
        // عرض فقط السجلات التي حالة الطلب لها "مقبول"
        return $model->newQuery()
            ->whereHas('requestStatus', function($q) {
                $q->where('description', 'مقبول');
            });
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
                    // ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print'),
                    ])
                    ->parameters([
                        'language' => [
                            'emptyTable' => 'لا توجد بيانات متاحة في الجدول',
                            'zeroRecords' => 'لم يتم العثور على سجلات مطابقة',
                            // يمكنك تخصيص رسائل أخرى هنا إذا رغبت
                        ],
                        'select' => false,
                        // اجعل العناوين في المنتصف مع اتجاه من اليمين لليسار
                        'initComplete' => "function() {
                            $('#recordsmanagemente-table thead').css({'direction':'rtl'});
                            $('#recordsmanagemente-table thead th').css({'text-align':'center'});
                        }",
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('file_id_number')->title('رقم الملف')->width(150),
            Column::make('section_name')->title('القسم')->width(100),
            Column::make('request_status_name')->title('حالة الطلب')->visible(false)->width(150),
            Column::make('data_id_number')->title('رقم الهوية')->width(180),
            Column::make('full_name')->title('الاسم الكامل')->width(250),
            Column::make('data_birth_date')->title('تاريخ الميلاد')->width(120),
            Column::make('relationship_name')->title('صلة القرابة')->visible(false)->width(120),
            Column::make('health_status_name')->title('الحالة الصحية')->visible(false)->width(120),
            Column::make('data_phone_number')->title('رقم الجوال')->width(150),
            Column::make('data_phone_number')->title('رقم جوال إضافي')->visible(false)->width(150),
            Column::make('marital_status_name')->title('الحالة الاجتماعية')->visible(false)->width(150),
            Column::make('academic_qualification_name')->title('المؤهل العلمي')->visible(false)->width(150),
            Column::make('city_name')->title('المدينة')->width(150),
            Column::make('employment_status_name')->title('حالة عمل المعيل')->visible(false)->width(150),

            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(100)
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
    {
        return 'RecordsManagemente_' . date('YmdHis');
    }

