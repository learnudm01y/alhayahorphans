<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Models\RePeople;

class ImportMissingPersons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:missing-persons
                            {--file= : مسار ملف JSON للأشخاص المفقودين}
                            {--dry-run : فحص فقط بدون إضافة فعلية}
                            {--type= : نوع الأشخاص للإضافة (family|guardians|all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'استيراد الأشخاص المفقودين من ملف JSON إلى قاعدة البيانات';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->option('file') ?? base_path('missing_persons.json');
        $dryRun = $this->option('dry-run');
        $typeFilter = $this->option('type') ?? 'all';

        if (!file_exists($filePath)) {
            $this->error("❌ ملف JSON غير موجود: {$filePath}");
            $this->info("تأكد من تشغيل سكربت extract_missing_persons.php أولاً");
            return 1;
        }

        $data = json_decode(file_get_contents($filePath), true);

        if (empty($data)) {
            $this->error("❌ لم يتم العثور على بيانات في الملف");
            return 1;
        }

        $this->info("=== بدء استيراد الأشخاص المفقودين ===");
        $this->newLine();

        if ($dryRun) {
            $this->warn("⚠️ وضع الفحص فقط - لن يتم إضافة أي بيانات");
            $this->newLine();
        }

        $this->info("📊 إجمالي الأشخاص المفقودين: " . $data['total_count']);
        $this->info("   - أفراد عائلة: " . $data['family_members_count']);
        $this->info("   - معيلين: " . $data['guardians_count']);
        $this->newLine();

        $addedToRePeople = 0;
        $addedToData = 0;
        $skipped = 0;
        $errors = 0;

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            // إضافة أفراد العائلة
            if (in_array($typeFilter, ['all', 'family'])) {
                $this->info("🔄 إضافة أفراد العائلة...");
                $progressBar = $this->output->createProgressBar(count($data['family_members']));
                $progressBar->start();

                foreach ($data['family_members'] as $person) {
                    $result = $this->addFamilyMember($person, $dryRun);

                    if ($result === 'added') {
                        $addedToRePeople++;
                    } elseif ($result === 'skipped') {
                        $skipped++;
                    } else {
                        $errors++;
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine(2);
            }

            // إضافة المعيلين
            if (in_array($typeFilter, ['all', 'guardians'])) {
                $this->info("🔄 إضافة المعيلين...");
                $progressBar = $this->output->createProgressBar(count($data['guardians']));
                $progressBar->start();

                foreach ($data['guardians'] as $person) {
                    $result = $this->addGuardian($person, $dryRun);

                    if ($result === 'added') {
                        $addedToData++;
                    } elseif ($result === 'skipped') {
                        $skipped++;
                    } else {
                        $errors++;
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine(2);
            }

            if (!$dryRun) {
                DB::commit();
            }

            $this->info("=== النتائج ===");
            $this->info("✅ تمت إضافة {$addedToRePeople} شخص إلى جدول re_people");
            $this->info("✅ تمت إضافة {$addedToData} معيل إلى جدول data");
            $this->info("⏭️ تم تخطي {$skipped} سجل (موجود مسبقاً أو بيانات ناقصة)");

            if ($errors > 0) {
                $this->error("❌ {$errors} خطأ");
            }

            return 0;

        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $this->error("❌ حدث خطأ: " . $e->getMessage());
            $this->error("تم التراجع عن جميع التغييرات.");
            return 1;
        }
    }

    /**
     * إضافة فرد عائلة
     */
    private function addFamilyMember(array $person, bool $dryRun): string
    {
        $identity = trim($person['identity'] ?? '');
        $name = trim($person['name'] ?? '');

        if (empty($identity) || $identity === '0') {
            return 'skipped';
        }

        // فحص إذا كان موجوداً مسبقاً
        $exists = RePeople::where('person_id', $identity)->exists();

        if ($exists) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'added';
        }

        // البحث عن registration_id من المعيل
        $registrationId = null;
        $guardianIdentity = $person['guardian_identity'] ?? '';

        if (!empty($guardianIdentity) && $guardianIdentity !== '0') {
            $guardian = Data::where('data_id_number', $guardianIdentity)->first();
            if ($guardian) {
                $registrationId = $guardian->file_id_number;
            }
        }

        // إنشاء سجل جديد
        $newPerson = new RePeople();
        $newPerson->person_id = $identity;
        $newPerson->person_name = $name;
        $newPerson->registration_id = $registrationId;
        $newPerson->save();

        return 'added';
    }

    /**
     * إضافة معيل
     */
    private function addGuardian(array $person, bool $dryRun): string
    {
        $identity = trim($person['identity'] ?? '');
        $name = trim($person['name'] ?? '');

        if (empty($identity) || $identity === '0') {
            return 'skipped';
        }

        // فحص إذا كان موجوداً مسبقاً
        $exists = Data::where('data_id_number', $identity)->exists();

        if ($exists) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'added';
        }

        // توليد رقم ملف جديد
        $fileIdNumber = $this->generateUniqueFileId();

        // إنشاء سجل جديد
        $newData = new Data();
        $newData->file_id_number = $fileIdNumber;
        $newData->data_id_number = $identity;
        $newData->data_name = $name;

        // أرقام الهاتف
        $phone = $person['phone'] ?? '';
        $altPhone = $person['alt_phone'] ?? '';

        if (!empty($phone) && $phone !== '0') {
            $newData->data_phone_number = $phone;
        }
        if (!empty($altPhone) && $altPhone !== '0') {
            $newData->data_alt_phone_number = $altPhone;
        }

        $newData->save();

        return 'added';
    }

    /**
     * توليد رقم ملف فريد
     */
    private function generateUniqueFileId(): int
    {
        $maxId = Data::max('file_id_number') ?? 0;
        return $maxId + 1;
    }
}
