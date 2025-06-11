<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DocumentTypeDataTable;
use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use PhpParser\Comment\Doc;

class DocumentTypeCotroller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(DocumentTypeDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.category_management.documentType');
    }

      /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        DocumentType::create([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تمت إضافة  نوع الوثيقة بنجاح');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
         $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $DocumentType = DocumentType::findOrFail($id);
        $DocumentType->update([
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث  نوع الوثيقة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $DocumentType = DocumentType::findOrFail($id);
        $DocumentType->delete();

        return redirect()->back()->with('success', 'تم حذف  نوع الوثيقة بنجاح');
    }
}
