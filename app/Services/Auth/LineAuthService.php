<?php

namespace App\Services\Auth;

use App\Models\Auth\UserAuthProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LineAuthService
{
    public function loginOrRegisterFromProvider(
        string $providerUserId,
        ?string $providerName,
        ?string $accessToken = null,
        ?string $refreshToken = null,
    ): User {
        return DB::transaction(function () use ($providerUserId, $providerName, $accessToken, $refreshToken): User {
            $provider = UserAuthProvider::query()
                ->with('user')
                ->where('provider', 'line')
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($provider !== null) {
                $provider->forceFill([
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                ])->save();

                return $provider->user;
            }

            $user = User::query()->create([
                'name' => $providerName,
                'password' => null,
                'status' => 'active',
            ]);

            UserAuthProvider::query()->create([
                'user_id' => $user->id,
                'provider' => 'line',
                'provider_user_id' => $providerUserId,
                'provider_name_snapshot' => $providerName,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ]);

            return $user;
        });
    }
}
