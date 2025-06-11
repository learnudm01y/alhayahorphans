<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ProvinceDataTable;
use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ProvinceDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.province');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        Province::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  المحافظة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $Province = Province::findOrFail($id);
        $Province->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  المحافظة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $Province = Province::findOrFail($id);
        $Province->delete();

        return redirect()->back()->with('success', 'تم حذف  المحافظة بنجاح');
    }
}
