<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\TypeOfAccommodationDataTable;
use App\Http\Controllers\Controller;
use App\Models\TypeOfAccommodation;
use Illuminate\Http\Request;
use Mockery\Matcher\Type;

class TypeOfAccommodationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TypeOfAccommodationDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.TypeOfAccommodation');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        TypeOfAccommodation::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  نوع السكن بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $TypeOfAccommodation = TypeOfAccommodation::findOrFail($id);
        $TypeOfAccommodation->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  نوع السكن بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $TypeOfAccommodation = TypeOfAccommodation::findOrFail($id);
        $TypeOfAccommodation->delete();

        return redirect()->back()->with('success', 'تم حذف  نوع السكن بنجاح');
    }
}
