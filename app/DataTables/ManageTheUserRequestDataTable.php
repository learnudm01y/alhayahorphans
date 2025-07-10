<?php

namespace App\DataTables;

use App\Models\Data;
use App\Models\ManageTheUserRequest;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class ManageTheUserRequestDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            // Full name column (concatenate fields, handle nulls)
            ->addColumn('full_name', function($row) {
                // استخدم أسماء الأعمدة الصحيحة من جدول data
                $parts = [];
                if (!empty($row->data_first_name)) $parts[] = $row->data_first_name;
                if (!empty($row->data_father_name)) $parts[] = $row->data_father_name;
                if (!empty($row->data_grand_father_name)) $parts[] = $row->data_grand_father_name;
                if (!empty($row->data_family_name)) $parts[] = $row->data_family_name;
                return count($parts) ? implode(' ', $parts) : '-';
            })
            // File number
            ->addColumn('file_number', function($row) {
                return $row->file_id_number ? $row->file_id_number : '-';
            })
            // Guardian ID
            ->addColumn('guardian_id', function($row) {
                return $row->data_id_number ? $row->data_id_number : '-';
            })
            // Request status badge (dynamic from الجدول RequestStatus)
            ->addColumn('request_status_badge', function($row) {
                $status = $row->requestStatus;
                if ($status) {
                    // إذا كان هناك عمود لون في الجدول استخدمه، وإلا استخدم منطق الألوان الافتراضي
                    $color = $status->color ?? 'secondary';
                    $desc = $status->description;
                    if (!$status->color) {
                        switch ($desc) {
                            case 'مقبول': $color = 'success'; break;
                            case 'مرفوض': $color = 'danger'; break;
                            case 'قيد المراجعة': $color = 'warning'; break;
                            case 'جديد': $color = 'info'; break;
                        }
                    }
                    return '<span class="badge badge-' . $color . '">' . e($desc) . '</span>';
                }
                return '<span class="badge badge-secondary">غير محدد</span>';
            })
            // Created at (date only)
            ->addColumn('created_at', function($row) {
                return $row->created_at ? date('Y-m-d', strtotime($row->created_at)) : '-';
            })
            // Updated at (date only)
            ->addColumn('updated_at', function($row) {
                return $row->updated_at ? date('Y-m-d', strtotime($row->updated_at)) : '-';
            })
            // Action column: edit, view, and status dropdown
            ->addColumn('action', function($row) {
                $editUrl = route('admin.records.management.edit', $row->id);
                $showUrl = route('admin.records.management.show', $row->id);
                $statusOptions = '';
                foreach (\App\Models\RequestStatus::all() as $status) {
                    $selected = $row->data_request_status == $status->id ? 'selected' : '';
                    $statusOptions .= '<option value="' . $status->id . '" ' . $selected . '>' . $status->description . '</option>';
                }
                $dropdown = '<select class="form-control change-status mt-3" data-id="' . $row->id . '">' . $statusOptions . '</select>';
                return '
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i> تعديل</a>
                    <a href="' . $showUrl . '" class="btn btn-sm btn-info"><i class="fa fa-eye"></i> عرض</a>
                    ' . $dropdown . '
                ';
            })
            ->rawColumns(['action', 'request_status_badge'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Data $model): QueryBuilder
    {
        // جلب السجلات التي data_user_insert_data = null أو تساوي n_user
        $n_user = 'N_user'; // ثابت مؤقتاً لعرض جميع الأسطر التي تحمل هذه القيمة
        return $model->newQuery()
            ->where(function($q) use ($n_user) {
                $q->whereNull('data_user_insert_data');
                if ($n_user) {
                    $q->orWhere('data_user_insert_data', $n_user);
                }
            })
            ->whereHas('requestStatus', function($q) {
                $q->where('description', '!=', 'مقبول');
            });
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('managetheuserrequest-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    //->dom('Bfrtip')
                    ->orderBy(0 , 'desc')
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print'),
                        // Button::make('reload')
                    ])
                     ->parameters([
                        'select' => false, // منع التحديد/التفاعل
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            // File number
            Column::make('file_number')->title('رقم الملف'),
            // Full name (concatenated)
            Column::make('full_name')->title('الاسم الكامل'),
            // Guardian ID
            Column::make('guardian_id')->title('رقم هوية ولي الأمر'),
            // Request status (with badge)
            Column::make('request_status_badge')->title('حالة الطلب')->orderable(false)->searchable(false),
            // Created/updated
            Column::make('created_at')->title('تاريخ الإنشاء'),
            Column::make('updated_at')->title('تاريخ التعديل'),
            // Action column for edit/view/change status (last column)
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(200)
                ->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'ManageTheUserRequest_' . date('YmdHis');
    }
}
