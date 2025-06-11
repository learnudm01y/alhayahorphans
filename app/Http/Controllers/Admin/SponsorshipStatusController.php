<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\SponsorshipStatusDataTable;
use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Models\SponsorshipStatus;
use Illuminate\Http\Request;

class SponsorshipStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SponsorshipStatusDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.sponsorshipstatus');
    }
   /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        SponsorshipStatus::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  حالة الكفالة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $SponsorshipStatus = SponsorshipStatus::findOrFail($id);
        $SponsorshipStatus->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  حالة الكفالة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $SponsorshipStatus = SponsorshipStatus::findOrFail($id);
        $SponsorshipStatus->delete();

        return redirect()->back()->with('success', 'تم حذف  حالة الكفالة بنجاح');
    }
}
