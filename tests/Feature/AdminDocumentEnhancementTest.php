<?php

namespace Tests\Feature;

use App\Jobs\EnhanceRegistrationDocument;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminDocumentEnhancementTest extends TestCase
{
    private const PERMISSION = 'تعديل سجل';

    private string $originalBytes;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'queue.default' => 'sync',
        ]);
        DB::purge('sqlite');
        $this->createSchema();
        Storage::fake('public');
        Queue::fake();
        $image = imagecreatetruecolor(48, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 250, 250));
        ob_start();
        imagepng($image);
        $this->originalBytes = ob_get_clean();
        imagedestroy($image);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        foreach (['permissions', 'roles'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->string('guard_name');
                $blueprint->timestamps();
                $blueprint->unique(['name', 'guard_name']);
            });
        }
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_type', 'model_id']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_type', 'model_id']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
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
            ['id' => 12, 'pref' => '13', 'description' => 'Numeric document preference', 'file_type' => 'document'],
        ]);
        (require database_path('migrations/2026_11_20_000001_add_document_enhancement_to_attachments.php'))->up();
    }

    private function user(string $role, bool $canEnhance): User
    {
        $user = User::create([
            'name' => $role,
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => 'secret',
            'role' => $role,
        ]);
        if ($canEnhance) {
            DB::table('permissions')->updateOrInsert(
                ['name' => self::PERMISSION, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->givePermissionTo(self::PERMISSION);
        } else {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $user;
    }

    private function attachment(string $fileType = 'identity', string $extension = 'png'): Attachment
    {
        $name = 'document-'.uniqid().'.'.$extension;
        $relative = 'uploads/123456/'.$name;
        Storage::disk('public')->put($relative, $this->originalBytes);

        return Attachment::create([
            'person_identity_number' => '111111111',
            'stored_file_name' => $name,
            'file_path' => 'storage/'.$relative,
            'file_type' => $fileType,
            'file_size' => strlen($this->originalBytes),
            'file_hash' => hash('sha256', $this->originalBytes),
        ]);
    }

    public function test_enhancement_endpoints_require_admin_role_and_edit_permission(): void
    {
        $attachment = $this->attachment();
        $enhance = route('admin.records-management.enhance-attachment', $attachment->id);
        $status = route('admin.records-management.enhancement-status', $attachment->id);

        $this->postJson($enhance)->assertUnauthorized();
        $this->getJson($status)->assertUnauthorized();

        $this->actingAs($this->user('user', true))->postJson($enhance)->assertRedirect(route('user.dashboard'));
        $this->actingAs($this->user('admin', false))->postJson($enhance)->assertForbidden();
        $unauthorized = $this->user('admin', false);
        $this->actingAs($unauthorized)->getJson($status)->assertForbidden();
        $this->assertFalse($unauthorized->checkPermissionTo(self::PERMISSION));
        Queue::assertNothingPushed();
        $this->assertNull($attachment->fresh()->document_enhancement_status);
    }

    public function test_authorized_admin_queues_documents_only(): void
    {
        $admin = $this->user('admin', true);
        $document = $this->attachment();
        $pdf = $this->attachment('identity', 'pdf');
        $photo = $this->attachment('photo');

        $this->actingAs($admin)->postJson(route('admin.records-management.enhance-attachment', $document->id))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'queued']);
        $this->assertSame('queued', $document->fresh()->document_enhancement_status);
        Queue::assertPushedOn('documents', EnhanceRegistrationDocument::class, fn ($job) => $job->attachmentId === $document->id);

        $this->actingAs($admin)->postJson(route('admin.records-management.enhance-attachment', $pdf->id))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'status' => 'not-allowed']);
        $this->actingAs($admin)->postJson(route('admin.records-management.enhance-attachment', $photo->id))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'status' => 'not-document']);
        $this->actingAs($admin)->postJson(route('admin.records-management.enhance-attachment', 99999))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'status' => 'missing']);

        $this->assertNull($pdf->fresh()->document_enhancement_status);
        $this->assertNull($photo->fresh()->document_enhancement_status);
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);
    }

    public function test_duplicate_request_during_queue_or_processing_returns_success_without_dispatching_again(): void
    {
        $admin = $this->user('admin', true);
        $attachment = $this->attachment();
        $url = route('admin.records-management.enhance-attachment', $attachment->id);

        $this->actingAs($admin)->postJson($url)->assertOk();
        $this->actingAs($admin)->postJson($url)
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'queued']);
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);

        $attachment->forceFill(['document_enhancement_status' => 'processing'])->save();
        $this->actingAs($admin)->postJson($url)
            ->assertOk()
            ->assertJson(['status' => 'processing']);
        Queue::assertPushed(EnhanceRegistrationDocument::class, 1);
    }

    public function test_repeat_requests_are_allowed_after_a_terminal_status_and_keep_the_same_file(): void
    {
        $admin = $this->user('admin', true);
        $attachment = $this->attachment('12');
        $url = route('admin.records-management.enhance-attachment', $attachment->id);

        foreach (['failed', 'unchanged', 'enhanced'] as $index => $terminal) {
            $attachment->forceFill([
                'document_enhancement_status' => $terminal,
                'document_enhancement_metadata' => ['reason' => $terminal],
            ])->save();
            $this->actingAs($admin)->postJson($url)->assertOk()->assertJson(['status' => 'queued']);
            Queue::assertPushed(EnhanceRegistrationDocument::class, $index + 1);
            $this->assertSame($attachment->file_path, $attachment->fresh()->file_path);
            $this->assertSame($attachment->stored_file_name, $attachment->fresh()->stored_file_name);
        }
    }

    public function test_status_endpoint_reports_file_url_and_version(): void
    {
        $admin = $this->user('admin', true);
        $attachment = $this->attachment();
        $attachment->forceFill([
            'document_enhancement_status' => 'enhanced',
            'document_enhancement_metadata' => ['status' => 'enhanced', 'reason' => null, 'operations' => ['deskew']],
        ])->save();

        $this->actingAs($admin)
            ->getJson(route('admin.records-management.enhancement-status', $attachment->id))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'enhanced',
                'operations' => ['deskew'],
                'file_name' => $attachment->stored_file_name,
                'file_url' => route('admin.file.show', ['filename' => $attachment->stored_file_name]),
            ])
            ->assertJsonStructure(['version']);

        $this->actingAs($admin)
            ->getJson(route('admin.records-management.enhancement-status', 99999))
            ->assertStatus(404)
            ->assertJson(['success' => false, 'status' => 'missing']);
    }
}
