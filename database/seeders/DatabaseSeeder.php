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
        $this->call(UserSeeder::class);
        $this->call(SeedersPermissionTableSeeder::class);
        $this->call(CountriesSeeder::class);
        $this->call(CiBirthSeeder::class);
        $this->call(CitiesSeeder::class);
    }
}
