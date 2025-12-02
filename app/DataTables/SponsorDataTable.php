<?php

namespace App\DataTables;

use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SponsorDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('bank_name', function ($row) {
                return $row->bankName ? $row->bankName->description : '-';
            })
            ->editColumn('sponsor_short_name', function ($row) {
                return $row->sponsor_short_name ?: '-';
            })
            ->editColumn('sponsor_phone_number', function ($row) {
                return $row->sponsor_phone_number ?: '-';
            })
            ->editColumn('sponsor_email', function ($row) {
                return $row->sponsor_email ?: '-';
            })
            ->editColumn('sponsor_account_bank_number', function ($row) {
                return $row->sponsor_account_bank_number ?: '-';
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at->format('Y-m-d H:i:s');
            })
            ->addColumn('actions', function ($row) {
                return view('admin.dashboard.sponsors.partials.actions', compact('row'))->render();
            })
            ->rawColumns(['actions']);
    }

    public function query(Sponsor $model): QueryBuilder
    {
        return $model->newQuery()->with(['bankName', 'currencyType'])->select('sponsors.*');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('sponsors-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(0, 'desc')
                    ->parameters([
                        'language' => [
                            'url' => '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
                        ],
                        'pageLength' => 25,
                        'lengthMenu' => [[10, 25, 50, -1], [10, 25, 50, 'الكل']],
                        'responsive' => true,
                        'autoWidth' => false,
                        'processing' => true,
                        'serverSide' => true,
                    ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->title('الرقم'),
            Column::make('sponsor_name')->title('اسم الجمعية'),
            Column::make('sponsor_short_name')->title('الاسم المختصر'),
            Column::make('sponsor_phone_number')->title('رقم الهاتف'),
            Column::make('sponsor_email')->title('البريد الإلكتروني'),
            Column::computed('bank_name')->title('البنك')->orderable(false)->searchable(false),
            Column::make('sponsor_account_bank_number')->title('رقم الحساب'),
            Column::make('created_at')->title('تاريخ الإضافة'),
            Column::computed('actions')->title('الإجراءات')->orderable(false)->searchable(false)->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'Sponsors_' . date('YmdHis');
    }
}
