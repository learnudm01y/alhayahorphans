<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CityDataTable;
use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CityDataTable $dataTable)
    {
        $provinces = \App\Models\Province::orderBy('description')->get();
        return $dataTable->render('admin.dashboard.category_management.city', compact('provinces'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'city' => 'required|string|max:255|unique:city,city',
            'province_id' => 'nullable|exists:provinces,id',
        ], [
            'city.required' => 'يرجى إدخال اسم المدينة',
            'city.unique' => 'اسم المدينة موجود بالفعل في النظام',
            'province_id.exists' => 'المحافظة المحددة غير صالحة',
        ]);

        City::create([
            'city' => trim($request->city),
            'province_id' => $request->province_id ?: null,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة المدينة بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'city' => 'required|string|max:255|unique:city,city,' . $id,
            'province_id' => 'nullable|exists:provinces,id',
        ], [
            'city.required' => 'يرجى إدخال اسم المدينة',
            'city.unique' => 'اسم المدينة موجود بالفعل في النظام',
            'province_id.exists' => 'المحافظة المحددة غير صالحة',
        ]);

        $city = City::findOrFail($id);
        $city->update([
            'city' => trim($request->city),
            'province_id' => $request->province_id ?: null,
        ]);

        return redirect()->back()->with('success', 'تم تحديث بيانات المدينة بنجاح');
    }

    /**
     * Quick AJAX update for city province.
     */
    public function updateProvince(Request $request, string $id)
    {
        $request->validate([
            'province_id' => 'nullable|exists:provinces,id',
        ]);

        $city = City::findOrFail($id);
        $city->update([
            'province_id' => $request->province_id ?: null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المحافظة بنجاح',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $city = City::findOrFail($id);

        $isReferencedInData = DB::table('data')
            ->where('data_city', $city->id)
            ->orWhere('city', $city->id)
            ->orWhere('city', $city->city)
            ->exists();

        if ($isReferencedInData) {
            return redirect()->back()->with('error', 'لا يمكن حذف هذه المدينة نظراً لوجود سجلات مرتبطة بها في النظام');
        }

        $city->delete();

        return redirect()->back()->with('success', 'تم حذف المدينة بنجاح');
    }
}
