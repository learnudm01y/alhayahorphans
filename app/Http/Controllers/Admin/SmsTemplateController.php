<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index()
    {
        return view('admin.sms.templates', ['templates' => SmsTemplate::latest()->get()]);
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
