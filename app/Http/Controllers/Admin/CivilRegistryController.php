<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\DataTables\PersonsDataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CivilRegistryController extends Controller
{
    /**
     * عرض قائمة السجلات
     */
    public function index(PersonsDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.civil_registry.index');
    }

    /**
     * عرض نموذج إنشاء سجل جديد
     */
    public function create()
    {
        // جلب البيانات المساعدة
        $CI_BIRTH_CD = collect([
            (object)['id' => 1, 'ci_birth_cd' => 'العراق'],
            (object)['id' => 2, 'ci_birth_cd' => 'سوريا'],
            (object)['id' => 3, 'ci_birth_cd' => 'الأردن'],
            (object)['id' => 4, 'ci_birth_cd' => 'لبنان'],
            (object)['id' => 5, 'ci_birth_cd' => 'مصر'],
            (object)['id' => 6, 'ci_birth_cd' => 'السعودية'],
            (object)['id' => 7, 'ci_birth_cd' => 'أخرى']
        ]);

        $CI_BIRTH_TB_CD = collect([
            (object)['id' => 1, 'CI_BIRTH_TB_CD' => 'بغداد'],
            (object)['id' => 2, 'CI_BIRTH_TB_CD' => 'البصرة'],
            (object)['id' => 3, 'CI_BIRTH_TB_CD' => 'الموصل'],
            (object)['id' => 4, 'CI_BIRTH_TB_CD' => 'أربيل'],
            (object)['id' => 5, 'CI_BIRTH_TB_CD' => 'النجف'],
            (object)['id' => 6, 'CI_BIRTH_TB_CD' => 'كربلاء'],
            (object)['id' => 7, 'CI_BIRTH_TB_CD' => 'الأنبار'],
            (object)['id' => 8, 'CI_BIRTH_TB_CD' => 'أخرى']
        ]);

        $socialStatuses = collect([
            (object)['id' => 1, 'CI_PERSONAL_CD' => 'أعزب'],
            (object)['id' => 2, 'CI_PERSONAL_CD' => 'متزوج'],
            (object)['id' => 3, 'CI_PERSONAL_CD' => 'مطلق'],
            (object)['id' => 4, 'CI_PERSONAL_CD' => 'أرمل']
        ]);

        $city = collect([
            (object)['id' => 1, 'city' => 'بغداد'],
            (object)['id' => 2, 'city' => 'البصرة'],
            (object)['id' => 3, 'city' => 'الموصل'],
            (object)['id' => 4, 'city' => 'أربيل'],
            (object)['id' => 5, 'city' => 'النجف'],
            (object)['id' => 6, 'city' => 'كربلاء'],
            (object)['id' => 7, 'city' => 'الأنبار'],
            (object)['id' => 8, 'city' => 'أخرى']
        ]);

        return view('admin.dashboard.civil_registry.create', compact('CI_BIRTH_CD', 'CI_BIRTH_TB_CD', 'socialStatuses', 'city'));
    }

    /**
     * حفظ سجل جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CI_ID_NUM' => 'required|numeric|unique:civilregistry.persons,CI_ID_NUM',
            'CI_FIRST_ARB' => 'required|string|max:255',
            'CI_FATHER_ARB' => 'required|string|max:255',
            'CI_FAMILY_ARB' => 'required|string|max:255',
            'CI_BIRTH_DT' => 'nullable|date',
            'CI_SEX_CD' => 'required|in:1,2',
            'CI_PERSONAL_CD' => 'nullable|numeric',
            'MOTHER_NAME1' => 'nullable|string|max:255',
            'CITY' => 'nullable|numeric',
            'STREET' => 'nullable|string|max:255',
            'HOUSE_NO' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::connection('civilregistry')->table('persons')->insert([
                'CI_ID_NUM' => $request->CI_ID_NUM,
                'CI_FIRST_ARB' => $request->CI_FIRST_ARB,
                'CI_FATHER_ARB' => $request->CI_FATHER_ARB,
                'CI_GRAND_FATHER_ARB' => $request->CI_GRAND_FATHER_ARB,
                'CI_FAMILY_ARB' => $request->CI_FAMILY_ARB,
                'CI_BIRTH_DT' => $request->CI_BIRTH_DT,
                'CI_SEX_CD' => $request->CI_SEX_CD,
                'CI_PERSONAL_CD' => $request->CI_PERSONAL_CD,
                'MOTHER_NAME1' => $request->MOTHER_NAME1,
                'CITY' => $request->CITY,
                'STREET' => $request->STREET,
                'HOUSE_NO' => $request->HOUSE_NO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('civil-registry.index')
                ->with('success', 'تم إنشاء السجل بنجاح');

        } catch (\Exception $e) {
            return back()->with('error', 'خطأ في إنشاء السجل: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * عرض سجل محدد
     */
    public function show($id)
    {
        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            return view('admin.civil_registry.show', compact('person'));

        } catch (\Exception $e) {
            return redirect()->route('civil-registry.index')
                ->with('error', 'خطأ في جلب البيانات: ' . $e->getMessage());
        }
    }

    /**
     * عرض نموذج تعديل السجل
     */
    public function edit($id)
    {
        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            // جلب البيانات المساعدة
            $CI_BIRTH_CD = collect([
                (object)['id' => 1, 'ci_birth_cd' => 'العراق'],
                (object)['id' => 2, 'ci_birth_cd' => 'سوريا'],
                (object)['id' => 3, 'ci_birth_cd' => 'الأردن'],
                (object)['id' => 4, 'ci_birth_cd' => 'لبنان'],
                (object)['id' => 5, 'ci_birth_cd' => 'مصر'],
                (object)['id' => 6, 'ci_birth_cd' => 'السعودية'],
                (object)['id' => 7, 'ci_birth_cd' => 'أخرى']
            ]);

            $CI_BIRTH_TB_CD = collect([
                (object)['id' => 1, 'CI_BIRTH_TB_CD' => 'بغداد'],
                (object)['id' => 2, 'CI_BIRTH_TB_CD' => 'البصرة'],
                (object)['id' => 3, 'CI_BIRTH_TB_CD' => 'الموصل'],
                (object)['id' => 4, 'CI_BIRTH_TB_CD' => 'أربيل'],
                (object)['id' => 5, 'CI_BIRTH_TB_CD' => 'النجف'],
                (object)['id' => 6, 'CI_BIRTH_TB_CD' => 'كربلاء'],
                (object)['id' => 7, 'CI_BIRTH_TB_CD' => 'الأنبار'],
                (object)['id' => 8, 'CI_BIRTH_TB_CD' => 'أخرى']
            ]);

            $socialStatuses = collect([
                (object)['id' => 1, 'CI_PERSONAL_CD' => 'أعزب'],
                (object)['id' => 2, 'CI_PERSONAL_CD' => 'متزوج'],
                (object)['id' => 3, 'CI_PERSONAL_CD' => 'مطلق'],
                (object)['id' => 4, 'CI_PERSONAL_CD' => 'أرمل']
            ]);

            $city = collect([
                (object)['id' => 1, 'city' => 'بغداد'],
                (object)['id' => 2, 'city' => 'البصرة'],
                (object)['id' => 3, 'city' => 'الموصل'],
                (object)['id' => 4, 'city' => 'أربيل'],
                (object)['id' => 5, 'city' => 'النجف'],
                (object)['id' => 6, 'city' => 'كربلاء'],
                (object)['id' => 7, 'city' => 'الأنبار'],
                (object)['id' => 8, 'city' => 'أخرى']
            ]);

            return view('admin.dashboard.civil_registry.edit', compact('person', 'CI_BIRTH_CD', 'CI_BIRTH_TB_CD', 'socialStatuses', 'city'));

        } catch (\Exception $e) {
            return redirect()->route('civil-registry.index')
                ->with('error', 'خطأ في جلب البيانات: ' . $e->getMessage());
        }
    }

    /**
     * تحديث السجل
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'CI_ID_NUM' => 'required|numeric|unique:civilregistry.persons,CI_ID_NUM,' . $id . ',ID',
            'CI_FIRST_ARB' => 'required|string|max:255',
            'CI_FATHER_ARB' => 'required|string|max:255',
            'CI_FAMILY_ARB' => 'required|string|max:255',
            'CI_BIRTH_DT' => 'nullable|date',
            'CI_SEX_CD' => 'required|in:1,2',
            'CI_PERSONAL_CD' => 'nullable|numeric',
            'MOTHER_NAME1' => 'nullable|string|max:255',
            'CITY' => 'nullable|numeric',
            'STREET' => 'nullable|string|max:255',
            'HOUSE_NO' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            DB::connection('civilregistry')->table('persons')->where('ID', $id)->update([
                'CI_ID_NUM' => $request->CI_ID_NUM,
                'CI_FIRST_ARB' => $request->CI_FIRST_ARB,
                'CI_FATHER_ARB' => $request->CI_FATHER_ARB,
                'CI_GRAND_FATHER_ARB' => $request->CI_GRAND_FATHER_ARB,
                'CI_FAMILY_ARB' => $request->CI_FAMILY_ARB,
                'CI_BIRTH_DT' => $request->CI_BIRTH_DT,
                'CI_SEX_CD' => $request->CI_SEX_CD,
                'CI_PERSONAL_CD' => $request->CI_PERSONAL_CD,
                'MOTHER_NAME1' => $request->MOTHER_NAME1,
                'CITY' => $request->CITY,
                'STREET' => $request->STREET,
                'HOUSE_NO' => $request->HOUSE_NO,
                'updated_at' => now(),
            ]);

            return redirect()->route('civil-registry.index')
                ->with('success', 'تم تحديث السجل بنجاح');

        } catch (\Exception $e) {
            return back()->with('error', 'خطأ في تحديث السجل: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * حذف السجل
     */
    public function destroy($id)
    {
        try {
            $person = DB::connection('civilregistry')->table('persons')->where('ID', $id)->first();

            if (!$person) {
                return redirect()->route('civil-registry.index')
                    ->with('error', 'السجل غير موجود');
            }

            DB::connection('civilregistry')->table('persons')->where('ID', $id)->delete();

            return redirect()->route('civil-registry.index')
                ->with('success', 'تم حذف السجل بنجاح');

        } catch (\Exception $e) {
            return redirect()->route('civil-registry.index')
                ->with('error', 'خطأ في حذف السجل: ' . $e->getMessage());
        }
    }

    /**
     * البحث في السجلات
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'نص البحث مطلوب'
            ]);
        }

        try {
            $results = DB::connection('civilregistry')->table('persons')
                ->where(function($q) use ($query) {
                    $q->where('CI_ID_NUM', 'LIKE', '%' . $query . '%')
                      ->orWhere('CI_FIRST_ARB', 'LIKE', '%' . $query . '%')
                      ->orWhere('CI_FATHER_ARB', 'LIKE', '%' . $query . '%')
                      ->orWhere('CI_FAMILY_ARB', 'LIKE', '%' . $query . '%');
                })
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $results,
                'count' => $results->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البحث: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إحصائيات السجلات
     */
    public function stats()
    {
        try {
            $totalRecords = DB::connection('civilregistry')->table('persons')->count();
            $maleCount = DB::connection('civilregistry')->table('persons')->where('CI_SEX_CD', 1)->count();
            $femaleCount = DB::connection('civilregistry')->table('persons')->where('CI_SEX_CD', 2)->count();
            $aliveCount = DB::connection('civilregistry')->table('persons')->whereNull('CI_DEAD_DT')->orWhere('CI_DEAD_DT', 0)->count();
            $deadCount = DB::connection('civilregistry')->table('persons')->where('CI_DEAD_DT', '>', 0)->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $totalRecords,
                    'male' => $maleCount,
                    'female' => $femaleCount,
                    'alive' => $aliveCount,
                    'dead' => $deadCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات: ' . $e->getMessage()
            ], 500);
        }
    }
}
