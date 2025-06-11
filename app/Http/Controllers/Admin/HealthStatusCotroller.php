<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\HealthStatusDataTable;
use App\Http\Controllers\Controller;
use App\Models\HealthStatus;
use Illuminate\Http\Request;

class HealthStatusCotroller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(HealthStatusDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.healthstatus');
    }

   /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        HealthStatus::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  الحالة الصحية بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $HealthStatus = HealthStatus::findOrFail($id);
        $HealthStatus->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  الحالة الصحية بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $HealthStatus = HealthStatus::findOrFail($id);
        $HealthStatus->delete();

        return redirect()->back()->with('success', 'تم حذف  الحالة الصحية بنجاح');
    }
}
