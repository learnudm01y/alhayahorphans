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
                // عرض جميع المؤسسات الكافلة كل واحدة في سطر منفصل
                if ($row->sponsors && $row->sponsors->count() > 0) {
                    $names = $row->sponsors->pluck('sponsor_name')->map(function($name) {
                        return '<div style="white-space: nowrap !important; display: block !important; margin-bottom: 3px !important;">' . $name . '</div>';
                    })->toArray();
                    return '<div style="display: flex !important; flex-direction: column !important; gap: 3px !important;">' . implode('', $names) . '</div>';
                }
                // fallback للعلاقة القديمة
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
            ->addColumn('guardian_identity', function ($row) {
                return $row->guardian_identity_number ?: '-';
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
            ->addColumn('sponsorship_status_dropdown', function ($row) {
                $statuses = \App\Models\SponsorshipStatus::all();
                $options = '';
                foreach ($statuses as $status) {
                    $selected = ($row->sponsorship_status_id == $status->id) ? 'selected' : '';
                    $options .= '<option value="' . $status->id . '" ' . $selected . '>' . $status->description . '</option>';
                }

                return '<select class="form-select form-select-sm form-select-solid change-sponsorship-status"
                                data-sponsorship-id="' . $row->id . '"
                                style="min-width: 150px;">
                            ' . $options . '
                        </select>';
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
            ->rawColumns(['sponsor_name', 'sponsorship_status', 'sponsorship_status_dropdown', 'remaining_days', 'actions'])
            ->filter(function ($query) {
                // ============================================
                // فلاتر المؤسسة الكافلة
                // ============================================
                if (request()->has('sponsor_id') && !empty(request()->get('sponsor_id'))) {
                    $sponsorId = request()->get('sponsor_id');
                    $query->whereHas('sponsors', function($q) use ($sponsorId) {
                        $q->where('sponsors.id', $sponsorId);
                    });
                }

                // ============================================
                // فلاتر نوع الكفالة
                // ============================================
                if (request()->has('sponsorship_type_id') && !empty(request()->get('sponsorship_type_id'))) {
                    $query->where('sponsorship_type_id', request()->get('sponsorship_type_id'));
                }

                // ============================================
                // فلاتر حالة الكفالة
                // ============================================
                if (request()->has('sponsorship_status_id') && !empty(request()->get('sponsorship_status_id'))) {
                    $query->where('sponsorship_status_id', request()->get('sponsorship_status_id'));
                }

                // ============================================
                // فلتر البحث النصي
                // ============================================
                if (request()->has('search') && !empty(request()->get('search')['value'])) {
                    $searchTerm = request()->get('search')['value'];

                    // تنظيف وتجهيز كلمات البحث
                    $searchWords = array_filter(array_map('trim', explode(' ', $searchTerm)));

                    if (!empty($searchWords)) {
                        $query->where(function ($q) use ($searchWords, $searchTerm) {
                            // البحث في جدول sponsorships مباشرة
                            $q->where(function ($subQ) use ($searchWords, $searchTerm) {
                                // البحث الكامل
                                $subQ->where('orphan_name', 'LIKE', "%{$searchTerm}%")
                                    ->orWhere('guardian_name', 'LIKE', "%{$searchTerm}%")
                                    ->orWhere('identity_number', 'LIKE', "%{$searchTerm}%")
                                    ->orWhere('internal_file_number', 'LIKE', "%{$searchTerm}%")
                                    ->orWhere('external_file_number', 'LIKE', "%{$searchTerm}%");

                                // البحث بالكلمات المنفصلة
                                foreach ($searchWords as $word) {
                                    $subQ->orWhere('orphan_name', 'LIKE', "%{$word}%")
                                        ->orWhere('guardian_name', 'LIKE', "%{$word}%");
                                }
                            })
                            // البحث في جدول data (المعيلين)
                            ->orWhereHas('guardianData', function ($dataQ) use ($searchWords, $searchTerm) {
                                $dataQ->where(function ($dq) use ($searchWords, $searchTerm) {
                                    // البحث في حقول الاسم
                                    $dq->where('data_first_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('data_father_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('data_grand_father_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('data_family_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('data_id_number', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('file_id_number', 'LIKE', "%{$searchTerm}%");

                                    // البحث الذكي بالكلمات المنفصلة
                                    foreach ($searchWords as $word) {
                                        $dq->orWhere('data_first_name', 'LIKE', "%{$word}%")
                                           ->orWhere('data_father_name', 'LIKE', "%{$word}%")
                                           ->orWhere('data_grand_father_name', 'LIKE', "%{$word}%")
                                           ->orWhere('data_family_name', 'LIKE', "%{$word}%");
                                    }

                                    // البحث بالاسم الأول + الأخير (متقدم)
                                    if (count($searchWords) >= 2) {
                                        $firstName = $searchWords[0];
                                        $lastName = end($searchWords);
                                        $dq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                            $nameQ->where('data_first_name', 'LIKE', "%{$firstName}%")
                                                  ->where('data_family_name', 'LIKE', "%{$lastName}%");
                                        });

                                        // محاولة الاسم الأول + اسم الأب
                                        $dq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                            $nameQ->where('data_first_name', 'LIKE', "%{$firstName}%")
                                                  ->where('data_father_name', 'LIKE', "%{$lastName}%");
                                        });
                                    }

                                    // البحث بثلاث كلمات (أول، وسط، أخير)
                                    if (count($searchWords) >= 3) {
                                        $firstName = $searchWords[0];
                                        $middleName = $searchWords[1];
                                        $lastName = end($searchWords);

                                        $dq->orWhere(function ($nameQ) use ($firstName, $middleName, $lastName) {
                                            $nameQ->where('data_first_name', 'LIKE', "%{$firstName}%")
                                                  ->where('data_father_name', 'LIKE', "%{$middleName}%")
                                                  ->where('data_family_name', 'LIKE', "%{$lastName}%");
                                        });
                                    }
                                });
                            })
                            // البحث في جدول re_people (الأيتام وأفراد العائلة)
                            ->orWhereHas('orphan', function ($orphanQ) use ($searchWords, $searchTerm) {
                                $orphanQ->where(function ($oq) use ($searchWords, $searchTerm) {
                                    // البحث الكامل في الأسماء
                                    $oq->where('first_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('second_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('third_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('person_id', 'LIKE', "%{$searchTerm}%");

                                    // البحث الذكي بالكلمات المنفصلة
                                    foreach ($searchWords as $word) {
                                        $oq->orWhere('first_name', 'LIKE', "%{$word}%")
                                           ->orWhere('second_name', 'LIKE', "%{$word}%")
                                           ->orWhere('third_name', 'LIKE', "%{$word}%")
                                           ->orWhere('last_name', 'LIKE', "%{$word}%");
                                    }

                                    // البحث بالاسم الأول + الأخير
                                    if (count($searchWords) >= 2) {
                                        $firstName = $searchWords[0];
                                        $lastName = end($searchWords);
                                        $oq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                            $nameQ->where('first_name', 'LIKE', "%{$firstName}%")
                                                  ->where('last_name', 'LIKE', "%{$lastName}%");
                                        });

                                        // محاولة الاسم الأول + الثاني
                                        $oq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                            $nameQ->where('first_name', 'LIKE', "%{$firstName}%")
                                                  ->where('second_name', 'LIKE', "%{$lastName}%");
                                        });
                                    }

                                    // البحث بثلاث كلمات
                                    if (count($searchWords) >= 3) {
                                        $firstName = $searchWords[0];
                                        $middleName = $searchWords[1];
                                        $lastName = end($searchWords);

                                        $oq->orWhere(function ($nameQ) use ($firstName, $middleName, $lastName) {
                                            $nameQ->where('first_name', 'LIKE', "%{$firstName}%")
                                                  ->where('second_name', 'LIKE', "%{$middleName}%")
                                                  ->where('last_name', 'LIKE', "%{$lastName}%");
                                        });
                                    }
                                });
                            })
                            // البحث في dead_people (المتوفين)
                            ->orWhere(function ($deadQ) use ($searchWords, $searchTerm) {
                                // البحث بالـ identity_number في جدول dead_people
                                $deadQ->whereIn('identity_number', function ($subQuery) use ($searchWords, $searchTerm) {
                                    $subQuery->select('father_id')
                                        ->from('dead_people')
                                        ->where(function ($dpq) use ($searchWords, $searchTerm) {
                                            $dpq->where('father_first_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('father_second_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('father_third_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('father_last_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('father_id', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('mother_first_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('mother_second_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('mother_third_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('mother_last_name', 'LIKE', "%{$searchTerm}%")
                                                ->orWhere('mother_id', 'LIKE', "%{$searchTerm}%");

                                            foreach ($searchWords as $word) {
                                                $dpq->orWhere('father_first_name', 'LIKE', "%{$word}%")
                                                    ->orWhere('father_second_name', 'LIKE', "%{$word}%")
                                                    ->orWhere('father_third_name', 'LIKE', "%{$word}%")
                                                    ->orWhere('father_last_name', 'LIKE', "%{$word}%")
                                                    ->orWhere('mother_first_name', 'LIKE', "%{$word}%")
                                                    ->orWhere('mother_second_name', 'LIKE', "%{$word}%")
                                                    ->orWhere('mother_third_name', 'LIKE', "%{$word}%")
                                                    ->orWhere('mother_last_name', 'LIKE', "%{$word}%");
                                            }

                                            if (count($searchWords) >= 2) {
                                                $firstName = $searchWords[0];
                                                $lastName = end($searchWords);
                                                $dpq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                                    $nameQ->where('father_first_name', 'LIKE', "%{$firstName}%")
                                                          ->where('father_last_name', 'LIKE', "%{$lastName}%");
                                                })->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                                    $nameQ->where('mother_first_name', 'LIKE', "%{$firstName}%")
                                                          ->where('mother_last_name', 'LIKE', "%{$lastName}%");
                                                });
                                            }
                                        });
                                });
                            })
                            // البحث في المؤسسات الكافلة
                            ->orWhereHas('sponsors', function ($sponsorQ) use ($searchWords, $searchTerm) {
                                $sponsorQ->where(function ($sq) use ($searchWords, $searchTerm) {
                                    $sq->where('sponsor_name', 'LIKE', "%{$searchTerm}%");
                                    foreach ($searchWords as $word) {
                                        $sq->orWhere('sponsor_name', 'LIKE', "%{$word}%");
                                    }
                                });
                            });
                        });
                    }
                }
            });
    }

    public function query(Sponsorship $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'sponsor',
                'sponsors',
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
                            'url' => asset('admin/assets/plugins/custom/datatables/i18n/ar.json')
                        ],
                        'pageLength' => 25,
                        'lengthMenu' => [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']],
                        'responsive' => true,
                        'autoWidth' => false,
                        'processing' => true,
                        'serverSide' => true,
                    ]);
    }

    public function getColumns(): array
    {
        return [
            Column::computed('sponsor_name')->title('إسم المؤسسة الكافلة')->orderable(false)->searchable(false),
            Column::computed('sponsoring_organization')->title('إسم الكافل'),
            Column::make('internal_file_number')->title('رقم الملف الداخلي'),
            Column::make('external_file_number')->title('رقم الملف الخارجي'),
            Column::make('identity_number')->title('رقم الهوية'),
            Column::make('orphan_name')->title('الإسم'),
            Column::make('guardian_name')->title('إسم المعيل'),
            Column::computed('guardian_identity')->title('رقم هوية المعيل')->orderable(false)->searchable(false),
            Column::computed('sponsorship_duration')->title('مدة الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_period')->title('فترة الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_type')->title('نوع الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_status_dropdown')->title('حالة الكفالة')->orderable(false)->searchable(false),
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
