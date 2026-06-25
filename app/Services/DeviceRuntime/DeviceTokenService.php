<?php

namespace App\Services\DeviceRuntime;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\DeviceJwtToken;
use App\Support\DeviceSerial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class DeviceTokenService
{
    private const LONG_TTL_MINUTES = 60 * 24 * 365;

    private const ACCESS_TTL_MINUTES = 15;

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{device: Device, token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueLongToken(string $serialNumber, string $secret, array $metadata = []): array
    {
        $serialNumber = DeviceSerial::normalize($serialNumber);

        return DB::transaction(function () use ($serialNumber, $secret, $metadata): array {
            $device = Device::query()
                ->where('serial_number', $serialNumber)
                ->lockForUpdate()
                ->first();

            if ($device === null) {
                throw new ApiException('Device not found.', 'DEVICE_NOT_FOUND', 404);
            }

            if (! Hash::check($secret, $device->secret_hash)) {
                throw new ApiException('Device secret is invalid.', 'DEVICE_SECRET_INVALID');
            }

            if ($device->runner_disabled_at !== null) {
                throw new ApiException('Device runtime is disabled.', 'DEVICE_RUNTIME_DISABLED', 403);
            }

            $now = now();
            $version = ((int) $device->token_version) + 1;
            $jti = (string) Str::uuid();
            $expiresAt = $now->copy()->addMinutes(self::LONG_TTL_MINUTES);
            $token = $this->makeToken($device, DeviceJwtToken::TYPE_LONG, ['device:issue-access-token'], $jti, $version, $expiresAt);

            $this->revokeDeviceTokens($device);

            DeviceJwtToken::query()->create([
                'jti' => $jti,
                'device_id' => $device->id,
                'type' => DeviceJwtToken::TYPE_LONG,
                'token_version' => $version,
                'issued_at' => $now,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ]);

            $device->forceFill([
                'long_token_jti' => $jti,
                'long_token_issued_at' => $now,
                'long_token_expires_at' => $expiresAt,
                'long_token_revoked_at' => null,
                'token_version' => $version,
                'current_access_jti' => null,
                'current_access_expires_at' => null,
                'runner_status' => 'idle',
                'runner_last_seen_at' => $now,
                'runner_registered_at' => $device->runner_registered_at ?? $now,
            ])->save();

            return [
                'device' => $device->refresh(),
                'token' => $token,
                'expires_at' => $expiresAt,
            ];
        });
    }

    /**
     * @return array{device: Device, token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueAccessToken(string $serialNumber, string $longToken): array
    {
        $device = $this->authenticate($serialNumber, $longToken, DeviceJwtToken::TYPE_LONG, 'device:issue-access-token');

        return DB::transaction(function () use ($device): array {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            $now = now();
            $jti = (string) Str::uuid();
            $expiresAt = $now->copy()->addMinutes(self::ACCESS_TTL_MINUTES);
            $token = $this->makeToken(
                $device,
                DeviceJwtToken::TYPE_ACCESS,
                ['device:poll', 'device:progress', 'device:complete'],
                $jti,
                (int) $device->token_version,
                $expiresAt,
            );

            DeviceJwtToken::query()
                ->where('device_id', $device->id)
                ->where('type', DeviceJwtToken::TYPE_ACCESS)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            DeviceJwtToken::query()->create([
                'jti' => $jti,
                'device_id' => $device->id,
                'type' => DeviceJwtToken::TYPE_ACCESS,
                'token_version' => $device->token_version,
                'issued_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            $device->forceFill([
                'current_access_jti' => $jti,
                'current_access_expires_at' => $expiresAt,
                'runner_status' => in_array($device->runner_status, ['running'], true) ? 'running' : 'idle',
                'runner_last_seen_at' => $now,
            ])->save();

            return [
                'device' => $device->refresh(),
                'token' => $token,
                'expires_at' => $expiresAt,
            ];
        });
    }

    public function authenticateAccessToken(string $serialNumber, string $token, string $scope): Device
    {
        return $this->authenticate($serialNumber, $token, DeviceJwtToken::TYPE_ACCESS, $scope);
    }

    private function authenticate(string $serialNumber, string $token, string $type, string $scope): Device
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
        } catch (JWTException $exception) {
            throw new ApiException('Device token is invalid.', $type === DeviceJwtToken::TYPE_LONG ? 'DEVICE_LONG_TOKEN_INVALID' : 'DEVICE_ACCESS_TOKEN_INVALID', 401);
        }

        $serialNumber = DeviceSerial::normalize($serialNumber);
        $device = Device::query()
            ->where('serial_number', $serialNumber)
            ->whereKey((int) $payload->get('sub'))
            ->first();

        if ($device === null) {
            throw new ApiException('Device token is invalid.', $type === DeviceJwtToken::TYPE_LONG ? 'DEVICE_LONG_TOKEN_INVALID' : 'DEVICE_ACCESS_TOKEN_INVALID', 401);
        }

        if ($device->runner_disabled_at !== null) {
            throw new ApiException('Device runtime is disabled.', 'DEVICE_RUNTIME_DISABLED', 403);
        }

        $jti = (string) $payload->get('jti');
        $scopes = $payload->get('scope') ?? [];
        $record = DeviceJwtToken::query()
            ->where('jti', $jti)
            ->where('device_id', $device->id)
            ->where('type', $type)
            ->first();

        if (
            $payload->get('typ') !== $type
            || ! is_array($scopes)
            || ! in_array($scope, $scopes, true)
            || (int) $payload->get('token_version') !== (int) $device->token_version
            || $record === null
            || $record->revoked_at !== null
            || $record->expires_at->lessThanOrEqualTo(now())
        ) {
            throw new ApiException('Device token is invalid.', $type === DeviceJwtToken::TYPE_LONG ? 'DEVICE_LONG_TOKEN_INVALID' : 'DEVICE_ACCESS_TOKEN_INVALID', 401);
        }

        if ($type === DeviceJwtToken::TYPE_ACCESS && $device->current_access_jti !== $jti) {
            throw new ApiException('Device token is invalid.', 'DEVICE_ACCESS_TOKEN_INVALID', 401);
        }

        $record->forceFill(['last_used_at' => now()])->save();
        $device->forceFill(['runner_last_seen_at' => now()])->save();

        return $device->refresh();
    }

    private function makeToken(Device $device, string $type, array $scopes, string $jti, int $version, \DateTimeInterface $expiresAt): string
    {
        $now = now();
        $payload = JWTFactory::customClaims([
            'iss' => config('app.url'),
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $expiresAt->getTimestamp(),
            'sub' => (string) $device->id,
            'jti' => $jti,
            'typ' => $type,
            'serial_number' => $device->serial_number,
            'token_version' => $version,
            'scope' => $scopes,
        ])->make();

        return JWTAuth::encode($payload)->get();
    }

    private function revokeDeviceTokens(Device $device): void
    {
        DeviceJwtToken::query()
            ->where('device_id', $device->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
