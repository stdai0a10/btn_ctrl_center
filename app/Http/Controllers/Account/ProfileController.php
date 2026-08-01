<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfileController extends ApiController
{
    #[OA\Get(
        path: '/api/account/profile',
        operationId: 'accountProfileShow',
        summary: 'Get the account profile',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function show(Request $request)
    {
        return $this->response($this->payload($request->user()));
    }

    #[OA\Put(
        path: '/api/account/profile',
        operationId: 'accountProfileUpdate',
        summary: 'Update the account profile',
        security: [['sessionCookie' => []]],
        tags: ['Account'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProfileUpdateRequest')),
        responses: [
            new OA\Response(response: 200, ref: '#/components/responses/Success'),
            new OA\Response(response: 'default', ref: '#/components/responses/Error'),
        ],
    )]
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()->forceFill([
            'name' => $validated['name'] ?? null,
        ])->save();

        return $this->response($this->payload($request->user()->refresh()), '帳號資料已更新。');
    }

    private function payload($user): array
    {
        $user->loadMissing(['primaryEmail', 'emails', 'authProviders']);
        $pendingEmail = $user->emails
            ->where('is_verified', false)
            ->sortByDesc('created_at')
            ->first();

        return [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'display_name' => $user->displayName(),
            'email' => [
                'current' => $user->primaryEmail?->email,
                'is_verified' => (bool) $user->primaryEmail?->is_verified,
                'pending' => $pendingEmail?->email,
            ],
            'password' => [
                'is_set' => $user->password !== null,
            ],
            'providers' => [
                'line' => $user->authProviders->contains('provider', 'line'),
            ],
        ];
    }
}
