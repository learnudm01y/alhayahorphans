<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CityDataTable;
use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CityDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.city');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'city' => 'required|string|max:255',
        ]);

        City::create([
            'city' => $request->city,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  اسم المدينة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'city' => 'required|string|max:255',
        ]);

        $City = City::findOrFail($id);
        $City->update([
            'city' => $request->city,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  اسم المدينة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $City = City::findOrFail($id);
        $City->delete();

        return redirect()->back()->with('success', 'تم حذف  اسم المدينة بنجاح');
    }
}
