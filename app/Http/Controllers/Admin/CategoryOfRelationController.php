<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CategoryOfRelationDataTable;
use App\Http\Controllers\Controller;
use App\Models\CategoryOfRelation;
use Illuminate\Http\Request;

class CategoryOfRelationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CategoryOfRelationDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.categoryOfRelation');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'attribute' => 'required|string|max:255',
        ]);

        CategoryOfRelation::create([
            'attribute' => $request->attribute,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  صلة القرابة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'attribute' => 'required|string|max:255',
        ]);

        $CategoryOfRelation = CategoryOfRelation::findOrFail($id);
        $CategoryOfRelation->update([
            'attribute' => $request->attribute,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  صلة القرابة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $CategoryOfRelation = CategoryOfRelation::findOrFail($id);
        $CategoryOfRelation->delete();

        return redirect()->back()->with('success', 'تم حذف  صلة القرابة بنجاح');
    }
}
