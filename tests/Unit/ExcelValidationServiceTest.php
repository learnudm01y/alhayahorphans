<?php

namespace Tests\Unit;

use App\Services\ExcelValidationService;
use Tests\TestCase;

class ExcelValidationServiceTest extends TestCase
{
    public function test_legacy_enet_headers_map_to_the_current_database_columns(): void
    {
        $mapping = (new TestableExcelValidationService)->columnMapping('data');

        $this->assertSame('data_current_address', $mapping['data_current_adress'] ?? null);
        $this->assertSame('data_number_mail', $mapping['data_number_male'] ?? null);
    }

    public function test_header_validation_accepts_any_alias_for_each_mandatory_field(): void
    {
        $errors = (new TestableExcelValidationService)->headerValidationErrors([
            'رقم الهوية',
            'data_birth_date',
        ], 'data');

        $this->assertSame([], $errors);
    }

    public function test_validation_failures_expose_the_actual_error_as_a_message(): void
    {
        $missingFile = storage_path('testing/missing-enet-import.xlsx');

        $result = (new ExcelValidationService)->validateExcelFile($missingFile, 'data');

        $this->assertFalse($result['success']);
        $this->assertSame('الملف غير موجود: ' . $missingFile, $result['message'] ?? null);
    }
}

class TestableExcelValidationService extends ExcelValidationService
{
    public function columnMapping(string $tableName): array
    {
        return $this->getColumnMapping($tableName);
    }

    public function headerValidationErrors(array $headers, string $tableName): array
    {
        $this->validateHeaders($headers, $this->getColumnMapping($tableName), $tableName);

        return $this->errors;
    }
}
