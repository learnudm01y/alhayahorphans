<?php

namespace App\Console\Commands;

use App\Services\AttachmentAuditService;
use Illuminate\Console\Command;

class CleanDuplicateAttachments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attachments:clean-duplicates
                            {--dry-run : عرض ما سيتم حذفه فقط دون تنفيذ أي حذف}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'حذف المرفقات المكررة (نفس الشخص + نفس النوع + نفس اسم الملف) مع الاحتفاظ بالسجل الأقدم';

    /**
     * Execute the console command.
     */
    public function handle(AttachmentAuditService $auditService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? '🔎 فحص تجريبي — لن يُحذف أي شيء'
            : '🧹 بدء حذف المرفقات المكررة (الاحتفاظ بالأقدم)...');

        $result = $auditService->deleteDuplicateFilenamesKeepOldest($dryRun);

        $this->newLine();
        $this->line("المجموعات المكررة: <info>{$result['groups']}</info>");
        $this->line(($dryRun ? 'سيتم حذف' : 'تم حذف') . ": <info>{$result['deleted_count']}</info> سجل");

        if (!empty($result['errors'])) {
            $this->newLine();
            $this->warn('أخطاء أثناء الحذف:');
            foreach ($result['errors'] as $error) {
                $this->line("  - #{$error['id']}: {$error['error']}");
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->comment('شغّل الأمر بدون --dry-run لتنفيذ الحذف فعلياً.');
        } else {
            $this->info('✅ اكتمل. يمكنك التحقق من صفحة تدقيق المرفقات.');
        }

        return self::SUCCESS;
    }
}
