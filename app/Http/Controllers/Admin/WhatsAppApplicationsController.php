<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Data;
use App\Models\WhatsAppLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppApplicationsController extends Controller
{
    /** قائمة طلبات التسجيل عبر واتساب — صف لكل رقم هاتف تواصل مع البوت */
    public function index(Request $request)
    {
        $query = WhatsAppLog::query()
            ->select('phone', DB::raw('MAX(created_at) as last_at'), DB::raw('COUNT(*) as messages_count'))
            ->groupBy('phone');

        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            $query->where('phone', 'like', '%' . $search . '%');
        }

        $phones = $query->orderByDesc('last_at')->paginate(20)->withQueryString();

        $rows = $phones->getCollection()->map(function ($row) {
            $file = $this->fileForPhone((string) $row->phone);

            return [
                'phone' => (string) $row->phone,
                'last_at' => (string) $row->last_at,
                'messages_count' => (int) $row->messages_count,
                'file_id' => $file['id'] ?? null,
                'name' => $file['name'] ?? null,
            ];
        });

        return view('admin.whatsapp.applications', [
            'rows' => $rows,
            'phones' => $phones,
        ]);
    }

    /** تفاصيل طلب: ملخص القناة + الملف المسجّل + المرفقات + المحادثة */
    public function show(string $phone)
    {
        abort_unless(ctype_digit($phone), 404);

        $logs = WhatsAppLog::where('phone', $phone)->orderByDesc('id')->paginate(50);
        $file = $this->fileForPhone($phone);

        $attachments = collect();

        if ($file !== null && $file['id'] !== null) {
            $attachments = $this->attachmentsForFile($file['id']);
        }

        $summary = WhatsAppLog::where('phone', $phone)
            ->selectRaw('COUNT(*) as messages_count, MIN(created_at) as first_at, MAX(created_at) as last_at')
            ->first();

        return view('admin.whatsapp.show', [
            'phone' => $phone,
            'logs' => $logs,
            'conversation' => $logs->getCollection()->reverse(),
            'file' => $file,
            'attachments' => $attachments,
            'messagesCount' => (int) ($summary->messages_count ?? 0),
            'firstAt' => $summary->first_at ?? null,
            'lastAt' => $summary->last_at ?? null,
        ]);
    }

    /**
     * رقم الملف الناتج عن تسجيل ناجح من هذا الرقم:
     * يُستخرج من رسالة النجاح الواردة "تم التسجيل بنجاح ... رقم الملف: XXXXXX".
     */
    private function fileForPhone(string $phone): ?array
    {
        $log = WhatsAppLog::where('phone', $phone)
            ->where('direction', 'outbound')
            ->where('text', 'like', '%تم التسجيل بنجاح%')
            ->orderByDesc('id')
            ->first(['text']);

        if ($log === null || !preg_match('/رقم الملف:\s*([0-9]{6})/u', (string) $log->text, $matches)) {
            return null;
        }

        $fileId = $matches[1];
        $variants = [$fileId, ltrim($fileId, '0')];
        $data = Data::whereIn('file_id_number', $variants)->first();

        if ($data === null) {
            return ['id' => $fileId, 'name' => null, 'data_id' => null];
        }

        return [
            'id' => str_pad((string) $data->file_id_number, 6, '0', STR_PAD_LEFT),
            'name' => trim(((string) $data->data_first_name) . ' ' . ((string) $data->data_family_name)),
            'data_id' => $data->id,
        ];
    }

    /** مرفقات الملف: تُطابق على المجلد المقطوع في المسار (مبদون وأصفار بادئة) */
    private function attachmentsForFile(string $fileId)
    {
        $variants = array_unique(array_filter([$fileId, ltrim($fileId, '0')]));

        return Attachment::where(function ($query) use ($variants) {
            foreach ($variants as $variant) {
                $query->orWhere('file_path', 'like', '%/' . $variant . '/%');
            }
        })->orderByDesc('id')->get();
    }
}
