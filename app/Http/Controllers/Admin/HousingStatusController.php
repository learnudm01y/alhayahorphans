<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\HousingStatusDataTable;
use App\Http\Controllers\Controller;
use App\Models\HousingStatus;
use Illuminate\Http\Request;

class HousingStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(HousingStatusDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.housing_status');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        HousingStatus::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  حالة المنزل بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $HousingStatus = HousingStatus::findOrFail($id);
        $HousingStatus->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  حالة المنزل بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $HousingStatus = HousingStatus::findOrFail($id);
        $HousingStatus->delete();

        return redirect()->back()->with('success', 'تم حذف  حالة المنزل بنجاح');
    }
}
