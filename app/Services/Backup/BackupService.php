<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * نظام النسخ الاحتياطي
 *
 * 1) أرشفة مجلدات الوسائط (uploads / attachments) إلى OneDrive عبر rclone copy
 *    — نسخ فقط: لا نقل، لا حذف، لا تغيير أسماء، ولا تغيير مسارات Laravel.
 * 2) نسخة MySQL (mysqldump) — القواعد المدرجة في BACKUP_DATABASES فقط.
 * 3) نسخة ملفات Laravel كـ ZIP مع استثناء مجلدات الوسائط المؤرشفة.
 *
 * الأرشيف في OneDrive مستقل: لا تخضع لسياسة حذف النسخ القديمة.
 */
class BackupService
{
    public const LOCK_MESSAGE = 'يوجد Backup قيد التنفيذ حاليًا. يرجى الانتظار حتى انتهاء العملية الحالية.';

    /** هل نملك القفل الآن؟ */
    private bool $lockAcquired = false;

    /** هل توجد عملية نسخ احتياطي تعمل الآن؟ (تُزيل القفل المهجور تلقائيًا) */
    public function isRunning(): bool
    {
        $info = $this->lockInfo();
        if ($info === null) {
            return false;
        }

        if ($this->isStale($info)) {
            Log::warning('Stale backup lock removed', [
                'age_seconds' => time() - (int) $info['heartbeat'],
                'pid'         => $info['pid'] ?? null,
            ]);
            @unlink($info['file']);

            return false;
        }

        return true;
    }

    /** رسالة رفض التشغيل مع عمر العملية وطريقة الفك اليدوي. */
    public function lockMessage(): string
    {
        $info = $this->lockInfo();
        if ($info === null || $this->isStale($info)) {
            return self::LOCK_MESSAGE;
        }

        $minutes = max(1, (int) floor((time() - (int) $info['heartbeat']) / 60));

        return sprintf(
            'توجد عملية نسخ احتياطي قيد التنفيذ منذ %d دقيقة. %s' .
            ' إذا لم تكن هناك عملية فعلية نفّذ: php artisan backup:unlock',
            $minutes,
            self::LOCK_MESSAGE
        );
    }

    /** حالة القفل الحالي (للاستعلام/الأوامر) أو null إن لم يوجد. */
    public function lockStatus(): ?array
    {
        $info = $this->lockInfo();
        if ($info === null) {
            return null;
        }

        $info['age_seconds'] = max(0, time() - (int) $info['heartbeat']);
        $info['stale'] = $this->isStale($info);
        $info['alive'] = !empty($info['pid']) && $this->processAlive((int) $info['pid']);

        return $info;
    }

    /** فك القفل يدويًا (لعمليات مقتل/معطّلة لم تحرّره). */
    public function forceUnlock(): bool
    {
        $file = (string) config('backup.lock.file');
        $removed = false;

        if (is_file($file)) {
            $removed = @unlink($file);
        }

        $this->lockAcquired = false;
        Log::warning('Backup lock released manually', ['removed' => $removed]);

        return $removed;
    }

    /**
     * إعادة العمليات العالقة إلى "فشل" عند عدم وجود أي عملية حقيقية.
     * (يحدث فقط إذا لم يكن هناك قفل — أي أن العملية ماتت قبل تحريره.)
     */
    public function recoverStuckRuns(): int
    {
        if ($this->isRunning()) {
            return 0;
        }

        $stuck = BackupRun::where('status', BackupRun::STATUS_RUNNING)->get();
        foreach ($stuck as $run) {
            $run->fill([
                'status'         => BackupRun::STATUS_FAILED,
                'current_step'   => 'Failed',
                'error_message'  => $this->appendMessage($run->error_message, 'انقطعت العملية قبل اكتمالها'),
                'completed_at'   => now(),
                'duration_seconds' => $run->started_at ? (int) $run->started_at->diffInSeconds(now()) : null,
            ])->save();
        }

        if ($stuck->isNotEmpty()) {
            Log::warning('Recovered stuck backup runs', ['count' => $stuck->count()]);
        }

        return $stuck->count();
    }

    /** معلومات ملف القفل أو null. */
    private function lockInfo(): ?array
    {
        $file = (string) config('backup.lock.file');
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data)) {
            $data = [];
        }

        $data['file']      = $file;
        $data['pid']       = (int) ($data['pid'] ?? 0);
        $data['started_at']= (int) ($data['started_at'] ?? (@filemtime($file) ?: 0));
        $data['heartbeat'] = (int) ($data['heartbeat'] ?? (@filemtime($file) ?: 0));

        return $data;
    }

    /** القفل مهجور إن انتهت مهلته أو ماتت العملية المالكة له. */
    private function isStale(array $info): bool
    {
        $timeout = ((int) config('backup.lock.timeout_minutes')) * 60;
        if ((time() - (int) $info['heartbeat']) >= $timeout) {
            return true;
        }

        $pid = (int) ($info['pid'] ?? 0);

        return $pid > 0 && $pid !== getmypid() && !$this->processAlive($pid);
    }

    /** هل العملية ما زالت حية؟ (POSIX على Linux / tasklist على Windows) */
    private function processAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return true;
        }

        if (function_exists('posix_kill')) {
            if (@posix_kill($pid, 0)) {
                return true;
            }

            // EPERM: العملية موجودة لكن لمستخدم آخر
            return function_exists('posix_get_last_error') && posix_get_last_error() === 1;
        }

        if (!function_exists('exec')) {
            return true; // لا يمكن التحقق → نعتمد على المهلة
        }

        $windows = strncasecmp(PHP_OS_FAMILY, 'Windows', 7) === 0;
        $command = $windows
            ? 'tasklist /FI "PID eq ' . $pid . '" /NH 2>&1'
            : 'kill -0 ' . $pid . ' 2>/dev/null';

        $output = [];
        $code = 0;
        @exec($command, $output, $code);

        if (!$windows) {
            return $code === 0;
        }

        foreach ($output as $line) {
            if (preg_match('/\s' . preg_quote((string) $pid, '/') . '\s/i', (string) $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * تنفيذ عملية نسخ احتياطي كاملة.
     *
     * @throws RuntimeException عند وجود عملية أخرى تعمل أو عند فشل قاتل
     */
    public function run(): BackupRun
    {
        $this->acquireLock();

        $run = BackupRun::create([
            'status'     => BackupRun::STATUS_RUNNING,
            'current_step' => 'Preparing',
            'started_at' => now(),
        ]);

        Log::info('Backup started');

        try {
            $this->assertDiskSpace();
            $this->heartbeat();

            $this->step($run, 'Archiving Uploads');
            Log::info('Uploads archive started');
            $this->archiveMedia('uploads', $run);
            Log::info('Uploads archive completed', ['status' => $run->uploads_status]);

            $this->step($run, 'Archiving Attachments');
            Log::info('Attachments archive started');
            $this->archiveMedia('attachments', $run);
            Log::info('Attachments archive completed', ['status' => $run->attachments_status]);

            $this->step($run, 'Backing up Database');
            Log::info('Database backup started');
            $this->backupDatabase($run);
            Log::info('Database backup completed', ['status' => $run->database_status]);

            $this->step($run, 'Backing up Laravel');
            Log::info('Laravel backup started');
            $this->backupLaravelFiles($run);
            Log::info('Laravel backup completed', ['status' => $run->laravel_status]);

            $this->step($run, 'Verifying');
            $this->verify($run);
            Log::info('Backup verification completed');

            $this->step($run, 'Cleaning Old Backups');
            $this->cleanupOldBackups();

            $status = $this->resolveStatus($run);

            $run->fill([
                'status'       => $status,
                'current_step' => $status === BackupRun::STATUS_SUCCESS ? 'Completed'
                    : ($status === BackupRun::STATUS_PARTIAL ? 'Partial' : 'Failed'),
                'completed_at'   => now(),
                'duration_seconds' => $run->started_at ? (int) $run->started_at->diffInSeconds(now()) : null,
            ])->save();

            Log::info('Backup finished', ['status' => $status]);
        } catch (Throwable $e) {
            Log::error('Backup failed', ['reason' => $e->getMessage()]);

            $run->fill([
                'status'       => BackupRun::STATUS_FAILED,
                'current_step' => 'Failed',
                'error_message' => $this->appendMessage($run->error_message, $e->getMessage()),
                'completed_at'   => now(),
                'duration_seconds' => $run->started_at ? (int) $run->started_at->diffInSeconds(now()) : null,
            ])->save();

            throw $e;
        } finally {
            $this->releaseLock();
        }

        return $run;
    }

    // ═══════════════════════════════════════════════════════════
    //  القفل
    // ═══════════════════════════════════════════════════════════

    private function acquireLock(): void
    {
        $file = (string) config('backup.lock.file');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $info = $this->lockInfo();

            if ($info !== null) {
                if (!$this->isStale($info)) {
                    Log::info('Backup lock held by another process', ['pid' => $info['pid'] ?? null]);
                    throw new RuntimeException($this->lockMessage());
                }

                // قفل مهجور (عملية ماتت قبل تحريره)
                Log::warning('Stale backup lock removed', [
                    'age_seconds' => time() - (int) $info['heartbeat'],
                    'pid'         => $info['pid'] ?? null,
                ]);
                @unlink($file);
            }

            // إنشاء حصري: يفوز واحد فقط عند التسابق
            $fp = @fopen($file, 'x');
            if ($fp === false) {
                continue;
            }

            fwrite($fp, json_encode([
                'pid'        => getmypid(),
                'started_at' => time(),
                'heartbeat'  => time(),
            ]));
            fclose($fp);

            $this->lockAcquired = true;
            Log::info('Backup lock acquired');

            // ضمان التحرير عند الأخطاء القاتلة التي لا تنفذ finally
            register_shutdown_function(function () {
                if ($this->lockAcquired) {
                    $this->releaseLock();
                    Log::warning('Backup lock released by shutdown handler');
                }
            });

            return;
        }

        throw new RuntimeException($this->lockMessage());
    }

    /** تحديث نبض القفل بين المراحل (يمنع اعتباره مهجوراً). */
    private function heartbeat(): void
    {
        if (!$this->lockAcquired) {
            return;
        }

        $file = (string) config('backup.lock.file');
        $data = json_decode((string) @file_get_contents($file), true) ?: [];
        $data['heartbeat'] = time();
        @file_put_contents($file, json_encode($data));
    }

    private function releaseLock(): void
    {
        if (!$this->lockAcquired) {
            return;
        }

        $file = (string) config('backup.lock.file');
        if (is_file($file)) {
            @unlink($file);
        }
        $this->lockAcquired = false;
        Log::info('Backup lock released');
    }

    // ═══════════════════════════════════════════════════════════
    //  فحوصات ما قبل التشغيل
    // ═══════════════════════════════════════════════════════════

    private function assertDiskSpace(): void
    {
        $dir = $this->backupDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $free = @disk_free_space($dir);
        $required = ((int) config('backup.min_free_space_mb')) * 1024 * 1024;

        if ($free !== false && $free < $required) {
            throw new RuntimeException(sprintf(
                'Insufficient free disk space for backup: %.1f GB free, %.1f GB required',
                $free / 1073741824,
                $required / 1073741824
            ));
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  أرشفة الوسائط إلى OneDrive (COPY — لا حذف ولا نقل)
    // ═══════════════════════════════════════════════════════════

    private function archiveMedia(string $key, BackupRun $run): void
    {
        $column = "{$key}_status";

        if (!config('backup.enabled') || !config("backup.archive.{$key}.enabled")) {
            $run->fill([$column => 'disabled'])->save();
            return;
        }

        $sourceRel = (string) config("backup.archive.{$key}.source");
        $source = $this->absolutePath($sourceRel);

        if (!is_dir($source)) {
            $run->fill([
                $column        => 'skipped',
                "{$key}_files" => 0,
                "{$key}_size"  => 0,
                'error_message' => $this->appendMessage(
                    $run->error_message,
                    "source directory does not exist: {$sourceRel}"
                ),
            ])->save();
            Log::warning('Backup media source missing', ['source' => $sourceRel]);
            return;
        }

        [$files, $size] = $this->directoryStats($source);
        $run->fill([
            "{$key}_files" => $files,
            "{$key}_size"  => $size,
        ])->save();

        // الوجهة: مجلد واحد يضم محتوى uploads و attachments مدمجاً (بلا مجلدات فرعية)
        // rclone copy: إضافة/تحديث فقط — لا حذف ولا نقل لأي ملف موجود هناك
        $destination = rtrim((string) config('backup.onedrive_path'), '/\\');

        $unavailable = $this->destinationProblem();
        if ($unavailable !== null) {
            $run->fill([
                $column         => 'failed',
                "{$key}_copied" => 0,
                "{$key}_skipped" => 0,
                "{$key}_failed" => $files,
                'error_message' => $this->appendMessage($run->error_message, $unavailable),
            ])->save();
            Log::error('OneDrive archive directory unavailable', ['reason' => $unavailable]);
            return;
        }

        // -v ضروري: بدونه لا يطبع rclone ملخص الإحصائيات في الطرفية
        $result = $this->runRclone(['copy', $source, $destination, '-v']);

        $stats = $this->parseRcloneStats($result['output']);
        $transferred = $stats['transferred'];
        $errors = $stats['errors'];

        $copied = $transferred;
        $failed = $errors;
        $skipped = null;
        if ($copied !== null) {
            $skipped = max(0, $files - $copied - (int) ($failed ?? 0));
        }

        $status = $result['success'] ? 'success' : 'failed';

        $message = null;
        if (!$result['success']) {
            $message = sprintf(
                'rclone copy failed (exit %d): %s',
                $result['return_code'],
                $this->tail($result['output'], 500)
            );
        }

        $run->fill([
            $column          => $status,
            "{$key}_copied"  => $copied,
            "{$key}_skipped" => $skipped,
            "{$key}_failed"  => $failed,
            'error_message'  => $message ? $this->appendMessage($run->error_message, $message) : $run->error_message,
        ])->save();

        if ($message) {
            Log::error('Backup media archive failed', ['media' => $key, 'reason' => $message]);
        } else {
            Log::info('Backup media archive stats', [
                'media'   => $key,
                'total'   => $files,
                'copied'  => $copied,
                'skipped' => $skipped,
                'failed'  => $failed,
                'size'    => $size,
            ]);
        }
    }

    /**
     * فحص توفّر الوجهة قبل النسخ.
     * مسار محلي: يجب أن يكون موجوداً أصلاً (وإذا أنشأناه يدوياً فلن يتزامن مع OneDrive = نجاح كاذب).
     * remote: يجب أن يستجيب rclone له.
     *
     * @return string|null رسالة المشكلة أو null إذا كانت الوجهة سليمة
     */
    private function destinationProblem(): ?string
    {
        $destination = (string) config('backup.onedrive_path');

        if ($destination === '') {
            return 'Backup destination is not configured (BACKUP_ONEDRIVE_PATH)';
        }

        if ($this->isRcloneRemote($destination)) {
            $remote = explode(':', $destination, 2)[0];
            $result = $this->runRclone(['lsd', $remote . ':']);
            if (!$result['success']) {
                return 'OneDrive archive directory unavailable: ' . $this->tail($result['output'], 300)
                    . $this->rcloneDiagnostics();
            }
            return null;
        }

        if (!is_dir($destination)) {
            return 'OneDrive archive directory unavailable: ' . $destination;
        }

        return null;
    }

    /**
     * تشخيص ذاتي عند فشل rclone: أي ملف إعدادات قرأ، وremotes المكتشفة فيه.
     * (الفرق بين بيئة artisan والطرفية يظهر مباشرة في الرسالة.)
     */
    private function rcloneDiagnostics(): string
    {
        $info = [];

        $cfg = $this->runRclone(['config', 'file']);
        foreach (explode("\n", $cfg['output']) as $line) {
            $line = trim($line);
            // الصياغة: "Configuration file is stored at:" ثم المسار في السطر التالي
            if ($line !== '' && !str_ends_with($line, ':')) {
                $info[] = 'config: ' . $line;
                break;
            }
        }

        $list = $this->runRclone(['listremotes']);
        if ($list['success']) {
            $remotes = array_values(array_filter(array_map('trim', explode("\n", trim($list['output'])))));
            $info[] = 'remotes: [' . ($remotes ? implode(', ', $remotes) : 'none') . ']';
        } else {
            $info[] = 'listremotes: ' . $this->tail($list['output'], 200);
        }

        return $info ? ' [' . implode(' | ', $info) . ']' : '';
    }

    /** هل الوجهة rclone remote (ون) وليس مساراً محلياً؟ */
    private function isRcloneRemote(string $path): bool
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
            return false; // C:\ أو C:/
        }

        return (bool) preg_match('/^[A-Za-z0-9_. -]+:/', $path);
    }

    // ═══════════════════════════════════════════════════════════
    //  نسخة MySQL
    // ═══════════════════════════════════════════════════════════

    private function backupDatabase(BackupRun $run): void
    {
        $databases = array_values(array_filter(array_map('trim', explode(',', (string) config('backup.databases')))));

        if (!$databases) {
            $run->fill(['database_status' => 'skipped', 'database_size' => 0])->save();
            return;
        }

        $connection = config('database.connections.' . config('database.default'), []);
        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';
        $user = $connection['username'] ?? 'root';
        $pass = $connection['password'] ?? '';

        $dir = $this->backupDir();
        $stamp = date('Y-m-d_His');
        $totalSize = 0;
        $failures = [];

        foreach ($databases as $database) {
            $file = $dir . DIRECTORY_SEPARATOR . 'backup_db_' . $this->safeName($database) . '_' . $stamp . '.sql';

            $arguments = [
                (string) config('backup.mysqldump_path'),
                '-h', (string) $host,
                '-P', (string) $port,
                '-u', (string) $user,
            ];
            if ($pass !== '' && $pass !== null) {
                $arguments[] = '-p' . $pass; // بدون مسافة — كي لا تظهر في سجل العملية
            }
            $arguments[] = '--single-transaction';
            $arguments[] = '--routines';
            $arguments[] = '--triggers';
            $arguments[] = '--events';
            $arguments[] = '--result-file=' . $file;
            $arguments[] = $database;

            $result = $this->runProcess($arguments);

            if (!$result['success'] || !is_file($file) || filesize($file) === 0) {
                $failures[] = sprintf(
                    '%s (exit %d): %s',
                    $database,
                    $result['return_code'],
                    $this->tail($result['output'], 400)
                );
                Log::error('Database backup failed', ['database' => $database, 'exit_code' => $result['return_code']]);
                @unlink($file);
                continue;
            }

            $totalSize += (int) filesize($file);
            Log::info('Database backup file created', ['database' => $database, 'file' => basename($file), 'size' => $totalSize]);
        }

        $run->fill([
            'database_size'   => $totalSize,
            'database_status' => $failures ? 'failed' : 'success',
            'error_message'   => $failures
                ? $this->appendMessage($run->error_message, implode(' | ', $failures))
                : $run->error_message,
        ])->save();
    }

    // ═══════════════════════════════════════════════════════════
    //  نسخة ملفات Laravel (ZIP) مع الاستثناءات
    // ═══════════════════════════════════════════════════════════

    private function backupLaravelFiles(BackupRun $run): void
    {
        $dir = $this->backupDir();
        $stamp = date('Y-m-d_His');
        $zipPath = $dir . DIRECTORY_SEPARATOR . 'backup_laravel_' . $stamp . '.zip';

        $excludes  = $this->buildExcludes();
        $gitignore = $this->gitignorePatterns();
        $base = base_path();
        $baseLen = strlen(rtrim($base, DIRECTORY_SEPARATOR));

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create Laravel backup zip: ' . $zipPath);
        }

        $directory = new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $current) use ($excludes, $gitignore, $baseLen) {
            $relative = str_replace('\\', '/', substr($current->getPathname(), $baseLen));
            $relative = ltrim($relative, '/');

            // false يقص الشجرة كاملة (أسرع من المرور على كل الأبناء)
            return !$this->excludedFromZip($relative, $excludes, $gitignore);
        });

        $iterator = new RecursiveIteratorIterator($filtered);
        $added = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), $baseLen)), '/');
            if ($zip->addFile($file->getPathname(), $relative)) {
                $added++;
            }
        }

        $closed = $zip->close();

        if (!$closed || !is_file($zipPath) || filesize($zipPath) === 0) {
            $run->fill([
                'laravel_status' => 'failed',
                'error_message'  => $this->appendMessage($run->error_message, 'Laravel zip creation failed'),
            ])->save();
            Log::error('Laravel backup failed', ['files' => $added]);
            return;
        }

        $run->fill([
            'laravel_size'   => (int) filesize($zipPath),
            'laravel_status' => 'success',
        ])->save();

        Log::info('Laravel backup zip created', ['files' => $added, 'size' => (int) filesize($zipPath)]);
    }

    /** الاستثناءات: مجلدا الوسائط + مجلد الإخراج + الاستثناءات الإضافية. */
    private function buildExcludes(): array
    {
        $excludes = [];

        if (config('backup.exclude_archived_media')) {
            $excludes[] = 'storage/app/public/uploads';
            $excludes[] = 'storage/app/public/attachments';
        }

        $excludes[] = trim((string) config('backup.path'), '/\\');
        $excludes = array_merge($excludes, (array) config('backup.exclude_extra'));

        return array_values(array_unique(array_filter($excludes)));
    }

    /**
     * قواعد .gitignore (تُقرأ وقت التشغيل) لاستثناءها من نسخة ZIP.
     * أرشيف الوسائط عبر rclone لا علاقة له بهذا — يبقى كما هو.
     */
    private function gitignorePatterns(): array
    {
        if (!config('backup.exclude_gitignore', true)) {
            return [];
        }

        $file = base_path('.gitignore');
        if (!is_file($file)) {
            return [];
        }

        $patterns = [];
        foreach ((array) @file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $patterns[] = $line;
        }

        return $patterns;
    }

    /** هل يُستبعد هذا المسار من ZIP (استثناءات صريحة أو قواعد .gitignore)؟ */
    private function excludedFromZip(string $relative, array $excludes, array $gitignore): bool
    {
        foreach ($excludes as $exclude) {
            if ($relative === $exclude || str_starts_with($relative, $exclude . '/')) {
                return true;
            }

            // اسم بلا شرطة مائلة (node_modules) يُطبَّق في أي عمق — كسلوك git
            if (!str_contains($exclude, '/') && str_contains('/' . $relative . '/', '/' . $exclude . '/')) {
                return true;
            }
        }

        return $this->matchesGitignore($relative, $gitignore);
    }

    /** مطابقة مسار نسبي مع قواعد .gitignore (بادئات وأنماط بجlobs). */
    private function matchesGitignore(string $relative, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $anchored = str_starts_with($pattern, '/');
            $clean = trim(rtrim($pattern, '/'), '/');

            if ($clean === '') {
                continue;
            }

            if (strpbrk($clean, '*?[') === false) {
                // بدون wildcard: بادئة ( /vendor ) أو اسم يظهر في أي مستوى (node_modules )
                if ($relative === $clean || str_starts_with($relative, $clean . '/')) {
                    return true;
                }

                if (!$anchored && str_contains('/' . $relative . '/', '/' . $clean . '/')) {
                    return true;
                }

                continue;
            }

            // wildcard: *.log / storage/*.key / emulator_*.png
            if (fnmatch($clean, $relative) || fnmatch($pattern, $relative)) {
                return true;
            }
        }

        return false;
    }

    // ═══════════════════════════════════════════════════════════
    //  التحقق
    // ═══════════════════════════════════════════════════════════

    private function verify(BackupRun $run): void
    {
        // 1) ملفات SQL
        if ($run->database_status === 'success') {
            foreach ($this->generatedFiles('backup_db_*.sql') as $file) {
                if (@filesize($file) === 0) {
                    $this->markCoreFailure($run, 'database', 'Empty database backup file: ' . basename($file));
                    break;
                }
            }
        }

        // 2) ZIP
        if ($run->laravel_status !== 'success') {
            return;
        }

        $zips = $this->generatedFiles('backup_laravel_*.zip');
        $zipFile = null;

        if ($zips) {
            usort($zips, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $zipFile = $zips[0];
        }

        if (!$zipFile || @filesize($zipFile) === 0) {
            $this->markCoreFailure($run, 'laravel', 'Laravel backup zip missing or empty');
            return;
        }

        // 3) يجب ألا يحتوي ZIP على مجلدات الوسائط المؤرشفة
        if (!config('backup.exclude_archived_media')) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            $this->markCoreFailure($run, 'laravel', 'Unable to open Laravel backup zip for verification');
            return;
        }

        $forbidden = ['storage/app/public/uploads', 'storage/app/public/attachments'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            foreach ($forbidden as $prefix) {
                if ($name === $prefix || str_starts_with($name, $prefix . '/')) {
                    $zip->close();
                    $this->markCoreFailure($run, 'laravel', 'Archived media leaked into zip: ' . $name);
                    return;
                }
            }
        }
        $zip->close();
    }

    private function markCoreFailure(BackupRun $run, string $target, string $message): void
    {
        $run->fill([
            "{$target}_status" => 'failed',
            'error_message'    => $this->appendMessage($run->error_message, $message),
        ])->save();

        Log::error('Backup verification failed', ['target' => $target, 'reason' => $message]);
    }

    // ═══════════════════════════════════════════════════════════
    //  حذف النسخ القديمة (على .backups فقط — لا شيء من الأرشيف)
    // ═══════════════════════════════════════════════════════════

    private function cleanupOldBackups(): void
    {
        $keep = (int) config('backup.keep_count');
        if ($keep < 1) {
            return;
        }

        foreach (['backup_db_*.sql', 'backup_laravel_*.zip'] as $pattern) {
            $files = $this->generatedFiles($pattern);
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

            foreach (array_slice($files, $keep) as $old) {
                if (@unlink($old)) {
                    Log::info('Old backup removed', ['file' => basename($old)]);
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  أدوات
    // ═══════════════════════════════════════════════════════════

    private function step(BackupRun $run, string $label): void
    {
        $run->fill(['current_step' => $label])->save();
        $this->heartbeat();
    }

    private function resolveStatus(BackupRun $run): string
    {
        $coreFailed = $run->database_status === 'failed' || $run->laravel_status === 'failed';
        $partial = $run->uploads_status === 'failed'
            || $run->attachments_status === 'failed';

        if ($coreFailed) {
            return BackupRun::STATUS_FAILED;
        }

        return $partial ? BackupRun::STATUS_PARTIAL : BackupRun::STATUS_SUCCESS;
    }

    private function backupDir(): string
    {
        return $this->absolutePath((string) config('backup.path'));
    }

    /** مسار نسبي إلى المجلد الجذر للمشروع، أو مسار مطلق كما هو. */
    private function absolutePath(string $path): string
    {
        if ($path === '') {
            return base_path();
        }

        if (preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path)) {
            return rtrim($path, '/\\');
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path, '/\\')));
    }

    private function generatedFiles(string $pattern): array
    {
        $files = glob($this->backupDir() . DIRECTORY_SEPARATOR . $pattern) ?: [];

        return array_values(array_filter($files, 'is_file'));
    }

    /** إحصائيات مجلد المصدر: [عدد الملفات, الحجم بالبايت]. */
    private function directoryStats(string $directory): array
    {
        $count = 0;
        $size = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
                $size += (int) $file->getSize();
            }
        }

        return [$count, $size];
    }

    /** تنفيذ أمر عبر Symfony Process (معاملات كمصفوفة — لا shell). */
    private function runRclone(array $arguments): array
    {
        array_unshift($arguments, (string) config('backup.rclone_path'));

        $config = config('backup.rclone_config');
        if ($config && is_file($config)) {
            array_splice($arguments, 1, 0, ['--config', $config]);
        }

        return $this->runProcess($arguments);
    }

    private function runProcess(array $arguments): array
    {
        $commandForLog = implode(' ', array_map(
            fn ($a) => str_starts_with((string) $a, '-p') && strlen($a) > 2 ? '-p***' : escapeshellarg((string) $a),
            $arguments
        ));

        Log::info('BACKUP_COMMAND', ['command' => $commandForLog]);

        $timeout = max(60, ((int) config('backup.timeout_minutes')) * 60);

        try {
            $result = Process::timeout($timeout)->run($arguments);

            return [
                'success'     => $result->successful(),
                'return_code' => $result->exitCode(),
                'output'      => $result->output() . "\n" . $result->errorOutput(),
            ];
        } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
            Log::error('Backup command timed out', ['command' => $commandForLog, 'timeout_seconds' => $timeout]);

            return ['success' => false, 'return_code' => 124, 'output' => 'Process timed out'];
        } catch (Throwable $e) {
            Log::error('Backup command execution error', ['command' => $commandForLog, 'reason' => $e->getMessage()]);

            return ['success' => false, 'return_code' => 1, 'output' => $e->getMessage()];
        }
    }

    /**
     * استخراج "Copied / Checks / Errors" من ملخص rclone (يتطلب -v).
     *
     * Transferred:  N / M, ...   ← عدد الملفات المنقولة (السطر الأخير مطابق هو الملخص)
     * Checks:       N / M, ...   ← عدد الملفات المفحوصة (المتطابقة تُتخطى)
     * Errors:       N            ← عدد الفشل
     * "There was nothing to transfer" ← لم يُنسخ أي ملف (الكل متطابق)
     */
    private function parseRcloneStats(string $output): array
    {
        $transferred = null;
        $errors = null;
        $checked = null;

        // السطر الأخير المطابق هو الملخص النهائي (سطر البايتات لا يطابق)
        if (preg_match_all('/^Transferred:\s+([\d,]+)\s*\/\s*[\d,]+,/mi', $output, $matches)) {
            $last = end($matches[1]);
            $transferred = (int) str_replace(',', '', $last);
        } elseif (str_contains($output, 'There was nothing to transfer')) {
            $transferred = 0;
        }

        if (preg_match('/^Checks:\s+([\d,]+)/mi', $output, $m)) {
            $checked = (int) str_replace(',', '', $m[1]);
        }

        if (preg_match('/^Errors:\s+([\d,]+)/mi', $output, $m)) {
            $errors = (int) str_replace(',', '', $m[1]);
        }

        return ['transferred' => $transferred, 'errors' => $errors, 'checked' => $checked];
    }

    private function safeName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $value) ?: 'database';
    }

    private function appendMessage(?string $existing, string $addition): ?string
    {
        if ($existing === null || $existing === '') {
            return $addition;
        }

        return $existing . ' | ' . $addition;
    }

    private function tail(string $text, int $length): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return strlen($text) > $length ? '...' . substr($text, -$length) : $text;
    }
}
