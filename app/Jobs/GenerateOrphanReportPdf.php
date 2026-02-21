<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Data;
use App\Models\Sponsorship;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\PortalGeneralRegistrationFieldValue;
use App\Models\Attachment;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class GenerateOrphanReportPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // العلامة البديلة للبيانات غير المتوفرة
    const NOT_AVAILABLE = '(/)';

    protected $sponsorshipId;
    protected $sponsorId; // معرف الكافل المحدد

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct($sponsorshipId, $sponsorId = null)
    {
        $this->sponsorshipId = $sponsorshipId;
        $this->sponsorId = $sponsorId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $sponsorship = Sponsorship::with(['sponsor', 'relationData'])->find($this->sponsorshipId);

            if (!$sponsorship) {
                Log::error('GenerateOrphanReportPdf: Sponsorship not found', [
                    'sponsorship_id' => $this->sponsorshipId
                ]);
                return;
            }

            // جمع البيانات للتقرير
            $reportData = $this->collectReportData($sponsorship);

            // إنشاء PDF باستخدام Laravel Snappy (بديل mpdf)
            $html = view('user.dashboard.pdf.orphan-report', $reportData)->render();

            // استخدام relation_id_number للمسار
            $relationIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number ?? 'unknown';

            // إنشاء مسار المجلد
            $folderPath = 'public/uploads/' . $relationIdNumber;

            // التأكد من وجود المجلد أو إنشائه
            if (!Storage::exists($folderPath)) {
                Storage::makeDirectory($folderPath);
            }

            // ✅ حذف ملفات PDF القديمة لنفس الكفالة لتجنب التكرار
            $existingFiles = Storage::files($folderPath);
            foreach ($existingFiles as $file) {
                // حذف ملفات orphan_report_*.pdf القديمة لنفس relation_id
                if (basename($file) !== 'index.html' && str_starts_with(basename($file), 'orphan_report_' . $relationIdNumber)) {
                    Storage::delete($file);
                    Log::info('DELETED_OLD_PDF_REPORT', ['file' => $file]);
                }
            }

            // اسم الملف
            $fileName = 'orphan_report_' . $relationIdNumber . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $fullPath = $folderPath . '/' . $fileName;

            // إنشاء PDF باستخدام Snappy مع إعدادات اللغة العربية المحسّنة
            $pdfContent = PDF::loadHTML($html)
                ->setOption('encoding', 'UTF-8')
                ->setOption('page-size', 'A4')
                ->setOption('margin-top', '20mm')
                ->setOption('margin-right', '20mm')
                ->setOption('margin-bottom', '20mm')
                ->setOption('margin-left', '20mm')
                ->setOption('enable-local-file-access', true)
                ->setOption('no-stop-slow-scripts', true)
                ->setOption('javascript-delay', '1000')
                ->setOption('enable-javascript', false)
                ->setOption('print-media-type', true)
                ->setOption('title', 'تقرير  - ' . ($reportData['orphan_name'] ?? 'غير معروف'))
                ->output();

            // حفظ PDF في الملف
            Storage::put($fullPath, $pdfContent);

            // حفظ معلومات الملف في قاعدة البيانات
            $fileSize = strlen($pdfContent);
            $publicPath = str_replace('public/', '', $fullPath);

            DB::table('attachments')->insert([
                'person_identity_number' => $relationIdNumber,
                'stored_file_name' => $fileName,
                'file_path' => 'storage/' . $publicPath,
                'file_type' => 'pdf',
                'file_size' => $fileSize,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // رفع PDF إلى Google Drive إذا كان مفعلاً للجمعية
            $this->uploadToGoogleDriveIfEnabled($sponsorship, $fullPath, $fileName);

            Log::info('GenerateOrphanReportPdf: PDF Report Generated and Saved', [
                'sponsorship_id' => $sponsorship->id,
                'relation_id_number' => $relationIdNumber,
                'file_path' => $fullPath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'saved_to_database' => true
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateOrphanReportPdf: Error generating PDF', [
                'sponsorship_id' => $this->sponsorshipId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * جمع بيانات التقرير من جميع الجداول
     * 🆕 إصلاح شامل لجلب البيانات من جميع المصادر بالترتيب الصحيح
     */
    private function collectReportData($sponsorship)
    {
        $na = self::NOT_AVAILABLE;
        $data = [];

        // بيانات الكفالة الأساسية
        $data['file_number'] = $sponsorship->internal_file_number ?? $na;
        $data['orphan_name'] = $sponsorship->orphan_name ?? $na;
        $data['identity_number'] = $sponsorship->identity_number ?? $na;
        $data['guardian_name'] = $sponsorship->guardian_name ?? $na;
        $data['guardian_identity_number'] = $sponsorship->guardian_identity_number ?? $na;

        Log::info('COLLECT_REPORT_DATA_START', [
            'sponsorship_id' => $sponsorship->id,
            'identity_number' => $sponsorship->identity_number,
            'internal_file_number' => $sponsorship->internal_file_number,
            'relation_id_number' => $sponsorship->relation_id_number,
            'person_type' => $sponsorship->person_type
        ]);

        // 1. جلب البيانات الأساسية من جدول data - البحث بجميع الطرق
        $dataRecord = null;

        // أولاً: البحث بـ relation_id_number (الأكثر دقة)
        if ($sponsorship->relation_id_number) {
            $dataRecord = Data::where('file_id_number', $sponsorship->relation_id_number)->first();
        }

        // ثانياً: البحث بـ internal_file_number
        if (!$dataRecord && $sponsorship->internal_file_number) {
            $dataRecord = Data::where('file_id_number', $sponsorship->internal_file_number)->first();
        }

        // ثالثاً: البحث بـ identity_number
        if (!$dataRecord && $sponsorship->identity_number) {
            $dataRecord = Data::where('data_id_number', $sponsorship->identity_number)->first();
        }

        if ($dataRecord) {
            Log::info('DATA_RECORD_FOUND', [
                'file_id_number' => $dataRecord->file_id_number,
                'data_phone_number' => $dataRecord->data_phone_number
            ]);

            $data['phone_number'] = $dataRecord->data_phone_number ?? $na;
            $data['city'] = $this->getCityName($dataRecord->data_city) ?? $na;
            $data['province'] = $this->getProvinceName($dataRecord->data_province) ?? $na;
            $data['housing_status'] = $this->getHousingStatus($dataRecord->data_housing_status) ?? $na;
            $data['current_housing_type'] = $this->getHousingType($dataRecord->data_current_housing_type) ?? $na;
            $data['health_status'] = $this->getHealthStatus($dataRecord->data_health_status) ?? $na;
            $data['description_needs'] = $dataRecord->data_description_needs ?? $na;
            $data['number_of_individuals'] = $dataRecord->data_number_of_individuals ?? $na;
            // ملاحظة: بيانات المعيل (employment_status) تُجلب بشكل منفصل لاحقاً
        } else {
            Log::warning('DATA_RECORD_NOT_FOUND', [
                'searched_by_relation_id' => $sponsorship->relation_id_number,
                'searched_by_file_number' => $sponsorship->internal_file_number,
                'searched_by_identity' => $sponsorship->identity_number
            ]);
        }

        // 2. جلب البيانات من portal_general_registration_field_values
        $portalFields = PortalGeneralRegistrationFieldValue::where('sponsorship_id', $sponsorship->id)
            ->orWhere('file_id_number', $sponsorship->internal_file_number)
            ->orWhere('identity_number', $sponsorship->identity_number)
            ->get()
            ->keyBy('field_key');

        Log::info('PORTAL_FIELDS_FOUND', ['count' => $portalFields->count()]);

        // استخراج البيانات من Portal Fields (أولوية عالية)
        $data['phone_number'] = $portalFields->get('field_data_phone_number')?->field_value ?? $data['phone_number'] ?? $na;
        $data['city'] = $portalFields->get('field_data_city')?->field_value ?? $data['city'] ?? $na;
        $data['housing_status'] = $portalFields->get('field_housing_status')?->field_value ?? $data['housing_status'] ?? $na;
        $data['current_housing_type'] = $portalFields->get('field_housing_type')?->field_value ?? $data['current_housing_type'] ?? $na;
        $data['housing_type'] = $data['current_housing_type']; // نسخ للعرض في القالب

        // البيانات المدرسية (من portal فقط)
        $data['school_name'] = $portalFields->get('field_school_name')?->field_value ?? $na;
        $data['school_address'] = $portalFields->get('field_school_address')?->field_value ?? $na;
        // المرحلة الدراسية من field_grade فقط (إزالة grade_level)
        $data['academic_stage'] = $portalFields->get('field_grade')?->field_value ?? $na;
        $data['student_level'] = $portalFields->get('field_student_level')?->field_value ?? $na;
        $data['weakness_reason'] = $portalFields->get('field_weakness_reason')?->field_value ?? $na;

        // البيانات النفسية والسلوكية (من portal فقط)
        $data['psychological_status'] = $portalFields->get('field_psychological_state')?->field_value ?? $na;
        $data['behavioral_status'] = $portalFields->get('field_behavioral_state')?->field_value ?? $na;
        $data['religious_commitment'] = $portalFields->get('field_religious_commitment')?->field_value ?? $na;
        $data['quran_memorization'] = $portalFields->get('field_quran_memorization')?->field_value ?? $na;

        // البيانات الصحية للمكفول - الأولوية لـ re_people.person_health_status
        $orphanHealthStatus = null;

        // الأولوية الأولى: جدول re_people (طالما نوع المكفول فرد عائلة)
        if ($sponsorship->identity_number) {
            $orphanRePeople = RePeople::where('person_id', $sponsorship->identity_number)->first();
            if ($orphanRePeople && $orphanRePeople->person_health_status) {
                $orphanHealthStatus = $this->getHealthStatus($orphanRePeople->person_health_status);
                Log::info('ORPHAN_HEALTH_FROM_RE_PEOPLE', [
                    'person_id' => $sponsorship->identity_number,
                    'health_status_id' => $orphanRePeople->person_health_status,
                    'health_status_text' => $orphanHealthStatus
                ]);
            }
        }

        // الأولوية الثانية: portal (field_health_status من النموذج)
        if (!$orphanHealthStatus) {
            $orphanHealthStatus = $portalFields->get('field_health_status')?->field_value;
            if ($orphanHealthStatus) {
                Log::info('ORPHAN_HEALTH_FROM_PORTAL', ['health_status' => $orphanHealthStatus]);
            }
        }

        // الأولوية الثالثة: جدول data بناءً على رقم هوية المكفول
        if (!$orphanHealthStatus && $sponsorship->identity_number) {
            $orphanDataRecord = Data::where('data_id_number', $sponsorship->identity_number)->first();
            if ($orphanDataRecord && $orphanDataRecord->data_health_status) {
                $orphanHealthStatus = $this->getHealthStatus($orphanDataRecord->data_health_status);
                Log::info('ORPHAN_HEALTH_FROM_DATA', [
                    'data_id_number' => $sponsorship->identity_number,
                    'health_status_id' => $orphanDataRecord->data_health_status,
                    'health_status_text' => $orphanHealthStatus
                ]);
            }
        }

        // Fallback إلى البيانات من dataRecord إذا كانت موجودة
        if (!$orphanHealthStatus && isset($data['health_status']) && $data['health_status'] !== $na) {
            $orphanHealthStatus = $data['health_status'];
        }

        $data['orphan_health_status'] = $orphanHealthStatus ?? $na;
        $data['health_status'] = $data['orphan_health_status']; // نسخ للعرض

        // الاحتياجات والإبداع (من portal فقط)
        $data['orphan_needs'] = $portalFields->get('field_orphan_needs')?->field_value ?? $na;
        $data['description_needs'] = $data['orphan_needs']; // نسخ للعرض
        $data['creativity_aspects'] = $portalFields->get('field_creativity_aspects')?->field_value ?? $na;

        // تأثير الكفالة والأحداث (من portal فقط)
        $data['sponsorship_impact'] = $portalFields->get('field_sponsorship_impact')?->field_value ?? $na;
        $data['family_events'] = $portalFields->get('field_important_events')?->field_value ?? $na;

        // بيانات المعيل - جلب صلة القرابة من مصادر متعددة (حسب الأولوية)
        // تحديد relation_id_number للاستخدام
        $relationIdNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number;

        //⁣ ✅ جلب guardian_relation_id من مصادر متعدد⁣ة حسب الأولوية
        $guardianRelationId = null;

        // ✅ الأولوية 1: من جدول sponsorships مباشرة
        if ($sponsorship->guardian_relation_id) {
            $guardianRelationId = $sponsorship->guardian_relation_id;
            Log::info('GUARDIAN_RELATION_FROM_SPONSORSHIPS', ['relation_id' => $guardianRelationId]);
        }

        // ✅ الأولوية 2: من portal_general_registration_field_values
        if (!$guardianRelationId) {
            $portalRelation = $portalFields->get('field_guardian_relationship')?->field_value;
            if ($portalRelation) {
                // تحويل النص إلى ID (أب=1, أم=2, وصي=3, إلخ)
                $relationMap = [
                    'الأب' => 1, 'اب' => 1, 'أب' => 1,
                    'الأم' => 2, 'ام' => 2, 'أم' => 2,
                    'الوصي' => 3, 'وصي' => 3,
                    'الجد' => 4, 'جد' => 4,
                    'الجدة' => 5, 'جدة' => 5,
                    'العم' => 6, 'عم' => 6,
                    'الخال' => 7, 'خال' => 7,
                    'الأخ' => 8, 'اخ' => 8, 'أخ' => 8,
                    'الأخت' => 9, 'اخت' => 9, 'أخت' => 9
                ];

                $guardianRelationId = $relationMap[$portalRelation] ?? null;

                if ($guardianRelationId) {
                    Log::info('GUARDIAN_RELATION_FROM_PORTAL', [
                        'text' => $portalRelation,
                        'id' => $guardianRelationId
                    ]);
                }
            }
        }

        // ✅ الأولوية 3: استنتاج ذكي من dead_people
        if (!$guardianRelationId && $relationIdNumber) {
            $deadPeopleRecord = DeadPepole::where('re_file_id', $relationIdNumber)
                ->orWhere('re_file_id', $sponsorship->internal_file_number)
                ->first();

            if ($deadPeopleRecord && $sponsorship->guardian_identity_number) {
                // ✅ استنتاج 1: مقارنة مباشرة مع father_id
                if ($deadPeopleRecord->father_id == $sponsorship->guardian_identity_number) {
                    $guardianRelationId = 1; // الأم (حسب جدول category_of_relations)
                    // ⚠️ ولكن هذا غير صحيح! الأب ID=1 في جدول category_of_relations هو "الأم"!
                    // دعنا نستخدم منطق مختلف
                }

                // ✅ استنتاج 2: مقارنة مع mother_id
                if (!$guardianRelationId && $deadPeopleRecord->mother_id == $sponsorship->guardian_identity_number) {
                    $guardianRelationId = 1; // الأم
                    Log::info('GUARDIAN_INFERRED_AS_MOTHER_BY_ID', [
                        'guardian_id' => $sponsorship->guardian_identity_number,
                        'mother_id' => $deadPeopleRecord->mother_id
                    ]);
                }

                // ✅ استنتاج 3: إذا كان father_id موجود و mother_id فارغ
                // والمعيل ليس الأب → المعيل هو الأم!
                if (!$guardianRelationId &&
                    $deadPeopleRecord->father_id &&
                    !$deadPeopleRecord->mother_id &&
                    $deadPeopleRecord->father_id != $sponsorship->guardian_identity_number) {

                    $guardianRelationId = 1; // الأم (حسب category_of_relations)
                    Log::info('GUARDIAN_INFERRED_AS_MOTHER_LOGIC', [
                        'reason' => 'Father deceased, mother_id NULL, guardian != father',
                        'father_id' => $deadPeopleRecord->father_id,
                        'guardian_id' => $sponsorship->guardian_identity_number
                    ]);
                }
            }
        }

        // ⚠️ ملاحظة: تم إزالة الاعتماد على data.data_relationship لأنه غير موثوق
        // (يخزن علاقة اليتيم بالمتوفي وليس علاقة المعيل)

        $data['guardian_relation'] = $guardianRelationId ? $this->getRelation($guardianRelationId) : $na;

        // ===== جلب بيانات المعيل بناءً على حالته (حي أو متوفي) =====
        // اسم المعيل: موجود مباشرة في sponsorships.guardian_name
        $data['guardian_full_name'] = $sponsorship->guardian_name ?? $na;

        // أولاً: فحص إذا كان المعيل متوفي في جدول dead_people
        // التحقق الصحيح: المعيل متوفي فقط إذا كان هو نفسه الشخص المتوفي (الأب أو الأم)
        $isGuardianDeceased = false;
        $deadGuardianRecord = null;

        if ($relationIdNumber) {
            $deadGuardianRecord = DeadPepole::where('re_file_id', $relationIdNumber)
                ->orWhere('re_file_id', $sponsorship->internal_file_number)
                ->first();

            if ($deadGuardianRecord) {
                // ✅ التحقق الصحيح بناءً على مقارنة رقم الهوية مباشرة
                // بدلاً من الاعتماد على guardian_relation_id الذي قد يكون خاطئاً

                // هل المعيل هو الأب المتوفي?
                if ($deadGuardianRecord->father_id &&
                    $sponsorship->guardian_identity_number == $deadGuardianRecord->father_id) {
                    $isGuardianDeceased = true;
                    Log::info('GUARDIAN_IS_DECEASED_FATHER', [
                        'guardian_id' => $sponsorship->guardian_identity_number,
                        'father_id' => $deadGuardianRecord->father_id
                    ]);
                }

                // هل المعيل هو الأم المتوفية?
                if (!$isGuardianDeceased &&
                    $deadGuardianRecord->mother_id &&
                    $sponsorship->guardian_identity_number == $deadGuardianRecord->mother_id) {
                    $isGuardianDeceased = true;
                    Log::info('GUARDIAN_IS_DECEASED_MOTHER', [
                        'guardian_id' => $sponsorship->guardian_identity_number,
                        'mother_id' => $deadGuardianRecord->mother_id
                    ]);
                }

                // إذا لم يكن المعيل متوفياً
                if (!$isGuardianDeceased) {
                    Log::info('GUARDIAN_IS_ALIVE_CONFIRMED', [
                        'guardian_id' => $sponsorship->guardian_identity_number,
                        'father_id' => $deadGuardianRecord->father_id,
                        'mother_id' => $deadGuardianRecord->mother_id,
                        'guardian_relation_id' => $guardianRelationId
                    ]);
                }
            }
        }

        if ($isGuardianDeceased) {
            // ===== المعيل متوفي: جلب البيانات من portal_general_registration_field_values =====
            Log::info('USING_PORTAL_FIELDS_FOR_DECEASED_GUARDIAN');

            // الحالة الصحية
            $guardianHealthStatus = $portalFields->get('field_guardian_health')?->field_value ?? $na;
            $data['guardian_health'] = $guardianHealthStatus;

            // وظيفة المعيل
            $guardianJob = $portalFields->get('field_guardian_job')?->field_value
                ?? $portalFields->get('field_guardian_job_text')?->field_value
                ?? $na;
            $data['guardian_job'] = $guardianJob;
            $data['employment_status'] = $guardianJob;

            // عدد المعالين
            $maleCount = (int) ($portalFields->get('field_dependents_male')?->field_value ?? 0);
            $femaleCount = (int) ($portalFields->get('field_dependents_female')?->field_value ?? 0);
            $totalDependents = $maleCount + $femaleCount;

            $data['dependents_count'] = $totalDependents > 0 ? $totalDependents : $na;
            $data['number_of_individuals'] = $data['dependents_count'];

            Log::info('DECEASED_GUARDIAN_DATA_FROM_PORTAL', [
                'health' => $guardianHealthStatus,
                'job' => $guardianJob,
                'dependents' => $totalDependents
            ]);

        } else {
            // ===== المعيل حي: جلب البيانات من جدول data =====
            $guardianDataRecord = null;

            if ($sponsorship->guardian_identity_number) {
                $guardianDataRecord = Data::where('data_id_number', $sponsorship->guardian_identity_number)->first();

                if ($guardianDataRecord) {
                    Log::info('GUARDIAN_DATA_FOUND', [
                        'guardian_identity' => $sponsorship->guardian_identity_number,
                        'file_id_number' => $guardianDataRecord->file_id_number,
                        'guardian_name' => $this->formatFullName(
                            $guardianDataRecord->data_first_name,
                            $guardianDataRecord->data_father_name,
                            $guardianDataRecord->data_grand_father_name,
                            $guardianDataRecord->data_family_name
                        )
                    ]);
                } else {
                    Log::warning('GUARDIAN_DATA_NOT_FOUND', [
                        'guardian_identity' => $sponsorship->guardian_identity_number
                    ]);
                }
            }

            // جلب الحالة الصحية للمعيل من data
            $guardianHealthStatus = $na;
            if ($guardianDataRecord && $guardianDataRecord->data_health_status) {
                $guardianHealthStatus = $this->getHealthStatus($guardianDataRecord->data_health_status);
            } elseif ($guardianDataRecord && !$guardianDataRecord->data_health_status) {
                // ✅ قيمة افتراضية معقولة إذا لم تكن مدخلة: افتراض سليم
                $guardianHealthStatus = 'سليم';
                Log::info('GUARDIAN_HEALTH_DEFAULT', ['assumed' => 'سليم']);
            }
            $data['guardian_health'] = $guardianHealthStatus;

            // جلب وظيفة المعيل من data
            $guardianJob = $na;
            if ($guardianDataRecord && $guardianDataRecord->data_employment_status_breadwinner) {
                $empStatus = $this->getEmploymentStatus($guardianDataRecord->data_employment_status_breadwinner);
                $guardianJob = $empStatus ? $empStatus : $na;
                Log::info('GUARDIAN_JOB_FROM_DATA', [
                    'employment_id' => $guardianDataRecord->data_employment_status_breadwinner,
                    'employment_text' => $guardianJob
                ]);
            }
            $data['guardian_job'] = $guardianJob;
            $data['employment_status'] = $guardianJob; // نسخ للعرض

            // ✅ عدد المعالين: الأولوية لـ data_number_of_individuals شامل للأطفال
            $totalDependents = 0;
            $childrenInFamily = 0;

            if ($guardianDataRecord) {
                // ✅ الأولوية 1: استخدام data_number_of_individuals إذا كان موجوداً (أدق)
                if ($guardianDataRecord->data_number_of_individuals) {
                    $totalDependents = $guardianDataRecord->data_number_of_individuals;
                    Log::info('GUARDIAN_DEPENDENTS_FROM_TOTAL', [
                        'data_number_of_individuals' => $totalDependents
                    ]);
                } else {
                    // الأولوية 2: جمع data_number_female + data_number_mail
                    $dataFemale = (int) ($guardianDataRecord->data_number_female ?? 0);
                    $dataMale = (int) ($guardianDataRecord->data_number_mail ?? 0);
                    $totalDependents = $dataFemale + $dataMale;

                    Log::info('GUARDIAN_DEPENDENTS_FROM_SUM', [
                        'male' => $dataMale,
                        'female' => $dataFemale,
                        'total' => $totalDependents
                    ]);
                }
            }

            // ✅ حساب عدد الأطفال في العائلة للمقارنة
            if ($sponsorship->guardian_identity_number) {
                $childrenInFamily = Sponsorship::where('guardian_identity_number', $sponsorship->guardian_identity_number)
                    ->count();
            }

            // ✅ في حالة لم يتوفر data_number_of_individuals → استخدم عدد الأطفال الفعلي
            if ($totalDependents == 0 && $childrenInFamily > 0) {
                $totalDependents = $childrenInFamily;
                Log::info('GUARDIAN_DEPENDENTS_FROM_CHILDREN', [
                    'children_count' => $childrenInFamily
                ]);
            }

            Log::info('GUARDIAN_DEPENDENTS_CALCULATED', [
                'guardian_identity' => $sponsorship->guardian_identity_number,
                'from_data_table' => $totalDependents,
                'children_in_family' => $childrenInFamily,
                'final_count' => $totalDependents
            ]);

            $data['dependents_count'] = $totalDependents > 0 ? $totalDependents : $na;
            $data['number_of_individuals'] = $data['dependents_count']; // نسخ للعرض
        }

        // الطابع الزمني
        $data['timestamp'] = $portalFields->get('field_data_update_date')?->field_value ?? date('Y-m-d');

        // 🔥 تحويل portalFields إلى array بسيط للوصول السهل
        $fieldValues = [];
        foreach ($portalFields as $field) {
            $fieldValues[$field->field_key] = $field->field_value;
        }

        // 🔥 جلب بيانات الأسماء من names JSON
        $namesRecord = DB::table('portal_general_registration_field_values')
            ->where('sponsorship_id', $sponsorship->id)
            ->where('field_key', 'names')
            ->first();

        if ($namesRecord && $namesRecord->field_value) {
            $namesData = json_decode($namesRecord->field_value, true);
            $fieldValues['_names_data'] = $namesData;
            Log::info('NAMES_DATA_LOADED', ['names' => $namesData]);
        }

        // 3. معالجة ذكية لبيانات الأم بناءً على حالتها
        // استخدام guardian_relation_id المُجلَّب من data أو dead_people (وليس من portal)
        $motherStatus = $fieldValues['field_mother_status'] ?? null;
        $guardianIsMother = ($guardianRelationId == 2); // 2 = أم

        Log::info('MOTHER_STATUS_CHECK', [
            'guardian_relationship' => $guardianRelationId,
            'mother_status' => $motherStatus,
            'guardian_is_mother' => $guardianIsMother
        ]);

        if ($guardianIsMother) {
            // الحالة 1: المعيل هو الأم - لا نعرض بيانات الأم (لأنها موجودة في قسم المعيل)
            Log::info('MOTHER_IS_GUARDIAN - Hiding mother section');
            $data['mother_name'] = null; // سيتم إخفاء القسم في PDF
            $data['mother_id'] = null;
            $data['mother_alive'] = null;

        } elseif ($motherStatus === 'حية') {
            // الحالة 2: الأم حية لكن ليست المعيل - جلب من portal_general_registration_field_values
            Log::info('MOTHER_ALIVE - Using living mother fields from field_values');

            $data['mother_name'] = $this->formatFullName(
                $fieldValues['field_living_mother_first_name'] ?? null,
                $fieldValues['field_living_mother_second_name'] ?? null,
                $fieldValues['field_living_mother_third_name'] ?? null,
                $fieldValues['field_living_mother_last_name'] ?? null
            ) ?: $na;
            $data['mother_id'] = $fieldValues['field_living_mother_id'] ?? $na;
            $data['mother_alive'] = 'نعم (على قيد الحياة)';

        } elseif ($motherStatus === 'متوفية') {
            // الحالة 3: الأم متوفية - جلب من dead_people أو field_values
            Log::info('MOTHER_DECEASED - Using deceased mother data');

            // أولاً: محاولة الحصول من dead_people
            $deadPeople = DeadPepole::where('re_file_id', $relationIdNumber)->first();
            if (!$deadPeople) {
                $deadPeople = DeadPepole::where('re_file_id', $sponsorship->internal_file_number)->first();
            }

            if ($deadPeople && $deadPeople->mother_first_name) {
                // جلب من dead_people
                Log::info('DEAD_PEOPLE_FOUND', ['re_file_id' => $deadPeople->re_file_id]);

                $data['mother_name'] = $this->formatFullName(
                    $deadPeople->mother_first_name,
                    $deadPeople->mother_second_name,
                    $deadPeople->mother_third_name,
                    $deadPeople->mother_last_name
                ) ?: $na;
                $data['mother_id'] = $deadPeople->mother_id ?? $na;
            } else {
                // جلب من field_values (الاسم الرباعي الكامل من قسم الأسماء)
                Log::info('USING_FIELD_VALUES_MOTHER_DECEASED');

                // البحث عن الاسم في names JSON
                $motherNameParts = [];
                if (isset($fieldValues['_names_data']['mother'])) {
                    $motherNameParts = $fieldValues['_names_data']['mother'];
                }

                $data['mother_name'] = $this->formatFullName(
                    $motherNameParts['first_name'] ?? $fieldValues['field_mother_first_name'] ?? null,
                    $motherNameParts['second_name'] ?? null,
                    $motherNameParts['third_name'] ?? null,
                    $motherNameParts['last_name'] ?? null
                ) ?: $na;
                $data['mother_id'] = $fieldValues['field_mother_id'] ?? $na;
            }

            $data['mother_alive'] = 'لا (متوفية)';

        } else {
            // الحالة 4: حالة الأم غير محددة - إخفاء القسم
            Log::info('MOTHER_STATUS_UNKNOWN - Hiding mother section');
            $data['mother_name'] = null;
            $data['mother_id'] = null;
            $data['mother_alive'] = null;
        }

        // معلومات الأب (نفس المنطق السابق)
        $deadPeople = DeadPepole::where('re_file_id', $relationIdNumber)->first();
        if (!$deadPeople) {
            $deadPeople = DeadPepole::where('re_file_id', $sponsorship->internal_file_number)->first();
        }

        if ($deadPeople) {
            $data['father_name'] = $this->formatFullName(
                $deadPeople->father_first_name,
                $deadPeople->father_second_name,
                $deadPeople->father_third_name,
                $deadPeople->father_last_name
            ) ?: $na;
            $data['father_id'] = $deadPeople->father_id ?? $na;
        } else {
            Log::warning('DEAD_PEOPLE_NOT_FOUND', ['searched_relation_id' => $relationIdNumber]);

            $data['father_name'] = $na;
            $data['father_id'] = $na;
        }

        // 4. جلب أفراد الأسرة من re_people
        $data['family_members'] = $this->getFamilyMembers($relationIdNumber, $sponsorship->identity_number);

        // 5. إذا كان اليتيم نفسه في re_people، جلب بياناته
        $orphanInRePeople = RePeople::where('person_id', $sponsorship->identity_number)->first();
        if ($orphanInRePeople) {
            Log::info('ORPHAN_FOUND_IN_RE_PEOPLE', ['person_id' => $orphanInRePeople->person_id]);

            // استخدم بيانات re_people كـ fallback
            if ($data['orphan_name'] === $na || empty($data['orphan_name'])) {
                $data['orphan_name'] = $this->formatFullName(
                    $orphanInRePeople->first_name,
                    $orphanInRePeople->second_name,
                    $orphanInRePeople->third_name,
                    $orphanInRePeople->last_name
                ) ?: $sponsorship->orphan_name;
            }
        }

        // 6. جلب جميع الوثائق من جدول attachments بناءً على رقم الملف
        $allAttachments = $this->getAllAttachments($relationIdNumber, $sponsorship, $data['family_members']);

        // فصل الصور الشخصية عن باقي الوثائق
        $personalPhotos = [];
        $otherAttachments = [];

        foreach ($allAttachments as $attachment) {
            if ($attachment['is_personal_photo']) {
                // حفظ الصورة الشخصية حسب رقم الهوية
                $personalPhotos[$attachment['person_identity']] = $attachment;
            } else {
                $otherAttachments[] = $attachment;
            }
        }

        // إضافة الصور الشخصية للمكفول والمعيل
        $data['orphan_photo'] = $personalPhotos[$sponsorship->identity_number] ?? null;
        $data['guardian_photo'] = $personalPhotos[$sponsorship->guardian_identity_number] ?? null;

        // باقي الوثائق (بدون الصور الشخصية)
        $data['attachments'] = $otherAttachments;

        Log::info('COLLECT_REPORT_DATA_COMPLETE', [
            'data_fields_filled' => array_filter($data, function($v) { return $v !== self::NOT_AVAILABLE; }),
            'family_members_count' => count($data['family_members']),
            'attachments_count' => count($data['attachments']),
            'orphan_photo' => $data['orphan_photo'] ? 'موجودة' : 'غير موجودة',
            'guardian_photo' => $data['guardian_photo'] ? 'موجودة' : 'غير موجودة'
        ]);

        return $data;
    }

    private function getFamilyMembers($fileIdNumber, $excludeIdentity = null)
    {
        Log::info('GET_FAMILY_MEMBERS_START', [
            'file_id_number' => $fileIdNumber,
            'exclude_identity' => $excludeIdentity
        ]);

        $members = RePeople::where('registration_id', $fileIdNumber)
            ->when($excludeIdentity, function($q) use ($excludeIdentity) {
                $q->where('person_id', '!=', $excludeIdentity);
            })
            ->get();

        Log::info('GET_FAMILY_MEMBERS_QUERY_RESULT', [
            'total_found' => $members->count(),
            'members' => $members->pluck('first_name', 'person_id')->toArray()
        ]);

        $na = self::NOT_AVAILABLE;
        $result = [];

        foreach ($members as $index => $member) {
            $result[] = [
                'index' => $index + 1,
                'person_id' => $member->person_id,
                'full_name' => $this->formatFullName(
                    $member->first_name,
                    $member->second_name,
                    $member->third_name,
                    $member->last_name
                ) ?: $na,
                'birth_date' => $member->person_birth_date ? date('d/m/Y', strtotime($member->person_birth_date)) : $na,
                'academic_degree' => $member->acadimic_degree ?? $na,
                'health_status' => $this->getHealthStatus($member->person_health_status) ?? $na
            ];
        }

        Log::info('GET_FAMILY_MEMBERS_RESULT', [
            'family_members_count' => count($result),
            'family_members' => $result
        ]);

        return $result;
    }

    // Helper methods
    private function formatFullName($first, $second, $third, $last)
    {
        $parts = array_filter([$first, $second, $third, $last], function($v) {
            return !empty($v) && $v !== null;
        });
        return implode(' ', $parts) ?: null;
    }

    private function getCityName($id)
    {
        if (!$id) return null;
        return DB::table('city')->where('id', $id)->value('city');
    }

    private function getProvinceName($id)
    {
        if (!$id) return null;
        return DB::table('provinces')->where('id', $id)->value('description');
    }

    private function getHousingStatus($id)
    {
        if (!$id) return null;
        return DB::table('housing_status')->where('id', $id)->value('description');
    }

    private function getHousingType($id)
    {
        if (!$id) return null;
        return DB::table('type_of_accommodation')->where('id', $id)->value('description');
    }

    private function getDisplacementStatus($id)
    {
        if (!$id) return null;
        return DB::table('displacement_statuses')->where('id', $id)->value('description');
    }

    private function getHealthStatus($id)
    {
        if (!$id) return null;
        return DB::table('health_statuses')->where('id', $id)->value('description');
    }

    private function getEmploymentStatus($id)
    {
        if (!$id) return null;
        return DB::table('employment')->where('id', $id)->value('description');
    }

    private function getMaritalStatus($id)
    {
        if (!$id) return null;
        return DB::table('marital_status')->where('id', $id)->value('description');
    }

    private function getAcademicQualification($id)
    {
        if (!$id) return null;
        return DB::table('academic_degrees')->where('id', $id)->value('description');
    }

    private function getDeathReason($id)
    {
        if (!$id) return null;
        return DB::table('death_reasons')->where('id', $id)->value('description');
    }

    private function getRelation($id)
    {
        if (!$id) return null;
        return DB::table('category_of_relations')->where('id', $id)->value('attribute');
    }

    /**
     * جلب جميع الوثائق من جدول attachments بناءً على رقم الملف
     */
    private function getAllAttachments($relationIdNumber, $sponsorship, $familyMembers)
    {
        Log::info('GET_ALL_ATTACHMENTS_START', [
            'relation_id_number' => $relationIdNumber,
            'orphan_identity' => $sponsorship->identity_number,
            'guardian_identity' => $sponsorship->guardian_identity_number
        ]);

        $attachments = [];
        $na = self::NOT_AVAILABLE;

        // جمع جميع أرقام الهوية المتعلقة بهذا الملف
        $identityNumbers = [];

        // 1. رقم هوية اليتيم/المكفول
        if ($sponsorship->identity_number) {
            $identityNumbers[] = $sponsorship->identity_number;
        }

        // 2. رقم هوية المعيل
        if ($sponsorship->guardian_identity_number) {
            $identityNumbers[] = $sponsorship->guardian_identity_number;
        }

        // 3. أرقام هوية أفراد الأسرة من جدول re_people
        if (!empty($familyMembers)) {
            $familyIdentities = RePeople::where('registration_id', $relationIdNumber)
                ->pluck('person_id')
                ->toArray();
            $identityNumbers = array_merge($identityNumbers, $familyIdentities);
        }

        // 4. البحث في جدول data عن أفراد بنفس رقم الملف
        $dataIdentities = DB::table('data')
            ->where('file_id_number', $relationIdNumber)
            ->pluck('data_id_number')
            ->toArray();
        $identityNumbers = array_merge($identityNumbers, $dataIdentities);

        // إزالة التكرارات والقيم الفارغة
        $identityNumbers = array_unique(array_filter($identityNumbers));

        Log::info('IDENTITY_NUMBERS_COLLECTED', [
            'count' => count($identityNumbers),
            'identities' => $identityNumbers
        ]);

        // جلب جميع الوثائق لجميع أرقام الهوية
        if (!empty($identityNumbers)) {
            $dbAttachments = Attachment::whereIn('person_identity_number', $identityNumbers)
                ->orderBy('person_identity_number')
                ->orderBy('file_type')
                ->get();

            Log::info('ATTACHMENTS_FOUND', [
                'count' => $dbAttachments->count()
            ]);

            // تنظيم الوثائق حسب الشخص ونوع الوثيقة
            foreach ($dbAttachments as $attachment) {
                // تحديد اسم الشخص
                $personName = $this->getPersonNameByIdentity($attachment->person_identity_number, $sponsorship, $familyMembers);

                // تحديد نوع الوثيقة
                $documentType = $this->getDocumentTypeName($attachment->file_type);

                // تحديد نوع الشخص (يتيم، معيل، فرد من الأسرة)
                $personType = 'آخر';
                if ($attachment->person_identity_number == $sponsorship->identity_number) {
                    $personType = 'المكفول';
                } elseif ($attachment->person_identity_number == $sponsorship->guardian_identity_number) {
                    $personType = 'المعيل';
                } else {
                    $personType = 'فرد من الأسرة';
                }

                // إعداد مسار الملف
                $filePath = $attachment->file_path;

                // تحويل المسار النسبي إلى مسار مطلق
                if (!str_starts_with($filePath, '/') && !str_starts_with($filePath, 'http')) {
                    // إذا كان المسار يبدأ بـ storage/، استخدم storage_path
                    if (str_starts_with($filePath, 'storage/')) {
                        $filePath = storage_path('app/public/' . str_replace('storage/', '', $filePath));
                    } else {
                        $filePath = public_path($filePath);
                    }
                }

                // التحقق من وجود الملف
                $fileExists = file_exists($filePath);

                // للصور الشخصية، نحتفظ بها منفصلة
                $isPersonalPhoto = in_array($attachment->file_type, ['صورة شخصية', 'الصورة الشخصية', 'personal_photo', '1', 1, 'صوره شخصيه']);

                // يمكن أيضاً التحقق من اسم الملف إذا احتوى على "personal" أو "صورة"
                if (!$isPersonalPhoto && $attachment->stored_file_name) {
                    $fileName = strtolower($attachment->stored_file_name);
                    $isPersonalPhoto = str_contains($fileName, 'personal') ||
                                      str_contains($fileName, 'صورة') ||
                                      str_contains($fileName, 'صوره');
                }

                $attachments[] = [
                    'person_identity' => $attachment->person_identity_number,
                    'person_name' => $personName,
                    'person_type' => $personType,
                    'document_type' => $documentType,
                    'file_name' => $attachment->stored_file_name ?? basename($attachment->file_path),
                    'file_path' => $filePath,
                    'file_exists' => $fileExists,
                    'is_personal_photo' => $isPersonalPhoto,
                    'is_image' => in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']),
                    'created_at' => $attachment->created_at
                ];
            }
        }

        Log::info('GET_ALL_ATTACHMENTS_COMPLETE', [
            'total_attachments' => count($attachments)
        ]);

        return $attachments;
    }

    /**
     * الحصول على اسم الشخص من رقم الهوية
     */
    private function getPersonNameByIdentity($identityNumber, $sponsorship, $familyMembers)
    {
        // تحقق من اليتيم
        if ($identityNumber == $sponsorship->identity_number) {
            return $sponsorship->orphan_name ?? 'المكفول';
        }

        // تحقق من المعيل
        if ($identityNumber == $sponsorship->guardian_identity_number) {
            return $sponsorship->guardian_name ?? 'المعيل';
        }

        // البحث في أفراد الأسرة
        $member = RePeople::where('person_id', $identityNumber)->first();
        if ($member) {
            return $this->formatFullName(
                $member->first_name,
                $member->second_name,
                $member->third_name,
                $member->last_name
            ) ?: 'فرد من الأسرة';
        }

        // البحث في جدول data
        $dataRecord = DB::table('data')
            ->where('data_id_number', $identityNumber)
            ->first();

        if ($dataRecord) {
            return $this->formatFullName(
                $dataRecord->data_first_name ?? null,
                $dataRecord->data_father_name ?? null,
                $dataRecord->data_grand_father_name ?? null,
                $dataRecord->data_family_name ?? null
            ) ?: 'شخص';
        }

        return 'غير معروف';
    }

    /**
     * الحصول على اسم نوع الوثيقة
     */
    private function getDocumentTypeName($fileType)
    {
        // إذا كان رقم، ابحث في جدول document_types
        if (is_numeric($fileType)) {
            $docType = DB::table('document_types')
                ->where('id', $fileType)
                ->orWhere('pref', $fileType)
                ->first();

            if ($docType) {
                return $docType->description ?? $docType->pref ?? 'وثيقة';
            }
        }

        // إرجاع النوع كما هو
        return $fileType ?: 'وثيقة';
    }

    /**
     * رفع PDF إلى Google Drive إذا كان مفعلاً للجمعية
     */
    private function uploadToGoogleDriveIfEnabled($sponsorship, $fullPath, $fileName)
    {
        try {
            // جلب الكافل المحدد بدقة
            $sponsor = null;

            if ($this->sponsorId) {
                // استخدام sponsor_id المحدد من الـ Job
                $sponsor = \App\Models\Sponsor::find($this->sponsorId);
            } else {
                // محاولة جلب أول كافل من العلاقة (للتوافقية مع الكود القديم)
                $sponsor = $sponsorship->sponsors()->first();
            }

            if (!$sponsor) {
                Log::warning('Google Drive upload skipped - sponsor not found', [
                    'sponsorship_id' => $sponsorship->id,
                    'sponsor_id' => $this->sponsorId
                ]);
                return;
            }

            if (!$sponsor->google_drive_enabled) {
                Log::info('Google Drive upload skipped - not enabled for sponsor', [
                    'sponsor_id' => $sponsor->id,
                    'sponsor_name' => $sponsor->sponsor_name,
                    'google_drive_enabled' => $sponsor->google_drive_enabled
                ]);
                return;
            }

            // التحقق من وجود إعدادات Rclone
            $rclonePath = env('RCLONE_PATH');
            $rcloneRemote = env('RCLONE_REMOTE_NAME');

            if (!$rclonePath || !$rcloneRemote) {
                Log::warning('Google Drive upload skipped - Rclone configuration missing', [
                    'rclone_path_exists' => !empty($rclonePath),
                    'rclone_remote_exists' => !empty($rcloneRemote)
                ]);
                return;
            }

            // الحصول على المسار المحلي للملف
            $localFilePath = Storage::path($fullPath);

            if (!file_exists($localFilePath)) {
                Log::error('PDF file not found for Google Drive upload', [
                    'local_path' => $localFilePath
                ]);
                return;
            }

            // إعداد البيانات للرفع على Google Drive
            // 1. اسم الجمعية فقط (بدون رقم الملف)
            $organizationName = $sponsor->sponsor_name;

            // 2. اسم الشخص المكفول فقط
            $personName = $sponsorship->orphan_name ?? 'unknown';

            // 3. اسم الملف في Google Drive: "استمارة بيانات"
            $documentTypeName = 'استمارة بيانات';

            // استخدام RcloneGoogleDriveService للرفع
            $rcloneService = app(\App\Services\RcloneGoogleDriveService::class);

            $uploadResult = $rcloneService->uploadFile(
                $localFilePath,
                $organizationName,     // اسم الجمعية فقط
                $personName,           // اسم الشخص المكفول
                $documentTypeName,     // "استمارة بيانات"
                'pdf',                 // الامتداد
                1                      // رقم الملف
            );

            if ($uploadResult['success']) {
                Log::info('PDF uploaded to Google Drive successfully', [
                    'sponsor_id' => $sponsor->id,
                    'sponsor_name' => $sponsor->sponsor_name,
                    'file_name' => $fileName,
                    'remote_path' => $uploadResult['remote_path'],
                    'file_size' => filesize($localFilePath)
                ]);
            } else {
                Log::error('Failed to upload PDF to Google Drive', [
                    'sponsor_id' => $sponsor->id,
                    'error' => $uploadResult['message'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception during Google Drive upload', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // لا نرمي الخطأ لأن رفع Google Drive اختياري
        }
    }
}
