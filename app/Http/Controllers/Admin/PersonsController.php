<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\PersonsDataTable;
use App\Http\Controllers\Controller;
use App\Models\CI_BIRTH_CD;
use App\Models\CI_BIRTH_TB_CD;
use App\Models\CI_PERSONAL_CD;
use App\Models\City;
use App\Models\Persons;
use Faker\Provider\ar_EG\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class PersonsController extends Controller
{
    public function index(PersonsDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.civil_registry.index');
    }

    public function show($id)
    {
        $person = Persons::with('socialStatus', 'city', 'CI_BIRTH_TB_CD', 'CI_BIRTH_CD')->findOrFail($id);

        if (request()->ajax()) {
            return response()->json($person);
        }

        return view('admin.dashboard.civil_registry.show', compact('person'));
    }

    public function edit($id)
    {
        $person = Persons::with('socialStatus','city')->findOrFail($id);
        $socialStatuses = CI_PERSONAL_CD::all();
        $city = City::all();
        $CI_BIRTH_TB_CD = CI_BIRTH_TB_CD::all();
        $CI_BIRTH_CD = CI_BIRTH_CD::all();

        return view('admin.dashboard.civil_registry.edit', compact('person','socialStatuses','city','CI_BIRTH_TB_CD','CI_BIRTH_CD'));
    }

    public function update(Request $request, $id)
    {
        // تحقق من صحة البيانات ثم حدث السجل
        DB::table('persons')->where('ID', $id)->update($request->only([
            'CI_ID_NUM','CI_FIRST_ARB','CI_FATHER_ARB','CI_GRAND_FATHER_ARB','CI_FAMILY_ARB',
            'CI_BIRTH_TB_CD','CI_BIRTH_CD','CI_BIRTH_DT','CI_SEX_CD','CI_PERSONAL_CD',
            'CI_DEAD_DT','MOTHER_NAME1','CITY','STREET','HOUSE_NO'
        ]));
        return redirect()->route('admin.persons.index')->with('success', 'تم التعديل بنجاح');
    }

    public function destroy($id)
    {
        DB::table('persons')->where('ID', $id)->delete();
        return redirect()->route('admin.persons.index')->with('success', 'تم الحذف بنجاح');
    }

    public function sort(Request $request)
    {
        // تحقق من صحة البيانات
        $validatedData = $request->validate([
            'sort_field' => 'required|string|max:255', // الحقل الذي سيتم الفرز بناءً عليه
            'sort_order' => 'required|in:asc,desc',    // ترتيب الفرز (تصاعدي أو تنازلي)
        ]);

        // تنفيذ عملية الفرز
        $persons = DB::table('persons')
            ->orderBy($validatedData['sort_field'], $validatedData['sort_order'])
            ->get();

        // إعادة التوجيه مع البيانات المفرزة
        return view('admin.dashboard.civil_registry.index', compact('persons'))->with('success', 'تم الفرز بنجاح.');
    }

    public function create()
    {
        // جلب البيانات اللازمة لإنشاء مواطن جديد
        $socialStatuses = CI_PERSONAL_CD::all();
        $city = City::all();
        $CI_BIRTH_TB_CD = CI_BIRTH_TB_CD::all();
        $CI_BIRTH_CD = CI_BIRTH_CD::all();

        // عرض صفحة إنشاء مواطن جديد
        return view('admin.dashboard.civil_registry.create', compact('socialStatuses', 'city', 'CI_BIRTH_TB_CD', 'CI_BIRTH_CD'));
    }

    public function store(Request $request)
    {
        try {
            // تحقق من صحة البيانات
            $validatedData = $request->validate([
                'CI_ID_NUM' => 'required|string|max:255',
                'CI_FIRST_ARB' => 'required|string|max:255',
                'CI_FATHER_ARB' => 'required|string|max:255',
                'CI_GRAND_FATHER_ARB' => 'required|string|max:255',
                'CI_FAMILY_ARB' => 'required|string|max:255',
                'MOTHER_NAME1' => 'required|string|max:255',
                'CI_BIRTH_DT' => 'required|date',
                'CI_BIRTH_CD' => 'required|integer',
                'CI_BIRTH_TB_CD' => 'required|integer',
                'CI_SEX_CD' => 'required|integer',
                'CI_PERSONAL_CD' => 'required|integer',
                'CI_DEAD_DT' => 'nullable|integer', // تعديل نوع البيانات
                'CITY' => 'required|integer',
                'STREET' => 'nullable|string|max:255',
                'HOUSE_NO' => 'nullable|string|max:255',
            ]);

            // إنشاء سجل جديد
            Persons::create($validatedData);

            return redirect()->route('admin.persons.index')->with('success', 'تم إضافة المواطن بنجاح.');
        } catch (\Exception $e) {
            // تسجيل الخطأ
            Log::error('Error adding person: ' . $e->getMessage());
            return redirect()->back()->with('error', 'حدث خطأ أثناء إضافة المواطن.');
        }
    }




}
