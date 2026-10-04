<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NsmsClient;
use Database\Seeders\PermissionTableSeeder;
use Database\Seeders\SmsTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * اختبارات شاشة الرسائل النصية (NSMS REST v2).
 * طبقة العميل والمسارات بلا قاعدة بيانات، والاختبار الأخير يعرض الصفحة فعليًا.
 */
class SmsSingleTest extends TestCase
{
    use RefreshDatabase;

    private function client(): NsmsClient
    {
        config()->set('services.nsms.country_code', '970');
        return app(NsmsClient::class);
    }

    /* ---------- توحيد الأرقام ---------- */

    public function test_normalize_local_number_with_leading_zero(): void
    {
        $this->assertSame('970599123456', $this->client()->normalize('0599123456'));
    }

    public function test_normalize_number_missing_zero_because_excel_dropped_it(): void
    {
        $this->assertSame('970599123456', $this->client()->normalize('599123456'));
    }

    public function test_normalize_keeps_already_prefixed_number(): void
    {
        $this->assertSame('970599123456', $this->client()->normalize('970599123456'));
    }

    public function test_normalize_converts_scientific_notation_from_excel(): void
    {
        // Excel يحذف الصفر الأول فيصبح الرقم 599123456 بصيغة علمية
        $this->assertSame('970599123456', $this->client()->normalize('5.99123456E+8'));
    }

    public function test_normalize_strips_non_digits(): void
    {
        $this->assertSame('970599123456', $this->client()->normalize('+970 599-123-456'));
    }

    /* ---------- التحقق ---------- */

    public function test_valid_number_is_accepted(): void
    {
        $this->assertTrue($this->client()->isValid('970599123456'));
    }

    public function test_empty_or_too_short_number_is_rejected(): void
    {
        $client = $this->client();
        $this->assertFalse($client->isValid(''));
        $this->assertFalse($client->isValid('12345'));
    }

    /* ---------- إرسال فردي مع Http::fake ---------- */

    public function test_single_send_posts_to_nsms_and_returns_ok(): void
    {
        Http::fake([
            '*messages/send/summary' => Http::response(['status' => true, 'data' => []]),
            '*messages/send'         => Http::response([
                'status' => true,
                'data'   => ['request_id' => 'REQ-1', 'provider_response' => ['accepted' => 1, 'total_cost' => 0.1]],
            ]),
        ]);

        $res = $this->client()->send('970599123456', 'مرحبا', 'TestSender');

        $this->assertTrue($res['status']);
        $this->assertSame('REQ-1', data_get($res, 'data.request_id'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://send.nsms.ps/api/rest/v2/messages/send'
                && $request['mobile'] === '970599123456'
                && $request['message'] === 'مرحبا'
                && $request['sender'] === 'TestSender';
        });
    }

    public function test_provider_error_code_is_translated_to_arabic(): void
    {
        Http::fake(['*messages/send' => Http::response(['status' => false, 'code' => 1101])]);

        $res = $this->client()->send('970599123456', 'نص', 'TestSender');

        $this->assertFalse($res['status']);
        $this->assertSame(NsmsClient::ERRORS[1101], $res['message']);
    }

    public function test_invalid_token_code_is_translated_to_arabic(): void
    {
        Http::fake(['*messages/send' => Http::response(['status' => false, 'code' => 1003])]);

        $res = $this->client()->send('970599123456', 'نص', 'TestSender');

        $this->assertSame(NsmsClient::ERRORS[1003], $res['message']);
    }

    public function test_summary_returns_can_send_flag(): void
    {
        Http::fake(['*messages/send/summary' => Http::response([
            'status' => true,
            'data'   => [
                'accepted' => 2, 'rejected' => 0, 'total_segments' => 2,
                'required_cost' => 0.2, 'available_credit' => 10, 'can_send' => true,
            ],
        ])]);

        $res = $this->client()->summary(['970599123456', '970598765432'], 'نص', 'TestSender');

        $this->assertTrue($res['status']);
        $this->assertTrue(data_get($res, 'data.can_send'));
        $this->assertSame(10, data_get($res, 'data.available_credit'));
    }

    public function test_credits_returns_null_when_api_fails(): void
    {
        Http::fake(['*credits' => Http::response(['status' => false, 'code' => 1003])]);

        $this->assertNull($this->client()->credits());
    }

    public function test_senders_falls_back_to_configured_sender(): void
    {
        Http::fake(['*senders' => Http::response(['status' => false, 'code' => 1003])]);
        config()->set('services.nsms.sender', 'MySender');

        $this->assertSame(['MySender'], $this->client()->senders());
    }

    /* ---------- المسارات ---------- */

    public function test_sms_route_names_are_registered(): void
    {
        foreach ([
            'admin.sms.index', 'admin.sms.logs', 'admin.sms.single',
            'admin.sms.bulk.summary', 'admin.sms.bulk.send',
            'admin.sms.excel.upload', 'admin.sms.excel.summary', 'admin.sms.excel.send',
            'admin.sms.templates.index', 'admin.sms.groups.index',
        ] as $name) {
            $this->assertNotEmpty(route($name), "Missing route: {$name}");
        }

        $this->assertSame('/admin/sms', parse_url(route('admin.sms.index'), PHP_URL_PATH));
        $this->assertSame('/admin/sms/logs', parse_url(route('admin.sms.logs'), PHP_URL_PATH));
    }

    public function test_guest_is_redirected_from_sms_page(): void
    {
        $this->get(route('admin.sms.index'))->assertRedirect(route('login'));
    }

    public function test_sms_page_renders_for_admin(): void
    {
        Http::fake(); // لا نتواصل مع NSMS أثناء العرض
        $this->actingAsAdmin();

        $this->get(route('admin.sms.index'))
            ->assertOk()
            ->assertSee('إرسال الرسائل النصية')
            ->assertSee('id="single-send"', false)
            ->assertSee('id="single-bubble"', false)   // معاينة الهاتف
            ->assertSee('admin\\/sms\\/single', false); // json_encode يهرب الشرطة المائلة
    }

    public function test_excel_upload_keeps_original_column_names(): void
    {
        Http::fake();
        $this->actingAsAdmin();

        $res = $this->post(route('admin.sms.excel.upload'), [
            'file' => $this->xlsxFile([
                ['Mobile Number', 'Full Name'],
                ['0599123456', 'Ahmad'],
            ]),
        ]);

        $res->assertOk()->assertJsonPath('total', 1);
        $this->assertNotEmpty($res->json('token'));

        // لو عاد الـ slug لصارت mobile_number / full_name بدل العنوان الأصلي
        $columns = $res->json('columns');
        $this->assertContains('Mobile Number', $columns);
        $this->assertContains('Full Name', $columns);
    }

    /** مستخدم admin له كل الصلاحيات لاجتياز middleware المسارات المحمية. */
    private function actingAsAdmin(): void
    {
        $this->seed([PermissionTableSeeder::class, SmsTemplateSeeder::class]);

        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $user->syncRoles([$role]);

        $this->actingAs($user);
    }

    private function xlsxFile(array $rows): UploadedFile
    {
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray($rows);

        $path = tempnam(sys_get_temp_dir(), 'sms_xl') . '.xlsx';
        (new Xlsx($sheet))->save($path);

        return UploadedFile::fake()
            ->createWithContent('sms_test.xlsx', (string) file_get_contents($path));
    }
}
