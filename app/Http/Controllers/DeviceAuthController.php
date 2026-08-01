<?php

namespace App\Http\Controllers;

use App\Services\DeviceRuntime\DeviceTokenService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DeviceAuthController extends ApiController
{
    #[OA\Post(
        path: '/device/api/device-auth/long-token',
        operationId: 'deviceAuthLongToken',
        summary: 'Issue a long-lived device JWT',
        tags: ['Device Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DeviceLongTokenRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
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

    #[OA\Post(
        path: '/device/api/devices/{serial_number}/access-tokens',
        operationId: 'deviceAuthAccessToken',
        summary: 'Exchange a long-lived device JWT for an access JWT',
        security: [['deviceBearer' => []]],
        tags: ['Device Authentication'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/PathSerialNumber')],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
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
