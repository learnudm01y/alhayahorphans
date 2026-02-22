<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateAdminUserSeeder extends Seeder
{
    /**
     * منح المستخدم admin جميع الصلاحيات تلقائياً.
     */
    public function run(): void
    {
        // إنشاء دور super-admin إذا لم يكن موجوداً
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // منح الدور جميع الصلاحيات الموجودة
        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);

        // البحث عن المستخدم admin وإسناد الدور إليه
        $admin = User::where('email', 'admin@gmail.com')->first();

        if ($admin) {
            // إزالة أي أدوار سابقة وإضافة دور super-admin
            $admin->syncRoles(['super-admin']);
        }
    }
}
