<?php

namespace App\Exports;

use App\Models\Attachment;
use Illuminate\Support\Facades\Log;

/**
 * ✅ Streaming Export لجدول Attachments
 *
 * المعمارية: cursor → process → write → free → repeat
 * الذاكرة: ثابتة (حتى مع ملايين المرفقات)
 */
class AttachmentsStreamingExport
{
    private $handle;
    private $processedCount = 0;

    /**
     * كتابة headers جدول Attachments
     */
    public function writeHeaders($fileHandle)
    {
        $headers = [
            'ID',
            'رقم الهوية',
            'اسم الملف المخزن',
            'مسار الملف',
            'نوع الملف',
            'تاريخ الإضافة',
            'تاريخ التحديث'
        ];

        fputcsv($fileHandle, $headers);
        Log::info('✅ تم كتابة headers لجدول Attachments');
    }

    /**
     * معالجة البيانات - Streaming Architecture
     */
    public function writeRecords($fileHandle)
    {
        $this->handle = $fileHandle;

        try {
            Log::info('🚀 بدء تصدير Attachments - Streaming Mode');

            $startTime = microtime(true);

            // استخدام cursor للقراءة (سجل واحد في كل مرة)
            $cursor = Attachment::query()
                ->orderBy('id', 'ASC')
                ->cursor();

            foreach ($cursor as $attachment) {
                $this->writeRow($attachment);
                $this->processedCount++;

                // تحرير الذاكرة كل 1000 سجل
                if ($this->processedCount % 1000 === 0) {
                    gc_collect_cycles();
                    Log::info("⚡ Attachments: {$this->processedCount} processed");
                }

                // تحرير الـ model من الذاكرة
                unset($attachment);
            }

            // تحرير الـ cursor
            unset($cursor);
            gc_collect_cycles();

            $endTime = microtime(true);

            Log::info('✅ اكتمل تصدير Attachments', [
                'total_records' => $this->processedCount,
                'duration' => round($endTime - $startTime, 2) . ' seconds'
            ]);

            return $this->processedCount;

        } catch (\Exception $e) {
            Log::error('❌ خطأ في تصدير Attachments', [
                'message' => $e->getMessage(),
                'processed_count' => $this->processedCount
            ]);

            throw $e;
        }
    }

    /**
     * كتابة سجل واحد
     */
    private function writeRow($attachment)
    {
        $row = [
            $attachment->id,
            $attachment->person_identity_number,
            $attachment->stored_file_name,
            $attachment->file_path,
            $attachment->file_type,
            $attachment->created_at ? $attachment->created_at->format('Y-m-d H:i:s') : '',
            $attachment->updated_at ? $attachment->updated_at->format('Y-m-d H:i:s') : ''
        ];

        fputcsv($this->handle, $row);
    }

    /**
     * الحصول على عدد السجلات المعالجة
     */
    public function getProcessedCount()
    {
        return $this->processedCount;
    }
}
