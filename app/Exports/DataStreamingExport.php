<?php

namespace App\Exports;

use App\Models\Data;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ✅ Streaming Export - معمارية احترافية للتصدير
 *
 * المبدأ: cursor → process → write → free → repeat
 * الذاكرة: ثابتة لا تزيد (حتى مع ملايين السجلات)
 *
 * يمكن استخدامه لتصدير 1 مليون أو 100 مليون سجل بدون أي crash
 */
class DataStreamingExport
{
    private $filePath;
    private $handle;
    private $processedCount = 0;

    public function __construct()
    {
        $timestamp = date('Ymd_His');
        $this->filePath = storage_path("app/exports/data_export_{$timestamp}.csv");

        // إنشاء مجلد exports إذا لم يكن موجود
        $exportDir = storage_path('app/exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
    }

    /**
     * تصدير جدول Data بالكامل - Streaming Architecture
     *
     * ✅ يقرأ سجل واحد في كل مرة (cursor)
     * ✅ يكتب مباشرة إلى الملف
     * ✅ لا يحفظ أي بيانات في الذاكرة
     * ✅ يحرر الذاكرة باستمرار
     */
    public function export()
    {
        try {
            ini_set('memory_limit', '256M'); // كافي جداً مع streaming
            set_time_limit(0);

            Log::info('🚀 بدء تصدير Data - Streaming Mode');

            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            // فتح الملف للكتابة المباشرة
            $this->handle = fopen($this->filePath, 'w');

            // UTF-8 BOM لدعم Excel العربي
            fprintf($this->handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // كتابة الرؤوس
            $this->writeHeaders();

            // معالجة البيانات - Streaming
            $this->processRecords();

            fclose($this->handle);

            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            $peakMemory = memory_get_peak_usage();

            Log::info('✅ اكتمل تصدير Data', [
                'total_records' => $this->processedCount,
                'file_path' => $this->filePath,
                'file_size' => round(filesize($this->filePath) / 1024 / 1024, 2) . ' MB',
                'duration' => round($endTime - $startTime, 2) . ' seconds',
                'memory_used' => round(($endMemory - $startMemory) / 1024 / 1024, 2) . ' MB',
                'peak_memory' => round($peakMemory / 1024 / 1024, 2) . ' MB'
            ]);

            return $this->filePath;

        } catch (\Exception $e) {
            if (isset($this->handle) && is_resource($this->handle)) {
                fclose($this->handle);
            }

            Log::error('❌ خطأ في تصدير Data', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

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
            'القسم',
            'رقم الهوية',
            'الاسم الأول',
            'اسم الأب',
            'اسم الجد',
            'اسم العائلة',
            'صلة القرابة',
            'تاريخ الميلاد',
            'الجنس',
            'رقم الهاتف',
            'رقم هاتف بديل',
            'عدد الأفراد',
            'الحالة الاجتماعية',
            'المؤهل الأكاديمي',
            'حالة النزوح',
            'العنوان قبل النزوح',
            'العنوان الحالي',
            'المدينة',
            'المحافظة',
            'الحالة الصحية',
            'وصف الاحتياجات',
            'حالة التوظيف',
            'حالة السكن',
            'نوع السكن',
            'المستخدم',
            'حالة الطلب',
            'اسم البنك',
            'IBAN USD',
            'IBAN Shekel',
            'رقم الحساب',
            'رقم هوية صاحب الحساب',
            'اسم ولي الأمر',
            'رقم هاتف ولي الأمر',
            'تاريخ الإنشاء',
            'تاريخ التحديث'
        ];

        fputcsv($handle, $headers);
    }

    private function writeHeaders()
    {
        $this->writeHeadersToHandle($this->handle);
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

    /**
     * ✅ المعالجة الحقيقية - Cursor-based Streaming
     *
     * cursor() = يحمل سجل واحد فقط في الذاكرة
     * fputcsv() = يكتب مباشرة إلى القرص
     * unset() = يحرر الذاكرة فوراً
     * gc_collect_cycles() = تنظيف دوري
     */
    private function processRecords()
    {
        $totalRecords = Data::count();
        Log::info("📊 إجمالي السجلات: {$totalRecords}");

        // ✅ استخدام cursor بدلاً من chunk للتصدير الضخم
        // cursor() أفضل لأنه:
        // - يستهلك ذاكرة أقل
        // - يحمل سجل واحد في كل iteration
        // - لا يجمع البيانات في arrays

        $cursor = Data::with([
            'section',
            'categoryOfRelation',
            'maritalStatus',
            'academicQualification',
            'displacementStatus',
            'city',
            'province',
            'healthStatus',
            'employmentStatusBreadwinner',
            'housingStatus',
            'currentHousingType',
            'userInserted',
            'requestStatus',
            'guardianBankAccount.bank'
        ])->orderBy('id')->cursor();

        foreach ($cursor as $record) {
            // كتابة السجل مباشرة إلى الملف
            $this->writeRecord($record);

            $this->processedCount++;

            // تسجيل التقدم كل 1000 سجل
            if ($this->processedCount % 1000 === 0) {
                Log::info("📝 معالجة: {$this->processedCount} / {$totalRecords}");

                // تحرير الذاكرة كل 1000 سجل
                gc_collect_cycles();
            }

            // ✅ تحرير المتغير فوراً
            unset($record);
        }

        // تحرير الذاكرة النهائي
        unset($cursor);
        gc_collect_cycles();
    }

    /**
     * كتابة سجل واحد إلى الملف
     */
    private function writeRecord($row)
    {
        $bankAccount = $row->guardianBankAccount;

        $data = [
            $row->id,
            $row->file_id_number,
            $row->section?->description ?? '',
            $row->data_id_number,
            $row->data_first_name,
            $row->data_father_name,
            $row->data_grand_father_name,
            $row->data_family_name,
            $row->categoryOfRelation?->attribute ?? '',
            $row->data_birth_date,
            $row->gender_text,
            $row->data_phone_number,
            $row->data_alt_phone_number,
            $row->data_number_of_individuals,
            $row->maritalStatus?->CI_PERSONAL_CD ?? '',
            $row->academicQualification?->description ?? '',
            $row->displacementStatus?->description ?? '',
            $row->data_address_before_displacement,
            $row->data_current_address,
            $row->city?->city ?? '',
            $row->province?->description ?? '',
            $row->healthStatus?->description ?? '',
            $row->data_description_needs,
            $row->employmentStatusBreadwinner?->description ?? '',
            $row->housingStatus?->description ?? '',
            $row->currentHousingType?->description ?? '',
            $row->data_user_insert_data ?? $row->userInserted?->name ?? '',
            $row->requestStatus?->description ?? '',
            $bankAccount?->bank?->description ?? '',
            $bankAccount?->iban_usd ?? '',
            $bankAccount?->iban_shekel ?? '',
            $bankAccount?->check_account ?? '',
            $bankAccount?->person_owner_identity_number ?? '',
            $bankAccount?->re_guardian_name ?? '',
            $bankAccount?->re_phone_number ?? '',
            $row->created_at,
            $row->updated_at
        ];

        fputcsv($this->handle, $data);

        // ✅ تحرير فوري
        unset($data, $bankAccount);
    }
}
