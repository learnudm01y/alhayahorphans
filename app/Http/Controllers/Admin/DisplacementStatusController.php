<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DisplacementStatusDataTable;
use App\Http\Controllers\Controller;
use App\Models\DisplacementStatus;
use Illuminate\Http\Request;

class DisplacementStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(DisplacementStatusDataTable $dataTable)
    {
          return $dataTable->render('admin.dashboard.category_management.DisplacementStatus');
    }

     /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        DisplacementStatus::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  حالة الوفاة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $DisplacementStatus = DisplacementStatus::findOrFail($id);
        $DisplacementStatus->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  حالة الوفاة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $DisplacementStatus = DisplacementStatus::findOrFail($id);
        $DisplacementStatus->delete();

        return redirect()->back()->with('success', 'تم حذف  حالة الوفاة بنجاح');
    }
}
