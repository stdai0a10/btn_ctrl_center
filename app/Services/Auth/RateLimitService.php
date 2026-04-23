<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RateLimitService
{
    public function ensure(string $key, int $maxAttempts, int $decaySeconds, string $message = '請稍後再試。'): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'rate_limit' => $message,
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }
}
