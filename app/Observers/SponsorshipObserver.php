<?php

namespace App\Observers;

use App\Models\Sponsorship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observer لمزامنة حالة الكفالة بشكل لحظي عند كل تغيير.
 *
 * عند حفظ/تعديل أي سجل كفالة:
 * - إذا تغيّر حقل sponsorship_status_id
 * - يُحدَّث data.sponsorship_status WHERE data_id_number = identity_number
 * - يُحدَّث re_people.sponsorship_status WHERE person_id = identity_number
 */
class SponsorshipObserver
{
    /**
     * يُنفَّذ بعد تحديث سجل كفالة.
     */
    public function updated(Sponsorship $sponsorship): void
    {
        // نتحقق إذا تغيّر حقل حالة الكفالة فعلاً
        if (!$sponsorship->isDirty('sponsorship_status_id') && !$sponsorship->wasChanged('sponsorship_status_id')) {
            return;
        }

        $this->syncStatus($sponsorship);
    }

    /**
     * يُنفَّذ بعد إنشاء سجل كفالة جديد (تأكد من المزامنة عند الإنشاء أيضاً).
     */
    public function created(Sponsorship $sponsorship): void
    {
        if (empty($sponsorship->sponsorship_status_id) || empty($sponsorship->identity_number)) {
            return;
        }

        $this->syncStatus($sponsorship);
    }

    /**
     * المزامنة الفعلية: تحديث جدولَي data و re_people.
     */
    private function syncStatus(Sponsorship $sponsorship): void
    {
        $identityNumber  = $sponsorship->identity_number;
        $newStatusId     = $sponsorship->sponsorship_status_id;

        if (empty($identityNumber) || empty($newStatusId)) {
            return;
        }

        try {
            // تحديث جدول data
            $dataUpdated = DB::table('data')
                ->where('data_id_number', $identityNumber)
                ->update(['sponsorship_status' => $newStatusId]);

            // تحديث جدول re_people
            $rePeopleUpdated = DB::table('re_people')
                ->where('person_id', $identityNumber)
                ->update(['sponsorship_status' => $newStatusId]);

            Log::info('SponsorshipObserver: مزامنة لحظية ناجحة', [
                'identity_number'       => $identityNumber,
                'sponsorship_id'        => $sponsorship->id,
                'new_status_id'         => $newStatusId,
                'data_rows_updated'     => $dataUpdated,
                're_people_rows_updated'=> $rePeopleUpdated,
            ]);

        } catch (\Throwable $e) {
            Log::error('SponsorshipObserver: فشل المزامنة', [
                'identity_number' => $identityNumber,
                'sponsorship_id'  => $sponsorship->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
