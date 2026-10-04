<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsGroup;
use Illuminate\Http\Request;

class SmsGroupController extends Controller
{
    public function index()
    {
        return view('admin.sms.groups', ['groups' => SmsGroup::latest()->get()]);
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
