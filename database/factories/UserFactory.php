<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // users.country_code → FK ci_birth_cd(code) — بيئة الاختبار قد يكون الجدول فارغاً
        if (!\DB::table('ci_birth_cd')->where('code', 'PS')->exists()) {
            try {
                \DB::table('ci_birth_cd')->insertOrIgnore([
                    'ci_birth_cd' => 'PS',
                    'flag' => 'PS',
                    'code' => 'PS',
                ]);
            } catch (\Throwable $e) {
                // بيئة بها قيود إضافية — نتجاهل ونترك default إن وُجد
            }
        }

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'country_code' => 'PS',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
