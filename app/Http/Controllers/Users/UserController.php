<?php

namespace App\Http\Controllers\Users;

use App\DataTables\UserRoleManagementDataTable;
use App\DataTables\UsersDataTable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Yajra\DataTables\Contracts\DataTable;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:عرض قسم إدارة الصلاحيات|المستخدمين', ['only' => ['index101', 'index102', 'show']]);
        $this->middleware('permission:إنشاء مستخدم', ['only' => ['create', 'store']]);
        $this->middleware('permission:تعديل مستخدم', ['only' => ['edit', 'update']]);
        $this->middleware('permission:حذف مستخدم', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index101(UserRoleManagementDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.authorization.index101');
    }
    public function index102(UsersDataTable $dataTable)
    {
        return $dataTable->render('admin.dashboard.authorization.index102');
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $roles = Role::pluck('name', 'name')->all();
        return view('admin.dashboard.authorization.create', compact('roles'));
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirm-password',
            'roles' => 'required',
            'role' => 'required|in:admin,user'
        ]);

        $input = $request->all();
        $input['password'] = Hash::make($input['password']);

        // التأكد من تضمين الرتبة في البيانات
        $input['role'] = $request->input('role');

        $user = User::create($input);
        $user->assignRole($request->input('roles'));

        return redirect()->back()
            ->with('success', 'تم إضافة المستخدم بنجاح');
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::find($id);
        return view('admin.dashboard.authorization.show', compact('user'));
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);
        $roles = Role::pluck('name', 'name')->all();
        $userRole = $user->roles->pluck('name', 'name')->all();
        return view('admin.dashboard.authorization.edit', compact('user', 'roles', 'userRole'));
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'same:confirm-password',
            'roles' => 'required',
            'role' => 'required|in:admin,user'
        ]);

        $input = $request->all();
        if (!empty($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        } else {
            $input = Arr::except($input, ['password']);
        }

        $user = User::find($id);
        $user->update($input);

        DB::table('model_has_roles')->where('model_id', $id)->delete();
        $user->assignRole($request->input('roles'));

        return redirect()->back()
            ->with('success', ' تم تحديث المستخدم بنجاح ');
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        User::find($id)->delete();
        return redirect()->back()
            ->with('success', ' تم حذف المستخدم بنجاح ');
    }

    /**
     * عرض لوحة تحكم المستخدم
     */
    public function dashboard()
    {
        $user = auth()->user();
        return view('user.dashboard', compact('user'));
    }
}
