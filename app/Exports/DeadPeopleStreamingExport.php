<?php

namespace App\Exports;

use App\Models\DeadPepole;
use Illuminate\Support\Facades\Log;

class DeadPeopleStreamingExport
{
    private $filePath;
    private $handle;
    private $processedCount = 0;

    public function __construct()
    {
        $timestamp = date('Ymd_His');
        $this->filePath = storage_path("app/exports/dead_people_export_{$timestamp}.csv");

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

            Log::info('🚀 بدء تصدير DeadPeople - Streaming Mode');

            $startTime = microtime(true);

            $this->handle = fopen($this->filePath, 'w');
            fprintf($this->handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $this->writeHeaders();
            $this->processRecords();

            fclose($this->handle);

            $endTime = microtime(true);

            Log::info('✅ اكتمل تصدير DeadPeople', [
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

            Log::error('❌ خطأ في تصدير DeadPeople: ' . $e->getMessage());
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
            'رقم الملف',
            'اسم الأب الأول',
            'اسم الأب الثاني',
            'اسم الأب الثالث',
            'لقب الأب',
            'رقم هوية الأب',
            'تاريخ وفاة الأب',
            'سبب وفاة الأب',
            'اسم الأم الأول',
            'اسم الأم الثاني',
            'اسم الأم الثالث',
            'لقب الأم',
            'رقم هوية الأم',
            'تاريخ وفاة الأم',
            'سبب وفاة الأم',
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
        $totalRecords = DeadPepole::count();
        Log::info("📊 إجمالي السجلات (DeadPeople): {$totalRecords}");

        $cursor = DeadPepole::with([
            'fatherDeathReason',
            'motherDeathReason'
        ])->orderBy('id')->cursor();

        foreach ($cursor as $record) {
            $this->writeRecord($record);
            $this->processedCount++;

            if ($this->processedCount % 1000 === 0) {
                Log::info("📝 معالجة DeadPeople: {$this->processedCount} / {$totalRecords}");
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
            $row->re_file_id,
            $row->father_first_name,
            $row->father_second_name,
            $row->father_third_name,
            $row->father_last_name,
            $row->father_id,
            $row->father_death_date,
            $row->fatherDeathReason?->description ?? '',
            $row->mother_first_name,
            $row->mother_second_name,
            $row->mother_third_name,
            $row->mother_last_name,
            $row->mother_id,
            $row->mother_death_date,
            $row->motherDeathReason?->description ?? '',
            $row->created_at,
            $row->updated_at
        ];

        fputcsv($this->handle, $data);
        unset($data);
    }
}
