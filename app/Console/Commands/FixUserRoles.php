<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class FixUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fix-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إصلاح أدوار المستخدمين التالفة في قاعدة البيانات';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 جاري فحص أدوار المستخدمين...');

        // فحص المستخدمين بأدوار فارغة أو null
        $usersWithNullRoles = User::whereNull('role')->orWhere('role', '')->count();
        $usersWithInvalidRoles = User::whereNotIn('role', ['admin', 'user'])->count();

        $this->info("📊 النتائج:");
        $this->line("- مستخدمين بأدوار فارغة/null: {$usersWithNullRoles}");
        $this->line("- مستخدمين بأدوار غير صحيحة: {$usersWithInvalidRoles}");

        if ($usersWithNullRoles === 0 && $usersWithInvalidRoles === 0) {
            $this->info('✅ جميع أدوار المستخدمين صحيحة!');
            return 0;
        }

        if ($this->confirm('هل تريد إصلاح الأدوار التالفة؟')) {
            // إصلاح الأدوار الفارغة
            if ($usersWithNullRoles > 0) {
                User::whereNull('role')->orWhere('role', '')->update(['role' => 'user']);
                $this->info("✅ تم إصلاح {$usersWithNullRoles} مستخدم بأدوار فارغة");
            }

            // إصلاح الأدوار غير الصحيحة
            if ($usersWithInvalidRoles > 0) {
                User::whereNotIn('role', ['admin', 'user'])->update(['role' => 'user']);
                $this->info("✅ تم إصلاح {$usersWithInvalidRoles} مستخدم بأدوار غير صحيحة");
            }

            $this->info('🎉 تم إصلاح جميع الأدوار بنجاح!');
        } else {
            $this->info('تم إلغاء العملية.');
        }

        return 0;
    }
}
