<?php

namespace App\DataTables;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class DocumentTypeDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('basic_enabled', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeSwitch', [
                    'row' => $row,
                    'portal' => 'basic'
                ])->render();
            })
            ->addColumn('deceased_enabled', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeSwitch', [
                    'row' => $row,
                    'portal' => 'deceased'
                ])->render();
            })
            ->addColumn('family_enabled', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeSwitch', [
                    'row' => $row,
                    'portal' => 'family'
                ])->render();
            })
            ->addColumn('is_required', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeRequiredSwitch', [
                    'row' => $row
                ])->render();
            })
            ->addColumn('actions', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeActions', compact('row'))->render();
            })
            ->rawColumns(['actions', 'basic_enabled', 'deceased_enabled', 'family_enabled', 'is_required'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(DocumentType $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('documenttype-table')
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
            Column::make('id')
                ->title('الرقم')
                ->addClass('text-center'),
            Column::make('description')
                ->title('انواع الوثائق')
                ->addClass('text-center'),
            Column::make('pref')
                ->title('الاختصار')
                ->addClass('text-center'),
            Column::computed('basic_enabled')
                ->title('البيانات الأساسية')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center'),
            Column::computed('deceased_enabled')
                ->title('المتوفين')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center'),
            Column::computed('family_enabled')
                ->title('أفراد الأسرة')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center'),
            Column::computed('is_required')
                ->title('إجباري / اختياري')
                ->exportable(false)
                ->printable(false)
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
        return 'DocumentType_' . date('YmdHis');
    }
}
