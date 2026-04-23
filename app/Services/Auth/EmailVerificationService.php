<?php

namespace App\Services\Auth;

use App\Models\Auth\EmailVerificationRequest;
use App\Models\Auth\UserEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function __construct(
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    /**
     * @return array{request: EmailVerificationRequest, token: string}
     */
    public function createRequest(User $user, string $email, string $purpose, ?string $ip): array
    {
        $email = $this->normalizeEmail($email);

        EmailVerificationRequest::query()
            ->where('user_id', $user->id)
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);

        $token = Str::random(64);

        $request = EmailVerificationRequest::query()->create([
            'user_id' => $user->id,
            'email' => $email,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
            'request_ip' => $ip,
        ]);

        $this->sendVerificationMail($email, $token);

        return ['request' => $request, 'token' => $token];
    }

    public function resend(string $email, ?string $ip): void
    {
        $email = $this->normalizeEmail($email);

        $this->rateLimitService->ensure("verify-email:email:{$email}:cooldown", 1, 300, '驗證信需間隔 300 秒才能重新發送。');
        $this->rateLimitService->ensure("verify-email:email:{$email}", 5, 86400);
        $this->rateLimitService->ensure('verify-email:ip:'.($ip ?: 'unknown'), 5, 86400);

        $userEmail = UserEmail::query()
            ->with('user')
            ->where('email', $email)
            ->where('is_verified', false)
            ->where(function ($query): void {
                $query->whereNull('reserved_until')->orWhere('reserved_until', '>', now());
            })
            ->first();

        if ($userEmail !== null) {
            $this->createRequest($userEmail->user, $email, 'register', $ip);
        }
    }

    public function verify(string $token): string
    {
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use ($tokenHash): string {
            $request = EmailVerificationRequest::query()
                ->lockForUpdate()
                ->where('token_hash', $tokenHash)
                ->whereNull('used_at')
                ->whereNull('invalidated_at')
                ->first();

            if ($request === null || $request->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => '驗證連結已失效，請重新申請驗證信。',
                ]);
            }

            if ($request->purpose === 'register') {
                $this->verifyRegistrationEmail($request);
            } elseif ($request->purpose === 'change_email') {
                $this->verifyChangedEmail($request);
            }

            $request->forceFill(['used_at' => now()])->save();

            return $request->purpose;
        });
    }

    private function verifyRegistrationEmail(EmailVerificationRequest $request): void
    {
        $userEmail = UserEmail::query()
            ->where('user_id', $request->user_id)
            ->where('email', $request->email)
            ->firstOrFail();

        $userEmail->forceFill([
            'is_verified' => true,
            'verified_at' => now(),
            'is_primary' => true,
            'reserved_until' => null,
        ])->save();

        $request->user->forceFill([
            'primary_email_id' => $userEmail->id,
            'status' => 'active',
        ])->save();
    }

    private function verifyChangedEmail(EmailVerificationRequest $request): void
    {
        $user = $request->user()->with('primaryEmail')->firstOrFail();
        $oldEmail = $user->primaryEmail?->email;

        $userEmail = UserEmail::query()
            ->where('user_id', $request->user_id)
            ->where('email', $request->email)
            ->firstOrFail();

        if (UserEmail::query()->where('email', $request->email)->where('user_id', '!=', $request->user_id)->exists()) {
            throw ValidationException::withMessages([
                'email' => '此 EMAIL 已被其他帳號使用。',
            ]);
        }

        UserEmail::query()
            ->where('user_id', $request->user_id)
            ->where('id', '!=', $userEmail->id)
            ->update(['is_primary' => false]);

        $userEmail->forceFill([
            'is_verified' => true,
            'verified_at' => now(),
            'is_primary' => true,
            'reserved_until' => null,
        ])->save();

        $user->forceFill([
            'primary_email_id' => $userEmail->id,
        ])->save();

        if ($oldEmail !== null && $oldEmail !== $request->email) {
            Mail::raw("你的帳號 EMAIL 已變更為 {$request->email}。若不是本人操作，請立即聯絡管理員。", function ($message) use ($oldEmail): void {
                $message->to($oldEmail)->subject('帳號 EMAIL 已變更');
            });
        }
    }

    private function sendVerificationMail(string $email, string $token): void
    {
        $url = route('email.verify.result', ['token' => $token]);

        Mail::raw("請點擊以下連結完成 EMAIL 驗證：\n{$url}\n\n此連結 24 小時內有效，且僅能使用一次。", function ($message) use ($email): void {
            $message->to($email)->subject('EMAIL 驗證');
        });
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
