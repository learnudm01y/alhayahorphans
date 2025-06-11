<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\EmploymentDataTable;
use App\Http\Controllers\Controller;
use App\Models\Employment;
use Illuminate\Http\Request;

class EmploymentCotroller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(EmploymentDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.Employment');
    }

  /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        Employment::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  الحالة الوظيفية بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $Employment = Employment::findOrFail($id);
        $Employment->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  الحالة الوظيفية بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $Employment = Employment::findOrFail($id);
        $Employment->delete();

        return redirect()->back()->with('success', 'تم حذف  الحالة الوظيفية بنجاح');
    }
}
