<?php

namespace App\DataTables;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CityDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $provinces = Province::orderBy('description')->get();

        return (new EloquentDataTable($query))
            ->addColumn('province_ui', function ($row) use ($provinces) {
                $options = '<option value="">اختر المحافظة</option>';

                foreach ($provinces as $province) {
                    $selected = ($row->province_id == $province->id) ? 'selected' : '';
                    $options .= '<option value="' . e($province->id) . '" ' . $selected . '>' . e($province->description) . '</option>';
                }

                return '<select class="form-select form-select-sm city-province-ui" data-city-id="' . e($row->id) . '" style="min-width: 180px;">' . $options . '</select>';
            })
            ->addColumn('actions', function ($row) {
                return view('admin.dashboard.category_management.partials.cityActions', compact('row'))->render();
            })
            ->filterColumn('province_ui', function ($query, $keyword) {
                $query->whereHas('province', function ($q) use ($keyword) {
                    $q->where('description', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['province_ui', 'actions'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(City $model): QueryBuilder
    {
        return $model->newQuery()->with('province');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('city-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(0, 'desc')
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print')
                    ])
                    ->parameters([
                        'select' => false,
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
            Column::make('city')
                ->title('اسم المدينة')
                ->addClass('text-center'),
            Column::make('province_ui')
                ->title('المحافظة')
                ->orderable(false)
                ->searchable(true)
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
        return 'City_' . date('YmdHis');
    }
}
