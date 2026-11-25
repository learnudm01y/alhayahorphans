<?php

namespace Tests\Feature;

use App\Http\Controllers\UnifiedFileManagementController;
use App\Models\User;
use App\Services\ExcelValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcelGatewayImportTest extends TestCase
{
    public function test_validation_controller_preserves_the_service_error_message(): void
    {
        Storage::fake('public');

        $user = new User([
            'name' => 'Excel Import Test',
            'email' => 'excel-import@example.test',
        ]);
        $user->id = 1;
        $this->actingAs($user);

        $validationService = $this->createMock(ExcelValidationService::class);
        $validationService->method('validateExcelFile')->willReturn([
            'success' => false,
            'error' => 'تعذر قراءة المصنف المرفوع',
            'errors' => [],
            'can_proceed' => false,
            'statistics' => [
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'duplicate_rows' => 0,
                'skipped_rows' => 0,
            ],
        ]);

        $controller = new UnifiedFileManagementController(excelValidationService: $validationService);
        $file = UploadedFile::fake()->createWithContent(
            'broken.csv',
            "data_id_number,data_birth_date\n123,01-01-2000\n"
        );
        $request = Request::create('/admin/file/validate-excel', 'POST', [
            'target_table' => 'data',
        ], [], [
            'files' => [$file],
        ]);

        $response = $controller->validateExcelBeforeImport($request);

        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('تعذر قراءة المصنف المرفوع', $response->getData(true)['message']);
    }
}
