<?php

namespace Database\Factories;

use App\Models\Auth\UserAuthProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAuthProvider>
 */
class UserAuthProviderFactory extends Factory
{
    protected $model = UserAuthProvider::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'line',
            'provider_user_id' => fake()->unique()->uuid(),
            'provider_name_snapshot' => fake()->name(),
            'access_token' => null,
            'refresh_token' => null,
        ];
    }
}
