<?php

namespace Tests\Feature;

use App\Http\Controllers\Users\GeneralRegistrationController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class PublicRegistrationValidationTest extends TestCase
{
    private GeneralRegistrationController $controller;

    private string $isolatedStorage;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $this->isolatedStorage = sys_get_temp_dir().'/public-registration-test-'.bin2hex(random_bytes(8));
        foreach (['framework/views', 'framework/cache', 'framework/sessions', 'logs'] as $directory) {
            (new Filesystem)->makeDirectory($this->isolatedStorage.'/'.$directory, 0755, true);
        }
        $app->useStoragePath($this->isolatedStorage);
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            // عزل جميع الاتصالات قبل إقلاع المزوّدات يمنع الوصول إلى قواعد البيانات الحقيقية.
            $sqlite = [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
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
        Storage::fake('public');
        $this->controller = new GeneralRegistrationController;
        $this->createLookupSchema();
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

    private function createLookupSchema(): void
    {
        foreach (['category_of_relations', 'ci_personal_cd', 'displacement_statuses', 'provinces', 'health_statuses', 'death_reasons'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
            });
            DB::table($name)->insert([['id' => 1], ['id' => 2]]);
        }
        Schema::create('general_category', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->boolean('status');
        });
        DB::table('general_category')->insert([
            ['id' => 1, 'status' => 1],
            ['id' => 2, 'status' => 1],
            ['id' => 3, 'status' => 0],
        ]);
        Schema::create('city', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('province_id');
        });
        DB::table('city')->insert([['id' => 1, 'province_id' => 1], ['id' => 2, 'province_id' => 2]]);
        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->string('pref');
            $table->string('description');
            foreach (['basic', 'family', 'deceased'] as $context) {
                $table->boolean($context.'_enabled')->default(false);
                $table->boolean($context.'_required')->default(false);
            }
        });
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('person_identity_number');
            $table->string('record_number')->nullable();
            $table->string('file_type');
            $table->string('file_path');
        });
        DB::table('document_types')->insert([
            'pref' => 'identity', 'description' => 'وثيقة الهوية',
            'basic_enabled' => 1, 'basic_required' => 1,
            'family_enabled' => 1, 'family_required' => 1,
        ]);
        DB::table('document_types')->insert([
            'pref' => 'death', 'description' => 'شهادة الوفاة',
            'deceased_enabled' => 1, 'deceased_required' => 1,
        ]);
    }

    private function createPromotionSchema(): void
    {
        foreach ([new \App\Models\Data, new \App\Models\RePeople, new \App\Models\DeadPepole,
            new \App\Models\AdditionalDeceased, new \App\Models\GuardianBankAccount,
            new \App\Models\PortalGeneralRegistrationFieldValue] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model): void {
                $table->id();
                foreach (array_diff($model->getFillable(), ['id', 'created_at', 'updated_at']) as $column) {
                    if (in_array($column, ['file_id_number', 'registration_id', 're_file_id'])) {
                        $table->unsignedBigInteger($column)->nullable();
                    } else {
                        $table->text($column)->nullable();
                    }
                }
                $table->timestamps();
            });
        }
        Schema::table('dead_people', function (Blueprint $table): void {
            $table->unsignedBigInteger('sponsorship_status')->nullable();
        });
        Schema::table('attachments', function (Blueprint $table): void {
            $table->string('stored_file_name')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('file_hash')->nullable();
            $table->timestamps();
        });
        Schema::create('sponsorships', function (Blueprint $table): void {
            $table->id();
            $table->string('identity_number')->nullable();
            $table->integer('internal_file_number')->nullable();
            $table->integer('relation_id_number')->nullable();
        });
        Schema::create('reserved_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('session_id');
            $table->timestamp('reserved_at');
            $table->boolean('used')->default(false);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('request_status', function (Blueprint $table): void {
            $table->id();
            $table->string('description');
        });
        DB::table('request_status')->insert([
            ['id' => 1, 'description' => 'جديد'], ['id' => 2, 'description' => 'تحديث'],
            ['id' => 3, 'description' => 'مراجعة'], ['id' => 4, 'description' => 'مقبول'],
        ]);
        (require database_path('migrations/2026_11_05_000000_create_member_transfer_requests_table.php'))->up();
        Storage::fake('local');
        DB::table('data')->insert([
            'file_id_number' => 111, 'data_id_number' => '999999999', 'data_first_name' => 'المعيل الأصلي',
            'data_number_of_individuals' => 5, 'data_request_status' => 4,
        ]);
        DB::table('re_people')->insert([
            'registration_id' => 111, 'person_id' => '111111111', 'first_name' => 'الاسم الأصلي',
            'person_note' => 'لا يغيّر قبل الموافقة', 'created_at' => '2020-01-01 00:00:00',
        ]);
    }

    private function promotionRequest(array $overrides = []): Request
    {
        $data = array_replace($this->validData(), $overrides);
        $data['attachments'] = [
            ['file_type' => 'identity', 'person_identity_number' => 'main'],
            ['file_type' => 'identity', 'person_identity_number' => 'family_0'],
        ];
        $request = $this->requestFor($data);
        $request->files->set('attachments', [
            ['file' => UploadedFile::fake()->image('guardian.jpg')],
            ['file' => UploadedFile::fake()->image('child.jpg')],
        ]);

        return $request;
    }

    private function queuePromotion(array $overrides = []): \App\Models\MemberTransferRequest
    {
        $response = $this->controller->store($this->promotionRequest($overrides));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertStringContainsString('guardian_promotion=pending', $response->getData(true)['redirect']);
        return \App\Models\MemberTransferRequest::where('identity_number', '111111111')->latest('id')->firstOrFail();
    }

    public function test_guardian_promotion_waits_without_changing_any_live_records(): void
    {
        $this->createPromotionSchema();
        $before = DB::table('re_people')->first();
        $transfer = $this->queuePromotion();
        $this->assertTrue($transfer->isGuardianPromotion());
        $this->assertNull($transfer->data_id);
        $this->assertSame('pending', $transfer->status);
        $this->assertSame(111, $transfer->source_file_id_number);
        $this->assertEquals($before, DB::table('re_people')->first());
        $this->assertSame(1, DB::table('data')->count());
        foreach (['users', 'attachments', 'guardian_bank_accounts', 'dead_people', 'portal_general_registration_field_values'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertCount(2, $transfer->member_snapshot['files']);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertFileExists($transfer->promotionAttachmentPath(0));
        $this->assertArrayNotHasKey('_token', $transfer->member_snapshot['registration']);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_promotion_approval_saves_registration_and_removes_only_the_source_membership(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->createPromotionSchema();
        $source = DB::table('data')->first();
        $member = DB::table('re_people')->first();
        $transfer = $this->queuePromotion();
        $this->travel(1)->day();
        $approvedAt = now();
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $guardian = \App\Models\Data::where('data_id_number', '111111111')->firstOrFail();
        $this->assertSame('فاطمة', $guardian->data_first_name);
        $this->assertEquals(123456, $guardian->file_id_number);
        $this->assertEquals($approvedAt, $guardian->created_at);
        $this->assertEquals($approvedAt, $guardian->updated_at);
        $this->assertEquals($source, DB::table('data')->where('id', $source->id)->first());
        $this->assertSame(0, DB::table('re_people')->where('person_id', '111111111')->count());
        $this->assertDatabaseHas('re_people', ['person_id' => '222222222', 'registration_id' => 123456]);
        $this->assertDatabaseHas('users', ['email' => '111111111']);
        $this->assertSame(2, DB::table('attachments')->count());
        foreach (DB::table('attachments')->get() as $attachment) {
            Storage::disk('public')->assertExists(substr($attachment->file_path, strlen('storage/')));
        }
        $transfer->refresh();
        $this->assertSame('approved', $transfer->status);
        $this->assertSame($guardian->id, $transfer->data_id);
        $this->assertEquals(77, $transfer->reviewed_by);
        $this->assertSame($member->person_note, $transfer->member_snapshot['source_member']['person_note']);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_rejection_changes_only_the_transfer_request(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        $before = DB::table('re_people')->first();
        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->reject(
            Request::create('/', 'POST', ['reason' => 'رفض التحويل']), $transfer
        );
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('rejected', $transfer->fresh()->status);
        $this->assertEquals($before, DB::table('re_people')->first());
        $this->assertSame(1, DB::table('data')->count());
        $this->assertSame(0, DB::table('users')->count());
        $this->expectException(\RuntimeException::class);
        $this->controller->approveGuardianPromotion($transfer->fresh(), 77);
    }

    public function test_repeated_registration_does_not_duplicate_or_replace_a_pending_promotion(): void
    {
        $this->createPromotionSchema();
        $first = $this->queuePromotion();
        $second = $this->queuePromotion(['file_id_number' => '123457', 'data_first_name' => 'اسم آخر']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->member_snapshot, $second->member_snapshot);
        $this->assertSame(1, \App\Models\MemberTransferRequest::count());
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_changed_source_or_duplicate_memberships_prevents_promotion(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        DB::table('re_people')->where('person_id', '111111111')->update(['registration_id' => 222]);
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame(1, DB::table('data')->count());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('re_people', ['person_id' => '111111111', 'registration_id' => 222]);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_failed_registration_write_rolls_back_promotion_and_prepared_attachments(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        $before = DB::table('re_people')->first();
        DB::unprepared("CREATE TRIGGER fail_user_insert BEFORE INSERT ON users BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertEquals($before, DB::table('re_people')->first());
        $this->assertSame(1, DB::table('data')->count());
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('attachments')->count());
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertFileExists($transfer->promotionAttachmentPath(0));
    }

    public function test_children_linked_elsewhere_get_separate_transfers_only_after_promotion_approval(): void
    {
        $this->createPromotionSchema();
        DB::table('re_people')->insert(['registration_id' => 111, 'person_id' => '222222222', 'first_name' => 'اسم الطفل الأصلي']);
        $transfer = $this->queuePromotion();
        $this->assertSame(1, \App\Models\MemberTransferRequest::count());
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $child = \App\Models\MemberTransferRequest::pending()->where('identity_number', '222222222')->firstOrFail();
        $this->assertFalse($child->isGuardianPromotion());
        $this->assertDatabaseHas('re_people', ['person_id' => '222222222', 'registration_id' => 111, 'first_name' => 'اسم الطفل الأصلي']);
        $this->assertDatabaseHas('data', ['data_id_number' => '111111111', 'data_request_status' => 3]);
        app(\App\Services\FamilyMemberTransferService::class)->approvePendingTransfer($child, 77);
        $this->assertDatabaseHas('re_people', ['person_id' => '222222222', 'registration_id' => 123456, 'first_name' => 'اسم الطفل الأصلي']);
    }

    private function createGuardianAsMemberFixture(): void
    {
        $this->createPromotionSchema();
        Schema::create('enhanced_attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('record_number');
            $table->string('person_identity_number')->nullable();
            $table->string('stored_file_name');
            $table->text('file_path');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        DB::table('data')->insert([
            'file_id_number' => 999,
            'data_id_number' => '222222222',
            'data_first_name' => 'علي',
            'data_request_status' => 4,
        ]);
        DB::table('re_people')->insert([
            'registration_id' => 999,
            'person_id' => '444444444',
            'first_name' => 'فرد ملف المصدر',
        ]);
    }

    private function queueGuardianAsMember(array $overrides = []): \App\Models\MemberTransferRequest
    {
        $response = $this->controller->store($this->promotionRequest(array_replace([
            'data_id_number' => '333333333',
            'family_members' => [[
                'person_id' => '222222222',
                'first_name' => 'علي',
                'last_name' => 'الأسرة',
                'person_birth_date' => '1980-02-03',
                'person_gender' => '1',
                'person_note' => 'بيانات الفرد المطلوب ضمّه',
            ]],
        ], $overrides)));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['success']);

        return \App\Models\MemberTransferRequest::where('identity_number', '222222222')->firstOrFail();
    }

    public function test_public_registration_queues_guardian_as_member_without_creating_membership(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();

        $this->assertTrue($transfer->isGuardianAsMember());
        $this->assertSame('pending', $transfer->status);
        $this->assertSame(999, $transfer->source_file_id_number);
        $this->assertSame(123456, $transfer->file_id_number);
        $this->assertSame('بيانات الفرد المطلوب ضمّه', $transfer->member_snapshot['person_note']);
        $this->assertDatabaseHas('data', ['file_id_number' => 123456, 'data_request_status' => 3]);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
        $this->assertDatabaseHas('data', ['file_id_number' => 999, 'data_id_number' => '222222222']);
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '444444444']);
    }

    public function test_public_registration_accepts_temp_original_page_token_without_persisting_it_as_file_number(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember([
            'original_file_id_number' => 'TEMP-ABC123',
        ]);

        $this->assertTrue($transfer->isGuardianAsMember());
        $this->assertSame(123456, $transfer->file_id_number);
        $this->assertDatabaseHas('data', ['file_id_number' => 123456, 'data_request_status' => 3]);
        $this->assertSame(0, DB::table('data')->where('file_id_number', 'TEMP-ABC123')->count());
    }

    public function test_public_registration_links_new_attachments_to_target_file_number(): void
    {
        $this->createGuardianAsMemberFixture();
        $this->queueGuardianAsMember();

        $this->assertSame(2, DB::table('attachments')->count());
        foreach (DB::table('attachments')->get() as $attachment) {
            $this->assertSame('123456', $attachment->record_number);
        }
    }

    public function test_guardian_as_member_approval_moves_source_family_and_deletes_source_guardian(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        $targetCounts = DB::table('data')->where('file_id_number', 123456)
            ->first(['data_number_of_individuals', 'data_number_female']);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertTrue($response->getData(true)['can_accept_file']);
        $this->assertDatabaseHas('re_people', [
            'registration_id' => 123456,
            'person_id' => '222222222',
            'first_name' => 'علي',
            'person_note' => 'بيانات الفرد المطلوب ضمّه',
        ]);
        $this->assertDatabaseHas('re_people', [
            'registration_id' => 123456,
            'person_id' => '444444444',
            'first_name' => 'فرد ملف المصدر',
        ]);
        $this->assertSame(1, DB::table('re_people')->where('person_id', '222222222')->count());
        $this->assertDatabaseMissing('data', ['file_id_number' => 999]);
        $this->assertSame(0, DB::table('re_people')->where('registration_id', 999)->count());
        $this->assertEquals($targetCounts, DB::table('data')->where('file_id_number', 123456)
            ->first(['data_number_of_individuals', 'data_number_female']));
        $this->assertSame('approved', $transfer->fresh()->status);
    }

    public function test_guardian_as_member_approval_preserves_attachments_and_relinks_enhanced_records(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        foreach (['222222222', '444444444'] as $identity) {
            $path = 'uploads/999/'.$identity.'.jpg';
            Storage::disk('public')->put($path, 'attachment-'.$identity);
            DB::table('attachments')->insert([
                'person_identity_number' => $identity,
                'record_number' => '000999',
                'stored_file_name' => $identity.'.jpg',
                'file_path' => $path,
                'file_type' => 'identity',
            ]);
        }
        DB::table('enhanced_attachments')->insert([
            'record_number' => '000999',
            'person_identity_number' => '222222222',
            'stored_file_name' => '222222222.jpg',
            'file_path' => 'uploads/999/222222222.jpg',
        ]);
        $attachmentFields = ['id', 'person_identity_number', 'stored_file_name', 'file_path', 'file_type'];
        $attachmentsBefore = DB::table('attachments')->select($attachmentFields)->orderBy('id')->get();
        $sourceAttachmentIds = DB::table('attachments')->where('record_number', '000999')->pluck('id');

        app(\App\Services\FamilyMemberTransferService::class)->approveGuardianAsMember($transfer, 77);

        $this->assertEquals($attachmentsBefore, DB::table('attachments')->select($attachmentFields)->orderBy('id')->get());
        $this->assertSame(2, DB::table('attachments')
            ->where('record_number', '123456')
            ->whereIn('id', $sourceAttachmentIds)
            ->count());
        $this->assertTrue(Storage::disk('public')->exists('uploads/999/222222222.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/999/444444444.jpg'));
        $this->assertDatabaseHas('enhanced_attachments', [
            'record_number' => '123456',
            'person_identity_number' => '222222222',
            'file_path' => 'uploads/999/222222222.jpg',
        ]);
    }

    public function test_guardian_as_member_approval_relinks_enhanced_attachment_folder_fallbacks(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        Schema::table('enhanced_attachments', function (Blueprint $table): void {
            $table->dropColumn('record_number');
        });
        Schema::table('enhanced_attachments', function (Blueprint $table): void {
            $table->string('folder_id')->nullable();
            $table->string('original_folder_name')->nullable();
        });
        DB::table('enhanced_attachments')->insert([
            'folder_id' => '000999',
            'person_identity_number' => '222222222',
            'stored_file_name' => 'guardian.jpg',
            'file_path' => 'uploads/999/guardian.jpg',
        ]);
        DB::table('enhanced_attachments')->insert([
            'original_folder_name' => '999',
            'person_identity_number' => '444444444',
            'stored_file_name' => 'member.jpg',
            'file_path' => 'uploads/999/member.jpg',
        ]);

        app(\App\Services\FamilyMemberTransferService::class)->approveGuardianAsMember($transfer, 77);

        $this->assertDatabaseHas('enhanced_attachments', [
            'folder_id' => '123456',
            'person_identity_number' => '222222222',
            'file_path' => 'uploads/999/guardian.jpg',
        ]);
        $this->assertDatabaseHas('enhanced_attachments', [
            'original_folder_name' => '123456',
            'person_identity_number' => '444444444',
            'file_path' => 'uploads/999/member.jpg',
        ]);
    }

    public function test_guardian_as_member_approval_merges_duplicate_and_new_deceased_people(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('dead_people')->insert([
            're_file_id' => 123456,
            'father_id' => '555555555',
            'father_first_name' => 'متوفى موجود',
        ]);
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_first_name' => 'متوفى أب جديد',
            'mother_id' => '555555555',
            'mother_first_name' => 'متوفى مكرر',
        ]);
        DB::table('additional_deceased')->insert([
            're_file_id' => 999,
            'person_id' => '777777777',
            'first_name' => 'متوفى إضافي جديد',
        ]);
        foreach (['555555555', '666666666', '777777777'] as $identity) {
            $path = 'uploads/999/'.$identity.'.jpg';
            Storage::disk('public')->put($path, 'death-'.$identity);
            DB::table('attachments')->insert([
                'person_identity_number' => $identity,
                'record_number' => '999',
                'stored_file_name' => $identity.'.jpg',
                'file_path' => $path,
                'file_type' => 'death',
            ]);
        }
        $attachmentFields = ['id', 'person_identity_number', 'stored_file_name', 'file_path', 'file_type'];
        $attachmentsBefore = DB::table('attachments')->select($attachmentFields)->orderBy('id')->get();

        app(\App\Services\FamilyMemberTransferService::class)->approveGuardianAsMember($transfer, 77);

        $this->assertDatabaseMissing('data', ['file_id_number' => 999]);
        $this->assertSame(0, DB::table('dead_people')->where('re_file_id', 999)->count());
        $this->assertSame(1, DB::table('dead_people')->where('re_file_id', 123456)->count());
        $this->assertDatabaseHas('dead_people', ['re_file_id' => 123456, 'father_id' => '555555555']);
        $this->assertDatabaseHas('additional_deceased', [
            're_file_id' => 123456,
            'person_id' => '666666666',
            'relationship' => 'father',
        ]);
        $this->assertDatabaseHas('additional_deceased', [
            're_file_id' => 123456,
            'person_id' => '777777777',
        ]);
        $this->assertSame(0, DB::table('additional_deceased')->where('person_id', '555555555')->count());
        $this->assertEquals($attachmentsBefore, DB::table('attachments')->select($attachmentFields)->orderBy('id')->get());
        $this->assertSame(3, DB::table('attachments')
            ->where('record_number', '123456')
            ->whereIn('person_identity_number', ['555555555', '666666666', '777777777'])
            ->count());
        foreach (['555555555', '666666666', '777777777'] as $identity) {
            $this->assertTrue(Storage::disk('public')->exists('uploads/999/'.$identity.'.jpg'));
        }
    }

    public function test_guardian_as_member_approval_blocks_deceased_conversion_that_would_lose_metadata(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('dead_people')->insert([
            're_file_id' => 123456,
            'father_id' => '555555555',
            'father_first_name' => 'متوفى موجود',
        ]);
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_first_name' => 'متوفى جديد',
            'sponsorship_status' => 2,
        ]);
        Storage::disk('public')->put('uploads/999/666666666.jpg', 'death-certificate');
        DB::table('attachments')->insert([
            'person_identity_number' => '666666666',
            'record_number' => '999',
            'stored_file_name' => '666666666.jpg',
            'file_path' => 'uploads/999/666666666.jpg',
            'file_type' => 'death',
        ]);
        $tables = ['data', 're_people', 'dead_people', 'additional_deceased', 'attachments', 'member_transfer_requests'];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->orderBy('id')->get()->all(),
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        foreach ($before as $table => $rows) {
            $this->assertEquals($rows, DB::table($table)->orderBy('id')->get()->all(), $table);
        }
        $this->assertTrue(Storage::disk('public')->exists('uploads/999/666666666.jpg'));
    }

    public function test_guardian_as_member_approval_blocks_deceased_certificate_conversion_without_a_target_column(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('dead_people')->insert([
            're_file_id' => 123456,
            'father_id' => '555555555',
            'father_first_name' => 'متوفى موجود',
        ]);
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_first_name' => 'متوفى جديد',
            'father_death_certificate' => 'uploads/999/666666666.jpg',
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999, 'data_id_number' => '222222222']);
        $this->assertDatabaseHas('dead_people', [
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_death_certificate' => 'uploads/999/666666666.jpg',
        ]);
        $this->assertSame(0, DB::table('additional_deceased')->where('person_id', '666666666')->count());
    }

    public function test_guardian_as_member_approval_blocks_source_deceased_metadata_without_a_person(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'sponsorship_status' => 2,
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999]);
        $this->assertDatabaseHas('dead_people', ['re_file_id' => 999, 'sponsorship_status' => 2]);
    }

    public function test_guardian_as_member_approval_blocks_target_deceased_metadata_without_a_person(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('dead_people')->insert([
            're_file_id' => 123456,
            'sponsorship_status' => 2,
        ]);
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_first_name' => 'متوفى المصدر',
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999]);
        $this->assertDatabaseHas('dead_people', ['re_file_id' => 123456, 'sponsorship_status' => 2]);
        $this->assertDatabaseHas('dead_people', ['re_file_id' => 999, 'father_id' => '666666666']);
    }

    public function test_guardian_as_member_approval_blocks_opposite_role_deceased_metadata_from_being_lost(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('dead_people')->insert([
            're_file_id' => 123456,
            'father_id' => '555555555',
            'father_first_name' => 'متوفى الهدف',
        ]);
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_first_name' => 'متوفى المصدر',
            'mother_death_certificate' => 'uploads/999/mother-certificate.jpg',
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999]);
        $this->assertDatabaseHas('dead_people', [
            're_file_id' => 999,
            'mother_death_certificate' => 'uploads/999/mother-certificate.jpg',
        ]);
    }

    public function test_guardian_as_member_approval_preserves_additional_deceased_metadata_without_standard_person_fields(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        Schema::table('additional_deceased', function (Blueprint $table): void {
            $table->unsignedBigInteger('sponsorship_status')->nullable();
        });
        DB::table('additional_deceased')->insert([
            're_file_id' => 999,
            'sponsorship_status' => 2,
        ]);

        app(\App\Services\FamilyMemberTransferService::class)->approveGuardianAsMember($transfer, 77);

        $this->assertDatabaseMissing('additional_deceased', ['re_file_id' => 999]);
        $this->assertDatabaseHas('additional_deceased', ['re_file_id' => 123456, 'sponsorship_status' => 2]);
        $this->assertDatabaseMissing('data', ['file_id_number' => 999]);
    }

    public function test_guardian_as_member_approval_moves_first_real_primary_deceased_into_blank_target(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('dead_people')->insert(['re_file_id' => 123456]);
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_first_name' => 'الأب الحقيقي',
            'mother_id' => '0',
            'mother_first_name' => '   ',
        ]);

        app(\App\Services\FamilyMemberTransferService::class)->approveGuardianAsMember($transfer, 77);

        $this->assertSame(1, DB::table('dead_people')->where('re_file_id', 123456)->count());
        $this->assertDatabaseHas('dead_people', [
            're_file_id' => 123456,
            'father_id' => '666666666',
            'father_first_name' => 'الأب الحقيقي',
            'mother_id' => null,
        ]);
        $this->assertSame(0, DB::table('dead_people')->where('re_file_id', 999)->count());
    }

    public function test_guardian_as_member_approval_blocks_target_family_identity_collision_without_partial_changes(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('re_people')->insert([
            'registration_id' => 123456,
            'person_id' => '444444444',
            'first_name' => 'فرد موجود في الهدف',
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999, 'data_id_number' => '222222222']);
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '444444444']);
        $this->assertDatabaseHas('re_people', ['registration_id' => 123456, 'person_id' => '444444444']);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
    }

    public function test_guardian_as_member_approval_blocks_source_member_matching_target_guardian_identity(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('re_people')->where('registration_id', 999)->update(['person_id' => '333333333']);
        $sourceBefore = DB::table('data')->where('file_id_number', 999)->first();
        $targetBefore = DB::table('data')->where('file_id_number', 123456)->first();

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertEquals($sourceBefore, DB::table('data')->where('file_id_number', 999)->first());
        $this->assertEquals($targetBefore, DB::table('data')->where('file_id_number', 123456)->first());
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '333333333']);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
    }

    public function test_guardian_as_member_approval_rejects_a_replacement_target_row_with_the_same_file_number(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        $targetId = $transfer->data_id;
        DB::table('data')->where('id', $targetId)->delete();
        DB::table('data')->insert([
            'file_id_number' => 123456,
            'data_id_number' => '888888888',
            'data_first_name' => 'معيل آخر',
            'data_request_status' => 4,
        ]);
        $replacementBefore = DB::table('data')->where('file_id_number', 123456)->first();

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999, 'data_id_number' => '222222222']);
        $this->assertEquals($replacementBefore, DB::table('data')->where('file_id_number', 123456)->first());
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '444444444']);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
    }

    public function test_guardian_as_member_approval_blocks_duplicate_identities_within_source_family(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('re_people')->insert([
            'registration_id' => 999,
            'person_id' => '444444444',
            'first_name' => 'فرد مكرر في المصدر',
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertSame(2, DB::table('re_people')->where('registration_id', 999)->where('person_id', '444444444')->count());
        $this->assertSame(0, DB::table('re_people')->where('registration_id', 123456)->where('person_id', '444444444')->count());
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
    }

    public function test_guardian_as_member_approval_rejects_a_replacement_source_row_with_the_same_identity_and_file(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('data')->where('file_id_number', 999)->delete();
        DB::table('data')->insert([
            'file_id_number' => 999,
            'data_id_number' => '222222222',
            'data_first_name' => 'معيل بديل',
            'data_request_status' => 4,
        ]);
        $replacementBefore = DB::table('data')->where('file_id_number', 999)->first();

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertEquals($replacementBefore, DB::table('data')->where('file_id_number', 999)->first());
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '444444444']);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
    }

    public function test_guardian_as_member_approval_rolls_back_when_source_guardian_cannot_be_deleted(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        $sourceId = DB::table('data')->where('file_id_number', 999)->value('id');
        Schema::create('blocking_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('data_id')->constrained('data')->restrictOnDelete();
        });
        DB::table('blocking_references')->insert(['data_id' => $sourceId]);
        DB::table('dead_people')->insert([
            're_file_id' => 999,
            'father_id' => '666666666',
            'father_first_name' => 'متوفى المصدر',
        ]);
        DB::table('enhanced_attachments')->insert([
            'record_number' => '999',
            'person_identity_number' => '222222222',
            'stored_file_name' => 'guardian.jpg',
            'file_path' => 'uploads/999/guardian.jpg',
        ]);
        DB::table('attachments')->insert([
            'person_identity_number' => '222222222',
            'record_number' => '999',
            'stored_file_name' => 'guardian.jpg',
            'file_path' => 'uploads/999/guardian.jpg',
            'file_type' => 'identity',
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(500, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999, 'data_id_number' => '222222222']);
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '444444444']);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
        $this->assertDatabaseHas('dead_people', ['re_file_id' => 999, 'father_id' => '666666666']);
        $this->assertDatabaseHas('attachments', ['record_number' => '999', 'person_identity_number' => '222222222']);
        $this->assertDatabaseHas('enhanced_attachments', ['record_number' => '999']);
    }

    public function test_guardian_as_member_pending_count_failure_rolls_back_instead_of_reporting_a_false_success(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        $throwOnPendingCount = false;
        DB::listen(function (QueryExecuted $query) use (&$throwOnPendingCount): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'update "member_transfer_requests"')
                && in_array('approved', $query->bindings, true)) {
                $throwOnPendingCount = true;
            }
            if ($throwOnPendingCount
                && str_contains($sql, 'select count(*)')
                && str_contains($sql, 'member_transfer_requests')) {
                throw new QueryException('sqlite', $query->sql, $query->bindings, new \RuntimeException('forced pending-count failure'));
            }
        });

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );
        $throwOnPendingCount = false;

        $this->assertSame(500, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['file_id_number' => 999, 'data_id_number' => '222222222']);
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '444444444']);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
    }

    public function test_regular_transfer_pending_count_failure_rolls_back_instead_of_reporting_a_false_success(): void
    {
        $this->createPromotionSchema();
        DB::table('re_people')->insert(['registration_id' => 111, 'person_id' => '222222222', 'first_name' => 'اسم الطفل الأصلي']);
        $promotion = $this->queuePromotion();
        $response = $this->controller->approveGuardianPromotion($promotion, 77);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $transfer = \App\Models\MemberTransferRequest::pending()->where('identity_number', '222222222')->firstOrFail();
        $throwOnPendingCount = false;
        DB::listen(function (QueryExecuted $query) use (&$throwOnPendingCount): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'update "member_transfer_requests"')
                && in_array('approved', $query->bindings, true)) {
                $throwOnPendingCount = true;
            }
            if ($throwOnPendingCount
                && str_contains($sql, 'select count(*)')
                && str_contains($sql, 'member_transfer_requests')) {
                throw new QueryException('sqlite', $query->sql, $query->bindings, new \RuntimeException('forced pending-count failure'));
            }
        });

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );
        $throwOnPendingCount = false;

        $this->assertSame(500, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('re_people', ['registration_id' => 111, 'person_id' => '222222222']);
        $this->assertDatabaseMissing('re_people', ['registration_id' => 123456, 'person_id' => '222222222']);
    }

    public function test_guardian_as_member_rejection_does_not_change_either_file(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        $sourceBefore = DB::table('data')->where('file_id_number', 999)->first();

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->reject(
            Request::create('/', 'POST', ['reason' => 'رفض الضم']),
            $transfer
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertStringContainsString('لم يُضف', $response->getData(true)['message']);
        $this->assertSame('rejected', $transfer->fresh()->status);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
        $this->assertEquals($sourceBefore, DB::table('data')->where('file_id_number', 999)->first());
        $this->assertDatabaseHas('re_people', ['registration_id' => 999, 'person_id' => '444444444']);
    }

    public function test_guardian_as_member_approval_rejects_an_existing_membership(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('re_people')->insert([
            'registration_id' => 888,
            'person_id' => '222222222',
            'first_name' => 'ارتباط لاحق',
        ]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertSame(1, DB::table('re_people')->where('person_id', '222222222')->count());
        $this->assertDatabaseHas('re_people', ['registration_id' => 888, 'person_id' => '222222222']);
        $this->assertDatabaseHas('data', ['file_id_number' => 999, 'data_id_number' => '222222222']);
    }

    public function test_guardian_as_member_approval_rejects_a_changed_source_file(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        DB::table('data')->where('id', DB::table('data')->where('file_id_number', 999)->value('id'))->update(['file_id_number' => 1000]);

        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'),
            $transfer,
            app(\App\Services\FamilyMemberTransferService::class)
        );

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
        $this->assertDatabaseHas('data', ['file_id_number' => 1000, 'data_id_number' => '222222222']);
    }

    public function test_guardian_as_member_uses_id_ordered_locks_for_guardians_and_members(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(\App\Services\FamilyMemberTransferService::class)->approveGuardianAsMember($transfer, 77);

        foreach (['data', 're_people'] as $table) {
            $lockQueries = array_values(array_filter(
                $queries,
                fn (string $sql): bool => str_contains($sql, 'select * from "'.$table.'"')
                    && str_contains($sql, 'order by "id" asc')
            ));
            $this->assertCount(1, $lockQueries, $table);
            $this->assertSame([], array_values(array_filter(
                $queries,
                fn (string $sql): bool => str_contains($sql, 'select "id" from "'.$table.'"')
            )), $table.' IDs must be selected by the locking query');
        }
    }

    public function test_regular_transfer_service_cannot_execute_guardian_as_member(): void
    {
        $this->createGuardianAsMemberFixture();
        $transfer = $this->queueGuardianAsMember();

        $this->expectException(\RuntimeException::class);
        app(\App\Services\FamilyMemberTransferService::class)->approvePendingTransfer($transfer, 77);
    }

    public function test_duplicate_guardian_identity_cannot_be_queued_as_a_family_member(): void
    {
        $this->createGuardianAsMemberFixture();
        DB::table('data')->insert([
            'file_id_number' => 1000,
            'data_id_number' => '222222222',
            'data_first_name' => 'سجل معيل مكرر',
        ]);

        $response = $this->controller->store($this->promotionRequest([
            'data_id_number' => '333333333',
            'family_members' => [[
                'person_id' => '222222222',
                'first_name' => 'علي',
                'last_name' => 'الأسرة',
                'person_birth_date' => '1980-02-03',
                'person_gender' => '1',
            ]],
        ]));

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame(0, \App\Models\MemberTransferRequest::count());
        $this->assertSame(0, DB::table('data')->where('file_id_number', 123456)->count());
        $this->assertSame(0, DB::table('re_people')->where('person_id', '222222222')->count());
    }

    public function test_transfer_screen_distinguishes_guardian_as_member_requests(): void
    {
        $this->createGuardianAsMemberFixture();
        $this->queueGuardianAsMember();
        (require database_path('migrations/2025_05_01_034534_create_permission_tables.php'))->up();
        $user = \App\Models\User::create([
            'name' => 'مراجع',
            'email' => 'reviewer@example.test',
            'password' => bcrypt('test-only-password'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('عرض قسم إدارة التسجيلات', 'web'));
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('تعديل سجل', 'web'));

        $response = $this->get(route('admin.member-transfer-requests.index'));

        $response->assertOk();
        $response->assertSee('تحويل معيل إلى فرد أسرة');
        $response->assertSee('موافقة ونقل الأسرة');
        $response->assertSee('سيُنقل المعيل');
        $response->assertSee('وأسرته ووفياته إلى ملف');
        $response->assertSee('يُحذف ملف المعيل الأصلي');
        $response->assertSee('نعم، انقل الأسرة واحذف المصدر');
    }

    public function test_thank_you_page_explains_pending_guardian_as_member_requests(): void
    {
        $response = $this->get(route('user.thank.you.page', ['guardian_as_member' => 'pending']));

        $response->assertOk();
        $response->assertSee('ضمّ الفرد المسجّل كمعيل بانتظار موافقة الإدارة');
        $response->assertSee('عند الموافقة سيُنقل مع أسرته ووفياته');
    }

    public function test_missing_documents_still_reject_promotion_without_queuing(): void
    {
        $this->createPromotionSchema();
        $response = $this->controller->store($this->requestFor($this->validData()));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, \App\Models\MemberTransferRequest::count());
        $this->assertSame(1, DB::table('data')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_promotion_preserves_deceased_mother_bank_and_additional_deceased_submission(): void
    {
        $this->createPromotionSchema();
        $data = $this->motherData(false);
        $data['additional_deceased'] = [$this->additionalDeceased()];
        $data['bank_accounts'] = [['bank_name' => '1', 're_guardian_name' => 'فاطمة', 'person_owner_identity_number' => '111111111']];
        $data['attachments'] = [];
        $files = [];
        foreach (['main' => 'identity', 'family_0' => 'identity', 'deceased_father' => 'death', 'deceased_mother' => 'death', 'additional_deceased_0' => 'death'] as $person => $type) {
            $data['attachments'][] = ['person_identity_number' => $person, 'file_type' => $type];
            $files[] = ['file' => UploadedFile::fake()->image($person . '.jpg')];
        }
        $request = $this->requestFor($data);
        $request->files->set('attachments', $files);
        $response = $this->controller->store($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $transfer = \App\Models\MemberTransferRequest::firstOrFail();
        $this->assertSame(0, DB::table('dead_people')->count());
        $this->assertSame(0, DB::table('guardian_bank_accounts')->count());
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseHas('dead_people', ['re_file_id' => 123456, 'father_id' => '333333333', 'mother_id' => '444444444']);
        $this->assertDatabaseHas('additional_deceased', ['re_file_id' => 123456, 'person_id' => '555555555']);
        $this->assertDatabaseHas('guardian_bank_accounts', ['guardian_registration' => 123456, 'person_owner_identity_number' => '111111111']);
        $this->assertSame(5, DB::table('attachments')->count());
    }

    public function test_chunk_uploads_are_copied_privately_and_survive_temporary_file_cleanup(): void
    {
        $this->createPromotionSchema();
        config(['filesystems.disks.public.root' => storage_path('app/public')]);
        Storage::forgetDisk('public');
        $request = $this->promotionRequest();
        $files = $request->files->get('attachments');
        $attachments = $request->input('attachments');
        foreach ($files as $index => $entry) {
            $path = 'temp_uploads/123456/document' . $index . '.jpg';
            Storage::disk('public')->put($path, file_get_contents($entry['file']->getRealPath()));
            $attachments[$index]['temp_path'] = $path;
        }
        $request->merge(['attachments' => $attachments]);
        $request->files->replace([]);
        $response = $this->controller->store($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        Storage::disk('public')->deleteDirectory('temp_uploads');
        $transfer = \App\Models\MemberTransferRequest::firstOrFail();
        $this->assertArrayNotHasKey('temp_path', $transfer->member_snapshot['registration']['attachments'][0]);
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame(2, DB::table('attachments')->count());
    }

    public function test_missing_snapshot_image_blocks_approval_without_changing_records(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        Storage::disk('local')->delete($transfer->member_snapshot['files'][0]['path']);
        $response = (new \App\Http\Controllers\Admin\MemberTransferRequestController)->approve(
            Request::create('/', 'POST'), $transfer, app(\App\Services\FamilyMemberTransferService::class)
        );
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertSame(1, DB::table('data')->count());
        $this->assertDatabaseHas('re_people', ['person_id' => '111111111', 'registration_id' => 111]);
    }

    public function test_approval_cannot_run_twice_even_with_a_stale_transfer_model(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $response = $this->controller->approveGuardianPromotion($transfer, 78);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(2, DB::table('data')->count());
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(2, DB::table('attachments')->count());
        $this->assertEquals(77, $transfer->fresh()->reviewed_by);
    }

    public function test_existing_guardian_created_after_queuing_blocks_promotion(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        DB::table('data')->insert(['data_id_number' => '111111111', 'file_id_number' => 333, 'data_first_name' => 'ملف مستقل']);
        $response = $this->controller->approveGuardianPromotion($transfer, 77);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertDatabaseHas('data', ['data_id_number' => '111111111', 'data_first_name' => 'ملف مستقل']);
        $this->assertDatabaseHas('re_people', ['person_id' => '111111111', 'registration_id' => 111]);
    }

    public function test_posted_flags_cannot_bypass_administrative_approval(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion([
            'promotion' => true, 'approved' => true, 'reviewer_id' => 77,
            'request_type' => 'guardian_promotion', 'data_request_status' => 4,
        ]);
        $this->assertSame('pending', $transfer->status);
        $this->assertSame(1, DB::table('data')->count());
        $this->assertDatabaseHas('re_people', ['person_id' => '111111111', 'registration_id' => 111]);
    }

    public function test_rejected_promotion_can_be_resubmitted_as_a_new_pending_request(): void
    {
        $this->createPromotionSchema();
        $old = $this->queuePromotion();
        (new \App\Http\Controllers\Admin\MemberTransferRequestController)->reject(Request::create('/', 'POST'), $old);
        $new = $this->queuePromotion(['file_id_number' => '123457']);
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('rejected', $old->fresh()->status);
        $this->assertSame('pending', $new->status);
        $this->assertSame(1, DB::table('data')->count());
    }

    public function test_pending_promotion_reserves_its_file_number_against_other_identities(): void
    {
        $this->createPromotionSchema();
        $this->queuePromotion();
        $this->assertSame('member_transfer_requests', $this->invoke('findFileNumberConflict', ['123456', '333333333']));
        $this->assertNull($this->invoke('findFileNumberConflict', ['123456', '111111111']));
    }

    public function test_promotion_approval_rejection_and_private_attachments_enforce_permissions(): void
    {
        $this->createPromotionSchema();
        (require database_path('migrations/2025_05_01_034534_create_permission_tables.php'))->up();
        $transfer = $this->queuePromotion();
        $user = \App\Models\User::create(['name' => 'مراجع', 'email' => 'reviewer@example.test', 'password' => bcrypt('test-only-password'), 'role' => 'admin']);
        $this->actingAs($user);
        $approve = route('admin.member-transfer-requests.approve', $transfer);
        $reject = route('admin.member-transfer-requests.reject', $transfer);
        $attachment = route('admin.member-transfer-requests.attachment', [$transfer, 0]);
        $this->postJson($approve)->assertForbidden();
        $this->postJson($reject)->assertForbidden();
        $this->getJson($attachment)->assertForbidden();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('عرض قسم إدارة التسجيلات', 'web'));
        $this->get($attachment)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson($approve)->assertForbidden();
        $this->postJson($reject)->assertForbidden();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('تعديل سجل', 'web'));
        $this->postJson($approve)->assertOk()->assertJson(['success' => true]);
        $this->postJson($approve)->assertStatus(422);
        $this->postJson($reject)->assertStatus(422);
        $this->assertSame('approved', $transfer->fresh()->status);
    }

    public function test_private_snapshot_paths_cannot_escape_the_pending_upload_directory(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        $snapshot = $transfer->member_snapshot;
        $snapshot['files'][0]['path'] = '../../.env';
        $transfer->member_snapshot = $snapshot;
        $this->expectException(\RuntimeException::class);
        $transfer->promotionAttachmentPath(0);
    }

    public function test_regular_transfer_service_cannot_execute_a_guardian_promotion(): void
    {
        $this->createPromotionSchema();
        $transfer = $this->queuePromotion();
        $this->expectException(\RuntimeException::class);
        app(\App\Services\FamilyMemberTransferService::class)->approvePendingTransfer($transfer, 77);
    }

    private function validData(): array
    {
        return [
            'file_id_number' => '123456',
            'data_section_id' => '2',
            'data_id_number' => '111111111',
            'data_first_name' => 'فاطمة',
            'data_father_name' => 'أحمد',
            'data_grand_father_name' => 'محمد',
            'data_family_name' => 'الأسرة',
            'data_relationship' => '1',
            'data_birth_date' => '1985-01-02',
            'data_gender' => '2',
            'data_phone_number' => '0591234567',
            'data_marital_status' => '1',
            'data_displacement_status' => '1',
            'data_current_address' => 'شارع المدرسة',
            'data_city' => '1',
            'data_province' => '1',
            'data_health_status' => '1',
            'family_members' => [[
                'person_id' => '222222222',
                'first_name' => 'علي',
                'last_name' => 'الأسرة',
                'person_birth_date' => '2015-02-03',
                'person_gender' => '1',
            ]],
        ];
    }

    private function orphanData(): array
    {
        return array_replace($this->validData(), [
            'data_section_id' => '1',
            'father_id' => '333333333',
            'father_first_name' => 'حسن',
            'father_last_name' => 'الأسرة',
            'father_death_date' => '2020-01-02',
            'father_death_reason' => '1',
        ]);
    }

    private function motherData(bool $alive): array
    {
        $data = array_replace($this->orphanData(), [
            'data_relationship' => '2',
            'mother_is_alive' => $alive ? '1' : '0',
            'mother_id' => '444444444',
            'mother_first_name' => 'مريم',
            'mother_second_name' => 'أحمد',
            'mother_third_name' => 'محمد',
            'mother_last_name' => 'الأسرة',
        ]);
        if (!$alive) {
            $data = array_replace($data, [
                'deceased_mother_id' => '444444444',
                'deceased_mother_first_name' => 'مريم',
                'deceased_mother_last_name' => 'الأسرة',
                'mother_death_date' => '2021-02-03',
                'mother_death_reason' => '1',
            ]);
        }

        return $data;
    }

    private function additionalDeceased(string $identity = '555555555'): array
    {
        return [
            'id_number' => $identity,
            'first_name' => 'عمر',
            'last_name' => 'الأسرة',
            'relationship' => 'brother',
            'death_date' => '2021-01-02',
            'death_reason' => '1',
        ];
    }

    private function requestFor(array $data): Request
    {
        $request = Request::create('/general-registration', 'POST', $data);
        $request->headers->set('Accept', 'application/json');
        $route = (new Route('POST', 'general-registration', [GeneralRegistrationController::class, 'store']))
            ->name('store.generalRegistration');
        $request->setRouteResolver(static fn () => $route);

        return $request;
    }

    private function invoke(string $method, array $arguments = []): mixed
    {
        return (new ReflectionMethod($this->controller, $method))->invokeArgs($this->controller, $arguments);
    }

    private function assertInvalid(callable $operation, array $fields): array
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $errors, json_encode($errors, JSON_UNESCAPED_UNICODE));
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', implode(' ', $errors[$field]));
            }

            return $errors;
        }
        $this->fail('Expected validation rejection for '.implode(', ', $fields));
    }

    public function test_complete_mother_guardian_fixture_is_valid_without_separate_mother_or_father_fields(): void
    {
        $this->assertNull($this->invoke('validatePublicRegistration', [$this->requestFor($this->validData())]));
    }

    public static function requiredBasicFields(): iterable
    {
        foreach ([
            'data_section_id', 'data_id_number', 'data_first_name', 'data_father_name',
            'data_grand_father_name', 'data_family_name', 'data_relationship', 'data_birth_date',
            'data_gender', 'data_phone_number', 'data_marital_status', 'data_displacement_status',
            'data_current_address', 'data_city', 'data_province', 'data_health_status',
        ] as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('requiredBasicFields')]
    public function test_basic_fields_cannot_be_missing_or_whitespace(string $field): void
    {
        foreach ([null, " \t\n "] as $value) {
            $data = $this->validData();
            if ($value === null) {
                unset($data[$field]);
            } else {
                $data[$field] = $value;
            }
            $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$field]);
        }
    }

    public static function malformedFields(): iterable
    {
        yield 'short identity' => ['data_id_number', '123'];
        yield 'nonnumeric identity' => ['data_id_number', '12345678x'];
        yield 'array identity' => ['data_id_number', ['111111111']];
        yield 'array family identity' => ['family_members.0.person_id', ['222222222']];
        yield 'array mother identity' => ['mother_id', ['444444444']];
        yield 'array father identity' => ['father_id', ['333333333']];
        yield 'array deceased mother identity' => ['deceased_mother_id', ['444444444']];
        yield 'array additional identity' => ['additional_deceased.0.id_number', ['555555555']];
        yield 'array name' => ['data_first_name', ['اسم']];
        yield 'long name' => ['data_family_name', str_repeat('س', 31)];
        yield 'invalid phone prefix' => ['data_phone_number', '0491234567'];
        yield 'invalid phone length' => ['data_phone_number', '059123456'];
        yield 'invalid alternate phone' => ['data_alt_phone_number', 'invalid'];
        yield 'invalid birth date' => ['data_birth_date', '2020-02-30'];
        yield 'future guardian birth' => ['data_birth_date', '2999-01-01'];
        yield 'invalid gender' => ['data_gender', '3'];
        yield 'inactive section' => ['data_section_id', '3'];
        yield 'unknown section' => ['data_section_id', '999'];
        yield 'unknown relation' => ['data_relationship', '999'];
        yield 'unknown marital status' => ['data_marital_status', '999'];
        yield 'unknown displacement status' => ['data_displacement_status', '999'];
        yield 'unknown health status' => ['data_health_status', '999'];
        yield 'unknown city' => ['data_city', '999'];
        yield 'unknown province' => ['data_province', '999'];
        yield 'city province mismatch' => ['data_city', '2'];
        yield 'array family list' => ['family_members', 'not-an-array'];
        yield 'missing family list' => ['family_members', null];
        yield 'empty family list' => ['family_members', []];
        yield 'scalar family row' => ['family_members.0', 'not-an-array'];
        yield 'empty family row' => ['family_members.0', []];
        yield 'array family name' => ['family_members.0.first_name', ['اسم']];
        yield 'malformed family identity' => ['family_members.0.person_id', 'bad'];
        yield 'future member birth' => ['family_members.0.person_birth_date', '2999-01-01'];
        yield 'invalid member birth' => ['family_members.0.person_birth_date', '31/02/2020'];
        yield 'invalid member gender' => ['family_members.0.person_gender', '3'];
        yield 'unknown member health' => ['family_members.0.person_health_status', '999'];
        yield 'scalar additional list' => ['additional_deceased', 'bad'];
        yield 'scalar additional row' => ['additional_deceased.0', 'bad'];
        yield 'scalar attachments' => ['attachments', 'bad'];
        yield 'scalar attachment row' => ['attachments.0', 'bad'];
        yield 'array attachment type' => ['attachments.0.file_type', ['identity']];
    }

    #[DataProvider('malformedFields')]
    public function test_malformed_values_are_rejected_as_validation_errors(string $field, mixed $value): void
    {
        $data = $this->validData();
        Arr::set($data, $field, $value);
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$field]);
    }

    public function test_nonnumeric_family_and_additional_indexes_are_rejected(): void
    {
        foreach (['family_members', 'additional_deceased'] as $key) {
            foreach (['invalid', -1, '1.5'] as $index) {
                $data = $this->orphanData();
                $data[$key] = [$index => $key === 'family_members' ? $data['family_members'][0] : $this->additionalDeceased()];
                $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$key]);
            }
        }
    }

    public function test_sparse_numeric_family_indexes_are_preserved(): void
    {
        $data = $this->validData();
        $data['family_members'] = [7 => $data['family_members'][0]];
        unset($data['family_members'][7]['person_id']);
        $request = $this->requestFor($data);
        $this->assertNull($this->invoke('validatePublicRegistration', [$request]));
        $groups = $this->invoke('publicDocumentGroups', [$request, '123456']);
        $this->assertSame('912345607', $groups['family_7']['identity']);
        $this->assertArrayNotHasKey('family_0', $groups);
    }

    public static function requiredFamilyFields(): iterable
    {
        foreach (['first_name', 'last_name', 'person_birth_date', 'person_gender'] as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('requiredFamilyFields')]
    public function test_each_family_member_requires_its_own_fields(string $field): void
    {
        $data = $this->validData();
        $data['family_members'][1] = array_replace($data['family_members'][0], ['person_id' => '666666666']);
        unset($data['family_members'][1][$field]);
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['family_members.1.'.$field]);
        $data['family_members'][1][$field] = '   ';
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['family_members.1.'.$field]);
    }

    public function test_optional_family_identity_and_middle_names_remain_optional_for_multiple_members(): void
    {
        foreach ([null, ''] as $empty) {
            $data = $this->validData();
            $data['family_members'][0]['person_id'] = $empty;
            $data['family_members'][0]['second_name'] = $empty;
            $data['family_members'][0]['third_name'] = $empty;
            $data['family_members'][1] = $data['family_members'][0];
            $this->assertNull($this->invoke('validatePublicRegistration', [$this->requestFor($data)]));
            $groups = $this->invoke('publicDocumentGroups', [$this->requestFor($data), '123456']);
            $this->assertSame('912345600', $groups['family_0']['identity']);
            $this->assertSame('912345601', $groups['family_1']['identity']);
        }
        unset($data['family_members'][0]['person_id'], $data['family_members'][0]['second_name'], $data['family_members'][0]['third_name']);
        $this->assertNull($this->invoke('validatePublicRegistration', [$this->requestFor($data)]));
    }

    public function test_father_is_required_only_in_section_one(): void
    {
        $this->assertNull($this->invoke('validatePublicRegistration', [$this->requestFor($this->validData())]));
        $this->assertNull($this->invoke('validatePublicRegistration', [$this->requestFor($this->orphanData())]));
        foreach (['father_id', 'father_first_name', 'father_last_name', 'father_death_date', 'father_death_reason'] as $field) {
            $data = $this->orphanData();
            unset($data[$field]);
            $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$field]);
        }
    }

    public function test_other_guardians_require_mother_identity_names_and_living_status(): void
    {
        foreach (['mother_id', 'mother_first_name', 'mother_second_name', 'mother_third_name', 'mother_last_name', 'mother_is_alive'] as $field) {
            $data = $this->motherData(true);
            unset($data[$field]);
            $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$field]);
        }
        $this->assertNull($this->invoke('validatePublicRegistration', [$this->requestFor($this->motherData(true))]));
    }

    public function test_deceased_mother_alias_can_share_identity_but_must_match(): void
    {
        $data = $this->motherData(false);
        $this->assertNull($this->invoke('validatePublicRegistration', [$this->requestFor($data)]));
        $data['deceased_mother_id'] = '777777777';
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['deceased_mother_id']);
    }

    public function test_living_mother_and_mother_guardian_cannot_have_deceased_mother_details(): void
    {
        foreach (['1', '2'] as $relationship) {
            $data = $this->motherData(false);
            $data['data_relationship'] = $relationship;
            $data['mother_is_alive'] = '1';
            $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['mother_is_alive']);
        }
    }

    public function test_numeric_living_mother_status_cannot_bypass_deceased_details_conflict(): void
    {
        $data = $this->motherData(false);
        $data['mother_is_alive'] = 1;
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['mother_is_alive']);
    }

    public function test_numeric_deceased_mother_status_still_requires_deceased_details(): void
    {
        $data = $this->motherData(true);
        $data['mother_is_alive'] = 0;
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [
            'deceased_mother_id', 'deceased_mother_first_name', 'deceased_mother_last_name', 'mother_death_date', 'mother_death_reason',
        ]);
    }

    public function test_deceased_mother_requires_her_own_deceased_fields(): void
    {
        foreach (['deceased_mother_id', 'deceased_mother_first_name', 'deceased_mother_last_name', 'mother_death_date', 'mother_death_reason'] as $field) {
            $data = $this->motherData(false);
            unset($data[$field]);
            $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$field]);
        }
    }

    public function test_future_death_dates_and_unknown_death_reasons_are_rejected(): void
    {
        foreach (['father_death_date', 'mother_death_date', 'additional_deceased.0.death_date', 'father_death_reason', 'mother_death_reason', 'additional_deceased.0.death_reason'] as $field) {
            $data = $this->motherData(false);
            $data['additional_deceased'] = [$this->additionalDeceased()];
            Arr::set($data, $field, str_ends_with($field, 'date') ? '2999-01-01' : '999');
            $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$field]);
        }
    }

    public function test_additional_deceased_requires_complete_fields_and_orphan_section(): void
    {
        foreach (['id_number', 'first_name', 'last_name', 'relationship', 'death_date', 'death_reason'] as $field) {
            $data = $this->orphanData();
            $data['additional_deceased'] = [$this->additionalDeceased()];
            unset($data['additional_deceased'][0][$field]);
            $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['additional_deceased.0.'.$field]);
        }
        $data = $this->validData();
        $data['additional_deceased'] = [$this->additionalDeceased()];
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['additional_deceased']);
        $data['data_section_id'] = '1';
        $data = array_replace($this->orphanData(), $data);
        $data['additional_deceased'][0]['relationship'] = 'invalid';
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['additional_deceased.0.relationship']);
    }

    public static function duplicateRoles(): iterable
    {
        yield 'guardian member' => ['data_id_number', 'family_members.0.person_id'];
        yield 'father member' => ['father_id', 'family_members.0.person_id'];
        yield 'mother member' => ['mother_id', 'family_members.0.person_id'];
        yield 'guardian father' => ['data_id_number', 'father_id'];
        yield 'family repeated' => ['family_members.0.person_id', 'family_members.1.person_id'];
        yield 'additional repeated' => ['additional_deceased.0.id_number', 'additional_deceased.1.id_number'];
        yield 'additional member' => ['additional_deceased.0.id_number', 'family_members.0.person_id'];
        yield 'father additional' => ['father_id', 'additional_deceased.0.id_number'];
    }

    #[DataProvider('duplicateRoles')]
    public function test_identity_cannot_be_reused_across_roles_or_repeated_cards(string $first, string $second): void
    {
        $data = $this->motherData(true);
        $data['family_members'][1] = array_replace($data['family_members'][0], ['person_id' => '666666666']);
        $data['additional_deceased'] = [$this->additionalDeceased(), $this->additionalDeceased('777777777')];
        Arr::set($data, $second, Arr::get($data, $first));
        $errors = $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), [$first, $second]);
        $this->assertStringContainsString('مكرر', $errors[$second][0]);
    }

    public function test_deceased_mother_alias_does_not_allow_a_duplicate_family_identity(): void
    {
        $data = $this->motherData(false);
        $data['family_members'][0]['person_id'] = $data['mother_id'];
        $this->assertInvalid(fn () => $this->invoke('validatePublicRegistration', [$this->requestFor($data)]), ['mother_id', 'family_members.0.person_id']);
    }

    public function test_missing_documents_are_reported_per_person_and_type_including_additional_deceased(): void
    {
        DB::table('document_types')->insert([
            'pref' => 'photo', 'description' => 'صورة شخصية', 'basic_enabled' => 1, 'basic_required' => 1,
        ]);
        $data = $this->motherData(false);
        $data['additional_deceased'] = [$this->additionalDeceased(), $this->additionalDeceased('777777777')];
        $request = $this->requestFor($data);
        $missing = $this->invoke('missingPublicDocuments', [$request, '123456', []]);
        $pairs = array_map(static fn (array $row) => $row['person_identity_number'].':'.$row['file_type'], $missing);
        $this->assertEqualsCanonicalizing([
            '111111111:identity', '111111111:photo', '222222222:identity',
            '333333333:death', '444444444:death', '555555555:death', '777777777:death',
        ], $pairs);
        foreach ($missing as $row) {
            $this->assertSame('missing_required_document', $row['reason']);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $row['message']);
        }
        $missing = $this->invoke('missingPublicDocuments', [$request, '123456', ['111111111' => ['identity' => true]]]);
        $this->assertCount(6, $missing);
        $this->assertContains('photo', array_column($missing, 'file_type'));
        $this->assertContains('222222222', array_column($missing, 'person_identity_number'));
    }

    public function test_living_mother_has_no_deceased_document_group_or_requirements(): void
    {
        $request = $this->requestFor($this->motherData(true));
        $groups = $this->invoke('publicDocumentGroups', [$request, '123456']);
        $this->assertArrayNotHasKey('deceased_mother', $groups);
        $missing = $this->invoke('missingPublicDocuments', [$request, '123456', []]);
        $this->assertCount(3, $missing);
        $this->assertNotContains('444444444', array_column($missing, 'person_identity_number'));
    }

    public function test_disabled_required_document_flags_and_unknown_placeholder_are_ignored(): void
    {
        DB::table('document_types')->update(['basic_enabled' => 0, 'family_enabled' => 0, 'deceased_enabled' => 0]);
        DB::table('document_types')->insert([
            'pref' => 'unknown', 'description' => 'Unknown',
            'basic_enabled' => 1, 'basic_required' => 1, 'family_enabled' => 1, 'family_required' => 1,
            'deceased_enabled' => 1, 'deceased_required' => 1,
        ]);
        $this->assertSame([], $this->invoke('missingPublicDocuments', [$this->requestFor($this->motherData(false)), '123456', []]));
    }

    public function test_missing_documents_block_source_validation_even_when_legacy_strict_flag_is_false(): void
    {
        $this->assertFalse(config('portal.strict_attachments'));
        $this->assertInvalid(fn () => $this->invoke('validatePublicAttachmentSources', [$this->requestFor($this->orphanData()), '123456']), ['attachments']);
    }

    public function test_stored_document_requires_an_existing_nonempty_safe_file(): void
    {
        $request = $this->requestFor($this->validData());
        DB::table('attachments')->insert([
            'person_identity_number' => '111111111', 'file_type' => 'identity', 'file_path' => '/storage/uploads/existing.jpg',
        ]);
        Storage::disk('public')->put('uploads/existing.jpg', '');
        $this->assertCount(2, $this->invoke('missingPublicDocuments', [$request, '123456', []]));
        $image = UploadedFile::fake()->image('existing.jpg');
        Storage::disk('public')->put('uploads/existing.jpg', file_get_contents($image->getRealPath()));
        $missing = $this->invoke('missingPublicDocuments', [$request, '123456', []]);
        $this->assertSame(['222222222'], array_column($missing, 'person_identity_number'));
        Storage::disk('public')->delete('uploads/existing.jpg');
        $this->assertCount(2, $this->invoke('missingPublicDocuments', [$request, '123456', []]));
        DB::table('attachments')->update(['file_path' => 'temp_uploads/123456/existing.jpg']);
        Storage::disk('public')->put('temp_uploads/123456/existing.jpg', file_get_contents($image->getRealPath()));
        $this->assertCount(2, $this->invoke('missingPublicDocuments', [$request, '123456', []]));
    }

    private function requestWithUploads(array $data, array $documents): Request
    {
        $data['attachments'] = [];
        $files = [];
        foreach ($documents as $index => [$person, $type, $file]) {
            $data['attachments'][$index] = ['person_identity_number' => $person, 'file_type' => $type];
            $files[$index] = ['file' => $file];
        }
        $request = $this->requestFor($data);
        $request->files->set('attachments', $files);

        return $request;
    }

    public function test_real_uploaded_images_cover_symbolic_groups_and_numeric_identities(): void
    {
        $data = $this->motherData(false);
        unset($data['family_members'][0]['person_id']);
        $data['additional_deceased'] = [$this->additionalDeceased()];
        $documents = [];
        foreach ([['main', 'identity'], ['family_0', 'identity'], ['333333333', 'death'], ['deceased_mother', 'death'], ['additional_deceased_0', 'death']] as [$person, $type]) {
            $documents[] = [$person, $type, UploadedFile::fake()->image($person.'.jpg')];
        }
        $request = $this->requestWithUploads($data, $documents);
        $this->assertInstanceOf(UploadedFile::class, $request->file('attachments.0.file'));
        $this->assertNull($this->invoke('validatePublicRegistration', [$request]));
        $this->assertNull($this->invoke('validatePublicAttachmentSources', [$request, '123456']));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function invalidAttachmentPeopleAndTypes(): iterable
    {
        yield 'unknown person' => ['999999999', 'identity'];
        yield 'unknown group' => ['family_99', 'identity'];
        yield 'unknown type' => ['main', 'nonexistent'];
        yield 'type disabled in context' => ['main', 'death'];
        yield 'living mother deceased attachment' => ['deceased_mother', 'death'];
    }

    #[DataProvider('invalidAttachmentPeopleAndTypes')]
    public function test_uploads_for_unknown_people_or_types_are_rejected(string $person, string $type): void
    {
        $request = $this->requestWithUploads($this->validData(), [[$person, $type, UploadedFile::fake()->image('valid.jpg')]]);
        $this->assertInvalid(fn () => $this->invoke('validatePublicAttachmentSources', [$request, '123456']), ['attachments.0.file']);
    }

    public static function invalidTemporaryPaths(): iterable
    {
        yield 'no file source' => [''];
        yield 'missing file' => ['temp_uploads/123456/missing.jpg'];
        yield 'traversal path' => ['temp_uploads/123456/../../outside.jpg'];
        yield 'outside uploads root' => ['uploads/123456/spoofed.jpg'];
        yield 'absolute file path' => ['C:/Windows/win.ini'];
    }

    #[DataProvider('invalidTemporaryPaths')]
    public function test_missing_or_spoofed_temporary_sources_cannot_cover_required_documents(string $path): void
    {
        $data = $this->validData();
        $data['attachments'] = [[
            'person_identity_number' => 'main', 'file_type' => 'identity',
            'temp_path' => $path, 'stored_file_name' => 'claimed-saved.jpg',
        ]];
        $this->assertInvalid(fn () => $this->invoke('validatePublicAttachmentSources', [$this->requestFor($data), '123456']), ['attachments.0.file', 'attachments']);
    }

    public function test_existing_temporary_upload_belonging_to_another_file_is_rejected(): void
    {
        // فحص المسار يستخدم storage_path مباشرة، لذلك يُنشأ هذا الملف داخل تخزين الاختبار المعزول فقط.
        $directory = storage_path('app/public/temp_uploads/654321');
        (new Filesystem)->makeDirectory($directory, 0755, true);
        $image = UploadedFile::fake()->image('foreign.jpg');
        copy($image->getRealPath(), $directory.'/foreign.jpg');
        $data = $this->validData();
        $data['attachments'] = [[
            'person_identity_number' => 'main', 'file_type' => 'identity',
            'temp_path' => 'temp_uploads/654321/foreign.jpg',
        ]];
        $this->assertInvalid(fn () => $this->invoke('validatePublicAttachmentSources', [$this->requestFor($data), '123456']), ['attachments.0.file']);
        $this->assertFileExists($directory.'/foreign.jpg');
    }

    public function test_nonimage_disguised_as_jpeg_and_corrupt_jpeg_are_rejected(): void
    {
        foreach (['not an image', "%PDF-1.4\nnot an image", "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00".str_repeat("\x00", 40)] as $contents) {
            $file = UploadedFile::fake()->createWithContent('spoofed.jpg', $contents);
            $request = $this->requestWithUploads($this->validData(), [['main', 'identity', $file]]);
            $this->assertInvalid(fn () => $this->invoke('validatePublicAttachmentSources', [$request, '123456']), ['attachments.0.file']);
        }
    }

    public function test_actual_image_larger_than_five_megabytes_is_rejected(): void
    {
        $image = UploadedFile::fake()->image('oversize.jpg');
        $path = $image->getRealPath();
        file_put_contents($path, str_repeat('x', 5242881), FILE_APPEND);
        clearstatcache(true, $path);
        $this->assertGreaterThan(5242880, filesize($path));
        $this->assertIsArray(getimagesize($path));
        $request = $this->requestWithUploads($this->validData(), [['main', 'identity', $image]]);
        $this->assertInvalid(fn () => $this->invoke('validatePublicAttachmentSources', [$request, '123456']), ['attachments.0.file']);
    }

    public function test_preparation_copies_upload_temp_and_absolute_sources_and_cleanup_preserves_sources(): void
    {
        $image = UploadedFile::fake()->image('source.jpg');
        $contents = file_get_contents($image->getRealPath());
        Storage::disk('public')->put('temp_uploads/123456/source.jpg', $contents);
        $pending = [
            ['type' => 'uploaded', 'file' => $image, 'final_path' => 'uploads/prepared/upload.jpg', 'index' => 0],
            ['type' => 'temp_path', 'source' => 'temp_uploads/123456/source.jpg', 'final_path' => 'uploads/prepared/temp.jpg', 'index' => 1],
            ['type' => 'absolute', 'source' => $image->getRealPath(), 'final_path' => 'uploads/prepared/absolute.jpg', 'index' => 2],
        ];
        $prepared = [];
        $this->assertSame(3, $this->invoke('preparePublicAttachmentStores', [$pending, &$prepared]));
        $this->assertSame(array_column($pending, 'final_path'), $prepared);
        foreach ($prepared as $path) {
            $this->assertSame($contents, Storage::disk('public')->get($path));
        }
        $this->invoke('cleanupPreparedAttachmentFiles', [$prepared]);
        $this->invoke('cleanupPreparedAttachmentFiles', [$prepared]);
        foreach ($prepared as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $this->assertSame($contents, file_get_contents($image->getRealPath()));
        $this->assertSame($contents, Storage::disk('public')->get('temp_uploads/123456/source.jpg'));
    }

    public function test_preparation_failure_tracks_destinations_for_rollback_without_deleting_sources(): void
    {
        $image = UploadedFile::fake()->image('source.jpg');
        $contents = file_get_contents($image->getRealPath());
        Storage::disk('public')->put('temp_uploads/123456/source.jpg', $contents);
        $pending = [
            ['type' => 'temp_path', 'source' => 'temp_uploads/123456/source.jpg', 'final_path' => 'uploads/prepared/first.jpg', 'index' => 0],
            ['type' => 'absolute', 'source' => $this->isolatedStorage.'/missing-source.jpg', 'final_path' => 'uploads/prepared/second.jpg', 'index' => 1],
        ];
        $prepared = [];
        $this->assertInvalid(function () use ($pending, &$prepared): void {
            $this->invoke('preparePublicAttachmentStores', [$pending, &$prepared]);
        }, ['attachments.1.file']);
        $this->assertSame(array_column($pending, 'final_path'), $prepared);
        Storage::disk('public')->assertExists('uploads/prepared/first.jpg');
        $this->invoke('cleanupPreparedAttachmentFiles', [$prepared]);
        Storage::disk('public')->assertMissing('uploads/prepared/first.jpg');
        Storage::disk('public')->assertMissing('uploads/prepared/second.jpg');
        $this->assertSame($contents, Storage::disk('public')->get('temp_uploads/123456/source.jpg'));
        $this->assertSame($contents, file_get_contents($image->getRealPath()));
    }

    public function test_partial_destination_is_removed_after_storage_failure_without_removing_source(): void
    {
        $image = UploadedFile::fake()->image('source.jpg');
        $contents = file_get_contents($image->getRealPath());
        $disk = Storage::disk('public');
        $proxy = \Mockery::mock($disk);
        $proxy->shouldReceive('put')
            ->with('uploads/prepared/partial.jpg', \Mockery::type('resource'))
            ->once()
            ->andReturnUsing(function (string $path) use ($disk): void {
                $disk->put($path, 'partial');
                throw new \RuntimeException('Simulated storage failure');
            });
        Storage::shouldReceive('disk')->with('public')->andReturn($proxy);
        $prepared = [];
        $pending = [[
            'type' => 'uploaded', 'file' => $image, 'final_path' => 'uploads/prepared/partial.jpg', 'index' => 0,
        ]];
        $this->assertInvalid(function () use ($pending, &$prepared): void {
            $this->invoke('preparePublicAttachmentStores', [$pending, &$prepared]);
        }, ['attachments.0.file']);
        $this->assertSame(['uploads/prepared/partial.jpg'], $prepared);
        $this->assertSame('partial', $disk->get('uploads/prepared/partial.jpg'));
        $this->invoke('cleanupPreparedAttachmentFiles', [$prepared]);
        $disk->assertMissing('uploads/prepared/partial.jpg');
        $this->assertSame($contents, file_get_contents($image->getRealPath()));
    }

    public function test_actual_store_rejects_missing_documents_before_transaction_or_writes(): void
    {
        Schema::create('data', function (Blueprint $table): void {
            $table->id();
            $table->string('data_id_number');
            $table->string('file_id_number');
            $table->timestamps();
        });
        Schema::create('re_people', function (Blueprint $table): void {
            $table->string('person_id');
            $table->string('registration_id');
        });
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $response = $this->controller->store($this->requestFor($this->validData()));
        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('لم يتم حفظ الطلب', $payload['message']);
        $this->assertCount(2, $payload['errors']['attachments']);
        $this->assertStringContainsString('الوصي', $payload['errors']['attachments'][0]);
        $this->assertStringContainsString('فرد الأسرة', $payload['errors']['attachments'][1]);
        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/^select\b/i', ltrim($sql));
        }
        $this->assertSame(0, DB::transactionLevel());
        foreach (['data', 're_people', 'attachments'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_actual_store_rejects_invalid_public_submission_with_arabic_422_and_no_writes(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $this->assertSame('sqlite', $query->connection->getDriverName());
            $queries[] = $query->sql;
        });
        $data = $this->validData();
        unset($data['data_father_name']);
        $response = $this->controller->store($this->requestFor($data));
        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('لم يتم حفظ الطلب', $payload['message']);
        $this->assertArrayHasKey('data_father_name', $payload['errors']);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $payload['errors']['data_father_name'][0]);
        $this->assertContains('data_father_name', array_column($payload['error_fields'], 'field'));
        $this->assertMatchesRegularExpression('/^R-[A-F0-9]{6}$/', $payload['error_reference']);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/^select\b/i', ltrim($sql));
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('attachments')->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
