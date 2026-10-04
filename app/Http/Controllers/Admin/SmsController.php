<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class SmsController extends Controller
{
    public function __construct(private NsmsClient $sms) {}

    /* ===================== الشاشة ===================== */

    public function index()
    {
        return view('admin.sms.index', [
            'templates' => SmsTemplate::where('is_active', true)->orderBy('title')->get(),
            'groups'    => SmsGroup::orderBy('name')->get(),
            'senders'   => $this->senders(),
            'credits'   => $this->sms->credits(),
        ]);
    }

    public function logs()
    {
        return view('admin.sms.logs', [
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
        HeadingRowFormatter::default(HeadingRowFormatter::FORMATTER_NONE);
        try {
            $sheets = Excel::toArray(new SmsRowsImport, $request->file('file'));
        } finally {
            HeadingRowFormatter::reset();   // يعيد الوضع الافتراضي من الإعدادات (slug)
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
        // لا نخزّن النتيجة الفارغة (فشل التوكن/الشبكة) حتى لا تعلق 5 دقائق
        $cached = Cache::get('nsms.senders');
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $list = $this->sms->senders();
        if ($list !== []) {
            Cache::put('nsms.senders', $list, 300);
        }

        return $list;
    }
}
