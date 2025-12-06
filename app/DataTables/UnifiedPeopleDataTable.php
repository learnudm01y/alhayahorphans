<?php

namespace App\DataTables;

use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;
use Illuminate\Support\Collection;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\CollectionDataTable;

class UnifiedPeopleDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     */
    public function dataTable($query): CollectionDataTable
    {
        return (new CollectionDataTable($query))
            ->addColumn('action', function($row) {
                $recordType = $row['record_type'];
                $recordId = $row['id'];
                $personType = $row['person_type'];

                // تحديد الروابط حسب نوع السجل
                if ($recordType === 'data') {
                    $showUrl = route('admin.records.management.show', $recordId);
                    $editUrl = route('admin.records.management.edit', $recordId);
                } else {
                    // يمكن إضافة روابط مخصصة لـ re_people و dead_people لاحقاً
                    $showUrl = '#';
                    $editUrl = '#';
                }

                return '
                <div class="dropdown">
                  <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    الإجراءات
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" style="min-width: 160px;">
                    <li>
                      <a class="dropdown-item sponsorship-btn" style="color:#222; cursor: pointer;"
                         data-record-id="'.$recordId.'"
                         data-record-type="'.$recordType.'"
                         data-person-type="'.$personType.'"
                         data-record-name="'.$row['full_name'].'"
                         data-file-id="'.$row['file_id'].'"
                         data-identity-number="'.$row['identity_number'].'"
                         data-guardian-name="'.$row['guardian_name'].'"
                         data-guardian-id="'.$row['guardian_id'].'">
                        <i class="bi bi-heart-fill text-success"></i> تنفيذ كفالة
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <a class="dropdown-item" style="color:#222;" href="'.$editUrl.'">
                        <i class="bi bi-pencil text-primary"></i> تعديل
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" style="color:#222;" href="'.$showUrl.'">
                        <i class="bi bi-eye text-info"></i> عرض
                      </a>
                    </li>
                  </ul>
                </div>
                ';
            })
            ->addColumn('person_type_badge', function($row) {
                $badges = [
                    'breadwinner' => '<span class="badge border border-dark text-dark">معيل</span>',
                    'orphan' => '<span class="badge border border-dark text-dark">يتيم</span>',
                    'family_member' => '<span class="badge border border-dark text-dark">فرد عائلة</span>',
                    'deceased_father' => '<span class="badge border border-dark text-dark">أب متوفي</span>',
                    'deceased_mother' => '<span class="badge border border-dark text-dark">أم متوفية</span>',
                ];
                return $badges[$row['person_type']] ?? '<span class="badge border border-dark text-dark">غير محدد</span>';
            })
            ->addColumn('guardian_info', function($row) {
                if (!empty($row['guardian_name'])) {
                    return $row['guardian_name'] . '<br><small class="text-muted">' . ($row['guardian_id'] ?? '-') . '</small>';
                }
                return '<span class="text-muted">-</span>';
            })
            ->rawColumns(['action', 'person_type_badge', 'guardian_info'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(): Collection
    {
        $unifiedData = collect();

        // 1. جلب المعيلين من جدول data
        $breadwinners = Data::with([
            'section',
            'requestStatus',
            'categoryOfRelation',
            'healthStatus'
        ])
        ->whereHas('requestStatus', function($q) {
            $q->where('description', 'مقبول');
        })
        ->get()
        ->map(function($record) {
            return [
                'id' => $record->id,
                'record_type' => 'data',
                'person_type' => 'breadwinner',
                'file_id' => $record->file_id_number,
                'identity_number' => $record->data_id_number,
                'full_name' => trim("{$record->data_first_name} {$record->data_father_name} {$record->data_grand_father_name} {$record->data_family_name}"),
                'birth_date' => $record->data_birth_date,
                'gender' => $record->data_gender == 1 ? 'ذكر' : 'أنثى',
                'phone' => $record->data_phone_number,
                'section' => optional($record->section)->description,
                'health_status' => optional($record->healthStatus)->description,
                'guardian_name' => null, // المعيل لا يحتاج معيل
                'guardian_id' => null,
                'source' => 'جدول المعيلين',
            ];
        });

        // 2. جلب الأيتام وأفراد الأسرة من جدول re_people - فقط المقبولين
        $familyMembers = RePeople::with([
            'healthStatus',
            'guaranteeType',
            'dataRecord'
        ])
        ->whereHas('dataRecord', function($q) {
            $q->whereHas('requestStatus', function($subq) {
                $subq->where('description', 'مقبول');
            });
        })
        ->get()
        ->map(function($record) {
            // تحديد نوع الشخص (يتيم أو فرد عائلة)
            $personType = 'family_member';
            if ($record->person_type_of_guarantee) {
                $guaranteeType = optional($record->guaranteeType)->description ?? '';
                if (stripos($guaranteeType, 'يتيم') !== false) {
                    $personType = 'orphan';
                }
            }

            // جلب معلومات المعيل من جدول data
            $guardianName = null;
            $guardianId = null;
            if ($record->dataRecord) {
                $guardianName = trim("{$record->dataRecord->data_first_name} {$record->dataRecord->data_father_name} {$record->dataRecord->data_grand_father_name} {$record->dataRecord->data_family_name}");
                $guardianId = $record->dataRecord->data_id_number;
            }

            return [
                'id' => $record->person_id ?? $record->registration_id,
                'record_type' => 're_people',
                'person_type' => $personType,
                'file_id' => $record->registration_id,
                'identity_number' => $record->person_id,
                'full_name' => trim("{$record->first_name} {$record->second_name} {$record->third_name} {$record->last_name}"),
                'birth_date' => $record->person_birth_date,
                'gender' => $record->person_gender == 1 ? 'ذكر' : 'أنثى',
                'phone' => null,
                'section' => null,
                'health_status' => optional($record->healthStatus)->description,
                'guardian_name' => $guardianName,
                'guardian_id' => $guardianId,
                'source' => $personType === 'orphan' ? 'جدول الأيتام' : 'جدول أفراد الأسرة',
            ];
        });

        // 3. جلب المتوفين من جدول dead_people - فقط للملفات المقبولة
        $deceased = DeadPepole::whereHas('dataRecord', function($q) {
            $q->whereHas('requestStatus', function($subq) {
                $subq->where('description', 'مقبول');
            });
        })
        ->get()
        ->flatMap(function($record) {
            $results = [];

            // إضافة الأب المتوفي
            if (!empty($record->father_id)) {
                $results[] = [
                    'id' => 'father_' . $record->re_file_id,
                    'record_type' => 'dead_people',
                    'person_type' => 'deceased_father',
                    'file_id' => $record->re_file_id,
                    'identity_number' => $record->father_id,
                    'full_name' => trim("{$record->father_first_name} {$record->father_second_name} {$record->father_third_name} {$record->father_last_name}"),
                    'birth_date' => null,
                    'gender' => 'ذكر',
                    'phone' => null,
                    'section' => null,
                    'health_status' => 'متوفي',
                    'guardian_name' => null, // المتوفي لا يحتاج معيل
                    'guardian_id' => null,
                    'source' => 'جدول المتوفين',
                    'death_date' => $record->father_death_date,
                ];
            }

            // إضافة الأم المتوفية
            if (!empty($record->mother_id)) {
                $results[] = [
                    'id' => 'mother_' . $record->re_file_id,
                    'record_type' => 'dead_people',
                    'person_type' => 'deceased_mother',
                    'file_id' => $record->re_file_id,
                    'identity_number' => $record->mother_id,
                    'full_name' => trim("{$record->mother_first_name} {$record->mother_second_name} {$record->mother_third_name} {$record->mother_last_name}"),
                    'birth_date' => null,
                    'gender' => 'أنثى',
                    'phone' => null,
                    'section' => null,
                    'health_status' => 'متوفية',
                    'guardian_name' => null, // المتوفي لا يحتاج معيل
                    'guardian_id' => null,
                    'source' => 'جدول المتوفين',
                    'death_date' => $record->mother_death_date,
                ];
            }

            return $results;
        });

        // دمج جميع البيانات
        $unifiedData = $breadwinners
            ->concat($familyMembers)
            ->concat($deceased);

        return $unifiedData;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('unified-people-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(0, 'desc')
                    ->buttons([
                        Button::make('excel')->text('تصدير Excel'),
                        Button::make('csv')->text('تصدير CSV'),
                        Button::make('print')->text('طباعة'),
                    ])
                    ->parameters([
                        'language' => [
                            'emptyTable' => 'لا توجد بيانات متاحة في الجدول',
                            'zeroRecords' => 'لم يتم العثور على سجلات مطابقة',
                            'info' => 'عرض _START_ إلى _END_ من أصل _TOTAL_ سجل',
                            'infoEmpty' => 'عرض 0 إلى 0 من أصل 0 سجل',
                            'infoFiltered' => '(تم التصفية من إجمالي _MAX_ سجل)',
                            'search' => 'بحث:',
                            'paginate' => [
                                'first' => 'الأول',
                                'last' => 'الأخير',
                                'next' => 'التالي',
                                'previous' => 'السابق',
                            ],
                        ],
                        'select' => false,
                        'initComplete' => "function() {
                            $('#unified-people-table thead').css({'direction':'rtl'});
                            $('#unified-people-table thead th').css({'text-align':'center'});
                        }",
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('file_id')->title('رقم الملف')->width(120),
            Column::make('person_type_badge')->title('نوع الشخص')->width(100)->orderable(false),
            Column::make('identity_number')->title('رقم الهوية')->width(150),
            Column::make('full_name')->title('الاسم الكامل')->width(250),
            Column::make('birth_date')->title('تاريخ الميلاد')->width(120),
            Column::make('gender')->title('الجنس')->width(80),
            Column::make('phone')->title('الجوال')->width(120),
            Column::make('health_status')->title('الحالة الصحية')->width(120),
            Column::computed('guardian_info')->title('المعيل')->width(200)->orderable(false),
            Column::make('source')->title('المصدر')->width(150),
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
        return 'UnifiedPeople_' . date('YmdHis');
    }
}
