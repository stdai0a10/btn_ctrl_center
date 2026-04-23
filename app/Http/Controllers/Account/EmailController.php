<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use App\Models\Auth\UserEmail;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\ReauthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailController extends ApiController
{
    public function store(
        Request $request,
        EmailVerificationService $emailVerificationService,
        ReauthenticationService $reauthenticationService,
    )
    {
        $reauthenticationService->assertFresh($request->user());

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $user = $request->user();
        $user->loadMissing('primaryEmail');

        if ($user->primaryEmail?->email === $email) {
            throw ValidationException::withMessages([
                'email' => '此 EMAIL 已是目前帳號 EMAIL。',
            ]);
        }

        if (UserEmail::query()->where('email', $email)->where('user_id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => '此 EMAIL 已被其他帳號使用或仍處於保留有效期間內。',
            ]);
        }

        DB::transaction(function () use ($user, $email, $request, $emailVerificationService): void {
            UserEmail::query()
                ->where('user_id', $user->id)
                ->where('is_verified', false)
                ->where('email', '!=', $email)
                ->delete();

            UserEmail::query()->updateOrCreate(
                ['user_id' => $user->id, 'email' => $email],
                [
                    'is_verified' => false,
                    'is_primary' => false,
                    'verified_at' => null,
                    'reserved_until' => now()->addDay(),
                ],
            );

            $emailVerificationService->createRequest($user, $email, 'change_email', $request->ip());
        });

        return $this->response(null, '系統已寄出 EMAIL 變更驗證信。');
    }
}
