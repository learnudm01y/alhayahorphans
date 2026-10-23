<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppFaq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class WhatsAppKnowledgeController extends Controller
{
    /** قاعدة المعرفة: النص العام (faq.md) + جدول الأسئلة والأجوبة */
    public function index(Request $request)
    {
        $editing = $request->query('faq') !== null
            ? WhatsAppFaq::find((int) $request->query('faq'))
            : null;

        return view('admin.whatsapp.knowledge', [
            'content' => $this->readKnowledgeFile(),
            'faqs' => WhatsAppFaq::query()->orderBy('sort_order')->orderBy('id')->paginate(20),
            'editing' => $editing,
        ]);
    }

    /** حفظ النص العام (يفتح وينتهي بقاعدة المعرفة) */
    public function saveGeneral(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:100000'],
        ]);

        File::ensureDirectoryExists(dirname($this->knowledgePath()));
        File::put($this->knowledgePath(), $data['content']);

        return redirect()->route('admin.whatsapp.knowledge')->with('success', 'حُفظ النص العام لقاعدة المعرفة.');
    }

    /** إضافة سؤال وجواب */
    public function store(Request $request)
    {
        WhatsAppFaq::create($this->validateFaq($request));

        return redirect()->route('admin.whatsapp.knowledge')->with('success', 'أُضيف السؤال إلى قاعدة المعرفة.');
    }

    /** تعديل سؤال (شامل تفعيل/تعطيله) */
    public function update(Request $request, WhatsAppFaq $faq)
    {
        $faq->update($this->validateFaq($request));

        return redirect()->route('admin.whatsapp.knowledge')->with('success', 'حُدّث السؤال.');
    }

    /** حذف سؤال */
    public function destroy(WhatsAppFaq $faq)
    {
        $faq->delete();

        return redirect()->route('admin.whatsapp.knowledge')->with('success', 'حُذف السؤال من قاعدة المعرفة.');
    }

    private function validateFaq(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:3000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'question' => trim($data['question']),
            'answer' => trim($data['answer']),
            'is_active' => $request->input('is_active') === '1',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function knowledgePath(): string
    {
        return storage_path('app/knowledge/faq.md');
    }

    private function readKnowledgeFile(): string
    {
        return File::exists($this->knowledgePath()) ? (string) File::get($this->knowledgePath()) : '';
    }
}
