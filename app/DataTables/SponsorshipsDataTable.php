<?php

namespace App\DataTables;

use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SponsorshipsDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('sponsor_name', function ($row) {
                return $row->sponsor ? $row->sponsor->sponsor_name : ($row->sponsoring_organization ?: '-');
            })
            ->addColumn('sponsoring_organization', function ($row) {
                return $row->sponsoring_organization ?: '-';
            })
            ->editColumn('internal_file_number', function ($row) {
                return $row->internal_file_number ?: '-';
            })
            ->editColumn('external_file_number', function ($row) {
                return $row->external_file_number ?: '-';
            })
            ->editColumn('identity_number', function ($row) {
                return $row->identity_number ?: '-';
            })
            ->editColumn('orphan_name', function ($row) {
                return $row->orphan_name ?: '-';
            })
            ->editColumn('guardian_name', function ($row) {
                return $row->guardian_name ?: '-';
            })
            ->addColumn('sponsorship_duration', function ($row) {
                if ($row->sponsorship_duration_months) {
                    return $row->sponsorship_duration_months . ' شهر';
                }
                return '-';
            })
            ->addColumn('sponsorship_period', function ($row) {
                $start = $row->sponsorship_start_date ? $row->sponsorship_start_date->format('Y-m-d') : '-';
                $end = $row->sponsorship_end_date ? $row->sponsorship_end_date->format('Y-m-d') : '-';
                return $start . ' → ' . $end;
            })
            ->addColumn('sponsorship_type', function ($row) {
                return $row->sponsorshipType ? $row->sponsorshipType->description : '-';
            })
            ->addColumn('sponsorship_status', function ($row) {
                if ($row->sponsorshipStatus) {
                    $status = $row->sponsorshipStatus->description;
                    $badge = 'badge-info';

                    // تخصيص الألوان حسب الحالة
                    if (stripos($status, 'نشط') !== false || stripos($status, 'فعال') !== false) {
                        $badge = 'badge-success';
                    } elseif (stripos($status, 'منتهي') !== false || stripos($status, 'موقوف') !== false) {
                        $badge = 'badge-danger';
                    } elseif (stripos($status, 'معلق') !== false) {
                        $badge = 'badge-warning';
                    }

                    return '<span class="badge ' . $badge . '">' . $status . '</span>';
                }
                return '-';
            })
            ->addColumn('remaining_days', function ($row) {
                if ($row->remaining_days !== null) {
                    if ($row->remaining_days == 0) {
                        return '<span class="badge badge-danger">منتهية</span>';
                    } elseif ($row->remaining_days <= 30) {
                        return '<span class="badge badge-warning">' . $row->remaining_days . ' يوم</span>';
                    } else {
                        return '<span class="badge badge-success">' . $row->remaining_days . ' يوم</span>';
                    }
                }
                return '<span class="badge badge-secondary">غير محدد</span>';
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at->format('Y-m-d H:i:s');
            })
            ->addColumn('actions', function ($row) {
                return view('admin.dashboard.sponsorships.partials.actions', compact('row'))->render();
            })
            ->rawColumns(['sponsorship_status', 'remaining_days', 'actions']);
    }

    public function query(Sponsorship $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'sponsor',
                'sponsorshipType',
                'sponsorshipStatus',
                'creator'
            ])
            ->select('sponsorships.*');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('sponsorships-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(0, 'desc')
                    ->parameters([
                        'language' => [
                            'url' => '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
                        ],
                        'pageLength' => 25,
                        'lengthMenu' => [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']],
                        'responsive' => true,
                        'autoWidth' => false,
                        'processing' => true,
                        'serverSide' => true,
                        'dom' => 'Bfrtip',
                        'buttons' => [
                            [
                                'extend' => 'excel',
                                'text' => 'تصدير Excel',
                                'className' => 'btn btn-success btn-sm'
                            ],
                            [
                                'extend' => 'pdf',
                                'text' => 'تصدير PDF',
                                'className' => 'btn btn-danger btn-sm'
                            ],
                            [
                                'extend' => 'print',
                                'text' => 'طباعة',
                                'className' => 'btn btn-info btn-sm'
                            ],
                        ],
                    ]);
    }

    public function getColumns(): array
    {
        return [
            Column::computed('sponsoring_organization')->title('إسم المؤسسة الكافلة'),
            Column::computed('sponsor_name')->title('الكافل')->orderable(false)->searchable(false),
            Column::make('internal_file_number')->title('رقم الملف الداخلي'),
            Column::make('external_file_number')->title('رقم الملف الخارجي'),
            Column::make('identity_number')->title('رقم الهوية'),
            Column::make('orphan_name')->title('الإسم'),
            Column::make('guardian_name')->title('إسم المعيل'),
            Column::computed('sponsorship_duration')->title('مدة الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_period')->title('فترة الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_type')->title('نوع الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_status')->title('حالة الكفالة')->orderable(false)->searchable(false),
            Column::computed('remaining_days')->title('المتبقي')->orderable(false)->searchable(false),
            Column::make('created_at')->title('تاريخ الإضافة'),
            Column::computed('actions')
                ->title('الإجراءات')
                ->orderable(false)
                ->searchable(false)
                ->exportable(false)
                ->printable(false)
                ->width(120),
        ];
    }

    protected function filename(): string
    {
        return 'Sponsorships_' . date('YmdHis');
    }
}
