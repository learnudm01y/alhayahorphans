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
            'file_type' => 'nullable|in:document,image',
        ]);

        try {
            DocumentType::create([
                'description' => $request->description,
                'pref' => $request->pref,
                'file_type' => $request->file_type ?? 'document',
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
            'file_type' => $request->input('file_type'),
            'all' => $request->all()
        ]);

        // تحديث نوع الملف (صورة/وثيقة) عبر AJAX
        if ($request->has('file_type') && !$request->has('description')) {
            $fileType = in_array($request->input('file_type'), ['image', 'document']) ? $request->input('file_type') : 'document';
            $documentType->file_type = $fileType;
            $documentType->save();
            return response()->json(['success' => true, 'file_type' => $documentType->file_type]);
        }

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

        // تحديث حالة required لأي بوابة عبر AJAX
        if ($request->has('required_portal') && $request->has('required_value')) {
            $portal = $request->input('required_portal');
            $value = (int)$request->input('required_value');
            $col = $portal . '_required';
            if (in_array($col, ['basic_required', 'deceased_required', 'family_required'])) {
                $documentType->$col = $value;
                $documentType->save();
                return response()->json(['success' => true, 'col' => $col, 'value' => $value]);
            } else {
                return response()->json(['success' => false, 'error' => 'عمود غير صالح']);
            }
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'pref' => 'required|string|max:255|unique:document_types,pref,' . $id,
            'file_type' => 'nullable|in:document,image',
        ]);

        try {
            $documentType->update([
                'description' => $request->description,
                'pref' => $request->pref,
                'file_type' => $request->file_type ?? $documentType->file_type ?? 'document',
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

        return redirect()->back()->with('success', 'تم حذف نوع الوثيقة بنجاح');
    }
}
