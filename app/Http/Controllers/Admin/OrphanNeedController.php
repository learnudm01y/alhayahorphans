<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\OrphanNeedDataTable;
use App\Http\Controllers\Controller;
use App\Models\OrphanNeed;
use Illuminate\Http\Request;

class OrphanNeedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(OrphanNeedDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.orphan_needs');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        OrphanNeed::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة احتياج المكفول بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $orphanNeed = OrphanNeed::findOrFail($id);
        $orphanNeed->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث احتياج المكفول بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $orphanNeed = OrphanNeed::findOrFail($id);
        $orphanNeed->delete();

        return redirect()->back()->with('success', 'تم حذف احتياج المكفول بنجاح');
    }
}
