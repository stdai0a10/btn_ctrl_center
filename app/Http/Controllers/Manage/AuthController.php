<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Auth\UserEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $ip = $request->ip() ?: 'unknown';
        $accountKey = "manage-login:account:{$email}";
        $ipKey = "manage-login:ip:{$ip}";

        $this->ensureLoginIsNotLocked($accountKey);
        $this->ensureLoginIsNotLocked($ipKey);

        $userEmail = UserEmail::query()
            ->with('user')
            ->where('email', $email)
            ->where('is_verified', true)
            ->where('is_primary', true)
            ->first();

        $user = $userEmail?->user;

        if (
            $user === null
            || $user->status !== 'active'
            || $user->password === null
            || ! Hash::check($validated['password'], $user->password)
            || ! $user->can('manage.access')
        ) {
            RateLimiter::hit($accountKey, 900);
            RateLimiter::hit($ipKey, 900);

            throw ValidationException::withMessages([
                'email' => '管理後台登入資料不正確。',
            ]);
        }

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('manage_authenticated_at', now()->toISOString());
        $request->session()->put('manage_authenticated_user_id', $user->id);

        return $this->response([
            'redirect_to' => '/manage',
        ], '管理後台登入成功。');
    }

    public function destroy(Request $request)
    {
        $request->session()->forget([
            'manage_authenticated_at',
            'manage_authenticated_user_id',
        ]);

        return $this->response([
            'redirect_to' => '/manage/login',
        ], '已登出管理後台。');
    }

    private function ensureLoginIsNotLocked(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => '管理後台登入資料不正確，請稍後再試。',
            ]);
        }
    }
}
