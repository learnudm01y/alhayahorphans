<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Database\Seeders\PermissionTableSeeder as SeedersPermissionTableSeeder;
use Illuminate\Database\Seeder;
use PermissionTableSeeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        // البيانات المرجعية أولاً لأن جداول أخرى تعتمد عليها عبر المفاتيح الأجنبية
        $this->call(CountriesSeeder::class);     // ci_birth_cd (users.country_code يعتمد عليها)
        $this->call(CiBirthSeeder::class);        // ci_birth_tb_cd
        $this->call(CitiesSeeder::class);         // city
        $this->call(SmsTemplateSeeder::class);    // قوالب الرسائل النصية

        $this->call(UserSeeder::class);
        $this->call(SeedersPermissionTableSeeder::class);
        $this->call(CreateAdminUserSeeder::class); // منح admin جميع الصلاحيات
    }
}
