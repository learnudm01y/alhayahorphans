<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * تشغيل فحص الوجه الخادمي (scripts/face-verify.js) على صورة واحدة في الذاكرة.
 *
 * يُرجّع قراراً بصيغة موحّدة:
 *   ['ok' => bool, 'pass' => bool, 'code' => string, 'detail' => ?string, ...]
 */
class FaceVerifyService
{
    private const TIMEOUT_SECONDS = 45;

    /**
     * ميزانية المسح داخل node: الصور الناجحة تُحسم عملياً في أقل من 4 ثوانٍ،
     * أما 12 ثانية كاملة فلا تُستهلك إلا في صورة بلا وجه (استنفاد كل الاتجاهات).
     */
    private const BUDGET_MS = 8000;

    public function verifyFile(string $absPath): array
    {
        $lock = @fopen(storage_path('framework/face-verify.lock'), 'c');

        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return $this->result(false, 'busy', 'الخادم مشغول بفحص صورة أخرى');
        }

        try {
            $process = new Process([
                'node',
                base_path('scripts/face-verify.js'),
                '--in', $absPath,
                '--json',
                '--budget', (string) self::BUDGET_MS,
            ], base_path());

            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->setIdleTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            $payload = $this->parseJsonOutput($process->getOutput());

            if (empty($payload) || !($payload['ok'] ?? false)) {
                $reason = $payload['reason'] ?? 'no-output';
                Log::warning('face-verify: تعذّر إتمام الفحص', [
                    'reason' => $reason,
                    'exit' => $process->getExitCode(),
                    'stderr' => mb_substr($process->getErrorOutput(), -600),
                ]);

                return $this->result(false, $reason === 'engine-error' ? 'engine-error' : $reason, null, $payload);
            }

            $check = $payload['check'] ?? [];

            return $this->result(
                true,
                $check['code'] ?? ($check['pass'] ? 'ok' : 'no-face'),
                $check['detail'] ?? null,
                $payload,
                (bool) ($check['pass'] ?? false),
            );
        } catch (ProcessTimedOutException $e) {
            Log::warning('face-verify: انتهت المهلة');

            return $this->result(false, 'timeout', 'انتهت مهلة الفحص');
        } catch (\Throwable $e) {
            Log::error('face-verify: خطأ غير متوقع', [
                'message' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return $this->result(false, 'engine-error', 'خطأ في نظام الفحص');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function result(bool $ok, string $code, ?string $detail, array $payload = [], ?bool $pass = null): array
    {
        $check = $payload['check'] ?? [];

        return [
            'ok' => $ok,
            'pass' => $pass ?? (bool) ($check['pass'] ?? false),
            'code' => $code,
            'detail' => $detail,
            'score' => $check['score'] ?? null,
            'rotated' => $check['rotated'] ?? 0,
            'metrics' => $check['metrics'] ?? null,
            'work' => isset($payload['w']) ? ['w' => $payload['w'], 'h' => $payload['h']] : null,
            'ms' => $payload['ms']['total'] ?? ($check['ms'] ?? null),
        ];
    }

    /** node يطبع سجلّه على stderr وسطر JSON واحداً على stdout — نلتقط آخر سطر صالح */
    private function parseJsonOutput(string $stdout): array
    {
        foreach (array_reverse(explode("\n", str_replace("\r", '', $stdout))) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
