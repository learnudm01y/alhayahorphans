<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CurrencyTypeDataTable;
use App\Http\Controllers\Controller;
use App\Models\CurrencyType;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard\Currency;

class CurrencyTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CurrencyTypeDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.currencytype');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        CurrencyType::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  العملة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $CurrencyType = CurrencyType::findOrFail($id);
        $CurrencyType->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  العملة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $CurrencyType = CurrencyType::findOrFail($id);
        $CurrencyType->delete();

        return redirect()->back()->with('success', 'تم حذف  العملة بنجاح');
    }
}
