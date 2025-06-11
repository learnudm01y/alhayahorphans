<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\TypeOfGuaranteeDataTable;
use App\Http\Controllers\Controller;
use App\Models\TypeOfGuarantee;
use Illuminate\Http\Request;

class TypeOfGuaranteeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TypeOfGuaranteeDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.type_of_guarantee');
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        TypeOfGuarantee::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  نوع الكفالة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $TypeOfGuarantee = TypeOfGuarantee::findOrFail($id);
        $TypeOfGuarantee->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  نوع الكفالة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $TypeOfGuarantee = TypeOfGuarantee::findOrFail($id);
        $TypeOfGuarantee->delete();

        return redirect()->back()->with('success', 'تم حذف  نوع الكفالة بنجاح');
    }
}
