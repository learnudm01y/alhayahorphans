<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DocumentTypeDataTable;
use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'pref' => 'required|string|max:255|unique:document_types,pref',
        ]);

        try {
            DocumentType::create([
                'description' => $request->description,
                'pref' => $request->pref,
            ]);
            return redirect()->back()->with('success', 'تمت إضافة نوع الوثيقة بنجاح');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'حدث خطأ أثناء إضافة نوع الوثيقة');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $documentType = DocumentType::findOrFail($id);

        // تتبع القيم القادمة من Ajax
        Log::info('DocumentType update request', [
            'id' => $id,
            'portal' => $request->input('portal'),
            'enabled' => $request->input('enabled'),
            'all' => $request->all()
        ]);

        $portal = $request->input('portal');
        $enabled = $request->input('enabled');

        if ($portal !== null && $enabled !== null) {
            $col = $portal . '_enabled';
            if (in_array($col, ['basic_enabled', 'deceased_enabled', 'family_enabled'])) {
                $documentType->$col = (int)$enabled; // استخدم int لضمان 0/1
                $documentType->save();
                Log::info('DocumentType updated', [
                    'id' => $id,
                    'col' => $col,
                    'new_value' => $documentType->$col
                ]);
                return response()->json(['success' => true, 'col' => $col, 'value' => $documentType->$col]);
            } else {
                Log::warning('Invalid column for portal', ['portal' => $portal, 'col' => $col]);
                return response()->json(['success' => false, 'error' => 'عمود غير صالح']);
            }
        }

        // تحديث حالة is_required عبر AJAX
        if ($request->has('is_required')) {
            $documentType->is_required = (int)$request->input('is_required');
            $documentType->save();
            return response()->json(['success' => true, 'is_required' => $documentType->is_required]);
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'pref' => 'required|string|max:255|unique:document_types,pref,' . $id,
        ]);

        try {
            $documentType->update([
                'description' => $request->description,
                'pref' => $request->pref,
            ]);
            return redirect()->back()->with('success', 'تم تحديث نوع الوثيقة بنجاح');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'حدث خطأ أثناء تحديث نوع الوثيقة');
        }
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
