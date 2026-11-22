<?php

namespace Tests\Feature;

use App\Jobs\EnhanceRegistrationDocument;
use App\Models\Attachment;
use App\Services\DocumentEnhancementService;
use Closure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DocumentEnhancementTest extends TestCase
{
    private const METADATA_COLUMNS = [
        'document_enhancement_status', 'document_enhancement_metadata',
    ];

    private const FILE_COLUMNS = [
        'file_path', 'stored_file_name', 'file_size', 'file_hash',
    ];

    private string $isolatedStorage;
    private string $originalBytes;
    private string $enhancedBytes;
    private DocumentEnhancementService $documents;
    private array $processOutputs = [];
    private int $fixtureNumber = 0;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $this->isolatedStorage = sys_get_temp_dir().'/document-enhancement-test-'.bin2hex(random_bytes(8));
        foreach (['framework/views', 'framework/cache', 'framework/sessions', 'logs'] as $directory) {
            (new Filesystem)->makeDirectory($this->isolatedStorage.'/'.$directory, 0755, true);
        }
        $app->useStoragePath($this->isolatedStorage);
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            // Replace EVERY connection before providers boot; never use a real database or migrations runner.
            $sqlite = [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ];
            $connections = array_fill_keys(array_keys($app['config']->get('database.connections', [])), $sqlite);
            $connections['sqlite'] = $sqlite;
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections' => $connections,
                'cache.default' => 'array',
                'session.driver' => 'array',
                'logging.default' => 'null',
                'queue.default' => 'sync',
                'portal.strict_attachments' => false,
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (config('database.connections') as $connection) {
            $this->assertSame('sqlite', $connection['driver']);
            $this->assertSame(':memory:', $connection['database']);
        }
        Storage::fake('public');
        Queue::fake();
        Process::fake()->preventStrayProcesses();
        $this->originalBytes = $this->imageBytes('upload.png', 48, 64);
        $this->enhancedBytes = $this->imageBytes('enhanced.jpg', 60, 80);
        $this->documents = app(DocumentEnhancementService::class);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if (isset($this->isolatedStorage)) {
                (new Filesystem)->deleteDirectory($this->isolatedStorage);
            }
        }
    }

    private function createSchema(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('person_identity_number');
            $table->string('stored_file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash')->nullable();
            $table->timestamps();
        });
        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->string('pref');
            $table->string('description');
            $table->string('file_type')->nullable();
        });
        DB::table('document_types')->insert([
            ['id' => 7, 'pref' => 'identity', 'description' => 'Identity', 'file_type' => 'document'],
            ['id' => 8, 'pref' => 'photo', 'description' => 'Photo', 'file_type' => 'image'],
            ['id' => 9, 'pref' => '7', 'description' => 'Numeric photo preference', 'file_type' => 'image'],
            ['id' => 10, 'pref' => 'personal', 'description' => 'Personal photo', 'file_type' => 'image'],
            ['id' => 11, 'pref' => 'unclassified', 'description' => 'Unclassified', 'file_type' => null],
            ['id' => 12, 'pref' => '13', 'description' => 'Numeric document preference', 'file_type' => 'document'],
            ['id' => 13, 'pref' => 'other-photo', 'description' => 'Other photo', 'file_type' => 'image'],
        ]);
        $this->migration()->up();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_20_000001_add_document_enhancement_to_attachments.php');
    }

    private function imageBytes(string $name, int $width, int $height): string
    {
        $image = UploadedFile::fake()->image($name, $width, $height);

        return file_get_contents($image->getRealPath());
    }

    private function attachment(array $attributes = []): Attachment
    {
        $name = 'upload-'.(++$this->fixtureNumber).'.png';
        $relative = 'uploads/123456/'.$name;
        Storage::disk('public')->put($relative, $this->originalBytes);

        return Attachment::create(array_replace([
            'person_identity_number' => '111111111',
            'stored_file_name' => $name,
            'file_path' => 'storage/'.$relative,
            'file_type' => 'identity',
            'file_size' => strlen($this->originalBytes),
            'file_hash' => hash('sha256', $this->originalBytes),
        ], $attributes));
    }

    private function queuedAttachment(): Attachment
    {
        $attachment = $this->attachment();
        $this->documents->enqueue([$attachment->id]);
        $this->assertSame('queued', $attachment->fresh()->document_enhancement_status);
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);

        return $attachment->refresh();
    }

    private function job(Attachment $attachment): EnhanceRegistrationDocument
    {
        return new EnhanceRegistrationDocument($attachment->id, $attachment->file_path);
    }

    private function metadata(array $overrides = []): array
    {
        return array_replace([
            'status' => 'enhanced', 'operations' => [], 'warnings' => [], 'width' => 60, 'height' => 80,
        ], $overrides);
    }

    private function argument(PendingProcess $process, string $flag): string
    {
        $this->assertIsArray($process->command, 'Pass process arguments without a shell command string.');
        $index = array_search($flag, $process->command, true);
        $this->assertNotFalse($index, 'Missing process argument '.$flag);
        $this->assertArrayHasKey($index + 1, $process->command);

        return $process->command[$index + 1];
    }

    private function fakeProcess(string $stdout, ?string $outputBytes, int $exitCode = 0, ?Closure $during = null): void
    {
        Process::fake(function (PendingProcess $process) use ($stdout, $outputBytes, $exitCode, $during): ProcessResult {
            $output = $this->argument($process, '--output');
            $this->processOutputs[] = $output;
            $this->assertStringStartsWith(storage_path('app/document-enhancement/'), $output);
            $this->assertDirectoryExists(dirname($output));
            if ($outputBytes !== null) {
                file_put_contents($output, $outputBytes);
            }
            if ($during !== null) {
                $during($process);
            }

            return Process::result(output: $stdout, errorOutput: $exitCode ? 'Simulated process failure' : '', exitCode: $exitCode);
        })->preventStrayProcesses();
    }

    private function fakeEnhanced(?Closure $during = null): void
    {
        $this->fakeProcess(json_encode($this->metadata(), JSON_THROW_ON_ERROR), $this->enhancedBytes, during: $during);
    }

    private function assertOriginalFields(Attachment $attachment, array $before): void
    {
        $this->assertSame($before, $attachment->fresh()->only(self::FILE_COLUMNS));
    }

    private function assertWorkRemoved(): void
    {
        foreach ($this->processOutputs as $output) {
            $this->assertFileDoesNotExist($output);
            $this->assertDirectoryDoesNotExist(dirname($output));
        }
        $root = storage_path('app/document-enhancement');
        if (is_dir($root)) {
            $this->assertSame([], (new Filesystem)->allFiles($root));
            $this->assertSame([], (new Filesystem)->directories($root));
        }
    }

    public static function classifications(): iterable
    {
        yield 'document preference, not numeric ID' => ['identity', true];
        yield 'image preference' => ['photo', false];
        yield 'personal photo' => ['personal', false];
        yield 'unknown preference' => ['unknown', false];
        yield 'null preference' => [null, false];
        yield 'empty preference' => ['', false];
        yield 'missing classification' => ['unclassified', false];
        yield 'classification is not itself a preference' => ['document', false];
        yield 'numeric ID of a document row' => ['7', true];
        yield 'image ID collides with document preference' => ['13', true];
        yield 'admin document ID without preference' => ['12', true];
        yield 'admin image ID' => ['8', false];
        yield 'admin unclassified ID' => ['11', false];
        yield 'missing admin ID' => ['999', false];
    }

    #[DataProvider('classifications')]
    public function test_only_document_preferences_are_queued(?string $type, bool $eligible): void
    {
        $attachment = $this->attachment(['file_type' => $type]);
        $this->documents->enqueue([$attachment->id]);
        $this->assertSame($eligible ? 'queued' : null, $attachment->fresh()->document_enhancement_status);
        if ($eligible) {
            Queue::assertPushed(EnhanceRegistrationDocument::class, fn ($job) => $job->attachmentId === $attachment->id);
        } else {
            Queue::assertNothingPushed();
        }
        Process::assertNothingRan();
    }

    public static function unsafePaths(): iterable
    {
        foreach ([
            'https://example.test/storage/uploads/123456/a.png',
            '/storage/uploads/123456/a.png',
            'uploads/123456/a.png',
            'C:/uploads/123456/a.png',
            'storage/temp_uploads/123456/a.png',
            'storage/photos/123456/a.png',
            'storage/uploads/a.png',
            'storage/uploads/12345/a.png',
            'storage/uploads/1234567/a.png',
            'storage/uploads/123456/subfolder/a.png',
            'storage/uploads/123456/../../outside.png',
            'storage/uploads/123456/../123456/a.png',
            'storage/uploads/123456/%2e%2e%2fa.png',
            'storage/uploads/123456/a.png?download=1',
            'storage\\uploads\\123456\\a.png',
            '',
        ] as $path) {
            yield $path ?: 'empty' => [$path];
        }
    }

    #[DataProvider('unsafePaths')]
    public function test_sources_outside_known_local_upload_paths_are_not_queued(string $path): void
    {
        $attachment = $this->attachment(['file_path' => $path]);
        $this->documents->enqueue([$attachment->id]);
        $this->assertNull($attachment->fresh()->document_enhancement_status);
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_existing_files_in_temporary_or_unrecognized_directories_are_not_queued(): void
    {
        foreach (['temp_uploads/123456/a.png', 'photos/123456/a.png', 'uploads/123456/nested/a.png'] as $path) {
            Storage::disk('public')->put($path, $this->originalBytes);
            $attachment = $this->attachment(['file_path' => 'storage/'.$path]);
            $this->documents->enqueue([$attachment->id]);
            $this->assertNull($attachment->fresh()->document_enhancement_status);
            $this->assertSame($this->originalBytes, Storage::disk('public')->get($path));
        }
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_missing_source_and_directory_instead_of_file_are_not_queued(): void
    {
        Storage::disk('public')->makeDirectory('uploads/123456/directory.png');
        foreach (['missing.png', 'directory.png'] as $name) {
            $attachment = $this->attachment(['file_path' => 'storage/uploads/123456/'.$name]);
            $this->documents->enqueue([$attachment->id]);
            $this->assertNull($attachment->fresh()->document_enhancement_status);
        }
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_enqueue_claims_once_and_uses_database_documents_queue_even_when_default_is_sync(): void
    {
        $this->assertSame('sync', config('queue.default'));
        $attachment = $this->attachment();
        $before = $attachment->fresh()->only(self::FILE_COLUMNS);
        $this->documents->enqueue([$attachment->id, $attachment->id, 99999]);
        $this->documents->enqueue([$attachment->id]);
        $this->documents->enqueue([]);
        Queue::assertPushedOn('documents', EnhanceRegistrationDocument::class, function ($job) use ($attachment): bool {
            return $job->connection === 'database'
                && $job->queue === 'documents'
                && $job->attachmentId === $attachment->id
                && $job->sourcePath === $attachment->file_path;
        });
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);
        $this->assertSame('queued', $attachment->fresh()->document_enhancement_status);
        $this->assertOriginalFields($attachment, $before);
        Process::assertNothingRan();
    }

    public static function alreadyClaimedStates(): iterable
    {
        foreach (['queued', 'processing', 'enhanced', 'unchanged', 'failed'] as $status) {
            yield $status => [['document_enhancement_status' => $status]];
        }
    }

    #[DataProvider('alreadyClaimedStates')]
    public function test_existing_claim_cannot_be_queued_again(array $state): void
    {
        $attachment = $this->attachment($state);
        $before = $attachment->fresh()->getAttributes();
        $this->documents->enqueue([$attachment->id]);
        $this->assertSame($before, $attachment->fresh()->getAttributes());
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public static function missingSchema(): iterable
    {
        foreach (self::METADATA_COLUMNS as $column) {
            yield $column => ['attachments', $column];
        }
        yield 'size column' => ['attachments', 'file_size'];
        yield 'classification preference' => ['document_types', 'pref'];
        yield 'classification type' => ['document_types', 'file_type'];
        yield 'attachments table' => ['attachments', null];
        yield 'document types table' => ['document_types', null];
    }

    #[DataProvider('missingSchema')]
    public function test_unavailable_schema_safely_disables_enqueue_processing_and_failure_updates(string $table, ?string $column): void
    {
        $attachment = $this->attachment(['document_enhancement_status' => 'queued']);
        $job = $this->job($attachment);
        if ($column === null) {
            Schema::drop($table);
        } else {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
        }
        $before = Schema::hasTable('attachments') ? DB::table('attachments')->first() : null;
        $this->documents->enqueue([$attachment->id]);
        $job->handle($this->documents);
        $job->failed(new RuntimeException('Simulated worker failure'));
        if ($before !== null) {
            $this->assertEquals($before, DB::table('attachments')->first());
        }
        Queue::assertNothingPushed();
        Process::assertNothingRan();
        $this->assertSame($this->originalBytes, Storage::disk('public')->get(substr($attachment->file_path, 8)));
    }

    public function test_enqueue_waits_for_outer_commit_and_claims_duplicate_callbacks_only_once(): void
    {
        DB::beginTransaction();
        $attachment = $this->attachment();
        DB::beginTransaction();
        $this->documents->enqueue([$attachment->id]);
        $this->documents->enqueue([$attachment->id]);
        $this->assertNull($attachment->fresh()->document_enhancement_status);
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertNothingPushed();
        $this->assertNull($attachment->fresh()->document_enhancement_status);
        DB::commit();
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);
        $this->assertSame('queued', $attachment->fresh()->document_enhancement_status);
        $this->assertSame(0, DB::transactionLevel());
        Process::assertNothingRan();
    }

    public function test_rolled_back_registration_never_dispatches_or_leaves_a_claim(): void
    {
        $existing = $this->attachment();
        DB::beginTransaction();
        $new = $this->attachment();
        $this->documents->enqueue([$existing->id, $new->id]);
        Queue::assertNothingPushed();
        DB::rollBack();
        DB::transaction(fn () => null); // A later commit must not revive discarded callbacks.
        $this->assertNull($new->fresh());
        $this->assertNull($existing->fresh()->document_enhancement_status);
        $this->assertSame(0, DB::transactionLevel());
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_rolled_back_nested_transaction_discards_its_callback(): void
    {
        $attachment = $this->attachment();
        DB::beginTransaction();
        DB::beginTransaction();
        $this->documents->enqueue([$attachment->id]);
        DB::rollBack();
        DB::commit();
        $this->assertNull($attachment->fresh()->document_enhancement_status);
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_success_replaces_upload_in_place_without_extra_files(): void
    {
        $attachment = $this->queuedAttachment();
        $job = $this->job($attachment);
        $sourcePath = $attachment->file_path;
        $sourceRelative = substr($sourcePath, 8);
        $hash = $attachment->file_hash;
        $before = $attachment->only(self::FILE_COLUMNS);
        $metadata = $this->metadata(['private_debug_path' => 'must not persist']);
        $this->fakeProcess(json_encode($metadata, JSON_THROW_ON_ERROR), $this->enhancedBytes, during: function (PendingProcess $process) use ($attachment, $before, $sourceRelative): void {
            $this->assertOriginalFields($attachment, $before);
            $this->assertSame('processing', $attachment->fresh()->document_enhancement_status);
            $this->assertSame(realpath(Storage::disk('public')->path($sourceRelative)), $this->argument($process, '--input'));
            $this->assertSame(base_path(), $process->path);
            $this->assertSame(90, $process->timeout);
            $this->assertSame('3200', $this->argument($process, '--max-side'));
            $this->assertSame('24000000', $this->argument($process, '--max-pixels'));
        });
        $job->handle($this->documents);
        $fresh = $attachment->fresh();
        $this->assertSame('enhanced', $fresh->document_enhancement_status);
        $this->assertSame($sourcePath, $fresh->file_path);
        $this->assertSame(basename($sourcePath), $fresh->stored_file_name);
        $this->assertSame($hash, $fresh->file_hash, 'The upload hash keeps identifying the original submission.');
        $this->assertSame(strlen($this->enhancedBytes), $fresh->file_size);
        $this->assertSame($this->enhancedBytes, Storage::disk('public')->get($sourceRelative));
        $this->assertSame('image/jpeg', getimagesize(Storage::disk('public')->path($sourceRelative))['mime']);
        $this->assertSame(
            $this->metadata(),
            array_diff_key($fresh->document_enhancement_metadata, array_flip(['replaced_sha256', 'replaced_size']))
        );
        $this->assertSame($hash, $fresh->document_enhancement_metadata['replaced_sha256']);
        $this->assertSame(strlen($this->originalBytes), $fresh->document_enhancement_metadata['replaced_size']);
        $this->assertCount(1, Storage::disk('public')->allFiles(), 'Enhancement must not leave a derivative file behind.');
        $published = $fresh->getAttributes();
        $job->handle($this->documents);
        $job->failed(new RuntimeException('Late failure from a stale delivery'));
        $this->documents->enqueue([$attachment->id]);
        $this->assertSame($published, $attachment->fresh()->getAttributes());
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);
        Process::assertRanTimes(fn () => true, 1);
        $this->assertWorkRemoved();
    }

    public static function unchangedResults(): iterable
    {
        yield 'low confidence' => ['low-confidence', false];
        yield 'unchanged with redundant temporary output' => ['no-improvement', true];
    }

    #[DataProvider('unchangedResults')]
    public function test_low_confidence_or_unchanged_result_keeps_original(string $reason, bool $writesOutput): void
    {
        $attachment = $this->queuedAttachment();
        $before = $attachment->only(self::FILE_COLUMNS);
        $metadata = $this->metadata(['status' => 'unchanged', 'reason' => $reason, 'warnings' => ['orientation-uncertain']]);
        $this->fakeProcess(json_encode($metadata, JSON_THROW_ON_ERROR), $writesOutput ? $this->enhancedBytes : null);
        $job = $this->job($attachment);
        $job->handle($this->documents);
        $this->assertOriginalFields($attachment, $before);
        $this->assertSame('unchanged', $attachment->fresh()->document_enhancement_status);
        $this->assertSame($metadata, $attachment->fresh()->document_enhancement_metadata);
        $this->assertSame($this->originalBytes, Storage::disk('public')->get(substr($attachment->file_path, 8)));
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $job->handle($this->documents);
        $job->failed(null);
        $this->assertSame('unchanged', $attachment->fresh()->document_enhancement_status);
        Process::assertRanTimes(fn () => true, 1);
        $this->assertWorkRemoved();
    }

    public static function invalidResults(): iterable
    {
        foreach (['invalid-json', 'scalar-json', 'missing-status', 'unknown-status', 'missing-output', 'empty-output', 'non-image', 'png-output', 'oversized-bytes', 'oversized-pixels', 'nonzero-exit', 'process-exception'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('invalidResults')]
    public function test_invalid_or_failed_process_never_replaces_original_and_terminal_failure_is_recorded(string $case): void
    {
        $attachment = $this->queuedAttachment();
        $before = $attachment->only(self::FILE_COLUMNS);
        $files = Storage::disk('public')->allFiles();
        $stdout = json_encode($this->metadata(), JSON_THROW_ON_ERROR);
        $bytes = $this->enhancedBytes;
        $exitCode = 0;
        $during = null;
        switch ($case) {
            case 'invalid-json': $stdout = '{broken'; break;
            case 'scalar-json': $stdout = '"enhanced"'; break;
            case 'missing-status': $stdout = '{}'; break;
            case 'unknown-status': $stdout = '{"status":"success"}'; break;
            case 'missing-output': $bytes = null; break;
            case 'empty-output': $bytes = ''; break;
            case 'non-image': $bytes = 'not a JPEG'; break;
            case 'png-output': $bytes = $this->originalBytes; break;
            case 'oversized-bytes': $bytes .= str_repeat('x', 5 * 1024 * 1024); break;
            case 'oversized-pixels':
                // Change only JPEG SOF dimensions: no allocation of a huge decoded image.
                $sof = strpos($bytes, "\xFF\xC0");
                $this->assertNotFalse($sof);
                $bytes = substr_replace($bytes, pack('nn', 6000, 6000), $sof + 5, 4);
                $this->assertSame(6000, getimagesizefromstring($bytes)[0]);
                break;
            case 'nonzero-exit': $exitCode = 1; break;
            case 'process-exception':
                $during = static function (): void { throw new RuntimeException('Simulated process timeout'); };
                break;
        }
        $this->fakeProcess($stdout, $bytes, $exitCode, $during);
        $job = $this->job($attachment);
        $failure = null;
        try {
            $job->handle($this->documents);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(RuntimeException::class, $failure, 'Invalid output must fail the background job.');
        $this->assertSame('processing', $attachment->fresh()->document_enhancement_status, 'Keep the claim available for worker retries.');
        $this->assertOriginalFields($attachment, $before);
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertWorkRemoved();
        $job->failed($failure);
        $this->assertSame('failed', $attachment->fresh()->document_enhancement_status);
        $this->assertSame(['reason' => 'processing-failed'], $attachment->fresh()->document_enhancement_metadata);
        $this->assertOriginalFields($attachment, $before);
        $this->assertSame($this->originalBytes, Storage::disk('public')->get(substr($attachment->file_path, 8)));
        $job->failed($failure);
        $job->handle($this->documents);
        $this->documents->enqueue([$attachment->id]);
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);
        $this->assertCount(1, $this->processOutputs);
    }

    public function test_worker_can_retry_a_processing_claim_after_transient_failure(): void
    {
        $attachment = $this->queuedAttachment();
        $job = $this->job($attachment);
        $this->fakeProcess('not-json', $this->enhancedBytes);
        try {
            $job->handle($this->documents);
            $this->fail('Expected a retryable worker exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('processing', $attachment->fresh()->document_enhancement_status);
        }
        $this->assertWorkRemoved();
        $this->fakeEnhanced();
        $job->handle($this->documents);
        $this->assertSame('enhanced', $attachment->fresh()->document_enhancement_status);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertCount(2, $this->processOutputs);
        $this->assertWorkRemoved();
    }

    public static function staleChanges(): iterable
    {
        yield 'deleted attachment' => ['deleted'];
        yield 'replaced path' => ['path'];
        yield 'changed classification' => ['type'];
        yield 'terminal status from another worker' => ['status'];
    }

    #[DataProvider('staleChanges')]
    public function test_stale_attachment_cannot_publish_and_discards_its_output(string $change): void
    {
        $attachment = $this->queuedAttachment();
        $job = $this->job($attachment);
        $expected = null;
        $files = [];
        $this->fakeEnhanced(function () use ($attachment, $change, &$expected, &$files): void {
            $query = DB::table('attachments')->where('id', $attachment->id);
            if ($change === 'deleted') {
                $query->delete();
            } elseif ($change === 'path') {
                Storage::disk('public')->put('uploads/123456/replacement.png', 'newly uploaded bytes');
                $query->update(['file_path' => 'storage/uploads/123456/replacement.png', 'stored_file_name' => 'replacement.png']);
            } elseif ($change === 'type') {
                $query->update(['file_type' => 'photo']);
            } elseif ($change === 'status') {
                $query->update(['document_enhancement_status' => 'unchanged']);
            }
            $expected = $attachment->fresh()?->getAttributes();
            $files = Storage::disk('public')->allFiles();
        });
        $job->handle($this->documents);
        if ($change === 'type') {
            $this->assertSame('unchanged', $attachment->fresh()->document_enhancement_status);
            $this->assertSame(['reason' => 'classification-changed'], $attachment->fresh()->document_enhancement_metadata);
        } else {
            $this->assertSame($expected, $attachment->fresh()?->getAttributes());
        }
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertSame($this->originalBytes, Storage::disk('public')->get(substr($attachment->file_path, 8)));
        Process::assertRanTimes(fn () => true, 1);
        $this->assertWorkRemoved();
        if (in_array($change, ['deleted', 'path'], true)) {
            $job->failed(new RuntimeException('Stale job failed later'));
            $this->assertSame($expected, $attachment->fresh()?->getAttributes());
        }
    }

    public function test_source_bytes_changed_in_place_during_processing_cannot_publish(): void
    {
        $attachment = $this->queuedAttachment();
        $before = $attachment->only(self::FILE_COLUMNS);
        $relative = substr($attachment->file_path, 8);
        $changed = str_repeat('x', strlen($this->originalBytes));
        $this->fakeEnhanced(function () use ($relative, $changed): void {
            Storage::disk('public')->put($relative, $changed);
        });
        $this->job($attachment)->handle($this->documents);
        $this->assertOriginalFields($attachment, $before);
        $this->assertSame('unchanged', $attachment->fresh()->document_enhancement_status);
        $this->assertSame(['reason' => 'source-changed'], $attachment->fresh()->document_enhancement_metadata);
        $this->assertSame($changed, Storage::disk('public')->get($relative));
        $this->assertSame([$relative], Storage::disk('public')->allFiles());
        $this->assertWorkRemoved();
    }

    public function test_source_removed_during_processing_cannot_publish(): void
    {
        $attachment = $this->queuedAttachment();
        $before = $attachment->only(self::FILE_COLUMNS);
        $this->fakeEnhanced(function () use ($attachment): void {
            Storage::disk('public')->delete(substr($attachment->file_path, 8));
        });
        $this->job($attachment)->handle($this->documents);
        $this->assertOriginalFields($attachment, $before);
        $this->assertSame(['reason' => 'source-changed'], $attachment->fresh()->document_enhancement_metadata);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertWorkRemoved();
    }

    public function test_job_rechecks_document_classification_and_file_existence_before_running(): void
    {
        $first = $this->queuedAttachment();
        DB::table('document_types')->where('pref', 'identity')->update(['file_type' => 'image']);
        $this->job($first)->handle($this->documents);
        $this->assertSame('unchanged', $first->fresh()->document_enhancement_status);
        DB::table('document_types')->where('pref', 'identity')->update(['file_type' => 'document']);
        $second = $this->attachment(['document_enhancement_status' => 'queued']);
        Storage::disk('public')->delete(substr($second->file_path, 8));
        $this->job($second)->handle($this->documents);
        $this->assertSame('unchanged', $second->fresh()->document_enhancement_status);
        foreach ([$first, $second] as $attachment) {
            $this->assertSame(['reason' => 'ineligible-source'], $attachment->fresh()->document_enhancement_metadata);
        }
        Process::assertNothingRan();
    }

    public function test_enqueue_failure_is_logged_and_does_not_undo_saved_registration_attachment(): void
    {
        $attachment = $this->attachment();
        $before = $attachment->fresh()->only(self::FILE_COLUMNS);
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
        Log::shouldReceive('warning')->once()->with('document-enhancement: enqueue failed', [
            'attachment_id' => $attachment->id, 'exception' => RuntimeException::class,
        ]);
        $this->documents->enqueue([$attachment->id]);
        $this->assertSame('failed', $attachment->fresh()->document_enhancement_status);
        $this->assertSame(['reason' => 'processing-failed'], $attachment->fresh()->document_enhancement_metadata);
        $this->assertOriginalFields($attachment, $before);
        $this->assertSame($this->originalBytes, Storage::disk('public')->get(substr($attachment->file_path, 8)));
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_terminal_failure_callback_logs_status_write_failure_without_throwing(): void
    {
        $attachment = $this->queuedAttachment();
        $job = $this->job($attachment);
        $documents = \Mockery::mock(DocumentEnhancementService::class);
        $documents->shouldReceive('fail')->once()->with($attachment->id, $attachment->file_path)
            ->andThrow(new RuntimeException('Status database unavailable'));
        $this->app->instance(DocumentEnhancementService::class, $documents);
        Log::shouldReceive('warning')->once()->with('document-enhancement: failure status unavailable', [
            'attachment_id' => $attachment->id, 'exception' => RuntimeException::class,
        ]);
        $job->failed(new RuntimeException('Worker exhausted retries'));
        $this->assertSame('queued', $attachment->fresh()->document_enhancement_status);
        Process::assertNothingRan();
    }

    public function test_repeated_migration_up_preserves_existing_metadata_and_repairs_partial_schema(): void
    {
        $attachment = $this->attachment([
            'document_enhancement_status' => 'unchanged',
            'document_enhancement_metadata' => ['reason' => 'low-confidence'],
        ]);
        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame('unchanged', $attachment->fresh()->document_enhancement_status);
        $this->assertSame(['reason' => 'low-confidence'], $attachment->fresh()->document_enhancement_metadata);
        Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('document_enhancement_metadata'));
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumns('attachments', self::METADATA_COLUMNS));
        $this->assertSame('unchanged', $attachment->fresh()->document_enhancement_status);
    }

    public function test_repeated_migration_down_is_safe_and_preserves_uploads(): void
    {
        $attachment = $this->attachment(['document_enhancement_status' => 'failed']);
        $before = $attachment->fresh()->only(['file_path', 'stored_file_name', 'file_size', 'file_hash']);
        $this->migration()->down();
        $this->migration()->down();
        foreach (self::METADATA_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('attachments', $column));
        }
        $this->assertSame($before, $attachment->fresh()->only(array_keys($before)));
        $this->assertSame($this->originalBytes, Storage::disk('public')->get(substr($attachment->file_path, 8)));
        $this->migration()->up();
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumns('attachments', self::METADATA_COLUMNS));
        $this->assertNull($attachment->fresh()->document_enhancement_status);
    }

    public function test_real_python_pipeline_enhances_in_place_when_osd_is_unavailable(): void
    {
        $python = getenv('DOCUMENT_ENHANCEMENT_TEST_PYTHON');
        if (!$python) {
            $this->markTestSkipped('Set DOCUMENT_ENHANCEMENT_TEST_PYTHON to an environment with the document image dependencies.');
        }
        $image = imagecreatetruecolor(780, 1000);
        for ($x = 0; $x < 780; $x++) {
            $shade = (int) round(248 * (0.72 + 0.28 * $x / 779));
            imageline($image, $x, 0, $x, 999, imagecolorallocate($image, $shade, $shade, $shade));
        }
        $ink = imagecolorallocate($image, 30, 30, 30);
        for ($y = 150; $y < 870; $y += 48) {
            imagestring($image, 5, 65, $y, 'REGISTRATION DOCUMENT 0123456789', $ink);
        }
        ob_start();
        imagepng($image);
        $this->originalBytes = ob_get_clean();
        imagedestroy($image);
        $attachment = $this->queuedAttachment();
        $source = $attachment->file_path;
        $storedName = $attachment->stored_file_name;
        $hash = $attachment->file_hash;
        config([
            'document_enhancement.python_binary' => $python,
            'document_enhancement.tesseract_binary' => 'qoder-unavailable-osd-test',
        ]);
        Process::swap(new \Illuminate\Process\Factory);
        $this->job($attachment)->handle($this->documents);
        $attachment->refresh();
        $this->assertSame('enhanced', $attachment->document_enhancement_status);
        $this->assertSame($source, $attachment->file_path);
        $this->assertSame($storedName, $attachment->stored_file_name);
        $this->assertSame($hash, $attachment->file_hash);
        $replaced = Storage::disk('public')->get(substr($source, 8));
        $this->assertNotSame($this->originalBytes, $replaced);
        $this->assertSame("\xFF\xD8\xFF", substr($replaced, 0, 3));
        $this->assertSame(strlen($replaced), $attachment->file_size);
        $this->assertSame(hash('sha256', $this->originalBytes), $attachment->document_enhancement_metadata['replaced_sha256']);
        $this->assertSame(strlen($this->originalBytes), $attachment->document_enhancement_metadata['replaced_size']);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertContains('illumination_normalization', $attachment->document_enhancement_metadata['operations']);
        $this->assertContains('osd_unavailable', $attachment->document_enhancement_metadata['warnings']);
        $this->assertSame('image/jpeg', Storage::disk('public')->mimeType(substr($attachment->file_path, 8)));
    }
}
