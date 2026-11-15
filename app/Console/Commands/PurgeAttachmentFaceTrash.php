<?php

namespace App\Console\Commands;

use App\Services\AttachmentTrashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PurgeAttachmentFaceTrash extends Command
{
    protected $signature = 'attachments:face-trash-purge
        {--dry-run : عدّ العناصر المنتهية دون حذف نهائي}';

    protected $description = 'حذف نهائي لعناصر سلة فحص الوجوه المتجاوزة مدة الاسترجاع (قاعدة البيانات والتخزين المحلي فقط)';

    public function handle(AttachmentTrashService $service): int
    {
        if (!Schema::hasTable('attachment_trash')) {
            $this->warn('جدول السلة غير موجود؛ لا شيء للتنظيف.');

            return self::SUCCESS;
        }

        $outcome = $service->purgeExpired((bool) $this->option('dry-run'));

        if ($outcome['dry_run']) {
            $this->info('وضع التجربة: لن يُحذف شيء.');
        }

        $this->table(['البند', 'القيمة'], [
            ['عناصر تجاوزت المدة', $outcome['purged']],
            ['ملفات حُذفت نهائياً', $outcome['files_removed']],
        ]);

        return self::SUCCESS;
    }
}
