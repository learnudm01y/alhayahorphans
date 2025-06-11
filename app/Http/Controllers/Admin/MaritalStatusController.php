<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\MaritalStatusDataTable;
use App\Http\Controllers\Controller;
use App\Models\MaritalStatus;
use Illuminate\Http\Request;

class MaritalStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(MaritalStatusDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.marital_status');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        MaritalStatus::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  الحالة الإجتماعية بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $MaritalStatus = MaritalStatus::findOrFail($id);
        $MaritalStatus->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  الحالة الإجتماعية بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $MaritalStatus = MaritalStatus::findOrFail($id);
        $MaritalStatus->delete();

        return redirect()->back()->with('success', 'تم حذف  الحالة الإجتماعية بنجاح');
    }
}
