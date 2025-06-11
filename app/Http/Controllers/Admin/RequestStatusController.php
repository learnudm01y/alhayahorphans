<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\RequestStatusDataTable;
use App\Http\Controllers\Controller;
use App\Models\RequestStatus;
use Illuminate\Http\Request;

class RequestStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(RequestStatusDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.request_status');
    }

/**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        RequestStatus::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  حالة الطلب بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $RequestStatus = RequestStatus::findOrFail($id);
        $RequestStatus->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  حالة الطلب بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $RequestStatus = RequestStatus::findOrFail($id);
        $RequestStatus->delete();

        return redirect()->back()->with('success', 'تم حذف  حالة الطلب بنجاح');
    }

}
