<?php

namespace App\Observers;

use App\Models\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observer للتأكد من تعيين حالة "مقبول" تلقائياً للبيانات المستوردة من Excel
 */
class DataExcelObserver
{
    /**
     * Handle the Data "creating" event.
     */
    public function creating(Data $data): void
    {
        // إذا كان السجل مستورد من Excel ولا يوجد له حالة طلب
        if (!empty($data->original_file_id_from_excel) &&
            (empty($data->data_request_status) || $data->data_request_status == 0)) {

            $acceptedStatusId = $this->getAcceptedStatusId();
            $data->data_request_status = $acceptedStatusId;

            Log::info('DataExcelObserver: Auto-assigned accepted status', [
                'file_id' => $data->file_id_number,
                'status_id' => $acceptedStatusId,
                'event' => 'creating'
            ]);
        }
    }

    /**
     * Handle the Data "updating" event.
     */
    public function updating(Data $data): void
    {
        // إذا كان السجل مستورد من Excel ولا يوجد له حالة طلب
        if (!empty($data->original_file_id_from_excel) &&
            (empty($data->data_request_status) || $data->data_request_status == 0)) {

            $acceptedStatusId = $this->getAcceptedStatusId();
            $data->data_request_status = $acceptedStatusId;

            Log::info('DataExcelObserver: Auto-assigned accepted status', [
                'file_id' => $data->file_id_number,
                'status_id' => $acceptedStatusId,
                'event' => 'updating'
            ]);
        }
    }

    /**
     * Handle the Data "saved" event.
     */
    public function saved(Data $data): void
    {
        // التحقق النهائي: إذا كان السجل مستورد من Excel ولكن لا يزال بدون حالة صحيحة
        if (!empty($data->original_file_id_from_excel)) {
            $hasCorrectStatus = DB::table('data')
                ->join('request_status', 'data.data_request_status', '=', 'request_status.id')
                ->where('data.id', $data->id)
                ->where('request_status.description', 'مقبول')
                ->exists();

            if (!$hasCorrectStatus) {
                // إصلاح فوري
                $acceptedStatusId = $this->getAcceptedStatusId();
                DB::table('data')
                    ->where('id', $data->id)
                    ->update(['data_request_status' => $acceptedStatusId]);

                Log::warning('DataExcelObserver: Emergency status fix applied', [
                    'data_id' => $data->id,
                    'file_id' => $data->file_id_number,
                    'status_id' => $acceptedStatusId,
                    'event' => 'saved'
                ]);
            }
        }
    }

    /**
     * الحصول على ID حالة "مقبول"
     */
    private function getAcceptedStatusId(): int
    {
        try {
            // البحث المباشر
            $acceptedStatusId = DB::table('request_status')
                ->where('description', 'مقبول')
                ->value('id');

            if ($acceptedStatusId) {
                return (int) $acceptedStatusId;
            }

            // البحث مع تجاهل الحالة
            $acceptedStatusId = DB::table('request_status')
                ->whereRaw('LOWER(description) = ?', ['مقبول'])
                ->value('id');

            if ($acceptedStatusId) {
                return (int) $acceptedStatusId;
            }

            // البحث الجزئي
            $allStatuses = DB::table('request_status')->get();
            foreach ($allStatuses as $status) {
                if (strpos($status->description, 'مقبول') !== false ||
                    strpos($status->description, 'موافق') !== false ||
                    strpos($status->description, 'accepted') !== false) {
                    return (int) $status->id;
                }
            }

            // استخدام أعلى ID كخيار أخير
            $fallbackId = DB::table('request_status')
                ->orderBy('id', 'desc')
                ->value('id');

            return $fallbackId ? (int) $fallbackId : 2;

        } catch (\Exception $e) {
            Log::error('DataExcelObserver: Error finding accepted status', [
                'error' => $e->getMessage()
            ]);
            return 2; // قيمة افتراضية آمنة
        }
    }
}
