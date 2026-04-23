<?php

namespace App\Services\Auth;

use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserRegistrationService
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    public function registerEmail(string $email, string $password, ?string $ip): ?User
    {
        $email = mb_strtolower(trim($email));

        $this->rateLimitService->ensure('register:ip:'.($ip ?: 'unknown'), 5, 3600, '註冊請求過於頻繁，請稍後再試。');

        if (UserEmail::query()->where('email', $email)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($email, $password, $ip): User {
            $user = User::query()->create([
                'password' => $password,
                'status' => 'pending',
            ]);

            UserEmail::query()->create([
                'user_id' => $user->id,
                'email' => $email,
                'is_verified' => false,
                'is_primary' => false,
                'reserved_until' => now()->addDay(),
            ]);

            $this->emailVerificationService->createRequest($user, $email, 'register', $ip);

            return $user;
        });
    }
}
