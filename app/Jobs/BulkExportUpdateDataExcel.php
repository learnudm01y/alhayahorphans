<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\SponsorFieldSetting;
use App\Models\SponsorshipStatus;
use App\Models\TypeOfGuarantee;
use App\Models\PortalGeneralRegistrationFieldValue;
use App\Models\Data;
use App\Models\DeadPepole;
use App\Services\ExportStatus;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Job لتصدير بيانات تحديث الكفالات إلى ملف إكسل في الخلفية.
 *
 * يجمع لكل كفالة: البيانات الأساسية + بيانات المعيل + بيانات الوفيات
 * + حقول البويرتال المفعّلة للجمعية في جدول sponsor_field_settings،
 * ثم يبني ملف PhpSpreadsheet ويحفظه محلياً ويرفعه إلى Google Drive (إن مفعّل).
 */
class BulkExportUpdateDataExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $sponsorId;
    protected ?int $sponsorshipStatusId;
    protected bool $updatedOnly;
    protected int $chunkSize = 200;

    public $tries = 1;
    public $backoff = 120;
    public $timeout = 3600;

    /** أعلام التحكم في الحقول (ليست بيانات تصدير) */
    private const SKIP_FIELDS = [
        'sponsor_id',
        'enabled_documents',
        'compress_attachments_images',
        'save_local_attachments',
        'field_family_members_section',
        'field_attachments_section',
        'field_attachments',
    ];

    private const SHARED_GUARDIAN_FIELDS = [
        'field_data_id_number',
        'field_data_first_name',
        'field_data_father_name',
        'field_data_grand_father_name',
        'field_data_family_name',
        'field_data_birth_date',
        'field_data_phone_number',
        'field_data_alt_phone_number',
        'field_data_address',
        'field_data_province',
        'field_data_city',
        'field_data_relationship',
        'field_relationship',
        'field_guardian_health',
        'field_guardian_job',
        'field_guardian_job_text',
        'field_dependents_male',
        'field_dependents_female',
        'field_family_members_count',
        'field_phone',
    ];

    public function __construct(int $sponsorId, ?int $sponsorshipStatusId = null, bool $updatedOnly = false)
    {
        $this->sponsorId = $sponsorId;
        $this->sponsorshipStatusId = $sponsorshipStatusId;
        $this->updatedOnly = $updatedOnly;
    }

    public function handle(): void
    {
        $lockKey = "export_update_data_excel_{$this->sponsorId}_{$this->sponsorshipStatusId}_" . ($this->updatedOnly ? 'updated' : 'all');

        $statusKey = ExportStatus::key(
            ExportStatus::TYPE_UPDATE_DATA,
            $this->sponsorId,
            $this->sponsorshipStatusId ? (int) $this->sponsorshipStatusId : null,
            $this->updatedOnly
        );

        $lock = Cache::lock($lockKey, 3600);

        if (!$lock->get()) {
            ExportStatus::running($statusKey, ['message' => 'تصدير آخر لهذه الجمعية قيد التشغيل بالفعل']);
            return;
        }

        try {
            $sponsor = Sponsor::find($this->sponsorId);

            if (!$sponsor) {
                ExportStatus::fail($statusKey, 'الجمعية غير موجودة');
                return;
            }

            $query = Sponsorship::with(['sponsor', 'sponsorshipStatus', 'sponsorshipType'])
                ->where('sponsor_id', $this->sponsorId);

            if ($this->sponsorshipStatusId) {
                $query->where('sponsorship_status_id', $this->sponsorshipStatusId);
            }

            if ($this->updatedOnly) {
                $query->where(function ($q) {
                    $q->whereExists(function ($sub) {
                        $sub->select('id')
                            ->from('portal_general_registration_field_values as pgv')
                            ->whereColumn('pgv.sponsorship_id', 'sponsorships.id');
                    })->orWhereExists(function ($sub) {
                        $sub->select('id')
                            ->from('portal_general_registration_field_values as pgv')
                            ->whereColumn('pgv.identity_number', 'sponsorships.identity_number');
                    });
                });
            }

            $totalCount = $query->count();

            if ($totalCount === 0) {
                ExportStatus::finish($statusKey, [
                    'total'   => 0,
                    'message' => 'لا توجد كفالات مطابقة للمعايير المحددة',
                ]);
                return;
            }

            ExportStatus::running($statusKey, [
                'total'   => $totalCount,
                'message' => 'جارٍ تجهيز أعمدة الإكسل...',
            ]);

            $fieldSettings = SponsorFieldSetting::where('sponsor_id', $this->sponsorId)->first();
            $columns = $this->buildColumns($fieldSettings);

            $typeMap = TypeOfGuarantee::pluck('description', 'id');
            $statusMap = SponsorshipStatus::pluck('description', 'id');

            $headers = array_map(fn ($col) => $col['title'], $columns);
            $rows = [];
            $processedCount = 0;

            $query->orderBy('id')->chunk($this->chunkSize, function ($sponsorships) use (
                $columns, $typeMap, $statusMap, &$rows, &$processedCount, $totalCount, $statusKey
            ) {
                $context = $this->loadChunkContext($sponsorships);

                foreach ($sponsorships as $sponsorship) {
                    $row = [];
                    foreach ($columns as $col) {
                        $row[] = ($col['resolve'] ?? fn () => null)($sponsorship, $context) ?? '';
                    }
                    $rows[] = $row;
                }

                $processedCount += $sponsorships->count();
                ExportStatus::progress($statusKey, $processedCount, $processedCount, 0, $totalCount);
            });

            ExportStatus::running($statusKey, [
                'total'   => $totalCount,
                'message' => 'جارٍ إنشاء ملف الإكسل...',
            ]);

            $fileName = $this->buildExcelFile($sponsor, $headers, $rows);

            $driveEnabled = (bool) $sponsor->google_drive_enabled;
            $driveMessage = 'محلياً فقط';

            if ($driveEnabled) {
                $uploaded = $this->uploadToGoogleDrive($sponsor, $fileName);
                $driveMessage = $uploaded ? 'ورُفع إلى Google Drive' : 'تعذّر الرفع إلى Google Drive (محفوظ محلياً)';
            }

            $downloadUrl = route('admin.sponsors.export-update-data-excel.download', ['file' => $fileName]);

            ExportStatus::finish($statusKey, [
                'total'        => $totalCount,
                'processed'    => $processedCount,
                'success'      => $processedCount,
                'failed'       => 0,
                'drive'        => $driveEnabled ? 'enabled' : 'disabled',
                'download_url' => $downloadUrl,
                'file_name'    => $fileName,
                'message'      => sprintf('اكتمل التصدير: %d سجل — %s', $processedCount, $driveMessage),
            ]);

            Log::info('BulkExportUpdateDataExcel: Completed', [
                'sponsor_id' => $this->sponsorId,
                'total'      => $totalCount,
                'file_name'  => $fileName,
            ]);
        } catch (\Exception $e) {
            Log::error('BulkExportUpdateDataExcel: Critical error', [
                'sponsor_id' => $this->sponsorId,
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            ExportStatus::fail($statusKey, $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BulkExportUpdateDataExcel: Job failed', [
            'sponsor_id' => $this->sponsorId,
            'error'      => $exception->getMessage(),
        ]);

        ExportStatus::fail(
            ExportStatus::key(
                ExportStatus::TYPE_UPDATE_DATA,
                $this->sponsorId,
                $this->sponsorshipStatusId ? (int) $this->sponsorshipStatusId : null,
                $this->updatedOnly
            ),
            $exception->getMessage()
        );
    }

    /**
     * تحميل بيانات الدفعة دفعة واحدة (بويرتال + معيل + وفيات) لتفادي N+1.
     */
    private function loadChunkContext($sponsorships): array
    {
        $identityNumbers = $sponsorships->pluck('identity_number')->filter()->unique()->values();
        $guardianIds = $sponsorships->pluck('guardian_identity_number')->filter()->unique()->values();
        $fileNumbers = $sponsorships
            ->map(fn ($s) => $s->relation_id_number ?? $s->internal_file_number)
            ->filter()->unique()->values();

        // بيانات المعيل من جدول data
        $guardianMap = [];
        if ($guardianIds->isNotEmpty()) {
            $guardians = Data::with(['categoryOfRelation', 'healthStatus', 'employmentStatusBreadwinner', 'city'])
                ->whereIn('data_id_number', $guardianIds)
                ->get();

            foreach ($guardians as $guardian) {
                if (!isset($guardianMap[$guardian->data_id_number])) {
                    $guardianMap[$guardian->data_id_number] = $guardian;
                }
            }
        }

        $portalFileNumbers = $fileNumbers
            ->merge(collect($guardianMap)->pluck('file_id_number'))
            ->filter()->unique()->values();

        // حقول البويرتال: عبر sponsorship_id و عبر رقم هوية المكفول (كما في تصدير الاستمارات)
        $portalById = [];
        $portalByIdentity = [];
        $portalByFileNumber = [];

        if ($sponsorships->isNotEmpty()) {
            $portalRows = PortalGeneralRegistrationFieldValue::query()
                ->where(function ($q) use ($sponsorships, $identityNumbers, $portalFileNumbers) {
                    $q->whereIn('sponsorship_id', $sponsorships->pluck('id'));
                    if ($identityNumbers->isNotEmpty()) {
                        $q->orWhereIn('identity_number', $identityNumbers);
                    }
                    if ($portalFileNumbers->isNotEmpty()) {
                        $q->orWhereIn('file_id_number', $portalFileNumbers);
                    }
                })
                ->get(['sponsorship_id', 'identity_number', 'file_id_number', 'field_key', 'field_value']);

            foreach ($portalRows as $row) {
                if ($row->sponsorship_id) {
                    $portalById[(int) $row->sponsorship_id][$row->field_key] = $row->field_value;
                }
                if ($row->identity_number) {
                    $portalByIdentity[$row->identity_number][$row->field_key] = $row->field_value;
                }
                if ($row->file_id_number && $row->field_value !== null && $row->field_value !== '') {
                    $portalByFileNumber[(string) $row->file_id_number][$row->field_key] = $row->field_value;
                }
            }
        }

        // بيانات الوفيات من جدول dead_people
        $deadMap = [];
        if ($fileNumbers->isNotEmpty()) {
            $deadMap = DeadPepole::with(['fatherDeathReason', 'motherDeathReason'])
                ->whereIn('re_file_id', $fileNumbers)
                ->get()
                ->keyBy('re_file_id');
        }

        return [
            'portal_by_id'      => $portalById,
            'portal_by_identity' => $portalByIdentity,
            'portal_by_file_number' => $portalByFileNumber,
            'guardians'         => $guardianMap,
            'dead'              => $deadMap,
        ];
    }

    /**
     * بناء تعريفات الأعمدة: أعمدة ثابتة (أساسية + معيل + وفيات)
     * + أعمدة ديناميكية لحقول البويرتال المفعّلة للجمعية.
     */
    private function buildColumns(?SponsorFieldSetting $settings): array
    {
        $portal = function (string $key) {
            return function (Sponsorship $s, array $ctx) use ($key) {
                return $this->portalValue($s, $ctx, $key);
            };
        };

        $columns = [
            ['key' => 'id', 'title' => 'رقم الكفالة', 'resolve' => fn (Sponsorship $s) => $s->id],
            ['key' => 'internal_file_number', 'title' => 'رقم الملف الداخلي', 'resolve' => fn (Sponsorship $s) => $s->internal_file_number],
            ['key' => 'external_file_number', 'title' => 'رقم الملف الخارجي', 'setting' => 'field_external_file_number', 'resolve' => fn (Sponsorship $s) => $s->external_file_number],
            ['key' => 'orphan_name', 'title' => 'اسم المكفول', 'setting' => 'field_first_name', 'resolve' => fn (Sponsorship $s) => $s->orphan_name],
            ['key' => 'identity_number', 'title' => 'رقم هوية المكفول', 'setting' => 'field_person_id', 'resolve' => fn (Sponsorship $s) => $s->identity_number],
            ['key' => 'sponsored_birth_date', 'title' => 'تاريخ ميلاد المكفول', 'setting' => 'field_person_birth_date', 'resolve' => fn (Sponsorship $s) => $s->sponsored_birth_date],
            ['key' => 'sponsor_name', 'title' => 'اسم المؤسسة الكافلة', 'resolve' => fn (Sponsorship $s, array $ctx) => $s->sponsor_name ?? $s->sponsoring_organization],
            ['key' => 'sponsoring_organization', 'title' => 'اسم جهة الكفالة', 'setting' => 'field_sponsoring_organization', 'resolve' => fn (Sponsorship $s) => $s->sponsoring_organization],
            ['key' => 'sponsorship_type', 'title' => 'نوع الكفالة', 'setting' => 'field_sponsorship_type_id', 'resolve' => fn (Sponsorship $s) => $s->sponsorshipType?->description],
            ['key' => 'sponsorship_status', 'title' => 'حالة الكفالة', 'setting' => 'field_sponsorship_status_id', 'resolve' => fn (Sponsorship $s) => $s->sponsorshipStatus?->description],
            ['key' => 'sponsorship_start_date', 'title' => 'تاريخ بداية الكفالة', 'setting' => 'field_sponsorship_start_date', 'resolve' => fn (Sponsorship $s) => $s->sponsorship_start_date],
            ['key' => 'sponsorship_end_date', 'title' => 'تاريخ نهاية الكفالة', 'setting' => 'field_sponsorship_end_date', 'resolve' => fn (Sponsorship $s) => $s->sponsorship_end_date],
            ['key' => 'sponsorship_duration_months', 'title' => 'مدة الكفالة (شهور)', 'setting' => 'field_sponsorship_duration_months', 'resolve' => fn (Sponsorship $s) => $s->sponsorship_duration_months],
            ['key' => 'guardian_name', 'title' => 'اسم المعيل', 'resolve' => fn (Sponsorship $s) => $s->guardian_name],
            ['key' => 'guardian_identity_number', 'title' => 'رقم هوية المعيل', 'setting' => 'field_guardian_identity_number', 'resolve' => fn (Sponsorship $s) => $s->guardian_identity_number],
            [
                'key'     => 'guardian_relation',
                'title'   => 'صلة قرابة المعيل',
                'setting' => 'field_data_relationship',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $guardian = $this->guardianOf($s, $ctx);

                    return $guardian?->categoryOfRelation?->description
                        ?? $this->portalValue($s, $ctx, 'field_data_relationship')
                        ?? $this->portalValue($s, $ctx, 'field_relationship');
                },
            ],
            [
                'key'     => 'guardian_health',
                'title'   => 'الحالة الصحية للمعيل',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $guardian = $this->guardianOf($s, $ctx);

                    return $this->portalValue($s, $ctx, 'field_guardian_health')
                        ?? $guardian?->healthStatus?->description;
                },
            ],
            [
                'key'     => 'guardian_job',
                'title'   => 'وظيفة المعيل',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $guardian = $this->guardianOf($s, $ctx);

                    return $this->portalValue($s, $ctx, 'field_guardian_job')
                        ?? $this->portalValue($s, $ctx, 'field_guardian_job_text')
                        ?? $guardian?->employmentStatusBreadwinner?->description;
                },
            ],
            ['key' => 'dependents_male', 'title' => 'عدد المعالين (ذكور)', 'resolve' => $portal('field_dependents_male')],
            ['key' => 'dependents_female', 'title' => 'عدد المعالين (إناث)', 'resolve' => $portal('field_dependents_female')],
            ['key' => 'family_members_count', 'title' => 'إجمالي أفراد الأسرة', 'resolve' => $portal('field_family_members_count')],
            [
                'key'     => 'phone_number',
                'title'   => 'رقم الهاتف',
                'setting' => 'field_data_phone_number',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $guardian = $this->guardianOf($s, $ctx);

                    return $guardian?->data_phone_number
                        ?? $this->portalValue($s, $ctx, 'field_data_phone_number')
                        ?? $this->portalValue($s, $ctx, 'field_phone');
                },
            ],
            [
                'key'     => 'city',
                'title'   => 'المدينة',
                'setting' => 'field_data_city',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $guardian = $this->guardianOf($s, $ctx);

                    return $guardian?->city?->description
                        ?? $this->portalValue($s, $ctx, 'field_data_city');
                },
            ],
            // ===== بيانات الوفيات =====
            [
                'key'     => 'father_name',
                'title'   => 'اسم الأب',
                'setting' => 'field_father_first_name',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);
                    $name = $dead ? trim(implode(' ', array_filter([
                        $dead->father_first_name, $dead->father_second_name, $dead->father_third_name, $dead->father_last_name,
                    ]))) : null;

                    return $name ?: $this->portalValue($s, $ctx, 'field_father_first_name');
                },
            ],
            [
                'key'     => 'father_id',
                'title'   => 'رقم هوية الأب',
                'setting' => 'field_father_id',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);

                    return $dead?->father_id ?? $this->portalValue($s, $ctx, 'field_father_id');
                },
            ],
            [
                'key'     => 'father_death_date',
                'title'   => 'تاريخ وفاة الأب',
                'setting' => 'field_father_death_date',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);

                    return $dead?->father_death_date ?? $this->portalValue($s, $ctx, 'field_father_death_date');
                },
            ],
            [
                'key'     => 'father_death_reason',
                'title'   => 'سبب وفاة الأب',
                'setting' => 'field_father_death_reason',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);

                    return $dead?->fatherDeathReason?->description
                        ?? $this->portalValue($s, $ctx, 'field_father_death_reason');
                },
            ],
            [
                'key'     => 'mother_name',
                'title'   => 'اسم الأم',
                'setting' => 'field_mother_first_name',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);
                    $name = $dead ? trim(implode(' ', array_filter([
                        $dead->mother_first_name, $dead->mother_second_name, $dead->mother_third_name, $dead->mother_last_name,
                    ]))) : null;

                    return $name ?: $this->portalValue($s, $ctx, 'field_mother_first_name');
                },
            ],
            [
                'key'     => 'mother_id',
                'title'   => 'رقم هوية الأم',
                'setting' => 'field_mother_id',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);

                    return $dead?->mother_id ?? $this->portalValue($s, $ctx, 'field_mother_id');
                },
            ],
            [
                'key'     => 'mother_death_date',
                'title'   => 'تاريخ وفاة الأم',
                'setting' => 'field_mother_death_date',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);

                    return $dead?->mother_death_date ?? $this->portalValue($s, $ctx, 'field_mother_death_date');
                },
            ],
            [
                'key'     => 'mother_death_reason',
                'title'   => 'سبب وفاة الأم',
                'setting' => 'field_mother_death_reason',
                'resolve' => function (Sponsorship $s, array $ctx) {
                    $dead = $this->deadOf($s, $ctx);

                    return $dead?->motherDeathReason?->description
                        ?? $this->portalValue($s, $ctx, 'field_mother_death_reason');
                },
            ],
            ['key' => 'notes', 'title' => 'ملاحظات', 'resolve' => fn (Sponsorship $s) => $s->notes],
        ];

        // حقول ثابتة إضافية من جدول data (تُضاف فقط عند تفعيلها للجمعية)
        $guardianStaticColumns = [
            'field_data_first_name'        => ['عنوان أول للمعيل', fn (Sponsorship $s, array $ctx) => $this->guardianOf($s, $ctx)?->data_first_name ?? $this->portalValue($s, $ctx, 'field_data_first_name')],
            'field_data_father_name'       => ['اسم الأب للمعيل', fn (Sponsorship $s, array $ctx) => $this->guardianOf($s, $ctx)?->data_father_name ?? $this->portalValue($s, $ctx, 'field_data_father_name')],
            'field_data_grand_father_name' => ['اسم الجد للمعيل', fn (Sponsorship $s, array $ctx) => $this->guardianOf($s, $ctx)?->data_grand_father_name ?? $this->portalValue($s, $ctx, 'field_data_grand_father_name')],
            'field_data_family_name'       => ['اسم العائلة للمعيل', fn (Sponsorship $s, array $ctx) => $this->guardianOf($s, $ctx)?->data_family_name ?? $this->portalValue($s, $ctx, 'field_data_family_name')],
            'field_data_birth_date'        => ['تاريخ ميلاد المعيل', fn (Sponsorship $s, array $ctx) => $this->guardianOf($s, $ctx)?->data_birth_date ?? $this->portalValue($s, $ctx, 'field_data_birth_date')],
        ];

        // أعمدة ديناميكية لبقية الحقول المفعّلة (حقول بويرتال)
        $coveredSettings = array_filter(array_map(fn ($col) => $col['setting'] ?? null, $columns));
        $dynamicColumns = [];

        if ($settings) {
            foreach ($settings->getActiveFields() as $field) {
                if (in_array($field, self::SKIP_FIELDS, true) || in_array($field, $coveredSettings, true)) {
                    continue;
                }

                if (isset($guardianStaticColumns[$field])) {
                    [$title, $resolver] = $guardianStaticColumns[$field];
                    $dynamicColumns[] = ['key' => $field, 'title' => $title, 'resolve' => $resolver];
                    continue;
                }

                $dynamicColumns[] = [
                    'key'     => $field,
                    'title'   => $this->fieldLabel($field),
                    'resolve' => fn (Sponsorship $s, array $ctx) => $this->portalValue($s, $ctx, $field),
                ];
            }
        }

        return array_merge($columns, $dynamicColumns);
    }

    /**
     * قراءة قيمة حقل بويرتال لكفالة (مع دعم القيم JSON المتعددة).
     */
    private function portalValue(Sponsorship $sponsorship, array $ctx, string $key)
    {
        $value = $ctx['portal_by_id'][(int) $sponsorship->id][$key]
            ?? $ctx['portal_by_identity'][$sponsorship->identity_number][$key]
            ?? null;

        if (($value === null || $value === '') && in_array($key, self::SHARED_GUARDIAN_FIELDS, true)) {
            $guardian = $this->guardianOf($sponsorship, $ctx);
            $fileNumbers = array_unique(array_filter([
                $guardian?->file_id_number,
                $sponsorship->relation_id_number,
                $sponsorship->internal_file_number,
            ]));

            foreach ($fileNumbers as $fileNumber) {
                $value = $ctx['portal_by_file_number'][(string) $fileNumber][$key] ?? null;
                if ($value !== null) {
                    break;
                }
            }
        }

        return $this->normalizeValue($value);
    }

    private function guardianOf(Sponsorship $sponsorship, array $ctx): ?Data
    {
        return $ctx['guardians'][$sponsorship->guardian_identity_number] ?? null;
    }

    private function deadOf(Sponsorship $sponsorship, array $ctx): ?DeadPepole
    {
        $fileNumber = $sponsorship->relation_id_number ?? $sponsorship->internal_file_number;

        return $ctx['dead'][$fileNumber] ?? null;
    }

    private function normalizeValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return implode('، ', array_map('strval', $decoded));
                }

                return is_scalar($decoded) ? (string) $decoded : $value;
            }
        }

        return is_scalar($value) ? (string) $value : $value;
    }

    /**
     * عنوان عربي مخصص لحقول البويرتال (مع بديل افتراضي مقروء).
     */
    private function fieldLabel(string $field): string
    {
        static $labels = [
            'field_sponsor_name'                  => 'اسم الكافل',
            'field_identity_number'               => 'رقم هوية الكافل',
            'field_phone'                         => 'رقم الهاتف (بويرتال)',
            'field_mother_name'                   => 'اسم الأم (بويرتال)',
            'field_housing_status'                => 'حالة السكن',
            'field_housing_type'                  => 'نوع السكن',
            'field_housing_address_detail'        => 'تفاصيل عنوان السكن',
            'field_house_demolition'              => 'حالة تفكيك المنزل',
            'field_house_repair_need'             => 'الحاجة إلى إصلاح المنزل',
            'field_school_name'                   => 'اسم المدرسة',
            'field_school_address'                => 'عنوان المدرسة',
            'field_grade'                         => 'الصف الدراسي',
            'field_student_level'                 => 'المستوى الدراسي',
            'field_weakness_reason'               => 'سبب الضعف الدراسي',
            'field_tent_school'                   => 'مدرسة الخيام',
            'field_orphan_ambition'               => 'طموح المكفول',
            'field_psychological_state'           => 'الحالة النفسية',
            'field_behavioral_state'              => 'الحالة السلوكية',
            'field_orphan_behavior'               => 'سلوك المكفول',
            'field_religious_commitment'          => 'الالتزام الديني',
            'field_commitment'                    => 'الالتزام العام',
            'field_quran_memorization'            => 'تحفيظ القرآن',
            'field_prayer_commitment'             => 'الالتزام بالصلاة',
            'field_health_status'                 => 'الحالة الصحية',
            'field_orphan_health'                 => 'صحة المكفول',
            'field_treatment_cost'                => 'تكلفة العلاج',
            'field_receives_treatment'            => 'يتلقى العلاج',
            'field_family_sick_member'            => 'فرد مريض في الأسرة',
            'field_family_disease_cost'           => 'تكلفة مرض الأسرة',
            'field_orphan_needs'                  => 'احتياجات المكفول',
            'field_creativity_aspects'            => 'جوانب الإبداع',
            'field_relationship'                  => 'صلة القرابة (بويرتال)',
            'field_guardian_job_text'             => 'نص وظيفة المعيل',
            'field_siblings_names'                => 'أسماء الإخوة',
            'field_sibling_birthdate'             => 'تاريخ ميلاد الأخ',
            'field_sibling_grade'                 => 'الصف الدراسي للأخ',
            'field_sibling_notes'                 => 'ملاحظات الإخوة',
            'field_sponsorship_impact'            => 'تأثير الكفالة',
            'field_important_events'              => 'الأحداث المهمة',
            'field_supervisor_notes'              => 'ملاحظات المشرف',
            'field_data_update_date'              => 'تاريخ تحديث البيانات',
            'field_supervisor_name'               => 'اسم المشرف',
            'field_institution_name'              => 'اسم المؤسسة (بويرتال)',
            'field_re_guardian_name'              => 'اسم ولي الأمر (بويرتال)',
            'field_re_guardian_phone'             => 'هاتف ولي الأمر',
            'field_re_guardian_id'                => 'هوية ولي الأمر',
            'field_person_gender'                 => 'جنس المكفول',
            'field_person_health_status'          => 'الحالة الصحية للمكفول',
            'field_person_type_of_guarantee'      => 'نوع الضمان',
            'field_person_note'                   => 'ملاحظات المكفول',
            'field_guardian_account_owner_name'   => 'اسم صاحب الحساب البنكي',
            'field_guardian_bank_name'            => 'اسم البنك',
            'field_guardian_id_owner'             => 'هوية صاحب الحساب',
            'field_guardian_phone_number'         => 'هاتف صاحب الحساب',
            'field_guardian_iban_usd'             => 'رقم الحساب (دولار)',
            'field_guardian_iban_shekel'          => 'رقم الحساب (شيكل)',
        ];

        if (isset($labels[$field])) {
            return $labels[$field];
        }

        return trim(str_replace('_', ' ', preg_replace('/^field_/', '', $field)));
    }

    /**
     * بناء ملف الإكسل وحفظه محلياً، مع حذف النسخ القديمة لنفس الجمعية.
     */
    private function buildExcelFile(Sponsor $sponsor, array $headers, array $rows): string
    {
        $folder = 'public/uploads/exports/update_data';

        if (!Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }

        // حذف ملفات الإكسل القديمة لهذه الجمعية
        foreach (Storage::files($folder) as $existing) {
            if (str_starts_with(basename($existing), 'update_data_' . $this->sponsorId . '_')) {
                Storage::delete($existing);
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('بيانات التحديث');
        $sheet->setRightToLeft(true);

        $sheet->fromArray($headers, null, 'A1');

        $lastColumn = $sheet->getHighestColumn();
        $headerRange = 'A1:' . $lastColumn . '1';

        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('009EF7');
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A' . $rowNumber);
            $rowNumber++;
        }

        // setAutoSize لكل الأعمدة (تدعم أعمدة AA, AB... عبر فهارس PhpSpreadsheet)
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
        for ($colIndex = 1; $colIndex <= $lastColumnIndex; $colIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }

        $fileName = 'update_data_' . $this->sponsorId . '_' . date('Ymd_His') . '.xlsx';
        $fullPath = $folder . '/' . $fileName;

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'update_data_xlsx_');
        $writer->save($tempFile);

        Storage::put($fullPath, file_get_contents($tempFile));
        @unlink($tempFile);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        Log::info('BulkExportUpdateDataExcel: Excel file saved', [
            'sponsor_id' => $this->sponsorId,
            'file_name'  => $fileName,
            'rows'       => count($rows),
            'columns'    => count($headers),
        ]);

        return $fileName;
    }

    /**
     * رفع ملف الإكسل إلى Google Drive عبر Rclone (إن كانت مفعّلة للجمعية).
     */
    private function uploadToGoogleDrive(Sponsor $sponsor, string $fileName): bool
    {
        try {
            $rclonePath = config('services.rclone.path', env('RCLONE_PATH'));
            $rcloneRemote = config('services.rclone.remote_name', env('RCLONE_REMOTE_NAME'));

            if (!$rclonePath || !$rcloneRemote) {
                Log::warning('BulkExportUpdateDataExcel: Rclone configuration missing');
                return false;
            }

            $localFilePath = Storage::path('public/uploads/exports/update_data/' . $fileName);

            if (!file_exists($localFilePath)) {
                return false;
            }

            $rcloneService = app(\App\Services\RcloneGoogleDriveService::class);

            $uploadResult = $rcloneService->uploadFile(
                $localFilePath,
                $sponsor->sponsor_name,
                $this->sponsorshipStatusId
                    ? (SponsorshipStatus::find($this->sponsorshipStatusId)->description ?? 'الكل')
                    : 'جميع الحالات',
                'تحديث بيانات الكفالات',
                'xlsx',
                1
            );

            if ($uploadResult['success']) {
                Log::info('BulkExportUpdateDataExcel: Uploaded to Google Drive', [
                    'sponsor_id'  => $this->sponsorId,
                    'remote_path' => $uploadResult['remote_path'] ?? null,
                ]);

                return true;
            }

            Log::error('BulkExportUpdateDataExcel: Google Drive upload failed', [
                'sponsor_id' => $this->sponsorId,
                'error'      => $uploadResult['message'] ?? 'Unknown error',
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('BulkExportUpdateDataExcel: Google Drive upload exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
