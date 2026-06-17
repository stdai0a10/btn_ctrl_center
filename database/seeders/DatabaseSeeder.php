<?php

namespace Database\Seeders;

use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
        ]);

        $email = UserEmail::factory()->create([
            'user_id' => $user->id,
            'email' => 'test@example.com',
        ]);

        $user->forceFill(['primary_email_id' => $email->id])->save();

        $this->call(ManagementRbacSeeder::class);
    }
}
