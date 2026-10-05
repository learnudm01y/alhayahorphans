<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class BackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * لوحة النسخ الاحتياطي
     */
    public function index(BackupService $backup)
    {
        $backup->recoverStuckRuns();

        return view('admin.backup.index', [
            'running' => $backup->isRunning(),
            'last'    => BackupRun::where('status', '!=', BackupRun::STATUS_RUNNING)
                ->latest('completed_at')
                ->first(),
            'runs' => BackupRun::latest('id')->take(10)->get(),
        ]);
    }

    /**
     * تشغيل النسخ الاحتياطي (AJAX)
     *
     * العملية تستغرق دقائق طويلة (dump + zip + نسخ)، لذلك نشغّلها في خلفية
     * PHP/الطرفية (detached) ثم يتابع الواجهة عبر status() polling.
     */
    public function run(Request $request, BackupService $backup): JsonResponse
    {
        if ($backup->isRunning()) {
            return response()->json(['ok' => false, 'message' => $backup->lockMessage()], 409);
        }

        if (function_exists('exec')) {
            $error = $this->startBackgroundRun();
            if ($error === null) {
                return response()->json([
                    'ok'      => true,
                    'status'  => BackupRun::STATUS_RUNNING,
                    'message' => 'بدأت عملية النسخ الاحتياطي في الخلفية...',
                ], 202);
            }
            // فشل التشغيل في الخلفية → نكمل بالطريقة المتزامنة بالأسفل
        }

        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $run = $backup->run();
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok'      => false,
                'message' => 'فشل تنفيذ النسخ الاحتياطي: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok'      => $run->status !== BackupRun::STATUS_FAILED,
            'status'  => $run->status,
            'message' => $this->statusLabel($run->status),
            'run'     => $run,
        ], $run->status === BackupRun::STATUS_FAILED ? 500 : 200);
    }

    /**
     * إطلاق عملية النسخ كعملية منفصلة (لا تعتمد على مدة اتصال المتصفح).
     *
     * @return string|null رسالة الخطأ، أو null عند النجاح
     */
    private function startBackgroundRun(): ?string
    {
        $php     = PHP_BINARY;
        $artisan = base_path('artisan');
        $log     = storage_path('logs/backup-cli.log');

        if ($php === '' || !is_file($artisan)) {
            return 'تعذر العثور على PHP أو artisan.';
        }

        $command = sprintf('%s %s backup:run', escapeshellarg($php), escapeshellarg($artisan));
        $redirect = sprintf('>> %s 2>&1', escapeshellarg($log));

        $line = strncasecmp(PHP_OS_FAMILY, 'Windows', 7) === 0
            ? 'start /B "" ' . $command . ' ' . $redirect
            : 'nohup ' . $command . ' ' . $redirect . ' &';

        $output = [];
        $code = 0;
        exec($line, $output, $code);

        if ($code !== 0) {
            return 'تعذر بدء العملية في الخلفية (رمز الخروج: ' . $code . ').';
        }

        return null;
    }

    /**
     * الحالة أثناء التنفيذ (AJAX polling)
     */
    public function status(BackupService $backup): JsonResponse
    {
        $backup->recoverStuckRuns();

        return response()->json([
            'running' => $backup->isRunning(),
            'current' => BackupRun::latest('id')->first(),
            'last'    => BackupRun::where('status', '!=', BackupRun::STATUS_RUNNING)
                ->latest('completed_at')
                ->first(),
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            BackupRun::STATUS_SUCCESS => 'اكتمل النسخ الاحتياطي بنجاح',
            BackupRun::STATUS_PARTIAL => 'اكتمل النسخ الاحتياطي جزئيًا — راجع التفاصيل',
            BackupRun::STATUS_FAILED  => 'فشل النسخ الاحتياطي',
            default                   => $status,
        };
    }
}
