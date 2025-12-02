<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\SponsorDataTable;
use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Models\CI_BIRTH_CD;
use App\Models\BankName;
use App\Models\CurrencyType;
use App\Models\AssociationEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SponsorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SponsorDataTable $dataTable)
    {
        try {
            $countries = CI_BIRTH_CD::whereNotNull('flag')
                ->where('flag', '!=', '')
                ->where('code', '!=', '0')
                ->get();
        } catch (\Exception $e) {
            $countries = collect();
        }

        $banks = BankName::all();
        $currencies = CurrencyType::all();
        $file_id = generateUniqueReservedCode('sponsors', 'file_id');

        return $dataTable->render('admin.dashboard.sponsors.index', compact('countries', 'banks', 'currencies', 'file_id'));
    }    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            Log::info('=== بدء إضافة جمعية ===');
            Log::info('البيانات الواردة:', $request->all());

            // التحقق من البيانات
            $validatedData = $request->validate([
                'file_id' => 'required|string|size:6',
                'sponsor_name' => 'required|string|max:255',
                'sponsor_short_name' => 'nullable|string|max:255',
                'sponsor_phone_number' => 'nullable|string|max:50',
                'sponsor_email' => 'nullable|email|max:255',
                'sponsor_address' => 'nullable|string|max:500',
                'sponsor_bank_name_id' => 'nullable|integer|exists:bank_names,id',
                'sponsor_account_bank_number' => 'nullable|string|max:100',
                'sponsor_bank_swift_code' => 'nullable|string|max:50',
                'sponsor_bank_related_phone_number' => 'nullable|string|max:50',
                'sponsor_bank_account_currency' => 'nullable|integer|exists:currency_types,id',
                'country_code' => 'nullable|string|max:10',
            ]);

            Log::info('البيانات بعد التحقق:', $validatedData);

            // إنشاء السجل
            $sponsor = new Sponsor();
            $sponsor->file_id = $validatedData['file_id'];
            $sponsor->sponsor_name = $validatedData['sponsor_name'];
            $sponsor->sponsor_short_name = $validatedData['sponsor_short_name'] ?? null;
            $sponsor->sponsor_phone_number = $validatedData['sponsor_phone_number'] ?? null;
            $sponsor->sponsor_email = $validatedData['sponsor_email'] ?? null;
            $sponsor->sponsor_address = $validatedData['sponsor_address'] ?? null;
            $sponsor->sponsor_bank_name_id = $validatedData['sponsor_bank_name_id'] ?? null;
            $sponsor->sponsor_account_bank_number = $validatedData['sponsor_account_bank_number'] ?? null;
            $sponsor->sponsor_bank_swift_code = $validatedData['sponsor_bank_swift_code'] ?? null;
            $sponsor->sponsor_bank_related_phone_number = $validatedData['sponsor_bank_related_phone_number'] ?? null;
            $sponsor->sponsor_bank_account_currency = $validatedData['sponsor_bank_account_currency'] ?? null;
            $sponsor->country_code = $validatedData['country_code'] ?? null;

            $sponsor->save();

            Log::info('تم الحفظ بنجاح! ID: ' . $sponsor->id);

            // وضع علامة على file_id كمستخدم
            markCodeAsUsed($validatedData['file_id']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الجمعية بنجاح',
                'data' => $sponsor
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('خطأ في التحقق:', $e->errors());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في الحفظ:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $sponsor = Sponsor::findOrFail($id);
            return response()->json($sponsor);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على الجمعية'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $sponsor = Sponsor::findOrFail($id);

            $validatedData = $request->validate([
                'sponsor_name' => 'required|string|max:255',
                'sponsor_short_name' => 'nullable|string|max:255',
                'sponsor_phone_number' => 'nullable|string|max:50',
                'sponsor_email' => 'nullable|email|max:255',
                'sponsor_address' => 'nullable|string|max:500',
                'sponsor_bank_name_id' => 'nullable|integer|exists:bank_names,id',
                'sponsor_account_bank_number' => 'nullable|string|max:100',
                'sponsor_bank_swift_code' => 'nullable|string|max:50',
                'sponsor_bank_related_phone_number' => 'nullable|string|max:50',
                'sponsor_bank_account_currency' => 'nullable|integer|exists:currency_types,id',
                'country_code' => 'nullable|string|max:10',
            ]);

            $sponsor->update($validatedData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الجمعية بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في التحديث:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $sponsor = Sponsor::findOrFail($id);
            $sponsor->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الجمعية بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new file_id for sponsors
     */
    public function generateFileId()
    {
        try {
            $file_id = generateUniqueReservedCode('sponsors', 'file_id');

            return response()->json([
                'success' => true,
                'file_id' => $file_id
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating file_id:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في توليد رقم الملف'
            ], 500);
        }
    }

    /**
     * Show test form (final version)
     */
    public function testFinal()
    {
        $banks = BankName::all();
        $file_id = generateUniqueReservedCode('sponsors', 'file_id');

        return view('admin.dashboard.sponsors.test_final', compact('banks', 'file_id'));
    }

    /**
     * Store association employee
     */
    public function storeEmployee(Request $request)
    {
        try {
            $validated = $request->validate([
                'sponsor_id' => 'required|exists:sponsors,id',
                'employee_name' => 'required|string|max:255'
            ]);

            $employee = AssociationEmployee::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المندوب بنجاح',
                'employee' => $employee
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding employee:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة المندوب'
            ], 500);
        }
    }

    /**
     * Get association employees
     */
    public function getEmployees($sponsorId)
    {
        try {
            $employees = AssociationEmployee::where('sponsor_id', $sponsorId)->get();

            return response()->json([
                'success' => true,
                'employees' => $employees
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ'
            ], 500);
        }
    }

    /**
     * Delete association employee
     */
    public function destroyEmployee($id)
    {
        try {
            $employee = AssociationEmployee::findOrFail($id);
            $employee->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المندوب بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف'
            ], 500);
        }
    }
}
