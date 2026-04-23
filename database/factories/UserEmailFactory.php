<?php

namespace Database\Factories;

use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserEmail>
 */
class UserEmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'is_verified' => true,
            'verified_at' => now(),
            'is_primary' => true,
            'reserved_until' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'is_verified' => false,
            'verified_at' => null,
            'reserved_until' => now()->addDay(),
        ]);
    }
}
