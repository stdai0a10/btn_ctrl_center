<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Models\Auth\AuthAttemptLog;
use App\Models\Auth\UserEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends ApiController
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $ip = $request->ip() ?: 'unknown';
        $accountKey = "login:account:{$email}";
        $ipKey = "login:ip:{$ip}";

        $this->ensureLoginIsNotLocked($accountKey);
        $this->ensureLoginIsNotLocked($ipKey);

        $userEmail = UserEmail::query()
            ->with('user')
            ->where('email', $email)
            ->where('is_verified', true)
            ->where('is_primary', true)
            ->first();

        $user = $userEmail?->user;

        if ($user === null || $user->status !== 'active' || $user->password === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($accountKey, 900);
            RateLimiter::hit($ipKey, 900);
            $this->logAttempt('login', $email, $request->ip(), false);

            throw ValidationException::withMessages([
                'email' => '登入資料不正確。',
            ]);
        }

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);
        $this->logAttempt('login', $email, $request->ip(), true);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->response($this->userPayload($user), '登入成功。');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return $this->response(null, '已登出。');
    }

    public function me(Request $request)
    {
        return $this->response($this->userPayload($request->user()));
    }

    private function ensureLoginIsNotLocked(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => '登入資料不正確，請稍後再試。',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload($user): array
    {
        $user->loadMissing(['primaryEmail', 'authProviders']);

        return [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
            'email' => $user->primaryEmail?->email,
            'email_verified' => (bool) $user->primaryEmail?->is_verified,
            'has_password' => $user->password !== null,
            'providers' => $user->authProviders->pluck('provider')->values(),
        ];
    }

    private function logAttempt(string $type, string $accountKey, ?string $ip, bool $isSuccess): void
    {
        AuthAttemptLog::query()->create([
            'type' => $type,
            'account_key' => $accountKey,
            'ip' => $ip,
            'is_success' => $isSuccess,
        ]);
    }
}
