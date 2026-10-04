# دمج شاشة إرسال الرسائل النصية (NSMS REST v2) في لوحة تحكم Laravel

شاشة واحدة بثلاث تبويبات + إدارة القوالب والمجموعات + سجل الإرسال:

| التبويب | الوظيفة |
|---|---|
| **فردي** | رسالة لرقم واحد، ويرد النتيجة فورًا |
| **جماعي** | أرقام ملصوقة أو مجموعات محفوظة، مع حساب الكلفة قبل الإرسال |
| **من Excel** | رفع ملف فيه أرقام ومتغيرات (اسم، مبلغ...) وقالب فيه `{name}` وإرسال مخصص لكل صف |

**الافتراضات:** Laravel 10/11/12، PHP 8.1+، Bootstrap 5 RTL في اللوحة، والتوكن موجود عندك في `.env` (انظر الرد السابق). عدّل أسماء الـ layout والـ sections حسب لوحتك.

---

## 0. ترتيب التنفيذ

1. تثبيت الحزمة + الإعدادات (القسم 1، 2)
2. الـ migrations والـ models (3، 4)
3. `NsmsClient` (5)
4. الـ Job (6)
5. الـ Controllers والـ routes (7، 8)
6. الـ Views (9)
7. القائمة الجانبية والصلاحيات (10)
8. تشغيل الـ queue (11)

---

## 1. التثبيت

```bash
composer require maatwebsite/excel
php artisan queue:table      # إذا ستستخدم QUEUE_CONNECTION=database
```

## 2. الإعدادات

`.env`
```env
NSMS_BASE_URL=https://send.nsms.ps/api/rest/v2
NSMS_TOKEN=xxxxxxxxxxxxxxxx
NSMS_SENDER=MyBrand
NSMS_COUNTRY_CODE=970
QUEUE_CONNECTION=database
```

`config/services.php`
```php
'nsms' => [
    'base_url'     => env('NSMS_BASE_URL'),
    'token'        => env('NSMS_TOKEN'),
    'sender'       => env('NSMS_SENDER'),
    'country_code' => env('NSMS_COUNTRY_CODE', '970'),
],
```

---

## 3. Migrations

```bash
php artisan make:migration create_sms_tables
```

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('sms_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->longText('numbers');          // رقم في كل سطر
            $t->timestamps();
        });

        Schema::create('sms_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 10);            // single | bulk | excel
            $t->string('sender', 50);
            $t->string('mobile', 20)->nullable();
            $t->unsignedInteger('recipients')->default(1);
            $t->text('message');               // في Excel يحفظ القالب
            $t->string('status', 10);          // sent | failed
            $t->unsignedInteger('code')->nullable();
            $t->string('error')->nullable();
            $t->unsignedInteger('accepted')->nullable();
            $t->unsignedInteger('rejected')->nullable();
            $t->decimal('cost', 12, 4)->nullable();
            $t->string('request_id', 64)->nullable()->index();
            $t->string('dlr_status', 20)->nullable();
            $t->timestamp('dlr_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_groups');
        Schema::dropIfExists('sms_templates');
    }
};
```

```bash
php artisan migrate
```

---

## 4. Models

`app/Models/SmsTemplate.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
```

`app/Models/SmsGroup.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsGroup extends Model
{
    protected $guarded = [];
}
```

`app/Models/SmsLog.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $guarded = [];

    public function user() { return $this->belongsTo(User::class); }

    /** ينشئ سجلًا من رد الـ API (يدعم رد send ورد sendvar). */
    public static function fromResponse(array $res, array $attrs): self
    {
        $ok = (bool) ($res['status'] ?? false);
        $pr = data_get($res, 'data.provider_response', data_get($res, 'data', []));

        $cost = $pr['total_cost'] ?? collect($pr['networks_report'] ?? [])->sum('cost');

        return static::create($attrs + [
            'status'     => $ok ? 'sent' : 'failed',
            'code'       => $res['code'] ?? null,
            'error'      => $ok ? null : mb_substr($res['message'] ?? 'Unknown error', 0, 250),
            'accepted'   => $pr['accepted'] ?? null,
            'rejected'   => $pr['rejected'] ?? null,
            'cost'       => $ok ? $cost : null,
            'request_id' => $pr['request_id'] ?? null,
        ]);
    }
}
```

---

## 5. عميل الـ API

`app/Services/NsmsClient.php`

> لا يوجد `retry` على عمليات الإرسال عمدًا: إذا انقطع الاتصال بعد وصول الطلب للسيرفر، إعادة المحاولة ستسبب إرسالًا مكررًا.

```php
<?php
namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class NsmsClient
{
    /** رسائل عربية لأشهر الأكواد */
    public const ERRORS = [
        1002 => 'لم يتم إرسال التوكن',
        1003 => 'التوكن غير صالح أو ملغي، سجّل دخول جديد وحدّث NSMS_TOKEN',
        1101 => 'الرصيد غير كافٍ',
        1201 => 'فشل الإرسال من المزود، أعد المحاولة لاحقًا',
        1401 => 'خدمة الـ API غير مفعّلة للحساب',
        1402 => 'عنوان IP السيرفر غير مسموح، أضفه في إعدادات الحساب',
        1601 => 'عدد الأرقام يتجاوز الحد المسموح',
        1602 => 'بيانات الطلب غير صالحة',
    ];

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.nsms.base_url'))
            ->withToken(config('services.nsms.token'))
            ->acceptJson()
            ->timeout(30);
    }

    private function call(string $method, string $uri, array $payload = []): array
    {
        try {
            $r = $this->http()->{$method}($uri, $payload);
        } catch (ConnectionException $e) {
            return ['status' => false, 'code' => 0, 'message' => 'تعذر الاتصال بخدمة الرسائل'];
        }

        $json = $r->json();
        $res  = is_array($json)
            ? $json
            : ['status' => false, 'code' => $r->status(), 'message' => 'استجابة غير متوقعة (HTTP '.$r->status().')'];

        if (!($res['status'] ?? false)) {
            $res['message'] = self::ERRORS[$res['code'] ?? 0] ?? ($res['message'] ?? 'خطأ غير معروف');
        }
        return $res;
    }

    /* ---------- عمليات الإرسال ---------- */

    public function send(string|array $mobiles, string $message, ?string $sender = null): array
    {
        return $this->call('post', 'messages/send', [
            'mobile'  => implode(',', (array) $mobiles),
            'message' => $message,
            'sender'  => $sender ?? config('services.nsms.sender'),
        ]);
    }

    public function summary(string|array $mobiles, string $message, ?string $sender = null): array
    {
        return $this->call('post', 'messages/send/summary', [
            'mobile'  => implode(',', (array) $mobiles),
            'message' => $message,
            'sender'  => $sender ?? config('services.nsms.sender'),
        ]);
    }

    public function sendVar(string $sender, string $mobileColumn, string $template, array $rows): array
    {
        return $this->call('post', 'messages/sendvar', [
            'sender_id'        => $sender,
            'mobile_column'    => $mobileColumn,
            'message_template' => $template,
            'rows'             => $rows,
        ]);
    }

    public function sendVarSummary(string $sender, string $mobileColumn, string $template, array $rows): array
    {
        return $this->call('post', 'messages/sendvar/summary', [
            'sender_id'        => $sender,
            'mobile_column'    => $mobileColumn,
            'message_template' => $template,
            'rows'             => $rows,
        ]);
    }

    /* ---------- معلومات الحساب ---------- */

    public function credits(): ?int
    {
        $res = $this->call('get', 'credits');
        return ($res['status'] ?? false) ? (int) data_get($res, 'data.credits') : null;
    }

    public function senders(): array
    {
        $res  = $this->call('get', 'senders');
        $list = data_get($res, 'data.senders');

        return is_array($list) && $list
            ? array_values($list)
            : array_values(array_filter([config('services.nsms.sender')]));
    }

    /* ---------- أدوات الأرقام ---------- */

    public function normalize(?string $m): string
    {
        $cc = config('services.nsms.country_code', '970');
        $m  = (string) $m;

        // أرقام Excel الكبيرة قد تأتي بصيغة علمية 5.99E+8
        if (is_numeric($m) && stripos($m, 'e') !== false) {
            $m = sprintf('%.0f', (float) $m);
        }

        $m = preg_replace('/\D+/', '', $m);

        if (str_starts_with($m, '00'))                         $m = substr($m, 2);
        elseif (str_starts_with($m, '0'))                      $m = $cc.substr($m, 1);   // 0599... → 970599...
        elseif (strlen($m) === 9 && str_starts_with($m, '5'))  $m = $cc.$m;              // 599... (صفر مفقود من Excel)

        return $m;
    }

    public function isValid(string $m): bool
    {
        return (bool) preg_match('/^\d{11,15}$/', $m);
    }
}
```

---

## 6. Job الإرسال الجماعي والـ Excel

```bash
php artisan make:job SendBulkSmsJob
```

`app/Jobs/SendBulkSmsJob.php`

> `$tries = 1` عمدًا حتى لا تُرسل نفس الرسائل مرتين عند فشل الـ Job في المنتصف.

```php
<?php
namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\NsmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class SendBulkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries   = 1;
    public $timeout = 900;

    public function __construct(
        public string  $type,            // bulk | excel
        public int     $userId,
        public string  $sender,
        public string  $message,         // نص الرسالة أو قالب Excel
        public array   $mobiles = [],    // للجماعي
        public ?string $file = null,     // مسار ملف الصفوف للـ Excel
        public ?string $mobileColumn = null,
    ) {}

    public function handle(NsmsClient $sms): void
    {
        try {
            $this->type === 'excel' ? $this->sendExcel($sms) : $this->sendBulk($sms);
        } finally {
            if ($this->file) Storage::delete($this->file);
        }
    }

    private function sendBulk(NsmsClient $sms): void
    {
        // 500 رقم في الطلب الواحد (قيمة آمنة؛ حدّها حسب ما يسمح به مزودك)
        foreach (array_chunk($this->mobiles, 500) as $chunk) {
            $res = $sms->send($chunk, $this->message, $this->sender);
            $this->log($res, count($chunk));
            if ($this->mustStop($res)) break;
        }
    }

    private function sendExcel(NsmsClient $sms): void
    {
        $rows = json_decode(Storage::get($this->file), true)['rows'] ?? [];

        // الحد الأقصى في sendvar هو 5000 صف، نستخدم 1000
        foreach (array_chunk($rows, 1000) as $chunk) {
            $res = $sms->sendVar($this->sender, $this->mobileColumn, $this->message, $chunk);
            $this->log($res, count($chunk));
            if ($this->mustStop($res)) break;
        }
    }

    private function log(array $res, int $count): void
    {
        SmsLog::fromResponse($res, [
            'user_id'    => $this->userId,
            'type'       => $this->type,
            'sender'     => $this->sender,
            'recipients' => $count,
            'message'    => $this->message,
        ]);
    }

    /** أخطاء لا فائدة من متابعة الدفعات بعدها */
    private function mustStop(array $res): bool
    {
        return !($res['status'] ?? false)
            && in_array($res['code'] ?? 0, [1002, 1003, 1101, 1401, 1402, 1602], true);
    }
}
```

---

## 7. Controllers

### 7.1 Import class (قراءة Excel)

`app/Imports/SmsRowsImport.php`
```php
<?php
namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SmsRowsImport implements ToArray, WithHeadingRow
{
    public function array(array $array): void {}
}
```

### 7.2 الشاشة الرئيسية

```bash
php artisan make:controller SmsController
```

`app/Http/Controllers/SmsController.php`
```php
<?php
namespace App\Http\Controllers;

use App\Imports\SmsRowsImport;
use App\Jobs\SendBulkSmsJob;
use App\Models\SmsGroup;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\NsmsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowFormatter;

class SmsController extends Controller
{
    public function __construct(private NsmsClient $sms) {}

    /* ===================== الشاشة ===================== */

    public function index()
    {
        return view('sms.index', [
            'templates' => SmsTemplate::where('is_active', true)->orderBy('title')->get(),
            'groups'    => SmsGroup::orderBy('name')->get(),
            'senders'   => $this->senders(),
            'credits'   => $this->sms->credits(),
        ]);
    }

    public function logs()
    {
        return view('sms.logs', [
            'logs' => SmsLog::with('user')->latest()->paginate(30),
        ]);
    }

    /* ===================== فردي ===================== */

    public function single(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile'  => 'required|string|max:30',
            'message' => 'required|string|max:2000',
            'sender'  => ['required', Rule::in($this->senders())],
        ]);

        $mobile = $this->sms->normalize($data['mobile']);
        if (!$this->sms->isValid($mobile)) {
            return response()->json(['ok' => false, 'message' => 'رقم الجوال غير صالح'], 422);
        }

        $res = $this->sms->send($mobile, $data['message'], $data['sender']);

        SmsLog::fromResponse($res, [
            'user_id'    => auth()->id(),
            'type'       => 'single',
            'sender'     => $data['sender'],
            'mobile'     => $mobile,
            'recipients' => 1,
            'message'    => $data['message'],
        ]);

        $ok = (bool) ($res['status'] ?? false);
        return response()->json([
            'ok'      => $ok,
            'message' => $ok ? 'تم إرسال الرسالة إلى '.$mobile : $res['message'],
        ], $ok ? 200 : 422);
    }

    /* ===================== جماعي ===================== */

    public function bulkSummary(Request $request): JsonResponse
    {
        [$data, $mobiles, $invalid] = $this->bulkInput($request);

        $res = $this->sms->summary($mobiles, $data['message'], $data['sender']);
        if (!($res['status'] ?? false)) {
            return response()->json(['message' => $res['message']], 422);
        }

        $d = $res['data'];
        return response()->json([
            'recipients' => count($mobiles),
            'invalid'    => $invalid,
            'accepted'   => $d['accepted'] ?? 0,
            'rejected'   => $d['rejected'] ?? 0,
            'segments'   => $d['total_segments'] ?? null,
            'cost'       => $d['required_cost'] ?? $d['total_cost'] ?? 0,
            'available'  => $d['available_credit'] ?? null,
            'can_send'   => (bool) ($d['can_send'] ?? false),
        ]);
    }

    public function bulkSend(Request $request): JsonResponse
    {
        [$data, $mobiles] = $this->bulkInput($request);

        SendBulkSmsJob::dispatch('bulk', auth()->id(), $data['sender'], $data['message'], mobiles: $mobiles);

        return response()->json([
            'ok'      => true,
            'message' => 'تمت جدولة الإرسال إلى '.count($mobiles).' رقم. تابع النتيجة من سجل الرسائل.',
        ]);
    }

    private function bulkInput(Request $request): array
    {
        $data = $request->validate([
            'message'     => 'required|string|max:2000',
            'sender'      => ['required', Rule::in($this->senders())],
            'numbers'     => 'nullable|string',
            'group_ids'   => 'nullable|array',
            'group_ids.*' => 'integer|exists:sms_groups,id',
        ]);

        $texts = collect([$data['numbers'] ?? ''])
            ->merge(SmsGroup::whereIn('id', $data['group_ids'] ?? [])->pluck('numbers'));

        $all = $texts
            ->flatMap(fn ($t) => preg_split('/[\s,;،]+/u', (string) $t, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($m) => $this->sms->normalize($m));

        $valid   = $all->filter(fn ($m) => $this->sms->isValid($m))->unique()->values();
        $invalid = $all->reject(fn ($m) => $this->sms->isValid($m))->count();

        abort_if($valid->isEmpty(), 422, 'لا توجد أرقام صالحة');
        abort_if($valid->count() > 10000, 422, 'الحد الأقصى 10000 رقم في الإرسال الواحد');

        return [$data, $valid->all(), $invalid];
    }

    /* ===================== Excel ===================== */

    public function excelUpload(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);

        // نحافظ على أسماء الأعمدة كما كتبتها (بدون تحويلها إلى snake_case)
        HeadingRowFormatter::default('none');
        try {
            $sheets = Excel::toArray(new SmsRowsImport, $request->file('file'));
        } finally {
            HeadingRowFormatter::default('slug');
        }

        $rows = collect($sheets[0] ?? [])
            ->map(fn ($r) => collect($r)
                ->filter(fn ($v, $k) => is_string($k) && trim($k) !== '')
                ->map(fn ($v) => $this->cell($v))
                ->all())
            ->filter(fn ($r) => collect($r)->contains(fn ($v) => $v !== ''))
            ->values();

        abort_if($rows->isEmpty(), 422, 'الملف فارغ أو لا يحتوي صف عناوين');
        abort_if($rows->count() > 20000, 422, 'الحد الأقصى 20000 صف');

        $token = (string) Str::uuid();
        Storage::put($this->importPath($token), json_encode(['rows' => $rows->all()], JSON_UNESCAPED_UNICODE));

        return response()->json([
            'token'   => $token,
            'total'   => $rows->count(),
            'columns' => array_keys($rows->first()),
            'preview' => $rows->take(5)->all(),
        ]);
    }

    public function excelSummary(Request $request): JsonResponse
    {
        $in = $this->excelInput($request);
        [$valid, $invalid] = $this->prepareRows($in['token'], $in['mobile_column'], $in['message']);

        $sum = ['accepted' => 0, 'rejected' => 0, 'segments' => 0, 'cost' => 0];
        $available = null;

        foreach (array_chunk($valid, 5000) as $chunk) {
            $res = $this->sms->sendVarSummary($in['sender'], $in['mobile_column'], $in['message'], $chunk);
            if (!($res['status'] ?? false)) {
                return response()->json(['message' => $res['message']], 422);
            }
            $d = $res['data'];
            $sum['accepted'] += $d['accepted'] ?? 0;
            $sum['rejected'] += $d['rejected'] ?? 0;
            $sum['segments'] += $d['total_segments'] ?? 0;
            $sum['cost']     += $d['required_cost'] ?? 0;
            $available     ??= $d['available_credit'] ?? null;
        }

        return response()->json($sum + [
            'recipients' => count($valid),
            'invalid'    => $invalid,
            'available'  => $available,
            'can_send'   => $available !== null && $available >= $sum['cost'],
        ]);
    }

    public function excelSend(Request $request): JsonResponse
    {
        $in = $this->excelInput($request);
        [$valid, $invalid, $path] = $this->prepareRows($in['token'], $in['mobile_column'], $in['message']);

        // نحفظ الصفوف الصالحة (بأرقام موحّدة) ليقرأها الـ Job
        Storage::put($path, json_encode(['rows' => $valid], JSON_UNESCAPED_UNICODE));

        SendBulkSmsJob::dispatch('excel', auth()->id(), $in['sender'], $in['message'],
            file: $path, mobileColumn: $in['mobile_column']);

        return response()->json([
            'ok'      => true,
            'message' => 'تمت جدولة الإرسال إلى '.count($valid).' صف'.($invalid ? " (تم تجاهل $invalid صف برقم غير صالح)" : '').'. تابع النتيجة من سجل الرسائل.',
        ]);
    }

    private function excelInput(Request $request): array
    {
        return $request->validate([
            'token'         => 'required|uuid',
            'mobile_column' => 'required|string|max:100',
            'message'       => 'required|string|max:2000',   // القالب مع {المتغيرات}
            'sender'        => ['required', Rule::in($this->senders())],
        ]);
    }

    /** يقرأ الصفوف، يتحقق من العمود والمتغيرات، ويوحّد الأرقام ويستبعد غير الصالح. */
    private function prepareRows(string $token, string $col, string $template): array
    {
        $path = $this->importPath($token);
        abort_unless(Storage::exists($path), 422, 'انتهت صلاحية الملف، أعد رفعه');

        $rows = json_decode(Storage::get($path), true)['rows'] ?? [];
        abort_if(!$rows || !array_key_exists($col, $rows[0]), 422, 'عمود الجوال غير موجود في الملف');

        preg_match_all('/\{([^{}]+)\}/u', $template, $m);
        $missing = array_diff(array_unique($m[1]), array_keys($rows[0]));
        abort_if($missing, 422, 'متغيرات غير موجودة في الملف: '.implode('، ', $missing));

        $valid = [];
        $invalid = 0;
        foreach ($rows as $r) {
            $n = $this->sms->normalize($r[$col] ?? '');
            if ($this->sms->isValid($n)) { $r[$col] = $n; $valid[] = $r; }
            else $invalid++;
        }

        abort_if(!$valid, 422, 'لا توجد أرقام صالحة في العمود المحدد');
        return [$valid, $invalid, $path];
    }

    private function importPath(string $token): string
    {
        abort_unless(Str::isUuid($token), 422, 'رمز الملف غير صالح');
        return 'sms_imports/'.auth()->id().'_'.$token.'.json';   // مرتبط بالمستخدم الحالي
    }

    private function cell(mixed $v): string
    {
        if (is_float($v) && floor($v) == $v) return sprintf('%.0f', $v);
        return trim((string) $v);
    }

    private function senders(): array
    {
        return Cache::remember('nsms.senders', 300, fn () => $this->sms->senders());
    }
}
```

### 7.3 القوالب والمجموعات

```bash
php artisan make:controller SmsTemplateController
php artisan make:controller SmsGroupController
```

`app/Http/Controllers/SmsTemplateController.php`
```php
<?php
namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index()
    {
        return view('sms.templates', ['templates' => SmsTemplate::latest()->get()]);
    }

    public function store(Request $request)
    {
        SmsTemplate::create($this->data($request));
        return back()->with('success', 'تمت إضافة القالب');
    }

    public function update(Request $request, SmsTemplate $template)
    {
        $template->update($this->data($request));
        return back()->with('success', 'تم تحديث القالب');
    }

    public function destroy(SmsTemplate $template)
    {
        $template->delete();
        return back()->with('success', 'تم حذف القالب');
    }

    private function data(Request $request): array
    {
        return [
            'title'     => $request->validate(['title' => 'required|string|max:120'])['title'],
            'body'      => $request->validate(['body' => 'required|string|max:2000'])['body'],
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
```

`app/Http/Controllers/SmsGroupController.php`
```php
<?php
namespace App\Http\Controllers;

use App\Models\SmsGroup;
use Illuminate\Http\Request;

class SmsGroupController extends Controller
{
    public function index()
    {
        return view('sms.groups', ['groups' => SmsGroup::latest()->get()]);
    }

    public function store(Request $request)
    {
        SmsGroup::create($request->validate([
            'name'    => 'required|string|max:120',
            'numbers' => 'required|string',
        ]));
        return back()->with('success', 'تمت إضافة المجموعة');
    }

    public function update(Request $request, SmsGroup $group)
    {
        $group->update($request->validate([
            'name'    => 'required|string|max:120',
            'numbers' => 'required|string',
        ]));
        return back()->with('success', 'تم تحديث المجموعة');
    }

    public function destroy(SmsGroup $group)
    {
        $group->delete();
        return back()->with('success', 'تم حذف المجموعة');
    }
}
```

---

## 8. Routes

`routes/web.php`
```php
use App\Http\Controllers\{SmsController, SmsTemplateController, SmsGroupController};

Route::middleware(['auth', 'can:send-sms'])->prefix('dashboard/sms')->name('sms.')->group(function () {
    Route::get('/',      [SmsController::class, 'index'])->name('index');
    Route::get('/logs',  [SmsController::class, 'logs'])->name('logs');

    Route::post('/single',       [SmsController::class, 'single'])->name('single');
    Route::post('/bulk/summary', [SmsController::class, 'bulkSummary'])->name('bulk.summary');
    Route::post('/bulk/send',    [SmsController::class, 'bulkSend'])->name('bulk.send');

    Route::post('/excel/upload',  [SmsController::class, 'excelUpload'])->name('excel.upload');
    Route::post('/excel/summary', [SmsController::class, 'excelSummary'])->name('excel.summary');
    Route::post('/excel/send',    [SmsController::class, 'excelSend'])->name('excel.send');

    Route::resource('templates', SmsTemplateController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('groups',    SmsGroupController::class)->only(['index', 'store', 'update', 'destroy']);
});
```

> مسار الـ webhook الخاص بتقارير التسليم (`/sms/dlr`) من الرد السابق يبقى **خارج** هذه المجموعة لأنه لا يحتاج `auth`.

---

## 9. Views

> عدّل `layouts.app` واسم الـ section حسب لوحتك. يفترض الـ layout وجود `<meta name="csrf-token" content="{{ csrf_token() }}">` وكتلة `@stack('scripts')`.

### 9.1 `resources/views/sms/_composer.blade.php`

```blade
{{-- يستخدم داخل كل تبويب: @include('sms._composer', ['id' => 'single']) --}}
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">اسم المرسل</label>
        <select class="form-select" id="{{ $id }}-sender">
            @foreach($senders as $s) <option value="{{ $s }}">{{ $s }}</option> @endforeach
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label">قالب جاهز</label>
        <select class="form-select tpl-select" data-target="{{ $id }}-message">
            <option value="">— بدون قالب —</option>
            @foreach($templates as $t) <option value="{{ $t->body }}">{{ $t->title }}</option> @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">نص الرسالة</label>
        <textarea id="{{ $id }}-message" class="form-control sms-text" rows="5" maxlength="2000"></textarea>
        <small class="text-muted" id="{{ $id }}-counter"></small>
    </div>
</div>
```

### 9.2 `resources/views/sms/index.blade.php`

```blade
@extends('layouts.app')
@section('title', 'إرسال الرسائل النصية')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">إرسال الرسائل النصية</h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary fs-6">الرصيد: {{ $credits ?? '—' }}</span>
            <a href="{{ route('sms.templates.index') }}" class="btn btn-outline-secondary btn-sm">القوالب</a>
            <a href="{{ route('sms.groups.index') }}" class="btn btn-outline-secondary btn-sm">المجموعات</a>
            <a href="{{ route('sms.logs') }}" class="btn btn-outline-secondary btn-sm">السجل</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-single" type="button">فردي</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bulk" type="button">جماعي</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-excel" type="button">من ملف Excel</button></li>
            </ul>
        </div>

        <div class="card-body tab-content">

            {{-- ============ فردي ============ --}}
            <div class="tab-pane fade show active" id="tab-single">
                <div class="mb-3">
                    <label class="form-label">رقم الجوال</label>
                    <input type="text" id="single-mobile" class="form-control" placeholder="0599123456" dir="ltr">
                </div>
                @include('sms._composer', ['id' => 'single'])
                <button class="btn btn-primary mt-3" id="single-send">إرسال</button>
                <div id="single-result" class="mt-3 d-none"></div>
            </div>

            {{-- ============ جماعي ============ --}}
            <div class="tab-pane fade" id="tab-bulk">
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label">مجموعات محفوظة</label>
                        <select id="bulk-groups" class="form-select" multiple size="6">
                            @foreach($groups as $g) <option value="{{ $g->id }}">{{ $g->name }}</option> @endforeach
                        </select>
                        <small class="text-muted">Ctrl للاختيار المتعدد</small>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">أو أرقام إضافية (رقم في كل سطر أو بفواصل)</label>
                        <textarea id="bulk-numbers" class="form-control" rows="6" dir="ltr"></textarea>
                    </div>
                </div>
                @include('sms._composer', ['id' => 'bulk'])
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-outline-primary" id="bulk-summary">حساب الكلفة</button>
                    <button class="btn btn-primary" id="bulk-send">إرسال</button>
                </div>
                <div id="bulk-result" class="mt-3 d-none"></div>
            </div>

            {{-- ============ Excel ============ --}}
            <div class="tab-pane fade" id="tab-excel">
                <div class="alert alert-info">
                    الصف الأول في الملف = أسماء الأعمدة. استخدمها في الرسالة بين أقواس مثل <code>{name}</code>.
                    يفضّل أسماء أعمدة بدون مسافات (مثل <code>name</code> أو <code>amount</code>). والأفضل تنسيق عمود الجوال كـ Text.
                </div>
                <div class="input-group mb-3">
                    <input type="file" id="xl-file" class="form-control" accept=".xlsx,.xls,.csv">
                    <button class="btn btn-outline-primary" id="xl-upload">رفع وقراءة الملف</button>
                </div>
                <div id="xl-upload-result" class="d-none"></div>

                <div id="xl-step2" class="d-none">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">عمود رقم الجوال</label>
                            <select id="xl-mobile-col" class="form-select"></select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">المتغيرات (اضغط للإدراج في الرسالة)</label>
                            <div id="xl-chips" class="d-flex flex-wrap gap-1"></div>
                        </div>
                    </div>
                    <div class="table-responsive mb-3"><table class="table table-sm table-bordered" id="xl-preview"></table></div>

                    @include('sms._composer', ['id' => 'xl'])
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-outline-primary" id="xl-summary">حساب الكلفة</button>
                        <button class="btn btn-primary" id="xl-send">إرسال</button>
                    </div>
                    <div id="xl-result" class="mt-3 d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const routes = @json([
        'single'      => route('sms.single'),
        'bulkSummary' => route('sms.bulk.summary'),
        'bulkSend'    => route('sms.bulk.send'),
        'xlUpload'    => route('sms.excel.upload'),
        'xlSummary'   => route('sms.excel.summary'),
        'xlSend'      => route('sms.excel.send'),
    ]);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = id => document.getElementById(id);
    let xlToken = null;

    /* ---------- أدوات ---------- */
    async function post(url, body, isForm = false) {
        const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
        if (!isForm) headers['Content-Type'] = 'application/json';
        try {
            const res = await fetch(url, { method: 'POST', headers, body: isForm ? body : JSON.stringify(body) });
            const data = await res.json().catch(() => ({ message: 'استجابة غير صالحة من السيرفر' }));
            if (data.errors) data.message = Object.values(data.errors)[0][0];
            return { ok: res.ok, data };
        } catch (e) {
            return { ok: false, data: { message: 'تعذر الاتصال بالسيرفر' } };
        }
    }
    function show(el, ok, text) {
        el.className = 'alert mt-3 alert-' + (ok ? 'success' : 'danger');
        el.style.whiteSpace = 'pre-line';
        el.textContent = text;
    }
    function summaryText(d) {
        return [
            `عدد المستلمين: ${d.recipients}` + (d.invalid ? ` (تم تجاهل ${d.invalid} رقم غير صالح)` : ''),
            `المقبول: ${d.accepted} | المرفوض: ${d.rejected}`,
            d.segments != null ? `عدد الأجزاء: ${d.segments}` : null,
            `الكلفة: ${d.cost} | الرصيد المتاح: ${d.available ?? '—'}`,
            d.can_send ? 'الرصيد كافٍ للإرسال ✔' : 'الرصيد غير كافٍ ✖',
        ].filter(Boolean).join('\n');
    }
    const val = id => $(id).value;
    const busy = (btn, on) => { btn.disabled = on; };

    /* ---------- عدّاد الأحرف والقوالب ---------- */
    function countSms(t) {
        const uni = /[^\x00-\x7F]/.test(t);                 // تقدير: غير GSM = Unicode
        const one = uni ? 70 : 160, multi = uni ? 67 : 153;
        const parts = t.length === 0 ? 0 : (t.length <= one ? 1 : Math.ceil(t.length / multi));
        return `${t.length} حرف — ${parts} جزء تقريبًا (${uni ? 'Unicode' : 'نص عادي'}). الرقم الفعلي يظهر في "حساب الكلفة".`;
    }
    document.querySelectorAll('.sms-text').forEach(ta => {
        const out = $(ta.id.replace('-message', '-counter'));
        const upd = () => out.textContent = countSms(ta.value);
        ta.addEventListener('input', upd); upd();
    });
    document.querySelectorAll('.tpl-select').forEach(sel => sel.addEventListener('change', () => {
        if (!sel.value) return;
        const ta = $(sel.dataset.target); ta.value = sel.value;
        ta.dispatchEvent(new Event('input'));
    }));

    /* ---------- فردي ---------- */
    $('single-send').onclick = async e => {
        busy(e.target, true);
        const { ok, data } = await post(routes.single, {
            mobile: val('single-mobile'), message: val('single-message'), sender: val('single-sender'),
        });
        show($('single-result'), ok && data.ok, data.message || '');
        busy(e.target, false);
    };

    /* ---------- جماعي ---------- */
    const bulkPayload = () => ({
        group_ids: [...$('bulk-groups').selectedOptions].map(o => o.value),
        numbers: val('bulk-numbers'), message: val('bulk-message'), sender: val('bulk-sender'),
    });
    $('bulk-summary').onclick = async e => {
        busy(e.target, true);
        const { ok, data } = await post(routes.bulkSummary, bulkPayload());
        show($('bulk-result'), ok && data.can_send, ok ? summaryText(data) : data.message);
        busy(e.target, false);
    };
    $('bulk-send').onclick = async e => {
        if (!confirm('هل أنت متأكد من إرسال الرسالة لجميع المستلمين؟')) return;
        busy(e.target, true);
        const { ok, data } = await post(routes.bulkSend, bulkPayload());
        show($('bulk-result'), ok, data.message);
        busy(e.target, false);
    };

    /* ---------- Excel ---------- */
    $('xl-upload').onclick = async e => {
        const f = $('xl-file').files[0];
        if (!f) return show($('xl-upload-result'), false, 'اختر ملفًا أولًا');
        busy(e.target, true);
        const fd = new FormData(); fd.append('file', f);
        const { ok, data } = await post(routes.xlUpload, fd, true);
        busy(e.target, false);
        if (!ok) { $('xl-step2').classList.add('d-none'); return show($('xl-upload-result'), false, data.message); }

        xlToken = data.token;
        show($('xl-upload-result'), true, `تمت قراءة ${data.total} صف و ${data.columns.length} عمود.`);

        // اختيار عمود الجوال تلقائيًا
        const sel = $('xl-mobile-col'); sel.innerHTML = '';
        data.columns.forEach(c => sel.add(new Option(c, c)));
        const guess = data.columns.find(c => /mobile|phone|tel|جوال|موبايل|هاتف|رقم/i.test(c));
        if (guess) sel.value = guess;

        // أزرار المتغيرات
        const chips = $('xl-chips'); chips.innerHTML = '';
        data.columns.forEach(c => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'btn btn-sm btn-outline-secondary'; b.textContent = '{' + c + '}';
            b.onclick = () => {
                const ta = $('xl-message'), s = ta.selectionStart ?? ta.value.length;
                ta.value = ta.value.slice(0, s) + '{' + c + '}' + ta.value.slice(ta.selectionEnd ?? s);
                ta.focus(); ta.dispatchEvent(new Event('input'));
            };
            chips.appendChild(b);
        });

        // معاينة أول 5 صفوف
        const t = $('xl-preview'); t.innerHTML = '';
        const head = t.insertRow(); data.columns.forEach(c => { const th = document.createElement('th'); th.textContent = c; head.appendChild(th); });
        data.preview.forEach(r => { const tr = t.insertRow(); data.columns.forEach(c => tr.insertCell().textContent = r[c] ?? ''); });

        $('xl-step2').classList.remove('d-none');
    };
    const xlPayload = () => ({
        token: xlToken, mobile_column: val('xl-mobile-col'), message: val('xl-message'), sender: val('xl-sender'),
    });
    $('xl-summary').onclick = async e => {
        busy(e.target, true);
        const { ok, data } = await post(routes.xlSummary, xlPayload());
        show($('xl-result'), ok && data.can_send, ok ? summaryText(data) : data.message);
        busy(e.target, false);
    };
    $('xl-send').onclick = async e => {
        if (!confirm('هل أنت متأكد من الإرسال لجميع صفوف الملف؟')) return;
        busy(e.target, true);
        const { ok, data } = await post(routes.xlSend, xlPayload());
        show($('xl-result'), ok, data.message);
        busy(e.target, false);
    };
});
</script>
@endpush
```

### 9.3 `resources/views/sms/templates.blade.php`

```blade
@extends('layouts.app')
@section('title', 'قوالب الرسائل')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex justify-content-between mb-3">
        <h4>قوالب الرسائل</h4>
        <div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tplModal" onclick="editTpl()">+ قالب جديد</button>
            <a href="{{ route('sms.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="card"><div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>العنوان</th><th>النص</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @forelse($templates as $t)
                <tr>
                    <td>{{ $t->title }}</td>
                    <td style="white-space:pre-line">{{ Str::limit($t->body, 120) }}</td>
                    <td>{!! $t->is_active ? '<span class="badge bg-success">فعّال</span>' : '<span class="badge bg-secondary">معطّل</span>' !!}</td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tplModal"
                                onclick='editTpl(@json($t))'>تعديل</button>
                        <form method="POST" action="{{ route('sms.templates.destroy', $t) }}" class="d-inline"
                              onsubmit="return confirm('حذف القالب؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">لا توجد قوالب</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</div>

<div class="modal fade" id="tplModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content" dir="rtl">
    <form method="POST" id="tplForm" action="{{ route('sms.templates.store') }}">
        @csrf <span id="tplMethod"></span>
        <div class="modal-header"><h5 class="modal-title">قالب رسالة</h5></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">العنوان</label><input name="title" id="tpl-title" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">النص</label>
                <textarea name="body" id="tpl-body" class="form-control" rows="5" required></textarea>
                <small class="text-muted">للإرسال من Excel استخدم متغيرات مثل {name} و {amount}</small>
            </div>
            <div class="form-check"><input type="checkbox" name="is_active" value="1" id="tpl-active" class="form-check-input" checked>
                <label class="form-check-label" for="tpl-active">فعّال</label></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">حفظ</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button></div>
    </form>
</div></div></div>

@push('scripts')
<script>
function editTpl(t) {
    const f = document.getElementById('tplForm');
    if (t) {
        f.action = "{{ url('dashboard/sms/templates') }}/" + t.id;
        document.getElementById('tplMethod').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    } else {
        f.action = "{{ route('sms.templates.store') }}";
        document.getElementById('tplMethod').innerHTML = '';
    }
    document.getElementById('tpl-title').value = t ? t.title : '';
    document.getElementById('tpl-body').value  = t ? t.body  : '';
    document.getElementById('tpl-active').checked = t ? !!t.is_active : true;
}
</script>
@endpush
@endsection
```

### 9.4 `resources/views/sms/groups.blade.php`

نفس بنية القوالب بالضبط، مع تغيير الحقول: `name` و `numbers` (textarea، رقم في كل سطر)، والمسارات إلى `sms.groups.*`.

```blade
@extends('layouts.app')
@section('title', 'مجموعات الأرقام')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex justify-content-between mb-3">
        <h4>مجموعات الأرقام</h4>
        <div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#grpModal" onclick="editGrp()">+ مجموعة جديدة</button>
            <a href="{{ route('sms.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="card"><div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>الاسم</th><th>عدد الأرقام</th><th></th></tr></thead>
            <tbody>
            @forelse($groups as $g)
                <tr>
                    <td>{{ $g->name }}</td>
                    <td>{{ count(preg_split('/[\s,;،]+/u', $g->numbers, -1, PREG_SPLIT_NO_EMPTY)) }}</td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#grpModal"
                                onclick='editGrp(@json($g))'>تعديل</button>
                        <form method="POST" action="{{ route('sms.groups.destroy', $g) }}" class="d-inline"
                              onsubmit="return confirm('حذف المجموعة؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted">لا توجد مجموعات</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</div>

<div class="modal fade" id="grpModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content" dir="rtl">
    <form method="POST" id="grpForm" action="{{ route('sms.groups.store') }}">
        @csrf <span id="grpMethod"></span>
        <div class="modal-header"><h5 class="modal-title">مجموعة أرقام</h5></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">اسم المجموعة</label><input name="name" id="grp-name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">الأرقام (رقم في كل سطر)</label>
                <textarea name="numbers" id="grp-numbers" class="form-control" rows="8" dir="ltr" required></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">حفظ</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button></div>
    </form>
</div></div></div>

@push('scripts')
<script>
function editGrp(g) {
    const f = document.getElementById('grpForm');
    if (g) {
        f.action = "{{ url('dashboard/sms/groups') }}/" + g.id;
        document.getElementById('grpMethod').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    } else {
        f.action = "{{ route('sms.groups.store') }}";
        document.getElementById('grpMethod').innerHTML = '';
    }
    document.getElementById('grp-name').value = g ? g.name : '';
    document.getElementById('grp-numbers').value = g ? g.numbers : '';
}
</script>
@endpush
@endsection
```

### 9.5 `resources/views/sms/logs.blade.php`

```blade
@extends('layouts.app')
@section('title', 'سجل الرسائل')

@section('content')
<div class="container-fluid" dir="rtl">
    <div class="d-flex justify-content-between mb-3">
        <h4>سجل الرسائل</h4>
        <a href="{{ route('sms.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr>
                <th>التاريخ</th><th>المستخدم</th><th>النوع</th><th>المرسل</th><th>المستلم</th>
                <th>الرسالة</th><th>الحالة</th><th>الكلفة</th><th>التسليم</th>
            </tr></thead>
            <tbody>
            @foreach($logs as $l)
                <tr>
                    <td class="text-nowrap">{{ $l->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $l->user?->name ?? '—' }}</td>
                    <td>{{ ['single' => 'فردي', 'bulk' => 'جماعي', 'excel' => 'Excel'][$l->type] ?? $l->type }}</td>
                    <td>{{ $l->sender }}</td>
                    <td dir="ltr">{{ $l->mobile ?? $l->recipients.' مستلم' }}</td>
                    <td style="max-width:300px;white-space:pre-line">{{ Str::limit($l->message, 80) }}</td>
                    <td>
                        @if($l->status === 'sent') <span class="badge bg-success">أُرسل</span>
                        @else <span class="badge bg-danger" title="{{ $l->error }}">فشل</span> @endif
                    </td>
                    <td>{{ $l->cost ?? '—' }}</td>
                    <td>{{ $l->dlr_status ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
```

---

## 10. القائمة الجانبية والصلاحيات

الرابط في القائمة:
```blade
@can('send-sms')
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('sms.*') ? 'active' : '' }}" href="{{ route('sms.index') }}">
        <i class="bi bi-chat-dots"></i> الرسائل النصية
    </a>
</li>
@endcan
```

الصلاحية في `app/Providers/AppServiceProvider.php` (عدّلها حسب نظام الأدوار عندك):
```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('send-sms', fn ($user) => (bool) ($user->is_admin ?? false));
}
```

---

## 11. التشغيل

```bash
php artisan migrate
php artisan queue:work --tries=1 --timeout=900
```

- على السيرفر شغّل `queue:work` تحت **Supervisor** حتى لا يتوقف.
- بعد أي تعديل على كود الـ Job نفّذ `php artisan queue:restart`.
- تنظيف ملفات الاستيراد المهجورة، في `routes/console.php` (Laravel 11+) أو `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::call(function () {
    foreach (Storage::files('sms_imports') as $f) {
        if (Storage::lastModified($f) < now()->subDay()->timestamp) Storage::delete($f);
    }
})->daily();
```

### قوالب افتراضية (اختياري)

```bash
php artisan make:seeder SmsTemplateSeeder
```
```php
public function run(): void
{
    $items = [
        ['title' => 'ترحيب',        'body' => 'مرحبًا {name}، يسعدنا انضمامك إلينا.'],
        ['title' => 'تذكير بدفعة',  'body' => 'عزيزي {name}، نذكّرك بسداد مبلغ {amount} قبل {date}.'],
        ['title' => 'شكر على التبرع','body' => 'شكرًا {name} على تبرعك الكريم. جزاك الله خيرًا.'],
    ];
    foreach ($items as $i) \App\Models\SmsTemplate::firstOrCreate(['title' => $i['title']], $i);
}
```

---

## 12. اختبار سريع (Feature Test)

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsSingleTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_send_logs_result(): void
    {
        Gate::define('send-sms', fn () => true);
        config(['services.nsms.base_url' => 'https://send.nsms.ps/api/rest/v2', 'services.nsms.token' => 't']);

        Http::fake([
            '*/senders'       => Http::response(['status' => true, 'code' => 1001, 'data' => ['senders' => ['MyBrand']]]),
            '*/credits'       => Http::response(['status' => true, 'code' => 1001, 'data' => ['credits' => 10]]),
            '*/messages/send' => Http::response(['status' => true, 'code' => 1001, 'data' => ['provider_response' => [
                'accepted' => 1, 'rejected' => 0, 'total_cost' => 1, 'request_id' => 'abc123']]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('sms.single'), ['mobile' => '0599123456', 'message' => 'Hello', 'sender' => 'MyBrand'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('sms_logs', ['mobile' => '970599123456', 'status' => 'sent', 'request_id' => 'abc123']);
    }
}
```

---

## 13. ملاحظات مهمة

- **رقم الجوال في Excel:** الأرقام التي تبدأ بصفر يحذف Excel الصفر منها (`599123456`). الكود يعالج ذلك (9 أرقام تبدأ بـ 5 تُسبق بكود الدولة)، لكن الأفضل تنسيق العمود كنص. تأكد من `NSMS_COUNTRY_CODE` حسب بلد أرقامك.
- **التواريخ في Excel:** تأتي كرقم تسلسلي. اكتبها كنص (`2026-10-15`) في الملف.
- **ملفات CSV:** احفظها بترميز **UTF-8** وإلا ستظهر العربية مشوّهة. الأفضل `.xlsx`.
- **أسماء الأعمدة والمتغيرات:** يجب أن تتطابق تمامًا (حساسة لحالة الأحرف). تجنب المسافات في أسماء الأعمدة لأن التوثيق يوضّح المتغيرات بصيغة `{name}` و `{city}` فقط.
- **التكرار في Excel:** لا يُحذف تكرار الأرقام لأن لكل صف متغيرات مختلفة. نظّف الملف قبل الرفع إن لزم.
- **حجم الدفعات:** الجماعي 500 رقم/طلب، وExcel حتى 1000 صف/طلب (الحد الأقصى في الـ API هو 5000). حدّ الطلبات للإرسال الجماعي المتغير 30/دقيقة، فلا تصغّر الدفعة كثيرًا.
- **التسليم (DLR):** كل طلب يرجع `request_id` واحدًا حتى لو احتوى أرقامًا كثيرة. السجل هنا لكل طلب، وأرقام الـ DLR الفردية تحتاج جدول `sms_log_recipients` إن أردت تتبع كل رقم على حدة.
- **التوكن:** إذا ظهر الخطأ `1003` في السجل فالتوكن انتهى، سجّل دخول جديد (مرحلتان مع OTP) وحدّث `.env` ثم `php artisan config:clear`.
- **ألوان/تصميم:** الـ views بصيغة Bootstrap 5 قياسية، فإذا لوحتك AdminLTE أو Tailwind غيّر الأصناف فقط، والـ JS ثابت.

## 14. هيكل الملفات النهائي

```
app/
├── Http/Controllers/{SmsController,SmsTemplateController,SmsGroupController}.php
├── Imports/SmsRowsImport.php
├── Jobs/SendBulkSmsJob.php
├── Models/{SmsLog,SmsTemplate,SmsGroup}.php
└── Services/NsmsClient.php
database/migrations/xxxx_create_sms_tables.php
resources/views/sms/{index,_composer,templates,groups,logs}.blade.php
routes/web.php            (مجموعة sms)
config/services.php       (nsms)
```
