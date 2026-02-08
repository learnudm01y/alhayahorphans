<?php

namespace App\Exports;

use App\Models\RePeople;
use Illuminate\Support\Facades\Log;

class RePeopleStreamingExport
{
    private $filePath;
    private $handle;
    private $processedCount = 0;

    public function __construct()
    {
        $timestamp = date('Ymd_His');
        $this->filePath = storage_path("app/exports/re_people_export_{$timestamp}.csv");
        
        $exportDir = storage_path('app/exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
    }

    public function export()
    {
        try {
            ini_set('memory_limit', '256M');
            set_time_limit(0);

            Log::info('🚀 بدء تصدير RePeople - Streaming Mode');
            
            $startTime = microtime(true);

            $this->handle = fopen($this->filePath, 'w');
            fprintf($this->handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $this->writeHeaders();
            $this->processRecords();

            fclose($this->handle);

            $endTime = microtime(true);

            Log::info('✅ اكتمل تصدير RePeople', [
                'total_records' => $this->processedCount,
                'file_size' => round(filesize($this->filePath) / 1024 / 1024, 2) . ' MB',
                'duration' => round($endTime - $startTime, 2) . ' seconds',
                'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB'
            ]);

            return $this->filePath;

        } catch (\Exception $e) {
            if (isset($this->handle) && is_resource($this->handle)) {
                fclose($this->handle);
            }
            
            Log::error('❌ خطأ في تصدير RePeople: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * كتابة Headers إلى handle معين (للملف الموحد)
     */
    public function writeHeadersToHandle($handle)
    {
        $headers = [
            'ID',
            'رقم التسجيل',
            'حالة الكفالة',
            'الاسم الأول',
            'الاسم الثاني',
            'الاسم الثالث',
            'اللقب',
            'رقم الهوية',
            'تاريخ الميلاد',
            'العمر',
            'الجنس',
            'الحالة الصحية',
            'نوع الكفالة',
            'تاريخ الإنشاء',
            'تاريخ التحديث'
        ];
        
        fputcsv($handle, $headers);
    }

    /**
     * كتابة السجلات إلى handle معين (للملف الموحد)
     */
    public function writeRecordsToHandle($handle): int
    {
        $this->handle = $handle;
        $this->processedCount = 0;
        
        $this->processRecords();
        
        return $this->processedCount;
    }

    private function writeHeaders()
    {
        $this->writeHeadersToHandle($this->handle);
    }

    private function processRecords()
    {
        $totalRecords = RePeople::count();
        Log::info("📊 إجمالي السجلات (RePeople): {$totalRecords}");

        $cursor = RePeople::with([
            'sponsorshipStatus',
            'healthStatus',
            'guaranteeType'
        ])->orderBy('id')->cursor();

        foreach ($cursor as $record) {
            $this->writeRecord($record);
            $this->processedCount++;

            if ($this->processedCount % 1000 === 0) {
                Log::info("📝 معالجة RePeople: {$this->processedCount} / {$totalRecords}");
                gc_collect_cycles();
            }

            unset($record);
        }

        unset($cursor);
        gc_collect_cycles();
    }

    private function writeRecord($row)
    {
        $data = [
            $row->id,
            $row->registration_id,
            $row->sponsorshipStatus?->description ?? '',
            $row->first_name,
            $row->second_name,
            $row->third_name,
            $row->last_name,
            $row->person_id,
            $row->person_birth_date,
            $row->person_age,
            $row->person_gender,
            $row->healthStatus?->description ?? '',
            $row->guaranteeType?->description ?? '',
            $row->created_at,
            $row->updated_at
        ];

        fputcsv($this->handle, $data);
        unset($data);
    }
}
