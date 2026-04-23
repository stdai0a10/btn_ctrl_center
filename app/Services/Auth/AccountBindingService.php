<?php

namespace App\Services\Auth;

use App\Models\Auth\UserAuthProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountBindingService
{
    public function bindLine(
        User $user,
        string $providerUserId,
        ?string $providerName,
        ?string $accessToken = null,
        ?string $refreshToken = null,
    ): UserAuthProvider {
        return DB::transaction(function () use ($user, $providerUserId, $providerName, $accessToken, $refreshToken): UserAuthProvider {
            $existing = UserAuthProvider::query()
                ->where('provider', 'line')
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'line' => '此 LINE 帳號已被其他帳號綁定，請先解除原帳號綁定。',
                ]);
            }

            return UserAuthProvider::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => 'line',
                ],
                [
                    'provider_user_id' => $providerUserId,
                    'provider_name_snapshot' => $providerName,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                ],
            );
        });
    }

    public function unbindLine(User $user): void
    {
        $provider = $user->authProviders()->where('provider', 'line')->firstOrFail();

        if (! $this->hasOtherLoginMethod($user)) {
            throw ValidationException::withMessages([
                'line' => '帳號須至少保留一種有效登入方式，無法解除 LINE 綁定。',
            ]);
        }

        $provider->delete();
    }

    public function hasOtherLoginMethod(User $user): bool
    {
        $user->loadMissing('primaryEmail');

        return $user->password !== null
            && $user->primaryEmail !== null
            && $user->primaryEmail->is_verified;
    }
}
