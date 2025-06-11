<?php

namespace App\DataTables;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Yajra\DataTables\QueryDataTable;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Column;

class PersonsDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): QueryDataTable
    {
        return datatables()
            ->query($query)
            ->editColumn('CI_SEX_CD', fn($row) =>
                $row->CI_SEX_CD == 1 ? 'ذكر' :
                ($row->CI_SEX_CD == 2 ? 'أنثى' : 'غير محدد')
            )
            ->editColumn('CI_BIRTH_DT', fn($row) =>
                empty($row->CI_BIRTH_DT) ? 'غير محدد' : $row->CI_BIRTH_DT
            )
            ->editColumn('social_status_name', fn($row) =>
            $row->social_status_name ?? 'غير محددة'
            )
            // ->editColumn('social_status', fn($row) =>
            //     $row->social_status ?? 'غير محددة'
            // )
            ->editColumn('MOTHER_NAME1', fn($row) =>
                empty($row->MOTHER_NAME1) ? 'غير موجود' : $row->MOTHER_NAME1
            )
            ->addColumn('action', function($row) {
                $editUrl = route('admin.persons.edit', $row->ID);
                $deleteUrl = route('admin.persons.destroy', $row->ID);
                return '
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary mx-1 mb-2"><i class="bi bi-pencil-square"></i> مشاهدة وتعديل</a>
                    <form action="'.$deleteUrl.'" method="POST" style="display:inline;">
                        '.csrf_field().'
                        '.method_field('DELETE').'
                        <button type="button" class="btn btn-sm btn-danger delete-btn"><i class="bi bi-trash"></i> حذف</button>
                    </form>
                ';
            })
            ->setRowId('ID');
    }

    public function query(): QueryBuilder
    {
        return DB::table('persons')
            ->leftJoin('ci_personal_cd', 'persons.ci_personal_cd', '=', 'ci_personal_cd.id')
            ->select([
                'persons.ID',
                'persons.CI_ID_NUM',
                'persons.CI_FIRST_ARB',
                'persons.CI_FATHER_ARB',
                'persons.CI_GRAND_FATHER_ARB',
                'persons.CI_FAMILY_ARB',
                'persons.CI_BIRTH_DT',
                'persons.CI_SEX_CD',
                'persons.MOTHER_NAME1',
                // 'persons.ci_personal_cd',
                'ci_personal_cd.ci_personal_cd as social_status_name', // افترضنا أن حقل الاسم في الجدول اسمه name
            ])
            ->orderBy('persons.CI_ID_NUM');
    }

    public function html(): \Yajra\DataTables\Html\Builder
    {
        return $this->builder()
            ->setTableId('persons-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->processing(true)
            ->serverSide(true)
            ->orderBy(0, 'desc')
            ->pageLength(10)
            ->responsive(true)
            ->autoWidth(false)
            ->parameters([
                'searching'   => true,
                'searchDelay' => 500, // يقلل الضغط على السيرفر
                'stateSave'   => true,
                'lengthMenu'  => [10,25, 50, 100],
            ])
            ->buttons(['excel','csv','pdf','print','reset','reload']);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('CI_ID_NUM')->title('رقم الهوية')->searchable(true)->className('text-center'),
            Column::make('CI_FIRST_ARB')->title('الاسم الأول')->searchable(true)->className('text-center'),
            Column::make('CI_FATHER_ARB')->title('اسم الأب')->searchable(true)->className('text-center'),
            Column::make('CI_GRAND_FATHER_ARB')->title('اسم الجد')->searchable(true)->className('text-center'),
            Column::make('CI_FAMILY_ARB')->title('اسم العائلة')->searchable(true)->className('text-center'),
            Column::make('CI_BIRTH_DT')->title('تاريخ الميلاد')->searchable(false)->className('text-center'),
            Column::make('CI_SEX_CD')->title('الجنس')->searchable(false)->className('text-center'),
            Column::make('social_status_name')->title('الحالة الإجتماعية')->searchable(false)->className('text-center'),
            Column::make('MOTHER_NAME1')->title('اسم الأم')->searchable(false)->className('text-center'),
            Column::computed('action')
                ->title('الإجراءات')
                ->exportable(false)
                ->printable(false)
                ->width(200)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Persons_' . date('YmdHis');
    }
}
