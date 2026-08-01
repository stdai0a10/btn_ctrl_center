<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\ApiController;
use App\Models\Auth\UserEmail;
use App\Models\ManageLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends ApiController
{
    #[OA\Post(
        path: '/manage/api/login',
        operationId: 'manageAuthLogin',
        summary: 'Log in to the management API',
        tags: ['Manage Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ManageLoginRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
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

        $this->ensureLoginIsNotLocked($request, $accountKey, $email);
        $this->ensureLoginIsNotLocked($request, $ipKey, $email);

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
            $this->logLogin($request, $email, $user?->id, false, 'invalid_credentials_or_permission');

            throw ValidationException::withMessages([
                'email' => '管理後台登入資料不正確。',
            ]);
        }

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);
        $this->logLogin($request, $email, $user->id, true);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('manage_authenticated_at', now()->toISOString());
        $request->session()->put('manage_authenticated_user_id', $user->id);

        return $this->response([
            'redirect_to' => '/manage',
        ], '管理後台登入成功。');
    }

    #[OA\Post(
        path: '/manage/api/logout',
        operationId: 'manageAuthLogout',
        summary: 'Log out of the management API',
        security: [['sessionCookie' => []]],
        tags: ['Manage Authentication'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
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

    private function ensureLoginIsNotLocked(Request $request, string $key, string $email): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $lockedUntil = now()->addSeconds(RateLimiter::availableIn($key));
            $this->logLogin($request, $email, null, false, 'rate_limited', $lockedUntil);

            throw ValidationException::withMessages([
                'email' => '管理後台登入資料不正確，請稍後再試。',
            ]);
        }
    }

    private function logLogin(
        Request $request,
        string $email,
        ?int $userId,
        bool $success,
        ?string $failureReason = null,
        $lockedUntil = null,
    ): void {
        ManageLoginLog::query()->create([
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'success' => $success,
            'failure_reason' => $failureReason,
            'locked_until' => $lockedUntil,
        ]);
    }
}
