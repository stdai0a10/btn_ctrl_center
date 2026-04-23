<?php

namespace App\Services\Auth;

use App\Models\Auth\PasswordResetRequest;
use App\Models\Auth\UserEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function __construct(
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    public function sendResetLink(string $email, ?string $ip): void
    {
        $email = mb_strtolower(trim($email));

        $this->rateLimitService->ensure("forgot-password:email:{$email}:cooldown", 1, 300, '密碼變更信需間隔 300 秒才能重新發送。');
        $this->rateLimitService->ensure("forgot-password:email:{$email}", 5, 86400);
        $this->rateLimitService->ensure('forgot-password:ip:'.($ip ?: 'unknown'), 5, 86400);

        $userEmail = UserEmail::query()
            ->with('user')
            ->where('email', $email)
            ->where('is_verified', true)
            ->first();

        if ($userEmail === null) {
            return;
        }

        PasswordResetRequest::query()
            ->where('user_id', $userEmail->user_id)
            ->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);

        $token = Str::random(64);

        PasswordResetRequest::query()->create([
            'user_id' => $userEmail->user_id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
            'request_ip' => $ip,
        ]);

        $this->sendResetMail($email, $token);
    }

    public function validateToken(string $token): void
    {
        if (! $this->validRequestQuery($token)->exists()) {
            throw ValidationException::withMessages([
                'token' => '密碼重設連結已失效，請重新申請。',
            ]);
        }
    }

    public function reset(string $token, string $password): void
    {
        $tokenHash = hash('sha256', $token);

        DB::transaction(function () use ($tokenHash, $password): void {
            $request = PasswordResetRequest::query()
                ->with('user')
                ->lockForUpdate()
                ->where('token_hash', $tokenHash)
                ->whereNull('used_at')
                ->whereNull('invalidated_at')
                ->first();

            if ($request === null || $request->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => '密碼重設連結已失效，請重新申請。',
                ]);
            }

            $request->user->forceFill([
                'password' => $password,
            ])->save();

            $request->forceFill(['used_at' => now()])->save();

            DB::table('sessions')->where('user_id', $request->user_id)->delete();

            Mail::raw('你的密碼已完成變更；若不是本人操作，請立即聯絡管理員。', function ($message) use ($request): void {
                $message->to($request->email)->subject('密碼已變更');
            });
        });
    }

    private function validRequestQuery(string $token)
    {
        return PasswordResetRequest::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now());
    }

    private function sendResetMail(string $email, string $token): void
    {
        $url = route('password.reset', ['token' => $token]);

        Mail::raw("請點擊以下連結設定新密碼：\n{$url}\n\n此連結 1 小時內有效，且僅能使用一次。", function ($message) use ($email): void {
            $message->to($email)->subject('密碼變更信');
        });
    }
}
