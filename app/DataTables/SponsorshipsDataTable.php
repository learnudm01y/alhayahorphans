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
                // عمود نصي بسيط للتصدير (بدون HTML)
                return $row->sponsorshipStatus ? $row->sponsorshipStatus->description : '-';
            })
            ->addColumn('sponsorship_status_dropdown', function ($row) {
                $statuses = \App\Models\SponsorshipStatus::all();
                $options = '';
                foreach ($statuses as $status) {
                    $selected = ($row->sponsorship_status_id == $status->id) ? 'selected' : '';

                    // إضافة أيقونات مميزة لكل حالة
                    $icon = '';
                    if ($status->id == 3 || stripos($status->description, 'محدث') !== false) {
                        $icon = '🟢 '; // نقطة خضراء لـ "محدث"
                    } elseif ($status->id == 5 || stripos($status->description, 'ذهب') !== false || stripos($status->description, 'صرف') !== false) {
                        $icon = '✓ '; // علامة صح لـ "ذهب للصرف"
                    }
                    // إزالة الأيقونة الخاصة بـ "جديد" و "اختبار حالة كفالة جديدة"

                    $options .= '<option value="' . $status->id . '" ' . $selected . '>' . $icon . $status->description . '</option>';
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
            ->addColumn('address', function ($row) {
                // جلب العنوان من جدول data عبر relation_id_number
                if ($row->relationData) {
                    return $row->relationData->data_description_needs ?: '-';
                }
                // fallback: جلب من guardianData
                return $row->guardianData ? ($row->guardianData->data_description_needs ?: '-') : '-';
            })
            ->addColumn('city', function ($row) {
                // جلب المدينة من جدول data عبر relation_id_number
                if ($row->relationData && $row->relationData->city) {
                    return $row->relationData->city->city;
                }
                // fallback: جلب من guardianData
                if ($row->guardianData && $row->guardianData->city) {
                    return $row->guardianData->city->city;
                }
                return '-';
            })
            ->addColumn('province', function ($row) {
                // جلب المحافظة من جدول data عبر relation_id_number
                if ($row->relationData && $row->relationData->province) {
                    return $row->relationData->province->description;
                }
                // fallback: جلب من guardianData
                if ($row->guardianData && $row->guardianData->province) {
                    return $row->guardianData->province->description;
                }
                return '-';
            })
            ->addColumn('bank_name', function ($row) {
                // عرض اسم البنك من الحساب المعتمد فقط (check_account = 1)
                // استخدام relationData (relation_id_number) للحصول على رقم الملف الصحيح
                $fileId = null;
                if ($row->relationData && $row->relationData->file_id_number) {
                    $fileId = $row->relationData->file_id_number;
                } elseif ($row->guardianData && $row->guardianData->file_id_number) {
                    $fileId = $row->guardianData->file_id_number;
                }

                if ($fileId) {
                    $approvedAccount = \App\Models\GuardianBankAccount::where('guardian_registration', $fileId)
                            ->where('check_account', 1)
                            ->first();

                    if ($approvedAccount) {
                        $bankName = $approvedAccount->bank_name;
                        if (is_numeric($bankName)) {
                            $bankModel = \App\Models\BankName::find($bankName);
                            return $bankModel ? $bankModel->description : $bankName;
                        }
                        return $bankName;
                    }
                }
                return '-';
            })
            ->addColumn('account_holder_name', function ($row) {
                // عرض اسم صاحب الحساب من الحساب المعتمد فقط (check_account = 1)
                $fileId = null;
                if ($row->relationData && $row->relationData->file_id_number) {
                    $fileId = $row->relationData->file_id_number;
                } elseif ($row->guardianData && $row->guardianData->file_id_number) {
                    $fileId = $row->guardianData->file_id_number;
                }

                if ($fileId) {
                    $approvedAccount = \App\Models\GuardianBankAccount::where('guardian_registration', $fileId)
                        ->where('check_account', 1)
                        ->first();

                    if ($approvedAccount) {
                        return $approvedAccount->re_guardian_name ?: '-';
                    }
                }
                return '-';
            })
            ->addColumn('account_holder_id', function ($row) {
                // عرض رقم هوية صاحب الحساب من الحساب المعتمد فقط (check_account = 1)
                $fileId = null;
                if ($row->relationData && $row->relationData->file_id_number) {
                    $fileId = $row->relationData->file_id_number;
                } elseif ($row->guardianData && $row->guardianData->file_id_number) {
                    $fileId = $row->guardianData->file_id_number;
                }

                if ($fileId) {
                    $approvedAccount = \App\Models\GuardianBankAccount::where('guardian_registration', $fileId)
                        ->where('check_account', 1)
                        ->first();

                    if ($approvedAccount) {
                        return $approvedAccount->person_owner_identity_number ?: '-';
                    }
                }
                return '-';
            })
            ->addColumn('account_phone', function ($row) {
                // عرض رقم الهاتف من الحساب المعتمد فقط (check_account = 1)
                $fileId = null;
                if ($row->relationData && $row->relationData->file_id_number) {
                    $fileId = $row->relationData->file_id_number;
                } elseif ($row->guardianData && $row->guardianData->file_id_number) {
                    $fileId = $row->guardianData->file_id_number;
                }

                if ($fileId) {
                    $approvedAccount = \App\Models\GuardianBankAccount::where('guardian_registration', $fileId)
                        ->where('check_account', 1)
                        ->first();

                    if ($approvedAccount) {
                        return $approvedAccount->re_phone_number ?: '-';
                    }
                }
                return '-';
            })
            ->addColumn('bank_account_numbers', function ($row) {
                // عرض أرقام الحسابات البنكية من الحساب المعتمد فقط (check_account = 1)
                $fileId = null;
                if ($row->relationData && $row->relationData->file_id_number) {
                    $fileId = $row->relationData->file_id_number;
                } elseif ($row->guardianData && $row->guardianData->file_id_number) {
                    $fileId = $row->guardianData->file_id_number;
                }

                if ($fileId) {
                    $approvedAccount = \App\Models\GuardianBankAccount::where('guardian_registration', $fileId)
                        ->where('check_account', 1)
                        ->first();

                    if ($approvedAccount) {
                        $accounts = [];
                        if ($approvedAccount->iban_shekel) {
                            $accounts[] = '<div><strong>شيكل:</strong> ' . $approvedAccount->iban_shekel . '</div>';
                        }
                        if ($approvedAccount->iban_usd) {
                            $accounts[] = '<div><strong>دولار:</strong> ' . $approvedAccount->iban_usd . '</div>';
                        }
                        return !empty($accounts) ? implode('', $accounts) : '-';
                    }
                }
                return '-';
            })
            ->addColumn('iban_shekel_export', function ($row) {
                // حساب شيكل للتصدير (نص بسيط)
                $fileId = null;
                if ($row->relationData && $row->relationData->file_id_number) {
                    $fileId = $row->relationData->file_id_number;
                } elseif ($row->guardianData && $row->guardianData->file_id_number) {
                    $fileId = $row->guardianData->file_id_number;
                }
                if ($fileId) {
                    $approvedAccount = \App\Models\GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                    if ($approvedAccount && $approvedAccount->iban_shekel) {
                        return $approvedAccount->iban_shekel;
                    }
                }
                return '-';
            })
            ->addColumn('iban_usd_export', function ($row) {
                // حساب دولار للتصدير (نص بسيط)
                $fileId = null;
                if ($row->relationData && $row->relationData->file_id_number) {
                    $fileId = $row->relationData->file_id_number;
                } elseif ($row->guardianData && $row->guardianData->file_id_number) {
                    $fileId = $row->guardianData->file_id_number;
                }
                if ($fileId) {
                    $approvedAccount = \App\Models\GuardianBankAccount::where('guardian_registration', $fileId)->where('check_account', 1)->first();
                    if ($approvedAccount && $approvedAccount->iban_usd) {
                        return $approvedAccount->iban_usd;
                    }
                }
                return '-';
            })
            ->addColumn('actions', function ($row) {
                return view('admin.dashboard.sponsorships.partials.actions', compact('row'))->render();
            })
            ->rawColumns(['sponsor_name', 'sponsorship_status', 'sponsorship_status_dropdown', 'remaining_days', 'bank_account_numbers', 'actions'])
            ->filter(function ($query) {
                try {
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
                // فلتر البحث النصي الذكي مع normalization
                // ============================================
                if (request()->has('search') && !empty(request()->get('search')['value'])) {
                    $searchTerm = request()->get('search')['value'];

                    // استخدام helper function لتطبيع البحث
                    $searchData = extractSearchTerms($searchTerm);
                    $normalizedTerm = $searchData['normalized'];
                    $searchWords = $searchData['words'];

                    if (!empty($searchWords)) {
                        $query->where(function ($q) use ($searchWords, $normalizedTerm, $searchTerm) {
                            // البحث في جدول sponsorships فقط في الأعمدة الموجودة فعلياً
                            $q->where(function ($subQ) use ($searchWords, $normalizedTerm, $searchTerm) {
                                // البحث في أرقام الملفات (بدون normalization)
                                $subQ->where('internal_file_number', 'LIKE', "%{$searchTerm}%")
                                    ->orWhere('external_file_number', 'LIKE', "%{$searchTerm}%");

                                // البحث في المؤسسة الكافلة (مع normalization)
                                if (!empty($normalizedTerm)) {
                                    $subQ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(sponsoring_organization), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$normalizedTerm}%"]);
                                }
                            })
                            // البحث في relationData (البيانات المرتبطة عبر relation_id_number) - بحث ذكي مع normalization
                            ->orWhereHas('relationData', function ($dataQ) use ($searchWords, $normalizedTerm, $searchTerm) {
                                $dataQ->where(function ($dq) use ($searchWords, $normalizedTerm, $searchTerm) {
                                    // البحث في رقم الهوية ورقم الملف (بدون normalization)
                                    $dq->where('data_id_number', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('file_id_number', 'LIKE', "%{$searchTerm}%");

                                    // البحث في الأسماء (مع normalization)
                                    if (!empty($searchWords)) {
                                        // البحث بكل كلمة على حدة
                                        foreach ($searchWords as $word) {
                                            $dq->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_first_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"])
                                               ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_father_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"])
                                               ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_grand_father_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"])
                                               ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_family_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"]);
                                        }

                                        // البحث بالاسم الكامل المطبع
                                        if (!empty($normalizedTerm) && count($searchWords) >= 2) {
                                            $firstName = $searchWords[0];
                                            $lastName = end($searchWords);

                                            // بحث دقيق: الاسم الأول + الأخير
                                            $dq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                                $nameQ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_first_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$firstName}%"])
                                                      ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_family_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$lastName}%"]);
                                            });

                                            // بحث بديل: الاسم الأول + اسم الأب
                                            $dq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                                $nameQ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_first_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$firstName}%"])
                                                      ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_father_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$lastName}%"]);
                                            });
                                        }
                                    }
                                });
                            })
                            // البحث في جدول data (المعيلين - guardianData) - بحث ذكي مع normalization
                            ->orWhereHas('guardianData', function ($dataQ) use ($searchWords, $normalizedTerm, $searchTerm) {
                                $dataQ->where(function ($dq) use ($searchWords, $normalizedTerm, $searchTerm) {
                                    // البحث في رقم الهوية ورقم الملف (بدون normalization)
                                    $dq->where('data_id_number', 'LIKE', "%{$searchTerm}%")
                                       ->orWhere('file_id_number', 'LIKE', "%{$searchTerm}%");

                                    // البحث في الأسماء (مع normalization)
                                    if (!empty($searchWords)) {
                                        // البحث بكل كلمة على حدة
                                        foreach ($searchWords as $word) {
                                            $dq->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_first_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"])
                                               ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_father_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"])
                                               ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_grand_father_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"])
                                               ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_family_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$word}%"]);
                                        }

                                        // البحث بالاسم الكامل المطبع
                                        if (count($searchWords) >= 2) {
                                            $firstName = $searchWords[0];
                                            $lastName = end($searchWords);

                                            // بحث دقيق: الاسم الأول + الأخير
                                            $dq->orWhere(function ($nameQ) use ($firstName, $lastName) {
                                                $nameQ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_first_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$firstName}%"])
                                                      ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_family_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$lastName}%"]);
                                            });
                                        }

                                        // البحث بثلاث كلمات (دقيق جداً)
                                        if (count($searchWords) >= 3) {
                                            $firstName = $searchWords[0];
                                            $middleName = $searchWords[1];
                                            $lastName = end($searchWords);

                                            $dq->orWhere(function ($nameQ) use ($firstName, $middleName, $lastName) {
                                                $nameQ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_first_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$firstName}%"])
                                                      ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_father_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$middleName}%"])
                                                      ->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(data_family_name), "أ", "ا"), "إ", "ا"), "آ", "ا"), "ى", "ي"), "ة", "ه") LIKE ?', ["%{$lastName}%"]);
                                            });
                                        }
                                    }
                                });
                            });
                        });
                    }
                }
                } catch (\Exception $e) {
                    \Log::error('❌ خطأ في البحث بـ DataTable', [
                        'error' => $e->getMessage(),
                        'search' => request()->get('search'),
                        'line' => $e->getLine()
                    ]);
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
                'creator',
                'guardianData',
                'guardianData.city',
                'guardianData.province',
                'relationData',
                'relationData.city',
                'relationData.province'
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
                    ->dom('<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 text-end"B>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>')
                    ->buttons([
                        ['extend' => 'excel', 'text' => '<i class="fa fa-file-excel"></i> Excel', 'className' => 'btn btn-success btn-sm buttons-excel d-none', 'exportOptions' => ['columns' => ':visible:not(.no-export)']],
                        ['extend' => 'colvis', 'text' => '<i class="fa fa-columns"></i> التحكم بالأعمدة', 'className' => 'btn btn-secondary btn-sm'],
                    ])
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
            Column::make('orphan_name')->title('الإسم'),
            Column::make('identity_number')->title('رقم الهوية'),
            Column::make('guardian_name')->title('إسم المعيل'),
            Column::computed('guardian_identity')->title('رقم هوية المعيل')->orderable(false)->searchable(false),
            Column::computed('sponsor_name')->title('إسم المؤسسة الكافلة')->orderable(false)->searchable(false),
            Column::computed('sponsoring_organization')->title('إسم الكافل'),
            Column::make('internal_file_number')->title('رقم الملف الداخلي'),
            Column::make('external_file_number')->title('رقم الملف الخارجي'),
            Column::computed('sponsorship_duration')->title('مدة الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_period')->title('فترة الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_type')->title('نوع الكفالة')->orderable(false)->searchable(false),
            Column::computed('sponsorship_status')->title('حالة الكفالة')->orderable(false)->searchable(false)->visible(false),
            Column::computed('sponsorship_status_dropdown')->title('حالة الكفالة')->orderable(false)->searchable(false)->addClass('no-export'),
            Column::computed('actions')
                ->title('الإجراءات')
                ->orderable(false)
                ->searchable(false)
                ->exportable(false)
                ->printable(false)
                ->addClass('no-export')
                ->width(120),
            Column::computed('province')->title('المحافظة')->orderable(false)->searchable(false),
            Column::computed('city')->title('المدينة')->orderable(false)->searchable(false),
            Column::computed('address')->title('العنوان')->orderable(false)->searchable(false),
            Column::computed('bank_name')->title('إسم البنك')->orderable(false)->searchable(false),
            Column::computed('account_holder_name')->title('إسم صاحب الحساب')->orderable(false)->searchable(false),
            Column::computed('account_holder_id')->title('رقم هوية صاحب الحساب')->orderable(false)->searchable(false),
            Column::computed('account_phone')->title('رقم الجوال المربوط بالحساب')->orderable(false)->searchable(false),
            Column::computed('bank_account_numbers')->title('أرقام الحساب البنكي')->orderable(false)->searchable(false)->addClass('no-export'),
            Column::computed('iban_shekel_export')->title('حساب شيكل')->orderable(false)->searchable(false)->visible(false),
            Column::computed('iban_usd_export')->title('حساب دولار')->orderable(false)->searchable(false)->visible(false),
            Column::computed('remaining_days')->title('المتبقي')->orderable(false)->searchable(false),
                Column::make('created_at')->title('تاريخ الإضافة'),
        ];
    }

    protected function filename(): string
    {
        return 'Sponsorships_' . date('YmdHis');
    }
}
