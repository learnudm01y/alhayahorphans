<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DeathReasonDataTable;
use App\Http\Controllers\Controller;
use App\Models\DeathReason;
use Illuminate\Http\Request;

class DeathReasonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(DeathReasonDataTable $dataTable)
    {
         return $dataTable->render('admin.dashboard.category_management.deathReason');
    }

  /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        DeathReason::create([
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

        $DeathReason = DeathReason::findOrFail($id);
        $DeathReason->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  حالة الوفاة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $DeathReason = DeathReason::findOrFail($id);
        $DeathReason->delete();

        return redirect()->back()->with('success', 'تم حذف  حالة الوفاة بنجاح');
    }
}
