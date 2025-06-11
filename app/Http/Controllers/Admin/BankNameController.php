<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\BankNameDataTable;
use App\Http\Controllers\Controller;
use App\Models\BankName;
use Illuminate\Http\Request;

class BankNameController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(BankNameDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.bankName');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        BankName::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  حالة المساعدة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $BankName = BankName::findOrFail($id);
        $BankName->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  حالة المساعدة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $BankName = BankName::findOrFail($id);
        $BankName->delete();

        return redirect()->back()->with('success', 'تم حذف  حالة المساعدة بنجاح');
    }
}
