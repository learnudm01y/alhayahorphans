<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * ترميم صورة الوجه كمساعد للفحص فقط (يُستدعى من cropper.blade.php عند فشل فحص الوجه).
 *
 * يولّد node scripts/face-restore.js ثم يعيد الصورة المرمّمة كـ base64 بعد أن يجتاز
 * ناتجُها الفحصَ بالخادم (check.pass). المتصفح يستخدم نجاحها دليلاً على وجود وجه في
 * الصورة الأصلية، لكنه يؤرشف الأصل دون استبدال (FACE_CFG.saveRestoredImage = false).
 * file_type = 12 تخرج 400x600، وبقية الأنواع تخرج بمقاسها الأصلي.
 */
class FaceRestoreController extends Controller
{
    /** حد رفع الصورة بالكيلوبايت (نفس حد الرفع في التطبيق). */
    private const MAX_UPLOAD_KB = 8192;

    /** مهلة ثابتة لعملية node (المعالجة تحتاج عشرات الثواني). */
    private const TIMEOUT_SECONDS = 120;

    /** الصور الشخصية (file_type = 12) فقط تخرج 400x600 — البقية تُرمَّم دون تغيير المقاس. */
    private const PERSONAL_PHOTO_FILE_TYPE = '12';

    private const PERSONAL_PHOTO_TARGET = '400x600';

    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|max:' . self::MAX_UPLOAD_KB . '|mimes:jpeg,jpg,png,webp',
            'file_type' => 'nullable|string|max:20',
        ]);

        $fileType = trim((string) $request->input('file_type', ''));

        // عملية node واحدة في المذاكرة (الذاكرة محدودة)
        $lock = @fopen(storage_path('framework/face-restore.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return response()->json(['ok' => false, 'applied' => true, 'reason' => 'busy']);
        }

        $dir = storage_path('app/face_tmp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $token = bin2hex(random_bytes(8));
        $inPath = $dir . DIRECTORY_SEPARATOR . $token . '.src';
        $outPath = $dir . DIRECTORY_SEPARATOR . $token . '.out.jpg';

        try {
            $request->file('image')->move($dir, $token . '.src');

            // --target للصورة الشخصية فقط (400x600)، وبقية الأنواع بلا --target فيخرج بمقاسها الأصلي
            $cmd = [
                'node',
                base_path('scripts/face-restore.js'),
                '--in', $inPath,
                '--out', $outPath,
                '--check',
                '--json',
            ];
            if ($fileType === self::PERSONAL_PHOTO_FILE_TYPE) {
                $cmd[] = '--target';
                $cmd[] = self::PERSONAL_PHOTO_TARGET;
            }

            $process = new Process($cmd, base_path());
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->setIdleTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            $payload = $this->parseJsonOutput($process->getOutput());
            $check = $payload['check'] ?? [];

            if (!is_file($outPath) || empty($payload['ok']) || empty($check['pass'])) {
                $reason = $payload['reason'] ?? ($check['detail'] ?? ($check['code'] ?? 'restore-failed'));
                Log::channel('face_check')->warning('face-restore: فشل الترميم', [
                    'reason' => $reason,
                    'code' => $check['code'] ?? ($payload['reason'] ?? null),
                    'exit' => $process->getExitCode(),
                    'stderr' => mb_substr($process->getErrorOutput(), -800),
                ]);

                return response()->json([
                    'ok' => false,
                    'applied' => true,
                    'reason' => $reason,
                    'restore' => $payload['restore'] ?? null,
                    'rfError' => $payload['rfError'] ?? null,
                ]);
            }

            $bytes = file_get_contents($outPath);
            if ($bytes === false || $bytes === '') {
                return response()->json(['ok' => false, 'applied' => true, 'reason' => 'empty-output']);
            }

            Log::channel('face_check')->info('face-restore: نجح الترميم', [
                'fileType' => $fileType ?: '-',
                'restore' => $payload['restore'] ?? null,
                'w' => $payload['w'] ?? null,
                'h' => $payload['h'] ?? null,
                'code' => $check['code'] ?? null,
                'score' => $check['score'] ?? null,
                'rotated' => $check['rotated'] ?? null,
                'ms' => $payload['ms']['total'] ?? null,
                'rfError' => $payload['rfError'] ?? null,
            ]);

            return response()->json([
                'ok' => true,
                'applied' => true,
                'image' => 'data:image/jpeg;base64,' . base64_encode($bytes),
                'width' => (int) ($payload['w'] ?? 0),
                'height' => (int) ($payload['h'] ?? 0),
                'restore' => $payload['restore'] ?? null,
                'rfError' => $payload['rfError'] ?? null,
                'check' => $check,
                'ms' => $payload['ms'] ?? null,
            ]);
        } catch (ProcessTimedOutException $e) {
            Log::channel('face_check')->warning('face-restore: انتهت المهلة', ['message' => $e->getMessage()]);

            return response()->json(['ok' => false, 'applied' => true, 'reason' => 'timeout']);
        } catch (\Throwable $e) {
            Log::channel('face_check')->error('face-restore: خطأ غير متوقع', [
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json(['ok' => false, 'applied' => true, 'reason' => 'error']);
        } finally {
            foreach ([$inPath, $outPath] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * سكربت node يطبع سجلاته على stderr وسطر JSON واحد على stdout
     * (قد تسبقه تحذيرات node)، فنلتقط آخر سطر صالح.
     */
    private function parseJsonOutput(string $stdout): array
    {
        $lines = array_reverse(explode("\n", str_replace("\r", '', $stdout)));
        foreach ($lines as $line) {
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
