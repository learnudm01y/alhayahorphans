<?php

namespace App\Console\Commands;

use App\Http\Controllers\UnifiedFileManagementController;
use Illuminate\Console\Command;

class CleanupExpiredDuplicates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'duplicates:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired duplicate files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new UnifiedFileManagementController(
            app(\App\Services\FileOrganizationService::class),
            app(\App\Services\CloudIntegrationService::class),
            app(\App\Services\ImageProcessingService::class),
            app(\App\Services\ExcelManagementService::class),
            app(\App\Services\PdfManagementService::class)
        );

        $result = $controller->cleanupExpiredDuplicates();
        $data = $result->getData();

        if ($data->success) {
            $this->info($data->message);
        } else {
            $this->error($data->message);
        }

        return 0;
    }
}
