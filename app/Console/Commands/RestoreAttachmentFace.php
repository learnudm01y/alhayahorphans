<?php

namespace App\Console\Commands;

use App\Models\AttachmentTrash;
use App\Services\AttachmentTrashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RestoreAttachmentFace extends Command
{
    protected $signature = 'attachments:face-restore {id : معرف عنصر سلة فحص الوجوه}';

    protected $description = 'استرجاع مرفق حُذف تلقائياً ضمن فحص تطابق الوجوه';

    public function handle(AttachmentTrashService $service): int
    {
        if (!Schema::hasTable('attachment_trash')) {
            $this->error('جدول السلة غير موجود. شغّل هجرة سلة الاسترجاع أولاً.');

            return self::FAILURE;
        }

        $trash = AttachmentTrash::find($this->argument('id'));
        if (!$trash) {
            $this->error('لا يوجد عنصر سلة بهذا المعرف.');

            return self::INVALID;
        }

        $outcome = $service->restore($trash, null);

        if ($outcome['status'] === 'restored') {
            $snapshot = is_array($trash->snapshot) ? $trash->snapshot : [];
            $this->info('استُعيد المرفق بنجاح.');
            $this->table(['البند', 'القيمة'], [
                ['معرف السلة', $trash->id],
                ['معرف المرفق المستعاد', $outcome['attachment_id']],
                ['الهوية', $this->maskIdentity((string) ($snapshot['person_identity_number'] ?? ''))],
            ]);

            return self::SUCCESS;
        }

        $messages = [
            'expired' => 'انتهت مدة السلة ولا يمكن الاسترجاع.',
            'not-trashed' => 'هذا العنصر ليس في السلة.',
            'move-failed' => 'تعذر إعادة الملف إلى مكانه الأصلي.',
            'db-failed' => 'تعذر استرجاع السجل.',
        ];
        $this->error($messages[$outcome['status']] ?? 'تعذر الاسترجاع.');

        return self::FAILURE;
    }

    private function maskIdentity(?string $identity): string
    {
        return '***' . substr((string) $identity, -3);
    }
}
