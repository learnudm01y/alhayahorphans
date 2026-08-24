<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ExportCivilRegistry
 *
 * Builds a lightweight, chunked, gzip-compressed export of the civil registry
 * (civilregistry.persons) so the Android app can download "نسخة خفيفة من السجل
 * المدني" and look up citizens by هوية fully offline.
 *
 * Output layout under storage/app/civil_registry/:
 *   manifest.json            — list of chunks (file, size, sha256, count), total, created_at
 *   chunk.0001.json.gz       — newline-delimited JSON rows, sorted by CI_ID_NUM
 *   chunk.0002.json.gz
 *   ...
 *
 * Each exported row keeps only the fields needed by the app:
 *   id   => CI_ID_NUM (هوية)
 *   f    => CI_FIRST_ARB
 *   fa   => CI_FATHER_ARB
 *   gf   => CI_GRAND_FATHER_ARB
 *   fam  => CI_FAMILY_ARB
 *   m    => MOTHER_NAME1
 *   b    => CI_BIRTH_DT
 *   s    => CI_SEX_CD
 *   p    => CI_PERSONAL_CD
 *   c    => CITY
 *   st   => STREET
 *   ho   => HOUSE_NO
 *   d    => CI_DEAD_DT (null = alive)
 */
class ExportCivilRegistry extends Command
{
    protected $signature = 'civil-registry:export
                            {--chunk=20000 : Rows per chunk file}
                            {--force : Rebuild from scratch (delete previous export)}
                            {--min-id= : Only export identities >= this value}
                            {--max-id= : Only export identities <= this value}';

    protected $description = 'Export the civil registry into compressed chunk files for the mobile app';

    public function handle(): int
    {
        $chunkSize = max(1000, (int) $this->option('chunk'));
        $force = (bool) $this->option('force');
        $minId = $this->option('min-id');
        $maxId = $this->option('max-id');

        $disk = 'local';
        $root = 'civil_registry';

        if ($force) {
            Storage::disk($disk)->deleteDirectory($root);
        }

        Storage::disk($disk)->makeDirectory($root);

        $query = DB::connection('civilregistry')
            ->table('persons')
            ->select(
                'ID',
                'CI_ID_NUM',
                'CI_FIRST_ARB',
                'CI_FATHER_ARB',
                'CI_GRAND_FATHER_ARB',
                'CI_FAMILY_ARB',
                'MOTHER_NAME1',
                'CI_BIRTH_DT',
                'CI_SEX_CD',
                'CI_PERSONAL_CD',
                'CITY',
                'STREET',
                'HOUSE_NO',
                'CI_DEAD_DT'
            )
            ->whereNotNull('CI_ID_NUM')
            ->where('CI_ID_NUM', '<>', '');

        if ($minId !== null) {
            $query->where('CI_ID_NUM', '>=', $minId);
        }
        if ($maxId !== null) {
            $query->where('CI_ID_NUM', '<=', $maxId);
        }

        $total = (clone $query)->count();
        $this->info("Exporting {$total} persons in chunks of {$chunkSize}");

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'total_records' => $total,
            'chunk_size' => $chunkSize,
            'format' => 'ndjson.gz',
            'chunks' => [],
        ];

        $chunkIndex = 0;
        $processed = 0;
        $startedAt = microtime(true);

        $this->newLine();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $handle = null;
        $recordsInChunk = 0;
        $chunkFile = null;

        $flushChunk = function () use (&$handle, &$chunkIndex, &$recordsInChunk, &$chunkFile, &$manifest, $disk, $root, $processed) {
            if ($handle === null) {
                return;
            }
            fclose($handle);
            $handle = null;

            $gzPath = "{$root}/chunk." . str_pad((string) $chunkIndex, 4, '0', STR_PAD_LEFT) . '.json.gz';

            // Re-target: we wrote to a temp file then gzip it into place.
            $tmpRaw = "{$root}/chunk." . str_pad((string) $chunkIndex, 4, '0', STR_PAD_LEFT) . '.raw';
            $rawPath = storage_path("app/{$tmpRaw}");

            if (Storage::disk($disk)->exists($tmpRaw)) {
                $rawFull = $tmpRaw;
            } else {
                $rawFull = $rawPath;
            }

            $rawAbs = storage_path('app/' . ltrim($rawFull, '/'));
            $gzAbs = storage_path('app/' . $gzPath);

            $in = fopen($rawAbs, 'rb');
            $gz = gzopen($gzAbs, 'wb6');
            stream_copy_to_stream($in, $gz);
            gzclose($gz);
            fclose($in);

            $size = filesize($gzAbs);
            $sha = hash_file('sha256', $gzAbs);

            // Keep only the gz file; drop the raw temp
            if ($rawAbs !== $gzAbs) {
                @unlink($rawAbs);
            }

            $manifest['chunks'][] = [
                'file' => str_replace($root . '/', '', $gzPath),
                'size' => $size,
                'sha256' => $sha,
                'records' => $recordsInChunk,
            ];

            $this->line('');
            $this->info("  chunk.{$chunkIndex} done: {$recordsInChunk} rows, " . round($size / 1024) . ' KB');

            $chunkIndex++;
            $recordsInChunk = 0;
            $chunkFile = null;
        };

        $query->chunkById($chunkSize, function ($rows) use (
            &$handle, &$recordsInChunk, &$chunkIndex, &$chunkFile, &$manifest,
            $flushChunk, $disk, $root, &$processed, $bar
        ) {
            foreach ($rows as $row) {
                if ($handle === null) {
                    $rawPath = "{$root}/chunk." . str_pad((string) $chunkIndex, 4, '0', STR_PAD_LEFT) . '.raw';
                    $handle = fopen(storage_path('app/' . $rawPath), 'wb');
                }

                $rec = [
                    'id' => (string) $row->CI_ID_NUM,
                    'f' => (string) ($row->CI_FIRST_ARB ?? ''),
                    'fa' => (string) ($row->CI_FATHER_ARB ?? ''),
                    'gf' => (string) ($row->CI_GRAND_FATHER_ARB ?? ''),
                    'fam' => (string) ($row->CI_FAMILY_ARB ?? ''),
                    'm' => (string) ($row->MOTHER_NAME1 ?? ''),
                    'b' => (string) ($row->CI_BIRTH_DT ?? ''),
                    's' => (string) ($row->CI_SEX_CD ?? ''),
                    'p' => (string) ($row->CI_PERSONAL_CD ?? ''),
                    'c' => (string) ($row->CITY ?? ''),
                    'st' => (string) ($row->STREET ?? ''),
                    'ho' => (string) ($row->HOUSE_NO ?? ''),
                    'd' => ($row->CI_DEAD_DT !== null && $row->CI_DEAD_DT !== '') ? (string) $row->CI_DEAD_DT : null,
                ];
                fwrite($handle, json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

                $recordsInChunk++;
                $processed++;
                if ($recordsInChunk % 100 === 0) {
                    $bar->advance(100);
                }
            }
            // flush at chunk boundary
            if ($recordsInChunk > 0) {
                $flushChunk();
            }
        }, 'ID');

        if ($handle !== null) {
            $flushChunk();
        }

        $bar->finish();

        // Write manifest
        $manifest['generated_seconds'] = round(microtime(true) - $startedAt, 1);
        Storage::disk($disk)->put("{$root}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        unset($manifest['generated_seconds']);

        // تأكد أن خادم الويب (www-data) يستطيع قراءة الملفات حتى لو شُغّل الأمر كـ root
        @chmod(storage_path('app/' . $root), 0755);
        foreach (glob(storage_path('app/' . $root . '/*')) as $file) {
            @chmod($file, 0644);
        }

        $this->newLine(2);
        $this->info("✅ Export complete: {$processed} persons in {$chunkIndex} chunk(s)");
        $this->info("📍 " . storage_path('app/' . $root . '/manifest.json'));

        return self::SUCCESS;
    }
}