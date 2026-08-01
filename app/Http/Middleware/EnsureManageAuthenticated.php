<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureManageAuthenticated
{
    private const SESSION_AUTHENTICATED_AT = 'manage_authenticated_at';

    private const SESSION_AUTHENTICATED_USER_ID = 'manage_authenticated_user_id';

    private const TIMEOUT_MINUTES = 30;

    /**
     * @param  Closure(Request): SymfonyResponse  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $request->user();
        $authenticatedAt = $request->session()->get(self::SESSION_AUTHENTICATED_AT);
        $authenticatedUserId = $request->session()->get(self::SESSION_AUTHENTICATED_USER_ID);

        if (
            $user === null
            || $authenticatedUserId !== $user->id
            || ! is_string($authenticatedAt)
            || ! $this->isFresh($authenticatedAt)
        ) {
            $this->clearManageSession($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => '管理後台登入已失效，請重新登入。',
                    'data' => null,
                ], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->guest('/manage/login');
        }

        return $next($request);
    }

    private function isFresh(string $authenticatedAt): bool
    {
        try {
            $issuedAt = Carbon::parse($authenticatedAt);
        } catch (\Throwable) {
            return false;
        }

        return $issuedAt->addMinutes(self::TIMEOUT_MINUTES)->isFuture();
    }

    private function clearManageSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_AUTHENTICATED_AT,
            self::SESSION_AUTHENTICATED_USER_ID,
        ]);
    }
}
