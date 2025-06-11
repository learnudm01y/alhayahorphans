<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\AcademicDegreeDataTable;
use App\Http\Controllers\Controller;
use App\Models\AcademicDegree;
use Illuminate\Http\Request;

class AcademicDegreeController extends Controller
{
    public function academicdegree(AcademicDegreeDataTable $dataTable){
        return $dataTable->render('admin.dashboard.category_management.academicdegree');
    }

    public function createAcademicDegree(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        AcademicDegree::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة الدرجة العلمية بنجاح');
    }
    public function academicDegreeUpdate(Request $request, $id)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $academicDegree = AcademicDegree::findOrFail($id);
        $academicDegree->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث الدرجة العلمية بنجاح');
    }
    public function academicDegreeDestroy($id)
    {
        $academicDegree = AcademicDegree::findOrFail($id);
        $academicDegree->delete();

        return redirect()->back()->with('success', 'تم حذف الدرجة العلمية بنجاح');
    }
}
