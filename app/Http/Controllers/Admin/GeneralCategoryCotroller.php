<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\GeneralCategoryDataTable;
use App\Http\Controllers\Controller;
use App\Models\GeneralCategory;
use Illuminate\Http\Request;

class GeneralCategoryCotroller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(GeneralCategoryDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.generalcategory');
    }
  /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        GeneralCategory::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  القسم بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $GeneralCategory = GeneralCategory::findOrFail($id);
        $GeneralCategory->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  القسم بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $GeneralCategory = GeneralCategory::findOrFail($id);
        $GeneralCategory->delete();

        return redirect()->back()->with('success', 'تم حذف  القسم بنجاح');
    }
}
