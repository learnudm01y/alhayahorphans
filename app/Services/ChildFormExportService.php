<?php

namespace App\Services;

use App\Models\Data;
use App\Models\DeadPepole;
use App\Models\GuardianBankAccount;
use App\Models\PortalGeneralRegistrationFieldValue;
use App\Models\RePeople;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ChildFormExportService
{
    private const TEMPLATE_RELATIVE_PATH = 'templates/child_form_template.xlsx';
    private const TEMPLATE_NUMBERED_ROWS = 50;
    private const NOTES_BLOCK_ROW = 53;
    private const DATA_START_ROW = 3;
    private const EXTRA_BANNER_TITLE = 'بيانات الكفالة';
    private const FIRST_EXTRA_COLUMN = 26; // Z

    private const PORTAL_FIELD_KEYS = [
        'field_mother_status',
        'field_living_mother_first_name',
        'field_living_mother_second_name',
        'field_living_mother_third_name',
        'field_living_mother_last_name',
        'field_living_mother_id',
        'field_mother_first_name',
        'field_mother_id',
        'field_data_phone_number',
    ];

    private array $rePeople = [];
    private array $fileRecords = [];
    private array $guardianById = [];
    private array $deadPeople = [];
    private array $bankAccounts = [];
    private array $portalFields = [];
    private array $relations = [];
    private array $bankNames = [];

    /**
     * بناء ملف Excel بصيغة استمارة الطفل (القالب A–Y) مع أعمدة إضافية بعد Y.
     *
     * @param iterable $sponsorships مجموعة نماذج Sponsorship
     * @param array $extraColumns تعريف الأعمدة الإضافية ['key' => ['title' => ..., 'callback' => fn($s)]]
     */
    public function generate($sponsorships, array $extraColumns = []): Spreadsheet
    {
        $templatePath = public_path(self::TEMPLATE_RELATIVE_PATH);
        if (!is_file($templatePath)) {
            throw new \RuntimeException('ملف القالب غير موجود: ' . $templatePath);
        }

        $rows = $sponsorships instanceof Collection ? $sponsorships : collect($sponsorships);

        $this->loadContext($rows);

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $count = $rows->count();

        if ($count > self::TEMPLATE_NUMBERED_ROWS) {
            $extraRows = $count - self::TEMPLATE_NUMBERED_ROWS;
            $sheet->insertNewRowBefore(self::NOTES_BLOCK_ROW, $extraRows);
            $lastInsertedRow = self::NOTES_BLOCK_ROW + $extraRows - 1;
            $sheet->duplicateStyle($sheet->getStyle('A52:Y52'), 'A' . self::NOTES_BLOCK_ROW . ':Y' . $lastInsertedRow);
        }

        $lastDataRow = self::DATA_START_ROW + $count - 1;

        $this->writeExtraHeaders($sheet, $extraColumns);

        $rowNumber = self::DATA_START_ROW;
        $index = 1;
        foreach ($rows as $sponsorship) {
            $values = array_merge(
                [$index],
                $this->templateValues($sponsorship),
                $this->extraValues($sponsorship, $extraColumns)
            );
            $sheet->fromArray($values, null, 'A' . $rowNumber, true);
            $rowNumber++;
            $index++;
        }

        $this->forceTextCells($sheet, $lastDataRow, $extraColumns);

        foreach (['B', 'J', 'M', 'U', 'X', 'Y'] as $column) {
            if ($lastDataRow >= self::DATA_START_ROW) {
                $sheet->getStyle($column . self::DATA_START_ROW . ':' . $column . $lastDataRow)
                    ->getNumberFormat()->setFormatCode('@');
            }
        }

        return $spreadsheet;
    }

    /**
     * اسم البنك النصي للحساب المفعّل (check_account=1) الخاص بالكفالة.
     */
    public function bankNameFor($sponsorship): ?string
    {
        $account = $this->bankAccountFor($sponsorship);
        if (!$account) {
            return null;
        }

        $bankName = $account->bank_name;
        if ($bankName === null || $bankName === '') {
            return null;
        }

        if (is_numeric($bankName) && isset($this->bankNames[(int) $bankName])) {
            return $this->bankNames[(int) $bankName];
        }

        return (string) $bankName;
    }

    /**
     * منع تحويل أرقام الهوية/الآيبان إلى أرقام علمية: خلايا نصية صريحة.
     */
    private function forceTextCells($sheet, int $lastDataRow, array $extraColumns): void
    {
        if ($lastDataRow < self::DATA_START_ROW) {
            return;
        }

        $columns = ['B', 'J', 'M', 'U', 'X', 'Y'];
        $extraCount = count($extraColumns);
        for ($i = 0; $i < $extraCount; $i++) {
            $columns[] = Coordinate::stringFromColumnIndex(self::FIRST_EXTRA_COLUMN + $i);
        }

        for ($row = self::DATA_START_ROW; $row <= $lastDataRow; $row++) {
            foreach ($columns as $column) {
                $cell = $sheet->getCell($column . $row);
                $value = $cell->getValue();
                if ($value !== null) {
                    $cell->setValueExplicit((string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }
    }

    private function writeExtraHeaders($sheet, array $extraColumns): void
    {
        if (empty($extraColumns)) {
            return;
        }

        $firstExtra = self::FIRST_EXTRA_COLUMN;
        $lastExtra = $firstExtra + count($extraColumns) - 1;
        $from = Coordinate::stringFromColumnIndex($firstExtra);
        $to = Coordinate::stringFromColumnIndex($lastExtra);

        $sheet->mergeCells($from . '1:' . $to . '1');
        $sheet->setCellValue($from . '1', self::EXTRA_BANNER_TITLE);

        $columnIndex = $firstExtra;
        foreach ($extraColumns as $definition) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . '2', $definition['title']);
            $columnIndex++;
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '009EF7'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->getStyle($from . '1:' . $to . '1')->applyFromArray($headerStyle);
        $sheet->getStyle($from . '2:' . $to . '2')->applyFromArray($headerStyle);

        for ($i = $firstExtra; $i <= $lastExtra; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
    }

    private function loadContext($rows): void
    {
        $this->rePeople = [];
        $this->fileRecords = [];
        $this->guardianById = [];
        $this->deadPeople = [];
        $this->bankAccounts = [];
        $this->portalFields = [];
        $this->relations = [];
        $this->bankNames = [];

        foreach ($rows->chunk(500) as $chunk) {
            $identities = $chunk->pluck('identity_number')->filter()->unique()->values()->all();
            $guardianIdentities = $chunk->pluck('guardian_identity_number')->filter()->unique()->values()->all();
            $fileIds = collect($chunk->pluck('relation_id_number'))
                ->merge($chunk->pluck('internal_file_number'))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $sponsorshipIds = $chunk->pluck('id')->all();

            if ($identities) {
                RePeople::whereIn('person_id', $identities)->get()->each(function ($record) {
                    $this->rePeople[(string) $record->person_id] = $record;
                });
            }

            if ($fileIds) {
                Data::whereIn('file_id_number', $fileIds)->get()->each(function ($record) {
                    $this->fileRecords[(string) $record->file_id_number] = $record;
                });
            }

            if ($guardianIdentities) {
                Data::whereIn('data_id_number', $guardianIdentities)->get()->each(function ($record) {
                    $this->guardianById[(string) $record->data_id_number] = $record;
                });
            }

            if ($fileIds && Schema::hasTable('dead_people')) {
                DeadPepole::whereIn('re_file_id', $fileIds)->get()->each(function ($record) {
                    $this->deadPeople[(string) $record->re_file_id] = $record;
                });
            }

            if ($fileIds && Schema::hasTable('guardian_bank_accounts')) {
                GuardianBankAccount::whereIn('guardian_registration', $fileIds)
                    ->where('check_account', 1)
                    ->orderBy('id')
                    ->get()
                    ->each(function ($record) {
                        $key = (string) $record->guardian_registration;
                        if (!isset($this->bankAccounts[$key])) {
                            $this->bankAccounts[$key] = $record;
                        }
                    });
            }

            if ($sponsorshipIds && Schema::hasTable('portal_general_registration_field_values')) {
                PortalGeneralRegistrationFieldValue::whereIn('sponsorship_id', $sponsorshipIds)
                    ->whereIn('field_key', self::PORTAL_FIELD_KEYS)
                    ->get()
                    ->each(function ($record) {
                        $this->portalFields[$record->sponsorship_id][$record->field_key] = $record->field_value;
                    });
            }

            $relationshipIds = collect($this->fileRecords)
                ->pluck('data_relationship')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($relationshipIds && Schema::hasTable('category_of_relations')) {
                DB::table('category_of_relations')
                    ->whereIn('id', $relationshipIds)
                    ->get()
                    ->each(function ($record) {
                        $this->relations[(int) $record->id] = $record->attribute;
                    });
            }

            if (Schema::hasTable('bank_names')) {
                DB::table('bank_names')->get()->each(function ($record) {
                    $this->bankNames[(int) $record->id] = $record->description;
                });
            }
        }

        Log::info('ChildFormExportService: context loaded', [
            'rows' => $rows->count(),
            're_people' => count($this->rePeople),
            'data_records' => count($this->fileRecords) + count($this->guardianById),
            'dead_people' => count($this->deadPeople),
            'bank_accounts' => count($this->bankAccounts),
            'portal_rows' => count($this->portalFields),
        ]);
    }

    /**
     * قيم الأعمدة B–Y بالترتيب (بعد عمود الترقيم A).
     */
    private function templateValues($sponsorship): array
    {
        return [
            $this->stringOrNull($sponsorship->identity_number),                                // B رقم الهوية
            $this->orphanNamePart($sponsorship, 1),                                            // C الاسم الأول
            $this->orphanNamePart($sponsorship, 2),                                            // D اسم الأب
            $this->orphanNamePart($sponsorship, 3),                                            // E اسم الجد
            $this->orphanNamePart($sponsorship, 4),                                            // F اسم العائلة
            $this->birthDate($sponsorship),                                                    // G تاريخ الميلاد
            $this->genderText($sponsorship),                                                   // H الجنس
            $this->motherQuartName($sponsorship),                                              // I الاسم الرباعي (أم)
            $this->motherId($sponsorship),                                                     // J رقم هوية الأم
            $this->motherAlive($sponsorship),                                                  // K على قيد الحياة؟
            $this->guardianQuartName($sponsorship),                                            // L الاسم الرباعي (معيل)
            $this->stringOrNull($sponsorship->guardian_identity_number),                       // M رقم الهوية (معيل)
            $this->guardianRelation($sponsorship),                                             // N صلة القرابة
            $this->dependentsCount($sponsorship),                                              // O عدد من يعيلهم
            $this->guardianField($sponsorship, 'data_phone_number')                            // P رقم الهاتف
                ?: $this->portalValue($sponsorship, 'field_data_phone_number')
                ?: null,
            $this->guardianField($sponsorship, 'data_alt_phone_number'),                       // Q رقم الهاتف احتياط
            $this->guardianField($sponsorship, 'data_address_before_displacement'),            // R العنوان السابق
            $this->guardianField($sponsorship, 'data_current_address'),                        // S العنوان الحالي مفصلاً
            $this->bankField($sponsorship, 're_guardian_name'),                                // T اسم صاحب الحساب
            $this->bankField($sponsorship, 'person_owner_identity_number'),                    // U هوية صاحب الحساب
            null,                                                                              // V رقم الحساب (لا مصدر له)
            $this->bankField($sponsorship, 're_phone_number'),                                 // W رقم الجوال
            $this->bankField($sponsorship, 'iban_shekel'),                                     // X الايبان شيكل
            $this->bankField($sponsorship, 'iban_usd'),                                        // Y الايبان دولار
        ];
    }

    private function extraValues($sponsorship, array $extraColumns): array
    {
        $values = [];
        foreach ($extraColumns as $definition) {
            $values[] = $definition['callback']($sponsorship);
        }

        return $values;
    }

    private function orphanRecord($sponsorship): ?RePeople
    {
        $identity = $sponsorship->identity_number;
        if ($identity === null || $identity === '') {
            return null;
        }

        return $this->rePeople[(string) $identity] ?? null;
    }

    private function orphanNamePart($sponsorship, int $position)
    {
        $record = $this->orphanRecord($sponsorship);
        if ($record) {
            $map = [1 => 'first_name', 2 => 'second_name', 3 => 'third_name', 4 => 'last_name'];
            $value = trim((string) ($record->{$map[$position]} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $orphanName = trim((string) ($sponsorship->orphan_name ?? ''));
        if ($orphanName === '') {
            return null;
        }

        $parts = preg_split('/\s+/u', $orphanName) ?: [];

        return $parts[$position - 1] ?? null;
    }

    private function birthDate($sponsorship)
    {
        if ($sponsorship->sponsored_birth_date) {
            return $sponsorship->sponsored_birth_date->format('Y-m-d');
        }

        $record = $this->orphanRecord($sponsorship);
        if ($record && $record->person_birth_date) {
            try {
                return \Illuminate\Support\Carbon::parse((string) $record->person_birth_date)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function genderText($sponsorship)
    {
        $record = $this->orphanRecord($sponsorship);
        if (!$record) {
            return null;
        }

        return match ((int) $record->person_gender) {
            1 => 'ذكر',
            2 => 'أنثى',
            default => null,
        };
    }

    private function motherQuartName($sponsorship)
    {
        $portal = $this->portalFor($sponsorship);
        $status = $portal['field_mother_status'] ?? null;

        if ($status === 'حية') {
            return $this->formatFullName(
                $portal['field_living_mother_first_name'] ?? null,
                $portal['field_living_mother_second_name'] ?? null,
                $portal['field_living_mother_third_name'] ?? null,
                $portal['field_living_mother_last_name'] ?? null
            );
        }

        if ($status === 'متوفية') {
            $dead = $this->deadPeopleFor($sponsorship);
            if ($dead && $dead->mother_first_name) {
                return $this->formatFullName(
                    $dead->mother_first_name,
                    $dead->mother_second_name,
                    $dead->mother_third_name,
                    $dead->mother_last_name
                );
            }

            return $this->stringOrNull($portal['field_mother_first_name'] ?? null);
        }

        return null;
    }

    private function motherId($sponsorship)
    {
        $portal = $this->portalFor($sponsorship);
        $status = $portal['field_mother_status'] ?? null;

        if ($status === 'حية') {
            return $this->stringOrNull($portal['field_living_mother_id'] ?? null);
        }

        if ($status === 'متوفية') {
            $dead = $this->deadPeopleFor($sponsorship);
            if ($dead && $dead->mother_id) {
                return $this->stringOrNull($dead->mother_id);
            }

            return $this->stringOrNull($portal['field_mother_id'] ?? null);
        }

        return null;
    }

    private function motherAlive($sponsorship)
    {
        $status = $this->portalFor($sponsorship)['field_mother_status'] ?? null;

        if ($status === 'حية') {
            return 'نعم';
        }

        if ($status === 'متوفية') {
            return 'لا';
        }

        return null;
    }

    private function guardianRecord($sponsorship): ?Data
    {
        $relationId = $sponsorship->relation_id_number;
        if ($relationId !== null && $relationId !== '' && isset($this->fileRecords[(string) $relationId])) {
            return $this->fileRecords[(string) $relationId];
        }

        $internalId = $sponsorship->internal_file_number;
        if ($internalId !== null && $internalId !== '' && isset($this->fileRecords[(string) $internalId])) {
            return $this->fileRecords[(string) $internalId];
        }

        $guardianIdentity = $sponsorship->guardian_identity_number;
        if ($guardianIdentity !== null && $guardianIdentity !== '' && isset($this->guardianById[(string) $guardianIdentity])) {
            return $this->guardianById[(string) $guardianIdentity];
        }

        return null;
    }

    private function guardianQuartName($sponsorship)
    {
        $record = $this->guardianRecord($sponsorship);
        $quart = null;

        if ($record) {
            $quart = $this->formatFullName(
                $record->data_first_name,
                $record->data_father_name,
                $record->data_grand_father_name,
                $record->data_family_name
            );
        }

        return $quart ?: $this->stringOrNull($sponsorship->guardian_name);
    }

    private function guardianRelation($sponsorship)
    {
        $record = $this->guardianRecord($sponsorship);
        if (!$record || $record->data_relationship === null || $record->data_relationship === '') {
            return null;
        }

        return $this->relations[(int) $record->data_relationship] ?? null;
    }

    private function dependentsCount($sponsorship)
    {
        $record = $this->guardianRecord($sponsorship);
        if (!$record) {
            return null;
        }

        $individuals = (int) ($record->data_number_of_individuals ?? 0);
        $childrenSum = (int) ($record->data_number_female ?? 0) + (int) ($record->data_number_mail ?? 0);

        if ($individuals - $childrenSum === 1 && $childrenSum > 0) {
            return $childrenSum;
        }

        if ($individuals > 0) {
            return $individuals;
        }

        if ($childrenSum > 0) {
            return $childrenSum;
        }

        return null;
    }

    private function guardianField($sponsorship, string $field)
    {
        $record = $this->guardianRecord($sponsorship);
        if (!$record) {
            return null;
        }

        return $this->stringOrNull($record->{$field} ?? null);
    }

    private function bankAccountFor($sponsorship): ?GuardianBankAccount
    {
        $fileId = $sponsorship->relation_id_number ?: $sponsorship->internal_file_number;
        if ($fileId === null || $fileId === '') {
            return null;
        }

        return $this->bankAccounts[(string) $fileId] ?? null;
    }

    private function bankField($sponsorship, string $field)
    {
        $account = $this->bankAccountFor($sponsorship);
        if (!$account) {
            return null;
        }

        return $this->stringOrNull($account->{$field} ?? null);
    }

    private function portalFor($sponsorship): array
    {
        return $this->portalFields[$sponsorship->id] ?? [];
    }

    private function portalValue($sponsorship, string $key)
    {
        return $this->stringOrNull($this->portalFor($sponsorship)[$key] ?? null);
    }

    private function deadPeopleFor($sponsorship): ?DeadPepole
    {
        $fileId = $sponsorship->relation_id_number ?: $sponsorship->internal_file_number;
        if ($fileId === null || $fileId === '') {
            return null;
        }

        return $this->deadPeople[(string) $fileId] ?? null;
    }

    private function formatFullName(...$parts): ?string
    {
        $name = trim(implode(' ', array_filter(array_map(
            fn ($part) => trim((string) ($part ?? '')),
            $parts
        ), fn ($part) => $part !== '')));

        return $name !== '' ? $name : null;
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
