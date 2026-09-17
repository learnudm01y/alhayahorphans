<?php

namespace App\DataTables;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class DocumentTypeDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     * @return EloquentDataTable
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('file_type', function ($row) {
                $isImage = ($row->file_type === 'image');
                $docActive = !$isImage ? 'btn-primary active' : 'btn-outline-secondary';
                $imgActive = $isImage ? 'btn-info text-white active' : 'btn-outline-secondary';

                return '
                <div class="btn-group btn-group-sm file-type-toggle-group" role="group" data-id="' . $row->id . '">
                    <button type="button" class="btn ' . $docActive . ' document-type-btn" data-value="document" title="وثيقة">
                        <i class="fas fa-file-alt"></i> وثيقة
                    </button>
                    <button type="button" class="btn ' . $imgActive . ' document-type-btn" data-value="image" title="صورة">
                        <i class="fas fa-image"></i> صورة
                    </button>
                </div>
                ';
            })
            ->addColumn('basic_enabled', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeSwitchWithRequired', [
                    'row' => $row,
                    'portal' => 'basic'
                ])->render();
            })
            ->addColumn('deceased_enabled', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeSwitchWithRequired', [
                    'row' => $row,
                    'portal' => 'deceased'
                ])->render();
            })
            ->addColumn('family_enabled', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeSwitchWithRequired', [
                    'row' => $row,
                    'portal' => 'family'
                ])->render();
            })
            ->addColumn('actions', function ($row) {
                return view('admin.dashboard.category_management.partials.DocumentTypeActions', compact('row'))->render();
            })
            ->rawColumns([
                'file_type',
                'basic_enabled',
                'deceased_enabled',
                'family_enabled',
                'actions',
            ])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @param DocumentType $model
     * @return QueryBuilder
     */
    public function query(DocumentType $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     *
     * @return HtmlBuilder
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('documenttype-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'desc')
            ->selectStyleSingle()
            ->buttons([
                Button::make('excel'),
                Button::make('csv'),
                Button::make('pdf'),
                Button::make('print'),
            ])
            ->parameters([
                'select' => false,
            ]);
    }

    /**
     * Get the dataTable columns definition.
     *
     * @return array
     */
    protected function getColumns(): array
    {
        return [
            Column::make('id')
                ->title('الرقم')
                ->addClass('text-center'),

            Column::make('description')
                ->title('أنواع الوثائق')
                ->addClass('text-center'),

            Column::make('pref')
                ->title('الاختصار')
                ->addClass('text-center'),

            Column::computed('file_type')
                ->title('طبيعة الملف')
                ->exportable(true)
                ->printable(true)
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

            Column::computed('actions')
                ->title('الإجراءات')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'DocumentType_' . date('YmdHis');
    }
}
