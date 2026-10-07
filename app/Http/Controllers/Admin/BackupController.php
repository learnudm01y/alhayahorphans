<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $php     = $this->phpCliBinary();
        $artisan = base_path('artisan');
        $log     = storage_path('logs/backup-cli.log');
        $lock    = (string) config('backup.lock.file');

        if ($php === '' || !is_file($artisan)) {
            return 'تعذر العثور على PHP CLI أو artisan.';
        }

        $command = sprintf('%s %s backup:run', escapeshellarg($php), escapeshellarg($artisan));
        $redirect = sprintf('>> %s 2>&1', escapeshellarg($log));

        $line = strncasecmp(PHP_OS_FAMILY, 'Windows', 7) === 0
            ? 'start /B "" ' . $command . ' ' . $redirect
            : 'nohup ' . $command . ' ' . $redirect . ' &';

        $output = [];
        $code = 0;
        $lockBefore = is_file($lock) ? (int) @filemtime($lock) : -1;
        exec($line, $output, $code);

        Log::info('Backup background launch', [
            'sapi'       => PHP_SAPI,
            'php_binary' => PHP_BINARY,
            'resolved'   => $php,
            'exit_code'  => $code,
        ]);

        if ($code !== 0) {
            return 'تعذر بدء العملية في الخلفية (رمز الخروج: ' . $code . ').';
        }

        // exit code 0 لا يثبت شيئًا: `nohup ... &` يرجع 0 دائمًا حتى لو مات الأمر
        if (!$this->waitForLaunch($lockBefore)) {
            return 'انطلقت الأوامر لكن العملية لم تبدأ — راجع storage/logs/backup-cli.log';
        }

        return null;
    }

    /**
     * مترجم PHP CLI الفعلي.
     *
     * PHP_BINARY تحت الويب (fpm/apache/cgi) يشير إلى مترجم الخادم لا إلى CLI،
     * فأمر `php-fpm artisan backup:run` يفشل فورًا مع بقاء رمز الخروج 0.
     */
    private function phpCliBinary(): string
    {
        $configured = trim((string) config('backup.php_binary'));

        if ($configured !== '') {
            return $configured;
        }

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return PHP_BINARY;
        }

        $command = strncasecmp(PHP_OS_FAMILY, 'Windows', 7) === 0 ? 'where php' : 'command -v php';
        $output = [];
        $code = 0;
        @exec($command, $output, $code);

        foreach ($output as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return PHP_BINARY;
    }

    /**
     * انتظار انطلاق العملية الخلفية فعلًا (ظهور قفل تشغيل جديد).
     *
     * @param int $lockBefore مtime القفل قبل الإطلاق (-1 إن لم يكن موجودًا)
     * @return bool true إذا أنشأت العملية قفلها جديدًا خلال المهلة
     */
    private function waitForLaunch(int $lockBefore = -1, int $timeoutSeconds = 15): bool
    {
        $lock = (string) config('backup.lock.file');
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            clearstatcache(true, $lock);

            // قفل قديم لا يثبت شيئًا — ننتظر قفلًا كُتب بعد الإطلاق
            if (is_file($lock) && (int) @filemtime($lock) > $lockBefore) {
                return true;
            }

            usleep(300000);
        }

        return false;
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
