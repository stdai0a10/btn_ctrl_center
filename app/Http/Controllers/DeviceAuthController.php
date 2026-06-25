<?php

namespace App\Http\Controllers;

use App\Services\DeviceRuntime\DeviceTokenService;
use Illuminate\Http\Request;

class DeviceAuthController extends ApiController
{
    public function longToken(Request $request, DeviceTokenService $tokens)
    {
        $validated = $request->validate([
            'serial_number' => ['required', 'string', 'max:100'],
            'secret' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:100'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:100'],
        ]);

        $issued = $tokens->issueLongToken($validated['serial_number'], $validated['secret'], [
            'name' => $validated['name'] ?? null,
            'version' => $validated['version'] ?? null,
            'capabilities' => $validated['capabilities'] ?? [],
        ]);

        return $this->response([
            'device_id' => $issued['device']->serial_number,
            'long_token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_at' => $issued['expires_at']->toISOString(),
        ], '設備長效 JWT 已發行。');
    }

    public function accessToken(Request $request, DeviceTokenService $tokens, string $serialNumber)
    {
        $issued = $tokens->issueAccessToken($serialNumber, $this->bearerToken($request));

        return $this->response([
            'access_token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => max(0, now()->diffInSeconds($issued['expires_at'], false)),
            'expires_at' => $issued['expires_at']->toISOString(),
        ], '設備短效 JWT 已發行。');
    }

    private function bearerToken(Request $request): string
    {
        return $request->bearerToken() ?: '';
    }
}
