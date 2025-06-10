<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::insert([
            [
                'name'=>'Admin',
                'email'=>'admin@gmail.com',
                'role'=>'admin',
                'password'=>'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'phone'=>'970598888888',
                'alt_phone'=>'970598888888',
                'country_code'=>'PS-G',

            ],
            [
                'name'=>'User',
                'email'=>'user@gmail.com',
                'role'=>'user',
                'password'=>'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'phone'=>'970598888888',
                'alt_phone'=>'970598888888',
                'country_code'=>'PS-G',

            ],
        ]);
    }
}
