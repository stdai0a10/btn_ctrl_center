<?php

namespace App\Services\Auth;

use App\Models\Auth\SecurityReauthLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ReauthenticationService
{
    public function passWithPassword(User $user, string $password, Request $request): SecurityReauthLog
    {
        if ($user->password === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => '重新驗證失敗。',
            ]);
        }

        $passedAt = now();
        $expiresAt = $passedAt->copy()->addMinutes(10);

        $log = SecurityReauthLog::query()->create([
            'user_id' => $user->id,
            'method' => 'password',
            'passed_at' => $passedAt,
            'expires_at' => $expiresAt,
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->forceFill(['last_reauth_at' => $passedAt])->save();

        return $log;
    }

    public function assertFresh(User $user): void
    {
        if (! $this->isFresh($user)) {
            throw ValidationException::withMessages([
                'reauth' => '此操作需要重新驗證身分。',
            ]);
        }
    }

    public function isFresh(User $user): bool
    {
        return $user->reauthLogs()
            ->where('expires_at', '>', now())
            ->exists();
    }
}
