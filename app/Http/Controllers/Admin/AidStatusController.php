<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\AidStatusDataTable;
use App\Http\Controllers\Controller;
use App\Models\AidStatus;
use Illuminate\Http\Request;

class AidStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(AidStatusDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.aidstatus');
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        AidStatus::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  حالة المساعدة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $AidStatus = AidStatus::findOrFail($id);
        $AidStatus->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  حالة المساعدة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $AidStatus = AidStatus::findOrFail($id);
        $AidStatus->delete();

        return redirect()->back()->with('success', 'تم حذف  حالة المساعدة بنجاح');
    }
}
