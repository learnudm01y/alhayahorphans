<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CreativityAspectDataTable;
use App\Http\Controllers\Controller;
use App\Models\CreativityAspect;
use Illuminate\Http\Request;

class CreativityAspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CreativityAspectDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.creativity_aspects');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        CreativityAspect::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة جانب الإبداع بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $creativityAspect = CreativityAspect::findOrFail($id);
        $creativityAspect->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث جانب الإبداع بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $creativityAspect = CreativityAspect::findOrFail($id);
        $creativityAspect->delete();

        return redirect()->back()->with('success', 'تم حذف جانب الإبداع بنجاح');
    }
}
